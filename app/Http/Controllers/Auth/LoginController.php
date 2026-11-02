<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Handle post-authentication checks for employment status, deactivation, and locum passes.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function authenticated(Request $request, $user)
    {
        // 1. Check if user account is deactivated
        if ((int) $user->status === 0) {
            $staff = $user->staff_profile;
            $message = 'Your user account has been deactivated. Please contact hospital administration for assistance.';

            if ($staff && $staff->isSuspended()) {
                $details = [];
                if ($staff->suspension_reason) {
                    $details[] = 'Reason: ' . $staff->suspension_reason;
                }
                if ($staff->suspension_end_date) {
                    $details[] = 'Expected Reinstatement: ' . $staff->suspension_end_date->format('M d, Y');
                }
                $extra = !empty($details) ? ' (' . implode(' | ', $details) . ')' : '';
                $message = 'Your staff account has been suspended' . $extra . '. System access is currently disabled. Please contact Human Resources or Hospital Administration for guidance.';
            } elseif ($staff && in_array($staff->employment_status, ['terminated', 'resigned', 'retired'])) {
                $statusLabel = ucfirst($staff->employment_status);
                $message = "Your staff employment record is marked as {$statusLabel}. System login has been revoked. Please contact Human Resources.";
            }

            return $this->rejectLogin($request, $message);
        }

        // 2. Check staff profile status and employment status
        if ($user->staff_profile) {
            $staff = $user->staff_profile;
            if (!$staff->canLogin()) {
                $message = 'Your staff account is currently inactive. Please contact hospital administration.';
                if ($staff->isSuspended()) {
                    $details = [];
                    if ($staff->suspension_reason) {
                        $details[] = 'Reason: ' . $staff->suspension_reason;
                    }
                    if ($staff->suspension_end_date) {
                        $details[] = 'Expected Reinstatement: ' . $staff->suspension_end_date->format('M d, Y');
                    }
                    $extra = !empty($details) ? ' (' . implode(' | ', $details) . ')' : '';
                    $message = 'Your staff account has been suspended' . $extra . '. System access is currently disabled. Please contact Human Resources or Hospital Administration for guidance.';
                } elseif (in_array($staff->employment_status, ['terminated', 'resigned', 'retired'])) {
                    $statusLabel = ucfirst($staff->employment_status);
                    $message = "Your staff employment record is marked as {$statusLabel}. System login has been revoked. Please contact Human Resources.";
                }

                return $this->rejectLogin($request, $message);
            }
        }

        // 3. Check Locum Shift Pass validity
        if ($user->isLocum()) {
            if (!$user->hasActiveLocumPass()) {
                $latestPass = $user->latest_locum_pass;
                if ($latestPass && $latestPass->isRevoked()) {
                    $reason = $latestPass->revocation_reason ? ': ' . $latestPass->revocation_reason : '.';
                    $message = 'Your locum shift pass has been revoked' . $reason . ' Please contact the Medical Director or HOD.';
                } elseif ($latestPass && $latestPass->isExpired()) {
                    $message = 'Your locum shift pass expired at ' . $latestPass->expires_at->format('h:i A on M d, Y') . '. Please request a shift renewal from the HOD.';
                } else {
                    $message = 'You do not have an active locum shift pass. Please contact the Medical Director or HOD for a pass.';
                }

                return $this->rejectLogin($request, $message);
            }
        }

        return redirect()->intended($this->redirectPath());
    }

    /**
     * Terminate session and redirect to login with error message.
     */
    protected function rejectLogin(Request $request, string $message)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => $message]);
    }

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }
}
