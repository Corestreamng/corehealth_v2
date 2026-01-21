<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Patient;
use App\Models\MedicationSchedule;
use App\Services\DepartmentNotificationService;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

$fileNo = '9426';

$patient = Patient::where('file_no', $fileNo)->first();
if (!$patient) {
    echo "Patient with file number {$fileNo} not found\n";
    exit(1);
}

echo "Patient: " . $patient->user->name . " (ID: " . $patient->id . ")\n\n";

// Check if patient is admitted
$isAdmitted = \App\Models\AdmissionRequest::where('patient_id', $patient->id)
    ->where('discharged', 0)
    ->exists();
echo "Patient Admitted: " . ($isAdmitted ? 'YES' : 'NO') . "\n\n";

$hourKey = Carbon::now()->format('Y-m-d-H');
$now = Carbon::now();
$upcomingTime = Carbon::now()->addMinutes(30);

echo "Time Window:\n";
echo "  Now: {$now}\n";
echo "  Upcoming (30 min): {$upcomingTime}\n";
echo "  Hour Key: {$hourKey}\n\n";

// Get medication schedules due within next 30 minutes
$schedules = MedicationSchedule::with(['patient.user', 'productOrServiceRequest.product'])
    ->where('patient_id', $patient->id)
    ->whereBetween('scheduled_time', [$now, $upcomingTime])
    ->whereNull('deleted_at')
    ->get();

echo "Medication Schedules Due (next 30 min): " . $schedules->count() . "\n\n";

if ($schedules->isEmpty()) {
    // Also show recent/past schedules for context
    $recentSchedules = MedicationSchedule::with(['patient.user', 'productOrServiceRequest.product'])
        ->where('patient_id', $patient->id)
        ->whereNull('deleted_at')
        ->orderBy('scheduled_time', 'desc')
        ->limit(50)
        ->get();

    echo "Recent Schedules (last 50):\n";
    foreach ($recentSchedules as $schedule) {
        $medName = $schedule->productOrServiceRequest && $schedule->productOrServiceRequest->product
            ? $schedule->productOrServiceRequest->product->product_name
            : 'Unknown';
        $cacheKey = "notified_med_schedule_{$schedule->id}_{$hourKey}";
        $notified = Cache::has($cacheKey) ? 'YES' : 'NO';
        echo "  ID: {$schedule->id} | Time: {$schedule->scheduled_time} | Med: {$medName} | Notified: {$notified}\n";
    }
} else {
    echo "Due Schedules:\n";
    foreach ($schedules as $schedule) {
        $medName = $schedule->productOrServiceRequest && $schedule->productOrServiceRequest->product
            ? $schedule->productOrServiceRequest->product->product_name
            : 'Unknown';
        $cacheKey = "notified_med_schedule_{$schedule->id}_{$hourKey}";
        $notified = Cache::has($cacheKey) ? 'YES' : 'NO';
        echo "  ID: {$schedule->id} | Time: {$schedule->scheduled_time} | Med: {$medName} | Notified: {$notified}\n";
    }
}

echo "\n--- Running notification checks manually ---\n\n";

$service = app(DepartmentNotificationService::class);
$service->runChecks();

echo "Checks completed. Re-checking cache...\n\n";

// Recheck after running
if ($schedules->isNotEmpty()) {
    foreach ($schedules as $schedule) {
        $cacheKey = "notified_med_schedule_{$schedule->id}_{$hourKey}";
        $notified = Cache::has($cacheKey) ? 'YES' : 'NO';
        echo "  ID: {$schedule->id} | Notified (AFTER): {$notified}\n";
    }
}

$service = app(DepartmentNotificationService::class);
$service->runChecks();

echo "\nCache Check (AFTER):\n";
echo "  Was Notified: " . (Cache::has($cacheKey) ? 'YES' : 'NO') . "\n";
