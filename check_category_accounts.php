<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Fixed Asset Categories and their GL Account Mappings:\n";
echo str_repeat("=", 80) . "\n\n";

$categories = \App\Models\Accounting\FixedAssetCategory::with([
    'assetAccount',
    'depreciationAccount',
    'expenseAccount'
])->get();

foreach ($categories as $cat) {
    echo "Category: {$cat->name} ({$cat->code})\n";
    echo "  Asset Account: " . ($cat->assetAccount ? $cat->assetAccount->code . " - " . $cat->assetAccount->name : 'NOT SET') . "\n";
    echo "  Depreciation Account: " . ($cat->depreciationAccount ? $cat->depreciationAccount->code . " - " . $cat->depreciationAccount->name : 'NOT SET') . "\n";
    echo "  Expense Account: " . ($cat->expenseAccount ? $cat->expenseAccount->code . " - " . $cat->expenseAccount->name : 'NOT SET') . "\n";
    echo "\n";
}
