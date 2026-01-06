<?php

namespace App\Http\Controllers;

use App\Models\service;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;

use App\Models\Sale;
use App\Models\ApplicationStatu;
use App\Models\Stock;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Auth;

class ServiceController extends Controller
{
    public function listServices(Request $request)
    {
        $query = service::where('status', '=', 1)->with('category');

        // Filter by category if provided
        if ($request->has('category') && $request->category) {
            $query->where('category_id', $request->category);
        }

        $pc = $query->orderBy('service_name', 'ASC')->get();

        return Datatables::of($pc)
            ->addIndexColumn()
            ->addColumn('service_code', function ($pc) {
                $service_code = '<span class="badge badge-pill badge-dark">' . $pc->service_code . '</sapn>';
                return $service_code;
            })
            ->addColumn('category_id', function ($pc) {
                $category_name = '<span class="badge badge-pill badge-dark">' . (($pc->category) ? $pc->category->category_name : 'N/A') . '</sapn>';
                return $category_name;
            })
            ->addColumn('visible', function ($pc) {

                $active = '<span class="badge badge-pill badge-success">Active</sapn>';
                $inactive = '<span class="badge badge-pill badge-dark">Inactive</sapn>';

                return (($pc->status == 0) ? $inactive : $active);
            })
            ->addColumn('adjust', function ($pc) {

                if (Auth::user()->hasPermissionTo('can-manage-products') || Auth::user()->hasRole(['ADMIN', 'STORE'])) {
                    # code...
                    $url = route('service-prices.edit', $pc->id);
                    return '<a href="' . $url . '" class="btn btn-secondary btn-sm"><i class="fa fa-info-circle"></i> Add/Adjust</a>';
                } else {
                    # code...
                    $label = '<button disabled class="btn btn-secondary btn-sm"> <i class="fa fa-info-circle"></i> Add/Adjust</button>';
                    return $label;
                }
            })
            ->addColumn('template', function ($pc) {
                // Show template builder button only for Med Lab (2) and Imaging (6) categories
                if (in_array($pc->category_id, [2, 6])) {
                    $url = route('services.build-template', $pc->id);
                    $hasTemplate = !empty($pc->result_template_v2);
                    $icon = $hasTemplate ? 'fa-edit' : 'fa-plus';
                    $text = $hasTemplate ? 'Edit' : 'Build';
                    $badge = $hasTemplate ? '<span class="badge badge-success badge-sm ml-1">✓</span>' : '';

                    return '<a href="' . $url . '" class="btn btn-warning btn-sm"><i class="fa ' . $icon . '"></i> ' . $text . '</a>' . $badge;
                } else {
                    return '<span class="text-muted">N/A</span>';
                }
            })
            ->addColumn('trans', function ($pc) {

                if (Auth::user()->hasPermissionTo('can-manage-products') || Auth::user()->hasRole(['ADMIN', 'STORE'])) {
                    # code...
                    $url = route('services.show', $pc->id);
                    return '<a href="' . $url . '" class="btn btn-info btn-sm"><i class="fa fa-map-pin"></i> View</a>';
                } else {
                    # code...
                    $label = '<button disabled class="btn btn-info btn-sm"> <i class="fa fa-map-pin"></i> View</button>';
                    return $label;
                }
            })
            ->addColumn('edit', function ($pc) {

                if (Auth::user()->hasPermissionTo('can-manage-products') || Auth::user()->hasRole(['ADMIN', 'STORE'])) {

                    $url = route('services.edit', $pc->id);
                    return '<a href="' . $url . '" class="btn btn-secondary btn-sm"><i class="fa fa-i-cursor"></i> Edit</a>';
                } else {

                    $label = '<button disabled class="btn btn-secondary btn-sm"> <i class="fa fa-i-cursor"></i> Edit</button>';
                    return $label;
                }
            })
            ->rawColumns(['service_code', 'category_id', 'visible', 'edit', 'adjust', 'template', 'trans'])
            ->make(true);
    }

    public function liveSearchServices(Request $request)
    {
        $request->validate([
            'term' => 'required|string',
            'patient_id' => 'nullable|integer'
        ]);

        $categoryId = $request->input('category_id', appsettings('investigation_category_id'));

        $pc = service::where('status', 1)
            ->where('category_id', $categoryId)
            ->whereHas('price')
            ->where('service_name', 'LIKE', "%{$request->term}%")
            ->with(['category', 'price'])
            ->orderBy('service_name', 'ASC')
            ->limit(10)
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

        return view('admin.service.index', [
            'filterCategory' => $categoryId,
            'categoryName' => $categoryName
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $category       = ServiceCategory::where('status', '=', 1)->pluck('category_name', 'id')->all();
        return view('admin.service.create', compact('category'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function store(Request $request)
    {

        $rules = [
            'category_id'          => 'required',
            'service_name'          => 'required',
            'service_code'          => 'required',
        ];

        try {
            $v = validator()->make($request->all(), $rules);

            if ($v->fails()) {
                // Alert::error('Error Title', 'One or more information is needed.');
                // return redirect()->back()->withInput()->with('errors', $v->messages()->all())->withInput();
                // return redirect()->back()->withInput()->with('toast_error', $v->messages()->all()[0])->withInput();
                return redirect()->back()->withInput()->with('errors', $v->messages()->all())->withInput();
            } else {

                $myservice                      = new Service();
                $myservice->user_id             = Auth::user()->id;
                $myservice->category_id         = $request->category_id;
                $myservice->service_name        = trim($request->service_name);
                $myservice->service_code        = $request->service_code;
                $myservice->status              = 1;

                if ($myservice->save()) {
                    $msg = 'The Service  ' . $request->service_name . ' was Saved Successfully.';
                    return redirect(route('services.index'))->withMessage($msg)->withMessageType('success');
                } else {
                    $msg = 'Something is went wrong. Please try again later, Service not Saved.';
                    //flash($msg, 'danger');
                    return redirect()->back()->withInput()->withMessage($msg)->withMessageType('danger')->withInput();
                }
            }
        } catch (\Exception $e) {

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
        $pp = service::find($id);

        return view('admin.service.product', compact('id', 'pp', 'pc', 'qt'));
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
            $product = service::whereId($id)->first();
            $category       = ServiceCategory::where('status', '=', 1)->pluck('category_name', 'id')->all();
            return view('admin.service.edit', compact('product', 'category'));
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
            $rules = [
                'category_id'          => 'required',
                'service_name'          => 'required',
                'service_code'          => 'required',
            ];

            $v = validator()->make($request->all(), $rules);

            if ($v->fails()) {
                //  $msg = 'Please cheak Your Inputs .';
                return redirect()->back()->withInput()->with('errors', $v->messages()->all())->withInput();
            } else {

                $myservice                 = service::whereId($id)->first();
                $myservice->user_id        = Auth::user()->id;
                $myservice->category_id    = $request->category_id;
                $myservice->service_name   = $request->service_name;
                $myservice->service_code   = $request->service_code;
                $myservice->status            = 1;

                if ($myservice->update()) {
                    $msg = 'The Service ' . $request->service_name . ' Was Updated Successfully.';
                    return redirect(route('services.index'))->withMessage($msg)->withMessageType('success');
                } else {
                    $msg = 'Something is went wrong. Please try again later, information not save.';

                    return redirect()->back()->withInput()->withMessage($msg)->withMessageType('success')->withInput();
                }
            }
        } catch (\Exception $e) {

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
        $service = service::with('category')->findOrFail($id);

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
            $service = service::findOrFail($id);

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
