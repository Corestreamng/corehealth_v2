<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\patient;
use App\Models\MedicationSchedule;
use App\Models\MedicationAdministration;
use App\Models\ProductOrServiceRequest;
use App\Models\Payment;

$fileNo = '9426';

echo "=== Medication Schedule Check for Patient File# {$fileNo} ===\n\n";

// Find patient
$patient = patient::where('file_no', $fileNo)->first();

if (!$patient) {
    echo "ERROR: Patient with file number {$fileNo} not found!\n";
    exit(1);
}

echo "Patient Found:\n";
echo "  ID: {$patient->id}\n";
echo "  Name: {$patient->firstname} {$patient->surname}\n";
echo "  File No: {$patient->file_no}\n\n";

// Get patient's payments
$paymentIds = Payment::where('patient_id', $patient->id)->pluck('id');
echo "Patient Payment IDs: " . $paymentIds->implode(', ') . "\n\n";

// Check medication requests (prescriptions) through payment
echo "=== Medication Requests (ProductOrServiceRequest via Payment) ===\n";
$requests = ProductOrServiceRequest::whereIn('payment_id', $paymentIds)
    ->whereNotNull('product_id')
    ->with('product')
    ->orderBy('created_at', 'desc')
    ->get();

echo "Total Medication Requests: " . $requests->count() . "\n\n";

if ($requests->isNotEmpty()) {
    echo "Last 10 Medication Requests:\n";
    echo str_repeat('-', 100) . "\n";
    foreach ($requests->take(10) as $r) {
        $productName = $r->product ? $r->product->product_name : 'N/A';
        echo "ID: {$r->id}, Product: {$productName}, Qty: {$r->qty}, Payment: {$r->payment_id}, Created: {$r->created_at}\n";
    }
    echo "\n";
}

// Check medication schedules
echo "=== Medication Schedules ===\n";
$schedules = MedicationSchedule::where('patient_id', $patient->id)
    ->orderBy('scheduled_time', 'desc')
    ->get();

echo "Total Schedules: " . $schedules->count() . "\n\n";

if ($schedules->isEmpty()) {
    echo "WARNING: No medication schedules found for this patient!\n";
    echo "This is likely why nothing shows on the calendar.\n\n";
} else {
    echo "Last 20 Schedules:\n";
    echo str_repeat('-', 120) . "\n";
    foreach ($schedules->take(20) as $s) {
        $adminStatus = $s->administrations()->count() > 0 ? 'Administered' : 'Pending';
        echo "ID: {$s->id}, MedRequest: {$s->product_or_service_request_id}, Time: {$s->scheduled_time}, Dose: {$s->dose}, Route: {$s->route}, Status: {$adminStatus}\n";
    }
    echo "\n";

    // Check date range
    $minDate = $schedules->min('scheduled_time');
    $maxDate = $schedules->max('scheduled_time');
    echo "Schedule Date Range: {$minDate} to {$maxDate}\n\n";
}

// Check administrations
echo "=== Medication Administrations ===\n";
$administrations = MedicationAdministration::where('patient_id', $patient->id)
    ->orderBy('administered_at', 'desc')
    ->get();

echo "Total Administrations: " . $administrations->count() . "\n\n";

if ($administrations->isNotEmpty()) {
    echo "Last 10 Administrations:\n";
    echo str_repeat('-', 100) . "\n";
    foreach ($administrations->take(10) as $a) {
        echo "ID: {$a->id}, Schedule: {$a->schedule_id}, Dose: {$a->dose}, Route: {$a->route}, Time: {$a->administered_at}\n";
    }
}

// Check if schedules exist for specific medication requests
echo "\n=== Schedules per Medication Request ===\n";
foreach ($requests->take(5) as $r) {
    $productName = $r->product ? $r->product->product_name : 'N/A';
    $scheduleCount = MedicationSchedule::where('product_or_service_request_id', $r->id)->count();
    echo "Request ID: {$r->id} ({$productName}): {$scheduleCount} schedules\n";
}

echo "\n=== Summary ===\n";
echo "Medication Requests: " . $requests->count() . "\n";
echo "Medication Schedules: " . $schedules->count() . "\n";
echo "Administrations: " . $administrations->count() . "\n";

if ($schedules->count() == 0 && $requests->count() > 0) {
    echo "\n*** ISSUE FOUND: Patient has medication requests but NO schedules! ***\n";
    echo "Schedules need to be created using the 'Set Schedule' button in the medication chart.\n";
}
