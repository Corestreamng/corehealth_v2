<?php

/**
 * Bed Billing Diagnostic Script
 * Investigates why daily bed billing is not working for patient 0001
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\patient;
use App\Models\AdmissionRequest;
use App\Models\Bed;
use App\Models\ProductOrServiceRequest;
use App\Models\service;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Helpers\HmoHelper;

echo "==============================================\n";
echo "BED BILLING DIAGNOSTIC SCRIPT\n";
echo "==============================================\n";
echo "Date: " . now()->format('Y-m-d H:i:s') . "\n\n";

// Find patient 0001
echo "1. SEARCHING FOR PATIENT FILE NO: 0001\n";
echo "----------------------------------------------\n";

$patient = patient::where('file_no', '0001')->first();

if (!$patient) {
    echo "❌ ERROR: Patient with file_no 0001 not found!\n";
    exit(1);
}

echo "✓ Patient found:\n";
echo "   ID: {$patient->id}\n";
echo "   Name: " . userfullname($patient->user_id) . "\n";
echo "   File No: {$patient->file_no}\n";
echo "   HMO ID: " . ($patient->hmo_id ?? 'None (Cash)') . "\n\n";

// Check admission records
echo "2. CHECKING ADMISSION RECORDS\n";
echo "----------------------------------------------\n";

$admissions = AdmissionRequest::where('patient_id', $patient->id)
    ->with(['bed', 'service'])
    ->orderBy('created_at', 'desc')
    ->get();

echo "Total admissions: {$admissions->count()}\n\n";

if ($admissions->isEmpty()) {
    echo "❌ ERROR: No admission records found for this patient!\n";
    exit(1);
}

foreach ($admissions as $index => $admission) {
    echo "Admission #{$admission->id}:\n";
    echo "   Created: {$admission->created_at}\n";
    echo "   Status: {$admission->admission_status}\n";
    echo "   Discharged: " . ($admission->discharged ? 'YES' : 'NO') . "\n";
    echo "   Bed ID: " . ($admission->bed_id ?? 'NOT ASSIGNED') . "\n";

    if ($admission->bed) {
        echo "   Bed Name: {$admission->bed->name}\n";
        echo "   Ward: {$admission->bed->ward}\n";
    }

    echo "   Bed Assign Date: " . ($admission->bed_assign_date ?? 'NOT SET') . "\n";
    echo "   Service ID: " . ($admission->service_id ?? 'NOT SET') . "\n";

    if ($admission->service) {
        echo "   Service Name: {$admission->service->service_name}\n";
        echo "   Service Code: {$admission->service->service_code}\n";

        // Get service price
        $price = DB::table('service_prices')
            ->where('service_id', $admission->service_id)
            ->first();
        if ($price) {
            // Check which column has the price
            if (isset($price->amount)) {
                echo "   Service Price: {$price->amount}\n";
            } elseif (isset($price->price)) {
                echo "   Service Price: {$price->price}\n";
            } else {
                echo "   Service Price: (Price column not found)\n";
            }
        }
    }

    if (!$admission->discharged && $admission->bed_id && $admission->bed_assign_date) {
        $daysAdmitted = Carbon::parse($admission->bed_assign_date)->diffInDays(now());
        echo "   Days Admitted: {$daysAdmitted}\n";
    }

    echo "\n";
}

// Find active admission
echo "3. CHECKING ACTIVE ADMISSION\n";
echo "----------------------------------------------\n";

$activeAdmission = AdmissionRequest::where('patient_id', $patient->id)
    ->where('discharged', 0)
    ->whereNotNull('bed_id')
    ->whereNotNull('bed_assign_date')
    ->whereNotNull('service_id')
    ->first();

if (!$activeAdmission) {
    echo "❌ WARNING: No active admission meeting billing criteria!\n";
    echo "   Criteria for daily billing:\n";
    echo "   - discharged = 0\n";
    echo "   - bed_id NOT NULL\n";
    echo "   - bed_assign_date NOT NULL\n";
    echo "   - service_id NOT NULL\n";
    echo "   - status = 1\n\n";

    // Check which criteria is failing
    $checkAdmission = AdmissionRequest::where('patient_id', $patient->id)
        ->where('discharged', 0)
        ->first();

    if ($checkAdmission) {
        echo "   Found active admission #{$checkAdmission->id}, checking criteria:\n";
        echo "   - discharged = 0: ✓\n";
        echo "   - bed_id: " . ($checkAdmission->bed_id ? "✓ ({$checkAdmission->bed_id})" : "❌ NULL") . "\n";
        echo "   - bed_assign_date: " . ($checkAdmission->bed_assign_date ? "✓ ({$checkAdmission->bed_assign_date})" : "❌ NULL") . "\n";
        echo "   - service_id: " . ($checkAdmission->service_id ? "✓ ({$checkAdmission->service_id})" : "❌ NULL") . "\n";
        echo "   - status: " . ($checkAdmission->status == 1 ? "✓" : "❌ ({$checkAdmission->status})") . "\n\n";
    }
} else {
    echo "✓ Active admission found:\n";
    echo "   Admission ID: {$activeAdmission->id}\n";
    echo "   Bed: {$activeAdmission->bed->ward} - {$activeAdmission->bed->name}\n";
    echo "   Admitted Since: {$activeAdmission->bed_assign_date}\n";
    echo "   Days: " . Carbon::parse($activeAdmission->bed_assign_date)->diffInDays(now()) . "\n";
    echo "   Service: {$activeAdmission->service->service_name}\n\n";
}

// Check existing bed bills
echo "4. CHECKING EXISTING BED BILLS\n";
echo "----------------------------------------------\n";

if ($activeAdmission) {
    $bedBills = ProductOrServiceRequest::where('user_id', $patient->user_id)
        ->where('service_id', $activeAdmission->service_id)
        ->whereDate('created_at', '>=', Carbon::parse($activeAdmission->bed_assign_date)->startOfDay())
        ->orderBy('created_at', 'desc')
        ->get();

    echo "Total bed bills found: {$bedBills->count()}\n\n";

    if ($bedBills->count() > 0) {
        foreach ($bedBills as $bill) {
            echo "Bill #{$bill->id}:\n";
            echo "   Created: {$bill->created_at}\n";
            echo "   Qty: {$bill->qty}\n";
            echo "   Payable Amount: {$bill->payable_amount}\n";
            echo "   Claims Amount: {$bill->claims_amount}\n";
            echo "   Payment ID: " . ($bill->payment_id ?? 'UNPAID') . "\n";
            echo "   Staff User ID: {$bill->staff_user_id}\n";
            echo "\n";
        }
    } else {
        echo "❌ No bed bills found! This is the problem.\n\n";
    }

    // Check bills for TODAY specifically
    $todayBills = ProductOrServiceRequest::where('user_id', $patient->user_id)
        ->where('service_id', $activeAdmission->service_id)
        ->whereDate('created_at', Carbon::today())
        ->get();

    echo "Bills created TODAY: {$todayBills->count()}\n\n";
}

// Check cache status
echo "5. CHECKING CACHE STATUS\n";
echo "----------------------------------------------\n";

$cacheKey = 'bed_billing_processed_' . Carbon::today()->format('Y-m-d');
$cacheExists = Cache::has($cacheKey);

echo "Cache Key: {$cacheKey}\n";
echo "Cache Exists: " . ($cacheExists ? "YES ⚠️" : "NO") . "\n";

if ($cacheExists) {
    echo "⚠️  WARNING: Cache exists! Daily billing already ran today.\n";
    echo "   This prevents duplicate billing but may indicate the job ran\n";
    echo "   before the admission was fully set up.\n\n";
} else {
    echo "✓ Cache clear - daily billing hasn't run today.\n\n";
}

// Check AppServiceProvider logic
echo "6. SIMULATING DAILY BILLING LOGIC\n";
echo "----------------------------------------------\n";

if ($activeAdmission) {
    echo "Checking if admission meets billing criteria:\n\n";

    $qualifiesForBilling = AdmissionRequest::where('discharged', 0)
        ->where('status', 1)
        ->whereNotNull('bed_id')
        ->whereNotNull('bed_assign_date')
        ->whereNotNull('service_id')
        ->where('id', $activeAdmission->id)
        ->exists();

    echo "Meets all criteria: " . ($qualifiesForBilling ? "✓ YES" : "❌ NO") . "\n\n";

    if ($qualifiesForBilling) {
        // Check for today's bill
        $existingBill = ProductOrServiceRequest::where('user_id', $patient->user_id)
            ->where('service_id', $activeAdmission->service_id)
            ->whereDate('created_at', Carbon::today())
            ->first();

        if ($existingBill) {
            echo "✓ Bill already exists for today (Bill ID: {$existingBill->id})\n\n";
        } else {
            echo "❌ No bill exists for today - SHOULD CREATE ONE!\n\n";
        }
    }
}

// Manual billing test
echo "7. MANUAL BILLING TEST\n";
echo "----------------------------------------------\n";

if (!$activeAdmission) {
    echo "⚠️  Cannot test manual billing - no active admission.\n\n";
} else {
    echo "Do you want to manually create a bed bill for today? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $response = trim(fgets($handle));
    fclose($handle);

    if (strtolower($response) === 'yes') {
        try {
            DB::beginTransaction();

            echo "\nCreating bed bill...\n";

            $bill_req = new ProductOrServiceRequest();
            $bill_req->user_id = $patient->user_id;
            $bill_req->staff_user_id = 1; // System user
            $bill_req->service_id = $activeAdmission->service_id;
            $bill_req->qty = 1;
            $bill_req->created_at = Carbon::now();

            // Apply HMO tariff if patient has HMO
            if ($patient->hmo_id) {
                try {
                    echo "Applying HMO tariff...\n";
                    $hmoData = HmoHelper::applyHmoTariff(
                        $patient->id,
                        null,
                        $activeAdmission->service_id
                    );
                    if ($hmoData) {
                        $bill_req->payable_amount = $hmoData['payable_amount'];
                        $bill_req->claims_amount = $hmoData['claims_amount'];
                        $bill_req->coverage_mode = $hmoData['coverage_mode'];
                        $bill_req->validation_status = $hmoData['validation_status'];
                        echo "   Payable Amount: {$bill_req->payable_amount}\n";
                        echo "   Claims Amount: {$bill_req->claims_amount}\n";
                        echo "   Coverage Mode: {$bill_req->coverage_mode}\n";
                    }
                } catch (\Exception $e) {
                    echo "   HMO Error: {$e->getMessage()}\n";
                    echo "   Using cash pricing...\n";
                }
            } else {
                echo "Cash patient - using service price...\n";
                $price = DB::table('service_prices')
                    ->where('service_id', $activeAdmission->service_id)
                    ->first();
                if ($price) {
                    $bill_req->payable_amount = $price->amount;
                    echo "   Payable Amount: {$bill_req->payable_amount}\n";
                }
            }

            $bill_req->save();
            DB::commit();

            echo "✓ SUCCESS! Bed bill created (ID: {$bill_req->id})\n";
            echo "   Check billing workbench for patient {$patient->file_no}\n\n";

        } catch (\Exception $e) {
            DB::rollBack();
            echo "❌ ERROR creating bill: {$e->getMessage()}\n\n";
        }
    } else {
        echo "Skipped manual billing.\n\n";
    }
}

// Recommendations
echo "==============================================\n";
echo "RECOMMENDATIONS\n";
echo "==============================================\n";

if (!$activeAdmission) {
    echo "1. Fix admission setup:\n";
    echo "   - Ensure bed is assigned\n";
    echo "   - Ensure bed_assign_date is set\n";
    echo "   - Ensure service_id is copied from bed\n";
    echo "   - Ensure admission status = 1\n\n";
}

if ($cacheExists && $activeAdmission && isset($bedBills) && $bedBills->count() == 0) {
    echo "2. Cache issue detected:\n";
    echo "   - Clear cache: php artisan cache:clear\n";
    echo "   - Or wait until tomorrow for next billing cycle\n\n";
}

echo "3. Check AppServiceProvider boot() method:\n";
echo "   - Ensure processDailyBedBills() is being called\n";
echo "   - Check if it's commented out or disabled\n\n";

echo "4. Check service_id on bed:\n";
if ($activeAdmission && $activeAdmission->bed) {
    $bed = Bed::find($activeAdmission->bed_id);
    echo "   Bed #{$bed->id} service_id: " . ($bed->service_id ?? 'NOT SET ❌') . "\n";
    if (!$bed->service_id) {
        echo "   ⚠️  FIX: Bed must have service_id configured!\n";
    }
}

echo "\n==============================================\n";
echo "DIAGNOSTIC COMPLETE\n";
echo "==============================================\n";
