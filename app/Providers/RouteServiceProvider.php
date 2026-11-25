<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    // protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));

            // Nursing Workbench Routes
            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/nursing_workbench.php'));

            // HR Routes (HRMS Implementation Plan - Section 7.1)
            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/hr.php'));

            // Accounting Routes (Accounting System Plan - Section 8)
            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/accounting.php'));

            // Maternity Workbench Routes
            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/maternity_workbench.php'));

            // Surgery Workbench Routes
            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/surgery_workbench.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        // Signed-in staff: a screen of the staff app makes several calls (context, counts, list, tiles) and every
        // refresh, sort, page and search makes more, so 60 a minute ran out in ordinary use and every list then failed
        // with 429. Anonymous callers keep the low limit (sign-in has its own, stricter one).
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();

            return $user ? Limit::perMinute(1200)->by('user:' . $user->id) : Limit::perMinute(60)->by($request->ip());
        });
    }
}
