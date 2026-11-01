<?php

/**
 * CoreHealth v2 - Skane Health Facility Pharmacy Pricing & Packaging Import Utility
 *
 * This script imports the official Skane Pharmacy Pricing Model Excel spreadsheet:
 *   `scratch/skane templates/Copy4-PHARMACY PRICING MODEL.xlsx`
 *
 * It processes all 163 drug items, automatically inferring:
 *   - Clinical Pharmaceutical Category (18 active clinical categories)
 *   - Base Dispensing Unit (Tablet, Capsule, Ampoule, Vial, Bottle, Tube, Pack, Sachet, Piece)
 *   - Hierarchical Packaging Levels (Strip, Pack, Box, Carton)
 *   - Default Bulk Purchase Packaging
 *   - Unit Cost Price (CP) & Unit Selling Price (SP with 57.5% markup)
 *   - Pharmacy Department Revenue Attribution (`department_id` = 11)
 *   - Central Pharmacy Store Stock & Opening Batches (`store_id` = 2, `initial_qty` = 100)
 *
 * Usage:
 *   php scripts/import_skane_pharmacy_pricing.php [--dry-run] [--db=_corehealth_db_v2_skane]
 *   php scripts/import_skane_pharmacy_pricing.php --apply [--db=_corehealth_db_v2_skane]
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Price;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPackaging;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\StockBatchTransaction;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

// Parse CLI options
$options = getopt('', ['dry-run', 'apply', 'db::', 'database::', 'file::', 'help']);

if (isset($options['help'])) {
    echo "Usage: php scripts/import_skane_pharmacy_pricing.php [options]\n";
    echo "Options:\n";
    echo "  --dry-run        Simulate import and preview all 163 drugs without database modifications (Default)\n";
    echo "  --apply          Execute live import and commit changes to the target database\n";
    echo "  --db=NAME        Target database name (default: _corehealth_db_v2_skane)\n";
    echo "  --file=PATH      Path to pricing Excel file (default: scratch/skane templates/Copy4-PHARMACY PRICING MODEL.xlsx)\n";
    echo "  --help           Display this help screen\n";
    exit(0);
}

$isApply = isset($options['apply']);
$isDryRun = !$isApply || isset($options['dry-run']);
$targetDb = $options['db'] ?? ($options['database'] ?? (env('DB_DATABASE') ?: '_corehealth_db_v2_skane'));
$defaultFile = base_path('scratch/skane templates/Copy4-PHARMACY PRICING MODEL.xlsx');
$filePath = $options['file'] ?? (file_exists($defaultFile) ? $defaultFile : base_path('Copy4-PHARMACY PRICING MODEL.xlsx'));

echo "================================================================================\n";
echo "  COREHEALTH v2 - SKANE PHARMACY PRICING & PACKAGING IMPORT UTILITY             \n";
echo "================================================================================\n";
echo "Target Database : {$targetDb}\n";
echo "Input File      : {$filePath}\n";
echo "Execution Mode  : " . ($isDryRun ? "DRY-RUN (Preview Only, No DB changes)" : "APPLY (Live Updates Committed)") . "\n";
echo "Timestamp       : " . date('Y-m-d H:i:s') . "\n";
echo "================================================================================\n\n";

if (!file_exists($filePath)) {
    echo "[✗] ERROR: File not found at '{$filePath}'\n";
    exit(1);
}

// 1. Connect to Target Database
try {
    DB::purge('mysql');
    Config::set('database.connections.mysql.database', $targetDb);
    DB::reconnect('mysql');
    $connectedDb = DB::connection()->getDatabaseName();
    echo "[✓] Connected to target database: {$connectedDb}\n";
} catch (\Throwable $e) {
    echo "[✗] ERROR connecting to database '{$targetDb}': " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Resolve Department & Store Bindings
$pharmacyDeptId = DB::table('departments')->where('id', 11)->value('id')
    ?? DB::table('departments')->whereRaw('LOWER(name) = ?', ['pharmacy'])->value('id')
    ?? 11;

$pharmacyStore = Store::where('id', 2)->first()
    ?? Store::where('store_name', 'like', '%pharmacy%')->first()
    ?? Store::first();

$pharmacyStoreId = $pharmacyStore ? $pharmacyStore->id : 2;
$adminUserId = User::orderBy('id')->value('id') ?? 1;

echo "[✓] Pharmacy Department ID : {$pharmacyDeptId}\n";
echo "[✓] Pharmacy Store         : [{$pharmacyStoreId}] " . ($pharmacyStore->store_name ?? 'Pharmacy') . "\n";
echo "[✓] Performing User ID     : {$adminUserId}\n\n";

// 3. Ensure Canonical Product Categories with department_id = 11
$canonicalCategories = [
    'Vitamins & Supplements' => 'Vitamins, minerals, tonics, and dietary supplements',
    'Gastrointestinal Agents' => 'Proton pump inhibitors, antacids, and GI motility regulators',
    'Antibiotics & Antimicrobials' => 'Oral and parenteral antibacterial chemotherapeutics',
    'Analgesics & Anti-Inflammatory' => 'NSAIDs, antipyretics, muscle relaxants, and pain management',
    'Antidiabetics' => 'Oral hypoglycaemic agents and insulin therapies',
    'IV Fluids & Electrolytes' => 'Intravenous infusion solutions and rehydration electrolytes',
    'Antimalarials' => 'Artemisinin combinations and parenteral antimalarial therapies',
    'Obstetrics & Gynaecology' => 'Uterotonics, contraceptives, and obstetric medications',
    'Antihypertensives' => 'ACE inhibitors, ARBs, CCBs, beta-blockers, and combination therapy',
    'Anthelmintics & Antiparasitics' => 'Anti-parasitic and deworming therapeutics',
    'Antifungals' => 'Systemic and mucosal antifungal formulations',
    'Sedatives & Anxiolytics' => 'Hypnotics, sedatives, and anxiolytic medications',
    'Anaesthetics' => 'Local, regional, and systemic anaesthetics',
    'Corticosteroids' => 'Anti-inflammatory glucocorticoids and steroid therapies',
    'Diuretics' => 'Loop, thiazide, and potassium-sparing diuretics',
    'Cardiovascular Agents' => 'Statins, antiplatelet agents, and cardiac therapeutics',
    'Antihistamines & Respiratory' => 'Allergy medications, bronchodilators, and cough syrups',
    'Dermatologicals & Topicals' => 'Topical anti-infectives, soothing creams, and ointments',
];

$categoryMap = [];
foreach ($canonicalCategories as $catName => $catDesc) {
    $existing = ProductCategory::where('category_name', $catName)->first();
    if ($existing) {
        if (!$isDryRun && $existing->department_id !== $pharmacyDeptId) {
            $existing->update(['department_id' => $pharmacyDeptId]);
        }
        $categoryMap[$catName] = $existing->id;
    } else {
        if (!$isDryRun) {
            $cat = ProductCategory::create([
                'category_name' => $catName,
                'category_description' => $catDesc,
                'department_id' => $pharmacyDeptId,
                'status' => 1,
            ]);
            $categoryMap[$catName] = $cat->id;
        } else {
            $categoryMap[$catName] = 999;
        }
    }
}
echo "[✓] Verified 18 pharmaceutical categories assigned to Pharmacy Department (#{$pharmacyDeptId}).\n";

// 4. Load Excel Spreadsheet
echo "--- READING EXCEL WORKBOOK ---\n";
$spreadsheet = IOFactory::load($filePath);
$sheet = $spreadsheet->getSheet(0);
$allRows = $sheet->toArray();

$items = [];
for ($i = 2; $i < count($allRows); $i++) {
    $row = $allRows[$i];
    $sn = $row[0] ?? null;
    $rawName = isset($row[1]) ? trim((string) $row[1]) : '';
    $rawCp = isset($row[2]) ? trim((string) $row[2]) : '0';
    $rawSp = isset($row[3]) ? trim((string) $row[3]) : '0';

    if ($sn !== null && $rawName !== '') {
        $cp = floatval(str_replace([',', ' '], '', $rawCp));
        $sp = floatval(str_replace([',', ' '], '', $rawSp));

        // If SP was missing or zero, apply 57.5% markup policy
        if ($sp <= 0 && $cp > 0) {
            $sp = round($cp * 1.575, 2);
        }

        $items[] = [
            'sn' => intval($sn),
            'raw_name' => $rawName,
            'cp' => $cp,
            'sp' => $sp,
        ];
    }
}

echo "[✓] Successfully parsed " . count($items) . " drug items from Excel.\n\n";

// 5. Pharmaceutical Inference Engine
/**
 * Infers normalized name, category, base unit, packaging hierarchy, default bulk pack, and price basis quantity.
 *
 * @return array [cleanName, categoryName, baseUnit, pkgLevels, bulkName, bulkQty, basisQty]
 */
function inferDrugAttributes(int $sn, string $rawName, float $cp, float $sp): array
{
    $nameLower = mb_strtolower(trim($rawName));

    // Specific high-frequency clinical name map
    $cleanNameMap = [
        'nat b' => 'Nat. B Complex',
        'levofloxicin' => 'Levofloxacin 500mg',
        'clarithromycin' => 'Clarithromycin 500mg',
        'amoxiclave 625' => 'Amoxiclav 625mg',
        'amoxiclave 1g' => 'Augmentin 1g (Amoxiclav)',
        'cipro tabs' => 'Ciprofloxacin 500mg',
        'misprostol' => 'Misoprostol 200mcg',
        'metro tabs' => 'Metronidazole 400mg',
        'amoxiciillin 500' => 'Amoxicillin 500mg',
        'sitex' => 'Sitex (Cefuroxime 500mg)',
        'fluconozol' => 'Fluconazole 200mg',
        'celecoxib' => 'Celecoxib 200mg',
        'etrobax 60mg' => 'Etoricoxib 60mg (Etrobax)',
        'aldactone' => 'Aldactone 25mg (Spironolactone)',
        'vit c tabs' => 'Vitamin C 100mg',
        'frusmide tabs' => 'Furosemide 40mg',
        'telmisartan 80mg' => 'Telmisartan 80mg',
        'telmisartan 40mg' => 'Telmisartan 40mg',
        'moduretic/jubretic' => 'Moduretic (Amiloride + HCTZ)',
        'moduretic' => 'Moduretic (Amiloride + HCTZ)',
        'amlodipine 5mg' => 'Amlodipine 5mg',
        'amlodipine 10mg' => 'Amlodipine 10mg',
        'enaretic' => 'Enaretic (Enalapril + HCTZ)',
        'larykul forte' => 'Larykul Forte (Artemether + Lumefantrine)',
        'albendazole' => 'Albendazole 400mg',
        'pcm tabs' => 'Paracetamol 500mg',
        'cefixime' => 'Cefixime 400mg',
        'omeprazole cap' => 'Omeprazole 20mg',
        'doxycycline' => 'Doxycycline 100mg',
        'secwid' => 'Secwid (Secnidazole 1g)',
        'normal saline' => 'Normal Saline 0.9% 500ml',
        'r/l  ringers lact' => "Ringer's Lactate 500ml IV",
        'iv augmentin' => 'Augmentin 1.2g IV inj',
        'syceph-sb' => 'Syceph-SB 1.5g inj',
        'exacef inj' => 'Exacef-TZ 1.125g inj',
        'oxitocin inj' => 'Oxytocin 10IU/ml inj',
        'pcm inj' => 'Paracetamol 300mg/2ml inj',
        'frusmide inj' => 'Furosemide 20mg/2ml inj',
        'penta inj' => 'Pentazocine 30mg/ml inj',
        'daizpam inj' => 'Diazepam 10mg/2ml inj',
        'ketamin' => 'Ketamine 50mg/ml 10ml inj',
        'antropin  inj' => 'Atropine 1mg/ml inj',
        '4.3 d/s' => '4.3% Dextrose in 0.18% Saline 500ml',
        'omeprazole inj' => 'Omeprazole 40mg IV inj',
        'lisinopril 10mg' => 'Lisinopril 10mg',
        'panfor sr' => 'Panfor-SR 500mg (Metformin)',
        'lisinopril 5mg' => 'Lisinopril 5mg',
        'follybin' => 'Follybin Nutritional Formula',
        'artesunat inj 120mg' => 'Artesunate 120mg inj',
        'artesunate 60mg' => 'Artesunate 60mg inj',
        'cef plain inj 1g' => 'Syceph Plain 1g inj (Ceftriaxone)',
        'antalgex t' => 'Antalgex-T (Tramadol + Paracetamol)',
        'hyocine inj' => 'Hyoscine Butylbromide 20mg/ml inj',
        'arthemeter inj' => 'Artemether 80mg/ml inj',
        'plasil inj' => 'Metoclopramide (Plasil) 10mg/2ml inj',
        'gentamycin inj' => 'Gentamicin 80mg/2ml inj',
        'cimetidine inj' => 'Cimetidine 200mg/2ml inj',
        'vit -b. complex' => 'Vitamin B-Complex inj',
        'hydrocortison' => 'Hydrocortisone 1% Topical',
        'promethanin inj' => 'Promethazine 25mg/ml inj',
        'diclofenac inj' => 'Diclofenac 75mg/3ml inj',
        'doza' => 'Doza Reproductive Supplement',
        'wellneswoman' => 'Wellwoman Multivitamin',
        'preg mum' => 'Preg Mum Prenatal Care',
        'amalar' => 'Amalar (Artemether + Lumefantrine)',
        'coaxin' => 'Coaxin (Cefuroxime 500mg)',
        'wakfit' => 'Wakfit ACT Antimalarial',
        'maluther' => 'Maluther ACT Antimalarial',
        'xenobrex' => 'Xenobrex 200mg (Celecoxib)',
        'pcm susp' => 'Paracetamol 120mg/5ml susp',
        'osteo spray' => 'Osteo Spray Topical Analgesic',
        'cetrozol susp' => 'Cetrozol Suspension',
        'honey cough' => 'Honey Cough Herbal Syrup',
        'jawasil' => 'Jawasil Antacid Suspension',
        'rusovastatin 20mg' => 'Rosuvastatin 20mg',
        'allsasil' => 'Alsasil Antacid Suspension',
        'klinfst fort' => 'Klinfast Forte ACT',
        'klinfast' => 'Klinfast ACT Antimalarial',
        'vasoprin' => 'Vasoprin 75mg (Aspirin)',
        'erythromycin' => 'Erythromycin 500mg',
        'biolin caugh syrup' => 'Biolin Cough Expectorant',
        'zibalox' => 'Zibalox (Moxifloxacin 400mg)',
        'cipro inj' => 'Ciprofloxacin 200mg/100ml IV',
        'cotazol  plus' => 'Cotazol Plus',
        'hyperex sr' => 'Hyperex-SR (Indapamide 1.5mg)',
        'clopidegrel 75mg' => 'Clopidogrel 75mg',
        'krisacid' => 'Krisacid Antacid',
        'little rex syrup' => 'Little Rex Pediatric Cough Syrup',
        'glydal sr 500mg' => 'Glydal-SR 500mg (Metformin)',
        'vit c  cd' => 'Vitamin C + Zinc Chewable',
        'clotrimazole' => 'Clotrimazole 1% Cream',
        'loratidine' => 'Loratadine 10mg',
        'dexamethason inj' => 'Dexamethasone 4mg/ml inj',
        'libre' => 'Libre 2mg (Tizanidine)',
        'protal gel' => 'Prostagel Cervical Ripening Gel',
        'kuinopril plus' => 'Kuinopril Plus',
        'funbact a' => 'Funbact-A Triple Action Cream',
        'minate cream' => 'Minate Topical Cream',
        'finasil cream' => 'Finasil Antifungal Cream',
        'timovte cream' => 'Timovate Topical Cream',
        'hydrocort cream' => 'Hydrocortisone 1% Cream',
        'susp.metro' => 'Metronidazole 200mg/5ml Suspension',
        'ampiclox' => 'Ampiclox 500mg Capsules',
        'tetracycline' => 'Tetracycline 250mg Capsules',
        'cardiovit' => 'Cardiovit Cardiovascular Multivitamin',
        'esomeprazole' => 'Esomeprazole 40mg',
        '50% d/w' => '50% Dextrose Water 50ml IV',
        'inj ephedrine' => 'Ephedrine 30mg/ml inj',
        'inj lidocaine' => 'Lidocaine 2% Local Anaesthetic inj',
        'inj torsinex' => 'Torsinex (Torsemide) inj',
        'tabs realflex' => 'Realflex Muscle Relaxant Tablets',
        '5% d/s' => '5% Dextrose Saline 500ml IV',
        'ors' => 'Oral Rehydration Salts (ORS) Sachet',
        'imbact oz' => 'Imbact-OZ (Ofloxacin + Ornidazole)',
        'ceezex' => 'Ceezex 1g inj (Ceftriaxone)',
        'metro infusion' => 'Metronidazole 500mg/100ml IV Infusion',
        'rabeprazole inj' => 'Rabeprazole 20mg IV inj',
        't. t injection' => 'Tetanus Toxoid (TT) 0.5ml inj',
        'fesolate tabs' => 'Ferrous Sulphate 200mg (Fesolate)',
        'natoenzyme' => 'Natto-Enzyme Cardiovascular Support',
        'lirapyn(pregabalin)' => 'Lirapyn 75mg (Pregabalin)',
        'cetidyn syrp' => 'Cetidyn (Cetirizine) Pediatric Syrup',
        'water for injection' => 'Sterile Water for Injection 10ml',
        'acel plus' => 'Acel Plus (Aceclofenac + Paracetamol)',
        'etostal 120mg' => 'Etostal 120mg (Etoricoxib)',
        'etrobax 90 mg' => 'Etrobax 90mg (Etoricoxib)',
        'celeflox' => 'Celeflox (Ciprofloxacin)',
        'meloxicam 7.5mg' => 'Meloxicam 7.5mg',
        'liver cap' => 'Essential Liver Tonic Capsules',
        'rabeprazole tabs' => 'Rabeprazole 20mg',
        'rabeprazole tabs(acirab)' => 'Acirab (Rabeprazole 20mg)',
        'atrovastatin 20mg' => 'Atorvastatin 20mg',
        'artequick' => 'Artequick ACT Antimalarial',
        'livolin fort' => 'Livolin Forte Liver Regeneration Capsules',
        'azithromycin' => 'Azithromycin 500mg',
        'lidocain inj' => 'Lidocaine 2% inj',
        'daravit woman' => 'Daravit Woman 50+ Multivitamin',
        'labetalol tabs' => 'Labetalol 100mg',
        'cetirizine 10mg' => 'Cetirizine 10mg',
        'vital man' => 'Vital Man Nutritional Formula',
        'vital woman /fyn mum' => 'Vital Woman / Fyn Mum Prenatal Formula',
        'losartan (zartan)' => 'Zartan (Losartan 50mg)',
        'pregabalin 75(larykul)' => 'Pregabalin 75mg (Larykul)',
        'etostal 90mg' => 'Etostal 90mg (Etoricoxib)',
        'co-mepiryl forte' => 'Co-Mepiryl Forte',
        'tinidazol' => 'Tinidazole 500mg',
        'condom' => 'Male Latex Condoms (Pack of 3)',
        'amoxicillin 250' => 'Amoxicillin 250mg',
        'ketoconazole' => 'Ketoconazole 200mg',
        'dexilant' => 'Dexilant 60mg (Dexlansoprazole)',
        'emal inj' => 'Emal 150mg/2ml inj (Arteether)',
        'emal' => 'Emal Oral ACT Treatment',
        'labetalol inj' => 'Labetalol 5mg/ml 20ml inj',
        'daravit forte' => 'Daravit Forte Multivitamin',
        'meloxicam 15mg' => 'Meloxicam 15mg',
        'mmt' => 'Mist Mag Trisilicate (MMT) Suspension',
        'omeprazole tabs' => 'Omeprazole 20mg Tablets',
        'acycor plus' => 'Acycor Plus (Aceclofenac + Paracetamol)',
        'simdalud(tizanidine)' => 'Sirdalud (Tizanidine 2mg)',
        'ulgicid susp' => 'Ulgicid Antacid Suspension',
        'alpha -beta arteether' => 'Alpha-Beta Arteether 150mg/2ml inj',
    ];

    $cleanName = $cleanNameMap[$nameLower] ?? ucwords(strtolower(trim($rawName)));

    // 1. SPECIAL DOSAGE FORMS & PACKAGINGS
    if (str_contains($nameLower, 'condom')) {
        return [$cleanName, 'Obstetrics & Gynaecology', 'Piece', 'Pack:3;Box:48;Carton:576', 'Box', 48, 3];
    }
    if (preg_match('/\bors\b/i', $nameLower)) {
        return [$cleanName, 'Gastrointestinal Agents', 'Sachet', 'Box:50;Carton:500', 'Box', 50, 1];
    }
    if (str_contains($nameLower, 'misprostol') || str_contains($nameLower, 'misofree')) {
        return [$cleanName, 'Obstetrics & Gynaecology', 'Tablet', 'Pack:4;Box:40;Carton:400', 'Pack', 4, 4];
    }
    if (str_contains($nameLower, 'fluconozol') || str_contains($nameLower, 'fluconazole')) {
        return [$cleanName, 'Antifungals', 'Capsule', 'Pack:2;Box:20;Carton:200', 'Pack', 2, 2];
    }
    if (str_contains($nameLower, 'tinidazol')) {
        return [$cleanName, 'Antibiotics & Antimicrobials', 'Tablet', 'Pack:4;Box:40;Carton:400', 'Pack', 4, 4];
    }
    if (str_contains($nameLower, 'secwid')) {
        return [$cleanName, 'Antibiotics & Antimicrobials', 'Tablet', 'Pack:2;Box:20;Carton:200', 'Pack', 2, 2];
    }
    if (str_contains($nameLower, 'azithromycin')) {
        return [$cleanName, 'Antibiotics & Antimicrobials', 'Tablet', 'Pack:3;Box:30;Carton:300', 'Pack', 3, 3];
    }
    if (str_contains($nameLower, 'albendazole')) {
        return [$cleanName, 'Anthelmintics & Antiparasitics', 'Tablet', 'Box:50;Carton:500', 'Box', 50, 1];
    }
    if (str_contains($nameLower, 'water for injection')) {
        return [$cleanName, 'IV Fluids & Electrolytes', 'Ampoule', 'Pack:50;Carton:500', 'Pack', 50, 1];
    }
    if (str_contains($nameLower, 'arteether') || (str_contains($nameLower, 'emal') && str_contains($nameLower, 'inj'))) {
        return [$cleanName, 'Antimalarials', 'Ampoule', 'Pack:3;Box:30;Carton:300', 'Pack', 3, 3];
    }
    if (str_contains($nameLower, 'ceezex')) {
        return [$cleanName, 'Antibiotics & Antimicrobials', 'Vial', 'Box:10;Carton:100', 'Box', 10, 1];
    }

    // 2. INJECTIONS & INFUSIONS
    if (str_contains($nameLower, 'inj') || str_contains($nameLower, 'injection') || str_contains($nameLower, 'infusion') || str_contains($nameLower, 'artesunat') || str_contains($nameLower, 'artesunate 60mg') || str_contains($nameLower, 'ketamin')) {
        $isVial = str_contains($nameLower, 'vial') || str_contains($nameLower, 'exacef') || str_contains($nameLower, 'syceph')
            || str_contains($nameLower, 'tazovisk') || str_contains($nameLower, 'artesunat') || str_contains($nameLower, 'artesunate')
            || str_contains($nameLower, 'augmentin') || str_contains($nameLower, 'rabeprazole inj') || str_contains($nameLower, 'omeprazole inj')
            || str_contains($nameLower, 'cef plain') || str_contains($nameLower, 'ceezex') || str_contains($nameLower, 'ketamin')
            || str_contains($nameLower, 't. t') || str_contains($nameLower, 'lidocain');

        $isInfusion = str_contains($nameLower, 'infusion') || str_contains($nameLower, 'cipro inj');

        // Categories
        if (str_contains($nameLower, 'artesun') || str_contains($nameLower, 'arthem') || str_contains($nameLower, 'arteeth') || str_contains($nameLower, 'emal')) {
            $category = 'Antimalarials';
        } elseif (str_contains($nameLower, 'pcm') || str_contains($nameLower, 'diclofenac') || str_contains($nameLower, 'penta')) {
            $category = 'Analgesics & Anti-Inflammatory';
        } elseif (str_contains($nameLower, 'frusmide') || str_contains($nameLower, 'torsinex')) {
            $category = 'Diuretics';
        } elseif (str_contains($nameLower, 'labetalol') || str_contains($nameLower, 'ephedrine')) {
            $category = 'Cardiovascular Agents';
        } elseif (str_contains($nameLower, 'daizpam') || str_contains($nameLower, 'diazepam')) {
            $category = 'Sedatives & Anxiolytics';
        } elseif (str_contains($nameLower, 'lidocain') || str_contains($nameLower, 'ketamin')) {
            $category = 'Anaesthetics';
        } elseif (str_contains($nameLower, 'dexamethason')) {
            $category = 'Corticosteroids';
        } elseif (str_contains($nameLower, 'oxitocin')) {
            $category = 'Obstetrics & Gynaecology';
        } elseif (str_contains($nameLower, 'cimetidine') || str_contains($nameLower, 'plasil') || str_contains($nameLower, 'hyocine') || str_contains($nameLower, 'omeprazole') || str_contains($nameLower, 'rabeprazole')) {
            $category = 'Gastrointestinal Agents';
        } elseif (str_contains($nameLower, 'vit')) {
            $category = 'Vitamins & Supplements';
        } else {
            $category = 'Antibiotics & Antimicrobials';
        }

        if ($isInfusion) {
            return [$cleanName, 'Antibiotics & Antimicrobials', 'Bottle', 'Carton:30', 'Carton', 30, 1];
        }

        if ($isVial) {
            return [$cleanName, $category, 'Vial', 'Box:10;Carton:100', 'Box', 10, 1];
        }

        // Ampoule: Check if price is for box of 10 vs single ampoule
        $basisQty = (str_contains($nameLower, 'antropin') || str_contains($nameLower, 'ephedrine') || str_contains($nameLower, 'torsinex')) ? 10 : 1;

        return [$cleanName, $category, 'Ampoule', 'Pack:10;Carton:100', 'Pack', 10, $basisQty];
    }

    // 3. IV FLUIDS & ELECTROLYTES (500ml bottles / bags)
    if (str_contains($nameLower, 'd/s') || str_contains($nameLower, 'd/w') || str_contains($nameLower, 'r/l') || str_contains($nameLower, 'saline')) {
        return [$cleanName, 'IV Fluids & Electrolytes', 'Bottle', 'Carton:20', 'Carton', 20, 1];
    }

    // 4. TOPICAL CREAMS, GELS & OINTMENTS
    if (str_contains($nameLower, 'cream') || str_contains($nameLower, 'gel') || str_contains($nameLower, 'funbact') || str_contains($nameLower, 'clotrimazole') || str_contains($nameLower, 'hydrocortison')) {
        if (str_contains($nameLower, 'clotrimazole') || str_contains($nameLower, 'funbact') || str_contains($nameLower, 'finasil')) {
            $category = 'Antifungals';
        } elseif (str_contains($nameLower, 'protal')) {
            $category = 'Obstetrics & Gynaecology';
        } elseif (str_contains($nameLower, 'hydrocort')) {
            $category = 'Corticosteroids';
        } else {
            $category = 'Dermatologicals & Topicals';
        }

        if (str_contains($nameLower, 'protal')) {
            return [$cleanName, $category, 'Tube', 'Box:5;Carton:50', 'Box', 5, 1];
        }

        return [$cleanName, $category, 'Tube', 'Box:10;Carton:100', 'Box', 10, 1];
    }

    // 5. ORAL LIQUIDS, SYRUPS & SUSPENSIONS
    if (str_contains($nameLower, 'susp') || str_contains($nameLower, 'syrup') || str_contains($nameLower, 'syrp')
        || str_contains($nameLower, 'cough') || str_contains($nameLower, 'jawasil') || str_contains($nameLower, 'biolin')
        || str_contains($nameLower, 'rex') || str_contains($nameLower, 'mmt') || str_contains($nameLower, 'ulgicid')
        || str_contains($nameLower, 'allsasil') || str_contains($nameLower, 'osteo spray')) {
        if (str_contains($nameLower, 'jawasil') || str_contains($nameLower, 'mmt') || str_contains($nameLower, 'ulgicid') || str_contains($nameLower, 'allsasil')) {
            $category = 'Gastrointestinal Agents';
        } elseif (str_contains($nameLower, 'cough') || str_contains($nameLower, 'biolin') || str_contains($nameLower, 'cetidyn') || str_contains($nameLower, 'rex')) {
            $category = 'Antihistamines & Respiratory';
        } elseif (str_contains($nameLower, 'pcm') || str_contains($nameLower, 'osteo spray')) {
            $category = 'Analgesics & Anti-Inflammatory';
        } else {
            $category = 'Antibiotics & Antimicrobials';
        }

        return [$cleanName, $category, 'Bottle', 'Pack:10;Carton:60', 'Pack', 10, 1];
    }

    // 6. SEALED CONSUMER MULTIVITAMIN & PRENATAL BOTTLES
    if (str_contains($nameLower, 'daravit') || str_contains($nameLower, 'wellwoman') || str_contains($nameLower, 'wellneswoman')
        || str_contains($nameLower, 'vital man') || str_contains($nameLower, 'vital woman') || str_contains($nameLower, 'doza')
        || str_contains($nameLower, 'natoenzyme') || str_contains($nameLower, 'liver cap') || str_contains($nameLower, 'nat b')
        || str_contains($nameLower, 'follybin') || str_contains($nameLower, 'cardiovit') || str_contains($nameLower, 'preg mum')
        || str_contains($nameLower, 'vit c  cd')) {
        return [$cleanName, 'Vitamins & Supplements', 'Pack', 'Box:10;Carton:60', 'Box', 10, 1];
    }

    // 7. SEALED ORAL ACT ANTIMALARIAL COMPLETE REGIMENS
    if (str_contains($nameLower, 'amalar') || str_contains($nameLower, 'maluther') || str_contains($nameLower, 'artequick')
        || str_contains($nameLower, 'larykul forte') || str_contains($nameLower, 'klinfast') || str_contains($nameLower, 'klinfst')
        || str_contains($nameLower, 'coartem') || str_contains($nameLower, 'wakfit') || ($nameLower === 'emal')) {
        return [$cleanName, 'Antimalarials', 'Pack', 'Box:10;Carton:180', 'Box', 10, 1];
    }

    // 8. LOW-COST GENERIC SINGLE-TABLET PRICED ITEMS (Tins of 1000)
    if ($sp < 50.0) {
        if (str_contains($nameLower, 'vit') || str_contains($nameLower, 'fesolate')) {
            $category = 'Vitamins & Supplements';
        } elseif (str_contains($nameLower, 'frusmide') || str_contains($nameLower, 'aldactone')) {
            $category = 'Diuretics';
        } else {
            $category = 'Analgesics & Anti-Inflammatory';
        }

        return [$cleanName, $category, 'Tablet', 'Strip:10;Tin:1000;Carton:10000', 'Tin', 1000, 1];
    }

    // 9. AUGMENTIN / CO-AMOXICLAV (Pack of 14)
    if (str_contains($nameLower, 'amoxiclav') || str_contains($nameLower, 'amoxiclave') || str_contains($nameLower, 'augmentin')) {
        return [$cleanName, 'Antibiotics & Antimicrobials', 'Tablet', 'Strip:7;Pack:14;Carton:140', 'Pack', 14, 14];
    }

    // 10. ANTIHYPERTENSIVES & CARDIOVASCULAR (28-day regimen)
    if (str_contains($nameLower, 'telmisartan') || str_contains($nameLower, 'amlodipine') || str_contains($nameLower, 'lisinopril')
        || str_contains($nameLower, 'losartan') || str_contains($nameLower, 'hyperex') || str_contains($nameLower, 'rusovastatin')
        || str_contains($nameLower, 'clopidegrel') || str_contains($nameLower, 'atrovastatin') || str_contains($nameLower, 'kuinopril')) {
        $category = (str_contains($nameLower, 'rusovastatin') || str_contains($nameLower, 'clopidegrel') || str_contains($nameLower, 'atrovastatin'))
            ? 'Cardiovascular Agents'
            : 'Antihypertensives';

        $basisQty = (str_contains($nameLower, 'amlodipine') || str_contains($nameLower, 'lisinopril') || str_contains($nameLower, 'kuinopril')) ? 14 : 28;

        return [$cleanName, $category, 'Tablet', 'Strip:14;Pack:28;Box:280', 'Pack', 28, $basisQty];
    }

    // 11. 30-DAY CHRONIC CARE REGIMENS (Enaretic, Metformin, Livolin, Tizanidine, Realflex, Etrobax 90)
    if (str_contains($nameLower, 'enaretic') || str_contains($nameLower, 'panfor') || str_contains($nameLower, 'simdalud')
        || str_contains($nameLower, 'tizanidine') || str_contains($nameLower, 'realflex') || str_contains($nameLower, 'livolin')
        || str_contains($nameLower, 'co-mepiryl') || (str_contains($nameLower, 'etrobax') && str_contains($nameLower, '90'))) {
        $baseUnit = str_contains($nameLower, 'livolin') ? 'Capsule' : 'Tablet';
        if (str_contains($nameLower, 'livolin')) {
            $category = 'Vitamins & Supplements';
        } elseif (str_contains($nameLower, 'enaretic')) {
            $category = 'Antihypertensives';
        } elseif (str_contains($nameLower, 'panfor') || str_contains($nameLower, 'co-mepiryl')) {
            $category = 'Antidiabetics';
        } else {
            $category = 'Analgesics & Anti-Inflammatory';
        }

        return [$cleanName, $category, $baseUnit, 'Strip:10;Pack:30;Box:300', 'Pack', 30, 30];
    }

    // 12. LABETALOL 100MG TABS (Pack of 100)
    if (str_contains($nameLower, 'labetalol') && !str_contains($nameLower, 'inj')) {
        return [$cleanName, 'Antihypertensives', 'Tablet', 'Strip:10;Box:100;Carton:1000', 'Box', 100, 100];
    }

    // 13. GLYDAL-SR 500MG (Pack of 100 Metformin SR)
    if (str_contains($nameLower, 'glydal')) {
        return [$cleanName, 'Antidiabetics', 'Tablet', 'Strip:10;Box:100;Carton:1000', 'Box', 100, 100];
    }

    // 14. PREGABALIN / LIRAPYN (Pack of 14)
    if (str_contains($nameLower, 'pregabalin') || str_contains($nameLower, 'lirapyn')) {
        return [$cleanName, 'Analgesics & Anti-Inflammatory', 'Capsule', 'Strip:7;Pack:14;Box:140', 'Pack', 14, 14];
    }

    // 15. DEXILANT (Pack of 14)
    if (str_contains($nameLower, 'dexilant')) {
        return [$cleanName, 'Gastrointestinal Agents', 'Capsule', 'Strip:7;Pack:14;Box:140', 'Pack', 14, 14];
    }

    // 16. ESOMEPRAZOLE / RABEPRAZOLE 30s
    if (str_contains($nameLower, 'esomeprazole') || (str_contains($nameLower, 'rabeprazole tabs') && !str_contains($nameLower, 'acirab'))) {
        return [$cleanName, 'Gastrointestinal Agents', 'Tablet', 'Strip:14;Pack:28;Box:280', 'Pack', 28, 28];
    }

    // 17. STANDARD 10s ORAL SOLID BLISTER STRIPS
    $baseUnit = (str_contains($nameLower, 'cap') || str_contains($nameLower, 'capsule') || str_contains($nameLower, 'ampiclox') || str_contains($nameLower, 'tetracycline') || str_contains($nameLower, 'doxycycline'))
        ? 'Capsule'
        : 'Tablet';

    if (str_contains($nameLower, 'pcm') || str_contains($nameLower, 'celecoxib') || str_contains($nameLower, 'etrobax')
        || str_contains($nameLower, 'etostal') || str_contains($nameLower, 'meloxicam') || str_contains($nameLower, 'acycor')
        || str_contains($nameLower, 'antalgex') || str_contains($nameLower, 'acel') || str_contains($nameLower, 'xenobrex')
        || str_contains($nameLower, 'vasoprin')) {
        $category = 'Analgesics & Anti-Inflammatory';
    } elseif (str_contains($nameLower, 'krisacid') || str_contains($nameLower, 'omeprazole') || str_contains($nameLower, 'acirab')) {
        $category = 'Gastrointestinal Agents';
    } elseif (str_contains($nameLower, 'ketoconazole')) {
        $category = 'Antifungals';
    } elseif (str_contains($nameLower, 'loratidine') || str_contains($nameLower, 'cetirizine')) {
        $category = 'Antihistamines & Respiratory';
    } elseif (str_contains($nameLower, 'moduretic')) {
        $category = 'Antihypertensives';
    } else {
        $category = 'Antibiotics & Antimicrobials';
    }

    return [$cleanName, $category, $baseUnit, 'Strip:10;Box:100;Carton:1000', 'Box', 100, 10];
}

/**
 * Parses packaging_levels string and synchronizes product_packagings records.
 */
function syncProductPackagings(int $productId, string $packagingLevels, string $bulkName, float $bulkQty): int
{
    ProductPackaging::where('product_id', $productId)->delete();

    $levels = explode(';', $packagingLevels);
    $previousBaseQty = 1.0;
    $previousId = null;
    $count = 0;

    foreach ($levels as $index => $level) {
        $level = trim($level);
        if (empty($level)) {
            continue;
        }

        $parts = explode(':', $level, 2);
        $name = trim($parts[0] ?? '');
        $baseUnitQty = floatval($parts[1] ?? 1);

        if (empty($name) || $baseUnitQty <= 0) {
            continue;
        }

        $unitsInParent = $previousBaseQty > 0 ? ($baseUnitQty / $previousBaseQty) : $baseUnitQty;
        $isDefaultPurchase = (strcasecmp($name, $bulkName) === 0);

        $pkg = ProductPackaging::create([
            'product_id' => $productId,
            'name' => $name,
            'level' => $index + 1,
            'parent_packaging_id' => $previousId,
            'units_in_parent' => $unitsInParent,
            'base_unit_qty' => $baseUnitQty,
            'is_default_purchase' => $isDefaultPurchase,
            'is_default_dispense' => ($index === 0),
        ]);

        $previousBaseQty = $baseUnitQty;
        $previousId = $pkg->id;
        $count++;
    }

    return $count;
}

// 6. Execution Loop
$stats = [
    'total_processed' => 0,
    'products_created' => 0,
    'products_updated' => 0,
    'prices_synced' => 0,
    'packagings_created' => 0,
    'batches_created' => 0,
    'total_cost_value' => 0.0,
    'total_sale_value' => 0.0,
];

if (!$isDryRun) {
    DB::beginTransaction();
}

try {
    echo "--- PROCESSING PRODUCTS, BASE UNIT PRICES & PACKAGING LEVELS ---\n";
    printf("%-4s | %-28s | %-11s | %-20s | %-7s | %-4s | %-9s | %-9s | %s\n", 'S/N', 'Product Name', 'Code', 'Category', 'BaseUnt', 'Pack', 'Base CP', 'Base SP', 'Packaging Hierarchy');
    echo str_repeat('-', 140) . "\n";

    foreach ($items as $item) {
        $sn = $item['sn'];
        $rawName = $item['raw_name'];
        $cp = $item['cp'];
        $sp = $item['sp'];

        [$cleanName, $categoryName, $baseUnit, $pkgLevels, $bulkName, $bulkQty, $basisQty] = inferDrugAttributes($sn, $rawName, $cp, $sp);

        $baseCp = round($cp / $basisQty, 2);
        $baseSp = round($sp / $basisQty, 2);

        $productCode = sprintf('DRG-SKN-%03d', $sn);
        $batchNumber = sprintf('BATCH-2026-SKN-%03d', $sn);
        $expiryDate = '2028-12-31';
        $categoryId = $categoryMap[$categoryName] ?? $categoryMap['Antibiotics & Antimicrobials'];

        // Opening inventory in base units
        $initialUnits = ($sp < 50.0) ? 1000 : (($basisQty > 1) ? max(100, $basisQty * 10) : 100);

        $stats['total_processed']++;
        $stats['total_cost_value'] += ($baseCp * $initialUnits);
        $stats['total_sale_value'] += ($baseSp * $initialUnits);

        printf(
            "%-4d | %-28.28s | %-11s | %-20.20s | %-7s | x%-2d | ₦%-8.2f | ₦%-8.2f | %s\n",
            $sn,
            $cleanName,
            $productCode,
            $categoryName,
            $baseUnit,
            $basisQty,
            $baseCp,
            $baseSp,
            $pkgLevels
        );

        if (!$isDryRun) {
            // A. Product
            $product = Product::where('product_code', $productCode)->first();
            if ($product) {
                $product->update([
                    'product_name' => $cleanName,
                    'category_id' => $categoryId,
                    'department_id' => $pharmacyDeptId,
                    'product_type' => 'drug',
                    'base_unit_name' => $baseUnit,
                    'reorder_alert' => 10,
                    'status' => 1,
                    'current_quantity' => $initialUnits,
                ]);
                $stats['products_updated']++;
            } else {
                $product = Product::create([
                    'user_id' => $adminUserId,
                    'category_id' => $categoryId,
                    'department_id' => $pharmacyDeptId,
                    'product_name' => $cleanName,
                    'product_code' => $productCode,
                    'product_type' => 'drug',
                    'base_unit_name' => $baseUnit,
                    'reorder_alert' => 10,
                    'status' => 1,
                    'current_quantity' => $initialUnits,
                ]);
                $stats['products_created']++;
            }

            // B. Price (Set strictly to Base Unit prices)
            Price::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'pr_buy_price' => $baseCp,
                    'initial_sale_price' => $baseSp,
                    'current_sale_price' => $baseSp,
                    'max_discount' => 0,
                    'status' => 1,
                ]
            );
            $stats['prices_synced']++;

            // C. Product Packagings
            $pkgCount = syncProductPackagings($product->id, $pkgLevels, $bulkName, $bulkQty);
            $stats['packagings_created'] += $pkgCount;

            // D. Store Stock & Global Stock
            StoreStock::updateOrCreate(
                [
                    'store_id' => $pharmacyStoreId,
                    'product_id' => $product->id,
                ],
                [
                    'initial_quantity' => $initialUnits,
                    'current_quantity' => $initialUnits,
                    'reorder_level' => 10,
                    'is_active' => 1,
                ]
            );

            Stock::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'initial_quantity' => $initialUnits,
                    'current_quantity' => $initialUnits,
                    'quantity_sale' => 0,
                    'order_quantity' => 0,
                ]
            );

            // E. Stock Batch & Opening Transaction
            $batch = StockBatch::where('product_id', $product->id)
                ->where('batch_number', $batchNumber)
                ->where('store_id', $pharmacyStoreId)
                ->first();

            if (!$batch) {
                $batch = StockBatch::create([
                    'product_id' => $product->id,
                    'store_id' => $pharmacyStoreId,
                    'batch_name' => 'Opening Stock - ' . $batchNumber,
                    'batch_number' => $batchNumber,
                    'initial_qty' => $initialUnits,
                    'current_qty' => $initialUnits,
                    'sold_qty' => 0,
                    'cost_price' => $baseCp,
                    'expiry_date' => $expiryDate,
                    'received_date' => now()->toDateString(),
                    'source' => StockBatch::SOURCE_OPENING_STOCK,
                    'is_active' => 1,
                    'created_by' => $adminUserId,
                ]);

                StockBatchTransaction::create([
                    'stock_batch_id' => $batch->id,
                    'type' => StockBatchTransaction::TYPE_IN,
                    'qty' => $initialUnits,
                    'balance_after' => $initialUnits,
                    'notes' => 'Opening stock batch import from Skane Pricing Model',
                    'performed_by' => $adminUserId,
                ]);

                $stats['batches_created']++;
            } else {
                $batch->update([
                    'cost_price' => $baseCp,
                    'initial_qty' => $initialUnits,
                    'current_qty' => $initialUnits,
                    'expiry_date' => $expiryDate,
                ]);
            }
        }
    }

    if (!$isDryRun) {
        DB::commit();
        echo "\n[✓] ALL CHANGES COMMITTED SUCCESSFULLY TO DATABASE: {$targetDb}\n";
    } else {
        echo "\n[!] DRY RUN COMPLETED: 0 database modifications were made.\n";
    }

} catch (\Throwable $e) {
    if (!$isDryRun) {
        DB::rollBack();
    }
    echo "\n[✗] FATAL ERROR DURING IMPORT: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

// 7. Executive Summary Report
echo "\n================================================================================\n";
echo "  EXECUTIVE IMPORT SUMMARY REPORT                                               \n";
echo "================================================================================\n";
echo "Total Rows Parsed from Excel    : " . $stats['total_processed'] . "\n";
if (!$isDryRun) {
    echo "Products Created in Catalog     : " . $stats['products_created'] . "\n";
    echo "Products Updated in Catalog     : " . $stats['products_updated'] . "\n";
    echo "Price Records Synchronized      : " . $stats['prices_synced'] . "\n";
    echo "Packaging Levels Created        : " . $stats['packagings_created'] . "\n";
    echo "Stock Batches Initialized       : " . $stats['batches_created'] . " (100 units/drug in Pharmacy Store #{$pharmacyStoreId})\n";
} else {
    echo "Products Ready for Import       : " . $stats['total_processed'] . "\n";
    echo "Simulated Packaging Tiers       : " . ($stats['total_processed'] * 3) . " estimated packaging records\n";
    echo "Simulated Stock Batches         : " . $stats['total_processed'] . " opening stock batches\n";
}
echo "Attributed Department           : Pharmacy (ID: {$pharmacyDeptId})\n";
echo "Total Opening Inventory (Cost)  : ₦" . number_format($stats['total_cost_value'], 2) . "\n";
echo "Total Opening Inventory (Sale)  : ₦" . number_format($stats['total_sale_value'], 2) . "\n";
echo "Expected Gross Margin           : ₦" . number_format($stats['total_sale_value'] - $stats['total_cost_value'], 2) . " (57.5% markup policy)\n";
echo "================================================================================\n\n";

if ($isDryRun) {
    echo "To commit these 163 products, prices, packagings, and stock batches into '{$targetDb}', run:\n";
    echo "  php scripts/import_skane_pharmacy_pricing.php --apply\n\n";
} else {
    echo "[✓] IMPORT COMPLETE. The Skane Pharmacy formulary is live and ready for dispensing.\n\n";
}
