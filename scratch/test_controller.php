<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = new \App\Http\Controllers\AuditWorkbenchController();
echo "Controller Instantiated Successfully\n";
echo "Count of responsibility categories: " . count(\App\Http\Controllers\AuditWorkbenchController::$responsibilities) . "\n";
