<?php

namespace App\Observers\HR;

use App\Models\HR\StaffMedicalExam;

/**
 * Syncs denormalized medical exam dates on staff when an exam is created/updated
 */
class StaffMedicalExamObserver
{
    public function saved(StaffMedicalExam $exam): void
    {
        $staff = $exam->staff;
        if (!$staff) return;

        $staff->last_medical_exam_date = $exam->exam_date;
        if ($exam->next_exam_due) {
            $staff->next_medical_exam_due = $exam->next_exam_due;
        }
        $staff->saveQuietly();
    }

    public function deleted(StaffMedicalExam $exam): void
    {
        $staff = $exam->staff;
        if (!$staff) return;

        $latest = $staff->medicalExams()->withoutTrashed()->latest('exam_date')->first();
        if ($latest) {
            $staff->last_medical_exam_date = $latest->exam_date;
            $staff->next_medical_exam_due = $latest->next_exam_due;
        } else {
            $staff->last_medical_exam_date = null;
            $staff->next_medical_exam_due = null;
        }
        $staff->saveQuietly();
    }
}
