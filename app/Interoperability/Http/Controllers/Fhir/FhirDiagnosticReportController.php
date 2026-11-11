<?php

namespace App\Interoperability\Http\Controllers\Fhir;

use App\Http\Controllers\Controller;
use App\Interoperability\Helpers\InteropRouteHelper;
use App\Interoperability\Models\InteropExternalIdentifier;
use App\Interoperability\Transformers\Fhir\DiagnosticReportTransformer;
use App\Models\LabServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FhirDiagnosticReportController extends Controller
{
    /**
     * Search FHIR R4 DiagnosticReports.
     * GET /api/fhir/r4/DiagnosticReport
     */
    public function index(Request $request): JsonResponse
    {
        $query = LabServiceRequest::with(['service', 'encounter', 'patient']);

        if ($request->filled('patient')) {
            $parts = explode('/', $request->input('patient'));
            $localPatientId = InteropExternalIdentifier::resolveLocalId('FHIR', 'Patient', end($parts));
            if ($localPatientId) {
                $query->where('patient_id', $localPatientId);
            }
        }

        $limit = min(50, (int) ($request->input('_count', 20)));
        $reports = $query->orderBy('created_at', 'desc')->paginate($limit);

        $entries = [];
        foreach ($reports as $report) {
            $fhirReport = DiagnosticReportTransformer::toFhir($report);
            $entries[] = [
                'fullUrl' => InteropRouteHelper::fhirRoute('DiagnosticReport', $fhirReport['id']),
                'resource' => $fhirReport,
            ];
        }

        $bundle = [
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => $reports->total(),
            'entry' => $entries,
        ];

        return response()->json($bundle, 200, [
            'Content-Type' => 'application/fhir+json; charset=UTF-8',
        ]);
    }

    /**
     * Read a single FHIR R4 DiagnosticReport.
     * GET /api/fhir/r4/DiagnosticReport/{id}
     */
    public function show(string $id): JsonResponse
    {
        $localId = InteropExternalIdentifier::resolveLocalId('FHIR', 'DiagnosticReport', $id);

        if (!$localId) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'not-found',
                        'diagnostics' => "DiagnosticReport '{$id}' not found.",
                    ],
                ],
            ], 404, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
        }

        $report = LabServiceRequest::with(['service', 'encounter', 'patient'])->find($localId);

        if (!$report) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'not-found',
                        'diagnostics' => "DiagnosticReport not found.",
                    ],
                ],
            ], 404, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
        }

        return response()->json(DiagnosticReportTransformer::toFhir($report), 200, [
            'Content-Type' => 'application/fhir+json; charset=UTF-8',
        ]);
    }

    /**
     * Inbound Update FHIR DiagnosticReport (Result Entry & Sign-off).
     * PUT /api/fhir/r4/DiagnosticReport/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $localId = InteropExternalIdentifier::resolveLocalId('FHIR', 'DiagnosticReport', $id);

        if (!$localId) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'not-found',
                        'diagnostics' => "DiagnosticReport '{$id}' not found.",
                    ],
                ],
            ], 404, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
        }

        $lab = LabServiceRequest::findOrFail($localId);
        $payload = $request->json()->all();

        $conclusion = $payload['conclusion'] ?? null;
        if ($conclusion !== null) {
            $lab->result = $conclusion;
            $lab->status = 2; // Approved / Completed
            $lab->approved_at = now();
            $lab->approved_by = auth()->id() ?? 1;
            $lab->save();
        }

        return response()->json(DiagnosticReportTransformer::toFhir($lab), 200, [
            'Content-Type' => 'application/fhir+json; charset=UTF-8',
        ]);
    }
}
