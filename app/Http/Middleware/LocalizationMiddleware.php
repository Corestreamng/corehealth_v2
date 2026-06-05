<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LocalizationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            // Set Language
            $lang = appsettings('language') ?: 'en';
            \Illuminate\Support\Facades\App::setLocale($lang);

            // Set Timezone
            $tz = appsettings('timezone') ?: 'Africa/Lagos';
            date_default_timezone_set($tz);
            \Illuminate\Support\Facades\Config::set('app.timezone', $tz);

            // Set Currency Symbol
            $currency = appsettings('currency_symbol') ?: '₦';
            \Illuminate\Support\Facades\Config::set('app.currency_symbol', $currency);
        } catch (\Exception $e) {
            // Failsafe if DB or appsettings is not ready yet
        }

        return $next($request);
    }
}
