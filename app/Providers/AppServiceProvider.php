<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Product;
use App\Models\Service;
use App\Models\Hmo;
use App\Observers\ProductObserver;
use App\Observers\ServiceObserver;
use App\Observers\HmoObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Register HMO tariff auto-generation observers
        Product::observe(ProductObserver::class);
        Service::observe(ServiceObserver::class);
        Hmo::observe(HmoObserver::class);
    }
}
