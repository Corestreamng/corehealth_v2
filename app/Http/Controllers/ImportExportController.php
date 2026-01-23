<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\patient;
use App\Models\Staff;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Price;
use App\Models\Stock;
use App\Models\StoreStock;
use App\Models\Store;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\Specialization;
use App\Models\Clinic;
use App\Models\Hmo;
use Spatie\Permission\Models\Role;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Protection;

/**
 * ImportExportController
 *
 * Handles XLSX import/export with dropdown validations for:
 * - Products (Stock)
 * - Services (Labs, Imaging, Nursing, etc.)
 * - Staff
 * - Patients
 *
 * Each import creates related records (User, Price, Stock) as needed.
 * Templates include dropdown lists for valid values only.
 */
class ImportExportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:SUPERADMIN|ADMIN']);
    }

    /**
     * Display the import/export dashboard
     */
    public function index()
    {
        $stats = [
            'products' => Product::count(),
            'services' => Service::count(),
            'staff' => Staff::count(),
            'patients' => patient::count(),
        ];

        $categories = [
            'products' => ProductCategory::orderBy('category_name')->get(),
            'services' => ServiceCategory::orderBy('category_name')->get(),
        ];

        $stores = Store::where('status', 1)->orderBy('store_name')->get();
        $specializations = Specialization::orderBy('name')->get();
        $clinics = Clinic::orderBy('name')->get();
        $hmos = Hmo::orderBy('name')->get();
        $roles = Role::orderBy('name')->get();

        return view('admin.import-export.index', compact(
            'stats', 'categories', 'stores', 'specializations', 'clinics', 'hmos', 'roles'
        ));
    }

    // ========================================
    // TEMPLATE DOWNLOADS (XLSX with Dropdowns)
    // ========================================

    /**
     * Download XLSX template for products with dropdown validations
     */
    public function downloadProductTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Products');

        // Headers
        $headers = [
            'A1' => 'product_name',
            'B1' => 'product_code',
            'C1' => 'category_name',
            'D1' => 'description',
            'E1' => 'unit',
            'F1' => 'cost_price',
            'G1' => 'sale_price',
            'H1' => 'reorder_level',
            'I1' => 'initial_quantity',
            'J1' => 'store_name',
            'K1' => 'batch_number',
            'L1' => 'expiry_date',
            'M1' => 'is_active',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Style headers
        $this->styleHeaders($sheet, 'A1:M1');

        // Get valid values for dropdowns
        $categories = ProductCategory::orderBy('category_name')->pluck('category_name')->toArray();
        $stores = Store::where('status', 1)->orderBy('store_name')->pluck('store_name')->toArray();
        $units = ['tablets', 'capsules', 'bottles', 'vials', 'ampoules', 'sachets', 'tubes', 'boxes', 'packs', 'pieces'];
        $activeOptions = ['1', '0'];

        // Create a hidden sheet for dropdown values
        $lookupSheet = $spreadsheet->createSheet();
        $lookupSheet->setTitle('_Lookups');

        // Populate lookup values
        $row = 1;
        foreach ($categories as $cat) {
            $lookupSheet->setCellValue('A' . $row, $cat);
            $row++;
        }
        $catLastRow = $row - 1;

        $row = 1;
        foreach ($stores as $store) {
            $lookupSheet->setCellValue('B' . $row, $store);
            $row++;
        }
        $storeLastRow = $row - 1;

        $row = 1;
        foreach ($units as $unit) {
            $lookupSheet->setCellValue('C' . $row, $unit);
            $row++;
        }
        $unitLastRow = $row - 1;

        $row = 1;
        foreach ($activeOptions as $opt) {
            $lookupSheet->setCellValue('D' . $row, $opt);
            $row++;
        }

        // Hide the lookup sheet
        $lookupSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

        // Add dropdown validations for 100 rows
        for ($i = 2; $i <= 101; $i++) {
            // Category dropdown
            if ($catLastRow > 0) {
                $this->addDropdownValidation($sheet, "C{$i}", "'_Lookups'!\$A\$1:\$A\${$catLastRow}");
            }

            // Store dropdown
            if ($storeLastRow > 0) {
                $this->addDropdownValidation($sheet, "J{$i}", "'_Lookups'!\$B\$1:\$B\${$storeLastRow}");
            }

            // Unit dropdown
            $this->addDropdownValidation($sheet, "E{$i}", "'_Lookups'!\$C\$1:\$C\${$unitLastRow}");

            // Is Active dropdown
            $this->addDropdownValidation($sheet, "M{$i}", "'_Lookups'!\$D\$1:\$D\$2");
        }

        // Add sample data
        $sampleData = [
            ['Paracetamol 500mg', 'PARA-500', $categories[0] ?? 'General', 'Pain relief tablets', 'tablets', '50.00', '100.00', '100', '500', $stores[0] ?? '', 'BTH-001', '2027-12-31', '1'],
            ['Amoxicillin 250mg', 'AMOX-250', $categories[0] ?? 'General', 'Antibiotic capsules', 'capsules', '80.00', '150.00', '50', '200', $stores[0] ?? '', 'BTH-002', '2026-06-30', '1'],
        ];

        $rowNum = 2;
        foreach ($sampleData as $data) {
            $col = 'A';
            foreach ($data as $value) {
                $sheet->setCellValue($col . $rowNum, $value);
                $col++;
            }
            $rowNum++;
        }

        // Auto-size columns
        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Set active sheet back to first
        $spreadsheet->setActiveSheetIndex(0);

        return $this->downloadXlsx($spreadsheet, 'products_template.xlsx');
    }

    /**
     * Download XLSX template for services with dropdown validations
     */
    public function downloadServiceTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Services');

        // Headers
        $headers = [
            'A1' => 'service_name',
            'B1' => 'service_code',
            'C1' => 'category_name',
            'D1' => 'description',
            'E1' => 'price',
            'F1' => 'cost_price',
            'G1' => 'duration_minutes',
            'H1' => 'is_active',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Style headers
        $this->styleHeaders($sheet, 'A1:H1');

        // Get valid values for dropdowns
        $categories = ServiceCategory::orderBy('category_name')->pluck('category_name')->toArray();
        $activeOptions = ['1', '0'];

        // Create a hidden sheet for dropdown values
        $lookupSheet = $spreadsheet->createSheet();
        $lookupSheet->setTitle('_Lookups');

        // Populate lookup values
        $row = 1;
        foreach ($categories as $cat) {
            $lookupSheet->setCellValue('A' . $row, $cat);
            $row++;
        }
        $catLastRow = max($row - 1, 1);

        $lookupSheet->setCellValue('B1', '1');
        $lookupSheet->setCellValue('B2', '0');

        // Hide the lookup sheet
        $lookupSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

        // Add dropdown validations for 100 rows
        for ($i = 2; $i <= 101; $i++) {
            // Category dropdown
            if (count($categories) > 0) {
                $this->addDropdownValidation($sheet, "C{$i}", "'_Lookups'!\$A\$1:\$A\${$catLastRow}");
            }

            // Is Active dropdown
            $this->addDropdownValidation($sheet, "H{$i}", "'_Lookups'!\$B\$1:\$B\$2");
        }

        // Add sample data
        $sampleData = [
            ['Full Blood Count', 'LAB-FBC-001', $categories[0] ?? 'Laboratory', 'Complete blood count analysis', '5000.00', '1000.00', '30', '1'],
            ['Chest X-Ray', 'IMG-CXR-001', $categories[0] ?? 'Radiology', 'Standard chest x-ray', '8000.00', '2000.00', '15', '1'],
        ];

        $rowNum = 2;
        foreach ($sampleData as $data) {
            $col = 'A';
            foreach ($data as $value) {
                $sheet->setCellValue($col . $rowNum, $value);
                $col++;
            }
            $rowNum++;
        }

        // Auto-size columns
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Set active sheet back to first
        $spreadsheet->setActiveSheetIndex(0);

        return $this->downloadXlsx($spreadsheet, 'services_template.xlsx');
    }

    /**
     * Download XLSX template for staff with dropdown validations
     */
    public function downloadStaffTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Staff');

        // Headers
        $headers = [
            'A1' => 'surname',
            'B1' => 'firstname',
            'C1' => 'othername',
            'D1' => 'email',
            'E1' => 'phone_number',
            'F1' => 'gender',
            'G1' => 'date_of_birth',
            'H1' => 'home_address',
            'I1' => 'role',
            'J1' => 'specialization',
            'K1' => 'clinic',
            'L1' => 'consultation_fee',
            'M1' => 'is_unit_head',
            'N1' => 'is_dept_head',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Style headers
        $this->styleHeaders($sheet, 'A1:N1');

        // Get valid values for dropdowns
        $roles = Role::whereNotIn('name', ['PATIENT'])->orderBy('name')->pluck('name')->toArray();
        $specializations = Specialization::orderBy('name')->pluck('name')->toArray();
        $clinics = Clinic::orderBy('name')->pluck('name')->toArray();
        $genders = ['Male', 'Female'];
        $boolOptions = ['1', '0'];

        // Create a hidden sheet for dropdown values
        $lookupSheet = $spreadsheet->createSheet();
        $lookupSheet->setTitle('_Lookups');

        // Populate lookup values
        $row = 1;
        foreach ($roles as $role) {
            $lookupSheet->setCellValue('A' . $row, $role);
            $row++;
        }
        $roleLastRow = max($row - 1, 1);

        $row = 1;
        foreach ($specializations as $spec) {
            $lookupSheet->setCellValue('B' . $row, $spec);
            $row++;
        }
        $specLastRow = max($row - 1, 1);

        $row = 1;
        foreach ($clinics as $clinic) {
            $lookupSheet->setCellValue('C' . $row, $clinic);
            $row++;
        }
        $clinicLastRow = max($row - 1, 1);

        $lookupSheet->setCellValue('D1', 'Male');
        $lookupSheet->setCellValue('D2', 'Female');

        $lookupSheet->setCellValue('E1', '1');
        $lookupSheet->setCellValue('E2', '0');

        // Hide the lookup sheet
        $lookupSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

        // Add dropdown validations for 100 rows
        for ($i = 2; $i <= 101; $i++) {
            // Gender dropdown
            $this->addDropdownValidation($sheet, "F{$i}", "'_Lookups'!\$D\$1:\$D\$2");

            // Role dropdown
            if (count($roles) > 0) {
                $this->addDropdownValidation($sheet, "I{$i}", "'_Lookups'!\$A\$1:\$A\${$roleLastRow}");
            }

            // Specialization dropdown
            if (count($specializations) > 0) {
                $this->addDropdownValidation($sheet, "J{$i}", "'_Lookups'!\$B\$1:\$B\${$specLastRow}");
            }

            // Clinic dropdown
            if (count($clinics) > 0) {
                $this->addDropdownValidation($sheet, "K{$i}", "'_Lookups'!\$C\$1:\$C\${$clinicLastRow}");
            }

            // Boolean dropdowns
            $this->addDropdownValidation($sheet, "M{$i}", "'_Lookups'!\$E\$1:\$E\$2");
            $this->addDropdownValidation($sheet, "N{$i}", "'_Lookups'!\$E\$1:\$E\$2");
        }

        // Add sample data
        $sampleData = [
            ['Adekunle', 'Bola', 'Mary', 'bola.adekunle@hospital.com', '08012345678', 'Female', '1985-06-15', '123 Medical Lane, Lagos', $roles[0] ?? 'DOCTOR', $specializations[0] ?? '', $clinics[0] ?? '', '5000', '0', '0'],
            ['Okonkwo', 'Chidi', '', 'chidi.okonkwo@hospital.com', '08023456789', 'Male', '1990-03-22', '456 Health Street, Abuja', $roles[0] ?? 'NURSE', $specializations[0] ?? '', $clinics[0] ?? '', '0', '0', '0'],
        ];

        $rowNum = 2;
        foreach ($sampleData as $data) {
            $col = 'A';
            foreach ($data as $value) {
                $sheet->setCellValue($col . $rowNum, $value);
                $col++;
            }
            $rowNum++;
        }

        // Auto-size columns
        foreach (range('A', 'N') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Set active sheet back to first
        $spreadsheet->setActiveSheetIndex(0);

        return $this->downloadXlsx($spreadsheet, 'staff_template.xlsx');
    }

    /**
     * Download XLSX template for patients with dropdown validations
     */
    public function downloadPatientTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Patients');

        // Headers
        $headers = [
            'A1' => 'file_no',
            'B1' => 'surname',
            'C1' => 'firstname',
            'D1' => 'othername',
            'E1' => 'email',
            'F1' => 'phone_no',
            'G1' => 'gender',
            'H1' => 'dob',
            'I1' => 'blood_group',
            'J1' => 'genotype',
            'K1' => 'address',
            'L1' => 'nationality',
            'M1' => 'ethnicity',
            'N1' => 'hmo_name',
            'O1' => 'hmo_no',
            'P1' => 'next_of_kin_name',
            'Q1' => 'next_of_kin_phone',
            'R1' => 'next_of_kin_address',
            'S1' => 'allergies',
            'T1' => 'medical_history',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Style headers
        $this->styleHeaders($sheet, 'A1:T1');

        // Get valid values for dropdowns
        $hmos = Hmo::orderBy('name')->pluck('name')->toArray();
        $genders = ['Male', 'Female'];
        $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        $genotypes = ['AA', 'AS', 'SS', 'AC', 'SC'];

        // Create a hidden sheet for dropdown values
        $lookupSheet = $spreadsheet->createSheet();
        $lookupSheet->setTitle('_Lookups');

        // Populate HMO lookup values
        $row = 1;
        foreach ($hmos as $hmo) {
            $lookupSheet->setCellValue('A' . $row, $hmo);
            $row++;
        }
        $hmoLastRow = max($row - 1, 1);

        // Gender
        $lookupSheet->setCellValue('B1', 'Male');
        $lookupSheet->setCellValue('B2', 'Female');

        // Blood Groups
        $row = 1;
        foreach ($bloodGroups as $bg) {
            $lookupSheet->setCellValue('C' . $row, $bg);
            $row++;
        }
        $bgLastRow = $row - 1;

        // Genotypes
        $row = 1;
        foreach ($genotypes as $gt) {
            $lookupSheet->setCellValue('D' . $row, $gt);
            $row++;
        }
        $gtLastRow = $row - 1;

        // Hide the lookup sheet
        $lookupSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

        // Add dropdown validations for 100 rows
        for ($i = 2; $i <= 101; $i++) {
            // Gender dropdown
            $this->addDropdownValidation($sheet, "G{$i}", "'_Lookups'!\$B\$1:\$B\$2");

            // Blood group dropdown
            $this->addDropdownValidation($sheet, "I{$i}", "'_Lookups'!\$C\$1:\$C\${$bgLastRow}");

            // Genotype dropdown
            $this->addDropdownValidation($sheet, "J{$i}", "'_Lookups'!\$D\$1:\$D\${$gtLastRow}");

            // HMO dropdown
            if (count($hmos) > 0) {
                $this->addDropdownValidation($sheet, "N{$i}", "'_Lookups'!\$A\$1:\$A\${$hmoLastRow}");
            }
        }

        // Add sample data
        $sampleData = [
            ['PAT-001', 'Bakare', 'Adebayo', 'Michael', 'adebayo.bakare@email.com', '08011111111', 'Male', '1988-03-10', 'O+', 'AA', '789 Patient Avenue, Lagos', 'Nigerian', 'Yoruba', $hmos[0] ?? '', '12345', 'Mrs. Bakare Funke', '08022222222', '789 Patient Avenue, Lagos', 'Penicillin,Sulfa drugs', 'Hypertension diagnosed 2020'],
            ['', 'Ibrahim', 'Fatima', '', '', '08033333333', 'Female', '1995-11-25', 'B+', 'AS', '321 Health Street, Abuja', 'Nigerian', 'Hausa', '', '', 'Mr. Ibrahim Ahmed', '08044444444', '321 Health Street, Abuja', '', ''],
        ];

        $rowNum = 2;
        foreach ($sampleData as $data) {
            $col = 'A';
            foreach ($data as $value) {
                $sheet->setCellValue($col . $rowNum, $value);
                $col++;
            }
            $rowNum++;
        }

        // Auto-size columns
        foreach (range('A', 'T') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Set active sheet back to first
        $spreadsheet->setActiveSheetIndex(0);

        return $this->downloadXlsx($spreadsheet, 'patients_template.xlsx');
    }

    // ========================================
    // IMPORTS (Support both CSV and XLSX)
    // ========================================

    /**
     * Import products from CSV or XLSX
     */
    public function importProducts(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
            'default_store_id' => 'nullable|exists:stores,id',
        ]);

        $file = $request->file('file');
        $defaultStoreId = $request->default_store_id;

        try {
            $data = $this->parseFile($file);

            if (empty($data)) {
                return back()->with('error', 'File is empty or invalid.');
            }

            $imported = 0;
            $errors = [];
            $skipped = 0;

            DB::beginTransaction();

            foreach ($data as $index => $row) {
                $rowNum = $index + 2; // Account for header row

                try {
                    // Validate required fields
                    if (empty($row['product_name']) || empty($row['product_code'])) {
                        $errors[] = "Row {$rowNum}: Missing product_name or product_code";
                        $skipped++;
                        continue;
                    }

                    // Check for duplicate product_code
                    if (Product::where('product_code', $row['product_code'])->exists()) {
                        $errors[] = "Row {$rowNum}: Product code '{$row['product_code']}' already exists";
                        $skipped++;
                        continue;
                    }

                    // Get or create category
                    $categoryId = null;
                    if (!empty($row['category_name'])) {
                        $category = ProductCategory::firstOrCreate(
                            ['category_name' => trim($row['category_name'])],
                            ['description' => 'Auto-created during import']
                        );
                        $categoryId = $category->id;
                    }

                    // Create product
                    $product = Product::create([
                        'user_id' => auth()->id(),
                        'category_id' => $categoryId,
                        'product_name' => trim($row['product_name']),
                        'product_code' => trim($row['product_code']),
                        'reorder_alert' => $row['reorder_level'] ?? 10,
                        'visible' => ($row['is_active'] ?? 1) == 1,
                        'current_quantity' => $row['initial_quantity'] ?? 0,
                    ]);

                    // Create Price record
                    // prices table uses: pr_buy_price (cost), current_sale_price (selling price)
                    $costPrice = floatval($row['cost_price'] ?? 0);
                    $salePrice = floatval($row['sale_price'] ?? 0);

                    Price::create([
                        'product_id' => $product->id,
                        'pr_buy_price' => $costPrice,
                        'initial_sale_price' => $salePrice,
                        'current_sale_price' => $salePrice,
                        'max_discount' => 0,
                        'status' => 1,
                    ]);

                    // Create Stock record
                    Stock::create([
                        'product_id' => $product->id,
                        'initial_quantity' => $row['initial_quantity'] ?? 0,
                        'current_quantity' => $row['initial_quantity'] ?? 0,
                        'quantity_sale' => 0,
                        'order_quantity' => 0,
                    ]);

                    // Create StoreStock if store specified
                    $storeId = null;
                    if (!empty($row['store_name'])) {
                        $store = Store::where('store_name', trim($row['store_name']))->first();
                        if ($store) {
                            $storeId = $store->id;
                        }
                    }
                    $storeId = $storeId ?? $defaultStoreId;

                    if ($storeId) {
                        StoreStock::create([
                            'store_id' => $storeId,
                            'product_id' => $product->id,
                            'initial_quantity' => $row['initial_quantity'] ?? 0,
                            'current_quantity' => $row['initial_quantity'] ?? 0,
                            'quantity_sale' => 0,
                            'order_quantity' => 0,
                            'reorder_level' => $row['reorder_level'] ?? 10,
                            'is_active' => true,
                        ]);
                    }

                    $imported++;

                } catch (\Exception $e) {
                    $errors[] = "Row {$rowNum}: " . $e->getMessage();
                    $skipped++;
                }
            }

            DB::commit();

            $message = "Successfully imported {$imported} products.";
            if ($skipped > 0) {
                $message .= " {$skipped} rows skipped.";
            }

            return back()->with('success', $message)->with('import_errors', $errors);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product import failed: ' . $e->getMessage());
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    /**
     * Import services from CSV or XLSX
     */
    public function importServices(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $file = $request->file('file');

        try {
            $data = $this->parseFile($file);

            if (empty($data)) {
                return back()->with('error', 'File is empty or invalid.');
            }

            $imported = 0;
            $errors = [];
            $skipped = 0;

            DB::beginTransaction();

            foreach ($data as $index => $row) {
                $rowNum = $index + 2;

                try {
                    if (empty($row['service_name']) || empty($row['service_code'])) {
                        $errors[] = "Row {$rowNum}: Missing service_name or service_code";
                        $skipped++;
                        continue;
                    }

                    // Check for duplicate
                    if (Service::where('service_code', $row['service_code'])->exists()) {
                        $errors[] = "Row {$rowNum}: Service code '{$row['service_code']}' already exists";
                        $skipped++;
                        continue;
                    }

                    // Get or create category
                    $categoryId = null;
                    if (!empty($row['category_name'])) {
                        $category = ServiceCategory::firstOrCreate(
                            ['category_name' => trim($row['category_name'])],
                            ['description' => 'Auto-created during import']
                        );
                        $categoryId = $category->id;
                    }

                    // Create service
                    $service = Service::create([
                        'user_id' => auth()->id(),
                        'category_id' => $categoryId,
                        'service_name' => trim($row['service_name']),
                        'service_code' => trim($row['service_code']),
                        'status' => ($row['is_active'] ?? 1) == 1 ? 1 : 0,
                    ]);

                    // Create ServicePrice record
                    ServicePrice::create([
                        'service_id' => $service->id,
                        'cost_price' => floatval($row['cost_price'] ?? 0),
                        'sale_price' => floatval($row['price'] ?? 0),
                    ]);

                    $imported++;

                } catch (\Exception $e) {
                    $errors[] = "Row {$rowNum}: " . $e->getMessage();
                    $skipped++;
                }
            }

            DB::commit();

            $message = "Successfully imported {$imported} services.";
            if ($skipped > 0) {
                $message .= " {$skipped} rows skipped.";
            }

            return back()->with('success', $message)->with('import_errors', $errors);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Service import failed: ' . $e->getMessage());
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    /**
     * Import staff from CSV or XLSX
     */
    public function importStaff(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
            'default_password' => 'nullable|string|min:6',
        ]);

        $file = $request->file('file');
        $defaultPassword = $request->default_password ?? 'password123';

        try {
            $data = $this->parseFile($file);

            if (empty($data)) {
                return back()->with('error', 'File is empty or invalid.');
            }

            $imported = 0;
            $errors = [];
            $skipped = 0;

            DB::beginTransaction();

            foreach ($data as $index => $row) {
                $rowNum = $index + 2;

                try {
                    if (empty($row['surname']) || empty($row['firstname']) || empty($row['email'])) {
                        $errors[] = "Row {$rowNum}: Missing required fields (surname, firstname, email)";
                        $skipped++;
                        continue;
                    }

                    // Check for duplicate email
                    if (User::where('email', $row['email'])->exists()) {
                        $errors[] = "Row {$rowNum}: Email '{$row['email']}' already exists";
                        $skipped++;
                        continue;
                    }

                    // Validate role
                    $roleName = trim($row['role'] ?? 'STAFF');
                    $role = Role::where('name', $roleName)->first();
                    if (!$role) {
                        $errors[] = "Row {$rowNum}: Role '{$roleName}' not found";
                        $skipped++;
                        continue;
                    }

                    // Create user
                    $user = User::create([
                        'surname' => trim($row['surname']),
                        'firstname' => trim($row['firstname']),
                        'othername' => trim($row['othername'] ?? ''),
                        'email' => trim($row['email']),
                        'password' => Hash::make($defaultPassword),
                        'is_admin' => 2, // Staff user category
                        'status' => 1,
                    ]);

                    // Assign role
                    $user->assignRole($role);

                    // Get specialization if provided
                    $specializationId = null;
                    if (!empty($row['specialization'])) {
                        $specialization = Specialization::where('name', trim($row['specialization']))->first();
                        if ($specialization) {
                            $specializationId = $specialization->id;
                        }
                    }

                    // Get clinic if provided
                    $clinicId = null;
                    if (!empty($row['clinic'])) {
                        $clinic = Clinic::where('name', trim($row['clinic']))->first();
                        if ($clinic) {
                            $clinicId = $clinic->id;
                        }
                    }

                    // Create staff profile
                    Staff::create([
                        'user_id' => $user->id,
                        'specialization_id' => $specializationId,
                        'clinic_id' => $clinicId,
                        'gender' => $row['gender'] ?? null,
                        'date_of_birth' => !empty($row['date_of_birth']) ? $row['date_of_birth'] : null,
                        'home_address' => $row['home_address'] ?? null,
                        'phone_number' => $row['phone_number'] ?? null,
                        'consultation_fee' => floatval($row['consultation_fee'] ?? 0),
                        'is_unit_head' => ($row['is_unit_head'] ?? 0) == 1,
                        'is_dept_head' => ($row['is_dept_head'] ?? 0) == 1,
                        'status' => 1,
                    ]);

                    $imported++;

                } catch (\Exception $e) {
                    $errors[] = "Row {$rowNum}: " . $e->getMessage();
                    $skipped++;
                }
            }

            DB::commit();

            $message = "Successfully imported {$imported} staff members.";
            if ($skipped > 0) {
                $message .= " {$skipped} rows skipped.";
            }

            return back()->with('success', $message)->with('import_errors', $errors);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Staff import failed: ' . $e->getMessage());
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    /**
     * Import patients from CSV or XLSX
     */
    public function importPatients(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $file = $request->file('file');

        try {
            $data = $this->parseFile($file);

            if (empty($data)) {
                return back()->with('error', 'File is empty or invalid.');
            }

            $imported = 0;
            $errors = [];
            $skipped = 0;

            DB::beginTransaction();

            foreach ($data as $index => $row) {
                $rowNum = $index + 2;

                try {
                    if (empty($row['surname']) || empty($row['firstname']) || empty($row['gender']) || empty($row['dob'])) {
                        $errors[] = "Row {$rowNum}: Missing required fields (surname, firstname, gender, dob)";
                        $skipped++;
                        continue;
                    }

                    // Check for duplicate email if provided
                    $email = trim($row['email'] ?? '');
                    if (!empty($email) && User::where('email', $email)->exists()) {
                        $errors[] = "Row {$rowNum}: Email '{$email}' already exists";
                        $skipped++;
                        continue;
                    }

                    // Generate unique email if not provided
                    if (empty($email)) {
                        $email = strtolower(Str::slug($row['surname'] . '.' . $row['firstname'])) . '.' . Str::random(4) . '@patient.local';
                    }

                    // Create user
                    $user = User::create([
                        'surname' => trim($row['surname']),
                        'firstname' => trim($row['firstname']),
                        'othername' => trim($row['othername'] ?? ''),
                        'email' => $email,
                        'password' => Hash::make(Str::random(12)),
                        'is_admin' => 3, // Patient user category
                        'status' => 1,
                    ]);

                    // Assign patient role
                    $patientRole = Role::where('name', 'PATIENT')->first();
                    if ($patientRole) {
                        $user->assignRole($patientRole);
                    }

                    // Get HMO if provided - exact match from dropdown
                    $hmoId = null;
                    if (!empty($row['hmo_name'])) {
                        $hmo = Hmo::where('name', trim($row['hmo_name']))->first();
                        if ($hmo) {
                            $hmoId = $hmo->id;
                        }
                    }

                    // Use provided file number or generate one
                    $fileNo = trim($row['file_no'] ?? '');
                    if (!empty($fileNo)) {
                        // Check for duplicate file number
                        if (patient::where('file_no', $fileNo)->exists()) {
                            $errors[] = "Row {$rowNum}: File number '{$fileNo}' already exists";
                            $skipped++;
                            continue;
                        }
                    } else {
                        $fileNo = $this->generatePatientFileNo();
                    }

                    // Parse allergies (comma-separated to array)
                    $allergies = null;
                    if (!empty($row['allergies'])) {
                        $allergies = array_map('trim', explode(',', $row['allergies']));
                    }

                    // Parse HMO number - must be numeric or null
                    $hmoNo = null;
                    if (!empty($row['hmo_no'])) {
                        // Extract numeric value if present, or use the value if already numeric
                        $hmoNoValue = trim($row['hmo_no']);
                        if (is_numeric($hmoNoValue)) {
                            $hmoNo = (int) $hmoNoValue;
                        } else {
                            // Try to extract numbers from string like 'HMO-12345'
                            preg_match('/\d+/', $hmoNoValue, $matches);
                            if (!empty($matches[0])) {
                                $hmoNo = (int) $matches[0];
                            }
                        }
                    }

                    // Create patient profile
                    patient::create([
                        'user_id' => $user->id,
                        'file_no' => $fileNo,
                        'hmo_id' => $hmoId,
                        'hmo_no' => $hmoNo,
                        'gender' => $row['gender'],
                        'dob' => $row['dob'],
                        'blood_group' => $row['blood_group'] ?? null,
                        'genotype' => $row['genotype'] ?? null,
                        'address' => $row['address'] ?? null,
                        'phone_no' => $row['phone_no'] ?? null,
                        'nationality' => $row['nationality'] ?? null,
                        'ethnicity' => $row['ethnicity'] ?? null,
                        'allergies' => $allergies,
                        'medical_history' => $row['medical_history'] ?? null,
                        'next_of_kin_name' => $row['next_of_kin_name'] ?? null,
                        'next_of_kin_phone' => $row['next_of_kin_phone'] ?? null,
                        'next_of_kin_address' => $row['next_of_kin_address'] ?? null,
                    ]);

                    $imported++;

                } catch (\Exception $e) {
                    $errors[] = "Row {$rowNum}: " . $e->getMessage();
                    $skipped++;
                }
            }

            DB::commit();

            $message = "Successfully imported {$imported} patients.";
            if ($skipped > 0) {
                $message .= " {$skipped} rows skipped.";
            }

            return back()->with('success', $message)->with('import_errors', $errors);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Patient import failed: ' . $e->getMessage());
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    // ========================================
    // EXPORTS
    // ========================================

    /**
     * Export products to CSV
     */
    public function exportProducts(Request $request)
    {
        $categoryId = $request->category_id;
        $storeId = $request->store_id;

        $query = Product::with(['category', 'price', 'stock', 'storeStock']);

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->get();

        $headers = [
            'id', 'product_name', 'product_code', 'category_name', 'cost_price',
            'sale_price', 'reorder_level', 'current_quantity', 'is_active', 'created_at'
        ];

        $data = [];
        foreach ($products as $product) {
            $data[] = [
                $product->id,
                $product->product_name,
                $product->product_code,
                $product->category->category_name ?? '',
                $product->price->pr_buy_price ?? 0,
                $product->price->current_sale_price ?? 0,
                $product->reorder_alert ?? 10,
                $product->current_quantity ?? 0,
                $product->visible ? 1 : 0,
                $product->created_at->format('Y-m-d H:i:s'),
            ];
        }

        return $this->generateCsvResponse($headers, $data, 'products_export_' . date('Y-m-d') . '.csv');
    }

    /**
     * Export services to CSV
     */
    public function exportServices(Request $request)
    {
        $categoryId = $request->category_id;

        $query = Service::with(['category', 'price']);

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $services = $query->get();

        $headers = [
            'id', 'service_name', 'service_code', 'category_name', 'cost_price',
            'price', 'is_active', 'created_at'
        ];

        $data = [];
        foreach ($services as $service) {
            $data[] = [
                $service->id,
                $service->service_name,
                $service->service_code,
                $service->category->category_name ?? '',
                $service->price->cost_price ?? 0,
                $service->price->sale_price ?? 0,
                $service->status == 1 ? 1 : 0,
                $service->created_at->format('Y-m-d H:i:s'),
            ];
        }

        return $this->generateCsvResponse($headers, $data, 'services_export_' . date('Y-m-d') . '.csv');
    }

    /**
     * Export staff to CSV
     */
    public function exportStaff(Request $request)
    {
        $roleFilter = $request->role;

        $query = Staff::with(['user', 'user.roles', 'specialization', 'clinic']);

        $staffMembers = $query->get();

        // Filter by role if specified
        if ($roleFilter) {
            $staffMembers = $staffMembers->filter(function ($staff) use ($roleFilter) {
                return $staff->user && $staff->user->hasRole($roleFilter);
            });
        }

        $headers = [
            'id', 'surname', 'firstname', 'othername', 'email', 'phone_number',
            'gender', 'date_of_birth', 'home_address', 'roles', 'specialization',
            'clinic', 'consultation_fee', 'is_unit_head', 'is_dept_head', 'created_at'
        ];

        $data = [];
        foreach ($staffMembers as $staff) {
            if (!$staff->user) continue;

            $data[] = [
                $staff->id,
                $staff->user->surname,
                $staff->user->firstname,
                $staff->user->othername ?? '',
                $staff->user->email,
                $staff->phone_number ?? '',
                $staff->gender ?? '',
                $staff->date_of_birth ? $staff->date_of_birth->format('Y-m-d') : '',
                $staff->home_address ?? '',
                $staff->user->roles->pluck('name')->implode(', '),
                $staff->specialization->name ?? '',
                $staff->clinic->name ?? '',
                $staff->consultation_fee ?? 0,
                $staff->is_unit_head ? 1 : 0,
                $staff->is_dept_head ? 1 : 0,
                $staff->created_at->format('Y-m-d H:i:s'),
            ];
        }

        return $this->generateCsvResponse($headers, $data, 'staff_export_' . date('Y-m-d') . '.csv');
    }

    /**
     * Export patients to CSV
     */
    public function exportPatients(Request $request)
    {
        $hmoId = $request->hmo_id;

        $query = patient::with(['user', 'hmo']);

        if ($hmoId) {
            $query->where('hmo_id', $hmoId);
        }

        $patients = $query->get();

        $headers = [
            'id', 'file_no', 'surname', 'firstname', 'othername', 'email', 'phone_no',
            'gender', 'dob', 'blood_group', 'genotype', 'address', 'nationality',
            'ethnicity', 'hmo_name', 'hmo_no', 'next_of_kin_name', 'next_of_kin_phone',
            'allergies', 'created_at'
        ];

        $data = [];
        foreach ($patients as $patient) {
            if (!$patient->user) continue;

            $data[] = [
                $patient->id,
                $patient->file_no,
                $patient->user->surname,
                $patient->user->firstname,
                $patient->user->othername ?? '',
                $patient->user->email,
                $patient->phone_no ?? '',
                $patient->gender ?? '',
                $patient->dob ?? '',
                $patient->blood_group ?? '',
                $patient->genotype ?? '',
                $patient->address ?? '',
                $patient->nationality ?? '',
                $patient->ethnicity ?? '',
                $patient->hmo->name ?? '',
                $patient->hmo_no ?? '',
                $patient->next_of_kin_name ?? '',
                $patient->next_of_kin_phone ?? '',
                is_array($patient->allergies) ? implode(', ', $patient->allergies) : ($patient->allergies ?? ''),
                $patient->created_at->format('Y-m-d H:i:s'),
            ];
        }

        return $this->generateCsvResponse($headers, $data, 'patients_export_' . date('Y-m-d') . '.csv');
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Parse file (CSV or XLSX) into array
     */
    private function parseFile($file)
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['xlsx', 'xls'])) {
            return $this->parseXlsx($file);
        }

        return $this->parseCsv($file);
    }

    /**
     * Parse XLSX file into array
     */
    private function parseXlsx($file)
    {
        $data = [];

        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            if (empty($rows)) {
                return $data;
            }

            // First row is headers
            $headers = [];
            $firstRow = array_shift($rows);
            foreach ($firstRow as $col => $value) {
                if (!empty($value)) {
                    $headers[$col] = strtolower(trim(str_replace(['"', "'"], '', $value)));
                }
            }

            // Data rows
            foreach ($rows as $row) {
                $rowData = [];
                foreach ($headers as $col => $header) {
                    $value = $row[$col] ?? '';
                    $rowData[$header] = is_string($value) ? trim($value) : $value;
                }
                if (!empty(array_filter($rowData))) {
                    $data[] = $rowData;
                }
            }
        } catch (\Exception $e) {
            Log::error('XLSX parse error: ' . $e->getMessage());
        }

        return $data;
    }

    /**
     * Parse CSV file into array
     */
    private function parseCsv($file)
    {
        $data = [];
        $headers = [];

        if (($handle = fopen($file->getPathname(), 'r')) !== false) {
            $rowIndex = 0;
            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                if ($rowIndex === 0) {
                    // First row is headers
                    $headers = array_map(function ($header) {
                        return strtolower(trim(str_replace(['"', "'"], '', $header)));
                    }, $row);
                } else {
                    // Data rows
                    $rowData = [];
                    foreach ($row as $index => $value) {
                        if (isset($headers[$index])) {
                            $rowData[$headers[$index]] = trim($value);
                        }
                    }
                    if (!empty(array_filter($rowData))) {
                        $data[] = $rowData;
                    }
                }
                $rowIndex++;
            }
            fclose($handle);
        }

        return $data;
    }

    /**
     * Generate XLSX download response
     */
    private function downloadXlsx(Spreadsheet $spreadsheet, string $filename)
    {
        $writer = new Xlsx($spreadsheet);

        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Style header row
     */
    private function styleHeaders($sheet, string $range)
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);
    }

    /**
     * Add dropdown validation to a cell
     */
    private function addDropdownValidation($sheet, string $cell, string $formula)
    {
        $validation = $sheet->getCell($cell)->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Invalid Value');
        $validation->setError('Please select a value from the dropdown list.');
        $validation->setFormula1($formula);
    }

    /**
     * Generate CSV download response
     */
    private function generateCsvResponse($headers, $data, $filename)
    {
        $callback = function () use ($headers, $data) {
            $file = fopen('php://output', 'w');

            // Add BOM for Excel UTF-8 compatibility
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Write headers
            fputcsv($file, $headers);

            // Write data
            foreach ($data as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Generate unique patient file number
     */
    private function generatePatientFileNo()
    {
        $prefix = date('Y');
        $lastPatient = patient::where('file_no', 'like', $prefix . '%')
            ->orderBy('file_no', 'desc')
            ->first();

        if ($lastPatient && preg_match('/(\d+)$/', $lastPatient->file_no, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
