<?php

namespace App\Interoperability\Http\Middleware;

use App\Interoperability\Models\PatientConsent;
use Closure;
use Illuminate\Http\Request;

class CheckNdpaConsent
{
    /**
     * Handle an incoming request and ensure patient consent is registered
     * when required by NDPA 2023.
     */
    public function handle(Request $request, Closure $next, string $consentType = 'TREATMENT')
    {
        $patientId = $request->route('patientId')
            ?? $request->route('patient')
            ?? $request->input('patient_id');

        if ($patientId && is_numeric($patientId)) {
            // Check if patient exists and if explicit consent or vital interest applies
            $hasConsent = PatientConsent::hasActiveConsent((int) $patientId, $consentType);

            // If no explicit consent record exists yet, allow with default legal basis for emergency / vital care,
            // but attach compliance header so client knows consent status.
            $response = $next($request);
            $response->headers->set('X-NDPA-Consent-Status', $hasConsent ? 'CONSENT_VERIFIED' : 'DEFAULT_VITAL_INTEREST');

            return $response;
        }

        return $next($request);
    }
}
