<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Bank;

echo "Banks in database:\n";
echo "=====================================\n";
foreach(Bank::all() as $b) {
    echo "ID: {$b->id} | Name: {$b->name} | Account ID: {$b->account_id}\n";
}
echo "=====================================\n";
