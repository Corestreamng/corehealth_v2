<?php

namespace App\Http\Controllers;

use App\Models\StoreStock;
use App\Models\Store;
use App\Models\Sale;
use App\Models\Product;
use Illuminate\Http\Request;

use Validator;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;

class StoreStockController extends Controller
{
    public function listStoresProducts(Request $request, $id)
    {
        $pc = StoreStock::where('store_id', '=', $id)->where('current_quantity', '>', 0)->with('product')->get();

        return Datatables::of($pc)
            ->addIndexColumn()
            ->editColumn('product', function ($pc) {
                return ($pc->product->product_name);
            })
            ->addColumn('movestock', function ($pc) {
                return '<a href="' . route('move-stock.show', $pc->id) . '" class="btn btn-warning"><i class="nav-icon fa fa-circle-o text-info"></i> Transfer</a>';
            })
            ->addColumn('unsupply', function ($pc) {
                $sales = Sale::where('product_id', '=', $pc->product->id)
                    ->where('store_id', '=', $pc->store_id)->where('supply', '=', 1)->sum('quantity_buy');
                return ($sales);
            })
            ->addColumn('totalQt', function ($pc) {
                $sumsale = Sale::where('product_id', '=', $pc->product->id)
                    ->where('store_id', '=', $pc->store_id)->where('supply', '=', 1)->sum('quantity_buy');
                $TQt = $sumsale + $pc->current_quantity;
                return ($TQt);
            })
            ->rawColumns(['product', 'movestock', 'unsupply', 'totalQt'])
            ->make(true);
    }

    public function listProductslocations(Request $request, $id)
    {
        $pc = StoreStock::where('product_id', '=', $id)->where('current_quantity', '>', 0)->with('store')->get();

        return Datatables::of($pc)
            // ->addColumn('sn', function($pc) use ($start) {
            // return $start++;})
            ->addIndexColumn()
            ->editColumn('product', function ($pc) {
                return ($pc->product->product_name);
            })
            //  ->addColumn('sum', function ($pc) {
            // $total = 0;
            // foreach($pc->current_quantity as $qt) {
            //     if ($qt->current_quantity > 1) $total += $qt->current_quantity ;
            // }

            // return number_format($total,2,',','.');
            //})
            ->editColumn('store', function ($pc) {
                return ($pc->store->store_name);
            })
            ->addColumn('unsupply', function ($pc) {
                $sales = Sale::where('product_id', '=', $pc->product->id)
                    ->where('store_id', '=', $pc->store_id)->where('supply', '=', 1)->sum('quantity_buy');
                return ($sales);
            })
            ->addColumn('totalQt', function ($pc) {
                $sumsale = Sale::where('product_id', '=', $pc->product->id)
                    ->where('store_id', '=', $pc->store_id)->where('supply', '=', 1)->sum('quantity_buy');
                $TQt = $sumsale + $pc->current_quantity;
                return ($TQt);
            })
            ->rawColumns(['product',  'unsupply', 'totalQt'])
            ->make(true);
    }
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
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $now = \Carbon\Carbon::now();

        // $pc = StoreStock::where('store_id', '=', $id)->with('store','product')->get();
        $store = Store::find($id);
        return view('admin.stores.show', compact('store', 'id', 'now'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $now = \Carbon\Carbon::now();
        $product = Product::find($id);
        // $qt = StoreStock::where('product_id', '=', $id)->sum('current_quantity');

        return view('admin.stores.product', compact('product', 'id', 'now'));
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
}
