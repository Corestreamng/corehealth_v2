<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * HRMS & Clinical Governance Middleware
 * Enforces staff deactivation, suspension, termination, resignation, retirement,
 * and locum shift pass TTL/revocation across all authenticated requests.
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

            // 1. Check if user account is deactivated (status = 0)
            if ((int) $user->status === 0) {
                $staff = $user->staff_profile;
                $message = 'Your user account has been deactivated. Please contact hospital administration.';
                if ($staff && $staff->isSuspended()) {
                    $details = [];
                    if ($staff->suspension_reason) {
                        $details[] = 'Reason: ' . $staff->suspension_reason;
                    }
                    if ($staff->suspension_end_date) {
                        $details[] = 'Expected Reinstatement: ' . $staff->suspension_end_date->format('M d, Y');
                    }
                    $extra = !empty($details) ? ' (' . implode(' | ', $details) . ')' : '';
                    $message = 'Your staff account has been suspended' . $extra . '. Your active session has been terminated. Please contact HR.';
                } elseif ($staff && in_array($staff->employment_status, ['terminated', 'resigned', 'retired'])) {
                    $statusLabel = ucfirst($staff->employment_status);
                    $message = "Your staff employment record is marked as {$statusLabel}. Your session has been terminated. Access revoked.";
                }

                return $this->revokeAndRedirect($request, $message);
            }

            // 2. Check staff profile status and employment status
            if ($user->staff_profile) {
                $staff = $user->staff_profile;

                // Check suspension
                if ($staff->isSuspended()) {
                    $details = [];
                    if ($staff->suspension_reason) {
                        $details[] = 'Reason: ' . $staff->suspension_reason;
                    }
                    if ($staff->suspension_end_date) {
                        $details[] = 'Expected Reinstatement: ' . $staff->suspension_end_date->format('M d, Y');
                    }
                    $extra = !empty($details) ? ' (' . implode(' | ', $details) . ')' : '';
                    $message = 'Your account has been suspended' . $extra . '. Your active session has been terminated. Please contact HR.';

                    return $this->revokeAndRedirect($request, $message);
                }

                // Check termination
                if ($staff->employment_status === 'terminated') {
                    return $this->revokeAndRedirect($request, 'Your employment has been terminated. Access to this system is no longer available.');
                }

                // Check resignation
                if ($staff->employment_status === 'resigned') {
                    return $this->revokeAndRedirect($request, 'Your employment has ended (Resigned). Access to this system is no longer available.');
                }

                // Check retirement
                if ($staff->employment_status === 'retired') {
                    return $this->revokeAndRedirect($request, 'Your employment has ended (Retired). Access to this system is no longer available.');
                }

                // Check staff status is 0
                if ((int) $staff->status === 0) {
                    return $this->revokeAndRedirect($request, 'Your staff account is currently inactive. Please contact administration.');
                }
            }

            // 3. Check Locum Shift Pass TTL & Revocation
            if ($user->isLocum()) {
                if (!$user->hasActiveLocumPass()) {
                    $latestPass = $user->latest_locum_pass;
                    if ($latestPass && $latestPass->isRevoked()) {
                        $reason = $latestPass->revocation_reason ? ': ' . $latestPass->revocation_reason : '.';
                        $message = 'Your locum shift pass has been revoked' . $reason . ' Please contact the Medical Director or HOD.';
                    } elseif ($latestPass && $latestPass->isExpired()) {
                        $message = 'Your locum shift pass expired at ' . $latestPass->expires_at->format('h:i A on M d, Y') . '. Please request a shift extension from the HOD.';
                    } else {
                        $message = 'You do not have an active locum shift pass. Please contact the Medical Director or HOD for a pass.';
                    }

                    return $this->revokeAndRedirect($request, $message);
                }
            }
        }

        return $next($request);
    }

    /**
     * Invalidate session, log out, and redirect to login with flash error.
     */
    protected function revokeAndRedirect(Request $request, string $message)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'redirect' => route('login'),
            ], 403);
        }

        return redirect()->route('login')
            ->withErrors(['email' => $message]);
    }
}
