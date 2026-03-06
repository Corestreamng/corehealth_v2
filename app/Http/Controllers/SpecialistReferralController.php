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
        $staff = Staff::where('user_id', Auth::id())->first();

        $referrals = SpecialistReferral::where('encounter_id', $encounter->id)
            ->with(['targetClinic', 'targetDoctor.user', 'referringDoctor.user', 'appointment'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($ref) use ($staff) {
                $isMine = $staff && $ref->referring_doctor_id == $staff->id;
                return [
                    'id'                    => $ref->id,
                    'type'                  => $ref->referral_type,
                    'status'                => $ref->status,
                    'urgency'               => $ref->urgency,
                    'reason'                => $ref->reason,
                    'clinical_summary'      => $ref->clinical_summary,
                    'provisional_diagnosis' => $ref->provisional_diagnosis,
                    'target_clinic'         => $ref->targetClinic->name ?? null,
                    'target_clinic_id'      => $ref->target_clinic_id,
                    'target_doctor'         => $ref->targetDoctor ? userfullname($ref->targetDoctor->user_id) : null,
                    'target_doctor_id'      => $ref->target_doctor_id,
                    'external_facility'     => $ref->external_facility_name,
                    'external_doctor'       => $ref->external_doctor_name,
                    'external_facility_address' => $ref->external_facility_address,
                    'external_facility_phone'   => $ref->external_facility_phone,
                    'referring_doctor'      => $ref->referringDoctor ? userfullname($ref->referringDoctor->user_id) : null,
                    'referring_doctor_id'   => $ref->referring_doctor_id,
                    'is_mine'               => $isMine,
                    'can_edit'              => $isMine && $ref->status === SpecialistReferral::STATUS_PENDING,
                    'appointment_id'        => $ref->appointment_id,
                    'created_at'            => $ref->created_at->format('M d, Y h:i A'),
                    'actioned_at'           => $ref->actioned_at?->format('M d, Y h:i A'),
                    'action_notes'          => $ref->action_notes,
                ];
            });

        return response()->json(['success' => true, 'referrals' => $referrals]);
    }

    /**
     * Get ALL referrals for a patient (across all encounters).
     * Returns ownership flag so any doctor can see them, but only
     * the creator can edit/delete pending ones.
     */
    public function getPatientReferrals(Encounter $encounter)
    {
        $staff = Staff::where('user_id', Auth::id())->first();
        $patientId = $encounter->patient_id;

        $referrals = SpecialistReferral::where('patient_id', $patientId)
            ->with(['targetClinic', 'targetDoctor.user', 'referringDoctor.user', 'referringClinic', 'encounter', 'appointment'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($ref) use ($staff, $encounter) {
                $isMine = $staff && $ref->referring_doctor_id == $staff->id;
                $isCurrentEncounter = $ref->encounter_id == $encounter->id;
                return [
                    'id'                    => $ref->id,
                    'type'                  => $ref->referral_type,
                    'status'                => $ref->status,
                    'urgency'               => $ref->urgency,
                    'reason'                => $ref->reason,
                    'clinical_summary'      => $ref->clinical_summary,
                    'provisional_diagnosis' => $ref->provisional_diagnosis,
                    'target_clinic'         => $ref->targetClinic->name ?? null,
                    'target_clinic_id'      => $ref->target_clinic_id,
                    'target_doctor'         => $ref->targetDoctor ? userfullname($ref->targetDoctor->user_id) : null,
                    'target_doctor_id'      => $ref->target_doctor_id,
                    'external_facility'     => $ref->external_facility_name,
                    'external_doctor'       => $ref->external_doctor_name,
                    'external_facility_address' => $ref->external_facility_address,
                    'external_facility_phone'   => $ref->external_facility_phone,
                    'referring_doctor'      => $ref->referringDoctor ? userfullname($ref->referringDoctor->user_id) : null,
                    'referring_doctor_id'   => $ref->referring_doctor_id,
                    'referring_clinic'      => $ref->referringClinic->name ?? null,
                    'encounter_id'          => $ref->encounter_id,
                    'is_current_encounter'  => $isCurrentEncounter,
                    'is_mine'               => $isMine,
                    'can_edit'              => $isMine && $ref->status === SpecialistReferral::STATUS_PENDING,
                    'appointment_id'        => $ref->appointment_id,
                    'action_notes'          => $ref->action_notes,
                    'created_at'            => $ref->created_at->format('M d, Y h:i A'),
                    'actioned_at'           => $ref->actioned_at?->format('M d, Y h:i A'),
                ];
            });

        return response()->json(['success' => true, 'referrals' => $referrals]);
    }

    // ─── Doctor: Update a pending referral ─────────────────────────────

    /**
     * Update a specialist referral (only if still pending).
     */
    public function updateReferral(Request $request, Encounter $encounter, SpecialistReferral $referral)
    {
        // Allow editing own referrals from any encounter context (patient-wide view)
        $staff = Staff::where('user_id', Auth::id())->first();
        if (!$staff || $referral->referring_doctor_id !== $staff->id) {
            return response()->json(['success' => false, 'message' => 'You can only edit referrals you created.'], 403);
        }

        if ($referral->status !== SpecialistReferral::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Only pending referrals can be edited.'], 422);
        }

        $request->validate([
            'referral_type'               => 'required|in:internal,external',
            'target_clinic_id'            => 'required_if:referral_type,internal|nullable|exists:clinics,id',
            'target_doctor_id'            => 'nullable|exists:staff,id',
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
            $referral->update([
                'referral_type'                => $request->referral_type,
                'target_clinic_id'             => $request->referral_type === 'internal' ? $request->target_clinic_id : null,
                'target_doctor_id'             => $request->referral_type === 'internal' ? $request->target_doctor_id : null,
                'external_facility_name'       => $request->referral_type === 'external' ? $request->external_facility_name : null,
                'external_doctor_name'         => $request->referral_type === 'external' ? $request->external_doctor_name : null,
                'external_facility_address'    => $request->referral_type === 'external' ? $request->external_facility_address : null,
                'external_facility_phone'      => $request->referral_type === 'external' ? $request->external_facility_phone : null,
                'reason'                       => $request->reason,
                'clinical_summary'             => $request->clinical_summary,
                'provisional_diagnosis'        => $request->provisional_diagnosis,
                'urgency'                      => $request->urgency ?? SpecialistReferral::URGENCY_ROUTINE,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Referral updated successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating referral', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to update referral: ' . $e->getMessage()], 500);
        }
    }

    // ─── Doctor: Delete a pending referral ─────────────────────────────

    /**
     * Delete a specialist referral (only if still pending).
     */
    public function deleteReferral(Encounter $encounter, SpecialistReferral $referral)
    {
        // Allow deleting own referrals from any encounter context
        $staff = Staff::where('user_id', Auth::id())->first();
        if (!$staff || $referral->referring_doctor_id !== $staff->id) {
            return response()->json(['success' => false, 'message' => 'You can only delete referrals you created.'], 403);
        }

        if ($referral->status !== SpecialistReferral::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Only pending referrals can be deleted.'], 422);
        }

        try {
            $referral->delete();

            return response()->json([
                'success' => true,
                'message' => 'Referral deleted successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting referral', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to delete referral: ' . $e->getMessage()], 500);
        }
    }

    // ─── Doctor: Incoming referrals (from other encounters) ────────────

    /**
     * Get pending referrals targeted at the current doctor or their clinic.
     * Excludes referrals from the current encounter.
     */
    public function getIncomingReferrals(Encounter $encounter)
    {
        try {
            $staff = Staff::where('user_id', Auth::id())->first();
            if (!$staff) {
                return response()->json(['success' => true, 'referrals' => []]);
            }

            $referrals = SpecialistReferral::where('status', SpecialistReferral::STATUS_PENDING)
                ->where('referral_type', 'internal')
                ->where('encounter_id', '!=', $encounter->id)
                ->where(function ($q) use ($staff) {
                    $q->where('target_doctor_id', $staff->id)
                      ->orWhere(function ($q2) use ($staff) {
                          // Referrals to my clinic with no specific doctor targeted
                          $q2->where('target_clinic_id', $staff->clinic_id)
                             ->whereNull('target_doctor_id');
                      });
                })
                ->with(['patient', 'referringDoctor', 'referringClinic', 'targetClinic', 'encounter'])
                ->orderByRaw("FIELD(urgency, 'emergency', 'urgent', 'routine')")
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($ref) {
                    return [
                        'id'                    => $ref->id,
                        'type'                  => $ref->referral_type,
                        'urgency'               => $ref->urgency,
                        'reason'                => $ref->reason,
                        'provisional_diagnosis' => $ref->provisional_diagnosis,
                        'clinical_summary'      => $ref->clinical_summary,
                        'patient_id'            => $ref->patient_id,
                        'patient_name'          => $ref->patient ? userfullname($ref->patient->user_id) : 'Unknown',
                        'patient_file_no'       => $ref->patient->file_no ?? 'N/A',
                        'referring_doctor'      => $ref->referringDoctor ? userfullname($ref->referringDoctor->user_id) : 'Unknown',
                        'referring_clinic'      => $ref->referringClinic->name ?? null,
                        'target_clinic'         => $ref->targetClinic->name ?? null,
                        'encounter_id'          => $ref->encounter_id,
                        'created_at'            => $ref->created_at->format('M d, Y H:i'),
                    ];
                });

            return response()->json(['success' => true, 'referrals' => $referrals]);
        } catch (\Exception $e) {
            Log::error('Error fetching incoming referrals', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to load incoming referrals'], 500);
        }
    }

    // ─── Doctor: My Referrals DataTable ────────────────────────────────

    /**
     * DataTable endpoint for "My Referrals" — referrals sent by or targeted at the current doctor.
     * Supports filters: start_date, end_date, status, direction (sent|received), referral_type.
     */
    public function getDoctorReferralsList(Request $request)
    {
        $staff = Staff::where('user_id', Auth::id())->first();
        if (!$staff) {
            return DataTables::of(collect([]))->make(true);
        }

        $query = SpecialistReferral::with([
            'patient', 'referringDoctor', 'referringClinic',
            'targetClinic', 'targetDoctor',
        ]);

        // Direction filter
        $direction = $request->input('direction', '');
        if ($direction === 'sent') {
            $query->where('referring_doctor_id', $staff->id);
        } elseif ($direction === 'received') {
            $query->where(function ($q) use ($staff) {
                $q->where('target_doctor_id', $staff->id)
                  ->orWhere(function ($q2) use ($staff) {
                      $q2->where('target_clinic_id', $staff->clinic_id)
                         ->whereNull('target_doctor_id');
                  });
            });
        } else {
            // All: sent by me OR targeted at me
            $query->where(function ($q) use ($staff) {
                $q->where('referring_doctor_id', $staff->id)
                  ->orWhere('target_doctor_id', $staff->id)
                  ->orWhere(function ($q2) use ($staff) {
                      $q2->where('target_clinic_id', $staff->clinic_id)
                         ->whereNull('target_doctor_id');
                  });
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        // Type filter
        if ($request->filled('referral_type')) {
            $query->where('referral_type', $request->referral_type);
        }
        // Date filter
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $query->orderByRaw("CASE urgency WHEN 'emergency' THEN 1 WHEN 'urgent' THEN 2 WHEN 'routine' THEN 3 ELSE 4 END ASC")
              ->orderBy('created_at', 'desc');

        return $this->buildReferralDataTable($query, $staff);
    }

    // ─── All Referrals DataTable (hospital-wide) ───────────────────────

    /**
     * DataTable endpoint for "All Referrals" — hospital-wide referral list.
     * Supports filters: start_date, end_date, status, clinic_id, doctor_id, referral_type.
     */
    public function getAllReferralsList(Request $request)
    {
        $staff = Staff::where('user_id', Auth::id())->first();

        $query = SpecialistReferral::with([
            'patient', 'referringDoctor', 'referringClinic',
            'targetClinic', 'targetDoctor',
        ]);

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        // Type filter
        if ($request->filled('referral_type')) {
            $query->where('referral_type', $request->referral_type);
        }
        // Clinic filter (either referring or target)
        if ($request->filled('clinic_id')) {
            $cid = $request->clinic_id;
            $query->where(function ($q) use ($cid) {
                $q->where('referring_clinic_id', $cid)
                  ->orWhere('target_clinic_id', $cid);
            });
        }
        // Doctor filter (either referring or target)
        if ($request->filled('doctor_id')) {
            $did = $request->doctor_id;
            $query->where(function ($q) use ($did) {
                $q->where('referring_doctor_id', $did)
                  ->orWhere('target_doctor_id', $did);
            });
        }
        // Date filter
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $query->orderByRaw("CASE urgency WHEN 'emergency' THEN 1 WHEN 'urgent' THEN 2 WHEN 'routine' THEN 3 ELSE 4 END ASC")
              ->orderBy('created_at', 'desc');

        return $this->buildReferralDataTable($query, $staff);
    }

    /**
     * Shared DataTable column builder for referral listings.
     */
    private function buildReferralDataTable($query, $staff = null)
    {
        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('patient_name', function ($ref) {
                return $ref->patient ? userfullname($ref->patient->user_id) : 'N/A';
            })
            ->addColumn('patient_file_no', function ($ref) {
                return $ref->patient->file_no ?? 'N/A';
            })
            ->addColumn('from_info', function ($ref) {
                $from = $ref->referringDoctor ? userfullname($ref->referringDoctor->user_id) : 'N/A';
                if ($ref->referringClinic) {
                    $from .= '<br><small class="text-muted">' . e($ref->referringClinic->name) . '</small>';
                }
                return $from;
            })
            ->addColumn('to_info', function ($ref) {
                if ($ref->referral_type === 'internal') {
                    $to = $ref->targetClinic->name ?? 'Any Clinic';
                    if ($ref->targetDoctor) {
                        $to .= '<br><small class="text-muted">' . userfullname($ref->targetDoctor->user_id) . '</small>';
                    }
                    return $to;
                }
                return e($ref->external_facility_name ?? 'External');
            })
            ->addColumn('urgency_badge', function ($ref) {
                $badges = [
                    'emergency' => '<span class="badge bg-danger"><i class="mdi mdi-alert-circle me-1"></i>Emergency</span>',
                    'urgent'    => '<span class="badge bg-warning text-dark"><i class="mdi mdi-alert me-1"></i>Urgent</span>',
                    'routine'   => '<span class="badge bg-secondary">Routine</span>',
                ];
                return $badges[$ref->urgency] ?? $badges['routine'];
            })
            ->addColumn('type_badge', function ($ref) {
                return $ref->referral_type === 'internal'
                    ? '<span class="badge bg-info">Internal</span>'
                    : '<span class="badge bg-dark">External</span>';
            })
            ->addColumn('status_badge', function ($ref) {
                $statusBadges = [
                    'pending'      => '<span class="badge bg-warning text-dark">Pending</span>',
                    'booked'       => '<span class="badge bg-primary">Booked</span>',
                    'completed'    => '<span class="badge bg-success">Completed</span>',
                    'cancelled'    => '<span class="badge bg-danger">Cancelled</span>',
                    'declined'     => '<span class="badge bg-dark">Declined</span>',
                    'referred_out' => '<span class="badge bg-purple text-white">Referred Out</span>',
                ];
                return $statusBadges[$ref->status] ?? '<span class="badge bg-secondary">' . ucfirst($ref->status) . '</span>';
            })
            ->addColumn('reason_short', function ($ref) {
                return '<span title="' . e($ref->reason) . '">' . e(\Illuminate\Support\Str::limit($ref->reason, 50)) . '</span>';
            })
            ->addColumn('time', function ($ref) {
                return $ref->created_at->format('M d, Y');
            })
            ->addColumn('actions', function ($ref) use ($staff) {
                $buttons = '<div class="btn-group btn-group-sm" role="group">';
                // View detail
                $buttons .= '<button class="btn btn-outline-secondary btn-view-ref-detail" data-id="' . $ref->id . '" title="View Details"><i class="mdi mdi-eye"></i></button>';

                if ($ref->status === SpecialistReferral::STATUS_PENDING) {
                    // Check if targeted at this doctor
                    $isTargeted = $staff && (
                        $ref->target_doctor_id == $staff->id ||
                        ($ref->target_clinic_id == $staff->clinic_id && !$ref->target_doctor_id)
                    );

                    if ($isTargeted) {
                        $buttons .= '<button class="btn btn-success btn-accept-ref" data-id="' . $ref->id . '" title="Accept &amp; Start Encounter"><i class="mdi mdi-check-circle"></i></button>';
                    }
                    $buttons .= '<button class="btn btn-warning btn-decline-ref" data-id="' . $ref->id . '" title="Decline"><i class="mdi mdi-close-circle"></i></button>';
                }

                // Print for external
                if ($ref->referral_type === 'external') {
                    $buttons .= '<button class="btn btn-outline-dark btn-view-ref-detail" data-id="' . $ref->id . '" title="Print"><i class="mdi mdi-printer"></i></button>';
                }

                $buttons .= '</div>';
                return $buttons;
            })
            ->rawColumns(['urgency_badge', 'type_badge', 'status_badge', 'reason_short', 'from_info', 'to_info', 'actions'])
            ->make(true);
    }

    // ─── Reception: Pending referrals ──────────────────────────────────

    /**
     * Get referrals for reception workbench.
     * Supports ?status=pending (default), ?status=all, or specific status.
     */
    public function getPendingReferrals(Request $request)
    {
        $query = SpecialistReferral::with([
                'patient.user', 'patient.hmo',
                'referringDoctor.user', 'referringClinic',
                'targetClinic', 'targetDoctor.user',
                'encounter',
            ]);

        // Status filter
        $statusFilter = $request->input('status', 'pending');
        if ($statusFilter === 'all') {
            // No status filter — show everything
        } elseif ($statusFilter === 'pending') {
            $query->where('status', SpecialistReferral::STATUS_PENDING);
        } else {
            $query->where('status', $statusFilter);
        }

        $query->orderByRaw("CASE urgency WHEN 'emergency' THEN 1 WHEN 'urgent' THEN 2 WHEN 'routine' THEN 3 ELSE 4 END ASC")
            ->orderBy('created_at', 'desc');

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
            ->addColumn('status_badge', function ($ref) {
                $statusBadges = [
                    'pending'      => '<span class="badge bg-warning text-dark">Pending</span>',
                    'booked'       => '<span class="badge bg-primary">Booked</span>',
                    'completed'    => '<span class="badge bg-success">Completed</span>',
                    'cancelled'    => '<span class="badge bg-danger">Cancelled</span>',
                    'declined'     => '<span class="badge bg-dark">Declined</span>',
                    'referred_out' => '<span class="badge bg-purple text-white">Referred Out</span>',
                ];
                return $statusBadges[$ref->status] ?? '<span class="badge bg-secondary">' . ucfirst($ref->status) . '</span>';
            })
            ->addColumn('reason_short', function ($ref) {
                return \Illuminate\Support\Str::limit($ref->reason, 80);
            })
            ->addColumn('time', function ($ref) {
                return $ref->created_at->format('M d, h:i A');
            })
            ->addColumn('actions', function ($ref) {
                $buttons = '<div class="btn-group btn-group-sm" role="group">';

                // View/Print button always available
                $buttons .= '<button class="btn btn-outline-secondary btn-view-referral" data-id="' . $ref->id . '" title="View Details"><i class="mdi mdi-eye"></i></button>';

                if ($ref->status === SpecialistReferral::STATUS_PENDING) {
                    if ($ref->referral_type === 'internal') {
                        $clinicName = $ref->targetClinic->name ?? '';
                        $doctorName = $ref->targetDoctor ? userfullname($ref->targetDoctor->user_id) : '';
                        $buttons .= '<button class="btn btn-success btn-book-referral" data-id="' . $ref->id . '" data-clinic="' . $ref->target_clinic_id . '" data-doctor="' . $ref->target_doctor_id . '" data-patient="' . $ref->patient_id . '" data-clinic-name="' . e($clinicName) . '" data-doctor-name="' . e($doctorName) . '" title="Book Appointment"><i class="mdi mdi-calendar-plus me-1"></i>Book</button>';
                    } else {
                        $buttons .= '<button class="btn btn-info btn-refer-out" data-id="' . $ref->id . '" title="Mark as Referred Out"><i class="mdi mdi-arrow-right-bold me-1"></i>Refer Out</button>';
                    }
                    $buttons .= '<button class="btn btn-warning btn-decline-referral" data-id="' . $ref->id . '" title="Decline Referral"><i class="mdi mdi-close-circle"></i></button>';
                    $buttons .= '<button class="btn btn-danger btn-cancel-referral" data-id="' . $ref->id . '" title="Cancel Referral"><i class="mdi mdi-delete"></i></button>';
                }

                // Print for external referrals (any status)
                if ($ref->referral_type === 'external') {
                    $buttons .= '<button class="btn btn-outline-dark btn-print-referral" data-id="' . $ref->id . '" title="Print Referral Letter"><i class="mdi mdi-printer"></i></button>';
                }

                $buttons .= '</div>';
                return $buttons;
            })
            ->rawColumns(['urgency_badge', 'type_badge', 'status_badge', 'actions'])
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

    /**
     * Get full referral detail for view/print modal.
     */
    public function getReferralDetail(SpecialistReferral $referral)
    {
        $referral->load([
            'patient.user', 'patient.hmo',
            'referringDoctor.user', 'referringClinic',
            'targetClinic', 'targetDoctor.user',
            'encounter',
        ]);

        $settings = appsettings();
        $staff = Staff::where('user_id', Auth::id())->first();
        $isTargetedAtMe = $staff && (
            $referral->target_doctor_id == $staff->id ||
            ($referral->target_clinic_id == $staff->clinic_id && !$referral->target_doctor_id)
        );

        return response()->json([
            'success' => true,
            'referral' => [
                'id'                      => $referral->id,
                'referral_type'           => $referral->referral_type,
                'status'                  => $referral->status,
                'urgency'                 => $referral->urgency,
                'reason'                  => $referral->reason,
                'clinical_summary'        => $referral->clinical_summary,
                'provisional_diagnosis'   => $referral->provisional_diagnosis,
                'patient_name'            => $referral->patient ? userfullname($referral->patient->user_id) : 'N/A',
                'patient_file_no'         => $referral->patient->file_no ?? 'N/A',
                'patient_dob'             => $referral->patient->user->dob ?? null,
                'patient_gender'          => $referral->patient->user->sex ?? null,
                'patient_phone'           => $referral->patient->user->phone ?? null,
                'patient_hmo'             => $referral->patient->hmo->name ?? 'N/A',
                'referring_doctor'        => $referral->referringDoctor ? userfullname($referral->referringDoctor->user_id) : 'N/A',
                'referring_clinic'        => $referral->referringClinic->name ?? 'N/A',
                'target_clinic'           => $referral->targetClinic->name ?? null,
                'target_doctor'           => $referral->targetDoctor ? userfullname($referral->targetDoctor->user_id) : null,
                'external_facility_name'  => $referral->external_facility_name,
                'external_doctor_name'    => $referral->external_doctor_name,
                'external_facility_address' => $referral->external_facility_address,
                'external_facility_phone' => $referral->external_facility_phone,
                'action_notes'            => $referral->action_notes,
                'is_targeted_at_me'       => $isTargetedAtMe,
                'created_at'              => $referral->created_at->format('M d, Y h:i A'),
                'actioned_at'             => $referral->actioned_at?->format('M d, Y h:i A'),
            ],
            'hospital' => [
                'name'    => $settings->site_name ?? 'Hospital',
                'address' => $settings->contact_address ?? '',
                'phones'  => $settings->contact_phones ?? '',
                'email'   => $settings->contact_email ?? '',
                'logo'    => $settings->logo ? 'data:image/jpeg;base64,' . $settings->logo : null,
            ],
        ]);
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
                'duration_minutes' => $startTime->diffInMinutes($endTime),
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

    // ─── Doctor: Accept incoming referral ──────────────────────────────

    /**
     * Accept an incoming referral – marks it as booked and returns
     * a URL so the doctor can start a new encounter for the patient.
     */
    public function acceptReferral(SpecialistReferral $referral)
    {
        if ($referral->status !== SpecialistReferral::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Only pending referrals can be accepted.'], 422);
        }

        $staff = Staff::where('user_id', Auth::id())->first();

        $referral->update([
            'status'       => SpecialistReferral::STATUS_BOOKED,
            'actioned_by'  => $staff ? $staff->id : null,
            'actioned_at'  => now(),
            'action_notes' => 'Accepted by ' . ($staff ? userfullname($staff->user_id) : 'Doctor'),
        ]);

        // Build URL to create a new encounter for this patient
        $encounterUrl = route('encounters.create', [
            'patient_id' => $referral->patient_id,
        ]);

        return response()->json([
            'success'       => true,
            'message'       => 'Referral accepted successfully.',
            'encounter_url' => $encounterUrl,
        ]);
    }

    // ─── Patient Referral History ──────────────────────────────────────

    /**
     * Get full referral history for a patient.
     */
    public function patientReferralHistory(int $patient)
    {
        $referrals = SpecialistReferral::where('patient_id', $patient)
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
