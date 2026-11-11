<?php

namespace App\Interoperability\Http\Controllers\Fhir;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VitalSignController;
use App\Interoperability\Helpers\InteropRouteHelper;
use App\Interoperability\Models\InteropExternalIdentifier;
use App\Interoperability\Mutators\Fhir\InboundFhirObservationMutator;
use App\Interoperability\Transformers\Fhir\ObservationTransformer;
use App\Models\VitalSign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FhirObservationController extends Controller
{
    /**
     * Search FHIR R4 Observations (Vital Signs).
     * GET /api/fhir/r4/Observation
     */
    public function index(Request $request): JsonResponse
    {
        $query = VitalSign::query();

        if ($request->filled('patient')) {
            $patientRef = $request->input('patient');
            $parts = explode('/', $patientRef);
            $localPatientId = InteropExternalIdentifier::resolveLocalId('FHIR', 'Patient', end($parts));
            if ($localPatientId) {
                $query->where('patient_id', $localPatientId);
            }
        }

        $limit = min(50, (int) ($request->input('_count', 20)));
        $vitals = $query->orderBy('created_at', 'desc')->paginate($limit);

        $entries = [];
        foreach ($vitals as $vital) {
            $fhirObs = ObservationTransformer::toFhir($vital);
            $entries[] = [
                'fullUrl' => InteropRouteHelper::fhirRoute('Observation', $fhirObs['id']),
                'resource' => $fhirObs,
            ];
        }

        $bundle = [
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => $vitals->total(),
            'entry' => $entries,
        ];

        return response()->json($bundle, 200, [
            'Content-Type' => 'application/fhir+json; charset=UTF-8',
        ]);
    }

    /**
     * Read a single FHIR R4 Observation.
     * GET /api/fhir/r4/Observation/{id}
     */
    public function show(string $id): JsonResponse
    {
        $localId = InteropExternalIdentifier::resolveLocalId('FHIR', 'Observation', $id);

        if (!$localId) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'not-found',
                        'diagnostics' => "Observation '{$id}' not found.",
                    ],
                ],
            ], 404, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
        }

        $vital = VitalSign::find($localId);

        if (!$vital) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'not-found',
                        'diagnostics' => "Observation not found.",
                    ],
                ],
            ], 404, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
        }

        return response()->json(ObservationTransformer::toFhir($vital), 200, [
            'Content-Type' => 'application/fhir+json; charset=UTF-8',
        ]);
    }

    /**
     * Inbound Create FHIR Observation (Vital Signs Panel): Mutates payload to proxy VitalSignController@store.
     * POST /api/fhir/r4/Observation
     */
    public function store(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $mutatedRequest = InboundFhirObservationMutator::mutateToStoreRequest($payload, $request);

        if (!$mutatedRequest->patient_id) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'required',
                        'diagnostics' => 'Patient subject reference could not be resolved.',
                    ],
                ],
            ], 422, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
        }

        $vitalController = app(VitalSignController::class);
        $vitalController->store($mutatedRequest);

        // Fetch the created vital record
        $vital = VitalSign::where('patient_id', $mutatedRequest->patient_id)
            ->orderBy('id', 'desc')
            ->first();

        if (!$vital) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'processing',
                        'diagnostics' => 'Vital sign creation failed.',
                    ],
                ],
            ], 500, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
        }

        if (!empty($payload['id'])) {
            InteropExternalIdentifier::linkExternalId('FHIR', 'Observation', $vital->id, $payload['id']);
        }

        $fhirObs = ObservationTransformer::toFhir($vital);

        return response()->json($fhirObs, 201, [
            'Content-Type' => 'application/fhir+json; charset=UTF-8',
            'Location' => InteropRouteHelper::fhirRoute('Observation', $fhirObs['id']),
        ]);
    }
}
