<?php

namespace App\Interoperability\Http\Controllers\Fhir;

use App\Http\Controllers\Controller;
use App\Http\Controllers\EncounterController;
use App\Interoperability\Helpers\InteropRouteHelper;
use App\Interoperability\Models\InteropExternalIdentifier;
use App\Interoperability\Mutators\Fhir\InboundFhirServiceRequestMutator;
use App\Interoperability\Transformers\Fhir\ServiceRequestTransformer;
use App\Models\LabServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FhirServiceRequestController extends Controller
{
    /**
     * Search FHIR R4 ServiceRequests (Laboratory Investigations).
     * GET /api/fhir/r4/ServiceRequest
     */
    public function index(Request $request): JsonResponse
    {
        $query = LabServiceRequest::with(['service', 'encounter']);

        if ($request->filled('patient')) {
            $parts = explode('/', $request->input('patient'));
            $localPatientId = InteropExternalIdentifier::resolveLocalId('FHIR', 'Patient', end($parts));
            if ($localPatientId) {
                $query->where('patient_id', $localPatientId);
            }
        }

        $limit = min(50, (int) ($request->input('_count', 20)));
        $requests = $query->orderBy('created_at', 'desc')->paginate($limit);

        $entries = [];
        foreach ($requests as $req) {
            $fhirReq = ServiceRequestTransformer::toFhir($req);
            $entries[] = [
                'fullUrl' => InteropRouteHelper::fhirRoute('ServiceRequest', $fhirReq['id']),
                'resource' => $fhirReq,
            ];
        }

        $bundle = [
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => $requests->total(),
            'entry' => $entries,
        ];

        return response()->json($bundle, 200, [
            'Content-Type' => 'application/fhir+json; charset=UTF-8',
        ]);
    }

    /**
     * Read a single FHIR R4 ServiceRequest.
     * GET /api/fhir/r4/ServiceRequest/{id}
     */
    public function show(string $id): JsonResponse
    {
        $localId = InteropExternalIdentifier::resolveLocalId('FHIR', 'ServiceRequest', $id);

        if (!$localId) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'not-found',
                        'diagnostics' => "ServiceRequest '{$id}' not found.",
                    ],
                ],
            ], 404, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
        }

        $req = LabServiceRequest::with(['service', 'encounter'])->find($localId);

        if (!$req) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'not-found',
                        'diagnostics' => "ServiceRequest not found.",
                    ],
                ],
            ], 404, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
        }

        return response()->json(ServiceRequestTransformer::toFhir($req), 200, [
            'Content-Type' => 'application/fhir+json; charset=UTF-8',
        ]);
    }

    /**
     * Inbound Create FHIR ServiceRequest: Mutates payload to proxy EncounterController@addSingleLabRequest.
     * POST /api/fhir/r4/ServiceRequest
     */
    public function store(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $mutated = InboundFhirServiceRequestMutator::mutate($payload, $request);

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
        $res = $encounterController->addSingleLabRequest($localRequest, $encounter);
        $responseData = json_decode($res->getContent(), true);

        if (!($responseData['success'] ?? false)) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'processing',
                        'diagnostics' => $responseData['message'] ?? 'Lab order addition failed.',
                    ],
                ],
            ], 500, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
        }

        $labId = $responseData['id'];
        $lab = LabServiceRequest::with(['service', 'encounter'])->find($labId);

        if (!empty($payload['id'])) {
            InteropExternalIdentifier::linkExternalId('FHIR', 'ServiceRequest', $lab->id, $payload['id']);
        }

        $fhirReq = ServiceRequestTransformer::toFhir($lab);

        return response()->json($fhirReq, 201, [
            'Content-Type' => 'application/fhir+json; charset=UTF-8',
            'Location' => InteropRouteHelper::fhirRoute('ServiceRequest', $fhirReq['id']),
        ]);
    }
}
