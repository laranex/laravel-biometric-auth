<?php

namespace Laranex\LaravelBiometricAuth;

use Exception;
use Laranex\LaravelBiometricAuth\Exceptions\BiometricChallengeNotFoundException;
use Laranex\LaravelBiometricAuth\Exceptions\BiometricNotFoundException;
use Laranex\LaravelBiometricAuth\Models\Biometric;
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

        throw_if(! $biometric->challenge, new BiometricChallengeNotFoundException());

        return openssl_verify($biometric->challenge, base64_decode($signature), $biometric->public_key, config('biometric-auth.signature_algorithm'));
    }

    /**
     * @throws Throwable
     */
    private function getActiveBiometric(string $biometricId): Biometric
    {
        $biometric = Biometric::where(['id' => $biometricId, 'revoked' => false])->first();

        throw_if(! $biometric, new BiometricNotFoundException());

        return $biometric;
    }
}
