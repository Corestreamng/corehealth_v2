<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Validator;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;

class StoreController extends Controller
{
    public function listStores()
    {
        $pc = Store::where('status', '=', 1)->orderBy('store_name', 'ASC')->get();

        return Datatables::of($pc)
            ->addIndexColumn()
            ->editColumn('status', function ($pc) {
                return (($pc->status == 0) ? "Inactive" : "Active");
            })
            ->editColumn('location', function ($pc) {
                return $pc->location;
            })
            ->addColumn('edit', function ($pc) {

                if (Auth::user()->hasPermissionTo('can-manage-store') || Auth::user()->hasRole(['ADMIN', 'STORE'])) {

                    $url = route('stores.edit', $pc->id);
                    return '<a href="' . $url . '" class="btn btn-info btn-sm"><i class="fa fa-pencil"></i> Edit</a>';
                } else {

                    $label = '<button disabled class="btn btn-secondary btn-sm"> <i class="fa fa-pencil"></i> Edit</button>';
                    return $label;
                }
            })
            ->addColumn('view', function ($pc) {

                if (Auth::user()->hasPermissionTo('can-manage-store') || Auth::user()->hasRole(['ADMIN', 'STORE'])) {

                    $url = route('stores-stokes.show', $pc->id);
                    return '<a href="' . $url . '" class="btn btn-info btn-sm"><i class="fa fa-eye"></i> View Product</a>';
                } else {

                    $label = '<button disabled class="btn btn-secondary btn-sm"> <i class="fa fa-eye"></i> View Product</button>';
                    return $label;
                }
            })
            ->rawColumns(['edit', 'view'])
            ->make(true);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        // $data = Store::orderBy('store_name','DESC')->get();
        return view('admin.stores.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {

        return view('admin.stores.create');
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
        try {
            $rules = [
                'store_name' => 'required|min:3|max:150',
                'location'   => 'nullable|min:3|max:150',
            ];

            $v = Validator::make($request->all(), $rules);

            if ($v->fails()) {
                Alert::error('Error Title', 'One or more information is needed.');
                return redirect()->back()->withInput()->with('errors', $v->messages()->all())->withInput();
            } else {

                $store               = new Store();
                $store->store_name   = $request->store_name;
                $store->location     = $request->location;
                $store->status       = $request->has('status') ? 1 : 0;

                if ($store->save()) {
                    $msg = 'New Store  ' . $request->store_name . ' was created successfully.';
                    Alert::success('Success ', $msg);
                    return redirect()->route('stores.index')->withMessage($msg)->withMessageType('success');
                }
            }
        } catch (\Exception $e) {

            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage());
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

        try {

            $store = Store::whereId($id)->first();
            return view('admin.stores.edit', compact('store'));
        } catch (\Exception $e) {

            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    { // dd($request);
        try {

            $rules = [
                'store_name' => 'required|min:3|max:150',
                'location'   => 'required|min:3|max:150',
            ];

            $v = Validator::make($request->all(), $rules);

            if ($v->fails()) {
                Alert::error('Error Title', 'One or more information is needed.');
                return redirect()->back()->withInput()->with('errors', $v->messages()->all())->withInput();
            } else {

                $store               = Store::findOrFail($id);
                $store->store_name   = $request->store_name;
                $store->location     = $request->location;
                $store->status       = $request->has('status') ? 1 : 0;

                if ($store->update()) {
                    $msg = 'Store  ' . $request->store_name . ' was Updated successfully.';
                    Alert::success('Success ', $msg);
                    return redirect()->route('stores.index')->withMessage($msg)->withMessageType('success');
                }
            }
        } catch (Exception $e) {

            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage())->withMessageType('danger');
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
        //
    }
}
