<?php

/**
 * Fix Bed Billing Setup
 * Corrects bed service assignments and sets up proper billing
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Bed;
use App\Models\service;
use App\Models\AdmissionRequest;
use Illuminate\Support\Facades\DB;

echo "==============================================\n";
echo "BED BILLING SETUP FIX\n";
echo "==============================================\n\n";

// Step 1: Find or create proper bed services
echo "1. CHECKING BED SERVICES\n";
echo "----------------------------------------------\n";

$bedServices = service::where('service_name', 'LIKE', '%bed%')
    ->orWhere('service_name', 'LIKE', '%ward%')
    ->orWhere('service_code', 'LIKE', '%BED%')
    ->get();

echo "Found {$bedServices->count()} bed-related services:\n";
foreach ($bedServices as $srv) {
    $price = DB::table('service_prices')->where('service_id', $srv->id)->first();
    $priceAmount = $price ? ($price->amount ?? $price->price ?? 'No price') : 'No price';
    echo "   - {$srv->service_name} (ID: {$srv->id}, Price: {$priceAmount})\n";
}
echo "\n";

// Check if we need to create bed services
if ($bedServices->isEmpty()) {
    echo "⚠️  No bed services found. You need to:\n";
    echo "   1. Create bed services (e.g., 'General Ward Bed', 'Special Ward Bed')\n";
    echo "   2. Set prices in service_prices table\n";
    echo "   3. Link beds to these services\n\n";

    echo "Would you like to create default bed services? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $response = trim(fgets($handle));
    fclose($handle);

    if (strtolower($response) === 'yes') {
        DB::beginTransaction();
        try {
            // Create General Ward Bed service
            $generalBed = new service();
            $generalBed->service_name = 'General Ward Bed (Per Day)';
            $generalBed->service_code = 'BED-GENERAL';
            $generalBed->category_id = 1; // Adjust if needed
            $generalBed->status = 1;
            $generalBed->save();

            // Create price for general ward
            DB::table('service_prices')->insert([
                'service_id' => $generalBed->id,
                'amount' => 5000, // ₦5,000 per day
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            echo "✓ Created 'General Ward Bed' service (ID: {$generalBed->id}, Price: ₦5,000)\n";

            // Create Special Ward Bed service
            $specialBed = new service();
            $specialBed->service_name = 'Special Ward Bed (Per Day)';
            $specialBed->service_code = 'BED-SPECIAL';
            $specialBed->category_id = 1;
            $specialBed->status = 1;
            $specialBed->save();

            DB::table('service_prices')->insert([
                'service_id' => $specialBed->id,
                'amount' => 10000, // ₦10,000 per day
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            echo "✓ Created 'Special Ward Bed' service (ID: {$specialBed->id}, Price: ₦10,000)\n\n";

            DB::commit();

            // Reload bed services
            $bedServices = service::whereIn('id', [$generalBed->id, $specialBed->id])->get();

        } catch (\Exception $e) {
            DB::rollBack();
            echo "❌ Error creating services: {$e->getMessage()}\n";
            exit(1);
        }
    } else {
        echo "Skipped service creation. Please create bed services manually.\n";
        exit(0);
    }
}

// Step 2: Fix beds with wrong service_id
echo "2. CHECKING ALL BEDS\n";
echo "----------------------------------------------\n";

$allBeds = Bed::all();
echo "Total beds: {$allBeds->count()}\n\n";

$bedsToFix = [];
foreach ($allBeds as $bed) {
    $hasWrongService = false;
    $serviceName = 'Not set';

    if ($bed->service_id) {
        $service = service::find($bed->service_id);
        if ($service) {
            $serviceName = $service->service_name;
            // Check if it's NOT a bed service
            if (!stripos($serviceName, 'bed') && !stripos($serviceName, 'ward')) {
                $hasWrongService = true;
            }
        }
    }

    echo "Bed #{$bed->id}: {$bed->ward} - {$bed->name}\n";
    echo "   Service ID: " . ($bed->service_id ?? 'NOT SET') . "\n";
    echo "   Service: {$serviceName}\n";

    if (!$bed->service_id || $hasWrongService) {
        echo "   ⚠️  NEEDS FIX\n";
        $bedsToFix[] = $bed;
    } else {
        echo "   ✓ OK\n";
    }
    echo "\n";
}

// Fix beds
if (!empty($bedsToFix) && !$bedServices->isEmpty()) {
    echo "3. FIXING BEDS\n";
    echo "----------------------------------------------\n";

    echo "Select service to assign to beds:\n";
    foreach ($bedServices as $index => $srv) {
        $price = DB::table('service_prices')->where('service_id', $srv->id)->first();
        $priceAmount = $price ? ($price->amount ?? $price->price ?? '0') : '0';
        echo "   {$index}. {$srv->service_name} (₦{$priceAmount}/day)\n";
    }
    echo "\nEnter number (or 'skip'): ";

    $handle = fopen("php://stdin", "r");
    $choice = trim(fgets($handle));
    fclose($handle);

    if ($choice !== 'skip' && isset($bedServices[$choice])) {
        $selectedService = $bedServices[$choice];

        DB::beginTransaction();
        try {
            foreach ($bedsToFix as $bed) {
                $bed->service_id = $selectedService->id;
                $bed->save();
                echo "✓ Updated Bed #{$bed->id} to service '{$selectedService->service_name}'\n";
            }

            DB::commit();
            echo "\n✓ All beds updated successfully!\n\n";

        } catch (\Exception $e) {
            DB::rollBack();
            echo "❌ Error updating beds: {$e->getMessage()}\n";
            exit(1);
        }
    }
}

// Step 3: Fix active admissions
echo "4. FIXING ACTIVE ADMISSIONS\n";
echo "----------------------------------------------\n";

$activeAdmissions = AdmissionRequest::where('discharged', 0)
    ->whereNotNull('bed_id')
    ->with('bed')
    ->get();

echo "Active admissions: {$activeAdmissions->count()}\n\n";

foreach ($activeAdmissions as $admission) {
    if ($admission->bed && $admission->bed->service_id) {
        if ($admission->service_id != $admission->bed->service_id) {
            echo "Admission #{$admission->id}:\n";
            echo "   Current service_id: " . ($admission->service_id ?? 'NULL') . "\n";
            echo "   Bed service_id: {$admission->bed->service_id}\n";
            echo "   → Updating to match bed service\n";

            $admission->service_id = $admission->bed->service_id;
            $admission->save();

            echo "   ✓ Updated\n\n";
        } else {
            echo "Admission #{$admission->id}: ✓ Already correct\n\n";
        }
    }
}

echo "==============================================\n";
echo "SETUP COMPLETE\n";
echo "==============================================\n\n";

echo "NEXT STEPS:\n";
echo "1. Clear cache: php artisan cache:clear\n";
echo "2. Wait for tomorrow's auto billing OR\n";
echo "3. Run debug_bed_billing.php again to manually create today's bill\n";
echo "4. Check billing workbench - bills should now have proper amounts\n\n";
