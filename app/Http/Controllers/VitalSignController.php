<?php

namespace App\Http\Controllers;

use App\Models\VitalSign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;

class VitalSignController extends Controller
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
                'bloodPressure' => 'required',
                'bodyTemperature' => 'required',
                'datetimeField' => 'required'
            ]);

            DB::beginTransaction();
            $vitalSign = new VitalSign;

            $vitalSign->taken_by = Auth::id();
            $vitalSign->patient_id = $request->patient_id;
            $vitalSign->temp = $request->bodyTemperature;
            $vitalSign->blood_pressure = $request->bloodPressure;
            $vitalSign->heart_rate = $request->heartRate;
            $vitalSign->resp_rate = $request->respiratoryRate;
            $vitalSign->other_notes = $request->otherNotes;
            $vitalSign->time_taken = $request->datetimeField;
            $vitalSign->save();
            DB::commit();
            return back()->withMessage('Vitals saved successfully')->withMessageType('success');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage(), ['exception' => $e]);
            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage() . 'line:' . $e->getLine());
        }
    }

    public function patientVitals($patient_id)
    {
        $his = VitalSign::with(['patient', 'takenBy','requstedBy'])
            ->where('status', 1)->where('patient_id', $patient_id)->orderBy('created_at', 'DESC')->get();
        //dd($pc);
        return Datatables::of($his)
            ->addIndexColumn()

            ->editColumn('created_at', function ($h) {
                $str = "<small>";
                $str .= "<b class = 'mb-2'>Requested by: </b>" . ((isset($h->requested_by)  && $h->requested_by != null) ? (userfullname($h->requested_by) . ' (' . date('h:i a D M j, Y', strtotime($h->created_at)) . ')') : "<span class='badge badge-secondary'>N/A</span>");
                $str .= "<br><b>Last Updated On:</b> " . date('h:i a D M j, Y', strtotime($h->updated_at));
                $str .= "<br><b>Taken by:</b> " . ((isset($h->taken_by) && $h->taken_by != null) ? (userfullname($h->taken_by) . ' (' . date('h:i a D M j, Y', strtotime($h->time_taken)) . ')') : "<span class='badge badge-secondary'>Not billed</span>");
                return "</small>" . $str;
            })
            ->editColumn('result', function ($his) {
                $str = "<b class = 'mb-2'> Blood Pressure (mmHg): </b>" . $his->blood_pressure . "<br>";
                $str .= "<b class = 'mb-2'> Body Temperature (°C): </b>" . $his->temp . "<br>";
                $str .= "<b class = 'mb-2'> Respiratory Rate (BPM) :</b>" . $his->resp_rate . "<br>";
                $str .= "<b class = 'mb-2'> Heart Rate (BPM): </b>" . $his->heart_rate . "<br><hr>";
                $str .= $his->other_notes ?? 'N/A';
                return $str;
            })
            ->rawColumns(['created_at', 'result'])
            ->make(true);
    }
    public function allPatientVitals($patient_id){
        $vitals = VitalSign::where('status',1)->where('patient_id', $patient_id)->limit(30)->get();
        return json_encode($vitals);
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
