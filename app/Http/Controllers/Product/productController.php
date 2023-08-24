<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DataTables;

class productController extends Controller
{
    public function listProducts()
    {
        $pc = Product::where('visible', '=', 1)->with('stock', 'category')->orderBy('product_name', 'ASC')->get();

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

                return (($pc->visible == 0) ? $inactive : $active);
            })
            ->editColumn('current_quantity', function ($pc) {
                return ($pc->stock->current_quantity);
            })
            ->addColumn('addstoke', function ($pc) {

                if (Auth::user()->hasPermissionTo('stock-show') || Auth::user()->hasRole(['Super-Admin', 'Admin','Requisition'])) {
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

                if (Auth::user()->hasPermissionTo('price-edit') || Auth::user()->hasRole(['Super-Admin', 'Admin','Requisition'])) {
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

                if (Auth::user()->hasPermissionTo('store-stocks-edit') || Auth::user()->hasRole(['Super-Admin', 'Admin','Requisition'])) {
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

                if (Auth::user()->hasPermissionTo('product-show') || Auth::user()->hasRole(['Super-Admin', 'Admin','Requisition'])) {
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

                if (Auth::user()->hasPermissionTo('product-edit') || Auth::user()->hasRole(['Super-Admin', 'Admin','Requisition'])) {

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

    public function listSalesProduct(Request $request, $id)
    {

        $pc = Sale::where('product_id', '=', $id)->with('transaction', 'product', 'store')->orderBy('id', 'DESC')->get();

        return Datatables::of($pc)
            ->addIndexColumn()
            ->addColumn('view', function ($pc) {
                return '<a href="' . route('transactions.show', $pc->transaction->id) . '" class="btn btn-dark btn-sm"><i class="fa fa-eye"></i> SIV</a>';
            })
            ->editColumn('product', function ($pc) {
                return ($pc->product->product_name);
            })
            ->editColumn('store', function ($pc) {
                return ($pc->store->store_name);
            })
            ->editColumn('trans', function ($pc) {
                return ($pc->transaction->transaction_no);
            })
            ->editColumn('customer', function ($pc) {
                return ($pc->transaction->customer_name);
            })
             ->editColumn('budgetYear', function ($pc) {
                $budgetYear = getBudgetYearName($pc->budget_year_id);

                return $budgetYear;
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

        $datarrr = Product::where('id', '>', 0)->limit(10)->get();

        // dd($datarrr);

        // foreach ($datarrr as $data2) {
        //     // dd($data2);
        //    $stk = StoreStoke::where('product_id', '=',  $data2->id)->first();
        //    // dd($stk);
        //     $stk->id = $stk->id;
        //     $stk->current_quantity = $data2->current_quantity;
        //     $stk->update();

        // }
        // alert()->success('SuccessAlert','Successfully Updated the record.');
        $data = Product::where('id', '>', 0)->with('stock')->orderBy('product_name', 'DESC')->get();
        // $data = Product::orderBy('product_name','DESC')->get();
        //dd( $data);

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
        $category       = Category::where('status_id', '=', 2)->pluck('category_name', 'id')->all();
        return view('admin.product.create', compact('application', 'category'));
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

        try {
            $v = validator()->make($request->all(), $rules);

            if ($v->fails()) {
                // Alert::error('Error Title', 'One or more information is needed.');
                // return redirect()->back()->withInput()->withInput()->withErrors($v);
                // return redirect()->back()->withInput()->with('toast_error', $v->messages()->all()[0])->withInput();
                return redirect()->back()->withInput()->with('toast_error', $v->messages()->all()[0])->withInput();
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

                $myproduct->visible             = 1;
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
                        # code...
                        // $msg = 'Product Created Successfully!';
                        // return redirect(route('products.index'))->withMessage($msg)->withMessageType('success')->with( $msg);
                        return redirect(route('products.index'))->with('toast_success', $msg);
                    }
                } else {
                    $msg = 'Something is went wrong. Please try again later, Product not Saved.';
                    //flash($msg, 'danger');
                    return redirect()->back()->withInput()->with('error', $msg)->withInput();
                }
            }
        } catch (Exception $e) {

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
            $category       = Category::where('status_id', '=', 2)->pluck('category_name', 'id')->all();
            return view('admin.product.edit', compact('product', 'application', 'category'));
        } catch (Exception $e) {

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
                return redirect()->back()->withInput()->with('error', $v->messages()->all()[0])->withInput();
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
                $myproduct->visible            = $request->visible;

                if ($myproduct->update()) {

                    $msg = 'The Product ' . $request->product_name . ' Was Updated Successfully.';
                    // flash($msg, 'success');
                    // Alert::success('Success ', 'Success Message');
                    // alert()->success('SuccessAlert','Successfully Updated the record.');
                    return redirect(route('products.index'))->with('toast_success', $msg);
                    return redirect(route('products.index'));
                } else {
                    $msg = 'Something is went wrong. Please try again later, information not save.';
                    //flash($msg, 'danger');
                    return redirect()->back()->withInput()->withInput();
                }
            }
        } catch (Exception $e) {

            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage());
        }
    }
}
