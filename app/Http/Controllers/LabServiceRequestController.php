<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LabServiceRequest;
use App\Models\AdmissionRequest;
use App\Models\Clinic;
use App\Models\Encounter;
use App\Models\DoctorQueue;
use Yajra\DataTables\DataTables;
use App\Models\User;
use App\Models\Staff;
use App\Models\Hmo;
use Illuminate\Support\Facades\Auth;
use App\Models\patient;
use App\Models\Product;
use App\Models\ProductOrServiceRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LabServiceRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (request('history') == 1) {
            return view('admin.lab_service_requests.history');
        } else {
            return view('admin.lab_service_requests.index');
        }
    }

    /**
     * save result of lab request
     */
    public function saveResult(Request $request)
    {
        // dd($request->all());
        try {
            $request->validate([
                'invest_res_template_submited' => 'required|string',
                'invest_res_entry_id' => 'required'
            ]);

            //make all contenteditable section uneditable, so that they wont be editable when they show up in medical history
            $request->invest_res_template_submited = str_replace('contenteditable="true"', 'contenteditable="false"', $request->invest_res_template_submited);
            $request->invest_res_template_submited = str_replace("contenteditable='true'", "contenteditable='false'", $request->invest_res_template_submited);
            $request->invest_res_template_submited = str_replace('contenteditable = "true"', 'contenteditable="false"', $request->invest_res_template_submited);
            $request->invest_res_template_submited = str_replace("contenteditable ='true'", "contenteditable='false'", $request->invest_res_template_submited);
            $request->invest_res_template_submited = str_replace('contenteditable= "true"', 'contenteditable="false"', $request->invest_res_template_submited);

            //remove all black borders and replace with gray
            $request->invest_res_template_submited = str_replace(' black', ' gray', $request->invest_res_template_submited);

            DB::beginTransaction();
            $req = LabServiceRequest::where('id', $request->invest_res_entry_id)->update([
                'result' => $request->invest_res_template_submited,
                'result_date' => date('Y-m-d H:i:s'),
                'result_by' => Auth::id(),
                'status' => 4
            ]);
            DB::commit();
            return redirect()->back()->with(['message' => "Results Saved Successfully", 'message_type' => 'success']);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage() . ' line' . $e->getLine());
        }
    }

    /**
     * bill selected service requets
     */

    public function bill(Request $request)
    {
        try {
            $request->validate([
                'consult_invest_note' => 'nullable|array|required_with:addedInvestBillRows',
                'addedInvestBillRows' => 'nullable|array|required_with:consult_invest_note',
                'selectedInvestBillRows' => 'array',
                'patient_user_id' => 'required',
                'patient_id' => 'required'
            ]);

            if (isset($request->dismiss_invest_bill) && isset($request->selectedInvestBillRows)) {
                DB::beginTransaction();
                for ($i = 0; $i < count($request->selectedInvestBillRows); $i++) {
                    LabServiceRequest::where('id', $request->selectedInvestBillRows[$i])->update([
                        'status' => 0
                    ]);
                }
                DB::commit();
                return redirect()->back()->with(['message' => "Service Requests Dismissed Successfully", 'message_type' => 'success']);
            } else {
                DB::beginTransaction();
                if (isset($request->selectedInvestBillRows)) {
                    for ($i = 0; $i < count($request->selectedInvestBillRows); $i++) {
                        $prod_id = LabServiceRequest::where('id', $request->selectedInvestBillRows[$i])->first()->service->id;
                        $bill_req = new ProductOrServiceRequest;
                        $bill_req->user_id = $request->patient_user_id;
                        $bill_req->staff_user_id = Auth::id();
                        $bill_req->service_id = $prod_id;
                        $bill_req->save();


                        LabServiceRequest::where('id', $request->selectedInvestBillRows[$i])->update([
                            'status' => 2,
                            'billed_by' => Auth::id(),
                            'billed_date' => date('Y-m-d H:i:s'),
                            'service_request_id' => $bill_req->id,
                        ]);
                    }
                }
                if (isset($request->addedInvestBillRows)) {
                    for ($i = 0; $i < count($request->addedInvestBillRows); $i++) {
                        $bill_req = new ProductOrServiceRequest;
                        $bill_req->user_id = $request->patient_user_id;
                        $bill_req->staff_user_id = Auth::id();
                        $bill_req->service_id = $request->addedInvestBillRows[$i];
                        $bill_req->save();

                        $inves = new LabServiceRequest();
                        $inves->service_id = $request->addedInvestBillRows[$i];
                        $inves->note = $request->consult_invest_note[$i];
                        // $inves->encounter_id = $encounter->id;
                        $inves->billed_by = Auth::id();
                        $inves->billed_date = date('Y-m-d H:i:s');
                        $inves->patient_id = $request->patient_id;
                        $inves->doctor_id = Auth::id();
                        $inves->service_request_id = $bill_req->id;
                        $inves->status = 2;
                        $inves->save();
                    }
                }
                DB::commit();
                return redirect()->back()->with(['message' => "Service Requests Billed Successfully", 'message_type' => 'success']);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage() . 'line' . $e->getLine());
        }
    }

    public function takeSample(Request $request)
    {
        try {
            $request->validate([
                'selectedInvestSampleRows' => 'array',
                'patient_user_id' => 'required',
                'patient_id' => 'required'
            ]);

            if (isset($request->dismiss_invest_sample) && isset($request->selectedInvestSampleRows)) {
                DB::beginTransaction();
                for ($i = 0; $i < count($request->selectedInvestSampleRows); $i++) {
                    LabServiceRequest::where('id', $request->selectedInvestSampleRows[$i])->update([
                        'status' => 0
                    ]);
                }
                DB::commit();
                return redirect()->back()->with(['message' => "Service Requests Dismissed Successfully", 'message_type' => 'success']);
            } else {
                DB::beginTransaction();
                if (isset($request->selectedInvestSampleRows)) {
                    for ($i = 0; $i < count($request->selectedInvestSampleRows); $i++) {

                        LabServiceRequest::where('id', $request->selectedInvestSampleRows[$i])->update([
                            'status' => 3,
                            'sample_taken_by' => Auth::id(),
                            'sample_date' => date('Y-m-d H:i:s'),
                            'sample_taken' => true
                        ]);
                    }
                }

                DB::commit();
                return redirect()->back()->with(['message' => "Service Requests Sample Taken Successfully", 'message_type' => 'success']);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage() . 'line' . $e->getLine());
        }
    }

    public function investResList($patient_id)
    {
        $his = LabServiceRequest::with(['service', 'encounter', 'patient', 'productOrServiceRequest', 'doctor', 'biller'])
            ->where('status', '=', 3)->where('patient_id', $patient_id)->orderBy('created_at', 'DESC')->get();
        //dd($pc);
        return Datatables::of($his)
            ->addIndexColumn()
            ->addColumn('select', function ($h) {
                $str = "
                    <button type='button' class='btn btn-primary' onclick='setResTempInModal(this)' data-service-name = '" . $h?->service->service_name . "' data-template = '" . htmlspecialchars($h?->service->template) . "' data-id='$h?->id'>
                        Enter Result
                    </button>";
                return $str;
            })
            ->editColumn('created_at', function ($h) {
                $str = "<small>";
                $str .= "<b >Requested by: </b>" . ((isset($h?->doctor_id)  && $h?->doctor_id != null) ? (userfullname($h?->doctor_id) . ' (' . date('h:i a D M j, Y', strtotime($h?->created_at)) . ')') : "<span class='badge badge-secondary'>N/A</span>");
                $str .= "<br><br><b >Last Updated On:</b> " . date('h:i a D M j, Y', strtotime($h?->updated_at));
                $str .= "<br><br><b >Billed by:</b> " . ((isset($h?->billed_by) && $h?->billed_by != null) ? (userfullname($h?->billed_by) . ' (' . date('h:i a D M j, Y', strtotime($h?->billed_date)) . ')') : "<span class='badge badge-secondary'>Not billed</span>");
                $str .= "<br><br><b >Sample taken by:</b> " . ((isset($h?->sample_taken_by) && $h?->sample_taken_by != null) ? (userfullname($h?->sample_taken_by) . ' (' . date('h:i a D M j, Y', strtotime($h?->sample_date)) . ')') : "<span class='badge badge-secondary'>Not taken</span>");
                $str .= "<br><br><b >Results by:</b> " . ((isset($h?->result_by) && $h?->result_by != null) ? (userfullname($h?->result_by) . ' (' . date('h:i a D M j, Y', strtotime($h?->result_date)) . ')') : "<span class='badge badge-secondary'>Awaiting Results</span>");
                $str .= "<br><br><b >Request Note:</b> " . ((isset($h?->note) && $h?->note != null) ? ($h?->note) : "<span class='badge badge-secondary'>N/A</span><br>");
                $str .= "</small>";
                return $str;
            })
            ->editColumn('result', function ($his) {
                $str = "<span class = 'badge badge-success'>" . (($his?->service) ? $his?->service->service_name : "N/A") . "</span><hr>";
                $str .= $his?->result ?? 'N/A';
                return $str;
            })
            ->rawColumns(['created_at', 'result', 'select'])
            ->make(true);
    }

    public function investQueueList(Request $request)
    {
        try {
            // Validate start and end dates
            $startDate = Carbon::parse($request->input('start_date'));
            $endDate = Carbon::parse($request->input('end_date'));

            // Build query with eager loading and filters
            $query = LabServiceRequest::with([
                'service',
                'encounter',
                'patient.user',
                'patient.hmo',
                'productOrServiceRequest',
                'doctor',
                'biller'
            ])
                ->whereIn('status', [1, 2, 3]);

            // Apply date range filter if provided
            if ($startDate && $endDate) {
                $query->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()]);
            }

            $requests = $query->orderByDesc('created_at')->get();

            // Generate DataTable response
            return Datatables::of($requests)
                ->addIndexColumn()
                ->addColumn('select', function ($request) {
                    if (!$request->patient) {
                        return '<span class="badge badge-danger">Invalid Patient Data</span>';
                    }

                    $url = route('patient.show', [$request->patient->id, 'section' => 'investigationsCardBody']);
                    return "<a class='btn btn-primary' href='{$url}'>view</a>";
                })
                ->editColumn('patient_id', function ($request) {
                    if (!$request->patient) {
                        return '<span class="badge badge-danger">Patient data not found</span>';
                    }

                    $str = "<small>";
                    $str .= "<b>Patient: </b>" . (($request->patient->user) ? userfullname($request->patient->user->id) : "N/A");
                    $str .= "<br><br><b>File No: </b>" . ($request->patient->file_no ?? "N/A");
                    $str .= "<br><br><b>Insurance/HMO: </b>" . (($request->patient->hmo) ? $request->patient->hmo->name : "N/A");
                    $str .= "<br><br><b>HMO Number: </b>" . ($request->patient->hmo_no ?? "N/A");
                    $str .= "</small>";

                    return $str;
                })
                ->editColumn('created_at', function ($request) {
                    $str = "<small>";

                    $str .= "<b>Requested by: </b>" .
                        (isset($request->doctor_id) ?
                            userfullname($request->doctor_id) . ' (' . $this->formatDateTime($request->created_at) . ')' :
                            "<span class='badge badge-secondary'>N/A</span>");

                    $str .= "<br><br><b>Last Updated On: </b>" . $this->formatDateTime($request->updated_at);

                    $str .= "<br><br><b>Billed by: </b>" .
                        (isset($request->billed_by) ?
                            userfullname($request->billed_by) . ' (' . $this->formatDateTime($request->billed_date) . ')' :
                            "<span class='badge badge-secondary'>Not billed</span>");

                    $str .= "<br><br><b>Sample taken by: </b>" .
                        (isset($request->sample_taken_by) ?
                            userfullname($request->sample_taken_by) . ' (' . $this->formatDateTime($request->sample_date) . ')' :
                            "<span class='badge badge-secondary'>Not taken</span>");

                    $str .= "<br><br><b>Results by: </b>" .
                        (isset($request->result_by) ?
                            userfullname($request->result_by) . ' (' . $this->formatDateTime($request->result_date) . ')' :
                            "<span class='badge badge-secondary'>Awaiting Results</span>");

                    $str .= "<br><br><b>Request Note: </b>" .
                        (isset($request->note) && $request->note != null ?
                            $request->note :
                            "<span class='badge badge-secondary'>N/A</span>");

                    $str .= "</small>";
                    return $str;
                })
                ->editColumn('result', function ($request) {
                    $str = "<span class='badge badge-success'>" .
                        (optional($request->service)->service_name ?? "N/A") .
                        "</span><hr>";
                    $str .= $request->result ?? 'N/A';
                    return $str;
                })
                ->rawColumns(['created_at', 'result', 'select', 'patient_id'])
                ->make(true);
        } catch (\Exception $e) {
            Log::error('Lab Service Request Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'An error occurred while processing the request.',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }


    /**
     * Format datetime to consistent format
     *
     * @param string|null $datetime
     * @return string
     */
    private function formatDateTime(?string $datetime): string
    {
        return $datetime
            ? date('h:i a D M j, Y', strtotime($datetime))
            : 'N/A';
    }


    public function investHistoryList(Request $request)
    {
        // Base query to fetch lab service requests with status 4
        $query = LabServiceRequest::with([
            'service',
            'encounter',
            'patient',
            'productOrServiceRequest',
            'doctor',
            'biller'
        ])->where('status', '=', 4);

        // Apply date filters if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        // Fetch filtered or unfiltered results
        $his = $query->orderBy('created_at', 'DESC')->get();

        return Datatables::of($his)
            ->addIndexColumn()
            ->addColumn('select', function ($h) {
                $url = route('patient.show', [$h?->patient->id, 'section' => 'investigationsCardBody']);
                $str = "
                <a class='btn btn-primary' href='$url'>
                    view
                </a>";
                return $str;
            })
            ->editColumn('patient_id', function ($h) {
                $str = "<small>";
                $str .= "<b >Patient </b> :" . (($h?->patient->user) ? userfullname($h?->patient->user->id) : "N/A");
                $str .= "<br><br><b >File No </b> : " . (($h?->patient) ? $h?->patient->file_no : "N/A");
                $str .= "<br><br><b >Insurance/HMO :</b> : " . (($h?->patient->hmo) ? $h?->patient->hmo->name : "N/A");
                $str .= "<br><br><b >HMO Number :</b> : " . (($h?->patient->hmo_no) ? $h?->patient->hmo_no : "N/A");
                $str .= "</small>";
                return $str;
            })
            ->editColumn('created_at', function ($h) {
                $str = "<small>";
                $str .= "<b >Requested by: </b>" . ((isset($h?->doctor_id)  && $h?->doctor_id != null) ? (userfullname($h?->doctor_id) . ' (' . date('h:i a D M j, Y', strtotime($h?->created_at)) . ')') : "<span class='badge badge-secondary'>N/A</span>");
                $str .= "<br><br><b >Last Updated On:</b> " . date('h:i a D M j, Y', strtotime($h?->updated_at));
                $str .= "<br><br><b >Billed by:</b> " . ((isset($h?->billed_by) && $h?->billed_by != null) ? (userfullname($h?->billed_by) . ' (' . date('h:i a D M j, Y', strtotime($h?->billed_date)) . ')') : "<span class='badge badge-secondary'>Not billed</span>");
                $str .= "<br><br><b >Sample taken by:</b> " . ((isset($h?->sample_taken_by) && $h?->sample_taken_by != null) ? (userfullname($h?->sample_taken_by) . ' (' . date('h:i a D M j, Y', strtotime($h?->sample_date)) . ')') : "<span class='badge badge-secondary'>Not taken</span>");
                $str .= "<br><br><b >Results by:</b> " . ((isset($h?->result_by) && $h?->result_by != null) ? (userfullname($h?->result_by) . ' (' . date('h:i a D M j, Y', strtotime($h?->result_date)) . ')') : "<span class='badge badge-secondary'>Awaiting Results</span>");
                $str .= "<br><br><b >Request Note:</b> " . ((isset($h?->note) && $h?->note != null) ? ($h?->note) : "<span class='badge badge-secondary'>N/A</span><br>");
                $str .= "</small>";
                return $str;
            })
            ->editColumn('result', function ($his) {
                $str = "<span class = 'badge badge-success'>" . (($his?->service) ? $his?->service->service_name : "N/A") . "</span><hr>";
                $str .= $his?->result ?? 'N/A';
                $view_url = route('service-requests.show', $his?->id);
                $str .= "<br><a href='$view_url' class = 'btn btn-primary btn-sm' target='_blank'><i class='fa fa-print'></i> Print</a>";
                return $str;
            })
            ->rawColumns(['created_at', 'result', 'select', 'patient_id'])
            ->make(true);
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
        $req = LabServiceRequest::where('id', $id)->first();

        return view('admin.lab_service_requests.show', ['req' => $req]);
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
