<?php

/**
 * Quick test script to verify auditing is working
 * Run: php test_auditing.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Patient;
use OwenIt\Auditing\Models\Audit;

echo "Testing Laravel Auditing Integration...\n\n";

// Count current audits
$beforeCount = Audit::count();
echo "Current audit records: $beforeCount\n";

try {
    // Try to create a test record (then delete it)
    echo "\nTesting audit creation...\n";

    // Get first patient and update something
    $patient = Patient::first();
    if ($patient) {
        $oldAddress = $patient->address;
        $patient->address = "Test Address " . time();
        $patient->save();

        echo "Updated patient ID: {$patient->id}\n";

        // Check if audit was created
        sleep(1); // Give it a moment
        $afterCount = Audit::count();
        echo "Audit records after update: $afterCount\n";

        if ($afterCount > $beforeCount) {
            $diff = $afterCount - $beforeCount;
            echo "✓ SUCCESS: Audit trail is working! ($diff new audit(s) created)\n";

            // Show the latest audit
            $latestAudit = Audit::latest()->first();
            echo "\nLatest Audit Details:\n";
            echo "  Event: {$latestAudit->event}\n";
            echo "  Model: " . class_basename($latestAudit->auditable_type) . "\n";
            echo "  Model ID: {$latestAudit->auditable_id}\n";
            echo "  User ID: {$latestAudit->user_id}\n";
            echo "  Created: {$latestAudit->created_at}\n";
        } else {
            echo "✗ WARNING: No new audit record was created\n";
        }

        // Restore the original value
        $patient->address = $oldAddress;
        $patient->save();
        echo "\n✓ Restored original data\n";
    } else {
        echo "No patients found in database to test with\n";
    }

} catch (Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\nTest complete!\n";
