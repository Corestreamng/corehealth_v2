<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\Department;
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
use Illuminate\View\ComponentAttributeBag;

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
        $user = User::with(['category' => function ($q) {
            $q->addSelect(['id', 'name']);
        }, 'staff_profile.department', 'roles'])->where('status', '>', 0)->orderBy('id', 'ASC')->where('is_admin', '!=', 19)->get();

        return DataTables::of($user)
            ->addIndexColumn()
            ->addColumn('staff_info', function ($user) {
                $avatar = view('components.user-avatar', [
                    'user' => $user,
                    'width' => '40px',
                    'height' => '40px',
                    'attributes' => new ComponentAttributeBag()
                ])->render();

                $name = e($user->surname . ' ' . $user->firstname);
                if ($user->othername && trim($user->othername) !== '') {
                    $name .= ' ' . e($user->othername);
                }
                $email = $user->email ? '<div style="font-size:0.8rem;color:#6b7280;"><i class="mdi mdi-email-outline"></i> ' . e($user->email) . '</div>' : '';

                return '<div style="display:flex;align-items:center;gap:10px;">' . $avatar . '<div><div style="font-weight:600;">' . $name . '</div>' . $email . '</div></div>';
            })
            ->addColumn('dept_info', function ($user) {
                $dept = $user->staff_profile?->department?->name;
                $job = $user->staff_profile?->job_title;
                if (!$dept && !$job) return '<span class="text-muted">-</span>';
                $html = $dept ? '<div style="font-weight:600;">' . e($dept) . '</div>' : '';
                $html .= $job ? '<div style="font-size:0.8rem;color:#6b7280;">' . e($job) . '</div>' : '';
                return $html;
            })
            ->addColumn('contact', function ($user) {
                $phone = $user->staff_profile?->phone_number;
                $email = $user->email;
                if (!$phone && !$email) return '<span class="text-muted">-</span>';
                $html = $phone ? '<div><i class="mdi mdi-phone"></i> ' . e($phone) . '</div>' : '';
                $html .= $email ? '<div style="font-size:0.8rem;"><a href="mailto:' . e($email) . '" class="text-primary"><i class="mdi mdi-email"></i> ' . e($email) . '</a></div>' : '';
                return $html;
            })
            ->addColumn('category_roles', function ($user) {
                $category = $user->category->name ?? 'N/A';
                $html = '<div><span class="badge badge-secondary" style="font-size:0.75rem;">' . e($category) . '</span></div>';
                if ($user->roles->isNotEmpty()) {
                    $roles = $user->roles->map(function ($role) {
                        return '<span class="badge badge-outline-primary" style="font-size:0.7rem;margin:1px;">' . e($role->name) . '</span>';
                    })->implode(' ');
                    $html .= '<div style="margin-top:3px;">' . $roles . '</div>';
                }
                return $html;
            })
            ->addColumn('status_info', function ($user) {
                $status = $user->staff_profile?->employment_status ?? 'active';
                $statusClass = match($status) {
                    'active' => 'badge-success',
                    'suspended' => 'badge-danger',
                    'resigned', 'terminated' => 'badge-secondary',
                    default => 'badge-secondary'
                };
                $html = '<span class="badge ' . $statusClass . '">' . ucfirst($status) . '</span>';
                if ($user->staff_profile) {
                    if ($user->staff_profile->is_dept_head) {
                        $html .= '<br><span class="badge badge-warning" title="Department Head"><i class="mdi mdi-account-star"></i> Dept Head</span>';
                    }
                    if ($user->staff_profile->is_unit_head) {
                        $html .= '<br><span class="badge badge-info" title="Unit Head"><i class="mdi mdi-shield-account"></i> Unit Head</span>';
                    }
                }
                return $html;
            })
            ->addColumn('actions', function ($user) {
                $viewUrl = route('staff.show', $user->id);
                $editUrl = route('staff.edit', $user->id);
                return '
                    <div class="btn-group" role="group">
                        <a href="' . $viewUrl . '" class="btn btn-outline-primary btn-sm" title="View"><i class="mdi mdi-eye"></i></a>
                        <a href="' . $editUrl . '" class="btn btn-outline-info btn-sm" title="Edit"><i class="mdi mdi-pencil"></i></a>
                    </div>
                ';
            })
            ->rawColumns(['staff_info', 'dept_info', 'contact', 'category_roles', 'status_info', 'actions'])
            ->make(true);
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
        $departments = Department::active()->ordered()->get();

        // dd($userRole);

        return view('admin.staff.edit-my-profile', compact('user', 'statuses', 'roles', 'permissions', 'userRole', 'userPermission', 'specializations', 'clinics', 'departments'));
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

                // Bank Information (user editable)
                $staff->bank_name = $request->bank_name ?? null;
                $staff->bank_account_number = $request->bank_account_number ?? null;
                $staff->bank_account_name = $request->bank_account_name ?? null;

                // Emergency Contact (user editable)
                $staff->emergency_contact_name = $request->emergency_contact_name ?? null;
                $staff->emergency_contact_phone = $request->emergency_contact_phone ?? null;
                $staff->emergency_contact_relationship = $request->emergency_contact_relationship ?? null;

                // Tax & Pension IDs (user editable)
                $staff->tax_id = $request->tax_id ?? null;
                $staff->pension_id = $request->pension_id ?? null;

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
        $departments = Department::active()->ordered()->get();

        return view('admin.staff.create', compact('roles', 'statuses', 'permissions', 'specializations', 'clinics', 'departments'));
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
                $staff->is_unit_head = $request->has('is_unit_head') ? true : false;
                $staff->is_dept_head = $request->has('is_dept_head') ? true : false;

                // HR Fields
                $staff->employee_id = $request->employee_id ?: null;
                $staff->date_hired = $request->date_hired ?: null;
                $staff->employment_type = $request->employment_type ?: 'full_time';
                $staff->employment_status = $request->employment_status ?: 'active';
                $staff->job_title = $request->job_title ?: null;
                $staff->department_id = $request->department_id ?: null;

                // Bank information
                $staff->bank_name = $request->bank_name ?: null;
                $staff->bank_account_number = $request->bank_account_number ?: null;
                $staff->bank_account_name = $request->bank_account_name ?: null;

                // Emergency contact
                $staff->emergency_contact_name = $request->emergency_contact_name ?: null;
                $staff->emergency_contact_phone = $request->emergency_contact_phone ?: null;
                $staff->emergency_contact_relationship = $request->emergency_contact_relationship ?: null;

                // Tax & pension
                $staff->tax_id = $request->tax_id ?: null;
                $staff->pension_id = $request->pension_id ?: null;

                if ($staff->save()) {
                    if (appsettings('goonline', 0) == 1) {
                        // Send to CoreHealth SuperAdmin
                        // For doctors and nurses
                        if ($request->is_admin == 21) {
                            $response = Http::withBasicAuth(
                                appsettings('COREHMS_SUPERADMIN_USERNAME'),
                                appsettings('COREHMS_SUPERADMIN_PASS')
                            )->withHeaders([
                                'Content-Type' => 'application/json',
                            ])->post(appsettings('COREHMS_SUPERADMIN_URL') . '/event-notification.php?notification_type=staff', [
                                'type' => 'Doctors',
                                'specialization' => $request->specialization ?? null,
                                'gender' => $request->gender ?? null,
                            ]);

                            Log::info("sent api request For Staff doc, ", [$response->body()]);
                        } elseif ($request->is_admin == 22) {
                            $response = Http::withBasicAuth(
                                appsettings('COREHMS_SUPERADMIN_USERNAME'),
                                appsettings('COREHMS_SUPERADMIN_PASS')
                            )->withHeaders([
                                'Content-Type' => 'application/json',
                            ])->post(appsettings('COREHMS_SUPERADMIN_URL') . '/event-notification.php?notification_type=staff', [
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
        $departments = Department::active()->ordered()->get();

        return view('admin.staff.show', compact('user', 'roles', 'statuses', 'permissions', 'specializations', 'clinics', 'departments'));
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
        $departments = Department::active()->ordered()->get();

        // dd($userRole);

        return view('admin.staff.edit', compact('user', 'statuses', 'roles', 'permissions', 'userRole', 'userPermission', 'specializations', 'clinics', 'departments'));
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
                    $user->syncRoles($request->roles);
                } else {
                    // Checkbox unchecked — remove all roles
                    $user->syncRoles([]);
                }

                if ($request->assignPermission) {
                    $user->syncPermissions($request->permissions);
                } else {
                    // Checkbox unchecked — remove all direct permissions
                    $user->syncPermissions([]);
                }
                $staff = Staff::where('user_id', $id)->first();
                if (!$staff) {
                    $staff = new Staff();
                    $staff->user_id = $user->id;
                }

                // Original fields
                $staff->clinic_id = $request->clinic ?? $staff->clinic_id;
                $staff->specialization_id = $request->specialization ?? $staff->specialization_id;
                $staff->gender = $request->gender ?? $staff->gender;
                $staff->date_of_birth = $request->dob ?? $staff->date_of_birth;
                $staff->home_address = $request->address ?? $staff->home_address;
                $staff->phone_number = $request->phone_number ?? $staff->phone_number;
                $staff->consultation_fee = $request->consultation_fee ?? $staff->consultation_fee ?? 0;
                $staff->is_unit_head = $request->has('is_unit_head') ? true : false;
                $staff->is_dept_head = $request->has('is_dept_head') ? true : false;

                // HR Fields
                $staff->employee_id = $request->employee_id ?: ($staff->employee_id ?? null);
                $staff->date_hired = $request->date_hired ?: ($staff->date_hired ?? null);
                $staff->employment_type = $request->employment_type ?: ($staff->employment_type ?? 'full_time');
                $staff->employment_status = $request->employment_status ?: ($staff->employment_status ?? 'active');
                $staff->job_title = $request->job_title ?: ($staff->job_title ?? null);
                $staff->department_id = $request->department_id ?: ($staff->department_id ?? null);

                // Bank information
                $staff->bank_name = $request->bank_name ?: ($staff->bank_name ?? null);
                $staff->bank_account_number = $request->bank_account_number ?: ($staff->bank_account_number ?? null);
                $staff->bank_account_name = $request->bank_account_name ?: ($staff->bank_account_name ?? null);

                // Emergency contact
                $staff->emergency_contact_name = $request->emergency_contact_name ?: ($staff->emergency_contact_name ?? null);
                $staff->emergency_contact_phone = $request->emergency_contact_phone ?: ($staff->emergency_contact_phone ?? null);
                $staff->emergency_contact_relationship = $request->emergency_contact_relationship ?: ($staff->emergency_contact_relationship ?? null);

                // Tax & pension
                $staff->tax_id = $request->tax_id ?: ($staff->tax_id ?? null);
                $staff->pension_id = $request->pension_id ?: ($staff->pension_id ?? null);

                if ($staff->save()) {
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
