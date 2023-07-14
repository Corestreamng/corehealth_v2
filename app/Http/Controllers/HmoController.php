<?php

namespace App\Http\Controllers;

use App\Models\Hmo;
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
        $hmos = Hmo::orderBy('name', 'ASC')->get();

        return Datatables::of($hmos)
            ->addIndexColumn()
            ->addColumn('edit',   '<a href="{{ route(\'hmo.edit\', $id)}}" class="btn btn-info btn-sm" ><i class="fa fa-pencil"></i> Edit</a>')
            ->addColumn('delete', '<button type="button" class="delete-modal btn btn-danger btn-sm" data-toggle="modal" data-id="{{$id}}"><i class="fa fa-trash"></i> Delete</button>')
            ->rawColumns(['edit', 'delete'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.hmo.create');
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

                if ($hmo->save()) {
                    $msg = 'The HMO [' . $hmo->name . '] was successfully Saved.';
                    Alert::success('Success ', $msg);
                    return redirect()->route('hmo.index')->withMessage($msg)->withMessageType('success');
                }
            }
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e);
            Log::error($e->getMessage(), ['exception' => $e]);
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
        return view('admin.hmo.edit', compact('hmo'));
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

                if ($hmo->update()) {
                    $msg = 'The HMO [' . $hmo->name . '] was successfully Updated.';
                    Alert::success('Success ', $msg);
                    return redirect()->route('hmo.index')->withMessage($msg)->withMessageType('success');
                }
            }
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e);
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
}
