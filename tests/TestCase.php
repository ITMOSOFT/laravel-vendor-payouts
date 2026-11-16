<?php

namespace ItmoSoft\VendorPayouts\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Illuminate\Support\Facades\Schema;
use ItmoSoft\VendorPayouts\VendorPayoutsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array { return [VendorPayoutsServiceProvider::class]; }
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
        $app['config']->set('itmosoft-vendor-payouts.vendor_model', TestVendor::class);
    }
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('test_vendors', function ($table): void { $table->id(); $table->string('name'); $table->timestamps(); });
        $this->artisan('migrate')->run();
    }
}
