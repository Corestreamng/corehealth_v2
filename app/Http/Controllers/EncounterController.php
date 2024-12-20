<?php

namespace App\Http\Controllers;

use App\Models\AdmissionRequest;
use App\Models\Clinic;
use App\Models\DoctorQueue;
use App\Models\Encounter;
use App\Models\Hmo;
use App\Models\LabServiceRequest;
use App\Models\NursingNote;
use App\Models\NursingNoteType;
use App\Models\patient;
use App\Models\ProductOrServiceRequest;
use App\Models\ProductRequest;
use App\Models\ReasonForEncounter;
use App\Models\Staff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;

class EncounterController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('admin.doctors.my_queues');
    }

    public function NewEncounterList(Request $request)
    {
        try {
            // Fetch the currently logged-in doctor
            $doc = Staff::where('user_id', Auth::id())->first();

            // Retrieve date range from the request
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            // Build the base query
            $queueQuery = DoctorQueue::where(function ($q) use ($doc) {
                $q->where('clinic_id', $doc->clinic_id)
                    ->orWhere('staff_id', $doc->id);
            })
                ->where('status', 1);

            // Apply date filtering if both dates are provided
            if ($startDate && $endDate) {
                $queueQuery->whereBetween('created_at', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay(),
                ]);
            }

            // Get the filtered results
            $queue = $queueQuery->orderBy('created_at', 'DESC')->get();

            return DataTables::of($queue)
                ->addIndexColumn()
                ->editColumn('fullname', function ($queue) {
                    $patient = patient::find($queue->patient_id);
                    return userfullname($patient->user_id);
                })
                ->editColumn('created_at', function ($note) {
                    return date('h:i a D M j, Y', strtotime($note->created_at));
                })
                ->editColumn('hmo_id', function ($queue) {
                    $patient = patient::find($queue->patient_id);
                    return Hmo::find($patient->hmo_id)->name ?? 'N/A';
                })
                ->editColumn('clinic_id', function ($queue) {
                    $clinic = Clinic::find($queue->clinic_id);
                    return $clinic->name ?? 'N/A';
                })
                ->editColumn('staff_id', function ($queue) use ($doc) {
                    return userfullname($doc->user_id);
                })
                ->addColumn('file_no', function ($queue) {
                    $patient = patient::find($queue->patient_id);
                    return $patient->file_no;
                })
                ->addColumn('view', function ($queue) {
                    $url = route(
                        'encounters.create',
                        ['patient_id' => $queue->patient_id, 'req_entry_id' => $queue->request_entry_id, 'queue_id' => $queue->id]
                    );

                    return '<a href="' . $url . '" class="btn btn-success btn-sm" ><i class="fa fa-street-view"></i> Encounter</a>';
                })
                ->rawColumns(['fullname', 'view'])
                ->make(true);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['exception' => $e]);

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function endOldEncounterReq()
    {
        $currentDateTime = Carbon::now();
        $timeThreshold = $currentDateTime->subHours(env('CONSULTATION_CYCLE_DURATION'));

        $q = DoctorQueue::where('status', 2)
            ->where('created_at', '<', $timeThreshold)->get();
        foreach ($q as $r) {
            $r->update([
                'status' => 3,
            ]);
        }
    }

    public function PrevEncounterList(Request $request)
    {
        try {
            $doc = Staff::where('user_id', Auth::id())->first();

            // Get start and end dates from request, fallback to null
            $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
            $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;

            $queueQuery = DoctorQueue::where(function ($q) use ($doc) {
                $q->where('clinic_id', $doc->clinic_id);
                $q->orWhere('staff_id', $doc->id);
            })
                ->where('status', '>', '2');

            // Apply date range filter if provided
            if ($startDate) {
                $queueQuery->where('created_at', '>=', $startDate->startOfDay());
            }
            if ($endDate) {
                $queueQuery->where('created_at', '<=', $endDate->endOfDay());
            }

            $queue = $queueQuery->orderBy('created_at', 'DESC')->get();

            return DataTables::of($queue)
                ->addIndexColumn()
                ->editColumn('fullname', function ($queue) {
                    $patient = patient::find($queue->patient_id);
                    return userfullname($patient->user_id);
                })
                ->editColumn('created_at', function ($note) {
                    return date('h:i a D M j, Y', strtotime($note->created_at));
                })
                ->editColumn('hmo_id', function ($queue) {
                    $patient = patient::find($queue->patient_id);
                    return Hmo::find($patient->hmo_id)->name ?? 'N/A';
                })
                ->editColumn('clinic_id', function ($queue) {
                    $clinic = Clinic::find($queue->clinic_id);
                    return $clinic->name ?? 'N/A';
                })
                ->editColumn('staff_id', function ($queue) use ($doc) {
                    return userfullname($doc->user_id);
                })
                ->addColumn('file_no', function ($queue) {
                    $patient = patient::find($queue->patient_id);
                    return $patient?->file_no;
                })
                ->addColumn('view', function ($queue) {
                    $url = route('patient.show', $queue->patient_id);
                    return '<a href="' . $url . '" class="btn btn-success btn-sm"><i class="fa fa-street-view"></i> View</a>';
                })
                ->rawColumns(['fullname', 'view'])
                ->make(true);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function ContEncounterList(Request $request)
    {
        try {
            $this->endOldEncounterReq();
            $doc = Staff::where('user_id', Auth::id())->first();

            $timeThreshold = Carbon::now()->subHours(env('CONSULTATION_CYCLE_DURATION', 24));

            // Get start and end dates from request, fallback to null
            $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
            $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;

            $queueQuery = DoctorQueue::where(function ($q) use ($doc) {
                $q->where('clinic_id', $doc->clinic_id);
                $q->orWhere('staff_id', $doc->id);
            })
                ->where('status', 2)
                ->where('created_at', '>=', $timeThreshold);

            // Apply date range filter if provided
            if ($startDate) {
                $queueQuery->where('created_at', '>=', $startDate->startOfDay());
            }
            if ($endDate) {
                $queueQuery->where('created_at', '<=', $endDate->endOfDay());
            }

            $queue = $queueQuery->orderBy('created_at', 'DESC')->get();

            return DataTables::of($queue)
                ->addIndexColumn()
                ->editColumn('fullname', function ($queue) {
                    $patient = patient::find($queue->patient_id);
                    return userfullname($patient->user_id);
                })
                ->editColumn('created_at', function ($note) {
                    return date('h:i a D M j, Y', strtotime($note->created_at));
                })
                ->editColumn('hmo_id', function ($queue) {
                    $patient = patient::find($queue->patient_id);
                    return Hmo::find($patient->hmo_id)->name ?? 'N/A';
                })
                ->editColumn('clinic_id', function ($queue) {
                    $clinic = Clinic::find($queue->clinic_id);
                    return $clinic->name ?? 'N/A';
                })
                ->editColumn('staff_id', function ($queue) use ($doc) {
                    return userfullname($doc->user_id);
                })
                ->addColumn('file_no', function ($queue) {
                    $patient = patient::find($queue->patient_id);
                    return $patient->file_no;
                })
                ->addColumn('view', function ($queue) {
                    $url = route('encounters.create', [
                        'patient_id' => $queue->patient_id,
                        'req_entry_id' => $queue->request_entry_id,
                        'queue_id' => $queue->id
                    ]);
                    return '<a href="' . $url . '" class="btn btn-success btn-sm"><i class="fa fa-street-view"></i> Encounter</a>';
                })
                ->rawColumns(['fullname', 'view'])
                ->make(true);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function allPrevEncounters()
    {
        return view('admin.encounters.index');
    }

    public function AllprevEncounterList(Request $request)
    {
        try {
            $query = Encounter::query()
                ->when($request->filled(['start_date', 'end_date']), function ($query) use ($request) {
                    return $query->whereBetween('created_at', [
                        $request->start_date . ' 00:00:00',
                        $request->end_date . ' 23:59:59'
                    ]);
                })
                ->orderBy('created_at', 'DESC');

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('fullname', function ($queue) {
                    return ($queue->patient) ? userfullname($queue->patient->user_id) : 'N/A';
                })
                ->editColumn('created_at', function ($note) {
                    return date('h:i a D M j, Y', strtotime($note->created_at));
                })
                ->addColumn('hmo_id', function ($queue) {
                    $patient = Patient::find($queue->patient_id);

                    if (!$patient) return 'N/A';
                    if (!$patient->hmo_id) return 'N/A';

                    $hmo = Hmo::find($patient->hmo_id);
                    return $hmo ? $hmo->name : 'N/A';
                })
                ->addColumn('clinic_id', function ($queue) {
                    $clinic = Clinic::find($queue->clinic_id);
                    return $clinic ? $clinic->name : 'N/A';
                })
                ->editColumn('doctor_id', function ($queue) {
                    return $queue->doctor_id ? userfullname($queue->doctor_id) : 'N/A';
                })
                ->addColumn('file_no', function ($queue) {
                    $patient = Patient::find($queue->patient_id);
                    return $patient ? $patient->file_no : 'N/A';
                })
                ->addColumn('view', function ($queue) {
                    $url = route('patient.show', $queue->patient_id);
                    return '<a href="' . $url . '" class="btn btn-success btn-sm"><i class="fa fa-street-view"></i> View</a>';
                })
                ->rawColumns(['view'])
                ->make(true);
        } catch (\Exception $e) {
            Log::error('Error in AllprevEncounterList: ' . $e->getMessage(), [
                'exception' => $e,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date
            ]);

            return response()->json([
                'error' => 'An error occurred while fetching the data. Please try again.'
            ], 500);
        }
    }

    public function investigationHistoryList($patient_id)
    {
        $his = LabServiceRequest::with(['service', 'encounter', 'patient', 'patient.user', 'productOrServiceRequest', 'doctor', 'biller', 'results_person'])
            ->where('status', '>', 0)->where('patient_id', $patient_id)->orderBy('created_at', 'DESC')->get();

        // dd($pc);
        return DataTables::of($his)
            ->addIndexColumn()
            ->editColumn('created_at', function ($h) {
                $str = '<small>';
                $str .= '<b >Requested by: </b>' . ((isset($h->doctor_id) && $h->doctor_id != null) ? (userfullname($h->doctor_id) . ' (' . date('h:i a D M j, Y', strtotime($h->created_at)) . ')') : "<span class='badge badge-secondary'>N/A</span>");
                $str .= '<br><br><b >Last Updated On:</b> ' . date('h:i a D M j, Y', strtotime($h->updated_at));
                $str .= '<br><br><b >Billed by:</b> ' . ((isset($h->billed_by) && $h->billed_by != null) ? (userfullname($h->billed_by) . ' (' . date('h:i a D M j, Y', strtotime($h->billed_date)) . ')') : "<span class='badge badge-secondary'>Not billed</span>");
                $str .= '<br><br><b >Sample taken by:</b> ' . ((isset($h->sample_taken_by) && $h->sample_taken_by != null) ? (userfullname($h->sample_taken_by) . ' (' . date('h:i a D M j, Y', strtotime($h->sample_date)) . ')') : "<span class='badge badge-secondary'>Not taken</span>");
                $str .= '<br><br><b >Results by:</b> ' . ((isset($h->result_by) && $h->result_by != null) ? (userfullname($h->result_by) . ' (' . date('h:i a D M j, Y', strtotime($h->result_date)) . ')') : "<span class='badge badge-secondary'>Awaiting Results</span>");
                $str .= '<br><br><b >Request Note:</b> ' . ((isset($h->note) && $h->note != null) ? ($h->note) : "<span class='badge badge-secondary'>N/A</span><br>");
                $str .= '</small>';

                return $str;
            })
            ->editColumn('result', function ($his) {
                $str = "<span class = 'badge badge-success'>" . (($his->service) ? $his->service->service_name : 'N/A') . '</span><hr>';
                $str .= $his->result ?? 'N/A';

                $str .= "
                    <hr>
                    <button type='button' class='btn btn-primary' onclick='setResViewInModal(this)' data-service-name = '" . (($his->service) ? $his->service->service_name : 'N/A') . "' data-result = '" . htmlspecialchars($his->result) . " 'data-result-obj = '" . htmlspecialchars($his) . "'>
                        Print Result
                    </button>";

                return $str;
            })

            ->rawColumns(['created_at', 'result'])
            ->make(true);
    }

    public function investBillList($patient_id)
    {
        $his = LabServiceRequest::with(['service', 'encounter', 'patient', 'productOrServiceRequest', 'doctor', 'biller'])
            ->where('status', '=', 1)->where('patient_id', $patient_id)->orderBy('created_at', 'DESC')->get();

        // dd($pc);
        return DataTables::of($his)
            ->addIndexColumn()
            ->addColumn('select', function ($h) {
                $str = "<input type='checkbox' name='selectedInvestBillRows[]' onclick='checkInvestBillRow(this)' data-price = '" . (($h->service) ? $h->service->price->sale_price : 'N/A') . "' value='$h->id' class='form-control'> ";

                return $str;
            })
            ->editColumn('created_at', function ($h) {
                $str = '<small>';
                $str .= '<b >Requested by: </b>' . ((isset($h->doctor_id) && $h->doctor_id != null) ? (userfullname($h->doctor_id) . ' (' . date('h:i a D M j, Y', strtotime($h->created_at)) . ')') : "<span class='badge badge-secondary'>N/A</span>");
                $str .= '<br><br><b >Last Updated On:</b> ' . date('h:i a D M j, Y', strtotime($h->updated_at));
                $str .= '<br><br><b >Billed by:</b> ' . ((isset($h->billed_by) && $h->billed_by != null) ? (userfullname($h->billed_by) . ' (' . date('h:i a D M j, Y', strtotime($h->billed_date)) . ')') : "<span class='badge badge-secondary'>Not billed</span>");
                $str .= '<br><br><b >Sample taken by:</b> ' . ((isset($h->sample_taken_by) && $h->sample_taken_by != null) ? (userfullname($h->sample_taken_by) . ' (' . date('h:i a D M j, Y', strtotime($h->sample_date)) . ')') : "<span class='badge badge-secondary'>Not taken</span>");
                $str .= '<br><br><b >Results by:</b> ' . ((isset($h->result_by) && $h->result_by != null) ? (userfullname($h->result_by) . ' (' . date('h:i a D M j, Y', strtotime($h->result_date)) . ')') : "<span class='badge badge-secondary'>Awaiting Results</span>");
                $str .= '<br><br><b >Request Note:</b> ' . ((isset($h->note) && $h->note != null) ? ($h->note) : "<span class='badge badge-secondary'>N/A</span><br>");
                $str .= '</small>';

                return $str;
            })
            ->editColumn('result', function ($his) {
                $str = "<span class = 'badge badge-success'>" . (($his->service) ? $his->service->service_name : 'N/A') . '</span><hr>';
                $str .= $his->result ?? 'N/A';

                return $str;
            })
            ->rawColumns(['created_at', 'result', 'select'])
            ->make(true);
    }

    public function investSampleList($patient_id)
    {
        $his = LabServiceRequest::with(['service', 'encounter', 'patient', 'productOrServiceRequest', 'doctor', 'biller'])
            ->where('status', '=', 2)->where('patient_id', $patient_id)->orderBy('created_at', 'DESC')->get();

        // dd($pc);
        return DataTables::of($his)
            ->addIndexColumn()
            ->addColumn('select', function ($h) {
                $str = "<input type='checkbox' name='selectedInvestSampleRows[]' onclick='checkInvestSampleRow(this)' data-price = '" . $h->service->price->sale_price . "' value='$h->id' class='form-control'> ";

                return $str;
            })
            ->editColumn('created_at', function ($h) {
                $str = '<small>';
                $str .= '<b >Requested by: </b>' . ((isset($h->doctor_id) && $h->doctor_id != null) ? (userfullname($h->doctor_id) . ' (' . date('h:i a D M j, Y', strtotime($h->created_at)) . ')') : "<span class='badge badge-secondary'>N/A</span>");
                $str .= '<br><br><b >Last Updated On:</b> ' . date('h:i a D M j, Y', strtotime($h->updated_at));
                $str .= '<br><br><b >Billed by:</b> ' . ((isset($h->billed_by) && $h->billed_by != null) ? (userfullname($h->billed_by) . ' (' . date('h:i a D M j, Y', strtotime($h->billed_date)) . ')') : "<span class='badge badge-secondary'>Not billed</span>");
                $str .= '<br><br><b >Sample taken by:</b> ' . ((isset($h->sample_taken_by) && $h->sample_taken_by != null) ? (userfullname($h->sample_taken_by) . ' (' . date('h:i a D M j, Y', strtotime($h->sample_date)) . ')') : "<span class='badge badge-secondary'>Not taken</span>");
                $str .= '<br><br><b >Results by:</b> ' . ((isset($h->result_by) && $h->result_by != null) ? (userfullname($h->result_by) . ' (' . date('h:i a D M j, Y', strtotime($h->result_date)) . ')') : "<span class='badge badge-secondary'>Awaiting Results</span>");
                $str .= '<br><br><b >Request Note:</b> ' . ((isset($h->note) && $h->note != null) ? ($h->note) : "<span class='badge badge-secondary'>N/A</span><br>");
                $str .= '</small>';

                return $str;
            })
            ->editColumn('result', function ($his) {
                $str = "<span class = 'badge badge-success'>" . (($his->service) ? $his->service->service_name : 'N/A') . '</span><hr>';
                $str .= $his->result ?? 'N/A';

                return $str;
            })
            ->rawColumns(['created_at', 'result', 'select'])
            ->make(true);
    }

    public function prescHistoryList($patient_id)
    {
        $his = ProductRequest::with(['product', 'encounter', 'patient', 'productOrServiceRequest', 'doctor', 'biller'])
            ->where('status', '>', 0)->where('patient_id', $patient_id)->orderBy('created_at', 'DESC')->get();

        // dd($pc);
        return DataTables::of($his)
            ->addIndexColumn()
            ->editColumn('created_at', function ($h) {
                $str = '<small>';
                $str .= '<b >Requested By: </b>' . ((isset($h->doctor_id) && $h->doctor_id != null) ? (userfullname($h->doctor_id) . ' (' . date('h:i a D M j, Y', strtotime($h->created_at)) . ')') : "<span class='badge badge-secondary'>N/A</span>") . '<br>';
                $str .= '<b >Last Updated On: </b>' . date('h:i a D M j, Y', strtotime($h->updated_at)) . '<br>';
                $str .= '<b >Billed By: </b>' . ((isset($h->billed_by) && $h->billed_by != null) ? (userfullname($h->billed_by) . ' (' . date('h:i a D M j, Y', strtotime($h->billed_date)) . ')') : "<span class='badge badge-secondary'>Not billed</span><br>");
                $str .= '<br><b >Dispensed By: </b>' . ((isset($h->dispensed_by) && $h->dispensed_by != null) ? (userfullname($h->dispensed_by) . ' (' . date('h:i a D M j, Y', strtotime($h->dispense_date)) . ')') : "<span class='badge badge-secondary'>Not dispensed</span><br>");
                $str .= '</small>';

                return $str;
            })
            ->editColumn('dose', function ($his) {
                $str = "<span class = 'badge badge-success'>[" . (($his->product->product_code) ? $his->product->product_code : '') . ']' . $his->product->product_name . '</span>';
                $str .= '<hr> <b>Dose/Freq:</b> ' . ($his->dose ?? 'N/A');

                return $str;
            })
            ->rawColumns(['created_at', 'dose'])
            ->make(true);
    }

    public function prescBillList($patient_id)
    {
        $his = ProductRequest::with(['product', 'encounter', 'patient', 'productOrServiceRequest', 'doctor', 'biller'])
            ->where('status', 1)->where('patient_id', $patient_id)->orderBy('created_at', 'DESC')->get();

        // dd($pc);
        return DataTables::of($his)
            ->addIndexColumn()
            ->addColumn('select', function ($h) {
                $str = "<input type='checkbox' name='selectedPrescBillRows[]' onclick='checkPrescBillRow(this)' data-price = '" . $h->product->price->current_sale_price . "' value='$h->id' class='form-control'> ";

                return $str;
            })
            ->editColumn('created_at', function ($h) {
                $str = '<small>';
                $str .= '<b >Requested By: </b>' . ((isset($h->doctor_id) && $h->doctor_id != null) ? (userfullname($h->doctor_id) . ' (' . date('h:i a D M j, Y', strtotime($h->created_at)) . ')') : "<span class='badge badge-secondary'>N/A</span>") . '<br>';
                $str .= '<b >Last Updated On: </b>' . date('h:i a D M j, Y', strtotime($h->updated_at)) . '<br>';
                $str .= '<b >Billed By: </b>' . ((isset($h->billed_by) && $h->billed_by != null) ? (userfullname($h->billed_by) . ' (' . date('h:i a D M j, Y', strtotime($h->billed_date)) . ')') : "<span class='badge badge-secondary'>Not billed</span><br>");
                $str .= '<br><b >Dispensed By: </b>' . ((isset($h->dispensed_by) && $h->dispensed_by != null) ? (userfullname($h->dispensed_by) . ' (' . date('h:i a D M j, Y', strtotime($h->dispense_date)) . ')') : "<span class='badge badge-secondary'>Not dispensed</span><br>");
                $str .= '</small>';

                return $str;
            })
            ->editColumn('dose', function ($his) {
                $str = "<span class = 'badge badge-success'>[" . (($his->product->product_code) ? $his->product->product_code : '') . ']' . $his->product->product_name . '</span>';
                $str .= '<hr> <b>Dose/Freq:</b> ' . ($his->dose ?? 'N/A');

                return $str;
            })
            ->rawColumns(['created_at', 'dose', 'select'])
            ->make(true);
    }

    public function prescDispenseList($patient_id)
    {
        $his = ProductRequest::with(['product', 'encounter', 'patient', 'productOrServiceRequest', 'doctor', 'biller'])
            ->where('status', 2)->where('patient_id', $patient_id)->orderBy('created_at', 'DESC')->get();

        // dd($pc);
        return DataTables::of($his)
            ->addIndexColumn()
            ->addColumn('select', function ($h) {
                $str = "<input type='checkbox' name='selectedPrescDispenseRows[]' value='$h->id' class='form-control'> ";

                return $str;
            })
            ->editColumn('created_at', function ($h) {
                $str = '<small>';
                $str .= '<b >Requested By: </b>' . ((isset($h->doctor_id) && $h->doctor_id != null) ? (userfullname($h->doctor_id) . ' (' . date('h:i a D M j, Y', strtotime($h->created_at)) . ')') : "<span class='badge badge-secondary'>N/A</span>") . '<br>';
                $str .= '<b >Last Updated On: </b>' . date('h:i a D M j, Y', strtotime($h->updated_at)) . '<br>';
                $str .= '<b >Billed By: </b>' . ((isset($h->billed_by) && $h->billed_by != null) ? (userfullname($h->billed_by) . ' (' . date('h:i a D M j, Y', strtotime($h->billed_date)) . ')') : "<span class='badge badge-secondary'>Not billed</span><br>");
                $str .= '<br><b >Dispensed By: </b>' . ((isset($h->dispensed_by) && $h->dispensed_by != null) ? (userfullname($h->dispensed_by) . ' (' . date('h:i a D M j, Y', strtotime($h->dispense_date)) . ')') : "<span class='badge badge-secondary'>Not dispensed</span><br>");
                $str .= '</small>';

                return $str;
            })
            ->editColumn('dose', function ($his) {
                $str = "<span class = 'badge badge-success'>[" . (($his->product->product_code) ? $his->product->product_code : '') . ']' . $his->product->product_name . '</span>';
                $str .= '<hr> <b>Dose/Freq:</b> ' . ($his->dose ?? 'N/A');

                return $str;
            })
            ->rawColumns(['created_at', 'dose', 'select'])
            ->make(true);
    }

    public function EncounterHistoryList($patient_id)
    {
        $hist = Encounter::where('patient_id', $patient_id)->where('notes', '!=', null)->orderBy('created_at', 'DESC')->get();

        // dd($pc);
        return DataTables::of($hist)
            ->addIndexColumn()
            // ->editColumn('doctor_id', function ($hist) {
            //     return userfullname($hist->doctor_id);
            // })
            // ->editColumn('created_at', function ($hist) {
            //     return date('h:i a D M j, Y', strtotime($hist->created_at));
            // })
            ->editColumn('notes', function ($hist) {
                $str = '';
                $str .= '<h6>Documented by: </h6>';
                $str .= userfullname($hist->doctor_id);
                $str .= "<br>";
                $str .= "<br>";

                $str .= '<h6>Documented at: </h6>';
                $str .= date('h:i a D M j, Y', strtotime($hist->created_at));
                $str .= "<br>";
                $str .= "<br>";
                if ($hist->reasons_for_encounter != '') {
                    $reasons_for_encounter = explode(',', $hist->reasons_for_encounter);
                    $str .= '<h6>Reason(s) for Encounter/ Diagnosis(ICPC -2 ): </h6>';
                    $str .= '<div class="row">';
                    foreach ($reasons_for_encounter as $reason) {
                        $str .= "<u class='ml-3'>$reason, </u>";
                    }
                    $str .= '</div><br>';
                }
                $str .= '<h6>Diagnosis Comment 1: </h6>' . "<u class='ml-3'>" . $hist->reasons_for_encounter_comment_1 . "</u>";
                $str .= '<h6>Diagnosis Comment 2: </h6>' . "<u class='ml-3'>" . $hist->reasons_for_encounter_comment_2 . "</u>";
                $str .= '<hr>';

                $str .= '<h6>Main Notes: </h6>';

                return $str .= $hist->notes;
            })
            ->rawColumns(['notes'])
            ->make(true);
    }

    /**
     * autosave notes.
     */
    public function autosaveNotes(Request $request)
    {
        try {
            $request->validate([
                'encounter_id' => 'required',
                'notes' => 'required',
            ]);

            $encounter = Encounter::where('id', $request->encounter_id)->update([
                'notes' => $request->notes,
            ]);

            return response()->json(['success']);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['exception' => $e]);

            return response()->json(['failed'], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        try {
            $doctor = Staff::where('user_id', Auth::id())->first();
            $patient = patient::find(request()->get('patient_id'));
            $clinic = Clinic::find($doctor->clinic_id);
            $req_entry = ProductOrServiceRequest::find(request()->get('req_entry_id'));
            $admission_exists = AdmissionRequest::where('patient_id', request()->get('patient_id'))->where('discharged', 0)->first();
            $queue_id = $request->get('queue_id');

            $reasons_for_encounter_list = ReasonForEncounter::all();
            $reasons_for_encounter_cat_list = ReasonForEncounter::distinct()->get(['category']);
            $reasons_for_encounter_sub_cat_list = ReasonForEncounter::distinct()->get(['sub_category', 'category']);

            // dd($reasons_for_encounter_cat_list);

            $encounter = Encounter::where('doctor_id', $doctor->id)->where('patient_id', $patient->id)->where('completed', false)->first();

            if (!$encounter) {
                $encounter = new Encounter();
                // $encounter->service_id = $req_entry->service_id;
                $encounter->doctor_id = $doctor->id;
                // $encounter->service_request_id = $req_entry->id;
                $encounter->patient_id = $patient->id;

                $encounter->save();
            } else {
                // $encounter->service_id = $req_entry->service_id;
                $encounter->doctor_id = $doctor->id;
                // $encounter->service_request_id = $req_entry->id;
                $encounter->patient_id = $patient->id;
                $encounter->update();
            }

            if ($encounter) {
                if (null != $admission_exists) {
                    $admission_exists_ = 1;
                } else {
                    $admission_exists_ = 0;
                }

                // dd($admission_exists_);

                if ($request->get('admission_req_id') != '' || $admission_exists_ == 1) {
                    $admission_request = AdmissionRequest::where('id', $request->admission_req_id)->first() ?? $admission_exists;
                    // for nursing notes
                    $patient_id = $patient->id;
                    $patient = patient::find($patient_id);

                    $observation_note = NursingNote::with(['patient', 'createdBy', 'type'])
                        ->where('patient_id', $patient_id)
                        ->where('completed', false)
                        ->where('nursing_note_type_id', 1)
                        ->first() ?? null;

                    $observation_note_template = NursingNoteType::find(1);

                    $treatment_sheet = NursingNote::with(['patient', 'createdBy', 'type'])
                        ->where('patient_id', $patient_id)
                        ->where('completed', false)
                        ->where('nursing_note_type_id', 2)
                        ->first() ?? null;

                    $treatment_sheet_template = NursingNoteType::find(2);

                    $io_chart = NursingNote::with(['patient', 'createdBy', 'type'])
                        ->where('patient_id', $patient_id)
                        ->where('completed', false)
                        ->where('nursing_note_type_id', 3)
                        ->first() ?? null;

                    $io_chart_template = NursingNoteType::find(3);
                    // dd($io_chart_template);

                    $labour_record = NursingNote::with(['patient', 'createdBy', 'type'])
                        ->where('patient_id', $patient_id)
                        ->where('completed', false)
                        ->where('nursing_note_type_id', 4)
                        ->first() ?? null;

                    $labour_record_template = NursingNoteType::find(4);

                    $others_record = NursingNote::with(['patient', 'createdBy', 'type'])
                        ->where('patient_id', $patient_id)
                        ->where('completed', false)
                        ->where('nursing_note_type_id', 5)
                        ->first() ?? null;

                    $others_record_template = NursingNoteType::find(5);

                    return view('admin.doctors.new_encounter')->with([
                        'patient' => $patient,
                        'doctor' => $doctor,
                        'clinic' => $clinic,
                        'req_entry' => $req_entry,
                        'admission_request' => $admission_request,
                        'observation_note' => $observation_note,
                        'treatment_sheet' => $treatment_sheet,
                        'io_chart' => $io_chart,
                        'labour_record' => $labour_record,
                        'others_record' => $others_record,
                        'observation_note_template' => $observation_note_template,
                        'treatment_sheet_template' => $treatment_sheet_template,
                        'io_chart_template' => $io_chart_template,
                        'labour_record_template' => $labour_record_template,
                        'others_record_template' => $others_record_template,
                        'admission_exists_' => $admission_exists_,
                        'encounter' => $encounter,
                        'reasons_for_encounter_list' => $reasons_for_encounter_list,
                        'reasons_for_encounter_cat_list' => $reasons_for_encounter_cat_list,
                        'reasons_for_encounter_sub_cat_list' => $reasons_for_encounter_sub_cat_list,
                    ]);
                } else {
                    return view('admin.doctors.new_encounter')->with([
                        'patient' => $patient,
                        'doctor' => $doctor,
                        'clinic' => $clinic,
                        'req_entry' => $req_entry,
                        'admission_exists_' => $admission_exists_,
                        'encounter' => $encounter,
                        'reasons_for_encounter_list' => $reasons_for_encounter_list,
                        'reasons_for_encounter_cat_list' => $reasons_for_encounter_cat_list,
                        'reasons_for_encounter_sub_cat_list' => $reasons_for_encounter_sub_cat_list,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['exception' => $e]);

            return redirect()->back()->withInput()->withMessage('An error occurred ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // dd($request->all());
        try {
            if (env('REQUIRE_DIAGNOSIS') == 1) {
                $request->validate([
                    'doctor_diagnosis' => 'required|string',
                    'consult_presc_dose' => 'nullable|array|required_with:consult_presc_id',
                    'consult_presc_id' => 'nullable|array|required_with:consult_presc_dose',
                    'consult_invest_note' => 'nullable|array',
                    'consult_invest_id' => 'nullable|array',
                    'consult_presc_dose.*' => 'required_with:consult_presc_dose',
                    'consult_presc_id.*' => 'required_with:consult_presc_id',
                    'consult_invest_note.*' => 'nullable',
                    'consult_invest_id.*' => 'required_with:consult_invest_id',
                    'admit_note' => 'nullable|string',
                    'consult_admit' => 'nullable',
                    'req_entry_service_id' => 'required',
                    'req_entry_id' => 'required',
                    'patient_id' => 'required',
                    'queue_id' => 'required',
                    'end_consultation' => 'nullable',
                    'encounter_id' => 'required',
                    'reasons_for_encounter' => 'required',
                    'reasons_for_encounter_comment_1' => 'required',
                    'reasons_for_encounter_comment_2' => 'required',
                ]);
            } else {
                $request->validate([
                    'doctor_diagnosis' => 'required|string',
                    'consult_presc_dose' => 'nullable|array|required_with:consult_presc_id',
                    'consult_presc_id' => 'nullable|array|required_with:consult_presc_dose',
                    'consult_invest_note' => 'nullable|array',
                    'consult_invest_id' => 'nullable|array',
                    'consult_presc_dose.*' => 'required_with:consult_presc_dose',
                    'consult_presc_id.*' => 'required_with:consult_presc_id',
                    'consult_invest_note.*' => 'nullable',
                    'consult_invest_id.*' => 'required_with:consult_invest_id',
                    'admit_note' => 'nullable|string',
                    'consult_admit' => 'nullable',
                    'req_entry_service_id' => 'required',
                    'req_entry_id' => 'required',
                    'patient_id' => 'required',
                    'queue_id' => 'required',
                    'end_consultation' => 'nullable',
                    'encounter_id' => 'required',
                ]);
            }

            if (isset($request->consult_presc_id) && isset($request->consult_presc_dose)) {
                if (count($request->consult_presc_id) !== count($request->consult_presc_dose)) {
                    $msg = 'Please fill out dosages for all selected products';

                    return redirect()->back()->withInput()->withMessage($msg)->withMessageType('warning');
                }
            }

            if (isset($request->consult_invest_id) && isset($request->consult_invest_note)) {
                if (count($request->consult_invest_id) !== count($request->consult_invest_note)) {
                    $msg = 'Please fill out notes for all selected services';

                    return redirect()->back()->withInput()->withMessage($msg)->withMessageType('warning');
                }
            }

            // Find the patient
            $patient = patient::findOrFail($request->patient_id);

            if (null != $patient->dhis_consult_enrollment_id && null != $patient->dhis_consult_tracker_id && $patient->dhis_consult_enrollment_id != '' && $patient->dhis_consult_tracker_id != '') {
                // Get current time in the required format
                $currentTime = Carbon::now()->format('Y-m-d\TH:i:s.000');

                if (env('GOONLINE') == 1) {

                    // Prepare the data values for reasons for encounter
                    // Loop through each reason for encounter and create an event
                    foreach ($request->reasons_for_encounter as $reason) {
                        $dataValues = [
                            [
                                'dataElement' => env('DHIS_TRACKED_ENTITY_PROGRAM_EVENT_DATAELEMENT'),
                                'value' => $reason,
                            ],
                        ];

                        Http::withBasicAuth('admin', 'district')
                            ->post(env('DHIS_API_URL') . '/tracker?importStrategy=CREATE&async=false', [
                                'events' => [
                                    [
                                        'dataValues' => $dataValues,
                                        'enrollmentStatus' => 'ACTIVE',
                                        'occurredAt' => $currentTime,
                                        'orgUnit' => env('DHIS_ORG_UNIT'),
                                        'program' => env('DHIS_TRACKED_ENTITY_PROGRAM'),
                                        'programStage' => env('DHIS_TRACKED_ENTITY_PROGRAM_STAGE2'),
                                        'scheduledAt' => $currentTime,
                                        'status' => 'COMPLETED',
                                        'enrollment' => $patient->dhis_consult_enrollment_id,
                                        'trackedEntity' => $patient->dhis_consult_tracker_id,
                                    ],
                                ],
                            ]);
                    }
                }
            }

            DB::beginTransaction();
            $encounter = Encounter::where('id', $request->encounter_id)->first();
            if ($request->req_entry_service_id == null || $request->req_entry_service_id == 'ward_round') {
                $encounter->service_id = null;
                $encounter->service_request_id = null;
            } else {
                $encounter->service_id = $request->req_entry_service_id;
                $encounter->service_request_id = $request->req_entry_id;
            }
            if ($request->admission_request_id != '') {
                $encounter->admission_request_id = $request->admission_request_id;
            }
            $encounter->doctor_id = Auth::id();
            $encounter->patient_id = $request->patient_id;
            if (env('REQUIRE_DIAGNOSIS')) {
                $encounter->reasons_for_encounter = implode(',', $request->reasons_for_encounter);
                $encounter->reasons_for_encounter_comment_1 = $request->reasons_for_encounter_comment_1;
                $encounter->reasons_for_encounter_comment_2 = $request->reasons_for_encounter_comment_2;
            }
            $encounter->notes = $request->doctor_diagnosis;
            $encounter->completed = true;
            $encounter->update();
            // dd($encounter);
            if (isset($request->consult_invest_id) && count($request->consult_invest_id) > 0) {
                for ($r = 0; $r < count($request->consult_invest_id); ++$r) {
                    $invest = new LabServiceRequest();
                    $invest->service_id = $request->consult_invest_id[$r];
                    $invest->note = $request->consult_invest_note[$r];
                    $invest->encounter_id = $encounter->id;
                    $invest->patient_id = $request->patient_id;
                    $invest->doctor_id = Auth::id();
                    $invest->save();

                    $req_entr = new ProductOrServiceRequest();
                }
            }

            if (isset($request->consult_presc_id) && count($request->consult_presc_id) > 0) {
                for ($r = 0; $r < count($request->consult_presc_id); ++$r) {
                    $presc = new ProductRequest();
                    $presc->product_id = $request->consult_presc_id[$r];
                    $presc->dose = $request->consult_presc_dose[$r];
                    $presc->encounter_id = $encounter->id;
                    $presc->patient_id = $request->patient_id;
                    $presc->doctor_id = Auth::id();
                    $presc->save();
                }
            }

            if ($request->queue_id != 'ward_round') {
                $queue = DoctorQueue::where('id', $request->queue_id)->update([
                    'status' => (($request->end_consultation && $request->end_consultation == '1') ? 3 : 2),
                ]);
            }

            if ($request->consult_admit && $request->consult_admit == '1') {
                $admit = new AdmissionRequest();
                $admit->encounter_id = $encounter->id;
                $admit->doctor_id = Auth::id();
                $admit->patient_id = $request->patient_id;
                $admit->note = $request->admit_note;
                $admit->save();
            }
            DB::commit();

            if ($request->queue_id != 'ward_round' && $request->queue_id != '') {
                // Send to CoreHealth SuperAdmin
                $queue = DoctorQueue::where('id', $request->queue_id)->first();
                // dd($queue);

                if (env('GOONLINE') == 1) {
                    $response = Http::withBasicAuth(
                        env('COREHMS_SUPERADMIN_USERNAME'),
                        env('COREHMS_SUPERADMIN_PASS')
                    )->withHeaders([
                        'Content-Type' => 'application/json',
                    ])->post(env('COREHMS_SUPERADMIN_URL') . '/event-notification.php?notification_type=consultation', [
                        'category' => $queue->clinic->name,
                        'health_case' => $request->reasons_for_encounter[0] ?? null
                    ]);

                    Log::info("sent api request For encounter, ", [$response->body()]);
                }
            }
            return redirect()->route('encounters.index')->with(['message' => 'Encounter Notes Saved Successfully', 'message_type' => 'success']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage(), ['exception' => $e]);

            return redirect()->back()->withInput()->withMessage('An error occurred ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Encounter $encounter) {}

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Encounter $encounter) {}

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Encounter $encounter) {}

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Encounter $encounter) {}
}
