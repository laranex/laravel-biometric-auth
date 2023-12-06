<?php

namespace Laranex\LaravelBiometricAuth\Traits;

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
     * @throws Throwable
     */
    public function revokeBiometric(string $biometricId): bool
    {
        $biometric = Biometric::where(['id' => $biometricId, 'authenticable_id' => $this->id, 'revoked' => false])->first();

        return $biometric->update(['revoked' => true]);
    }
}
