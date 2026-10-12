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
        foreach ($this->migrations() as $migration) {
            if ($this->publishedMigration($migration) === null) {
                $this->loadMigrationsFrom(__DIR__."/../database/migrations/{$migration}.php");
            }
        }

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/biometric-auth.php' => $this->app->configPath('biometric-auth.php'),
        ], ['biometric-auth', 'biometric-auth-config']);

        $migrations = [];
        $now = time();

        // One second apart, so the published files keep the order of migrations().
        foreach ($this->migrations() as $offset => $migration) {
            $migrations[__DIR__."/../database/migrations/{$migration}.php"] = $this->publishedMigration($migration)
                ?? $this->app->databasePath('migrations/'.date('Y_m_d_His', $now + $offset)."_{$migration}.php");
        }

        $this->publishes($migrations, ['biometric-auth', 'biometric-auth-migrations']);
    }

    /**
     * The package migrations, in the order they run.
     *
     * @return list<string>
     */
    private function migrations(): array
    {
        return ['create_biometrics_table', 'add_challenge_issued_at_to_biometrics_table'];
    }

    /**
     * The path of the given migration if the application has already published it.
     *
     * When it exists the package migration is not loaded a second time, so
     * "vendor:publish" followed by "migrate" never runs it twice.
     */
    private function publishedMigration(string $migration): ?string
    {
        $published = glob($this->app->databasePath("migrations/*_{$migration}.php"));

        return is_array($published) && $published !== [] ? $published[0] : null;
    }
}
