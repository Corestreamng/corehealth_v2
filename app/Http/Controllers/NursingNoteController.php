<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NursingNote;
use App\Models\patient;
use App\Models\NursingNoteType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Storage;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class NursingNoteController extends Controller
{
    /**
     * Display a listing of the resource.
     *@param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $patient_id = $request->input('patient_id');
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

        return view('admin.nursing_notes.index', compact(
            'patient',
            'dependant',
            'observation_note',
            'treatment_sheet',
            'io_chart',
            'labour_record',
            'others_record',
            'observation_note_template',
            'treatment_sheet_template',
            'io_chart_template',
            'labour_record_template',
            'others_record_template'
        ));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // dd($request->patient_id);
        $rules = [
            'patient_id'  => 'required',
            'note_type'  => 'required',
            'the_text' => 'required',
        ];
        try {


            $v = validator()->make($request->all(), $rules);

            if ($v->fails()) {
                // Alert::error('Error Title', 'One or more information is needed.');
                // return redirect()->back()->withInput()->withErrors($v);
                // return redirect()->back()->with('toast_error', $v->messages()->all()[0])->withInput();
                return redirect()->back()->with('toast_error', $v->messages()->all()[0])->withInput();
            } else {
                $type = $request->note_type;
                $patient_id = $request->patient_id;
                $is_new = true;
                if ($type == 1) {
                    $observation_note = NursingNote::with(['patient', 'createdBy', 'type'])
                        ->where('patient_id', $patient_id)
                        ->where('completed', false)
                        ->where('nursing_note_type_id', 1)
                        ->first() ?? null;

                    if ($observation_note) {
                        $is_new = false;
                        if ($observation_note->update(['note' => ($request->the_text), 'updated_by' => Auth::id()])) {
                            return back()->withMessage("Nursing Note Updated")->withMessageType('success');
                        }
                    }
                } elseif ($type == 2) {
                    $treatment_sheet = NursingNote::with(['patient', 'createdBy', 'type'])
                        ->where('patient_id', $patient_id)
                        ->where('completed', false)
                        ->where('nursing_note_type_id', 2)
                        ->first() ?? null;

                    if ($treatment_sheet) {
                        $is_new = false;
                        if ($treatment_sheet->update(['note' => ($request->the_text), 'updated_by' => Auth::id()])) {
                            return back()->withMessage("Nursing Note Updated")->withMessageType('success');;
                        }
                    }
                } elseif ($type == 3) {
                    $io_chart = NursingNote::with(['patient', 'createdBy', 'type'])
                        ->where('patient_id', $patient_id)
                        ->where('completed', false)
                        ->where('nursing_note_type_id', 3)
                        ->first() ?? null;

                    if ($io_chart) {
                        $is_new = false;
                        if ($io_chart->update(['note' => ($request->the_text), 'updated_by' => Auth::id()])) {
                            return back()->withMessage("Nursing Note Updated")->withMessageType('success');;
                        }
                    }
                } elseif ($type == 5) {
                    $others_record = NursingNote::with(['patient', 'createdBy', 'type'])
                        ->where('patient_id', $patient_id)
                        ->where('completed', false)
                        ->where('nursing_note_type_id', 5)
                        ->first() ?? null;

                    if ($others_record) {
                        $is_new = false;
                        if ($others_record->update(['note' => ($request->the_text), 'updated_by' => Auth::id()])) {
                            return back()->withMessage("Nursing Note Updated")->withMessageType('success');;
                        }
                    }
                } else {
                    $labour_record = NursingNote::with(['patient', 'createdBy', 'type'])
                        ->where('patient_id', $patient_id)
                        ->where('completed', false)
                        ->where('nursing_note_type_id', 4)
                        ->first() ?? null;

                    if ($labour_record) {
                        $is_new = false;
                        if ($labour_record->update(['note' => ($request->the_text), 'updated_by' => Auth::id()])) {
                            return back()->withMessage("Nursing Note Updated")->withMessageType('success');;
                        }
                    }
                }

                if ($is_new == 1) {
                    $note = new NursingNote;

                    $note->patient_id = $request->patient_id;
                    $note->nursing_note_type_id = $request->note_type;
                    $note->created_by = Auth::id();
                    $note->note = $request->the_text;

                    if ($note->save()) {
                        return redirect()->back()->withMessage("Nursing Note Added")->withMessageType('success');
                    }
                }
            }
        } catch (\Exception $e) {

            return redirect()->back()->withMessage("An error occurred " . $e->getMessage());
        }
    }

    public function new_note(Request $request)
    {
        $patient_id = $request->input('patient_id');
        $type = $request->note_type;


        if ($type == 1) {
            $observation_note = NursingNote::with(['patient', 'createdBy', 'type'])
                ->where('patient_id', $patient_id)
                ->where('completed', false)
                ->where('nursing_note_type_id', 1)
                ->first() ?? null;

            if ($observation_note) {
                if ($observation_note->update(['completed' => true, 'note' => $this->remove_editable($observation_note->note), 'updated_by' => Auth::id()])) {
                    return back()->withMessage("Nursing Note Updated")->withMessageType('success');
                }
            }
        } elseif ($type == 2) {
            $treatment_sheet = NursingNote::with(['patient', 'createdBy', 'type'])
                ->where('patient_id', $patient_id)
                ->where('completed', false)
                ->where('nursing_note_type_id', 2)
                ->first() ?? null;

            if ($treatment_sheet) {
                if ($treatment_sheet->update(['completed' => true, 'note' => $this->remove_editable($treatment_sheet->note), 'updated_by' => Auth::id()])) {
                    return back()->withMessage("Nursing Note Updated")->withMessageType('success');;
                }
            }
        } elseif ($type == 3) {
            $io_chart = NursingNote::with(['patient', 'createdBy', 'type'])
                ->where('patient_id', $patient_id)
                ->where('completed', false)
                ->where('nursing_note_type_id', 3)
                ->first() ?? null;

            if ($io_chart) {
                if ($io_chart->update(['completed' => true, 'note' => $this->remove_editable($io_chart->note), 'updated_by' => Auth::id()])) {
                    return back()->withMessage("Nursing Note Updated")->withMessageType('success');;
                }
            }
        } elseif ($type == 5) {
            $others_record = NursingNote::with(['patient', 'createdBy', 'type'])
                ->where('patient_id', $patient_id)
                ->where('completed', false)
                ->where('nursing_note_type_id', 5)
                ->first() ?? null;

            if ($others_record) {
                if ($others_record->update(['completed' => true, 'note' => $this->remove_editable($others_record->note), 'updated_by' => Auth::id()])) {
                    return back()->withMessage("Nursing Note Updated")->withMessageType('success');;
                }
            }
        } else {
            $labour_record = NursingNote::with(['patient', 'createdBy', 'type'])
                ->where('patient_id', $patient_id)
                ->where('completed', false)
                ->where('nursing_note_type_id', 4)
                ->first() ?? null;

            if ($labour_record) {
                if ($labour_record->update(['completed' => true, 'note' => $this->remove_editable($labour_record->note), 'updated_by' => Auth::id()])) {
                    return back()->withMessage("Nursing Note Updated")->withMessageType('success');;
                }
            }
        }
    }

    public function remove_editable($the_string)
    {
        //make all contenteditable section uneditable, so that they wont be editable when they show up in medical history
        $the_string = str_replace('contenteditable="true"', 'contenteditable="false"', $the_string);
        $the_string = str_replace("contenteditable='true'", "contenteditable='false'", $the_string);
        $the_string = str_replace('contenteditable = "true"', 'contenteditable="false"', $the_string);
        $the_string = str_replace("contenteditable ='true'", "contenteditable='false'", $the_string);
        $the_string = str_replace('contenteditable= "true"', 'contenteditable="false"', $the_string);
        //remove all black borders
        $the_string = str_replace(' black', ' gray', $the_string);
        return $the_string;
    }

    public function patientNursngNote($patient_id, $note_type = 1)
    {
        $his = NursingNote::with(['patient', 'createdBy', 'type'])
            ->where('patient_id', $patient_id)
            // ->where('completed', false)
            ->where('nursing_note_type_id', $note_type)
            ->get();
        //dd($pc);
        return Datatables::of($his)
            ->addIndexColumn()
            ->addColumn('select', function ($h) {
                $str = "
                    <button type='button' class='btn btn-primary' onclick='setNoteInModal(this)' data-service-name = '" . $h->type->name . "' data-template = '" . $h->note . "' data-id='$h->id'>
                        View Sheet
                    </button>";
                return $str;
            })

            ->editColumn('created_by', function ($his) {
                $str = "<br><br><b >Opened By:</b> " . ((isset($his->created_by) && $his->created_by != null) ? (userfullname($his->created_by) . ' (' . date('h:i a D M j, Y', strtotime($his->created_at)) . ')') : "N/A");
                $str .= "<br><br><b >Last Updated By:</b> " . ((isset($his->updated_by) && $his->updated_by != null) ? (userfullname($his->updated_by) . ' (' . date('h:i a D M j, Y', strtotime($his->updated_at)) . ')') : "N/A");
                if ($his->completed == true) {
                    $str .= "<span class = 'badge badge-success'>Closed</span>";
                } else {
                    $str .= "<span class = 'badge badge-danger'>Still Open</span>";
                }
                return $str;
            })
            ->editColumn('nursing_note_type_id', function ($his) {
                return $his->type->name;
            })
            ->rawColumns(['created_by', 'select'])
            ->make(true);
    }


    /**
     * Display the specified resource.
     *
     * @param  \App\NursingNote  $nursingNote
     * @return \Illuminate\Http\Response
     */
    public function show(NursingNote $nursingNote)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\NursingNote  $nursingNote
     * @return \Illuminate\Http\Response
     */
    public function edit(NursingNote $nursingNote)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\NursingNote  $nursingNote
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, NursingNote $nursingNote)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\NursingNote  $nursingNote
     * @return \Illuminate\Http\Response
     */
    public function destroy(NursingNote $nursingNote)
    {
        //
    }
}
