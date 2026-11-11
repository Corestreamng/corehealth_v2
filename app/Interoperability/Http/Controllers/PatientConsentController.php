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
     * Record a new patient consent per NDPA 2023 Section 30 & 31.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'consent_type' => 'required|in:TREATMENT,RESEARCH,CLOUD_AI,THIRD_PARTY_TRANSFER,ALL',
            'ndpa_lawful_basis' => 'nullable|in:EXPLICIT_CONSENT,PARENTAL_AUTHORIZATION,VITAL_INTEREST,LEGAL_OBLIGATION,PUBLIC_HEALTH',
            'notes' => 'nullable|string|max:500',
            'parent_guardian_name' => 'nullable|string|max:191',
            'parent_guardian_nin' => 'nullable|string|max:32',
            'parent_guardian_relationship' => 'nullable|string|max:64',
        ]);

        $patient = Patient::findOrFail($validated['patient_id']);
        $isMinor = false;
        if ($patient->dob) {
            $age = now()->diffInYears($patient->dob);
            if ($age < 18) {
                $isMinor = true;
            }
        }

        $consent = PatientConsent::create([
            'patient_id' => $patient->id,
            'consent_type' => $validated['consent_type'],
            'status' => 'ACTIVE',
            'ndpa_lawful_basis' => $validated['ndpa_lawful_basis'] ?? 'EXPLICIT_CONSENT',
            'granted_by' => auth()->id() ?? 1,
            'ip_address' => $request->ip(),
            'notes' => $validated['notes'] ?? null,
            'parent_guardian_name' => $validated['parent_guardian_name'] ?? null,
            'parent_guardian_nin' => $validated['parent_guardian_nin'] ?? null,
            'parent_guardian_relationship' => $validated['parent_guardian_relationship'] ?? null,
            'is_minor' => $isMinor,
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

        // BOLA / IDOR Verification: Ensure patient-level tokens only access their own record
        $user = auth()->user();
        if ($user) {
            $patientProfile = $user->patient_profile ?? Patient::where('user_id', $user->id)->first();
            if ($patientProfile && !$user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'DOCTOR', 'NURSE', 'RECEPTIONIST', 'RECORD_OFFICER'])) {
                if ($patientProfile->id !== $patient->id) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Access denied. You may only view consents for your own patient profile.',
                    ], 403);
                }
            }
        }

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
