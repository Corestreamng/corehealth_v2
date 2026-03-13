<?php

namespace App\Http\Controllers;

use App\Models\Hmo;
use App\Models\HmoScheme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;

class HmoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('admin.hmo.index');
    }

    public function listHmo()
    {
        $hmos = Hmo::with('scheme')->orderBy('name', 'ASC')->get();

        return Datatables::of($hmos)
            ->addIndexColumn()
            ->addColumn('scheme', function($hmo) {
                return $hmo->scheme ? $hmo->scheme->name : 'N/A';
            })
            ->addColumn('status_badge', function($hmo) {
                if ($hmo->status) {
                    return '<span class="badge badge-success">Active</span>';
                }
                return '<span class="badge badge-secondary">Inactive</span>';
            })
            ->addColumn('toggle', function($hmo) {
                $label = $hmo->status ? 'Deactivate' : 'Activate';
                $btnClass = $hmo->status ? 'btn-warning' : 'btn-success';
                $icon = $hmo->status ? 'fa-ban' : 'fa-check';
                return '<button type="button" class="btn btn-sm ' . $btnClass . ' toggle-hmo-status" data-id="' . $hmo->id . '"><i class="fa ' . $icon . '"></i> ' . $label . '</button>';
            })
            ->addColumn('edit',   '<a href="{{ route(\'hmo.edit\', $id)}}" class="btn btn-info btn-sm" ><i class="fa fa-pencil"></i> Edit</a>')
            ->addColumn('delete', '<button type="button" class="delete-modal btn btn-danger btn-sm" data-toggle="modal" data-id="{{$id}}"><i class="fa fa-trash"></i> Delete</button>')
            ->rawColumns(['status_badge', 'toggle', 'edit', 'delete'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $schemes = HmoScheme::where('status', 1)->orderBy('name', 'ASC')->get();
        return view('admin.hmo.create', compact('schemes'));
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
            $rules =
                [
                    'name' => 'required',
                    'description' => 'nullable',
                    'discount' => 'required'

                ];
            $v = Validator::make($request->all(), $rules);
            if ($v->fails()) {
                return back()->with('errors', $v->messages()->all())->withInput();
            } else {

                $hmo              = new Hmo;
                $hmo->name        = $request->name;
                $hmo->desc = $request->description;
                $hmo->discount    = $request->discount;
                $hmo->hmo_scheme_id = $request->hmo_scheme_id ?? 1;

                if ($hmo->save()) {
                    $msg = 'The HMO [' . $hmo->name . '] was successfully Saved.';
                    Alert::success('Success ', $msg);
                    return redirect()->route('hmo.index')->withMessage($msg)->withMessageType('success');
                }
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            return redirect()->back()->withInput()->with('error',$e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Hmo  $hmo
     * @return \Illuminate\Http\Response
     */
    public function show(Hmo $hmo)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Hmo  $hmo
     * @return \Illuminate\Http\Response
     */
    public function edit(Hmo $hmo)
    {
        $schemes = HmoScheme::where('status', 1)->orderBy('name', 'ASC')->get();
        return view('admin.hmo.edit', compact('hmo', 'schemes'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Hmo  $hmo
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Hmo $hmo)
    {
        try {
            $rules =
                [
                    'name' => 'required',
                    'description' => 'nullable',
                    'discount' => 'required'

                ];
            $v = Validator::make($request->all(), $rules);
            if ($v->fails()) {
                return back()->with('errors', $v->messages()->all())->withInput();
            } else {

                $hmo->name        = $request->name;
                $hmo->desc        = $request->description;
                $hmo->discount    = $request->discount;
                $hmo->hmo_scheme_id = $request->hmo_scheme_id ?? $hmo->hmo_scheme_id ?? 1;

                if ($hmo->update()) {
                    $msg = 'The HMO [' . $hmo->name . '] was successfully Updated.';
                    Alert::success('Success ', $msg);
                    return redirect()->route('hmo.index')->withMessage($msg)->withMessageType('success');
                }
            }
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error',$e->getMessage());
            Log::error($e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Hmo  $hmo
     * @return \Illuminate\Http\Response
     */
    public function destroy(Hmo $hmo)
    {
        //
    }

    /**
     * Toggle HMO active/inactive status.
     */
    public function toggleStatus(Hmo $hmo)
    {
        $hmo->status = !$hmo->status;
        $hmo->save();

        $state = $hmo->status ? 'activated' : 'deactivated';
        return response()->json([
            'success' => true,
            'message' => "HMO '{$hmo->name}' has been {$state}.",
            'status' => $hmo->status,
        ]);
    }
}
