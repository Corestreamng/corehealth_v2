<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Staff;

class StaffTrackingProfileController extends Controller
{
    public function show(Staff $staff)
    {
        $staff->load([
            'user',
            'department',
            'unit',
            'cadre',
            'gradeLevel',
            'entryGradeLevel',
            'nextOfKin',
            'promotions.fromGradeLevel',
            'promotions.toGradeLevel',
            'qualifications',
            'trainings',
            'medicalExams',
            'followUps.createdByUser',
            'currentSalaryProfile',
            'salaryProfiles',
            'leaveRequests',
            'disciplinaryQueries',
        ]);

        return view('admin.hr.tracking.profile', compact('staff'));
    }
}
