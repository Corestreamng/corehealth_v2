<?php

namespace App\Http\Controllers\API;

use App\Enums\WeCare\ClinicalCategory;
use App\Enums\WeCare\ClinicalPriority;
use App\Enums\WeCare\ImagingStatus;
use App\Enums\WeCare\LabStatus;
use App\Enums\WeCare\PrescriptionStatus;
use App\Enums\WeCare\TimelineType;
use App\Http\Controllers\Controller;
use App\Models\AdmissionRequest;
use App\Models\Encounter;
use App\Models\ImagingServiceRequest;
use App\Models\LabServiceRequest;
use App\Models\MedicationAdministration;
use App\Models\NursingNote;
use App\Models\Procedure;
use App\Models\ProductRequest;
use App\Models\SpecialistReferral;
use App\Models\VitalSign;
use App\Support\WeCare\ClinicalStoryDto;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * WeCare247 ↔ CoreHealth v2 Bidirectional Integration Controller
 *
 * Provides:
 *  - Patient lookup for WeCare247 invite auto-fill
 *  - Patient-linked callback to activate sync
 *  - Tele-consultation encounter ingestion into hospital EHR
 *  - Lab result webhook dispatch to WeCare247
 *
 * Auth: SHA-256 Basic Auth (reuses existing auth.basic.sha256 middleware)
 *       All endpoints require the WeCare facility to present:
 *       Authorization: Basic base64(clientId:clientSecret)
 */
class WeCareIntegrationController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    // PUBLIC LIVENESS PROBE (no auth — mirrors /api/corehms/ping pattern)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/corehealth/ping
     *
     * Ultra-fast liveness check. Returns facility metadata and software version.
     * Must never be blocked by session middleware or heavy queries.
     */
    public function ping(Request $request)
    {
        $start = microtime(true);

        // Lightweight DB touch to confirm connectivity
        try {
            DB::select('SELECT 1');
            $dbOk = true;
        } catch (\Throwable $e) {
            $dbOk = false;
        }

        $latency = round((microtime(true) - $start) * 1000, 2);

        return response()->json([
            'status' => true,
            'wecare_enabled' => (bool) appsettings('wecare_enabled', false),
            'facility_id' => (int) appsettings('facility_id', 0),
            'facility' => appsettings('hospital_name', config('app.name')),
            'version' => '2.5',
            'db_ok' => $dbOk,
            'latency_ms' => $latency,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // PATIENT LOOKUP  (auth required)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/corehealth/patient/lookup
     *
     * Looks up a patient by file_no OR phone_no so that the WeCare247 Facility
     * Admin can autofill the invite modal from the hospital record.
     *
     * Query params (at least one required):
     *   ?file_no=CH/2026/0491
     *   ?phone_no=08012345678
     *
     * Returns a slim demographic payload — NO clinical data.
     */
    public function lookupPatient(Request $request)
    {
        if (!appsettings('wecare_enabled', false)) {
            return response()->json([
                'status' => false,
                'message' => 'WeCare247 integration is currently disabled in Hospital Settings.',
                'code' => 'WECARE_INTEGRATION_DISABLED',
            ], 403);
        }

        if (!appsettings('wecare_allow_patient_lookup', true)) {
            return response()->json([
                'status' => false,
                'message' => 'Patient demographic lookup by WeCare247 is disabled in Hospital Settings.',
                'code' => 'PATIENT_LOOKUP_DISABLED',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'file_no' => 'required_without:phone_no|string|max:64',
            'phone_no' => 'required_without:file_no|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Provide either file_no or phone_no.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = DB::table('patients')
            ->leftJoin('users', 'patients.user_id', '=', 'users.id');

        if ($request->filled('file_no')) {
            $query->where('patients.file_no', $request->file_no);
        } else {
            $query->where('patients.phone_no', $request->phone_no);
        }

        $patient = $query->select([
            'patients.id',
            'patients.file_no',
            'users.surname',
            'users.firstname',
            'users.othername',
            'users.email',
            'patients.phone_no',
            'patients.dob as date_of_birth',
            'patients.gender',
            'patients.address',
            'patients.wecare_patient_id',
            'patients.wecare_sync_uuid',
            'patients.wecare_sync_active',
        ])->first();

        if (!$patient) {
            return response()->json([
                'status' => false,
                'message' => 'Patient not found.',
            ], 404);
        }

        $facilityId = (int) appsettings('facility_id', 0);
        $syncUuid = $patient->wecare_sync_uuid
            ?: sha1($facilityId . ':' . $patient->id . ':' . $patient->file_no);

        return response()->json([
            'status' => true,
            'patient' => [
                'id' => $patient->id,
                'file_no' => $patient->file_no,
                'fullname' => trim($patient->surname . ' ' . $patient->firstname . ' ' . ($patient->othername ?? '')),
                'email' => $patient->email,
                'phone' => $patient->phone_no,
                'date_of_birth' => $patient->date_of_birth,
                'gender' => $patient->gender,
                'address' => $patient->address,
                'corehealth_sync_uuid' => $syncUuid,
                'already_linked' => (bool) $patient->wecare_sync_active,
                'wecare_patient_id' => $patient->wecare_patient_id,
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CLINICAL STORY / EHR TIMELINE LOOKUP  (auth required)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/corehealth/patient/clinical-story
     *
     * Returns the comprehensive longitudinal clinical story for a patient:
     *  - Consultations & Diagnoses (ICD-10 / ICPC-2 codes, notes, outcomes)
     *  - Vital Signs (BP, pulse, temp, respiratory rate, SpO2, blood sugar, BMI, pain)
     *  - Nursing Notes (nursing shift observations & progress notes)
     *  - Laboratory Investigations (requests, samples, results, reference ranges)
     *  - Imaging / Radiology (scans, impressions, findings)
     *  - Prescriptions & Medication Requests (dose, qty, status, dispensing info)
     *  - Procedures & Surgeries (pre/post-op notes, outcomes, OR)
     *  - Ward Admissions (bed assignments, reasons, discharge notes)
     *  - Specialist Referrals (internal & external referrals)
     *  - Medication Administrations (administered doses, routes, timings)
     *  - Unified Chronological Timeline
     *
     * Query params:
     *   ?file_no=...  OR  ?patient_id=...  OR  ?phone_no=...
     *   ?category=all|encounters|vitals|nursing_notes|labs|imaging|prescriptions|procedures|admissions|referrals|med_admin
     *   ?limit=100
     */
    public function getClinicalStory(Request $request)
    {
        if (!appsettings('wecare_enabled', false)) {
            return response()->json([
                'status' => false,
                'message' => 'WeCare247 integration is currently disabled in Hospital Settings.',
                'code' => 'WECARE_INTEGRATION_DISABLED',
            ], 403);
        }

        $validCategories = implode(',', array_column(ClinicalCategory::cases(), 'value'));

        $validator = Validator::make($request->all(), [
            'file_no' => 'required_without_all:patient_id,phone_no|string|max:64',
            'patient_id' => 'required_without_all:file_no,phone_no|integer',
            'phone_no' => 'required_without_all:file_no,patient_id|string|max:20',
            'category' => 'nullable|string|in:' . $validCategories,
            'limit' => 'nullable|integer|min:1|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Provide at least one of: file_no, patient_id, or phone_no.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $patientQuery = DB::table('patients')
            ->leftJoin('users', 'patients.user_id', '=', 'users.id');

        if ($request->filled('file_no')) {
            $patientQuery->where('patients.file_no', $request->file_no);
        } elseif ($request->filled('patient_id')) {
            $patientQuery->where('patients.id', (int) $request->patient_id);
        } else {
            $patientQuery->where('patients.phone_no', $request->phone_no);
        }

        $patient = $patientQuery->select([
            'patients.id',
            'patients.file_no',
            'users.surname',
            'users.firstname',
            'users.othername',
            'users.email',
            'patients.phone_no',
            'patients.dob as date_of_birth',
            'patients.gender',
            'patients.address',
            'patients.blood_group',
            'patients.genotype',
            'patients.wecare_patient_id',
            'patients.wecare_sync_uuid',
            'patients.wecare_sync_active',
        ])->first();

        if (!$patient) {
            return response()->json([
                'status' => false,
                'message' => 'Patient not found.',
                'code' => 'PATIENT_NOT_FOUND',
            ], 404);
        }

        $facilityId = (int) appsettings('facility_id', 0);
        $syncUuid = $patient->wecare_sync_uuid
            ?: sha1($facilityId . ':' . $patient->id . ':' . $patient->file_no);

        $limit = max(1, min((int) $request->input('limit', 100), 500));
        $categoryFilter = $request->input('category', 'all');
        $include = fn ($cat) => in_array($categoryFilter, ['all', $cat]);

        // 1. Encounters / Consultations & Diagnoses
        $encounters = [];
        if ($include('encounters')) {
            $rawEncounters = Encounter::where('patient_id', $patient->id)
                ->with(['doctor', 'service'])
                ->orderByRaw('COALESCE(started_at, created_at) DESC')
                ->take($limit)
                ->get();

            foreach ($rawEncounters as $item) {
                $anchor = $item->started_at ?? $item->created_at;
                $docName = $item->doctor ? userfullname($item->doctor->id) : 'Any Doctor';
                $clinic = $item->service ? $item->service->service_name : 'Consultation';

                $diagnoses = [];
                if (!empty($item->reasons_for_encounter)) {
                    $decoded = json_decode($item->reasons_for_encounter, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $d) {
                            if (is_array($d)) {
                                $diagnoses[] = [
                                    'code' => $d['code'] ?? '',
                                    'name' => $d['name'] ?? '',
                                    'status' => $d['comment_1'] ?? '',
                                    'course' => $d['comment_2'] ?? '',
                                ];
                            } elseif (is_string($d) && trim($d) !== '') {
                                $diagnoses[] = [
                                    'code' => '',
                                    'name' => trim($d),
                                    'status' => '',
                                    'course' => '',
                                ];
                            }
                        }
                    } else {
                        foreach (explode(',', $item->reasons_for_encounter) as $r) {
                            $tr = trim($r);
                            if ($tr !== '') {
                                $diagnoses[] = ['code' => '', 'name' => $tr, 'status' => '', 'course' => ''];
                            }
                        }
                    }
                }

                $dxNames = array_filter(array_map(fn ($d) => $d['name'] ?: $d['code'], $diagnoses));
                $dxSummary = !empty($dxNames) ? implode('; ', $dxNames) : 'Routine Clinical Review';

                $encounters[] = [
                    'id' => $item->id,
                    'date' => $anchor ? Carbon::parse($anchor)->toIso8601String() : null,
                    'date_formatted' => $anchor ? Carbon::parse($anchor)->format('M d, Y H:i') : null,
                    'doctor_name' => $docName,
                    'clinic' => $clinic,
                    'status' => $item->completed ? 'Completed' : 'In Progress',
                    'outcome' => $item->outcome ?? 'discharged',
                    'diagnoses' => $diagnoses,
                    'diagnoses_summary' => $dxSummary,
                    'reasons_raw' => $item->reasons_for_encounter,
                    'comment_1' => $item->reasons_for_encounter_comment_1,
                    'comment_2' => $item->reasons_for_encounter_comment_2,
                    'clinical_notes' => $item->notes ?: '',
                    'wecare_sync_ref' => $item->wecare_sync_ref,
                ];
            }
        }

        // 2. Vital Signs
        $vitals = [];
        if ($include('vitals')) {
            $rawVitals = VitalSign::where('patient_id', $patient->id)
                ->with('takenBy')
                ->orderBy('time_taken', 'desc')
                ->take($limit)
                ->get();

            foreach ($rawVitals as $item) {
                $takenBy = $item->takenBy ? userfullname($item->takenBy->id) : 'N/A';
                $t = $item->time_taken ? Carbon::parse($item->time_taken) : null;

                $vitals[] = [
                    'id' => $item->id,
                    'time_taken' => $t ? $t->toIso8601String() : null,
                    'date_formatted' => $t ? $t->format('M d, Y H:i') : null,
                    'blood_pressure' => $item->blood_pressure,
                    'temperature' => $item->temp,
                    'heart_rate' => $item->heart_rate,
                    'resp_rate' => $item->resp_rate,
                    'spo2' => $item->spo2,
                    'weight' => $item->weight,
                    'height' => $item->height,
                    'bmi' => $item->bmi,
                    'blood_sugar' => $item->blood_sugar,
                    'pain_score' => $item->pain_score,
                    'other_notes' => $item->other_notes ?: '',
                    'taken_by' => $takenBy,
                ];
            }
        }

        // 3. Nursing Notes
        $nursingNotes = [];
        if ($include('nursing_notes')) {
            $rawNotes = NursingNote::where('patient_id', $patient->id)
                ->with(['createdBy', 'type'])
                ->orderBy('created_at', 'desc')
                ->take($limit)
                ->get();

            foreach ($rawNotes as $item) {
                $nurse = $item->createdBy ? userfullname($item->createdBy->id) : 'N/A';
                $typeName = $item->type ? $item->type->name : 'General Note';
                $t = $item->created_at ? Carbon::parse($item->created_at) : null;

                $nursingNotes[] = [
                    'id' => $item->id,
                    'date' => $t ? $t->toIso8601String() : null,
                    'date_formatted' => $t ? $t->format('M d, Y H:i') : null,
                    'type' => $typeName,
                    'note' => $item->note ?: '',
                    'nurse_name' => $nurse,
                    'completed' => (bool) $item->completed,
                ];
            }
        }

        // 4. Laboratory Tests & Results
        $labs = [];
        if ($include('labs')) {
            $rawLabs = LabServiceRequest::where('patient_id', $patient->id)
                ->with(['service', 'doctor'])
                ->orderBy('created_at', 'desc')
                ->take($limit)
                ->get();

            foreach ($rawLabs as $item) {
                $t = $item->created_at ? Carbon::parse($item->created_at) : null;
                $doc = $item->doctor ? userfullname($item->doctor->id) : 'N/A';
                $resultBy = $item->result_by ? userfullname($item->result_by) : null;
                $name = $item->service ? $item->service->service_name : ($item->free_form_name ?: 'Laboratory Test');
                $code = $item->service ? $item->service->service_code : null;

                $resultData = null;
                if (!empty($item->result_data)) {
                    $resultData = is_string($item->result_data) ? json_decode($item->result_data, true) : $item->result_data;
                }

                $labs[] = [
                    'id' => $item->id,
                    'test_name' => $name,
                    'test_code' => $code,
                    'status' => LabStatus::fromCode($item->status),
                    'status_code' => (int) $item->status,
                    'priority' => ClinicalPriority::fromString($item->priority)->value,
                    'requested_date' => $t ? $t->toIso8601String() : null,
                    'date_formatted' => $t ? $t->format('M d, Y H:i') : null,
                    'doctor_name' => $doc,
                    'sample_taken' => (bool) $item->sample_taken,
                    'sample_date' => $item->sample_date ? Carbon::parse($item->sample_date)->toIso8601String() : null,
                    'result_date' => $item->result_date ? Carbon::parse($item->result_date)->toIso8601String() : null,
                    'result_by' => $resultBy,
                    'note' => $item->note ?: '',
                    'result' => $item->result ?: '',
                    'result_data' => $resultData,
                    'approved_at' => $item->approved_at ? Carbon::parse($item->approved_at)->toIso8601String() : null,
                ];
            }
        }

        // 5. Imaging / Radiology
        $imaging = [];
        if ($include('imaging')) {
            $rawImaging = ImagingServiceRequest::where('patient_id', $patient->id)
                ->with(['service', 'doctor'])
                ->orderBy('created_at', 'desc')
                ->take($limit)
                ->get();

            foreach ($rawImaging as $item) {
                $t = $item->created_at ? Carbon::parse($item->created_at) : null;
                $doc = $item->doctor ? userfullname($item->doctor->id) : 'N/A';
                $resultBy = $item->result_by ? userfullname($item->result_by) : null;
                $name = $item->service ? $item->service->service_name : ($item->free_form_name ?: 'Imaging / Radiology');
                $code = $item->service ? $item->service->service_code : null;

                $resultData = null;
                if (!empty($item->result_data)) {
                    $resultData = is_string($item->result_data) ? json_decode($item->result_data, true) : $item->result_data;
                }

                $imaging[] = [
                    'id' => $item->id,
                    'test_name' => $name,
                    'test_code' => $code,
                    'status' => ImagingStatus::fromCode($item->status),
                    'status_code' => (int) $item->status,
                    'priority' => ClinicalPriority::fromString($item->priority)->value,
                    'requested_date' => $t ? $t->toIso8601String() : null,
                    'date_formatted' => $t ? $t->format('M d, Y H:i') : null,
                    'doctor_name' => $doc,
                    'result_date' => $item->result_date ? Carbon::parse($item->result_date)->toIso8601String() : null,
                    'result_by' => $resultBy,
                    'note' => $item->note ?: '',
                    'result' => $item->result ?: '',
                    'result_data' => $resultData,
                    'approved_at' => $item->approved_at ? Carbon::parse($item->approved_at)->toIso8601String() : null,
                ];
            }
        }

        // 6. Prescriptions / Medications
        $prescriptions = [];
        if ($include('prescriptions')) {
            $rawPrescriptions = ProductRequest::where('patient_id', $patient->id)
                ->with(['product', 'doctor'])
                ->orderBy('created_at', 'desc')
                ->take($limit)
                ->get();

            foreach ($rawPrescriptions as $item) {
                $t = $item->created_at ? Carbon::parse($item->created_at) : null;
                $doc = $item->doctor ? userfullname($item->doctor->id) : 'N/A';
                $dispensedBy = $item->dispensed_by ? userfullname($item->dispensed_by) : null;
                $drugName = $item->product ? $item->product->product_name : ($item->free_form_name ?: 'Medication');
                $productCode = $item->product ? ($item->product->product_code ?? '') : '';

                $prescriptions[] = [
                    'id' => $item->id,
                    'drug_name' => $drugName,
                    'product_code' => $productCode,
                    'dose' => $item->dose ?: '',
                    'qty' => $item->qty,
                    'status' => PrescriptionStatus::fromCode($item->status),
                    'status_code' => (int) $item->status,
                    'requested_date' => $t ? $t->toIso8601String() : null,
                    'date_formatted' => $t ? $t->format('M d, Y H:i') : null,
                    'doctor_name' => $doc,
                    'dispensed_at' => $item->dispense_date ? Carbon::parse($item->dispense_date)->toIso8601String() : null,
                    'dispensed_by' => $dispensedBy,
                ];
            }
        }

        // 7. Procedures & Surgeries
        $procedures = [];
        if ($include('procedures')) {
            $rawProcedures = Procedure::where('patient_id', $patient->id)
                ->with(['service', 'requestedByUser'])
                ->orderBy('created_at', 'desc')
                ->take($limit)
                ->get();

            foreach ($rawProcedures as $item) {
                $t = $item->created_at ? Carbon::parse($item->created_at) : null;
                $doc = $item->requestedByUser ? userfullname($item->requestedByUser->id) : 'N/A';
                $procName = $item->service ? $item->service->service_name : ($item->free_form_name ?: 'Procedure');

                $procedures[] = [
                    'id' => $item->id,
                    'procedure_name' => $procName,
                    'status' => ucfirst($item->procedure_status ?: 'requested'),
                    'priority' => ucfirst($item->priority ?: 'routine'),
                    'doctor_name' => $doc,
                    'requested_date' => $t ? $t->toIso8601String() : null,
                    'date_formatted' => $t ? $t->format('M d, Y H:i') : null,
                    'scheduled_date' => $item->scheduled_date,
                    'scheduled_time' => $item->scheduled_time,
                    'operating_room' => $item->operating_room ?: '',
                    'pre_notes' => $item->pre_notes ?: '',
                    'post_notes' => $item->post_notes ?: '',
                    'outcome' => $item->outcome ?: '',
                    'outcome_notes' => $item->outcome_notes ?: '',
                ];
            }
        }

        // 8. Ward Admissions
        $admissions = [];
        if ($include('admissions')) {
            $rawAdmissions = AdmissionRequest::where('patient_id', $patient->id)
                ->with(['doctor', 'bed', 'bed.wardRelation'])
                ->orderBy('created_at', 'desc')
                ->take($limit)
                ->get();

            foreach ($rawAdmissions as $item) {
                $t = $item->created_at ? Carbon::parse($item->created_at) : null;
                $doc = $item->doctor ? userfullname($item->doctor->id) : 'N/A';
                $wardName = $item->bed && $item->bed->wardRelation ? $item->bed->wardRelation->name : ($item->bed->ward ?? 'Ward');
                $bedName = $item->bed ? $item->bed->name : 'Bed';

                $admissions[] = [
                    'id' => $item->id,
                    'admission_reason' => $item->admission_reason ?: 'Clinical Admission',
                    'chief_complaint' => $item->chief_complaint ?: '',
                    'ward' => $wardName,
                    'bed' => $bedName,
                    'status' => ucfirst(str_replace('_', ' ', $item->admission_status ?: ($item->discharged ? 'discharged' : 'admitted'))),
                    'priority' => $item->priority ?: 'routine',
                    'doctor_name' => $doc,
                    'admitted_at' => $t ? $t->toIso8601String() : null,
                    'date_formatted' => $t ? $t->format('M d, Y H:i') : null,
                    'discharged' => (bool) $item->discharged,
                    'discharge_date' => $item->discharge_date ? Carbon::parse($item->discharge_date)->toIso8601String() : null,
                    'discharge_reason' => $item->discharge_reason ?: '',
                    'discharge_note' => $item->discharge_note ?: '',
                ];
            }
        }

        // 9. Specialist Referrals
        $referrals = [];
        if ($include('referrals')) {
            $rawReferrals = SpecialistReferral::where('patient_id', $patient->id)
                ->with(['targetClinic', 'targetDoctor.user', 'referringDoctor.user'])
                ->orderBy('created_at', 'desc')
                ->take($limit)
                ->get();

            foreach ($rawReferrals as $item) {
                $t = $item->created_at ? Carbon::parse($item->created_at) : null;
                $referrer = $item->referringDoctor && $item->referringDoctor->user ? userfullname($item->referringDoctor->user->id) : 'N/A';
                $isInt = $item->referral_type === 'internal';
                $target = $isInt
                    ? ($item->targetClinic->name ?? 'Specialist') . ($item->targetDoctor ? ' (' . userfullname($item->targetDoctor->user_id) . ')' : '')
                    : ($item->external_facility_name ?: 'External Facility') . ($item->external_doctor_name ? " (Dr. {$item->external_doctor_name})" : '');

                $referrals[] = [
                    'id' => $item->id,
                    'referral_type' => $item->referral_type,
                    'target' => $target,
                    'urgency' => ucfirst($item->urgency ?: 'routine'),
                    'status' => ucfirst($item->status ?: 'pending'),
                    'reason' => $item->reason ?: '',
                    'clinical_summary' => $item->clinical_summary ?: '',
                    'provisional_diagnosis' => $item->provisional_diagnosis ?: '',
                    'referring_doctor' => $referrer,
                    'date' => $t ? $t->toIso8601String() : null,
                    'date_formatted' => $t ? $t->format('M d, Y H:i') : null,
                ];
            }
        }

        // 10. Medication Administration Records
        $medAdmin = [];
        if ($include('med_admin')) {
            $rawMedAdmin = MedicationAdministration::where('patient_id', $patient->id)
                ->with(['product', 'administeredBy'])
                ->orderBy('administered_at', 'desc')
                ->take($limit)
                ->get();

            foreach ($rawMedAdmin as $item) {
                $pName = $item->product ? $item->product->product_name : ($item->external_drug_name ?? 'Medication');
                $admin = $item->administeredBy ? userfullname($item->administeredBy->id) : 'N/A';
                $t = $item->administered_at ? Carbon::parse($item->administered_at) : null;

                $medAdmin[] = [
                    'id' => $item->id,
                    'drug_name' => $pName,
                    'dose' => $item->dose ?: '',
                    'qty' => $item->qty,
                    'route' => $item->route ?: '',
                    'comment' => $item->comment ?: '',
                    'administered_by' => $admin,
                    'date' => $t ? $t->toIso8601String() : null,
                    'date_formatted' => $t ? $t->format('M d, Y H:i') : null,
                ];
            }
        }

        // 11. Unified Chronological Timeline Stream
        $timeline = [];

        foreach ($encounters as $e) {
            $timeline[] = [
                'type' => TimelineType::ENCOUNTER->value,
                'type_label' => TimelineType::ENCOUNTER->label(),
                'badge_color' => TimelineType::ENCOUNTER->badgeColor(),
                'icon' => TimelineType::ENCOUNTER->icon(),
                'title' => $e['clinic'] . ' (' . $e['doctor_name'] . ')',
                'subtitle' => $e['diagnoses_summary'],
                'details' => $e['clinical_notes'],
                'timestamp' => $e['date'],
                'date_formatted' => $e['date_formatted'],
                'data' => $e,
            ];
        }

        foreach ($vitals as $v) {
            $vSummary = [];
            if ($v['blood_pressure']) {
                $vSummary[] = 'BP: ' . $v['blood_pressure'];
            }
            if ($v['temperature']) {
                $vSummary[] = 'Temp: ' . $v['temperature'] . '°C';
            }
            if ($v['heart_rate']) {
                $vSummary[] = 'HR: ' . $v['heart_rate'] . ' bpm';
            }
            if ($v['spo2']) {
                $vSummary[] = 'SpO2: ' . $v['spo2'] . '%';
            }
            if ($v['blood_sugar']) {
                $vSummary[] = 'Blood Sugar: ' . $v['blood_sugar'];
            }

            $timeline[] = [
                'type' => TimelineType::VITAL->value,
                'type_label' => TimelineType::VITAL->label(),
                'badge_color' => TimelineType::VITAL->badgeColor(),
                'icon' => TimelineType::VITAL->icon(),
                'title' => 'Vitals Logged (' . $v['taken_by'] . ')',
                'subtitle' => !empty($vSummary) ? implode(' | ', $vSummary) : 'Vitals Recorded',
                'details' => $v['other_notes'],
                'timestamp' => $v['time_taken'],
                'date_formatted' => $v['date_formatted'],
                'data' => $v,
            ];
        }

        foreach ($nursingNotes as $nn) {
            $timeline[] = [
                'type' => TimelineType::NURSING_NOTE->value,
                'type_label' => TimelineType::NURSING_NOTE->label(),
                'badge_color' => TimelineType::NURSING_NOTE->badgeColor(),
                'icon' => TimelineType::NURSING_NOTE->icon(),
                'title' => $nn['type'] . ' (' . $nn['nurse_name'] . ')',
                'subtitle' => mb_substr(strip_tags($nn['note']), 0, 100),
                'details' => $nn['note'],
                'timestamp' => $nn['date'],
                'date_formatted' => $nn['date_formatted'],
                'data' => $nn,
            ];
        }

        foreach ($labs as $l) {
            $timeline[] = [
                'type' => TimelineType::LAB->value,
                'type_label' => TimelineType::LAB->label(),
                'badge_color' => TimelineType::LAB->badgeColor(),
                'icon' => TimelineType::LAB->icon(),
                'title' => $l['test_name'] . ' [' . $l['status'] . ']',
                'subtitle' => 'Requested by: ' . $l['doctor_name'],
                'details' => $l['result'] ?: $l['note'],
                'timestamp' => $l['requested_date'],
                'date_formatted' => $l['date_formatted'],
                'data' => $l,
            ];
        }

        foreach ($imaging as $im) {
            $timeline[] = [
                'type' => TimelineType::IMAGING->value,
                'type_label' => TimelineType::IMAGING->label(),
                'badge_color' => TimelineType::IMAGING->badgeColor(),
                'icon' => TimelineType::IMAGING->icon(),
                'title' => $im['test_name'] . ' [' . $im['status'] . ']',
                'subtitle' => 'Requested by: ' . $im['doctor_name'],
                'details' => $im['result'] ?: $im['note'],
                'timestamp' => $im['requested_date'],
                'date_formatted' => $im['date_formatted'],
                'data' => $im,
            ];
        }

        foreach ($prescriptions as $p) {
            $timeline[] = [
                'type' => TimelineType::PRESCRIPTION->value,
                'type_label' => TimelineType::PRESCRIPTION->label(),
                'badge_color' => TimelineType::PRESCRIPTION->badgeColor(),
                'icon' => TimelineType::PRESCRIPTION->icon(),
                'title' => $p['drug_name'] . ' (' . ($p['dose'] ?: 'Dose: N/A') . ')',
                'subtitle' => 'Qty: ' . $p['qty'] . ' · Status: ' . $p['status'],
                'details' => 'Prescribed by ' . $p['doctor_name'],
                'timestamp' => $p['requested_date'],
                'date_formatted' => $p['date_formatted'],
                'data' => $p,
            ];
        }

        foreach ($procedures as $pr) {
            $timeline[] = [
                'type' => TimelineType::PROCEDURE->value,
                'type_label' => TimelineType::PROCEDURE->label(),
                'badge_color' => TimelineType::PROCEDURE->badgeColor(),
                'icon' => TimelineType::PROCEDURE->icon(),
                'title' => $pr['procedure_name'] . ' [' . $pr['status'] . ']',
                'subtitle' => 'Doctor: ' . $pr['doctor_name'] . ($pr['operating_room'] ? ' (OR: ' . $pr['operating_room'] . ')' : ''),
                'details' => $pr['post_notes'] ?: $pr['pre_notes'],
                'timestamp' => $pr['requested_date'],
                'date_formatted' => $pr['date_formatted'],
                'data' => $pr,
            ];
        }

        foreach ($admissions as $a) {
            $timeline[] = [
                'type' => TimelineType::ADMISSION->value,
                'type_label' => TimelineType::ADMISSION->label(),
                'badge_color' => TimelineType::ADMISSION->badgeColor(),
                'icon' => TimelineType::ADMISSION->icon(),
                'title' => $a['admission_reason'] . ' (' . $a['ward'] . ' - ' . $a['bed'] . ')',
                'subtitle' => 'Status: ' . $a['status'] . ' · Doctor: ' . $a['doctor_name'],
                'details' => $a['discharge_note'] ?: $a['chief_complaint'],
                'timestamp' => $a['admitted_at'],
                'date_formatted' => $a['date_formatted'],
                'data' => $a,
            ];
        }

        foreach ($referrals as $ref) {
            $timeline[] = [
                'type' => TimelineType::REFERRAL->value,
                'type_label' => TimelineType::REFERRAL->label(),
                'badge_color' => TimelineType::REFERRAL->badgeColor(),
                'icon' => TimelineType::REFERRAL->icon(),
                'title' => 'Referral: ' . $ref['target'] . ' (' . $ref['urgency'] . ')',
                'subtitle' => 'Reason: ' . $ref['reason'],
                'details' => $ref['clinical_summary'],
                'timestamp' => $ref['date'],
                'date_formatted' => $ref['date_formatted'],
                'data' => $ref,
            ];
        }

        foreach ($medAdmin as $ma) {
            $timeline[] = [
                'type' => TimelineType::MED_ADMIN->value,
                'type_label' => TimelineType::MED_ADMIN->label(),
                'badge_color' => TimelineType::MED_ADMIN->badgeColor(),
                'icon' => TimelineType::MED_ADMIN->icon(),
                'title' => $ma['drug_name'] . ' (' . ($ma['dose'] ?: 'Qty: ' . $ma['qty']) . ')',
                'subtitle' => 'Route: ' . ($ma['route'] ?: 'N/A') . ' · Administered by: ' . $ma['administered_by'],
                'details' => $ma['comment'],
                'timestamp' => $ma['date'],
                'date_formatted' => $ma['date_formatted'],
                'data' => $ma,
            ];
        }

        usort($timeline, function ($a, $b) {
            return strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? '');
        });

        $summary = [
            'encounters_count' => count($encounters),
            'vitals_count' => count($vitals),
            'nursing_notes_count' => count($nursingNotes),
            'labs_count' => count($labs),
            'imaging_count' => count($imaging),
            'prescriptions_count' => count($prescriptions),
            'procedures_count' => count($procedures),
            'admissions_count' => count($admissions),
            'referrals_count' => count($referrals),
            'med_admin_count' => count($medAdmin),
            'total_records' => count($timeline),
        ];

        $patientData = [
            'id' => $patient->id,
            'file_no' => $patient->file_no,
            'fullname' => trim($patient->surname . ' ' . $patient->firstname . ' ' . ($patient->othername ?? '')),
            'email' => $patient->email,
            'phone' => $patient->phone_no,
            'date_of_birth' => $patient->date_of_birth,
            'gender' => $patient->gender,
            'address' => $patient->address,
            'blood_group' => $patient->blood_group,
            'genotype' => $patient->genotype,
            'corehealth_sync_uuid' => $syncUuid,
            'already_linked' => (bool) $patient->wecare_sync_active,
            'wecare_patient_id' => $patient->wecare_patient_id,
        ];

        $dto = new ClinicalStoryDto(
            patient: $patientData,
            summary: $summary,
            encounters: $encounters,
            vitals: $vitals,
            nursingNotes: $nursingNotes,
            labs: $labs,
            imaging: $imaging,
            prescriptions: $prescriptions,
            procedures: $procedures,
            admissions: $admissions,
            referrals: $referrals,
            medAdmin: $medAdmin,
            timeline: $timeline
        );

        return response()->json($dto->toPayload());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // PATIENT-LINKED CALLBACK  (auth required)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/corehealth/sync/patient-linked
     *
     * Called by WeCare247 when a patient successfully registers using an invite
     * token that contained a CoreHealth file number.
     * Activates bidirectional sync on the CoreHealth side.
     *
     * Body (JSON):
     *   file_no             string   required
     *   corehealth_patient_id  int   required
     *   wecare_patient_id   string   required  (MongoDB ObjectId)
     *   corehealth_sync_uuid string  required
     */
    public function patientLinked(Request $request)
    {
        if (!appsettings('wecare_enabled', false)) {
            return response()->json([
                'status' => false,
                'message' => 'WeCare247 integration is currently disabled in Hospital Settings.',
                'code' => 'WECARE_INTEGRATION_DISABLED',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'file_no' => 'required|string|max:64',
            'corehealth_patient_id' => 'required|integer',
            'wecare_patient_id' => 'required|string|max:64',
            'corehealth_sync_uuid' => 'required|string|size:40',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $patient = DB::table('patients')
            ->where('id', $request->corehealth_patient_id)
            ->where('file_no', $request->file_no)
            ->first();

        if (!$patient) {
            return response()->json([
                'status' => false,
                'message' => 'Patient record not found or file_no mismatch.',
            ], 404);
        }

        // Verify the UUID matches (prevent forged callbacks)
        $facilityId = (int) appsettings('facility_id', 0);
        $expectedUuid = sha1($facilityId . ':' . $patient->id . ':' . $patient->file_no);

        if (!hash_equals($expectedUuid, $request->corehealth_sync_uuid)) {
            Log::warning('WeCareIntegration: UUID mismatch on patient-linked callback', [
                'patient_id' => $patient->id,
                'provided_uuid' => $request->corehealth_sync_uuid,
                'expected_uuid' => $expectedUuid,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Sync UUID mismatch. Callback rejected.',
            ], 403);
        }

        // Activate sync
        DB::table('patients')
            ->where('id', $patient->id)
            ->update([
                'wecare_patient_id' => $request->wecare_patient_id,
                'wecare_sync_uuid' => $expectedUuid,
                'wecare_sync_active' => 1,
                'wecare_synced_at' => now(),
            ]);

        Log::info('WeCareIntegration: Patient linked', [
            'corehealth_patient_id' => $patient->id,
            'file_no' => $patient->file_no,
            'wecare_patient_id' => $request->wecare_patient_id,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Patient successfully linked. Bidirectional sync is now active.',
            'linked' => [
                'corehealth_patient_id' => $patient->id,
                'file_no' => $patient->file_no,
                'wecare_patient_id' => $request->wecare_patient_id,
                'sync_uuid' => $expectedUuid,
                'synced_at' => now()->toIso8601String(),
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // TELE-CONSULTATION ENCOUNTER INGEST  (auth required)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/corehealth/sync/encounter
     *
     * Ingests a WeCare247 tele-consultation note into the hospital longitudinal
     * EHR as a new encounter entry visible in the Doctor Workbench.
     *
     * Body (JSON) — see plan §5.A for full payload schema.
     */
    public function ingestEncounter(Request $request)
    {
        if (!appsettings('wecare_enabled', false)) {
            return response()->json([
                'status' => false,
                'message' => 'WeCare247 integration is currently disabled in Hospital Settings.',
                'code' => 'WECARE_INTEGRATION_DISABLED',
            ], 403);
        }

        if (!appsettings('wecare_auto_sync_encounters', true)) {
            return response()->json([
                'status' => false,
                'message' => 'Telehealth encounter ingestion is disabled in Hospital Settings.',
                'code' => 'ENCOUNTER_SYNC_DISABLED',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'facility_id' => 'required|integer',
            'corehealth_file_no' => 'required|string|max:64',
            'corehealth_patient_id' => 'required|integer',
            'wecare_appointment_id' => 'required|string|max:64',
            'consultation_type' => 'nullable|string|max:150',
            'doctor_name' => 'nullable|string|max:150',
            'doctor_specialty' => 'nullable|string|max:100',
            'consultation_date' => 'required|date',
            'chief_complaint' => 'nullable|string|max:500',
            'clinical_notes' => 'nullable|string',
            'diagnoses' => 'nullable|array',
            'diagnoses.*.icd10_code' => 'nullable|string|max:20',
            'diagnoses.*.title' => 'nullable|string|max:255',
            'vitals' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verify the patient is linked and the IDs match
        $patient = DB::table('patients')
            ->where('id', $request->corehealth_patient_id)
            ->where('file_no', $request->corehealth_file_no)
            ->where('wecare_sync_active', 1)
            ->first();

        if (!$patient) {
            return response()->json([
                'status' => false,
                'message' => 'Patient not found, IDs do not match, or WeCare sync is not active.',
            ], 404);
        }

        // Idempotency check — prevent duplicate ingestion of the same appointment
        $dedupKey = sha1('wecare_encounter:' . $request->wecare_appointment_id);
        $existing = DB::table('encounters')
            ->where('wecare_sync_ref', $dedupKey)
            ->exists();

        if ($existing) {
            return response()->json([
                'status' => true,
                'message' => 'Encounter already ingested (idempotent). No duplicate created.',
            ]);
        }

        // Build a rich source_note for the EHR
        $sourceParts = [];
        if ($request->filled('chief_complaint')) {
            $sourceParts[] = "Chief Complaint: {$request->chief_complaint}";
        }
        if ($request->filled('clinical_notes')) {
            $sourceParts[] = "Clinical Notes: {$request->clinical_notes}";
        }
        if (!empty($request->diagnoses)) {
            $diagLines = collect($request->diagnoses)->map(function ($d) {
                return ($d['icd10_code'] ?? '') . ' — ' . ($d['title'] ?? '');
            })->implode('; ');
            $sourceParts[] = "Diagnoses: {$diagLines}";
        }
        if (!empty($request->vitals)) {
            $vitalLines = collect($request->vitals)->map(function ($v, $k) {
                return is_array($v) ? "{$k}: " . implode(', ', $v) : "{$k}: {$v}";
            })->implode(' | ');
            $sourceParts[] = "Vitals: {$vitalLines}";
        }

        $noteBody = "=== WeCare247 Telemedicine Consultation ===\n";
        $noteBody .= "Type: " . ($request->consultation_type ?? 'Virtual Consultation') . "\n";
        if ($request->filled('doctor_name')) {
            $noteBody .= "Doctor: {$request->doctor_name}" . ($request->filled('doctor_specialty') ? " ({$request->doctor_specialty})" : "") . "\n";
        }
        $noteBody .= "Appointment ID: {$request->wecare_appointment_id}\n\n";
        $noteBody .= implode("\n\n", $sourceParts);

        $consultDate = $request->consultation_date ? \Illuminate\Support\Carbon::parse($request->consultation_date) : now();

        $doctorId = (int) ($request->doctor_id ?? 0);
        if ($doctorId <= 0) {
            $doctorId = (int) (DB::table('users')->where('is_admin', 1)->value('id') ?? 1);
        }

        $encounterId = DB::table('encounters')->insertGetId([
            'patient_id' => $patient->id,
            'doctor_id' => $doctorId,
            'reasons_for_encounter' => $request->chief_complaint ?? ($request->consultation_type ?? 'Telemedicine Virtual Consultation'),
            'notes' => $noteBody,
            'completed' => 1,
            'outcome' => 'concluded',
            'started_at' => $consultDate,
            'completed_at' => $consultDate,
            'wecare_sync_ref' => $dedupKey,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update last sync timestamp on patient record
        DB::table('patients')
            ->where('id', $patient->id)
            ->update(['wecare_synced_at' => now()]);

        Log::info('WeCareIntegration: Encounter ingested', [
            'encounter_id' => $encounterId,
            'corehealth_patient_id' => $patient->id,
            'wecare_appointment_id' => $request->wecare_appointment_id,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Tele-consultation encounter successfully ingested into hospital EHR.',
            'encounter_id' => $encounterId,
        ], 201);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // PATIENT UNLINK  (auth required)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/corehealth/sync/patient-unlink
     *
     * Safely detaches the WeCare247 binding from a patient record without
     * deleting any clinical data. Called when the Facility Admin unlinks a
     * patient from the WeCare247 management console.
     */
    public function patientUnlink(Request $request)
    {
        if (!appsettings('wecare_enabled', false)) {
            return response()->json([
                'status' => false,
                'message' => 'WeCare247 integration is currently disabled in Hospital Settings.',
                'code' => 'WECARE_INTEGRATION_DISABLED',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'corehealth_patient_id' => 'required|integer',
            'wecare_patient_id' => 'required|string|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updated = DB::table('patients')
            ->where('id', $request->corehealth_patient_id)
            ->where('wecare_patient_id', $request->wecare_patient_id)
            ->update([
                'wecare_sync_active' => 0,
                'wecare_synced_at' => now(),
            ]);

        if (!$updated) {
            return response()->json([
                'status' => false,
                'message' => 'Patient not found or wecare_patient_id mismatch.',
            ], 404);
        }

        Log::info('WeCareIntegration: Patient unlinked', [
            'corehealth_patient_id' => $request->corehealth_patient_id,
            'wecare_patient_id' => $request->wecare_patient_id,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Patient unlinked from WeCare247. Sync deactivated.',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // LAB RESULT PUSH (from CoreHealth → WeCare247)  [internal dispatch helper]
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Called internally (e.g., from Lab Observer or artisan command) to push
     * a verified lab result to the WeCare247 webhook endpoint.
     *
     * NOT an HTTP route — invoked via static call: WeCareIntegrationController::dispatchLabResult($result)
     *
     * @param  \stdClass  $result  Row from lab_results / investigations table
     */
    public static function dispatchLabResult(\stdClass $result): void
    {
        if (!appsettings('wecare_enabled', false) || !appsettings('wecare_auto_sync_labs', true)) {
            return; // WeCare sync is disabled in hospital settings
        }

        $patient = DB::table('patients')
            ->where('id', $result->patient_id ?? 0)
            ->where('wecare_sync_active', 1)
            ->first();

        if (!$patient || empty($patient->wecare_patient_id)) {
            return; // Patient not linked — nothing to push
        }

        $facilitySettings = appsettings('wecare_url') ?: appsettings('wecare247_webhook_url');
        if (!$facilitySettings) {
            return; // Webhook URL not configured
        }

        $payload = [
            'event' => 'lab_verified',
            'facility_id' => (int) appsettings('facility_id', 0),
            'corehealth_file_no' => $patient->file_no,
            'wecare_patient_id' => $patient->wecare_patient_id,
            'test_name' => $result->test_name ?? '',
            'verified_at' => now()->toDateTimeString(),
            'verified_by' => $result->verified_by ?? '',
            'results' => json_decode($result->results ?? '[]', true),
        ];

        try {
            $ch = curl_init($facilitySettings . '/api/integrations/corehealth/webhook/lab-result');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            Log::info('WeCareIntegration: Lab result dispatched', [
                'patient_id' => $patient->id,
                'http_code' => $httpCode,
            ]);
        } catch (\Throwable $e) {
            Log::error('WeCareIntegration: Lab result dispatch failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
