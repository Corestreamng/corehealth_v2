<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\Specialization;
use App\Models\Staff;
use App\Models\User;
// use Illuminate\Support\Facades\DB;
use App\Models\UserCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Facades\Image;
use RealRashid\SweetAlert\Facades\Alert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\DataTables;

class StaffController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct()
    {
        // $this->middleware(['role:super-admin', 'permission:publish articles|edit articles']);
        // $this->middleware(['Role:Super-Admin|Admin|Users', 'permission:users|user-create|user-list|user-edit|user-delete']);
    }

    public function listStaff()
    {
        // if(Auth::user()->hasRole(['Super-Admin', 'Admin'])){

        $user = User::with(['category' => function ($q) {
            $q->addSelect(['id', 'name']);
        }])->where('status', '>', 0)->orderBy('id', 'ASC')->where('is_admin', '!=', 19)->get();
        // dd($user);
        // }else{

        //     if (Auth::user()->hasPermissionTo('user-list')) {
        //         # code...
        //         $user = User::with(['statuscategory' => function ($q) {
        //             $q->addSelect(['id', 'name']);
        //         }])->where('visible', '=', 1)->where('id', '=', Auth::user()->id)->orderBy('id', 'ASC')->first();
        //     }
        // }

        return DataTables::of($user)
            ->addIndexColumn()
            ->editColumn('is_admin', function ($user) {
                $user_category_name = $user->category->name;

                return $user_category_name;
            })
            ->addColumn('filename', function ($user) {
                $url = url('storage/image/user/thumbnail/' . $user->filename);

                return '<img src=' . $url . ' border="0" width="30" class="img-rounded" align="center" />';
            })
            ->addColumn('view', function ($user) {
                // if (Auth::user()->hasPermissionTo('user-show') || Auth::user()->hasRole(['Super-Admin', 'Admin'])) {

                $url = route('staff.show', $user->id);

                return '<a href="' . $url . '" class="btn btn-success btn-sm" ><i class="fa fa-street-view"></i> View</a>';
                // } else {

                //     $label = '<span class="label label-warning">Not Allowed</span>';
                //     return $label;
                // }
            })
            ->addColumn('edit', function ($user) {
                // if (Auth::user()->hasPermissionTo('user-edit') || Auth::user()->hasRole(['Super-Admin', 'Admin'])) {

                $url = route('staff.edit', $user->id);

                return '<a href="' . $url . '" class="btn btn-info btn-sm" ><i class="fa fa-pencil"></i> Edit</a>';
                // } else {

                //     $label = '<span class="label label-warning">Not Allow</span>';
                //     return $label;
                // }
            })
            ->addColumn('delete', function ($user) {
                // if (Auth::user()->hasPermissionTo('user-delete') || Auth::user()->hasRole(['Super-Admin', 'Admin'])) {
                $id = $user->id;

                return '<button type="button" class="delete-modal btn btn-danger btn-sm" data-toggle="modal" data-id="' . $id . '"><i class="fa fa-trash"></i> Delete</button>';
                // } else {
                //     $label = '<span class="label label-danger">Not Allow</span>';
                //     return $label;
                // }
            })
            ->rawColumns(['filename', 'view', 'edit', 'delete'])
            ->make(true);

        // }
    }

    public function my_profile()
    {
        $user = User::whereId(Auth::id())->first();
        $roles = Role::pluck('name', 'name')->all();
        $permissions = Permission::pluck('name', 'name')->all();
        $statuses = UserCategory::whereStatus(1)->get();
        $userRole = $user->roles->pluck('name', 'name')->all();
        $specializations = Specialization::pluck('name', 'id')->all();
        $userPermission = $user->permissions->pluck('name', 'name')->all();
        $clinics = Clinic::pluck('name', 'id')->all();

        // dd($userRole);

        return view('admin.staff.edit-my-profile', compact('user', 'statuses', 'roles', 'permissions', 'userRole', 'userPermission', 'specializations', 'clinics'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update_my_profile(Request $request)
    {
        $id = Auth::id();
        if ($id != Auth::id()) {
            abort(403, 'You Do Not Have Access to This Profile');
        }
        $rules = [
            // 'is_admin'  => 'required',
            'designation' => 'nullable',
            'surname' => 'required|min:3|max:150',
            'firstname' => 'required|min:3|max:150',
            'phone_number' => 'required',
            'gender' => 'required',
        ];

        if ($request->hasFile('filename')) {
            //  Making sure if password change was selected it's being validated
            $rules += [
                'filename' => 'max:10240|mimes:jpeg,bmp,png,gif,svg,jpg',
            ];
        }

        if ($request->hasFile('old_records')) {
            $rules += [
                'old_records' => 'max:2000000024|mimes:jpeg,png,svg,jpg,pdf,doc,docx',
            ];
        }

        if (!empty($request->password)) {
            $rules += ['password' => 'required|confirmed|min:6'];
        }

        $v = Validator::make($request->all(), $rules);
        if ($v->fails()) {
            return back()->with('errors', $v->messages()->all())->withInput();
        } else {
            $user = User::findOrFail($id);

            if ($request->hasFile('filename')) {
                $path = storage_path('/app/public/image/user/');
                $file = $request->file('filename');
                $extension = strtolower($file->getClientOriginalExtension());

                // format of file is "timestamp-file-name.extension"
                $name = str_replace('', '-', strtolower($file->getClientOriginalName()));
                $name = str_replace('_', '-', $name);
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

                // save thumbnail for index images
                if (Storage::disk('thumbnail_user_images')->exists($user->filename)) {
                    // delete image before uploading
                    File::delete($thumbnail_path . $user->filename);

                    Image::make($file)
                        ->resize(106, 106)
                        ->save($thumbnail_path . $filename);
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
                $name_o = str_replace(' ', '-', strtolower($file_o->getClientOriginalName()));
                $name_o = str_replace('_', '-', $name_o);
                $filename_o = time() . '-' . $name_o;
                // dd($filename_o);

                if (Storage::disk('old_records')->exists($user->old_records)) {
                    // delete image before uploading
                    Storage::disk('old_records')->delete($user->old_records);

                    Storage::disk('old_records')->put($filename_o, $file_o->get());
                } else {
                    Storage::disk('old_records')->put($filename_o, $file_o->get());
                }

                if ($request->old_records) {
                    $user->old_records = $filename_o ?? null;
                } else {
                    $user->old_records = null;
                }
            }

            $user->surname = $request->surname;
            $user->firstname = $request->firstname;
            $user->othername = ($request->othername) ? $request->othername : ' ';
            if ($request->password) {
                $user->password = Hash::make($request->password);
            }

            if ($user->update()) {
                $staff = Staff::where('user_id', $id)->first();
                // dd($staff);
                $staff->clinic_id = $request->clinic ?? null;
                $staff->user_id = $user->id;
                $staff->specialization_id = $request->specialization ?? null;
                $staff->gender = $request->gender ?? null;
                $staff->date_of_birth = $request->dob ?? null;
                $staff->home_address = $request->address ?? null;
                $staff->phone_number = $request->phone_number ?? null;
                $staff->consultation_fee = $request->consultation_fee ?? 0;

                if ($staff->update()) {
                    // Send User an email with set password link
                    $msg = 'Your profile was successfully updated.';
                    Alert::success('Success ', $msg);

                    return back()->withMessage($msg)->withMessageType('success');
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
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $statuses = UserCategory::whereStatus(1)->get();
        // $options = Status::whereVisible(1)->get();
        $roles = Role::pluck('name', 'name')->all();

        return view('admin.staff.index', compact('roles', 'statuses'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $roles = Role::pluck('name', 'id')->all();
        $statuses = UserCategory::pluck('name', 'id')->all();
        $specializations = Specialization::pluck('name', 'id')->all();
        $clinics = Clinic::pluck('name', 'id')->all();
        $permissions = Permission::pluck('name', 'id')->all();

        return view('admin.staff.create', compact('roles', 'statuses', 'permissions', 'specializations', 'clinics'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // dd($request->all());
        $rules = [
            'is_admin' => 'required',
            'surname' => 'required|min:3|max:150',
            'firstname' => 'required|min:3|max:150',
            // 'email'     => 'required|Email|min:6|max:150',
            'gender' => 'required',
            'phone_number' => 'required',
            'password' => 'nullable|min:6',
            // 'password'  => 'required|min:6|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
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

        if ($request->assignRole) {
            //  Making sure if password change was selected it's being validated
            $rules += [
                'roles' => 'required',
            ];
        }

        if ($request->is_admin == 21) {
            //  Making sure specialization being validated for doctors
            $rules += [
                'specialization' => 'required',
            ];
        }
        if ($request->is_admin == 21) {
            // Making sure clinic being validated for doctors
            $rules += [
                'clinic' => 'required',
            ];
        }
        if ($request->is_admin == 21) {
            // Making sure consultation_fee being validated for doctors
            $rules += [
                'consultation_fee' => 'required',
            ];
        }

        if ($request->assignPermission) {
            // Making sure permissions being validated
            $rules += [
                'permissions' => 'required',
            ];
        }

        if (!$request->email) {
            $request->email = strtolower(trim($request->firstname)) . '.' . strtolower(trim($request->surname)) . '@hms.com';
        } else {
            $rules += [
                'email' => 'email|min:6|max:150|unique:users,email',
            ];
        }

        if (!$request->password) {
            $request->password = '123456';
        }

        $v = Validator::make($request->all(), $rules);

        if ($v->fails()) {
            // return Response::json(array('errors' => $v->getMessageBag()->toArray()));
            // Alert::error('Error Title', 'One or more information is needed.');
            return back()->with('errors', $v->messages()->all())->withInput();
        } else {
            if ($request->hasFile('filename')) {
                $path = storage_path('/app/public/image/user/');
                $file = $request->file('filename');
                $extension = strtolower($file->getClientOriginalExtension());

                // format of file is "timestamp-file-name.extension"
                $name = str_replace(' ', '-', strtolower($file->getClientOriginalName()));
                $name = str_replace('_', '-', $name);
                $filename = time() . '-' . $name;

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
                $name_o = str_replace(' ', '-', strtolower($file_o->getClientOriginalName()));
                $name_o = str_replace('_', '-', $name_o);
                $filename_o = time() . '-' . $name_o;
                // dd($filename_o);

                if (Storage::disk('old_records')->exists($filename_o)) {
                    // delete image before uploading
                    Storage::disk('old_records')->delete($filename_o);

                    Storage::disk('old_records')->put($filename_o, $file_o->get());
                } else {
                    Storage::disk('old_records')->put($filename_o, $file_o->get());
                }
            }

            $user = new User();

            if ($request->filename) {
                $user->filename = ($filename) ? $filename : 'avatar.png';
            } else {
                $user->filename = 'avatar.png';
            }

            if ($request->old_records) {
                $user->old_records = ($filename_o) ? $filename_o : null;
            } else {
                $user->old_records = null;
            }

            $user->is_admin = $request->is_admin;
            $user->surname = $request->surname;
            $user->firstname = $request->firstname;
            $user->othername = ($request->othername) ? $request->othername : ' ';
            $user->email = $request->email;
            $user->password = Hash::make($request->password);

            $user->assignRole = ($request->assignRole) ? 1 : 0;
            $user->assignPermission = ($request->assignPermission) ? 1 : 0;

            if ($user->save()) {
                if ($request->assignRole) {
                    // code...
                    $user->assignRole($request->roles);
                }

                if ($request->assignPermission) {
                    // code...
                    $user->givePermissionTo($request->permissions);
                }
                $staff = new Staff();
                $staff->clinic_id = $request->clinic ?? null;
                $staff->user_id = $user->id;
                $staff->specialization_id = $request->specialization ?? null;
                $staff->gender = $request->gender ?? null;
                $staff->date_of_birth = $request->dob ?? null;
                $staff->home_address = $request->address ?? null;
                $staff->phone_number = $request->phone_number ?? null;
                $staff->consultation_fee = $request->consultation_fee ?? 0;

                if ($staff->save()) {
                    if (env('GOONLINE') == 1) {
                        // Send to CoreHealth SuperAdmin
                        // For doctors and nurses
                        if ($request->is_admin == 21) {
                            $response = Http::withBasicAuth(
                                env('COREHMS_SUPERADMIN_USERNAME'),
                                env('COREHMS_SUPERADMIN_PASS')
                            )->withHeaders([
                                'Content-Type' => 'application/json',
                            ])->post(env('COREHMS_SUPERADMIN_URL') . '/event-notification.php?notification_type=staff', [
                                'type' => 'Doctors',
                                'specialization' => $request->specialization ?? null,
                                'gender' => $request->gender ?? null,
                            ]);

                            Log::info("sent api request For Staff doc, ", [$response->body()]);
                        } elseif ($request->is_admin == 22) {
                            $response = Http::withBasicAuth(
                                env('COREHMS_SUPERADMIN_USERNAME'),
                                env('COREHMS_SUPERADMIN_PASS')
                            )->withHeaders([
                                'Content-Type' => 'application/json',
                            ])->post(env('COREHMS_SUPERADMIN_URL') . '/event-notification.php?notification_type=staff', [
                                'type' => 'Nurse',
                                'specialization' => $request->specialization ?? null,
                                'gender' => $request->gender ?? null,
                            ]);

                            Log::info("sent api request For staff nurse, ", [$response->body()]);
                        }
                    }


                    $msg = 'User [' . $user->firstname . ' ' . $user->surname . '] was successfully created.';
                    Alert::success('Success ', $msg);

                    return redirect()->back()->withInput()->withMessage($msg)->withMessageType('success');
                } else {
                    $user->delete(); // rollback
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
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = User::whereId($id)->first();
        $roles = Role::pluck('name', 'id')->all();
        $statuses = UserCategory::all();
        $specializations = Specialization::pluck('name', 'id')->all();
        $clinics = Clinic::pluck('name', 'id')->all();
        $permissions = Permission::pluck('name', 'id')->all();

        return view('admin.staff.show', compact('user', 'roles', 'statuses', 'permissions', 'specializations', 'clinics'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $user = User::whereId($id)->first();
        $roles = Role::pluck('name', 'name')->all();
        $permissions = Permission::pluck('name', 'name')->all();
        $statuses = UserCategory::whereStatus(1)->get();
        $userRole = $user->roles->pluck('name', 'name')->all();
        $specializations = Specialization::pluck('name', 'id')->all();
        $userPermission = $user->permissions->pluck('name', 'name')->all();
        $clinics = Clinic::pluck('name', 'id')->all();

        // dd($userRole);

        return view('admin.staff.edit', compact('user', 'statuses', 'roles', 'permissions', 'userRole', 'userPermission', 'specializations', 'clinics'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // dd($request->all());
        $rules = [
            'is_admin' => 'required',
            'designation' => 'nullable',
            'surname' => 'required|min:3|max:150',
            'firstname' => 'required|min:3|max:150',
            'email' => "required|Email|min:6|max:150|unique:users,email,$id",
            'phone_number' => 'required',
            // 'password'  => 'required|min:6',
            // 'password'  => 'required|min:6|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
            // 'visible'   => 'required'/,
            'gender' => 'required',
        ];

        if ($request->hasFile('filename')) {
            //  Making sure if password change was selected it's being validated
            $rules += [
                'filename' => 'max:10240|mimes:jpeg,bmp,png,gif,svg,jpg',
            ];
        }

        if ($request->hasFile('old_records')) {
            $rules += [
                'old_records' => 'max:2000000024|mimes:jpeg,png,svg,jpg,pdf,doc,docx',
            ];
        }

        if (!empty($request->password)) {
            $rules += ['password' => 'required|min:6'];
        }

        $v = Validator::make($request->all(), $rules);
        if ($v->fails()) {
            return back()->with('errors', $v->messages()->all())->withInput();
        } else {
            $user = User::findOrFail($id);

            if ($request->hasFile('filename')) {
                $path = storage_path('/app/public/image/user/');
                $file = $request->file('filename');
                $extension = strtolower($file->getClientOriginalExtension());

                // format of file is "timestamp-file-name.extension"
                $name = str_replace('', '-', strtolower($file->getClientOriginalName()));
                $name = str_replace('_', '-', $name);
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

                // save thumbnail for index images
                if (Storage::disk('thumbnail_user_images')->exists($user->filename)) {
                    // delete image before uploading
                    File::delete($thumbnail_path . $user->filename);

                    Image::make($file)
                        ->resize(106, 106)
                        ->save($thumbnail_path . $filename);
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
                $name_o = str_replace(' ', '-', strtolower($file_o->getClientOriginalName()));
                $name_o = str_replace('_', '-', $name_o);
                $filename_o = time() . '-' . $name_o;
                // dd($filename_o);

                if (Storage::disk('old_records')->exists($user->old_records)) {
                    // delete image before uploading
                    Storage::disk('old_records')->delete($user->old_records);

                    Storage::disk('old_records')->put($filename_o, $file_o->get());
                } else {
                    Storage::disk('old_records')->put($filename_o, $file_o->get());
                }

                if ($request->old_records) {
                    $user->old_records = $filename_o ?? null;
                } else {
                    $user->old_records = null;
                }
            }

            // if ($request->filename) {
            //     $user->filename    = ($filename) ? $filename : "avatar.png";
            // } else {
            //     $user->filename    = "avatar.png";
            // }

            // if ($request->old_records) {
            //     $user->old_records    = ($filename_o) ? $filename_o : null;
            // } else {
            //     $user->old_records    = null;
            // }

            $user->is_admin = $request->is_admin;
            $user->surname = $request->surname;
            $user->firstname = $request->firstname;
            $user->othername = ($request->othername) ? $request->othername : ' ';
            $user->email = $request->email;
            $user->password = Hash::make($request->password);

            $user->assignRole = ($request->assignRole) ? 1 : 0;
            $user->assignPermission = ($request->assignPermission) ? 1 : 0;

            if ($user->update()) {
                if ($request->assignRole) {
                    // code...
                    $user->assignRole($request->roles);
                }

                if ($request->assignPermission) {
                    // code...
                    $user->givePermissionTo($request->permissions);
                }
                $staff = Staff::where('user_id', $id)->first();
                // dd($staff);
                $staff->clinic_id = $request->clinic ?? null;
                $staff->user_id = $user->id;
                $staff->specialization_id = $request->specialization ?? null;
                $staff->gender = $request->gender ?? null;
                $staff->date_of_birth = $request->dob ?? null;
                $staff->home_address = $request->address ?? null;
                $staff->phone_number = $request->phone_number ?? null;
                $staff->consultation_fee = $request->consultation_fee ?? 0;

                if ($staff->update()) {
                    // Send User an email with set password link
                    $msg = 'User [' . $user->firstname . ' ' . $user->surname . '] was successfully updated.';
                    Alert::success('Success ', $msg);

                    return redirect()->route('staff.index')->withMessage($msg)->withMessageType('success');
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

    public function updateAvatar(Request $request, $id)
    {
        // dd($request->all());
        $rules = [];

        if ($request->hasFile('filename')) {
            $rules += [
                'filename' => 'max:1024|mimes:jpeg,bmp,png,gif,svg,jpg',
            ];
        }

        $v = Validator::make($request->all(), $rules);
        if ($v->fails()) {
            return back()->with('errors', $v->messages()->all())->withInput();
        } else {
            $user = User::findOrFail($id);

            if ($request->hasFile('filename')) {
                $path = storage_path('/app/public/image/user/');
                $file = $request->file('filename');
                $extension = strtolower($file->getClientOriginalExtension());

                // format of file is "timestamp-file-name.extension"
                $name = str_replace('', '-', strtolower($file->getClientOriginalName()));
                $name = str_replace('_', '-', $name);
                $filename = time() . '-' . $name;

                if (Storage::disk('user_images')->exists($user->filename)) {
                    // delete image before uploading
                    File::delete($path . $user->filename);

                    Image::make($file)
                        ->resize(215, 215)
                        ->save($filename);
                }

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
                if (Storage::disk('thumbnail_user_images')->exists($user->filename)) {
                    // delete image before uploading
                    File::delete($thumbnail_path . $user->filename);

                    Image::make($file)
                        ->resize(106, 106)
                        ->save($path . $filename);
                }

                // save thumbnail for index images
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

                $user->filename = ($filename) ? $filename : 'avatar.png';
            }

            if ($user->save()) {
                $msg = 'The Avatar for [' . $user->firstname . ' ' . $user->surname . '] was successfully updated.';
                Alert::success('Success ', $msg);

                return redirect()->back()->withInput()->withMessage($msg)->withMessageType('success');
            }
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json($user);
    }

    public function profile($email)
    {
        // $user = User::whereEmail($email)->first();

        $user = User::with(['statuscategory'])->whereEmail($email)->where('visible', '=', 2)->first();

        return view('admin.staff.profile', compact('user'));
    }
}
