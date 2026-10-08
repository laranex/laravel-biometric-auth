---
name: laravel-biometric-auth-development
description: >
  Add passwordless biometric login (Face ID, Touch ID, Android biometrics) to a Laravel API with laranex/laravel-biometric-auth: register device public keys, issue challenges and verify signatures.
license: MIT
metadata:
  author: Nay Thu Khant
---

# Laravel Biometric Auth

Use this skill when a Laravel API lets mobile or web clients sign in with a device-held key pair instead of a password.

## Primary Goal

- implement the register → challenge → verify flow with the package's API, never by hand-rolling signature checks

## Workflow

### 1. Install

- `composer require laranex/laravel-biometric-auth` then `php artisan migrate`; the package loads its own `biometrics` migration
- publish only to customise: `php artisan vendor:publish --tag="biometric-auth-config"` (table name, RSA padding/hash) or `--tag="biometric-auth-migrations"`; once published the package stops loading its own migration
- add `Laranex\LaravelBiometricAuth\Traits\HasBiometrics` to every authenticatable model that may register devices (User, Admin, ...)

### 2. Register a device (authenticated request)

- the client generates a key pair in secure hardware and sends the base64 encoded public key (PEM or DER)
- `$biometric = $request->user()->createBiometric($publicKeyBase64)`; return `$biometric->id` (a UUID) for the client to store next to its private key
- it throws `InvalidPublicKeyException` when phpseclib cannot load the key

### 3. Challenge and verify (guest requests)

- `LaravelBiometricAuth::getBiometric($id)->challenge` returns the pending challenge (64 hex chars), issuing one if none is pending; it is reused until verified
- the client signs the challenge string after the biometric prompt and sends the base64 signature
- `LaravelBiometricAuth::verifyBiometric($id, $signatureBase64)` returns `bool`; on `true` the challenge is consumed, then load the owner with `Biometric::query()->findOrFail($id)->instance` (calling `getBiometric()` again would issue a new challenge) and issue your session or token
- RSA signatures must use the configured padding/hash (`biometric-auth.rsa`, default PKCS1 v1.5 + SHA-256); EC and Ed25519 keys need no configuration

### 4. Revoke

- `$user->revokeBiometric($id)` marks the key revoked; revoked or unknown ids throw `BiometricNotFoundException` from every method

## Rules, References, and Templates

- facade: `Laranex\LaravelBiometricAuth\Facades\LaravelBiometricAuth`; model: `Laranex\LaravelBiometricAuth\Models\Biometric` (`public_key` is hidden from serialisation, `active()` scope, `instance` morph-to relation)
- exceptions live in `Laranex\LaravelBiometricAuth\Exceptions`: `BiometricNotFoundException`, `BiometricChallengeNotFoundException`, `InvalidPublicKeyException`

## Examples

- challenge endpoint: `Route::post('/biometrics/{id}/challenge', fn (string $id) => ['challenge' => LaravelBiometricAuth::getBiometric($id)->challenge]);`
- verify endpoint: `abort_unless(LaravelBiometricAuth::verifyBiometric($id, $request->string('signature')), 401);` then `Biometric::query()->findOrFail($id)->instance->createToken('biometric')`
- test: generate a key with `EC::createKey('secp256r1')` (`phpseclib3\Crypt\EC` on phpseclib 3, `phpseclib4\Crypt\EC` on phpseclib 4), register `base64_encode($key->getPublicKey()->toString('PKCS8'))`, sign the challenge with `base64_encode($key->sign($challenge))`

## Anti-patterns

- do not treat a known biometric id as proof of identity; only a `true` from `verifyBiometric()` is
- do not hand the challenge to the client over an unauthenticated channel and skip rate limiting; throttle the challenge and verify routes
- do not store or log private keys or signatures; the server only ever sees public keys
- do not catch `BiometricNotFoundException` to fall back to another biometric; ask the client to re-register
