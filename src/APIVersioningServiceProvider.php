<?php

namespace FYousri\APIVersioning;

use FYousri\APIVersioning\Commands\IncrementVersion;
use FYousri\APIVersioning\Commands\SetVersion;
use Illuminate\Support\ServiceProvider;

class APIVersioningServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('api-versioning.php'),
            ], 'config');

            $this->loadMigrationsFrom(__DIR__.'/database/migrations');

            // Registering package commands.
            $this->commands([
                IncrementVersion::class,
                SetVersion::class
            ]);
        }

    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'api-versioning');

        // Register the main class to use with the facade
        $this->app->singleton('api-versioning', function () {
            return new APIVersioning;
        });
    }
}
