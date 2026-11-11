<?php

namespace App\Interoperability\Http\Controllers\Fhir;

use App\Http\Controllers\Controller;
use App\Interoperability\Helpers\InteropRouteHelper;
use App\Interoperability\Models\InteropExternalIdentifier;
use App\Interoperability\Transformers\Fhir\PractitionerTransformer;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FhirPractitionerController extends Controller
{
    /**
     * Search FHIR R4 Practitioners.
     * GET /api/fhir/r4/Practitioner
     */
    public function index(Request $request): JsonResponse
    {
        $query = Staff::with('user');

        if ($request->filled('name')) {
            $name = $request->input('name');
            $query->whereHas('user', function ($q) use ($name) {
                $q->where('surname', 'LIKE', "%{$name}%")
                    ->orWhere('firstname', 'LIKE', "%{$name}%");
            });
        }

        $limit = min(50, (int) ($request->input('_count', 20)));
        $staffMembers = $query->paginate($limit);

        $entries = [];
        foreach ($staffMembers as $staff) {
            $fhirPrac = PractitionerTransformer::toFhir($staff);
            $entries[] = [
                'fullUrl' => InteropRouteHelper::fhirRoute('Practitioner', $fhirPrac['id']),
                'resource' => $fhirPrac,
            ];
        }

        $bundle = [
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => $staffMembers->total(),
            'entry' => $entries,
        ];

        return response()->json($bundle, 200, [
            'Content-Type' => 'application/fhir+json; charset=UTF-8',
        ]);
    }

    /**
     * Read a single FHIR R4 Practitioner.
     * GET /api/fhir/r4/Practitioner/{id}
     */
    public function show(string $id): JsonResponse
    {
        $localId = InteropExternalIdentifier::resolveLocalId('FHIR', 'Practitioner', $id);

        if (!$localId) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'not-found',
                        'diagnostics' => "Practitioner '{$id}' not found.",
                    ],
                ],
            ], 404, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
        }

        $user = User::with('staff')->find($localId);

        if (!$user) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'not-found',
                        'diagnostics' => "Practitioner not found.",
                    ],
                ],
            ], 404, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
        }

        return response()->json(PractitionerTransformer::toFhir($user), 200, [
            'Content-Type' => 'application/fhir+json; charset=UTF-8',
        ]);
    }
}
