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

/**
 * ImportExportController
 *
 * Handles CSV import/export for:
 * - Products (Stock)
 * - Services (Labs, Imaging, Nursing, etc.)
 * - Staff
 * - Patients
 *
 * Each import creates related records (User, Price, Stock) as needed.
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
            'products' => ProductCategory::orderBy('name')->get(),
            'services' => ServiceCategory::orderBy('name')->get(),
        ];

        $stores = Store::where('is_active', true)->orderBy('store_name')->get();
        $specializations = Specialization::orderBy('name')->get();
        $clinics = Clinic::orderBy('clinic_name')->get();
        $hmos = Hmo::orderBy('hmo_name')->get();
        $roles = Role::orderBy('name')->get();

        return view('admin.import-export.index', compact(
            'stats', 'categories', 'stores', 'specializations', 'clinics', 'hmos', 'roles'
        ));
    }

    // ========================================
    // TEMPLATE DOWNLOADS
    // ========================================

    /**
     * Download CSV template for products
     */
    public function downloadProductTemplate()
    {
        $headers = [
            'product_name',         // Required
            'product_code',         // Required (SKU)
            'category_name',        // Required - will lookup/create ProductCategory
            'description',          // Optional
            'unit',                 // Optional (e.g., "tablets", "bottles")
            'cost_price',           // Required - for Price model
            'sale_price',           // Required - for Price model
            'reorder_level',        // Optional - alert threshold
            'initial_quantity',     // Optional - for Stock/StoreStock
            'store_name',           // Optional - if provided, creates StoreStock
            'batch_number',         // Optional - for batch tracking
            'expiry_date',          // Optional - format: YYYY-MM-DD
            'is_active',            // Optional - 1 or 0
        ];

        $sampleData = [
            [
                'Paracetamol 500mg',
                'PARA-500',
                'Analgesics',
                'Pain relief tablets',
                'tablets',
                '50.00',
                '100.00',
                '100',
                '500',
                'Main Pharmacy',
                'BTH-001',
                '2027-12-31',
                '1',
            ],
            [
                'Amoxicillin 250mg',
                'AMOX-250',
                'Antibiotics',
                'Antibiotic capsules',
                'capsules',
                '80.00',
                '150.00',
                '50',
                '200',
                'Main Pharmacy',
                'BTH-002',
                '2026-06-30',
                '1',
            ],
        ];

        return $this->generateCsvResponse($headers, $sampleData, 'products_template.csv');
    }

    /**
     * Download CSV template for services
     */
    public function downloadServiceTemplate()
    {
        $headers = [
            'service_name',         // Required
            'service_code',         // Required
            'category_name',        // Required - will lookup/create ServiceCategory
            'description',          // Optional
            'price',                // Required - for ServicePrice model
            'cost_price',           // Optional - for ServicePrice model
            'duration_minutes',     // Optional
            'is_active',            // Optional - 1 or 0
        ];

        $sampleData = [
            [
                'Full Blood Count',
                'LAB-FBC-001',
                'Laboratory - Hematology',
                'Complete blood count analysis',
                '5000.00',
                '2000.00',
                '30',
                '1',
            ],
            [
                'Chest X-Ray',
                'IMG-CXR-001',
                'Imaging - Radiology',
                'Standard chest x-ray examination',
                '15000.00',
                '5000.00',
                '15',
                '1',
            ],
            [
                'Wound Dressing',
                'NUR-WD-001',
                'Nursing - Procedures',
                'Standard wound care and dressing',
                '3000.00',
                '500.00',
                '20',
                '1',
            ],
        ];

        return $this->generateCsvResponse($headers, $sampleData, 'services_template.csv');
    }

    /**
     * Download CSV template for staff
     */
    public function downloadStaffTemplate()
    {
        $headers = [
            'surname',              // Required
            'firstname',            // Required
            'othername',            // Optional
            'email',                // Required - unique
            'phone_number',         // Optional
            'gender',               // Optional - Male/Female
            'date_of_birth',        // Optional - YYYY-MM-DD
            'home_address',         // Optional
            'role',                 // Required - must match existing role name
            'specialization',       // Optional - for doctors
            'clinic',               // Optional - clinic name
            'consultation_fee',     // Optional - for doctors
            'is_unit_head',         // Optional - 1 or 0
            'is_dept_head',         // Optional - 1 or 0
        ];

        $sampleData = [
            [
                'Adeyemi',
                'John',
                'Oluwaseun',
                'john.adeyemi@hospital.com',
                '08012345678',
                'Male',
                '1985-05-15',
                '123 Hospital Road, Lagos',
                'DOCTOR',
                'General Practice',
                'General Outpatient',
                '5000.00',
                '0',
                '0',
            ],
            [
                'Okonkwo',
                'Grace',
                '',
                'grace.okonkwo@hospital.com',
                '08098765432',
                'Female',
                '1990-08-22',
                '456 Nurse Street, Lagos',
                'NURSE',
                '',
                'Ward A',
                '',
                '0',
                '0',
            ],
        ];

        return $this->generateCsvResponse($headers, $sampleData, 'staff_template.csv');
    }

    /**
     * Download CSV template for patients
     */
    public function downloadPatientTemplate()
    {
        $headers = [
            'surname',              // Required
            'firstname',            // Required
            'othername',            // Optional
            'email',                // Optional - if provided, must be unique
            'phone_no',             // Optional
            'gender',               // Required - Male/Female
            'dob',                  // Required - YYYY-MM-DD
            'blood_group',          // Optional - A+, B+, O-, etc.
            'genotype',             // Optional - AA, AS, SS, etc.
            'address',              // Optional
            'nationality',          // Optional
            'ethnicity',            // Optional
            'hmo_name',             // Optional - must match existing HMO
            'hmo_no',               // Optional - HMO enrollment number
            'next_of_kin_name',     // Optional
            'next_of_kin_phone',    // Optional
            'next_of_kin_address',  // Optional
            'allergies',            // Optional - comma-separated
            'medical_history',      // Optional
        ];

        $sampleData = [
            [
                'Bakare',
                'Adebayo',
                'Michael',
                'adebayo.bakare@email.com',
                '08011111111',
                'Male',
                '1988-03-10',
                'O+',
                'AA',
                '789 Patient Avenue, Lagos',
                'Nigerian',
                'Yoruba',
                'NHIS',
                'NHIS-12345',
                'Mrs. Bakare Funke',
                '08022222222',
                '789 Patient Avenue, Lagos',
                'Penicillin,Sulfa drugs',
                'Hypertension diagnosed 2020',
            ],
            [
                'Ibrahim',
                'Fatima',
                '',
                '',
                '08033333333',
                'Female',
                '1995-11-25',
                'B+',
                'AS',
                '321 Health Street, Abuja',
                'Nigerian',
                'Hausa',
                '',
                '',
                'Mr. Ibrahim Ahmed',
                '08044444444',
                '321 Health Street, Abuja',
                '',
                '',
            ],
        ];

        return $this->generateCsvResponse($headers, $sampleData, 'patients_template.csv');
    }

    // ========================================
    // IMPORTS
    // ========================================

    /**
     * Import products from CSV
     */
    public function importProducts(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
            'default_store_id' => 'nullable|exists:stores,id',
        ]);

        $file = $request->file('file');
        $defaultStoreId = $request->default_store_id;

        try {
            $data = $this->parseCsv($file);

            if (empty($data)) {
                return back()->with('error', 'CSV file is empty or invalid.');
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
                            ['name' => trim($row['category_name'])],
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
     * Import services from CSV
     */
    public function importServices(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('file');

        try {
            $data = $this->parseCsv($file);

            if (empty($data)) {
                return back()->with('error', 'CSV file is empty or invalid.');
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
                            ['name' => trim($row['category_name'])],
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
     * Import staff from CSV
     */
    public function importStaff(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
            'default_password' => 'nullable|string|min:6',
        ]);

        $file = $request->file('file');
        $defaultPassword = $request->default_password ?? 'password123';

        try {
            $data = $this->parseCsv($file);

            if (empty($data)) {
                return back()->with('error', 'CSV file is empty or invalid.');
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
                        $specialization = Specialization::where('name', 'like', '%' . trim($row['specialization']) . '%')->first();
                        if ($specialization) {
                            $specializationId = $specialization->id;
                        }
                    }

                    // Get clinic if provided
                    $clinicId = null;
                    if (!empty($row['clinic'])) {
                        $clinic = Clinic::where('clinic_name', 'like', '%' . trim($row['clinic']) . '%')->first();
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
     * Import patients from CSV
     */
    public function importPatients(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('file');

        try {
            $data = $this->parseCsv($file);

            if (empty($data)) {
                return back()->with('error', 'CSV file is empty or invalid.');
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

                    // Get HMO if provided
                    $hmoId = null;
                    if (!empty($row['hmo_name'])) {
                        $hmo = Hmo::where('hmo_name', 'like', '%' . trim($row['hmo_name']) . '%')->first();
                        if ($hmo) {
                            $hmoId = $hmo->id;
                        }
                    }

                    // Generate file number
                    $fileNo = $this->generatePatientFileNo();

                    // Parse allergies (comma-separated to array)
                    $allergies = null;
                    if (!empty($row['allergies'])) {
                        $allergies = array_map('trim', explode(',', $row['allergies']));
                    }

                    // Create patient profile
                    patient::create([
                        'user_id' => $user->id,
                        'file_no' => $fileNo,
                        'hmo_id' => $hmoId,
                        'hmo_no' => $row['hmo_no'] ?? null,
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
                $product->category->name ?? '',
                $product->price->pr_buy_price ?? 0,  // Correct field name: pr_buy_price
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
                $service->category->name ?? '',
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
                $staff->clinic->clinic_name ?? '',
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
                $patient->hmo->hmo_name ?? '',
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
