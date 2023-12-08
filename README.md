# A laravel package to provide asymmetric biometric authentication

[![Latest Version on Packagist](https://img.shields.io/packagist/v/laranex/laravel-biometric-auth.svg?style=flat-square)](https://packagist.org/packages/laranex/laravel-biometric-auth)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/laranex/laravel-biometric-auth/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/laranex/laravel-biometric-auth/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/laranex/laravel-biometric-auth/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/laranex/laravel-biometric-auth/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/laranex/laravel-biometric-auth.svg?style=flat-square)](https://packagist.org/packages/laranex/laravel-biometric-auth)

This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.


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
return [
    'table' => env('BIOMETRIC_AUTH_TABLE', 'biometrics'),

    'signature_algorithm' => env('BIOMETRIC_AUTH_ALGORITHM', OPENSSL_ALGO_SHA256),
];
```

## Usage

```php
// Use Laranex\LaravelBiometricAuth\Traits\HasBiometris in your Authenticable Model such as User, Admin
class User extends Authenticatable {
    use Laranex\LaravelBiometricAuth\Traits\HasBiometricAuth;
}

// Register a new biometric
$user->createBiometric("Public Key in PEM format");

// Create a challenge for biometric authentication
Laranex\LaravelBiometricAuth\Facades\LaravelBiometricAuth::getBiometric("UUID of a biometric");

// Verify the signature
Laranex\LaravelBiometricAuth\Facades\LaravelBiometricAuth::verifyBiometric("UUID of a biometric", "Signature");

// Revoke a biometric
$user->revokeBiometric("UUID of a biometric");
```

## Testing

```bash
composer test
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
