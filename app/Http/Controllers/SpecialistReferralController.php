<?php

namespace App\Http\Controllers;

use App\Enums\QueueStatus;
use App\Models\DoctorAppointment;
use App\Models\Encounter;
use App\Models\SpecialistReferral;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;

class SpecialistReferralController extends Controller
{
    // ─── Doctor: Create referral from encounter ────────────────────────

    /**
     * Create a specialist referral from within an encounter.
     */
    public function createReferral(Request $request, Encounter $encounter)
    {
        $request->validate([
            'referral_type'               => 'required|in:internal,external',
            'target_clinic_id'            => 'required_if:referral_type,internal|nullable|exists:clinics,id',
            'target_doctor_id'            => 'nullable|exists:staff,id',
            'target_specialization_id'    => 'nullable|integer',
            'external_facility_name'      => 'required_if:referral_type,external|nullable|string|max:255',
            'external_doctor_name'        => 'nullable|string|max:255',
            'external_facility_address'   => 'nullable|string|max:500',
            'external_facility_phone'     => 'nullable|string|max:50',
            'reason'                      => 'required|string|max:1000',
            'clinical_summary'            => 'nullable|string|max:2000',
            'provisional_diagnosis'       => 'nullable|string|max:500',
            'urgency'                     => 'nullable|in:routine,urgent,emergency',
        ]);

        try {
            $doctor = Staff::where('user_id', Auth::id())->first();

            $referral = SpecialistReferral::create([
                'patient_id'                   => $encounter->patient_id,
                'encounter_id'                 => $encounter->id,
                'referring_doctor_id'          => $doctor?->id,
                'referring_clinic_id'          => $doctor?->clinic_id,
                'referral_type'                => $request->referral_type,
                'target_clinic_id'             => $request->target_clinic_id,
                'target_doctor_id'             => $request->target_doctor_id,
                'target_specialization_id'     => $request->target_specialization_id,
                'external_facility_name'       => $request->external_facility_name,
                'external_doctor_name'         => $request->external_doctor_name,
                'external_facility_address'    => $request->external_facility_address,
                'external_facility_phone'      => $request->external_facility_phone,
                'reason'                       => $request->reason,
                'clinical_summary'             => $request->clinical_summary,
                'provisional_diagnosis'        => $request->provisional_diagnosis,
                'urgency'                      => $request->urgency ?? SpecialistReferral::URGENCY_ROUTINE,
                'status'                       => SpecialistReferral::STATUS_PENDING,
            ]);

            return response()->json([
                'success'  => true,
                'message'  => 'Referral submitted successfully.',
                'referral' => $referral->load('targetClinic', 'targetDoctor'),
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating referral', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create referral: ' . $e->getMessage()], 500);
        }
    }

    // ─── Get referrals for an encounter ────────────────────────────────

    /**
     * List all referrals for a specific encounter.
     */
    public function getEncounterReferrals(Encounter $encounter)
    {
        $referrals = SpecialistReferral::where('encounter_id', $encounter->id)
            ->with(['targetClinic', 'targetDoctor.user', 'referringDoctor.user', 'appointment'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($ref) {
                return [
                    'id'                    => $ref->id,
                    'type'                  => $ref->referral_type,
                    'status'                => $ref->status,
                    'urgency'               => $ref->urgency,
                    'reason'                => $ref->reason,
                    'clinical_summary'      => $ref->clinical_summary,
                    'target_clinic'         => $ref->targetClinic->name ?? null,
                    'target_doctor'         => $ref->targetDoctor ? userfullname($ref->targetDoctor->user_id) : null,
                    'external_facility'     => $ref->external_facility_name,
                    'external_doctor'       => $ref->external_doctor_name,
                    'referring_doctor'      => $ref->referringDoctor ? userfullname($ref->referringDoctor->user_id) : null,
                    'appointment_id'        => $ref->appointment_id,
                    'created_at'            => $ref->created_at->format('M d, Y h:i A'),
                    'actioned_at'           => $ref->actioned_at?->format('M d, Y h:i A'),
                    'action_notes'          => $ref->action_notes,
                ];
            });

        return response()->json(['success' => true, 'referrals' => $referrals]);
    }

    // ─── Reception: Pending referrals ──────────────────────────────────

    /**
     * Get all pending referrals for reception workbench.
     */
    public function getPendingReferrals(Request $request)
    {
        $query = SpecialistReferral::with([
                'patient.user', 'patient.hmo',
                'referringDoctor.user', 'referringClinic',
                'targetClinic', 'targetDoctor.user',
                'encounter',
            ])
            ->where('status', SpecialistReferral::STATUS_PENDING)
            ->orderByRaw("FIELD(urgency, 'emergency', 'urgent', 'routine') ASC")
            ->orderBy('created_at', 'asc');

        if ($request->filled('referral_type')) {
            $query->where('referral_type', $request->referral_type);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('patient_name', function ($ref) {
                return $ref->patient ? userfullname($ref->patient->user_id) : 'N/A';
            })
            ->addColumn('patient_file_no', function ($ref) {
                return $ref->patient->file_no ?? 'N/A';
            })
            ->addColumn('referring_doctor', function ($ref) {
                return $ref->referringDoctor ? userfullname($ref->referringDoctor->user_id) : 'N/A';
            })
            ->addColumn('referring_clinic', function ($ref) {
                return $ref->referringClinic->name ?? 'N/A';
            })
            ->addColumn('target_info', function ($ref) {
                if ($ref->referral_type === 'internal') {
                    $target = $ref->targetClinic->name ?? 'Any Clinic';
                    if ($ref->targetDoctor) {
                        $target .= ' — ' . userfullname($ref->targetDoctor->user_id);
                    }
                    return $target;
                }
                return $ref->external_facility_name ?? 'External';
            })
            ->addColumn('urgency_badge', function ($ref) {
                $badges = [
                    'emergency' => '<span class="badge bg-danger">Emergency</span>',
                    'urgent'    => '<span class="badge bg-warning text-dark">Urgent</span>',
                    'routine'   => '<span class="badge bg-secondary">Routine</span>',
                ];
                return $badges[$ref->urgency] ?? $badges['routine'];
            })
            ->addColumn('type_badge', function ($ref) {
                return $ref->referral_type === 'internal'
                    ? '<span class="badge bg-info">Internal</span>'
                    : '<span class="badge bg-dark">External</span>';
            })
            ->addColumn('reason_short', function ($ref) {
                return \Illuminate\Support\Str::limit($ref->reason, 80);
            })
            ->addColumn('time', function ($ref) {
                return $ref->created_at->format('M d, h:i A');
            })
            ->addColumn('actions', function ($ref) {
                $buttons = '';
                if ($ref->referral_type === 'internal') {
                    $buttons .= '<button class="btn btn-sm btn-success btn-book-referral" data-id="' . $ref->id . '" data-clinic="' . $ref->target_clinic_id . '" data-doctor="' . $ref->target_doctor_id . '" data-patient="' . $ref->patient_id . '"><i class="mdi mdi-calendar-plus"></i> Book</button> ';
                } else {
                    $buttons .= '<button class="btn btn-sm btn-info btn-refer-out" data-id="' . $ref->id . '"><i class="mdi mdi-arrow-right-bold"></i> Referred Out</button> ';
                }
                $buttons .= '<button class="btn btn-sm btn-warning btn-decline-referral" data-id="' . $ref->id . '"><i class="mdi mdi-close"></i></button> ';
                $buttons .= '<button class="btn btn-sm btn-danger btn-cancel-referral" data-id="' . $ref->id . '"><i class="mdi mdi-delete"></i></button>';
                return $buttons;
            })
            ->rawColumns(['urgency_badge', 'type_badge', 'actions'])
            ->make(true);
    }

    /**
     * Get count of pending referrals (for widget badge).
     */
    public function getPendingReferralCount()
    {
        $count = SpecialistReferral::where('status', SpecialistReferral::STATUS_PENDING)->count();
        return response()->json(['count' => $count]);
    }

    // ─── Reception: Book appointment from referral ─────────────────────

    /**
     * Book an appointment for an internal referral.
     */
    public function bookReferralAppointment(Request $request, SpecialistReferral $referral)
    {
        $request->validate([
            'appointment_date' => 'required|date|after_or_equal:today',
            'start_time'       => 'required|date_format:H:i',
            'end_time'         => 'nullable|date_format:H:i|after:start_time',
            'doctor_id'        => 'nullable|exists:staff,id',
            'clinic_id'        => 'nullable|exists:clinics,id',
        ]);

        if ($referral->status !== SpecialistReferral::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'This referral has already been actioned.'], 422);
        }

        if ($referral->referral_type !== 'internal') {
            return response()->json(['success' => false, 'message' => 'Only internal referrals can be booked as appointments.'], 422);
        }

        try {
            DB::beginTransaction();

            $startTime = Carbon::parse($request->start_time);
            $endTime = $request->end_time
                ? Carbon::parse($request->end_time)
                : $startTime->copy()->addMinutes((int) appsettings('default_slot_duration', 15));

            $bookedByStaff = Staff::where('user_id', Auth::id())->first();

            $appointment = DoctorAppointment::create([
                'patient_id'       => $referral->patient_id,
                'clinic_id'        => $request->clinic_id ?? $referral->target_clinic_id,
                'staff_id'         => $request->doctor_id ?? $referral->target_doctor_id,
                'appointment_date' => $request->appointment_date,
                'start_time'       => $request->start_time,
                'end_time'         => $endTime->format('H:i'),
                'appointment_type' => 'referral',
                'status'           => QueueStatus::SCHEDULED,
                'priority'         => $referral->urgency ?? 'routine',
                'booked_by'        => $bookedByStaff ? $bookedByStaff->id : null,
                'source'           => 'referral',
                'referral_id'      => $referral->id,
                'notes'            => 'Referral from ' . ($referral->referringDoctor ? userfullname($referral->referringDoctor->user_id) : 'Unknown') . ': ' . $referral->reason,
            ]);

            $referral->update([
                'status'        => SpecialistReferral::STATUS_BOOKED,
                'appointment_id' => $appointment->id,
                'actioned_by'   => $bookedByStaff ? $bookedByStaff->id : null,
                'actioned_at'   => now(),
                'action_notes'  => 'Appointment booked for ' . $request->appointment_date,
            ]);

            DB::commit();

            return response()->json([
                'success'     => true,
                'message'     => 'Referral appointment booked successfully.',
                'appointment' => $appointment->load('patient.user', 'clinic'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error booking referral appointment', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to book: ' . $e->getMessage()], 500);
        }
    }

    // ─── Reception: Mark as referred externally ────────────────────────

    /**
     * Mark referral as referred out (external).
     */
    public function referOut(Request $request, SpecialistReferral $referral)
    {
        $request->validate([
            'action_notes' => 'nullable|string|max:1000',
        ]);

        if ($referral->status !== SpecialistReferral::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'This referral has already been actioned.'], 422);
        }

        $actionStaff = Staff::where('user_id', Auth::id())->first();

        $referral->update([
            'status'       => SpecialistReferral::STATUS_REFERRED_OUT,
            'actioned_by'  => $actionStaff ? $actionStaff->id : null,
            'actioned_at'  => now(),
            'action_notes' => $request->action_notes ?? 'Referred to external facility',
        ]);

        return response()->json(['success' => true, 'message' => 'Marked as referred out.']);
    }

    // ─── Cancel / Decline ──────────────────────────────────────────────

    /**
     * Cancel a referral.
     */
    public function cancelReferral(Request $request, SpecialistReferral $referral)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        if (in_array($referral->status, [SpecialistReferral::STATUS_COMPLETED, SpecialistReferral::STATUS_CANCELLED])) {
            return response()->json(['success' => false, 'message' => 'This referral cannot be cancelled.'], 422);
        }

        $cancelStaff = Staff::where('user_id', Auth::id())->first();

        $referral->update([
            'status'       => SpecialistReferral::STATUS_CANCELLED,
            'actioned_by'  => $cancelStaff ? $cancelStaff->id : null,
            'actioned_at'  => now(),
            'action_notes' => $request->reason ?? 'Cancelled',
        ]);

        return response()->json(['success' => true, 'message' => 'Referral cancelled.']);
    }

    /**
     * Decline a referral (with reason).
     */
    public function declineReferral(Request $request, SpecialistReferral $referral)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if ($referral->status !== SpecialistReferral::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Only pending referrals can be declined.'], 422);
        }

        $declineStaff = Staff::where('user_id', Auth::id())->first();

        $referral->update([
            'status'       => SpecialistReferral::STATUS_DECLINED,
            'actioned_by'  => $declineStaff ? $declineStaff->id : null,
            'actioned_at'  => now(),
            'action_notes' => 'Declined: ' . $request->reason,
        ]);

        return response()->json(['success' => true, 'message' => 'Referral declined.']);
    }

    // ─── Patient Referral History ──────────────────────────────────────

    /**
     * Get full referral history for a patient.
     */
    public function patientReferralHistory(int $patientId)
    {
        $referrals = SpecialistReferral::where('patient_id', $patientId)
            ->with([
                'referringDoctor.user', 'referringClinic',
                'targetClinic', 'targetDoctor.user',
                'encounter', 'appointment',
            ])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($ref) {
                return [
                    'id'                 => $ref->id,
                    'type'               => $ref->referral_type,
                    'status'             => $ref->status,
                    'urgency'            => $ref->urgency,
                    'reason'             => $ref->reason,
                    'clinical_summary'   => $ref->clinical_summary,
                    'diagnosis'          => $ref->provisional_diagnosis,
                    'referring_doctor'   => $ref->referringDoctor ? userfullname($ref->referringDoctor->user_id) : null,
                    'referring_clinic'   => $ref->referringClinic->name ?? null,
                    'target_clinic'      => $ref->targetClinic->name ?? null,
                    'target_doctor'      => $ref->targetDoctor ? userfullname($ref->targetDoctor->user_id) : null,
                    'external_facility'  => $ref->external_facility_name,
                    'appointment_date'   => $ref->appointment ? $ref->appointment->appointment_date : null,
                    'action_notes'       => $ref->action_notes,
                    'created_at'         => $ref->created_at->format('M d, Y'),
                    'actioned_at'        => $ref->actioned_at?->format('M d, Y'),
                ];
            });

        return response()->json(['success' => true, 'referrals' => $referrals]);
    }
}
