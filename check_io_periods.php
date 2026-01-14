<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\IntakeOutputPeriod;

echo "=== Intake/Output Periods Check ===\n\n";
echo "Total Periods: " . IntakeOutputPeriod::count() . "\n\n";

$periods = IntakeOutputPeriod::orderBy('id', 'desc')->take(10)->get();

if ($periods->isEmpty()) {
    echo "No periods found in database!\n";
} else {
    echo "Last 10 Periods:\n";
    echo str_repeat('-', 80) . "\n";
    foreach ($periods as $p) {
        echo "ID: {$p->id}, Patient: {$p->patient_id}, Type: {$p->type}, Started: {$p->started_at}, Ended: {$p->ended_at}\n";
    }
}
