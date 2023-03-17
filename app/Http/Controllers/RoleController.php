<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Response;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct()
    {
        // $this->middleware(['role:super-admin', 'permission:publish articles|edit articles']);
        // $this->middleware(['role:Super-Admin|Admin']);
    }

    public function listRoles()
    {

        $role = Role::orderBy('name', 'ASC')->get();

        return Datatables::of($role)
            ->addIndexColumn()
            ->addColumn('edit',   '<a href="{{ route(\'roles.edit\', $id)}}" class="btn btn-info btn-sm" ><i class="fa fa-pencil"></i> Edit</a>')
            ->addColumn('delete', '<button type="button" class="delete-modal btn btn-danger btn-sm" data-toggle="modal" data-id="{{$id}}"><i class="fa fa-trash"></i> Delete</button>')
            ->rawColumns(['edit', 'delete'])
            ->make(true);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $permission = Permission::get();
        // $role = Role::find($request->id);
        // $rolePermissions = DB::table("role_has_permissions")->where("role_has_permissions.role_id",1)
        //     ->pluck('role_has_permissions.permission_id','role_has_permissions.permission_id')
        //     ->all();
        return view('admin.access.roles.index', compact('permission'));
        // return view('admin.access.roles.index', compact('permission'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $permission = Permission::orderBy('name')->get();
        return view('admin.access.roles.create', compact('permission'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules =
            [
                'name' => 'required|unique:roles,name',
                'permission' => 'required',

            ];
        $v = Validator::make($request->all(), $rules);
        if ($v->fails()) {
            // return Response::json(array('errors' => $v->getMessageBag()->toArray()));
            Alert::error('Error Title', 'One or more information is needed.');
            return back()->with('errors', $v->messages()->all())->withInput();
        } else {

            $role              = new Role;
            $role->name        = $request->name;

            if ($role->save()) {
                $role->syncPermissions($request->permission);
                // return response()->json($role);
                $msg = 'The Role [' . $role->name . '] was successfully Saved.';
                Alert::success('Success ', $msg);
                return redirect()->route('roles.index')->withMessage($msg)->withMessageType('success');
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
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $role = Role::find($id);
        $permission = Permission::orderBy('name')->get();
        $rolePermissions = DB::table("role_has_permissions")->where("role_has_permissions.role_id", $id)
            ->pluck('role_has_permissions.permission_id', 'role_has_permissions.permission_id')
            ->all();
        return view('admin.access.roles.edit', compact('role', 'permission', 'rolePermissions'));
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
        $rules =
            [
                'name' => 'required',
                // 'permission' => 'required',

            ];
        $v = Validator::make($request->all(), $rules);
        if ($v->fails()) {
            // return Response::json(array('errors' => $v->getMessageBag()->toArray()));
            Alert::error('Error Title', 'One or more information is needed.');
            return redirect()->back()->with('errors', $v->messages()->all())->withInput();
        } else {

            $role = Role::find($id);
            $role->name = $request->name;
            $role->save();
            $role->syncPermissions($request->permission);

            $msg = 'The Role [' . $role->name . '] was successfully updated.';
            Alert::success('Success ', $msg);
            return redirect()->route('roles.index')->withMessage($msg)->withMessageType('success');
            // return response()->json($role);
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
        $role = DB::table("roles")->where('id', $id)->delete();
        return response()->json($role);
    }
}
