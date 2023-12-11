<?php

namespace Laranex\LaravelBiometricAuth\Traits;

use Laranex\LaravelBiometricAuth\Models\Biometric;
use Throwable;

trait HasBiometrics
{
    /**
     * @throws Throwable
     */
    public function createBiometric(string $publicKey): Biometric
    {
        return Biometric::create([
            'authenticable_id' => $this->id,
            'authenticable_type' => get_class($this),
            'public_key' => $publicKey,
        ]);
    }

    /**
     * @throws Throwable
     */
    public function revokeBiometric(string $biometricId): bool
    {
        $biometric = Biometric::where(['id' => $biometricId, 'authenticable_id' => $this->id, 'revoked' => false])->first();

        return $biometric->update(['revoked' => true]);
    }
}
