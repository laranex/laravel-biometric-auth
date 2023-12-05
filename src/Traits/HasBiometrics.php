<?php

namespace Laranex\LaravelBiometricAuth\Traits;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laranex\LaravelBiometricAuth\Exceptions\BiometricChallengeNotFoundException;
use Laranex\LaravelBiometricAuth\Exceptions\BiometricNotFoundException;
use Laranex\LaravelBiometricAuth\Models\Biometric;

trait HasBiometrics
{
    public function createBiometric(string $publicKey): Biometric
    {
        return Biometric::create([
            'authenticable_id' => $this->id,
            'authenticable_type' => get_class($this),
            'public_key' => $publicKey,
        ]);
    }

    /**
     * @throws Exception
     */
    public function getBiometric(string $biometricId): Model
    {
        $biometric = $this->biometrics()->where('id', $biometricId)->first();

        if (! $biometric) {
            throw new BiometricNotFoundException();
        }

        if (! $biometric->challenge) {
            $biometric->update(['challenge' => bin2hex(random_bytes(32))]);
        }

        return $biometric;
    }

    /**
     * @throws BiometricNotFoundException
     * @throws BiometricChallengeNotFoundException
     */
    public function verifyBiometric(string $biometricId, string $signature): bool
    {
        $biometric = $this->biometrics()->where('id', $biometricId)->first();

        if (! $biometric) {
            throw new BiometricNotFoundException();
        }

        if (! $biometric->challenge) {
            throw new BiometricChallengeNotFoundException();
        }

        return openssl_verify($biometric->challenge, base64_decode($signature), $biometric->public_key, config('biometric-auth.signature_algorithm'));
    }

    public function biometrics(): HasMany
    {
        return $this->hasMany(Biometric::class, 'authenticable_id');
    }
}
