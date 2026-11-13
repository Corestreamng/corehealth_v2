<?php

namespace App\Interoperability\Http\Controllers\Sdk;

use App\Http\Controllers\Controller;
use App\Interoperability\Models\PatientConsent;
use App\Interoperability\Models\SdkClient;
use App\Interoperability\Services\SdkTokenService;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SdkAuthController extends Controller
{
    protected SdkTokenService $tokenService;

    public function __construct(SdkTokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    /**
     * Issue an ephemeral SDK session token for an authenticated partner client.
     */
    public function issue(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|string|max:100',
            'client_secret' => 'required|string',
            'patient_id' => 'nullable|integer',
            'encounter_id' => 'nullable|integer',
            'clinician_id' => 'nullable|integer',
            'locale' => 'nullable|string|max:10',
            'scope' => 'nullable',
            'ttl' => 'nullable|integer|min:300|max:86400',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'invalid',
                        'diagnostics' => $validator->errors()->first(),
                    ],
                ],
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $clientId = $request->input('client_id');
        $clientSecret = $request->input('client_secret');

        $client = SdkClient::where('client_id', $clientId)->where('is_active', true)->first();
        if (!$client || !$client->verifySecret($clientSecret)) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'security',
                        'diagnostics' => 'Invalid client credentials. Check client_id and client_secret.',
                    ],
                ],
                'message' => 'Invalid client credentials.',
            ], 401, [
                'WWW-Authenticate' => 'Bearer realm="CoreHealth AI Scribe SDK"',
            ]);
        }

        $patientId = $request->input('patient_id');
        if ($patientId) {
            $patient = Patient::find($patientId);
            if (!$patient) {
                return response()->json([
                    'resourceType' => 'OperationOutcome',
                    'issue' => [
                        [
                            'severity' => 'error',
                            'code' => 'not-found',
                            'diagnostics' => "Patient with ID {$patientId} not found in hospital registry.",
                        ],
                    ],
                    'message' => "Patient ID {$patientId} not found.",
                ], 404);
            }

            // NDPA 2023 Consent verification
            $hasConsent = PatientConsent::hasActiveConsent($patientId, 'TREATMENT');
            if (!$hasConsent) {
                // If no explicit treatment consent recorded yet, we flag in response but allow clinical emergency under Sec 30 vital interest
                $consentStatus = 'PENDING_EXPLICIT_CONSENT';
            } else {
                $consentStatus = 'ACTIVE';
            }
        } else {
            $consentStatus = 'NOT_APPLICABLE';
        }

        $ttl = (int)($request->input('ttl', 3600));
        $claims = [
            'patient_id' => $patientId,
            'encounter_id' => $request->input('encounter_id'),
            'clinician_id' => $request->input('clinician_id'),
            'locale' => $request->input('locale', 'en-NG'),
            'scope' => $request->input('scope', 'scribe:process scribe:sign interop:compositions'),
        ];

        $token = $this->tokenService->generateToken(
            $client,
            $claims,
            $ttl,
            $request->ip()
        );

        return response()->json([
            'sdk_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'scope' => is_array($claims['scope']) ? implode(' ', $claims['scope']) : $claims['scope'],
            'patient_id' => $patientId,
            'encounter_id' => $request->input('encounter_id'),
            'clinician_id' => $request->input('clinician_id'),
            'ndpa_consent_status' => $consentStatus,
            'client' => [
                'client_id' => $client->client_id,
                'name' => $client->name,
                'organization' => $client->organization,
            ],
        ], 200);
    }

    /**
     * Revoke an active SDK session token.
     */
    public function revoke(Request $request): JsonResponse
    {
        $token = $request->input('token') ?: $request->bearerToken();
        if (!$token) {
            return response()->json([
                'message' => 'Token parameter is required for revocation.',
            ], 422);
        }

        $revoked = $this->tokenService->revokeToken($token);

        return response()->json([
            'revoked' => $revoked,
            'message' => $revoked ? 'Token successfully revoked.' : 'Token not found or already revoked.',
        ], 200);
    }
}
