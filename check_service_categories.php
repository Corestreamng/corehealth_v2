<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ServiceCategory;

echo "=== SERVICE CATEGORIES ===\n\n";

$cats = ServiceCategory::all(['id', 'category_name']);
foreach($cats as $c) {
    echo $c->id . ': ' . $c->category_name . PHP_EOL;
}

echo "\n=== BED SERVICE CATEGORY ID FROM APPSETTINGS ===\n";
echo "bed_service_category_id: " . appsettings('bed_service_category_id') . "\n";

echo "\n=== PROCEDURE SERVICE CATEGORY ID ===\n";
// Check if there's a procedure category
$procedureCategory = ServiceCategory::where('category_name', 'LIKE', '%procedure%')->first();
echo "Procedure category: " . ($procedureCategory ? $procedureCategory->id . ': ' . $procedureCategory->category_name : 'Not found') . "\n";

echo "\n=== CURRENT BEDS & THEIR SERVICES ===\n";
$beds = \App\Models\Bed::with('service.category')->get();
foreach ($beds as $bed) {
    echo "Bed #{$bed->id} '{$bed->name}' - Service ID: {$bed->service_id}";
    if ($bed->service) {
        echo " - {$bed->service->service_name} (Category: {$bed->service->category_id}";
        if ($bed->service->category) {
            echo " - {$bed->service->category->category_name}";
        }
        echo ")";
    }
    echo "\n";
}
