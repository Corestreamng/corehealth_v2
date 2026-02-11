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
use App\Models\StockBatch;
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

        // Headers - comprehensive staff fields
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
            'L1' => 'department',
            'M1' => 'employee_id',
            'N1' => 'job_title',
            'O1' => 'date_hired',
            'P1' => 'employment_type',
            'Q1' => 'employment_status',
            'R1' => 'consultation_fee',
            'S1' => 'is_unit_head',
            'T1' => 'is_dept_head',
            'U1' => 'bank_name',
            'V1' => 'bank_account_number',
            'W1' => 'bank_account_name',
            'X1' => 'emergency_contact_name',
            'Y1' => 'emergency_contact_phone',
            'Z1' => 'emergency_contact_relationship',
            'AA1' => 'tax_id',
            'AB1' => 'pension_id',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Style headers
        $this->styleHeaders($sheet, 'A1:AB1');

        // Get valid values for dropdowns
        $roles = Role::whereNotIn('name', ['PATIENT'])->orderBy('name')->pluck('name')->toArray();
        $specializations = Specialization::orderBy('name')->pluck('name')->toArray();
        $clinics = Clinic::orderBy('name')->pluck('name')->toArray();
        $departments = \App\Models\Department::orderBy('name')->pluck('name')->toArray();
        $genders = ['Male', 'Female', 'Others'];
        $boolOptions = ['1', '0'];
        $employmentTypes = ['full_time', 'part_time', 'contract', 'intern'];
        $employmentStatuses = ['active', 'suspended', 'terminated', 'resigned'];
        $emergencyRelationships = ['spouse', 'parent', 'sibling', 'child', 'friend', 'other'];
        $nigerianBanks = [
            'Access Bank', 'Citibank', 'Ecobank Nigeria', 'Fidelity Bank',
            'First Bank of Nigeria', 'First City Monument Bank', 'Guaranty Trust Bank',
            'Heritage Bank', 'Keystone Bank', 'Polaris Bank', 'Providus Bank',
            'Stanbic IBTC Bank', 'Standard Chartered Bank', 'Sterling Bank',
            'Suntrust Bank', 'Union Bank of Nigeria', 'United Bank for Africa',
            'Unity Bank', 'Wema Bank', 'Zenith Bank'
        ];

        // Create a hidden sheet for dropdown values
        $lookupSheet = $spreadsheet->createSheet();
        $lookupSheet->setTitle('_Lookups');

        // Column A: Roles
        $row = 1;
        foreach ($roles as $role) {
            $lookupSheet->setCellValue('A' . $row, $role);
            $row++;
        }
        $roleLastRow = max($row - 1, 1);

        // Column B: Specializations
        $row = 1;
        foreach ($specializations as $spec) {
            $lookupSheet->setCellValue('B' . $row, $spec);
            $row++;
        }
        $specLastRow = max($row - 1, 1);

        // Column C: Clinics
        $row = 1;
        foreach ($clinics as $clinic) {
            $lookupSheet->setCellValue('C' . $row, $clinic);
            $row++;
        }
        $clinicLastRow = max($row - 1, 1);

        // Column D: Genders
        $row = 1;
        foreach ($genders as $gender) {
            $lookupSheet->setCellValue('D' . $row, $gender);
            $row++;
        }
        $genderLastRow = $row - 1;

        // Column E: Bool options (0/1)
        $lookupSheet->setCellValue('E1', '1');
        $lookupSheet->setCellValue('E2', '0');

        // Column F: Departments
        $row = 1;
        foreach ($departments as $dept) {
            $lookupSheet->setCellValue('F' . $row, $dept);
            $row++;
        }
        $deptLastRow = max($row - 1, 1);

        // Column G: Employment Types
        $row = 1;
        foreach ($employmentTypes as $type) {
            $lookupSheet->setCellValue('G' . $row, $type);
            $row++;
        }
        $empTypeLastRow = $row - 1;

        // Column H: Employment Statuses
        $row = 1;
        foreach ($employmentStatuses as $status) {
            $lookupSheet->setCellValue('H' . $row, $status);
            $row++;
        }
        $empStatusLastRow = $row - 1;

        // Column I: Nigerian Banks
        $row = 1;
        foreach ($nigerianBanks as $bank) {
            $lookupSheet->setCellValue('I' . $row, $bank);
            $row++;
        }
        $bankLastRow = $row - 1;

        // Column J: Emergency Contact Relationships
        $row = 1;
        foreach ($emergencyRelationships as $rel) {
            $lookupSheet->setCellValue('J' . $row, $rel);
            $row++;
        }
        $relLastRow = $row - 1;

        // Hide the lookup sheet
        $lookupSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

        // Add dropdown validations for 100 rows
        for ($i = 2; $i <= 101; $i++) {
            // F: Gender dropdown
            $this->addDropdownValidation($sheet, "F{$i}", "'_Lookups'!\$D\$1:\$D\${$genderLastRow}");

            // I: Role dropdown
            if (count($roles) > 0) {
                $this->addDropdownValidation($sheet, "I{$i}", "'_Lookups'!\$A\$1:\$A\${$roleLastRow}");
            }

            // J: Specialization dropdown
            if (count($specializations) > 0) {
                $this->addDropdownValidation($sheet, "J{$i}", "'_Lookups'!\$B\$1:\$B\${$specLastRow}");
            }

            // K: Clinic dropdown
            if (count($clinics) > 0) {
                $this->addDropdownValidation($sheet, "K{$i}", "'_Lookups'!\$C\$1:\$C\${$clinicLastRow}");
            }

            // L: Department dropdown
            if (count($departments) > 0) {
                $this->addDropdownValidation($sheet, "L{$i}", "'_Lookups'!\$F\$1:\$F\${$deptLastRow}");
            }

            // P: Employment Type dropdown
            $this->addDropdownValidation($sheet, "P{$i}", "'_Lookups'!\$G\$1:\$G\${$empTypeLastRow}");

            // Q: Employment Status dropdown
            $this->addDropdownValidation($sheet, "Q{$i}", "'_Lookups'!\$H\$1:\$H\${$empStatusLastRow}");

            // S: is_unit_head Boolean dropdown
            $this->addDropdownValidation($sheet, "S{$i}", "'_Lookups'!\$E\$1:\$E\$2");

            // T: is_dept_head Boolean dropdown
            $this->addDropdownValidation($sheet, "T{$i}", "'_Lookups'!\$E\$1:\$E\$2");

            // U: Bank Name dropdown
            $this->addDropdownValidation($sheet, "U{$i}", "'_Lookups'!\$I\$1:\$I\${$bankLastRow}");

            // Z: Emergency Contact Relationship dropdown
            $this->addDropdownValidation($sheet, "Z{$i}", "'_Lookups'!\$J\$1:\$J\${$relLastRow}");
        }

        // Add sample data
        $sampleData = [
            [
                'Adekunle', 'Bola', 'Mary', 'bola.adekunle@hospital.com', '08012345678',
                'Female', '1985-06-15', '123 Medical Lane, Lagos',
                $roles[0] ?? 'DOCTOR', $specializations[0] ?? '', $clinics[0] ?? '', $departments[0] ?? '',
                'EMP-001', 'Senior Doctor', '2020-01-15', 'full_time', 'active',
                '5000', '0', '0',
                'Zenith Bank', '1234567890', 'Bola Mary Adekunle',
                'John Adekunle', '08098765432', 'spouse',
                'TIN-12345678', 'PEN-87654321'
            ],
            [
                'Okonkwo', 'Chidi', '', 'chidi.okonkwo@hospital.com', '08023456789',
                'Male', '1990-03-22', '456 Health Street, Abuja',
                $roles[0] ?? 'NURSE', $specializations[0] ?? '', $clinics[0] ?? '', $departments[0] ?? '',
                'EMP-002', 'Staff Nurse', '2021-06-01', 'full_time', 'active',
                '0', '0', '0',
                'First Bank of Nigeria', '0987654321', 'Chidi Okonkwo',
                'Ada Okonkwo', '08011223344', 'sibling',
                '', ''
            ],
        ];

        $rowNum = 2;
        foreach ($sampleData as $data) {
            $col = 'A';
            foreach ($data as $value) {
                $sheet->setCellValue($col . $rowNum, $value);
                // Move to next column (handles AA, AB, etc.)
                $col++;
            }
            $rowNum++;
        }

        // Auto-size columns A to AB
        $columns = array_merge(range('A', 'Z'), ['AA', 'AB']);
        foreach ($columns as $col) {
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
    // Optimized with batch processing, upsert, and detailed reporting
    // ========================================

    const BATCH_SIZE = 500;

    /**
     * Import products from CSV or XLSX
     * - Batch commits every 500 records
     * - Detects duplicates by product_code and updates instead of skipping
     * - Returns detailed import report
     */
    public function importProducts(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
            'default_store_id' => 'nullable|exists:stores,id',
        ]);

        $file = $request->file('file');
        $defaultStoreId = $request->default_store_id;
        $startTime = microtime(true);

        try {
            $data = $this->parseFile($file);

            if (empty($data)) {
                return back()->with('error', 'File is empty or invalid.');
            }

            // Pre-fetch lookup data for performance
            $existingProducts = Product::pluck('id', 'product_code')->toArray();
            $categories = ProductCategory::pluck('id', 'category_name')->toArray();
            $stores = Store::pluck('id', 'store_name')->toArray();

            $report = [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [],
                'created_items' => [],
                'updated_items' => [],
            ];

            $batches = array_chunk($data, self::BATCH_SIZE);
            $totalBatches = count($batches);

            foreach ($batches as $batchIndex => $batch) {
                DB::beginTransaction();
                try {
                    foreach ($batch as $index => $row) {
                        $rowNum = ($batchIndex * self::BATCH_SIZE) + $index + 2;

                        // Validate required fields
                        if (empty($row['product_name']) || empty($row['product_code'])) {
                            $report['errors'][] = "Row {$rowNum}: Missing product_name or product_code";
                            $report['skipped']++;
                            continue;
                        }

                        $productCode = trim($row['product_code']);
                        $productName = trim($row['product_name']);

                        // Get or create category (cached)
                        $categoryId = null;
                        if (!empty($row['category_name'])) {
                            $categoryName = trim($row['category_name']);
                            if (!isset($categories[$categoryName])) {
                                $category = ProductCategory::create([
                                    'category_name' => $categoryName,
                                    'description' => 'Auto-created during import'
                                ]);
                                $categories[$categoryName] = $category->id;
                            }
                            $categoryId = $categories[$categoryName];
                        }

                        // Determine store
                        $storeId = null;
                        if (!empty($row['store_name']) && isset($stores[trim($row['store_name'])])) {
                            $storeId = $stores[trim($row['store_name'])];
                        }
                        $storeId = $storeId ?? $defaultStoreId;

                        $costPrice = floatval($row['cost_price'] ?? 0);
                        $salePrice = floatval($row['sale_price'] ?? 0);
                        $initialQty = intval($row['initial_quantity'] ?? 0);
                        $reorderLevel = intval($row['reorder_level'] ?? 10);
                        $isActive = ($row['is_active'] ?? 1) == 1;

                        // Check if product exists (update) or create new
                        if (isset($existingProducts[$productCode])) {
                            // UPDATE existing product
                            $productId = $existingProducts[$productCode];

                            Product::where('id', $productId)->update([
                                'product_name' => $productName,
                                'category_id' => $categoryId,
                                'reorder_alert' => $reorderLevel,
                                'visible' => $isActive,
                            ]);

                            // Update Price
                            Price::updateOrCreate(
                                ['product_id' => $productId],
                                [
                                    'pr_buy_price' => $costPrice,
                                    'current_sale_price' => $salePrice,
                                    'initial_sale_price' => $salePrice,
                                    'max_discount' => 0,
                                    'status' => 1,
                                ]
                            );

                            // Ensure StoreStock + StockBatch exist for updated products too
                            if ($storeId && $initialQty > 0) {
                                $storeStock = StoreStock::firstOrCreate(
                                    ['store_id' => $storeId, 'product_id' => $productId],
                                    [
                                        'initial_quantity' => 0,
                                        'current_quantity' => 0,
                                        'quantity_sale' => 0,
                                        'order_quantity' => 0,
                                        'reorder_level' => $reorderLevel,
                                        'is_active' => true,
                                    ]
                                );

                                // Check if this product already has batches in this store
                                $existingBatchQty = StockBatch::where('product_id', $productId)
                                    ->where('store_id', $storeId)
                                    ->active()
                                    ->sum('current_qty');

                                // If store_stock qty is higher than batch total, create a reconciliation batch
                                $storeQty = $storeStock->current_quantity;
                                $unbatchedQty = max(0, $storeQty - $existingBatchQty);
                                if ($unbatchedQty > 0) {
                                    StockBatch::create([
                                        'product_id' => $productId,
                                        'store_id' => $storeId,
                                        'batch_name' => 'Import Reconciliation - ' . now()->format('M d, Y h:i A'),
                                        'batch_number' => 'IMP-RECON-' . strtoupper(Str::random(6)),
                                        'initial_qty' => $unbatchedQty,
                                        'current_qty' => $unbatchedQty,
                                        'sold_qty' => 0,
                                        'cost_price' => $costPrice,
                                        'received_date' => now(),
                                        'source' => 'manual',
                                        'is_active' => true,
                                        'created_by' => auth()->id(),
                                    ]);
                                }
                            }

                            $report['updated']++;
                            $report['updated_items'][] = $productCode;
                        } else {
                            // CREATE new product
                            $product = Product::create([
                                'user_id' => auth()->id(),
                                'category_id' => $categoryId,
                                'product_name' => $productName,
                                'product_code' => $productCode,
                                'reorder_alert' => $reorderLevel,
                                'visible' => $isActive,
                                'current_quantity' => $initialQty,
                            ]);

                            // Price record (triggers observer for HMO tariffs)
                            Price::create([
                                'product_id' => $product->id,
                                'pr_buy_price' => $costPrice,
                                'initial_sale_price' => $salePrice,
                                'current_sale_price' => $salePrice,
                                'max_discount' => 0,
                                'status' => 1,
                            ]);

                            // Stock record
                            Stock::create([
                                'product_id' => $product->id,
                                'initial_quantity' => $initialQty,
                                'current_quantity' => $initialQty,
                                'quantity_sale' => 0,
                                'order_quantity' => 0,
                            ]);

                            // StoreStock if store specified
                            if ($storeId) {
                                StoreStock::create([
                                    'store_id' => $storeId,
                                    'product_id' => $product->id,
                                    'initial_quantity' => $initialQty,
                                    'current_quantity' => $initialQty,
                                    'quantity_sale' => 0,
                                    'order_quantity' => 0,
                                    'reorder_level' => $reorderLevel,
                                    'is_active' => true,
                                ]);

                                // Create a StockBatch so batch-aware dispensing works
                                if ($initialQty > 0) {
                                    StockBatch::create([
                                        'product_id' => $product->id,
                                        'store_id' => $storeId,
                                        'batch_name' => 'Import - ' . now()->format('M d, Y h:i A'),
                                        'batch_number' => 'IMP-' . strtoupper(Str::random(6)),
                                        'initial_qty' => $initialQty,
                                        'current_qty' => $initialQty,
                                        'sold_qty' => 0,
                                        'cost_price' => $costPrice,
                                        'received_date' => now(),
                                        'source' => 'manual',
                                        'is_active' => true,
                                        'created_by' => auth()->id(),
                                    ]);
                                }
                            }

                            // Cache the new product
                            $existingProducts[$productCode] = $product->id;

                            $report['created']++;
                            $report['created_items'][] = $productCode;
                        }
                    }

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    $report['errors'][] = "Batch " . ($batchIndex + 1) . " failed: " . $e->getMessage();
                    Log::error("Product import batch {$batchIndex} failed: " . $e->getMessage());
                }
            }

            $duration = round(microtime(true) - $startTime, 2);

            return back()->with('import_report', [
                'type' => 'Products',
                'created' => $report['created'],
                'updated' => $report['updated'],
                'skipped' => $report['skipped'],
                'errors' => $report['errors'],
                'duration' => $duration,
                'total_rows' => count($data),
                'batches_processed' => $totalBatches,
            ]);

        } catch (\Exception $e) {
            Log::error('Product import failed: ' . $e->getMessage());
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    /**
     * Import services from CSV or XLSX
     * - Batch commits every 50 records
     * - Detects duplicates by service_code and updates instead of skipping
     * - Handles special categories: Bed (creates Bed records), Procedures (creates ProcedureDefinition)
     * - Returns detailed import report
     */
    public function importServices(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $file = $request->file('file');
        $startTime = microtime(true);

        try {
            $data = $this->parseFile($file);

            if (empty($data)) {
                return back()->with('error', 'File is empty or invalid.');
            }

            // Pre-fetch lookup data for performance
            $existingServices = Service::pluck('id', 'service_code')->toArray();
            $categories = ServiceCategory::pluck('id', 'category_name')->toArray();

            // Get special category IDs from appsettings
            $bedCategoryId = appsettings('bed_service_category_id');
            $procedureCategoryId = appsettings('procedure_category_id');

            // Pre-fetch procedure categories
            $procedureCategories = \App\Models\ProcedureCategory::pluck('id', 'name')->toArray();

            $report = [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [],
                'created_items' => [],
                'updated_items' => [],
                'beds_created' => 0,
                'procedures_created' => 0,
            ];

            $batches = array_chunk($data, self::BATCH_SIZE);
            $totalBatches = count($batches);

            foreach ($batches as $batchIndex => $batch) {
                DB::beginTransaction();
                try {
                    foreach ($batch as $index => $row) {
                        $rowNum = ($batchIndex * self::BATCH_SIZE) + $index + 2;

                        if (empty($row['service_name']) || empty($row['service_code'])) {
                            $report['errors'][] = "Row {$rowNum}: Missing service_name or service_code";
                            $report['skipped']++;
                            continue;
                        }

                        $serviceCode = trim($row['service_code']);
                        $serviceName = trim($row['service_name']);

                        // Get or create category (cached)
                        $categoryId = null;
                        if (!empty($row['category_name'])) {
                            $categoryName = trim($row['category_name']);
                            if (!isset($categories[$categoryName])) {
                                $category = ServiceCategory::create([
                                    'category_name' => $categoryName,
                                    'description' => 'Auto-created during import'
                                ]);
                                $categories[$categoryName] = $category->id;
                            }
                            $categoryId = $categories[$categoryName];
                        }

                        $costPrice = floatval($row['cost_price'] ?? 0);
                        $salePrice = floatval($row['price'] ?? 0);
                        $isActive = ($row['is_active'] ?? 1) == 1 ? 1 : 0;

                        if (isset($existingServices[$serviceCode])) {
                            // UPDATE existing service
                            $serviceId = $existingServices[$serviceCode];

                            $service = Service::find($serviceId);
                            $oldCategoryId = $service->category_id;

                            $service->update([
                                'service_name' => $serviceName,
                                'category_id' => $categoryId,
                                'status' => $isActive,
                            ]);

                            // Update ServicePrice
                            ServicePrice::updateOrCreate(
                                ['service_id' => $serviceId],
                                [
                                    'cost_price' => $costPrice,
                                    'sale_price' => $salePrice,
                                ]
                            );

                            // Handle category-specific related models
                            $this->handleCategoryRelatedModels(
                                $service,
                                $categoryId,
                                $oldCategoryId,
                                $bedCategoryId,
                                $procedureCategoryId,
                                $salePrice,
                                $row,
                                $procedureCategories,
                                $report
                            );

                            $report['updated']++;
                            $report['updated_items'][] = $serviceCode;
                        } else {
                            // CREATE new service
                            $service = Service::create([
                                'user_id' => auth()->id(),
                                'category_id' => $categoryId,
                                'service_name' => $serviceName,
                                'service_code' => $serviceCode,
                                'status' => $isActive,
                            ]);

                            // ServicePrice (triggers observer for HMO tariffs)
                            ServicePrice::create([
                                'service_id' => $service->id,
                                'cost_price' => $costPrice,
                                'sale_price' => $salePrice,
                            ]);

                            // Handle category-specific related models for new services
                            $this->handleCategoryRelatedModels(
                                $service,
                                $categoryId,
                                null,  // No old category for new service
                                $bedCategoryId,
                                $procedureCategoryId,
                                $salePrice,
                                $row,
                                $procedureCategories,
                                $report
                            );

                            $existingServices[$serviceCode] = $service->id;

                            $report['created']++;
                            $report['created_items'][] = $serviceCode;
                        }
                    }

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    $report['errors'][] = "Batch " . ($batchIndex + 1) . " failed: " . $e->getMessage();
                    Log::error("Service import batch {$batchIndex} failed: " . $e->getMessage());
                }
            }

            $duration = round(microtime(true) - $startTime, 2);

            return back()->with('import_report', [
                'type' => 'Services',
                'created' => $report['created'],
                'updated' => $report['updated'],
                'skipped' => $report['skipped'],
                'errors' => $report['errors'],
                'duration' => $duration,
                'total_rows' => count($data),
                'batches_processed' => $totalBatches,
                'beds_created' => $report['beds_created'],
                'procedures_created' => $report['procedures_created'],
            ]);

        } catch (\Exception $e) {
            Log::error('Service import failed: ' . $e->getMessage());
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle category-specific related models (Bed, ProcedureDefinition)
     * Called during service import for both create and update operations.
     */
    protected function handleCategoryRelatedModels(
        $service,
        $categoryId,
        $oldCategoryId,
        $bedCategoryId,
        $procedureCategoryId,
        $salePrice,
        $row,
        $procedureCategories,
        &$report
    ) {
        // === BED CATEGORY ===
        if ($categoryId == $bedCategoryId) {
            // Create or update linked Bed record
            $bed = \App\Models\Bed::where('service_id', $service->id)->first();

            // Parse bed details from service name or specific columns
            $bedName = $row['bed_name'] ?? $this->extractBedNameFromServiceName($service->service_name);
            $wardName = $row['ward'] ?? $this->extractWardFromServiceName($service->service_name);
            $wardId = null;

            // Try to find ward by name
            if ($wardName) {
                $ward = \App\Models\Ward::where('name', 'LIKE', "%{$wardName}%")->first();
                $wardId = $ward ? $ward->id : null;
            }

            if ($bed) {
                // Update existing bed
                $bed->update([
                    'name' => $bedName,
                    'ward' => $wardName,
                    'ward_id' => $wardId ?? $bed->ward_id,
                    'price' => $salePrice,
                    'unit' => $row['unit'] ?? $bed->unit,
                ]);
            } else {
                // Create new bed linked to this service
                \App\Models\Bed::create([
                    'name' => $bedName,
                    'ward' => $wardName,
                    'ward_id' => $wardId,
                    'price' => $salePrice,
                    'unit' => $row['unit'] ?? null,
                    'service_id' => $service->id,
                    'status' => 1,
                    'bed_status' => 'available',
                ]);
                $report['beds_created']++;
            }
        } elseif ($oldCategoryId == $bedCategoryId && $categoryId != $bedCategoryId) {
            // Category changed FROM bed - unlink bed from service (don't delete bed)
            \App\Models\Bed::where('service_id', $service->id)->update(['service_id' => null]);
        }

        // === PROCEDURE CATEGORY ===
        if ($categoryId == $procedureCategoryId) {
            // Get procedure category from row or default
            $procCategoryName = $row['procedure_category'] ?? 'General';
            $procCategoryId = $procedureCategories[$procCategoryName] ?? null;

            // Create default procedure category if not found
            if (!$procCategoryId) {
                $procCat = \App\Models\ProcedureCategory::firstOrCreate(
                    ['name' => $procCategoryName],
                    ['description' => 'Auto-created during import', 'status' => 1]
                );
                $procCategoryId = $procCat->id;
                $procedureCategories[$procCategoryName] = $procCategoryId;
            }

            // Create or update procedure definition
            \App\Models\ProcedureDefinition::updateOrCreate(
                ['service_id' => $service->id],
                [
                    'procedure_category_id' => $procCategoryId,
                    'name' => $service->service_name,
                    'code' => $row['procedure_code'] ?? $service->service_code,
                    'description' => $row['procedure_description'] ?? $row['description'] ?? null,
                    'is_surgical' => filter_var($row['is_surgical'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'estimated_duration_minutes' => intval($row['duration_minutes'] ?? 0) ?: null,
                    'status' => 1,
                ]
            );
            $report['procedures_created']++;
        } elseif ($oldCategoryId == $procedureCategoryId && $categoryId != $procedureCategoryId) {
            // Category changed FROM procedure - delete linked procedure definition
            \App\Models\ProcedureDefinition::where('service_id', $service->id)->delete();
        }
    }

    /**
     * Extract bed name from service name (e.g., "Bed ICU 1 Intensive Care Unit" -> "ICU 1")
     */
    protected function extractBedNameFromServiceName($serviceName)
    {
        // Remove "Bed " prefix if present
        $name = preg_replace('/^Bed\s+/i', '', $serviceName);

        // Try to extract the bed identifier (usually first 1-3 words)
        $parts = explode(' ', $name);
        if (count($parts) >= 2) {
            return $parts[0] . ' ' . $parts[1];
        }
        return $parts[0] ?? $serviceName;
    }

    /**
     * Extract ward name from service name (e.g., "Bed ICU 1 Intensive Care Unit" -> "Intensive Care Unit")
     */
    protected function extractWardFromServiceName($serviceName)
    {
        // Remove "Bed " prefix if present
        $name = preg_replace('/^Bed\s+/i', '', $serviceName);

        // Skip bed identifier and return the rest
        $parts = explode(' ', $name);
        if (count($parts) > 2) {
            // Return everything after the first 2 words
            return implode(' ', array_slice($parts, 2));
        }
        return null;
    }

    /**
     * Import staff from CSV or XLSX
     * - Batch commits every 50 records
     * - Detects duplicates by email and updates instead of skipping
     * - Returns detailed import report
     */
    public function importStaff(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
            'default_password' => 'nullable|string|min:6',
        ]);

        $file = $request->file('file');
        $defaultPassword = $request->default_password ?? 'password123';
        $startTime = microtime(true);

        try {
            $data = $this->parseFile($file);

            if (empty($data)) {
                return back()->with('error', 'File is empty or invalid.');
            }

            // Pre-fetch lookup data for performance
            $existingUsers = User::where('is_admin', 2)->pluck('id', 'email')->toArray();
            $specializations = Specialization::pluck('id', 'name')->toArray();
            $clinics = Clinic::pluck('id', 'name')->toArray();
            $departments = \App\Models\Department::pluck('id', 'name')->toArray();
            $roles = Role::pluck('id', 'name')->toArray();

            $report = [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [],
                'created_items' => [],
                'updated_items' => [],
            ];

            $batches = array_chunk($data, self::BATCH_SIZE);
            $totalBatches = count($batches);

            foreach ($batches as $batchIndex => $batch) {
                DB::beginTransaction();
                try {
                    foreach ($batch as $index => $row) {
                        $rowNum = ($batchIndex * self::BATCH_SIZE) + $index + 2;

                        // Required fields validation
                        if (empty($row['surname']) || empty($row['firstname']) || empty($row['email'])) {
                            $report['errors'][] = "Row {$rowNum}: Missing required fields (surname, firstname, email)";
                            $report['skipped']++;
                            continue;
                        }

                        $email = strtolower(trim($row['email']));

                        // Validate gender
                        $gender = trim($row['gender'] ?? '');
                        if (empty($gender) || !in_array($gender, ['Male', 'Female', 'Others'])) {
                            $report['errors'][] = "Row {$rowNum}: Invalid or missing gender (must be Male, Female, or Others)";
                            $report['skipped']++;
                            continue;
                        }

                        // Validate role
                        $roleName = trim($row['role'] ?? 'STAFF');
                        if (!isset($roles[$roleName])) {
                            $report['errors'][] = "Row {$rowNum}: Role '{$roleName}' not found";
                            $report['skipped']++;
                            continue;
                        }

                        // Get foreign keys from cache
                        $specializationId = !empty($row['specialization']) && isset($specializations[trim($row['specialization'])])
                            ? $specializations[trim($row['specialization'])] : null;
                        $clinicId = !empty($row['clinic']) && isset($clinics[trim($row['clinic'])])
                            ? $clinics[trim($row['clinic'])] : null;
                        $departmentId = !empty($row['department']) && isset($departments[trim($row['department'])])
                            ? $departments[trim($row['department'])] : null;

                        // Validate employment type/status
                        $employmentType = trim($row['employment_type'] ?? 'full_time');
                        if (!in_array($employmentType, ['full_time', 'part_time', 'contract', 'intern'])) {
                            $employmentType = 'full_time';
                        }
                        $employmentStatus = trim($row['employment_status'] ?? 'active');
                        if (!in_array($employmentStatus, ['active', 'suspended', 'terminated', 'resigned'])) {
                            $employmentStatus = 'active';
                        }

                        if (isset($existingUsers[$email])) {
                            // UPDATE existing staff
                            $userId = $existingUsers[$email];

                            User::where('id', $userId)->update([
                                'surname' => trim($row['surname']),
                                'firstname' => trim($row['firstname']),
                                'othername' => trim($row['othername'] ?? ''),
                            ]);

                            Staff::updateOrCreate(
                                ['user_id' => $userId],
                                [
                                    'employee_id' => !empty($row['employee_id']) ? trim($row['employee_id']) : null,
                                    'specialization_id' => $specializationId,
                                    'clinic_id' => $clinicId,
                                    'department_id' => $departmentId,
                                    'gender' => $gender,
                                    'date_of_birth' => !empty($row['date_of_birth']) ? $row['date_of_birth'] : null,
                                    'home_address' => $row['home_address'] ?? null,
                                    'phone_number' => $row['phone_number'] ?? null,
                                    'job_title' => !empty($row['job_title']) ? trim($row['job_title']) : null,
                                    'date_hired' => !empty($row['date_hired']) ? $row['date_hired'] : null,
                                    'employment_type' => $employmentType,
                                    'employment_status' => $employmentStatus,
                                    'consultation_fee' => floatval($row['consultation_fee'] ?? 0),
                                    'is_unit_head' => ($row['is_unit_head'] ?? 0) == 1,
                                    'is_dept_head' => ($row['is_dept_head'] ?? 0) == 1,
                                    'bank_name' => !empty($row['bank_name']) ? trim($row['bank_name']) : null,
                                    'bank_account_number' => !empty($row['bank_account_number']) ? trim($row['bank_account_number']) : null,
                                    'bank_account_name' => !empty($row['bank_account_name']) ? trim($row['bank_account_name']) : null,
                                    'emergency_contact_name' => !empty($row['emergency_contact_name']) ? trim($row['emergency_contact_name']) : null,
                                    'emergency_contact_phone' => !empty($row['emergency_contact_phone']) ? trim($row['emergency_contact_phone']) : null,
                                    'emergency_contact_relationship' => !empty($row['emergency_contact_relationship']) ? trim($row['emergency_contact_relationship']) : null,
                                    'tax_id' => !empty($row['tax_id']) ? trim($row['tax_id']) : null,
                                    'pension_id' => !empty($row['pension_id']) ? trim($row['pension_id']) : null,
                                    'status' => 1,
                                ]
                            );

                            $report['updated']++;
                            $report['updated_items'][] = $email;
                        } else {
                            // CREATE new staff
                            $user = User::create([
                                'surname' => trim($row['surname']),
                                'firstname' => trim($row['firstname']),
                                'othername' => trim($row['othername'] ?? ''),
                                'email' => $email,
                                'password' => Hash::make($defaultPassword),
                                'is_admin' => 2,
                                'status' => 1,
                            ]);

                            $user->assignRole($roleName);

                            Staff::create([
                                'user_id' => $user->id,
                                'employee_id' => !empty($row['employee_id']) ? trim($row['employee_id']) : null,
                                'specialization_id' => $specializationId,
                                'clinic_id' => $clinicId,
                                'department_id' => $departmentId,
                                'gender' => $gender,
                                'date_of_birth' => !empty($row['date_of_birth']) ? $row['date_of_birth'] : null,
                                'home_address' => $row['home_address'] ?? null,
                                'phone_number' => $row['phone_number'] ?? null,
                                'job_title' => !empty($row['job_title']) ? trim($row['job_title']) : null,
                                'date_hired' => !empty($row['date_hired']) ? $row['date_hired'] : null,
                                'employment_type' => $employmentType,
                                'employment_status' => $employmentStatus,
                                'consultation_fee' => floatval($row['consultation_fee'] ?? 0),
                                'is_unit_head' => ($row['is_unit_head'] ?? 0) == 1,
                                'is_dept_head' => ($row['is_dept_head'] ?? 0) == 1,
                                'bank_name' => !empty($row['bank_name']) ? trim($row['bank_name']) : null,
                                'bank_account_number' => !empty($row['bank_account_number']) ? trim($row['bank_account_number']) : null,
                                'bank_account_name' => !empty($row['bank_account_name']) ? trim($row['bank_account_name']) : null,
                                'emergency_contact_name' => !empty($row['emergency_contact_name']) ? trim($row['emergency_contact_name']) : null,
                                'emergency_contact_phone' => !empty($row['emergency_contact_phone']) ? trim($row['emergency_contact_phone']) : null,
                                'emergency_contact_relationship' => !empty($row['emergency_contact_relationship']) ? trim($row['emergency_contact_relationship']) : null,
                                'tax_id' => !empty($row['tax_id']) ? trim($row['tax_id']) : null,
                                'pension_id' => !empty($row['pension_id']) ? trim($row['pension_id']) : null,
                                'status' => 1,
                            ]);

                            $existingUsers[$email] = $user->id;

                            $report['created']++;
                            $report['created_items'][] = $email;
                        }
                    }

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    $report['errors'][] = "Batch " . ($batchIndex + 1) . " failed: " . $e->getMessage();
                    Log::error("Staff import batch {$batchIndex} failed: " . $e->getMessage());
                }
            }

            $duration = round(microtime(true) - $startTime, 2);

            return back()->with('import_report', [
                'type' => 'Staff',
                'created' => $report['created'],
                'updated' => $report['updated'],
                'skipped' => $report['skipped'],
                'errors' => $report['errors'],
                'duration' => $duration,
                'total_rows' => count($data),
                'batches_processed' => $totalBatches,
            ]);

        } catch (\Exception $e) {
            Log::error('Staff import failed: ' . $e->getMessage());
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    /**
     * Import patients from CSV or XLSX
     * - Batch commits every 50 records
     * - Detects duplicates by file_no and updates instead of skipping
     * - Allows blank patients (only file_no required) with defaults:
     *   surname: "Blank", firstname: file_no, dob: 2000-01-01, gender: Male
     * - Returns detailed import report
     */
    public function importPatients(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $file = $request->file('file');
        $startTime = microtime(true);

        try {
            $data = $this->parseFile($file);

            if (empty($data)) {
                return back()->with('error', 'File is empty or invalid.');
            }

            // Pre-fetch lookup data for performance
            $existingPatients = patient::pluck('user_id', 'file_no')->toArray();
            $existingEmails = User::pluck('id', 'email')->toArray();
            $hmos = Hmo::pluck('id', 'name')->toArray();
            $patientRole = Role::where('name', 'PATIENT')->first();

            $report = [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [],
                'created_items' => [],
                'updated_items' => [],
            ];

            $batches = array_chunk($data, self::BATCH_SIZE);
            $totalBatches = count($batches);

            foreach ($batches as $batchIndex => $batch) {
                DB::beginTransaction();
                try {
                    foreach ($batch as $index => $row) {
                        $rowNum = ($batchIndex * self::BATCH_SIZE) + $index + 2;

                        // File number is the primary key for patient identification
                        $fileNo = trim($row['file_no'] ?? '');

                        // If no file_no provided, generate one
                        if (empty($fileNo)) {
                            // Check if we have enough data to create a patient
                            if (empty($row['surname']) && empty($row['firstname'])) {
                                $report['errors'][] = "Row {$rowNum}: No file_no and no name data provided";
                                $report['skipped']++;
                                continue;
                            }
                            $fileNo = $this->generatePatientFileNo();
                        }

                        // Apply defaults for blank patients (only file_no provided)
                        $surname = trim($row['surname'] ?? '');
                        $firstname = trim($row['firstname'] ?? '');
                        $gender = trim($row['gender'] ?? '');
                        $dob = trim($row['dob'] ?? '');

                        // If minimal data, use defaults
                        if (empty($surname)) {
                            $surname = 'Blank';
                        }
                        if (empty($firstname)) {
                            $firstname = $fileNo;
                        }
                        if (empty($gender) || !in_array($gender, ['Male', 'Female', 'Others'])) {
                            $gender = 'Male';
                        }
                        if (empty($dob)) {
                            $dob = '2000-01-01';
                        }

                        // Get HMO if provided
                        $hmoId = null;
                        if (!empty($row['hmo_name']) && isset($hmos[trim($row['hmo_name'])])) {
                            $hmoId = $hmos[trim($row['hmo_name'])];
                        }

                        // Parse HMO number
                        $hmoNo = null;
                        if (!empty($row['hmo_no'])) {
                            $hmoNoValue = trim($row['hmo_no']);
                            if (is_numeric($hmoNoValue)) {
                                $hmoNo = (int) $hmoNoValue;
                            } else {
                                preg_match('/\d+/', $hmoNoValue, $matches);
                                if (!empty($matches[0])) {
                                    $hmoNo = (int) $matches[0];
                                }
                            }
                        }

                        // Parse allergies
                        $allergies = null;
                        if (!empty($row['allergies'])) {
                            $allergies = array_map('trim', explode(',', $row['allergies']));
                        }

                        if (isset($existingPatients[$fileNo])) {
                            // UPDATE existing patient
                            $userId = $existingPatients[$fileNo];

                            User::where('id', $userId)->update([
                                'surname' => $surname,
                                'firstname' => $firstname,
                                'othername' => trim($row['othername'] ?? ''),
                            ]);

                            patient::where('file_no', $fileNo)->update([
                                'hmo_id' => $hmoId,
                                'hmo_no' => $hmoNo,
                                'gender' => $gender,
                                'dob' => $dob,
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

                            $report['updated']++;
                            $report['updated_items'][] = $fileNo;
                        } else {
                            // CREATE new patient
                            // Handle email - generate unique if not provided or exists
                            $email = trim($row['email'] ?? '');
                            if (empty($email) || isset($existingEmails[$email])) {
                                $email = strtolower(Str::slug($surname . '.' . $firstname)) . '.' . Str::random(4) . '@patient.local';
                                // Make sure generated email is unique
                                while (isset($existingEmails[$email])) {
                                    $email = strtolower(Str::slug($surname . '.' . $firstname)) . '.' . Str::random(4) . '@patient.local';
                                }
                            }

                            $user = User::create([
                                'surname' => $surname,
                                'firstname' => $firstname,
                                'othername' => trim($row['othername'] ?? ''),
                                'email' => $email,
                                'password' => Hash::make(Str::random(12)),
                                'is_admin' => 3,
                                'status' => 1,
                            ]);

                            if ($patientRole) {
                                $user->assignRole($patientRole);
                            }

                            patient::create([
                                'user_id' => $user->id,
                                'file_no' => $fileNo,
                                'hmo_id' => $hmoId,
                                'hmo_no' => $hmoNo,
                                'gender' => $gender,
                                'dob' => $dob,
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

                            // Cache new entries
                            $existingPatients[$fileNo] = $user->id;
                            $existingEmails[$email] = $user->id;

                            $report['created']++;
                            $report['created_items'][] = $fileNo;
                        }
                    }

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    $report['errors'][] = "Batch " . ($batchIndex + 1) . " failed: " . $e->getMessage();
                    Log::error("Patient import batch {$batchIndex} failed: " . $e->getMessage());
                }
            }

            $duration = round(microtime(true) - $startTime, 2);

            return back()->with('import_report', [
                'type' => 'Patients',
                'created' => $report['created'],
                'updated' => $report['updated'],
                'skipped' => $report['skipped'],
                'errors' => $report['errors'],
                'duration' => $duration,
                'total_rows' => count($data),
                'batches_processed' => $totalBatches,
            ]);

        } catch (\Exception $e) {
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

        $query = Staff::with(['user', 'user.roles', 'specialization', 'clinic', 'department']);

        $staffMembers = $query->get();

        // Filter by role if specified
        if ($roleFilter) {
            $staffMembers = $staffMembers->filter(function ($staff) use ($roleFilter) {
                return $staff->user && $staff->user->hasRole($roleFilter);
            });
        }

        $headers = [
            'id', 'employee_id', 'surname', 'firstname', 'othername', 'email', 'phone_number',
            'gender', 'date_of_birth', 'home_address', 'roles', 'specialization',
            'clinic', 'department', 'job_title', 'date_hired', 'employment_type', 'employment_status',
            'consultation_fee', 'is_unit_head', 'is_dept_head',
            'bank_name', 'bank_account_number', 'bank_account_name',
            'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship',
            'tax_id', 'pension_id', 'created_at'
        ];

        $data = [];
        foreach ($staffMembers as $staff) {
            if (!$staff->user) continue;

            $data[] = [
                $staff->id,
                $staff->employee_id ?? '',
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
                $staff->department->name ?? '',
                $staff->job_title ?? '',
                $staff->date_hired ? $staff->date_hired->format('Y-m-d') : '',
                $staff->employment_type ?? 'full_time',
                $staff->employment_status ?? 'active',
                $staff->consultation_fee ?? 0,
                $staff->is_unit_head ? 1 : 0,
                $staff->is_dept_head ? 1 : 0,
                $staff->bank_name ?? '',
                $staff->bank_account_number ?? '',
                $staff->bank_account_name ?? '',
                $staff->emergency_contact_name ?? '',
                $staff->emergency_contact_phone ?? '',
                $staff->emergency_contact_relationship ?? '',
                $staff->tax_id ?? '',
                $staff->pension_id ?? '',
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

    // ========================================
    // ASYNC IMPORT ENDPOINTS (AJAX + Queue)
    // ========================================

    /**
     * Upload file and queue the import job
     *
     * @param Request $request
     * @param string $type (products, services, staff, patients)
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadAndQueueImport(Request $request, string $type)
    {
        // Validate type
        $validTypes = ['products', 'services', 'staff', 'patients'];
        if (!in_array($type, $validTypes)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid import type: ' . $type
            ], 400);
        }

        // Validate file
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:51200', // 50MB max for large files
        ]);

        try {
            // Store the file temporarily (don't parse it here!)
            $file = $request->file('file');
            $filename = 'import_' . uniqid() . '_' . time() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('imports', $filename, 'local');

            // Get file row count estimate (quick scan for progress tracking)
            $extension = strtolower($file->getClientOriginalExtension());
            $estimatedRows = $this->estimateRowCount(storage_path('app/' . $filePath), $extension);

            // Get additional options based on type
            $options = [];
            if ($type === 'products') {
                $options['default_store_id'] = $request->input('default_store_id');
            } elseif ($type === 'staff') {
                $options['default_password'] = $request->input('default_password', 'password123');
            }

            // Get duplicate handling option (update or skip)
            $duplicateAction = $request->input('duplicate_action', 'update');

            // Generate import ID and start progress tracking
            $importId = \App\Services\ImportProgressService::generateImportId();
            $userId = auth()->id() ?? 0;

            \App\Services\ImportProgressService::startImport(
                $importId,
                $type,
                $estimatedRows,
                $userId
            );

            // Dispatch the appropriate job with FILE PATH (not parsed data)
            switch ($type) {
                case 'products':
                    $defaultStoreId = $options['default_store_id'] ?? null;
                    \App\Jobs\ImportProductsJob::dispatch($importId, $filePath, $defaultStoreId, $userId, $duplicateAction);
                    break;
                case 'services':
                    \App\Jobs\ImportServicesJob::dispatch($importId, $filePath, $userId, $duplicateAction);
                    break;
                case 'staff':
                    $defaultPassword = $options['default_password'] ?? 'password123';
                    \App\Jobs\ImportStaffJob::dispatch($importId, $filePath, $defaultPassword, $userId, $duplicateAction);
                    break;
                case 'patients':
                    \App\Jobs\ImportPatientsJob::dispatch($importId, $filePath, $userId, $duplicateAction);
                    break;
            }

            return response()->json([
                'success' => true,
                'import_id' => $importId,
                'total_rows' => $estimatedRows,
                'message' => ucfirst($type) . ' import queued successfully. Processing in background...'
            ]);

        } catch (\Exception $e) {
            Log::error("Async {$type} import upload failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to process file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Quick estimate of row count without fully parsing the file
     */
    private function estimateRowCount(string $filePath, string $extension): int
    {
        try {
            if (in_array($extension, ['xlsx', 'xls'])) {
                // For Excel, use a lightweight reader to get row count
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
                $reader->setReadDataOnly(true);

                // Only read first sheet info
                $spreadsheet = $reader->load($filePath);
                $sheet = $spreadsheet->getActiveSheet();
                $highestRow = $sheet->getHighestRow();
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);

                return max(0, $highestRow - 1); // Subtract header row
            } else {
                // For CSV, count lines quickly
                $lineCount = 0;
                $handle = fopen($filePath, 'r');
                while (!feof($handle)) {
                    fgets($handle);
                    $lineCount++;
                }
                fclose($handle);
                return max(0, $lineCount - 1); // Subtract header row
            }
        } catch (\Exception $e) {
            Log::warning("Could not estimate row count: " . $e->getMessage());
            return 0; // Unknown
        }
    }

    /**
     * Get import status/progress
     *
     * @param string $importId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getImportStatus(string $importId)
    {
        $progress = \App\Services\ImportProgressService::getProgress($importId);

        if (!$progress) {
            return response()->json([
                'success' => false,
                'error' => 'Import not found or expired.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'progress' => $progress
        ]);
    }

    /**
     * Cancel a running import
     *
     * @param string $importId
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelImport(string $importId)
    {
        $progress = \App\Services\ImportProgressService::getProgress($importId);

        if (!$progress) {
            return response()->json([
                'success' => false,
                'error' => 'Import not found or expired.'
            ], 404);
        }

        if ($progress['status'] === 'completed' || $progress['status'] === 'failed') {
            return response()->json([
                'success' => false,
                'error' => 'Cannot cancel an import that has already finished.'
            ], 400);
        }

        \App\Services\ImportProgressService::cancelImport($importId);

        return response()->json([
            'success' => true,
            'message' => 'Import cancellation requested.'
        ]);
    }
}
