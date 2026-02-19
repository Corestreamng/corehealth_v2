<?php

namespace App\Http\Controllers;

use App\Helpers\HmoHelper;
use App\Models\AdmissionRequest;
use App\Models\Bed;
use App\Models\Clinic;
use App\Models\DoctorQueue;
use App\Models\ImagingServiceRequest;
use App\Models\LabServiceRequest;
use App\Models\patient;
use App\Models\PatientAccount;
use App\Models\ProductOrServiceRequest;
use App\Models\service;
use App\Models\User;
use App\Models\VitalSign;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmergencyIntakeController extends Controller
{
    /**
     * ESI Triage Levels
     */
    const ESI_LEVELS = [
        1 => 'Resuscitation - Immediate life-saving intervention',
        2 => 'Emergent - High risk, confused/lethargic, severe pain',
        3 => 'Urgent - Multiple resources needed',
        4 => 'Less Urgent - One resource needed',
        5 => 'Non-Urgent - No resources needed',
    ];

    /**
     * Process emergency intake
     *
     * Handles 3 dispositions:
     *  - admit_emergency: Admit to emergency ward with bed assignment
     *  - queue_consultation: Queue the patient for doctor consultation
     *  - direct_service: Direct to lab/imaging without consultation
     */
    public function intake(Request $request)
    {
        $request->validate([
            // Patient identification (existing or new)
            'patient_id' => 'nullable|exists:patients,id',
            'is_new_patient' => 'nullable|boolean',
            'is_unidentified' => 'nullable|boolean',

            // New patient fields
            'surname' => 'required_if:is_new_patient,1|nullable|min:2|max:150',
            'firstname' => 'required_if:is_new_patient,1|nullable|min:2|max:150',
            'gender' => 'required_if:is_new_patient,1|nullable|in:Male,Female,Others',
            'dob' => 'nullable|date',
            'approx_age' => 'nullable|string|in:neonate,infant,child_1_5,child_6_12,adolescent,adult_18_30,adult_31_50,adult_51_65,elderly',
            'phone_no' => 'nullable|string|max:20',
            'distinguishing_features' => 'nullable|string|max:500',

            // Arrival info
            'arrival_mode' => 'nullable|string|in:walk_in,ambulance,police,referral,brought_in',
            'brought_by_name' => 'nullable|string|max:150',
            'brought_by_phone' => 'nullable|string|max:20',

            // Triage data
            'esi_level' => 'required|integer|between:1,5',
            'chief_complaint' => 'required|string|max:500',
            'triage_notes' => 'nullable|string|max:1000',

            // Quick vitals
            'vital_hr' => 'nullable|integer|between:20,250',
            'vital_bp_sys' => 'nullable|integer|between:40,300',
            'vital_bp_dia' => 'nullable|integer|between:20,200',
            'vital_spo2' => 'nullable|integer|between:0,100',
            'vital_temp' => 'nullable|numeric|between:25,45',
            'vital_rr' => 'nullable|integer|between:4,60',
            'vital_bs' => 'nullable|numeric|min:0',

            // GCS & Pain
            'gcs_eye' => 'nullable|integer|between:1,4',
            'gcs_verbal' => 'nullable|integer|between:1,5',
            'gcs_motor' => 'nullable|integer|between:1,6',
            'gcs_total' => 'nullable|integer|between:3,15',
            'pain_scale' => 'nullable|integer|between:0,10',

            // Allergies
            'allergy_status' => 'nullable|string|in:nkda,has_allergies,unknown',
            'allergies_text' => 'nullable|string|max:500',

            // Disposition
            'disposition' => 'required|in:admit_emergency,queue_consultation,direct_service',

            // For queue_consultation
            'clinic_id' => 'required_if:disposition,queue_consultation|nullable|exists:clinics,id',
            'service_id' => 'required_if:disposition,queue_consultation|nullable|exists:services,id',

            // For admit_emergency
            'bed_id' => 'nullable|exists:beds,id',
            'admit_service_id' => 'required_if:disposition,admit_emergency|nullable|exists:services,id',
            'admit_clinic_id' => 'required_if:disposition,admit_emergency|nullable|exists:clinics,id',

            // For direct_service
            'direct_services' => 'nullable|array',
            'direct_services.*.type' => 'required_with:direct_services|in:lab,imaging',
            'direct_services.*.id' => 'required_with:direct_services|integer',

            // Elapsed time
            'elapsed_seconds' => 'nullable|integer|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Step 1: Resolve or create patient
            $patient = null;
            $newPatientCreated = false;

            if ($request->patient_id) {
                $patient = patient::find($request->patient_id);
            } elseif ($request->is_new_patient) {
                // Handle unidentified patients
                $surname = $request->is_unidentified
                    ? 'Unknown'
                    : ucwords($request->surname);
                $firstname = $request->is_unidentified
                    ? 'Patient'
                    : ucwords($request->firstname);

                // Quick register — mirrors ReceptionWorkbenchController::quickRegister()
                $email = strtolower(str_replace(' ', '', $firstname) . '.' . str_replace(' ', '', $surname) . '.' . rand(100, 999) . '@hms.com');

                $user = new User();
                $user->surname = $surname;
                $user->firstname = $firstname;
                $user->othername = $request->othername ? ucwords($request->othername) : null;
                $user->email = $email;
                $user->password = bcrypt('123456');
                $user->is_admin = 19; // Patient role
                $user->status = 1;

                $user->save();

                $patient = new patient();
                $patient->user_id = $user->id;
                $patient->gender = $request->gender;
                $patient->dob = $request->dob; // may be null for unknown patients
                $patient->phone_no = $request->phone_no;
                $patient->hmo_id = 1; // Default to Private

                // Store brought-by info as next of kin on the patient record (varchar columns)
                if ($request->brought_by_name) {
                    $patient->next_of_kin_name = $request->brought_by_name;
                    $patient->next_of_kin_phone = $request->brought_by_phone;
                }

                // Store distinguishing features in misc field for unidentified
                if ($request->is_unidentified && $request->distinguishing_features) {
                    $patient->misc = json_encode([
                        'unidentified' => true,
                        'distinguishing_features' => $request->distinguishing_features,
                        'arrival_mode' => $request->arrival_mode,
                    ]);
                }

                $patient->save();

                // Create patient account
                $account = new PatientAccount();
                $account->patient_id = $patient->id;
                $account->balance = 0;
                $account->save();

                $newPatientCreated = true;
            }

            // Update patient allergies if provided (for both new and existing)
            if ($patient && $request->allergy_status) {
                if ($request->allergy_status === 'nkda') {
                    $patient->allergies = json_encode(['NKDA']);
                    $patient->save();
                } elseif ($request->allergy_status === 'has_allergies' && $request->allergies_text) {
                    $allergyList = array_map('trim', explode(',', $request->allergies_text));
                    $allergyList = array_filter($allergyList);
                    if (!empty($allergyList)) {
                        $patient->allergies = json_encode($allergyList);
                        $patient->save();
                    }
                }
                // 'unknown' status — leave allergies as-is
            }

            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient not found. Please select an existing patient or register a new one.',
                ], 422);
            }

            // Step 2: Build comprehensive triage narrative for admission_reason
            $esiLabel = self::ESI_LEVELS[$request->esi_level] ?? 'Unknown';
            $arrivalModes = [
                'walk_in' => 'Walk-In',
                'ambulance' => 'Ambulance',
                'police' => 'Police/Security',
                'referral' => 'Referral',
                'brought_in' => 'Brought by Relative',
            ];

            $triageData = "[EMERGENCY INTAKE] ESI Level {$request->esi_level}: {$esiLabel}\n"
                . "Chief Complaint: {$request->chief_complaint}\n";

            // Arrival mode
            if ($request->arrival_mode) {
                $triageData .= "Arrival Mode: " . ($arrivalModes[$request->arrival_mode] ?? $request->arrival_mode) . "\n";
            }
            if ($request->brought_by_name) {
                $triageData .= "Brought By: {$request->brought_by_name}"
                    . ($request->brought_by_phone ? " ({$request->brought_by_phone})" : '') . "\n";
            }

            // Vitals summary
            $vitals = [];
            if ($request->vital_hr) $vitals[] = "HR: {$request->vital_hr} bpm";
            if ($request->vital_bp_sys && $request->vital_bp_dia) $vitals[] = "BP: {$request->vital_bp_sys}/{$request->vital_bp_dia} mmHg";
            if ($request->vital_spo2) $vitals[] = "SpO2: {$request->vital_spo2}%";
            if ($request->vital_temp) $vitals[] = "Temp: {$request->vital_temp}°C";
            if ($request->vital_rr) $vitals[] = "RR: {$request->vital_rr}/min";
            if ($request->vital_bs) $vitals[] = "BS: {$request->vital_bs} mg/dL";
            if (!empty($vitals)) {
                $triageData .= "Vitals: " . implode(' | ', $vitals) . "\n";
            }

            // GCS
            if ($request->gcs_total) {
                $severity = $request->gcs_total <= 8 ? 'Severe' : ($request->gcs_total <= 12 ? 'Moderate' : 'Mild');
                $triageData .= "GCS: {$request->gcs_total}/15 ({$severity}) [E{$request->gcs_eye} V{$request->gcs_verbal} M{$request->gcs_motor}]\n";
            }

            // Pain scale
            if ($request->pain_scale !== null && $request->pain_scale > 0) {
                $triageData .= "Pain Scale: {$request->pain_scale}/10\n";
            }

            // Allergies
            if ($request->allergy_status === 'nkda') {
                $triageData .= "Allergies: NKDA\n";
            } elseif ($request->allergy_status === 'has_allergies' && $request->allergies_text) {
                $triageData .= "Allergies: ⚠ {$request->allergies_text}\n";
            } elseif ($request->allergy_status === 'unknown') {
                $triageData .= "Allergies: Unknown — VERIFY\n";
            }

            // Triage notes
            if ($request->triage_notes) {
                $triageData .= "Triage Notes: {$request->triage_notes}\n";
            }

            // Distinguishing features for unidentified
            if ($request->is_unidentified && $request->distinguishing_features) {
                $triageData .= "Distinguishing Features: {$request->distinguishing_features}\n";
            }

            // Elapsed time & intake staff
            if ($request->elapsed_seconds) {
                $mins = floor($request->elapsed_seconds / 60);
                $secs = $request->elapsed_seconds % 60;
                $triageData .= "Triage Duration: {$mins}m {$secs}s\n";
            }
            $triageData .= "Intake By: " . (Auth::user()->surname ?? '') . ' ' . (Auth::user()->firstname ?? '') . "\n"
                . "Intake Time: " . Carbon::now()->format('Y-m-d H:i:s');

            // Step 2b: Create VitalSign record from triage vitals (if any vitals provided)
            if ($request->vital_hr || $request->vital_bp_sys || $request->vital_spo2 || $request->vital_temp || $request->vital_rr || $request->vital_bs) {
                $bp = null;
                if ($request->vital_bp_sys && $request->vital_bp_dia) {
                    $bp = $request->vital_bp_sys . '/' . $request->vital_bp_dia;
                }

                VitalSign::create([
                    'patient_id' => $patient->id,
                    'taken_by' => Auth::id(),
                    'blood_pressure' => $bp,
                    'heart_rate' => $request->vital_hr,
                    'temp' => $request->vital_temp,
                    'resp_rate' => $request->vital_rr,
                    'spo2' => $request->vital_spo2,
                    'blood_sugar' => $request->vital_bs,
                    'pain_score' => $request->pain_scale > 0 ? $request->pain_scale : null,
                    'other_notes' => $request->gcs_total ? "GCS: {$request->gcs_total}/15 [E{$request->gcs_eye} V{$request->gcs_verbal} M{$request->gcs_motor}]" : null,
                    'time_taken' => Carbon::now(),
                    'status' => 2, // Taken (not pending)
                    'source' => 'emergency_intake',
                ]);
            }

            // Step 3: Handle disposition
            $result = [];

            switch ($request->disposition) {
                case 'admit_emergency':
                    $result = $this->handleAdmitEmergency($patient, $request, $triageData);
                    break;

                case 'queue_consultation':
                    $result = $this->handleQueueConsultation($patient, $request, $triageData);
                    break;

                case 'direct_service':
                    $result = $this->handleDirectService($patient, $request, $triageData);
                    break;
            }

            DB::commit();

            return response()->json(array_merge([
                'success' => true,
                'new_patient_created' => $newPatientCreated,
                'patient_id' => $patient->id,
                'patient_name' => userfullname($patient->user_id),
            ], $result));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Emergency intake error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Emergency intake failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disposition: Admit to Emergency Ward
     */
    private function handleAdmitEmergency($patient, Request $request, string $triageData): array
    {
        // 1. Use the nurse-selected admission service for billing
        $serviceId = $request->admit_service_id;

        // 2. Create ProductOrServiceRequest (so billing can track this encounter)
        $serviceRequest = new ProductOrServiceRequest();
        $serviceRequest->service_id = $serviceId;
        $serviceRequest->user_id = $patient->user_id;
        $serviceRequest->staff_user_id = Auth::id();

        // Apply HMO tariff if applicable
        if ($patient->hmo_id && $patient->hmo_id > 1 && $serviceId) {
            try {
                $hmoData = HmoHelper::applyHmoTariff($patient->id, null, $serviceId);
                if ($hmoData) {
                    $serviceRequest->payable_amount = $hmoData['payable_amount'];
                    $serviceRequest->claims_amount = $hmoData['claims_amount'];
                    $serviceRequest->coverage_mode = $hmoData['coverage_mode'];
                    $serviceRequest->validation_status = $hmoData['validation_status'];
                }
            } catch (\Exception $e) {
                Log::warning('HMO tariff lookup failed during emergency admission', [
                    'patient_id' => $patient->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $serviceRequest->save();

        // 3. Create admission request with priority = emergency
        $admission = new AdmissionRequest();
        $admission->patient_id = $patient->id;
        $admission->doctor_id = Auth::id();
        $admission->admission_reason = $triageData;
        $admission->priority = 'emergency';
        $admission->esi_level = $request->esi_level;
        $admission->chief_complaint = $request->chief_complaint;
        $admission->admission_status = 'pending_checklist';
        $admission->discharged = 0;
        $admission->service_request_id = $serviceRequest->id;

        // Assign bed if provided
        if ($request->bed_id) {
            $bed = Bed::find($request->bed_id);
            if ($bed && $bed->bed_status === 'available') {
                $admission->bed_id = $bed->id;
                $admission->admission_status = 'admitted';
                $bed->assignPatient($patient->id);
            }
        }

        $admission->save();

        // 4. Create DoctorQueue entry using the nurse-selected clinic
        $clinicId = $request->admit_clinic_id;

        if ($clinicId) {
            $queue = new DoctorQueue();
            $queue->patient_id = $patient->id;
            $queue->clinic_id = $clinicId;
            $queue->receptionist_id = Auth::id();
            $queue->request_entry_id = $serviceRequest->id;
            $queue->status = 1; // Waiting
            $queue->priority = 'emergency';
            $queue->source = 'emergency_intake';
            $queue->triage_note = $triageData;
            $queue->save();
        }

        return [
            'message' => 'Patient admitted to emergency ward successfully.',
            'disposition' => 'admit_emergency',
            'admission_id' => $admission->id,
            'bed_assigned' => $admission->bed_id ? true : false,
        ];
    }

    /**
     * Disposition: Queue for Doctor Consultation
     */
    private function handleQueueConsultation($patient, Request $request, string $triageData): array
    {
        // Use the nurse-selected consultation service
        $serviceId = $request->service_id;

        // Create ProductOrServiceRequest (mirrors bookConsultation pattern)
        $serviceRequest = new ProductOrServiceRequest();
        $serviceRequest->service_id = $serviceId;
        $serviceRequest->user_id = $patient->user_id;
        $serviceRequest->staff_user_id = Auth::id();

        // Apply HMO tariff if applicable
        if ($patient->hmo_id && $patient->hmo_id > 1 && $serviceId) {
            try {
                $hmoData = HmoHelper::applyHmoTariff($patient->id, null, $serviceId);
                if ($hmoData) {
                    $serviceRequest->payable_amount = $hmoData['payable_amount'];
                    $serviceRequest->claims_amount = $hmoData['claims_amount'];
                    $serviceRequest->coverage_mode = $hmoData['coverage_mode'];
                    $serviceRequest->validation_status = $hmoData['validation_status'];
                }
            } catch (\Exception $e) {
                Log::warning('HMO tariff lookup failed during emergency intake', [
                    'patient_id' => $patient->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $serviceRequest->save();

        // Create DoctorQueue entry with status=1 (Waiting)
        $queue = new DoctorQueue();
        $queue->patient_id = $patient->id;
        $queue->clinic_id = $request->clinic_id;
        $queue->receptionist_id = Auth::id();
        $queue->request_entry_id = $serviceRequest->id;
        $queue->status = 1; // Waiting
        $queue->priority = 'emergency';
        $queue->source = 'emergency_intake';
        $queue->triage_note = $triageData;
        $queue->save();

        return [
            'message' => 'Patient queued for emergency consultation.',
            'disposition' => 'queue_consultation',
            'queue_id' => $queue->id,
            'triage_note' => $triageData,
        ];
    }

    /**
     * Disposition: Direct to Lab/Imaging
     */
    private function handleDirectService($patient, Request $request, string $triageData): array
    {
        $createdRequests = [];

        $services = $request->direct_services ?? [];
        foreach ($services as $item) {
            // Create ProductOrServiceRequest
            $serviceRequest = new ProductOrServiceRequest();
            $serviceRequest->service_id = $item['id'];
            $serviceRequest->user_id = $patient->user_id;
            $serviceRequest->staff_user_id = Auth::id();

            // Apply HMO tariff
            if ($patient->hmo_id && $patient->hmo_id > 1) {
                try {
                    $hmoData = HmoHelper::applyHmoTariff($patient->id, null, $item['id']);
                    if ($hmoData) {
                        $serviceRequest->payable_amount = $hmoData['payable_amount'];
                        $serviceRequest->claims_amount = $hmoData['claims_amount'];
                        $serviceRequest->coverage_mode = $hmoData['coverage_mode'];
                        $serviceRequest->validation_status = $hmoData['validation_status'];
                    }
                } catch (\Exception $e) {
                    // No tariff — patient pays full price
                }
            }

            $serviceRequest->save();

            if ($item['type'] === 'lab') {
                $labRequest = new LabServiceRequest();
                $labRequest->service_request_id = $serviceRequest->id;
                $labRequest->service_id = $item['id'];
                $labRequest->patient_id = $patient->id;
                $labRequest->doctor_id = null; // Emergency walk-in
                $labRequest->status = 1;
                $labRequest->priority = 'emergency';
                $labRequest->save();

                $createdRequests[] = ['type' => 'lab', 'id' => $labRequest->id];
            } elseif ($item['type'] === 'imaging') {
                $imagingRequest = new ImagingServiceRequest();
                $imagingRequest->service_request_id = $serviceRequest->id;
                $imagingRequest->service_id = $item['id'];
                $imagingRequest->patient_id = $patient->id;
                $imagingRequest->doctor_id = null;
                $imagingRequest->status = 1;
                $imagingRequest->priority = 'emergency';
                $imagingRequest->save();

                $createdRequests[] = ['type' => 'imaging', 'id' => $imagingRequest->id];
            }
        }

        return [
            'message' => count($createdRequests) . ' service(s) ordered. Awaiting billing.',
            'disposition' => 'direct_service',
            'requests' => $createdRequests,
        ];
    }

    /**
     * Get available emergency beds
     */
    public function getEmergencyBeds()
    {
        // Get beds from emergency wards
        $beds = Bed::with('wardRelation')
            ->where('bed_status', 'available')
            ->whereHas('wardRelation', function ($q) {
                $q->where('type', 'emergency')
                  ->where('is_active', true);
            })
            ->orderBy('name')
            ->get()
            ->map(function ($bed) {
                return [
                    'id' => $bed->id,
                    'name' => $bed->name,
                    'ward' => $bed->wardRelation->name ?? 'Emergency',
                    'bed_type' => $bed->bed_type ?? 'Standard',
                ];
            });

        // If no emergency ward beds, also include general available beds
        if ($beds->isEmpty()) {
            $beds = Bed::with('wardRelation')
                ->where('bed_status', 'available')
                ->whereHas('wardRelation', function ($q) {
                    $q->where('is_active', true);
                })
                ->orderBy('name')
                ->limit(20)
                ->get()
                ->map(function ($bed) {
                    return [
                        'id' => $bed->id,
                        'name' => $bed->name,
                        'ward' => $bed->wardRelation->name ?? 'General',
                        'bed_type' => $bed->bed_type ?? 'Standard',
                    ];
                });
        }

        return response()->json($beds);
    }

    /**
     * Search patients (lightweight, for emergency modal)
     */
    public function searchPatient(Request $request)
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $patients = patient::with(['user', 'hmo'])
            ->whereHas('user', function ($q) use ($query) {
                $q->where('surname', 'like', "%{$query}%")
                  ->orWhere('firstname', 'like', "%{$query}%")
                  ->orWhere('othername', 'like', "%{$query}%");
            })
            ->orWhere('phone_no', 'like', "%{$query}%")
            ->orWhere('file_no', 'like', "%{$query}%")
            ->limit(10)
            ->get()
            ->map(function ($patient) {
                return [
                    'id' => $patient->id,
                    'user_id' => $patient->user_id,
                    'name' => userfullname($patient->user_id),
                    'file_no' => $patient->file_no ?? 'N/A',
                    'phone' => $patient->phone_no ?? 'N/A',
                    'gender' => $patient->gender,
                    'hmo' => $patient->hmo->name ?? 'Private',
                    'allergies' => $patient->allergies,
                ];
            });

        return response()->json($patients);
    }

    /**
     * Get emergency queue (for nursing workbench)
     * Returns currently admitted emergency patients
     */
    public function getEmergencyQueue()
    {
        $patients = AdmissionRequest::with(['patient.user', 'patient.hmo', 'bed.wardRelation'])
            ->where('priority', 'emergency')
            ->where('discharged', 0)
            ->orderByRaw("FIELD(admission_status, 'pending_checklist', 'checklist_complete', 'admitted', 'discharge_requested') ASC")
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($admission) {
                // Extract ESI level from admission_reason
                $esiLevel = null;
                if (preg_match('/ESI Level (\d)/', $admission->admission_reason ?? '', $m)) {
                    $esiLevel = (int) $m[1];
                }

                $esiColors = [1 => 'danger', 2 => 'danger', 3 => 'warning', 4 => 'info', 5 => 'success'];
                $esiLabels = [1 => 'Resuscitation', 2 => 'Emergent', 3 => 'Urgent', 4 => 'Less Urgent', 5 => 'Non-Urgent'];

                return [
                    'admission_id' => $admission->id,
                    'patient_id' => $admission->patient_id,
                    'patient_name' => $admission->patient ? userfullname($admission->patient->user_id) : 'N/A',
                    'file_no' => $admission->patient->file_no ?? 'N/A',
                    'esi_level' => $esiLevel,
                    'esi_label' => $esiLabels[$esiLevel] ?? 'Unknown',
                    'esi_color' => $esiColors[$esiLevel] ?? 'secondary',
                    'bed' => $admission->bed ? $admission->bed->name : 'Unassigned',
                    'ward' => $admission->bed && $admission->bed->wardRelation ? $admission->bed->wardRelation->name : 'N/A',
                    'status' => $admission->admission_status,
                    'status_badge' => $this->getAdmissionStatusBadge($admission->admission_status),
                    'admitted_at' => $admission->created_at->format('M d, H:i'),
                    'duration' => $admission->created_at->diffForHumans(null, true) . ' ago',
                    'hmo' => $admission->patient->hmo->name ?? 'Private',
                ];
            });

        return response()->json($patients);
    }

    /**
     * Get admission status badge HTML
     */
    private function getAdmissionStatusBadge(string $status): string
    {
        $badges = [
            'pending_checklist' => '<span class="badge bg-warning">Pending Checklist</span>',
            'checklist_complete' => '<span class="badge bg-info">Checklist Done</span>',
            'admitted' => '<span class="badge bg-danger">Admitted</span>',
            'discharge_requested' => '<span class="badge bg-primary">Discharge Requested</span>',
            'discharge_checklist' => '<span class="badge bg-secondary">Discharge Checklist</span>',
            'discharged' => '<span class="badge bg-success">Discharged</span>',
        ];

        return $badges[$status] ?? '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
    }

    /**
     * Get services for emergency intake selectors (admission + consultation)
     */
    public function getServices()
    {
        // Admission/Emergency services — from the configured category or fallback
        $admissionCategoryId = appsettings('bed_service_category_id');
        $consultationCategoryId = appsettings('consultation_category_id');

        $admissionServices = service::with('price')
            ->where('status', 1)
            ->where(function ($q) use ($admissionCategoryId) {
                if ($admissionCategoryId) {
                    $q->where('category_id', $admissionCategoryId);
                }
                // Also include any service categories with emergency/admission in the name
                $q->orWhereHas('category', function ($cq) {
                    $cq->where('category_name', 'like', '%emergency%')
                       ->orWhere('category_name', 'like', '%admission%')
                       ->orWhere('category_name', 'like', '%bed%');
                });
            })
            ->orderBy('service_name')
            ->get()
            ->map(function ($s) {
                return [
                    'id' => $s->id,
                    'name' => $s->service_name,
                    'code' => $s->service_code,
                    'price' => $s->price->sale_price ?? 0,
                    'category' => $s->category->category_name ?? 'General',
                ];
            });

        // Consultation services — from the configured category or fallback
        $consultationServices = service::with('price')
            ->where('status', 1)
            ->where(function ($q) use ($consultationCategoryId) {
                if ($consultationCategoryId) {
                    $q->where('category_id', $consultationCategoryId);
                }
                $q->orWhereHas('category', function ($cq) {
                    $cq->where('category_name', 'like', '%consult%')
                       ->orWhere('category_name', 'like', '%clinic%');
                });
            })
            ->orderBy('service_name')
            ->get()
            ->map(function ($s) {
                return [
                    'id' => $s->id,
                    'name' => $s->service_name,
                    'code' => $s->service_code,
                    'price' => $s->price->sale_price ?? 0,
                    'category' => $s->category->category_name ?? 'General',
                ];
            });

        return response()->json([
            'admission' => $admissionServices,
            'consultation' => $consultationServices,
        ]);
    }

    /**
     * Get clinics for emergency consultation routing
     */
    public function getClinics()
    {
        $clinics = Clinic::orderBy('name')->get(['id', 'name']);
        return response()->json($clinics);
    }
}
