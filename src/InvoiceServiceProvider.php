<?php

namespace Aldiazhar\Invoice;

use Illuminate\Support\ServiceProvider;
use Aldiazhar\Invoice\Builders\InvoiceBuilder;

class InvoiceServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'invoice-migrations');

        // Publish config
        $this->publishes([
            __DIR__.'/../config/invoice.php' => config_path('invoice.php'),
        ], 'invoice-config');

        // Publish views
        // $this->publishes([
        //     __DIR__.'/../resources/views' => resource_path('views/vendor/invoice'),
        // ], 'invoice-views');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load views
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'invoice');

        // Load routes
        // if (config('invoice.routes.enabled', true)) {
        //     $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        // }
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/invoice.php', 'invoice'
        );

        // Register the main class to use with the facade
        $this->app->singleton('invoice', function ($app) {
            return new InvoiceManager($app);
        });

        // Register invoice builder
        $this->app->bind(InvoiceBuilder::class, function ($app) {
            return new InvoiceBuilder();
        });
    }
}