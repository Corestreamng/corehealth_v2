<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
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

        $query = DB::table('patients');

        if ($request->filled('file_no')) {
            $query->where('file_no', $request->file_no);
        } else {
            $query->where('phone_no', $request->phone_no);
        }

        $patient = $query->select([
            'id',
            'file_no',
            'surname',
            'firstname',
            'othername',
            'email',
            'phone_no',
            'date_of_birth',
            'gender',
            'address',
            'wecare_patient_id',
            'wecare_sync_uuid',
            'wecare_sync_active',
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

        $encounterId = DB::table('encounters')->insertGetId([
            'patient_id' => $patient->id,
            'file_no' => $patient->file_no,
            'encounter_type' => $request->consultation_type ?? 'Telemedicine Virtual Consultation',
            'doctor' => $request->doctor_name ?? 'WeCare247 Teleconsultant',
            'specialty' => $request->doctor_specialty ?? '',
            'encounter_date' => $request->consultation_date,
            'source_note' => implode("\n\n", $sourceParts),
            'encounter_source' => 'wecare247',
            'wecare_sync_ref' => $dedupKey,
            'wecare_appointment' => $request->wecare_appointment_id,
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
