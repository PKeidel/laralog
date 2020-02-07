<?php

namespace PKeidel\Laralog;

use Illuminate\Support\ServiceProvider;
use PKeidel\laralog\src\Commands\LaralogInstallElasticsearch;

class LaralogServiceProvider extends ServiceProvider {
    /**
     * Bootstrap the application services.
     */
    public function boot() {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('laralog.php'),
            ], 'config');

            // Registering package commands.
            $this->commands([
                LaralogInstallElasticsearch::class
            ]);
        }
    }

    /**
     * Register the application services.
     */
    public function register() {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'laralog');
    }
}
