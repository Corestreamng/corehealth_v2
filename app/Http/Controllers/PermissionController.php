<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Validator;
use Response;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;

class PermissionController extends Controller
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
    public function listPermissions()
    {
        $permissions = Permission::orderBy('name', 'ASC')->get();
        return Datatables::of($permissions)
                ->addIndexColumn()
                ->addColumn('show',   '<a href="{{ route(\'permissions.show\', $id)}}" class="btn btn-success btn-sm" ><i class="fa fa-eye"></i> Show</a>')
                ->addColumn('edit',   '<a href="{{ route(\'permissions.edit\', $id)}}" class="btn btn-info btn-sm" ><i class="fa fa-pencil"></i> Edit</a>')
                ->addColumn('delete', '<button type="button" class="delete-modal btn btn-danger btn-sm" data-toggle="modal" data-id="{{$id}}"><i class="fa fa-trash"></i> Delete</button>')
                ->rawColumns(['show','edit', 'delete'])
                ->make(true);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $permission = Permission::get();
        return view('admin.access.permissions.index', compact('permission'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.access.permissions.create');
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
            'name' => 'required',
        ];
        $v = Validator::make($request->all(), $rules);

        if( $v->fails() ) {
            // return Response::json(array('errors' => $v->getMessageBag()->toArray()));
            Alert::error('Error Title', 'One or more information is needed.');
            return redirect()->back()->withInput()->with('errors', $v->messages()->all())->withInput();
        } else {

            $permission              = new Permission;
            $permission->name        = $request->name;

            if( $permission->save() ) {
                $permission->syncPermissions($request->permission);
                $msg = 'The Permission [' . $permission->name . '] was successfully Saved.';
                Alert::success('Success ', $msg);
                return redirect()->route('permissions.index')->withMessage($msg)->withMessageType('success');
                // return response()->json($permission);
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
        $permission = Permission::find($id);
        return view('admin.access.permissions.show', compact('permission'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $permission = Permission::find($id);
        return view('admin.access.permissions.edit', compact('permission'));
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
        if( $v->fails() ) {
            // return Response::json(array('errors' => $v->getMessageBag()->toArray()));
            Alert::error('Error Title', 'One or more information is needed.');
            return redirect()->back()->withInput()->with('errors', $v->messages()->all())->withInput();
        } else {

            $permission = Permission::find($id);
            $permission->name = $request->name;
            $permission->save();
            // return response()->json($permission);
            $msg = 'The Permission [' . $permission->name . '] was successfully updated.';
            Alert::success('Success ', $msg);
            return redirect()->route('permissions.index')->withMessage($msg)->withMessageType('success');
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
        // $permission = DB::table("permissions")->where('id', $id)->delete();
        $permission = Permission::where('id', '=', $id)->delete();
        return response()->json($permission);
    }
}
