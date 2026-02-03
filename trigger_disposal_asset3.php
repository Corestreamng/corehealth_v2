<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Accounting\FixedAssetDisposal;
use App\Observers\Accounting\FixedAssetDisposalObserver;
use Illuminate\Support\Facades\Log;

try {
    echo "=== Manually Triggering Disposal Observer for Asset #3 ===\n\n";

    $disposalIds = [1, 2, 4];
    $observer = new FixedAssetDisposalObserver();

    foreach ($disposalIds as $id) {
        $disposal = FixedAssetDisposal::find($id);

        if (!$disposal) {
            echo "Disposal #{$id} not found!\n";
            continue;
        }

        echo "Processing Disposal #{$id}:\n";
        echo "  Type: {$disposal->disposal_type}\n";
        echo "  Status: {$disposal->status}\n";
        echo "  Current JE ID: " . ($disposal->journal_entry_id ?? 'NULL') . "\n";

        if ($disposal->status === 'completed' && !$disposal->journal_entry_id) {
            try {
                echo "  Calling observer->created()...\n";
                $observer->created($disposal);

                $disposal->refresh();
                echo "  Result: JE ID = " . ($disposal->journal_entry_id ?? 'STILL NULL') . "\n";

                if ($disposal->journal_entry_id) {
                    $je = $disposal->journalEntry;
                    echo "  ✓ Success! Created JE #{$je->entry_number}\n";
                } else {
                    echo "  ✗ Failed - no JE created\n";
                }
            } catch (\Exception $e) {
                echo "  ✗ Error: {$e->getMessage()}\n";
                echo "     " . $e->getFile() . ":{$e->getLine()}\n";
            }
        } else {
            if ($disposal->status !== 'completed') {
                echo "  Skipped: Status is '{$disposal->status}', not 'completed'\n";
            } else {
                echo "  Skipped: Already has JE #{$disposal->journal_entry_id}\n";
            }
        }

        echo "\n";
    }

} catch (\Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
