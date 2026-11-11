<?php

namespace App\Interoperability\Http\Middleware;

use App\Interoperability\Models\PatientConsent;
use Closure;
use Illuminate\Http\Request;

class CheckNdpaConsent
{
    /**
     * Handle an incoming request and ensure patient consent is registered
     * when required by NDPA 2023 Section 30.
     */
    public function handle(Request $request, Closure $next, string $consentType = 'TREATMENT')
    {
        $patientId = $this->resolvePatientId($request);

        if ($patientId && is_numeric($patientId)) {
            $hasConsent = PatientConsent::hasActiveConsent((int) $patientId, $consentType);

            // 1. Explicit affirmative consent verified
            if ($hasConsent) {
                $response = $next($request);
                $response->headers->set('X-NDPA-Consent-Status', 'CONSENT_VERIFIED');

                return $response;
            }

            // 2. Clinical Emergency / Direct Treatment Presumption (NDPA Sec 30(2)(a))
            if ($consentType === 'TREATMENT') {
                $response = $next($request);
                $response->headers->set('X-NDPA-Consent-Status', 'DEFAULT_VITAL_INTEREST');

                return $response;
            }

            // 3. Secondary processing (RESEARCH, CLOUD_AI, THIRD_PARTY_TRANSFER) strictly requires consent
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => 'security',
                        'diagnostics' => "Explicit patient consent is required under NDPA 2023 Section 30 for category: {$consentType}",
                    ],
                ],
                'status' => 'error',
                'code' => 'NDPA_CONSENT_REQUIRED',
                'message' => "Explicit patient consent is required under NDPA 2023 Section 30 for processing category: {$consentType}",
                'patient_id' => (int) $patientId,
                'required_consent_type' => $consentType,
            ], 403, [
                'Content-Type' => 'application/json; charset=UTF-8',
                'X-NDPA-Consent-Status' => 'CONSENT_REQUIRED',
            ]);
        }

        return $next($request);
    }

    /**
     * Resolve target patient ID from routes or query/body parameters.
     */
    protected function resolvePatientId(Request $request): ?int
    {
        if ($request->route('patientId')) {
            return (int) $request->route('patientId');
        }

        if ($request->route('patient')) {
            return (int) $request->route('patient');
        }

        if ($request->filled('patient_id') && is_numeric($request->input('patient_id'))) {
            return (int) $request->input('patient_id');
        }

        // Context-aware route {id} for Patient or Consent routes
        if ($request->is('*/Patient/*') || $request->is('*/consent/*') || $request->is('*/ccda/*')) {
            $routeId = $request->route('id') ?? $request->route('patientId');
            if ($routeId && is_numeric($routeId)) {
                return (int) $routeId;
            }
        }

        // FHIR string or nested reference patterns (e.g. "Patient/105", {"reference": "Patient/105"})
        $rawRef = null;
        if ($request->has('subject.reference')) {
            $rawRef = $request->input('subject.reference');
        } elseif ($request->has('patient.reference')) {
            $rawRef = $request->input('patient.reference');
        } elseif ($request->has('patient')) {
            $val = $request->input('patient');
            $rawRef = is_array($val) ? ($val['reference'] ?? null) : $val;
        } elseif ($request->has('subject')) {
            $val = $request->input('subject');
            $rawRef = is_array($val) ? ($val['reference'] ?? null) : $val;
        }

        if ($rawRef && is_scalar($rawRef) && preg_match('/(?:Patient\/|ch2-pat-)?(\d+)/', (string) $rawRef, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
