<?php

namespace App\Http\Controllers;

use App\Models\AdmissionRequest;
use App\Models\Bed;
use App\Models\patient;
use App\Models\Hmo;
use App\Models\ProductOrServiceRequest;
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

    public function myAdmissionRequests()
    {
        $req = AdmissionRequest::where('discharged', 0)->where('status', 1)->where('doctor_id', Auth::id())->orderBy('created_at', 'DESC')->get();

        return Datatables::of($req)
            ->addIndexColumn()
            ->addColumn('show', function ($r) {
                $url =  route('patient.show', [$r->patient_id, 'section' => 'addmissionsCardBody']);
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
                return (($hmo) ? $hmo->name : 'N/A');
            })
            ->addColumn('hmo_no', function ($r) {
                $p = patient::where('user_id', $r->patient->user_id)->first();
                return $p->hmo_no ?? 'N/A';
            })
            ->editColumn('bed_id', function ($r) {
                $str = "<small>";
                $str .= "<b >Bed</b>: " . (($r->bed) ? $r->bed->name : 'N/A') . " <b>Ward</b>: " . (($r->bed) ? $r->bed->ward : "N/A") . " <b>Unit</b>: " . ($r->bed->unit ?? "N/A") . "<br>";
                $str .= "<b >Assigned By</b>: " . (($r->bed_assigned_by) ? (userfullname($r->bed_assigned_by)) : "N/A");
                $str .= "<br> <b>Date Assigned</b>: " . (($r->bed_assign_date) ? date('h:i a D M j, Y', strtotime($r->bed_assign_date)) : 'N/A') . "<br>";
                $str .= "<br> <b>Discharged By </b>: " . (($r->discharged_by) ? (userfullname($r->discharged_by)) : "N/A")." (".(($r->discharge_date) ? date('h:i a D M j, Y', strtotime($r->discharge_date)) : 'N/A') . ")<br>";
                $str .= "</small>";
                return $str;
            })
            ->editColumn('doctor_id', function ($r) {
                return ($r->doctor_id) ? userfullname($r->doctor_id) : 'N/A';
            })
            ->editColumn('billed_by', function ($r) {
                $str = "<small>";
                $str .= "<b >Biiled by: </b>" . (($r->billed_by) ? userfullname($r->billed_by) : 'N/A') . "<br>Date: " . (($r->billed_date) ? date('h:i a D M j, Y', strtotime($r->billed_date)) : 'N/A') . "<br>";
                $str .= "</small>";
                return $str;
            })
            ->rawColumns(['show', 'bed_id', 'billed_by'])
            ->make(true);
    }

    public function admissionRequests()
    {
        // DB::statement("SET SQL_MODE=''");//disable sql strict mode to allow groupby query
        $req = AdmissionRequest::where('discharged', 0)->where('status', 1)->orderBy('created_at', 'DESC')->get();

        return Datatables::of($req)
            ->addIndexColumn()
            ->addColumn('show', function ($r) {
                $url =  route('patient.show', [$r->patient_id, 'section' => 'addmissionsCardBody']);
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
                return (($hmo) ? $hmo->name : 'N/A');
            })
            ->addColumn('hmo_no', function ($r) {
                $p = patient::where('user_id', $r->patient->user_id)->first();
                return $p->hmo_no ?? 'N/A';
            })
            ->editColumn('bed_id', function ($r) {
                $str = "<small>";
                $str .= "<b >Bed</b>: " . (($r->bed) ? $r->bed->name : 'N/A') . " <b>Ward</b>: " . (($r->bed) ? $r->bed->ward : "N/A") . " <b>Unit</b>: " . ($r->bed->unit ?? "N/A") . "<br>";
                $str .= "<b >Assigned By</b>: " . (($r->bed_assigned_by) ? (userfullname($r->bed_assigned_by)) : "N/A");
                $str .= "<br> <b>Date Assigned</b>: " . (($r->bed_assign_date) ? date('h:i a D M j, Y', strtotime($r->bed_assign_date)) : 'N/A') . "<br>";
                $str .= "<br> <b>Discharged By </b>: " . (($r->discharged_by) ? (userfullname($r->discharged_by)) : "N/A")." (".(($r->discharge_date) ? date('h:i a D M j, Y', strtotime($r->discharge_date)) : 'N/A') . ")<br>";
                $str .= "</small>";
                return $str;
            })
            ->editColumn('doctor_id', function ($r) {
                return ($r->doctor_id) ? userfullname($r->doctor_id) : 'N/A';
            })
            ->editColumn('billed_by', function ($r) {
                $str = "<small>";
                $str .= "<b >Biiled by: </b>" . (($r->billed_by) ? userfullname($r->billed_by) : 'N/A') . "<br>Date: " . (($r->billed_date) ? date('h:i a D M j, Y', strtotime($r->billed_date)) : 'N/A') . "<br>";
                $str .= "</small>";
                return $str;
            })
            ->rawColumns(['show', 'bed_id', 'billed_by'])
            ->make(true);
    }

    public function patientAdmissionRequests($patient_id)
    {
        // DB::statement("SET SQL_MODE=''");//disable sql strict mode to allow groupby query
        $req = AdmissionRequest::where('status', 1)->where('patient_id', $patient_id)->orderBy('created_at', 'DESC')->get();

        return Datatables::of($req)
            ->addIndexColumn()
            ->addColumn('show', function ($r) {
                $url_ward_round =  route(
                    'encounters.create',
                    [
                        'patient_id' => $r->patient_id,
                        'queue_id' => 'ward_round',
                        'admission_req_id' => $r->id
                    ]
                );
                $url_discharge = route('discharge-patient', $r->id);
                $str = '';
                if ($r->discharged == false ) {
                    $str .= '<a href="' . $url_ward_round . '" class="btn btn-success btn-sm" ><i class="fa fa-plus"></i> Encounter</a><br>';
                }
                if ($r->discharged == false && $r->bed_id == null) {
                    $str .= "<br>
                    <button type='button' class='btn btn-primary' onclick='setBedModal(this)' data-id='$r->id' data-reassign='false'>
                        <i class='fa fa-bed'></i> Assign/ Reassign Bed
                    </button><br>";
                }


                if ($r->bed_id != null && $r->discharged == false) {
                    $days = date_diff(date_create($r->discharge_date), date_create($r->bed_assign_date))->days;
                    if ($days < 1) {
                        $days = 1;
                    }
                    $str .= "<br>
                    <button type='button' class='btn btn-primary' onclick='setBillModal(this)' data-id='$r->id' data-days = '$days'
                    data-bed='<b>Bed</b>:" . $r->bed->name . " <b>Ward</b>: " .  $r->bed->ward . " <b>Unit</b>: " . $r->bed->unit . "' data-price='" . $r->bed->price . "'>
                        <i class='fa fa-dollar'></i> Bill/ Release Bed
                    </button><br>";
                }

                if ($r->discharged == false) {
                    $str .= '<br><a href="' . $url_discharge . '" class="btn btn-danger btn-sm" ><i class="fa fa-minus"></i> Discharge</a><br>';
                }

                if ($r->discharged == true && $r->bed_id != null && $r->billed_by != null) {
                    $str .= '<br><a href="#" class="btn btn-secondary btn-sm" ><i class="fa fa-minus"></i> Discharged</a><br>';
                }

                return $str;
            })
            ->editColumn('bed_id', function ($r) {
                $str = "<small>";
                $str .= "<b >Bed</b>: " . (($r->bed) ? $r->bed->name : 'N/A') . " <b>Ward</b>: " . (($r->bed) ? $r->bed->ward : "N/A") . " <b>Unit</b>: " . ($r->bed->unit ?? "N/A") . "<br>";
                $str .= "<b >Assigned By</b>: " . (($r->bed_assigned_by) ? (userfullname($r->bed_assigned_by)) : "N/A");
                $str .= "<br> <b>Date Assigned</b>: " . (($r->bed_assign_date) ? date('h:i a D M j, Y', strtotime($r->bed_assign_date)) : 'N/A') . "<br>";
                $str .= "<br> <b>Discharged By </b>: " . (($r->discharged_by) ? (userfullname($r->discharged_by)) : "N/A")." (".(($r->discharge_date) ? date('h:i a D M j, Y', strtotime($r->discharge_date)) : 'N/A') . ")<br>";
                $str .= "</small>";
                return $str;
            })
            ->editColumn('doctor_id', function ($r) {
                return ($r->doctor_id) ? userfullname($r->doctor_id) : 'N/A';
            })
            ->editColumn('billed_by', function ($r) {
                $str = "<small>";
                $str .= "<b >Biiled by: </b>" . (($r->billed_by) ? userfullname($r->billed_by) : 'N/A') . "<br>Date: " . (($r->billed_date) ? date('h:i a D M j, Y', strtotime($r->billed_date)) : 'N/A') . "<br>";
                $str .= "</small>";
                return $str;
            })
            ->rawColumns(['bed_id', 'billed_by', 'show'])
            ->make(true);
    }

    public function assignBed(Request $request)
    {
        try {
            $request->validate([
                'assign_bed_req_id' => 'required',
                'assign_bed_reassign' => 'required',//redundent
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
            $bill_req->save();

            //release bed after billing
            Bed::where('id', $admit_req->bed_id)->update([
                'occupant_id' => null
            ]);

            $admit_req = AdmissionRequest::where('id', $request->assign_bed_req_id)->first();
            $admit_req->update([
                'service_request_id' => $bill_req->id,
                'bed_id'=> null //once billed, the admission entry bed should be null, this will enable bed resaasignment, as bill bed will show after bed is reassigned
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
}
