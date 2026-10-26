<?php

/**
 * CoreHealth v2 - Skane Facility Department Streamlining & Revenue Attribution Backfill
 *
 * This script streamlines Skane's department list to the core clinical modalities:
 *   - Ultrasound
 *   - Consultation / Clinic
 *   - Health Records
 *   - Laboratory
 *   - X-ray
 *   - Nursing
 *   - Pharmacy
 *   - Plus operational departments: Administration, Finance & Accounts, Billing, Store & Inventory.
 *
 * It maps all imported services (123 items) and pharmacy products (99 items) to their
 * respective departments and executes the backfill across categories, items, transactional
 * requests (POSR), and double-entry general ledger lines (JEL).
 *
 * Usage:
 *   php scripts/streamline_skane_departments.php [--dry-run] [--db=_corehealth_db_v2_skane]
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

// Parse options
$options = getopt('', ['dry-run', 'db::', 'database::', 'help']);

if (isset($options['help'])) {
    echo "Usage: php scripts/streamline_skane_departments.php [options]\n";
    echo "Options:\n";
    echo "  --dry-run        Simulate the streamline & backfill without writing database changes\n";
    echo "  --db=NAME        Target database (default: _corehealth_db_v2_skane)\n";
    echo "  --help           Show this help message\n";
    exit(0);
}

$isDryRun = isset($options['dry-run']);
$targetDb = $options['db'] ?? ($options['database'] ?? '_corehealth_db_v2_skane');

echo "================================================================================\n";
echo "  COREHEALTH v2 - SKANE DEPARTMENT STREAMLINING & ATTRIBUTION BACKFILL          \n";
echo "================================================================================\n";
echo "Target Database : {$targetDb}\n";
echo "Mode            : " . ($isDryRun ? "DRY-RUN (Preview Only, No DB modifications)" : "APPLY (Live Updates)") . "\n";
echo "Timestamp       : " . date('Y-m-d H:i:s') . "\n";
echo "================================================================================\n\n";

// 1. Connect to Target Database
try {
    DB::purge('mysql');
    config(['database.connections.mysql.database' => $targetDb]);
    DB::reconnect('mysql');
    $dbName = DB::connection()->getDatabaseName();
    echo "[✓] Successfully connected to database: {$dbName}\n";
} catch (\Throwable $e) {
    echo "[✗] ERROR connecting to database '{$targetDb}': " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Ensure Schema Migrations
echo "\n--- 1. CHECKING SCHEMA & MIGRATIONS ---\n";
$needsMigration = !Schema::hasColumn('service_categories', 'department_id')
    || !Schema::hasColumn('product_categories', 'department_id')
    || !Schema::hasColumn('services', 'department_id')
    || !Schema::hasColumn('products', 'department_id')
    || !Schema::hasColumn('product_or_service_requests', 'department_id');

if ($needsMigration) {
    echo "[!] Schema missing department_id columns across catalog & requests.\n";
    if ($isDryRun) {
        echo "    [Dry-run] Pending migrations (including 2026_10_25_000001_add_department_id_to_categories_services_products) will be applied on live run.\n";
    } else {
        echo "    [*] Running pending database migrations...\n";
        Artisan::call('migrate', ['--force' => true]);
        echo Artisan::output();
        echo "[✓] Migrations executed successfully.\n";
    }
} else {
    echo "[✓] All required department_id columns exist on target schema.\n";
}

// 3. Streamline Departments
echo "\n--- 2. STREAMLINING DEPARTMENTS LIST ---\n";

/**
 * Expected Core Departments:
 * 1. Ultrasound (USS)
 * 2. Consultation / Clinic (CONS)
 * 3. Health Records (HRD)
 * 4. Laboratory (LAB)
 * 5. X-ray (XRY)
 * 6. Nursing (NURS)
 * 7. Pharmacy (PHAR)
 * 8. Administration (ADMIN)
 * 9. Finance & Accounts (FIN)
 * 10. Billing (BILL)
 * 11. Store & Inventory (STOR)
 */

$deptMap = []; // Key: standardized key => Department ID

// 3.1. Ensure/Update "Ultrasound"
$ultrasound = DB::table('departments')->where('code', 'USS')->orWhere('name', 'like', '%ultrasound%')->first();
if ($ultrasound) {
    $deptMap['ultrasound'] = $ultrasound->id;
    if (!$isDryRun) {
        DB::table('departments')->where('id', $ultrasound->id)->update([
            'name' => 'Ultrasound',
            'code' => 'USS',
            'description' => 'Ultrasound scanning, Sonography, Echocardiography, Doppler & ECG',
            'is_active' => 1,
            'updated_at' => now(),
        ]);
    }
    echo "    [→] Found existing Ultrasound department (ID: {$ultrasound->id})\n";
} else {
    if (!$isDryRun) {
        $newUssId = DB::table('departments')->insertGetId([
            'name' => 'Ultrasound',
            'code' => 'USS',
            'description' => 'Ultrasound scanning, Sonography, Echocardiography, Doppler & ECG',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $deptMap['ultrasound'] = $newUssId;

        // Auto-create department store if not exists
        if (!DB::table('stores')->where('code', 'USS_DS')->exists()) {
            DB::table('stores')->insert([
                'store_name' => 'Ultrasound Store',
                'code' => 'USS_DS',
                'description' => 'Department store for Ultrasound',
                'location' => 'Ultrasound',
                'store_type' => 'other',
                'distribution_role' => 'department',
                'department_id' => $newUssId,
                'status' => 1,
                'requires_shift_context' => 0,
                'allows_direct_patient_dispense' => 0,
                'is_default' => 0,
                'is_immutable' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        echo "    [+] Created new Ultrasound department (ID: {$newUssId})\n";
    } else {
        $deptMap['ultrasound'] = 9991; // Dummy for preview
        echo "    [Dry-run] Would create new Ultrasound department (Code: USS)\n";
    }
}

// 3.2. Ensure/Update "X-ray"
// Update Dept 10 (Radiology Department) to X-ray
$xray = DB::table('departments')->where('id', 10)->first()
    ?? DB::table('departments')->where('code', 'XRY')->orWhere('code', 'RAD')->first();

if ($xray) {
    $deptMap['xray'] = $xray->id;
    if (!$isDryRun) {
        DB::table('departments')->where('id', $xray->id)->update([
            'name' => 'X-ray',
            'code' => 'XRY',
            'description' => 'X-Ray Radiography, Fluoroscopy, Mammography, HSG & Diagnostic Imaging',
            'is_active' => 1,
            'updated_at' => now(),
        ]);
    }
    echo "    [→] Streamlined X-ray department (ID: {$xray->id})\n";
} else {
    if (!$isDryRun) {
        $newXryId = DB::table('departments')->insertGetId([
            'name' => 'X-ray',
            'code' => 'XRY',
            'description' => 'X-Ray Radiography, Fluoroscopy, Mammography, HSG & Diagnostic Imaging',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $deptMap['xray'] = $newXryId;
        echo "    [+] Created X-ray department (ID: {$newXryId})\n";
    } else {
        $deptMap['xray'] = 10;
        echo "    [Dry-run] Would streamline X-ray department (ID: 10)\n";
    }
}

// 3.3. Ensure/Update "Consultation / Clinic"
// Update Dept 1 (Medical Department)
$cons = DB::table('departments')->where('id', 1)->first()
    ?? DB::table('departments')->where('code', 'CONS')->orWhere('code', 'MED')->first();

if ($cons) {
    $deptMap['consultation'] = $cons->id;
    if (!$isDryRun) {
        DB::table('departments')->where('id', $cons->id)->update([
            'name' => 'Consultation / Clinic',
            'code' => 'CONS',
            'description' => 'General & Specialist Consultations, Clinical Reviews, and Outpatient Care',
            'is_active' => 1,
            'updated_at' => now(),
        ]);
    }
    echo "    [→] Streamlined Consultation / Clinic department (ID: {$cons->id})\n";
}

// 3.4. Ensure/Update "Health Records"
// Update Dept 12 (Health Records Department)
$hrd = DB::table('departments')->where('id', 12)->first()
    ?? DB::table('departments')->where('code', 'HRD')->first();

if ($hrd) {
    $deptMap['health_records'] = $hrd->id;
    if (!$isDryRun) {
        DB::table('departments')->where('id', $hrd->id)->update([
            'name' => 'Health Records',
            'code' => 'HRD',
            'description' => 'Medical Records, Folder Registration, Card Issuance, and Health Information',
            'is_active' => 1,
            'updated_at' => now(),
        ]);
    }
    echo "    [→] Streamlined Health Records department (ID: {$hrd->id})\n";
}

// 3.5. Ensure/Update "Laboratory"
// Update Dept 9 (Laboratory Department)
$lab = DB::table('departments')->where('id', 9)->first()
    ?? DB::table('departments')->where('code', 'LAB')->first();

if ($lab) {
    $deptMap['lab'] = $lab->id;
    if (!$isDryRun) {
        DB::table('departments')->where('id', $lab->id)->update([
            'name' => 'Laboratory',
            'code' => 'LAB',
            'description' => 'Medical Laboratory, Pathology, Haematology, Microbiology, and Diagnostic Tests',
            'is_active' => 1,
            'updated_at' => now(),
        ]);
    }
    echo "    [→] Streamlined Laboratory department (ID: {$lab->id})\n";
}

// 3.6. Ensure/Update "Nursing"
// Update Dept 8 (Nursing Department)
$nurs = DB::table('departments')->where('id', 8)->first()
    ?? DB::table('departments')->where('code', 'NURS')->first();

if ($nurs) {
    $deptMap['nursing'] = $nurs->id;
    if (!$isDryRun) {
        DB::table('departments')->where('id', $nurs->id)->update([
            'name' => 'Nursing',
            'code' => 'NURS',
            'description' => 'Nursing Care, Vital Signs Triage, Inpatient Stays, and Bed Accommodation',
            'is_active' => 1,
            'updated_at' => now(),
        ]);
    }
    echo "    [→] Streamlined Nursing department (ID: {$nurs->id})\n";
}

// 3.7. Ensure/Update "Pharmacy"
// Update Dept 11 (Pharmacy Department)
$phar = DB::table('departments')->where('id', 11)->first()
    ?? DB::table('departments')->where('code', 'PHAR')->first();

if ($phar) {
    $deptMap['pharmacy'] = $phar->id;
    if (!$isDryRun) {
        DB::table('departments')->where('id', $phar->id)->update([
            'name' => 'Pharmacy',
            'code' => 'PHAR',
            'description' => 'Pharmaceutical Dispensing, Medications, and Drug Formulary Management',
            'is_active' => 1,
            'updated_at' => now(),
        ]);
    }
    echo "    [→] Streamlined Pharmacy department (ID: {$phar->id})\n";
}

// 3.8. Operational Departments ("etc.")
$admin = DB::table('departments')->where('id', 14)->first();
if ($admin) {
    $deptMap['admin'] = $admin->id;
    if (!$isDryRun) {
        DB::table('departments')->where('id', $admin->id)->update(['is_active' => 1]);
    }
}

$fin = DB::table('departments')->where('id', 16)->first();
if ($fin) {
    $deptMap['finance'] = $fin->id;
    if (!$isDryRun) {
        DB::table('departments')->where('id', $fin->id)->update(['is_active' => 1]);
    }
}

$bill = DB::table('departments')->where('id', 17)->first();
if ($bill) {
    $deptMap['billing'] = $bill->id;
    if (!$isDryRun) {
        DB::table('departments')->where('id', $bill->id)->update(['name' => 'Billing', 'is_active' => 1]);
    }
}

$store = DB::table('departments')->where('id', 19)->first();
if ($store) {
    $deptMap['store'] = $store->id;
    if (!$isDryRun) {
        DB::table('departments')->where('id', $store->id)->update(['is_active' => 1]);
    }
}

// 3.9. Reassign staff and stores from deprecated departments
$activeDeptIds = array_values(array_filter($deptMap));

if (!$isDryRun) {
    // Staff in Outpatient (6) -> Consultation / Clinic (1)
    DB::table('staff')->where('department_id', 6)->update(['department_id' => $deptMap['consultation']]);

    // Staff in Reception & Front Office (13) -> Health Records (12)
    DB::table('staff')->where('department_id', 13)->update(['department_id' => $deptMap['health_records']]);

    // Staff in Maintenance (24), Patient Relations (35), Medical Education (34) -> Administration (14)
    DB::table('staff')->whereIn('department_id', [24, 34, 35])->update(['department_id' => $deptMap['admin']]);

    // Link Pharmacy Store (ID 2) to Pharmacy Department
    DB::table('stores')->where('id', 2)->update(['department_id' => $deptMap['pharmacy']]);

    // Link Central Store (ID 3) to Store Department
    DB::table('stores')->where('id', 3)->update(['department_id' => $deptMap['store']]);

    // Deactivate all other unused departments
    $deactivatedCount = DB::table('departments')->whereNotIn('id', $activeDeptIds)->update(['is_active' => 0]);
    echo "    [✓] Deactivated {$deactivatedCount} non-applicable departments.\n";
} else {
    $pendingDeactivate = DB::table('departments')->whereNotIn('id', $activeDeptIds)->count();
    echo "    [Dry-run] Would deactivate {$pendingDeactivate} non-applicable departments.\n";
}

// 4. Map Service Categories
echo "\n--- 3. MAPPING SERVICE CATEGORIES ---\n";
$scatMap = [
    1 => $deptMap['consultation'], // Consultation -> Consultation / Clinic
    2 => $deptMap['lab'],          // Investigation -> Laboratory
    3 => $deptMap['nursing'],      // Bed -> Nursing
    4 => $deptMap['nursing'],      // Nursing -> Nursing
    5 => $deptMap['billing'],      // Miscellaneous -> Billing
    6 => $deptMap['xray'],         // Imaging -> X-ray (base default)
    7 => $deptMap['health_records'], // Registration -> Health Records
    8 => $deptMap['consultation'], // Procedures -> Consultation / Clinic
    9 => $deptMap['admin'],        // Morgue -> Administration
];

foreach ($scatMap as $catId => $deptId) {
    $cat = DB::table('service_categories')->where('id', $catId)->first();
    if ($cat) {
        $deptName = DB::table('departments')->where('id', $deptId)->value('name') ?? "Dept #{$deptId}";
        echo "    [→] Category #{$catId} '{$cat->category_name}' => {$deptName} (ID: {$deptId})\n";
        if (!$isDryRun && Schema::hasColumn('service_categories', 'department_id')) {
            DB::table('service_categories')->where('id', $catId)->update(['department_id' => $deptId]);
        }
    }
}

// 5. Map Product Categories -> Pharmacy
echo "\n--- 4. MAPPING PRODUCT CATEGORIES (PHARMACY) ---\n";
$prodCatCount = DB::table('product_categories')->count();
echo "    [→] Assigning all {$prodCatCount} product categories to Pharmacy (ID: {$deptMap['pharmacy']})\n";
if (!$isDryRun && Schema::hasColumn('product_categories', 'department_id')) {
    DB::table('product_categories')->update(['department_id' => $deptMap['pharmacy']]);
}

// 6. Map Services to Specific Departments (Item-Level Attribution)
echo "\n--- 5. MAPPING SERVICES (ITEM-LEVEL REVENUE ATTRIBUTION) ---\n";

// 6.1. Ultrasound Services
$ussKeywords = ['ultrasound', 'sonogra', 'doppler', 'echo', 'ecg', 'tvs'];
$ussQuery = DB::table('services')
    ->where('category_id', 6)
    ->where(function ($q) use ($ussKeywords) {
        $q->where('service_code', 'like', 'SRV-USS-%')
          ->orWhere('service_code', 'like', 'USS-%');
        foreach ($ussKeywords as $kw) {
            $q->orWhere('service_name', 'like', "%{$kw}%");
        }
    });

$ussCount = $ussQuery->count();
$ussIds = $ussQuery->pluck('id')->toArray();
echo "    [•] Ultrasound Services identified: {$ussCount} services\n";
if (!$isDryRun && !empty($ussIds) && Schema::hasColumn('services', 'department_id')) {
    DB::table('services')->whereIn('id', $ussIds)->update(['department_id' => $deptMap['ultrasound']]);
}

// 6.2. X-ray Services
$xryQuery = DB::table('services')
    ->where('category_id', 6)
    ->whereNotIn('id', $ussIds);

$xryCount = $xryQuery->count();
$xryIds = $xryQuery->pluck('id')->toArray();
echo "    [•] X-ray Services identified: {$xryCount} services\n";
if (!$isDryRun && !empty($xryIds) && Schema::hasColumn('services', 'department_id')) {
    DB::table('services')->whereIn('id', $xryIds)->update(['department_id' => $deptMap['xray']]);
}

// 6.3. Laboratory Services (Category 2)
$labQuery = DB::table('services')->where('category_id', 2);
$labCount = $labQuery->count();
echo "    [•] Laboratory Services identified: {$labCount} tests\n";
if (!$isDryRun && Schema::hasColumn('services', 'department_id')) {
    $labQuery->update(['department_id' => $deptMap['lab']]);
}

// 6.4. Consultation Services (Category 1)
$consQuery = DB::table('services')->where('category_id', 1);
$consCount = $consQuery->count();
echo "    [•] Consultation Services identified: {$consCount} services\n";
if (!$isDryRun && Schema::hasColumn('services', 'department_id')) {
    $consQuery->update(['department_id' => $deptMap['consultation']]);
}

// 6.5. Health Records / Registration Services (Category 7)
$regQuery = DB::table('services')->where('category_id', 7);
$regCount = $regQuery->count();
echo "    [•] Health Records Services identified: {$regCount} services\n";
if (!$isDryRun && Schema::hasColumn('services', 'department_id')) {
    $regQuery->update(['department_id' => $deptMap['health_records']]);
}

// 6.6. Any other remaining services get their category department_id
if (Schema::hasColumn('services', 'department_id') && Schema::hasColumn('service_categories', 'department_id')) {
    $otherQuery = DB::table('services as s')
        ->join('service_categories as sc', 's.category_id', '=', 'sc.id')
        ->whereNull('s.department_id')
        ->whereNotNull('sc.department_id');
    $otherCount = $otherQuery->count();
    echo "    [•] Services inherited from parent categories: {$otherCount} services\n";
    if (!$isDryRun && $otherCount > 0) {
        DB::update("UPDATE services s JOIN service_categories sc ON s.category_id = sc.id SET s.department_id = sc.department_id WHERE s.department_id IS NULL AND sc.department_id IS NOT NULL");
    }
} else {
    echo "    [•] Category inheritance will be applied on live run after migrations.\n";
}

// 7. Map Products to Pharmacy
echo "\n--- 6. MAPPING PRODUCTS (PHARMACY) ---\n";
$prodQuery = DB::table('products');
$prodCount = $prodQuery->count();
echo "    [•] Products assigned to Pharmacy: {$prodCount} items\n";
if (!$isDryRun && Schema::hasColumn('products', 'department_id')) {
    $prodQuery->update(['department_id' => $deptMap['pharmacy']]);
}

// 8. Backfill Transactions (POSR) & Ledger (JEL)
echo "\n--- 7. TRANSACTIONAL & GENERAL LEDGER BACKFILL ---\n";

if (Schema::hasColumn('product_or_service_requests', 'department_id') && Schema::hasColumn('services', 'department_id')) {
    // 8.1. Backfill POSR for Services
    $posrServicesQuery = DB::table('product_or_service_requests as posr')
        ->join('services as s', 'posr.service_id', '=', 's.id')
        ->whereNull('posr.department_id')
        ->whereNotNull('s.department_id');
    $posrServicesCount = $posrServicesQuery->count();
    echo "    [•] Service Requests (POSR) pending backfill: {$posrServicesCount}\n";
    if (!$isDryRun && $posrServicesCount > 0) {
        DB::update("UPDATE product_or_service_requests posr JOIN services s ON posr.service_id = s.id SET posr.department_id = s.department_id WHERE posr.department_id IS NULL AND s.department_id IS NOT NULL");
    }

    // 8.2. Backfill POSR for Products
    $posrProductsQuery = DB::table('product_or_service_requests as posr')
        ->join('products as p', 'posr.product_id', '=', 'p.id')
        ->whereNull('posr.department_id')
        ->whereNotNull('p.department_id');
    $posrProductsCount = $posrProductsQuery->count();
    echo "    [•] Product Requests (POSR) pending backfill: {$posrProductsCount}\n";
    if (!$isDryRun && $posrProductsCount > 0) {
        DB::update("UPDATE product_or_service_requests posr JOIN products p ON posr.product_id = p.id SET posr.department_id = p.department_id WHERE posr.department_id IS NULL AND p.department_id IS NOT NULL");
    }

    // 8.3. Backfill JEL for POSR
    $jelPosrQuery = DB::table('journal_entry_lines as jel')
        ->join('journal_entries as je', function ($join) {
            $join->on('jel.journal_entry_id', '=', 'je.id')
                ->where('je.reference_type', '=', 'App\\Models\\ProductOrServiceRequest');
        })
        ->join('product_or_service_requests as posr', 'posr.id', '=', 'je.reference_id')
        ->whereNull('jel.department_id')
        ->whereNotNull('posr.department_id');
    $jelPosrCount = $jelPosrQuery->count();
    echo "    [•] Journal Entry Lines (POSR) pending backfill: {$jelPosrCount}\n";
    if (!$isDryRun && $jelPosrCount > 0) {
        DB::update("UPDATE journal_entry_lines jel JOIN journal_entries je ON jel.journal_entry_id = je.id AND je.reference_type = 'App\\\\Models\\\\ProductOrServiceRequest' JOIN product_or_service_requests posr ON posr.id = je.reference_id SET jel.department_id = posr.department_id WHERE jel.department_id IS NULL AND posr.department_id IS NOT NULL");
    }

    // 8.4. Backfill JEL for Payments
    $jelPaymentQuery = DB::table('journal_entry_lines as jel')
        ->join('journal_entries as je', function ($join) {
            $join->on('jel.journal_entry_id', '=', 'je.id')
                ->where('je.reference_type', '=', 'App\\Models\\Payment');
        })
        ->join('product_or_service_requests as posr', 'posr.payment_id', '=', 'je.reference_id')
        ->whereNull('jel.department_id')
        ->whereNotNull('posr.department_id');
    $jelPaymentCount = $jelPaymentQuery->count();
    echo "    [•] Journal Entry Lines (Payments) pending backfill: {$jelPaymentCount}\n";
    if (!$isDryRun && $jelPaymentCount > 0) {
        DB::update("UPDATE journal_entry_lines jel JOIN journal_entries je ON jel.journal_entry_id = je.id AND je.reference_type = 'App\\\\Models\\\\Payment' JOIN product_or_service_requests posr ON posr.payment_id = je.reference_id SET jel.department_id = posr.department_id WHERE jel.department_id IS NULL AND posr.department_id IS NOT NULL");
    }
} else {
    echo "    [•] Transactional & GL backfill will execute on live run after schema migration.\n";
}

// 9. Summary & Verification
echo "\n================================================================================\n";
echo "  SUMMARY OF STREAMLINED DEPARTMENTS                                            \n";
echo "================================================================================\n";
printf("%-4s | %-24s | %-6s | %-8s | %-6s | %-8s\n", "ID", "Department Name", "Code", "Status", "Staff", "Catalog");
echo str_repeat("-", 75) . "\n";

$activeDepts = DB::table('departments')
    ->where('is_active', 1)
    ->orderBy('name')
    ->get();

foreach ($activeDepts as $d) {
    $staffCnt = DB::table('staff')->where('department_id', $d->id)->count();
    $srvCnt = Schema::hasColumn('services', 'department_id')
        ? DB::table('services')->where('department_id', $d->id)->count()
        : 0;
    $prodCnt = Schema::hasColumn('products', 'department_id')
        ? DB::table('products')->where('department_id', $d->id)->count()
        : 0;

    $itemCountStr = $srvCnt > 0 ? "{$srvCnt} srv" : ($prodCnt > 0 ? "{$prodCnt} prd" : "-");

    printf("%-4d | %-24s | %-6s | %-8s | %-6d | %-8s\n",
        $d->id,
        Str::limit($d->name, 24),
        $d->code,
        "Active",
        $staffCnt,
        $itemCountStr
    );
}

echo "================================================================================\n";
if ($isDryRun) {
    echo "DRY-RUN COMPLETE: Run without --dry-run to commit changes to {$targetDb}.\n";
} else {
    echo "SUCCESS: Skane departments streamlined and backfill completed successfully!\n";
}
echo "================================================================================\n";
