<?php

namespace App\Http\Controllers;

use App\Models\Procedure;
use Illuminate\Http\Request;
use App\Models\Clinic;
use App\Models\DoctorQueue;
use App\Models\Encounter;
use App\Models\Hmo;
use App\Models\patient;
use App\Models\Staff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;

class ProcedureController extends Controller
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

    public function NewProcedureList()
    {
        try {
            $queue = Procedure::where('requested_by', Auth::id())
                ->where('status', 1)->orderBy('created_at', 'DESC')->get();
            // dd($pc);
            return Datatables::of($queue)
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
                ->editColumn('staff_id', function ($queue) {
                    return userfullname($doc->user_id);
                })
                ->addColumn('file_no', function ($queue) {
                    $patient = patient::find($queue->patient_id);

                    return $patient->file_no;
                })
                ->addColumn('view', function ($queue) {
                    // if (Auth::user()->hasPermissionTo('user-show') || Auth::user()->hasRole(['Super-Admin', 'Admin'])) {

                    $url = route(
                        'encounters.create',
                        ['patient_id' => $queue->patient_id, 'req_entry_id' => $queue->request_entry_id, 'queue_id' => $queue->id]
                    );

                    return '<a href="' . $url . '" class="btn btn-success btn-sm" ><i class="fa fa-street-view"></i> Procedure</a>';
                    // } else {

                    //     $label = '<span class="label label-warning">Not Allowed</span>';
                    //     return $label;
                    // }
                })
                ->rawColumns(['fullname', 'view'])
                ->make(true);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['exception' => $e]);

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }



    public function ContProcedureList()
    {
        try {
            $currentDateTime = Carbon::now();
            $timeThreshold = $currentDateTime->subHours(appsettings('consultation_cycle_duration', 24));

            // dd($timeThreshold);
            $queue = Procedure::where('requested_by', Auth::id())
                ->where('status', 2)
                ->where('created_at', '>=', $timeThreshold)
                ->orderBy('created_at', 'DESC')
                ->get();
            // dd($pc);
            return Datatables::of($queue)
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
                ->editColumn('staff_id', function ($queue) {
                    return userfullname($doc->user_id);
                })
                ->addColumn('file_no', function ($queue) {
                    $patient = patient::find($queue->patient_id);

                    return $patient->file_no;
                })
                ->addColumn('view', function ($queue) {
                    // if (Auth::user()->hasPermissionTo('user-show') || Auth::user()->hasRole(['Super-Admin', 'Admin'])) {

                    $url = route(
                        'encounters.create',
                        ['patient_id' => $queue->patient_id, 'req_entry_id' => $queue->request_entry_id, 'queue_id' => $queue->id]
                    );

                    return '<a href="' . $url . '" class="btn btn-success btn-sm" ><i class="fa fa-street-view"></i> Procedure</a>';
                    // } else {

                    //     $label = '<span class="label label-warning">Not Allowed</span>';
                    //     return $label;
                    // }
                })
                ->rawColumns(['fullname', 'view'])
                ->make(true);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['exception' => $e]);

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function PrevProcedureList()
    {
        try {
            $doc = Staff::where('user_id', Auth::id())->first();
            $queue = Procedure::where('requested_by', Auth::id())->where('status', '>', '2')
                ->orderBy('created_at', 'DESC')->get();

            // dd($pc);
            return Datatables::of($queue)
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
                ->editColumn('staff_id', function ($queue) {
                    return userfullname($doc->user_id);
                })
                ->addColumn('file_no', function ($queue) {
                    $patient = patient::find($queue->patient_id);

                    return $patient->file_no;
                })
                ->addColumn('view', function ($queue) {
                    // if (Auth::user()->hasPermissionTo('user-show') || Auth::user()->hasRole(['Super-Admin', 'Admin'])) {

                    $url = route('patient.show', $queue->patient_id);

                    return '<a href="' . $url . '" class="btn btn-success btn-sm" ><i class="fa fa-street-view"></i> View</a>';
                    // } else {

                    //     $label = '<span class="label label-warning">Not Allowed</span>';
                    //     return $label;
                    // }
                })
                ->rawColumns(['fullname', 'view'])
                ->make(true);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['exception' => $e]);

            return redirect()->back()->withInput()->with('error', $e->getMessage());
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
     * @param  \App\Models\Procedure  $procedure
     * @return \Illuminate\Http\Response
     */
    public function show(Procedure $procedure)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Procedure  $procedure
     * @return \Illuminate\Http\Response
     */
    public function edit(Procedure $procedure)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Procedure  $procedure
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Procedure $procedure)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Procedure  $procedure
     * @return \Illuminate\Http\Response
     */
    public function destroy(Procedure $procedure)
    {
        //
    }
}
