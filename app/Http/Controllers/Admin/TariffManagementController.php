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
        $hmos = Hmo::where('status', 1)->orderBy('name', 'ASC')->get();
        $products = Product::where('status', 1)->orderBy('product_name', 'ASC')->get();
        $services = Service::where('status', 1)->orderBy('service_name', 'ASC')->get();
        $schemes = HmoScheme::where('status', 1)->orderBy('name', 'ASC')->get();
        $productCategories = ProductCategory::orderBy('category_name', 'ASC')->get();
        $serviceCategories = ServiceCategory::orderBy('category_name', 'ASC')->get();

        return view('admin.tariffs.index', compact('hmos', 'products', 'services', 'schemes', 'productCategories', 'serviceCategories'));
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
                    $product = Product::find($tariff->product_id);
                    return $product ? $product->product_name : 'N/A';
                } elseif ($tariff->service_id) {
                    $service = Service::find($tariff->service_id);
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
        $headers = ['Item Code', 'Item Name', 'Item Type', 'Category', 'Current Price', 'Claims Amount', 'Payable Amount', 'Coverage Mode'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '2', $header);
            $sheet->getStyle($col . '2')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
            $sheet->getStyle($col . '2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1565C0');
            $sheet->getStyle($col . '2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }

        // Data rows
        $row = 3;
        foreach ($tariffs as $tariff) {
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

            $sheet->setCellValue("A{$row}", $code);
            $sheet->setCellValue("B{$row}", $name);
            $sheet->setCellValue("C{$row}", $itemType);
            $sheet->setCellValue("D{$row}", $category);
            $sheet->setCellValue("E{$row}", (float) $currentPrice);
            $sheet->setCellValue("F{$row}", (float) $tariff->claims_amount);
            $sheet->setCellValue("G{$row}", (float) $tariff->payable_amount);
            $sheet->setCellValue("H{$row}", $tariff->coverage_mode);

            // Number format for price columns
            $sheet->getStyle("E{$row}:G{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

            // Alternate row color
            if ($row % 2 === 1) {
                $sheet->getStyle("A{$row}:H{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5F5F5');
            }
            $row++;
        }

        // Auto-filter
        $sheet->setAutoFilter("A2:H2");

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
            'scope'     => 'required|in:hmo,scheme',
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

        // Find header row (skip metadata)
        $headerRowIndex = 0;
        foreach ($rows as $i => $row) {
            $firstCell = strtolower(trim($row[0] ?? ''));
            if ($firstCell === 'item code') {
                $headerRowIndex = $i;
                break;
            }
        }

        $dataRows = array_slice($rows, $headerRowIndex + 1);
        $preview = [];
        $totalUpdates = 0;
        $totalNew = 0;
        $totalSkipped = 0;

        foreach ($dataRows as $row) {
            if (empty(trim($row[1] ?? ''))) continue; // skip empty name rows

            $code     = trim($row[0] ?? '');
            $name     = trim($row[1] ?? '');
            $itemType = strtolower(trim($row[2] ?? ''));
            $claims   = floatval($row[5] ?? 0);
            $payable  = floatval($row[6] ?? 0);
            $mode     = strtolower(trim($row[7] ?? 'primary'));

            // Find item
            $item = null;
            if ($itemType === 'product') {
                $item = $code ? Product::where('product_code', $code)->first() : null;
                if (!$item) $item = Product::where('product_name', $name)->first();
            } else {
                $item = $code ? Service::where('service_code', $code)->first() : null;
                if (!$item) $item = Service::where('service_name', $name)->first();
            }

            if (!$item) {
                $preview[] = [
                    'name' => $name, 'code' => $code, 'type' => $itemType,
                    'new_claims' => $claims, 'new_payable' => $payable, 'new_mode' => $mode,
                    'old_claims' => null, 'old_payable' => null, 'old_mode' => null,
                    'status' => 'skipped', 'reason' => 'Item not found',
                ];
                $totalSkipped++;
                continue;
            }

            // Check existing tariff (use first target HMO as reference)
            $existingQuery = HmoTariff::where('hmo_id', $hmoIds->first());
            if ($itemType === 'product') {
                $existingQuery->where('product_id', $item->id)->whereNull('service_id');
            } else {
                $existingQuery->where('service_id', $item->id)->whereNull('product_id');
            }
            $existing = $existingQuery->first();

            $status = $existing ? 'update' : 'new';
            if ($existing) $totalUpdates++;
            else $totalNew++;

            $preview[] = [
                'name' => $name, 'code' => $code, 'type' => $itemType,
                'new_claims' => $claims, 'new_payable' => $payable, 'new_mode' => $mode,
                'old_claims' => $existing ? (float) $existing->claims_amount : null,
                'old_payable' => $existing ? (float) $existing->payable_amount : null,
                'old_mode' => $existing ? $existing->coverage_mode : null,
                'status' => $status,
            ];
        }

        return response()->json([
            'success' => true,
            'target' => $targetLabel,
            'hmo_count' => $hmos->count(),
            'hmo_names' => $hmos->pluck('name'),
            'summary' => ['updates' => $totalUpdates, 'new' => $totalNew, 'skipped' => $totalSkipped],
            'preview' => array_slice($preview, 0, 100), // limit preview to 100 rows
            'total_rows' => count($preview),
        ]);
    }

    /**
     * Import tariffs from Excel — applies to single HMO or all HMOs in a scheme.
     */
    public function importExcel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file'      => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
            'scope'     => 'required|in:hmo,scheme',
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

        // Find header row
        $headerRowIndex = 0;
        foreach ($rows as $i => $row) {
            if (strtolower(trim($row[0] ?? '')) === 'item code') {
                $headerRowIndex = $i;
                break;
            }
        }

        $dataRows = array_slice($rows, $headerRowIndex + 1);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors  = [];

        DB::beginTransaction();
        try {
            foreach ($dataRows as $idx => $row) {
                if (empty(trim($row[1] ?? ''))) continue;

                $code     = trim($row[0] ?? '');
                $name     = trim($row[1] ?? '');
                $itemType = strtolower(trim($row[2] ?? ''));
                $claims   = floatval($row[5] ?? 0);
                $payable  = floatval($row[6] ?? 0);
                $mode     = strtolower(trim($row[7] ?? 'primary'));

                if (!in_array($mode, ['express', 'primary', 'secondary'])) {
                    $errors[] = "Row " . ($idx + 1) . ": Invalid coverage mode '{$mode}'";
                    $skipped++;
                    continue;
                }

                // Find item by code first, then name
                $productId = null;
                $serviceId = null;

                if ($itemType === 'product') {
                    $item = $code ? Product::where('product_code', $code)->first() : null;
                    if (!$item) $item = Product::where('product_name', $name)->first();
                    if (!$item) { $skipped++; $errors[] = "Row " . ($idx + 1) . ": Product '{$name}' not found"; continue; }
                    $productId = $item->id;
                } else {
                    $item = $code ? Service::where('service_code', $code)->first() : null;
                    if (!$item) $item = Service::where('service_name', $name)->first();
                    if (!$item) { $skipped++; $errors[] = "Row " . ($idx + 1) . ": Service '{$name}' not found"; continue; }
                    $serviceId = $item->id;
                }

                // Apply to all target HMOs
                foreach ($hmoIds as $hmoId) {
                    $existing = HmoTariff::where('hmo_id', $hmoId)
                        ->where(function ($q) use ($productId, $serviceId) {
                            if ($productId) {
                                $q->where('product_id', $productId)->whereNull('service_id');
                            } else {
                                $q->where('service_id', $serviceId)->whereNull('product_id');
                            }
                        })->first();

                    if ($existing) {
                        $existing->update([
                            'claims_amount'  => $claims,
                            'payable_amount' => $payable,
                            'coverage_mode'  => $mode,
                        ]);
                        $updated++;
                    } else {
                        HmoTariff::create([
                            'hmo_id'         => $hmoId,
                            'product_id'     => $productId,
                            'service_id'     => $serviceId,
                            'claims_amount'  => $claims,
                            'payable_amount' => $payable,
                            'coverage_mode'  => $mode,
                        ]);
                        $created++;
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
            'message' => "Import complete: {$created} created, {$updated} updated, {$skipped} skipped.",
            'errors' => array_slice($errors, 0, 10),
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
}
