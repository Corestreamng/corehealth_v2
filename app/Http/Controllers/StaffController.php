<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
// use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;
use App\User;
use App\Status;
use App\Customer;
use App\StatusCategory;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;
use Intervention\Image\Facades\Image;

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
        $this->middleware(['role:Super-Admin|Admin|Users', 'permission:users|user-create|user-list|user-edit|user-delete']);
    }

    public function listUsers()
    {


        // if(Auth::user()->hasRole(['Super-Admin', 'Admin'])){

        $user = User::with(['statuscategory' => function ($q) {
            $q->addSelect(['id', 'name']);
        }])->where('visible', '>=', 0)->orderBy('id', 'ASC')->get();
        // dd($user);
        // }else{

        //     if (Auth::user()->hasPermissionTo('user-list')) {
        //         # code...
        //         $user = User::with(['statuscategory' => function ($q) {
        //             $q->addSelect(['id', 'name']);
        //         }])->where('visible', '=', 1)->where('id', '=', Auth::user()->id)->orderBy('id', 'ASC')->first();
        //     }
        // }

        return Datatables::of($user)
            ->addIndexColumn()
            ->editColumn('is_admin', function ($user) {
                $statuscategory_name = $user->statuscategory->name;
                return $statuscategory_name;
            })
            ->addColumn('filename', function ($user) {

                $url = url('storage/image/user/thumbnail/' . $user->filename);
                return '<img src=' . $url . ' border="0" width="30" class="img-rounded" align="center" />';
            })
            ->addColumn('view', function ($user) {

                if (Auth::user()->hasPermissionTo('user-show') || Auth::user()->hasRole(['Super-Admin', 'Admin'])) {

                    $url =  route('users.show', $user->id);
                    return '<a href="' . $url . '" class="btn btn-success btn-sm" ><i class="fa fa-street-view"></i> View</a>';
                } else {

                    $label = '<span class="label label-warning">Not Allowed</span>';
                    return $label;
                }
            })
            ->addColumn('edit', function ($user) {

                if (Auth::user()->hasPermissionTo('user-edit') || Auth::user()->hasRole(['Super-Admin', 'Admin'])) {

                    $url =  route('users.edit', $user->id);
                    return '<a href="' . $url . '" class="btn btn-info btn-sm" ><i class="fa fa-pencil"></i> Edit</a>';
                } else {

                    $label = '<span class="label label-warning">Not Allow</span>';
                    return $label;
                }
            })
            ->addColumn('delete', function ($user) {

                if (Auth::user()->hasPermissionTo('user-delete') || Auth::user()->hasRole(['Super-Admin', 'Admin'])) {
                    $id = $user->id;
                    return '<button type="button" class="delete-modal btn btn-danger btn-sm" data-toggle="modal" data-id="' . $id . '"><i class="fa fa-trash"></i> Delete</button>';
                } else {
                    $label = '<span class="label label-danger">Not Allow</span>';
                    return $label;
                }
            })
            ->rawColumns(['filename', 'view', 'edit', 'delete'])
            ->make(true);




        // }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        $statuses = StatusCategory::whereVisible(1)->get();
        $options = Status::whereVisible(1)->get();
        $roles = Role::pluck('name', 'name')->all();

        return view('admin.users.index', compact('statuses', 'options', 'roles'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $roles = Role::pluck('name', 'name')->all();
        $statuses = StatusCategory::pluck('name', 'id')->all();
        $options = Status::pluck('name', 'id')->all();
        $permissions = Permission::pluck('name', 'name')->all();
        $clientUsers = Customer::pluck('fullname', 'id')->all();

        return view('admin.users.create', compact('options', 'roles', 'statuses', 'permissions', 'clientUsers'));
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
            'is_admin'  => 'required',
            'designation'  => 'nullable',
            'surname'   => 'required|min:3|max:150',
            'firstname' => 'required|min:3|max:150',
            // 'email'     => 'required|Email|min:6|max:150',
            'phone_number'     => 'required',
            'content'   => 'nullable|min:3',
            'password'  => 'nullable|min:6',
            // 'password'  => 'required|min:6|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
            'visible'   => 'required',
            // 'roles' => 'required',
        ];

        if ($request->hasFile('filename')) {

            $rules += [
                'filename' => 'max:1024|mimes:jpeg,bmp,png,gif,svg,jpg',
            ];
        }

        if ($request->hasFile('old_records')) {

            $rules += [
                'old_records' => 'max:2000000024|mimes:jpeg,png,svg,jpg,pdf,doc,docx',
            ];
        }

        if ($request->assignRole) {
            #  Making sure if password change was selected it's being validated
            $rules += [
                'roles' => 'required',
            ];
        }

        if ($request->assignPermission) {
            #  Making sure if password change was selected it's being validated
            $rules += [
                'permissions' => 'required',
            ];
        }

        if(!$request->email){
            $request->email = strtolower(trim($request->firstname)).'.'.strtolower(trim($request->surname)).'@hms.com';
        }

        if(!$request->password){
            $request->password = '123456';
        }



        $v = Validator::make($request->all(), $rules);

        if ($v->fails()) {
            // return Response::json(array('errors' => $v->getMessageBag()->toArray()));
            // Alert::error('Error Title', 'One or more information is needed.');
            return back()->with('errors', $v->messages()->all()[0])->withInput();
        } else {

            if ($request->hasFile('filename')) {
                $path = storage_path('/app/public/image/user/');
                $file = $request->file('filename');
                $extension = strtolower($file->getClientOriginalExtension());

                // format of file is "timestamp-file-name.extension"
                $name = str_replace(" ", "-", strtolower($file->getClientOriginalName()));
                $name = str_replace("_", "-", $name);
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
                $name_o = str_replace(" ", "-", strtolower($file_o->getClientOriginalName()));
                $name_o = str_replace("_", "-", $name_o);
                $filename_o = time() . '-' . $name_o;
                //dd($filename_o);

                if (Storage::disk('old_records')->exists($filename_o)) {
                    // delete image before uploading
                    Storage::disk('old_records')->delete($filename_o);

                    Storage::disk('old_records')->put($filename_o,$file_o->get());
                } else {
                    Storage::disk('old_records')->put($filename_o,$file_o->get());
                }
            }

            $user              = new User;

            if($request->filename){
                $user->filename    = ($filename) ? $filename : "avatar.png";
            }else{
                $user->filename    = "avatar.png";
            }

            if($request->old_records){
                $user->old_records    = ($filename_o) ? $filename_o : null;
            }else{
                $user->old_records    = null;
            }

            $user->is_admin    = $request->is_admin;
            $user->customer_id = ($request->customer_id) ? $request->customer_id : 0;
            $user->designation = $request->designation ?? null;
            $user->slug        = str_replace(" ", "-", strtolower($request->firstname));
            $user->surname     = $request->surname;
            $user->firstname   = $request->firstname;
            $user->othername   = ($request->othername) ? $request->othername : " ";
            $user->email       = $request->email;
            $user->phone_number= $request->phone_number;
            $user->content     = $request->content;
            $user->password    = Hash::make($request->password);

            if($request->is_admin == 19){
                $user->visible     = 0;
            }else{
                $user->visible     = $request->visible;
            }

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

                // Send User an email with set password link
                $msg = 'User [' . $user->firstname . ' ' . $user->surname . '] was successfully created. An email was sent to [' . $user->email . '] providing a set password link.';
                Alert::success('Success ', $msg);
                // return redirect()->back()->withMessage($msg)->withMessageType('success');
                return redirect()->route('users.create');
            }
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = User::whereId($id)->first();
        // dd($user->getRoleNames());
        // $roles = Role::pluck('name','name')->all();
        $statuses = StatusCategory::whereVisible(1)->get();
        $options = Status::whereVisible(1)->get();
        // $userRole = $user->roles->pluck('name', 'name')->all();

        // return view('admin.users.show', compact('user', 'statuses', 'options', 'roles', 'userRole'));
        return view('admin.users.show', compact('user', 'statuses', 'options'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $user = User::whereId($id)->first();
        $roles = Role::pluck('name', 'name')->all();
        $permissions = Permission::pluck('name', 'name')->all();
        $statuses = StatusCategory::whereVisible(1)->get();
        $options = Status::whereVisible(1)->get();
        $userRole = $user->roles->pluck('name', 'name')->all();
        $userPermission = $user->permissions->pluck('name', 'name')->all();
        $clientUsers = Customer::pluck('fullname', 'id')->all();

        // dd($userRole);

        return view('admin.users.edit', compact('user', 'statuses', 'options', 'roles', 'permissions', 'userRole', 'userPermission', 'clientUsers'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // dd($request->all());
        $rules = [
            'is_admin'  => 'required',
            'designation'  => 'nullable',
            'surname'   => 'required|min:3|max:150',
            'firstname' => 'required|min:3|max:150',
            'email'     => 'required|Email|min:6|max:150',
            'phone_number'     => 'required',
            // 'password'  => 'required|min:6',
            // 'password'  => 'required|min:6|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
            'visible'   => 'required',
            // 'roles' => 'required',
        ];

        if ($request->hasFile('filename')) {
            #  Making sure if password change was selected it's being validated
            $rules += [
                'filename' => 'max:1024|mimes:jpeg,bmp,png,gif,svg,jpg',
            ];
        }

        if ($request->hasFile('old_records')) {

            $rules += [
                'old_records' => 'max:2000000024|mimes:jpeg,png,svg,jpg,pdf,doc,docx',
            ];
        }

        if (!empty($request->password)) {
            $rules += ['password'  => 'required|min:6'];
        }

        $v = Validator::make($request->all(), $rules);
        if ($v->fails()) {
            return back()->with('errors', $v->messages()->all()[0])->withInput();
        } else {

            $user = User::findOrFail($id);

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

                if (Storage::disk('old_records')->exists($filename_o)) {
                    // delete image before uploading
                    Storage::disk('old_records')->delete($filename_o);

                    Storage::disk('old_records')->put($filename_o,$file_o->get());
                } else {
                    Storage::disk('old_records')->put($filename_o,$file_o->get());
                }

                if($request->old_records){
                    $user->old_records    = $filename_o ?? null;
                }else{
                    $user->old_records    = null;
                }

            }

            $user->is_admin         = $request->is_admin;
            $user->customer_id      = ($request->customer_id) ? $request->customer_id : 0;
            $user->designation      = $request->designation;
            $user->slug             = str_replace(" ", "-", strtolower($request->firstname));
            $user->surname          = $request->surname;
            $user->firstname        = $request->firstname;
            $user->othername        = ($request->othername) ? $request->othername : " ";
            $user->email            = $request->email;
            $user->phone_number            = $request->phone_number;
            $user->content          = $request->content;
            //
            if (!empty($request->password)) {
                $user->password     = Hash::make($request->password);
            }

            // else{
            //     // $input = array_except($input,array('password'));
            // }
            $user->visible          = $request->visible;
            $user->assignRole       = ($request->assignRole) ? 1 : 0;
            $user->assignPermission = ($request->assignPermission) ? 1 : 0;

            if ($user->save()) {
                # code...
                if ($user->assignRole) {
                    # code...
                    $user->syncRoles([$request->roles]);
                }

                if ($user->assignPermission) {
                    # code...
                    // dd($request->permissions);
                    $user->syncPermissions([$request->permissions]);
                }

                $msg = 'The Information for [' . $user->firstname . ' ' . $user->surname . '] was successfully updated.';
                Alert::success('Success ', $msg);
                return redirect()->back()->withMessage($msg)->withMessageType('success');
            }


            // DB::table('model_has_roles')->where('model_id', $id)->delete();
            // $user->assignRole($request->roles);
            // dd( $user->assignRole($request->roles));
            // return response()->json($user);

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
            return back()->with('errors', $v->messages()->all()[0])->withInput();
        } else {

            $user = User::findOrFail($id);

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
                        ->save($thumbnail_path .  $filename);
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
                return redirect()->back()->withMessage($msg)->withMessageType('success');
            }

        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
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

        return view('admin.users.profile', compact('user'));
    }
}
