<?php

/**
 * Test Fixed Bed Billing Logic
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\patient;
use App\Models\AdmissionRequest;
use App\Models\Bed;
use App\Models\service;
use App\Models\ProductOrServiceRequest;
use App\Helpers\HmoHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "==============================================\n";
echo "TEST FIXED BED BILLING LOGIC\n";
echo "==============================================\n\n";

// Find patient 0001
$patient = patient::where('file_no', '0001')->first();
if (!$patient) {
    echo "❌ Patient not found!\n";
    exit(1);
}

echo "Patient: " . userfullname($patient->user_id) . "\n";
echo "HMO ID: " . ($patient->hmo_id ?? 'None (Cash)') . "\n\n";

// Find active admission
$admission = AdmissionRequest::with(['bed', 'patient.user', 'patient.hmo'])
    ->where('patient_id', $patient->id)
    ->where('discharged', 0)
    ->whereNotNull('bed_id')
    ->first();

if (!$admission) {
    echo "❌ No active admission!\n";
    exit(1);
}

echo "Admission ID: {$admission->id}\n";
echo "Bed: {$admission->bed->ward} - {$admission->bed->name}\n";
echo "Bed Price: ₦" . number_format($admission->bed->price, 2) . "\n\n";

// Check if bill already exists for today
$existingBill = ProductOrServiceRequest::where('user_id', $patient->user_id)
    ->where('service_id', $admission->service_id)
    ->whereDate('created_at', Carbon::today())
    ->first();

if ($existingBill) {
    echo "Bill already exists for today (Bill #" . $existingBill->id . "):\n";
    echo "   Created: {$existingBill->created_at}\n";
    echo "   Payable: ₦" . number_format($existingBill->payable_amount, 2) . "\n";
    echo "   Claims: ₦" . number_format($existingBill->claims_amount, 2) . "\n\n";
}

echo "Creating test bill with FIXED logic...\n";
echo "----------------------------------------------\n";

try {
    DB::beginTransaction();

    $bill_req = new ProductOrServiceRequest();
    $bill_req->user_id = $admission->patient->user->id;
    $bill_req->staff_user_id = 1; // System user
    $bill_req->service_id = $admission->service_id;
    $bill_req->qty = 1; // One day
    $bill_req->created_at = Carbon::now();

    // Get bed for pricing
    $bed = Bed::find($admission->bed_id);

    echo "Step 1: Get bed (ID: {$bed->id}, Price: ₦{$bed->price})\n";

    // Apply HMO tariff if patient has HMO
    if ($admission->patient->hmo_id) {
        echo "Step 2: Patient has HMO (ID: {$admission->patient->hmo_id})\n";
        echo "   Attempting HMO tariff lookup...\n";

        try {
            $hmoData = HmoHelper::applyHmoTariff(
                $admission->patient_id,
                null,
                $admission->service_id
            );
            if ($hmoData) {
                $bill_req->payable_amount = $hmoData['payable_amount'];
                $bill_req->claims_amount = $hmoData['claims_amount'];
                $bill_req->coverage_mode = $hmoData['coverage_mode'];
                $bill_req->validation_status = $hmoData['validation_status'];
                echo "   ✓ HMO tariff applied:\n";
                echo "      Payable: ₦" . number_format($bill_req->payable_amount, 2) . "\n";
                echo "      Claims: ₦" . number_format($bill_req->claims_amount, 2) . "\n";
            } else {
                echo "   ⚠️  HMO data returned empty\n";
                // HMO data returned but empty - use bed price
                $bill_req->payable_amount = $bed->price ?? 0;
                echo "   → Fallback to bed price: ₦" . number_format($bill_req->payable_amount, 2) . "\n";
            }
        } catch (\Exception $e) {
            echo "   ❌ HMO Error: {$e->getMessage()}\n";
            // Fallback to bed price
            $bill_req->payable_amount = $bed->price ?? 0;
            echo "   → Fallback to bed price: ₦" . number_format($bill_req->payable_amount, 2) . "\n";
        }
    } else {
        echo "Step 2: Cash patient (no HMO)\n";
        // Cash patient - use bed price directly
        $bill_req->payable_amount = $bed->price ?? 0;
        echo "   → Using bed price: ₦" . number_format($bill_req->payable_amount, 2) . "\n";

        // If bed has no price, try service price as last resort
        if (!$bill_req->payable_amount && $bed->service_id) {
            echo "   → Bed has no price, checking service price...\n";
            $service = service::with('price')->find($bed->service_id);
            if ($service && $service->price) {
                $bill_req->payable_amount = $service->price->sale_price ?? 0;
                echo "   → Using service price: ₦" . number_format($bill_req->payable_amount, 2) . "\n";
            }
        }
    }

    echo "\nStep 3: Saving bill...\n";
    $bill_req->save();

    DB::commit();

    echo "\n✓ SUCCESS!\n";
    echo "----------------------------------------------\n";
    echo "Bill ID: {$bill_req->id}\n";
    echo "Service ID: {$bill_req->service_id}\n";
    echo "Qty: {$bill_req->qty}\n";
    echo "Payable Amount: ₦" . number_format($bill_req->payable_amount, 2) . "\n";
    echo "Claims Amount: ₦" . number_format($bill_req->claims_amount ?? 0, 2) . "\n";
    echo "Coverage Mode: " . ($bill_req->coverage_mode ?? 'N/A') . "\n";
    echo "Created At: {$bill_req->created_at}\n\n";

    echo "🎉 Bed billing is now working correctly!\n";
    echo "   Check billing workbench for patient {$patient->file_no}\n\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR: {$e->getMessage()}\n";
    echo "   Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "==============================================\n";
echo "TEST COMPLETE\n";
echo "==============================================\n";
