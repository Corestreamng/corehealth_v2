<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AdmissionRequest;
use App\Models\DoctorAppointment;
use App\Models\Encounter;
use App\Models\ImagingServiceRequest;
use App\Models\LabServiceRequest;
use App\Models\NursingNote;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\ProductRequest;
use App\Models\SpecialistReferral;
use App\Models\VitalSign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MobilePatientController extends Controller
{
    /**
     * Clamp per_page to a reasonable maximum to prevent abuse.
     */
    private function perPage(Request $request, int $default = 20, int $max = 100): int
    {
        return min((int) $request->input('per_page', $default), $max);
    }

    /**
     * GET /api/mobile/patient/profile
     */
    public function myProfile()
    {
        try {
            $user = Auth::user();
            $patient = Patient::with('user', 'hmo')->where('user_id', $user->id)->first();

            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient profile not found.'], 404);
            }

            return response()->json([
                'status' => true,
                'data'   => [
                    'id'               => $patient->id,
                    'name'             => $patient->user->name ?? '',
                    'email'            => $patient->user->email ?? '',
                    'file_no'          => $patient->file_no ?? '',
                    'gender'           => $patient->gender ?? '',
                    'dob'              => $patient->dob ?? '',
                    'blood_group'      => $patient->blood_group ?? '',
                    'genotype'         => $patient->genotype ?? '',
                    'phone'            => $patient->phone_no ?? '',
                    'address'          => $patient->address ?? '',
                    'nationality'      => $patient->nationality ?? '',
                    'ethnicity'        => $patient->ethnicity ?? '',
                    'disability'       => $patient->disability ?? '',
                    'allergies'        => $patient->allergies ?? [],
                    'hmo_name'         => $patient->hmo->name ?? 'N/A',
                    'hmo_no'           => $patient->hmo_no ?? '',
                    'next_of_kin_name'    => $patient->next_of_kin_name ?? '',
                    'next_of_kin_phone'   => $patient->next_of_kin_phone ?? '',
                    'next_of_kin_address' => $patient->next_of_kin_address ?? '',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile myProfile error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to load profile.'], 500);
        }
    }

    /**
     * GET /api/mobile/patient/encounters
     */
    public function myEncounters(Request $request)
    {
        try {
            $patient = $this->getAuthenticatedPatient();
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found.'], 404);
            }

            $encounters = Encounter::with('doctor', 'queue.clinic')
                ->withCount([
                    'labRequests as lab_count' => fn($q) => $q->where('status', '>', 0),
                    'imagingRequests as imaging_count' => fn($q) => $q->where('status', '>', 0),
                    'productRequests as prescription_count' => fn($q) => $q->where('status', '>', 0),
                ])
                ->where('patient_id', $patient->id)
                ->where('completed', true)
                ->orderBy('created_at', 'DESC')
                ->paginate($this->perPage($request));

            $items = $encounters->getCollection()->map(function ($enc) {
                return [
                    'id'                    => $enc->id,
                    'doctor_name'           => $enc->doctor ? $enc->doctor->name : 'Unknown',
                    'clinic_name'           => $enc->queue?->clinic?->name ?? $enc->queue?->clinic?->clinic_name ?? null,
                    'doctor_diagnosis'      => $enc->notes,
                    'reasons_for_encounter' => $enc->reasons_for_encounter,
                    'comment_1'             => $enc->reasons_for_encounter_comment_1,
                    'comment_2'             => $enc->reasons_for_encounter_comment_2,
                    'has_notes'             => !empty($enc->notes),
                    'lab_count'             => $enc->lab_count ?? 0,
                    'imaging_count'         => $enc->imaging_count ?? 0,
                    'prescription_count'    => $enc->prescription_count ?? 0,
                    'created_at'            => $enc->created_at->toIso8601String(),
                ];
            });

            return response()->json([
                'status' => true,
                'data'   => $items,
                'meta'   => [
                    'total'     => $encounters->total(),
                    'page'      => $encounters->currentPage(),
                    'per_page'  => $encounters->perPage(),
                    'last_page' => $encounters->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile myEncounters error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to load encounters.'], 500);
        }
    }

    /**
     * GET /api/mobile/patient/encounters/{encounter}
     */
    public function encounterDetail(Encounter $encounter)
    {
        try {
            $patient = $this->getAuthenticatedPatient();
            if (!$patient || $encounter->patient_id !== $patient->id) {
                return response()->json(['status' => false, 'message' => 'Unauthorized.'], 403);
            }

            $encounter->load('doctor', 'queue.clinic');

            // Clinic name via queue -> clinic
            $clinicName = $encounter->queue?->clinic?->name
                ?? $encounter->queue?->clinic?->clinic_name
                ?? null;

            // Vitals closest to encounter (patient-level, taken around encounter time)
            $vitals = VitalSign::where('patient_id', $patient->id)
                ->where('created_at', '<=', $encounter->created_at->copy()->addHours(24))
                ->where('created_at', '>=', $encounter->created_at->copy()->subHours(24))
                ->orderBy('created_at', 'DESC')
                ->limit(5)
                ->get()
                ->map(fn($v) => [
                    'id'              => $v->id,
                    'blood_pressure'  => $v->blood_pressure,
                    'temperature'     => $v->temp,
                    'heart_rate'      => $v->heart_rate,
                    'respiratory_rate' => $v->resp_rate,
                    'spo2'            => $v->spo2,
                    'weight'          => $v->weight,
                    'height'          => $v->height,
                    'bmi'             => $v->bmi,
                    'blood_sugar'     => $v->blood_sugar,
                    'pain_score'      => $v->pain_score,
                    'other_notes'     => $v->other_notes,
                    'time_taken'      => $v->time_taken,
                    'created_at'      => $v->created_at->toIso8601String(),
                ]);

            $labs = LabServiceRequest::with('service')
                ->where('encounter_id', $encounter->id)
                ->where('status', '>', 0)
                ->orderBy('created_at', 'DESC')
                ->get()
                ->map(fn($lab) => [
                    'id'           => $lab->id,
                    'service_name' => $lab->service->service_name ?? 'Unknown',
                    'status'       => (int) $lab->status,
                    'status_label' => $this->labStatusLabel($lab->status),
                    'result'       => $lab->result,
                    'result_data'  => $lab->result_data,
                    'result_date'  => $lab->result_date,
                    'note'         => $lab->note,
                    'priority'     => $lab->priority,
                    'attachments'  => $lab->attachments ?? [],
                    'created_at'   => $lab->created_at->toIso8601String(),
                ]);

            $imaging = ImagingServiceRequest::with('service')
                ->where('encounter_id', $encounter->id)
                ->where('status', '>', 0)
                ->orderBy('created_at', 'DESC')
                ->get()
                ->map(fn($img) => [
                    'id'           => $img->id,
                    'service_name' => $img->service->service_name ?? 'Unknown',
                    'status'       => (int) $img->status,
                    'status_label' => $this->imagingStatusLabel($img->status),
                    'result'       => $img->result,
                    'result_data'  => $img->result_data,
                    'result_date'  => $img->result_date,
                    'note'         => $img->note,
                    'priority'     => $img->priority,
                    'attachments'  => $img->attachments ?? [],
                    'created_at'   => $img->created_at->toIso8601String(),
                ]);

            $prescriptions = ProductRequest::with('product')
                ->where('encounter_id', $encounter->id)
                ->where('status', '>', 0)
                ->orderBy('created_at', 'DESC')
                ->get()
                ->map(fn($rx) => [
                    'id'           => $rx->id,
                    'product_name' => $rx->product->product_name ?? 'Unknown',
                    'dose'         => $rx->dose,
                    'qty'          => $rx->qty,
                    'status'       => (int) $rx->status,
                    'status_label' => $this->prescriptionStatusLabel($rx->status),
                    'created_at'   => $rx->created_at->toIso8601String(),
                ]);

            $procedures = Procedure::with('service')
                ->where('encounter_id', $encounter->id)
                ->orderBy('requested_on', 'DESC')
                ->get()
                ->map(fn($p) => [
                    'id'               => $p->id,
                    'service_name'     => $p->service->service_name ?? 'Unknown',
                    'priority'         => $p->priority,
                    'procedure_status' => $p->procedure_status,
                    'status_label'     => ucfirst(str_replace('_', ' ', $p->procedure_status ?? '')),
                    'scheduled_date'   => $p->scheduled_date,
                    'scheduled_time'   => $p->scheduled_time,
                    'outcome'          => $p->outcome,
                    'outcome_notes'    => $p->outcome_notes,
                    'pre_notes'        => $p->pre_notes,
                    'post_notes'       => $p->post_notes,
                    'operating_room'   => $p->operating_room,
                    'created_at'       => $p->created_at->toIso8601String(),
                ]);

            // Referrals for this encounter
            $referrals = SpecialistReferral::with('referringDoctor', 'targetDoctor', 'referringClinic', 'targetClinic')
                ->where('encounter_id', $encounter->id)
                ->orderBy('created_at', 'DESC')
                ->get()
                ->map(fn($r) => [
                    'id'                    => $r->id,
                    'referral_type'         => $r->referral_type,
                    'from_doctor'           => $r->referringDoctor?->name ?? 'Unknown',
                    'from_clinic'           => $r->referringClinic?->name ?? $r->referringClinic?->clinic_name ?? '',
                    'to_doctor'             => $r->targetDoctor?->name ?? $r->external_doctor_name ?? '',
                    'to_clinic'             => $r->targetClinic?->name ?? $r->targetClinic?->clinic_name ?? $r->external_facility_name ?? '',
                    'reason'                => $r->reason,
                    'clinical_summary'      => $r->clinical_summary,
                    'provisional_diagnosis' => $r->provisional_diagnosis,
                    'urgency'               => $r->urgency,
                    'status'                => $r->status,
                    'action_notes'          => $r->action_notes,
                    'created_at'            => $r->created_at->toIso8601String(),
                ]);

            // Nursing notes for this patient (not encounter-linked)
            $nursingNotes = NursingNote::with('createdBy', 'type')
                ->where('patient_id', $patient->id)
                ->where('created_at', '<=', $encounter->created_at->copy()->addHours(48))
                ->where('created_at', '>=', $encounter->created_at->copy()->subHours(24))
                ->orderBy('created_at', 'DESC')
                ->limit(20)
                ->get()
                ->map(fn($n) => [
                    'id'         => $n->id,
                    'note'       => $n->note,
                    'type'       => $n->type?->name ?? 'General',
                    'created_by' => $n->createdBy?->name ?? 'Unknown',
                    'created_at' => $n->created_at->toIso8601String(),
                ]);

            // Admission for this encounter
            $admission = AdmissionRequest::with('doctor', 'bed')
                ->where('encounter_id', $encounter->id)
                ->first();

            $admissionData = null;
            if ($admission) {
                $admissionData = [
                    'id'               => $admission->id,
                    'doctor_name'      => $admission->doctor?->name ?? 'Unknown',
                    'admission_status' => $admission->admission_status ?? $admission->status,
                    'admission_reason' => $admission->admission_reason,
                    'discharge_reason' => $admission->discharge_reason,
                    'discharged'       => (bool) $admission->discharged,
                    'discharge_date'   => $admission->discharge_date,
                    'priority'         => $admission->priority,
                    'bed_info'         => $admission->bed ? ($admission->bed->name ?? 'Assigned') : 'Pending',
                    'days_admitted'    => $admission->days_admitted ?? null,
                    'created_at'       => $admission->created_at->toIso8601String(),
                ];
            }

            return response()->json([
                'status' => true,
                'data'   => [
                    'encounter' => [
                        'id'                    => $encounter->id,
                        'doctor_name'           => $encounter->doctor ? $encounter->doctor->name : 'Unknown',
                        'clinic_name'           => $clinicName,
                        'doctor_diagnosis'      => $encounter->notes,
                        'reasons_for_encounter' => $encounter->reasons_for_encounter,
                        'comment_1'             => $encounter->reasons_for_encounter_comment_1,
                        'comment_2'             => $encounter->reasons_for_encounter_comment_2,
                        'notes'                 => $encounter->notes,
                        'started_at'            => $encounter->started_at,
                        'completed_at'          => $encounter->completed_at,
                        'created_at'            => $encounter->created_at->toIso8601String(),
                    ],
                    'vitals'        => $vitals,
                    'labs'          => $labs,
                    'imaging'       => $imaging,
                    'prescriptions' => $prescriptions,
                    'procedures'    => $procedures,
                    'referrals'     => $referrals,
                    'nursing_notes' => $nursingNotes,
                    'admission'     => $admissionData,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile patient encounterDetail error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to load encounter detail.'], 500);
        }
    }

    /**
     * GET /api/mobile/patient/vitals
     */
    public function myVitals(Request $request)
    {
        try {
            $patient = $this->getAuthenticatedPatient();
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found.'], 404);
            }

            $vitals = VitalSign::where('patient_id', $patient->id)
                ->orderBy('created_at', 'DESC')
                ->paginate($this->perPage($request));

            $items = $vitals->getCollection()->map(fn($v) => [
                'id'              => $v->id,
                'blood_pressure'  => $v->blood_pressure,
                'temperature'     => $v->temp,
                'heart_rate'      => $v->heart_rate,
                'respiratory_rate' => $v->resp_rate,
                'spo2'            => $v->spo2,
                'weight'          => $v->weight,
                'height'          => $v->height,
                'bmi'             => $v->bmi,
                'blood_sugar'     => $v->blood_sugar,
                'pain_score'      => $v->pain_score,
                'other_notes'     => $v->other_notes,
                'time_taken'      => $v->time_taken,
                'created_at'      => $v->created_at->toIso8601String(),
            ]);

            return response()->json([
                'status' => true,
                'data'   => $items,
                'meta'   => [
                    'total'     => $vitals->total(),
                    'page'      => $vitals->currentPage(),
                    'per_page'  => $vitals->perPage(),
                    'last_page' => $vitals->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile myVitals error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to load vitals.'], 500);
        }
    }

    /**
     * GET /api/mobile/patient/lab-results
     */
    public function myLabResults(Request $request)
    {
        try {
            $patient = $this->getAuthenticatedPatient();
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found.'], 404);
            }

            $labs = LabServiceRequest::with('service')
                ->where('patient_id', $patient->id)
                ->where('status', '>', 0)
                ->orderBy('created_at', 'DESC')
                ->paginate($this->perPage($request));

            $items = $labs->getCollection()->map(fn($lab) => [
                'id'           => $lab->id,
                'service_name' => $lab->service->service_name ?? 'Unknown',
                'status'       => (int) $lab->status,
                'status_label' => $this->labStatusLabel($lab->status),
                'result'       => $lab->result,
                'result_date'  => $lab->result_date,
                'created_at'   => $lab->created_at->toIso8601String(),
            ]);

            return response()->json([
                'status' => true,
                'data'   => $items,
                'meta'   => [
                    'total'     => $labs->total(),
                    'page'      => $labs->currentPage(),
                    'per_page'  => $labs->perPage(),
                    'last_page' => $labs->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile myLabResults error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to load lab results.'], 500);
        }
    }

    /**
     * GET /api/mobile/patient/imaging-results
     */
    public function myImagingResults(Request $request)
    {
        try {
            $patient = $this->getAuthenticatedPatient();
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found.'], 404);
            }

            $imaging = ImagingServiceRequest::with('service')
                ->where('patient_id', $patient->id)
                ->where('status', '>', 0)
                ->orderBy('created_at', 'DESC')
                ->paginate($this->perPage($request));

            $items = $imaging->getCollection()->map(fn($img) => [
                'id'           => $img->id,
                'service_name' => $img->service->service_name ?? 'Unknown',
                'status'       => (int) $img->status,
                'status_label' => $this->imagingStatusLabel($img->status),
                'result'       => $img->result,
                'result_date'  => $img->result_date,
                'created_at'   => $img->created_at->toIso8601String(),
            ]);

            return response()->json([
                'status' => true,
                'data'   => $items,
                'meta'   => [
                    'total'     => $imaging->total(),
                    'page'      => $imaging->currentPage(),
                    'per_page'  => $imaging->perPage(),
                    'last_page' => $imaging->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile myImagingResults error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to load imaging results.'], 500);
        }
    }

    /**
     * GET /api/mobile/patient/prescriptions
     */
    public function myPrescriptions(Request $request)
    {
        try {
            $patient = $this->getAuthenticatedPatient();
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found.'], 404);
            }

            $prescriptions = ProductRequest::with('product')
                ->where('patient_id', $patient->id)
                ->orderBy('created_at', 'DESC')
                ->paginate($this->perPage($request));

            $items = $prescriptions->getCollection()->map(fn($rx) => [
                'id'           => $rx->id,
                'product_name' => $rx->product->product_name ?? 'Unknown',
                'dose'         => $rx->dose,
                'qty'          => $rx->qty,
                'status'       => (int) $rx->status,
                'status_label' => $this->prescriptionStatusLabel($rx->status),
                'created_at'   => $rx->created_at->toIso8601String(),
            ]);

            return response()->json([
                'status' => true,
                'data'   => $items,
                'meta'   => [
                    'total'     => $prescriptions->total(),
                    'page'      => $prescriptions->currentPage(),
                    'per_page'  => $prescriptions->perPage(),
                    'last_page' => $prescriptions->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile myPrescriptions error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to load prescriptions.'], 500);
        }
    }

    /**
     * GET /api/mobile/patient/procedures
     */
    public function myProcedures(Request $request)
    {
        try {
            $patient = $this->getAuthenticatedPatient();
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found.'], 404);
            }

            $procedures = Procedure::with('service')
                ->where('patient_id', $patient->id)
                ->orderBy('requested_on', 'DESC')
                ->paginate($this->perPage($request));

            $items = $procedures->getCollection()->map(fn($p) => [
                'id'               => $p->id,
                'service_name'     => $p->service->service_name ?? 'Unknown',
                'priority'         => $p->priority,
                'procedure_status' => $p->procedure_status,
                'status_label'     => ucfirst(str_replace('_', ' ', $p->procedure_status ?? '')),
                'scheduled_date'   => $p->scheduled_date,
                'outcome'          => $p->outcome,
                'created_at'       => $p->created_at->toIso8601String(),
            ]);

            return response()->json([
                'status' => true,
                'data'   => $items,
                'meta'   => [
                    'total'     => $procedures->total(),
                    'page'      => $procedures->currentPage(),
                    'per_page'  => $procedures->perPage(),
                    'last_page' => $procedures->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile myProcedures error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to load procedures.'], 500);
        }
    }

    /**
     * GET /api/mobile/patient/admissions
     */
    public function myAdmissions(Request $request)
    {
        try {
            $patient = $this->getAuthenticatedPatient();
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found.'], 404);
            }

            $admissions = AdmissionRequest::with('doctor', 'bed')
                ->where('patient_id', $patient->id)
                ->orderBy('created_at', 'DESC')
                ->paginate($this->perPage($request));

            $items = $admissions->getCollection()->map(fn($a) => [
                'id'               => $a->id,
                'doctor_name'      => $a->doctor ? $a->doctor->name : 'Unknown',
                'admission_status' => $a->admission_status ?? $a->status,
                'admission_reason' => $a->admission_reason,
                'discharge_reason' => $a->discharge_reason,
                'discharged'       => (bool) $a->discharged,
                'discharge_date'   => $a->discharge_date,
                'priority'         => $a->priority,
                'bed_info'         => $a->bed ? ($a->bed->name ?? 'Assigned') : 'Pending',
                'days_admitted'    => $a->days_admitted ?? null,
                'created_at'       => $a->created_at->toIso8601String(),
            ]);

            return response()->json([
                'status' => true,
                'data'   => $items,
                'meta'   => [
                    'total'     => $admissions->total(),
                    'page'      => $admissions->currentPage(),
                    'per_page'  => $admissions->perPage(),
                    'last_page' => $admissions->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile myAdmissions error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to load admissions.'], 500);
        }
    }

    /**
     * GET /api/mobile/patient/referrals
     */
    public function myReferrals(Request $request)
    {
        try {
            $patient = $this->getAuthenticatedPatient();
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found.'], 404);
            }

            $referrals = SpecialistReferral::with('referringDoctor', 'targetDoctor', 'referringClinic', 'targetClinic')
                ->where('patient_id', $patient->id)
                ->orderBy('created_at', 'DESC')
                ->paginate($this->perPage($request));

            $items = $referrals->getCollection()->map(fn($r) => [
                'id'                    => $r->id,
                'referral_type'         => $r->referral_type,
                'from_doctor'           => $r->referringDoctor?->name ?? 'Unknown',
                'from_clinic'           => $r->referringClinic?->name ?? $r->referringClinic?->clinic_name ?? '',
                'to_doctor'             => $r->targetDoctor?->name ?? $r->external_doctor_name ?? '',
                'to_clinic'             => $r->targetClinic?->name ?? $r->targetClinic?->clinic_name ?? $r->external_facility_name ?? '',
                'reason'                => $r->reason,
                'clinical_summary'      => $r->clinical_summary,
                'provisional_diagnosis' => $r->provisional_diagnosis,
                'urgency'               => $r->urgency,
                'status'                => $r->status,
                'action_notes'          => $r->action_notes,
                'created_at'            => $r->created_at->toIso8601String(),
            ]);

            return response()->json([
                'status' => true,
                'data'   => $items,
                'meta'   => [
                    'total'     => $referrals->total(),
                    'page'      => $referrals->currentPage(),
                    'per_page'  => $referrals->perPage(),
                    'last_page' => $referrals->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile myReferrals error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to load referrals.'], 500);
        }
    }

    /**
     * GET /api/mobile/patient/appointments
     */
    public function myAppointments(Request $request)
    {
        try {
            $patient = $this->getAuthenticatedPatient();
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found.'], 404);
            }

            $filter = $request->input('filter', 'all'); // all, upcoming, past

            $query = DoctorAppointment::with('doctor', 'clinic')
                ->where('patient_id', $patient->id);

            if ($filter === 'upcoming') {
                $query->where('appointment_date', '>=', now()->toDateString())
                      ->whereIn('status', ['scheduled', 'checked_in', 'rescheduled']);
            } elseif ($filter === 'past') {
                $query->where(function ($q) {
                    $q->where('appointment_date', '<', now()->toDateString())
                       ->orWhereIn('status', ['completed', 'cancelled', 'no_show']);
                });
            }

            $appointments = $query->orderBy('appointment_date', 'DESC')
                ->orderBy('start_time', 'DESC')
                ->paginate($this->perPage($request));

            $items = $appointments->getCollection()->map(fn($a) => [
                'id'                => $a->id,
                'doctor_name'       => $a->doctor?->name ?? 'Unknown',
                'clinic_name'       => $a->clinic?->name ?? $a->clinic?->clinic_name ?? '',
                'appointment_date'  => $a->appointment_date,
                'start_time'        => $a->start_time,
                'end_time'          => $a->end_time,
                'duration_minutes'  => $a->duration_minutes,
                'status'            => $a->status,
                'priority'          => $a->priority,
                'appointment_type'  => $a->appointment_type,
                'reason'            => $a->reason,
                'notes'             => $a->notes,
                'cancellation_reason' => $a->cancellation_reason,
                'created_at'        => $a->created_at->toIso8601String(),
            ]);

            return response()->json([
                'status' => true,
                'data'   => $items,
                'meta'   => [
                    'total'     => $appointments->total(),
                    'page'      => $appointments->currentPage(),
                    'per_page'  => $appointments->perPage(),
                    'last_page' => $appointments->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile myAppointments error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to load appointments.'], 500);
        }
    }

    // ── Private helpers ─────────────────────────────────────────────

    private function getAuthenticatedPatient(): ?Patient
    {
        $user = Auth::user();
        return Patient::where('user_id', $user->id)->first();
    }

    private function labStatusLabel($status): string
    {
        return match ((int) $status) {
            0 => 'Dismissed',
            1 => 'Requested',
            2 => 'Billed/Sample',
            3 => 'Results Ready',
            default => 'Unknown',
        };
    }

    private function imagingStatusLabel($status): string
    {
        return match ((int) $status) {
            0 => 'Dismissed',
            1 => 'Requested',
            2 => 'Billed',
            3 => 'Results Ready',
            default => 'Unknown',
        };
    }

    private function prescriptionStatusLabel($status): string
    {
        return match ((int) $status) {
            0 => 'Dismissed',
            1 => 'Requested',
            2 => 'Billed',
            3 => 'Dispensed',
            default => 'Unknown',
        };
    }

    // ═══════════════════════════════════════════════════════════════
    //  Patient Profile Update & Password Change
    // ═══════════════════════════════════════════════════════════════

    /**
     * PUT /api/mobile/patient/profile
     * Updates the patient's editable fields.
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_no'            => 'nullable|string|max:20',
            'address'             => 'nullable|string|max:500',
            'next_of_kin_name'    => 'nullable|string|max:255',
            'next_of_kin_phone'   => 'nullable|string|max:20',
            'next_of_kin_address' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $patient = $this->getAuthenticatedPatient();
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found.'], 404);
            }

            $fields = ['phone_no', 'address', 'next_of_kin_name', 'next_of_kin_phone', 'next_of_kin_address'];
            foreach ($fields as $field) {
                if ($request->has($field)) {
                    $patient->$field = $request->input($field);
                }
            }
            $patient->save();

            return response()->json(['status' => true, 'message' => 'Profile updated successfully.']);
        } catch (\Exception $e) {
            Log::error('Mobile patient updateProfile error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to update profile.'], 500);
        }
    }

    /**
     * POST /api/mobile/patient/change-password
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = Auth::user();

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json(['status' => false, 'message' => 'Current password is incorrect.'], 422);
            }

            $user->update(['password' => Hash::make($request->password)]);

            return response()->json(['status' => true, 'message' => 'Password updated successfully.']);
        } catch (\Exception $e) {
            Log::error('Mobile patient changePassword error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to change password.'], 500);
        }
    }
}
