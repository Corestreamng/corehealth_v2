<?php

namespace App\Http\Controllers;

use App\Enums\QueueStatus;
use App\Helpers\HmoHelper;
use App\Models\Clinic;
use App\Models\DoctorAppointment;
use App\Models\DoctorQueue;
use App\Models\Encounter;
use App\Models\Patient;
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
     * Compute a human-readable "next step" hint for a given status + delivery state.
     * Used by both reception and doctor views (calendar, table, context menus).
     */
    private function nextStepHint(int $status, bool $canDeliver, string $deliveryReason = '', string $eventType = 'queue'): string
    {
        if ($eventType === 'appointment' && $status == QueueStatus::SCHEDULED) {
            return 'Check in the patient to begin';
        }

        switch ($status) {
            case QueueStatus::WAITING:
                return $canDeliver
                    ? 'Patient is waiting — open encounter to begin consultation'
                    : 'Payment pending — direct patient to billing/cashier';
            case QueueStatus::VITALS_PENDING:
                return 'Vitals in progress — waiting for nurse to complete';
            case QueueStatus::READY:
                return $canDeliver
                    ? 'Vitals done — patient is ready for consultation'
                    : 'Payment still pending — direct patient to billing';
            case QueueStatus::IN_CONSULTATION:
                return 'Consultation in progress';
            case QueueStatus::COMPLETED:
                return 'Visit completed';
            case QueueStatus::CANCELLED:
                return 'Appointment was cancelled';
            case QueueStatus::NO_SHOW:
                return 'Patient did not show up';
            default:
                return '';
        }
    }

    /**
     * Get appointments as FullCalendar events (JSON).
     * Accepts: start, end (date range), clinic_id, doctor_id, status
     */
    public function getCalendarEvents(Request $request)
    {
        $startDate = $request->filled('start') ? Carbon::parse($request->start)->toDateString() : Carbon::today()->toDateString();
        $endDate   = $request->filled('end')   ? Carbon::parse($request->end)->toDateString()   : Carbon::today()->toDateString();

        $query = DoctorAppointment::with(['patient.user', 'clinic', 'doctor.user'])
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->whereBetween('appointment_date', [$startDate, $endDate]);

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
            $dateStr = $appt->appointment_date instanceof \Carbon\Carbon
                ? $appt->appointment_date->format('Y-m-d')
                : Carbon::parse($appt->appointment_date)->format('Y-m-d');

            // Delivery check for checked-in appointments (have a queue entry)
            $canDeliver = true;
            $deliveryReason = '';
            $deliveryHint = '';
            if ($appt->doctor_queue_id && in_array($appt->status, [QueueStatus::WAITING, QueueStatus::VITALS_PENDING, QueueStatus::READY, QueueStatus::IN_CONSULTATION])) {
                $queueEntry = DoctorQueue::find($appt->doctor_queue_id);
                if ($queueEntry && $queueEntry->request_entry_id) {
                    $reqEntry = ProductOrServiceRequest::find($queueEntry->request_entry_id);
                    $deliveryCheck = $reqEntry ? HmoHelper::canDeliverService($reqEntry) : ['can_deliver' => true, 'reason' => '', 'hint' => ''];
                    $canDeliver = $deliveryCheck['can_deliver'] ?? true;
                    $deliveryReason = $deliveryCheck['reason'] ?? '';
                    $deliveryHint = $deliveryCheck['hint'] ?? '';
                }
            }

            $nextStep = $this->nextStepHint($appt->status, $canDeliver, $deliveryReason, 'appointment');

            return [
                'id'              => 'appt-' . $appt->id,
                'title'           => $patientName,
                'start'           => $dateStr . 'T' . $appt->start_time,
                'end'             => $dateStr . 'T' . ($appt->end_time ?? $appt->start_time),
                'color'           => $color,
                'textColor'       => '#fff',
                'borderColor'     => $color,
                'className'       => 'appt-event appt-status-' . $appt->status,
                // Extra data for popover/context menu
                'event_type'      => 'appointment',
                'record_id'       => $appt->id,
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
                'queue_id'        => $appt->doctor_queue_id,
                'can_deliver'     => $canDeliver,
                'delivery_reason' => $deliveryReason,
                'delivery_hint'   => $deliveryHint,
                'next_step'       => $nextStep,
            ];
        });
        $events = collect($events->all());  // force base Support\Collection — Eloquent\Collection::merge() calls getKey()

        // ── Optionally merge live queue entries ──────────────────────
        if ($request->boolean('include_queue')) {
            $linkedQueueIds = $query->get()->pluck('doctor_queue_id')->filter()->toArray();

            $queueQuery = DoctorQueue::with(['patient.user', 'patient.hmo'])
                ->whereBetween('created_at', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay(),
                ])
                ->whereNotIn('id', $linkedQueueIds);

            if ($request->filled('clinic_id')) {
                $queueQuery->where('clinic_id', $request->clinic_id);
            }
            if ($request->filled('doctor_id')) {
                $queueQuery->where('staff_id', $request->doctor_id);
            }
            if ($request->filled('status')) {
                $queueQuery->where('status', $request->status);
            }

            $queueEvents = $queueQuery->orderBy('created_at')->get()->map(function ($queue) use ($statusColors) {
                $patientName = $queue->patient ? userfullname($queue->patient->user_id) : 'N/A';
                $fileNo  = $queue->patient->file_no ?? '';
                $phone   = $queue->patient->phone_no ?? '';
                $doctor  = $queue->staff_id ? userfullname(Staff::find($queue->staff_id)->user_id ?? 0) : 'Unassigned';
                $clinic  = Clinic::find($queue->clinic_id)->name ?? '';
                $color   = $statusColors[$queue->status] ?? '#6c757d';

                $startTime = Carbon::parse($queue->created_at);
                $endTime   = $startTime->copy()->addMinutes((int) (appsettings('default_slot_duration') ?? 15));

                // Delivery check for queue entries
                $canDeliver = true;
                $deliveryReason = '';
                $deliveryHint = '';
                if ($queue->request_entry_id && in_array($queue->status, [QueueStatus::WAITING, QueueStatus::VITALS_PENDING, QueueStatus::READY, QueueStatus::IN_CONSULTATION])) {
                    $reqEntry = ProductOrServiceRequest::find($queue->request_entry_id);
                    $deliveryCheck = $reqEntry ? HmoHelper::canDeliverService($reqEntry) : ['can_deliver' => true, 'reason' => '', 'hint' => ''];
                    $canDeliver = $deliveryCheck['can_deliver'] ?? true;
                    $deliveryReason = $deliveryCheck['reason'] ?? '';
                    $deliveryHint = $deliveryCheck['hint'] ?? '';
                }

                $nextStep = $this->nextStepHint($queue->status, $canDeliver, $deliveryReason, 'queue');

                return [
                    'id'              => 'queue-' . $queue->id,
                    'title'           => $patientName,
                    'start'           => $startTime->toIso8601String(),
                    'end'             => $endTime->toIso8601String(),
                    'color'           => $color,
                    'textColor'       => '#fff',
                    'borderColor'     => ($queue->priority === 'emergency') ? '#dc3545' : $color,
                    'className'       => 'appt-event appt-status-' . $queue->status . ' queue-event',
                    'event_type'      => 'queue',
                    'record_id'       => $queue->id,
                    'appointment_id'  => null,
                    'patient_name'    => $patientName,
                    'file_no'         => $fileNo,
                    'phone'           => $phone,
                    'doctor'          => $doctor,
                    'clinic'          => $clinic,
                    'status'          => $queue->status,
                    'status_label'    => QueueStatus::label($queue->status),
                    'appointment_type' => 'walk_in',
                    'priority'        => $queue->priority ?? 'routine',
                    'clinic_id'       => $queue->clinic_id,
                    'doctor_id'       => $queue->staff_id,
                    'reschedule_count' => 0,
                    'is_follow_up'    => false,
                    'queue_id'        => $queue->id,
                    'can_deliver'     => $canDeliver,
                    'delivery_reason' => $deliveryReason,
                    'delivery_hint'   => $deliveryHint,
                    'next_step'       => $nextStep,
                ];
            });
            $queueEvents = collect($queueEvents->all());

            $events = $events->merge($queueEvents);
        }

        return response()->json($events->values());
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
            'priority'         => 'nullable|in:routine,urgent,emergency',
            'notes'            => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            // Calculate end_time from default slot duration if not provided
            $startTime = Carbon::parse($request->start_time);
            $endTime = $request->end_time
                ? Carbon::parse($request->end_time)
                : $startTime->copy()->addMinutes((int) (appsettings('default_slot_duration') ?? 15));

            // Check slot availability (skip for custom time entries)
            if ($request->doctor_id && !$request->boolean('custom_time')) {
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

            // Double-booking conflict detection
            $conflicts = $this->slotService->detectDoubleBooking(
                $request->patient_id,
                $request->doctor_id,
                $request->clinic_id,
                $request->appointment_date,
                $request->start_time,
                $endTime->format('H:i')
            );

            if ($conflicts['has_conflict']) {
                $messages = collect($conflicts['conflicts'])->pluck('message')->implode(' | ');
                return response()->json([
                    'success'   => false,
                    'message'   => 'Double-booking conflict detected: ' . $messages,
                    'conflicts' => $conflicts['conflicts'],
                ], 422);
            }

            $bookedByStaff = Staff::where('user_id', Auth::id())->first();
            if (!$bookedByStaff) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Staff profile not found for current user.'], 422);
            }

            $appointment = DoctorAppointment::create([
                'patient_id'       => $request->patient_id,
                'clinic_id'        => $request->clinic_id,
                'staff_id'         => $request->doctor_id,
                'appointment_date' => $request->appointment_date,
                'start_time'       => $request->start_time,
                'end_time'         => $endTime->format('H:i'),
                'duration_minutes' => $startTime->diffInMinutes($endTime),
                'appointment_type' => $request->appointment_type ?? 'scheduled',
                'status'           => QueueStatus::SCHEDULED,
                'priority'         => $request->priority ?? 'routine',
                'booked_by'        => $bookedByStaff->id,
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
                $serviceRequest->service_id = $this->getDefaultConsultationServiceId($appointment->clinic_id);
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
            $receptionistStaff = Staff::where('user_id', Auth::id())->first();
            if (!$receptionistStaff) {
                DB::rollBack();
                return response()->json(['error' => 'Staff profile not found for current user.'], 422);
            }

            $queue = DoctorQueue::create([
                'patient_id'       => $appointment->patient_id,
                'clinic_id'        => $appointment->clinic_id,
                'staff_id'         => $appointment->staff_id,
                'receptionist_id'  => $receptionistStaff->id,
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

        $maxReschedules = (int) (appsettings('max_reschedule_count') ?? 3);
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
                : $startTime->copy()->addMinutes((int) (appsettings('default_slot_duration') ?? 15));

            // Double-booking conflict detection for reschedule
            $conflicts = $this->slotService->detectDoubleBooking(
                $appointment->patient_id,
                $request->doctor_id ?? $appointment->staff_id,
                $appointment->clinic_id,
                $request->appointment_date,
                $request->start_time,
                $endTime->format('H:i'),
                $appointment->id // Exclude current appointment
            );

            if ($conflicts['has_conflict']) {
                $messages = collect($conflicts['conflicts'])->pluck('message')->implode(' | ');
                return response()->json([
                    'success'   => false,
                    'message'   => 'Double-booking conflict detected: ' . $messages,
                    'conflicts' => $conflicts['conflicts'],
                ], 422);
            }

            $rescheduleBookedBy = Staff::where('user_id', Auth::id())->first();
            if (!$rescheduleBookedBy) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Staff profile not found for current user.'], 422);
            }

            // Create new appointment from old
            $newAppointment = DoctorAppointment::create([
                'patient_id'          => $appointment->patient_id,
                'clinic_id'           => $appointment->clinic_id,
                'staff_id'            => $request->doctor_id ?? $appointment->staff_id,
                'appointment_date'    => $request->appointment_date,
                'start_time'          => $request->start_time,
                'end_time'            => $endTime->format('H:i'),
                'duration_minutes'    => $startTime->diffInMinutes($endTime),
                'appointment_type'    => $appointment->appointment_type,
                'status'              => QueueStatus::SCHEDULED,
                'priority'            => $appointment->priority,
                'booked_by'           => $rescheduleBookedBy->id,
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
            'start_time'       => 'nullable|date_format:H:i',
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

            // If no start_time provided, default to clinic opening time or 09:00
            $startTime = $request->start_time
                ? Carbon::parse($request->start_time)
                : Carbon::parse('09:00');
            $endTime = $request->end_time
                ? Carbon::parse($request->end_time)
                : $startTime->copy()->addMinutes((int) (appsettings('default_slot_duration') ?? 15));

            $isPrepaid = $request->boolean('is_prepaid', false);

            $appointment = DoctorAppointment::create([
                'patient_id'            => $encounter->patient_id,
                'clinic_id'             => $request->clinic_id ?? $doctor->clinic_id,
                'staff_id'              => $request->doctor_id ?? $doctor->id,
                'appointment_date'      => $request->appointment_date,
                'start_time'            => $startTime->format('H:i'),
                'end_time'              => $endTime->format('H:i'),
                'duration_minutes'      => $startTime->diffInMinutes($endTime),
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
        // Return all doctors for the clinic — the frontend will mark the current doctor
        // Slot-availability filtering is omitted to avoid false-empty results when
        // schedules are not fully configured (slot check uses the existing appointment time)
        $doctors = Staff::where('clinic_id', $appointment->clinic_id)
            ->whereHas('user')   // ensure user record exists; no is_active column on users table
            ->get()
            ->map(function ($doctor) {
                return [
                    'id'   => $doctor->id,
                    'name' => userfullname($doctor->user_id),
                ];
            })
            ->values();

        // Fallback: if clinic has no staff, broaden to any staff with a user
        if ($doctors->isEmpty()) {
            $doctors = Staff::whereHas('user')->get()->map(fn($d) => [
                'id'   => $d->id,
                'name' => userfullname($d->user_id),
            ])->values();
        }

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
        $visited = [$appointment->id]; // prevent infinite loops
        while ($root->parent_appointment_id || $root->rescheduled_from_id) {
            $parentId = $root->parent_appointment_id ?? $root->rescheduled_from_id;
            if (in_array($parentId, $visited)) break;
            $parent = DoctorAppointment::find($parentId);
            if (!$parent) break;
            $visited[] = $parentId;
            $root = $parent;
        }

        // Flatten the tree into a chronological array for the frontend
        $flat = [];
        $this->flattenChain($root, $flat);

        return response()->json(['success' => true, 'chain' => $flat]);
    }

    /**
     * Recursively flatten appointment chain into a chronological array.
     */
    private function flattenChain(DoctorAppointment $appointment, array &$flat, int $depth = 0): void
    {
        $flat[] = [
            'id'                  => $appointment->id,
            'appointment_date'    => $appointment->appointment_date ? (
                $appointment->appointment_date instanceof \Carbon\Carbon
                    ? $appointment->appointment_date->format('Y-m-d')
                    : $appointment->appointment_date
            ) : null,
            'start_time'          => $appointment->start_time,
            'end_time'            => $appointment->end_time,
            'status'              => $appointment->status,
            'status_label'        => QueueStatus::label($appointment->status),
            'status_badge'        => QueueStatus::badge($appointment->status),
            'appointment_type'    => $appointment->appointment_type,
            'doctor_name'         => $appointment->doctor ? userfullname($appointment->doctor->user_id) : 'Any',
            'clinic'              => $appointment->clinic->name ?? 'N/A',
            'rescheduled_from_id' => $appointment->rescheduled_from_id,
            'reassignment_reason' => $appointment->reassignment_reason ?? null,
            'cancellation_reason' => $appointment->cancellation_reason ?? null,
            'depth'               => $depth,
        ];

        // Follow-ups
        $followUps = DoctorAppointment::where('parent_appointment_id', $appointment->id)
            ->orderBy('appointment_date')
            ->get();
        foreach ($followUps as $fu) {
            $this->flattenChain($fu, $flat, $depth + 1);
        }

        // Rescheduled-to
        $rescheduled = DoctorAppointment::where('rescheduled_from_id', $appointment->id)->first();
        if ($rescheduled) {
            $this->flattenChain($rescheduled, $flat, $depth);
        }
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
            ->addColumn('patient_name', function ($appt) {
                return $appt->patient ? userfullname($appt->patient->user_id) : 'N/A';
            })
            ->addColumn('file_no', function ($appt) {
                return $appt->patient->file_no ?? 'N/A';
            })
            ->editColumn('appointment_date', function ($appt) {
                return $appt->appointment_date ? Carbon::parse($appt->appointment_date)->format('M d, Y') : 'N/A';
            })
            ->addColumn('time_slot', function ($appt) {
                return Carbon::parse($appt->start_time)->format('h:i A');
            })
            ->editColumn('priority', function ($appt) {
                $badges = [
                    'routine'   => '<span class="badge bg-secondary">Routine</span>',
                    'urgent'    => '<span class="badge bg-warning text-dark">Urgent</span>',
                    'emergency' => '<span class="badge bg-danger">Emergency</span>',
                ];
                return $badges[$appt->priority] ?? '<span class="badge bg-secondary">' . ucfirst($appt->priority ?? 'routine') . '</span>';
            })
            ->addColumn('status', function ($appt) {
                return QueueStatus::badge($appt->status);
            })
            ->addColumn('clinic', function ($appt) {
                return $appt->clinic->name ?? 'N/A';
            })
            ->addColumn('reason', function ($appt) {
                return \Illuminate\Support\Str::limit($appt->reason ?? $appt->notes ?? '-', 50);
            })
            ->addColumn('action', function ($appt) {
                return '<button class="btn btn-sm btn-primary btn-view-scheduled-appt" data-id="' . $appt->id . '" title="View"><i class="mdi mdi-eye"></i></button>';
            })
            ->rawColumns(['priority', 'status', 'action'])
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
        $baseApptQuery = DoctorAppointment::where(function ($q) use ($doc) {
                $q->where('staff_id', $doc->id)
                  ->orWhere('clinic_id', $doc->clinic_id);
            })
            ->where('appointment_date', '>=', $today)
            ->where('status', QueueStatus::SCHEDULED);

        return response()->json([
            'scheduled_today'  => (clone $baseApptQuery)->whereDate('appointment_date', $today)->count(),
            'scheduled_total'  => (clone $baseApptQuery)->count(),
            'scheduled_future' => (clone $baseApptQuery)->where('appointment_date', '>', $today)->count(),
        ]);
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
            $updates['last_resumed_at'] = now();
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

    // ─── Unified Calendar Events (Queue + Appointments) ──────────────

    /**
     * Return FullCalendar-format events merging both doctor_appointments (scheduled)
     * and doctor_queues (walk-ins, active) for the logged-in doctor.
     */
    public function getUnifiedCalendarEvents(Request $request)
    {
        $doc = Staff::where('user_id', Auth::id())->first();
        if (!$doc) {
            return response()->json([]);
        }

        $startDate = $request->filled('start') ? Carbon::parse($request->start)->toDateString() : Carbon::today()->toDateString();
        $endDate   = $request->filled('end')   ? Carbon::parse($request->end)->toDateString()   : Carbon::today()->toDateString();

        $statusColors = QueueStatus::COLORS;
        $events = collect();

        // ── 1. Scheduled Appointments ──────────────────────────────────
        $appts = DoctorAppointment::with(['patient.user', 'clinic'])
            ->where(function ($q) use ($doc) {
                $q->where('staff_id', $doc->id)
                  ->orWhere('clinic_id', $doc->clinic_id);
            })
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->get();

        foreach ($appts as $appt) {
            $patientName = $appt->patient ? userfullname($appt->patient->user_id) : 'N/A';
            $fileNo  = $appt->patient->file_no ?? '';
            $phone   = $appt->patient->phone_no ?? '';
            $clinic  = $appt->clinic->name ?? '';
            $color   = $statusColors[$appt->status] ?? '#6c757d';
            $hmoName = '';
            if ($appt->patient && $appt->patient->hmo_id) {
                $hmoName = \App\Models\Hmo::find($appt->patient->hmo_id)->name ?? '';
            }
            $dateStr = $appt->appointment_date->format('Y-m-d');

            $events->push([
                'id'              => 'appt-' . $appt->id,
                'title'           => $patientName,
                'start'           => $dateStr . 'T' . $appt->start_time,
                'end'             => $dateStr . 'T' . ($appt->end_time ?? $appt->start_time),
                'color'           => $color,
                'textColor'       => '#fff',
                'borderColor'     => $color,
                'className'       => 'unified-event status-' . $appt->status,
                // Payload
                'event_type'      => 'appointment',
                'record_id'       => $appt->id,
                'patient_id'      => $appt->patient_id,
                'patient_name'    => $patientName,
                'file_no'         => $fileNo,
                'phone'           => $phone,
                'hmo'             => $hmoName,
                'clinic'          => $clinic,
                'clinic_id'       => $appt->clinic_id,
                'doctor_id'       => $appt->staff_id,
                'status'          => $appt->status,
                'status_label'    => QueueStatus::label($appt->status),
                'priority'        => $appt->priority ?? 'routine',
                'source'          => $appt->appointment_type ?? 'scheduled',
                'appointment_type' => $appt->appointment_type ?? 'scheduled',
                'reason'          => $appt->reason ?? $appt->notes ?? '',
                'queue_id'        => $appt->doctor_queue_id,
                'reschedule_count' => $appt->reschedule_count ?? 0,
                'encounter_url'   => null,
                'can_deliver'     => true,
                'delivery_reason' => 'Scheduled',
                'delivery_hint'   => 'Check in to start encounter',
                'next_step'       => $this->nextStepHint($appt->status, true, '', 'appointment'),
            ]);
        }

        // Collect appointment-linked queue IDs to avoid duplicates
        $linkedQueueIds = $appts->pluck('doctor_queue_id')->filter()->toArray();

        // ── 2. Doctor Queue entries (walk-ins & active) ────────────────
        $queues = DoctorQueue::with(['patient.user', 'patient.hmo'])
            ->where(function ($q) use ($doc) {
                $q->where('clinic_id', $doc->clinic_id)
                  ->orWhere('staff_id', $doc->id);
            })
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ])
            ->whereNotIn('id', $linkedQueueIds)
            ->orderBy('created_at')
            ->get();

        foreach ($queues as $queue) {
            $patientName = $queue->patient ? userfullname($queue->patient->user_id) : 'N/A';
            $fileNo  = $queue->patient->file_no ?? '';
            $phone   = $queue->patient->phone_no ?? '';
            $hmoName = ($queue->patient && $queue->patient->hmo) ? $queue->patient->hmo->name : '';
            $clinic  = \App\Models\Clinic::find($queue->clinic_id)->name ?? '';
            $color   = $statusColors[$queue->status] ?? '#6c757d';

            $startTime = Carbon::parse($queue->created_at);
            $endTime   = $startTime->copy()->addMinutes((int) (appsettings('default_slot_duration') ?? 15));

            // HMO delivery check — determines encounter access + shown in calendar
            $encounterUrl = null;
            $canDeliver = true;
            $deliveryReason = 'Ready';
            $deliveryHint = 'Service is ready for delivery.';

            if (in_array($queue->status, [QueueStatus::WAITING, QueueStatus::VITALS_PENDING, QueueStatus::READY, QueueStatus::IN_CONSULTATION])) {
                $reqEntry = ProductOrServiceRequest::find($queue->request_entry_id);
                $deliveryCheck = $reqEntry ? HmoHelper::canDeliverService($reqEntry) : ['can_deliver' => true, 'reason' => 'Ready', 'hint' => 'No service request linked.'];
                $canDeliver = $deliveryCheck['can_deliver'] ?? true;
                $deliveryReason = $deliveryCheck['reason'] ?? 'Ready';
                $deliveryHint = $deliveryCheck['hint'] ?? '';

                if ($canDeliver) {
                    $encounterUrl = route('encounters.create', [
                        'patient_id'  => $queue->patient_id,
                        'req_entry_id' => $queue->request_entry_id,
                        'queue_id'    => $queue->id,
                    ]);
                }
            }

            $events->push([
                'id'              => 'queue-' . $queue->id,
                'title'           => $patientName,
                'start'           => $startTime->toIso8601String(),
                'end'             => $endTime->toIso8601String(),
                'color'           => $color,
                'textColor'       => '#fff',
                'borderColor'     => ($queue->source === 'emergency' || $queue->priority === 'emergency') ? '#dc3545' : $color,
                'className'       => 'unified-event status-' . $queue->status . ($queue->priority === 'emergency' ? ' event-emergency' : ''),
                // Payload
                'event_type'      => 'queue',
                'record_id'       => $queue->id,
                'patient_id'      => $queue->patient_id,
                'patient_name'    => $patientName,
                'file_no'         => $fileNo,
                'phone'           => $phone,
                'hmo'             => $hmoName,
                'clinic'          => $clinic,
                'clinic_id'       => $queue->clinic_id,
                'doctor_id'       => $queue->staff_id,
                'status'          => $queue->status,
                'status_label'    => QueueStatus::label($queue->status),
                'priority'        => $queue->priority ?? 'routine',
                'source'          => $queue->source ?? 'walk_in',
                'appointment_type' => $queue->source ?? 'walk_in',
                'reason'          => $queue->triage_note ?? '',
                'queue_id'        => $queue->id,
                'encounter_url'   => $encounterUrl,
                'can_deliver'     => $canDeliver,
                'delivery_reason' => $deliveryReason,
                'delivery_hint'   => $deliveryHint,
                'next_step'       => $this->nextStepHint($queue->status, $canDeliver, $deliveryReason, 'queue'),
                'timer'           => ($queue->status == QueueStatus::IN_CONSULTATION && $queue->consultation_started_at) ? [
                    'started_at'       => Carbon::parse($queue->consultation_started_at)->toIso8601String(),
                    'paused_seconds'   => $queue->consultation_paused_seconds ?? 0,
                    'is_paused'        => (bool) $queue->is_paused,
                    'last_paused_at'   => $queue->last_paused_at ? Carbon::parse($queue->last_paused_at)->toIso8601String() : null,
                ] : null,
            ]);
        }

        // Apply status filter if provided
        if ($request->filled('status') && $request->status !== '') {
            $filterStatus = (int) $request->status;
            $events = $events->filter(fn($e) => $e['status'] === $filterStatus);
        }

        return response()->json($events->values());
    }

    // ─── Unified Queue DataTable ───────────────────────────────────────

    /**
     * Return a merged DataTable of doctor_appointments + doctor_queues
     * for the unified table view — all active + scheduled entries.
     */
    public function getUnifiedQueueList(Request $request)
    {
        $doc = Staff::where('user_id', Auth::id())->first();
        if (!$doc) {
            return DataTables::of(collect([]))->make(true);
        }

        $startDate = $request->input('start_date', Carbon::today()->toDateString());
        $endDate   = $request->input('end_date', Carbon::today()->toDateString());
        $statusFilter = $request->input('status_filter', '');

        $rows = collect();

        // ── 1. Scheduled Appointments (include all upcoming, not just today) ──
        $appts = DoctorAppointment::with(['patient.user', 'patient.hmo', 'clinic'])
            ->where(function ($q) use ($doc) {
                $q->where('staff_id', $doc->id)
                  ->orWhere('clinic_id', $doc->clinic_id);
            })
            ->where('status', QueueStatus::SCHEDULED)
            ->where('appointment_date', '>=', $startDate)
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->get();

        foreach ($appts as $appt) {
            $patientName = $appt->patient ? userfullname($appt->patient->user_id) : 'N/A';
            $hmoName = '';
            if ($appt->patient && $appt->patient->hmo_id) {
                $hmoName = \App\Models\Hmo::find($appt->patient->hmo_id)->name ?? '';
            }

            // Appointments don't have a service request yet — delivery N/A
            $rows->push([
                'event_type'       => 'appointment',
                'record_id'        => $appt->id,
                'patient_name'     => $patientName,
                'file_no'          => $appt->patient->file_no ?? 'N/A',
                'hmo'              => $hmoName,
                'clinic'           => $appt->clinic->name ?? 'N/A',
                'status'           => $appt->status,
                'status_badge'     => QueueStatus::badge($appt->status),
                'priority'         => $appt->priority ?? 'routine',
                'source'           => $appt->appointment_type ?? 'scheduled',
                'is_follow_up'     => $appt->parent_appointment_id !== null,
                'is_prepaid'       => (bool) $appt->is_prepaid_followup,
                'time'             => $appt->start_time ? Carbon::parse($appt->start_time)->format('h:i A') : '-',
                'appointment_date' => $appt->appointment_date ? $appt->appointment_date->format('Y-m-d') : null,
                'is_future'        => $appt->appointment_date && $appt->appointment_date->gt(Carbon::today()),
                'sort_time'        => $appt->appointment_date . ' ' . $appt->start_time,
                'reason'           => \Illuminate\Support\Str::limit($appt->reason ?? $appt->notes ?? '-', 50),
                'patient_id'       => $appt->patient_id,
                'clinic_id'        => $appt->clinic_id,
                'doctor_id'        => $appt->staff_id,
                'queue_id'         => $appt->doctor_queue_id,
                'encounter_url'    => null,
                'can_deliver'      => true,
                'delivery_reason'  => 'Scheduled',
                'delivery_hint'    => 'Check in to start encounter',
                'next_step'        => $this->nextStepHint($appt->status, true, '', 'appointment'),
            ]);
        }

        // Collect linked queue IDs
        $linkedQueueIds = $appts->pluck('doctor_queue_id')->filter()->toArray();

        // ── 2. Doctor Queue entries ────────────────────────────────────
        $queues = DoctorQueue::where(function ($q) use ($doc) {
                $q->where('clinic_id', $doc->clinic_id)
                  ->orWhere('staff_id', $doc->id);
            })
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ])
            ->whereNotIn('id', $linkedQueueIds)
            ->whereNotIn('status', [QueueStatus::COMPLETED, QueueStatus::CANCELLED, QueueStatus::NO_SHOW])
            ->orderByRaw("CASE IFNULL(priority,'routine') WHEN 'emergency' THEN 1 WHEN 'urgent' THEN 2 WHEN 'routine' THEN 3 ELSE 4 END ASC")
            ->orderBy('created_at', 'DESC')
            ->get();

        foreach ($queues as $queue) {
            $patient = Patient::find($queue->patient_id);
            $patientName = $patient ? userfullname($patient->user_id) : 'N/A';
            $hmoName = ($patient && $patient->hmo_id) ? (\App\Models\Hmo::find($patient->hmo_id)->name ?? '') : '';
            $clinic  = \App\Models\Clinic::find($queue->clinic_id)->name ?? '';

            // HMO delivery check — determines encounter access + shown in table
            $encounterUrl = null;
            $canDeliver = true;
            $deliveryReason = 'Ready';
            $deliveryHint = 'Service is ready for delivery.';

            if (in_array($queue->status, [QueueStatus::WAITING, QueueStatus::VITALS_PENDING, QueueStatus::READY, QueueStatus::IN_CONSULTATION])) {
                $reqEntry = ProductOrServiceRequest::find($queue->request_entry_id);
                $deliveryCheck = $reqEntry ? HmoHelper::canDeliverService($reqEntry) : ['can_deliver' => true, 'reason' => 'Ready', 'hint' => 'No service request linked.'];
                $canDeliver = $deliveryCheck['can_deliver'] ?? true;
                $deliveryReason = $deliveryCheck['reason'] ?? 'Ready';
                $deliveryHint = $deliveryCheck['hint'] ?? '';

                if ($canDeliver) {
                    $encounterUrl = route('encounters.create', [
                        'patient_id'  => $queue->patient_id,
                        'req_entry_id' => $queue->request_entry_id,
                        'queue_id'    => $queue->id,
                    ]);
                }
            }

            // Status badge with timer (use ISO 8601 format for reliable JS Date parsing)
            $statusBadge = QueueStatus::badge($queue->status);
            if ($queue->status == QueueStatus::IN_CONSULTATION && $queue->consultation_started_at) {
                $startedIso = Carbon::parse($queue->consultation_started_at)->toIso8601String();
                $pausedAtIso = $queue->last_paused_at ? Carbon::parse($queue->last_paused_at)->toIso8601String() : '';
                $statusBadge .= ' <span class="badge bg-success-subtle text-success mini-timer" data-started="' . $startedIso . '" data-paused-seconds="' . ($queue->consultation_paused_seconds ?? 0) . '" data-is-paused="' . ($queue->is_paused ? '1' : '0') . '" data-last-paused-at="' . $pausedAtIso . '"><i class="mdi mdi-timer"></i> <span class="timer-value">00:00:00</span></span>';
            }

            $rows->push([
                'event_type'       => 'queue',
                'record_id'        => $queue->id,
                'patient_name'     => $patientName,
                'file_no'          => $patient->file_no ?? 'N/A',
                'hmo'              => $hmoName,
                'clinic'           => $clinic,
                'status'           => $queue->status,
                'status_badge'     => $statusBadge,
                'priority'         => $queue->priority ?? 'routine',
                'source'           => $queue->source ?? 'walk_in',
                'is_follow_up'     => $queue->source === 'follow_up',
                'is_prepaid'       => false,
                'time'             => Carbon::parse($queue->created_at)->format('h:i A'),
                'appointment_date' => null,
                'is_future'        => false,
                'sort_time'        => $queue->created_at,
                'reason'           => \Illuminate\Support\Str::limit($queue->triage_note ?? '-', 50),
                'patient_id'       => $queue->patient_id,
                'clinic_id'        => $queue->clinic_id,
                'doctor_id'        => $queue->staff_id,
                'queue_id'         => $queue->id,
                'encounter_url'    => $encounterUrl,
                'can_deliver'      => $canDeliver,
                'delivery_reason'  => $deliveryReason,
                'delivery_hint'    => $deliveryHint,
                'next_step'        => $this->nextStepHint($queue->status, $canDeliver, $deliveryReason, 'queue'),
            ]);
        }

        // Apply status filter
        if ($statusFilter !== '' && $statusFilter !== 'all') {
            $rows = $rows->filter(fn($r) => $r['status'] == (int) $statusFilter);
        }

        // Sort: emergency first, then by time
        $rows = $rows->sortBy([
            fn($a, $b) => ($a['priority'] === 'emergency' ? 0 : ($a['priority'] === 'urgent' ? 1 : 2))
                       <=> ($b['priority'] === 'emergency' ? 0 : ($b['priority'] === 'urgent' ? 1 : 2)),
            fn($a, $b) => $a['sort_time'] <=> $b['sort_time'],
        ])->values();

        return DataTables::of($rows)
            ->addIndexColumn()
            ->addColumn('patient_info', function ($row) {
                // Rich patient column: name + file_no + priority icon + HMO line
                $priorityIcon = '';
                if ($row['priority'] === 'emergency') {
                    $priorityIcon = '<i class="fa fa-bolt text-danger me-1" title="Emergency"></i>';
                } elseif ($row['priority'] === 'urgent') {
                    $priorityIcon = '<i class="mdi mdi-alert text-warning me-1" title="Urgent"></i>';
                }

                $name = $priorityIcon . '<strong>' . e($row['patient_name']) . '</strong>';
                $fileNo = '<span class="text-muted small">' . e($row['file_no']) . '</span>';

                $hmoLine = '';
                if (!empty($row['hmo'])) {
                    $hmoLine = '<br><span class="badge bg-light text-dark border" style="font-size:0.68rem;"><i class="mdi mdi-shield-check-outline"></i> ' . e($row['hmo']) . '</span>';
                }

                $reasonLine = '';
                if (!empty($row['reason']) && $row['reason'] !== '-') {
                    $reasonLine = '<br><span class="text-muted small fst-italic" title="' . e($row['reason']) . '"><i class="mdi mdi-note-text-outline"></i> ' . e($row['reason']) . '</span>';
                }

                return '<div class="d-flex flex-column lh-sm">'
                    . '<div>' . $name . ' <span class="text-muted mx-1">·</span> ' . $fileNo . '</div>'
                    . $hmoLine
                    . $reasonLine
                    . '</div>';
            })
            ->addColumn('source_time', function ($row) {
                // Combined source badge + time
                $icons = [
                    'scheduled'   => '<span class="badge bg-purple-subtle text-purple source-badge"><i class="mdi mdi-calendar-check"></i> Scheduled</span>',
                    'follow_up'   => '<span class="badge bg-info-subtle text-info source-badge"><i class="mdi mdi-calendar-refresh"></i> Follow-up</span>',
                    'referral'    => '<span class="badge bg-warning-subtle text-warning source-badge"><i class="mdi mdi-share-variant"></i> Referral</span>',
                    'appointment' => '<span class="badge bg-purple-subtle text-purple source-badge"><i class="mdi mdi-calendar-check"></i> Appointment</span>',
                    'emergency'   => '<span class="badge bg-danger-subtle text-danger source-badge"><i class="mdi mdi-ambulance"></i> Emergency</span>',
                    'walk_in'     => '<span class="badge bg-secondary-subtle text-secondary source-badge"><i class="mdi mdi-walk"></i> Walk-in</span>',
                ];
                $badge = $icons[$row['source']] ?? $icons['walk_in'];

                if (!empty($row['is_follow_up'])) {
                    $badge = '<span class="badge bg-info-subtle text-info source-badge"><i class="mdi mdi-link-variant"></i> Follow-up</span>';
                }
                if (!empty($row['is_prepaid'])) {
                    $badge .= ' <span class="badge bg-success-subtle text-success" title="Pre-paid"><i class="mdi mdi-cash-check"></i></span>';
                }

                if (!empty($row['is_future'])) {
                    $dateLabel = \Carbon\Carbon::parse($row['appointment_date'])->format('M j');
                    $time = '<div class="text-muted small mt-1"><i class="mdi mdi-calendar-arrow-right"></i> ' . e($dateLabel) . ' · ' . e($row['time']) . '</div>';
                } else {
                    $time = '<div class="text-muted small mt-1"><i class="mdi mdi-clock-outline"></i> ' . e($row['time']) . '</div>';
                }
                return $badge . $time;
            })
            ->addColumn('delivery_badge', function ($row) {
                // Very small delivery status + next-step hint
                $style = 'font-size:0.7rem;line-height:1.3;';
                $nextStep = $row['next_step'] ?? '';
                $hintLine = $nextStep ? '<br><span style="font-size:0.65rem;color:#0d6efd;font-style:italic;" title="' . e($nextStep) . '"><i class="mdi mdi-arrow-right-circle"></i> ' . e(\Illuminate\Support\Str::limit($nextStep, 35)) . '</span>' : '';

                if ($row['event_type'] === 'appointment' && $row['status'] == QueueStatus::SCHEDULED) {
                    return '<span class="text-muted" style="' . $style . 'cursor:default;" title="Check in first"><i class="mdi mdi-clock-outline"></i> Pending</span>' . $hintLine;
                }
                if ($row['can_deliver']) {
                    return '<span class="text-success" style="' . $style . '" title="' . e($row['delivery_hint']) . '"><i class="mdi mdi-check-circle"></i> Ready</span>' . $hintLine;
                }
                return '<span class="text-danger" style="' . $style . 'cursor:help;" title="' . e($row['delivery_hint']) . '"><i class="mdi mdi-alert-circle"></i> ' . e($row['delivery_reason']) . '</span>' . $hintLine;
            })
            ->addColumn('action', function ($row) {
                $btns = '<div class="d-flex gap-1 flex-wrap">';
                if ($row['encounter_url']) {
                    $btns .= '<a href="' . $row['encounter_url'] . '" class="btn btn-success btn-sm" title="Open Encounter"><i class="fa fa-street-view"></i> Encounter</a>';
                } elseif ($row['event_type'] === 'queue' && !$row['can_deliver']) {
                    $btns .= '<button class="btn btn-sm btn-secondary" disabled title="' . e($row['delivery_hint']) . '"><i class="fa fa-street-view"></i> Blocked</button>';
                }
                if ($row['event_type'] === 'appointment' && $row['status'] == QueueStatus::SCHEDULED) {
                    $btns .= '<button class="btn btn-sm btn-primary btn-checkin-appt" data-id="' . $row['record_id'] . '" title="Check-In"><i class="mdi mdi-login"></i> Check-In</button>';
                    $btns .= '<button class="btn btn-sm btn-warning btn-reschedule-queue" data-id="' . $row['record_id'] . '" data-clinic="' . $row['clinic_id'] . '" data-doctor="' . ($row['doctor_id'] ?? '') . '" data-patient="' . e($row['patient_name']) . '" title="Reschedule"><i class="mdi mdi-calendar-edit"></i> Reschedule</button>';
                    $btns .= '<button class="btn btn-sm btn-purple btn-reassign-queue" data-id="' . $row['record_id'] . '" data-clinic="' . $row['clinic_id'] . '" data-patient="' . e($row['patient_name']) . '" title="Change Doctor"><i class="mdi mdi-account-switch"></i> Reassign</button>';
                }
                $btns .= '</div>';
                return $btns;
            })
            ->rawColumns(['patient_info', 'source_time', 'status_badge', 'delivery_badge', 'action'])
            ->make(true);
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

        // Appointment base query — all upcoming scheduled (today + future)
        $apptBase = function () use ($doc, $today) {
            return DoctorAppointment::where(function ($q) use ($doc) {
                $q->where('staff_id', $doc->id)
                  ->orWhere('clinic_id', $doc->clinic_id);
            })
            ->where('appointment_date', '>=', $today)
            ->where('status', QueueStatus::SCHEDULED);
        };

        $counts = [
            'new'              => $baseQuery()->where('status', QueueStatus::WAITING)->count(),
            'vitals_pending'   => $baseQuery()->where('status', QueueStatus::VITALS_PENDING)->count(),
            'ready'            => $baseQuery()->where('status', QueueStatus::READY)->count(),
            'in_consultation'  => $baseQuery()->where('status', QueueStatus::IN_CONSULTATION)->count(),
            'completed'        => $baseQuery()->where('status', QueueStatus::COMPLETED)->count(),
            'scheduled'        => $apptBase()->count(), // all upcoming (today + future)
            'scheduled_today'  => $apptBase()->whereDate('appointment_date', $today)->count(),
            'scheduled_future' => $apptBase()->where('appointment_date', '>', $today)->count(),
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
        $service = \App\Models\Service::where('service_name', 'LIKE', '%consultation%')
            ->first();

        return $service?->id;
    }
}
