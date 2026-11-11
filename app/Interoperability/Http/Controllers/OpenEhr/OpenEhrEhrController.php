<?php

namespace App\Interoperability\Http\Controllers\OpenEhr;

use App\Http\Controllers\Controller;
use App\Interoperability\Helpers\InteropRouteHelper;
use App\Interoperability\Models\InteropExternalIdentifier;
use App\Interoperability\Transformers\OpenEhr\EhrStatusTransformer;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpenEhrEhrController extends Controller
{
    /**
     * Get EHR status by ehr_id.
     * GET /api/openehr/v1/ehr/{ehr_id}
     */
    public function show(string $ehrId): JsonResponse
    {
        $patientId = InteropExternalIdentifier::resolveLocalId('OPENEHR', 'Ehr', $ehrId);

        if (!$patientId) {
            return response()->json([
                'message' => "EHR with id '{$ehrId}' does not exist.",
            ], 404);
        }

        $patient = Patient::find($patientId);
        if (!$patient) {
            return response()->json(['message' => 'Patient record not found.'], 404);
        }

        return response()->json(EhrStatusTransformer::toEhr($patient), 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    /**
     * Lookup EHR by subject ID and namespace.
     * GET /api/openehr/v1/ehr?subject_id=...&subject_namespace=...
     */
    public function findBySubject(Request $request): JsonResponse
    {
        $subjectId = $request->query('subject_id');
        $subjectNamespace = $request->query('subject_namespace', 'corehealth');

        if (!$subjectId) {
            return response()->json(['message' => 'Query parameter subject_id is required.'], 400);
        }

        $patient = Patient::where('id', $subjectId)
            ->orWhere('file_no', $subjectId)
            ->first();

        if (!$patient) {
            return response()->json(['message' => 'EHR not found for specified subject.'], 404);
        }

        return response()->json(EhrStatusTransformer::toEhr($patient), 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    /**
     * Create or link an EHR for a patient.
     * POST /api/openehr/v1/ehr
     */
    public function store(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $subjectId = $payload['ehr_status']['subject']['external_ref']['id']['value'] ?? $request->input('patient_id');

        $patient = $subjectId ? Patient::find($subjectId) : Patient::first();

        if (!$patient) {
            return response()->json(['message' => 'Subject patient not found.'], 404);
        }

        $ehr = EhrStatusTransformer::toEhr($patient);
        $ehrId = $ehr['ehr_id']['value'];

        return response()->json($ehr, 201, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Location' => InteropRouteHelper::openEhrRoute("ehr/{$ehrId}"),
            'ETag' => '"' . md5(json_encode($ehr)) . '"',
        ]);
    }
}
