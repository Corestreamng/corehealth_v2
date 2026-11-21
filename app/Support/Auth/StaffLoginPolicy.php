<?php

namespace App\Support\Auth;

use App\Models\User;

/**
 * Who may start a web session. Shared by the web sign-in form (LoginController) and the app-to-web
 * hand-off (WebHandoffController), so both apply the same employment, suspension and locum rules.
 */
class StaffLoginPolicy
{
    /**
     * The message explaining why [$user] may not sign in, or null when they may.
     */
    public static function refusal(User $user): ?string
    {
        // 1. Deactivated account
        if ((int) $user->status === 0) {
            $staff = $user->staff_profile;
            if ($staff && $staff->isSuspended()) {
                return 'Your staff account has been suspended' . self::suspensionDetails($staff) . '. System access is currently disabled. Please contact Human Resources or Hospital Administration for guidance.';
            }
            if ($staff && in_array($staff->employment_status, ['terminated', 'resigned', 'retired'])) {
                return 'Your staff employment record is marked as ' . ucfirst($staff->employment_status) . '. System login has been revoked. Please contact Human Resources.';
            }

            return 'Your user account has been deactivated. Please contact hospital administration for assistance.';
        }

        // 2. Staff profile status and employment status
        if ($user->staff_profile && !$user->staff_profile->canLogin()) {
            $staff = $user->staff_profile;
            if ($staff->isSuspended()) {
                return 'Your staff account has been suspended' . self::suspensionDetails($staff) . '. System access is currently disabled. Please contact Human Resources or Hospital Administration for guidance.';
            }
            if (in_array($staff->employment_status, ['terminated', 'resigned', 'retired'])) {
                return 'Your staff employment record is marked as ' . ucfirst($staff->employment_status) . '. System login has been revoked. Please contact Human Resources.';
            }

            return 'Your staff account is currently inactive. Please contact hospital administration.';
        }

        // 3. Locum shift pass
        if ($user->isLocum() && !$user->hasActiveLocumPass()) {
            $latestPass = $user->latest_locum_pass;
            if ($latestPass && $latestPass->isRevoked()) {
                $reason = $latestPass->revocation_reason ? ': ' . $latestPass->revocation_reason : '.';

                return 'Your locum shift pass has been revoked' . $reason . ' Please contact the Medical Director or HOD.';
            }
            if ($latestPass && $latestPass->isExpired()) {
                return 'Your locum shift pass expired at ' . $latestPass->expires_at->format('h:i A on M d, Y') . '. Please request a shift renewal from the HOD.';
            }

            return 'You do not have an active locum shift pass. Please contact the Medical Director or HOD for a pass.';
        }

        return null;
    }

    private static function suspensionDetails($staff): string
    {
        $details = [];
        if ($staff->suspension_reason) {
            $details[] = 'Reason: ' . $staff->suspension_reason;
        }
        if ($staff->suspension_end_date) {
            $details[] = 'Expected Reinstatement: ' . $staff->suspension_end_date->format('M d, Y');
        }

        return !empty($details) ? ' (' . implode(' | ', $details) . ')' : '';
    }
}
