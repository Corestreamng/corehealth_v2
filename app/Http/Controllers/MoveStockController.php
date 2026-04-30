<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StoreStock;
use App\Models\Store;
use App\Services\StockService;


class MoveStockController extends Controller
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

    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            $rules = [
                'quantity' => 'required|numeric|min:0.01',
                'stores_' => 'required|exists:stores,id',
                'product_id' => 'required|exists:products,id',
                'store_id' => 'required|exists:stores,id',
            ];

            $v = validator()->make($request->all(), $rules);

            if ($v->fails()) {
                return redirect()->back()->withErrors($v)->withInput();
            }

            // Convert to base units if packaging provided (if we decide to send packaging info from this legacy view)
            $qty = (float) $request->quantity;
            
            // Perform the transfer using StockService (handles FIFO batches automatically)
            $this->stockService->transferStock(
                $request->product_id,
                $request->store_id,
                $request->stores_,
                $qty,
                null, // Use FIFO
                ['notes' => 'Manual stock movement']
            );

            $msg = 'Items were transferred successfully.';
            return redirect(route('inventory.store-workbench.index'))->withMessage($msg)->withMessageType('success');

        } catch (\Exception $e) {
            \Log::error('MoveStock failed: ' . $e->getMessage());
            return redirect()->back()->withInput()->withMessage("An error occurred: " . $e->getMessage())->withMessageType('danger');
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
        $now = \Carbon\Carbon::now();


        $pc = StoreStock::where('id', '=', $id)->with('store', 'product')->first();

        $product_curr_store = $pc->store->id;

        $stores    = Store::whereStatus(1)->where('id','!=', $product_curr_store)->orderBy('store_name', 'asc')->pluck('store_name', 'id');
        // dd($stores);

        return view('admin.move_stock.move_stock', compact('stores', 'pc', 'id', 'now'));
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
}
