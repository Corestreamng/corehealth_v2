<?php

namespace App\Http\Controllers;

use App\Models\VitalSign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;
use App\Models\DoctorQueue;
use Carbon\Carbon;

class VitalSignController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (request('history') == 1) {
            return view('admin.vitalsign_requests.history');
        } else {
            return view('admin.vitalsign_requests.index');
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
        try {
            $request->validate([
                'patient_id' => 'required',
                'bloodPressure' => 'nullable|regex:/^\d{2,3}\/\d{2,3}$/',
                'bodyTemperature' => 'required|numeric|min:34|max:42',
                'datetimeField' => 'required',
                'heartRate' => 'nullable|numeric|min:30|max:250',
                'respiratoryRate' => 'nullable|numeric|min:5|max:60',
                'bodyWeight' => 'nullable|numeric|min:0.5|max:500',
                'height' => 'nullable|numeric|min:30|max:300',
                'spo2' => 'nullable|numeric|min:50|max:100',
                'bloodSugar' => 'nullable|numeric|min:20|max:600',
                'painScore' => 'nullable|integer|min:0|max:10',
                'bmi' => 'nullable|numeric',
            ]);

            DB::beginTransaction();
            $vitalSign = new VitalSign;

            $vitalSign->taken_by = Auth::id();
            $vitalSign->patient_id = $request->patient_id;
            $vitalSign->temp = $request->bodyTemperature;
            $vitalSign->blood_pressure = $request->bloodPressure ?? '0/0';
            $vitalSign->heart_rate = $request->heartRate;
            $vitalSign->resp_rate = $request->respiratoryRate;
            $vitalSign->weight = $request->bodyWeight;
            $vitalSign->height = $request->height;
            $vitalSign->spo2 = $request->spo2;
            $vitalSign->blood_sugar = $request->bloodSugar;
            $vitalSign->pain_score = $request->painScore;
            $vitalSign->other_notes = $request->otherNotes;
            $vitalSign->time_taken = $request->datetimeField;

            // Auto-calculate BMI if weight and height provided
            if ($request->bmi) {
                $vitalSign->bmi = $request->bmi;
            } elseif ($request->bodyWeight && $request->height) {
                $heightInMeters = $request->height / 100;
                $vitalSign->bmi = round($request->bodyWeight / ($heightInMeters * $heightInMeters), 1);
            }

            $vitalSign->save();

            //update all doc queues for the patient, so that they no longer show on the viatls queue
            $doQueue = DoctorQueue::where('patient_id', $request->patient_id)->update([
                'vitals_taken' => true
            ]);
            DB::commit();

            if ($request->wantsJson()) {
                 return response()->json(['success' => true, 'message' => 'Vitals saved successfully']);
            }

            return back()->withMessage('Vitals saved successfully')->withMessageType('success');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage(), ['exception' => $e]);

            if ($request->wantsJson()) {
                 return response()->json(['success' => false, 'message' => "An error occurred " . $e->getMessage()], 500);
            }

            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage() . 'line:' . $e->getLine());
        }
    }

    public function patientVitals($patient_id)
    {
        $his = VitalSign::with(['patient', 'takenBy', 'requstedBy'])
            ->where('status', 1)->where('patient_id', $patient_id)->orderBy('created_at', 'DESC')->get();
        //dd($pc);
        return Datatables::of($his)
            ->addIndexColumn()

            ->editColumn('created_at', function ($h) {
                $str = "<small>";
                $str .= "<b >Requested by: </b>" . ((isset($h->requested_by)  && $h->requested_by != null) ? (userfullname($h->requested_by) . ' (' . date('h:i a D M j, Y', strtotime($h->created_at)) . ')') : "<span class='badge badge-secondary'>N/A</span>");
                $str .= "<br><br><b >Last Updated On:</b> " . date('h:i a D M j, Y', strtotime($h->updated_at));
                $str .= "<br><br><b >Taken by:</b> " . ((isset($h->taken_by) && $h->taken_by != null) ? (userfullname($h->taken_by) . ' (' . date('h:i a D M j, Y', strtotime($h->time_taken)) . ')') : "<span class='badge badge-secondary'>Not billed</span>");
                $str .= "</small>";
                return $str;
            })
            ->editColumn('result', function ($his) {
                $str = "<b > Blood Pressure (mmHg): </b>" . $his->blood_pressure . "<br>";
                $str .= "<b > Body Temperature (°C): </b>" . $his->temp . "<br>";
                $str .= "<b > Body Weight (Kg): </b>" . $his->weight . "<br>";
                $str .= "<b > Respiratory Rate (BPM) :</b>" . $his->resp_rate . "<br>";
                $str .= "<b > Heart Rate (BPM): </b>" . $his->heart_rate . "<br><hr>";
                $str .= $his->other_notes ?? 'N/A';
                return $str;
            })
            ->rawColumns(['created_at', 'result'])
            ->make(true);
    }
    public function allPatientVitals($patient_id)
    {
        $vitals = VitalSign::where('status', 1)->where('patient_id', $patient_id)->limit(30)->get();
        return json_encode($vitals);
    }

    public function patientVitalsQueue(Request $request)
    {
        $currentDateTime = Carbon::now();
        $timeThreshold = $currentDateTime->subHours(appsettings('consultation_cycle_duration', 24));

        // Retrieve date range from the request
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Base query
        $query = DoctorQueue::with(['patient', 'doctor', 'receptionist'])
            ->where('status', 1)
            ->where('vitals_taken', false)
            ->where('created_at', '>', $timeThreshold);

        // Apply date range filter if provided
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);
        }

        $his = $query->orderBy('created_at', 'DESC')->get();

        return Datatables::of($his)
            ->addIndexColumn()
            ->addColumn('select', function ($h) {
                $url = route('patient.show', [$h->patient->id, 'section' => 'vitalsCardBody']);
                $str = "
                <a class='btn btn-primary' href='$url'>
                    view
                </a>";
                return $str;
            })
            ->editColumn('patient_id', function ($h) {
                $str = "<small>";
                $str .= "<b>Patient</b>: " . (($h->patient->user) ? userfullname($h->patient->user->id) : "N/A");
                $str .= "<br><br><b>File No</b>: " . (($h->patient) ? $h->patient->file_no : 'N/A');
                $str .= "<br><br><b>Insurance/HMO</b>: " . (($h->patient->hmo) ? $h->patient->hmo->name : "N/A");
                $str .= "<br><br><b>HMO Number</b>: " . (($h->patient->hmo_no) ? $h->patient->hmo_no : "N/A");
                $str .= "</small>";
                return $str;
            })
            ->editColumn('created_at', function ($h) {
                $str = "<small>";
                $str .= "<b>Clinic</b>: " . (($h->clinic) ? $h->clinic->name : "N/A");
                $str .= "<br><br><b>Doctor</b>: " . (($h->doctor) ? userfullname($h->doctor->id) : "N/A");
                $str .= "<br><br><b>Requested by:</b> " . ((isset($h->receptionist_id) && $h->receptionist_id != null) ? (userfullname($h->receptionist_id) . ' (' . date('h:i a D M j, Y', strtotime($h->created_at)) . ')') : "<span class='badge badge-secondary'>N/A</span>");
                $str .= "<br><br><b>Last Updated On:</b> " . date('h:i a D M j, Y', strtotime($h->updated_at));
                $str .= "</small>";
                return $str;
            })
            ->rawColumns(['created_at', 'select', 'patient_id'])
            ->make(true);
    }


    public function patientVitalsHistoryQueue()
    {
        $his = VitalSign::with(['patient', 'takenBy', 'requstedBy'])
            ->where('status', 1)->where('blood_pressure', '!=', null)->orderBy('created_at', 'DESC')->get();
        //dd($pc);
        return Datatables::of($his)
            ->addIndexColumn()
            ->addColumn('select', function ($h) {
                $url = route('patient.show', [$h->patient->id, 'section' => 'vitalsCardBody']);
                $str = "
                    <a class='btn btn-primary' href='$url'>
                        view
                    </a>";
                return $str;
            })
            ->editColumn('patient_id', function ($h) {
                $str = "<small>";
                $str .= "<b >Patient </b> :" . (($h->patient) ? userfullname($h->patient->user_id) : "N/A");
                $str .= "<br><br><b >File No </b> : " . (($h->patient) ? $h->patient->file_no : "N/A");
                $str .= "<br><br><b >Insurance/HMO :</b> : " . (($h->patient->hmo) ? $h->patient->hmo->name : "N/A");
                $str .= "<br><br><b >HMO Number :</b> : " . (($h->patient->hmo_no) ? $h->patient->hmo_no : "N/A");
                $str .= "</small>";
                return $str;
            })
            ->editColumn('created_at', function ($h) {
                $str = "<small>";
                $str .= "<b >Requested by: </b>" . ((isset($h->requested_by)  && $h->requested_by != '') ? (userfullname($h->requested_by) . ' (' . date('h:i a D M j, Y', strtotime($h->created_at)) . ')') : "<span class='badge badge-secondary'>N/A</span>");
                $str .= "<br><br><b >Last Updated On:</b> " . date('h:i a D M j, Y', strtotime($h->updated_at));
                $str .= "<br><br><b >Taken by:</b> " . ((isset($h->taken_by) && $h->taken_by != '') ? (userfullname($h->taken_by) . ' (' . date('h:i a D M j, Y', strtotime($h->time_taken)) . ')') : "<span class='badge badge-secondary'>Not billed</span>");
                $str .= "</small>";
                return $str;
            })
            ->editColumn('result', function ($his) {
                $str = "<b > Blood Pressure (mmHg): </b>" . $his->blood_pressure . "<br>";
                $str .= "<b > Body Temperature (°C): </b>" . $his->temp . "<br>";
                $str .= "<b > Body Weight (Kg): </b>" . $his->weight . "<br>";
                $str .= "<b > Respiratory Rate (BPM) :</b>" . $his->resp_rate . "<br>";
                $str .= "<b > Heart Rate (BPM): </b>" . $his->heart_rate . "<br><hr>";
                $str .= $his->other_notes ?? 'N/A';
                return $str;
            })
            ->rawColumns(['created_at', 'result', 'select', 'patient_id'])
            ->make(true);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\VitalSign  $vitalSign
     * @return \Illuminate\Http\Response
     */
    public function show(VitalSign $vitalSign)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\VitalSign  $vitalSign
     * @return \Illuminate\Http\Response
     */
    public function edit(VitalSign $vitalSign)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\VitalSign  $vitalSign
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, VitalSign $vitalSign)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\VitalSign  $vitalSign
     * @return \Illuminate\Http\Response
     */
    public function destroy(VitalSign $vitalSign)
    {
        //
    }
}
