# Laravel Biometric Auth

[![Latest Version on Packagist](https://img.shields.io/packagist/v/laranex/laravel-biometric-auth.svg?style=flat-square)](https://packagist.org/packages/laranex/laravel-biometric-auth)
[![Tests](https://img.shields.io/github/actions/workflow/status/laranex/laravel-biometric-auth/tests.yml?branch=master&label=tests&style=flat-square)](https://github.com/laranex/laravel-biometric-auth/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/laranex/laravel-biometric-auth.svg?style=flat-square)](https://packagist.org/packages/laranex/laravel-biometric-auth)
[![License](https://img.shields.io/packagist/l/laranex/laravel-biometric-auth.svg?style=flat-square)](LICENSE.md)

Asymmetric biometric authentication for Laravel APIs that serve mobile apps. The device keeps its private key behind Face ID, Touch ID or the Android biometric prompt and registers only the public key; your API issues a random challenge, the device signs it, and the package verifies the signature with [phpseclib](https://phpseclib.com/docs/publickeys) (RSA, EC, Ed25519 and every other key type phpseclib loads). No biometric data ever leaves the device.

## Documentation

Full documentation lives at **[laranex.vercel.app/laravel-biometric-auth](https://laranex.vercel.app/laravel-biometric-auth)**.

## Requirements

- PHP 8.1 or higher
- Laravel 10, 11, 12 or 13
- phpseclib 3 (3.0.57+) or 4

## Installation

```bash
composer require laranex/laravel-biometric-auth
```

The `biometrics` table is created by the package's own migration, so `php artisan migrate` is all you need. Publish the migration and the config file only when you want to change them:

```bash
php artisan vendor:publish --tag="biometric-auth-migrations"
php artisan vendor:publish --tag="biometric-auth-config"
php artisan migrate
```

The config file holds the table name (`BIOMETRIC_AUTH_TABLE`) and the RSA padding/hash your apps sign with: `'pkcs1'` (default) or `'pss'`, with SHA-256 by default. Every other key type is detected automatically.

## Usage

```php
use Illuminate\Http\Request;
use Laranex\LaravelBiometricAuth\Facades\LaravelBiometricAuth;
use Laranex\LaravelBiometricAuth\Models\Biometric;
use Laranex\LaravelBiometricAuth\Traits\HasBiometrics;

class User extends Authenticatable
{
    use HasBiometrics;
}

// 1. Registration (authenticated): the device creates a key pair and sends its base64 encoded public key.
Route::post('/biometrics', function (Request $request) {
    $biometric = $request->user()->createBiometric($request->string('public_key'));

    return ['biometric_id' => $biometric->id]; // the device stores this next to its private key
})->middleware('auth:sanctum');

// 2. Challenge (guest): the device asks for something to sign.
Route::post('/biometrics/{id}/challenge', fn (string $id) => [
    'challenge' => LaravelBiometricAuth::getBiometric($id)->challenge,
]);

// 3. Verification (guest): the device signs the challenge after the biometric prompt.
Route::post('/biometrics/{id}/verify', function (Request $request, string $id) {
    if (! LaravelBiometricAuth::verifyBiometric($id, $request->string('signature'))) {
        abort(401);
    }

    $user = Biometric::query()->findOrFail($id)->instance; // the owner of the key

    return ['token' => $user->createToken('biometric')->plainTextToken];
});

// Revoke a device
$user->revokeBiometric($biometricId);
```

`getBiometric()` reuses the pending challenge until it is verified; a verified challenge is consumed so a captured signature cannot be replayed. A failed verification keeps the challenge for a retry until `biometric-auth.challenge.max_attempts` (default 5) failures, then clears it. Unknown or revoked biometrics throw `BiometricNotFoundException` (404), verifying without a challenge throws `BiometricChallengeNotFoundException` (422), and keys phpseclib cannot load throw `InvalidPublicKeyException` (422); in JSON requests they render as `{"message": "..."}` with that status.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Nay Thu Khant](https://github.com/NayThuKhant)
- [Pai Soe Htike](https://github.com/paisoedev)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
