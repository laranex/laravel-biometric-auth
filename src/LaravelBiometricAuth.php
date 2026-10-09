<?php

declare(strict_types=1);

namespace Laranex\LaravelBiometricAuth;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Laranex\LaravelBiometricAuth\Exceptions\BiometricChallengeNotFoundException;
use Laranex\LaravelBiometricAuth\Exceptions\BiometricNotFoundException;
use Laranex\LaravelBiometricAuth\Exceptions\InvalidPublicKeyException;
use Laranex\LaravelBiometricAuth\Models\Biometric;
use Laranex\LaravelBiometricAuth\Support\PublicKey;

class LaravelBiometricAuth
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly CacheRepository $cache,
    ) {}

    /**
     * Load an active biometric and make sure it carries a challenge for the device to sign.
     *
     * The challenge is reused until it has been verified or has expired, so calling this
     * twice before the device answers does not invalidate the first challenge.
     *
     * @throws BiometricNotFoundException
     */
    public function getBiometric(string $biometricId): Biometric
    {
        $biometric = $this->getActiveBiometric($biometricId);

        if ($this->hasPendingChallenge($biometric)) {
            return $biometric;
        }

        // Only replace the challenge this request has seen, so concurrent requests agree on one challenge.
        $this->whereChallenge($biometric, $biometric->challenge)->update(['challenge' => $this->generateChallenge()]);

        $biometric->refresh();

        return $biometric;
    }

    /**
     * Verify a base64 encoded signature of the pending challenge with the biometric's public key.
     *
     * A successfully verified challenge is consumed, so a captured signature cannot be replayed:
     * the next call to getBiometric() issues a fresh challenge. Failed attempts keep the challenge
     * for a retry until `biometric-auth.challenge.max_attempts` is reached, then it is cleared too.
     * A challenge older than `biometric-auth.challenge.ttl` seconds is cleared without being checked.
     *
     * @throws BiometricNotFoundException
     * @throws BiometricChallengeNotFoundException
     * @throws InvalidPublicKeyException
     */
    public function verifyBiometric(string $biometricId, string $signature): bool
    {
        $biometric = $this->getActiveBiometric($biometricId);
        $challenge = $biometric->challenge;

        if ($challenge === null || $challenge === '') {
            throw new BiometricChallengeNotFoundException;
        }

        if ($this->challengeHasExpired($biometric)) {
            $this->clearChallenge($biometric, $challenge);

            throw new BiometricChallengeNotFoundException;
        }

        $decoded = base64_decode($signature, true);

        $verified = $decoded !== false
            && $decoded !== ''
            && $this->loadPublicKey($biometric->public_key)->verify($challenge, $decoded);

        $attemptsKey = $this->attemptsKey($biometric, $challenge);

        if ($verified) {
            $this->cache->forget($attemptsKey);

            // Consume the challenge atomically: when the same signature arrives twice at once,
            // only the request that actually clears the challenge is verified.
            return $this->clearChallenge($biometric, $challenge);
        }

        $this->recordFailedAttempt($biometric, $challenge, $attemptsKey);

        return false;
    }

    /**
     * Count a failed verification and clear the challenge once the configured limit is reached.
     */
    private function recordFailedAttempt(Biometric $biometric, string $challenge, string $attemptsKey): void
    {
        $maxAttempts = $this->positiveIntegerConfig('biometric-auth.challenge.max_attempts', 5);

        if ($maxAttempts === null) {
            return;
        }

        // Failed attempts are remembered for a day per challenge.
        $this->cache->add($attemptsKey, 0, 86400);
        $attempts = $this->cache->increment($attemptsKey);

        if (is_int($attempts) && $attempts < $maxAttempts) {
            return;
        }

        $this->clearChallenge($biometric, $challenge);
        $this->cache->forget($attemptsKey);
    }

    /**
     * Whether the biometric carries a challenge that has not expired yet.
     */
    private function hasPendingChallenge(Biometric $biometric): bool
    {
        return $biometric->challenge !== null
            && $biometric->challenge !== ''
            && ! $this->challengeHasExpired($biometric);
    }

    /**
     * Whether the pending challenge is older than the configured lifetime.
     *
     * A challenge is only ever written together with the row's updated_at timestamp, which
     * therefore records when it was issued.
     */
    private function challengeHasExpired(Biometric $biometric): bool
    {
        $ttl = $this->positiveIntegerConfig('biometric-auth.challenge.ttl', 300);

        if ($ttl === null) {
            return false;
        }

        return $biometric->updated_at === null || $biometric->updated_at->copy()->addSeconds($ttl)->isPast();
    }

    /**
     * Clear the given challenge if it is still the pending one; true when this call cleared it.
     */
    private function clearChallenge(Biometric $biometric, string $challenge): bool
    {
        return $this->whereChallenge($biometric, $challenge)->update(['challenge' => null]) === 1;
    }

    /**
     * A query for the active biometric while it still carries the given challenge.
     *
     * @return Builder<Biometric>
     */
    private function whereChallenge(Biometric $biometric, ?string $challenge): Builder
    {
        return Biometric::query()
            ->whereKey($biometric->getKey())
            ->where('revoked', false)
            ->when(
                $challenge === null,
                fn (Builder $query): Builder => $query->whereNull('challenge'),
                fn (Builder $query): Builder => $query->where('challenge', $challenge),
            );
    }

    /**
     * A positive integer option, or null when it is disabled (0, null or not numeric).
     */
    private function positiveIntegerConfig(string $key, int $default): ?int
    {
        $value = $this->config->get($key, $default);

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value > 0 ? (int) $value : null;
    }

    /**
     * The cache key counting failed attempts; it is tied to the challenge, so a new challenge starts at zero.
     */
    private function attemptsKey(Biometric $biometric, string $challenge): string
    {
        return 'biometric-auth:attempts:'.$biometric->id.':'.hash('sha256', $challenge);
    }

    /**
     * @throws BiometricNotFoundException
     */
    private function getActiveBiometric(string $biometricId): Biometric
    {
        // Ids are UUIDs: anything else cannot match and would fail on databases with a native uuid type.
        if (! Str::isUuid($biometricId)) {
            throw new BiometricNotFoundException;
        }

        $biometric = Biometric::query()->whereKey($biometricId)->where('revoked', false)->first();

        if (! $biometric instanceof Biometric) {
            throw new BiometricNotFoundException;
        }

        return $biometric;
    }

    /**
     * @throws InvalidPublicKeyException
     */
    private function loadPublicKey(string $publicKeyBase64): PublicKey
    {
        $hash = $this->config->get('biometric-auth.rsa.hash_algorithm', 'sha256');
        $padding = $this->config->get('biometric-auth.rsa.encryption_padding', 'pkcs1');

        return PublicKey::load(
            $publicKeyBase64,
            is_string($hash) ? $hash : 'sha256',
            is_int($padding) || is_string($padding) ? $padding : 'pkcs1',
        );
    }

    private function generateChallenge(): string
    {
        return bin2hex(random_bytes(32));
    }
}
