---
name: laravel-biometric-auth
description: >
  Add passwordless biometric login (Face ID, Touch ID, Android biometrics) to a Laravel API with laranex/laravel-biometric-auth: register device public keys, issue challenges and verify signed challenges.
license: MIT
metadata:
  author: Nay Thu Khant
---

# Laravel Biometric Auth

## When to use

- A Laravel API lets mobile or web clients sign in with a key pair held in the device's secure hardware (behind Face ID, Touch ID or the Android biometric prompt) instead of a password.
- Use the package's register, challenge and verify API; never hand-roll signature checks.
- The server only ever stores public keys. No biometric data and no private key leaves the device.

## Install

```bash
composer require laranex/laravel-biometric-auth
php artisan migrate
```

The package loads its own `biometrics` migration. Add the trait to every authenticatable model that may register devices:

```php
use Laranex\LaravelBiometricAuth\Traits\HasBiometrics;

class User extends Authenticatable
{
    use HasBiometrics;
}
```

## Configure

Publish only when you need to change something:

```bash
php artisan vendor:publish --tag="biometric-auth-config"      # config/biometric-auth.php
php artisan vendor:publish --tag="biometric-auth-migrations"  # once published, the package stops loading its own migration
```

`config/biometric-auth.php`:

- `table` (`BIOMETRIC_AUTH_TABLE`, default `biometrics`): the table that stores the public keys and pending challenges.
- `challenge.max_attempts` (`BIOMETRIC_AUTH_CHALLENGE_MAX_ATTEMPTS`, default `5`): failed verifications allowed per challenge before it is cleared; `0` or `null` disables the limit. Attempts are counted in the default cache store.
- `rsa.encryption_padding` (`pkcs1` by default, or `pss`) and `rsa.hash_algorithm` (`sha256` by default): must match how the app signs with an RSA key. EC and Ed25519 keys need no configuration.

## Use

### Register a device (authenticated request)

The client generates a key pair and sends the base64 encoded public key (PEM or DER). Return the biometric id (a UUID); the client stores it next to its private key.

```php
Route::post('/biometrics', function (Request $request) {
    $biometric = $request->user()->createBiometric($request->string('public_key'));

    return ['biometric_id' => $biometric->id];
})->middleware('auth:sanctum');
```

`createBiometric()` throws `InvalidPublicKeyException` when phpseclib cannot load the key.

### Issue a challenge (guest request)

```php
use Laranex\LaravelBiometricAuth\Facades\LaravelBiometricAuth;

Route::post('/biometrics/{id}/challenge', fn (string $id) => [
    'challenge' => LaravelBiometricAuth::getBiometric($id)->challenge,
])->middleware('throttle:10,1');
```

`getBiometric()` issues a random challenge (64 hex characters) when none is pending and reuses it until it is verified.

### Verify the signed challenge (guest request)

The client signs the challenge string after the biometric prompt and sends the base64 signature.

```php
use Laranex\LaravelBiometricAuth\Models\Biometric;

Route::post('/biometrics/{id}/verify', function (Request $request, string $id) {
    abort_unless(LaravelBiometricAuth::verifyBiometric($id, $request->string('signature')), 401);

    $user = Biometric::query()->findOrFail($id)->instance;

    return ['token' => $user->createToken('biometric')->plainTextToken];
})->middleware('throttle:10,1');
```

- `verifyBiometric()` returns `true` and consumes the challenge, so a captured signature cannot be replayed.
- A `false` keeps the challenge for a retry until `challenge.max_attempts` failures, then clears it.
- Load the owner with `Biometric::query()->findOrFail($id)->instance`. Calling `getBiometric()` again after a successful verification would issue a new challenge.

### Revoke a device

```php
$request->user()->revokeBiometric($biometricId);
```

`$user->biometrics()` lists every registered key, revoked ones included; `Biometric::query()->active()` returns only active keys. `public_key` is hidden from serialization.

### Errors

All exceptions live in `Laranex\LaravelBiometricAuth\Exceptions` and extend `BiometricException`. In JSON requests they render as `{"message": "..."}` with their status, so API routes need no try/catch. `getStatusCode()` returns the status.

- `BiometricNotFoundException` (404): unknown or revoked biometric id, from every method.
- `BiometricChallengeNotFoundException` (422): verifying with no pending challenge; request a new one.
- `InvalidPublicKeyException` (422): the public key cannot be loaded.

## Test your app

Generate a real key with phpseclib (`phpseclib3\Crypt\EC` on phpseclib 3, `phpseclib4\Crypt\EC` on phpseclib 4), register it and sign the challenge the way a device would:

```php
use phpseclib3\Crypt\EC;

it('logs in with a biometric and rejects a replayed signature', function () {
    $key = EC::createKey('secp256r1');
    $biometric = User::factory()->create()
        ->createBiometric(base64_encode($key->getPublicKey()->toString('PKCS8')));

    $challenge = $this->postJson("/biometrics/{$biometric->id}/challenge")->json('challenge');
    $signature = base64_encode($key->sign($challenge));

    $this->postJson("/biometrics/{$biometric->id}/verify", ['signature' => $signature])->assertOk();

    // The challenge was consumed, so the same signature now hits BiometricChallengeNotFoundException.
    $this->postJson("/biometrics/{$biometric->id}/verify", ['signature' => $signature])->assertStatus(422);
});
```

## Avoid

- Treating a known biometric id as proof of identity: only a `true` from `verifyBiometric()` is.
- Leaving the challenge and verify routes unthrottled.
- Storing or logging private keys or signatures; the server only needs the public key.
- Catching `BiometricNotFoundException` to fall back to another biometric; ask the client to register again.
- Signing RSA challenges with a padding or hash other than the configured `rsa` settings.
