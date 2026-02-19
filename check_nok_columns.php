<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "=== PATIENTS table next_of_kin columns ===\n";
$cols = DB::select("SHOW COLUMNS FROM patients WHERE Field LIKE 'next_of_kin%'");
foreach ($cols as $c) {
    echo "{$c->Field} => {$c->Type} (Null: {$c->Null})\n";
}

echo "\n=== USERS table next_of_kin columns ===\n";
$cols = DB::select("SHOW COLUMNS FROM users WHERE Field LIKE 'next_of_kin%'");
foreach ($cols as $c) {
    echo "{$c->Field} => {$c->Type} (Null: {$c->Null})\n";
}
