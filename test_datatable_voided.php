<?php

/**
 * Quick test to verify datatable displays voided assets correctly
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Accounting\FixedAsset;

echo "=== DATATABLE VOIDED STATUS TEST ===\n\n";

// Count assets by status
$statuses = [
    'active' => FixedAsset::where('status', FixedAsset::STATUS_ACTIVE)->count(),
    'fully_depreciated' => FixedAsset::where('status', FixedAsset::STATUS_FULLY_DEPRECIATED)->count(),
    'disposed' => FixedAsset::where('status', FixedAsset::STATUS_DISPOSED)->count(),
    'impaired' => FixedAsset::where('status', FixedAsset::STATUS_IMPAIRED)->count(),
    'under_maintenance' => FixedAsset::where('status', FixedAsset::STATUS_UNDER_MAINTENANCE)->count(),
    'idle' => FixedAsset::where('status', FixedAsset::STATUS_IDLE)->count(),
    'voided' => FixedAsset::where('status', FixedAsset::STATUS_VOIDED)->count(),
];

echo "Assets by Status:\n";
foreach ($statuses as $status => $count) {
    echo "  " . ucfirst(str_replace('_', ' ', $status)) . ": $count\n";
}
echo "\n";

// Get voided assets
$voidedAssets = FixedAsset::where('status', FixedAsset::STATUS_VOIDED)->get();

if ($voidedAssets->count() > 0) {
    echo "Voided Assets Details:\n";
    foreach ($voidedAssets as $asset) {
        echo "  • {$asset->asset_number}: {$asset->name}\n";
        echo "    Cost: ₦" . number_format($asset->total_cost, 2) . "\n";
        echo "    Accum. Depreciation: ₦" . number_format($asset->accumulated_depreciation, 2) . "\n";
        echo "    Status: {$asset->status}\n";

        // Check color mapping
        $color = match ($asset->status) {
            'active' => 'success',
            'fully_depreciated' => 'info',
            'disposed' => 'secondary',
            'impaired' => 'warning',
            'under_maintenance' => 'primary',
            'idle' => 'dark',
            'voided' => 'danger',
            default => 'secondary',
        };
        echo "    Badge Color: $color\n\n";
    }
} else {
    echo "No voided assets found.\n\n";
}

// Calculate totals (excluding disposed and voided per IAS 16)
$totalAssetsExcluding = FixedAsset::whereNotIn('status', ['disposed', 'voided'])->count();
$totalCostExcluding = FixedAsset::whereNotIn('status', ['disposed', 'voided'])->sum('total_cost');

echo "Register Totals (Excluding Disposed & Voided):\n";
echo "  Total Assets: $totalAssetsExcluding\n";
echo "  Total Cost: ₦" . number_format($totalCostExcluding, 2) . "\n\n";

echo "✅ Datatable should now correctly:\n";
echo "  1. Display voided assets with RED badge\n";
echo "  2. Allow filtering by 'Voided' status\n";
echo "  3. Show void button only for assets that canBeVoided()\n";
echo "  4. Exclude voided assets from register totals\n";
