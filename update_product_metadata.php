<?php

/**
 * Update Product Metadata Script
 * 
 * Usage: php update_product_metadata.php <csv_file> [--apply]
 * 
 * This script updates product_code, category, and base_unit_name (unit) 
 * based on a CSV file.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$filename = $argv[1] ?? null;
$apply    = in_array('--apply', $argv);

if (!$filename || !file_exists($filename)) {
    echo "Usage: php update_product_metadata.php <csv_file> [--apply]\n";
    exit(1);
}

if (!$apply) {
    echo "*** DRY RUN — pass --apply to commit changes ***\n\n";
}

// ─── 1. LOAD DATA ────────────────────────────────────────

$handle = fopen($filename, 'r');
$header = fgetcsv($handle); // product_name, product_code, category_name, description, unit

$rows = [];
while (($data = fgetcsv($handle)) !== false) {
    if (count($data) < 5) continue;
    $rows[] = [
        'name'     => $data[0],
        'code'     => $data[1],
        'category' => $data[2],
        'desc'     => $data[3],
        'unit'     => $data[4],
    ];
}
fclose($handle);

echo "Parsed " . count($rows) . " data rows from CSV.\n\n";

// ─── 2. LOAD PRODUCTS & CATEGORIES ───────────────────────

$products = Product::all()->keyBy(function ($p) {
    return mb_strtolower(trim($p->product_name));
});

$categories = ProductCategory::all()->keyBy(function ($c) {
    return mb_strtolower(trim($c->category_name));
});

echo "Loaded " . $products->count() . " products and " . $categories->count() . " categories from DB.\n\n";

// ─── 3. HELPERS ──────────────────────────────────────────

function normalizeForMatch(string $name): string
{
    $name = mb_strtolower(trim($name));
    // Remove common trailing notes like (per pack), (per sachet)
    $name = preg_replace('/\s*\(per\s+.*\)$/', '', $name);
    // Collapse extra spaces
    $name = preg_replace('/\s+/', ' ', $name);
    return trim($name);
}

// ─── 4. PROCESS ROWS ────────────────────────────────────

$stats = [
    'matched'         => 0,
    'unmatched'       => 0,
    'updated'         => 0,
    'cat_created'     => 0,
];

foreach ($rows as $row) {
    $csvName = $row['name'];
    $key     = mb_strtolower(trim($csvName));

    // Try exact match
    $product = $products->get($key);

    // Try normalized match if exact failed
    if (!$product) {
        $normKey = normalizeForMatch($csvName);
        foreach ($products as $pKey => $pObj) {
            if (normalizeForMatch($pKey) === $normKey) {
                $product = $pObj;
                break;
            }
        }
    }

    if (!$product) {
        $stats['unmatched']++;
        continue;
    }

    $stats['matched']++;

    // ── Get/Create Category ──
    $rawCat  = trim($row['category']);
    $rawDesc = trim($row['desc']);
    
    // Use "Category-Description" as the name, fallback to Category if Description is empty
    $catName = !empty($rawDesc) ? "{$rawCat}-{$rawDesc}" : $rawCat;
    
    if (empty($catName)) {
        $catName = 'Uncategorized';
    }

    $catKey  = mb_strtolower($catName);
    $category = $categories->get($catKey);

    if (!$category) {
        if ($apply) {
            $category = ProductCategory::create([
                'category_name' => $catName,
                'status'        => 1,
            ]);
            $categories->put($catKey, $category);
        }
        $stats['cat_created']++;
    }

    // ── Update Metadata ──
    $changed = false;
    
    // 1. Product Code
    if (!empty($row['code']) && $product->product_code !== $row['code']) {
        $product->product_code = $row['code'];
        $changed = true;
    }

    // 2. Category
    if ($category && $product->category_id !== $category->id) {
        $product->category_id = $category->id;
        $changed = true;
    }

    // 3. Base Unit Name (unit)
    if (!empty($row['unit']) && mb_strtolower($product->base_unit_name) !== mb_strtolower($row['unit'])) {
        $product->base_unit_name = $row['unit'];
        $changed = true;
    }

    if ($changed) {
        if ($apply) {
            $product->save();
        }
        $stats['updated']++;
    }
}

// ─── 5. SUMMARY ─────────────────────────────────────────

echo "═══════════════════════════════════════════\n";
echo "  Product Metadata Update Summary\n";
echo "═══════════════════════════════════════════\n";
echo "  Products matched:     {$stats['matched']}\n";
echo "  Products unmatched:   {$stats['unmatched']}\n";
echo "  Products updated:     {$stats['updated']}\n";
echo "  Categories created:   {$stats['cat_created']}\n";
echo "═══════════════════════════════════════════\n\n";

if ($apply) {
    echo "*** CHANGES COMMITTED ***\n";
} else {
    echo "*** DRY RUN — no changes were saved. Pass --apply to commit. ***\n";
}
