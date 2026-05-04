<?php

/**
 * Import Stock Data Script
 * 
 * Usage: php import_stock_data.php <csv_file> [--apply]
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockBatch;
use App\Models\StockBatchTransaction;
use App\Models\Store;
use App\Models\ProductPackaging;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$filename = $argv[1] ?? null;
$apply    = in_array('--apply', $argv);
$centralStoreId = 3; // Hardcoded Central Store ID

if (!$filename || !file_exists($filename)) {
    echo "Usage: php import_stock_data.php <csv_file> [--apply]\n";
    exit(1);
}

if (!$apply) {
    echo "*** DRY RUN — pass --apply to commit changes ***\n\n";
}

// ─── 1. LOAD DATA ────────────────────────────────────────

$handle = fopen($filename, 'r');
$header = fgetcsv($handle); 
// product_name, product_code, category_name, description, unit, initial_quantity, store_name, batch_number, expiry_date, is_active

$rows = [];
while (($data = fgetcsv($handle)) !== false) {
    if (count($data) < 9) continue;
    $rows[] = [
        'name'     => trim($data[0]),
        'code'     => trim($data[1]),
        'category' => trim($data[2]),
        'base_unit'=> trim($data[3]), // description column is the base unit name
        'unit_mult'=> (float) $data[4], // unit column is the multiplier
        'qty_packs'=> (float) $data[5], // initial_quantity is no of packs
        'batch_no' => trim($data[7]),
        'expiry'   => trim($data[8]),
    ];
}
fclose($handle);

echo "Parsed " . count($rows) . " data rows from CSV.\n\n";

// ─── 2. HELPERS ──────────────────────────────────────────

// ─── 2. HELPERS ──────────────────────────────────────────

function normalizeName(string $name): string
{
    $name = mb_strtolower(trim($name));
    // Remove (ZOVIRAX) and similar suffixes in parentheses
    $name = preg_replace('/\s*\(.*\)$/', '', $name);
    // Remove common non-essential prefixes/suffixes like TAB, CAP, INJ, SYRUP
    $name = preg_replace('/\b(tab|caps|inj|susp|syr|cream|gel|oint|supp|drop|inj\.)\b/', '', $name);
    // Remove extra internal spaces
    $name = preg_replace('/\s+/', ' ', $name);
    return trim($name);
}

function getStrengthTokens(string $name): array
{
    // Extract numbers followed by mg, g, ml, etc. (e.g., 500mg, 10mg, 1g)
    preg_match_all('/\d+\s*(mg|g|ml|iu|%)/i', $name, $matches);
    return array_map('mb_strtolower', $matches[0]);
}

function parseExpiry(string $expiry): ?string
{
    try {
        // Expected format: May-27
        return Carbon::createFromFormat('M-y', $expiry)->startOfMonth()->format('Y-m-d');
    } catch (\Exception $e) {
        return null;
    }
}

// ─── 3. PROCESS ROWS ────────────────────────────────────

// Load all products into memory for fuzzy matching
$allProducts = Product::withoutGlobalScopes()->get();

// Synonym mapping for common brand/generic variations
$synonyms = [
    'amoxyl' => 'amoxicillin',
    'paxil'  => 'paroxetine',
    'pcm'    => 'paracetamol',
    'flagyl' => 'metronidazole',
    'coartem' => 'artemether',
    'aldactone' => 'spironolactone',
    'atenelol' => 'atenolol', // fixing typo
    'bis0prolol' => 'bisoprolol', // fixing typo
];

$stats = [
    'matched'   => 0,
    'unmatched' => 0,
    'imported'  => 0,
];

foreach ($rows as $row) {
    $csvName = $row['name'];
    $csvCode = $row['code'];

    echo "Processing: $csvName ($csvCode)... ";

    $product = null;

    // A. Direct Matches
    $product = $allProducts->where('product_code', $csvCode)->first();
    if (!$product) $product = $allProducts->where('product_name', $csvName)->first();

    // B. Normalized Matching
    if (!$product) {
        $normCsvName = normalizeName($csvName);
        $product = $allProducts->first(function($p) use ($normCsvName) {
            return normalizeName($p->product_name) === $normCsvName;
        });
    }

    // C. Synonym & Strength Aware Matching (Robust Stage)
    if (!$product) {
        $csvTokens = getStrengthTokens($csvName);
        $normCsvBase = $normCsvName;
        foreach ($synonyms as $brand => $generic) {
            $normCsvBase = str_replace($brand, $generic, $normCsvBase);
        }

        $product = $allProducts->first(function($p) use ($normCsvBase, $csvTokens) {
            $dbName = mb_strtolower($p->product_name);
            $normDbName = normalizeName($p->product_name);
            $dbTokens = getStrengthTokens($p->product_name);
            
            // 1. Must contain the base name
            if (strpos($normDbName, $normCsvBase) === false && strpos($normCsvBase, $normDbName) === false) {
                return false;
            }

            // 2. Strict Strength Matching: 
            // If tokens exist in either, they MUST match exactly.
            $allTokens = array_unique(array_merge($csvTokens, $dbTokens));
            foreach ($allTokens as $token) {
                $inCsv = in_array($token, $csvTokens);
                $inDb  = in_array($token, $dbTokens);
                if ($inCsv !== $inDb) return false; // Token exists in one but not the other
            }
            
            return true;
        });
    }

    // D. Final Loose Fallback (Use with caution)
    if (!$product) {
        $normCsvName = normalizeName($csvName);
        $product = $allProducts->first(function($p) use ($normCsvName) {
            $normDbName = normalizeName($p->product_name);
            return (strpos($normDbName, $normCsvName) !== false) || (strpos($normCsvName, $normDbName) !== false);
        });
    }

    if (!$product) {
        echo "NOT FOUND\n";
        $stats['unmatched']++;
        continue;
    }

    $stats['matched']++;
    echo "MATCHED (ID: {$product->id})\n";

    // ── Update Base Unit Name ──
    if ($apply && !empty($row['base_unit'])) {
        $product->base_unit_name = $row['base_unit'];
        $product->save();
    }

    // ── Calculate Total Base Units ──
    $totalBaseUnits = $row['qty_packs'] * $row['unit_mult'];
    $expiryDate = parseExpiry($row['expiry']);

    // ── Update Base Unit Name (Cleanup) ──
    $csvUnit = mb_strtolower(trim($row['base_unit']));
    $currentBaseUnit = mb_strtolower(trim($product->base_unit_name));
    
    // List of values that are likely "placeholders" or categories rather than units
    $placeholders = ['tablets', 'syrups', 'injectibles', 'capsules', 'surgicals', 'laboratory', 'infusions', 'cream', 'eye drop'];
    
    // If the current base unit is a placeholder OR the CSV provides a more specific unit
    if ($apply && (in_array($currentBaseUnit, $placeholders) || !empty($csvUnit))) {
        $newUnit = $csvUnit ?: $currentBaseUnit;
        
        // Final normalization: plural to singular if possible for consistency
        $mapping = [
            'tablets'   => 'tablet',
            'capsules'  => 'capsule',
            'ampoules'  => 'ampoule',
            'vials'     => 'vial',
            'bottles'   => 'bottle',
            'syrups'    => 'syrup',
            'surgicals' => 'surgical',
        ];
        
        if (isset($mapping[$newUnit])) {
            $newUnit = $mapping[$newUnit];
        }
        
        if ($product->base_unit_name !== $newUnit) {
            $product->update(['base_unit_name' => $newUnit]);
        }
    }

    // ── Handle Packaging ──
    // Check if a packaging exists with this multiplier OR this name OR level 1
    $packaging = ProductPackaging::where('product_id', $product->id)
        ->where(function($q) use ($row) {
            $q->where('base_unit_qty', $row['unit_mult'])
              ->orWhere('name', 'Pack')
              ->orWhere('level', 1);
        })
        ->first();

    if (!$packaging && $apply) {
        $packaging = ProductPackaging::create([
            'product_id'    => $product->id,
            'name'          => 'Pack',
            'units_in_parent' => $row['unit_mult'], // Set correctly for UI display
            'base_unit_qty' => $row['unit_mult'],
            'level'         => 1,
            'is_default_purchase' => true,
        ]);
    } elseif ($packaging && $apply && $packaging->base_unit_qty != $row['unit_mult']) {
        // If it exists but the multiplier is different, we update it
        if ($packaging->name === 'Pack') {
             // Optional: Update multiplier if it was 1 (placeholder) but CSV says more
             if ($packaging->base_unit_qty == 1 && $row['unit_mult'] > 1) {
                 $packaging->update([
                     'units_in_parent' => $row['unit_mult'],
                     'base_unit_qty'   => $row['unit_mult']
                 ]);
             }
        }
    }

    // ── Check for existing batch ──
    $existingBatch = StockBatch::where('product_id', $product->id)
        ->where('batch_number', $row['batch_no'])
        ->where('store_id', $centralStoreId)
        ->first();

    if ($existingBatch) {
        echo "ALREADY IMPORTED (Batch: {$row['batch_no']})\n";
        continue;
    }

    // ── Create Batch ──
    if ($apply) {
        $batch = StockBatch::create([
            'product_id'     => $product->id,
            'store_id'       => $centralStoreId,
            'batch_name'     => $row['batch_no'],
            'batch_number'   => $row['batch_no'],
            'initial_qty'    => $totalBaseUnits,
            'current_qty'    => $totalBaseUnits,
            'expiry_date'    => $expiryDate,
            'cost_price'     => 0, // Not provided in CSV
            'source'         => StockBatch::SOURCE_MANUAL,
            'received_date'  => now()->format('Y-m-d'),
            'is_active'      => 1,
            'created_by'     => 1, // System admin
        ]);

        // ── Create Transaction ──
        StockBatchTransaction::create([
            'stock_batch_id' => $batch->id,
            'type'           => StockBatchTransaction::TYPE_IN,
            'qty'            => $totalBaseUnits,
            'balance_after'  => $totalBaseUnits,
            'notes'          => 'Initial stock import from CSV',
            'performed_by'   => 1,
        ]);

        $stats['imported']++;
    }

    echo "   -> Qty: $totalBaseUnits pieces (from {$row['qty_packs']} packs x {$row['unit_mult']})\n";
    echo "   -> Expiry: " . ($expiryDate ?? 'INVALID') . "\n";
}

// ─── 4. SUMMARY ─────────────────────────────────────────

echo "\n" . str_repeat("=", 40) . "\n";
echo "  Stock Import Summary\n";
echo str_repeat("=", 40) . "\n";
echo "  Rows parsed:    " . count($rows) . "\n";
echo "  Matched:        {$stats['matched']}\n";
echo "  Unmatched:      {$stats['unmatched']}\n";
echo "  Imported:       {$stats['imported']}\n";
echo str_repeat("=", 40) . "\n\n";

if ($apply) {
    echo "*** IMPORT COMPLETED ***\n";
} else {
    echo "*** DRY RUN FINISHED — Pass --apply to commit to DB ***\n";
}
