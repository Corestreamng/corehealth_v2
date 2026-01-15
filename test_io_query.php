<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\IntakeOutputPeriod;
use App\Models\patient;
use Carbon\Carbon;

// Get patient by file number
$fileNo = '9426';
$patient = Patient::where('file_no', $fileNo)->first();

if (!$patient) {
    echo "ERROR: Patient with file number $fileNo not found!\n";
    exit(1);
}

$patientId = $patient->id;

// Use today's date range (today only)
$today = Carbon::today();
$startDateStr = $today->format('Y-m-d');
$endDateStr = $today->copy()->addDay()->format('Y-m-d'); // Add 1 day to catch future-dated records

// Parse with endOfDay to include all records on the end date
$startDate = Carbon::parse($startDateStr)->startOfDay();
$endDate = Carbon::parse($endDateStr)->endOfDay();

echo "=== Testing I/O Period Query for Patient File #$fileNo ===\n";
echo "Patient ID: $patientId\n";
echo "Patient Name: {$patient->name}\n";
echo "Date Range: start=$startDateStr, end=$endDateStr\n";
echo "Parsed to: start=$startDate, end=$endDate\n\n";

echo "NOTE: Testing with extended date range to catch records dated in the future\n\n";

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
    echo "    Records: " . $p->records->count() . "\n";
    foreach ($p->records as $r) {
        echo "      * ID: {$r->id}, Type: {$r->io_type}, Time: {$r->recorded_at}, Amount: {$r->amount} {$r->unit}\n";
    }
}

echo "\n=== Detailed Period Data ===\n";
if ($fluidPeriods->count() > 0) {
    echo "\nFirst Fluid Period Details:\n";
    $firstFluid = $fluidPeriods->first();
    echo "  ID: {$firstFluid->id}\n";
    echo "  Patient ID: {$firstFluid->patient_id}\n";
    echo "  Type: {$firstFluid->type}\n";
    echo "  Started At: {$firstFluid->started_at}\n";
    echo "  Ended At: " . ($firstFluid->ended_at ?? 'NULL') . "\n";
    echo "  Records Count: " . $firstFluid->records->count() . "\n";
    echo "  Nurse: " . ($firstFluid->nurse ? $firstFluid->nurse->name : 'NULL') . "\n";
}

if ($solidPeriods->count() > 0) {
    echo "\nFirst Solid Period Details:\n";
    $firstSolid = $solidPeriods->first();
    echo "  ID: {$firstSolid->id}\n";
    echo "  Patient ID: {$firstSolid->patient_id}\n";
    echo "  Type: {$firstSolid->type}\n";
    echo "  Started At: {$firstSolid->started_at}\n";
    echo "  Ended At: " . ($firstSolid->ended_at ?? 'NULL') . "\n";
    echo "  Records Count: " . $firstSolid->records->count() . "\n";
    echo "  Nurse: " . ($firstSolid->nurse ? $firstSolid->nurse->name : 'NULL') . "\n";
}

echo "\n=== JSON Response Structure (First Period Only) ===\n";
$response = [
    'fluidPeriods' => $fluidPeriods->take(1),
    'solidPeriods' => $solidPeriods->take(1)
];
echo json_encode($response, JSON_PRETTY_PRINT);

echo "\n\n=== All Records for All Periods ===\n";
echo "Fluid Records:\n";
foreach ($fluidPeriods as $period) {
    echo "Period {$period->id}:\n";
    foreach ($period->records as $record) {
        echo "  - Record ID: {$record->id}, Type: {$record->io_type}, Amount: {$record->amount} {$record->unit}, Time: {$record->recorded_at}\n";
    }
}

echo "\nSolid Records:\n";
foreach ($solidPeriods as $period) {
    echo "Period {$period->id}:\n";
    foreach ($period->records as $record) {
        echo "  - Record ID: {$record->id}, Type: {$record->io_type}, Amount: {$record->amount} {$record->unit}, Time: {$record->recorded_at}\n";
    }
}
