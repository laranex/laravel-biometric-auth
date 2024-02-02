<?php

namespace Laranex\LaravelBiometricAuth;

use Illuminate\Support\ServiceProvider;

class LaravelBiometricAuthServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/biometric-auth.php', 'biometric-auth'
        );
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPublishing();
    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    private function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'biometric-auth-migrations');

            $this->publishes([
                __DIR__.'/../config/biometric-auth.php' => config_path('biometric-auth.php'),
            ], 'biometric-auth-config');
        }
    }
}
