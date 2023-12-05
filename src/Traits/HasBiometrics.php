<?php

namespace Laranex\LaravelBiometricAuth\Traits;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laranex\LaravelBiometricAuth\Exceptions\BiometricChallengeNotFoundException;
use Laranex\LaravelBiometricAuth\Exceptions\BiometricNotFoundException;
use Laranex\LaravelBiometricAuth\Exceptions\InvalidPEMFormatPublicKeyException;
use Laranex\LaravelBiometricAuth\Models\Biometric;
use Throwable;

trait HasBiometrics
{
    /**
     * @throws Throwable
     */
    public function createBiometric(string $publicKey): Biometric
    {
        $pemPattern = '/^-----BEGIN [A-Z ]+-----\n([A-Za-z0-9+\/=\n]+)\n-----END [A-Z ]+-----$/m';
        throw_if(preg_match($pemPattern, $publicKey) !== 1, new InvalidPEMFormatPublicKeyException());

        return Biometric::create([
            'authenticable_id' => $this->id,
            'authenticable_type' => get_class($this),
            'public_key' => $publicKey,
        ]);
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function getBiometric(string $biometricId): Model
    {
        $biometric = $this->getActiveBiometric($biometricId);

        if (!$biometric->challenge) $biometric->update(['challenge' => bin2hex(random_bytes(32))]);

        return $biometric;
    }

    /**
     * @throws Throwable
     */
    public function verifyBiometric(string $biometricId, string $signature): bool
    {
        $biometric = $this->getActiveBiometric($biometricId);

        throw_if(!$biometric->challenge, new BiometricChallengeNotFoundException());

        return openssl_verify($biometric->challenge, base64_decode($signature), $biometric->public_key, config('biometric-auth.signature_algorithm'));
    }

    /**
     * @throws Throwable
     */
    public function revokeBiometric(string $biometricId): bool
    {
        $biometric = $this->getActiveBiometric($biometricId);

        return $biometric->update(["revoked" => true]);
    }

    public function biometrics(): HasMany
    {
        return $this->hasMany(Biometric::class, 'authenticable_id');
    }

    /**
     * @throws Throwable
     */
    private function getActiveBiometric(string $biometricId): Model {
        $biometric = $this->biometrics()->where(['id' => $biometricId, 'revoked' => false])->first();

        throw_if(!$biometric, new BiometricNotFoundException());

        return $biometric;
    }
}
