<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\patient;
use App\Models\ImagingServiceRequest;
use App\Models\ProductOrServiceRequest;
use Illuminate\Support\Facades\DB;

$patient = patient::where('file_no', '9426')->first();

if (!$patient) {
    echo "Patient not found\n";
    exit;
}

echo "======================================\n";
echo "PATIENT INFO\n";
echo "======================================\n";
echo "Patient ID: " . $patient->id . "\n";
echo "Patient File No: " . $patient->file_no . "\n";
echo "Patient Name: " . ($patient->user ? $patient->user->firstname . ' ' . $patient->user->surname : 'N/A') . "\n";
echo "HMO ID: " . ($patient->hmo_id ?? 'NULL') . "\n\n";

// Get ALL imaging requests for this patient
$requests = ImagingServiceRequest::withTrashed()
    ->where('patient_id', $patient->id)
    ->orderBy('created_at', 'desc')
    ->get();

echo "======================================\n";
echo "ALL IMAGING SERVICE REQUESTS ({$requests->count()} total)\n";
echo "======================================\n\n";

foreach ($requests as $r) {
    $serviceName = $r->service ? $r->service->service_name : 'N/A';
    echo "=== Request ID: {$r->id} - {$serviceName} ===\n";
    echo "Service ID: {$r->service_id}\n";
    echo "Status: {$r->status}\n";
    echo "Billed By: " . ($r->billed_by ?? 'NULL') . "\n";
    echo "Billed Date: " . ($r->billed_date ?? 'NULL') . "\n";
    echo "Encounter ID: " . ($r->encounter_id ?? 'NULL') . "\n";
    echo "service_request_id (POS): " . ($r->service_request_id ?? 'NULL') . "\n";
    echo "Created: {$r->created_at}\n";
    echo "Deleted at: " . ($r->deleted_at ?? 'NULL') . "\n";

    // Check if there's a linked ProductOrServiceRequest
    if ($r->service_request_id) {
        $posReq = ProductOrServiceRequest::find($r->service_request_id);
        if ($posReq) {
            echo "\n  --> Linked ProductOrServiceRequest (ID: {$posReq->id}):\n";
            echo "      Service ID: {$posReq->service_id}\n";
            echo "      User ID: {$posReq->user_id}\n";
            echo "      Payable Amount: " . ($posReq->payable_amount ?? 'NULL') . "\n";
            echo "      Claims Amount: " . ($posReq->claims_amount ?? 'NULL') . "\n";
            echo "      Coverage Mode: " . ($posReq->coverage_mode ?? 'NULL') . "\n";
            echo "      Validation Status: " . ($posReq->validation_status ?? 'NULL') . "\n";
            echo "      Payment ID: " . ($posReq->payment_id ?? 'NULL') . "\n";
            echo "      Deleted at: " . ($posReq->deleted_at ?? 'NULL') . "\n";
        } else {
            echo "\n  --> Linked ProductOrServiceRequest NOT FOUND (ID: {$r->service_request_id})!\n";
        }
    } else {
        echo "\n  --> NO ProductOrServiceRequest linked (service_request_id is NULL)!\n";
    }
    echo "\n";
}

// Now check ProductOrServiceRequests for this user directly
echo "======================================\n";
echo "ALL ProductOrServiceRequest FOR THIS PATIENT USER\n";
echo "======================================\n";

$userPosRequests = ProductOrServiceRequest::where('user_id', $patient->user_id)
    ->orderBy('created_at', 'desc')
    ->get();

echo "Found {$userPosRequests->count()} ProductOrServiceRequest(s) for user {$patient->user_id}\n\n";

foreach ($userPosRequests as $pos) {
    $itemName = 'Unknown';
    if ($pos->service_id && $pos->service) {
        $itemName = $pos->service->service_name;
    } elseif ($pos->product_id && $pos->product) {
        $itemName = $pos->product->product_name;
    }

    echo "POS ID: {$pos->id} - {$itemName}\n";
    echo "  Service ID: " . ($pos->service_id ?? 'NULL') . "\n";
    echo "  Product ID: " . ($pos->product_id ?? 'NULL') . "\n";
    echo "  Payable Amount: " . ($pos->payable_amount ?? 'NULL') . "\n";
    echo "  Claims Amount: " . ($pos->claims_amount ?? 'NULL') . "\n";
    echo "  Coverage Mode: " . ($pos->coverage_mode ?? 'NULL') . "\n";
    echo "  Validation Status: " . ($pos->validation_status ?? 'NULL') . "\n";
    echo "  Payment ID: " . ($pos->payment_id ?? 'NULL') . "\n";
    echo "  Created: {$pos->created_at}\n";
    echo "  Deleted at: " . ($pos->deleted_at ?? 'NULL') . "\n";
    echo "---\n";
}

// Check the HMO workbench query to understand why claims might not show
echo "\n======================================\n";
echo "CHECKING HMO CLAIMS WORKBENCH CRITERIA\n";
echo "======================================\n";
echo "For items to appear in HMO Claims Workbench:\n";
echo "- Must have coverage_mode set (NOT NULL)\n";
echo "- payment_id = NULL (not yet paid)\n";
echo "- validation_status = 'pending'\n";
echo "- User must have HMO\n\n";

// Simulate the HMO workbench query for this patient (UPDATED LOGIC)
$hmoWorkbenchItems = ProductOrServiceRequest::with([
    'user.patient_profile.hmo',
    'service.price'
])
->whereHas('user.patient_profile', function($q) {
    $q->whereNotNull('hmo_id');
})
->where('user_id', $patient->user_id)
->whereNotNull('coverage_mode')
->where(function($q) {
    // Show unpaid requests OR paid requests that still need claim validation
    $q->whereNull('payment_id')
      ->orWhere(function($q2) {
          // Paid but claims not yet validated
          $q2->whereNotNull('payment_id')
             ->where('claims_amount', '>', 0)
             ->where('validation_status', 'pending');
      });
})
->get();

echo "Items that WOULD appear in HMO Workbench for this patient: {$hmoWorkbenchItems->count()}\n";
foreach ($hmoWorkbenchItems as $item) {
    $name = $item->service ? $item->service->service_name : ($item->product ? $item->product->product_name : 'Unknown');
    echo "- POS ID {$item->id}: {$name}\n";
    echo "  Coverage Mode: {$item->coverage_mode}, Validation Status: {$item->validation_status}\n";
    echo "  Claims: {$item->claims_amount}, Payable: {$item->payable_amount}\n";
}

// IMPORTANT: Check if there's a workflow issue
echo "\n======================================\n";
echo "WORKFLOW ANALYSIS\n";
echo "======================================\n";

// Find chest x-ray request that should be problematic (most recent one that's billed)
$chestXray = $requests->where('service_id', 64)->where('status', '>=', 2)->first();
$abdominal = $requests->where('service_id', 65)->first();

if ($chestXray) {
    echo "CHEST X-RAY (ID: {$chestXray->id}):\n";
    echo "  Status: {$chestXray->status}\n";
    echo "  service_request_id: " . ($chestXray->service_request_id ?? 'NULL') . "\n";
    if (!$chestXray->service_request_id) {
        echo "  PROBLEM: This chest x-ray has NO linked ProductOrServiceRequest!\n";
        echo "  This means it was either never billed through the standard workflow,\n";
        echo "  or the billing process failed to create the POS entry.\n";
    }
}

if ($abdominal) {
    echo "\nABDOMINOPELVIC SCAN (ID: {$abdominal->id}):\n";
    echo "  Status: {$abdominal->status}\n";
    echo "  service_request_id: " . ($abdominal->service_request_id ?? 'NULL') . "\n";
    if (!$abdominal->service_request_id) {
        echo "  PROBLEM: This scan also has NO linked ProductOrServiceRequest!\n";
    }
}
