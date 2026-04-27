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

        // Filter by category if provided
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

                return '<div>'
                    . '<strong>' . e($pc->service_name) . '</strong>'
                    . '<br><small class="text-muted">' . e($pc->service_code) . '</small>'
                    . ' <span class="badge" style="background:' . $catBg . ';color:' . $catFg . ';font-size:0.7rem">' . e($catName) . '</span>'
                    . $templateBadge
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

    public function toggleStatus($id)
    {
        try {
            $service = Service::withoutGlobalScopes()->findOrFail($id);
            $service->status = $service->status == 1 ? 0 : 1;
            $service->save();

            return response()->json([
                'success' => true,
                'message' => 'Service status updated successfully.',
                'new_status' => $service->status
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    public function liveSearchServices(Request $request)
    {
        $request->validate([
            'term' => 'nullable|string',
            'patient_id' => 'nullable|integer'
        ]);

        $categoryId = $request->input('category_id', appsettings('investigation_category_id'));

        $query = Service::where('status', 1)
            ->where('category_id', $categoryId)
            ->whereHas('price');

        if ($request->filled('term')) {
            $query->where('service_name', 'LIKE', "%{$request->term}%");
        }

        $pc = $query
            ->with(['category', 'price'])
            ->orderBy('service_name', 'ASC')
            ->get()
            ->map(function ($service) use ($request) {
                $basePrice = optional($service->price)->sale_price;
                $coverage = null;

                if ($request->filled('patient_id')) {
                    try {
                        $coverage = \App\Helpers\HmoHelper::applyHmoTariff($request->patient_id, null, $service->id);
                    } catch (\Exception $e) {
                        $coverage = null; // fallback to cash if tariff missing
                    }
                }

                return [
                    'id' => $service->id,
                    'service_name' => $service->service_name,
                    'service_code' => $service->service_code,
                    'coverage_mode' => $coverage['coverage_mode'] ?? 'cash',
                    'payable_amount' => $coverage['payable_amount'] ?? ($basePrice ?? 0),
                    'claims_amount' => $coverage['claims_amount'] ?? 0,
                    'validation_status' => $coverage['validation_status'] ?? null,
                    'category' => $service->category,
                    'price' => $service->price,
                ];
            });

        return response()->json($pc);
    }

    public function listSalesService(Request $request, $id)
    {

        $pc = Sale::where('service_id', '=', $id)->with('product_or_service_request', 'product', 'store')->orderBy('id', 'DESC')->get();

        return Datatables::of($pc)
            ->addIndexColumn()
            ->addColumn('view', function ($pc) {
                // return '<a href="' . route('transactions.show', $pc->transaction->id) . '" class="btn btn-dark btn-sm"><i class="fa fa-eye"></i> SIV</a>';
                return 'todo';
            })
            ->editColumn('product', function ($pc) {
                return ($pc->product->service_name);
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
    public function index(Request $request)
    {
        $categoryId = $request->input('category');
        $categoryName = null;

        if ($categoryId) {
            $category = ServiceCategory::find($categoryId);
            $categoryName = $category ? $category->category_name : null;
        }

        $categories = ServiceCategory::where('status', 1)->orderBy('category_name')->pluck('category_name', 'id')->all();

        return view('admin.service.index', [
            'filterCategory' => $categoryId,
            'categoryName' => $categoryName,
            'categories' => $categories,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $selectedCategory = $request->query('category');
        $category = ServiceCategory::where('status', '=', 1)->pluck('category_name', 'id')->all();
        $procedureCategories = ProcedureCategory::where('status', 1)->orderBy('name')->get();
        $procedureCategoryId = appsettings('procedure_category_id');
        
        return view('admin.service.create', compact('category', 'procedureCategories', 'procedureCategoryId', 'selectedCategory'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function store(Request $request)
    {
        $procedureCategoryId = appsettings('procedure_category_id');
        $isProcedure = $request->category_id == $procedureCategoryId;

        $rules = [
            'category_id'          => 'required',
            'service_name'          => 'required',
            'service_code'          => 'required',
        ];

        // Add procedure-specific validation rules
        if ($isProcedure) {
            $rules['procedure_category_id'] = 'required|exists:procedure_categories,id';
            $rules['procedure_code'] = 'nullable|string|max:50';
            $rules['is_surgical'] = 'nullable|boolean';
            $rules['estimated_duration_minutes'] = 'nullable|integer|min:1';
            $rules['procedure_description'] = 'nullable|string';
        }

        try {
            $v = validator()->make($request->all(), $rules);

            if ($v->fails()) {
                return redirect()->back()->withInput()->with('errors', $v->messages()->all())->withInput();
            } else {

                DB::beginTransaction();

                $myservice                      = new Service();
                $myservice->user_id             = Auth::user()->id;
                $myservice->category_id         = $request->category_id;
                $myservice->service_name        = trim($request->service_name);
                $myservice->service_code        = $request->service_code;
                $myservice->status              = 1;

                if ($myservice->save()) {
                    // If this is a procedure service, create linked procedure definition
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

                    DB::commit();
                    $msg = 'The Service  ' . $request->service_name . ' was Saved Successfully.';
                    return redirect(route('services.index', ['category' => $myservice->category_id]))->withMessage($msg)->withMessageType('success');
                } else {
                    DB::rollBack();
                    $msg = 'Something is went wrong. Please try again later, Service not Saved.';
                    return redirect()->back()->withInput()->withMessage($msg)->withMessageType('danger')->withInput();
                }
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage());
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
        $pc = Sale::where('service_id', '=', $id)->with('transaction', 'product', 'store')->sum('total_amount');
        $qt = Sale::where('service_id', '=', $id)->with('transaction', 'product', 'store')->sum('quantity_buy');
        $pp = Service::withoutGlobalScopes()->find($id);

        return view('admin.service.product', compact('id', 'pp', 'pc', 'qt'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {

        try {
            $selectedCategory = $request->query('category');
            $product = Service::withoutGlobalScopes()->with('procedureDefinition')->whereId($id)->first();
            $category = ServiceCategory::where('status', '=', 1)->pluck('category_name', 'id')->all();
            $procedureCategories = ProcedureCategory::where('status', 1)->orderBy('name')->get();
            $procedureCategoryId = appsettings('procedure_category_id');
            $procedure = $product->procedureDefinition;

            return view('admin.service.edit', compact('product', 'category', 'procedureCategories', 'procedureCategoryId', 'procedure', 'selectedCategory'));
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
            $procedureCategoryId = appsettings('procedure_category_id');
            $isProcedure = $request->category_id == $procedureCategoryId;

            $rules = [
                'category_id'          => 'required',
                'service_name'          => 'required',
                'service_code'          => 'required',
            ];

            // Add procedure-specific validation rules
            if ($isProcedure) {
                $rules['procedure_category_id'] = 'required|exists:procedure_categories,id';
                $rules['procedure_code'] = 'nullable|string|max:50';
                $rules['is_surgical'] = 'nullable|boolean';
                $rules['estimated_duration_minutes'] = 'nullable|integer|min:1';
                $rules['procedure_description'] = 'nullable|string';
            }

            $v = validator()->make($request->all(), $rules);

            if ($v->fails()) {
                return redirect()->back()->withInput()->with('errors', $v->messages()->all())->withInput();
            } else {

                DB::beginTransaction();

                $myservice                 = Service::withoutGlobalScopes()->whereId($id)->first();
                $oldCategoryId             = $myservice->category_id;
                $myservice->user_id        = Auth::user()->id;
                $myservice->category_id    = $request->category_id;
                $myservice->service_name   = $request->service_name;
                $myservice->service_code   = $request->service_code;

                if ($myservice->update()) {
                    // Handle procedure definition
                    if ($isProcedure) {
                        // Create or update procedure definition
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
                        // If category changed away from procedure, delete linked procedure definition
                        if ($oldCategoryId == $procedureCategoryId) {
                            ProcedureDefinition::where('service_id', $myservice->id)->delete();
                        }
                    }

                    DB::commit();
                    $msg = 'The Service ' . $request->service_name . ' Was Updated Successfully.';
                    return redirect(route('services.index', ['category' => $myservice->category_id]))->withMessage($msg)->withMessageType('success');
                } else {
                    DB::rollBack();
                    $msg = 'Something is went wrong. Please try again later, information not save.';

                    return redirect()->back()->withInput()->withMessage($msg)->withMessageType('success')->withInput();
                }
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage());
        }
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

    /**
     * Show the result template builder for a service
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function buildTemplate($id)
    {
        $service = Service::with('category')->findOrFail($id);

        // Decode existing template if any
        $template = $service->result_template_v2;

        return view('admin.service.template-builder', [
            'service' => $service,
            'template' => $template
        ]);
    }

    /**
     * Save the result template for a service
     *
     * @param  Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function saveTemplate(Request $request, $id)
    {
        try {
            $service = Service::findOrFail($id);

            // Validate the template structure with flexible rules
            $validated = $request->validate([
                'template_name' => 'required|string|max:255',
                'parameters' => 'required|array|min:1',
                'parameters.*.id' => 'required|string',
                'parameters.*.name' => 'required|string',
                'parameters.*.code' => 'required|string',
                'parameters.*.type' => 'required|in:string,integer,float,boolean,enum,long_text',
                'parameters.*.unit' => 'nullable|string',
                'parameters.*.required' => 'required',
                'parameters.*.show_in_report' => 'required',
                'parameters.*.order' => 'required|integer',
                'parameters.*.reference_range' => 'nullable|array',
                'parameters.*.reference_range.min' => 'nullable|numeric',
                'parameters.*.reference_range.max' => 'nullable|numeric',
                'parameters.*.reference_range.reference_value' => 'nullable',
                'parameters.*.reference_range.text' => 'nullable|string',
                'parameters.*.options' => 'nullable|array',
                'parameters.*.options.*.value' => 'nullable|string',
                'parameters.*.options.*.label' => 'nullable|string',
            ]);

            // Clean up and process parameters
            $parameters = [];
            foreach ($validated['parameters'] as $param) {
                $cleanParam = [
                    'id' => $param['id'],
                    'name' => $param['name'],
                    'code' => $param['code'],
                    'type' => $param['type'],
                    'unit' => $param['unit'] ?? null,
                    'required' => filter_var($param['required'], FILTER_VALIDATE_BOOLEAN),
                    'show_in_report' => filter_var($param['show_in_report'], FILTER_VALIDATE_BOOLEAN),
                    'order' => (int)$param['order'],
                ];

                // Add options if present and not empty (for enum type)
                if (isset($param['options']) && is_array($param['options']) && !empty($param['options'])) {
                    $cleanParam['options'] = array_values(array_filter($param['options'], function($opt) {
                        return isset($opt['value']) && $opt['value'] !== '';
                    }));
                }

                // Add reference range if present and not empty
                if (isset($param['reference_range']) && is_array($param['reference_range'])) {
                    $refRange = array_filter($param['reference_range'], function($value) {
                        return $value !== null && $value !== '';
                    });

                    if (!empty($refRange)) {
                        // For enum type, validate that reference_value is in options
                        if ($param['type'] === 'enum' && isset($refRange['reference_value'])) {
                            if (!isset($cleanParam['options']) || empty($cleanParam['options'])) {
                                return response()->json([
                                    'success' => false,
                                    'message' => "Enum parameter '{$param['name']}' must have options defined before setting a reference value."
                                ], 400);
                            }

                            $optionValues = array_column($cleanParam['options'], 'value');
                            if (!in_array($refRange['reference_value'], $optionValues)) {
                                return response()->json([
                                    'success' => false,
                                    'message' => "Reference value '{$refRange['reference_value']}' for parameter '{$param['name']}' must be one of the defined options: " . implode(', ', $optionValues)
                                ], 400);
                            }
                        }

                        // Convert numeric strings to numbers for min/max
                        if (isset($refRange['min'])) {
                            $refRange['min'] = is_numeric($refRange['min']) ? (float)$refRange['min'] : $refRange['min'];
                        }
                        if (isset($refRange['max'])) {
                            $refRange['max'] = is_numeric($refRange['max']) ? (float)$refRange['max'] : $refRange['max'];
                        }
                        $cleanParam['reference_range'] = $refRange;
                    }
                }

                $parameters[] = $cleanParam;
            }

            // Build the template structure
            $template = [
                'template_name' => $validated['template_name'],
                'version' => '2.0',
                'parameters' => $parameters
            ];

            // Save to database
            $service->result_template_v2 = $template;
            $service->save();

            return response()->json([
                'success' => true,
                'message' => 'Template saved successfully',
                'template' => $template
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving template: ' . $e->getMessage()
            ], 500);
        }
    }
}
