<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Accounting\FixedAsset;
use Illuminate\Support\Facades\DB;

echo "Checking void and disposal data storage...\n";
echo str_repeat("=", 70) . "\n\n";

// Check for voided assets
$voidedAssets = FixedAsset::where('status', 'voided')->with('journalEntry')->get();
echo "Voided Assets: " . $voidedAssets->count() . "\n";
if ($voidedAssets->count() > 0) {
    $asset = $voidedAssets->first();
    echo "Sample Asset: {$asset->asset_number} - {$asset->name}\n";
    echo "Journal Entry: " . ($asset->journal_entry_id ? "ID {$asset->journal_entry_id}" : "None") . "\n";
}
echo "\n";

// Check for disposed assets
$disposedAssets = FixedAsset::where('status', 'disposed')->with(['disposals', 'disposals.journalEntry'])->get();
echo "Disposed Assets: " . $disposedAssets->count() . "\n";
if ($disposedAssets->count() > 0) {
    $asset = $disposedAssets->first();
    echo "Sample Asset: {$asset->asset_number} - {$asset->name}\n";
    echo "Disposal Records: " . $asset->disposals->count() . "\n";
    if ($asset->disposals->count() > 0) {
        $disposal = $asset->disposals->first();
        echo "Disposal Type: {$disposal->disposal_type}\n";
        echo "Disposal Date: {$disposal->disposal_date->format('Y-m-d')}\n";
        echo "Proceeds: ₦" . number_format($disposal->disposal_proceeds, 2) . "\n";
        echo "Book Value: ₦" . number_format($disposal->book_value_at_disposal, 2) . "\n";
        echo "Gain/Loss: ₦" . number_format($disposal->gain_loss_on_disposal, 2) . "\n";
        echo "Journal Entry: " . ($disposal->journal_entry_id ? "ID {$disposal->journal_entry_id}" : "None") . "\n";
    }
}
echo "\n";

// Check activity logs for void actions
$activityLogs = DB::table('activity_log')
    ->where('subject_type', 'App\Models\Accounting\FixedAsset')
    ->where('description', 'like', '%void%')
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get(['id', 'subject_id', 'description', 'properties', 'created_at']);

echo "Recent Void Activity Logs: " . $activityLogs->count() . "\n";
foreach ($activityLogs as $log) {
    echo "  - Log ID {$log->id}: {$log->description}\n";
    if ($log->properties) {
        $props = json_decode($log->properties, true);
        if (isset($props['reason'])) {
            echo "    Reason: {$props['reason']}\n";
        }
    }
}
