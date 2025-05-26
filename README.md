# A laravel package to provide asymmetric biometric authentication

[![Latest Version on Packagist](https://img.shields.io/packagist/v/laranex/laravel-biometric-auth.svg?style=flat-square)](https://packagist.org/packages/laranex/laravel-biometric-auth)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/laranex/laravel-biometric-auth/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/laranex/laravel-biometric-auth/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/laranex/laravel-biometric-auth/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/laranex/laravel-biometric-auth/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/laranex/laravel-biometric-auth.svg?style=flat-square)](https://packagist.org/packages/laranex/laravel-biometric-auth)

![Create Biometric](./docs/CreateBiometric.png)
![Verify Biometric](./docs/CreateBiometric.png)


## Supported Public Keys
https://phpseclib.com/docs/publickeys

## Installation

You can install the package via composer:

```bash
composer require laranex/laravel-biometric-auth
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="biometric-auth-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="biometric-auth-config"
```

This is the contents of the published config file:

```php
<?php

return [
    'table' => env('BIOMETRIC_AUTH_TABLE', 'biometrics'),

    // You will need to be explicit about the encryption padding and hash algorithm when working with RSA keys.
    // For the rest of the algorithms, the package will automatically detect with the help of phpseclib.
    'rsa' => [
        'encryption_padding' => \phpseclib3\Crypt\RSA::SIGNATURE_PKCS1,
        'hash_algorithm' => 'sha256',
    ],
];

```

## Usage

```php
// Use Laranex\LaravelBiometricAuth\Traits\HasBiometrics in your Authenticable Model such as User, Admin
class User extends Authenticatable {
    use Laranex\LaravelBiometricAuth\Traits\HasBiometrics;
}

// Register a new biometric
$user->createBiometric("Base 64 encoded public key");

// Create a challenge for biometric authentication
$biometric = Laranex\LaravelBiometricAuth\Facades\LaravelBiometricAuth::getBiometric("UUID of a biometric");

// Verify the signature
Laranex\LaravelBiometricAuth\Facades\LaravelBiometricAuth::verifyBiometric("UUID of a biometric", "Signature");

// Get the user of verified biometric key
$user = Biometric::find("UUID of a biometric")->instance;

// Revoke a biometric
$user->revokeBiometric("UUID of a biometric");
```


## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Nay Thu Khant](https://github.com/naythukhant)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
