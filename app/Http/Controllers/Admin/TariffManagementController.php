<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HmoTariff;
use App\Models\Hmo;
use App\Models\HmoScheme;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ProductCategory;
use App\Models\Price;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class TariffManagementController extends Controller
{
    /**
     * Display the tariff management page.
     */
    public function index()
    {
        $hmos = Hmo::with('scheme')->where('status', 1)->orderBy('name', 'ASC')->get();
        $products = Product::where('status', 1)->orderBy('product_name', 'ASC')->get();
        $services = Service::where('status', 1)->orderBy('service_name', 'ASC')->get();
        $schemes = HmoScheme::where('status', 1)->orderBy('name', 'ASC')->get();
        $productCategories = ProductCategory::orderBy('category_name', 'ASC')->get();
        $serviceCategories = ServiceCategory::orderBy('category_name', 'ASC')->get();

        return view('admin.tariffs.index', compact('hmos', 'products', 'services', 'schemes', 'productCategories', 'serviceCategories'));
    }

    /**
     * Dynamically load the configuration view for a specific axis
     */
    public function loadView(Request $request)
    {
        $type = $request->get('type');
        $id = $request->get('id');

        try {
            if ($type === 'product' || $type === 'service') {
                // --- AXIS 1: One Product/Service -> All HMOs ---
                $itemName = '';
                $salePrice = 0;
                $tariffsQuery = HmoTariff::query();
                
                if ($type === 'product') {
                    $product = Product::withoutGlobalScopes()->findOrFail($id);
                    $itemName = $product->product_name;
                    $price = Price::where('product_id', $id)->first();
                    $salePrice = $price ? (float) $price->current_sale_price : 0;
                    $tariffsQuery->where('product_id', $id)->whereNull('service_id');
                } else {
                    $service = Service::withoutGlobalScopes()->findOrFail($id);
                    $itemName = $service->service_name;
                    $price = DB::table('service_prices')->where('service_id', $id)->first();
                    $salePrice = $price ? (float) $price->sale_price : 0;
                    $tariffsQuery->where('service_id', $id)->whereNull('product_id');
                }

                $schemes = HmoScheme::with(['hmos' => fn($q) => $q->where('status', 1)])->get();
                $tariffs = $tariffsQuery->get()->keyBy('hmo_id');

                $schemeSummary = [];
                foreach ($schemes as $scheme) {
                    $activeHmos = $scheme->hmos;
                    if ($activeHmos->isEmpty()) continue;

                    $payableValues = [];
                    $claimsValues = [];
                    $hmosData = [];

                    foreach ($activeHmos as $hmo) {
                        $tariff = $tariffs->get($hmo->id);
                        $payable = $tariff ? (float) $tariff->payable_amount : 0;
                        $claims = $tariff ? (float) $tariff->claims_amount : 0;
                        $payableValues[] = $payable;
                        $claimsValues[] = $claims;
                        $hmosData[] = [
                            'id' => $hmo->id, 'name' => $hmo->name,
                            'payable_amount' => $payable, 'claims_amount' => $claims,
                            'coverage_mode' => $tariff ? $tariff->coverage_mode : 'primary',
                            'has_tariff' => (bool) $tariff,
                            'is_manual' => $tariff && $payable > 0,
                        ];
                    }

                    $schemeSummary[] = [
                        'id' => $scheme->id, 'name' => $scheme->name,
                        'hmo_count' => count($hmosData), 'hmos' => $hmosData,
                        'payable_min' => min($payableValues), 'payable_max' => max($payableValues),
                        'payable_avg' => round(array_sum($payableValues) / count($payableValues), 2),
                        'claims_min' => min($claimsValues), 'claims_max' => max($claimsValues),
                        'claims_avg' => round(array_sum($claimsValues) / count($claimsValues), 2),
                        'manual_count' => collect($hmosData)->where('is_manual', true)->count(),
                        'auto_count' => collect($hmosData)->where('is_manual', false)->count(),
                    ];
                }

                $standaloneHmos = Hmo::where('status', 1)->whereNull('hmo_scheme_id')->get();
                $standaloneData = [];
                foreach ($standaloneHmos as $hmo) {
                    $tariff = $tariffs->get($hmo->id);
                    $standaloneData[] = [
                        'id' => $hmo->id, 'name' => $hmo->name,
                        'payable_amount' => $tariff ? (float) $tariff->payable_amount : 0,
                        'claims_amount' => $tariff ? (float) $tariff->claims_amount : 0,
                        'coverage_mode' => $tariff ? $tariff->coverage_mode : 'primary',
                        'has_tariff' => (bool) $tariff,
                        'is_manual' => $tariff && (float) $tariff->payable_amount > 0,
                    ];
                }

                $data = [
                    'itemName' => $itemName,
                    'itemType' => $type,
                    'itemId' => $id,
                    'salePrice' => $salePrice,
                    'schemeSummary' => $schemeSummary,
                    'standaloneData' => $standaloneData,
                    'totalCount' => Hmo::where('status', 1)->count(),
                    'backUrl' => route('hmo-tariffs.index'),
                ];

                if (request()->ajax()) {
                    return view('admin.partials.hmo-tariff-view-partial', $data);
                }

                return view('admin.tariffs.standalone', [
                    'partial' => 'admin.partials.hmo-tariff-view-partial',
                    'data' => $data
                ]);

            } elseif ($type === 'hmo' || $type === 'scheme') {
                // --- AXIS 2: One HMO/Scheme -> All Products/Services ---
                $targetName = '';
                $hmoIds = [];
                
                if ($type === 'hmo') {
                    $hmo = Hmo::findOrFail($id);
                    $targetName = $hmo->name;
                    $hmoIds = [$id];
                } else {
                    $scheme = HmoScheme::findOrFail($id);
                    $targetName = $scheme->name . ' Scheme';
                    $hmoIds = Hmo::where('hmo_scheme_id', $id)->where('status', 1)->pluck('id')->toArray();
                }

                if (empty($hmoIds)) {
                    return "<div class='alert alert-warning'>No active HMOs found for this selection.</div>";
                }

                // If scheme is selected, we map values to the first HMO in the scheme for display
                $referenceHmoId = $hmoIds[0];

                // Get all active products with prices
                $products = DB::table('products')
                    ->leftJoin('prices', 'products.id', '=', 'prices.product_id')
                    ->leftJoin('product_categories', 'products.category_id', '=', 'product_categories.id')
                    ->where('products.status', 1)
                    ->select('products.id', 'products.product_name as name', 'prices.current_sale_price as price', 'product_categories.category_name as category', 'product_categories.id as category_id')
                    ->orderBy('product_categories.category_name')
                    ->orderBy('products.product_name')
                    ->get();

                // Get all active services with prices
                $services = DB::table('services')
                    ->leftJoin('service_prices', 'services.id', '=', 'service_prices.service_id')
                    ->leftJoin('service_categories', 'services.category_id', '=', 'service_categories.id')
                    ->where('services.status', 1)
                    ->select('services.id', 'services.service_name as name', 'service_prices.sale_price as price', 'service_categories.category_name as category', 'service_categories.id as category_id')
                    ->orderBy('service_categories.category_name')
                    ->orderBy('services.service_name')
                    ->get();

                // Get existing tariffs for this reference HMO
                $tariffs = HmoTariff::where('hmo_id', $referenceHmoId)->get();
                $productTariffs = $tariffs->whereNotNull('product_id')->keyBy('product_id');
                $serviceTariffs = $tariffs->whereNotNull('service_id')->keyBy('service_id');

                $catalogData = [
                    'targetName' => $targetName,
                    'targetType' => $type,
                    'targetId' => $id,
                    'hmoIds' => $hmoIds,
                    'products' => $products,
                    'services' => $services,
                    'productTariffs' => $productTariffs,
                    'serviceTariffs' => $serviceTariffs,
                    'backUrl' => route('hmo-tariffs.index'),
                ];

                if (request()->ajax()) {
                    return view('admin.partials.hmo-catalog-view', $catalogData);
                }

                return view('admin.tariffs.standalone', [
                    'partial' => 'admin.partials.hmo-catalog-view',
                    'data' => $catalogData
                ]);
            }
        } catch (\Exception $e) {
            return "<div class='alert alert-danger'>Error loading view: " . $e->getMessage() . "</div>";
        }
    }

    /**
     * Bulk save tariffs from the dynamic UI
     */
    public function bulkUpdate(Request $request)
    {
        try {
            $rows = $request->input('rows', []);
            $savedCount = 0;

            foreach ($rows as $row) {
                HmoTariff::updateOrCreate(
                    ['hmo_id' => $row['hmo_id'], 'product_id' => $row['product_id'] ?? null, 'service_id' => $row['service_id'] ?? null],
                    ['payable_amount' => $row['payable_amount'] ?? 0, 'claims_amount' => $row['claims_amount'] ?? 0, 'coverage_mode' => $row['coverage_mode'] ?? 'primary']
                );
                $savedCount++;
            }

            return response()->json(['success' => true, 'message' => "Successfully updated $savedCount tariffs."]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get tariffs for DataTables with filters.
     * Optimized for performance: Pagination at DB level, fixed N+1 queries, column selection
     */
    public function getTariffs(Request $request)
    {
        // Build base query with selected columns only
        $query = HmoTariff::select([
            'id', 'hmo_id', 'product_id', 'service_id',
            'claims_amount', 'payable_amount', 'coverage_mode', 'created_at'
        ])->with('hmo'); // Only load HMO relationship

        // Apply filters
        if ($request->filled('hmo_id')) {
            $query->where('hmo_id', $request->hmo_id);
        }

        if ($request->filled('coverage_mode')) {
            $query->where('coverage_mode', $request->coverage_mode);
        }

        if ($request->filled('type')) {
            if ($request->type === 'product') {
                $query->whereNotNull('product_id')->whereNull('service_id');
            } elseif ($request->type === 'service') {
                $query->whereNotNull('service_id')->whereNull('product_id');
            }
        }

        // Use DataTables with server-side pagination (now properly paginated at DB level)
        return DataTables::of($query->orderBy('created_at', 'DESC'))
            ->addIndexColumn()
            ->addColumn('hmo_name', function ($tariff) {
                return $tariff->hmo ? $tariff->hmo->name : 'N/A';
            })
            ->addColumn('item_name', function ($tariff) {
                if ($tariff->product_id) {
                    $product = Product::withoutGlobalScopes()->find($tariff->product_id);
                    return $product ? $product->product_name : 'N/A';
                } elseif ($tariff->service_id) {
                    $service = Service::withoutGlobalScopes()->find($tariff->service_id);
                    return $service ? $service->service_name : 'N/A';
                }
                return 'N/A';
            })
            ->addColumn('item_type', function ($tariff) {
                if ($tariff->product_id) {
                    return '<span class="badge badge-success">Product</span>';
                } elseif ($tariff->service_id) {
                    return '<span class="badge badge-info">Service</span>';
                }
                return '<span class="badge badge-secondary">N/A</span>';
            })
            ->addColumn('original_price', function ($tariff) {
                if ($tariff->product_id) {
                    // Load product price from prices table
                    $price = DB::table('prices')
                        ->where('product_id', $tariff->product_id)
                        ->value('current_sale_price');
                    return $price ? '₦' . number_format($price, 2) : 'N/A';
                } elseif ($tariff->service_id) {
                    // Load service price from service_prices table
                    $price = DB::table('service_prices')
                        ->where('service_id', $tariff->service_id)
                        ->value('sale_price');
                    return $price ? '₦' . number_format($price, 2) : 'N/A';
                }
                return 'N/A';
            })
            ->addColumn('coverage_badge', function ($tariff) {
                $badgeColor = $tariff->coverage_mode === 'express' ? 'success' :
                             ($tariff->coverage_mode === 'primary' ? 'warning' : 'danger');
                return '<span class="badge badge-' . $badgeColor . '">' . strtoupper($tariff->coverage_mode) . '</span>';
            })
            ->addColumn('claims_amount_formatted', function ($tariff) {
                return '₦' . number_format($tariff->claims_amount, 2);
            })
            ->addColumn('payable_amount_formatted', function ($tariff) {
                return '₦' . number_format($tariff->payable_amount, 2);
            })
            ->addColumn('actions', function ($tariff) {
                return '
                    <button type="button" class="btn btn-sm btn-info edit-tariff-btn" data-id="' . $tariff->id . '">
                        <i class="fa fa-edit"></i> Edit
                    </button>
                    <button type="button" class="btn btn-sm btn-danger delete-tariff-btn" data-id="' . $tariff->id . '">
                        <i class="fa fa-trash"></i> Delete
                    </button>
                ';
            })
            ->rawColumns(['item_type', 'coverage_badge', 'actions'])
            ->make(true);
    }

    /**
     * Store a new tariff.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'hmo_id' => 'required|exists:hmos,id',
            'item_type' => 'required|in:product,service',
            'product_id' => 'required_if:item_type,product|nullable|exists:products,id',
            'service_id' => 'required_if:item_type,service|nullable|exists:services,id',
            'claims_amount' => 'required|numeric|min:0',
            'payable_amount' => 'required|numeric|min:0',
            'coverage_mode' => 'required|in:express,primary,secondary',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check for duplicate
        $exists = HmoTariff::where('hmo_id', $request->hmo_id)
            ->where(function ($query) use ($request) {
                if ($request->item_type === 'product') {
                    $query->where('product_id', $request->product_id);
                } else {
                    $query->where('service_id', $request->service_id);
                }
            })
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Tariff already exists for this HMO and item combination.'
            ], 422);
        }

        $tariff = HmoTariff::create([
            'hmo_id' => $request->hmo_id,
            'product_id' => $request->item_type === 'product' ? $request->product_id : null,
            'service_id' => $request->item_type === 'service' ? $request->service_id : null,
            'claims_amount' => $request->claims_amount,
            'payable_amount' => $request->payable_amount,
            'coverage_mode' => $request->coverage_mode,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tariff created successfully.',
            'data' => $tariff
        ]);
    }

    /**
     * Update an existing tariff.
     */
    public function update(Request $request, $id)
    {
        $tariff = HmoTariff::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'claims_amount' => 'required|numeric|min:0',
            'payable_amount' => 'required|numeric|min:0',
            'coverage_mode' => 'required|in:express,primary,secondary',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $tariff->update([
            'claims_amount' => $request->claims_amount,
            'payable_amount' => $request->payable_amount,
            'coverage_mode' => $request->coverage_mode,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tariff updated successfully.',
            'data' => $tariff
        ]);
    }

    /**
     * Delete a tariff.
     */
    public function destroy($id)
    {
        $tariff = HmoTariff::findOrFail($id);
        $tariff->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tariff deleted successfully.'
        ]);
    }

    /**
     * Export tariffs to CSV.
     */
    public function exportCsv(Request $request)
    {
        $query = HmoTariff::with(['hmo', 'product.price', 'service.price']);

        // Apply same filters as DataTables
        if ($request->filled('hmo_id')) {
            $query->where('hmo_id', $request->hmo_id);
        }

        if ($request->filled('coverage_mode')) {
            $query->where('coverage_mode', $request->coverage_mode);
        }

        if ($request->filled('type')) {
            if ($request->type === 'product') {
                $query->whereNotNull('product_id')->whereNull('service_id');
            } elseif ($request->type === 'service') {
                $query->whereNotNull('service_id')->whereNull('product_id');
            }
        }

        $tariffs = $query->orderBy('created_at', 'DESC')->get();

        $filename = 'hmo_tariffs_' . date('Y-m-d_His') . '.csv';
        $handle = fopen('php://output', 'w');

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // CSV headers
        fputcsv($handle, [
            'ID',
            'HMO Name',
            'Item Type',
            'Item Name',
            'Original Price',
            'Claims Amount',
            'Payable Amount',
            'Coverage Mode',
            'Created At',
            'Updated At'
        ]);

        // CSV data
        foreach ($tariffs as $tariff) {
            $itemType = $tariff->product_id ? 'Product' : 'Service';
            $itemName = $tariff->product_id
                ? ($tariff->product ? $tariff->product->product_name : 'N/A')
                : ($tariff->service ? $tariff->service->service_name : 'N/A');

            $originalPrice = 'N/A';
            if ($tariff->product_id && $tariff->product && $tariff->product->price) {
                $originalPrice = $tariff->product->price->current_sale_price;
            } elseif ($tariff->service_id && $tariff->service && $tariff->service->price) {
                $originalPrice = $tariff->service->price->sale_price;
            }

            fputcsv($handle, [
                $tariff->id,
                $tariff->hmo ? $tariff->hmo->name : 'N/A',
                $itemType,
                $itemName,
                $originalPrice,
                $tariff->claims_amount,
                $tariff->payable_amount,
                $tariff->coverage_mode,
                $tariff->created_at ? $tariff->created_at->format('Y-m-d H:i:s') : '',
                $tariff->updated_at ? $tariff->updated_at->format('Y-m-d H:i:s') : '',
            ]);
        }

        fclose($handle);
        exit;
    }

    /**
     * Import tariffs from CSV.
     */
    public function importCsv(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        if ($validator->fails()) {
            Alert::error('Error', 'Please upload a valid CSV file (max 10MB).');
            return redirect()->back();
        }

        $file = $request->file('csv_file');
        $handle = fopen($file->getPathname(), 'r');

        // Skip header row
        fgetcsv($handle);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        while (($data = fgetcsv($handle)) !== false) {
            try {
                // Expected CSV format:
                // ID, HMO Name, Item Type, Item Name, Original Price, Claims Amount, Payable Amount, Coverage Mode, Created At, Updated At

                if (count($data) < 8) {
                    $skipped++;
                    continue;
                }

                $hmoName = trim($data[1]);
                $itemType = strtolower(trim($data[2]));
                $itemName = trim($data[3]);
                $claimsAmount = floatval($data[5]);
                $payableAmount = floatval($data[6]);
                $coverageMode = strtolower(trim($data[7]));

                // Find HMO
                $hmo = Hmo::where('name', $hmoName)->first();
                if (!$hmo) {
                    $errors[] = "HMO '$hmoName' not found";
                    $skipped++;
                    continue;
                }

                // Find product or service
                $productId = null;
                $serviceId = null;

                if ($itemType === 'product') {
                    $product = Product::where('product_name', $itemName)->first();
                    if (!$product) {
                        $errors[] = "Product '$itemName' not found";
                        $skipped++;
                        continue;
                    }
                    $productId = $product->id;
                } elseif ($itemType === 'service') {
                    $service = Service::where('service_name', $itemName)->first();
                    if (!$service) {
                        $errors[] = "Service '$itemName' not found";
                        $skipped++;
                        continue;
                    }
                    $serviceId = $service->id;
                } else {
                    $errors[] = "Invalid item type: '$itemType'";
                    $skipped++;
                    continue;
                }

                // Validate coverage mode
                if (!in_array($coverageMode, ['express', 'primary', 'secondary'])) {
                    $errors[] = "Invalid coverage mode: '$coverageMode'";
                    $skipped++;
                    continue;
                }

                // Check if tariff exists
                $tariff = HmoTariff::where('hmo_id', $hmo->id)
                    ->where('product_id', $productId)
                    ->where('service_id', $serviceId)
                    ->first();

                if ($tariff) {
                    // Update existing
                    $tariff->update([
                        'claims_amount' => $claimsAmount,
                        'payable_amount' => $payableAmount,
                        'coverage_mode' => $coverageMode,
                    ]);
                    $updated++;
                } else {
                    // Create new
                    HmoTariff::create([
                        'hmo_id' => $hmo->id,
                        'product_id' => $productId,
                        'service_id' => $serviceId,
                        'claims_amount' => $claimsAmount,
                        'payable_amount' => $payableAmount,
                        'coverage_mode' => $coverageMode,
                    ]);
                    $created++;
                }
            } catch (\Exception $e) {
                $errors[] = "Error processing row: " . $e->getMessage();
                $skipped++;
            }
        }

        fclose($handle);

        $message = "Import complete: $created created, $updated updated, $skipped skipped.";
        if (!empty($errors)) {
            $message .= " Errors: " . implode(', ', array_slice($errors, 0, 5));
        }

        Alert::success('Import Complete', $message);
        return redirect()->back();
    }

    /**
     * Get a single tariff for editing.
     */
    public function show($id)
    {
        $tariff = HmoTariff::with(['hmo', 'product', 'service'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $tariff
        ]);
    }

    /**
     * Get HMOs belonging to a scheme (AJAX).
     */
    public function getSchemeHmos($schemeId)
    {
        $hmos = Hmo::where('hmo_scheme_id', $schemeId)
            ->where('status', 1)
            ->orderBy('name', 'ASC')
            ->get(['id', 'name']);

        return response()->json(['success' => true, 'data' => $hmos]);
    }

    /**
     * Export tariffs to Excel with scheme/HMO/type/category filters.
     */
    public function exportExcel(Request $request)
    {
        $scope      = $request->input('scope', 'hmo'); // hmo | scheme
        $hmoId      = $request->input('hmo_id');
        $schemeId   = $request->input('scheme_id');
        $type       = $request->input('type', '');       // product | service | ''
        $coverage   = $request->input('coverage_mode', '');
        $prodCatId  = $request->input('product_category_id', '');
        $svcCatId   = $request->input('service_category_id', '');
        $layout     = $request->input('layout', 'standard');

        if ($layout !== 'standard') {
            return $this->exportConsolidated($request);
        }

        // Determine which HMO(s) to export
        if ($scope === 'scheme' && $schemeId) {
            $hmoIds = Hmo::where('hmo_scheme_id', $schemeId)->where('status', 1)->pluck('id');
            $referenceHmo = Hmo::where('hmo_scheme_id', $schemeId)->where('status', 1)->orderBy('id')->first();
            $scheme = HmoScheme::find($schemeId);
            $scopeLabel = $scheme ? $scheme->name : 'Scheme';
        } elseif ($hmoId) {
            $hmoIds = collect([$hmoId]);
            $referenceHmo = Hmo::find($hmoId);
            $scopeLabel = $referenceHmo ? $referenceHmo->name : 'HMO';
        } else {
            $hmoIds = Hmo::where('status', 1)->pluck('id');
            $referenceHmo = Hmo::where('status', 1)->orderBy('id')->first();
            $scopeLabel = 'All HMOs';
        }

        if (!$referenceHmo) {
            return response()->json(['success' => false, 'message' => 'No active HMOs found for the selected ' . ($scope === 'scheme' ? 'scheme. Please assign HMOs to this scheme first.' : 'filter.')], 404);
        }

        // For scheme export, use only the first/reference HMO's tariffs (one row per item)
        $query = HmoTariff::with(['product.price', 'product.category', 'service.price', 'service.category'])
            ->where('hmo_id', $referenceHmo->id);

        if ($type === 'product') {
            $query->whereNotNull('product_id')->whereNull('service_id');
        } elseif ($type === 'service') {
            $query->whereNotNull('service_id')->whereNull('product_id');
        }

        if ($coverage) {
            $query->where('coverage_mode', $coverage);
        }

        if ($prodCatId && $type !== 'service') {
            $query->whereHas('product', fn($q) => $q->where('category_id', $prodCatId));
        }

        if ($svcCatId && $type !== 'product') {
            $query->whereHas('service', fn($q) => $q->where('category_id', $svcCatId));
        }

        $tariffs = $query->get();

        // Build Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Tariffs');

        // Metadata row
        $metaText = "Scope: {$scopeLabel} | Reference HMO: {$referenceHmo->name} | HMOs in scope: {$hmoIds->count()} | Exported: " . now()->format('Y-m-d H:i');
        $sheet->setCellValue('A1', $metaText);
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setItalic(true)->setSize(10);
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F5E9');

        // Headers
        $headers = ['HMO Provider', 'Item Code', 'Item Name', 'Item Type', 'Category', 'Current Price', 'Claims Amount', 'Payable Amount', 'Coverage Mode'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '2', $header);
            $sheet->getStyle($col . '2')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
            $sheet->getStyle($col . '2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1565C0');
            $sheet->getStyle($col . '2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }

        // Determine actual tariffs to export based on scope
        if ($scope === 'all') {
            // Export EVERY tariff record in the system across all HMOs, but respecting filters
            $fullQuery = HmoTariff::with(['hmo', 'product.price', 'product.category', 'service.price', 'service.category']);
            
            if ($type === 'product') $fullQuery->whereNotNull('product_id')->whereNull('service_id');
            elseif ($type === 'service') $fullQuery->whereNotNull('service_id')->whereNull('product_id');

            if ($prodCatId && $type !== 'service') $fullQuery->whereHas('product', fn($q) => $q->where('category_id', $prodCatId));
            if ($svcCatId && $type !== 'product') $fullQuery->whereHas('service', fn($q) => $q->where('category_id', $svcCatId));
            if ($coverage) $fullQuery->where('coverage_mode', $coverage);

            $tariffs = $fullQuery->get();
        } else {
            // For hmo/scheme scope, we use the already filtered $query
            $tariffs = $query->get();
        }

        // Data rows
        $row = 3;
        foreach ($tariffs as $tariff) {
            $hmoName = $tariff->hmo ? $tariff->hmo->name : 'Unknown';
            
            if ($tariff->product_id && $tariff->product) {
                $code = $tariff->product->product_code ?? '';
                $name = $tariff->product->product_name;
                $itemType = 'Product';
                $category = $tariff->product->category ? $tariff->product->category->category_name : '';
                $currentPrice = optional(optional($tariff->product)->price)->current_sale_price ?? 0;
            } elseif ($tariff->service_id && $tariff->service) {
                $code = $tariff->service->service_code ?? '';
                $name = $tariff->service->service_name;
                $itemType = 'Service';
                $category = $tariff->service->category ? $tariff->service->category->category_name : '';
                $currentPrice = optional(optional($tariff->service)->price)->sale_price ?? 0;
            } else {
                continue;
            }

            $sheet->setCellValue("A{$row}", $hmoName);
            $sheet->setCellValue("B{$row}", $code);
            $sheet->setCellValue("C{$row}", $name);
            $sheet->setCellValue("D{$row}", $itemType);
            $sheet->setCellValue("E{$row}", $category);
            $sheet->setCellValue("F{$row}", (float) $currentPrice);
            $sheet->setCellValue("G{$row}", (float) $tariff->claims_amount);
            $sheet->setCellValue("H{$row}", (float) $tariff->payable_amount);
            $sheet->setCellValue("I{$row}", $tariff->coverage_mode);

            // Number format for price columns
            $sheet->getStyle("F{$row}:H{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

            // Alternate row color
            if ($row % 2 === 1) {
                $sheet->getStyle("A{$row}:I{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5F5F5');
            }
            $row++;
        }

        // Auto-filter
        $sheet->setAutoFilter("A2:I2");

        // Stream download
        $filename = 'tariffs_' . str_replace(' ', '_', $scopeLabel) . '_' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Preview import — returns JSON with parsed rows + diff against current tariffs.
     */
    public function importPreview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file'      => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
            'scope'     => 'required|in:hmo,scheme,all',
            'hmo_id'    => 'required_if:scope,hmo|nullable|exists:hmos,id',
            'scheme_id' => 'required_if:scope,scheme|nullable|exists:hmo_schemes,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $file  = $request->file('file');
        $scope = $request->input('scope');

        // Determine target HMO(s)
        if ($scope === 'scheme') {
            $hmos = Hmo::where('hmo_scheme_id', $request->scheme_id)->where('status', 1)->get();
            $targetLabel = HmoScheme::find($request->scheme_id)->name ?? 'Scheme';
        } else {
            $hmos = Hmo::where('id', $request->hmo_id)->get();
            $targetLabel = $hmos->first()->name ?? 'HMO';
        }

        $hmoIds = $hmos->pluck('id');

        // Parse Excel/CSV
        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // Find header row (detect both layouts)
        $headerRowIndex = -1;
        foreach ($rows as $i => $row) {
            $c0 = strtolower(trim($row[0] ?? ''));
            $c1 = strtolower(trim($row[1] ?? ''));
            if ($c0 === 'item code' || $c1 === 'item code' || $c0 === 'hmo provider') {
                $headerRowIndex = $i;
                break;
            }
        }

        if ($headerRowIndex === -1) {
            return response()->json(['success' => false, 'message' => 'Invalid file format: Could not find header row (Item Code).'], 422);
        }

        $dataRows = array_slice($rows, $headerRowIndex + 1);
        $preview = [];
        $totalUpdates = 0;
        $totalNew = 0;
        $totalSkipped = 0;

        $headers = $rows[$headerRowIndex];
        $colOffset = (strtolower(trim($headers[0] ?? '')) === 'hmo provider') ? 1 : 0;

        foreach ($dataRows as $idx => $row) {
            if (empty(trim($row[$colOffset + 1] ?? ''))) continue; // skip empty name rows

            $code      = trim($row[$colOffset + 0] ?? '');
            $name      = trim($row[$colOffset + 1] ?? '');
            $itemType  = strtolower(trim($row[$colOffset + 2] ?? ''));
            $rowHmoName = ($colOffset === 1) ? trim($row[0] ?? '') : '';

            // Find item
            $item = null;
            if ($itemType === 'product' || $itemType === 'pharmacy') {
                $item = $code ? Product::where('product_code', $code)->first() : null;
                if (!$item) $item = Product::where('product_name', $name)->first();
            } else {
                $item = $code ? Service::where('service_code', $code)->first() : null;
                if (!$item) $item = Service::where('service_name', $name)->first();
            }

            // For preview, we use the first triplet we find if consolidated, or the standard cols
            if ($colOffset === 1) {
                // Standard 9-column
                $claims    = floatval($row[6] ?? 0);
                $payable   = floatval($row[7] ?? 0);
                $mode      = strtolower(trim($row[8] ?? 'primary'));
            } else {
                // Consolidated (find first triplet)
                $claims = 0; $payable = 0; $mode = 'primary';
                for ($i = 5; $i < count($row); $i++) {
                    if (strpos($headers[$i] ?? '', ' Claims') !== false) {
                        $claims = floatval($row[$i] ?? 0);
                        $payable = floatval($row[$i+1] ?? 0);
                        $mode = strtolower(trim($row[$i+2] ?? 'primary'));
                        break;
                    }
                }
            }

            if ($colOffset === 1) {
                // Standard 9-column format
                $rowHmoName = trim($row[0] ?? '');
                $targetHmo = Hmo::where('name', $rowHmoName)->first();
                $targetHmoId = $targetHmo ? $targetHmo->id : null;
                
                if (!$item || ($request->scope === 'all' && !$targetHmoId)) {
                    $preview[] = [
                        'name' => $name, 'code' => $code, 'type' => $itemType,
                        'new_claims' => $claims, 'new_payable' => $payable, 'new_mode' => $mode,
                        'status' => 'skipped', 'reason' => (!$item ? 'Item not found' : 'HMO not found'),
                    ];
                    $totalSkipped++;
                    continue;
                }

                $existing = HmoTariff::where('hmo_id', $targetHmoId)
                    ->where(function ($q) use ($item, $itemType) {
                        if ($itemType === 'product' || $itemType === 'pharmacy') $q->where('product_id', $item->id)->whereNull('service_id');
                        else $q->where('service_id', $item->id)->whereNull('product_id');
                    })->first();

                $status = $existing ? 'update' : 'new';
                if ($existing) $totalUpdates++; else $totalNew++;

                $preview[] = [
                    'name' => $name, 'code' => $code, 'type' => $itemType,
                    'new_claims' => $claims, 'new_payable' => $payable, 'new_mode' => $mode,
                    'status' => $status, 'reason' => null,
                ];
            } else {
                // Consolidated format (Iterate through ALL triplets for summary)
                if (!$item) {
                    $preview[] = [
                        'name' => $name, 'code' => $code, 'type' => $itemType,
                        'new_claims' => 0, 'new_payable' => 0, 'new_mode' => '',
                        'status' => 'skipped', 'reason' => 'Item not found',
                    ];
                    $totalSkipped++;
                    continue;
                }

                $rowHasUpdate = false;
                $firstTriplet = null;

                for ($i = 5; $i < count($row); $i += 3) {
                    if (empty($headers[$i]) || strpos($headers[$i], ' Claims') === false) continue;
                    
                    $claims  = floatval($row[$i] ?? 0);
                    $payable = floatval($row[$i+1] ?? 0);
                    $mode    = strtolower(trim($row[$i+2] ?? 'primary'));

                    if ($claims == 0 && $payable == 0) continue; // No data

                    $entityName = str_replace(' Claims', '', $headers[$i]);
                    $targetHmoIds = [];
                    $hmo = Hmo::where('name', $entityName)->first();
                    if ($hmo) $targetHmoIds = [$hmo->id];
                    else {
                        $scheme = HmoScheme::where('name', $entityName)->first();
                        if ($scheme) $targetHmoIds = $scheme->hmos()->where('status', 1)->pluck('id')->toArray();
                    }

                    if (!$firstTriplet) $firstTriplet = ['claims' => $claims, 'payable' => $payable, 'mode' => $mode];

                    foreach ($targetHmoIds as $hmoId) {
                        $existing = HmoTariff::where('hmo_id', $hmoId)
                            ->where(function ($q) use ($item, $itemType) {
                                if ($itemType === 'product' || $itemType === 'pharmacy') $q->where('product_id', $item->id)->whereNull('service_id');
                                else $q->where('service_id', $item->id)->whereNull('product_id');
                            })->first();

                        if ($existing) $totalUpdates++; else $totalNew++;
                        $rowHasUpdate = true;
                    }
                }

                if ($rowHasUpdate && $firstTriplet) {
                    $preview[] = [
                        'name' => $name, 'code' => $code, 'type' => $itemType,
                        'new_claims' => $firstTriplet['claims'], 'new_payable' => $firstTriplet['payable'], 'new_mode' => $firstTriplet['mode'],
                        'status' => 'multi-update', 'reason' => 'Multiple entities affected',
                    ];
                } else if (!$rowHasUpdate) {
                    $totalSkipped++;
                }
            }

            if (count($preview) >= 100) break; // Limit preview for performance
        }

        return response()->json([
            'success' => true,
            'summary' => [
                'updates' => $totalUpdates,
                'new' => $totalNew,
                'skipped' => $totalSkipped,
            ],
            'preview' => $preview,
        ]);
    }

    /**
     * Import tariffs from Excel — applies to single HMO or all HMOs in a scheme.
     */
    public function importExcel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file'      => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
            'scope'     => 'required|in:hmo,scheme,all',
            'hmo_id'    => 'required_if:scope,hmo|nullable|exists:hmos,id',
            'scheme_id' => 'required_if:scope,scheme|nullable|exists:hmo_schemes,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $scope  = $request->input('scope');

        // Determine target HMO(s)
        if ($scope === 'scheme') {
            $hmoIds = Hmo::where('hmo_scheme_id', $request->scheme_id)->where('status', 1)->pluck('id');
        } else {
            $hmoIds = collect([(int) $request->hmo_id]);
        }

        if ($hmoIds->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No active HMOs found for selected scope.'], 422);
        }

        // Parse file
        $spreadsheet = IOFactory::load($request->file('file')->getPathname());
        $rows = $spreadsheet->getActiveSheet()->toArray();

        // Find header row (detect both layouts)
        $headerRowIndex = -1;
        foreach ($rows as $i => $row) {
            $c0 = strtolower(trim($row[0] ?? ''));
            $c1 = strtolower(trim($row[1] ?? ''));
            if ($c0 === 'item code' || $c1 === 'item code' || $c0 === 'hmo provider') {
                $headerRowIndex = $i;
                break;
            }
        }

        if ($headerRowIndex === -1) {
            return response()->json(['success' => false, 'message' => 'Invalid file format: Could not find header row.'], 422);
        }

        // Identify columns and layout
        $headers = $rows[$headerRowIndex];
        $isConsolidated = false;
        $entityGroups = [];
        $colOffset = (strtolower(trim($headers[0] ?? '')) === 'hmo provider') ? 1 : 0;

        foreach ($headers as $i => $h) {
            $h = trim($h ?? '');
            if (strpos($h, ' Claims') !== false) {
                $isConsolidated = true;
                $entityName = trim(str_replace(' Claims', '', $h));
                $entityGroups[] = [
                    'name' => $entityName,
                    'claimsIdx' => $i,
                    'payableIdx' => $i + 1,
                    'modeIdx' => $i + 2
                ];
            }
        }

        $dataRows = array_slice($rows, $headerRowIndex + 1);
        $created = 0; $updated = 0; $skipped = 0; $errors = [];

        DB::beginTransaction();
        try {
            foreach ($dataRows as $idx => $row) {
                if (empty(trim($row[$colOffset + 1] ?? ''))) continue;

                $code     = trim($row[$colOffset + 0] ?? '');
                $name     = trim($row[$colOffset + 1] ?? '');
                $itemType = strtolower(trim($row[$colOffset + 2] ?? ''));

                // Find Item
                $productId = null; $serviceId = null;
                if ($itemType === 'product' || $itemType === 'pharmacy') {
                    $item = $code ? Product::where('product_code', $code)->first() : null;
                    if (!$item) $item = Product::where('product_name', $name)->first();
                    if (!$item) { $skipped++; continue; }
                    $productId = $item->id;
                } else {
                    $item = $code ? Service::where('service_code', $code)->first() : null;
                    if (!$item) $item = Service::where('service_name', $name)->first();
                    if (!$item) { $skipped++; continue; }
                    $serviceId = $item->id;
                }

                if ($isConsolidated) {
                    // Process multiple entity columns
                    foreach ($entityGroups as $group) {
                        $claims  = floatval($row[$group['claimsIdx']] ?? 0);
                        $payable = floatval($row[$group['payableIdx']] ?? 0);
                        $mode    = strtolower(trim($row[$group['modeIdx']] ?? 'primary'));
                        
                        if ($claims == 0 && $payable == 0) continue; // Skip if no data in this triplet

                        // Find target HMOs for this entity (Case-insensitive & trimmed)
                        $targetHmoIds = [];
                        $eName = trim($group['name']);
                        $hmo = Hmo::where('name', 'LIKE', $eName)->first();
                        if ($hmo) {
                            $targetHmoIds = [$hmo->id];
                        } else {
                            $scheme = HmoScheme::where('name', 'LIKE', $eName)->first();
                            if ($scheme) $targetHmoIds = $scheme->hmos()->where('status', 1)->pluck('id')->toArray();
                        }

                        foreach ($targetHmoIds as $hmoId) {
                            $res = $this->upsertTariffRow($hmoId, $productId, $serviceId, $payable, $claims, $mode);
                            if ($res === 'updated') $updated++; else $created++;
                        }
                    }
                } else {
                    // Standard 9-column format
                    $rowHmoName = trim($row[0] ?? '');
                    $claims     = floatval($row[6] ?? 0);
                    $payable    = floatval($row[7] ?? 0);
                    $mode       = strtolower(trim($row[8] ?? 'primary'));

                    $targetHmoIds = [];
                    if ($request->hmo_id) {
                        $targetHmoIds = [$request->hmo_id];
                    } else {
                        $hmo = Hmo::where('name', $rowHmoName)->first();
                        if ($hmo) $targetHmoIds = [$hmo->id];
                    }

                    foreach ($targetHmoIds as $hmoId) {
                        $res = $this->upsertTariffRow($hmoId, $productId, $serviceId, $payable, $claims, $mode);
                        if ($res === 'updated') $updated++; else $created++;
                    }
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Import failed: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Import complete: {$created} created, {$updated} updated.",
        ]);
    }

    /**
     * Quick Normalize — apply drug split / service coverage to all HMOs in a scheme.
     */
    public function normalizeScheme(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scheme_id'        => 'required|exists:hmo_schemes,id',
            'drug_patient_pct' => 'required|numeric|min:0|max:100',
            'service_claims_pct' => 'required|numeric|min:0|max:100',
            'general_consult_express' => 'nullable|boolean',
            'other_consult_secondary' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $schemeId          = $request->scheme_id;
        $drugPatientPct    = $request->drug_patient_pct / 100;
        $drugClaimsPct     = 1 - $drugPatientPct;
        $serviceClaimsPct  = $request->service_claims_pct / 100;
        $servicePatientPct = 1 - $serviceClaimsPct;
        $generalExpress    = (bool) $request->general_consult_express;
        $otherSecondary    = (bool) $request->other_consult_secondary;

        $hmoIds = Hmo::where('hmo_scheme_id', $schemeId)->where('status', 1)->pluck('id');
        if ($hmoIds->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No active HMOs in this scheme.'], 422);
        }

        $products = Product::with('price')->where('status', 1)->get();
        $services = Service::with('price', 'category')->where('status', 1)->get();

        // Identify general consultation services
        $generalConsultIds = $services->filter(function ($svc) {
            return preg_match('/^general\s+consultation$/i', trim($svc->service_name));
        })->pluck('id')->toArray();

        $otherConsultIds = $services->filter(function ($svc) use ($generalConsultIds) {
            return stripos($svc->service_name, 'consultation') !== false
                && !in_array($svc->id, $generalConsultIds);
        })->pluck('id')->toArray();

        $created = 0;
        $updated = 0;

        DB::beginTransaction();
        try {
            foreach ($hmoIds as $hmoId) {
                // Products (drugs)
                foreach ($products as $product) {
                    $price   = $product->price ? (float) $product->price->current_sale_price : 0;
                    $payable = round($price * $drugPatientPct, 2);
                    $claims  = round($price * $drugClaimsPct, 2);

                    $result = $this->upsertTariffRow($hmoId, $product->id, null, $payable, $claims, 'primary');
                    $result === 'created' ? $created++ : $updated++;
                }

                // Services
                foreach ($services as $service) {
                    $price   = $service->price ? (float) $service->price->sale_price : 0;
                    $payable = round($price * $servicePatientPct, 2);
                    $claims  = round($price * $serviceClaimsPct, 2);

                    // Determine coverage mode
                    if ($generalExpress && in_array($service->id, $generalConsultIds)) {
                        $mode = 'express';
                        $payable = 0;
                        $claims = $price;
                    } elseif ($otherSecondary && in_array($service->id, $otherConsultIds)) {
                        $mode = 'secondary';
                    } else {
                        $mode = 'primary';
                    }

                    $result = $this->upsertTariffRow($hmoId, null, $service->id, $payable, $claims, $mode);
                    $result === 'created' ? $created++ : $updated++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Normalization failed: ' . $e->getMessage()], 500);
        }

        $scheme = HmoScheme::find($schemeId);
        return response()->json([
            'success' => true,
            'message' => "Normalized {$scheme->name}: {$created} created, {$updated} updated across {$hmoIds->count()} HMO(s).",
        ]);
    }

    /**
     * Helper: upsert a single tariff row (bypasses model boot validation).
     */
    private function upsertTariffRow(int $hmoId, ?int $productId, ?int $serviceId, float $payable, float $claims, string $mode): string
    {
        $existing = HmoTariff::where('hmo_id', $hmoId)
            ->where(function ($q) use ($productId) {
                $productId ? $q->where('product_id', $productId) : $q->whereNull('product_id');
            })
            ->where(function ($q) use ($serviceId) {
                $serviceId ? $q->where('service_id', $serviceId) : $q->whereNull('service_id');
            })
            ->first();

        if ($existing) {
            $existing->update([
                'payable_amount' => $payable,
                'claims_amount'  => $claims,
                'coverage_mode'  => $mode,
            ]);
            return 'updated';
        }

        HmoTariff::create([
            'hmo_id'         => $hmoId,
            'product_id'     => $productId,
            'service_id'     => $serviceId,
            'payable_amount' => $payable,
            'claims_amount'  => $claims,
            'coverage_mode'  => $mode,
        ]);
        return 'created';
    }
    /**
     * Export Consolidated — One row per item, entities as columns.
     */
    private function exportConsolidated(Request $request)
    {
        $layout    = $request->input('layout'); // consolidated_hmo | consolidated_scheme
        $type      = $request->input('type', '');
        $prodCatId = $request->input('product_category_id', '');
        $svcCatId  = $request->input('service_category_id', '');

        // 1. Determine Entities (Columns)
        if ($layout === 'consolidated_scheme') {
            $entities = HmoScheme::whereHas('hmos', fn($q) => $q->where('status', 1))
                ->orderBy('name')
                ->get();
            $entityType = 'Scheme';
        } else {
            $entities = Hmo::where('status', 1)->with('scheme')->orderBy('name')->get();
            $entityType = 'HMO';
        }

        // 2. Determine Items (Rows)
        $products = [];
        $services = [];

        if ($type !== 'service') {
            $pQuery = Product::with('price', 'category')->where('status', 1);
            if ($prodCatId) $pQuery->where('category_id', $prodCatId);
            $products = $pQuery->orderBy('product_name')->get();
        }

        if ($type !== 'product') {
            $sQuery = Service::with('price', 'category')->where('status', 1);
            if ($svcCatId) $sQuery->where('category_id', $svcCatId);
            $services = $sQuery->orderBy('service_name')->get();
        }

        // 3. Build Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Consolidated Tariffs');

        // Headers
        $baseHeaders = ['Item Code', 'Item Name', 'Item Type', 'Category', 'Base Price'];
        $col = 'A';
        foreach ($baseHeaders as $h) {
            $sheet->setCellValue($col . '2', $h);
            $sheet->getStyle($col . '2')->getFont()->setBold(true);
            $col++;
        }

        // Entity Dynamic Headers
        foreach ($entities as $entity) {
            $startCol = $col;
            $name = $entity->name;
            $sheet->setCellValue($col . '2', "{$name} Claims");
            $col++;
            $sheet->setCellValue($col . '2', "{$name} Payable");
            $col++;
            $sheet->setCellValue($col . '2', "{$name} Mode");
            
            // Style entity group
            $endCol = $col;
            $sheet->getStyle("{$startCol}2:{$endCol}2")->getFont()->setBold(true);
            $sheet->getStyle("{$startCol}2:{$endCol}2")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E3F2FD');
            $col++;
        }

        // 4. Data Rows
        $rowIdx = 3;
        $items = collect($products)->concat($services);

        // Pre-fetch all tariffs for efficiency
        $allTariffs = HmoTariff::all()->groupBy(fn($t) => 
            ($t->product_id ? 'p'.$t->product_id : 's'.$t->service_id) . '_' . $t->hmo_id
        );

        foreach ($items as $item) {
            $isProd = $item instanceof Product;
            $itemId = $isProd ? 'p'.$item->id : 's'.$item->id;
            
            $sheet->setCellValue("A{$rowIdx}", $isProd ? ($item->product_code ?? '') : ($item->service_code ?? ''));
            $sheet->setCellValue("B{$rowIdx}", $isProd ? $item->product_name : $item->service_name);
            $sheet->setCellValue("C{$rowIdx}", $isProd ? 'Product' : 'Service');
            $sheet->setCellValue("D{$rowIdx}", $item->category ? $item->category->category_name : '');
            $sheet->setCellValue("E{$rowIdx}", (float)($isProd ? optional($item->price)->current_sale_price : optional($item->price)->sale_price));

            $col = 'F';
            foreach ($entities as $entity) {
                if ($layout === 'consolidated_scheme') {
                    // For scheme, we use the first HMO in the scheme as the representative
                    $refHmo = $entity->hmos()->where('status', 1)->first();
                    $tariff = $refHmo ? ($allTariffs[$itemId . '_' . $refHmo->id][0] ?? null) : null;
                } else {
                    $tariff = $allTariffs[$itemId . '_' . $entity->id][0] ?? null;
                }

                if ($tariff) {
                    $sheet->setCellValue($col . $rowIdx, (float)$tariff->claims_amount);
                    $col++;
                    $sheet->setCellValue($col . $rowIdx, (float)$tariff->payable_amount);
                    $col++;
                    $sheet->setCellValue($col . $rowIdx, $tariff->coverage_mode);
                    $col++;
                } else {
                    $col++; $col++; $col++; // skip 3 columns
                }
            }
            $rowIdx++;
        }

        $filename = 'consolidated_tariffs_' . date('Y-m-d') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
