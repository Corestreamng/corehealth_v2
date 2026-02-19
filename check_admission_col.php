<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$cols = DB::select("SHOW COLUMNS FROM admission_requests WHERE Field = 'admission_reason'");
foreach ($cols as $c) {
    echo "{$c->Field} => {$c->Type} (Null: {$c->Null})\n";
}
