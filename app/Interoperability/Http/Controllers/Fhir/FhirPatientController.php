<?php

namespace App\Interoperability\Http\Controllers\Fhir;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PatientController;
use App\Interoperability\Helpers\InteropRouteHelper;
use App\Interoperability\Models\InteropExternalIdentifier;
use App\Interoperability\Mutators\Fhir\InboundFhirPatientMutator;
use App\Interoperability\Transformers\Fhir\PatientTransformer;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FhirPatientController extends Controller
{
    /**
     * Search FHIR R4 Patients.
     * GET /api/fhir/r4/Patient
     */
    public function index(Request $request): JsonResponse
    {
        $query = Patient::with(['user', 'hmo']);

        if ($request->filled('identifier')) {
            $identifier = $request->input('identifier');
            if (str_contains($identifier, '|')) {
                [$system, $val] = explode('|', $identifier, 2);
                $query->where(function ($q) use ($val) {
                    $q->where('file_no', $val)->orWhere('hmo_no', $val);
                });
            } else {
                $query->where(function ($q) use ($identifier) {
                    $q->where('file_no', $identifier)->orWhere('hmo_no', $identifier);
                });
            }
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->input('gender'));
        }

        if ($request->filled('birthdate')) {
            $query->where('dob', $request->input('birthdate'));
        }

        if ($request->filled('name')) {
            $name = $request->input('name');
            $query->whereHas('user', function ($q) use ($name) {
                $q->where('surname', 'LIKE', "%{$name}%")
                    ->orWhere('firstname', 'LIKE', "%{$name}%")
                    ->orWhere('othername', 'LIKE', "%{$name}%");
            });
        }

        $limit = min(50, (int) ($request->input('_count', 20)));
        $patients = $query->paginate($limit);

        $entries = [];
        foreach ($patients as $patient) {
            $fhirPatient = PatientTransformer::toFhir($patient);
            $entries[] = [
                'fullUrl' => InteropRouteHelper::fhirRoute('Patient', $fhirPatient['id']),
                'resource' => $fhirPatient,
            ];
        }

        $bundle = [
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => $patients->total(),
            'entry' => $entries,
        ];

        return response()->json($bundle, 200, [
            'Content-Type' => 'application/fhir+json; charset=UTF-8',
        ]);
    }

    /**
     * Read a single FHIR R4 Patient by ID (UUID or numeric ID).
     * GET /api/fhir/r4/Patient/{id}
     */
    public function show(string $id): JsonResponse
    {
        $localId = InteropExternalIdentifier::resolveLocalId('FHIR', 'Patient', $id);

        if (!$localId) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'not-found',
                        'diagnostics' => "Patient with identifier '{$id}' not found.",
                    ],
                ],
            ], 404, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
        }

        $patient = Patient::with(['user', 'hmo'])->find($localId);

        if (!$patient) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'not-found',
                        'diagnostics' => "Patient not found.",
                    ],
                ],
            ], 404, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
        }

        // BOLA / IDOR Verification: Ensure patient-level tokens only access their own compartment
        $user = auth()->user();
        if ($user) {
            $patientProfile = $user->patient_profile ?? Patient::where('user_id', $user->id)->first();
            if ($patientProfile && !$user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'DOCTOR', 'NURSE', 'RECEPTIONIST', 'RECORD_OFFICER'])) {
                if ($patientProfile->id !== $patient->id) {
                    return response()->json([
                        'resourceType' => 'OperationOutcome',
                        'issue' => [
                            [
                                'severity' => 'error',
                                'code' => 'security',
                                'diagnostics' => 'Access denied. Patient compartment restriction enforced under BOLA security guidelines.',
                            ],
                        ],
                    ], 403, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
                }
            }
        }

        return response()->json(PatientTransformer::toFhir($patient), 200, [
            'Content-Type' => 'application/fhir+json; charset=UTF-8',
        ]);
    }

    /**
     * Inbound Create FHIR Patient: Mutates payload to proxy PatientController@store.
     * POST /api/fhir/r4/Patient
     */
    public function store(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $mutatedRequest = InboundFhirPatientMutator::mutateToStoreRequest($payload, $request);

        // Execute local hospital controller store
        $patientController = app(PatientController::class);
        $patientController->store($mutatedRequest);

        // Retrieve created user & patient record
        $user = User::where('email', $mutatedRequest->email)->first();
        if (!$user || !$user->patient_profile) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'processing',
                        'diagnostics' => 'Patient creation could not be completed.',
                    ],
                ],
            ], 500, ['Content-Type' => 'application/fhir+json; charset=UTF-8']);
        }

        $patient = $user->patient_profile;
        $patient->loadMissing(['user', 'hmo']);

        // Persist external UUID if provided in request
        if (!empty($payload['id'])) {
            InteropExternalIdentifier::linkExternalId('FHIR', 'Patient', $patient->id, $payload['id']);
        }

        $fhirPatient = PatientTransformer::toFhir($patient);

        return response()->json($fhirPatient, 201, [
            'Content-Type' => 'application/fhir+json; charset=UTF-8',
            'Location' => InteropRouteHelper::fhirRoute('Patient', $fhirPatient['id']),
        ]);
    }
}
