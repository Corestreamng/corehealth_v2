<?php

namespace App\Http\Controllers;

use App\Helpers\HmoHelper;

use App\Models\AdmissionRequest;
use App\Models\Bed;
use App\Models\patient;
use App\Models\Hmo;
use App\Models\ProductOrServiceRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdmissionRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('admin.admission_requests.index');
    }

    public function myAdmissionRequests(Request $request)
    {
        // Get start and end dates from request
        $startDate = Carbon::parse($request->input('start_date'));
        $endDate = Carbon::parse($request->input('end_date'));

        // Build the query with date range filtering
        $query = AdmissionRequest::where('discharged', 0)
            ->where('status', 1)
            ->where('doctor_id', Auth::id());

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()]);
        }

        $req = $query->orderBy('created_at', 'DESC')->get();

        return Datatables::of($req)
            ->addIndexColumn()
            ->addColumn('show', function ($r) {
                $url = route('patient.show', [$r->patient_id, 'section' => 'addmissionsCardBody']);
                return '<a href="' . $url . '" class="btn btn-success btn-sm" ><i class="fa fa-street-view"></i> View</a>';
            })
            ->addColumn('patient', function ($r) {
                return userfullname($r->patient->user_id);
            })
            ->addColumn('file_no', function ($r) {
                $p = patient::where('user_id', $r->patient->user_id)->first();
                return $p->file_no ?? 'N/A';
            })
            ->addColumn('hmo', function ($r) {
                $p = patient::where('user_id', $r->patient->user_id)->first();
                $hmo = Hmo::find($p->hmo_id);
                return $hmo ? $hmo->name : 'N/A';
            })
            ->addColumn('hmo_no', function ($r) {
                $p = patient::where('user_id', $r->patient->user_id)->first();
                return $p->hmo_no ?? 'N/A';
            })
            ->editColumn('bed_id', function ($r) {
                $str = "<small>";
                $str .= "<b>Bed:</b> " . ($r->bed ? $r->bed->name : 'N/A') . " <b>Ward:</b> " . ($r->bed ? $r->bed->ward : 'N/A') . " <b>Unit:</b> " . ($r->bed->unit ?? 'N/A') . "<br>";
                $str .= "<b>Assigned By:</b> " . ($r->bed_assigned_by ? userfullname($r->bed_assigned_by) : 'N/A') . "<br>";
                $str .= "<b>Date Assigned:</b> " . ($r->bed_assign_date ? date('h:i a D M j, Y', strtotime($r->bed_assign_date)) : 'N/A') . "<br>";
                $str .= "<b>Discharged By:</b> " . ($r->discharged_by ? userfullname($r->discharged_by) : 'N/A') . " (" . ($r->discharge_date ? date('h:i a D M j, Y', strtotime($r->discharge_date)) : 'N/A') . ")<br>";
                $str .= "</small>";
                return $str;
            })
            ->editColumn('doctor_id', function ($r) {
                return $r->doctor_id ? userfullname($r->doctor_id) : 'N/A';
            })
            ->editColumn('billed_by', function ($r) {
                $str = "<small>";
                $str .= "<b>Billed by:</b> " . ($r->billed_by ? userfullname($r->billed_by) : 'N/A') . "<br>";
                $str .= "<b>Date:</b> " . ($r->billed_date ? date('h:i a D M j, Y', strtotime($r->billed_date)) : 'N/A') . "<br>";
                $str .= "</small>";
                return $str;
            })
            ->rawColumns(['show', 'bed_id', 'billed_by'])
            ->make(true);
    }
    public function admissionRequests(Request $request)
    {
        // Get start and end dates from request
        $startDate = Carbon::parse($request->input('start_date'));
        $endDate = Carbon::parse($request->input('end_date'));

        // Build the query with date range filtering
        $query = AdmissionRequest::where('discharged', 0)
            ->where('status', 1);

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()]);
        }

        $req = $query->orderBy('created_at', 'DESC')->get();

        return Datatables::of($req)
            ->addIndexColumn()
            ->addColumn('show', function ($r) {
                $url = route('patient.show', [$r->patient_id, 'section' => 'addmissionsCardBody']);
                return '<a href="' . $url . '" class="btn btn-success btn-sm" ><i class="fa fa-street-view"></i> View</a>';
            })
            ->addColumn('patient', function ($r) {
                return userfullname($r->patient->user_id);
            })
            ->addColumn('file_no', function ($r) {
                $p = patient::where('user_id', $r->patient->user_id)->first();
                return $p->file_no ?? 'N/A';
            })
            ->addColumn('hmo', function ($r) {
                $p = patient::where('user_id', $r->patient->user_id)->first();
                $hmo = Hmo::find($p->hmo_id);
                return $hmo ? $hmo->name : 'N/A';
            })
            ->addColumn('hmo_no', function ($r) {
                $p = patient::where('user_id', $r->patient->user_id)->first();
                return $p->hmo_no ?? 'N/A';
            })
            ->editColumn('bed_id', function ($r) {
                $str = "<small>";
                $str .= "<b>Bed:</b> " . ($r->bed ? $r->bed->name : 'N/A') . " <b>Ward:</b> " . ($r->bed ? $r->bed->ward : 'N/A') . " <b>Unit:</b> " . ($r->bed->unit ?? 'N/A') . "<br>";
                $str .= "<b>Assigned By:</b> " . ($r->bed_assigned_by ? userfullname($r->bed_assigned_by) : 'N/A') . "<br>";
                $str .= "<b>Date Assigned:</b> " . ($r->bed_assign_date ? date('h:i a D M j, Y', strtotime($r->bed_assign_date)) : 'N/A') . "<br>";
                $str .= "<b>Discharged By:</b> " . ($r->discharged_by ? userfullname($r->discharged_by) : 'N/A') . " (" . ($r->discharge_date ? date('h:i a D M j, Y', strtotime($r->discharge_date)) : 'N/A') . ")<br>";
                $str .= "</small>";
                return $str;
            })
            ->editColumn('doctor_id', function ($r) {
                return $r->doctor_id ? userfullname($r->doctor_id) : 'N/A';
            })
            ->editColumn('billed_by', function ($r) {
                $str = "<small>";
                $str .= "<b>Billed by:</b> " . ($r->billed_by ? userfullname($r->billed_by) : 'N/A') . "<br>";
                $str .= "<b>Date:</b> " . ($r->billed_date ? date('h:i a D M j, Y', strtotime($r->billed_date)) : 'N/A') . "<br>";
                $str .= "</small>";
                return $str;
            })
            ->rawColumns(['show', 'bed_id', 'billed_by'])
            ->make(true);
    }


    public function patientAdmissionRequests($patient_id)
    {
        $req = AdmissionRequest::where('status', 1)->where('patient_id', $patient_id)->orderBy('created_at', 'DESC')->get();

        return Datatables::of($req)
            ->addColumn('info', function ($r) {
                $hosColor = appsettings('hos_color', '#007bff');
                $borderColor = $r->discharged ? '#6c757d' : $hosColor;

                $str = '<div class="card-modern mb-3" style="border-left: 4px solid ' . $borderColor . ';">';
                $str .= '<div class="card-body p-3">';

                // Header with status badge and date
                $str .= '<div class="d-flex justify-content-between align-items-start mb-3">';
                $str .= '<h6 class="mb-0"><i class="fa fa-bed"></i> Admission #' . $r->id . '</h6>';
                if ($r->discharged) {
                    $str .= '<span class="badge bg-secondary"><i class="fa fa-check"></i> Discharged</span>';
                } else {
                    $str .= '<span class="badge bg-success"><i class="fa fa-heartbeat"></i> Active</span>';
                }
                $str .= '</div>';

                // Admission Details Row
                $str .= '<div class="row mb-3">';

                // Left Column - Admission Info
                $str .= '<div class="col-md-6">';
                $str .= '<div class="mb-3">';
                $str .= '<small class="text-muted d-block mb-1"><i class="fa fa-calendar"></i> ADMISSION DETAILS</small>';
                $str .= '<div class="small mb-1"><strong>Date:</strong> ' . date('M j, Y h:i A', strtotime($r->created_at)) . '</div>';
                $str .= '<div class="small mb-1"><strong>Admitted By:</strong> ' . ($r->doctor_id ? userfullname($r->doctor_id) : 'N/A') . '</div>';
                $priorityClass = $r->priority == 'emergency' ? 'danger' : ($r->priority == 'urgent' ? 'warning' : 'info');
                $str .= '<div class="small mb-1"><strong>Priority:</strong> <span class="badge bg-' . $priorityClass . '">' . ucfirst($r->priority ?? 'routine') . '</span></div>';
                if ($r->admission_reason) {
                    $str .= '<div class="small mb-1"><strong>Reason:</strong> ' . $r->admission_reason . '</div>';
                }
                $str .= '</div>';
                $str .= '</div>';

                // Right Column - Bed Info
                $str .= '<div class="col-md-6">';
                if ($r->bed_id) {
                    $str .= '<div class="mb-3">';
                    $str .= '<small class="text-muted d-block mb-1"><i class="fa fa-hospital"></i> BED & WARD</small>';
                    $str .= '<div class="small mb-1"><strong>Bed:</strong> ' . ($r->bed ? $r->bed->name : 'N/A') . '</div>';
                    $str .= '<div class="small mb-1"><strong>Ward:</strong> ' . ($r->bed ? $r->bed->ward : 'N/A') . '</div>';
                    $str .= '<div class="small mb-1"><strong>Unit:</strong> ' . ($r->bed ? $r->bed->unit : 'N/A') . '</div>';
                    if ($r->bed_assigned_by) {
                        $str .= '<div class="small mb-1"><strong>Assigned By:</strong> ' . userfullname($r->bed_assigned_by) . '</div>';
                    }
                    $str .= '</div>';
                } else {
                    $str .= '<div class="alert alert-warning py-2 mb-3"><small><i class="fa fa-info-circle"></i> No bed assigned yet</small></div>';
                }
                $str .= '</div>';
                $str .= '</div>'; // End row

                // Admission Notes
                if ($r->note) {
                    $str .= '<div class="alert alert-light mb-3 py-2">';
                    $str .= '<small><strong><i class="fa fa-notes-medical"></i> Admission Notes:</strong><br>' . nl2br($r->note) . '</small>';
                    $str .= '</div>';
                }

                // Discharge Details (if discharged)
                if ($r->discharged) {
                    $str .= '<div class="border-top pt-3 mb-3">';
                    $str .= '<small class="text-muted d-block mb-2"><i class="fa fa-sign-out-alt"></i> DISCHARGE INFORMATION</small>';
                    $str .= '<div class="row">';
                    $str .= '<div class="col-md-6">';
                    $str .= '<div class="small mb-1"><strong>Date:</strong> ' . ($r->discharge_date ? date('M j, Y h:i A', strtotime($r->discharge_date)) : 'N/A') . '</div>';
                    $str .= '<div class="small mb-1"><strong>Discharged By:</strong> ' . ($r->discharged_by ? userfullname($r->discharged_by) : 'N/A') . '</div>';
                    if ($r->discharge_reason) {
                        $str .= '<div class="small mb-1"><strong>Reason:</strong> ' . $r->discharge_reason . '</div>';
                    }
                    $str .= '</div>';
                    $str .= '<div class="col-md-6">';
                    if ($r->billed_by) {
                        $str .= '<div class="small mb-1"><strong>Billed By:</strong> ' . userfullname($r->billed_by) . '</div>';
                        $str .= '<div class="small mb-1"><strong>Bill Date:</strong> ' . ($r->billed_date ? date('M j, Y', strtotime($r->billed_date)) : 'N/A') . '</div>';
                    }
                    $str .= '</div>';
                    $str .= '</div>'; // End row

                    if ($r->discharge_note) {
                        $str .= '<div class="alert alert-secondary py-2 mt-2 mb-2">';
                        $str .= '<small><strong>Discharge Summary:</strong><br>' . nl2br($r->discharge_note) . '</small>';
                        $str .= '</div>';
                    }
                    if ($r->followup_instructions) {
                        $str .= '<div class="alert alert-info py-2 mb-0">';
                        $str .= '<small><strong><i class="fa fa-calendar-check"></i> Follow-up Instructions:</strong><br>' . nl2br($r->followup_instructions) . '</small>';
                        $str .= '</div>';
                    }
                    $str .= '</div>';
                }

                // Action Buttons (if not discharged)
                if (!$r->discharged) {
                    $url_ward_round = route('encounters.create', [
                        'patient_id' => $r->patient_id,
                        'queue_id' => 'ward_round',
                        'admission_req_id' => $r->id
                    ]);

                    $str .= '<div class="d-flex gap-2 flex-wrap mt-3">';
                    $str .= '<a href="' . $url_ward_round . '" class="btn btn-success btn-sm"><i class="fa fa-plus"></i> New Encounter</a>';

                    if ($r->bed_id == null) {
                        $str .= "<button type='button' class='btn btn-primary btn-sm' onclick='setBedModal(this)' data-id='$r->id' data-reassign='false'>
                            <i class='fa fa-bed'></i> Assign Bed
                        </button>";
                    }

                    if ($r->bed_id != null) {
                        $days = date_diff(date_create($r->discharge_date ?? now()), date_create($r->bed_assign_date))->days;
                        if ($days < 1) $days = 1;
                        $str .= "<button type='button' class='btn btn-info btn-sm' onclick='setBillModal(this)' data-id='$r->id' data-days='$days'
                            data-bed='<b>Bed</b>:" . $r->bed->name . " <b>Ward</b>: " . $r->bed->ward . " <b>Unit</b>: " . $r->bed->unit . "' data-price='" . $r->bed->price . "'>
                            <i class='fa fa-dollar'></i> Bill & Release Bed
                        </button>";
                    }

                    $url_discharge = route('discharge-patient', $r->id);
                    $str .= '<a href="' . $url_discharge . '" class="btn btn-warning btn-sm" onclick="return confirm(\'Are you sure you want to discharge this patient?\')">
                        <i class="fa fa-sign-out-alt"></i> Discharge
                    </a>';
                    $str .= '</div>';
                }

                $str .= '</div>'; // Close card-body
                $str .= '</div>'; // Close card

                return $str;
            })
            ->rawColumns(['info'])
            ->make(true);
    }

    public function assignBed(Request $request)
    {
        try {
            $request->validate([
                'assign_bed_req_id' => 'required',
                'assign_bed_reassign' => 'required', //redundent
                'bed_id' => 'required|exists:beds,id'
            ]);

            DB::beginTransaction();
            $admit_req = AdmissionRequest::where('id', $request->assign_bed_req_id)->first();
            $admit_req->update([
                'bed_id' => $request->bed_id,
                'bed_assign_date' => date('Y-m-d H:i:s'),
                'bed_assigned_by' => Auth::id()
            ]);
            $bed = Bed::where('id', $request->bed_id)->first();
            $bed->update([
                'occupant_id' => $admit_req->patient_id
            ]);
            $bed = Bed::where('id', $request->bed_id)->first();
            $admit_req = AdmissionRequest::where('id', $request->assign_bed_req_id)->first();
            $admit_req->update([
                'service_id' => $bed->service_id
            ]);
            DB::commit();
            return back()->withMessage('Bed Assigned')->withMessageType('success');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage(), ['exception' => $e]);
            return redirect()->back()->withMessage("An error occurred " . $e->getMessage());
        }
    }

    public function assignBill(Request $request)
    {
        try {
            // dd($request->all());
            $request->validate([
                'assign_bed_req_id' => 'required',
                'days' => 'required'
            ]);

            DB::beginTransaction();
            $admit_req = AdmissionRequest::where('id', $request->assign_bed_req_id)->first();
            // $bed = Bed::where('id', $admit_req->bed_id)->first();
            $admit_req->update([
                'billed_date' => date('Y-m-d H:i:s'),
                'billed_by' => Auth::id(),
            ]);

            $bill_req = new ProductOrServiceRequest();
            $bill_req->user_id = $admit_req->patient->user->id;
            $bill_req->staff_user_id = Auth::id();
            $bill_req->service_id = $admit_req->service_id;
            $bill_req->qty = $request->days;

            // Apply HMO tariff if patient has HMO
            try {
                $hmoData = HmoHelper::applyHmoTariff(
                    $admit_req->patient_id,
                    null,
                    $admit_req->service_id
                );
                if ($hmoData) {
                    $bill_req->payable_amount = $hmoData['payable_amount'];
                    $bill_req->claims_amount = $hmoData['claims_amount'];
                    $bill_req->coverage_mode = $hmoData['coverage_mode'];
                    $bill_req->validation_status = $hmoData['validation_status'];
                }
            } catch (\Exception $e) {
                DB::rollBack();
                return back()->withErrors(['error' => 'HMO Tariff Error: ' . $e->getMessage()]);
            }

            $bill_req->save();

            //release bed after billing
            Bed::where('id', $admit_req->bed_id)->update([
                'occupant_id' => null
            ]);

            $admit_req = AdmissionRequest::where('id', $request->assign_bed_req_id)->first();
            $admit_req->update([
                'service_request_id' => $bill_req->id,
                'bed_id' => null //once billed, the admission entry bed should be null, this will enable bed resaasignment, as bill bed will show after bed is reassigned
            ]);
            DB::commit();
            return back()->withMessage('Bill Assigned, you can proceed to make payment in the payments section')->withMessageType('success');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage(), ['exception' => $e]);
            return redirect()->back()->withMessage("An error occurred " . $e->getMessage());
        }
    }

    public function dischargePatient($admission_req_id)
    {
        try {
            DB::beginTransaction();
            $req = AdmissionRequest::where('id', $admission_req_id)->first();

            // Check for unpaid/unvalidated bed bills
            $unpaidBills = ProductOrServiceRequest::where('user_id', $req->patient->user->id)
                ->where('service_id', $req->service_id)
                ->whereNull('payment_id')
                ->whereDate('created_at', '>=', $req->bed_assign_date)
                ->count();

            if ($unpaidBills > 0) {
                DB::rollBack();
                return back()->with([
                    'message' => "Cannot discharge patient: {$unpaidBills} unpaid bed bill(s) found. Please process all payments before discharge.",
                    'message_type' => 'error'
                ]);
            }

            // Check for pending/rejected HMO validations
            $invalidBills = ProductOrServiceRequest::where('user_id', $req->patient->user->id)
                ->where('service_id', $req->service_id)
                ->whereDate('created_at', '>=', $req->bed_assign_date)
                ->where(function($q) {
                    $q->where('validation_status', 'pending')
                      ->orWhere('validation_status', 'rejected');
                })
                ->where('claims_amount', '>', 0)
                ->count();

            if ($invalidBills > 0) {
                DB::rollBack();
                return back()->with([
                    'message' => "Cannot discharge patient: {$invalidBills} bed bill(s) require HMO validation. Please validate all claims before discharge.",
                    'message_type' => 'error'
                ]);
            }

            $req->update([
                'discharged' => true,
                'discharged_by' => Auth::id(),
                'discharge_date' => date('Y-m-d H:i:s'),
            ]);
            Bed::where('id', $req->bed_id)->update([
                'occupant_id' => null
            ]);
            DB::commit();
            return back()->withMessage('Patient Discharged')->withMessageType('success');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage(), ['exception' => $e]);
            return redirect()->back()->withMessage("An error occurred " . $e->getMessage());
        }
    }

    public function dischargePatientApi(Request $request, $admission_req_id)
    {
        $request->validate([
            'discharge_reason' => 'required|string',
            'discharge_note' => 'required|string',
        ]);

        try {
            DB::beginTransaction();
            $req = AdmissionRequest::where('id', $admission_req_id)->first();

            if (!$req) {
                return response()->json(['message' => 'Admission request not found'], 404);
            }

            // Check for unpaid/unvalidated bed bills
            $unpaidBills = ProductOrServiceRequest::where('user_id', $req->patient->user->id)
                ->where('service_id', $req->service_id)
                ->whereNull('payment_id')
                ->whereDate('created_at', '>=', $req->bed_assign_date)
                ->count();

            if ($unpaidBills > 0) {
                DB::rollBack();
                return response()->json([
                    'message' => "Cannot discharge patient: {$unpaidBills} unpaid bed bill(s) found. Please process all payments before discharge."
                ], 422);
            }

            // Check for pending/rejected HMO validations
            $invalidBills = ProductOrServiceRequest::where('user_id', $req->patient->user->id)
                ->where('service_id', $req->service_id)
                ->whereDate('created_at', '>=', $req->bed_assign_date)
                ->where(function($q) {
                    $q->where('validation_status', 'pending')
                      ->orWhere('validation_status', 'rejected');
                })
                ->where('claims_amount', '>', 0)
                ->count();

            if ($invalidBills > 0) {
                DB::rollBack();
                return response()->json([
                    'message' => "Cannot discharge patient: {$invalidBills} bed bill(s) require HMO validation. Please validate all claims before discharge."
                ], 422);
            }

            $req->update([
                'discharged' => true,
                'discharged_by' => Auth::id(),
                'discharge_date' => date('Y-m-d H:i:s'),
                'discharge_reason' => $request->discharge_reason,
                'discharge_note' => $request->discharge_note,
                'followup_instructions' => $request->followup_instructions,
            ]);

            if ($req->bed_id) {
                Bed::where('id', $req->bed_id)->update([
                    'occupant_id' => null,
                    'status' => 'available'
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Patient discharged successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
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
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'note' => 'required|string',
            'admission_reason' => 'nullable|string',
            'bed_id' => 'nullable|exists:beds,id',
            'priority' => 'nullable|in:routine,urgent,emergency',
        ]);

        try {
            DB::beginTransaction();

            $admissionRequest = AdmissionRequest::create([
                'patient_id' => $request->patient_id,
                'encounter_id' => $request->encounter_id,
                'doctor_id' => $request->doctor_id ?? Auth::id(),
                'note' => $request->note,
                'admission_reason' => $request->admission_reason,
                'bed_id' => $request->bed_id,
                'priority' => $request->priority ?? 'routine',
                'status' => 1,
            ]);

            // If bed is assigned, update bed occupant
            if ($request->bed_id) {
                Bed::where('id', $request->bed_id)->update([
                    'occupant_id' => $request->patient_id,
                    'status' => 'occupied'
                ]);
                $admissionRequest->update([
                    'bed_assign_date' => now(),
                    'bed_assigned_by' => Auth::id()
                ]);
            }

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Admission request created successfully'], 201);
            }

            return back()->withMessage('Admission request created successfully')->withMessageType('success');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage(), ['exception' => $e]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Error creating admission request: ' . $e->getMessage()], 500);
            }

            return redirect()->back()->withMessage('An error occurred: ' . $e->getMessage())->withMessageType('error');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\AdmissionRequest  $admissionRequest
     * @return \Illuminate\Http\Response
     */
    public function show(AdmissionRequest $admissionRequest)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\AdmissionRequest  $admissionRequest
     * @return \Illuminate\Http\Response
     */
    public function edit(AdmissionRequest $admissionRequest)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\AdmissionRequest  $admissionRequest
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, AdmissionRequest $admissionRequest)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\AdmissionRequest  $admissionRequest
     * @return \Illuminate\Http\Response
     */
    public function destroy(AdmissionRequest $admissionRequest)
    {
        //
    }

    /**
     * Get HMO coverage breakdown for a bed assignment
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBedCoverage(Request $request)
    {
        try {
            $request->validate([
                'patient_id' => 'required|exists:patients,id',
                'bed_id' => 'required|exists:beds,id'
            ]);

            $patient = patient::find($request->patient_id);
            $bed = Bed::with('service')->find($request->bed_id);

            if (!$bed->service_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'This bed does not have an associated service'
                ], 400);
            }

            // Get bed price
            $bedPrice = $bed->service->price->sale_price ?? 0;

            // Apply HMO tariff if patient has HMO
            $coverage = [
                'has_hmo' => false,
                'bed_price' => $bedPrice,
                'payable_amount' => $bedPrice,
                'claims_amount' => 0,
                'coverage_mode' => 'none',
                'validation_status' => 'n/a',
                'hmo_name' => null,
                'requires_validation' => false
            ];

            if ($patient->hmo_id) {
                try {
                    $hmoData = HmoHelper::applyHmoTariff(
                        $patient->id,
                        null,
                        $bed->service_id
                    );

                    if ($hmoData) {
                        $coverage = [
                            'has_hmo' => true,
                            'bed_price' => $bedPrice,
                            'payable_amount' => $hmoData['payable_amount'],
                            'claims_amount' => $hmoData['claims_amount'],
                            'coverage_mode' => $hmoData['coverage_mode'],
                            'validation_status' => $hmoData['validation_status'],
                            'hmo_name' => $patient->hmo->name ?? 'Unknown',
                            'requires_validation' => $hmoData['claims_amount'] > 0
                        ];
                    }
                } catch (\Exception $e) {
                    // If HMO tariff fails, return cash pricing
                    $coverage['error_message'] = 'HMO tariff not available: ' . $e->getMessage();
                }
            }

            return response()->json([
                'success' => true,
                'coverage' => $coverage
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching bed coverage: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Error fetching coverage information: ' . $e->getMessage()
            ], 500);
        }
    }
}
