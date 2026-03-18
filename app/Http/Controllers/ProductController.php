<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductPackaging;
use App\Models\Sale;
use App\Models\ApplicationStatu;
use App\Models\Stock;
use App\Models\ProductCategory;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function listProducts(Request $request)
    {
        $query = Product::where('status', '=', 1)
            ->with(['stock', 'category', 'price', 'packagings', 'stockBatches' => function($q) {
                $q->active()->where('current_qty', '>', 0);
            }])
            ->orderBy('product_name', 'ASC');

        // Type filter
        if ($request->filled('product_type') && $request->product_type !== 'all') {
            $query->where('product_type', $request->product_type);
        }

        // Category filter
        if ($request->filled('category_id') && $request->category_id !== 'all') {
            $query->where('category_id', $request->category_id);
        }

        $pc = $query->get();

        return Datatables::of($pc)
            ->addIndexColumn()
            ->addColumn('product_info', function ($pc) {
                $typeIcons = ['drug' => 'mdi-pill', 'consumable' => 'mdi-bandage', 'utility' => 'mdi-broom'];
                $typeColors = ['drug' => '#28a745', 'consumable' => '#ffc107', 'utility' => '#17a2b8'];
                $type = $pc->product_type ?? 'drug';
                $icon = $typeIcons[$type] ?? 'mdi-pill';
                $color = $typeColors[$type] ?? '#28a745';
                $catName = optional($pc->category)->category_name ?? 'N/A';

                return '<div class="d-flex align-items-center">'
                    . '<i class="mdi ' . e($icon) . ' me-2 mr-2" style="font-size:1.4rem; color:' . $color . '"></i>'
                    . '<div>'
                    . '<strong>' . e($pc->product_name) . '</strong>'
                    . '<br><small class="text-muted">' . e($pc->product_code) . '</small>'
                    . ' <span class="badge badge-light">' . e($catName) . '</span>'
                    . '</div></div>';
            })
            ->addColumn('type_badge', function ($pc) {
                $type = $pc->product_type ?? 'drug';
                $badges = [
                    'drug' => '<span class="badge" style="background:#d4edda;color:#155724">Drug</span>',
                    'consumable' => '<span class="badge" style="background:#fff3cd;color:#856404">Consumable</span>',
                    'utility' => '<span class="badge" style="background:#d1ecf1;color:#0c5460">Utility</span>',
                ];
                return $badges[$type] ?? $badges['drug'];
            })
            ->editColumn('current_quantity', function ($pc) {
                $batchTotal = $pc->stockBatches->sum('current_qty');
                $oldTotal = optional($pc->stock)->current_quantity ?? 0;
                $qty = $batchTotal > 0 ? $batchTotal : $oldTotal;
                $reorderLevel = $pc->reorder_alert ?? 10;

                $formatted = $pc->formatQty($qty);
                $alert = '';
                if ($qty <= 0) {
                    $alert = '<span class="badge badge-danger">' . e($formatted) . '</span>';
                } elseif ($qty <= $reorderLevel) {
                    $alert = '<span class="badge badge-warning">' . e($formatted) . '</span> <small class="text-danger"><i class="mdi mdi-alert"></i> Low</small>';
                } else {
                    $alert = '<span class="badge badge-success">' . e($formatted) . '</span>';
                }
                return $alert;
            })
            ->addColumn('sale_price', function ($pc) {
                $price = optional($pc->price)->current_sale_price ?? optional($pc->price)->initial_sale_price;
                return $price ? '₦' . number_format($price, 2) : '<span class="text-muted">—</span>';
            })
            ->addColumn('actions', function ($pc) {
                $canManage = Auth::user()->hasPermissionTo('can-manage-products') || Auth::user()->hasRole(['ADMIN', 'STORE']);
                if (!$canManage) {
                    return '<button disabled class="btn btn-sm btn-secondary"><i class="mdi mdi-eye"></i></button>';
                }

                $showUrl = route('products.show', $pc->id);
                $editUrl = route('products.edit', $pc->id);
                $batchUrl = route('inventory.store-workbench.manual-batch-form') . '?product_id=' . $pc->id;
                $stockUrl = route('inventory.store-workbench.stock-overview') . '?product_id=' . $pc->id;
                $batchesUrl = route('inventory.store-workbench.product-batches', $pc->id);
                $priceUrl = route('prices.edit', $pc->id);

                return '<div class="btn-group">'
                    . '<a href="' . $showUrl . '" class="btn btn-sm btn-outline-primary" title="View"><i class="mdi mdi-eye"></i></a>'
                    . '<a href="' . $editUrl . '" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="mdi mdi-pencil"></i></a>'
                    . '<div class="btn-group">'
                    . '<button type="button" class="btn btn-sm btn-outline-info dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="mdi mdi-dots-vertical"></i></button>'
                    . '<div class="dropdown-menu dropdown-menu-right">'
                    . '<a class="dropdown-item" href="' . $batchUrl . '"><i class="mdi mdi-plus-box mr-1"></i> Add Batch</a>'
                    . '<a class="dropdown-item" href="' . $priceUrl . '"><i class="mdi mdi-currency-ngn mr-1"></i> Adjust Price</a>'
                    . '<a class="dropdown-item" href="' . $stockUrl . '"><i class="mdi mdi-warehouse mr-1"></i> Stock Overview</a>'
                    . '<a class="dropdown-item" href="' . $batchesUrl . '"><i class="mdi mdi-history mr-1"></i> View Batches</a>'
                    . '</div></div></div>';
            })
            ->rawColumns(['product_info', 'type_badge', 'current_quantity', 'sale_price', 'actions'])
            ->make(true);
    }

    public function liveSearchProducts(Request $request)
    {
        $request->validate([
            'term' => 'nullable|string',
            'patient_id' => 'nullable|integer',
            'type' => 'nullable|in:drug,consumable,utility',
        ]);

        $query = Product::query()->where('status', 1);

        // Optional type filter
        if ($request->filled('type')) {
            $query->where('product_type', $request->type);
        }

        if ($request->filled('term')) {
            $query->where(function ($q) use ($request) {
                $q->where('product_name', 'LIKE', "%{$request->term}%")
                    ->orWhere('product_code', 'LIKE', "%{$request->term}%");
            });
        }

        $pc = $query
            ->with(['stock', 'category', 'price', 'packagings'])
            ->orderBy('product_name', 'ASC')
            ->get()
            ->map(function ($product) use ($request) {
                $basePrice = optional($product->price)->initial_sale_price;
                $coverage = null;

                if ($request->filled('patient_id')) {
                    try {
                        $coverage = \App\Helpers\HmoHelper::applyHmoTariff($request->patient_id, $product->id, null);
                    } catch (\Exception $e) {
                        $coverage = null;
                    }
                }

                return [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'product_code' => $product->product_code,
                    'product_type' => $product->product_type ?? 'drug',
                    'base_unit_name' => $product->base_unit_name ?? 'Piece',
                    'allow_decimal_qty' => (bool) $product->allow_decimal_qty,
                    'coverage_mode' => $coverage['coverage_mode'] ?? 'cash',
                    'payable_amount' => $coverage['payable_amount'] ?? ($basePrice ?? 0),
                    'claims_amount' => $coverage['claims_amount'] ?? 0,
                    'validation_status' => $coverage['validation_status'] ?? null,
                    'category' => $product->category,
                    'stock' => $product->stock,
                    'price' => $product->price,
                    'packagings' => $product->packagings->map(fn($p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'level' => $p->level,
                        'base_unit_qty' => (float) $p->base_unit_qty,
                        'is_default_purchase' => (bool) $p->is_default_purchase,
                        'is_default_dispense' => (bool) $p->is_default_dispense,
                    ]),
                ];
            });

        return response()->json($pc);
    }

    public function listSalesProduct(Request $request, $id)
    {

        $pc = Sale::where('product_id', '=', $id)->with('product_or_service_request', 'product', 'store')->orderBy('id', 'DESC')->get();

        return Datatables::of($pc)
            ->addIndexColumn()
            ->addColumn('view', function ($pc) {
                // return '<a href="' . route('transactions.show', $pc->transaction->id) . '" class="btn btn-dark btn-sm"><i class="fa fa-eye"></i> SIV</a>';
                return 'todo';
            })
            ->editColumn('product', function ($pc) {
                return ($pc->product->product_name);
            })
            ->editColumn('store', function ($pc) {
                return ($pc->store->store_name);
            })
            ->editColumn('trans', function ($pc) {
                return ($pc->product_or_service_request->invoice->id);
            })
            ->editColumn('customer', function ($pc) {
                // return ($pc->transaction->customer_name);
                return 'todo';
            })
            ->editColumn('budgetYear', function ($pc) {
                // $budgetYear = getBudgetYearName($pc->budget_year_id);

                return 'todo';
            })

            ->rawColumns(['view', 'product', 'store', 'trans', 'customer', 'budgetYear'])

            ->make(true);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $categories = ProductCategory::where('status', '=', 1)->pluck('category_name', 'id')->all();
        return view('admin.product.index', compact('categories'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $application = ApplicationStatu::whereId(1)->first();
        $category = ProductCategory::where('status', '=', 1)->pluck('category_name', 'id')->all();
        return view('admin.product.create', compact('category', 'application'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function store(Request $request)
    {
        $application = ApplicationStatu::whereId(1)->first();

        $rules = [
            'category_id'       => 'required',
            'product_name'      => 'required',
            'product_code'      => 'required',
            'reorder_alert'     => 'required',
            'product_type'      => 'required|in:drug,consumable,utility',
            'base_unit_name'    => 'required|string|max:50',
            'packagings'        => 'nullable|array',
            'packagings.*.name' => 'required_with:packagings|string|max:100',
            'packagings.*.units_in_parent' => 'required_with:packagings|numeric|min:0.0001',
        ];

        if ($application->allow_piece_sale == 1) {
            if ($request->s1 == null) {
                $rules += ['s1' => 'required'];
            }
        }

        if ($application->allow_halve_sale == 1) {
            if ($request->s2 == null) {
                $rules += ['s2' => 'required'];
            }
        }

        if ($application->allow_piece_sale == 1 || $application->allow_halve_sale) {
            if ($request->quantity_in == null) {
                $rules += ['quantity_in' => 'required'];
            }
        }

        try {
            $v = validator()->make($request->all(), $rules);

            if ($v->fails()) {
                return redirect()->back()->withInput()->with('errors', $v->messages()->all())->withInput();
            }

            DB::beginTransaction();

            $myproduct                      = new Product();
            $myproduct->user_id             = Auth::user()->id;
            $myproduct->category_id         = $request->category_id;
            $myproduct->product_name        = trim($request->product_name);
            $myproduct->product_code        = $request->product_code;
            $myproduct->reorder_alert       = $request->reorder_alert;
            $myproduct->product_type        = $request->product_type;
            $myproduct->base_unit_name      = $request->base_unit_name;
            $myproduct->allow_decimal_qty   = $request->has('allow_decimal_qty') ? 1 : 0;

            if ($application->allow_halve_sale == 1) {
                $myproduct->has_have        = $request->s1;
                $myproduct->has_piece       = $request->s2;
                $myproduct->howmany_to      = $request->quantity_in;
            } else {
                $myproduct->has_have        = 0;
                $myproduct->has_piece       = 0;
                $myproduct->howmany_to      = 0;
            }

            $myproduct->status             = 1;
            $myproduct->current_quantity    = 0;
            $myproduct->save();

            // Save packaging levels
            $this->syncPackagings($myproduct, $request->input('packagings', []));

            // Create legacy stock record
            $stock                     = new Stock();
            $stock->product_id         = $myproduct->id;
            $stock->initial_quantity   = 0;
            $stock->order_quantity     = 0;
            $stock->current_quantity   = 0;
            $stock->quantity_sale      = 0;
            $stock->save();

            DB::commit();

            $msg = 'The Product ' . $request->product_name . ' was Saved Successfully.';
            return redirect(route('products.index'))->withMessage($msg)->withMessageType('success');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withMessage("An error occurred: " . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $pp = Product::with(['category', 'price', 'stock', 'packagings' => function($q) {
            $q->orderBy('level');
        }, 'stockBatches' => function($q) {
            $q->active()->where('current_qty', '>', 0);
        }])->findOrFail($id);

        $pc = Sale::where('product_id', '=', $id)->sum('total_amount');
        $qt = Sale::where('product_id', '=', $id)->sum('quantity_buy');

        $batchTotal = $pp->stockBatches->sum('current_qty');
        $oldTotal = optional($pp->stock)->current_quantity ?? 0;
        $totalQty = $batchTotal > 0 ? $batchTotal : $oldTotal;

        return view('admin.product.product', compact('id', 'pp', 'pc', 'qt', 'totalQty'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        try {
            $application = ApplicationStatu::whereId(1)->first();
            $product = Product::with(['packagings' => function($q) {
                $q->orderBy('level');
            }])->findOrFail($id);
            $category = ProductCategory::where('status', '=', 1)->pluck('category_name', 'id')->all();
            return view('admin.product.edit', compact('product', 'application', 'category'));
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try {
            $application = ApplicationStatu::whereId(1)->first();

            $rules = [
                'category_id'       => 'required',
                'product_name'      => 'required',
                'product_code'      => 'required',
                'reorder_alert'     => 'required',
                'product_type'      => 'required|in:drug,consumable,utility',
                'base_unit_name'    => 'required|string|max:50',
                'packagings'        => 'nullable|array',
                'packagings.*.name' => 'required_with:packagings|string|max:100',
                'packagings.*.units_in_parent' => 'required_with:packagings|numeric|min:0.0001',
            ];

            if ($application->allow_piece_sale == 1) {
                if ($request->s1 == null) {
                    $rules += ['s1' => 'required'];
                }
            }

            if ($application->allow_halve_sale == 1) {
                if ($request->s2 == null) {
                    $rules += ['s2' => 'required'];
                }
            }

            if ($application->allow_piece_sale == 1 || $application->allow_halve_sale) {
                if ($request->quantity_in == null) {
                    $rules += ['quantity_in' => 'required'];
                }
            }

            $v = validator()->make($request->all(), $rules);

            if ($v->fails()) {
                return redirect()->back()->withInput()->with('errors', $v->messages()->all())->withInput();
            }

            DB::beginTransaction();

            $myproduct                 = Product::whereId($id)->first();
            $myproduct->user_id        = Auth::user()->id;
            $myproduct->category_id    = $request->category_id;
            $myproduct->product_name   = $request->product_name;
            $myproduct->product_code   = $request->product_code;
            $myproduct->reorder_alert  = $request->reorder_alert;
            $myproduct->product_type   = $request->product_type;
            $myproduct->base_unit_name = $request->base_unit_name;
            $myproduct->allow_decimal_qty = $request->has('allow_decimal_qty') ? 1 : 0;

            if ($request->s1) {
                $myproduct->has_have = $request->s1;
            }
            if ($request->s2) {
                $myproduct->has_piece = $request->s2;
            }
            if ($request->s1 || $request->s2) {
                $myproduct->howmany_to = $request->quantity_in;
            }
            $myproduct->status = 1;
            $myproduct->update();

            // Sync packaging levels
            $this->syncPackagings($myproduct, $request->input('packagings', []));

            DB::commit();

            $msg = 'The Product ' . $request->product_name . ' Was Updated Successfully.';
            return redirect(route('products.index'))->withMessage($msg)->withMessageType('success');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withMessage("An error occurred: " . $e->getMessage());
        }
    }

    /**
     * Sync packaging levels for a product.
     * Deletes removed levels, updates existing, creates new ones.
     * Auto-computes base_unit_qty for each level.
     */
    private function syncPackagings(Product $product, array $packagings): void
    {
        // Collect IDs to keep
        $keepIds = [];

        $previousBaseQty = 1; // level 0 = 1 base unit
        $previousPackagingId = null;

        foreach ($packagings as $index => $pkgData) {
            if (empty($pkgData['name']) || empty($pkgData['units_in_parent'])) {
                continue;
            }

            $level = $index + 1;
            $unitsInParent = (float) $pkgData['units_in_parent'];
            $baseUnitQty = $unitsInParent * $previousBaseQty;

            $data = [
                'product_id' => $product->id,
                'name' => trim($pkgData['name']),
                'description' => $pkgData['description'] ?? null,
                'level' => $level,
                'parent_packaging_id' => $previousPackagingId,
                'units_in_parent' => $unitsInParent,
                'base_unit_qty' => $baseUnitQty,
                'is_default_purchase' => !empty($pkgData['is_default_purchase']) ? 1 : 0,
                'is_default_dispense' => !empty($pkgData['is_default_dispense']) ? 1 : 0,
                'barcode' => $pkgData['barcode'] ?? null,
            ];

            if (!empty($pkgData['id'])) {
                // Update existing
                $pkg = ProductPackaging::find($pkgData['id']);
                if ($pkg && $pkg->product_id === $product->id) {
                    $pkg->update($data);
                    $keepIds[] = $pkg->id;
                    $previousPackagingId = $pkg->id;
                }
            } else {
                // Create new
                $pkg = ProductPackaging::create($data);
                $keepIds[] = $pkg->id;
                $previousPackagingId = $pkg->id;
            }

            $previousBaseQty = $baseUnitQty;
        }

        // Delete removed packagings
        $product->packagings()->whereNotIn('id', $keepIds)->delete();
    }

    /**
     * API: Get packagings for a product (used by AJAX in PO, billing, etc.)
     */
    public function getPackagings($productId)
    {
        $product = Product::with(['packagings' => function($q) {
            $q->orderBy('level');
        }])->findOrFail($productId);

        return response()->json([
            'base_unit_name' => $product->base_unit_name ?? 'Piece',
            'allow_decimal_qty' => (bool) $product->allow_decimal_qty,
            'packagings' => $product->packagings->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'level' => $p->level,
                'units_in_parent' => (float) $p->units_in_parent,
                'base_unit_qty' => (float) $p->base_unit_qty,
                'is_default_purchase' => (bool) $p->is_default_purchase,
                'is_default_dispense' => (bool) $p->is_default_dispense,
            ]),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
