<?php

namespace App\Http\Controllers;

use App\Models\servicePrice;
use Illuminate\Http\Request;
use App\Models\Service;
use App\Models\Hmo;
use App\Models\HmoScheme;
use App\Models\HmoTariff;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;
use App\Models\ApplicationStatu;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ServicePriceController extends Controller
{
    public function listServicePrice()
    {
        $pc = ServicePrice::where('status', '=', 1)->with('service')->get();

        return Datatables::of($pc)
            ->addIndexColumn()
            ->editColumn('service', function ($pc) {
                return ($pc->service->service_name);
            })
            ->rawColumns(['service'])

            ->make(true);
    }

    public function index()
    {
        try {
            $service_id = request()->get('service_id');
            $service     = Service::find($service_id);
            $application = ApplicationStatu::whereId(1)->first();
            return view('admin.service_prices.create', compact('service', 'application'));
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
        return view('admin.service_prices.pricelist');
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
        try {
            $rules = [
                'service_id' => 'required|max:100',
                'price'    => 'required|max:11',
                'buy_price' => 'required'
            ];

            $v = validator()->make($request->all(), $rules);

            if ($v->fails()) {
                $msg = 'Please cheak Your Inputs .';
                //flash($msg, 'danger');
                return redirect()->back()->withInput()->with('errors', $v->messages()->all())->withInput();
            } else {
                $myprice                 = new ServicePrice();
                $myprice->service_id     = $request->service_id;
                $myprice->cost_price     = $request->buy_price;
                $myprice->sale_price     = $request->price;
                $myprice->max_discount   = $request->max_discount ?? 0;
                $myprice->status         = 1;

                if ($myprice->save()) {
                    $assing_price = Service::find($request->service_id);
                    $assing_price->price_assign = 1;
                    $assing_price->update();
                    $msg = 'price for ' . $assing_price->service_name . ' was saved successfully.';
                    // flash($msg, 'success');
                    return redirect(route('services.index'))->withMessage($msg)->withMessageType('success')->with($msg);
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
            $service     = Service::whereId($id)->first();
            return view('admin.service_prices.newprice', compact('service'));
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
            $data = ServicePrice::with('service')->whereService_id($id)->first();
            if (empty($data)) {
                return redirect(route('service-prices.index', ['service_id' => $id]));
            }

            // Load HMO schemes with their HMOs and tariff stats for this service
            $serviceId = $data->service_id;

            $schemes = HmoScheme::with(['hmos' => function ($q) {
                $q->where('status', 1);
            }])->get();

            $tariffs = HmoTariff::where('service_id', $serviceId)
                ->whereNull('product_id')
                ->get()
                ->keyBy('hmo_id');

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

            return view('admin.service_prices.edit', compact(
                'data', 'schemeSummary', 'standaloneData', 'totalHmoCount'
            ));
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
                'cost_price' => 'required|max:11',
                'price'    => 'required|max:11'
            ];

            $v = validator()->make($request->all(), $rules);

            if ($v->fails()) {
                $msg = 'Please cheak Your Inputs .';
                return redirect()->back()->withInput()->with('errors', $v->messages()->all())->withInput();
            } else {
                $myprice               =  ServicePrice::find($id);
                $myprice->cost_price   = $request->cost_price;
                $myprice->sale_price   = $request->price;
                $myprice->max_discount = $request->max_discount ?? 0;
                $myprice->status       = 1;

                if ($myprice->update()) {
                    // ── Tariff propagation (only if user opted in) ──
                    $tariffMsg = '';
                    $syncPayable = $request->has('sync_payable');
                    $syncClaims  = $request->has('sync_claims');

                    if ($syncPayable || $syncClaims) {
                        $tariffMsg = $this->propagateServiceTariffs(
                            $myprice->service_id,
                            $syncPayable ? (float) $request->new_payable_amount : null,
                            $syncClaims  ? (float) $request->new_claims_amount : null,
                            $request->input('tariff_scope', 'none'),
                            $request->input('selected_scheme_ids', []),
                            $request->input('selected_hmo_ids', []),
                            $request->boolean('override_manual')
                        );
                    }

                    $msg = "Price for [".$myprice->service->service_name."] was updated successfully";
                    if ($tariffMsg) {
                        $msg .= ' ' . $tariffMsg;
                    }

                    return redirect(route('services.index'))->withMessage($msg)->withMessageType('success')->with($msg);
                } else {
                    $msg = 'Something is went wrong. Please try again later, information not save.';
                    return redirect()->back()->withInput()->withInput();
                }
            }
        } catch (\Exception $e) {
            Log::error('ServicePriceController@update: ' . $e->getMessage());
            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage());
        }
    }

    /**
     * Propagate tariff updates for a service to selected HMOs.
     */
    private function propagateServiceTariffs(
        int $serviceId,
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
                    ->where('service_id', $serviceId)
                    ->whereNull('product_id')
                    ->first();

                if ($tariff) {
                    $changes = [];

                    if ($newPayable !== null) {
                        if (!$overrideManual && (float) $tariff->payable_amount > 0) {
                            $skipped++;
                            if ($newClaims !== null) {
                                $tariff->update(['claims_amount' => $newClaims]);
                                $updated++;
                                $skipped--;
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
                    HmoTariff::create([
                        'hmo_id'         => $hmoId,
                        'product_id'     => null,
                        'service_id'     => $serviceId,
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
            Log::info("ServicePriceController tariff propagation for service {$serviceId}: {$result}");
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("ServicePriceController tariff propagation failed for service {$serviceId}: " . $e->getMessage());
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
     * Show the HMO tariff view for a service.
     */
    public function tariffView($id)
    {
        try {
            $service = \App\Models\Service::findOrFail($id);
            $price = \App\Models\ServicePrice::where('service_id', $id)->first();
            $salePrice = $price ? (float) $price->sale_price : 0;

            $schemes = HmoScheme::with(['hmos' => fn($q) => $q->where('status', 1)])->get();
            $tariffs = HmoTariff::where('service_id', $id)->whereNull('product_id')->get()->keyBy('hmo_id');

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
                'itemName' => $service->service_name,
                'itemType' => 'service',
                'itemId' => $id,
                'salePrice' => $salePrice,
                'schemeSummary' => $schemeSummary,
                'standaloneData' => $standaloneData,
                'totalHmoCount' => Hmo::where('status', 1)->count(),
                'backUrl' => route('services.index'),
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
