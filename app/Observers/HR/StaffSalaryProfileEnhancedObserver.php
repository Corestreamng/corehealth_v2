<?php

namespace App\Observers\HR;

use App\Models\HR\StaffSalaryProfile;

/**
 * Tracks salary increment dates when new salary profiles are created
 */
class StaffSalaryProfileEnhancedObserver
{
    public function created(StaffSalaryProfile $profile): void
    {
        $this->syncIncrementDate($profile);
    }

    public function updated(StaffSalaryProfile $profile): void
    {
        $this->syncIncrementDate($profile);
    }

    public function deleted(StaffSalaryProfile $profile): void
    {
        $staff = $profile->staff;
        if (!$staff) return;

        // Recalculate from remaining active profiles
        $latest = $staff->salaryProfiles()
            ->where('is_active', true)
            ->latest('effective_from')
            ->first();

        if ($latest) {
            // Check if there was a prior profile before the latest
            $priorExists = $staff->salaryProfiles()
                ->where('id', '!=', $latest->id)
                ->exists();
            $staff->salary_increment_date = $priorExists ? ($latest->effective_from ?? null) : null;
        } else {
            $staff->salary_increment_date = null;
        }
        $staff->saveQuietly();
    }

    private function syncIncrementDate(StaffSalaryProfile $profile): void
    {
        if (!$profile->is_active) return;

        $staff = $profile->staff;
        if (!$staff) return;

        // If there was a previous profile, this counts as a salary increment
        $previousCount = $staff->salaryProfiles()
            ->where('id', '!=', $profile->id)
            ->count();

        if ($previousCount > 0) {
            $staff->salary_increment_date = $profile->effective_from ?? now();
            $staff->saveQuietly();
        }
    }
}
