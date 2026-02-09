<?php

/**
 * Comprehensive Bed Billing Investigation
 * Shows exactly how billing should work based on bed price
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\patient;
use App\Models\AdmissionRequest;
use App\Models\Bed;
use App\Models\service;
use App\Models\ServicePrice;
use App\Models\ProductOrServiceRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

echo "==============================================\n";
echo "BED BILLING INVESTIGATION - CORRECTED\n";
echo "==============================================\n\n";

// Get configuration
$bedServiceCategoryId = appsettings('bed_service_category_id');
echo "System Configuration:\n";
echo "   Bed Service Category ID: " . ($bedServiceCategoryId ?? 'NOT SET') . "\n\n";

// Find patient 0001
$patient = patient::where('file_no', '0001')->first();
if (!$patient) {
    echo "❌ Patient 0001 not found!\n";
    exit(1);
}

echo "Patient: " . userfullname($patient->user_id) . " (File: {$patient->file_no})\n\n";

// Find active admission
$admission = AdmissionRequest::with(['bed.service.price', 'service.price'])
    ->where('patient_id', $patient->id)
    ->where('discharged', 0)
    ->whereNotNull('bed_id')
    ->first();

if (!$admission) {
    echo "❌ No active admission with assigned bed!\n";
    exit(1);
}

echo "==============================================\n";
echo "ACTIVE ADMISSION DETAILS\n";
echo "==============================================\n";
echo "Admission ID: {$admission->id}\n";
echo "Admitted Since: {$admission->bed_assign_date}\n";
echo "Days: " . Carbon::parse($admission->bed_assign_date)->diffInDays(now()) . "\n\n";

// BED INFORMATION
echo "==============================================\n";
echo "BED DETAILS (Source of Truth for Billing)\n";
echo "==============================================\n";

$bed = $admission->bed;
echo "Bed ID: {$bed->id}\n";
echo "Bed Name: {$bed->name}\n";
echo "Ward: {$bed->ward}\n";
echo "Unit: " . ($bed->unit ?? 'N/A') . "\n";
echo "Status: {$bed->status}\n";
echo "\n";

echo "💰 BED PRICING:\n";
echo "   Bed->price (direct field): " . ($bed->price ?? 'NULL ❌') . "\n";
echo "   Bed->service_id: " . ($bed->service_id ?? 'NULL ❌') . "\n";

if ($bed->service_id) {
    $bedService = service::with('price')->find($bed->service_id);
    if ($bedService) {
        echo "   \n   Bed Service Details:\n";
        echo "   - Service Name: {$bedService->service_name}\n";
        echo "   - Service Code: {$bedService->service_code}\n";
        echo "   - Category ID: " . ($bedService->category_id ?? 'N/A') . "\n";

        if ($bedService->price) {
            echo "   - ServicePrice->amount: {$bedService->price->amount}\n";
            echo "   - ServicePrice->id: {$bedService->price->id}\n";
        } else {
            echo "   - ServicePrice: NOT FOUND ❌\n";
        }
    }
}

echo "\n";

// Show which should be used
echo "==============================================\n";
echo "BILLING LOGIC SHOULD USE:\n";
echo "==============================================\n";

$billingAmount = null;
$billingSource = null;

// Priority 1: Bed->price field
if ($bed->price && $bed->price > 0) {
    $billingAmount = $bed->price;
    $billingSource = "Bed->price (direct field)";
}
// Priority 2: Bed->service->price
elseif ($bed->service_id && $bed->service && $bed->service->price) {
    $billingAmount = $bed->service->price->amount;
    $billingSource = "Bed->service->price->amount";
}
else {
    $billingAmount = 0;
    $billingSource = "❌ NO PRICE CONFIGURED";
}

echo "Price to bill: ₦" . number_format($billingAmount, 2) . "\n";
echo "Source: {$billingSource}\n\n";

// Check current AppServiceProvider logic
echo "==============================================\n";
echo "CURRENT AppServiceProvider LOGIC ANALYSIS\n";
echo "==============================================\n";

echo "Current logic at line 221-257:\n";
echo "   1. Uses admission->service_id (copied from bed during assignment)\n";
echo "   2. Creates ProductOrServiceRequest with service_id\n";
echo "   3. Applies HMO tariff to service\n";
echo "   4. ❌ PROBLEM: No explicit price fallback if HMO fails\n\n";

// Check admission's service
echo "Admission->service_id: " . ($admission->service_id ?? 'NULL ❌') . "\n";
if ($admission->service_id) {
    $admissionService = service::with('price')->find($admission->service_id);
    if ($admissionService) {
        echo "Admission Service: {$admissionService->service_name}\n";
        if ($admissionService->price) {
            echo "Admission Service Price: ₦{$admissionService->price->amount}\n";
        } else {
            echo "❌ Admission Service has NO price!\n";
        }
    }
}

echo "\n";

// Check actual bills created
echo "==============================================\n";
echo "ACTUAL BILLS CREATED (Last 5)\n";
echo "==============================================\n";

$bills = ProductOrServiceRequest::where('user_id', $patient->user_id)
    ->where('service_id', $admission->service_id)
    ->whereDate('created_at', '>=', Carbon::parse($admission->bed_assign_date))
    ->orderBy('created_at', 'desc')
    ->take(5)
    ->get();

foreach ($bills as $bill) {
    echo "Bill #{$bill->id}:\n";
    echo "   Date: {$bill->created_at}\n";
    echo "   Service ID: {$bill->service_id}\n";
    echo "   Qty: {$bill->qty}\n";
    echo "   Payable: ₦" . number_format($bill->payable_amount, 2) . "\n";
    echo "   Claims: ₦" . number_format($bill->claims_amount, 2) . "\n";
    echo "   Staff: {$bill->staff_user_id}\n";
    echo "\n";
}

// THE SOLUTION
echo "==============================================\n";
echo "💡 THE FIX NEEDED\n";
echo "==============================================\n";

if ($billingAmount == 0) {
    echo "1. SET BED PRICE:\n";
    echo "   Either:\n";
    echo "   a) Update bed->price field directly, OR\n";
    echo "   b) Create bed service with proper price in service_prices table\n\n";

    echo "   Command to fix:\n";
    echo "   UPDATE beds SET price = 10000 WHERE id = {$bed->id};\n";
    echo "   OR\n";
    echo "   INSERT INTO service_prices (service_id, amount) VALUES ({$bed->service_id}, 10000);\n\n";
}

echo "2. UPDATE AppServiceProvider::processDailyBedBills():\n";
echo "   Add fallback to use bed price when HMO tariff fails:\n\n";
echo "   if (\$admission->patient->hmo_id) {\n";
echo "       try {\n";
echo "           // Try HMO first\n";
echo "       } catch (\\Exception \$e) {\n";
echo "           // FALLBACK: Use bed->price or service price\n";
echo "           \$bed = Bed::find(\$admission->bed_id);\n";
echo "           \$price = \$bed->price;\n";
echo "           if (!\$price && \$bed->service && \$bed->service->price) {\n";
echo "               \$price = \$bed->service->price->amount;\n";
echo "           }\n";
echo "           \$bill_req->payable_amount = \$price ?? 0;\n";
echo "       }\n";
echo "   }\n\n";

echo "3. CHECK service_prices TABLE STRUCTURE:\n";
$columns = DB::select("SHOW COLUMNS FROM service_prices");
echo "   Columns:\n";
foreach ($columns as $col) {
    echo "   - {$col->Field} ({$col->Type})\n";
}

echo "\n==============================================\n";
echo "END OF INVESTIGATION\n";
echo "==============================================\n";
