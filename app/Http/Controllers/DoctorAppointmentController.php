<?php

namespace App\Http\Controllers;

use App\Enums\QueueStatus;
use App\Helpers\HmoHelper;
use App\Models\Clinic;
use App\Models\DoctorAppointment;
use App\Models\DoctorQueue;
use App\Models\Encounter;
use App\Models\patient;
use App\Models\ProductOrServiceRequest;
use App\Models\Staff;
use App\Services\AppointmentSlotService;
use App\Services\QueueStatusService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;

class DoctorAppointmentController extends Controller
{
    protected QueueStatusService $queueStatusService;
    protected AppointmentSlotService $slotService;

    public function __construct(QueueStatusService $queueStatusService, AppointmentSlotService $slotService)
    {
        $this->queueStatusService = $queueStatusService;
        $this->slotService = $slotService;
    }

    // ─── Reception: List & DataTable ───────────────────────────────────

    /**
     * Get appointments for DataTable (reception workbench).
     * Supports filters: date, clinic_id, status, doctor_id
     */
    public function getAppointments(Request $request)
    {
        $query = DoctorAppointment::with(['patient.user', 'patient.hmo', 'clinic', 'doctor.user'])
            ->orderBy('appointment_date', 'asc')
            ->orderBy('start_time', 'asc');

        // Date filter
        if ($request->filled('date')) {
            $query->whereDate('appointment_date', $request->date);
        } elseif (!$request->filled('patient_id')) {
            // Default to today only when not filtering by patient
            $query->whereDate('appointment_date', Carbon::today());
        }

        // Patient filter (for patient-specific appointments tab)
        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Clinic filter
        if ($request->filled('clinic_id')) {
            $query->where('clinic_id', $request->clinic_id);
        }

        // Doctor filter
        if ($request->filled('doctor_id')) {
            $query->where('staff_id', $request->doctor_id);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('appointment_date', function ($appt) {
                return $appt->appointment_date ? Carbon::parse($appt->appointment_date)->format('M d, Y') : 'N/A';
            })
            ->addColumn('patient_name', function ($appt) {
                return $appt->patient ? userfullname($appt->patient->user_id) : 'N/A';
            })
            ->addColumn('patient_file_no', function ($appt) {
                return $appt->patient->file_no ?? 'N/A';
            })
            ->addColumn('patient_hmo', function ($appt) {
                return $appt->patient && $appt->patient->hmo ? $appt->patient->hmo->name : 'Private';
            })
            ->addColumn('clinic_name', function ($appt) {
                return $appt->clinic->name ?? 'N/A';
            })
            ->addColumn('doctor_name', function ($appt) {
                return $appt->doctor ? userfullname($appt->doctor->user_id) : 'Any';
            })
            ->addColumn('time_slot', function ($appt) {
                $start = Carbon::parse($appt->start_time)->format('h:i A');
                $end = Carbon::parse($appt->end_time)->format('h:i A');
                return $start . ' - ' . $end;
            })
            ->addColumn('type_badge', function ($appt) {
                $types = [
                    'scheduled'   => '<span class="badge bg-purple">Scheduled</span>',
                    'follow_up'   => '<span class="badge bg-info">Follow-Up</span>',
                    'referral'    => '<span class="badge bg-warning text-dark">Referral</span>',
                    'walk_in'     => '<span class="badge bg-secondary">Walk-In</span>',
                ];
                return $types[$appt->appointment_type] ?? '<span class="badge bg-secondary">' . ucfirst($appt->appointment_type ?? 'N/A') . '</span>';
            })
            ->addColumn('status_badge', function ($appt) {
                return QueueStatus::badge($appt->status);
            })
            ->addColumn('actions', function ($appt) {
                $buttons = '';
                $clinicId = $appt->clinic_id;
                $doctorId = $appt->staff_id;
                $date = $appt->appointment_date;
                $patientName = $appt->patient ? userfullname($appt->patient->user_id) : 'N/A';
                if ($appt->status === QueueStatus::SCHEDULED) {
                    $buttons .= '<button class="btn btn-sm btn-success btn-check-in-appointment" data-id="' . $appt->id . '" title="Check In"><i class="mdi mdi-account-check"></i> Check In</button> ';
                    $buttons .= '<button class="btn btn-sm btn-warning btn-reschedule-appointment" data-id="' . $appt->id . '" data-clinic="' . $clinicId . '" data-doctor="' . $doctorId . '" data-date="' . $date . '" data-patient="' . e($patientName) . '" data-reschedule-count="' . ($appt->reschedule_count ?? 0) . '" title="Reschedule"><i class="mdi mdi-calendar-edit"></i></button> ';
                    $buttons .= '<button class="btn btn-sm btn-purple btn-reassign-appointment" data-id="' . $appt->id . '" data-clinic="' . $clinicId . '" data-doctor="' . $doctorId . '" data-patient="' . e($patientName) . '" title="Change Doctor"><i class="mdi mdi-account-switch"></i></button> ';
                    $buttons .= '<button class="btn btn-sm btn-danger btn-cancel-appointment" data-id="' . $appt->id . '" title="Cancel"><i class="mdi mdi-close-circle"></i></button> ';
                    $buttons .= '<button class="btn btn-sm btn-info btn-noshow-appointment" data-id="' . $appt->id . '" title="Mark No-Show"><i class="mdi mdi-account-off"></i></button>';
                }
                if ($appt->status === QueueStatus::COMPLETED) {
                    $buttons .= '<button class="btn btn-sm btn-purple btn-view-chain" data-id="' . $appt->id . '" title="View History"><i class="mdi mdi-link-variant"></i></button>';
                }
                return $buttons;
            })
            ->rawColumns(['type_badge', 'status_badge', 'actions'])
            ->make(true);
    }

    /**
     * Get today's appointment counts for reception dashboard widgets.
     */
    public function getTodayAppointmentCounts()
    {
        $today = Carbon::today();

        $counts = [
            'scheduled'       => DoctorAppointment::whereDate('appointment_date', $today)->where('status', QueueStatus::SCHEDULED)->count(),
            'checked_in'      => DoctorAppointment::whereDate('appointment_date', $today)->whereIn('status', [QueueStatus::WAITING, QueueStatus::VITALS_PENDING, QueueStatus::READY])->count(),
            'in_consultation' => DoctorAppointment::whereDate('appointment_date', $today)->where('status', QueueStatus::IN_CONSULTATION)->count(),
            'completed'       => DoctorAppointment::whereDate('appointment_date', $today)->where('status', QueueStatus::COMPLETED)->count(),
            'no_show'         => DoctorAppointment::whereDate('appointment_date', $today)->where('status', QueueStatus::NO_SHOW)->count(),
            'cancelled'       => DoctorAppointment::whereDate('appointment_date', $today)->where('status', QueueStatus::CANCELLED)->count(),
            'total'           => DoctorAppointment::whereDate('appointment_date', $today)->count(),
        ];

        return response()->json($counts);
    }

    /**
     * Get appointments as FullCalendar events (JSON).
     * Accepts: start, end (date range), clinic_id, doctor_id, status
     */
    public function getCalendarEvents(Request $request)
    {
        $query = DoctorAppointment::with(['patient.user', 'clinic', 'doctor.user'])
            ->orderBy('appointment_date')
            ->orderBy('start_time');

        if ($request->filled('start')) {
            $query->where('appointment_date', '>=', Carbon::parse($request->start)->toDateString());
        }
        if ($request->filled('end')) {
            $query->where('appointment_date', '<=', Carbon::parse($request->end)->toDateString());
        }
        if ($request->filled('clinic_id')) {
            $query->where('clinic_id', $request->clinic_id);
        }
        if ($request->filled('doctor_id')) {
            $query->where('staff_id', $request->doctor_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $statusColors = QueueStatus::COLORS;

        $events = $query->get()->map(function ($appt) use ($statusColors) {
            $patientName = $appt->patient ? userfullname($appt->patient->user_id) : 'N/A';
            $fileNo = $appt->patient->file_no ?? '';
            $phone = $appt->patient->phone_no ?? '';
            $doctor = $appt->doctor ? userfullname($appt->doctor->user_id) : 'Any';
            $clinic = $appt->clinic->name ?? '';
            $color = $statusColors[$appt->status] ?? '#6c757d';
            $statusLabel = QueueStatus::label($appt->status);

            return [
                'id'              => $appt->id,
                'title'           => $patientName,
                'start'           => $appt->appointment_date . 'T' . $appt->start_time,
                'end'             => $appt->appointment_date . 'T' . ($appt->end_time ?? $appt->start_time),
                'color'           => $color,
                'textColor'       => '#fff',
                'borderColor'     => $color,
                'className'       => 'appt-event appt-status-' . $appt->status,
                // Extra data for popover/context menu
                'appointment_id'  => $appt->id,
                'patient_name'    => $patientName,
                'file_no'         => $fileNo,
                'phone'           => $phone,
                'doctor'          => $doctor,
                'clinic'          => $clinic,
                'status'          => $appt->status,
                'status_label'    => $statusLabel,
                'appointment_type' => $appt->appointment_type ?? 'scheduled',
                'priority'        => $appt->priority ?? 'routine',
                'clinic_id'       => $appt->clinic_id,
                'doctor_id'       => $appt->staff_id,
                'reschedule_count' => $appt->reschedule_count ?? 0,
                'is_follow_up'    => $appt->appointment_type === 'follow_up',
            ];
        });

        return response()->json($events);
    }

    // ─── CRUD & Actions ────────────────────────────────────────────────

    /**
     * Create a new appointment (reception).
     */
    public function createAppointment(Request $request)
    {
        $request->validate([
            'patient_id'       => 'required|exists:patients,id',
            'clinic_id'        => 'required|exists:clinics,id',
            'doctor_id'        => 'nullable|exists:staff,id',
            'appointment_date' => 'required|date|after_or_equal:today',
            'start_time'       => 'required|date_format:H:i',
            'end_time'         => 'nullable|date_format:H:i|after:start_time',
            'appointment_type' => 'nullable|in:scheduled,follow_up,referral',
            'service_id'       => 'nullable|exists:services,id',
            'priority'         => 'nullable|in:routine,urgent,emergency',
            'notes'            => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            // Calculate end_time from default slot duration if not provided
            $startTime = Carbon::parse($request->start_time);
            $endTime = $request->end_time
                ? Carbon::parse($request->end_time)
                : $startTime->copy()->addMinutes((int) appsettings('default_slot_duration', 15));

            // Check slot availability
            if ($request->doctor_id) {
                $isAvailable = $this->slotService->isSlotAvailable(
                    $request->clinic_id,
                    Carbon::parse($request->appointment_date),
                    $request->start_time,
                    $request->doctor_id
                );

                if (!$isAvailable) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The selected time slot is no longer available.',
                    ], 422);
                }
            }

            $bookedByStaff = Staff::where('user_id', Auth::id())->first();

            $appointment = DoctorAppointment::create([
                'patient_id'       => $request->patient_id,
                'clinic_id'        => $request->clinic_id,
                'staff_id'         => $request->doctor_id,
                'appointment_date' => $request->appointment_date,
                'start_time'       => $request->start_time,
                'end_time'         => $endTime->format('H:i'),
                'appointment_type' => $request->appointment_type ?? 'scheduled',
                'status'           => QueueStatus::SCHEDULED,
                'priority'         => $request->priority ?? 'routine',
                'booked_by'        => $bookedByStaff ? $bookedByStaff->id : null,
                'notes'            => $request->notes,
                'source'           => 'reception',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Appointment scheduled successfully.',
                'appointment' => $appointment->load('patient.user', 'clinic', 'doctor.user'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating appointment', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to schedule appointment: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update an existing appointment.
     */
    public function updateAppointment(Request $request, DoctorAppointment $appointment)
    {
        $request->validate([
            'appointment_date' => 'nullable|date|after_or_equal:today',
            'start_time'       => 'nullable|date_format:H:i',
            'end_time'         => 'nullable|date_format:H:i|after:start_time',
            'doctor_id'        => 'nullable|exists:staff,id',
            'priority'         => 'nullable|in:routine,urgent,emergency',
            'notes'            => 'nullable|string|max:1000',
        ]);

        if (!in_array($appointment->status, [QueueStatus::SCHEDULED])) {
            return response()->json(['success' => false, 'message' => 'Only scheduled appointments can be updated.'], 422);
        }

        try {
            $updateData = $request->only(['appointment_date', 'start_time', 'end_time', 'priority', 'notes']);
            if ($request->has('doctor_id')) {
                $updateData['staff_id'] = $request->doctor_id;
            }
            $appointment->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Appointment updated.',
                'appointment' => $appointment->fresh()->load('patient.user', 'clinic', 'doctor.user'),
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating appointment', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to update appointment.'], 500);
        }
    }

    // ─── Check-In ──────────────────────────────────────────────────────

    /**
     * Check in a scheduled appointment — creates queue entry and service request.
     */
    public function checkIn(DoctorAppointment $appointment)
    {
        if ($appointment->status !== QueueStatus::SCHEDULED) {
            return response()->json(['success' => false, 'message' => 'Only scheduled appointments can be checked in.'], 422);
        }

        try {
            DB::beginTransaction();

            $patient = Patient::find($appointment->patient_id);

            // Create ProductOrServiceRequest (unless pre-paid follow-up)
            $serviceRequest = null;
            if ($appointment->is_prepaid_followup && $appointment->service_request_id) {
                // Re-use parent's service request
                $serviceRequest = ProductOrServiceRequest::find($appointment->service_request_id);
            }

            if (!$serviceRequest) {
                $serviceRequest = new ProductOrServiceRequest();
                $serviceRequest->service_id = $appointment->service_id ?? $this->getDefaultConsultationServiceId($appointment->clinic_id);
                $serviceRequest->user_id = $patient->user_id;
                $serviceRequest->staff_user_id = Auth::id();

                // Apply HMO tariff if applicable
                if ($patient->hmo_id && $patient->hmo_id > 1) {
                    try {
                        $hmoData = HmoHelper::applyHmoTariff($patient->id, null, $serviceRequest->service_id);
                        if ($hmoData) {
                            $serviceRequest->payable_amount = $hmoData['payable_amount'];
                            $serviceRequest->claims_amount = $hmoData['claims_amount'];
                            $serviceRequest->coverage_mode = $hmoData['coverage_mode'];
                            $serviceRequest->validation_status = $hmoData['validation_status'];
                        }
                    } catch (\Exception $e) {
                        Log::warning('HMO tariff not found during check-in', [
                            'patient_id' => $patient->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                $serviceRequest->save();
                $appointment->service_request_id = $serviceRequest->id;
            }

            // Create DoctorQueue entry
            $queue = DoctorQueue::create([
                'patient_id'       => $appointment->patient_id,
                'clinic_id'        => $appointment->clinic_id,
                'staff_id'         => $appointment->staff_id,
                'receptionist_id'  => Auth::id(),
                'request_entry_id' => $serviceRequest->id,
                'appointment_id'   => $appointment->id,
                'status'           => QueueStatus::WAITING,
                'priority'         => $appointment->priority ?? 'routine',
                'source'           => 'appointment',
            ]);

            // Update appointment
            $appointment->update([
                'status'        => QueueStatus::WAITING,
                'checked_in_at' => now(),
                'doctor_queue_id' => $queue->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Patient checked in successfully.',
                'queue_id' => $queue->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error checking in appointment', ['error' => $e->getMessage(), 'appointment_id' => $appointment->id]);
            return response()->json(['success' => false, 'message' => 'Check-in failed: ' . $e->getMessage()], 500);
        }
    }

    // ─── Cancel / No-Show ──────────────────────────────────────────────

    /**
     * Cancel a scheduled appointment.
     */
    public function cancel(Request $request, DoctorAppointment $appointment)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        if (in_array($appointment->status, [QueueStatus::COMPLETED, QueueStatus::CANCELLED])) {
            return response()->json(['success' => false, 'message' => 'This appointment cannot be cancelled.'], 422);
        }

        try {
            DB::beginTransaction();

            $appointment->update([
                'status'              => QueueStatus::CANCELLED,
                'cancellation_reason' => $request->reason ?? 'Cancelled by staff',
                'cancelled_at'        => now(),
            ]);

            // Cancel linked queue entry if exists
            if ($appointment->doctor_queue_id) {
                DoctorQueue::where('id', $appointment->doctor_queue_id)->update([
                    'status' => QueueStatus::CANCELLED,
                ]);
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Appointment cancelled.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to cancel: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Mark appointment as no-show.
     */
    public function markNoShow(DoctorAppointment $appointment)
    {
        if ($appointment->status !== QueueStatus::SCHEDULED) {
            return response()->json(['success' => false, 'message' => 'Only scheduled appointments can be marked as no-show.'], 422);
        }

        $appointment->update([
            'status'           => QueueStatus::NO_SHOW,
            'no_show_marked_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Marked as no-show.']);
    }

    // ─── Reschedule ────────────────────────────────────────────────────

    /**
     * Reschedule an appointment — creates a new appointment linked to the original.
     */
    public function reschedule(Request $request, DoctorAppointment $appointment)
    {
        $request->validate([
            'appointment_date' => 'required|date|after_or_equal:today',
            'start_time'       => 'required|date_format:H:i',
            'end_time'         => 'nullable|date_format:H:i|after:start_time',
            'doctor_id'        => 'nullable|exists:staff,id',
            'reason'           => 'nullable|string|max:500',
        ]);

        if (!in_array($appointment->status, [QueueStatus::SCHEDULED, QueueStatus::CANCELLED, QueueStatus::NO_SHOW])) {
            return response()->json(['success' => false, 'message' => 'This appointment cannot be rescheduled.'], 422);
        }

        $maxReschedules = (int) appsettings('max_reschedule_count', 3);
        if ($appointment->reschedule_count >= $maxReschedules) {
            return response()->json([
                'success' => false,
                'message' => "Maximum reschedule limit ({$maxReschedules}) reached.",
            ], 422);
        }

        try {
            DB::beginTransaction();

            $startTime = Carbon::parse($request->start_time);
            $endTime = $request->end_time
                ? Carbon::parse($request->end_time)
                : $startTime->copy()->addMinutes((int) appsettings('default_slot_duration', 15));

            $rescheduleBookedBy = Staff::where('user_id', Auth::id())->first();

            // Create new appointment from old
            $newAppointment = DoctorAppointment::create([
                'patient_id'          => $appointment->patient_id,
                'clinic_id'           => $appointment->clinic_id,
                'staff_id'            => $request->doctor_id ?? $appointment->staff_id,
                'appointment_date'    => $request->appointment_date,
                'start_time'          => $request->start_time,
                'end_time'            => $endTime->format('H:i'),
                'appointment_type'    => $appointment->appointment_type,
                'status'              => QueueStatus::SCHEDULED,
                'priority'            => $appointment->priority,
                'booked_by'           => $rescheduleBookedBy ? $rescheduleBookedBy->id : null,
                'source'              => $appointment->source,
                'notes'               => $appointment->notes,
                'rescheduled_from_id' => $appointment->id,
                'reschedule_count'    => $appointment->reschedule_count + 1,
                'is_prepaid_followup' => $appointment->is_prepaid_followup,
                'service_request_id'  => $appointment->is_prepaid_followup ? $appointment->service_request_id : null,
                'parent_appointment_id' => $appointment->parent_appointment_id,
            ]);

            // Cancel original
            $appointment->update([
                'status'              => QueueStatus::CANCELLED,
                'cancellation_reason' => 'Rescheduled to ' . $request->appointment_date . '. ' . ($request->reason ?? ''),
                'cancelled_at'        => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Appointment rescheduled successfully.',
                'appointment' => $newAppointment->load('patient.user', 'clinic', 'doctor.user'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error rescheduling appointment', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Reschedule failed: ' . $e->getMessage()], 500);
        }
    }

    // ─── Follow-Up ─────────────────────────────────────────────────────

    /**
     * Schedule a follow-up appointment from an encounter.
     */
    public function scheduleFollowUp(Request $request, Encounter $encounter)
    {
        $request->validate([
            'appointment_date' => 'required|date|after_or_equal:today',
            'start_time'       => 'required|date_format:H:i',
            'end_time'         => 'nullable|date_format:H:i|after:start_time',
            'clinic_id'        => 'nullable|exists:clinics,id',
            'doctor_id'        => 'nullable|exists:staff,id',
            'is_prepaid'       => 'nullable|boolean',
            'reason'           => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $doctor = Staff::where('user_id', Auth::id())->first();

            // Find the parent appointment (if any) from the encounter's queue
            $parentAppointment = null;
            if ($encounter->queue && $encounter->queue->appointment_id) {
                $parentAppointment = DoctorAppointment::find($encounter->queue->appointment_id);
            }

            $startTime = Carbon::parse($request->start_time);
            $endTime = $request->end_time
                ? Carbon::parse($request->end_time)
                : $startTime->copy()->addMinutes((int) appsettings('default_slot_duration', 15));

            $isPrepaid = $request->boolean('is_prepaid', false);

            $appointment = DoctorAppointment::create([
                'patient_id'            => $encounter->patient_id,
                'clinic_id'             => $request->clinic_id ?? $doctor->clinic_id,
                'staff_id'              => $request->doctor_id ?? $doctor->id,
                'appointment_date'      => $request->appointment_date,
                'start_time'            => $request->start_time,
                'end_time'              => $endTime->format('H:i'),
                'appointment_type'      => 'follow_up',
                'status'                => QueueStatus::SCHEDULED,
                'priority'              => 'routine',
                'booked_by'             => $doctor->id,
                'source'                => 'follow_up',
                'notes'                 => $request->reason,
                'parent_appointment_id' => $parentAppointment?->id,
                'is_prepaid_followup'   => $isPrepaid,
                'service_request_id'    => $isPrepaid && $parentAppointment ? $parentAppointment->service_request_id : null,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Follow-up scheduled for ' . Carbon::parse($request->appointment_date)->format('M d, Y') . '.',
                'appointment' => $appointment->load('patient.user', 'clinic', 'doctor.user'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error scheduling follow-up', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to schedule follow-up: ' . $e->getMessage()], 500);
        }
    }

    // ─── Reassignment ──────────────────────────────────────────────────

    /**
     * Reassign appointment to a different doctor.
     */
    public function reassignDoctor(Request $request, DoctorAppointment $appointment)
    {
        $request->validate([
            'doctor_id' => 'required|exists:staff,id',
            'reason'    => 'nullable|string|max:500',
        ]);

        if ($appointment->status !== QueueStatus::SCHEDULED) {
            return response()->json(['success' => false, 'message' => 'Only scheduled appointments can be reassigned.'], 422);
        }

        try {
            $originalDoctorId = $appointment->staff_id;

            $appointment->update([
                'staff_id'            => $request->doctor_id,
                'original_staff_id'   => $appointment->original_staff_id ?? $originalDoctorId,
                'reassignment_reason' => $request->reason,
                'reassigned_at'       => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Doctor reassigned successfully.',
                'appointment' => $appointment->fresh()->load('patient.user', 'clinic', 'doctor.user'),
            ]);
        } catch (\Exception $e) {
            Log::error('Error reassigning doctor', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Reassignment failed: ' . $e->getMessage()], 500);
        }
    }

    // ─── Availability & Slots ──────────────────────────────────────────

    /**
     * Get available time slots for a clinic/doctor on a specific date.
     */
    public function getAvailableSlots(Request $request)
    {
        $request->validate([
            'clinic_id' => 'required|exists:clinics,id',
            'date'      => 'required|date',
            'doctor_id' => 'nullable|exists:staff,id',
        ]);

        $date = Carbon::parse($request->date);
        $slots = $this->slotService->getAvailableSlots(
            $request->clinic_id,
            $date,
            $request->doctor_id
        );

        return response()->json([
            'success' => true,
            'slots'   => $slots,
            'date'    => $date->toDateString(),
        ]);
    }

    /**
     * Get available doctors for a specific appointment slot.
     */
    public function getAvailableDoctors(Request $request, DoctorAppointment $appointment)
    {
        $doctors = Staff::where('clinic_id', $appointment->clinic_id)
            ->whereHas('user', function ($q) {
                $q->where('is_active', 1);
            })
            ->get()
            ->filter(function ($doctor) use ($appointment) {
                return $this->slotService->isSlotAvailable(
                    $appointment->clinic_id,
                    Carbon::parse($appointment->appointment_date),
                    $appointment->start_time,
                    $doctor->id
                );
            })
            ->map(function ($doctor) {
                return [
                    'id'   => $doctor->id,
                    'name' => userfullname($doctor->user_id),
                ];
            })
            ->values();

        return response()->json(['success' => true, 'doctors' => $doctors]);
    }

    // ─── Chain / History ───────────────────────────────────────────────

    /**
     * Get the full appointment chain (parent → follow-ups, reschedule history).
     */
    public function getAppointmentChain(DoctorAppointment $appointment)
    {
        // Build chain: find root appointment
        $root = $appointment;
        while ($root->parent_appointment_id || $root->rescheduled_from_id) {
            $parentId = $root->parent_appointment_id ?? $root->rescheduled_from_id;
            $parent = DoctorAppointment::find($parentId);
            if (!$parent) break;
            $root = $parent;
        }

        // Get all descendants
        $chain = $this->buildChain($root);

        return response()->json(['success' => true, 'chain' => $chain]);
    }

    /**
     * Recursively build chain from root appointment.
     */
    private function buildChain(DoctorAppointment $appointment): array
    {
        $data = [
            'id'               => $appointment->id,
            'date'             => $appointment->appointment_date,
            'start_time'       => $appointment->start_time,
            'status'           => $appointment->status,
            'status_label'     => QueueStatus::label($appointment->status),
            'type'             => $appointment->appointment_type,
            'doctor'           => $appointment->doctor ? userfullname($appointment->doctor->user_id) : 'Any',
            'clinic'           => $appointment->clinic->name ?? 'N/A',
            'follow_ups'       => [],
            'rescheduled_to'   => null,
        ];

        // Follow-ups
        $followUps = DoctorAppointment::where('parent_appointment_id', $appointment->id)->orderBy('appointment_date')->get();
        foreach ($followUps as $fu) {
            $data['follow_ups'][] = $this->buildChain($fu);
        }

        // Rescheduled to (find via reverse relationship)
        $rescheduled = DoctorAppointment::where('rescheduled_from_id', $appointment->id)->first();
        if ($rescheduled) {
            $data['rescheduled_to'] = $this->buildChain($rescheduled);
        }

        return $data;
    }

    // ─── Doctor-Facing: My Appointments ────────────────────────────────

    /**
     * Get appointments for the logged-in doctor (for doctor queue "Scheduled" tab).
     */
    public function getDoctorAppointments(Request $request)
    {
        $doc = Staff::where('user_id', Auth::id())->first();
        if (!$doc) {
            return DataTables::of(collect([]))->make(true);
        }

        $date = $request->input('date', Carbon::today()->toDateString());

        $query = DoctorAppointment::with(['patient.user', 'patient.hmo', 'clinic'])
            ->where(function ($q) use ($doc) {
                $q->where('staff_id', $doc->id)
                  ->orWhere('clinic_id', $doc->clinic_id);
            })
            ->whereDate('appointment_date', $date)
            ->where('status', QueueStatus::SCHEDULED)
            ->orderBy('start_time', 'asc');

        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('fullname', function ($appt) {
                return $appt->patient ? userfullname($appt->patient->user_id) : 'N/A';
            })
            ->addColumn('file_no', function ($appt) {
                return $appt->patient->file_no ?? 'N/A';
            })
            ->editColumn('hmo_id', function ($appt) {
                return $appt->patient && $appt->patient->hmo ? $appt->patient->hmo->name : 'Private';
            })
            ->addColumn('time_slot', function ($appt) {
                return Carbon::parse($appt->start_time)->format('h:i A');
            })
            ->addColumn('type_badge', function ($appt) {
                $types = [
                    'follow_up' => '<span class="badge bg-info"><i class="mdi mdi-link-variant"></i> Follow-Up</span>',
                    'referral'  => '<span class="badge bg-warning text-dark"><i class="mdi mdi-account-arrow-right"></i> Referral</span>',
                ];
                return $types[$appt->appointment_type] ?? '<span class="badge bg-purple"><i class="mdi mdi-calendar-clock"></i> Scheduled</span>';
            })
            ->editColumn('clinic_id', function ($appt) {
                return $appt->clinic->name ?? 'N/A';
            })
            ->rawColumns(['type_badge'])
            ->make(true);
    }

    /**
     * Get appointment counts for the doctor's queue view.
     */
    public function getDoctorAppointmentCounts()
    {
        $doc = Staff::where('user_id', Auth::id())->first();
        if (!$doc) {
            return response()->json(['scheduled_today' => 0]);
        }

        $today = Carbon::today();
        $count = DoctorAppointment::where(function ($q) use ($doc) {
                $q->where('staff_id', $doc->id)
                  ->orWhere('clinic_id', $doc->clinic_id);
            })
            ->whereDate('appointment_date', $today)
            ->where('status', QueueStatus::SCHEDULED)
            ->count();

        return response()->json(['scheduled_today' => $count]);
    }

    // ─── Timer Endpoints ───────────────────────────────────────────────

    /**
     * Start or resume the consultation timer.
     */
    public function startTimer(DoctorQueue $queue)
    {
        if ($queue->status !== QueueStatus::IN_CONSULTATION) {
            return response()->json(['success' => false, 'message' => 'Queue must be in consultation to start timer.'], 422);
        }

        $updates = [];
        if (!$queue->consultation_started_at) {
            $updates['consultation_started_at'] = now();
        }
        if ($queue->is_paused) {
            $updates['is_paused'] = false;
            $updates['last_paused_at'] = null;
            // Add paused duration to accumulated total
            if ($queue->last_paused_at) {
                $pausedSeconds = Carbon::parse($queue->last_paused_at)->diffInSeconds(now());
                $updates['consultation_paused_seconds'] = ($queue->consultation_paused_seconds ?? 0) + $pausedSeconds;
            }
        }

        if (!empty($updates)) {
            $queue->update($updates);
        }

        return response()->json([
            'success' => true,
            'timer'   => $this->getTimerData($queue->fresh()),
        ]);
    }

    /**
     * Pause the consultation timer.
     */
    public function pauseTimer(DoctorQueue $queue)
    {
        if ($queue->status !== QueueStatus::IN_CONSULTATION || $queue->is_paused) {
            return response()->json(['success' => false, 'message' => 'Cannot pause.'], 422);
        }

        $queue->update([
            'is_paused' => true,
            'last_paused_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'timer'   => $this->getTimerData($queue->fresh()),
        ]);
    }

    /**
     * Get current timer status.
     */
    public function getTimerStatus(DoctorQueue $queue)
    {
        return response()->json([
            'success' => true,
            'timer'   => $this->getTimerData($queue),
        ]);
    }

    /**
     * Build timer data response.
     */
    private function getTimerData(DoctorQueue $queue): array
    {
        $started = $queue->consultation_started_at;
        $elapsedSeconds = 0;

        if ($started) {
            $elapsedSeconds = Carbon::parse($started)->diffInSeconds(now());
            $elapsedSeconds -= ($queue->consultation_paused_seconds ?? 0);

            if ($queue->is_paused && $queue->last_paused_at) {
                $currentPause = Carbon::parse($queue->last_paused_at)->diffInSeconds(now());
                $elapsedSeconds -= $currentPause;
            }
        }

        $elapsedSeconds = max(0, $elapsedSeconds);

        return [
            'started_at'       => $started ? Carbon::parse($started)->toISOString() : null,
            'is_paused'        => (bool) $queue->is_paused,
            'elapsed_seconds'  => $elapsedSeconds,
            'elapsed_display'  => gmdate('H:i:s', $elapsedSeconds),
            'status'           => $queue->status,
            'status_label'     => QueueStatus::label($queue->status),
        ];
    }

    // ─── Doctor Queue Counts (enhanced) ────────────────────────────────

    /**
     * Get queue counts for the doctor's view with status breakdown.
     */
    public function getDoctorQueueCounts()
    {
        $doc = Staff::where('user_id', Auth::id())->first();
        if (!$doc) {
            return response()->json([]);
        }

        $today = Carbon::today();

        $baseQuery = function () use ($doc, $today) {
            return DoctorQueue::where(function ($q) use ($doc) {
                $q->where('clinic_id', $doc->clinic_id)
                  ->orWhere('staff_id', $doc->id);
            })->whereDate('created_at', $today);
        };

        $counts = [
            'new'            => $baseQuery()->where('status', QueueStatus::WAITING)->count(),
            'vitals_pending' => $baseQuery()->where('status', QueueStatus::VITALS_PENDING)->count(),
            'ready'          => $baseQuery()->where('status', QueueStatus::READY)->count(),
            'in_consultation' => $baseQuery()->where('status', QueueStatus::IN_CONSULTATION)->count(),
            'completed'      => $baseQuery()->where('status', QueueStatus::COMPLETED)->count(),
            'scheduled'      => DoctorAppointment::where(function ($q) use ($doc) {
                    $q->where('staff_id', $doc->id)
                      ->orWhere('clinic_id', $doc->clinic_id);
                })
                ->whereDate('appointment_date', $today)
                ->where('status', QueueStatus::SCHEDULED)
                ->count(),
        ];

        $counts['total_active'] = $counts['new'] + $counts['vitals_pending'] + $counts['ready'] + $counts['in_consultation'];

        return response()->json($counts);
    }

    // ─── Helpers ───────────────────────────────────────────────────────

    /**
     * Get the default consultation service ID for a clinic.
     */
    private function getDefaultConsultationServiceId(int $clinicId): ?int
    {
        $clinic = Clinic::find($clinicId);
        if ($clinic && $clinic->default_service_id) {
            return $clinic->default_service_id;
        }

        // Fall back to first active consultation service
        $service = \App\Models\service::where('service_name', 'LIKE', '%consultation%')
            ->first();

        return $service?->id;
    }
}
