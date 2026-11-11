<?php

namespace App\Interoperability\Http\Controllers\Fhir;

use App\Http\Controllers\Controller;
use App\Http\Controllers\EncounterController;
use App\Interoperability\Helpers\InteropRouteHelper;
use App\Interoperability\Models\InteropExternalIdentifier;
use App\Interoperability\Mutators\Fhir\InboundFhirPrescriptionMutator;
use App\Interoperability\Transformers\Fhir\MedicationRequestTransformer;
use App\Models\ProductRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FhirMedicationRequestController extends Controller
{
    /**
     * Search FHIR R4 MedicationRequests.
     * GET /api/fhir/r4/MedicationRequest
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProductRequest::with(['product', 'encounter']);

        if ($request->filled('patient')) {
            $parts = explode('/', $request->input('patient'));
            $localPatientId = InteropExternalIdentifier::resolveLocalId('FHIR', 'Patient', end($parts));
            if ($localPatientId) {
                $query->where('patient_id', $localPatientId);
            }
        }

        $limit = min(50, (int) ($request->input('_count', 20)));
        $prescriptions = $query->orderBy('created_at', 'desc')->paginate($limit);

        $entries = [];
        foreach ($prescriptions as $rx) {
            $fhirRx = MedicationRequestTransformer::toFhir($rx);
            $entries[] = [
                'fullUrl' => InteropRouteHelper::fhirRoute('MedicationRequest', $fhirRx['id']),
                'resource' => $fhirRx,
            ];
        }

        $bundle = [
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => $prescriptions->total(),
            'entry' => $entries,
        ];

        return response()->json($bundle, 200, [
            'Content-Type' => 'application/fhir+json; charset=UTF-8',
        ]);
    }

    /**
     * Read a single FHIR R4 MedicationRequest.
     * GET /api/fhir/r4/MedicationRequest/{id}
     */
    public function show(string $id): JsonResponse
    {
        $localId = InteropExternalIdentifier::resolveLocalId('FHIR', 'MedicationRequest', $id);

        if (!$localId) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'not-found',
                        'diagnostics' => "MedicationRequest '{$id}' not found.",
                    ],
                ],
            ], 404, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
        }

        $rx = ProductRequest::with(['product', 'encounter'])->find($localId);

        if (!$rx) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'not-found',
                        'diagnostics' => "MedicationRequest not found.",
                    ],
                ],
            ], 404, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
        }

        return response()->json(MedicationRequestTransformer::toFhir($rx), 200, [
            'Content-Type' => 'application/fhir+json; charset=UTF-8',
        ]);
    }

    /**
     * Inbound Create FHIR MedicationRequest: Mutates payload to proxy EncounterController@addSinglePrescriptionRequest.
     * POST /api/fhir/r4/MedicationRequest
     */
    public function store(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $mutated = InboundFhirPrescriptionMutator::mutate($payload, $request);

        $localRequest = $mutated['request'];
        $encounter = $mutated['encounter'];

        if (!$encounter) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'required',
                        'diagnostics' => 'Patient or encounter reference could not be resolved.',
                    ],
                ],
            ], 422, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
        }

        $encounterController = app(EncounterController::class);
        $res = $encounterController->addSinglePrescriptionRequest($localRequest, $encounter);
        $responseData = json_decode($res->getContent(), true);

        if (!($responseData['success'] ?? false)) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'processing',
                        'diagnostics' => $responseData['message'] ?? 'Prescription addition failed.',
                    ],
                ],
            ], 500, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
        }

        $rxId = $responseData['id'];
        $rx = ProductRequest::with(['product', 'encounter'])->find($rxId);

        if (!empty($payload['id'])) {
            InteropExternalIdentifier::linkExternalId('FHIR', 'MedicationRequest', $rx->id, $payload['id']);
        }

        $fhirRx = MedicationRequestTransformer::toFhir($rx);

        return response()->json($fhirRx, 201, [
            'Content-Type' => 'application/fhir+json; charset=UTF-8',
            'Location' => InteropRouteHelper::fhirRoute('MedicationRequest', $fhirRx['id']),
        ]);
    }
}
