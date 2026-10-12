<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
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
        ->and(Schema::hasColumns('biometrics', ['id', 'authenticable_id', 'authenticable_type', 'public_key', 'challenge', 'challenge_issued_at', 'revoked', 'created_at', 'updated_at']))->toBeTrue();
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

it('publishes the migrations with ordered timestamps and reuses them on the next publish', function () {
    $published = fn (): array => [
        ...glob(database_path('migrations/*_create_biometrics_table.php')) ?: [],
        ...glob(database_path('migrations/*_add_challenge_issued_at_to_biometrics_table.php')) ?: [],
    ];
    File::delete($published());

    $this->artisan('vendor:publish', ['--tag' => 'biometric-auth-migrations', '--no-interaction' => true])->assertExitCode(0);

    [$create, $upgrade] = $published();

    expect($published())->toHaveCount(2)
        ->and(strcmp(basename($create), basename($upgrade)))->toBeLessThan(0)
        ->and(basename($create))->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_create_biometrics_table\.php$/')
        ->and(basename($upgrade))->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_add_challenge_issued_at_to_biometrics_table\.php$/')
        ->and(File::get($create))->toBe(File::get(__DIR__.'/../../database/migrations/create_biometrics_table.php'))
        ->and(File::get($upgrade))->toBe(File::get(__DIR__.'/../../database/migrations/add_challenge_issued_at_to_biometrics_table.php'));

    $this->artisan('vendor:publish', ['--tag' => 'biometric-auth-migrations', '--no-interaction' => true])->assertExitCode(0);

    expect($published())->toBe([$create, $upgrade]);

    File::delete($published());
});

it('publishes the config and the migrations under the biometric-auth tag', function () {
    $paths = array_values(ServiceProvider::pathsToPublish(LaravelBiometricAuthServiceProvider::class, 'biometric-auth'));

    expect($paths)->toHaveCount(3)
        ->and($paths)->toContain(config_path('biometric-auth.php'))
        ->and(collect($paths)->contains(fn (string $target): bool => str_starts_with($target, database_path('migrations/')) && str_ends_with($target, '_create_biometrics_table.php')))->toBeTrue()
        ->and(collect($paths)->contains(fn (string $target): bool => str_starts_with($target, database_path('migrations/')) && str_ends_with($target, '_add_challenge_issued_at_to_biometrics_table.php')))->toBeTrue();
});

it('stops loading each migration once the application has published it', function () {
    $published = database_path('migrations/2020_01_01_000000_create_biometrics_table.php');
    File::ensureDirectoryExists(dirname($published));
    File::copy(__DIR__.'/../../database/migrations/create_biometrics_table.php', $published);

    $provider = new class(app()) extends LaravelBiometricAuthServiceProvider
    {
        /** @var list<string> */
        public array $loaded = [];

        protected function loadMigrationsFrom(mixed $paths): void
        {
            foreach ((array) $paths as $path) {
                $this->loaded[] = (string) realpath($path);
            }
        }
    };

    $create = realpath(__DIR__.'/../../database/migrations/create_biometrics_table.php');
    $upgrade = realpath(__DIR__.'/../../database/migrations/add_challenge_issued_at_to_biometrics_table.php');

    $provider->boot();

    expect($provider->loaded)->toBe([$upgrade]);

    File::delete($published);
    $provider->loaded = [];
    $provider->boot();

    expect($provider->loaded)->toBe([$create, $upgrade]);
});

it('adds the challenge_issued_at column to a table created by v3', function () {
    $upgrade = require __DIR__.'/../../database/migrations/add_challenge_issued_at_to_biometrics_table.php';

    Schema::drop('biometrics');
    Schema::create('biometrics', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('authenticable_id');
        $table->string('authenticable_type');
        $table->longText('public_key');
        $table->string('challenge')->nullable();
        $table->boolean('revoked')->default(false);
        $table->timestamps();
    });

    $upgrade->up();
    $upgrade->up();

    expect(Schema::hasColumn('biometrics', 'challenge_issued_at'))->toBeTrue();

    $upgrade->down();
    Schema::drop('biometrics');
    $upgrade->up();

    expect(Schema::hasTable('biometrics'))->toBeFalse();
});
