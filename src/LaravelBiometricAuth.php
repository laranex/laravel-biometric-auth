<?php

declare(strict_types=1);

namespace Laranex\LaravelBiometricAuth;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
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
     * The challenge is reused until it has been verified, so calling this twice before
     * the device answers does not invalidate the first challenge.
     *
     * @throws BiometricNotFoundException
     */
    public function getBiometric(string $biometricId): Biometric
    {
        $biometric = $this->getActiveBiometric($biometricId);

        if ($biometric->challenge === null || $biometric->challenge === '') {
            $biometric->update(['challenge' => $this->generateChallenge()]);
        }

        return $biometric;
    }

    /**
     * Verify a base64 encoded signature of the pending challenge with the biometric's public key.
     *
     * A successfully verified challenge is consumed, so a captured signature cannot be replayed:
     * the next call to getBiometric() issues a fresh challenge. Failed attempts keep the challenge
     * for a retry until `biometric-auth.challenge.max_attempts` is reached, then it is cleared too.
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

        $verified = $this->loadPublicKey($biometric->public_key)->verify($challenge, base64_decode($signature));

        $attemptsKey = $this->attemptsKey($biometric, $challenge);

        if ($verified) {
            $biometric->update(['challenge' => null]);
            $this->cache->forget($attemptsKey);

            return true;
        }

        $this->recordFailedAttempt($biometric, $attemptsKey);

        return false;
    }

    /**
     * Count a failed verification and clear the challenge once the configured limit is reached.
     */
    private function recordFailedAttempt(Biometric $biometric, string $attemptsKey): void
    {
        $maxAttempts = $this->maxAttempts();

        if ($maxAttempts === null) {
            return;
        }

        // Failed attempts are remembered for a day per challenge.
        $this->cache->add($attemptsKey, 0, 86400);
        $attempts = $this->cache->increment($attemptsKey);

        if (is_int($attempts) && $attempts < $maxAttempts) {
            return;
        }

        $biometric->update(['challenge' => null]);
        $this->cache->forget($attemptsKey);
    }

    /**
     * The configured failed attempt limit per challenge, or null when the limit is disabled.
     */
    private function maxAttempts(): ?int
    {
        $maxAttempts = $this->config->get('biometric-auth.challenge.max_attempts', 5);

        if (! is_numeric($maxAttempts)) {
            return null;
        }

        return (int) $maxAttempts > 0 ? (int) $maxAttempts : null;
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
