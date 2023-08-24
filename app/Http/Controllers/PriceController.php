<?php

namespace App\Http\Controllers;

use App\Models\Price;
use App\Models\Product;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Http\Request;
use App\Models\ApplicationStatu;
use Illuminate\Support\Facades\Auth;

class PriceController extends Controller
{
    public function listPrices()
    {
        $pc = Price::where('status', '=', 1)->with('product')->get();

        return Datatables::of($pc)
            ->addIndexColumn()
            ->editColumn('product', function ($pc) {
                return ($pc->product->product_name);
            })
            ->rawColumns(['product'])

            ->make(true);
    }

    public function index()
    {
        try {
            $product_id = request()->get('product_id');
            $product     = Product::whereId($product_id)->whereStatus(1)->wherePrice_assign(0)->orderBy('product_name', 'asc')->first();
            if($product->stock_assign == 0){
                $msg = "Please assign stock to the item [$product->product_name] before attempting to set price";
                return redirect(route('products.index'))->withMessage($msg)->withMessageType('danger');
            }else{
                $application = ApplicationStatu::whereId(1)->first();
                return view('admin.prices.create', compact('product', 'application'));
            }
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage());
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $data = Price::where('status', '=', 1)->with('product')->get();
        //if (Auth::user('id', '>',2)) {
        return view('admin.prices.pricelist');
        // }else{return view('admin.prices.customer_price_list', compact('data'));
        // }

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $now = \Carbon\Carbon::now();
        // $now = date(0000 - 00 - 00);

        try {
            $rules = [
                'products' => 'required|max:100',
                'price'    => 'required|max:11'
            ];

            $v = validator()->make($request->all(), $rules);

            if ($v->fails()) {
                $msg = 'Please cheak Your Inputs .';
                //flash($msg, 'danger');
                return redirect()->back()->withInput()->with('errors', $v->messages()->all())->withInput();
            } else {
                $cheak_half = Product::find($request->products);
                //dd($cheak_half);
                $myprice                 = new Price();
                $myprice->product_id         = $request->products;
                $myprice->initial_sale_date   = $now;
                $myprice->current_sale_date   = $now;
                $myprice->initial_sale_price   = $request->price;
                $myprice->current_sale_price  = $request->price;
                $myprice->pr_buy_price        = $request->buy_price;
                if ($request->max_discount == "") {
                    $myprice->max_discount        = 0;
                } else {
                    $myprice->max_discount        = $request->max_discount;
                }

                if ($cheak_half->has_have == 1) {
                    $myprice->half_price         = $request->price / 2;
                } elseif ($cheak_half->has_have == 0) {
                    $myprice->half_price = 0;
                }
                if ($cheak_half->has_piece == 1) {
                    $myprice->pieces_price        = $request->pieces_price;
                    $myprice->pieces_max_discount = $request->pieces_max_discount;
                } elseif ($cheak_half->has_piece == 0) {
                    $myprice->pieces_price        = 0;
                    $myprice->pieces_max_discount = 0;
                }

                $myprice->status            = 1;
                if ($myprice->save()) {
                    $assing_stock = Product::find($request->products);
                    $assing_stock->price_assign = 1;
                    $assing_stock->update();
                    $msg = 'price for ' . $cheak_half->product_name . ' was saved successfully.';
                    // flash($msg, 'success');
                    return redirect(route('products.index'))->withMessage($msg)->withMessageType('success')->with($msg);
                } else {
                    $msg = 'Something is went wrong. Please try again later, information not save.';
                    //flash($msg, 'danger');
                    return redirect()->back()->withInput()->withMessage($msg)->withMessageType('danger');
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
        try {

            if (Auth::user()) {

                $products     = Product::whereId($id)->first();
                $application = ApplicationStatu::whereId(1)->first();
                return view('admin.prices.newprice', compact('products', 'application'));
            } else {
                return view('home.index');
            }
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage());
        }
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
            $data = Price::with('product')->whereProduct_id($id)->first();
            if (empty($data)) {
                return redirect(route('prices.index', ['product_id' => $id]));
            } else {
                // dd($data);
                return view('admin.prices.edit', compact('data', 'application'));
            }
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
    {   //  dd($request);
        $now = \Carbon\Carbon::now();;
        // $now = date(0000 - 00 - 00);

        try {
            $rules = [
                //'buy_price' => 'required|max:11',
                //'price'    => 'required|max:11'
            ];

            $v = validator()->make($request->all(), $rules);

            if ($v->fails()) {
                $msg = 'Please cheak Your Inputs .';
                //flash($msg, 'danger');
                return redirect()->back()->withInput()->with('errors', $v->messages()->all())->withInput();
            } else {
                $cheak_half = Product::find($request->products);
                //  dd($request);
                $myprice                       =  Price::where('id', "=", $id)->first();

                $myprice->initial_sale_date    = $now;
                $myprice->current_sale_date    = $now;
                $myprice->initial_sale_price   = $request->price;
                $myprice->current_sale_price   = $request->price;
                $myprice->pr_buy_price         = $request->new_buy_price;
                if ($request->max_discount == "") {
                    $myprice->max_discount        = 0;
                } else {
                    $myprice->max_discount        = $request->max_discount;
                }

                $myprice->half_price         = 0;

                $myprice->pieces_price        = 0;
                $myprice->pieces_max_discount = 0;


                $myprice->status            = 1;
                if ($myprice->update()) {

                    $msg = 'price was updated successfully';
                    // flash($msg, 'success');
                    return redirect(route('products.index'))->withMessage($msg)->withMessageType('success')->with($msg);
                } else {
                    $msg = 'Something is went wrong. Please try again later, information not save.';
                    //flash($msg, 'danger');
                    return redirect()->back()->withInput()->withInput();
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
    public function priceslist()
    {
        //
    }
    public function destroy($id)
    {
        //
    }
}
