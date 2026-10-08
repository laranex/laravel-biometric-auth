# Changelog

All notable changes to `laravel-biometric-auth` will be documented in this file.

## v4.0.0 - Unreleased

### Changed
- Requires PHP 8.1+ and supports Laravel 10 through 13.
- Rebuilt on the official Laravel package skeleton (Pest, PHPStan, Pint, Testbench workbench, GitHub Actions matrix).
- Dropped `spatie/laravel-package-tools`; the service provider is a plain `Illuminate\Support\ServiceProvider` with the same `biometric-auth-config` and `biometric-auth-migrations` publish tags plus a `biometric-auth` tag that publishes both.
- The package migration is skipped once a `*_create_biometrics_table.php` migration has been published, so publishing and migrating no longer creates the table twice; republishing reuses the published file instead of adding a second copy.
- A challenge is single-use: `verifyBiometric()` clears it after a successful verification and the next `getBiometric()` issues a fresh one, so a captured signature cannot be replayed. Failed attempts keep the challenge so the device can retry.
- `revokeBiometric()` throws `BiometricNotFoundException` when the biometric does not exist, is already revoked or belongs to another model (it previously failed with a PHP error on `null`).
- `HasBiometrics` stores `authenticable_type` through `getMorphClass()` so morph maps are honoured, and exposes a `biometrics()` morph-many relation. `Biometric` gained an `active()` scope.
- `LaravelBiometricAuth` now takes the config repository through its constructor; resolve it from the container or the facade instead of `new`-ing it.
- The exceptions extend a new `BiometricException` base class and type their constructor arguments (`string $message`, `?int $code`, `?Throwable $previous`).
- The exceptions default to an HTTP status code instead of `500`: `BiometricNotFoundException` 404, `BiometricChallengeNotFoundException` 422, `InvalidPublicKeyException` 422 (`getStatusCode()`). They are renderable: requests that expect JSON get `{"message": "..."}` with that status; other requests go to the application's exception handler.
- New `challenge.max_attempts` option (`BIOMETRIC_AUTH_CHALLENGE_MAX_ATTEMPTS`, default 5): after that many failed verifications the pending challenge is cleared and the client must request a new one. Attempts are counted in the default cache store; `0` or `null` disables the limit.
- `LaravelBiometricAuth` also takes the cache repository through its constructor.
- Requires `illuminate/http` (installed with every Laravel application) for rendering the exceptions.
- Supports phpseclib 3 (3.0.57+) and phpseclib 4. phpseclib 4 moved to the `phpseclib4` namespace and renumbered the RSA signature constants, so `biometric-auth.rsa.encryption_padding` now takes a name, `'pkcs1'` (default) or `'pss'` (`'relaxed_pkcs1'` on phpseclib 3 only); a raw `RSA::SIGNATURE_*` integer from the installed phpseclib is still accepted. Unknown names throw `InvalidArgumentException`.
- Everything is strictly typed; the migration and the model read the table name from `biometric-auth.table`.

### Upgrading
- Require PHP 8.1 or higher and Laravel 10 or higher (Laravel 9 is no longer supported), then `composer require laranex/laravel-biometric-auth:^4.0`.
- No database changes: the `biometrics` table and its columns are unchanged. If you published the migration under v3, keep it; the package detects it and no longer loads its own copy.
- If you published `config/biometric-auth.php`, replace `\phpseclib3\Crypt\RSA::SIGNATURE_PKCS1` with `'pkcs1'` (or `SIGNATURE_PSS` with `'pss'`). The old constant keeps working while you stay on phpseclib 3, but it is a fatal error on phpseclib 4 (the class no longer exists), and the integer values differ between the two majors.
- If you create `LaravelBiometricAuth` yourself, resolve it instead: `app(\Laranex\LaravelBiometricAuth\LaravelBiometricAuth::class)` or the `LaravelBiometricAuth` facade.
- Clients must request a new challenge after every successful verification and after `challenge.max_attempts` (5) failed ones (set `BIOMETRIC_AUTH_CHALLENGE_MAX_ATTEMPTS=0` to keep unlimited retries); calling `getBiometric()` again returns one. Wrap `revokeBiometric()` in a `BiometricNotFoundException` catch where you previously checked for a failure.
- If you relied on the exceptions' `500` code, or on uncaught exceptions returning a `500` response to API clients, they now carry and render 404/422; catch them to keep a custom response.
- If your application uses a morph map, new rows store the alias instead of the class name; existing rows with the class name keep resolving through the `instance` relation.
