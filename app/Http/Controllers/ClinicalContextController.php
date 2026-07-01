<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\VitalSign;
use App\Models\Encounter;
use App\Models\ProductRequest;
use App\Models\LabServiceRequest;
use App\Models\ImagingServiceRequest;
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
                    'form_data' => $v->form_data,
                ];
            });

        return response()->json($vitals);
    }

    /**
     * Get patient clinical/encounter notes.
     */
    public function getNotes($patientId)
    {
        $patient = Patient::findOrFail($patientId);

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
        $patient = Patient::findOrFail($patientId);

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
                'drug_name' => $med->is_free_form ? $med->free_form_name : ($med->product ? $med->product->product_name : 'N/A'),
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
        $patient = Patient::findOrFail($patientId);

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

    /**
     * Get patient lab investigation results for clinical context modal.
     */
    public function getLabs($patientId)
    {
        Patient::findOrFail($patientId);

        $labs = LabServiceRequest::with(['service', 'doctor', 'patient.user', 'resultBy'])
            ->where('patient_id', $patientId)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $result = $labs->map(function ($lab) {
            $statusMap = [0 => 'requested', 1 => 'sample_taken', 2 => 'processing', 3 => 'completed'];
            $status = $statusMap[$lab->status] ?? 'requested';

            return [
                'id' => $lab->id,
                'service_name' => $lab->is_free_form ? $lab->free_form_name : ($lab->service ? $lab->service->service_name : 'N/A'),
                'lab_number' => $lab->lab_number,
                'status' => $status,
                'priority' => $lab->priority,
                'result_preview' => $lab->result ? \Illuminate\Support\Str::limit(strip_tags($lab->result), 100) : null,
                'has_result' => !empty($lab->result) || !empty($lab->result_data),
                'result' => $lab->result,
                'result_data' => $lab->result_data,
                'attachments' => $lab->attachments,
                'requested_date' => $lab->created_at->format('h:i a D M j, Y'),
                'sample_date' => $lab->sample_date ? \Carbon\Carbon::parse($lab->sample_date)->format('h:i a D M j, Y') : null,
                'result_date' => $lab->result_date ? \Carbon\Carbon::parse($lab->result_date)->format('h:i a D M j, Y') : null,
                'doctor' => $lab->doctor ? $lab->doctor->firstname . ' ' . $lab->doctor->surname : 'N/A',
                'result_by' => $lab->resultBy ? $lab->resultBy->firstname . ' ' . $lab->resultBy->surname : null,
                'note' => $lab->note,
                'approved' => !empty($lab->approved_by),
                'patient' => $lab->patient ? [
                    'file_no' => $lab->patient->file_no,
                    'name' => $lab->patient->user ? $lab->patient->user->firstname . ' ' . $lab->patient->user->surname : 'N/A',
                    'gender' => $lab->patient->gender,
                    'date_of_birth' => $lab->patient->date_of_birth,
                ] : null,
            ];
        });

        return response()->json($result);
    }

    /**
     * Get patient imaging results for clinical context modal.
     */
    public function getImaging($patientId)
    {
        Patient::findOrFail($patientId);

        $imaging = ImagingServiceRequest::with(['service', 'doctor', 'patient.user', 'resultBy'])
            ->where('patient_id', $patientId)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $result = $imaging->map(function ($img) {
            $statusMap = [0 => 'requested', 1 => 'in_progress', 2 => 'processing', 3 => 'completed'];
            $status = $statusMap[$img->status] ?? 'requested';

            return [
                'id' => $img->id,
                'service_name' => $img->is_free_form ? $img->free_form_name : ($img->service ? $img->service->service_name : 'N/A'),
                'status' => $status,
                'priority' => $img->priority,
                'result_preview' => $img->result ? \Illuminate\Support\Str::limit(strip_tags($img->result), 100) : null,
                'has_result' => !empty($img->result) || !empty($img->result_data),
                'has_attachments' => !empty($img->attachments),
                'result' => $img->result,
                'result_data' => $img->result_data,
                'attachments' => $img->attachments,
                'requested_date' => $img->created_at->format('h:i a D M j, Y'),
                'result_date' => $img->result_date ? \Carbon\Carbon::parse($img->result_date)->format('h:i a D M j, Y') : null,
                'doctor' => $img->doctor ? $img->doctor->firstname . ' ' . $img->doctor->surname : 'N/A',
                'result_by' => $img->resultBy ? $img->resultBy->firstname . ' ' . $img->resultBy->surname : null,
                'note' => $img->note,
                'approved' => !empty($img->approved_by),
                'patient' => $img->patient ? [
                    'file_no' => $img->patient->file_no,
                    'name' => $img->patient->user ? $img->patient->user->firstname . ' ' . $img->patient->user->surname : 'N/A',
                    'gender' => $img->patient->gender,
                    'date_of_birth' => $img->patient->date_of_birth,
                ] : null,
            ];
        });

        return response()->json($result);
    }

    /**
     * Get patient procedures for clinical context modal.
     */
    public function getProcedures($patientId)
    {
        $patient = Patient::findOrFail($patientId);

        $procedures = \App\Models\Procedure::with(['service', 'requestedByUser', 'encounter', 'procedureDefinition'])
            ->where('patient_id', $patientId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json($procedures->map(function ($p) {
            $status = $p->procedure_status ?? 'requested';
            return [
                'id' => $p->id,
                'service_name' => $p->is_free_form ? $p->free_form_name : ($p->service ? $p->service->service_name : ($p->procedureDefinition ? $p->procedureDefinition->name : 'N/A')),
                'status' => $status,
                'priority' => $p->priority ?? 'routine',
                'requested_date' => $p->created_at->format('h:i a D M j, Y'),
                'doctor' => $p->requestedByUser ? $p->requestedByUser->firstname . ' ' . $p->requestedByUser->surname : 'N/A',
                'location' => $p->location ?? 'N/A',
                'scheduled_time' => $p->scheduled_time ? \Carbon\Carbon::parse($p->scheduled_time)->format('h:i a D M j, Y') : null,
            ];
        }));
    }
}
