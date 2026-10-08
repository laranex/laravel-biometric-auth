<?php

declare(strict_types=1);

namespace Laranex\LaravelBiometricAuth;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

class LaravelBiometricAuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/biometric-auth.php', 'biometric-auth');

        $this->app->singleton(LaravelBiometricAuth::class, fn (Container $app): LaravelBiometricAuth => new LaravelBiometricAuth(
            $app->make(ConfigRepository::class),
            $app->make(CacheRepository::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->publishedMigration() === null) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/biometric-auth.php' => $this->app->configPath('biometric-auth.php'),
        ], ['biometric-auth', 'biometric-auth-config']);

        $this->publishes([
            __DIR__.'/../database/migrations/create_biometrics_table.php' => $this->publishedMigration()
                ?? $this->app->databasePath('migrations/'.date('Y_m_d_His').'_create_biometrics_table.php'),
        ], ['biometric-auth', 'biometric-auth-migrations']);
    }

    /**
     * The path of the migration the application has already published, if any.
     *
     * When it exists the package migration is not loaded a second time, so
     * "vendor:publish" followed by "migrate" never creates the table twice.
     */
    private function publishedMigration(): ?string
    {
        $published = glob($this->app->databasePath('migrations/*_create_biometrics_table.php'));

        return is_array($published) && $published !== [] ? $published[0] : null;
    }
}
