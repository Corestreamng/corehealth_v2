<?php

namespace App\Interoperability\Http\Controllers\Fhir;

use App\Http\Controllers\Controller;
use App\Interoperability\Helpers\InteropRouteHelper;
use App\Interoperability\Models\InteropExternalIdentifier;
use App\Interoperability\Transformers\Fhir\EncounterTransformer;
use App\Models\Encounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FhirEncounterController extends Controller
{
    /**
     * Search FHIR R4 Encounters.
     * GET /api/fhir/r4/Encounter
     */
    public function index(Request $request): JsonResponse
    {
        $query = Encounter::with(['patient.user', 'doctor', 'treatmentPlan']);

        if ($request->filled('patient')) {
            $parts = explode('/', $request->input('patient'));
            $localPatientId = InteropExternalIdentifier::resolveLocalId('FHIR', 'Patient', end($parts));
            if ($localPatientId) {
                $query->where('patient_id', $localPatientId);
            }
        }

        $limit = min(50, (int) ($request->input('_count', 20)));
        $encounters = $query->orderBy('created_at', 'desc')->paginate($limit);

        $entries = [];
        foreach ($encounters as $encounter) {
            $fhirEnc = EncounterTransformer::toFhir($encounter);
            $entries[] = [
                'fullUrl' => InteropRouteHelper::fhirRoute('Encounter', $fhirEnc['id']),
                'resource' => $fhirEnc,
            ];
        }

        $bundle = [
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => $encounters->total(),
            'entry' => $entries,
        ];

        return response()->json($bundle, 200, [
            'Content-Type' => 'application/fhir+json; charset=UTF-8',
        ]);
    }

    /**
     * Read a single FHIR R4 Encounter.
     * GET /api/fhir/r4/Encounter/{id}
     */
    public function show(string $id): JsonResponse
    {
        $localId = InteropExternalIdentifier::resolveLocalId('FHIR', 'Encounter', $id);

        if (!$localId) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'not-found',
                        'diagnostics' => "Encounter '{$id}' not found.",
                    ],
                ],
            ], 404, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
        }

        $encounter = Encounter::with(['patient.user', 'doctor', 'treatmentPlan'])->find($localId);

        if (!$encounter) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'not-found',
                        'diagnostics' => "Encounter not found.",
                    ],
                ],
            ], 404, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
        }

        return response()->json(EncounterTransformer::toFhir($encounter), 200, [
            'Content-Type' => 'application/fhir+json; charset=UTF-8',
        ]);
    }

    /**
     * Inbound Create FHIR Encounter.
     * POST /api/fhir/r4/Encounter
     */
    public function store(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        $patientId = null;
        if (!empty($payload['subject']['reference'])) {
            $parts = explode('/', $payload['subject']['reference']);
            $patientId = InteropExternalIdentifier::resolveLocalId('FHIR', 'Patient', end($parts));
        }

        if (!$patientId) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'required',
                        'diagnostics' => 'Patient subject reference is required.',
                    ],
                ],
            ], 422, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
        }

        $encounter = new Encounter();
        $encounter->patient_id = $patientId;
        $encounter->doctor_id = auth()->id() ?? 1;
        $encounter->started_at = now();
        $encounter->reasons_for_encounter = $payload['reasonCode'][0]['text'] ?? 'FHIR Ambulatory Encounter';
        $encounter->save();

        if (!empty($payload['id'])) {
            InteropExternalIdentifier::linkExternalId('FHIR', 'Encounter', $encounter->id, $payload['id']);
        }

        $fhirEnc = EncounterTransformer::toFhir($encounter);

        return response()->json($fhirEnc, 201, [
            'Content-Type' => 'application/fhir+json; charset=UTF-8',
            'Location' => InteropRouteHelper::fhirRoute('Encounter', $fhirEnc['id']),
        ]);
    }
}
