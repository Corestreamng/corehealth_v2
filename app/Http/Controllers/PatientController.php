<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\User;
use App\Models\Hmo;
use App\Models\Clinic;
use App\Models\service;
use App\Models\Product;
use App\Models\UserCategory;
use Illuminate\Http\Request;
use Response;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;
use App\Http\Controllers\Controller;
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
        $pc = Patient::with('user')->orderBy('created_at', 'DESC')->get();
        //dd($pc);
        return Datatables::of($pc)
            ->addIndexColumn()
            ->editColumn('fullname', function ($pc) {
                return (userfullname($pc->user->id));
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
    }

    public function listReturningPatients(Request $request)
    {

        // dd($request->all());
        $rules =
            [
                'q' => 'required',

            ];
        $v = Validator::make($request->all(), $rules);

        if ($v->fails()) {
            return Response::json(array('errors' => $v->getMessageBag()->toArray()));
        } else {
            $q = (!empty($request->q)) ? ($request->q) : ('');
            # code...
            // $postsQuery = DB::select("select * from `patients` left join `users` on `patients`.`user_id` = `users`.`id`
            //     where `patients`.`status` != :visibility and (`users`.`surname` like :q1 or `users`.`firstname` like :q2
            //     or `users`.`othername` like :q3 or `patients`.`file_no` like :q4)", array('visibility' => 1, 'q1' => '%' . $q . '%', 'q2' => '%' . $q . '%', 'q3' => '%' . $q . '%', 'q4' => '%' . $q . '%'));

            $postsQuery = DB::select("select * from `patients` left join `users` on `patients`.`user_id` = `users`.`id`
                where (`users`.`surname` like :q1 or `users`.`firstname` like :q2
                or `users`.`othername` like :q3 or `patients`.`file_no` like :q4)", array('q1' => '%' . $q . '%', 'q2' => '%' . $q . '%', 'q3' => '%' . $q . '%', 'q4' => '%' . $q . '%'));
            $list = $postsQuery;
            return Datatables::of($list)
                ->addIndexColumn()
                ->addColumn('user_id', function ($list) {
                    $fullname = $list->surname . " " . $list->firstname . " " . $list->othername;
                    return $fullname;
                })
                ->addColumn('hmo', function ($list) {
                    $patient_hmo = Patient::where('user_id', $list->user_id)->first()->hmo_id;
                    $hmo_name = Hmo::where('id', $patient_hmo)->first()->name ?? 'N/A';
                    return $hmo_name;
                })
                // ->addColumn('acc_bal', function ($list) {
                //     $patient_acc = PatientAccount::where('user_id', $list->user_id)->first();
                //     if (null != $patient_acc) {
                //         $patient_acc_markup = "<span class= 'badge badge-success'>Deposit: NGN $patient_acc->deposit</span><br><span class= 'badge badge-danger'>Credit: NGN $patient_acc->credit</span>";
                //         return $patient_acc_markup;
                //     } else {
                //         return "<span class= 'badge badge-success'>No Account</span>";
                //     }
                // })
                ->addColumn('phone', function ($list) {
                    $phone_number = User::where('id', $list->user_id)->first()->phone_number ?? 'N/A';
                    return $phone_number;
                })
                ->addColumn(
                    'process',
                    function ($list) {
                        $url = route('getMyDependants',$list->user_id);
                        return '<a class="btn-success btn-sm" href="'.$url.'">Process</a>';
                    }
                )
                ->rawColumns(['user_id', 'process'])
                ->make(true);
        }
    }

    public function addToQueue()
    {
        return view('admin.receptionist.add_to_queue');
    }

    public function getMyDependants($user_id)
    {
        $patient = Patient::where('user_id', $user_id)->first();
        $family = Patient::with(['user'])->where('file_no', $patient->file_no)->get();
        $products = Product::with(['category','price'])->where('status',1)->get();
        $services = service::with(['category','price'])->where('status',1)->get();
        $clinics = Clinic::where('status', 1)->get();
        return view('admin.receptionist.send_queue', compact('family','products','services','clinics'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $all_patients = Patient::with(['user'])->where('status', 1);
        $hmos = Hmo::where('status', 1)->get();
        return view('admin.receptionist.new_patient')->with(['all_patients' => $all_patients, 'hmos' => $hmos]);
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
            $request->email = strtolower(trim($request->firstname)) . '.' . strtolower(trim($request->surname)) . '@hms.com';
        }

        if (!$request->password) {
            $request->password = '123456';
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

            if ($user->save()) {

                if ($request->assignRole) {
                    # code...
                    $user->assignRole($request->roles);
                }

                if ($request->assignPermission) {
                    # code...
                    $user->givePermissionTo($request->permissions);
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
                $patient->hmo_id = $request->hmo_id ?? null;
                $patient->hmo_no = $request->hmo_no ?? null;
                $patient->misc = $request->misc ?? null;
                $patient->nationality = $request->nationality ?? null;

                if ($patient->save()) {
                    // Send User an email with set password link
                    $msg = 'Patient  [' . $user->firstname . ' ' . $user->surname . '] was successfully created.';
                    Alert::success('Success ', $msg);
                    return redirect()->back()->withInput()->withMessage($msg)->withMessageType('success');
                    // return redirect()->route('staff.create');
                } else {
                    $user->delete(); //rollback
                    $msg = 'Something is went wrong. Please try again later.';
                    return redirect()->back()->withInput()->with('error', $msg)->withInput();
                }
            } else {

                $msg = 'Something is went wrong. Please try again later.';
                return redirect()->back()->withInput()->with('error', $msg)->withInput();
            }
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Patient  $patient
     * @return \Illuminate\Http\Response
     */
    public function show(Patient $patient)
    {
        $user = User::find($patient->user_id);
        $roles = Role::pluck('name', 'id')->all();
        $statuses = UserCategory::all();
        $permissions = Permission::pluck('name', 'id')->all();
        return view('admin.patients.show', compact('user', 'roles', 'statuses', 'permissions', 'patient'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Patient  $patient
     * @return \Illuminate\Http\Response
     */
    public function edit(Patient $patient)
    {
        $hmos = Hmo::where('status', 1)->get();
        return view('admin.patients.edit', compact('patient', 'hmos'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Patient  $patient
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Patient $patient)
    {
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
            $request->email = strtolower(trim($request->firstname)) . '.' . strtolower(trim($request->surname)) . '@hms.com';
        }

        if (!$request->password) {
            $request->password = '123456';
        }

        $v = Validator::make($request->all(), $rules);
        if ($v->fails()) {
            return back()->with('errors', $v->messages()->all())->withInput();
        } else {

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

            if ($user->update()) {

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
                $patient->hmo_id = $request->hmo_id ?? null;
                $patient->hmo_no = $request->hmo_no ?? null;
                $patient->misc = $request->misc ?? null;
                $patient->nationality = $request->nationality ?? null;

                if ($patient->update()) {
                    // Send User an email with set password link
                    $msg = 'User [' . $user->firstname . ' ' . $user->surname . '] was successfully updated.';
                    Alert::success('Success ', $msg);
                    return redirect()->route('patient.index')->withMessage($msg)->withMessageType('success');
                    // return redirect()->route('staff.create');
                } else {
                    $msg = 'Something is went wrong. Please try again later.';
                    return redirect()->back()->withInput()->with('error', $msg)->withInput();
                }
            } else {
                $msg = 'Something is went wrong. Please try again later.';
                return redirect()->back()->withInput()->with('error', $msg)->withInput();
            }
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Patient  $patient
     * @return \Illuminate\Http\Response
     */
    public function destroy(Patient $patient)
    {
        //
    }
}
