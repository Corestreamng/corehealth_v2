<?php

namespace App\Http\Controllers;

use App\Models\AdmissionRequest;
use App\Models\Clinic;
use App\Models\Encounter;
use App\Models\DoctorQueue;
use Yajra\DataTables\DataTables;
use App\Models\User;
use App\Models\Staff;
use App\Models\Hmo;
use App\Models\LabServiceRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\patient;
use App\Models\ProductOrServiceRequest;
use App\Models\ProductRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function NewEncounterList()
    {
        $doc = Staff::where('user_id', Auth::id())->first();
        $queue = DoctorQueue::where(function ($q) use ($doc) {
            $q->where('clinic_id', $doc->clinic_id);
            $q->orWhere('staff_id', $doc->id);
        })
            ->where('status', 1)->orderBy('created_at', 'DESC')->get();
        //dd($pc);
        return Datatables::of($queue)
            ->addIndexColumn()
            ->editColumn('fullname', function ($queue) {
                $patient = patient::find($queue->patient_id);
                return (userfullname($patient->user_id));
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
                return (userfullname($doc->user_id));
            })
            ->addColumn('file_no', function ($queue) {
                $patient = patient::find($queue->patient_id);
                return $patient->file_no;
            })
            ->addColumn('view', function ($queue) {

                // if (Auth::user()->hasPermissionTo('user-show') || Auth::user()->hasRole(['Super-Admin', 'Admin'])) {

                $url =  route(
                    'encounters.create',
                    ['patient_id' => $queue->patient_id, 'req_entry_id' => $queue->request_entry_id, 'queue_id' => $queue->id]
                );
                return '<a href="' . $url . '" class="btn btn-success btn-sm" ><i class="fa fa-street-view"></i> Encounter</a>';
                // } else {

                //     $label = '<span class="label label-warning">Not Allowed</span>';
                //     return $label;
                // }
            })
            ->rawColumns(['fullname', 'view',])
            ->make(true);
    }

    public function PrevEncounterList()
    {
        $doc = Staff::where('user_id', Auth::id())->first();
        $queue = DoctorQueue::where(function ($q) use ($doc) {
            $q->where('clinic_id', $doc->clinic_id);
            $q->orWhere('staff_id', $doc->id);
        })
            ->where('status', '>', 1)->orderBy('created_at', 'DESC')->get();
        //dd($pc);
        return Datatables::of($queue)
            ->addIndexColumn()
            ->editColumn('fullname', function ($queue) {
                $patient = patient::find($queue->patient_id);
                return (userfullname($patient->user_id));
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
                return (userfullname($doc->user_id));
            })
            ->addColumn('file_no', function ($queue) {
                $patient = patient::find($queue->patient_id);
                return $patient->file_no;
            })
            ->addColumn('view', function ($queue) {

                // if (Auth::user()->hasPermissionTo('user-show') || Auth::user()->hasRole(['Super-Admin', 'Admin'])) {

                    $url =  route('patient.show', $queue->patient_id);
                    return '<a href="' . $url . '" class="btn btn-success btn-sm" ><i class="fa fa-street-view"></i> View</a>';
                // } else {

                //     $label = '<span class="label label-warning">Not Allowed</span>';
                //     return $label;
                // }
            })
            ->rawColumns(['fullname', 'view',])
            ->make(true);
    }


    public function EncounterHistoryList($patient_id)
    {

        $hist = Encounter::where('patient_id', $patient_id)->where('notes', '!=', null)->get();
        //dd($pc);
        return Datatables::of($hist)
            ->addIndexColumn()
            ->editColumn('doctor_id', function ($hist) {
                return (userfullname($hist->doctor_id));
            })
            ->editColumn('created_at', function ($hist) {
                return date('h:i a D M j, Y', strtotime($hist->created_at));
            })
            ->editColumn('notes', function ($hist) {
                $str = '';
                $reasons_for_encounter = json_decode($hist->reasons_for_encounter);
                if ($reasons_for_encounter != '') {
                    $str .= "<h5>Reasons for Encounter</h5>";
                    foreach ($reasons_for_encounter as $reason) {
                        $str .= "<span class='badge badge-success m-2'>$reason</span>";
                    }
                    $str .= "<br>";
                }
                return $str .=  $hist->notes;
            })
            ->rawColumns(['notes'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        try {
            $doctor = Staff::where('user_id', Auth::id())->first();
            $patient = patient::find(request()->get('patient_id'));
            $clinic = Clinic::find($doctor->clinic_id);
            $req_entry = ProductOrServiceRequest::find(request()->get('req_entry_id'));
            return view('admin.doctors.new_encounter')->with(['patient' => $patient, 'doctor' => $doctor, 'clinic' => $clinic, 'req_entry' => $req_entry]);
        } catch (\Exception $e) {

            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // dd($request->all());
        try {
            $request->validate([
                'doctor_diagnosis' => 'required|string',
                'consult_presc_dose' => 'nullable|array|required_with:consult_presc_id',
                'consult_presc_id' => 'nullable|array|required_with:consult_presc_dose',
                'consult_inves_note' => 'nullable|array|required_with:consult_inves_id',
                'consult_inves_id' => 'nullable|array|required_with:consult_inves_note',
                'consult_presc_dose.*' => 'required_with:consult_presc_dose',
                'consult_presc_id.*' => 'required_with:consult_presc_id',
                'consult_inves_note.*' => 'required_with:consult_inves_note',
                'consult_inves_id.*' => 'required_with:consult_inves_id',
                'admit_note' => 'nullable|string',
                'consult_admit' => 'nullable',
                'req_entry_service_id' => 'required',
                'req_entry_id' => 'required',
                'patient_id' => 'required',
                'queue_id' => 'required'
            ]);

            if (isset($request->consult_presc_id) && isset($request->consult_presc_dose)) {
                if (count($request->consult_presc_id) !== count($request->consult_presc_dose)) {
                    $msg = "Please fill out dosages for all selected products";
                    return redirect()->back()->withInput()->withMessage($msg)->withMessageType('warning');
                }
            }

            if (isset($request->consult_inves_id) && isset($request->consult_inves_note)) {
                if (count($request->consult_inves_id) !== count($request->consult_inves_note)) {
                    $msg = "Please fill out notes for all selected services";
                    return redirect()->back()->withInput()->withMessage($msg)->withMessageType('warning');
                }
            }

            DB::beginTransaction();
            $encounter = new Encounter;
            $encounter->service_id = $request->req_entry_service_id;
            $encounter->service_request_id = $request->req_entry_id;
            $encounter->doctor_id = Auth::id();
            $encounter->patient_id = $request->patient_id;
            $encounter->reasons_for_encounter = null;
            $encounter->notes = $request->doctor_diagnosis;
            $encounter->save();

            if (isset($request->consult_inves_id) && count($request->consult_inves_id) > 0) {
                for ($r = 0; $r < count($request->consult_inves_id); $r++) {
                    $invest = new LabServiceRequest;
                    $invest->service_id = $request->consult_inves_id[$r];
                    $invest->note = $request->consult_inves_note[$r];
                    $invest->encounter_id = $encounter->id;
                    $invest->patient_id = $request->patient_id;
                    $invest->doctor_id = Auth::id();
                    $invest->save();
                }
            }

            if (isset($request->consult_presc_id) && count($request->consult_presc_id) > 0) {
                for ($r = 0; $r < count($request->consult_presc_id); $r++) {
                    $presc = new ProductRequest();
                    $presc->product_id = $request->consult_presc_id[$r];
                    $presc->dose = $request->consult_presc_dose[$r];
                    $presc->encounter_id = $encounter->id;
                    $presc->patient_id = $request->patient_id;
                    $presc->doctor_id = Auth::id();
                    $presc->save();
                }
            }

            $queue = DoctorQueue::where('id', $request->queue_id)->update([
                'status' => 2
            ]);

            if($request->consult_admit == 1){
                $admit = new AdmissionRequest;
                $admit->encounter_id = $encounter->id;
                $admit->doctor_id = Auth::id();
                $admit->patient_id = $request->patient_id;
                $admit->note = $request->admit_note;
                $admit->save();
            }
            DB::commit();
            return redirect()->route('encounters.index')->with(['message' => "Encounter Notes Saved Successfully", 'message_type' => 'success']);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Encounter  $encounter
     * @return \Illuminate\Http\Response
     */
    public function show(Encounter $encounter)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Encounter  $encounter
     * @return \Illuminate\Http\Response
     */
    public function edit(Encounter $encounter)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Encounter  $encounter
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Encounter $encounter)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Encounter  $encounter
     * @return \Illuminate\Http\Response
     */
    public function destroy(Encounter $encounter)
    {
        //
    }
}
