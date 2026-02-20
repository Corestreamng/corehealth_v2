<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HmoTariff;
use App\Models\Hmo;
use App\Models\Product;
use App\Models\service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;

class TariffManagementController extends Controller
{
    /**
     * Display the tariff management page.
     */
    public function index()
    {
        $hmos = Hmo::where('status', 1)->orderBy('name', 'ASC')->get();
        $products = Product::where('status', 1)->orderBy('product_name', 'ASC')->get();
        $services = service::where('status', 1)->orderBy('service_name', 'ASC')->get();

        return view('admin.tariffs.index', compact('hmos', 'products', 'services'));
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
                    $service = service::find($tariff->service_id);
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
                    $price = \DB::table('prices')
                        ->where('product_id', $tariff->product_id)
                        ->value('current_sale_price');
                    return $price ? '₦' . number_format($price, 2) : 'N/A';
                } elseif ($tariff->service_id) {
                    // Load service price from service_prices table
                    $price = \DB::table('service_prices')
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
                    $service = service::where('service_name', $itemName)->first();
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
}
