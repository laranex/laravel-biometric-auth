<?php

namespace Laranex\LaravelBiometricAuth\Traits;

use Laranex\LaravelBiometricAuth\Exceptions\InvalidPublicKeyException;
use Laranex\LaravelBiometricAuth\Models\Biometric;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Exception\NoKeyLoadedException;
use Throwable;

trait HasBiometrics
{
    /**
     * @throws Throwable
     */
    public function createBiometric(string $publicKeyBase64): Biometric
    {
        try {
            PublicKeyLoader::loadPublicKey(base64_decode($publicKeyBase64));
        } catch (NoKeyLoadedException $exception) {
            throw new InvalidPublicKeyException;
        }

        return Biometric::create([
            'authenticable_id' => $this->id,
            'authenticable_type' => get_class($this),
            'public_key' => $publicKeyBase64,
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
