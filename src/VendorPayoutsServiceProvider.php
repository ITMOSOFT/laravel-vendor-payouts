<?php

namespace ItmoSoft\VendorPayouts;

use Illuminate\Support\ServiceProvider;

class VendorPayoutsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/itmosoft-vendor-payouts.php', 'itmosoft-vendor-payouts');
        $this->app->singleton(VendorPayoutManager::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/itmosoft-vendor-payouts.php' => config_path('itmosoft-vendor-payouts.php'),
        ], 'itmosoft-vendor-payouts-config');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
