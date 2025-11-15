<?php

namespace Aldiazhar\Invoice;

use Illuminate\Support\ServiceProvider;
use Aldiazhar\Invoice\Builders\InvoiceBuilder;
use Aldiazhar\Invoice\Console\Commands\GenerateRecurringInvoices;

class InvoiceServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'invoice-migrations');

        $this->publishes([
            __DIR__.'/../config/invoice.php' => config_path('invoice.php'),
        ], 'invoice-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateRecurringInvoices::class,
            ]);
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/invoice.php', 'invoice'
        );

        $this->app->singleton('invoice', function ($app) {
            return new InvoiceManager();
        });

        $this->app->bind(InvoiceBuilder::class, function ($app) {
            return new InvoiceBuilder();
        });
    }
}