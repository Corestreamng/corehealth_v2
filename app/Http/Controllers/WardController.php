<?php

namespace App\Http\Controllers;

use App\Models\Ward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;

class WardController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('admin.wards.index');
    }

    /**
     * Get wards list for DataTable.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function listWards()
    {
        $wards = Ward::withCount(['beds', 'beds as occupied_beds_count' => function ($query) {
            $query->where('bed_status', 'occupied');
        }])->orderBy('name', 'ASC')->get();

        return DataTables::of($wards)
            ->addIndexColumn()
            ->addColumn('type_badge', function ($ward) {
                $colors = [
                    'general' => 'primary',
                    'icu' => 'danger',
                    'pediatric' => 'info',
                    'maternity' => 'pink',
                    'recovery' => 'warning',
                    'emergency' => 'danger',
                    'psychiatric' => 'secondary',
                    'isolation' => 'dark',
                    'private' => 'success',
                    'other' => 'light',
                ];
                $color = $colors[$ward->type] ?? 'secondary';
                $label = Ward::TYPES[$ward->type] ?? ucfirst($ward->type);
                return '<span class="badge badge-' . $color . '">' . $label . '</span>';
            })
            ->addColumn('location_display', function ($ward) {
                $parts = [];
                if ($ward->building) $parts[] = $ward->building;
                if ($ward->floor) $parts[] = $ward->floor;
                return implode(', ', $parts) ?: '-';
            })
            ->addColumn('occupancy', function ($ward) {
                $total = $ward->beds_count;
                $occupied = $ward->occupied_beds_count;
                $percentage = $total > 0 ? round(($occupied / $total) * 100) : 0;
                $color = $percentage >= 90 ? 'danger' : ($percentage >= 70 ? 'warning' : 'success');
                return '<div class="progress" style="height: 20px;">
                            <div class="progress-bar bg-' . $color . '" role="progressbar" style="width: ' . $percentage . '%">
                                ' . $occupied . '/' . $total . ' (' . $percentage . '%)
                            </div>
                        </div>';
            })
            ->addColumn('status_badge', function ($ward) {
                return $ward->is_active
                    ? '<span class="badge badge-success">Active</span>'
                    : '<span class="badge badge-secondary">Inactive</span>';
            })
            ->addColumn('edit', function ($ward) {
                return '<a href="' . route('wards.edit', $ward->id) . '" class="btn btn-info btn-sm"><i class="fa fa-pencil"></i> Edit</a>';
            })
            ->addColumn('delete', function ($ward) {
                return '<button type="button" class="delete-modal btn btn-danger btn-sm" data-toggle="modal" data-id="' . $ward->id . '"><i class="fa fa-trash"></i> Delete</button>';
            })
            ->rawColumns(['type_badge', 'location_display', 'occupancy', 'status_badge', 'edit', 'delete'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $wardTypes = Ward::TYPES;

        return view('admin.wards.create', compact('wardTypes'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255|unique:wards,name',
            'code' => 'nullable|string|max:20|unique:wards,code',
            'type' => 'required|in:' . implode(',', array_keys(Ward::TYPES)),
            'capacity' => 'nullable|integer|min:1',
            'floor' => 'nullable|string|max:50',
            'building' => 'nullable|string|max:100',
            'nurse_station' => 'nullable|string|max:255',
            'contact_extension' => 'nullable|string|max:20',
            'nurse_patient_ratio' => 'nullable|numeric|min:0|max:1',
            'is_active' => 'boolean',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $ward = Ward::create([
            'name' => $request->name,
            'code' => $request->code,
            'type' => $request->type,
            'capacity' => $request->capacity,
            'floor' => $request->floor,
            'building' => $request->building,
            'nurse_station' => $request->nurse_station,
            'contact_extension' => $request->contact_extension,
            'nurse_patient_ratio' => $request->nurse_patient_ratio,
            'is_active' => $request->has('is_active'),
            'created_by' => auth()->id(),
        ]);

        Alert::success('Success', 'Ward "' . $ward->name . '" created successfully!');
        return redirect()->route('wards.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Ward  $ward
     * @return \Illuminate\Http\Response
     */
    public function show(Ward $ward)
    {
        $ward->load(['beds' => function ($query) {
            $query->orderBy('name');
        }]);

        return view('admin.wards.show', compact('ward'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Ward  $ward
     * @return \Illuminate\Http\Response
     */
    public function edit(Ward $ward)
    {
        $wardTypes = Ward::TYPES;

        return view('admin.wards.edit', compact('ward', 'wardTypes'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Ward  $ward
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Ward $ward)
    {
        $rules = [
            'name' => 'required|string|max:255|unique:wards,name,' . $ward->id,
            'code' => 'nullable|string|max:20|unique:wards,code,' . $ward->id,
            'type' => 'required|in:' . implode(',', array_keys(Ward::TYPES)),
            'capacity' => 'nullable|integer|min:1',
            'floor' => 'nullable|string|max:50',
            'building' => 'nullable|string|max:100',
            'nurse_station' => 'nullable|string|max:255',
            'contact_extension' => 'nullable|string|max:20',
            'nurse_patient_ratio' => 'nullable|numeric|min:0|max:1',
            'is_active' => 'boolean',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $ward->update([
            'name' => $request->name,
            'code' => $request->code,
            'type' => $request->type,
            'capacity' => $request->capacity,
            'floor' => $request->floor,
            'building' => $request->building,
            'nurse_station' => $request->nurse_station,
            'contact_extension' => $request->contact_extension,
            'nurse_patient_ratio' => $request->nurse_patient_ratio,
            'is_active' => $request->has('is_active'),
        ]);

        Alert::success('Success', 'Ward "' . $ward->name . '" updated successfully!');
        return redirect()->route('wards.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Ward  $ward
     * @return \Illuminate\Http\Response
     */
    public function destroy(Ward $ward)
    {
        // Check if ward has any beds
        if ($ward->beds()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete ward with existing beds. Please reassign or delete the beds first.'
            ], 422);
        }

        $wardName = $ward->name;
        $ward->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ward "' . $wardName . '" deleted successfully!'
        ]);
    }

    /**
     * Get wards for dropdown/select.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWardsForSelect()
    {
        $wards = Ward::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'floor', 'building']);

        return response()->json($wards);
    }

    /**
     * Get ward availability with bed counts for admission modal.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailability()
    {
        $wards = Ward::where('is_active', true)
            ->withCount([
                'beds as total_beds',
                'beds as available_beds' => function ($q) {
                    $q->where('bed_status', 'available');
                },
                'beds as occupied_beds' => function ($q) {
                    $q->where('bed_status', 'occupied');
                },
            ])
            ->orderBy('name')
            ->get();

        $result = $wards->map(function ($ward) {
            $occupancyPct = $ward->total_beds > 0
                ? round(($ward->occupied_beds / $ward->total_beds) * 100)
                : 0;

            return [
                'id'             => $ward->id,
                'name'           => $ward->name,
                'type'           => $ward->type,
                'type_label'     => Ward::TYPES[$ward->type] ?? ucfirst($ward->type),
                'floor'          => $ward->floor,
                'building'       => $ward->building,
                'total_beds'     => (int) $ward->total_beds,
                'available_beds' => (int) $ward->available_beds,
                'occupied_beds'  => (int) $ward->occupied_beds,
                'occupancy_pct'  => $occupancyPct,
            ];
        });

        return response()->json($result);
    }
}
