<?php

namespace Jdclzn\PayrollEngine;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class PayrollEngineServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public const CONFIG_KEY = 'payroll-engine';

    public const CONFIG_TAG = 'payroll-engine-config';

    public const SERVICE_KEY = 'payroll-engine';

    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        /*
         * Optional methods to load your package assets
         */
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'payroll-engine');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'payroll-engine');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => $this->app->configPath(self::CONFIG_KEY.'.php'),
            ], self::CONFIG_TAG);

            $this->publishes([
                __DIR__.'/../config/config.php' => $this->app->configPath(self::CONFIG_KEY.'.php'),
            ], 'config');

            // Publishing the views.
            /*$this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/payroll-engine'),
            ], 'views');*/

            // Publishing assets.
            /*$this->publishes([
                __DIR__.'/../resources/assets' => public_path('vendor/payroll-engine'),
            ], 'assets');*/

            // Publishing the translation files.
            /*$this->publishes([
                __DIR__.'/../resources/lang' => resource_path('lang/vendor/payroll-engine'),
            ], 'lang');*/

            // Registering package commands.
            // $this->commands([]);
        }
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        // Recursively merge package defaults so host apps can override only the
        // specific nested keys they need without losing the rest of the config tree.
        $this->replaceConfigRecursivelyFrom(__DIR__.'/../config/config.php', self::CONFIG_KEY);

        // Register the main class and facade service alias through Laravel's container.
        $this->app->singleton(PayrollEngine::class, function () {
            return new PayrollEngine(
                (array) $this->app['config']->get(self::CONFIG_KEY, []),
                fn (string $class): object => $this->app->make($class),
            );
        });

        $this->app->alias(PayrollEngine::class, self::SERVICE_KEY);
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            PayrollEngine::class,
            self::SERVICE_KEY,
        ];
    }
}
