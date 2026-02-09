<?php
/**
 * Daily Bed Billing Explanation & Test
 *
 * This script explains how the daily bed billing works and tests
 * that it will run consistently.
 */

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

echo "=============================================================\n";
echo "   DAILY BED BILLING MECHANISM EXPLAINED\n";
echo "=============================================================\n\n";

echo "HOW IT WORKS:\n";
echo "-------------\n";
echo "1. The processDailyBedBills() method runs in AppServiceProvider::boot()\n";
echo "2. This method is called on EVERY WEB REQUEST to your Laravel application\n";
echo "3. However, it uses a cache lock to ensure it only processes ONCE per day\n\n";

echo "CACHE MECHANISM:\n";
echo "----------------\n";
$cacheKey = 'bed_billing_processed_' . Carbon::today()->format('Y-m-d');
echo "Cache Key: $cacheKey\n";
$isCached = Cache::has($cacheKey);
echo "Currently Cached: " . ($isCached ? 'YES (already processed today)' : 'NO (will process on next request)') . "\n\n";

echo "WHEN BILLS DROP:\n";
echo "----------------\n";
echo "Bills are created on the FIRST WEB REQUEST after midnight (00:00) each day.\n";
echo "The exact time depends on when someone first accesses your application.\n\n";

echo "TIMELINE EXAMPLE:\n";
echo "-----------------\n";
$now = Carbon::now();
$tomorrow = Carbon::tomorrow();
$cacheExpiry = $tomorrow->format('Y-m-d H:i:s');

echo "Current Time: " . $now->format('Y-m-d H:i:s') . "\n";
echo "Cache Expires: " . $cacheExpiry . " (midnight tomorrow)\n";
echo "Next Billing Window: " . $tomorrow->format('Y-m-d') . " 00:00:00\n\n";

echo "POTENTIAL ISSUES:\n";
echo "-----------------\n";
echo "1. If NO ONE accesses the app after midnight, bills won't be created\n";
echo "2. If your cache driver resets (e.g., server restart), bills might duplicate\n";
echo "3. There's no scheduled task - it relies on user activity\n\n";

echo "=============================================================\n";
echo "   CURRENT BILLING STATUS\n";
echo "=============================================================\n\n";

// Check active admissions
$admissions = \App\Models\AdmissionRequest::with(['patient.user', 'bed'])
    ->where('discharged', 0)
    ->where('status', 1)
    ->whereNotNull('bed_id')
    ->whereNotNull('service_id')
    ->get();

echo "Active Admissions with Beds: " . $admissions->count() . "\n\n";

foreach ($admissions as $admission) {
    $patient = $admission->patient;
    $user = $patient->user ?? null;

    echo "Patient: " . ($patient->surname ?? 'N/A') . " " . ($patient->firstname ?? '') . "\n";
    echo "  Admission ID: {$admission->id}\n";
    echo "  Bed ID: {$admission->bed_id}\n";
    echo "  Service ID: {$admission->service_id}\n";

    // Check today's bill
    if ($user) {
        $todayBill = \App\Models\ProductOrServiceRequest::where('user_id', $user->id)
            ->where('service_id', $admission->service_id)
            ->whereDate('created_at', Carbon::today())
            ->first();

        echo "  Today's Bill: " . ($todayBill ? "Yes (Bill #{$todayBill->id}, ₦" . number_format($todayBill->payable_amount, 2) . ")" : "Not yet created") . "\n";
    }
    echo "\n";
}

echo "=============================================================\n";
echo "   RECOMMENDATION: USE LARAVEL SCHEDULER\n";
echo "=============================================================\n\n";

echo "For CONSISTENT billing at a SPECIFIC time, add this to:\n";
echo "app/Console/Kernel.php in the schedule() method:\n\n";

echo "protected function schedule(Schedule \$schedule)\n";
echo "{\n";
echo "    // Run bed billing at 1:00 AM daily\n";
echo "    \$schedule->call(function () {\n";
echo "        app(\App\Providers\AppServiceProvider::class)->processDailyBedBillsForced();\n";
echo "    })->dailyAt('01:00')\n";
echo "      ->name('daily-bed-billing')\n";
echo "      ->withoutOverlapping();\n";
echo "}\n\n";

echo "Then set up a cron job (Windows Task Scheduler) to run:\n";
echo "php artisan schedule:run\n";
echo "Every minute.\n\n";

echo "=============================================================\n";
echo "   MANUAL BILLING TRIGGER TEST\n";
echo "=============================================================\n\n";

echo "To manually trigger billing right now (for testing):\n";
echo "1. Clear the cache: php artisan cache:forget $cacheKey\n";
echo "2. Visit any page on your application\n";
echo "3. Or run: php artisan tinker --execute=\"app()->make('App\Providers\AppServiceProvider')->boot();\"\n\n";

echo "Would you like me to clear the cache and force billing? (This script is read-only)\n";
