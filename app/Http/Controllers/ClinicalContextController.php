<?php

namespace App\Http\Controllers;

use App\Models\patient;
use App\Models\VitalSign;
use App\Models\Encounter;
use App\Models\ProductRequest;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * Shared Clinical Context Controller
 *
 * Provides patient clinical data (vitals, notes, medications, allergies)
 * used by the clinical context modal across all workbenches.
 */
class ClinicalContextController extends Controller
{
    /**
     * Get patient vitals for clinical context modal.
     * Returns all supported vital sign parameters.
     */
    public function getVitals($patientId)
    {
        $vitals = VitalSign::where('patient_id', $patientId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($v) {
                return [
                    'id' => $v->id,
                    'created_at' => $v->created_at,
                    'blood_pressure' => $v->blood_pressure,
                    'temp' => $v->temp,
                    'heart_rate' => $v->heart_rate,
                    'resp_rate' => $v->resp_rate,
                    'weight' => $v->weight,
                    'height' => $v->height,
                    'spo2' => $v->spo2,
                    'blood_sugar' => $v->blood_sugar,
                    'bmi' => $v->bmi,
                    'pain_score' => $v->pain_score,
                    'other_notes' => $v->other_notes,
                    'time_taken' => $v->time_taken,
                    'taken_by' => $v->taken_by ? userfullname($v->taken_by) : null,
                    'source' => $v->source,
                ];
            });

        return response()->json($vitals);
    }

    /**
     * Get patient clinical/encounter notes.
     */
    public function getNotes($patientId)
    {
        $patient = patient::findOrFail($patientId);

        $encounters = Encounter::with(['doctor.staff_profile.specialization'])
            ->where('patient_id', $patientId)
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $notes = $encounters->map(function ($encounter) {
            $specialty = 'N/A';
            if ($encounter->doctor && $encounter->doctor->staff_profile && $encounter->doctor->staff_profile->specialization) {
                $specialty = $encounter->doctor->staff_profile->specialization->name;
            }

            return [
                'id' => $encounter->id,
                'date' => $encounter->created_at->toISOString(),
                'date_formatted' => $encounter->created_at->format('h:i a D M j, Y'),
                'doctor' => $encounter->doctor ? $encounter->doctor->firstname . ' ' . $encounter->doctor->surname : 'N/A',
                'doctor_id' => $encounter->doctor_id,
                'specialty' => $specialty,
                'reasons_for_encounter' => $encounter->reasons_for_encounter,
                'notes' => $encounter->notes,
                'notes_preview' => \Illuminate\Support\Str::limit(strip_tags($encounter->notes), 150),
            ];
        });

        return response()->json($notes);
    }

    /**
     * Get patient medications (prescription history).
     */
    public function getMedications($patientId)
    {
        $patient = patient::findOrFail($patientId);

        $meds = ProductRequest::with(['product', 'doctor', 'biller', 'dispenser'])
            ->where('patient_id', $patientId)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $result = $meds->map(function ($med) {
            $status = 'pending';
            if ($med->dispensed_by) {
                $status = 'dispensed';
            } elseif ($med->billed_by) {
                $status = 'billed';
            }

            return [
                'id' => $med->id,
                'drug_name' => $med->product ? $med->product->product_name : 'N/A',
                'product_code' => $med->product ? $med->product->product_code : null,
                'dose' => $med->dose ?? 'N/A',
                'freq' => $med->freq ?? 'N/A',
                'duration' => $med->duration ?? 'N/A',
                'status' => $status,
                'requested_date' => $med->created_at->format('h:i a D M j, Y'),
                'doctor' => $med->doctor ? $med->doctor->firstname . ' ' . $med->doctor->surname : 'N/A',
            ];
        });

        return response()->json($result);
    }

    /**
     * Get patient allergies and medical history.
     */
    public function getAllergies($patientId)
    {
        $patient = patient::findOrFail($patientId);

        $allergies = $patient->allergies ?? [];

        // Ensure it's always an array
        if (is_string($allergies)) {
            $decoded = json_decode($allergies, true);
            $allergies = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $allergies)));
        } elseif (!is_array($allergies)) {
            $allergies = [];
        }

        return response()->json([
            'allergies' => array_values($allergies),
            'medical_history' => $patient->medical_history ?? null,
        ]);
    }
}
