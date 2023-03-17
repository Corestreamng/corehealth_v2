<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\StoreStock;
use App\Models\Product;
use App\Models\StockOrder;
use Illuminate\Http\Request;
use App\Models\Store;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Auth;

class StockController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
            // dd($request->all());

        if ($request->quantity < 1) {
            # code...
            Alert::error('Error', 'The Order Quantity Cannot be Less Than Zero.');
            return redirect()->back();
        }

        $product_id  = $request->product_id;
        $store_id    = $request->stores_;
        $stock_order                 = new StockOrder();
        $stock_order->invoice_id     = 22;
        $stock_order->product_id     = $product_id;
        $stock_order->store_id       = $store_id;
        $stock_order->order_quantity = $request->quantity;
        $stock_order->total_amount   = $request->total_amount;


            if($stock_order->save()) {

                $stock_cheak = Stock::where('product_id',"=", $product_id)->first();
                if(empty($stock_cheak)) {
                   $stock                     = new Stock();
                   $stock->product_id        = $product_id;
                   $stock->initial_quantity   = 0;
                   $stock->order_quantity     = $request->quantity;
                   $stock->current_quantity   = $request->quantity;
                   $stock->quantity_sale      = 0;
                   $stock->save();
                   $assing_stock = Product::find($product_id);
                   $assing_stock->stock_assign =1;
                   $assing_stock->current_quantity = $stock->current_quantity;
                   $assing_stock->update();

                }else {
                    $stock_cheak = Stock::where('product_id',"=", $product_id)->first();
                    $stock  =  Stock::where('product_id',"=", $product_id)->first();
                    $stock->product_id         = $product_id;
                    $stock->initial_quantity   = $stock_cheak->current_quantity;
                    $stock->order_quantity     = $request->quantity;
                    $stock->current_quantity   = $stock_cheak->current_quantity + $request->quantity;
                    $stock->update();

                    // This area is used for assigning Stock to Product
                    $assing_stock = Product::find($product_id);
                    $assing_stock->stock_assign =1;
                    $assing_stock->current_quantity = $stock->current_quantity;
                    $assing_stock->update();

                }

                $store_stock_cheak = StoreStock::where('product_id',"=", $product_id)->where('store_id',"=", $store_id)->first();

                if(empty($store_stock_cheak)) {

                   $store_stock                     = new StoreStock();
                   $store_stock->store_id           = $store_id;
                   $store_stock->product_id         = $product_id;
                   $store_stock->initial_quantity   = 0;
                   $store_stock->order_quantity     = $request->quantity;
                   $store_stock->current_quantity   = $request->quantity;
                   $store_stock->quantity_sale      = 0;
                   $store_stock->save();

                   return redirect()->route('products.index');

                }else {
                       $store_stock  =  StoreStock::where('product_id',"=", $product_id)->first();
                       $store_stock->product_id         = $product_id;
                       $store_stock->initial_quantity   = $store_stock->current_quantity;
                       $store_stock->order_quantity     = $request->quantity;
                       $store_stock->current_quantity   = $store_stock->current_quantity
                       + $request->quantity;
                       $store_stock->update();
                        return redirect()->route('products.index');

                }
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
        $products     = Product::whereId($id)->first();
        $stores     = Store::whereStatus(1)->orderBy('store_name', 'asc')->pluck('store_name', 'id');
        return view('admin.stocks.create', compact('products','stores'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
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
        //
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
    public function anyDataStockRequest()
    {

        $products = StockOrder::where('visible','=',1)->select(['id','product_id', 'order_quantity', 'total_amount'])->get();

        //dd($products);
            return Datatables::of($products)->make(true);



    }
}
