<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laranex\LaravelBiometricAuth\Facades\LaravelBiometricAuth;
use Laranex\LaravelBiometricAuth\LaravelBiometricAuth as BiometricAuth;
use Laranex\LaravelBiometricAuth\LaravelBiometricAuthServiceProvider;
use Laranex\LaravelBiometricAuth\Models\Biometric;

it('merges the default configuration', function () {
    expect(config('biometric-auth.table'))->toBe('biometrics')
        ->and(config('biometric-auth.rsa.encryption_padding'))->toBe('pkcs1')
        ->and(config('biometric-auth.rsa.hash_algorithm'))->toBe('sha256')
        ->and(config('biometric-auth.challenge.ttl'))->toBe(300)
        ->and(config('biometric-auth.challenge.max_attempts'))->toBe(5);
});

it('binds the manager as a singleton behind the facade', function () {
    expect(app(BiometricAuth::class))->toBeInstanceOf(BiometricAuth::class)
        ->toBe(app(BiometricAuth::class))
        ->toBe(LaravelBiometricAuth::getFacadeRoot());
});

it('runs the package migration', function () {
    expect(Schema::hasTable('biometrics'))->toBeTrue()
        ->and(Schema::hasColumns('biometrics', ['id', 'authenticable_id', 'authenticable_type', 'public_key', 'challenge', 'revoked', 'created_at', 'updated_at']))->toBeTrue();
});

it('reads the table name from the configuration', function () {
    expect((new Biometric)->getTable())->toBe('biometrics');

    config()->set('biometric-auth.table', 'device_keys');

    expect((new Biometric)->getTable())->toBe('device_keys');
});

it('publishes the configuration file', function () {
    $paths = ServiceProvider::pathsToPublish(LaravelBiometricAuthServiceProvider::class, 'biometric-auth-config');

    expect($paths)->toHaveCount(1)
        ->and(realpath((string) array_key_first($paths)))->toBe(realpath(__DIR__.'/../../config/biometric-auth.php'))
        ->and(array_values($paths))->toBe([config_path('biometric-auth.php')]);
});

it('publishes the migration with a timestamp and reuses it on the next publish', function () {
    $pattern = database_path('migrations/*_create_biometrics_table.php');
    File::delete(glob($pattern) ?: []);

    $this->artisan('vendor:publish', ['--tag' => 'biometric-auth-migrations', '--no-interaction' => true])->assertExitCode(0);

    $published = glob($pattern) ?: [];

    expect($published)->toHaveCount(1)
        ->and(basename($published[0]))->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_create_biometrics_table\.php$/')
        ->and(File::get($published[0]))->toBe(File::get(__DIR__.'/../../database/migrations/create_biometrics_table.php'));

    $this->artisan('vendor:publish', ['--tag' => 'biometric-auth-migrations', '--no-interaction' => true])->assertExitCode(0);

    expect(glob($pattern) ?: [])->toHaveCount(1);

    File::delete($published);
});

it('publishes the config and the migration under the biometric-auth tag', function () {
    $paths = ServiceProvider::pathsToPublish(LaravelBiometricAuthServiceProvider::class, 'biometric-auth');

    expect(array_values($paths))->toHaveCount(2)
        ->and($paths)->toContain(config_path('biometric-auth.php'))
        ->and(collect($paths)->contains(fn (string $target): bool => str_starts_with($target, database_path('migrations/')) && str_ends_with($target, '_create_biometrics_table.php')))->toBeTrue();
});

it('stops loading its own migration once the application has published one', function () {
    $published = database_path('migrations/2020_01_01_000000_create_biometrics_table.php');
    File::ensureDirectoryExists(dirname($published));
    File::copy(__DIR__.'/../../database/migrations/create_biometrics_table.php', $published);

    $provider = new LaravelBiometricAuthServiceProvider(app());
    $before = app('migrator')->paths();
    $provider->boot();

    expect(app('migrator')->paths())->toBe($before);

    File::delete($published);
    $provider->boot();

    expect(array_map(fn (string $path): string|false => realpath($path), app('migrator')->paths()))
        ->toContain(realpath(__DIR__.'/../../database/migrations'));
});
