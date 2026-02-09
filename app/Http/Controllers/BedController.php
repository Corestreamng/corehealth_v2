<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use App\Models\Ward;
use App\Models\servicePrice;
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
        $beds = Bed::with('wardRelation')->orderBy('name', 'ASC')->where('status', 1)->get();

        return Datatables::of($beds)
            ->addIndexColumn()
            ->addColumn('ward_name', function ($bed) {
                return $bed->wardRelation ? $bed->wardRelation->name : ($bed->ward ?? 'N/A');
            })
            ->addColumn('status_badge', function ($bed) {
                $statusColors = [
                    'available' => 'success',
                    'occupied' => 'danger',
                    'maintenance' => 'warning',
                    'reserved' => 'info',
                ];
                $color = $statusColors[$bed->bed_status] ?? 'secondary';
                return '<span class="badge badge-' . $color . '">' . ucfirst($bed->bed_status ?? 'available') . '</span>';
            })
            ->addColumn('edit', '<a href="{{ route(\'beds.edit\', $id)}}" class="btn btn-info btn-sm" ><i class="fa fa-pencil"></i> Edit</a>')
            ->addColumn('delete', '<button type="button" class="delete-modal btn btn-danger btn-sm" data-toggle="modal" data-id="{{$id}}"><i class="fa fa-trash"></i> Delete</button>')
            ->rawColumns(['status_badge', 'edit', 'delete'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $wards = Ward::where('is_active', true)->orderBy('name')->get();
        return view('admin.beds.create', compact('wards'));
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
            $rules = [
                'name' => 'required',
                'ward_id' => 'nullable|exists:wards,id',
                'ward' => 'required_without:ward_id',
                'price' => 'required',
                'bed_status' => 'nullable|in:available,occupied,maintenance,reserved',
            ];
            $v = Validator::make($request->all(), $rules);
            if ($v->fails()) {
                return back()->with('errors', $v->messages()->all())->withInput();
            } else {
                DB::beginTransaction();

                // Get ward name from ward_id if provided
                $wardName = $request->ward;
                if ($request->ward_id) {
                    $ward = Ward::find($request->ward_id);
                    $wardName = $ward ? $ward->name : $request->ward;
                }

                $bed_servie_entry                      = new service;
                $bed_servie_entry->user_id             = Auth::user()->id;
                $bed_servie_entry->category_id         = appsettings('bed_service_category_id', 1);
                $bed_servie_entry->service_name        = 'Bed ' . $request->name . " " . $wardName . " " . $request->unit;
                $bed_servie_entry->service_code        = strtoupper('Bed ' . $request->name . " " . $wardName . " " . $request->unit);
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
                $bed->ward        = $wardName;
                $bed->ward_id     = $request->ward_id;
                $bed->unit        = $request->unit;
                $bed->price       = $request->price;
                $bed->bed_status  = $request->bed_status ?? 'available';
                $bed->service_id  = $bed_servie_entry->id;

                if ($bed->save()) {
                    $msg = 'The bed [' . $bed->name . '] was successfully Saved.';
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
        $wards = Ward::where('is_active', true)->orderBy('name')->get();
        return view('admin.beds.edit', compact('bed', 'wards'));
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
            $rules = [
                'name' => 'required',
                'price' => 'nullable',
                'ward_id' => 'nullable|exists:wards,id',
                'ward' => 'required_without:ward_id',
                'bed_status' => 'nullable|in:available,occupied,maintenance,reserved',
            ];
            $v = Validator::make($request->all(), $rules);
            if ($v->fails()) {
                return back()->with('errors', $v->messages()->all())->withInput();
            } else {
                // Get ward name from ward_id if provided
                $wardName = $request->ward;
                if ($request->ward_id) {
                    $ward = Ward::find($request->ward_id);
                    $wardName = $ward ? $ward->name : $request->ward;
                }

                $bedCategoryId = appsettings('bed_service_category_id', 1);
                $serviceName = 'Bed ' . $request->name . " " . $wardName . " " . $request->unit;
                $serviceCode = strtoupper(str_replace(' ', '-', $serviceName));

                // Find or create bed service
                if ($bed->service_id) {
                    $bed_service_entry = service::find($bed->service_id);
                }

                if (!isset($bed_service_entry) || !$bed_service_entry) {
                    // Create new service if bed doesn't have one
                    $bed_service_entry = service::create([
                        'user_id' => auth()->id() ?? 1,
                        'category_id' => $bedCategoryId,
                        'service_name' => $serviceName,
                        'service_code' => $serviceCode,
                        'status' => 1,
                        'price_assign' => 1,
                    ]);
                    $bed->service_id = $bed_service_entry->id;
                } else {
                    // Update existing service
                    $bed_service_entry->category_id = $bedCategoryId;
                    $bed_service_entry->service_name = $serviceName;
                    $bed_service_entry->service_code = $serviceCode;
                    $bed_service_entry->update();
                }

                // Update or create service price - ALWAYS sync with bed price
                ServicePrice::updateOrCreate(
                    ['service_id' => $bed_service_entry->id],
                    [
                        'cost_price' => $request->price ?? 0,
                        'sale_price' => $request->price ?? 0,
                        'status' => 1,
                    ]
                );

                $bed->name        = $request->name;
                $bed->ward        = $wardName;
                $bed->ward_id     = $request->ward_id;
                $bed->price       = $request->price;
                $bed->unit        = $request->unit;
                $bed->bed_status  = $request->bed_status ?? $bed->bed_status ?? 'available';

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
