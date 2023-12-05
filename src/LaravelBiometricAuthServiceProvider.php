<?php

namespace Laranex\LaravelBiometricAuth;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelBiometricAuthServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-biometric-auth')
            ->hasConfigFile()
            ->hasMigration('create_biometrics_table')
            ->runsMigrations();
    }
}
