<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\IntakeOutputPeriod;
use Carbon\Carbon;

$patientId = 1; // Test with patient ID 1

// Simulate frontend date range (7 days ago to today)
$startDateStr = '2026-01-07';
$endDateStr = '2026-01-14';

// Parse with endOfDay to include all records on the end date
$startDate = Carbon::parse($startDateStr)->startOfDay();
$endDate = Carbon::parse($endDateStr)->endOfDay();

echo "=== Testing I/O Period Query ===\n";
echo "Patient ID: $patientId\n";
echo "Frontend sends: start=$startDateStr, end=$endDateStr\n";
echo "Parsed to: start=$startDate, end=$endDate\n\n";

$fluidPeriods = IntakeOutputPeriod::with(['records', 'nurse'])
    ->where('patient_id', $patientId)
    ->where('type', 'fluid')
    ->where(function($q) use ($startDate, $endDate) {
        // Include periods that:
        // 1. Started within the date range
        $q->whereBetween('started_at', [$startDate, $endDate])
          // 2. OR ended within the date range
          ->orWhereBetween('ended_at', [$startDate, $endDate])
          // 3. OR are still active (not ended) and started before or within the range
          ->orWhere(function($innerQ) use ($endDate) {
              $innerQ->whereNull('ended_at')
                     ->where('started_at', '<=', $endDate);
          })
          // 4. OR span the entire date range (started before and ended after)
          ->orWhere(function($innerQ) use ($startDate, $endDate) {
              $innerQ->where('started_at', '<=', $startDate)
                     ->where('ended_at', '>=', $endDate);
          });
    })
    ->orderBy('started_at', 'desc')
    ->get();

echo "Fluid Periods Found: " . $fluidPeriods->count() . "\n";
foreach ($fluidPeriods as $p) {
    echo "  - ID: {$p->id}, Started: {$p->started_at}, Ended: " . ($p->ended_at ?? 'NULL') . "\n";
}

$solidPeriods = IntakeOutputPeriod::with(['records', 'nurse'])
    ->where('patient_id', $patientId)
    ->where('type', 'solid')
    ->where(function($q) use ($startDate, $endDate) {
        $q->whereBetween('started_at', [$startDate, $endDate])
          ->orWhereBetween('ended_at', [$startDate, $endDate])
          ->orWhere(function($innerQ) use ($endDate) {
              $innerQ->whereNull('ended_at')
                     ->where('started_at', '<=', $endDate);
          })
          ->orWhere(function($innerQ) use ($startDate, $endDate) {
              $innerQ->where('started_at', '<=', $startDate)
                     ->where('ended_at', '>=', $endDate);
          });
    })
    ->orderBy('started_at', 'desc')
    ->get();

echo "\nSolid Periods Found: " . $solidPeriods->count() . "\n";
foreach ($solidPeriods as $p) {
    echo "  - ID: {$p->id}, Started: {$p->started_at}, Ended: " . ($p->ended_at ?? 'NULL') . "\n";
}

echo "\n=== JSON Response Structure ===\n";
echo json_encode([
    'fluidPeriods' => $fluidPeriods,
    'solidPeriods' => $solidPeriods
], JSON_PRETTY_PRINT);
