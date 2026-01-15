<?php

namespace App\Http\Controllers;

use App\Models\patientProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class PatientProfileController extends Controller
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
    public function create(Request $request)
    {
        $request->validate([
            'form_id' => 'required'
        ]);

        if ($request->form_id == 'test') {
            $formdata = '{
            "name": {
                "name": "Name",
                "label": "name",
                "is_required": "required",
                "extensions": "",
                "options": [],
                "type": "text"
            },
            "message": {
                "name": "Message",
                "label": "message",
                "is_required": "required",
                "extensions": "",
                "options": [],
                "type": "textarea"
            },
            "phone": {
                "name": "Phone",
                "label": "phone",
                "is_required": "optional",
                "extensions": "",
                "options": [],
                "type": "text"
            },
            "some_tags": {
                "name": "Some Tags",
                "label": "some_tags",
                "is_required": "optional",
                "extensions": "",
                "options": [
                "Food",
                "Drinks",
                "Meat"
                ],
                "type": "checkbox"
            }
            }';
        } elseif ($request->form_id == 'anc') {
            $formdata = '
            {
                "parity": {
                    "name": "Parity",
                    "label": "parity",
                    "is_required": "required",
                    "extensions": "",
                    "options": [],
                    "type": "text"
                },
                "antenatal_clinic_attendance": {
                    "name": "Antenatal Clinic Attendance",
                    "label": "antenatal_clinic_attendance",
                    "is_required": "required",
                    "extensions": "",
                    "options": ["N", "R"],
                    "type": "radio"
                },
                "lmp_(write_exact_date)": {
                    "name": "LMP (write exact date)",
                    "label": "lmp_(write_exact_date)",
                    "is_required": "required",
                    "extensions": "",
                    "options": [],
                    "type": "text"
                },
                "age_of_pregnancy_(in_weeks)": {
                    "name": "Age of Pregnancy (in weeks)",
                    "label": "age_of_pregnancy_(in_weeks)",
                    "is_required": "required",
                    "extensions": "",
                    "options": [],
                    "type": "text"
                },
                "weight_(kg)": {
                    "name": "Weight (kg)",
                    "label": "weight_(kg)",
                    "is_required": "required",
                    "extensions": "",
                    "options": [],
                    "type": "text"
                },
                "height_(m)": {
                    "name": "Height (M)",
                    "label": "height_(m)",
                    "is_required": "required",
                    "extensions": "",
                    "options": [],
                    "type": "text"
                },
                "blood_pressure": {
                    "name": "Blood Pressure",
                    "label": "blood_pressure",
                    "is_required": "required",
                    "extensions": "",
                    "options": [],
                    "type": "text"
                },
                "no_of_antenatal_clinic_visit(s)to_date": {
                    "name": "No of Antenatal Clinic Visit(s) to Date",
                    "label": "no_of_antenatal_clinic_visit(s)_to_date",
                    "is_required": "required",
                    "extensions": "",
                    "options": [],
                    "type": "text"
                },
                "hiv_testing_services": {
                    "name": "HIV Testing Services",
                    "label": "hiv_testing_services",
                    "is_required": "required",
                    "extensions": "",
                    "options": ["Yes", "No"],
                    "type": "radio"
                },
                "female_genital_mutilation": {
                    "name": "Female Genital Mutilation",
                    "label": "female_genital_mutilation",
                    "is_required": "required",
                    "extensions": "",
                    "options": ["Yes", "No"],
                    "type": "radio"
                },
                "family_planning": {
                    "name": "Family Planning",
                    "label": "family_planning",
                    "is_required": "required",
                    "extensions": "",
                    "options": ["Yes", "No"],
                    "type": "radio"
                },
                "maternal_nutrition": {
                    "name": "Maternal Nutrition",
                    "label": "maternal_nutrition",
                    "is_required": "required",
                    "extensions": "",
                    "options": ["Yes", "No"],
                    "type": "radio"
                },
                "early_intiation_of_breastfeeding": {
                    "name": "Early Intiation of Breastfeeding",
                    "label": "early_intiation_of_breastfeeding",
                    "is_required": "required",
                    "extensions": "",
                    "options": ["Yes", "No"],
                    "type": "radio"
                },
                "exclusive_breastfeeding": {
                    "name": "Exclusive Breastfeeding",
                    "label": "exclusive_breastfeeding",
                    "is_required": "required",
                    "extensions": "",
                    "options": ["Yes", "No"],
                    "type": "radio"
                },
                "syphilis_testing": {
                    "name": "Syphilis Testing",
                    "label": "syphilis_testing",
                    "is_required": "required",
                    "extensions": "",
                    "options": ["Not Done", "Positive", "Negative"],
                    "type": "radio"
                },
                "syphilis_treatment": {
                    "name": "Syphilis Treatment",
                    "label": "syphilis_treatment",
                    "is_required": "required",
                    "extensions": "",
                    "options": ["Treated", "Not Treated"],
                    "type": "radio"
                },
                "hepatitis_b_testing": {
                    "name": "Hepatitis B Testing",
                    "label": "hepatitis_b_testing",
                    "is_required": "required",
                    "extensions": "",
                    "options": ["Not Done", "Positive", "Negative"],
                    "type": "radio"
                },
                "hepatitis_c_testing": {
                    "name": "Hepatitis C Testing",
                    "label": "hepatitis_c_testing",
                    "is_required": "required",
                    "extensions": "",
                    "options": ["Not Done", "Positive", "Negative"],
                    "type": "radio"
                },
                "hb/pcv(g/dl_or_%)": {
                    "name": "HB/PCV (g/dl or %)",
                    "label": "hb/pcv_(g/dl_or_%)",
                    "is_required": "required",
                    "extensions": "",
                    "options": [],
                    "type": "text"
                },
                "sugar_(gestational_diabetes)": {
                    "name": "Sugar (Gestational Diabetes)",
                    "label": "sugar_(gestational_diabetes)",
                    "is_required": "required",
                    "extensions": "",
                    "options": [],
                    "type": "text"
                },
                "urinalysis_-sugar_result": {
                    "name": "Urinalysis - Sugar Result",
                    "label": "urinalysis-sugar_result",
                    "is_required": "required",
                    "extensions": "",
                    "options": [],
                    "type": "text"
                },
                "urinalysis-protein_result": {
                    "name": "Urinalysis - Protein Result",
                    "label": "urinalysis-protein_result",
                    "is_required": "required",
                    "extensions": "",
                    "options": [],
                    "type": "text"
                },
                "llin_given?": {
                    "name": "LLIN Given?",
                    "label": "llin_given?",
                    "is_required": "required",
                    "extensions": "",
                    "options": ["Yes", "No"],
                    "type": "radio"
                },
                "doses_of_ipt_given": {
                    "name": "Doses of IPT Given",
                    "label": "doses_of_ipt_given",
                    "is_required": "required",
                    "extensions": "",
                    "options": ["IPT1", "IPT2", "IPT3", "IPT4+"],
                    "type": "radio"
                },
                "hematinics_given(iron_and_folic_acid_supplement)": {
                    "name": "Hematinics Given (Iron and Folic Acid supplement)",
                    "label": "hematinics_given_(iron_and_folic_acid_supplement)",
                    "is_required": "required",
                    "extensions": "",
                    "options": [],
                    "type": "textarea"
                },
                "td_(indicate)": {
                    "name": "TD (indicate)",
                    "label": "td_(indicate)",
                    "is_required": "required",
                    "extensions": "",
                    "options": ["Td1", "Td2", "Td3", "Td4", "Td5"],
                    "type": "radio"
                },
                "associated_problems": {
                    "name": "Associated Problems",
                    "label": "associated_problems",
                    "is_required": "optional",
                    "extensions": "",
                    "options": [],
                    "type": "textarea"
                }
            }';
        }

        $formdata = json_decode($formdata);
        $form = generateForm($formdata);
        return response(['formdata' => $form, 'form_id' => $request->form_id]);
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
            'form_id' => 'required',
        ]);

        try {
            $data = $request->except(['_token', 'patient_id', 'encounter_id', 'form_id']);

            $d = new PatientProfile();
            $d->encounter_id = $request->encounter_id;
            $d->patient_id = $request->patient_id;
            $d->filled_by = Auth::id();
            $d->form_data = json_encode($data);
            $d->form_id = $request->form_id;
            $d->save();
            return back()->withMessage("Form data saved, continue consulting")->withMessageType('success');
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            return redirect()->back()->withInput()->withMessage("An error occurred while saving patient profile/form");
        }
    }

    public function listPatientForm($patient_id)
    {
        $his = PatientProfile::with(['patient', 'doctor'])
            ->where('patient_id', $patient_id)->orderBy('created_at', 'DESC')->get();
        //dd($pc);
        return DataTables::of($his)
            ->addIndexColumn()

            ->editColumn('form_data', function ($his) {
                $str = "";
                $str .= "<span class = 'badge bg-primary'>";
                $str .= keyToTitle($his->form_id);
                $str .= "</span>";
                $str .= '<hr>';
                $str .= "<small>";
                $str .= "<b >Taken by:</b> " . ((isset($his->filled_by) && $his->filled_by != null) ? (userfullname($his->filled_by) . ' (' . date('h:i a D M j, Y', strtotime($his->created_at)) . ')') : "<span class='badge badge-secondary'>N/A</span>");
                $str .= "</small>";
                $str .= '<hr>';
                $data = json_decode($his->form_data);
                foreach ($data as $key => $d) {
                    $str .= "<b >" . keyToTitle($key) . " </b> : " . str_replace('\\', '', str_replace('"', " ", json_encode($d))) . "<br><br>";
                }

                return $str;
            })
            ->rawColumns(['form_data'])
            ->make(true);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\patientProfile  $patientProfile
     * @return \Illuminate\Http\Response
     */
    public function show(PatientProfile $patientProfile)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\patientProfile  $patientProfile
     * @return \Illuminate\Http\Response
     */
    public function edit(PatientProfile $patientProfile)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\patientProfile  $patientProfile
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PatientProfile $patientProfile)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\patientProfile  $patientProfile
     * @return \Illuminate\Http\Response
     */
    public function destroy(PatientProfile $patientProfile)
    {
        //
    }
}
