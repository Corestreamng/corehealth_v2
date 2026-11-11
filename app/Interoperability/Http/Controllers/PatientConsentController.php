<?php

namespace App\Interoperability\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Interoperability\Models\PatientConsent;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientConsentController extends Controller
{
    /**
     * Record a new patient consent per NDPA 2023 Section 30.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'consent_type' => 'required|in:TREATMENT,RESEARCH,CLOUD_AI,THIRD_PARTY_TRANSFER,ALL',
            'ndpa_lawful_basis' => 'nullable|in:EXPLICIT_CONSENT,VITAL_INTEREST,LEGAL_OBLIGATION,PUBLIC_HEALTH',
            'notes' => 'nullable|string|max:500',
        ]);

        $consent = PatientConsent::create([
            'patient_id' => $validated['patient_id'],
            'consent_type' => $validated['consent_type'],
            'status' => 'ACTIVE',
            'ndpa_lawful_basis' => $validated['ndpa_lawful_basis'] ?? 'EXPLICIT_CONSENT',
            'granted_by' => auth()->id() ?? 1,
            'ip_address' => $request->ip(),
            'notes' => $validated['notes'] ?? null,
            'granted_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Patient consent recorded successfully per NDPA 2023 standards.',
            'data' => $consent,
        ], 201);
    }

    /**
     * Get all active consents for a specific patient.
     */
    public function show(int $patientId): JsonResponse
    {
        $patient = Patient::findOrFail($patientId);
        $consents = PatientConsent::where('patient_id', $patient->id)
            ->orderBy('granted_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'patient_id' => $patient->id,
            'consents' => $consents,
        ]);
    }

    /**
     * Revoke a consent per NDPA Section 34 (Right to Withdraw Consent).
     */
    public function revoke(int $id): JsonResponse
    {
        $consent = PatientConsent::findOrFail($id);
        $consent->update([
            'status' => 'REVOKED',
            'revoked_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Consent revoked successfully.',
            'data' => $consent,
        ]);
    }
}
