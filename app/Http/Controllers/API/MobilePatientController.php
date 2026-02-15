<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AdmissionRequest;
use App\Models\Encounter;
use App\Models\ImagingServiceRequest;
use App\Models\LabServiceRequest;
use App\Models\patient;
use App\Models\Procedure;
use App\Models\ProductRequest;
use App\Models\VitalSign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MobilePatientController extends Controller
{
    /**
     * GET /api/mobile/patient/profile
     */
    public function myProfile()
    {
        try {
            $user = Auth::user();
            $patient = patient::with('user', 'hmo')->where('user_id', $user->id)->first();

            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient profile not found.'], 404);
            }

            return response()->json([
                'status' => true,
                'data'   => [
                    'id'               => $patient->id,
                    'name'             => $patient->user->name ?? '',
                    'email'            => $patient->user->email ?? '',
                    'file_no'          => $patient->file_no ?? '',
                    'gender'           => $patient->gender ?? '',
                    'dob'              => $patient->dob ?? '',
                    'blood_group'      => $patient->blood_group ?? '',
                    'genotype'         => $patient->genotype ?? '',
                    'phone'            => $patient->phone_no ?? '',
                    'address'          => $patient->address ?? '',
                    'nationality'      => $patient->nationality ?? '',
                    'ethnicity'        => $patient->ethnicity ?? '',
                    'disability'       => $patient->disability ?? '',
                    'allergies'        => $patient->allergies ?? [],
                    'hmo_name'         => $patient->hmo->name ?? 'N/A',
                    'hmo_no'           => $patient->hmo_no ?? '',
                    'next_of_kin_name'    => $patient->next_of_kin_name ?? '',
                    'next_of_kin_phone'   => $patient->next_of_kin_phone ?? '',
                    'next_of_kin_address' => $patient->next_of_kin_address ?? '',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile myProfile error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to load profile.'], 500);
        }
    }

    /**
     * GET /api/mobile/patient/encounters
     */
    public function myEncounters(Request $request)
    {
        try {
            $patient = $this->getAuthenticatedPatient();
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found.'], 404);
            }

            $encounters = Encounter::with('doctor')
                ->where('patient_id', $patient->id)
                ->where('completed', true)
                ->orderBy('created_at', 'DESC')
                ->paginate($request->input('per_page', 20));

            $items = $encounters->getCollection()->map(function ($enc) {
                $labCount = LabServiceRequest::where('encounter_id', $enc->id)->where('status', '>', 0)->count();
                $imagingCount = ImagingServiceRequest::where('encounter_id', $enc->id)->where('status', '>', 0)->count();
                $rxCount = ProductRequest::where('encounter_id', $enc->id)->where('status', '>', 0)->count();

                return [
                    'id'                    => $enc->id,
                    'doctor_name'           => $enc->doctor ? $enc->doctor->name : 'Unknown',
                    'reasons_for_encounter' => $enc->reasons_for_encounter,
                    'comment_1'             => $enc->reasons_for_encounter_comment_1,
                    'comment_2'             => $enc->reasons_for_encounter_comment_2,
                    'has_notes'             => !empty($enc->notes),
                    'lab_count'             => $labCount,
                    'imaging_count'         => $imagingCount,
                    'prescription_count'    => $rxCount,
                    'created_at'            => $enc->created_at->toIso8601String(),
                ];
            });

            return response()->json([
                'status' => true,
                'data'   => $items,
                'meta'   => [
                    'total'     => $encounters->total(),
                    'page'      => $encounters->currentPage(),
                    'per_page'  => $encounters->perPage(),
                    'last_page' => $encounters->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile myEncounters error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to load encounters.'], 500);
        }
    }

    /**
     * GET /api/mobile/patient/encounters/{encounter}
     */
    public function encounterDetail(Encounter $encounter)
    {
        try {
            $patient = $this->getAuthenticatedPatient();
            if (!$patient || $encounter->patient_id !== $patient->id) {
                return response()->json(['status' => false, 'message' => 'Unauthorized.'], 403);
            }

            $labs = LabServiceRequest::with('service')
                ->where('encounter_id', $encounter->id)
                ->where('status', '>', 0)
                ->orderBy('created_at', 'DESC')
                ->get()
                ->map(fn($lab) => [
                    'id'           => $lab->id,
                    'service_name' => $lab->service->service_name ?? 'Unknown',
                    'status'       => (int) $lab->status,
                    'status_label' => $this->labStatusLabel($lab->status),
                    'result'       => $lab->result,
                    'result_date'  => $lab->result_date,
                    'created_at'   => $lab->created_at->toIso8601String(),
                ]);

            $imaging = ImagingServiceRequest::with('service')
                ->where('encounter_id', $encounter->id)
                ->where('status', '>', 0)
                ->orderBy('created_at', 'DESC')
                ->get()
                ->map(fn($img) => [
                    'id'           => $img->id,
                    'service_name' => $img->service->service_name ?? 'Unknown',
                    'status'       => (int) $img->status,
                    'status_label' => $this->imagingStatusLabel($img->status),
                    'result'       => $img->result,
                    'result_date'  => $img->result_date,
                    'created_at'   => $img->created_at->toIso8601String(),
                ]);

            $prescriptions = ProductRequest::with('product')
                ->where('encounter_id', $encounter->id)
                ->where('status', '>', 0)
                ->orderBy('created_at', 'DESC')
                ->get()
                ->map(fn($rx) => [
                    'id'           => $rx->id,
                    'product_name' => $rx->product->product_name ?? 'Unknown',
                    'dose'         => $rx->dose,
                    'status'       => (int) $rx->status,
                    'status_label' => $this->prescriptionStatusLabel($rx->status),
                    'created_at'   => $rx->created_at->toIso8601String(),
                ]);

            $procedures = Procedure::with('service')
                ->where('encounter_id', $encounter->id)
                ->orderBy('requested_on', 'DESC')
                ->get()
                ->map(fn($p) => [
                    'id'               => $p->id,
                    'service_name'     => $p->service->service_name ?? 'Unknown',
                    'priority'         => $p->priority,
                    'procedure_status' => $p->procedure_status,
                    'status_label'     => ucfirst(str_replace('_', ' ', $p->procedure_status ?? '')),
                    'scheduled_date'   => $p->scheduled_date,
                    'created_at'       => $p->created_at->toIso8601String(),
                ]);

            return response()->json([
                'status' => true,
                'data'   => [
                    'encounter' => [
                        'id'                    => $encounter->id,
                        'doctor_name'           => $encounter->doctor ? $encounter->doctor->name : 'Unknown',
                        'reasons_for_encounter' => $encounter->reasons_for_encounter,
                        'comment_1'             => $encounter->reasons_for_encounter_comment_1,
                        'comment_2'             => $encounter->reasons_for_encounter_comment_2,
                        'notes'                 => $encounter->notes,
                        'created_at'            => $encounter->created_at->toIso8601String(),
                    ],
                    'labs'          => $labs,
                    'imaging'       => $imaging,
                    'prescriptions' => $prescriptions,
                    'procedures'    => $procedures,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile patient encounterDetail error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to load encounter detail.'], 500);
        }
    }

    /**
     * GET /api/mobile/patient/vitals
     */
    public function myVitals(Request $request)
    {
        try {
            $patient = $this->getAuthenticatedPatient();
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found.'], 404);
            }

            $vitals = VitalSign::where('patient_id', $patient->id)
                ->orderBy('created_at', 'DESC')
                ->paginate($request->input('per_page', 20));

            $items = $vitals->getCollection()->map(fn($v) => [
                'id'              => $v->id,
                'blood_pressure'  => $v->blood_pressure,
                'temperature'     => $v->temp,
                'heart_rate'      => $v->heart_rate,
                'respiratory_rate' => $v->resp_rate,
                'spo2'            => $v->spo2,
                'weight'          => $v->weight,
                'height'          => $v->height,
                'bmi'             => $v->bmi,
                'blood_sugar'     => $v->blood_sugar,
                'pain_score'      => $v->pain_score,
                'other_notes'     => $v->other_notes,
                'time_taken'      => $v->time_taken,
                'created_at'      => $v->created_at->toIso8601String(),
            ]);

            return response()->json([
                'status' => true,
                'data'   => $items,
                'meta'   => [
                    'total'     => $vitals->total(),
                    'page'      => $vitals->currentPage(),
                    'per_page'  => $vitals->perPage(),
                    'last_page' => $vitals->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile myVitals error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to load vitals.'], 500);
        }
    }

    /**
     * GET /api/mobile/patient/lab-results
     */
    public function myLabResults(Request $request)
    {
        try {
            $patient = $this->getAuthenticatedPatient();
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found.'], 404);
            }

            $labs = LabServiceRequest::with('service')
                ->where('patient_id', $patient->id)
                ->where('status', '>', 0)
                ->orderBy('created_at', 'DESC')
                ->paginate($request->input('per_page', 20));

            $items = $labs->getCollection()->map(fn($lab) => [
                'id'           => $lab->id,
                'service_name' => $lab->service->service_name ?? 'Unknown',
                'status'       => (int) $lab->status,
                'status_label' => $this->labStatusLabel($lab->status),
                'result'       => $lab->result,
                'result_date'  => $lab->result_date,
                'created_at'   => $lab->created_at->toIso8601String(),
            ]);

            return response()->json([
                'status' => true,
                'data'   => $items,
                'meta'   => [
                    'total'     => $labs->total(),
                    'page'      => $labs->currentPage(),
                    'per_page'  => $labs->perPage(),
                    'last_page' => $labs->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile myLabResults error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to load lab results.'], 500);
        }
    }

    /**
     * GET /api/mobile/patient/imaging-results
     */
    public function myImagingResults(Request $request)
    {
        try {
            $patient = $this->getAuthenticatedPatient();
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found.'], 404);
            }

            $imaging = ImagingServiceRequest::with('service')
                ->where('patient_id', $patient->id)
                ->where('status', '>', 0)
                ->orderBy('created_at', 'DESC')
                ->paginate($request->input('per_page', 20));

            $items = $imaging->getCollection()->map(fn($img) => [
                'id'           => $img->id,
                'service_name' => $img->service->service_name ?? 'Unknown',
                'status'       => (int) $img->status,
                'status_label' => $this->imagingStatusLabel($img->status),
                'result'       => $img->result,
                'result_date'  => $img->result_date,
                'created_at'   => $img->created_at->toIso8601String(),
            ]);

            return response()->json([
                'status' => true,
                'data'   => $items,
                'meta'   => [
                    'total'     => $imaging->total(),
                    'page'      => $imaging->currentPage(),
                    'per_page'  => $imaging->perPage(),
                    'last_page' => $imaging->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile myImagingResults error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to load imaging results.'], 500);
        }
    }

    /**
     * GET /api/mobile/patient/prescriptions
     */
    public function myPrescriptions(Request $request)
    {
        try {
            $patient = $this->getAuthenticatedPatient();
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found.'], 404);
            }

            $prescriptions = ProductRequest::with('product')
                ->where('patient_id', $patient->id)
                ->orderBy('created_at', 'DESC')
                ->paginate($request->input('per_page', 20));

            $items = $prescriptions->getCollection()->map(fn($rx) => [
                'id'           => $rx->id,
                'product_name' => $rx->product->product_name ?? 'Unknown',
                'dose'         => $rx->dose,
                'qty'          => $rx->qty,
                'status'       => (int) $rx->status,
                'status_label' => $this->prescriptionStatusLabel($rx->status),
                'created_at'   => $rx->created_at->toIso8601String(),
            ]);

            return response()->json([
                'status' => true,
                'data'   => $items,
                'meta'   => [
                    'total'     => $prescriptions->total(),
                    'page'      => $prescriptions->currentPage(),
                    'per_page'  => $prescriptions->perPage(),
                    'last_page' => $prescriptions->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile myPrescriptions error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to load prescriptions.'], 500);
        }
    }

    /**
     * GET /api/mobile/patient/procedures
     */
    public function myProcedures(Request $request)
    {
        try {
            $patient = $this->getAuthenticatedPatient();
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found.'], 404);
            }

            $procedures = Procedure::with('service')
                ->where('patient_id', $patient->id)
                ->orderBy('requested_on', 'DESC')
                ->paginate($request->input('per_page', 20));

            $items = $procedures->getCollection()->map(fn($p) => [
                'id'               => $p->id,
                'service_name'     => $p->service->service_name ?? 'Unknown',
                'priority'         => $p->priority,
                'procedure_status' => $p->procedure_status,
                'status_label'     => ucfirst(str_replace('_', ' ', $p->procedure_status ?? '')),
                'scheduled_date'   => $p->scheduled_date,
                'outcome'          => $p->outcome,
                'created_at'       => $p->created_at->toIso8601String(),
            ]);

            return response()->json([
                'status' => true,
                'data'   => $items,
                'meta'   => [
                    'total'     => $procedures->total(),
                    'page'      => $procedures->currentPage(),
                    'per_page'  => $procedures->perPage(),
                    'last_page' => $procedures->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile myProcedures error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to load procedures.'], 500);
        }
    }

    /**
     * GET /api/mobile/patient/admissions
     */
    public function myAdmissions(Request $request)
    {
        try {
            $patient = $this->getAuthenticatedPatient();
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found.'], 404);
            }

            $admissions = AdmissionRequest::with('doctor', 'bed')
                ->where('patient_id', $patient->id)
                ->orderBy('created_at', 'DESC')
                ->paginate($request->input('per_page', 20));

            $items = $admissions->getCollection()->map(fn($a) => [
                'id'               => $a->id,
                'doctor_name'      => $a->doctor ? $a->doctor->name : 'Unknown',
                'admission_status' => $a->admission_status ?? $a->status,
                'admission_reason' => $a->admission_reason,
                'discharge_reason' => $a->discharge_reason,
                'discharged'       => (bool) $a->discharged,
                'discharge_date'   => $a->discharge_date,
                'priority'         => $a->priority,
                'bed_info'         => $a->bed ? ($a->bed->name ?? 'Assigned') : 'Pending',
                'days_admitted'    => $a->days_admitted ?? null,
                'created_at'       => $a->created_at->toIso8601String(),
            ]);

            return response()->json([
                'status' => true,
                'data'   => $items,
                'meta'   => [
                    'total'     => $admissions->total(),
                    'page'      => $admissions->currentPage(),
                    'per_page'  => $admissions->perPage(),
                    'last_page' => $admissions->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile myAdmissions error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to load admissions.'], 500);
        }
    }

    // ── Private helpers ─────────────────────────────────────────────

    private function getAuthenticatedPatient(): ?patient
    {
        $user = Auth::user();
        return patient::where('user_id', $user->id)->first();
    }

    private function labStatusLabel($status): string
    {
        return match ((int) $status) {
            0 => 'Dismissed',
            1 => 'Requested',
            2 => 'Billed/Sample',
            3 => 'Results Ready',
            default => 'Unknown',
        };
    }

    private function imagingStatusLabel($status): string
    {
        return match ((int) $status) {
            0 => 'Dismissed',
            1 => 'Requested',
            2 => 'Billed',
            3 => 'Results Ready',
            default => 'Unknown',
        };
    }

    private function prescriptionStatusLabel($status): string
    {
        return match ((int) $status) {
            0 => 'Dismissed',
            1 => 'Requested',
            2 => 'Billed',
            3 => 'Dispensed',
            default => 'Unknown',
        };
    }
}
