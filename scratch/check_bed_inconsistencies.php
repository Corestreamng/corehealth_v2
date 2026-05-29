<?php

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Bed;
use App\Models\AdmissionRequest;
use Illuminate\Support\Facades\DB;

echo "--- Bed Allocation & Occupancy Inconsistencies ---\n\n";

// Query beds that are occupied but have no active admission
$occupiedBedsWithNoActiveAdmission = Bed::where('bed_status', 'occupied')
    ->whereDoesntHave('currentAdmission')
    ->get();

echo "Beds with status = 'occupied' but NO active admission (discharged = 0):\n";
if ($occupiedBedsWithNoActiveAdmission->isEmpty()) {
    echo "  None\n";
} else {
    foreach ($occupiedBedsWithNoActiveAdmission as $bed) {
        $lastAdmission = $bed->admissions()->latest()->first();
        echo "  - Bed ID: {$bed->id}, Name: {$bed->name}, Ward: {$bed->ward}, occupant_id: " . ($bed->occupant_id ?? 'NULL') . "\n";
        if ($lastAdmission) {
            echo "    * Last Admission ID: {$lastAdmission->id}, Discharged: {$lastAdmission->discharged}, Status: {$lastAdmission->admission_status}, Patient ID: {$lastAdmission->patient_id}\n";
        }
    }
}
echo "\n";

// Query beds that have occupant_id set but no active admission
$bedsWithOccupantButNoActiveAdmission = Bed::whereNotNull('occupant_id')
    ->whereDoesntHave('currentAdmission')
    ->get();

echo "Beds with occupant_id set but NO active admission (discharged = 0):\n";
if ($bedsWithOccupantButNoActiveAdmission->isEmpty()) {
    echo "  None\n";
} else {
    foreach ($bedsWithOccupantButNoActiveAdmission as $bed) {
        $lastAdmission = $bed->admissions()->latest()->first();
        echo "  - Bed ID: {$bed->id}, Name: {$bed->name}, Ward: {$bed->ward}, occupant_id: {$bed->occupant_id}\n";
        if ($lastAdmission) {
            echo "    * Last Admission ID: {$lastAdmission->id}, Discharged: {$lastAdmission->discharged}, Status: {$lastAdmission->admission_status}\n";
        }
    }
}
echo "\n";

// Query beds with status != 'occupied' but occupant_id is set
$bedsWithMismatchedStatusAndOccupant = Bed::where('bed_status', '!=', 'occupied')
    ->whereNotNull('occupant_id')
    ->get();

echo "Beds with status != 'occupied' but occupant_id IS set:\n";
if ($bedsWithMismatchedStatusAndOccupant->isEmpty()) {
    echo "  None\n";
} else {
    foreach ($bedsWithMismatchedStatusAndOccupant as $bed) {
        echo "  - Bed ID: {$bed->id}, Name: {$bed->name}, Ward: {$bed->ward}, Status: {$bed->bed_status}, occupant_id: {$bed->occupant_id}\n";
    }
}
echo "\n";

// Query beds that are active (status = 1) but have status column casts to 0 or similar
$bedsStatusQuery = Bed::select('status', DB::raw('count(*) as count'))->groupBy('status')->get();
echo "Beds status column counts:\n";
foreach ($bedsStatusQuery as $group) {
    echo "  - status value: '{$group->status}' -> count: {$group->count}\n";
}
echo "\n";

