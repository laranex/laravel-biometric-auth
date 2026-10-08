<?php

declare(strict_types=1);

namespace Laranex\LaravelBiometricAuth\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laranex\LaravelBiometricAuth\LaravelBiometricAuthServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            LaravelBiometricAuthServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Each (parallel) test process publishes into its own database path, so the
        // publish tests never race with the provider's published-migration check.
        $app->useDatabasePath(sys_get_temp_dir().'/laravel-biometric-auth-'.getmypid().'/database');

        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../workbench/database/migrations');
    }
}
