<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;

use App\Models\Sale;
use App\Models\ApplicationStatu;
use App\Models\Stock;
use App\Models\ServiceCategory;
use App\Models\ProcedureDefinition;
use App\Models\ProcedureCategory;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    public function listServices(Request $request)
    {
        $showDeactivated = $request->has('show_deactivated') && $request->show_deactivated == 'true';
        
        $query = Service::withoutGlobalScopes();
        
        if (!$showDeactivated) {
            $query->where('status', 1);
        }

        $query->with(['category' => function ($q) {
            $q->select(['id', 'category_name']);
        }, 'price']);

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category_id', $request->category);
        }

        return Datatables::of($query->orderBy('service_name', 'ASC'))
            ->addIndexColumn()
            ->addColumn('service_info', function ($pc) {
                $catName = optional($pc->category)->category_name ?? 'N/A';
                $catColors = [
                    2 => ['#d4edda', '#155724'],   // Lab
                    6 => ['#d1ecf1', '#0c5460'],   // Imaging
                    9 => ['#f8d7da', '#721c24'],   // Morgue
                ];
                $catBg = $catColors[$pc->category_id][0] ?? '#e9ecef';
                $catFg = $catColors[$pc->category_id][1] ?? '#495057';

                $templateBadge = '';
                if (in_array($pc->category_id, [2, 6])) {
                    $templateBadge = !empty($pc->result_template_v2)
                        ? ' <span class="badge badge-success" style="font-size:0.65rem"><i class="mdi mdi-check"></i> Template</span>'
                        : ' <span class="badge badge-light text-muted" style="font-size:0.65rem"><i class="mdi mdi-alert-outline"></i> No Template</span>';
                }

                $comboBadge = $pc->is_combo ? ' <span class="badge badge-info" style="font-size:0.65rem"><i class="mdi mdi-package-variant"></i> Combo</span>' : '';

                return '<div>'
                    . '<strong>' . e($pc->service_name) . '</strong>'
                    . '<br><small class="text-muted">' . e($pc->service_code) . '</small>'
                    . ' <span class="badge" style="background:' . $catBg . ';color:' . $catFg . ';font-size:0.7rem">' . e($catName) . '</span>'
                    . $templateBadge . $comboBadge
                    . '</div>';
            })
            ->addColumn('price_info', function ($pc) {
                $price = optional($pc->price)->sale_price;
                $statusBadge = '';
                if ($pc->status == 0) {
                    $statusBadge = '<br><span class="badge badge-danger">Deactivated</span>';
                }
                return ($price ? '₦' . number_format($price, 2) : '<span class="text-muted">—</span>') . $statusBadge;
            })
            ->addColumn('actions', function ($pc) {
                $canManage = Auth::user()->hasPermissionTo('can-manage-products') || Auth::user()->hasRole(['ADMIN', 'STORE']);
                if (!$canManage) {
                    return '<button disabled class="btn btn-sm btn-secondary"><i class="mdi mdi-eye"></i></button>';
                }
                $viewUrl = route('services.show', $pc->id);
                $editUrl = route('services.edit', $pc->id);
                $priceUrl = route('service-prices.edit', $pc->id);
                $tariffUrl = route('service-tariffs.view', $pc->id);

                $templateBtn = '';
                if (in_array($pc->category_id, [2, 6])) {
                    $tmplUrl = route('services.build-template', $pc->id);
                    $hasTemplate = !empty($pc->result_template_v2);
                    $tmplIcon = $hasTemplate ? 'mdi-file-document-edit' : 'mdi-file-plus';
                    $tmplTitle = $hasTemplate ? 'Edit Template' : 'Build Template';
                    $templateBtn = '<a href="' . $tmplUrl . '" class="dropdown-item"><i class="mdi ' . $tmplIcon . ' mr-1"></i> ' . $tmplTitle . '</a>';
                }

                $toggleStatusUrl = route('services.toggle-status', $pc->id);
                $toggleText = $pc->status == 1 ? 'Deactivate' : 'Activate';
                $toggleIcon = $pc->status == 1 ? 'mdi-close-circle' : 'mdi-check-circle';
                $toggleColor = $pc->status == 1 ? 'text-danger' : 'text-success';

                return '<div class="btn-group">'
                    . '<a href="' . $viewUrl . '" class="btn btn-sm btn-outline-primary" title="View"><i class="mdi mdi-eye"></i></a>'
                    . '<a href="' . $editUrl . '" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="mdi mdi-pencil"></i></a>'
                    . '<div class="btn-group">'
                    . '<button type="button" class="btn btn-sm btn-outline-info dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="mdi mdi-dots-vertical"></i></button>'
                    . '<div class="dropdown-menu dropdown-menu-right">'
                    . '<a href="' . $priceUrl . '" class="dropdown-item"><i class="mdi mdi-currency-ngn mr-1"></i> Adjust Price</a>'
                    . '<a href="' . $tariffUrl . '" class="dropdown-item"><i class="mdi mdi-shield-check mr-1"></i> HMO Tariffs</a>'
                    . $templateBtn
                    . '<div class="dropdown-divider"></div>'
                    . '<a href="javascript:void(0);" class="dropdown-item ' . $toggleColor . '" onclick="toggleServiceStatus(' . $pc->id . ', \'' . $toggleText . '\', \'' . $pc->service_name . '\')"><i class="mdi ' . $toggleIcon . ' mr-1"></i> ' . $toggleText . '</a>'
                    . '</div></div></div>';
            })
            ->rawColumns(['service_info', 'price_info', 'actions'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $procedureCategoryId = appsettings('procedure_category_id');
        $isProcedure = $request->category_id == $procedureCategoryId;

        $rules = [
            'category_id'          => 'required',
            'service_name'          => 'required',
            'service_code'          => 'required',
        ];

        if ($isProcedure) {
            $rules['procedure_category_id'] = 'required|exists:procedure_categories,id';
        }

        if ($request->is_combo) {
            $rules['bundle_items'] = 'required|array|min:1';
        }

        try {
            $v = validator()->make($request->all(), $rules);
            if ($v->fails()) {
                return redirect()->back()->withInput()->with('errors', $v->messages()->all());
            }

            DB::beginTransaction();
            $myservice                      = new Service();
            $myservice->user_id             = Auth::user()->id;
            $myservice->category_id         = $request->category_id;
            $myservice->service_name        = trim($request->service_name);
            $myservice->service_code        = $request->service_code;
            $myservice->is_combo            = $request->has('is_combo');
            $myservice->status              = 1;
            $myservice->save();

            if ($isProcedure) {
                ProcedureDefinition::create([
                    'service_id' => $myservice->id,
                    'procedure_category_id' => $request->procedure_category_id,
                    'name' => trim($request->service_name),
                    'code' => $request->procedure_code ?? $request->service_code,
                    'description' => $request->procedure_description,
                    'is_surgical' => $request->has('is_surgical'),
                    'estimated_duration_minutes' => $request->estimated_duration_minutes,
                    'status' => 1,
                ]);
            }

            if ($myservice->is_combo && $request->has('bundle_items')) {
                foreach ($request->bundle_items as $item) {
                    $myservice->bundleItems()->create([
                        'item_id'   => $item['item_id'],
                        'item_type' => $item['item_type'],
                        'qty'       => $item['qty'] ?? 1,
                        'note'      => $item['note'] ?? null,
                        'dose'      => $item['dose'] ?? null,
                    ]);
                }
            }

            DB::commit();
            return redirect(route('services.index', ['category' => $myservice->category_id]))->withMessage('Saved successfully')->withMessageType('success');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $procedureCategoryId = appsettings('procedure_category_id');
            $isProcedure = $request->category_id == $procedureCategoryId;

            $rules = [
                'category_id'          => 'required',
                'service_name'          => 'required',
                'service_code'          => 'required',
            ];

            if ($isProcedure) {
                $rules['procedure_category_id'] = 'required|exists:procedure_categories,id';
            }

            if ($request->is_combo) {
                $rules['bundle_items'] = 'required|array|min:1';
            }

            $v = validator()->make($request->all(), $rules);
            if ($v->fails()) {
                return redirect()->back()->withInput()->with('errors', $v->messages()->all());
            }

            DB::beginTransaction();
            $myservice                 = Service::withoutGlobalScopes()->whereId($id)->first();
            $oldCategoryId             = $myservice->category_id;
            $myservice->category_id    = $request->category_id;
            $myservice->service_name   = $request->service_name;
            $myservice->service_code   = $request->service_code;
            $myservice->is_combo       = $request->has('is_combo');
            $myservice->update();

            if ($isProcedure) {
                ProcedureDefinition::updateOrCreate(
                    ['service_id' => $myservice->id],
                    [
                        'procedure_category_id' => $request->procedure_category_id,
                        'name' => trim($request->service_name),
                        'code' => $request->procedure_code ?? $request->service_code,
                        'description' => $request->procedure_description,
                        'is_surgical' => $request->has('is_surgical'),
                        'estimated_duration_minutes' => $request->estimated_duration_minutes,
                        'status' => 1,
                    ]
                );
            } else {
                if ($oldCategoryId == $procedureCategoryId) {
                    ProcedureDefinition::where('service_id', $myservice->id)->delete();
                }
            }

            if ($myservice->is_combo && $request->has('bundle_items')) {
                $myservice->bundleItems()->delete();
                foreach ($request->bundle_items as $item) {
                    $myservice->bundleItems()->create([
                        'item_id'   => $item['item_id'],
                        'item_type' => $item['item_type'],
                        'qty'       => $item['qty'] ?? 1,
                        'note'      => $item['note'] ?? null,
                        'dose'      => $item['dose'] ?? null,
                    ]);
                }
            } else {
                $myservice->bundleItems()->delete();
            }

            DB::commit();
            return redirect(route('services.index', ['category' => $myservice->category_id]))->withMessage('Updated successfully')->withMessageType('success');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage());
        }
    }

    public function index(Request $request)
    {
        $filterCategory = $request->input('category');
        $categories = ServiceCategory::where('status', 1)->orderBy('category_name')->pluck('category_name', 'id')->all();
        $categoryName = $filterCategory ? ($categories[$filterCategory] ?? null) : null;
        return view('admin.service.index', compact('filterCategory', 'categories', 'categoryName'));
    }

    public function create(Request $request)
    {
        $selectedCategory = $request->query('category');
        $category = ServiceCategory::where('status', '=', 1)->pluck('category_name', 'id')->all();
        $procedureCategories = ProcedureCategory::where('status', 1)->orderBy('name')->get();
        $procedureCategoryId = appsettings('procedure_category_id');
        return view('admin.service.create', compact('category', 'procedureCategories', 'procedureCategoryId', 'selectedCategory'));
    }

    public function edit(Request $request, $id)
    {
        $product = Service::withoutGlobalScopes()->with('bundleItems.service', 'bundleItems.product', 'procedureDefinition')->whereId($id)->first();
        $category = ServiceCategory::where('status', '=', 1)->pluck('category_name', 'id')->all();
        $procedureCategories = ProcedureCategory::where('status', 1)->orderBy('name')->get();
        $procedureCategoryId = appsettings('procedure_category_id');
        $procedure = $product->procedureDefinition;
        $selectedCategory = $product->category_id;
        return view('admin.service.edit', compact('product', 'category', 'procedureCategories', 'procedureCategoryId', 'procedure', 'selectedCategory'));
    }

    public function show($id)
    {
        $pp = Service::withoutGlobalScopes()->with(['bundleItems.service', 'bundleItems.product'])->findOrFail($id);
        $pc = \App\Models\Sale::where('service_id', $id)->sum('total_amount');
        $qt = \App\Models\Sale::where('service_id', $id)->sum('quantity_buy');
        return view('admin.service.product', compact('id', 'pp', 'pc', 'qt'));
    }

    public function toggleStatus($id)
    {
        $service = Service::withoutGlobalScopes()->with(['bundleItems.service', 'bundleItems.product'])->findOrFail($id);
        $service->status = $service->status == 1 ? 0 : 1;
        $service->save();
        return response()->json(['success' => true, 'new_status' => $service->status]);
    }

    public function liveSearchServices(Request $request)
    {
        $term = $request->term;
        $categoryId = $request->input('category_id');
        $patientId = $request->input('patient_id');
        $context = $request->input('context', 'all'); // 'lab', 'imaging', 'product', 'all'

        // PART 1: Direct service/combo matches
        $query = Service::with(['price', 'category', 'bundleItems.service', 'bundleItems.product'])
            ->where('status', 1);

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }
        if ($term) {
            $query->where('service_name', 'LIKE', "%{$term}%");
        }

        $directServices = $query->limit(20)->get();

        // PART 2: Combos that contain matching services/products (or match by name)
        $relatedCombos = collect();
        if ($term) {
            $combosQuery = Service::with(['price', 'category', 'bundleItems.service', 'bundleItems.product'])
                ->where('is_combo', true)
                ->where('status', 1)
                ->where(function ($q) use ($term) {
                    // Match by combo name OR by bundle item names
                    $q->where('service_name', 'LIKE', "%{$term}%")
                      ->orWhereHas('bundleItems', function ($bq) use ($term) {
                          $bq->where(function ($subQ) use ($term) {
                              $subQ->where('item_type', 'service')
                                  ->whereHas('service', function ($sQ) use ($term) {
                                      $sQ->where('service_name', 'LIKE', "%{$term}%");
                                  });
                          })->orWhere(function ($subQ) use ($term) {
                              $subQ->where('item_type', 'product')
                                  ->whereHas('product', function ($pQ) use ($term) {
                                      $pQ->where('product_name', 'LIKE', "%{$term}%");
                                  });
                          });
                      });
                });

            // When searching within a specific category (e.g. imaging), only show combos
            // that contain at least one service item belonging to that category
            if ($categoryId) {
                $combosQuery->whereHas('bundleItems', function ($q) use ($categoryId) {
                    $q->where('item_type', 'service')
                      ->whereHas('service', function ($sQ) use ($categoryId) {
                          $sQ->where('category_id', $categoryId);
                      });
                });
            }

            // Exclude combos already returned as direct results
            $directIds = $directServices->pluck('id')->toArray();
            if (!empty($directIds)) {
                $combosQuery->whereNotIn('id', $directIds);
            }

            $relatedCombos = $combosQuery->limit(10)->get();
        }

        // PART 3: Batch-load HMO tariffs for all results (direct + combos)
        $hmoMap = [];
        if ($patientId) {
            try {
                $patient = \App\Models\Patient::find($patientId);
                if ($patient && $patient->hmo_id) {
                    $allServiceIds = $directServices->pluck('id')
                        ->merge($relatedCombos->pluck('id'))
                        ->unique()
                        ->toArray();

                    $tariffs = \App\Models\HmoTariff::where('hmo_id', $patient->hmo_id)
                        ->whereIn('service_id', $allServiceIds)
                        ->whereNull('product_id')
                        ->get();

                    foreach ($tariffs as $tariff) {
                        $hmoMap[$tariff->service_id] = [
                            'payable_amount' => $tariff->payable_amount,
                            'claims_amount'  => $tariff->claims_amount,
                            'coverage_mode'  => $tariff->coverage_mode,
                        ];
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('HMO tariff batch load failed: ' . $e->getMessage());
            }
        }

        // PART 4: Format response - direct services first
        $results = [];

        foreach ($directServices as $service) {
            $results[] = $this->formatServiceSearchResult($service, $hmoMap, false);
        }

        // PART 5: Add related combos as separate group
        foreach ($relatedCombos as $combo) {
            $results[] = $this->formatServiceSearchResult($combo, $hmoMap, true);
        }

        return response()->json($results);
    }

    /**
     * Format a single service or combo for search results.
     * Includes full pricing (base, payable, claims) and combo detection.
     */
    protected function formatServiceSearchResult($service, $hmoMap, $isCombo)
    {
        $basePrice = $service->price ? $service->price->sale_price : 0;
        $hmoData = $hmoMap[$service->id] ?? null;

        $result = [
            'id'               => $service->id,
            'service_name'     => $service->service_name,
            'service_code'     => $service->service_code ?? '',
            'category'         => $service->category ? $service->category->category_name : 'N/A',
            'price'            => $service->price,
            'base_price'       => $basePrice,
            'payable_amount'   => $hmoData['payable_amount'] ?? $basePrice,
            'claims_amount'    => $hmoData['claims_amount'] ?? 0,
            'coverage_mode'    => $hmoData['coverage_mode'] ?? null,
            'is_combo'         => $isCombo || $service->is_combo,
        ];

        // If this is a combo, include bundle item summary
        if ($result['is_combo'] && $service->bundleItems && $service->bundleItems->count() > 0) {
            $result['bundle_items'] = $service->bundleItems->map(function ($item) {
                return [
                    'id'        => $item->id,
                    'type'      => $item->item_type, // 'service' | 'product'
                    'item_id'   => $item->item_id,
                    'name'      => $item->item_type === 'service'
                        ? ($item->service->service_name ?? 'Unknown')
                        : ($item->product->product_name ?? 'Unknown'),
                    'qty'       => $item->qty,
                    'dose'      => $item->dose,
                    'note'      => $item->note,
                ];
            })->toArray();
        }

        return $result;
    }

    public function buildTemplate($id)
    {
        $service = Service::with('category')->findOrFail($id);
        return view('admin.service.template-builder', ['service' => $service, 'template' => $service->result_template_v2]);
    }

    public function saveTemplate(Request $request, $id)
    {
        $service = Service::findOrFail($id);
        $service->result_template_v2 = $request->template;
        $service->save();
        return response()->json(['success' => true]);
    }

    public function listSalesService(Request $request, $id)
    {
        $pc = \App\Models\Sale::where('service_id', '=', $id)
            ->with(['product_or_service_request.invoice', 'service', 'store'])
            ->orderBy('id', 'DESC')->get();

        return \Yajra\DataTables\DataTables::of($pc)
            ->addIndexColumn()
            ->addColumn('view', function ($pc) {
                return 'todo';
            })
            ->editColumn('product', function ($pc) {
                return $pc->service->service_name ?? 'N/A';
            })
            ->editColumn('store', function ($pc) {
                return $pc->store->store_name ?? 'N/A';
            })
            ->editColumn('trans', function ($pc) {
                return $pc->product_or_service_request->invoice->id ?? 'N/A';
            })
            ->editColumn('customer', function ($pc) {
                return $pc->product_or_service_request->user->surname ?? 'N/A';
            })
            ->editColumn('budgetYear', function ($pc) {
                return 'todo';
            })
            ->rawColumns(['view', 'product', 'store', 'trans', 'customer', 'budgetYear'])
            ->make(true);
    }
}
