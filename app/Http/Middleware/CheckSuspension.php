<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * HRMS Implementation Plan - Section 6.4
 * CheckSuspension Middleware - Blocks suspended staff from logging in
 */
class CheckSuspension
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Check if user has staff record
            if ($user->staff) {
                $staff = $user->staff;

                // Check if staff is suspended
                if ($staff->isSuspended()) {
                    Auth::logout();

                    $message = $staff->suspension_message
                        ?? 'Your account has been suspended. Please contact HR for more information.';

                    return redirect()->route('login')
                        ->withErrors(['email' => $message]);
                }

                // Check if staff is terminated
                if (in_array($staff->employment_status, ['terminated', 'resigned'])) {
                    Auth::logout();

                    return redirect()->route('login')
                        ->withErrors(['email' => 'Your employment has been terminated. Access to this system is no longer available.']);
                }
            }
        }

        return $next($request);
    }
}
