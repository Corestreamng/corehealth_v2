<?php

namespace App\Http\Controllers;

use App\Jobs\DispatchCoreHmsSyncJob;
use App\Models\Clinic;
use App\Models\Department;
use App\Models\HR\Cadre;
use App\Models\HR\GradeLevel;
use App\Models\HR\StaffSuspension;
use App\Models\HR\StaffTermination;
use App\Models\HR\Unit;
use App\Models\LocumPass;
use App\Models\Specialization;
use App\Models\Staff;
use App\Models\User;
use App\Models\UserCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\ComponentAttributeBag;
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

    public function listStaff(Request $request)
    {
        $query = User::with([
            'category:id,name',
            'staff_profile:id,user_id,department_id,job_title,phone_number,employment_status,employment_type,is_dept_head,is_unit_head,status,suspension_reason,suspension_end_date,suspended_at',
            'staff_profile.department:id,name',
            'roles:id,name',
            'locum_profile',
            'active_locum_pass',
            'latest_locum_pass',
        ])->whereHas('staff_profile')->orderBy('id', 'ASC');

        // Department filter
        if ($request->filled('department_id') && $request->department_id !== 'all') {
            $query->whereHas('staff_profile', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        // Category filter
        if ($request->filled('category_id') && $request->category_id !== 'all') {
            $query->where('is_admin', $request->category_id);
        }

        // Employment type filter
        if ($request->filled('employment_type') && $request->employment_type !== 'all') {
            if ($request->employment_type === 'locum') {
                $query->where(function ($q) {
                    $q->where('is_locum', true)
                      ->orWhereHas('locum_profile')
                      ->orWhereHas('staff_profile', function ($sq) {
                          $sq->where('employment_type', 'locum');
                      });
                });
            } else {
                $query->whereHas('staff_profile', function ($q) use ($request) {
                    $q->where('employment_type', $request->employment_type);
                });
            }
        }

        // Employment status filter
        if ($request->filled('employment_status') && $request->employment_status !== 'all') {
            $status = $request->employment_status;
            if ($status === 'active') {
                $query->where('status', 1)->whereHas('staff_profile', function ($q) {
                    $q->whereIn('employment_status', ['active', 'probation'])
                      ->orWhereNull('employment_status');
                });
            } elseif ($status === 'inactive') {
                $query->where(function ($q) {
                    $q->where('status', 0)->orWhereHas('staff_profile', function ($sq) {
                        $sq->where('status', 0)->orWhereIn('employment_status', ['suspended', 'terminated', 'resigned', 'retired']);
                    });
                });
            } else {
                $query->whereHas('staff_profile', function ($q) use ($status) {
                    $q->where('employment_status', $status);
                });
            }
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->filterColumn('staff_info', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('surname', 'like', "%{$keyword}%")
                      ->orWhere('firstname', 'like', "%{$keyword}%")
                      ->orWhere('othername', 'like', "%{$keyword}%")
                      ->orWhere('email', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('contact', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('email', 'like', "%{$keyword}%")
                      ->orWhereHas('staff_profile', function ($sq) use ($keyword) {
                          $sq->where('phone_number', 'like', "%{$keyword}%");
                      });
                });
            })
            ->addColumn('staff_info', function ($user) {
                $avatar = view('components.user-avatar', [
                    'user' => $user,
                    'width' => '40px',
                    'height' => '40px',
                    'attributes' => new ComponentAttributeBag(),
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
                if (!$dept && !$job) {
                    return '<span class="text-muted">-</span>';
                }
                $html = $dept ? '<div style="font-weight:600;">' . e($dept) . '</div>' : '';
                $html .= $job ? '<div style="font-size:0.8rem;color:#6b7280;">' . e($job) . '</div>' : '';

                return $html;
            })
            ->addColumn('contact', function ($user) {
                $phone = $user->staff_profile?->phone_number;
                $email = $user->email;
                if (!$phone && !$email) {
                    return '<span class="text-muted">-</span>';
                }
                $html = $phone ? '<div><i class="mdi mdi-phone"></i> ' . e($phone) . '</div>' : '';
                $html .= $email ? '<div style="font-size:0.8rem;"><a href="mailto:' . e($email) . '" class="text-primary"><i class="mdi mdi-email"></i> ' . e($email) . '</a></div>' : '';

                return $html;
            })
            ->addColumn('category_roles', function ($user) {
                $category = $user->category->name ?? 'N/A';
                $html = '<div><span class="badge badge-secondary" style="font-size:0.75rem;">' . e($category) . '</span>';

                // Locum practitioner badge and shift pass badge
                if ($user->isLocum()) {
                    $html .= ' <span class="badge badge-info" style="font-size:0.7rem;"><i class="mdi mdi-badge-account-outline"></i> Locum</span>';

                    if ($user->hasActiveLocumPass()) {
                        $html .= ' <span class="badge badge-success" style="font-size:0.68rem;" title="Shift Pass Active: ' . e($user->active_locum_pass?->time_remaining ?? '') . '"><i class="mdi mdi-key-variant"></i> Pass Active</span>';
                    } elseif ($user->latest_locum_pass && $user->latest_locum_pass->isRevoked()) {
                        $html .= ' <span class="badge badge-danger" style="font-size:0.68rem;" title="Shift Pass Revoked"><i class="mdi mdi-cancel"></i> Pass Revoked</span>';
                    } elseif ($user->latest_locum_pass && $user->latest_locum_pass->isExpired()) {
                        $html .= ' <span class="badge badge-secondary" style="font-size:0.68rem;" title="Shift Pass Expired"><i class="mdi mdi-key-remove"></i> Pass Expired</span>';
                    } else {
                        $html .= ' <span class="badge badge-light text-muted border" style="font-size:0.68rem;" title="No Shift Pass"><i class="mdi mdi-key-outline"></i> No Pass</span>';
                    }
                }
                $html .= '</div>';

                if ($user->roles->isNotEmpty()) {
                    $roles = $user->roles->map(function ($role) {
                        return '<span class="badge badge-outline-primary" style="font-size:0.7rem;margin:1px;">' . e($role->name) . '</span>';
                    })->implode(' ');
                    $html .= '<div style="margin-top:3px;">' . $roles . '</div>';
                }

                return $html;
            })
            ->addColumn('status_info', function ($user) {
                $staff = $user->staff_profile;
                $status = $staff?->employment_status ?? 'active';
                $reason = $staff?->suspension_reason ? ' title="' . e($staff->suspension_reason) . '"' : '';

                $badgeClass = match($status) {
                    'active' => 'badge-success',
                    'probation' => 'badge-info',
                    'on_leave' => 'badge-warning text-dark',
                    'suspended' => 'badge-danger',
                    'terminated' => 'badge-dark',
                    'resigned', 'retired' => 'badge-secondary',
                    default => 'badge-secondary'
                };

                $icon = match($status) {
                    'active' => 'mdi-check-circle',
                    'probation' => 'mdi-clock-outline',
                    'on_leave' => 'mdi-airplane',
                    'suspended' => 'mdi-alert-circle',
                    'terminated' => 'mdi-account-remove',
                    'resigned' => 'mdi-logout',
                    'retired' => 'mdi-account-arrow-right',
                    default => 'mdi-information-outline'
                };

                $html = '<span class="badge ' . $badgeClass . '"' . $reason . '><i class="mdi ' . $icon . '"></i> ' . ucfirst(str_replace('_', ' ', $status)) . '</span>';

                if ((int) $user->status === 0) {
                    $html .= '<br><span class="badge badge-outline-danger" style="font-size:0.68rem;margin-top:2px;" title="User account deactivated"><i class="mdi mdi-lock"></i> Deactivated</span>';
                }

                if ($staff) {
                    if ($staff->is_dept_head) {
                        $html .= '<br><span class="badge badge-warning" title="Department Head"><i class="mdi mdi-account-star"></i> Dept Head</span>';
                    }
                    if ($staff->is_unit_head) {
                        $html .= '<br><span class="badge badge-info" title="Unit Head"><i class="mdi mdi-shield-account"></i> Unit Head</span>';
                    }
                }

                return $html;
            })
            ->addColumn('actions', function ($user) {
                $viewUrl = route('staff.show', $user->id);
                $editUrl = route('staff.edit', $user->id);
                $staff = $user->staff_profile;
                $status = $staff?->employment_status ?? 'active';
                $reason = $staff?->suspension_reason ?? '';
                $endDate = $staff?->suspension_end_date ? $staff->suspension_end_date->format('Y-m-d') : '';
                $name = $user->surname . ' ' . $user->firstname . ($user->othername ? ' ' . $user->othername : '');

                return '
                    <div class="btn-group" role="group">
                        <a href="' . $viewUrl . '" class="btn btn-outline-primary btn-sm" title="View"><i class="mdi mdi-eye"></i></a>
                        <a href="' . $editUrl . '" class="btn btn-outline-info btn-sm" title="Edit"><i class="mdi mdi-pencil"></i></a>
                        <button type="button" class="btn btn-outline-warning btn-sm btn-quick-status"
                            data-user-id="' . $user->id . '"
                            data-staff-id="' . ($staff?->id ?? '') . '"
                            data-name="' . e($name) . '"
                            data-status="' . e($status) . '"
                            data-user-status="' . (int) $user->status . '"
                            data-is-locum="' . ($user->isLocum() ? '1' : '0') . '"
                            data-reason="' . e($reason) . '"
                            data-end-date="' . e($endDate) . '"
                            data-toggle="modal"
                            data-bs-toggle="modal"
                            data-target="#quickStatusModal"
                            data-bs-target="#quickStatusModal"
                            title="Quick Status / Deactivate">
                            <i class="mdi mdi-account-settings"></i>
                        </button>
                    </div>
                ';
            })
            ->rawColumns(['staff_info', 'dept_info', 'contact', 'category_roles', 'status_info', 'actions'])
            ->make(true);
    }

    /**
     * AJAX endpoint to quickly update staff employment status, sync user status,
     * record disciplinary/termination history, and handle locum shift passes.
     */
    public function updateEmploymentStatus(Request $request, $id)
    {
        $request->validate([
            'employment_status' => 'required|in:active,probation,on_leave,suspended,resigned,terminated,retired',
            'reason' => 'nullable|string|max:1000',
            'suspension_end_date' => 'nullable|date',
            'effective_date' => 'nullable|date',
        ]);

        $user = User::findOrFail($id);
        $staff = $user->staff_profile;
        if (!$staff) {
            return response()->json(['success' => false, 'message' => 'Staff profile not found.'], 404);
        }

        $newStatus = $request->employment_status;
        $reason = trim((string) $request->input('reason', ''));
        $authId = Auth::id() ?: 1;

        DB::beginTransaction();

        try {
            $staff->employment_status = $newStatus;

            // Non-loginable exit/disciplinary statuses
            if (in_array($newStatus, [Staff::STATUS_SUSPENDED, Staff::STATUS_TERMINATED, Staff::STATUS_RESIGNED, Staff::STATUS_RETIRED])) {
                $staff->status = 0;
                $user->status = 0;

                if ($newStatus === Staff::STATUS_SUSPENDED) {
                    $staff->suspended_at = now();
                    $staff->suspended_by = $authId;
                    $staff->suspension_reason = $reason ?: 'Suspended by administrator';
                    $staff->suspension_end_date = $request->suspension_end_date ?: null;

                    // Create StaffSuspension record if none active
                    if (!$staff->activeSuspension) {
                        StaffSuspension::create([
                            'staff_id' => $staff->id,
                            'type' => StaffSuspension::TYPE_UNPAID,
                            'start_date' => $request->effective_date ?: now(),
                            'end_date' => $request->suspension_end_date ?: null,
                            'reason' => $reason ?: 'Suspended by administrator',
                            'suspension_message' => 'Your account has been suspended: ' . ($reason ?: 'Please contact HR.'),
                            'status' => StaffSuspension::STATUS_ACTIVE,
                            'issued_by' => $authId,
                        ]);
                    }
                } elseif (in_array($newStatus, [Staff::STATUS_TERMINATED, Staff::STATUS_RESIGNED, Staff::STATUS_RETIRED])) {
                    // Lift any existing suspension
                    if ($staff->isSuspended() && $staff->activeSuspension) {
                        $staff->activeSuspension->update(['status' => StaffSuspension::STATUS_LIFTED]);
                    }

                    // Create termination record if not exists
                    if (!$staff->termination) {
                        $terminationType = match($newStatus) {
                            Staff::STATUS_RESIGNED => StaffTermination::TYPE_VOLUNTARY,
                            Staff::STATUS_RETIRED => StaffTermination::TYPE_RETIREMENT,
                            default => StaffTermination::TYPE_INVOLUNTARY,
                        };
                        $reasonCategory = match($newStatus) {
                            Staff::STATUS_RESIGNED => StaffTermination::REASON_RESIGNATION,
                            Staff::STATUS_RETIRED => StaffTermination::REASON_RETIREMENT,
                            default => StaffTermination::REASON_OTHER,
                        };

                        StaffTermination::create([
                            'staff_id' => $staff->id,
                            'type' => $terminationType,
                            'reason_category' => $reasonCategory,
                            'reason_details' => $reason ?: ucfirst($newStatus) . ' recorded by administrator',
                            'notice_date' => now(),
                            'effective_date' => $request->effective_date ?: now(),
                            'last_working_day' => $request->effective_date ?: now(),
                            'processed_by' => $authId,
                        ]);
                    }
                }

                // If locum, revoke all active shift passes immediately
                if ($user->isLocum()) {
                    LocumPass::where('user_id', $user->id)
                        ->whereNull('revoked_at')
                        ->update([
                            'revoked_at' => now(),
                            'revoked_by' => $authId,
                            'revocation_reason' => $reason ?: 'Locum account ' . $newStatus,
                        ]);
                }
            } else {
                // Active, Probation, or On Leave
                $staff->status = 1;
                $user->status = 1;

                // If previously suspended, lift suspension
                if ($staff->isSuspended() || $staff->suspended_at) {
                    $staff->suspended_at = null;
                    $staff->suspended_by = null;
                    $staff->suspension_reason = null;
                    $staff->suspension_end_date = null;

                    if ($staff->activeSuspension) {
                        $staff->activeSuspension->update([
                            'status' => StaffSuspension::STATUS_LIFTED,
                            'lifted_by' => $authId,
                            'lifted_at' => now(),
                            'lift_reason' => $reason ?: 'Reinstated by administrator',
                        ]);
                    }
                }
            }

            $staff->save();
            $user->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Status for ' . userfullname($user->id) . ' updated to ' . ucfirst(str_replace('_', ' ', $newStatus)) . '.',
                'status' => $newStatus,
                'user_status' => (int) $user->status,
                'is_locum' => (bool) $user->isLocum(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update staff employment status: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update employment status: ' . $e->getMessage(),
            ], 500);
        }
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
                $staff->can_see_clinic_queues = array_values(array_unique(array_map('intval', array_filter((array) $request->input('can_see_clinic_queues', [])))));
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
        $roles = Role::pluck('name', 'name')->all();
        $departments = Department::orderBy('name')->pluck('name', 'id')->all();

        return view('admin.staff.index', compact('roles', 'statuses', 'departments'));
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
        $cadres = Cadre::active()->ordered()->get();
        $gradeLevels = GradeLevel::active()->ordered()->get();
        $units = Unit::active()->ordered()->get();

        return view('admin.staff.create', compact('roles', 'statuses', 'permissions', 'specializations', 'clinics', 'departments', 'cadres', 'gradeLevels', 'units'));
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

        if ($request->is_admin == 21) {
            //  Making sure specialization being validated for doctors
            $rules += [
                'specialization' => 'nullable',
            ];
        }
        if ($request->is_admin == 21) {
            // Making sure clinic being validated for doctors
            $rules += [
                'clinic' => 'required',
            ];
        }

        if (!$request->email) {
            $baseEmail = strtolower(trim($request->firstname)) . '.' . strtolower(trim($request->surname)) . '@hms.com';
            $email = $baseEmail;
            $count = 1;
            while (User::where('email', $email)->exists()) {
                $email = strtolower(trim($request->firstname)) . '.' . strtolower(trim($request->surname)) . $count . '@hms.com';
                $count++;
            }
            $request->merge(['email' => $email]);
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

            DB::beginTransaction();

            try {
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

                $user->save();

                if ($request->has('roles') && count($request->roles) > 0) {
                    $user->syncRoles($request->roles);
                    $user->assignRole = 1;
                } else {
                    $user->assignRole = 0;
                }

                if ($request->has('permissions') && count($request->permissions) > 0) {
                    $user->syncPermissions($request->permissions);
                    $user->assignPermission = 1;
                } else {
                    $user->assignPermission = 0;
                }

                $user->update();

                $staff = Staff::firstOrNew(['user_id' => $user->id]);
                $staff->clinic_id = $request->clinic ?? null;
                $staff->can_see_clinic_queues = array_values(array_unique(array_map('intval', array_filter((array) $request->input('can_see_clinic_queues', [])))));
                $staff->user_id = $user->id;
                $staff->specialization_id = $request->specialization ?? null;
                $staff->gender = $request->gender ?? null;
                $staff->date_of_birth = $request->dob ?? null;
                $staff->home_address = $request->address ?? null;
                $staff->phone_number = $request->phone_number ?? null;

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

                // HR Enhancement fields
                $staff->unit_id = $request->unit_id ?: null;
                $staff->cadre_id = $request->cadre_id ?: null;
                $staff->grade_level_id = $request->grade_level_id ?: null;
                $staff->entry_grade_level_id = $request->entry_grade_level_id ?: null;
                $staff->license_number = $request->license_number ?: null;
                $staff->license_expiry_date = $request->license_expiry_date ?: null;
                $staff->national_id_number = $request->national_id_number ?: null;
                $staff->job_location = $request->job_location ?: null;
                $staff->responsibility = $request->responsibility ?: null;
                $staff->marital_status = $request->marital_status ?: null;
                $staff->number_of_children = $request->filled('number_of_children') ? intval($request->number_of_children) : 0;
                $staff->permanent_home_address = $request->permanent_home_address ?: null;
                $staff->other_talents = $request->other_talents ?: null;
                $staff->date_confirmed = $request->date_confirmed ?: null;
                $staff->confirmation_due_date = $request->confirmation_due_date ?: null;
                $staff->hr_notes = $request->hr_notes ?: null;

                $staff->save();

                DB::commit();

                if (appsettings('goonline', 0) == 1) {
                    $staffName = trim($user->surname . ' ' . $user->firstname . ' ' . ($user->othername ?? ''));
                    $staffType = ($request->is_admin == 21) ? 'Doctors' : (($request->is_admin == 22) ? 'Nurse' : 'Staffs');

                    DispatchCoreHmsSyncJob::dispatch('staff', [
                        'type' => $staffType,
                        'name' => $staffName,
                        'specialization' => $request->specialization ?? null,
                        'gender' => $request->gender ?? null,
                        'email' => $user->email ?? null,
                    ], $user->id, now()->toDateTimeString());
                }

                $msg = 'User [' . trim($user->surname . ' ' . $user->firstname . ' ' . ($user->othername ?? '')) . '] was successfully created.';
                Alert::success('Success ', $msg);

                return redirect()->route('staff.index')->withMessage($msg)->withMessageType('success');
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Failed to create staff: " . $e->getMessage(), ['exception' => $e]);
                $msg = 'Something went wrong: ' . $e->getMessage();

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
        $cadres = Cadre::active()->ordered()->get();
        $gradeLevels = GradeLevel::active()->ordered()->get();
        $units = Unit::active()->ordered()->get();

        return view('admin.staff.edit', compact('user', 'statuses', 'roles', 'permissions', 'userRole', 'userPermission', 'specializations', 'clinics', 'departments', 'cadres', 'gradeLevels', 'units'));
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
            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }
            DB::beginTransaction();

            try {
                if ($request->has('roles') && count($request->roles) > 0) {
                    $user->syncRoles($request->roles);
                    $user->assignRole = 1;
                } else {
                    // Checkbox unchecked — remove all roles
                    $user->syncRoles([]);
                    $user->assignRole = 0;
                }

                if ($request->has('permissions') && count($request->permissions) > 0) {
                    $user->syncPermissions($request->permissions);
                    $user->assignPermission = 1;
                } else {
                    // Checkbox unchecked — remove all direct permissions
                    $user->syncPermissions([]);
                    $user->assignPermission = 0;
                }

                $user->update();
                $staff = Staff::where('user_id', $id)->first();
                if (!$staff) {
                    $staff = new Staff();
                    $staff->user_id = $user->id;
                }

                // Original fields
                $staff->clinic_id = $request->clinic ?? $staff->clinic_id;
                $staff->can_see_clinic_queues = $request->has('can_see_clinic_queues')
                    ? array_values(array_unique(array_map('intval', array_filter((array) $request->input('can_see_clinic_queues', [])))))
                    : ($staff->can_see_clinic_queues ?? []);
                $staff->specialization_id = $request->specialization ?? $staff->specialization_id;
                $staff->gender = $request->gender ?? $staff->gender;
                $staff->date_of_birth = $request->dob ?? $staff->date_of_birth;
                $staff->home_address = $request->address ?? $staff->home_address;
                $staff->phone_number = $request->phone_number ?? $staff->phone_number;

                $staff->is_unit_head = $request->has('is_unit_head') ? true : false;
                $staff->is_dept_head = $request->has('is_dept_head') ? true : false;

                // HR Fields
                $staff->employee_id = $request->employee_id ?: ($staff->employee_id ?? null);
                $staff->employment_type = $request->employment_type ?: ($staff->employment_type ?? 'full_time');
                $staff->employment_status = $request->employment_status ?: ($staff->employment_status ?? 'active');

                // Suspension fields & locum governance
                if ($staff->employment_status === Staff::STATUS_SUSPENDED) {
                    $staff->status = 0;
                    $staff->suspended_at = $staff->suspended_at ?: now();
                    $staff->suspended_by = Auth::id() ?: 1;
                    if ($request->filled('suspension_reason')) {
                        $staff->suspension_reason = $request->suspension_reason;
                    }
                    if ($request->filled('suspension_end_date')) {
                        $staff->suspension_end_date = $request->suspension_end_date;
                    }
                    if ($user->isLocum()) {
                        LocumPass::where('user_id', $user->id)
                            ->whereNull('revoked_at')
                            ->update([
                                'revoked_at' => now(),
                                'revoked_by' => Auth::id() ?: 1,
                                'revocation_reason' => 'Staff suspended by administrator',
                            ]);
                    }
                } elseif (in_array($staff->employment_status, [Staff::STATUS_TERMINATED, Staff::STATUS_RESIGNED, Staff::STATUS_RETIRED])) {
                    $staff->status = 0;
                    if ($user->isLocum()) {
                        LocumPass::where('user_id', $user->id)
                            ->whereNull('revoked_at')
                            ->update([
                                'revoked_at' => now(),
                                'revoked_by' => Auth::id() ?: 1,
                                'revocation_reason' => 'Employment ended (' . $staff->employment_status . ')',
                            ]);
                    }
                } elseif (in_array($staff->employment_status, [Staff::STATUS_ACTIVE, Staff::STATUS_PROBATION, Staff::STATUS_ON_LEAVE])) {
                    $staff->status = 1;
                    $staff->suspension_reason = null;
                    $staff->suspension_end_date = null;
                }

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

                // HR Enhancement fields
                $staff->unit_id = $request->unit_id ?: ($staff->unit_id ?? null);
                $staff->cadre_id = $request->cadre_id ?: ($staff->cadre_id ?? null);
                $staff->grade_level_id = $request->grade_level_id ?: ($staff->grade_level_id ?? null);
                $staff->entry_grade_level_id = $request->entry_grade_level_id ?: ($staff->entry_grade_level_id ?? null);
                $staff->license_number = $request->license_number ?: ($staff->license_number ?? null);
                $staff->license_expiry_date = $request->license_expiry_date ?: ($staff->license_expiry_date ?? null);
                $staff->national_id_number = $request->national_id_number ?: ($staff->national_id_number ?? null);
                $staff->job_location = $request->job_location ?: ($staff->job_location ?? null);
                $staff->responsibility = $request->responsibility ?: ($staff->responsibility ?? null);
                $staff->marital_status = $request->marital_status ?: ($staff->marital_status ?? null);
                $staff->number_of_children = $request->has('number_of_children') && $request->input('number_of_children') !== null && $request->input('number_of_children') !== '' ? intval($request->number_of_children) : ($staff->number_of_children ?? 0);
                $staff->permanent_home_address = $request->permanent_home_address ?: ($staff->permanent_home_address ?? null);
                $staff->other_talents = $request->other_talents ?: ($staff->other_talents ?? null);
                $staff->date_confirmed = $request->date_confirmed ?: ($staff->date_confirmed ?? null);
                $staff->confirmation_due_date = $request->confirmation_due_date ?: ($staff->confirmation_due_date ?? null);
                $staff->hr_notes = $request->hr_notes ?: ($staff->hr_notes ?? null);

                $staff->save();

                DB::commit();

                $msg = 'User [' . $user->firstname . ' ' . $user->surname . '] was successfully updated.';
                Alert::success('Success ', $msg);

                return redirect()->route('staff.index')->withMessage($msg)->withMessageType('success');
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Failed to update staff: " . $e->getMessage(), ['exception' => $e]);
                $msg = 'Something went wrong: ' . $e->getMessage();

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
