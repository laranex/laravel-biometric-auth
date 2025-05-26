<?php

namespace Laranex\LaravelBiometricAuth;

use Exception;
use Laranex\LaravelBiometricAuth\Exceptions\BiometricChallengeNotFoundException;
use Laranex\LaravelBiometricAuth\Exceptions\BiometricNotFoundException;
use Laranex\LaravelBiometricAuth\Exceptions\InvalidPublicKeyException;
use Laranex\LaravelBiometricAuth\Models\Biometric;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use phpseclib3\Exception\NoKeyLoadedException;
use Throwable;

class LaravelBiometricAuth
{
    /**
     * @throws Exception
     * @throws Throwable
     */
    public function getBiometric(string $biometricId): Biometric
    {
        $biometric = $this->getActiveBiometric($biometricId);

        if (! $biometric->challenge) {
            $biometric->update(['challenge' => bin2hex(random_bytes(32))]);
        }

        return $biometric;
    }

    /**
     * @throws Throwable
     */
    public function verifyBiometric(string $biometricId, string $signature): bool
    {
        $biometric = $this->getActiveBiometric($biometricId);

        throw_if(! $biometric->challenge, new BiometricChallengeNotFoundException);

        try {
            $publicKey = PublicKeyLoader::load(base64_decode($biometric->public_key));
        } catch (NoKeyLoadedException $exception) {
            throw new InvalidPublicKeyException;
        }

        if ($publicKey instanceof RSA\PublicKey) {
            $publicKey = $publicKey->withHash(config('biometric-auth.rsa.hash_algorithm'))->withPadding(config('biometric-auth.rsa.encryption_padding'));
        }

        return $publicKey->verify($biometric->challenge, base64_decode($signature));
    }

    /**
     * @throws Throwable
     */
    private function getActiveBiometric(string $biometricId): Biometric
    {
        $biometric = Biometric::where(['id' => $biometricId, 'revoked' => false])->first();

        throw_if(! $biometric, new BiometricNotFoundException);

        return $biometric;
    }
}
