<?php

namespace App\Observers\HR;

use App\Models\HR\StaffPromotion;

/**
 * Syncs denormalized promotion dates on staff when a promotion is created/updated
 */
class StaffPromotionObserver
{
    public function saved(StaffPromotion $promotion): void
    {
        $staff = $promotion->staff;
        if (!$staff) return;

        $staff->last_promotion_date = $promotion->promotion_date;

        if ($promotion->next_promotion_due_date) {
            $staff->next_promotion_due_date = $promotion->next_promotion_due_date;
        } elseif ($promotion->toGradeLevel?->min_years_to_next) {
            $staff->next_promotion_due_date = $promotion->promotion_date
                ->copy()
                ->addYears($promotion->toGradeLevel->min_years_to_next);
        }

        // Update grade level and job title from latest promotion
        if ($promotion->to_grade_level_id) {
            $staff->grade_level_id = $promotion->to_grade_level_id;
        }
        if ($promotion->to_job_title) {
            $staff->job_title = $promotion->to_job_title;
        }

        // Recalculate exit dates with new grade level
        $staff->retirement_date = $staff->computeRetirementDate();
        $staff->max_service_date = $staff->computeMaxServiceDate();

        $staff->saveQuietly();
    }

    public function deleted(StaffPromotion $promotion): void
    {
        $staff = $promotion->staff;
        if (!$staff) return;

        // Recalculate from remaining promotions
        $latest = $staff->promotions()->withoutTrashed()->latest('promotion_date')->first();
        if ($latest) {
            $staff->last_promotion_date = $latest->promotion_date;
            $staff->next_promotion_due_date = $latest->next_promotion_due_date;
            // Re-sync grade level and job title from now-latest promotion
            if ($latest->to_grade_level_id) {
                $staff->grade_level_id = $latest->to_grade_level_id;
            }
            if ($latest->to_job_title) {
                $staff->job_title = $latest->to_job_title;
            }
        } else {
            $staff->last_promotion_date = null;
            $staff->next_promotion_due_date = null;
            // Revert to entry grade level if no promotions remain
            $staff->grade_level_id = $staff->entry_grade_level_id;
        }

        // Recalculate exit dates with potentially changed grade level
        $staff->retirement_date = $staff->computeRetirementDate();
        $staff->max_service_date = $staff->computeMaxServiceDate();

        $staff->saveQuietly();
    }
}
