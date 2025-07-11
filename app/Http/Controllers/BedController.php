<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use App\Models\ServicePrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\service;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Auth;

class BedController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('admin.beds.index');
    }

    public function listBeds()
    {
        $beds = Bed::orderBy('name', 'ASC')->where('status', 1)->get();

        return Datatables::of($beds)
            ->addIndexColumn()
            ->addColumn('edit',   '<a href="{{ route(\'beds.edit\', $id)}}" class="btn btn-info btn-sm" ><i class="fa fa-pencil"></i> Edit</a>')
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
        return view('admin.beds.create');
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
                    'ward' => 'required',
                    'price' => 'required'

                ];
            $v = Validator::make($request->all(), $rules);
            if ($v->fails()) {
                return back()->with('errors', $v->messages()->all())->withInput();
            } else {
                DB::beginTransaction();
                $bed_servie_entry                      = new service;
                $bed_servie_entry->user_id             = Auth::user()->id;
                $bed_servie_entry->category_id         = env('BED_SERVICE_CATGORY_ID', 1);
                $bed_servie_entry->service_name        = 'Bed ' . $request->name . " " . $request->ward . " " . $request->unit;
                $bed_servie_entry->service_code        = strtoupper('Bed ' . $request->name . " " . $request->ward . " " . $request->unit);
                $bed_servie_entry->status              = 1;
                $bed_servie_entry->price_assign        = 1;
                $bed_servie_entry->save();

                $bed_entry_service_price_entry                 = new ServicePrice();
                $bed_entry_service_price_entry->service_id     = $bed_servie_entry->id;
                $bed_entry_service_price_entry->cost_price     = $request->price;
                $bed_entry_service_price_entry->sale_price     = $request->price;
                $bed_entry_service_price_entry->max_discount   = $request->max_discount ?? 0;
                $bed_entry_service_price_entry->status         = 1;
                $bed_entry_service_price_entry->save();

                $bed              = new Bed;
                $bed->name        = $request->name;
                $bed->ward        = $request->ward;
                $bed->unit        = $request->unit;
                $bed->price       = $request->price;
                $bed->service_id  = $bed_servie_entry->id;

                if ($bed->save()) {
                    $msg = 'The bed [' . $bed->name . '] was successfully Saved.';
                    // dd($request->all());
                    DB::commit();
                    return redirect()->route('beds.index')->withMessage($msg)->withMessageType('success');
                }
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
            Log::error($e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Bed  $bed
     * @return \Illuminate\Http\Response
     */
    public function show(Bed $bed) {}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Bed  $bed
     * @return \Illuminate\Http\Response
     */
    public function edit(Bed $bed)
    {
        return view('admin.beds.edit', compact('bed'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Bed  $bed
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Bed $bed)
    {
        try {
            $rules =
                [
                    'name' => 'required',
                    'price' => 'nullable',
                    'ward' => 'required'

                ];
            $v = Validator::make($request->all(), $rules);
            if ($v->fails()) {
                return back()->with('errors', $v->messages()->all())->withInput();
            } else {

                $bed_servie_entry                      = service::where('id', $bed->service_id)->first();
                $bed_servie_entry->category_id         = env('BED_SERVICE_CATGORY_ID', 1);
                $bed_servie_entry->service_name        = 'Bed ' . $request->name . " " . $request->ward . " " . $request->unit;
                $bed_servie_entry->service_code        = strtoupper('Bed ' . $request->name . " " . $request->ward . " " . $request->unit);
                $bed_servie_entry->update();

                $bed_entry_service_price_entry                 = ServicePrice::where('service_id', $bed->service_id)->first();
                $bed_entry_service_price_entry->service_id     = $bed_servie_entry->id;
                $bed_entry_service_price_entry->cost_price     = $request->price;
                $bed_entry_service_price_entry->sale_price     = $request->price;
                $bed_entry_service_price_entry->update();

                $bed->name        = $request->name;
                $bed->ward        = $request->ward;
                $bed->price       = $request->price;
                $bed->unit        = $request->unit;

                if ($bed->update()) {
                    $msg = 'The bed [' . $bed->name . '] was successfully Updated.';
                    Alert::success('Success ', $msg);
                    return redirect()->route('beds.index')->withMessage($msg)->withMessageType('success');
                }
            }
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
            Log::error($e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Bed  $bed
     * @return \Illuminate\Http\Response
     */
    public function destroy(Bed $bed)
    {
        //
    }
}
