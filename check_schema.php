<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cols = \Illuminate\Support\Facades\Schema::getColumnListing('product_or_service_requests');
echo implode(', ', $cols) . "\n";

// Check payments table too
$pcols = \Illuminate\Support\Facades\Schema::getColumnListing('payments');
echo "\npayments columns:\n" . implode(', ', $pcols) . "\n";
