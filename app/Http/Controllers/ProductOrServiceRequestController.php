<?php

namespace App\Http\Controllers;

use App\Models\DoctorQueue;
use App\Models\Hmo;
use App\Models\patient;
use App\Models\ProductOrServiceRequest;
use App\Models\Staff;
use App\Models\VitalSign;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helpers\HmoHelper;
use Yajra\DataTables\DataTables;

class ProductOrServiceRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('admin.product_or_service_request.index');
    }

    public function productOrServicesRequestersList(Request $request, $patient_user_id = null)
    {
        // Extract date filters only when provided (avoid default date clipping)
        $startDateInput = $request->input('start_date');
        $endDateInput = $request->input('end_date');

        // Base query: unpaid and not invoiced
        $query = ProductOrServiceRequest::query()
            ->whereNull('payment_id')
            ->whereNull('invoice_id');

        // Apply date filtering only if both dates are supplied
        if (!empty($startDateInput) && !empty($endDateInput)) {
            $startDate = Carbon::parse($startDateInput)->startOfDay();
            $endDate = Carbon::parse($endDateInput)->endOfDay();
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        if ($patient_user_id === null) {
            // Aggregate unpaid items by patient
            $req = $query
                ->select([
                    'user_id',
                    DB::raw('COUNT(*) as pending_items'),
                    DB::raw('SUM(CASE WHEN claims_amount > 0 THEN 1 ELSE 0 END) as pending_claims'),
                    DB::raw('MAX(created_at) as last_created'),
                ])
                ->groupBy('user_id')
                ->orderByDesc('last_created')
                ->get();

            // Preload patient + HMO data to avoid N+1
            $patients = patient::with('hmo')
                ->whereIn('user_id', $req->pluck('user_id'))
                ->get()
                ->keyBy('user_id');

            return Datatables::of($req)
                ->addIndexColumn()
                ->addColumn('show', function ($r) {
                    $claimBadge = ($r->pending_claims ?? 0) > 0
                        ? " <span class='badge bg-warning text-dark'>HMO claims pending</span>"
                        : '';
                    return "<button type='button' class='btn btn-info btn-sm btn-pay' data-user='{$r->user_id}'><i class='fa fa-credit-card'></i> Pay</button>{$claimBadge}";
                })
                ->addColumn('pending', fn($r) => $r->pending_items)
                ->addColumn('claims', fn($r) => $r->pending_claims ?? 0)
                ->addColumn('patient', fn($r) => userfullname($r->user_id))
                ->addColumn('file_no', function ($r) use ($patients) {
                    $p = $patients->get($r->user_id);
                    return $p->file_no ?? 'N/A';
                })
                ->addColumn('hmo', function ($r) use ($patients) {
                    $p = $patients->get($r->user_id);
                    return optional($p?->hmo)->name ?? 'N/A';
                })
                ->addColumn('hmo_no', function ($r) use ($patients) {
                    $p = $patients->get($r->user_id);
                    return $p->hmo_no ?? 'N/A';
                })
                ->rawColumns(['show'])
                ->make(true);
        } else {
            $req = $query
                ->where('user_id', $patient_user_id)
                ->orderBy('created_at', 'DESC')
                ->get();

            return Datatables::of($req)
                ->addIndexColumn()
                ->addColumn('show', function ($r) {
                    $url = route('servicess', $r->user_id);
                    return "<a href='$url' class='btn btn-info btn-sm'><i class='fa fa-eye'></i> View</a>";
                })
                ->editColumn('service_id', function ($r) {
                    if (null != $r->service_id) {
                        $str = "<b>Service: </b>" . $r->service->service_name;
                        $str .= "<br><b>Price: </b>" . $r->service->price->sale_price;
                        return $str;
                    } else {
                        return "N/A";
                    }
                })
                ->editColumn('product_id', function ($r) {
                    if (null != $r->product_id) {
                        $str = "<b>Product: </b>" . $r->product->product_name;
                        $str .= "<br><b>Price: </b>" . $r->product->price->current_sale_price;
                        return $str;
                    } else {
                        return "N/A";
                    }
                })
                ->rawColumns(['show', 'service_id', 'product_id'])
                ->make(true);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {}

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // dd($request->all());
        try {
            if ($request->is_consultation == 1) {
                if (count(array_filter($request->service_id)) < 1) {
                    $msg = 'Please select a service for at least one of the listed patients';

                    return redirect()->back()->withInput()->withMessage($msg)->withMessageType('warning');
                } else {
                    $service_ids = ($request->service_id);
                    // sort($service_ids);
                    $clinic_ids = ($request->clinic_id);
                    // sort($clinic_ids);
                    $user_ids = ($request->user_id);
                    // sort($user_ids);
                    $doctor_ids = ($request->doctor_id);
                    // sort($doctor_ids);

                    if (isset($request->request_vitals)) {
                        $request_vitals = ($request->request_vitals);
                        sort($request_vitals);
                    } else {
                        $request_vitals = [];
                    }

                    if (count($clinic_ids) == count($service_ids)) {
                        DB::beginTransaction();
                        for ($i = 0; $i < count($service_ids); ++$i) {
                            if ($service_ids[$i] != null) {
                                // dd($user_ids[$i]);
                                $req = new ProductOrServiceRequest();
                                $req->service_id = $service_ids[$i];
                                $req->user_id = $user_ids[$i];
                                $req->staff_user_id = Auth::id();

                                // Apply HMO tariff if patient has HMO
                                try {
                                    $p = patient::where('user_id', $user_ids[$i])->first();
                                    if ($p) {
                                        $hmoData = HmoHelper::applyHmoTariff(
                                            $p->id,
                                            null,
                                            $service_ids[$i]
                                        );
                                        if ($hmoData) {
                                            $req->payable_amount = $hmoData['payable_amount'];
                                            $req->claims_amount = $hmoData['claims_amount'];
                                            $req->coverage_mode = $hmoData['coverage_mode'];
                                            $req->validation_status = $hmoData['validation_status'];
                                        }
                                    }
                                } catch (\Exception $e) {
                                    DB::rollBack();
                                    return redirect()->back()
                                        ->withErrors(['error' => 'HMO Tariff Error: ' . $e->getMessage()])
                                        ->withInput();
                                }

                                $req->save();

                                $queue = new DoctorQueue();
                                if (isset($doctor_ids[$i])) {
                                    $p = patient::where('user_id', $user_ids[$i])->first();
                                    // die($p);
                                    $d = Staff::find($doctor_ids[$i]);
                                    $r = Staff::where('user_id', Auth::id())->first();
                                    $queue->patient_id = $p->id;
                                    $queue->clinic_id = $clinic_ids[$i];
                                    $queue->receptionist_id = $r->id;
                                    $queue->staff_id = $d->id ?? null;
                                    $queue->request_entry_id = $req->id;
                                } else {
                                    $p = patient::where('user_id', $user_ids[$i])->first();
                                    $r = Staff::where('user_id', Auth::id())->first();
                                    $queue->patient_id = $p->id;
                                    $queue->clinic_id = $clinic_ids[$i];
                                    $queue->receptionist_id = $r->id;
                                    // $queue->staff_id = $d->id ?? null;
                                    $queue->request_entry_id = $req->id;
                                }

                                $queue->save();

                                // if (isset($request_vitals[$i])) {
                                //     $vitalSign = new VitalSign;
                                //     $vitalSign->requested_by = Auth::id();
                                //     $vitalSign->patient_id = $request->patient_id;
                                //     $vitalSign->save();
                                // }
                            } else {
                                continue;
                            }
                        }
                        DB::commit();
                        $msg = 'Request(s) saved successfully';

                        return redirect()->route('add-to-queue')->withMessage($msg)->withMessageType('success')->withInput();
                    } else {
                        $msg = 'Please specify a clinic for all patients for whom you specified a service';

                        return redirect()->back()->withInput()->withMessage($msg)->withMessageType('warning')->withInput();
                    }
                }
            } else {
                // if (count(array_filter($request->service_id)) < 1) {
                //     $msg = "Please select a service for at least one of the listed patients";
                //     return redirect()->back()->withInput()->withMessage($msg)->withMessageType('warning');
                // } else {
                //     $service_ids = array_filter(($request->service_id));
                //     sort($service_ids);
                //     $user_ids  = array_filter($request->user_id);
                //     sort($user_ids);
                //     for ($i = 0; $i < count($service_ids); $i++) {
                //         $req = new ProductOrServiceRequest;
                //         $req->service_id = $service_ids[$i];
                //         $req->user_id = $user_ids[$i];
                //         $req->staff_user_id = Auth::id();
                //         if ($req->save()) {
                //             $msg = "Request(s) saved successfully";
                //             return redirect()->route('add-to-queue')->withMessage($msg)->withMessageType('success')->withInput();
                //         } else {
                //             $msg = "An error occured while saving the request, please try again later";
                //             return redirect()->back()->withInput()->withMessage($msg)->withMessageType('danger')->withInput();
                //         }
                //     }
                // }
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage(), ['exception' => $e]);

            return redirect()->back()->withInput()->withMessage('An error occurred ' . $e->getMessage() . 'line:' . $e->getLine());
        }
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(ProductOrServiceRequest $productOrServiceRequest) {}

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(ProductOrServiceRequest $productOrServiceRequest) {}

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ProductOrServiceRequest $productOrServiceRequest) {}

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(ProductOrServiceRequest $productOrServiceRequest) {}
}
