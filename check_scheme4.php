<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$schemeId = 4;
$scheme = App\Models\HmoScheme::find($schemeId);
echo "Scheme {$schemeId}: " . ($scheme ? "{$scheme->name} (status={$scheme->status})" : "NOT FOUND") . PHP_EOL;

// All HMOs in scheme regardless of status
$allHmos = App\Models\Hmo::where('hmo_scheme_id', $schemeId)->get(['id','name','status']);
echo "All HMOs in scheme: {$allHmos->count()}" . PHP_EOL;
foreach ($allHmos as $h) {
    echo "  id={$h->id} | name={$h->name} | status={$h->status}" . PHP_EOL;
}

// Only status=1
$activeHmos = App\Models\Hmo::where('hmo_scheme_id', $schemeId)->where('status', 1)->get(['id','name','status']);
echo "Active HMOs (status=1): {$activeHmos->count()}" . PHP_EOL;

// Check distinct status values
$statuses = App\Models\Hmo::where('hmo_scheme_id', $schemeId)->distinct()->pluck('status');
echo "Distinct status values: " . $statuses->implode(', ') . PHP_EOL;
