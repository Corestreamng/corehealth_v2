<?php

namespace App\Http\Controllers;

use App\Models\Product;
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

class ProductController extends Controller
{
    public function listProducts()
    {
        $pc = Product::where('status', '=', 1)->with('stock', 'category')->orderBy('product_name', 'ASC')->get();

        return Datatables::of($pc)
            ->addIndexColumn()
            ->addColumn('product_code', function ($pc) {
                $product_code = '<span class="badge badge-pill badge-dark">' . $pc->product_code . '</sapn>';
                return $product_code;
            })
            ->addColumn('category_id', function ($pc) {
                $category_name = '<span class="badge badge-pill badge-dark">' . $pc->category->category_code . '</sapn>';
                return $category_name;
            })
            ->addColumn('visible', function ($pc) {

                $active = '<span class="badge badge-pill badge-success">Active</sapn>';
                $inactive = '<span class="badge badge-pill badge-dark">Inactive</sapn>';

                return (($pc->status == 0) ? $inactive : $active);
            })
            ->editColumn('current_quantity', function ($pc) {
                return ($pc->stock->current_quantity);
            })
            ->addColumn('addstoke', function ($pc) {
                if (Auth::user()->hasPermissionTo('can-manage-products') || Auth::user()->hasRole(['ADMIN', 'STORE'])) {
                    # code...
                    $url = route('stocks.show', $pc->id);
                    return '<a href="' . $url . '" class="btn btn-info btn-sm"><i class="fa fa-plus"></i> Add</a>';
                } else {
                    # code...
                    $label = '<button disabled class="btn btn-info btn-sm"> <i class="fa fa-plus"></i> Add</button>';
                    return $label;
                }
            })
            ->addColumn('adjust', function ($pc) {

                if (Auth::user()->hasPermissionTo('can-manage-products') || Auth::user()->hasRole(['ADMIN', 'STORE'])) {
                    # code...
                    $url = route('prices.edit', $pc->id);
                    return '<a href="' . $url . '" class="btn btn-secondary btn-sm"><i class="fa fa-info-circle"></i> Add/Adjust</a>';
                } else {
                    # code...
                    $label = '<button disabled class="btn btn-secondary btn-sm"> <i class="fa fa-info-circle"></i> Add/Adjust</button>';
                    return $label;
                }
            })
            ->addColumn('store', function ($pc) {

                if (Auth::user()->hasPermissionTo('can-manage-products') || Auth::user()->hasRole(['ADMIN', 'STORE'])) {
                    # code...
                    $url = route('stores-stokes.edit', $pc->id);
                    return '<a href="' . $url . '" class="btn btn-success btn-sm"><i class="fa fa-map-pin"></i> View</a>';
                } else {
                    # code...
                    $label = '<button disabled class="btn btn-success btn-sm"> <i class="fa fa-map-pin"></i> View</button>';
                    return $label;
                }
            })
            ->addColumn('trans', function ($pc) {

                if (Auth::user()->hasPermissionTo('can-manage-products') || Auth::user()->hasRole(['ADMIN', 'STORE'])) {
                    # code...
                    $url = route('products.show', $pc->id);
                    return '<a href="' . $url . '" class="btn btn-info btn-sm"><i class="fa fa-map-pin"></i> View</a>';
                } else {
                    # code...
                    $label = '<button disabled class="btn btn-info btn-sm"> <i class="fa fa-map-pin"></i> View</button>';
                    return $label;
                }
            })
            ->addColumn('edit', function ($pc) {

                if (Auth::user()->hasPermissionTo('can-manage-products') || Auth::user()->hasRole(['ADMIN', 'STORE'])) {

                    $url = route('products.edit', $pc->id);
                    return '<a href="' . $url . '" class="btn btn-secondary btn-sm"><i class="fa fa-i-cursor"></i> Edit</a>';
                } else {

                    $label = '<button disabled class="btn btn-secondary btn-sm"> <i class="fa fa-i-cursor"></i> Edit</button>';
                    return $label;
                }
            })
            ->rawColumns(['product_code', 'category_id', 'visible', 'edit', 'adjust', 'addstoke', 'store', 'trans'])
            ->make(true);
    }

    public function liveSearchProducts(Request $request){
        $request->validate([
            'term' => 'required|string'
        ]);
        $pc = Product::where('status', '=', 1)->where('product_name', 'LIKE', "%$request->term%")->with('stock', 'category', 'price')->orderBy('product_name', 'ASC')->get();
        return json_decode($pc);
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
        return view('admin.product.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $application = ApplicationStatu::whereId(1)->first();
        $category       = ProductCategory::where('status', '=', 1)->pluck('category_name', 'id')->all();
        return view('admin.product.create', compact('category','application'));
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
            'category_id'          => 'required',
            'product_name'          => 'required',
            'product_code'          => 'required',
            'reorder_alert'         => 'required',
        ];

        if ($application->allow_piece_sale == 1) {
            # code...
            if ($request->s1 == null) {
                $rules += [
                    // 's1.required'            => 'Allow Sale of Pieces',
                    's1'            => 'required',
                ];
            }
        }

        if ($application->allow_halve_sale == 1) {
            # code...
            if ($request->s2 == null) {
                $rules += [
                    // 's2.required'            => 'Allow Sale of Half',
                    's2'            => 'required',
                ];
            }
        }

        if ($application->allow_piece_sale == 1 || $application->allow_halve_sale) {
            # code...
            if ($request->quantity_in == null) {
                $rules += [
                    'quantity_in'   => 'required',
                ];
            }
        }

        try {
            $v = validator()->make($request->all(), $rules);

            if ($v->fails()) {
                // Alert::error('Error Title', 'One or more information is needed.');
                // return redirect()->back()->with('errors', $v->messages()->all())->withInput();
                // return redirect()->back()->with('toast_error', $v->messages()->all()[0])->withInput();
                return redirect()->back()->with('errors', $v->messages()->all())->withInput();
            } else {

                $myproduct                      = new Product();
                $myproduct->user_id             = Auth::user()->id;
                $myproduct->category_id         = $request->category_id;
                $myproduct->product_name        = trim($request->product_name);
                $myproduct->product_code        = $request->product_code;
                $myproduct->reorder_alert       = $request->reorder_alert;

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

                if ($myproduct->save()) {

                    $msg = 'The Product  ' . $request->product_name . ' was Saved Successfully.';

                    $stock                     = new Stock();
                    $stock->product_id         = $myproduct->id;
                    $stock->initial_quantity   = 0;
                    $stock->order_quantity     = 0;
                    $stock->current_quantity   = 0;
                    $stock->quantity_sale      = 0;


                    if ($stock->save()) {
                        return redirect(route('products.index'))->withMessage($msg)->withMessageType('success');
                    }
                } else {
                    $msg = 'Something is went wrong. Please try again later, Product not Saved.';
                    //flash($msg, 'danger');
                    return redirect()->back()->withMessage($msg)->withMessageType('danger')->withInput();
                }
            }
        } catch (\Exception $e) {

            return redirect()->back()->withMessage("An error occurred " . $e->getMessage());
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
        $pc = Sale::where('product_id', '=', $id)->with('transaction', 'product', 'store')->sum('total_amount');
        $qt = Sale::where('product_id', '=', $id)->with('transaction', 'product', 'store')->sum('quantity_buy');
        $pp = Product::find($id);

        return view('admin.product.product', compact('id', 'pp', 'pc', 'qt'));
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
            $product = Product::whereId($id)->first();
            $category       = ProductCategory::where('status', '=', 1)->pluck('category_name', 'id')->all();
            return view('admin.product.edit', compact('product', 'application', 'category'));
        } catch (\Exception $e) {

            return redirect()->back()->withMessage("An error occurred " . $e->getMessage());
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
                'category_id'          => 'required',
                'product_name'          => 'required',
                'product_code'          => 'required',
                'reorder_alert'         => 'required',
            ];

            if ($application->allow_piece_sale == 1) {
                # code...
                if ($request->s1 == null) {
                    #  Making sure if password change was selected it's being validated
                    $rules += [
                        // 's1.required'            => 'Allow Sale of Pieces',
                        's1'            => 'required',
                    ];
                }
            }

            if ($application->allow_halve_sale == 1) {
                # code...
                if ($request->s2 == null) {
                    #  Making sure if password change was selected it's being validated
                    $rules += [
                        // 's2.required'            => 'Allow Sale of Half',
                        's2'            => 'required',
                    ];
                }
            }

            if ($application->allow_piece_sale == 1 || $application->allow_halve_sale) {
                # code...
                if ($request->quantity_in == null) {
                    #  Making sure if password change was selected it's being validated
                    $rules += [
                        'quantity_in'   => 'required',
                    ];
                }
            }

            $v = validator()->make($request->all(), $rules);

            if ($v->fails()) {

                //  $msg = 'Please cheak Your Inputs .';
                return redirect()->back()->with('errors', $v->messages()->all())->withInput();
            } else {

                $myproduct                 = Product::whereId($id)->first();
                $myproduct->user_id        = Auth::user()->id;
                $myproduct->category_id    = $request->category_id;
                $myproduct->product_name   = $request->product_name;
                $myproduct->product_code   = $request->product_code;
                $myproduct->reorder_alert  = $request->reorder_alert;

                if ($request->s1) {
                    $myproduct->has_have         = $request->s1;
                }
                if ($request->s2) {
                    $myproduct->has_piece         = $request->s2;
                }
                if ($request->s1 || $request->s2) {
                    $myproduct->howmany_to       = $request->quantity_in;
                }
                $myproduct->status            = 1;

                if ($myproduct->update()) {
                    $msg = 'The Product ' . $request->product_name . ' Was Updated Successfully.';
                    return redirect(route('products.index'))->withMessage($msg)->withMessageType('success');
                } else {
                    $msg = 'Something is went wrong. Please try again later, information not save.';

                    return redirect()->back()->withMessage($msg)->withMessageType('success')->withInput();
                }
            }
        } catch (Exception $e) {

            return redirect()->back()->withMessage("An error occurred " . $e->getMessage());
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
}
