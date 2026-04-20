<?php

namespace App\Http\Controllers;

use App\Models\Price;
use App\Models\Product;
use App\Models\Hmo;
use App\Models\HmoScheme;
use App\Models\HmoTariff;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Http\Request;
use App\Models\ApplicationStatu;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            // Check legacy flag first; if unset, also check actual stock batches
            $hasStock = $product->stock_assign == 1
                || \App\Models\StockBatch::where('product_id', $product_id)->active()->where('current_qty', '>', 0)->exists();
            if(!$hasStock){
                $msg = "Please assign stock to the item [$product->product_name] before attempting to set price";
                return redirect(route('products.index'))->withMessage($msg)->withMessageType('danger');
            }else{
                // Backfill the legacy flag so this check doesn't repeat
                if ($product->stock_assign == 0) {
                    $product->update(['stock_assign' => 1]);
                }
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
                // Load HMO schemes with their HMOs and tariff stats for this product
                $productId = $data->product_id;

                $schemes = HmoScheme::with(['hmos' => function ($q) {
                    $q->where('status', 1);
                }])->get();

                // Get all tariffs for this product keyed by hmo_id
                $tariffs = HmoTariff::where('product_id', $productId)
                    ->whereNull('service_id')
                    ->get()
                    ->keyBy('hmo_id');

                // Build scheme summary data with tariff stats
                $schemeSummary = [];
                foreach ($schemes as $scheme) {
                    $activeHmos = $scheme->hmos;
                    if ($activeHmos->isEmpty()) continue;

                    $payableValues = [];
                    $claimsValues = [];
                    $hmosData = [];

                    foreach ($activeHmos as $hmo) {
                        $tariff = $tariffs->get($hmo->id);
                        $payable = $tariff ? (float) $tariff->payable_amount : 0;
                        $claims = $tariff ? (float) $tariff->claims_amount : 0;

                        $payableValues[] = $payable;
                        $claimsValues[] = $claims;

                        $hmosData[] = [
                            'id' => $hmo->id,
                            'name' => $hmo->name,
                            'payable_amount' => $payable,
                            'claims_amount' => $claims,
                            'coverage_mode' => $tariff ? $tariff->coverage_mode : 'primary',
                            'has_tariff' => $tariff ? true : false,
                            'is_manual' => $tariff && $payable > 0,
                        ];
                    }

                    $schemeSummary[] = [
                        'id' => $scheme->id,
                        'name' => $scheme->name,
                        'code' => $scheme->code ?? '',
                        'hmo_count' => count($hmosData),
                        'hmos' => $hmosData,
                        'payable_min' => count($payableValues) ? min($payableValues) : 0,
                        'payable_max' => count($payableValues) ? max($payableValues) : 0,
                        'payable_avg' => count($payableValues) ? round(array_sum($payableValues) / count($payableValues), 2) : 0,
                        'claims_min' => count($claimsValues) ? min($claimsValues) : 0,
                        'claims_max' => count($claimsValues) ? max($claimsValues) : 0,
                        'claims_avg' => count($claimsValues) ? round(array_sum($claimsValues) / count($claimsValues), 2) : 0,
                        'manual_count' => collect($hmosData)->where('is_manual', true)->count(),
                        'auto_count' => collect($hmosData)->where('is_manual', false)->count(),
                    ];
                }

                // Standalone HMOs (no scheme)
                $standaloneHmos = Hmo::where('status', 1)
                    ->whereNull('hmo_scheme_id')
                    ->get();
                $standaloneData = [];
                foreach ($standaloneHmos as $hmo) {
                    $tariff = $tariffs->get($hmo->id);
                    $standaloneData[] = [
                        'id' => $hmo->id,
                        'name' => $hmo->name,
                        'payable_amount' => $tariff ? (float) $tariff->payable_amount : 0,
                        'claims_amount' => $tariff ? (float) $tariff->claims_amount : 0,
                        'coverage_mode' => $tariff ? $tariff->coverage_mode : 'primary',
                        'has_tariff' => $tariff ? true : false,
                        'is_manual' => $tariff && (float) $tariff->payable_amount > 0,
                    ];
                }

                $totalHmoCount = Hmo::where('status', 1)->count();

                return view('admin.prices.edit', compact(
                    'data', 'application', 'schemeSummary', 'standaloneData', 'totalHmoCount'
                ));
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
    {
        $now = \Carbon\Carbon::now();

        try {
            $rules = [
                'price' => 'required|numeric|min:0',
            ];

            $v = validator()->make($request->all(), $rules);

            if ($v->fails()) {
                $msg = 'Please check your inputs.';
                return redirect()->back()->withInput()->with('errors', $v->messages()->all())->withInput();
            } else {
                $cheak_half = Product::find($request->products);
                $myprice = Price::where('id', '=', $id)->first();

                $myprice->initial_sale_date    = $now;
                $myprice->current_sale_date    = $now;
                $myprice->initial_sale_price   = $request->price;
                $myprice->current_sale_price   = $request->price;
                $myprice->pr_buy_price         = $request->new_buy_price;
                if ($request->max_discount == '') {
                    $myprice->max_discount = 0;
                } else {
                    $myprice->max_discount = $request->max_discount;
                }

                $myprice->half_price          = 0;
                $myprice->pieces_price        = 0;
                $myprice->pieces_max_discount = 0;

                $myprice->status = 1;

                if ($myprice->update()) {
                    // ── Tariff propagation (only if user opted in) ──
                    $tariffMsg = '';
                    $syncPayable = $request->has('sync_payable');
                    $syncClaims  = $request->has('sync_claims');

                    if ($syncPayable || $syncClaims) {
                        $tariffMsg = $this->propagateTariffs(
                            $myprice->product_id,
                            $syncPayable ? (float) $request->new_payable_amount : null,
                            $syncClaims  ? (float) $request->new_claims_amount : null,
                            $request->input('tariff_scope', 'none'),
                            $request->input('selected_scheme_ids', []),
                            $request->input('selected_hmo_ids', []),
                            $request->boolean('override_manual')
                        );
                    }

                    $msg = 'Price was updated successfully.';
                    if ($tariffMsg) {
                        $msg .= ' ' . $tariffMsg;
                    }

                    return redirect(route('products.index'))->withMessage($msg)->withMessageType('success');
                } else {
                    $msg = 'Something went wrong. Please try again later.';
                    return redirect()->back()->withInput()->withMessage($msg)->withMessageType('danger');
                }
            }
        } catch (\Exception $e) {
            Log::error('PriceController@update: ' . $e->getMessage());
            return redirect()->back()->withInput()->withMessage('An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Propagate tariff updates for a product to selected HMOs.
     */
    private function propagateTariffs(
        int $productId,
        ?float $newPayable,
        ?float $newClaims,
        string $scope,
        array $schemeIds,
        array $hmoIds,
        bool $overrideManual
    ): string {
        // Resolve target HMO IDs based on scope
        switch ($scope) {
            case 'all':
                $targetHmoIds = Hmo::where('status', 1)->pluck('id')->toArray();
                break;
            case 'scheme':
                $cleanSchemeIds = array_map('intval', array_filter($schemeIds));
                $targetHmoIds = Hmo::where('status', 1)
                    ->whereIn('hmo_scheme_id', $cleanSchemeIds)
                    ->pluck('id')->toArray();
                break;
            case 'manual':
                $targetHmoIds = array_map('intval', array_filter($hmoIds));
                break;
            default:
                return '';
        }

        if (empty($targetHmoIds)) {
            return 'No HMOs selected for tariff update.';
        }

        $updated = 0;
        $skipped = 0;
        $created = 0;

        DB::beginTransaction();
        try {
            foreach ($targetHmoIds as $hmoId) {
                $tariff = HmoTariff::where('hmo_id', $hmoId)
                    ->where('product_id', $productId)
                    ->whereNull('service_id')
                    ->first();

                if ($tariff) {
                    $changes = [];

                    if ($newPayable !== null) {
                        if (!$overrideManual && (float) $tariff->payable_amount > 0) {
                            $skipped++;
                            // Still update claims if requested
                            if ($newClaims !== null) {
                                $tariff->update(['claims_amount' => $newClaims]);
                                $updated++;
                                $skipped--; // Not fully skipped
                            }
                            continue;
                        }
                        $changes['payable_amount'] = $newPayable;
                    }

                    if ($newClaims !== null) {
                        $changes['claims_amount'] = $newClaims;
                    }

                    if (!empty($changes)) {
                        $tariff->update($changes);
                        $updated++;
                    }
                } else {
                    // Create tariff if it doesn't exist
                    HmoTariff::create([
                        'hmo_id'         => $hmoId,
                        'product_id'     => $productId,
                        'service_id'     => null,
                        'claims_amount'  => $newClaims ?? 0,
                        'payable_amount' => $newPayable ?? 0,
                        'coverage_mode'  => 'primary',
                    ]);
                    $created++;
                }
            }

            DB::commit();

            $parts = [];
            if ($updated > 0) $parts[] = "{$updated} tariff(s) updated";
            if ($created > 0) $parts[] = "{$created} tariff(s) created";
            if ($skipped > 0) $parts[] = "{$skipped} skipped (manual pricing)";

            $result = implode(', ', $parts) . '.';
            Log::info("PriceController tariff propagation for product {$productId}: {$result}");
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("PriceController tariff propagation failed for product {$productId}: " . $e->getMessage());
            return 'Tariff update failed: ' . $e->getMessage();
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    /**
     * Show the HMO tariff view for a product.
     */
    public function tariffView($id)
    {
        try {
            $product = Product::findOrFail($id);
            $price = Price::where('product_id', $id)->first();
            $salePrice = $price ? (float) $price->current_sale_price : 0;

            $schemes = HmoScheme::with(['hmos' => fn($q) => $q->where('status', 1)])->get();
            $tariffs = HmoTariff::where('product_id', $id)->whereNull('service_id')->get()->keyBy('hmo_id');

            $schemeSummary = [];
            foreach ($schemes as $scheme) {
                $activeHmos = $scheme->hmos;
                if ($activeHmos->isEmpty()) continue;

                $payableValues = [];
                $claimsValues = [];
                $hmosData = [];

                foreach ($activeHmos as $hmo) {
                    $tariff = $tariffs->get($hmo->id);
                    $payable = $tariff ? (float) $tariff->payable_amount : 0;
                    $claims = $tariff ? (float) $tariff->claims_amount : 0;
                    $payableValues[] = $payable;
                    $claimsValues[] = $claims;
                    $hmosData[] = [
                        'id' => $hmo->id, 'name' => $hmo->name,
                        'payable_amount' => $payable, 'claims_amount' => $claims,
                        'coverage_mode' => $tariff ? $tariff->coverage_mode : 'primary',
                        'has_tariff' => (bool) $tariff,
                        'is_manual' => $tariff && $payable > 0,
                    ];
                }

                $schemeSummary[] = [
                    'id' => $scheme->id, 'name' => $scheme->name,
                    'hmo_count' => count($hmosData), 'hmos' => $hmosData,
                    'payable_min' => min($payableValues), 'payable_max' => max($payableValues),
                    'payable_avg' => round(array_sum($payableValues) / count($payableValues), 2),
                    'claims_min' => min($claimsValues), 'claims_max' => max($claimsValues),
                    'claims_avg' => round(array_sum($claimsValues) / count($claimsValues), 2),
                    'manual_count' => collect($hmosData)->where('is_manual', true)->count(),
                    'auto_count' => collect($hmosData)->where('is_manual', false)->count(),
                ];
            }

            $standaloneHmos = Hmo::where('status', 1)->whereNull('hmo_scheme_id')->get();
            $standaloneData = [];
            foreach ($standaloneHmos as $hmo) {
                $tariff = $tariffs->get($hmo->id);
                $standaloneData[] = [
                    'id' => $hmo->id, 'name' => $hmo->name,
                    'payable_amount' => $tariff ? (float) $tariff->payable_amount : 0,
                    'claims_amount' => $tariff ? (float) $tariff->claims_amount : 0,
                    'coverage_mode' => $tariff ? $tariff->coverage_mode : 'primary',
                    'has_tariff' => (bool) $tariff,
                    'is_manual' => $tariff && (float) $tariff->payable_amount > 0,
                ];
            }

            return view('admin.partials.hmo-tariff-view', [
                'itemName' => $product->product_name,
                'itemType' => 'product',
                'itemId' => $id,
                'salePrice' => $salePrice,
                'schemeSummary' => $schemeSummary,
                'standaloneData' => $standaloneData,
                'totalHmoCount' => Hmo::where('status', 1)->count(),
                'backUrl' => route('products.index'),
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withMessage('Error: ' . $e->getMessage());
        }
    }

    public function priceslist()
    {
        //
    }
    public function destroy($id)
    {
        //
    }
}
