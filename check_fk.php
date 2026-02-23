<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$fks = DB::select("
    SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'medication_administrations'
      AND REFERENCED_TABLE_NAME IS NOT NULL
");

echo "Foreign keys on medication_administrations:\n";
foreach ($fks as $fk) {
    echo "  {$fk->CONSTRAINT_NAME} => {$fk->COLUMN_NAME} -> {$fk->REFERENCED_TABLE_NAME}\n";
}

if (empty($fks)) {
    echo "  (none found)\n";
}
