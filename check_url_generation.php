<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Simulate a request from different hosts
$testUrls = [
    'http://localhost:8000',
    'http://127.0.0.1:8000',
    'https://corehealth.example.com',
];

echo "Testing asset() URL generation:\n\n";

// Test with default request
echo "Default (CLI): " . asset('storage/bank-statements/test.pdf') . "\n";
echo "URL helper: " . url('storage/bank-statements/test.pdf') . "\n";
echo "Storage URL: " . Storage::disk('public')->url('bank-statements/test.pdf') . "\n";

echo "\nThe asset() helper should automatically use the correct host from the incoming HTTP request.\n";
echo "In CLI context, it uses APP_URL from .env which is: " . config('app.url') . "\n";
