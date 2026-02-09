<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Patient;
use App\Models\AdmissionRequest;
use App\Models\Staff;
use App\Models\User;

echo "=== Testing Admission Doctor Lookup for Patient 0001 ===\n\n";

// Find patient with file_no 0001
$patient = Patient::where('file_no', 'LIKE', '%0001%')->first();

if (!$patient) {
    echo "Patient with file_no containing '0001' not found.\n";
    echo "\nListing first 5 patients:\n";
    $patients = Patient::take(5)->get(['id', 'file_no', 'user_id']);
    foreach ($patients as $p) {
        echo "  - ID: {$p->id}, File No: {$p->file_no}, User ID: {$p->user_id}\n";
    }
    exit;
}

echo "Found Patient:\n";
echo "  - Patient ID: {$patient->id}\n";
echo "  - File No: {$patient->file_no}\n";
echo "  - User ID: {$patient->user_id}\n";
echo "  - Name: " . userfullname($patient->user_id) . "\n\n";

// Get admissions for this patient
$admissions = AdmissionRequest::where('patient_id', $patient->id)
    ->orderBy('created_at', 'desc')
    ->get();

echo "Found " . $admissions->count() . " admission(s):\n\n";

foreach ($admissions as $index => $admission) {
    echo "--- Admission #" . ($index + 1) . " (ID: {$admission->id}) ---\n";
    echo "  bed_assign_date: " . ($admission->bed_assign_date ?? 'NULL') . "\n";
    echo "  discharge_date: " . ($admission->discharge_date ?? 'NULL') . "\n";
    echo "  discharged: " . ($admission->discharged ? 'Yes' : 'No') . "\n";
    echo "  doctor_id: " . ($admission->doctor_id ?? 'NULL') . "\n";
    echo "  encounter_id: " . ($admission->encounter_id ?? 'NULL') . "\n";

    // Check what doctor_id contains
    if ($admission->doctor_id) {
        echo "\n  Checking doctor_id = {$admission->doctor_id}:\n";

        // Check if it's a Staff ID
        $staff = Staff::find($admission->doctor_id);
        if ($staff) {
            echo "    Found Staff record:\n";
            echo "      Staff ID: {$staff->id}\n";
            echo "      Staff user_id: " . ($staff->user_id ?? 'NULL') . "\n";

            if ($staff->user_id) {
                $user = User::find($staff->user_id);
                if ($user) {
                    echo "      User surname: {$user->surname}\n";
                    echo "      User firstname: {$user->firstname}\n";
                    echo "      userfullname(): " . userfullname($staff->user_id) . "\n";
                } else {
                    echo "      User NOT FOUND for user_id: {$staff->user_id}\n";
                }
            }
        } else {
            echo "    Staff record NOT FOUND for doctor_id: {$admission->doctor_id}\n";
        }

        // Check if it's a User ID directly
        $userDirect = User::find($admission->doctor_id);
        if ($userDirect) {
            echo "\n    Also checking if doctor_id is a User ID directly:\n";
            echo "      Found User: {$userDirect->surname} {$userDirect->firstname}\n";

            // Check if this user has a staff profile
            $staffProfile = Staff::where('user_id', $admission->doctor_id)->first();
            if ($staffProfile) {
                echo "      This User has Staff profile with ID: {$staffProfile->id}\n";
            }
        }
    }

    // Check encounter's doctor
    if ($admission->encounter_id) {
        $encounter = \App\Models\Encounter::find($admission->encounter_id);
        if ($encounter) {
            echo "\n  Encounter doctor_id: " . ($encounter->doctor_id ?? 'NULL') . "\n";
            if ($encounter->doctor_id) {
                // Check if encounter's doctor_id is Staff or User
                $encStaff = Staff::find($encounter->doctor_id);
                if ($encStaff) {
                    echo "    Encounter doctor is Staff ID: {$encStaff->id}, user_id: {$encStaff->user_id}\n";
                }
                $encUser = User::find($encounter->doctor_id);
                if ($encUser) {
                    echo "    Encounter doctor is User ID: {$encUser->id}, name: {$encUser->surname} {$encUser->firstname}\n";
                }
            }
        }
    }

    echo "\n";
}

// Show what the relationship returns
echo "\n=== Checking AdmissionRequest->doctor() relationship definition ===\n";
$admission = $admissions->first();
if ($admission) {
    echo "Calling \$admission->doctor:\n";
    $doctorRelation = $admission->doctor;
    if ($doctorRelation) {
        echo "  Returned: " . get_class($doctorRelation) . "\n";
        echo "  ID: {$doctorRelation->id}\n";
        if ($doctorRelation instanceof User) {
            echo "  Type: User\n";
            echo "  Name: {$doctorRelation->surname} {$doctorRelation->firstname}\n";

            // Check if this user has staff_profile
            if (method_exists($doctorRelation, 'staff_profile')) {
                $sp = $doctorRelation->staff_profile;
                if ($sp) {
                    echo "  Has staff_profile: Yes (Staff ID: {$sp->id})\n";
                } else {
                    echo "  Has staff_profile: No\n";
                }
            }
        }
    } else {
        echo "  Returned NULL\n";
    }
}

echo "\n=== Summary ===\n";
echo "Based on the data above, determine if doctor_id stores:\n";
echo "  A) Staff ID (need Staff->user_id->userfullname)\n";
echo "  B) User ID directly (need userfullname(doctor_id))\n";
