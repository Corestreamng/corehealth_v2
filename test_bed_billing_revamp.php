<?php
/**
 * Test the revamped bed billing system
 */

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

echo "=============================================================\n";
echo "   REVAMPED BED BILLING - SHARED HOSTING COMPATIBLE\n";
echo "=============================================================\n\n";

echo "KEY IMPROVEMENTS:\n";
echo "-----------------\n";
echo "1. Uses DATABASE (application_status.last_bed_billing_date) instead of cache\n";
echo "2. Uses FILE LOCK to prevent concurrent execution\n";
echo "3. Individual bill checks prevent duplicates even if tracking fails\n";
echo "4. Auto-creates the tracking column if it doesn't exist\n\n";

// Check current state
$lastBillingDate = DB::table('application_status')->where('id', 1)->value('last_bed_billing_date');
echo "CURRENT STATE:\n";
echo "--------------\n";
echo "Last Billing Date in DB: " . ($lastBillingDate ?? 'NOT SET') . "\n";
echo "Today's Date: " . Carbon::today()->format('Y-m-d') . "\n";
echo "Will Bill Run? " . ($lastBillingDate !== Carbon::today()->format('Y-m-d') ? 'YES' : 'NO (already done today)') . "\n\n";

// Check active admissions
$admissions = \App\Models\AdmissionRequest::with(['patient.user', 'bed'])
    ->where('discharged', 0)
    ->where('status', 1)
    ->whereNotNull('bed_id')
    ->whereNotNull('service_id')
    ->get();

echo "ACTIVE ADMISSIONS: " . $admissions->count() . "\n\n";

foreach ($admissions as $admission) {
    $patient = $admission->patient;
    $user = $patient->user ?? null;
    $bed = $admission->bed;

    echo "► " . ($patient->surname ?? 'N/A') . " " . ($patient->firstname ?? '') . "\n";
    echo "  Admission #{$admission->id} | Bed: " . ($bed->name ?? 'N/A') . " | Service ID: {$admission->service_id}\n";

    if ($user) {
        // Check today's bill
        $todayBill = \App\Models\ProductOrServiceRequest::where('user_id', $user->id)
            ->where('service_id', $admission->service_id)
            ->whereDate('created_at', Carbon::today())
            ->first();

        if ($todayBill) {
            echo "  ✓ Today's Bill: #{$todayBill->id} - ₦" . number_format($todayBill->payable_amount, 2) . "\n";
        } else {
            echo "  ○ No bill yet today (will be created on next request)\n";
        }
    }
    echo "\n";
}

echo "=============================================================\n";
echo "   HOW BILLING TIMING WORKS\n";
echo "=============================================================\n\n";

echo "WHEN BILLS ARE CREATED:\n";
echo "-----------------------\n";
echo "Bills are created on the FIRST web request after midnight each day.\n";
echo "The exact time depends on when someone first accesses your application.\n\n";

echo "EXAMPLE TIMELINE:\n";
echo "-----------------\n";
echo "Day 1: 11:59 PM - Last request of the day\n";
echo "Day 2: 12:00 AM - Midnight (no automatic trigger)\n";
echo "Day 2: 07:30 AM - First nurse logs in → Bills created!\n";
echo "Day 2: 08:00 AM onwards - All subsequent requests skip billing\n\n";

echo "TO ENSURE CONSISTENT BILLING:\n";
echo "-----------------------------\n";
echo "Option 1: Have someone access the app early morning (7-8 AM)\n";
echo "Option 2: Use an external uptime monitor (UptimeRobot, Pingdom)\n";
echo "         that pings your site every hour - this triggers billing!\n";
echo "Option 3: Create a simple cron on hosting (if available):\n";
echo "         curl https://yoursite.com/api/health-check\n\n";

echo "=============================================================\n";
echo "   MANUAL TRIGGER TEST\n";
echo "=============================================================\n\n";

echo "To reset and test billing manually:\n";
echo "1. Run: php reset_and_test_billing.php (creates this script below)\n\n";

// Create reset script
$resetScript = <<<'PHP'
<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Resetting last_bed_billing_date to yesterday...\n";
$yesterday = \Carbon\Carbon::yesterday()->format('Y-m-d');
DB::table('application_status')->where('id', 1)->update(['last_bed_billing_date' => $yesterday]);
echo "Done! Next web request will trigger billing.\n";
PHP;

file_put_contents('reset_bed_billing.php', $resetScript);
echo "Created: reset_bed_billing.php\n\n";

echo "Lock file location: " . storage_path('framework/bed_billing.lock') . "\n";
