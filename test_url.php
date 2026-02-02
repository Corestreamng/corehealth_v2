<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test the URL generation
$testPath = 'bank-statements/Bsj009deUPasVuCJxWRvzQS889ZTlepNFdWAC1Pw.pdf';

echo "APP_URL: " . config('app.url') . "\n";
echo "storage_path(): " . storage_path('app/public/' . $testPath) . "\n";
echo "url() result: " . url('storage/' . $testPath) . "\n";
echo "File exists: " . (file_exists(storage_path('app/public/' . $testPath)) ? 'Yes' : 'No') . "\n";

// Check public/storage symlink
echo "public/storage symlink exists: " . (is_link(public_path('storage')) ? 'Yes' : 'No') . "\n";
echo "Symlink target: " . (is_link(public_path('storage')) ? readlink(public_path('storage')) : 'N/A') . "\n";
