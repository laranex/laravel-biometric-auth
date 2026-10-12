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
- `HasBiometrics` stores `authenticable_type` through `getMorphClass()` so morph maps are honored, and exposes a `biometrics()` morph-many relation. `Biometric` gained an `active()` scope.
- `LaravelBiometricAuth` now takes the config repository through its constructor; resolve it from the container or the facade instead of `new`-ing it.
- The exceptions extend a new `BiometricException` base class and type their constructor arguments (`string $message`, `?int $code`, `?Throwable $previous`).
- The exceptions default to an HTTP status code instead of `500`: `BiometricNotFoundException` 404, `BiometricChallengeNotFoundException` 422, `InvalidPublicKeyException` 422 (`getStatusCode()`). They are renderable: requests that expect JSON get `{"message": "..."}` with that status; other requests go to the application's exception handler.
- New `challenge.max_attempts` option (`BIOMETRIC_AUTH_CHALLENGE_MAX_ATTEMPTS`, default 5): after that many failed verifications the pending challenge is cleared and the client must request a new one. Attempts are counted in the default cache store; `0` or `null` disables the limit.
- `LaravelBiometricAuth` also takes the cache repository through its constructor.
- New `challenge.ttl` option (`BIOMETRIC_AUTH_CHALLENGE_TTL`, default 300 seconds): an expired challenge is replaced by the next `getBiometric()` call and refused by `verifyBiometric()` with `BiometricChallengeNotFoundException`. The issue time is stored in a new nullable `challenge_issued_at` column (added to the create migration, and by a new `add_challenge_issued_at_to_biometrics_table` migration for v3 tables); `0` or `null` disables expiry.
- Challenges are issued and consumed with conditional updates, so two concurrent verifications of the same signature cannot both succeed and concurrent `getBiometric()` calls agree on one challenge.
- `getBiometric()`, `verifyBiometric()` and `revokeBiometric()` throw `BiometricNotFoundException` for an id that is not a UUID instead of querying the database (which failed on PostgreSQL's native `uuid` column).
- `verifyBiometric()` decodes the signature as strict base64; an invalid or empty signature returns `false` and counts as a failed attempt.
- `createBiometric()` only accepts EC P-256, EC P-384, Ed25519 and 2048 to 4096-bit RSA keys, given as strict base64 of at most 8 KiB; DSA, other curves and other RSA sizes throw `InvalidPublicKeyException`, also when a stored key is verified.
- New `max_per_user` option (`BIOMETRIC_AUTH_MAX_PER_USER`, default 10): `createBiometric()` throws the new `TooManyBiometricsException` (422) once the model has that many active biometrics; `0` or `null` disables the limit.
- New `HasBiometrics::revokeAllBiometrics()` revokes every active biometric of the model and returns the count; call it on a password change or a suspected compromise.
- `Biometric` only mass assigns `public_key` (the owner, challenge and revoked flag are set by the package) and also hides `challenge`, `authenticable_id` and `authenticable_type` from serialization.
- Requires `illuminate/http` (installed with every Laravel application) for rendering the exceptions.
- Supports phpseclib 3 (3.0.57+) and phpseclib 4. phpseclib 4 moved to the `phpseclib4` namespace and renumbered the RSA signature constants, so `biometric-auth.rsa.encryption_padding` now takes a name, `'pkcs1'` (default) or `'pss'` (`'relaxed_pkcs1'` on phpseclib 3 only); a raw `RSA::SIGNATURE_*` integer from the installed phpseclib is still accepted. Unknown names throw `InvalidArgumentException`.
- Everything is strictly typed; the migration and the model read the table name from `biometric-auth.table`.
- The `Biometric` model and the migration read `biometric-auth.table` through the `Config` facade instead of the `config()` helper, which only `laravel/framework` defines, so the package runs on the `illuminate/*` components it requires.

### Upgrading
- Require PHP 8.1 or higher and Laravel 10 or higher (Laravel 9 is no longer supported), then `composer require laranex/laravel-biometric-auth:^4.0`.
- Run `php artisan migrate`: the package's `add_challenge_issued_at_to_biometrics_table` migration adds a nullable `challenge_issued_at` column to the existing table (pending challenges without it are replaced on the next `getBiometric()`). If you published the migration under v3, keep it; the package detects it and no longer loads its own copy of that file. If you publish the migrations again, `vendor:publish --tag=biometric-auth-migrations` adds only the new one.
- Devices registered with a key outside the accepted types (for example RSA 1024 or DSA) now fail with `InvalidPublicKeyException`; ask them to register again. Users with more than `max_per_user` (10) active biometrics keep them but cannot add more until they revoke some.
- If you published `config/biometric-auth.php`, replace `\phpseclib3\Crypt\RSA::SIGNATURE_PKCS1` with `'pkcs1'` (or `SIGNATURE_PSS` with `'pss'`). The old constant keeps working while you stay on phpseclib 3, but it is a fatal error on phpseclib 4 (the class no longer exists), and the integer values differ between the two majors.
- If you create `LaravelBiometricAuth` yourself, resolve it instead: `app(\Laranex\LaravelBiometricAuth\LaravelBiometricAuth::class)` or the `LaravelBiometricAuth` facade.
- Clients must request a new challenge after every successful verification and after `challenge.max_attempts` (5) failed ones (set `BIOMETRIC_AUTH_CHALLENGE_MAX_ATTEMPTS=0` to keep unlimited retries) and after a challenge expires (300 seconds by default; set `BIOMETRIC_AUTH_CHALLENGE_TTL=0` to keep challenges that never expire); calling `getBiometric()` again returns one. Wrap `revokeBiometric()` in a `BiometricNotFoundException` catch where you previously checked for a failure.
- If you relied on the exceptions' `500` code, or on uncaught exceptions returning a `500` response to API clients, they now carry and render 404/422; catch them to keep a custom response.
- If your application uses a morph map, new rows store the alias instead of the class name; existing rows with the class name keep resolving through the `instance` relation.
- If you mass assigned `Biometric` attributes other than `public_key`, or read `challenge` or `authenticable_*` from its JSON, set them explicitly (`forceFill()`) or read the attributes directly.
