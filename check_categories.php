<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$total = \App\Models\Accounting\FixedAssetCategory::count();
$active = \App\Models\Accounting\FixedAssetCategory::where('is_active', true)->count();
$inactive = \App\Models\Accounting\FixedAssetCategory::where('is_active', false)->count();

echo "Total categories: {$total}\n";
echo "Active categories: {$active}\n";
echo "Inactive categories: {$inactive}\n";

if ($total > 0) {
    echo "\nCategories list:\n";
    $categories = \App\Models\Accounting\FixedAssetCategory::all();
    foreach ($categories as $cat) {
        echo "  - {$cat->name} (Code: {$cat->code}, Active: " . ($cat->is_active ? 'Yes' : 'No') . ")\n";
    }
}
