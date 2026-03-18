<?php

/**
 * One-time script to import pharmacy drug price list CSV.
 *
 * Updates:
 *  - GOPD base prices (prices.current_sale_price)
 *  - HMO tariffs (hmo_tariffs) for all active HMOs
 *
 * Usage:
 *   php import_pharmacy_prices.php path/to/file.csv         # dry-run by default
 *   php import_pharmacy_prices.php path/to/file.csv --apply # apply changes
 *   php import_pharmacy_prices.php file.csv --skip-gopd     # skip base price updates
 *   php import_pharmacy_prices.php file.csv --skip-tariffs  # skip tariff updates
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\Price;
use App\Models\Hmo;
use App\Models\HmoScheme;
use App\Models\HmoTariff;

// ─── CONFIG ──────────────────────────────────────────────
// First non-flag argument is the CSV file path
$csvPath = null;
foreach (array_slice($argv, 1) as $arg) {
    if (strpos($arg, '--') !== 0) {
        $csvPath = $arg;
        break;
    }
}
if (!$csvPath) {
    die("Usage: php import_pharmacy_prices.php <csv-file> [--apply] [--skip-gopd] [--skip-tariffs]\n");
}
// Resolve relative paths
if (!preg_match('#^([a-zA-Z]:\\\\|/)#', $csvPath)) {
    $csvPath = getcwd() . DIRECTORY_SEPARATOR . $csvPath;
}
$apply      = in_array('--apply', $argv);
$skipGopd   = in_array('--skip-gopd', $argv);
$skipTariff = in_array('--skip-tariffs', $argv);

if (!$apply) {
    echo "*** DRY RUN — pass --apply to commit changes ***\n\n";
}

// ─── 1. PARSE CSV ────────────────────────────────────────
if (!file_exists($csvPath)) {
    die("CSV file not found: {$csvPath}\n");
}

$rows = [];
$handle = fopen($csvPath, 'r');
$lineNum = 0;
while (($line = fgetcsv($handle)) !== false) {
    $lineNum++;
    // Skip header rows: data rows start with a numeric value in col 0
    $first = trim($line[0] ?? '');
    if (!is_numeric($first)) {
        continue;
    }

    $name      = trim($line[1] ?? '');
    $gopd      = trim($line[2] ?? '');
    $hmo       = trim($line[3] ?? '');
    $nhis      = trim($line[4] ?? '');
    $cbn       = trim($line[5] ?? '');
    $plashema  = trim($line[6] ?? '');

    if ($name === '' || strtoupper($name) === 'NAME') continue;

    $rows[] = compact('name', 'gopd', 'hmo', 'nhis', 'cbn', 'plashema');
}
fclose($handle);

echo "Parsed " . count($rows) . " data rows from CSV.\n\n";

// ─── 2. LOAD REFERENCE DATA ─────────────────────────────
$products = Product::with('price')->get()->keyBy(function ($p) {
    return mb_strtolower(trim($p->product_name));
});

echo "Loaded " . $products->count() . " products from DB.\n";

// Categorize HMOs
$allHmos = Hmo::with('scheme')->where('status', 1)->get();
$hmoGroups = [
    'private'  => collect(),
    'cbn'      => collect(),
    'nhis'     => collect(),
    'shis'     => collect(),
    'generic'  => collect(),
];

foreach ($allHmos as $h) {
    $nameUpper   = mb_strtoupper($h->name);
    $schemeCode  = $h->scheme ? mb_strtoupper($h->scheme->code) : '';
    $schemeName  = $h->scheme ? mb_strtoupper($h->scheme->name) : '';

    if (stripos($h->name, 'Private') !== false || $schemeCode === 'SELF') {
        $hmoGroups['private']->push($h);
    } elseif (stripos($h->name, 'CBN') !== false) {
        $hmoGroups['cbn']->push($h);
    } elseif (in_array($schemeCode, ['NHIS', 'NHIA']) || str_contains($schemeName, 'NHIS') || str_contains($schemeName, 'NHIA')) {
        $hmoGroups['nhis']->push($h);
    } elseif ($schemeCode === 'SHIS' || str_contains($schemeName, 'PLASCHEMA') || str_contains($schemeName, 'SHIS')) {
        $hmoGroups['shis']->push($h);
    } else {
        $hmoGroups['generic']->push($h);
    }
}

echo "HMO groups:\n";
foreach ($hmoGroups as $group => $hmos) {
    echo "  {$group}: " . $hmos->count() . " HMOs";
    if ($hmos->count() <= 10) {
        echo " [" . $hmos->pluck('name')->implode(', ') . "]";
    }
    echo "\n";
}
echo "\n";

// ─── 3. HELPER FUNCTIONS ────────────────────────────────

function parseValue(string $raw): array
{
    $val = strtoupper(trim($raw));

    if ($val === '' || $val === '-') {
        return ['type' => 'empty'];
    }
    if ($val === 'NC' || $val === 'N/C') {
        return ['type' => 'nc'];
    }
    if ($val === 'NEG') {
        return ['type' => 'neg'];
    }
    if (str_starts_with($val, 'PE')) {
        $amount = (float) str_replace(',', '', substr($val, 2));
        return ['type' => 'pe', 'amount' => $amount];
    }

    $num = str_replace([',', ' '], '', $val);
    if (is_numeric($num)) {
        return ['type' => 'numeric', 'amount' => (float) $num];
    }

    return ['type' => 'unknown', 'raw' => $raw];
}

/**
 * Calculate payable/claims for a tariff.
 *
 * @param array  $parsed   Parsed value from target column
 * @param string $group    HMO group: private|cbn|nhis|shis|generic
 * @param float  $gopdPrice GOPD price for this product
 * @param array  $hmoParsed Parsed value from HMO column (fallback for NEG)
 * @return array|null ['payable' => float, 'claims' => float, 'mode' => string] or null
 */
function calculateTariff(array $parsed, string $group, float $gopdPrice, array $hmoParsed): ?array
{
    // NEG → fallback to HMO column value, then apply this group's rules
    if ($parsed['type'] === 'neg') {
        // Use HMO column amount as the base
        if ($hmoParsed['type'] === 'numeric') {
            $parsed = $hmoParsed;
        } elseif ($hmoParsed['type'] === 'pe') {
            $parsed = $hmoParsed;
        } elseif ($hmoParsed['type'] === 'nc') {
            $parsed = $hmoParsed;
        } else {
            // HMO column itself is also NEG or empty — use GOPD
            $parsed = ['type' => 'numeric', 'amount' => $gopdPrice];
        }
    }

    // Empty → fallback to GOPD price, apply this group's rules
    if ($parsed['type'] === 'empty') {
        $parsed = ['type' => 'numeric', 'amount' => $gopdPrice];
    }

    if ($parsed['type'] === 'unknown') {
        return null;
    }

    // NC — not covered: patient pays 100% of GOPD
    if ($parsed['type'] === 'nc') {
        return ['payable' => $gopdPrice, 'claims' => 0, 'mode' => 'primary'];
    }

    // PE — partial exclusion: 50/50
    if ($parsed['type'] === 'pe') {
        $half = round($parsed['amount'] / 2, 2);
        return ['payable' => $half, 'claims' => $half, 'mode' => 'primary'];
    }

    // Numeric
    $amount = $parsed['amount'];

    if ($group === 'private') {
        return ['payable' => $amount, 'claims' => 0, 'mode' => 'primary'];
    }

    if (in_array($group, ['nhis', 'shis'])) {
        return [
            'payable' => round($amount * 0.10, 2),
            'claims'  => round($amount * 0.90, 2),
            'mode'    => 'primary',
        ];
    }

    // generic / cbn — HMO covers full amount
    return ['payable' => 0, 'claims' => $amount, 'mode' => 'primary'];
}

function normalizeForMatch(string $name): string
{
    $name = mb_strtolower(trim($name));
    // collapse multiple spaces
    $name = preg_replace('/\s+/', ' ', $name);
    // remove trailing parenthetical notes like "(per pack)"
    $name = preg_replace('/\s*\(.*?\)\s*$/', '', $name);
    return trim($name);
}

// ─── 4. PROCESS ROWS ────────────────────────────────────

$stats = [
    'matched'         => 0,
    'unmatched'       => 0,
    'gopd_updated'    => 0,
    'tariff_created'  => 0,
    'tariff_updated'  => 0,
    'tariff_skipped'  => 0,
];
$unmatched = [];

if ($apply) {
    DB::beginTransaction();
}

try {
    foreach ($rows as $row) {
        $csvName = $row['name'];
        $key = mb_strtolower(trim($csvName));

        // Try exact match
        $product = $products->get($key);

        // Try normalized match
        if (!$product) {
            $normKey = normalizeForMatch($csvName);
            foreach ($products as $k => $p) {
                if (normalizeForMatch($p->product_name) === $normKey) {
                    $product = $p;
                    break;
                }
            }
        }

        if (!$product) {
            $unmatched[] = $csvName;
            $stats['unmatched']++;
            continue;
        }

        $stats['matched']++;

        $gopdParsed     = parseValue($row['gopd']);
        $hmoParsed      = parseValue($row['hmo']);
        $nhisParsed     = parseValue($row['nhis']);
        $cbnParsed      = parseValue($row['cbn']);
        $plaschemaParsed = parseValue($row['plashema']);

        // Determine GOPD price (for base price update and NC fallback)
        $gopdPrice = 0;
        if ($gopdParsed['type'] === 'numeric') {
            $gopdPrice = $gopdParsed['amount'];
        } elseif ($product->price) {
            $gopdPrice = (float) $product->price->current_sale_price;
        }

        // ── Update base GOPD price ──
        if (!$skipGopd && $gopdParsed['type'] === 'numeric') {
            $oldPrice = $product->price ? (float) $product->price->current_sale_price : 0;
            if (abs($oldPrice - $gopdPrice) > 0.01) {
                if ($apply) {
                    if ($product->price) {
                        $product->price->current_sale_price = $gopdPrice;
                        $product->price->save();
                    } else {
                        Price::create([
                            'product_id'         => $product->id,
                            'current_sale_price'  => $gopdPrice,
                            'initial_sale_price'  => $gopdPrice,
                            'pr_buy_price'       => 0,
                            'status'             => 1,
                        ]);
                    }
                }
                $stats['gopd_updated']++;
            }
        }

        // ── Update HMO tariffs ──
        if ($skipTariff) continue;

        $groupColumns = [
            'private'  => ['type' => 'numeric', 'amount' => $gopdPrice], // always GOPD
            'cbn'      => $cbnParsed,
            'nhis'     => $nhisParsed,
            'shis'     => $plaschemaParsed,
            'generic'  => $hmoParsed,
        ];

        foreach ($groupColumns as $group => $parsedVal) {
            $tariff = calculateTariff($parsedVal, $group, $gopdPrice, $hmoParsed);
            if (!$tariff) {
                $stats['tariff_skipped'] += $hmoGroups[$group]->count();
                continue;
            }

            foreach ($hmoGroups[$group] as $hmo) {
                $existing = HmoTariff::where('hmo_id', $hmo->id)
                    ->where('product_id', $product->id)
                    ->whereNull('service_id')
                    ->first();

                if ($existing) {
                    $changed = abs((float)$existing->claims_amount - $tariff['claims']) > 0.01
                            || abs((float)$existing->payable_amount - $tariff['payable']) > 0.01
                            || $existing->coverage_mode !== $tariff['mode'];

                    if ($changed) {
                        if ($apply) {
                            $existing->update([
                                'claims_amount'  => $tariff['claims'],
                                'payable_amount' => $tariff['payable'],
                                'coverage_mode'  => $tariff['mode'],
                            ]);
                        }
                        $stats['tariff_updated']++;
                    } else {
                        $stats['tariff_skipped']++;
                    }
                } else {
                    if ($apply) {
                        HmoTariff::create([
                            'hmo_id'         => $hmo->id,
                            'product_id'     => $product->id,
                            'service_id'     => null,
                            'claims_amount'  => $tariff['claims'],
                            'payable_amount' => $tariff['payable'],
                            'coverage_mode'  => $tariff['mode'],
                        ]);
                    }
                    $stats['tariff_created']++;
                }
            }
        }
    }

    if ($apply) {
        DB::commit();
        echo "*** CHANGES COMMITTED ***\n\n";
    }

} catch (\Exception $e) {
    if ($apply) {
        DB::rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

// ─── 5. REPORT ───────────────────────────────────────────
echo "═══════════════════════════════════════════\n";
echo "  Pharmacy Price Import Summary\n";
echo "═══════════════════════════════════════════\n";
echo "  Products matched:     {$stats['matched']}\n";
echo "  Products unmatched:   {$stats['unmatched']}\n";
echo "  GOPD prices updated:  {$stats['gopd_updated']}\n";
echo "  Tariffs created:      {$stats['tariff_created']}\n";
echo "  Tariffs updated:      {$stats['tariff_updated']}\n";
echo "  Tariffs skipped:      {$stats['tariff_skipped']}\n";
echo "═══════════════════════════════════════════\n\n";

if (!empty($unmatched)) {
    echo "UNMATCHED PRODUCTS (" . count($unmatched) . "):\n";
    foreach ($unmatched as $i => $name) {
        echo "  " . ($i + 1) . ". {$name}\n";
    }
    echo "\n";
}

if (!$apply) {
    echo "*** DRY RUN — no changes were saved. Pass --apply to commit. ***\n";
}
