<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$result = DB::select("SHOW COLUMNS FROM bank_statement_imports WHERE Field = 'status'");
print_r($result);
