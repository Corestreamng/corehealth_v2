<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\Encounter;
use App\Models\DoctorQueue;
use Yajra\DataTables\DataTables;
use App\Models\User;
use App\Models\Staff;
use App\Models\Hmo;
use Illuminate\Support\Facades\Auth;
use App\Models\patient;
use Illuminate\Http\Request;

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

    public function NewEncounterList(){
        $doc = Staff::where('user_id',Auth::id())->first();
        $queue = DoctorQueue::where('clinic_id',$doc->clinic_id)->orWhere('staff_id', $doc->id)->orderBy('created_at', 'DESC')->get();
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
            ->editColumn('staff_id', function ($queue) use($doc) {
                return (userfullname($doc->user_id));
            })
            ->addColumn('file_no', function ($queue) {
                $patient = patient::find($queue->patient_id);
                return $patient->file_no;
            })
            ->addColumn('view', function ($queue) {

                // if (Auth::user()->hasPermissionTo('user-show') || Auth::user()->hasRole(['Super-Admin', 'Admin'])) {

                $url =  route('encounters.create', ['patient_id'=>$queue->patient_id]);
                return '<a href="' . $url . '" class="btn btn-success btn-sm" ><i class="fa fa-street-view"></i> Encounter</a>';
                // } else {

                //     $label = '<span class="label label-warning">Not Allowed</span>';
                //     return $label;
                // }
            })
            ->rawColumns(['fullname', 'view',])
            ->make(true);
    }

    public function EncounterHistoryList($patient_id){
        
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
                $reasons_for_encounter = json_decode($hist->reasons_for_encounter);
                $str = "<h5>Reasons for Encounter</h5>";
                foreach($reasons_for_encounter as $reason){
                    $str .= "<span class='badge badge-success m-2'>$reason</span>";
                }
                $str .= "<br>";
                $str .=  $hist->notes;
            })
            ->rawColumns(['notes',])
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
            $doctor = Staff::where('user_id',Auth::id())->first();
            $patient = patient::find(request()->get('patient_id'));
            $clinic = Clinic::find($doctor->clinic_id);
            return view('admin.doctors.new_encounter')->with(['patient'=> $patient,'doctor' => $doctor,'clinic'=> $clinic]);
        } catch (\Exception $e) {

            return redirect()->back()->withMessage("An error occurred " . $e->getMessage());
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
        $request->validate([
            
        ]);
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
