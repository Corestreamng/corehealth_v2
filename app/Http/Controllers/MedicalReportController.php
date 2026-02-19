<?php

namespace App\Http\Controllers;

use App\Models\MedicalReport;
use App\Models\patient;
use App\Models\Encounter;
use App\Models\VitalSign;
use App\Models\Staff;
use App\Models\ImagingServiceRequest;
use App\Models\Procedure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MedicalReportController extends Controller
{
    /**
     * Store a new medical report (draft).
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'patient_id' => 'required|exists:patients,id',
                'encounter_id' => 'nullable|exists:encounters,id',
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'report_date' => 'nullable|date',
            ]);

            $report = MedicalReport::create([
                'patient_id' => $validated['patient_id'],
                'encounter_id' => $validated['encounter_id'] ?? null,
                'doctor_id' => Auth::id(),
                'title' => $validated['title'],
                'content' => $validated['content'],
                'report_date' => $validated['report_date'] ?? now()->toDateString(),
                'status' => MedicalReport::STATUS_DRAFT,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Medical report saved as draft.',
                'report' => $report,
            ]);
        } catch (\Exception $e) {
            Log::error('Medical report creation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create report: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing draft report.
     */
    public function update(Request $request, MedicalReport $medical_report)
    {
        try {
            if ($medical_report->isFinalized()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot edit a finalized report.',
                ], 403);
            }

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'report_date' => 'nullable|date',
            ]);

            $medical_report->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Report updated successfully.',
                'report' => $medical_report,
            ]);
        } catch (\Exception $e) {
            Log::error('Medical report update failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update report: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Finalize a report (locks it from editing).
     */
    public function finalize(MedicalReport $medical_report)
    {
        try {
            if ($medical_report->isFinalized()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Report is already finalized.',
                ], 400);
            }

            $medical_report->update([
                'status' => MedicalReport::STATUS_FINALIZED,
                'finalized_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Report finalized successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to finalize report: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a draft report.
     */
    public function destroy(MedicalReport $medical_report)
    {
        try {
            if ($medical_report->isFinalized()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete a finalized report.',
                ], 403);
            }

            $medical_report->delete();

            return response()->json([
                'success' => true,
                'message' => 'Report deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete report: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a single report (for editing).
     */
    public function show(MedicalReport $medical_report)
    {
        return response()->json([
            'success' => true,
            'report' => $medical_report->load('patient.user', 'doctor'),
        ]);
    }

    /**
     * List reports for a patient.
     */
    public function listByPatient(Request $request, $patientId)
    {
        $reports = MedicalReport::where('patient_id', $patientId)
            ->with('doctor')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'title' => $r->title,
                    'status' => $r->status,
                    'report_date' => $r->report_date->format('M j, Y'),
                    'doctor' => $r->doctor ? trim(($r->doctor->surname ?? '') . ' ' . ($r->doctor->firstname ?? '')) : '-',
                    'finalized_at' => $r->finalized_at ? $r->finalized_at->format('M j, Y h:i A') : null,
                    'created_at' => $r->created_at->format('M j, Y h:i A'),
                ];
            });

        return response()->json([
            'success' => true,
            'reports' => $reports,
        ]);
    }

    /**
     * Get patient data sections for the sidebar data picker.
     * Returns demographics, vitals, diagnoses, medications, labs summary.
     */
    public function getPatientData(Request $request, $patientId)
    {
        try {
            $patient = patient::with('user')->find($patientId);
            if (!$patient) {
                return response()->json(['success' => false, 'message' => 'Patient not found'], 404);
            }

            $data = [];
            $user = $patient->user;

            // Demographics
            $data['demographics'] = [
                'name' => $user ? trim(($user->surname ?? '') . ' ' . ($user->firstname ?? '') . ' ' . ($user->othername ?? '')) : '-',
                'file_no' => $patient->file_no ?? '-',
                'sex' => $patient->gender ?? '-',
                'dob' => $patient->dob ? Carbon::parse($patient->dob)->format('M j, Y') : '-',
                'age' => $patient->dob ? Carbon::parse($patient->dob)->age . ' years' : '-',
                'phone' => $patient->phone_no ?? '-',
                'address' => $patient->address ?? '-',
                'blood_group' => $patient->blood_group ?? '-',
            ];

            // Latest vitals
            $latestVitals = VitalSign::where('patient_id', $patientId)
                ->orderBy('created_at', 'desc')
                ->first();
            // Parse blood_pressure field (stored as "120/80" or similar)
            $bpSystolic = '-';
            $bpDiastolic = '-';
            if ($latestVitals && $latestVitals->blood_pressure) {
                $bpParts = explode('/', $latestVitals->blood_pressure);
                $bpSystolic = trim($bpParts[0] ?? '-');
                $bpDiastolic = trim($bpParts[1] ?? '-');
            }

            $data['vitals'] = $latestVitals ? [
                'bp_systolic' => $bpSystolic,
                'bp_diastolic' => $bpDiastolic,
                'pulse' => $latestVitals->heart_rate ?? '-',
                'temperature' => $latestVitals->temp ?? '-',
                'respiratory_rate' => $latestVitals->resp_rate ?? '-',
                'spo2' => $latestVitals->spo2 ?? '-',
                'weight' => $latestVitals->weight ?? '-',
                'height' => $latestVitals->height ?? '-',
                'bmi' => $latestVitals->bmi ?? '-',
                'recorded_at' => $latestVitals->created_at ? $latestVitals->created_at->format('M j, Y h:i A') : '-',
            ] : null;

            // Recent encounters (last 5) with diagnoses
            $encounters = Encounter::where('patient_id', $patientId)
                ->whereNotNull('reasons_for_encounter')
                ->where('reasons_for_encounter', '!=', '')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'reasons_for_encounter', 'notes', 'created_at']);

            $data['diagnoses'] = $encounters->map(function ($e) {
                $reasons = $e->reasons_for_encounter;
                $decoded = json_decode($reasons, true);
                $diagList = [];
                if (is_array($decoded) && isset($decoded[0]['code'])) {
                    foreach ($decoded as $dx) {
                        $diagList[] = ($dx['code'] ?? '') . ' - ' . ($dx['name'] ?? '');
                    }
                } else {
                    $diagList = array_map('trim', explode(',', $reasons));
                }
                return [
                    'date' => $e->created_at->format('M j, Y'),
                    'diagnoses' => $diagList,
                ];
            });

            // Recent medications (last 10)
            $medications = DB::table('product_requests')
                ->join('products', 'product_requests.product_id', '=', 'products.id')
                ->where('product_requests.patient_id', $patientId)
                ->orderBy('product_requests.created_at', 'desc')
                ->limit(10)
                ->select('products.product_name as product_name', 'product_requests.dose', 'product_requests.created_at')
                ->get();

            $data['medications'] = $medications->map(function ($m) {
                return [
                    'name' => $m->product_name,
                    'dose' => $m->dose ?? '-',
                    'date' => Carbon::parse($m->created_at)->format('M j, Y'),
                ];
            });

            // Recent lab results (last 5)
            $labs = DB::table('product_or_service_requests')
                ->leftJoin('services', 'product_or_service_requests.service_id', '=', 'services.id')
                ->where('product_or_service_requests.patient_id', $patientId)
                ->where('product_or_service_requests.type', 'lab')
                ->orderBy('product_or_service_requests.created_at', 'desc')
                ->limit(5)
                ->select('product_or_service_requests.id', 'services.service_name', 'product_or_service_requests.created_at')
                ->get();

            $data['labs'] = $labs->map(function ($l) {
                return [
                    'name' => $l->service_name ?? '-',
                    'status' => '-',
                    'result' => '-',
                    'date' => Carbon::parse($l->created_at)->format('M j, Y'),
                ];
            });

            // Recent imaging results (last 5)
            $imaging = ImagingServiceRequest::with('service')
                ->where('patient_id', $patientId)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            $data['imaging'] = $imaging->map(function ($img) {
                return [
                    'name' => $img->service->service_name ?? '-',
                    'result' => $img->result ?? '-',
                    'result_date' => $img->result_date ? Carbon::parse($img->result_date)->format('M j, Y') : '-',
                    'priority' => $img->priority ?? 'routine',
                    'date' => $img->created_at->format('M j, Y'),
                ];
            });

            // Recent procedures (last 5)
            $procedures = Procedure::with('procedureDefinition')
                ->where('patient_id', $patientId)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            $data['procedures'] = $procedures->map(function ($p) {
                return [
                    'name' => $p->procedureDefinition->name ?? '-',
                    'code' => $p->procedureDefinition->code ?? '-',
                    'status' => $p->procedure_status ?? '-',
                    'outcome' => $p->outcome ?? '-',
                    'outcome_notes' => $p->outcome_notes ?? '-',
                    'scheduled_date' => $p->scheduled_date ? Carbon::parse($p->scheduled_date)->format('M j, Y') : '-',
                    'date' => $p->created_at->format('M j, Y'),
                ];
            });

            // Recent clinical notes (last 5 encounters with notes)
            $notes = Encounter::where('patient_id', $patientId)
                ->whereNotNull('notes')
                ->where('notes', '!=', '')
                ->with('doctor')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'doctor_id', 'notes', 'created_at']);

            $data['clinical_notes'] = $notes->map(function ($n) {
                $doctor = $n->doctor;
                $doctorName = $doctor ? (($doctor->surname ?? '') . ' ' . ($doctor->firstname ?? '')) : '-';
                return [
                    'doctor' => $doctorName,
                    'notes' => $n->notes,
                    'date' => $n->created_at->format('M j, Y'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get patient data for report: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load patient data.',
            ], 500);
        }
    }

    /**
     * Print view for a medical report.
     */
    public function print(MedicalReport $medical_report)
    {
        $report = $medical_report->load('patient.user', 'doctor');
        return view('admin.medical_reports.print', compact('report'));
    }
}
