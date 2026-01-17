<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Patient;
use App\Models\LabServiceRequest;
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

$latestLab = LabServiceRequest::where('patient_id', $patient->id)
    ->orderBy('created_at', 'desc')
    ->first();

if (!$latestLab) {
    echo "No lab requests found for this patient\n";
    exit(1);
}

echo "Latest Lab Request:\n";
echo "  ID: " . $latestLab->id . "\n";
echo "  Created: " . $latestLab->created_at . "\n";
echo "  Service: " . ($latestLab->service ? $latestLab->service->service_name : 'N/A') . "\n\n";

$hourKey = Carbon::now()->format('Y-m-d-H');
$cacheKey = "notified_lab_{$latestLab->id}_{$hourKey}";

echo "Cache Check (BEFORE):\n";
echo "  Current Hour Key: {$hourKey}\n";
echo "  Cache Key: {$cacheKey}\n";
echo "  Was Notified: " . (Cache::has($cacheKey) ? 'YES' : 'NO') . "\n";

// Check if patient is admitted
$isAdmitted = \App\Models\AdmissionRequest::where('patient_id', $patient->id)
    ->where('discharged', 0)
    ->exists();

echo "\n  Patient Admitted: " . ($isAdmitted ? 'YES' : 'NO') . "\n";

// Check cutoff time
$cutoff = Carbon::now()->subHour();
$isWithinHour = Carbon::parse($latestLab->created_at)->gt($cutoff);
echo "  Cutoff Time: {$cutoff}\n";
echo "  Lab Created Within Last Hour: " . ($isWithinHour ? 'YES' : 'NO') . "\n";

echo "\n--- Running notification checks manually ---\n\n";

$service = app(DepartmentNotificationService::class);
$service->runChecks();

echo "\nCache Check (AFTER):\n";
echo "  Was Notified: " . (Cache::has($cacheKey) ? 'YES' : 'NO') . "\n";
