<?php

namespace App\Http\Controllers;

use App\Models\ClinicSchedule;
use App\Models\DoctorAvailability;
use App\Models\DoctorAvailabilityOverride;
use App\Models\Clinic;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class ClinicScheduleController extends Controller
{
    // ─── Day labels ────────────────────────────────────────────────────
    const DAY_LABELS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    // ═══════════════════════════════════════════════════════════════════
    //  Page View
    // ═══════════════════════════════════════════════════════════════════

    public function index()
    {
        $clinics = Clinic::orderBy('name')->get();
        $doctors = Staff::whereHas('user', function ($q) {
            $q->whereHas('roles', function ($rq) {
                $rq->where('name', 'DOCTOR');
            });
        })->with('user:id,name')->orderBy('id')->get();

        return view('admin.clinic-schedules.index', compact('clinics', 'doctors'));
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Clinic Weekly Schedules  (Tab 1)
    // ═══════════════════════════════════════════════════════════════════

    public function clinicScheduleData(Request $request)
    {
        $query = ClinicSchedule::with('clinic:id,name')->orderBy('clinic_id')->orderBy('day_of_week');

        if ($request->filled('clinic_id')) {
            $query->where('clinic_id', $request->clinic_id);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('clinic_name', fn($row) => $row->clinic->name ?? 'N/A')
            ->addColumn('day_name', fn($row) => self::DAY_LABELS[$row->day_of_week] ?? $row->day_of_week)
            ->addColumn('hours', fn($row) => date('h:i A', strtotime($row->open_time)) . ' – ' . date('h:i A', strtotime($row->close_time)))
            ->addColumn('status', fn($row) => $row->is_active
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-secondary">Inactive</span>')
            ->addColumn('actions', function ($row) {
                return '<button class="btn btn-sm btn-warning me-1" onclick="editClinicSchedule(' . $row->id . ')"><i class="mdi mdi-pencil"></i></button>'
                    . '<button class="btn btn-sm btn-' . ($row->is_active ? 'secondary' : 'success') . ' me-1" onclick="toggleClinicSchedule(' . $row->id . ')"><i class="mdi mdi-' . ($row->is_active ? 'eye-off' : 'eye') . '"></i></button>'
                    . '<button class="btn btn-sm btn-danger" onclick="deleteClinicSchedule(' . $row->id . ')"><i class="fa fa-trash"></i></button>';
            })
            ->rawColumns(['status', 'actions'])
            ->make(true);
    }

    public function storeClinicSchedule(Request $request)
    {
        try {
            $validated = $request->validate([
                'clinic_id'             => 'required|exists:clinics,id',
                'day_of_week'           => 'required|integer|between:0,6',
                'open_time'             => 'required|date_format:H:i',
                'close_time'            => 'required|date_format:H:i|after:open_time',
                'slot_duration_minutes' => 'nullable|integer|min:5|max:120',
                'max_concurrent_slots'  => 'nullable|integer|min:1|max:20',
                'is_active'             => 'nullable|boolean',
            ]);

            $validated['slot_duration_minutes'] = $validated['slot_duration_minutes'] ?? 15;
            $validated['max_concurrent_slots']  = $validated['max_concurrent_slots'] ?? 1;
            $validated['is_active']             = $validated['is_active'] ?? true;

            $schedule = ClinicSchedule::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Clinic schedule created successfully.',
                'data'    => $schedule,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Clinic schedule creation failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create schedule: ' . $e->getMessage()], 500);
        }
    }

    public function showClinicSchedule(ClinicSchedule $schedule)
    {
        return response()->json($schedule->load('clinic:id,name'));
    }

    public function updateClinicSchedule(Request $request, ClinicSchedule $schedule)
    {
        try {
            $validated = $request->validate([
                'clinic_id'             => 'required|exists:clinics,id',
                'day_of_week'           => 'required|integer|between:0,6',
                'open_time'             => 'required|date_format:H:i',
                'close_time'            => 'required|date_format:H:i|after:open_time',
                'slot_duration_minutes' => 'nullable|integer|min:5|max:120',
                'max_concurrent_slots'  => 'nullable|integer|min:1|max:20',
                'is_active'             => 'nullable|boolean',
            ]);

            $schedule->update($validated);

            return response()->json(['success' => true, 'message' => 'Schedule updated successfully.', 'data' => $schedule]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Clinic schedule update failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update schedule.'], 500);
        }
    }

    public function toggleClinicSchedule(ClinicSchedule $schedule)
    {
        $schedule->update(['is_active' => !$schedule->is_active]);
        return response()->json(['success' => true, 'message' => 'Schedule ' . ($schedule->is_active ? 'activated' : 'deactivated') . '.']);
    }

    public function destroyClinicSchedule(ClinicSchedule $schedule)
    {
        try {
            $schedule->delete();
            return response()->json(['success' => true, 'message' => 'Schedule deleted.']);
        } catch (\Exception $e) {
            Log::error('Clinic schedule delete failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete schedule.'], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Doctor Weekly Availability  (Tab 2)
    // ═══════════════════════════════════════════════════════════════════

    public function doctorAvailabilityData(Request $request)
    {
        $query = DoctorAvailability::with(['staff.user:id,name', 'clinic:id,name'])
            ->orderBy('staff_id')
            ->orderBy('clinic_id')
            ->orderBy('day_of_week');

        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }
        if ($request->filled('clinic_id')) {
            $query->where('clinic_id', $request->clinic_id);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('doctor_name', fn($row) => $row->staff && $row->staff->user ? $row->staff->user->name : 'N/A')
            ->addColumn('clinic_name', fn($row) => $row->clinic->name ?? 'N/A')
            ->addColumn('day_name', fn($row) => self::DAY_LABELS[$row->day_of_week] ?? $row->day_of_week)
            ->addColumn('hours', fn($row) => date('h:i A', strtotime($row->start_time)) . ' – ' . date('h:i A', strtotime($row->end_time)))
            ->addColumn('status', fn($row) => $row->is_active
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-secondary">Inactive</span>')
            ->addColumn('actions', function ($row) {
                return '<button class="btn btn-sm btn-warning me-1" onclick="editAvailability(' . $row->id . ')"><i class="mdi mdi-pencil"></i></button>'
                    . '<button class="btn btn-sm btn-' . ($row->is_active ? 'secondary' : 'success') . ' me-1" onclick="toggleAvailability(' . $row->id . ')"><i class="mdi mdi-' . ($row->is_active ? 'eye-off' : 'eye') . '"></i></button>'
                    . '<button class="btn btn-sm btn-danger" onclick="deleteAvailability(' . $row->id . ')"><i class="fa fa-trash"></i></button>';
            })
            ->rawColumns(['status', 'actions'])
            ->make(true);
    }

    public function storeDoctorAvailability(Request $request)
    {
        try {
            $validated = $request->validate([
                'staff_id'    => 'required|exists:staff,id',
                'clinic_id'   => 'required|exists:clinics,id',
                'day_of_week' => 'required|integer|between:0,6',
                'start_time'  => 'required|date_format:H:i',
                'end_time'    => 'required|date_format:H:i|after:start_time',
                'is_active'   => 'nullable|boolean',
            ]);

            $validated['is_active'] = $validated['is_active'] ?? true;

            $avail = DoctorAvailability::create($validated);

            return response()->json(['success' => true, 'message' => 'Doctor availability created.', 'data' => $avail]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Doctor availability creation failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create availability.'], 500);
        }
    }

    public function showDoctorAvailability(DoctorAvailability $availability)
    {
        return response()->json($availability->load(['staff.user:id,name', 'clinic:id,name']));
    }

    public function updateDoctorAvailability(Request $request, DoctorAvailability $availability)
    {
        try {
            $validated = $request->validate([
                'staff_id'    => 'required|exists:staff,id',
                'clinic_id'   => 'required|exists:clinics,id',
                'day_of_week' => 'required|integer|between:0,6',
                'start_time'  => 'required|date_format:H:i',
                'end_time'    => 'required|date_format:H:i|after:start_time',
                'is_active'   => 'nullable|boolean',
            ]);

            $availability->update($validated);

            return response()->json(['success' => true, 'message' => 'Availability updated.', 'data' => $availability]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Doctor availability update failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update availability.'], 500);
        }
    }

    public function toggleDoctorAvailability(DoctorAvailability $availability)
    {
        $availability->update(['is_active' => !$availability->is_active]);
        return response()->json(['success' => true, 'message' => 'Availability ' . ($availability->is_active ? 'activated' : 'deactivated') . '.']);
    }

    public function destroyDoctorAvailability(DoctorAvailability $availability)
    {
        try {
            $availability->delete();
            return response()->json(['success' => true, 'message' => 'Availability deleted.']);
        } catch (\Exception $e) {
            Log::error('Doctor availability delete failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete availability.'], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Doctor Availability Overrides  (Tab 3)
    // ═══════════════════════════════════════════════════════════════════

    public function overrideData(Request $request)
    {
        $query = DoctorAvailabilityOverride::with(['staff.user:id,name', 'clinic:id,name'])
            ->orderBy('override_date', 'desc');

        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }
        if ($request->filled('clinic_id')) {
            $query->where('clinic_id', $request->clinic_id);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('doctor_name', fn($row) => $row->staff && $row->staff->user ? $row->staff->user->name : 'N/A')
            ->addColumn('clinic_name', fn($row) => $row->clinic ? $row->clinic->name : '<span class="badge bg-info">All Clinics</span>')
            ->addColumn('type_badge', function ($row) {
                return $row->is_available
                    ? '<span class="badge bg-success"><i class="mdi mdi-plus-circle"></i> Extra Availability</span>'
                    : '<span class="badge bg-danger"><i class="mdi mdi-cancel"></i> Blocked</span>';
            })
            ->addColumn('time_range', function ($row) {
                if (!$row->start_time && !$row->end_time) {
                    return '<span class="text-muted">Full Day</span>';
                }
                return date('h:i A', strtotime($row->start_time)) . ' – ' . date('h:i A', strtotime($row->end_time));
            })
            ->addColumn('actions', function ($row) {
                return '<button class="btn btn-sm btn-warning me-1" onclick="editOverride(' . $row->id . ')"><i class="mdi mdi-pencil"></i></button>'
                    . '<button class="btn btn-sm btn-danger" onclick="deleteOverride(' . $row->id . ')"><i class="fa fa-trash"></i></button>';
            })
            ->rawColumns(['clinic_name', 'type_badge', 'time_range', 'actions'])
            ->make(true);
    }

    public function storeOverride(Request $request)
    {
        try {
            $validated = $request->validate([
                'staff_id'      => 'required|exists:staff,id',
                'clinic_id'     => 'nullable|exists:clinics,id',
                'override_date' => 'required|date',
                'start_time'    => 'nullable|date_format:H:i',
                'end_time'      => 'nullable|date_format:H:i|after:start_time',
                'is_available'  => 'required|boolean',
                'reason'        => 'nullable|string|max:255',
            ]);

            $override = DoctorAvailabilityOverride::create($validated);

            return response()->json(['success' => true, 'message' => 'Override created.', 'data' => $override]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Override creation failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create override.'], 500);
        }
    }

    public function showOverride(DoctorAvailabilityOverride $override)
    {
        return response()->json($override->load(['staff.user:id,name', 'clinic:id,name']));
    }

    public function updateOverride(Request $request, DoctorAvailabilityOverride $override)
    {
        try {
            $validated = $request->validate([
                'staff_id'      => 'required|exists:staff,id',
                'clinic_id'     => 'nullable|exists:clinics,id',
                'override_date' => 'required|date',
                'start_time'    => 'nullable|date_format:H:i',
                'end_time'      => 'nullable|date_format:H:i|after:start_time',
                'is_available'  => 'required|boolean',
                'reason'        => 'nullable|string|max:255',
            ]);

            $override->update($validated);

            return response()->json(['success' => true, 'message' => 'Override updated.', 'data' => $override]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Override update failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update override.'], 500);
        }
    }

    public function destroyOverride(DoctorAvailabilityOverride $override)
    {
        try {
            $override->delete();
            return response()->json(['success' => true, 'message' => 'Override deleted.']);
        } catch (\Exception $e) {
            Log::error('Override delete failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete override.'], 500);
        }
    }
}
