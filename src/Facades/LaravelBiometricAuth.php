<?php

declare(strict_types=1);

namespace Laranex\LaravelBiometricAuth\Facades;

use Illuminate\Support\Facades\Facade;
use Laranex\LaravelBiometricAuth\LaravelBiometricAuth as BiometricAuth;

/**
 * @method static \Laranex\LaravelBiometricAuth\Models\Biometric getBiometric(string $biometricId)
 * @method static bool verifyBiometric(string $biometricId, string $signature)
 *
 * @see BiometricAuth
 */
class LaravelBiometricAuth extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BiometricAuth::class;
    }
}
