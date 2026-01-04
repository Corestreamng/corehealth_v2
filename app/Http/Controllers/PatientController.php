<?php

namespace App\Http\Controllers;

use App\Models\NursingNote;
use App\Models\patient;
use App\Models\NursingNoteType;
use App\Models\User;
use App\Models\Hmo;
use App\Models\Clinic;
use App\Models\service;
use App\Models\Product;
use App\Models\UserCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;
use App\Http\Controllers\Controller;
use App\Models\AdmissionRequest;
use App\Models\Bed;
use App\Models\Encounter;
use App\Models\LabServiceRequest;
use App\Models\MiscBill;
use App\Models\PatientAccount;
use App\Models\ProductRequest;
use App\Models\ReasonForEncounter;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;



class PatientController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('admin.patients.index');
    }

    public function patientsList()
    {
        try {
            $pc = patient::with('user')->orderBy('created_at', 'DESC')->get();
            //dd($pc);
            return Datatables::of($pc)
                ->addIndexColumn()
                ->editColumn('fullname', function ($pc) {
                    return ($pc->user) ? (userfullname($pc->user->id)) : ($pc->user_id);
                })

                ->editColumn('created_at', function ($note) {
                    return date('h:i a D M j, Y', strtotime($note->created_at));
                })
                ->editColumn('hmo_id', function ($patient) {
                    return Hmo::find($patient->hmo_id)->name ?? 'N/A';
                })
                ->addColumn('view', function ($patient) {

                    // if (Auth::user()->hasPermissionTo('user-show') || Auth::user()->hasRole(['Super-Admin', 'Admin'])) {

                    $url =  route('patient.show', $patient->id);
                    return '<a href="' . $url . '" class="btn btn-success btn-sm" ><i class="fa fa-street-view"></i> View</a>';
                    // } else {

                    //     $label = '<span class="label label-warning">Not Allowed</span>';
                    //     return $label;
                    // }
                })
                ->addColumn('edit', function ($patient) {

                    // if (Auth::user()->hasPermissionTo('user-edit') || Auth::user()->hasRole(['Super-Admin', 'Admin'])) {

                    $url =  route('patient.edit', $patient->id);
                    return '<a href="' . $url . '" class="btn btn-info btn-sm" ><i class="fa fa-pencil"></i> Edit</a>';
                    // } else {

                    //     $label = '<span class="label label-warning">Not Allow</span>';
                    //     return $label;
                    // }
                })
                ->addColumn('delete', function ($patient) {

                    // if (Auth::user()->hasPermissionTo('user-delete') || Auth::user()->hasRole(['Super-Admin', 'Admin'])) {
                    $id = $patient->id;
                    return '<button type="button" class="delete-modal btn btn-danger btn-sm" data-toggle="modal" data-id="' . $id . '"><i class="fa fa-trash"></i> Delete</button>';
                    // } else {
                    //     $label = '<span class="label label-danger">Not Allow</span>';
                    //     return $label;
                    // }
                })
                ->rawColumns(['fullname', 'view', 'edit', 'delete'])
                ->make(true);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function PatientServicesRendered(Request $request, $patient_id)
    {
        $patient = patient::where('id', $patient_id)->first();
        if (null != $request->start_from && null != $request->stop_at) {
            try {
                $start = $request->start_from;
                $end = $request->stop_at;
                $consultation = Encounter::where('notes', '!=', null)->where('patient_id', $patient_id)->where('created_at', '<=', $end)->where('created_at', '>=', $start)->get();
                $prescription = ProductRequest::where('status', '>', 0)->where('patient_id', $patient_id)->where('created_at', '<=', $end)->where('created_at', '>=', $start)->get();
                $lab = LabServiceRequest::where('status', '>', 0)->where('patient_id', $patient_id)->where('created_at', '<=', $end)->where('created_at', '>=', $start)->get();

                $bed = AdmissionRequest::where('discharged', true)
                    ->where('patient_id', $patient_id)
                    ->where('discharge_date', '<=', $end)
                    ->where('discharge_date', '>=', $start)
                    ->get();

                foreach ($bed as $b) {
                    $days = date_diff(date_create($b->discharge_date), date_create($b->bed_assign_date))->days;
                    if ($days < 1) {
                        $days = 1;
                    }
                    $b->days = $days; // Add 'days' key to the Eloquent model
                }


                $misc = MiscBill::where('status', '>', 1)->where('patient_id', $patient_id)->where('created_at', '<=', $end)->where('created_at', '>=', $start)->get();

                return view('admin.encounters.services_rendered')->with([
                    'patient' => $patient,
                    'consultation' => $consultation,
                    'prescription' => $prescription,
                    'lab' => $lab,
                    'bed' => $bed,
                    'misc' => $misc,
                    'app' => appsettings()
                ]);
            } catch (\Exception $e) {
                Log::error($e->getMessage(), ['exception' => $e]);
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        } else {
            return view('admin.encounters.services_rendered')->with(['patient' => $patient]);
        }
    }

    public function listReturningPatients(Request $request)
    {
        try {
            // Get search query
            $q = trim($request->q ?? '');

            // If no search query provided, return empty dataset
            if (empty($q)) {
                return Datatables::of(collect([]))->make(true);
            }

            // Split search query into individual words for better matching
            $searchTerms = array_filter(explode(' ', $q));

            // Build query using Eloquent for better performance and maintainability
            $query = patient::leftJoin('users', 'patients.user_id', '=', 'users.id')
                ->select(
                    'patients.*',
                    'users.surname',
                    'users.firstname',
                    'users.othername'
                );

            // If multiple search terms, search for each term across all fields
            if (count($searchTerms) > 1) {
                $query->where(function($mainQuery) use ($searchTerms) {
                    foreach ($searchTerms as $term) {
                        $mainQuery->where(function($subQuery) use ($term) {
                            $subQuery->where('users.surname', 'like', '%' . $term . '%')
                                ->orWhere('users.firstname', 'like', '%' . $term . '%')
                                ->orWhere('users.othername', 'like', '%' . $term . '%')
                                ->orWhere('patients.phone_no', 'like', '%' . $term . '%')
                                ->orWhere('patients.file_no', 'like', '%' . $term . '%');
                        });
                    }
                });
            } else {
                // Single search term - search across all fields
                $query->where(function($subQuery) use ($q) {
                    $subQuery->where('users.surname', 'like', '%' . $q . '%')
                        ->orWhere('users.firstname', 'like', '%' . $q . '%')
                        ->orWhere('users.othername', 'like', '%' . $q . '%')
                        ->orWhere('patients.phone_no', 'like', '%' . $q . '%')
                        ->orWhere('patients.file_no', 'like', '%' . $q . '%')
                        ->orWhereRaw("CONCAT(COALESCE(users.surname, ''), ' ', COALESCE(users.firstname, ''), ' ', COALESCE(users.othername, '')) like ?", ['%' . $q . '%']);
                });
            }

            // Order by relevance - exact matches first, then partial matches
            $query->orderByRaw("
                CASE
                    WHEN users.surname = ? THEN 1
                    WHEN users.firstname = ? THEN 2
                    WHEN patients.file_no = ? THEN 3
                    WHEN patients.phone_no = ? THEN 4
                    WHEN users.surname LIKE ? THEN 5
                    WHEN users.firstname LIKE ? THEN 6
                    ELSE 7
                END
            ", [$q, $q, $q, $q, $q . '%', $q . '%'])
            ->orderBy('users.surname', 'asc');

            $postsQuery = $query->limit(100)->get(); // Limit to 100 results for performance

            return Datatables::of($postsQuery)
                ->addIndexColumn()
                ->addColumn('user_id', function ($list) {
                    $fullname = trim(($list->surname ?? '') . " " . ($list->firstname ?? '') . " " . ($list->othername ?? ''));
                    return $fullname;
                })
                ->addColumn('hmo', function ($list) {
                    $hmo_name = Hmo::where('id', $list->hmo_id)->first()->name ?? 'N/A';
                    return $hmo_name;
                })
                ->addColumn('acc_bal', function ($list) {
                    $patient_acc = $list->account ? (json_decode($list->account)->balance ?? '') : '';
                    if ($patient_acc !== '') {
                        if ($patient_acc >= 0) {
                            return "<span class='badge badge-success'>NGN " . number_format($patient_acc, 2) . "</span>";
                        } else {
                            return "<span class='badge badge-danger'>NGN " . number_format($patient_acc, 2) . "</span>";
                        }
                    } else {
                        return "<span class='badge badge-secondary'>No Account</span>";
                    }
                })
                ->addColumn('phone', function ($list) {
                    return $list->phone_no ?? 'N/A';
                })
                ->addColumn('process', function ($list) {
                    $url = route('getMyDependants', $list->user_id);
                    $url2 = route('patient.show', [$list->id]);

                    return '<a class="btn btn-success btn-sm" href="' . $url . '">Add To Queue</a> <br><br><a class="btn btn-primary btn-sm" href="' . $url2 . '"> View Profile</a>';
                })
                ->rawColumns(['user_id', 'process', 'acc_bal'])
                ->make(true);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'An error occurred while searching patients.'], 500);
        }
    }

    public function addToQueue()
    {
        return view('admin.receptionist.add_to_queue');
    }

    public function getMyDependants($user_id)
    {
        try {
            $patient = patient::where('user_id', $user_id)->first();
            $family = patient::with(['user'])->where('file_no', $patient->file_no)->get();
            $products = Product::with(['category', 'price'])->where('status', 1)->get();
            $services = service::with(['category', 'price'])->where('status', 1)->where('price_assign', 1)->where('category_id', appsettings('consultation_category_id'))->get();
            $clinics = Clinic::where('status', 1)->get();
            return view('admin.receptionist.send_queue', compact('family', 'products', 'services', 'clinics'));
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
        try {
            $all_patients = patient::with(['user'])->where('status', 1);
            $hmos = Hmo::with('scheme')->where('status', 1)->get()->groupBy('scheme.name');
            return view('admin.receptionist.new_patient')->with(['all_patients' => $all_patients, 'hmos' => $hmos]);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
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
        try {
            // dd($request->all());
            $rules = [
                'surname'   => 'required|min:3|max:150',
                'firstname' => 'required|min:3|max:150',
                'othername' => 'nullable|min:3|max:150',
                'email'     => 'nullable|email|min:3|max:150|unique:users,email',
            ];

            if ($request->hasFile('filename')) {

                $rules += [
                    'filename' => 'max:10240|mimes:jpeg,bmp,png,gif,svg,jpg',
                ];
            }

            if ($request->hasFile('old_records')) {

                $rules += [
                    'old_records' => 'max:2000000024|mimes:jpeg,png,svg,jpg,pdf,doc,docx',
                ];
            }

            if (!$request->email) {
                $request->email = strtolower(trim($request->firstname)) . '.' . strtolower(trim($request->surname)) . '.' . rand(100000, 9999999) . '@hms.com';
            }

            if (!$request->password) {
                $request->password = '123456';
            }

            // Check if the user already exists based on email, or a combination of surname, firstname, and file number in the patient relationship.
            $existingUser = User::where('email', $request->email)
                ->orWhere(function ($query) use ($request) {
                    $query->where('surname', $request->surname)
                        ->where('firstname', $request->firstname)
                        ->whereHas('patient_profile', function ($subQuery) use ($request) {
                            $subQuery->where('file_no', $request->file_no);
                        });
                })
                ->first();


            if ($existingUser) {
                return redirect()->back()->withMessage('A user with this email or name/file no already exists.')->withMessageType('danger');
            }


            $v = Validator::make($request->all(), $rules);

            if ($v->fails()) {
                return back()->with('errors', $v->messages()->all())->withInput();
            } else {
                if ($request->hasFile('filename')) {
                    $path = storage_path('/app/public/image/user/');
                    $file = $request->file('filename');

                    // format of file is "timestamp-file-name.extension"
                    $name = str_replace(" ", "-", strtolower($file->getClientOriginalName()));
                    $name = str_replace("_", "-", $name);
                    $filename = time() . '-' . $name;
                    // dd($filename);

                    if (Storage::disk('user_images')->exists($filename)) {
                        // delete image before uploading
                        File::delete($path . $filename);

                        Image::make($file)
                            ->resize(215, 215)
                            ->save($path . $filename);
                    } else {
                        Image::make($file)
                            ->resize(215, 215)
                            ->save($path . $filename);
                    }

                    $thumbnail_path = storage_path('/app/public/image/user/thumbnail/');
                    // save thumbnail for user images
                    if (Storage::disk('thumbnail_user_images')->exists($filename)) {
                        // delete image before uploading
                        File::delete($thumbnail_path . $filename);

                        Image::make($file)
                            ->resize(106, 106)
                            ->save($thumbnail_path . $filename);
                    } else {
                        Image::make($file)
                            ->resize(106, 106)
                            ->save($thumbnail_path . $filename);
                    }
                }


                if ($request->hasFile('old_records')) {
                    $path_o = storage_path('/app/public/image/user/old_records/');
                    $file_o = $request->file('old_records');
                    $extension_o = strtolower($file_o->getClientOriginalExtension());

                    // format of file is "timestamp-file-name.extension"
                    $name_o = str_replace(" ", "-", strtolower($file_o->getClientOriginalName()));
                    $name_o = str_replace("_", "-", $name_o);
                    $filename_o = time() . '-' . $name_o;
                    //dd($filename_o);

                    if (Storage::disk('old_records')->exists($filename_o)) {
                        // delete image before uploading
                        Storage::disk('old_records')->delete($filename_o);

                        Storage::disk('old_records')->put($filename_o, $file_o->get());
                    } else {
                        Storage::disk('old_records')->put($filename_o, $file_o->get());
                    }
                }
                Db::beginTransaction();
                $user              = new User;
                // dd($filename);
                if ($request->filename) {
                    $user->filename    = $filename;
                } else {
                    $user->filename    = "avatar.png";
                }

                if ($request->old_records) {
                    $user->old_records    = ($filename_o) ? $filename_o : null;
                } else {
                    $user->old_records    = null;
                }

                $user->is_admin    = 19;
                $user->surname     = $request->surname;
                $user->firstname   = $request->firstname;
                $user->othername   = ($request->othername) ? $request->othername : " ";
                $user->email       = $request->email;
                $user->password    = Hash::make($request->password);

                $user->assignRole      = ($request->assignRole) ? 1 : 0;
                $user->assignPermission      = ($request->assignPermission) ? 1 : 0;

                $user->save();

                if ($request->assignRole) {
                    # code...
                    $user->assignRole($request->roles);
                }

                if ($request->assignPermission) {
                    # code...
                    $user->givePermissionTo($request->permissions);
                }

                // Create Tracked Entity Instance

                //get last word in address and use as city name
                $last_word_start = strrpos($request->address, ' ');
                $last_word = trim(substr($request->address, $last_word_start));

                if (appsettings('goonline', 0) == 1) {

                    $trackedEntityResponse = Http::withBasicAuth(appsettings('dhis_username'), appsettings('dhis_pass'))
                        ->post(appsettings('dhis_api_url') . '/tracker?importStrategy=CREATE&async=false', [
                            "trackedEntities" => [
                                [
                                    "orgUnit" => appsettings('dhis_org_unit'),
                                    "trackedEntityType" => appsettings('dhis_tracked_entity_type'),
                                    "attributes" => [
                                        [
                                            "attribute" => appsettings('dhis_tracked_entity_attr_fname'),
                                            "value" => $request->firstname
                                        ],
                                        [
                                            "attribute" => appsettings('dhis_tracked_entity_attr_lname'),
                                            "value" => $request->surname
                                        ],
                                        [
                                            "attribute" => appsettings('dhis_tracked_entity_attr_gender'),
                                            "value" => $request->gender
                                        ],
                                        [
                                            "attribute" => appsettings('dhis_tracked_entity_attr_dob'),
                                            "value" => date('Y-m-d', strtotime($request->dob))
                                        ],
                                        [
                                            "attribute" => appsettings('dhis_tracked_entity_attr_city'),
                                            "value" => $last_word ?? ''
                                        ]
                                    ]
                                ]
                            ]
                        ]);


                    $trackedEntityInstanceId = $trackedEntityResponse->json()['bundleReport']['typeReportMap']['TRACKED_ENTITY']['objectReports'][0]['uid'];

                    // Get current time in the required format
                    $currentTime = Carbon::now()->format('Y-m-d\TH:i:s.000');
                    // Create Enrollment
                    $enrollmentResponse = Http::withBasicAuth(appsettings('dhis_username'), appsettings('dhis_pass'))
                        ->post(appsettings('dhis_api_url') . '/tracker?importStrategy=CREATE&async=false', [
                            "enrollments" => [
                                [
                                    "attributes" => [
                                        [
                                            "attribute" => appsettings('dhis_tracked_entity_attr_fname'),
                                            "value" => $request->firstname
                                        ],
                                        [
                                            "attribute" => appsettings('dhis_tracked_entity_attr_lname'),
                                            "value" => $request->surname
                                        ],
                                        [
                                            "attribute" => appsettings('dhis_tracked_entity_attr_gender'),
                                            "value" => $request->gender
                                        ],
                                        [
                                            "attribute" => appsettings('dhis_tracked_entity_attr_dob'),
                                            "value" => date('Y-m-d', strtotime($request->dob))
                                        ],
                                        [
                                            "attribute" => appsettings('dhis_tracked_entity_attr_city'),
                                            "value" => $last_word ?? ''
                                        ]
                                    ],
                                    "enrolledAt" => $currentTime,
                                    "occurredAt" => $currentTime,
                                    "orgUnit" => appsettings('dhis_org_unit'),
                                    "program" => appsettings('dhis_tracked_entity_program'),
                                    "status" => "COMPLETED",
                                    "trackedEntityType" => appsettings('dhis_tracked_entity_type'),
                                    "trackedEntity" => $trackedEntityInstanceId
                                ]
                            ]
                        ]);

                    $enrollmentId = $enrollmentResponse->json()['bundleReport']['typeReportMap']['ENROLLMENT']['objectReports'][0]['uid'];
                } else {
                    $trackedEntityInstanceId = '';
                    $enrollmentId = '';
                }

                $patient = new patient;

                $patient->user_id = $user->id;
                $patient->file_no = $request->file_no ?? null;
                $patient->address = $request->address ?? null;
                $patient->insurance_scheme = $request->insurance_scheme ?? null;
                $patient->blood_group = $request->blood_group ?? null;
                $patient->disability = $request->disability ?? null;
                $patient->dob = $request->dob ?? null;
                $patient->ethnicity = $request->ethnicity ?? null;
                $patient->gender = $request->gender ?? null;
                $patient->genotype = $request->genotype ?? null;
                $patient->hmo_id = $request->hmo_id ?? 1; // Default to Private HMO (id=1)
                $patient->hmo_no = $request->hmo_no ?? null;
                $patient->misc = $request->misc ?? null;
                $patient->nationality = $request->nationality ?? null;
                $patient->next_of_kin_name = $request->next_of_kin_name ?? null;
                $patient->next_of_kin_phone = $request->next_of_kin_phone ?? null;
                $patient->next_of_kin_address = $request->next_of_kin_address ?? null;
                $patient->phone_no = $request->phone_no ?? null;
                $patient->dhis_consult_tracker_id = $trackedEntityInstanceId;
                $patient->dhis_consult_enrollment_id = $enrollmentId;

                $patient->save();

                $patient_account = new PatientAccount;
                $patient_account->patient_id = $patient->id;
                $patient_account->save();

                if (appsettings('goonline', 0) == 1) {
                    //send to corehms super admin
                    $response = Http::withBasicAuth(
                        appsettings('COREHMS_SUPERADMIN_USERNAME'),
                        appsettings('COREHMS_SUPERADMIN_PASS')
                    )->withHeaders([
                        'Content-Type' => 'application/json',
                    ])->post(appsettings('COREHMS_SUPERADMIN_URL') . '/event-notification.php?notification_type=patient', [
                        'nothing' => true
                    ]);

                    Log::info("sent api request For Patient, ", [$response->body()]);
                }
                $msg = 'Patient  [' . $user->firstname . ' ' . $user->surname . '] was successfully created.';
                Db::commit();
                return redirect()->back()->withMessage($msg)->withMessageType('success');
                // return redirect()->route('staff.create');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage(), ['exception' => $e]);
            return redirect()->back()->withMessage($e->getMessage())->withMessageType('danger');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\patient  $patient
     * @return \Illuminate\Http\Response
     */
    public function show(Patient $patient)
    {
        try {
            $user = User::find($patient->user_id);
            $roles = Role::pluck('name', 'id')->all();
            $statuses = UserCategory::all();
            $permissions = Permission::pluck('name', 'id')->all();
            $patient_acc = $patient->account;
            $avail_beds = Bed::where('occupant_id', null)->where('status', 1)->get();

            //for nursing notes
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

            $reasons_for_encounter_list = ReasonForEncounter::all();
            $reasons_for_encounter_cat_list = ReasonForEncounter::select('category')->distinct()->get();
            $reasons_for_encounter_sub_cat_list = ReasonForEncounter::select('category', 'sub_category')->distinct()->get();

            return view('admin.patients.show1', compact(
                'user',
                'roles',
                'statuses',
                'permissions',
                'patient',
                'patient_acc',
                'avail_beds',
                'observation_note',
                'treatment_sheet',
                'io_chart',
                'labour_record',
                'others_record',
                'observation_note_template',
                'treatment_sheet_template',
                'io_chart_template',
                'labour_record_template',
                'others_record_template',
                'reasons_for_encounter_list',
                'reasons_for_encounter_cat_list',
                'reasons_for_encounter_sub_cat_list'
            ));
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\patient  $patient
     * @return \Illuminate\Http\Response
     */
    public function edit(Patient $patient)
    {
        try {
            $hmos = Hmo::with('scheme')->where('status', 1)->get()->groupBy('scheme.name');
            return view('admin.patients.edit', compact('patient', 'hmos'));
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\patient  $patient
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Patient $patient)
    {
        try {
            // dd($request->all());
            $rules = [
                'surname'   => 'required|min:3|max:150',
                'firstname' => 'required|min:3|max:150',
                'othername' => 'nullable|min:3|max:150',
                'email'     => 'nullable|email|min:3|max:150|unique:users,email,' . $patient->user_id,
            ];

            if ($request->hasFile('filename')) {

                $rules += [
                    'filename' => 'max:10240|mimes:jpeg,bmp,png,gif,svg,jpg',
                ];
            }

            if ($request->hasFile('old_records')) {

                $rules += [
                    'old_records' => 'max:2000000024|mimes:jpeg,png,svg,jpg,pdf,doc,docx',
                ];
            }

            if (!$request->email) {
                $request->email = strtolower(trim($request->firstname)) . '.' . strtolower(trim($request->surname)) . '.' . rand(100000, 9999999) . '@hms.com';
            }

            if (!$request->password) {
                $request->password = '123456';
            }

            $v = Validator::make($request->all(), $rules);
            if ($v->fails()) {
                return back()->with('errors', $v->messages()->all())->withInput();
            } else {
                DB::beginTransaction();
                $user = User::findOrFail($patient->user_id);

                if ($request->hasFile('filename')) {
                    $path = storage_path('/app/public/image/user/');
                    $file = $request->file('filename');
                    $extension = strtolower($file->getClientOriginalExtension());

                    // format of file is "timestamp-file-name.extension"
                    $name = str_replace("", "-", strtolower($file->getClientOriginalName()));
                    $name = str_replace("_", "-", $name);
                    $filename = time() . '-' . $name;

                    if (Storage::disk('user_images')->exists($user->filename)) {
                        // delete image before uploading
                        File::delete($path . $user->filename);

                        Image::make($file)
                            ->resize(215, 215)
                            ->save($path . $filename);
                    } else {
                        Image::make($file)
                            ->resize(215, 215)
                            ->save($path . $filename);
                    }

                    $thumbnail_path = storage_path('/app/public/image/user/thumbnail/');

                    //save thumbnail for index images
                    if (Storage::disk('thumbnail_user_images')->exists($user->filename)) {
                        // delete image before uploading
                        File::delete($thumbnail_path . $user->filename);

                        Image::make($file)
                            ->resize(106, 106)
                            ->save($thumbnail_path .  $filename);
                    } else {
                        Image::make($file)
                            ->resize(106, 106)
                            ->save($thumbnail_path . $filename);
                    }

                    $user->filename = ($filename) ? $filename : 'avatar.png';
                }

                if ($request->hasFile('old_records')) {
                    $path_o = storage_path('/app/public/image/user/old_records/');
                    $file_o = $request->file('old_records');
                    $extension_o = strtolower($file_o->getClientOriginalExtension());

                    // format of file is "timestamp-file-name.extension"
                    $name_o = str_replace(" ", "-", strtolower($file_o->getClientOriginalName()));
                    $name_o = str_replace("_", "-", $name_o);
                    $filename_o = time() . '-' . $name_o;
                    //dd($filename_o);

                    if (Storage::disk('old_records')->exists($user->old_records)) {
                        // delete image before uploading
                        Storage::disk('old_records')->delete($user->old_records);

                        Storage::disk('old_records')->put($filename_o, $file_o->get());
                    } else {
                        Storage::disk('old_records')->put($filename_o, $file_o->get());
                    }

                    if ($request->old_records) {
                        $user->old_records    = $filename_o ?? null;
                    } else {
                        $user->old_records    = null;
                    }
                }


                $user->is_admin    = 19;
                $user->surname     = $request->surname;
                $user->firstname   = $request->firstname;
                $user->othername   = ($request->othername) ? $request->othername : " ";
                $user->email       = $request->email;
                $user->password    = Hash::make($request->password);

                $user->assignRole      = ($request->assignRole) ? 1 : 0;
                $user->assignPermission      = ($request->assignPermission) ? 1 : 0;

                $user->update();

                if ($request->assignRole) {
                    # code...
                    $user->assignRole($request->roles);
                }

                if ($request->assignPermission) {
                    # code...
                    $user->givePermissionTo($request->permissions);
                }

                $patient->file_no = $request->file_no ?? null;
                $patient->address = $request->address ?? null;
                $patient->insurance_scheme = $request->insurance_scheme ?? null;
                $patient->blood_group = $request->blood_group ?? null;
                $patient->disability = $request->disability ?? null;
                $patient->dob = $request->dob ?? null;
                $patient->ethnicity = $request->ethnicity ?? null;
                $patient->gender = $request->gender ?? null;
                $patient->genotype = $request->genotype ?? null;
                $patient->hmo_id = $request->hmo_id ?? 1; // Default to Private HMO (id=1)
                $patient->hmo_no = $request->hmo_no ?? null;
                $patient->misc = $request->misc ?? null;
                $patient->nationality = $request->nationality ?? null;
                $patient->next_of_kin_name = $request->next_of_kin_name ?? null;
                $patient->next_of_kin_phone = $request->next_of_kin_phone ?? null;
                $patient->next_of_kin_address = $request->next_of_kin_address ?? null;
                $patient->phone_no = $request->phone_no ?? null;

                $patient->update();
                // Send User an email with set password link
                $msg = 'User [' . $user->firstname . ' ' . $user->surname . '] was successfully updated.';
                DB::commit();
                return redirect()->route('patient.index')->withMessage($msg)->withMessageType('success');
                // return redirect()->route('staff.create');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage(), ['exception' => $e]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\patient  $patient
     * @return \Illuminate\Http\Response
     */
    public function destroy(Patient $patient)
    {
        //
    }
}
