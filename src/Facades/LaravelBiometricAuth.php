<?php

namespace Laranex\LaravelBiometricAuth\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Laranex\LaravelBiometricAuth\LaravelBiometricAuth
 */
class LaravelBiometricAuth extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Laranex\LaravelBiometricAuth\LaravelBiometricAuth::class;
    }
}
