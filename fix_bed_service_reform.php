<?php

/**
 * =============================================================================
 * BED SERVICE MODEL REFORM PROPOSAL
 * =============================================================================
 *
 * CURRENT PROBLEM:
 * ----------------
 * 1. Beds can have ANY service_id (e.g., bed #7 has service_id=3 which is "Urea")
 * 2. Billing workbench shows wrong service names for bed charges
 * 3. Old beds created before proper service creation logic don't have bed services
 * 4. No validation that bed->service belongs to bed_service_category
 *
 * CURRENT ARCHITECTURE (as of BedController):
 * -------------------------------------------
 * When creating a NEW bed:
 * 1. Creates service with category_id = bed_service_category_id (3)
 * 2. Creates service_price with bed price
 * 3. Links bed.service_id to new service
 *
 * THE REFORM:
 * -----------
 * 1. Add relationship validation in Bed model
 * 2. Create migration to fix orphan beds (no service) and mislinked beds (wrong category)
 * 3. Add observer to auto-create/sync bed service on bed save
 * 4. Add helper methods for billing to always get correct price source
 *
 * =============================================================================
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Bed;
use App\Models\service;
use App\Models\ServicePrice;
use App\Models\ServiceCategory;
use Illuminate\Support\Facades\DB;

echo "=============================================================================\n";
echo "BED SERVICE MODEL REFORM - DIAGNOSIS & FIX\n";
echo "=============================================================================\n\n";

$bedServiceCategoryId = appsettings('bed_service_category_id');
echo "Configuration:\n";
echo "   bed_service_category_id: {$bedServiceCategoryId}\n\n";

// Get bed service category name
$category = ServiceCategory::find($bedServiceCategoryId);
echo "   Category Name: " . ($category ? $category->category_name : 'NOT FOUND ❌') . "\n\n";

// Diagnose all beds
echo "=============================================================================\n";
echo "DIAGNOSING ALL BEDS\n";
echo "=============================================================================\n\n";

$beds = Bed::all();
$issues = [];

foreach ($beds as $bed) {
    $issue = [
        'bed_id' => $bed->id,
        'bed_name' => "{$bed->ward} - {$bed->name}",
        'bed_price' => $bed->price,
        'problems' => [],
        'needs_fix' => false,
    ];

    echo "Bed #{$bed->id}: {$bed->ward} - {$bed->name}\n";
    echo "   Price: ₦" . number_format($bed->price ?? 0, 2) . "\n";
    echo "   service_id: " . ($bed->service_id ?? 'NULL') . "\n";

    if (!$bed->service_id) {
        $issue['problems'][] = 'NO SERVICE LINKED';
        $issue['needs_fix'] = true;
        echo "   ❌ Problem: No service linked\n";
    } else {
        $srv = service::with('price')->find($bed->service_id);
        if (!$srv) {
            $issue['problems'][] = 'SERVICE NOT FOUND (orphan reference)';
            $issue['needs_fix'] = true;
            echo "   ❌ Problem: Service #{$bed->service_id} not found\n";
        } else {
            echo "   Service: {$srv->service_name}\n";
            echo "   Service Category: {$srv->category_id}\n";

            if ($srv->category_id != $bedServiceCategoryId) {
                $issue['problems'][] = "WRONG CATEGORY (is {$srv->category_id}, should be {$bedServiceCategoryId})";
                $issue['service_name'] = $srv->service_name;
                $issue['needs_fix'] = true;
                echo "   ❌ Problem: Wrong category (should be {$bedServiceCategoryId})\n";
            } else {
                echo "   ✓ Category correct\n";
            }

            if ($srv->price) {
                echo "   Service Price: ₦" . number_format($srv->price->sale_price ?? 0, 2) . "\n";
                if ($srv->price->sale_price != $bed->price) {
                    $issue['problems'][] = "PRICE MISMATCH (bed: {$bed->price}, service: {$srv->price->sale_price})";
                    echo "   ⚠️ Warning: Price mismatch\n";
                }
            } else {
                $issue['problems'][] = 'SERVICE HAS NO PRICE';
                echo "   ❌ Problem: Service has no price record\n";
            }
        }
    }

    if (!empty($issue['problems'])) {
        $issues[] = $issue;
    }
    echo "\n";
}

// Summary
echo "=============================================================================\n";
echo "SUMMARY\n";
echo "=============================================================================\n";
echo "Total beds: " . count($beds) . "\n";
echo "Beds with issues: " . count($issues) . "\n\n";

if (empty($issues)) {
    echo "✓ All beds are properly configured!\n";
    exit(0);
}

// Show issues
echo "Issues found:\n";
foreach ($issues as $issue) {
    echo "   Bed #{$issue['bed_id']} ({$issue['bed_name']}): " . implode(', ', $issue['problems']) . "\n";
}

echo "\n";

// Ask to fix
echo "=============================================================================\n";
echo "FIX AVAILABLE\n";
echo "=============================================================================\n";
echo "This will:\n";
echo "1. Create proper bed services for beds without services\n";
echo "2. Fix beds linked to wrong-category services\n";
echo "3. Sync service prices with bed prices\n\n";

echo "Proceed with fix? (yes/no): ";
$handle = fopen("php://stdin", "r");
$response = trim(fgets($handle));
fclose($handle);

if (strtolower($response) !== 'yes') {
    echo "Aborted.\n";
    exit(0);
}

echo "\n";
echo "=============================================================================\n";
echo "FIXING BEDS\n";
echo "=============================================================================\n\n";

$fixed = 0;

foreach ($issues as $issue) {
    if (!$issue['needs_fix']) continue;

    $bed = Bed::find($issue['bed_id']);
    echo "Fixing Bed #{$bed->id}: {$bed->ward} - {$bed->name}\n";

    DB::beginTransaction();
    try {
        // Check if bed already has a valid service in wrong category
        $existingService = null;
        if ($bed->service_id) {
            $existingService = service::find($bed->service_id);
        }

        // Create new bed service
        $serviceName = "Bed {$bed->name} {$bed->ward}" . ($bed->unit ? " {$bed->unit}" : "");
        $serviceCode = strtoupper(str_replace(' ', '-', $serviceName));

        // Check if service with this code already exists in bed category
        $newService = service::where('service_code', $serviceCode)
            ->where('category_id', $bedServiceCategoryId)
            ->first();

        if (!$newService) {
            $newService = new service();
            $newService->user_id = 1;
            $newService->category_id = $bedServiceCategoryId;
            $newService->service_name = $serviceName;
            $newService->service_code = $serviceCode;
            $newService->status = 1;
            $newService->price_assign = 1;
            $newService->save();
            echo "   ✓ Created service: {$serviceName} (ID: {$newService->id})\n";
        } else {
            echo "   → Using existing service: {$newService->service_name} (ID: {$newService->id})\n";
        }

        // Create or update service price
        $servicePrice = ServicePrice::where('service_id', $newService->id)->first();
        if (!$servicePrice) {
            $servicePrice = new ServicePrice();
            $servicePrice->service_id = $newService->id;
        }
        $servicePrice->cost_price = $bed->price ?? 0;
        $servicePrice->sale_price = $bed->price ?? 0;
        $servicePrice->max_discount = 0;
        $servicePrice->status = 1;
        $servicePrice->save();
        echo "   ✓ Set service price: ₦" . number_format($bed->price ?? 0, 2) . "\n";

        // Update bed to link to new service
        $oldServiceId = $bed->service_id;
        $bed->service_id = $newService->id;
        $bed->save();
        echo "   ✓ Updated bed.service_id: {$oldServiceId} → {$newService->id}\n";

        DB::commit();
        $fixed++;
        echo "   ✓ FIXED!\n\n";

    } catch (\Exception $e) {
        DB::rollBack();
        echo "   ❌ ERROR: {$e->getMessage()}\n\n";
    }
}

echo "=============================================================================\n";
echo "COMPLETE\n";
echo "=============================================================================\n";
echo "Fixed: {$fixed} beds\n\n";

// Now update existing admission records
echo "=============================================================================\n";
echo "UPDATING ACTIVE ADMISSIONS\n";
echo "=============================================================================\n";

$admissions = \App\Models\AdmissionRequest::where('discharged', 0)
    ->whereNotNull('bed_id')
    ->with('bed')
    ->get();

$admissionsFix = 0;
foreach ($admissions as $admission) {
    if ($admission->bed && $admission->service_id != $admission->bed->service_id) {
        echo "Admission #{$admission->id}: Updating service_id {$admission->service_id} → {$admission->bed->service_id}\n";
        $admission->service_id = $admission->bed->service_id;
        $admission->save();
        $admissionsFix++;
    }
}

echo "\nFixed: {$admissionsFix} admissions\n";

echo "\n=============================================================================\n";
echo "ALL DONE!\n";
echo "=============================================================================\n";
echo "Next steps:\n";
echo "1. Clear cache: php artisan cache:clear\n";
echo "2. Verify in billing workbench - beds should show proper service names\n";
echo "3. Future beds created via admin will auto-create proper services\n";
