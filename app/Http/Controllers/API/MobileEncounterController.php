<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Helpers\HmoHelper;
use App\Models\AdmissionRequest;
use App\Models\Clinic;
use App\Models\DoctorQueue;
use App\Models\Encounter;
use App\Models\Hmo;
use App\Models\ImagingServiceRequest;
use App\Models\LabServiceRequest;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\ProductOrServiceRequest;
use App\Models\ProductRequest;
use App\Models\Staff;
use App\Models\NursingNote;
use App\Models\NursingNoteType;
use App\Models\ClinicNoteTemplate;
use App\Models\VitalSign;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Enums\QueueStatus;

class MobileEncounterController extends Controller
{
    /**
     * GET /api/mobile/doctor/queues
     *
     * Returns a JSON queue listing for the current doctor.
     * Params:
     *   filter_status  – exact QueueStatus enum value (0-7) for individual status filtering
     *   status         – legacy grouped filter (1=new/waiting, 2=continuing, 3=previous)
     *   start_date, end_date, page, per_page
     */
    public function queues(Request $request)
    {
        try {
            $doc = Staff::where('user_id', Auth::id())->first();

            if (!$doc) {
                return response()->json([
                    'status' => false,
                    'message' => 'Staff profile not found for this user.',
                ], 404);
            }

            $filterStatus = $request->input('filter_status');
            $status = (int) $request->input('status', 1);
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $perPage = $request->input('per_page', 30);

            // Map legacy mobile status values to QueueStatus enum
            $statusMap = [
                1 => [QueueStatus::WAITING, QueueStatus::VITALS_PENDING, QueueStatus::READY],
                2 => [QueueStatus::IN_CONSULTATION],
                3 => [QueueStatus::COMPLETED],
            ];

            // Auto-close old continuing encounters
            if ($filterStatus === null && $status == 2) {
                $this->endOldContinuingEncounters();
            } elseif ($filterStatus !== null && (int) $filterStatus === QueueStatus::IN_CONSULTATION) {
                $this->endOldContinuingEncounters();
            }

            $query = DoctorQueue::where(function ($q) use ($doc) {
                if ($doc->clinic_id) {
                    $q->where('clinic_id', $doc->clinic_id);
                }
                $q->orWhere('staff_id', $doc->id);
            });

            if ($filterStatus !== null) {
                // Exact status code filtering (mobile v2)
                $query->where('status', (int) $filterStatus);
            } elseif ($status == 3) {
                // Previous = completed + any terminal status
                $query->whereIn('status', [QueueStatus::COMPLETED, QueueStatus::CANCELLED, QueueStatus::NO_SHOW]);
            } elseif (isset($statusMap[$status])) {
                $query->whereIn('status', $statusMap[$status]);
            } else {
                $query->where('status', $status);
            }

            // For continuing, also apply time threshold
            if (($filterStatus === null && $status == 2)
                || ($filterStatus !== null && (int) $filterStatus === QueueStatus::IN_CONSULTATION)) {
                $threshold = Carbon::now()->subHours(appsettings('consultation_cycle_duration', 24));
                $query->where('created_at', '>=', $threshold);
            }

            if ($startDate && $endDate) {
                $query->whereBetween('created_at', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay(),
                ]);
            }

            $paginated = $query->orderBy('created_at', 'DESC')->paginate($perPage);

            $items = $paginated->getCollection()->map(function ($queue) {
                $patient = Patient::with('user', 'hmo')->find($queue->patient_id);
                $clinic = Clinic::find($queue->clinic_id);
                $reqEntry = ProductOrServiceRequest::find($queue->request_entry_id);
                $deliveryCheck = $reqEntry
                    ? HmoHelper::canDeliverService($reqEntry)
                    : ['can_deliver' => true, 'reason' => 'Ready', 'hint' => ''];

                $statusLabels = [
                    QueueStatus::WAITING => 'New',
                    QueueStatus::VITALS_PENDING => 'New',
                    QueueStatus::READY => 'New',
                    QueueStatus::IN_CONSULTATION => 'Continuing',
                    QueueStatus::COMPLETED => 'Completed',
                    QueueStatus::CANCELLED => 'Cancelled',
                    QueueStatus::NO_SHOW => 'No-Show',
                    QueueStatus::SCHEDULED => 'Scheduled',
                ];

                return [
                    'queue_id'          => $queue->id,
                    'patient_id'        => $queue->patient_id,
                    'patient_name'      => $patient && $patient->user ? $patient->user->name : 'Unknown',
                    'file_no'           => $patient->file_no ?? '',
                    'gender'            => $patient->gender ?? '',
                    'dob'               => $patient->dob ?? '',
                    'hmo_name'          => $patient && $patient->hmo ? $patient->hmo->name : 'N/A',
                    'hmo_no'            => $patient->hmo_no ?? '',
                    'clinic_name'       => $clinic->name ?? 'N/A',
                    'doctor_name'       => $queue->doctor ? userfullname($queue->doctor->user_id) : 'N/A',
                    'status'            => (int) $queue->status,
                    'status_label'      => $statusLabels[$queue->status] ?? 'Unknown',
                    'vitals_taken'      => (bool) $queue->vitals_taken,
                    'request_entry_id'  => $queue->request_entry_id,
                    'priority'          => $queue->priority ?? 'normal',
                    'source'            => $queue->source ?? 'walk-in',
                    'can_deliver'       => $deliveryCheck['can_deliver'],
                    'delivery_reason'   => $deliveryCheck['reason'] ?? '',
                    'delivery_hint'     => $deliveryCheck['hint'] ?? '',
                    'created_at'        => $queue->created_at->toIso8601String(),
                ];
            });

            return response()->json([
                'status' => true,
                'data'   => $items,
                'meta'   => [
                    'total'        => $paginated->total(),
                    'page'         => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile queues error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'status' => false,
                'message' => 'Failed to load queues.',
            ], 500);
        }
    }

    /**
     * POST /api/mobile/doctor/encounters/start
     *
     * Find-or-create an encounter for the given patient/queue.
     * Body: { patient_id, req_entry_id, queue_id }
     */
    public function startEncounter(Request $request)
    {
        try {
            $request->validate([
                'patient_id'   => 'required|integer|exists:patients,id',
                'req_entry_id' => 'nullable|integer',
                'queue_id'     => 'nullable|integer',
            ]);

            $doctor = Staff::where('user_id', Auth::id())->first();
            if (!$doctor) {
                return response()->json([
                    'status' => false,
                    'message' => 'Staff profile not found.',
                ], 404);
            }

            $patient = Patient::with('user', 'hmo')->find($request->patient_id);
            $clinic = Clinic::find($doctor->clinic_id);
            $reqEntry = $request->req_entry_id
                ? ProductOrServiceRequest::find($request->req_entry_id)
                : null;
            $admissionExists = AdmissionRequest::where('patient_id', $request->patient_id)
                ->where('discharged', 0)
                ->first();

            // Find or create encounter (same logic as web create())
            $encounterQuery = Encounter::where('doctor_id', $doctor->id)
                ->where('patient_id', $patient->id)
                ->where('completed', false);

            if ($reqEntry) {
                $encounterQuery->where('service_request_id', $reqEntry->id);
            }

            $encounter = $encounterQuery->first();

            if (!$encounter) {
                $encounter = new Encounter();
                $encounter->doctor_id = $doctor->id;
                $encounter->service_request_id = $reqEntry ? $reqEntry->id : null;
                $encounter->service_id = $reqEntry ? $reqEntry->service_id : null;
                $encounter->patient_id = $patient->id;
                $encounter->save();
            } else {
                $encounter->doctor_id = $doctor->id;
                $encounter->service_request_id = $reqEntry ? $reqEntry->id : null;
                $encounter->service_id = $reqEntry ? $reqEntry->service_id : null;
                $encounter->patient_id = $patient->id;
                $encounter->update();
            }

            // Load existing diagnosis if any — handle both legacy comma-separated and JSON formats
            $existingDiagnosis = [];
            $reasonsRaw = $encounter->reasons_for_encounter;
            if ($reasonsRaw) {
                $decoded = json_decode($reasonsRaw, true);
                if (is_array($decoded) && !empty($decoded)) {
                    // New per-diagnosis JSON format
                    $existingDiagnosis = $decoded;
                } else {
                    // Legacy comma-separated format
                    $existingDiagnosis = array_values(array_filter(explode(',', $reasonsRaw)));
                }
            }

            return response()->json([
                'status' => true,
                'data'   => [
                    'encounter' => [
                        'id'                              => $encounter->id,
                        'patient_id'                      => $encounter->patient_id,
                        'doctor_id'                       => $encounter->doctor_id,
                        'service_request_id'              => $encounter->service_request_id,
                        'notes'                           => $encounter->notes ?? '',
                        'doctor_diagnosis'                => $encounter->notes ?? '',
                        'completed'                       => (bool) $encounter->completed,
                        'diagnosis_applicable'            => $encounter->diagnosis_applicable ?? '1',
                        'reasons_for_encounter'           => $encounter->reasons_for_encounter,
                        'reasons_for_encounter_comment_1' => $encounter->reasons_for_encounter_comment_1,
                        'reasons_for_encounter_comment_2' => $encounter->reasons_for_encounter_comment_2,
                        'created_at'                      => $encounter->created_at->toIso8601String(),
                    ],
                    'patient' => [
                        'id'               => $patient->id,
                        'name'             => $patient->user->name ?? '',
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
                        'photo'            => $patient->user->staff->photo ?? null,
                    ],
                    'clinic' => $clinic ? [
                        'id'   => $clinic->id,
                        'name' => $clinic->name,
                    ] : null,
                    'queue_id'           => $request->queue_id,
                    'is_admitted'        => $admissionExists ? true : false,
                    'admission'          => $admissionExists ? [
                        'id'               => $admissionExists->id,
                        'status'           => $admissionExists->admission_status ?? $admissionExists->status,
                        'admission_reason' => $admissionExists->admission_reason,
                        'bed_id'           => $admissionExists->bed_id,
                    ] : null,
                    'existing_diagnosis' => $existingDiagnosis,
                    'settings' => [
                        'require_diagnosis'  => (bool) appsettings('requirediagnosis'),
                        'note_edit_duration' => (int) appsettings('note_edit_duration', 30),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile startEncounter error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'status' => false,
                'message' => 'Failed to start encounter: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/mobile/doctor/encounters/{encounter}
     *
     * Full encounter detail — patient data, vitals, diagnosis, labs, imaging, prescriptions, procedures.
     */
    public function encounterDetail(Encounter $encounter)
    {
        try {
            $patient = Patient::with('user', 'hmo')->find($encounter->patient_id);
            $vitals = VitalSign::where('patient_id', $encounter->patient_id)
                ->orderBy('created_at', 'DESC')
                ->limit(10)
                ->get()
                ->map(function ($v) {
                    return [
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
                        'taken_by'        => $v->takenBy ? $v->takenBy->name : null,
                        'created_at'      => $v->created_at->toIso8601String(),
                    ];
                });

            $labs = LabServiceRequest::with(['service', 'doctor', 'biller', 'resultBy', 'sampleTakenBy', 'approvedBy'])
                ->where('encounter_id', $encounter->id)
                ->where('status', '>', 0)
                ->orderBy('created_at', 'DESC')
                ->get()
                ->map(function ($lab) {
                    return [
                        'id'              => $lab->id,
                        'service_name'    => $lab->service->service_name ?? 'Unknown',
                        'service_code'    => $lab->service->service_code ?? '',
                        'note'            => $lab->note,
                        'priority'        => $lab->priority ?? 'routine',
                        'status'          => (int) $lab->status,
                        'status_label'    => $this->labStatusLabel($lab->status),
                        'result'          => $lab->result,
                        'result_data'     => $lab->result_data,
                        'attachments'     => $lab->attachments ?? [],
                        'result_date'     => $lab->result_date,
                        'result_by_name'  => $lab->resultBy?->name,
                        'sample_taken'    => (bool) $lab->sample_taken,
                        'sample_date'     => $lab->sample_date,
                        'sample_taken_by' => $lab->sampleTakenBy?->name,
                        'lab_number'      => $lab->lab_number,
                        'billed_date'     => $lab->billed_date,
                        'billed_by_name'  => $lab->biller?->name,
                        'approved_at'     => $lab->approved_at?->toIso8601String(),
                        'approved_by_name'=> $lab->approvedBy?->name,
                        'rejection_reason'=> $lab->rejection_reason,
                        'doctor_name'     => $lab->doctor?->name,
                        'created_at'      => $lab->created_at->toIso8601String(),
                    ];
                });

            $imaging = ImagingServiceRequest::with(['service', 'doctor', 'biller', 'resultBy', 'approvedBy'])
                ->where('encounter_id', $encounter->id)
                ->where('status', '>', 0)
                ->orderBy('created_at', 'DESC')
                ->get()
                ->map(function ($img) {
                    return [
                        'id'              => $img->id,
                        'service_name'    => $img->service->service_name ?? 'Unknown',
                        'service_code'    => $img->service->service_code ?? '',
                        'note'            => $img->note,
                        'priority'        => $img->priority ?? 'routine',
                        'status'          => (int) $img->status,
                        'status_label'    => $this->imagingStatusLabel($img->status),
                        'result'          => $img->result,
                        'result_data'     => $img->result_data,
                        'attachments'     => $img->attachments ?? [],
                        'result_date'     => $img->result_date,
                        'result_by_name'  => $img->resultBy?->name,
                        'billed_date'     => $img->billed_date,
                        'billed_by_name'  => $img->biller?->name,
                        'approved_at'     => $img->approved_at?->toIso8601String(),
                        'approved_by_name'=> $img->approvedBy?->name,
                        'rejection_reason'=> $img->rejection_reason,
                        'doctor_name'     => $img->doctor?->name,
                        'created_at'      => $img->created_at->toIso8601String(),
                    ];
                });

            $prescriptions = ProductRequest::with('product')
                ->where('encounter_id', $encounter->id)
                ->where('status', '>', 0)
                ->orderBy('created_at', 'DESC')
                ->get()
                ->map(function ($rx) {
                    return [
                        'id'                  => $rx->id,
                        'product_name'        => $rx->product->product_name ?? 'Unknown',
                        'product_code'        => $rx->product->product_code ?? '',
                        'dose'                => $rx->dose,
                        'qty'                 => $rx->qty,
                        'frequency'           => $rx->frequency,
                        'duration'            => $rx->duration,
                        'duration_unit'       => $rx->duration_unit,
                        'route'               => $rx->route,
                        'special_instruction' => $rx->special_instruction,
                        'status'              => (int) $rx->status,
                        'status_label'        => $this->prescriptionStatusLabel($rx->status),
                        'created_at'          => $rx->created_at->toIso8601String(),
                    ];
                });

            $procedures = Procedure::with('service')
                ->where('encounter_id', $encounter->id)
                ->orderBy('requested_on', 'DESC')
                ->get()
                ->map(function ($proc) {
                    return [
                        'id'               => $proc->id,
                        'service_name'     => $proc->service->service_name ?? 'Unknown',
                        'priority'         => $proc->priority,
                        'procedure_status' => $proc->procedure_status,
                        'scheduled_date'   => $proc->scheduled_date,
                        'pre_notes'        => $proc->pre_notes,
                        'requested_on'     => $proc->requested_on?->toIso8601String(),
                        'created_at'       => $proc->created_at->toIso8601String(),
                    ];
                });

            return response()->json([
                'status' => true,
                'data'   => [
                    'encounter' => [
                        'id'                              => $encounter->id,
                        'doctor_id'                       => $encounter->doctor_id,
                        'patient_id'                      => $encounter->patient_id,
                        'completed'                       => (bool) $encounter->completed,
                        'notes'                           => $encounter->notes,
                        'doctor_diagnosis'                => $encounter->notes,
                        'diagnosis_applicable'            => $encounter->diagnosis_applicable ?? '1',
                        'reasons_for_encounter'           => $encounter->reasons_for_encounter,
                        'reasons_for_encounter_comment_1' => $encounter->reasons_for_encounter_comment_1,
                        'reasons_for_encounter_comment_2' => $encounter->reasons_for_encounter_comment_2,
                        'created_at'                      => $encounter->created_at->toIso8601String(),
                        'updated_at'                      => $encounter->updated_at->toIso8601String(),
                    ],
                    'patient' => [
                        'id'                  => $patient->id,
                        'name'                => $patient->user->name ?? '',
                        'file_no'             => $patient->file_no ?? '',
                        'gender'              => $patient->gender ?? '',
                        'dob'                 => $patient->dob ?? '',
                        'blood_group'         => $patient->blood_group ?? '',
                        'genotype'            => $patient->genotype ?? '',
                        'phone'               => $patient->phone_no ?? '',
                        'address'             => $patient->address ?? '',
                        'nationality'         => $patient->nationality ?? '',
                        'ethnicity'           => $patient->ethnicity ?? '',
                        'disability'          => $patient->disability ?? '',
                        'hmo_name'            => $patient->hmo->name ?? 'N/A',
                        'hmo_no'              => $patient->hmo_no ?? '',
                        'insurance_scheme'    => $patient->insurance_scheme ?? '',
                        'allergies'           => $patient->allergies ?? [],
                        'medical_history'     => $patient->medical_history ?? '',
                        'next_of_kin_name'    => $patient->next_of_kin_name ?? '',
                        'next_of_kin_phone'   => $patient->next_of_kin_phone ?? '',
                        'next_of_kin_address' => $patient->next_of_kin_address ?? '',
                        'photo'               => $patient->user?->photo ?? null,
                    ],
                    'vitals'        => $vitals,
                    'labs'          => $labs,
                    'imaging'       => $imaging,
                    'prescriptions' => $prescriptions,
                    'procedures'    => $procedures,
                    'settings' => [
                        'require_diagnosis'  => (bool) appsettings('requirediagnosis'),
                        'note_edit_duration' => (int) appsettings('note_edit_duration', 30),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile encounterDetail error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'status' => false,
                'message' => 'Failed to load encounter details.',
            ], 500);
        }
    }

    /**
     * GET /api/mobile/doctor/patient/{patient}/encounter-history
     */
    public function encounterHistory(Patient $patient)
    {
        try {
            $encounters = Encounter::with(['doctor.staff_profile'])
                ->where('patient_id', $patient->id)
                ->where('completed', true)
                ->orderBy('created_at', 'DESC')
                ->paginate(20);

            $items = $encounters->getCollection()->map(function ($enc) {
                return [
                    'id'                    => $enc->id,
                    'doctor_name'           => $enc->doctor ? $enc->doctor->name : 'Unknown',
                    'notes'                 => $enc->notes,
                    'reasons_for_encounter' => $enc->reasons_for_encounter,
                    'comment_1'             => $enc->reasons_for_encounter_comment_1,
                    'comment_2'             => $enc->reasons_for_encounter_comment_2,
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
            Log::error('Mobile encounterHistory error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['status' => false, 'message' => 'Failed to load encounter history.'], 500);
        }
    }

    /**
     * GET /api/mobile/doctor/patient/{patient}/lab-history
     */
    public function labHistory(Patient $patient)
    {
        try {
            $labs = LabServiceRequest::with(['service', 'doctor', 'encounter'])
                ->where('patient_id', $patient->id)
                ->where('status', '>', 0)
                ->orderBy('created_at', 'DESC')
                ->paginate(30);

            $items = $labs->getCollection()->map(function ($lab) {
                return [
                    'id'           => $lab->id,
                    'service_name' => $lab->service->service_name ?? 'Unknown',
                    'service_code' => $lab->service->service_code ?? '',
                    'note'         => $lab->note,
                    'status'       => (int) $lab->status,
                    'status_label' => $this->labStatusLabel($lab->status),
                    'result'       => $lab->result,
                    'result_data'  => $lab->result_data,
                    'doctor_name'  => $lab->doctor ? $lab->doctor->name : 'Unknown',
                    'encounter_id' => $lab->encounter_id,
                    'billed_date'  => $lab->billed_date,
                    'result_date'  => $lab->result_date,
                    'created_at'   => $lab->created_at->toIso8601String(),
                ];
            });

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
            Log::error('Mobile labHistory error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['status' => false, 'message' => 'Failed to load lab history.'], 500);
        }
    }

    /**
     * GET /api/mobile/doctor/patient/{patient}/imaging-history
     */
    public function imagingHistory(Patient $patient)
    {
        try {
            $imaging = ImagingServiceRequest::with(['service', 'doctor', 'encounter'])
                ->where('patient_id', $patient->id)
                ->where('status', '>', 0)
                ->orderBy('created_at', 'DESC')
                ->paginate(30);

            $items = $imaging->getCollection()->map(function ($img) {
                return [
                    'id'           => $img->id,
                    'service_name' => $img->service->service_name ?? 'Unknown',
                    'service_code' => $img->service->service_code ?? '',
                    'note'         => $img->note,
                    'status'       => (int) $img->status,
                    'status_label' => $this->imagingStatusLabel($img->status),
                    'result'       => $img->result,
                    'result_data'  => $img->result_data,
                    'doctor_name'  => $img->doctor ? $img->doctor->name : 'Unknown',
                    'encounter_id' => $img->encounter_id,
                    'billed_date'  => $img->billed_date,
                    'result_date'  => $img->result_date,
                    'created_at'   => $img->created_at->toIso8601String(),
                ];
            });

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
            Log::error('Mobile imagingHistory error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['status' => false, 'message' => 'Failed to load imaging history.'], 500);
        }
    }

    /**
     * GET /api/mobile/doctor/patient/{patient}/prescription-history
     */
    public function prescriptionHistory(Patient $patient)
    {
        try {
            $prescriptions = ProductRequest::with(['product.price', 'product.category', 'doctor', 'encounter'])
                ->where('patient_id', $patient->id)
                ->orderBy('created_at', 'DESC')
                ->paginate(30);

            $items = $prescriptions->getCollection()->map(function ($rx) {
                return [
                    'id'            => $rx->id,
                    'product_name'  => $rx->product->product_name ?? 'Unknown',
                    'product_code'  => $rx->product->product_code ?? '',
                    'category'      => $rx->product && $rx->product->category ? $rx->product->category->name : '',
                    'dose'          => $rx->dose,
                    'qty'           => $rx->qty,
                    'status'        => (int) $rx->status,
                    'status_label'  => $this->prescriptionStatusLabel($rx->status),
                    'price'         => $rx->product && $rx->product->price ? $rx->product->price->initial_sale_price : 0,
                    'doctor_name'   => $rx->doctor ? $rx->doctor->name : 'Unknown',
                    'encounter_id'  => $rx->encounter_id,
                    'billed_date'   => $rx->billed_date,
                    'dispense_date' => $rx->dispense_date,
                    'created_at'    => $rx->created_at->toIso8601String(),
                ];
            });

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
            Log::error('Mobile prescriptionHistory error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['status' => false, 'message' => 'Failed to load prescription history.'], 500);
        }
    }

    /**
     * GET /api/mobile/doctor/patient/{patient}/procedure-history
     */
    public function procedureHistory(Patient $patient)
    {
        try {
            $procedures = Procedure::with(['service', 'requestedByUser', 'encounter'])
                ->where('patient_id', $patient->id)
                ->orderBy('requested_on', 'DESC')
                ->paginate(30);

            $items = $procedures->getCollection()->map(function ($proc) {
                return [
                    'id'               => $proc->id,
                    'service_name'     => $proc->service->service_name ?? 'Unknown',
                    'priority'         => $proc->priority,
                    'priority_label'   => ucfirst($proc->priority ?? 'routine'),
                    'procedure_status' => $proc->procedure_status,
                    'status_label'     => ucfirst(str_replace('_', ' ', $proc->procedure_status ?? '')),
                    'scheduled_date'   => $proc->scheduled_date,
                    'pre_notes'        => $proc->pre_notes,
                    'post_notes'       => $proc->post_notes,
                    'outcome'          => $proc->outcome,
                    'requested_by'     => $proc->requestedByUser ? $proc->requestedByUser->name : 'Unknown',
                    'encounter_id'     => $proc->encounter_id,
                    'requested_on'     => $proc->requested_on?->toIso8601String(),
                    'created_at'       => $proc->created_at->toIso8601String(),
                ];
            });

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
            Log::error('Mobile procedureHistory error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['status' => false, 'message' => 'Failed to load procedure history.'], 500);
        }
    }

    // ── Private helpers ─────────────────────────────────────────────

    /**
     * GET /api/mobile/doctor/queues/stats
     * Returns live count badges matching the web stats bar.
     */
    public function queueStats(Request $request)
    {
        try {
            $doc = Staff::where('user_id', Auth::id())->first();
            if (!$doc) {
                return response()->json(['status' => false, 'message' => 'Staff profile not found.'], 404);
            }

            $base = DoctorQueue::where(function ($q) use ($doc) {
                if ($doc->clinic_id) {
                    $q->where('clinic_id', $doc->clinic_id);
                }
                $q->orWhere('staff_id', $doc->id);
            });

            $stats = [
                'waiting'    => (clone $base)->where('status', QueueStatus::WAITING)->count(),
                'vitals'     => (clone $base)->where('status', QueueStatus::VITALS_PENDING)->count(),
                'ready'      => (clone $base)->where('status', QueueStatus::READY)->count(),
                'in_consult' => (clone $base)->where('status', QueueStatus::IN_CONSULTATION)->count(),
                'scheduled'  => (clone $base)->where('status', QueueStatus::SCHEDULED)->count(),
                'completed'  => (clone $base)->where('status', QueueStatus::COMPLETED)->whereDate('created_at', today())->count(),
            ];
            $stats['total'] = $stats['waiting'] + $stats['vitals'] + $stats['ready'] + $stats['in_consult'];

            return response()->json(['status' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            Log::error('Mobile queueStats error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to load stats.'], 500);
        }
    }

    /**
     * GET /api/mobile/doctor/admissions
     * Returns the doctor's active admissions.
     */
    public function myAdmissions(Request $request)
    {
        try {
            $doc = Staff::where('user_id', Auth::id())->first();
            if (!$doc) {
                return response()->json(['status' => false, 'message' => 'Staff profile not found.'], 404);
            }

            $query = AdmissionRequest::with(['patient.user', 'patient.hmo', 'bed.ward', 'admittingDoctor'])
                ->where('admission_status', '!=', 'discharged');

            if ($doc->clinic_id) {
                $query->whereHas('bed.ward', function ($q) use ($doc) {
                    $q->where('clinic_id', $doc->clinic_id);
                });
            }

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('created_at', [
                    Carbon::parse($request->start_date)->startOfDay(),
                    Carbon::parse($request->end_date)->endOfDay(),
                ]);
            }

            $admissions = $query->orderBy('created_at', 'DESC')->paginate(20);

            $items = $admissions->getCollection()->map(function ($adm) {
                return [
                    'id'               => $adm->id,
                    'patient_id'       => $adm->patient_id,
                    'patient_name'     => $adm->patient && $adm->patient->user ? $adm->patient->user->name : 'Unknown',
                    'file_no'          => $adm->patient->file_no ?? '',
                    'gender'           => $adm->patient->gender ?? '',
                    'dob'              => $adm->patient->dob ?? '',
                    'ward_name'        => $adm->bed?->ward?->name ?? 'N/A',
                    'bed_name'         => $adm->bed?->bed_name ?? 'N/A',
                    'admission_reason' => $adm->admission_reason,
                    'admission_status' => $adm->admission_status,
                    'admitted_at'      => $adm->created_at?->toIso8601String(),
                    'doctor_name'      => $adm->admittingDoctor ? $adm->admittingDoctor->name : 'N/A',
                ];
            });

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

    /**
     * GET /api/mobile/doctor/patient/{patient}/allergies
     */
    public function getPatientAllergies(Patient $patient)
    {
        $raw = $patient->allergies;
        return response()->json([
            'status' => true,
            'data'   => is_array($raw) ? $raw : [],
        ]);
    }

    /**
     * POST /api/mobile/doctor/patient/{patient}/allergies
     * Body: { allergy: string }
     */
    public function addPatientAllergy(Request $request, Patient $patient)
    {
        $request->validate(['allergy' => 'required|string|max:255']);
        try {
            $raw = $patient->allergies;
            $allergies = is_array($raw) ? $raw : [];
            if (!in_array($request->allergy, $allergies)) {
                $allergies[] = $request->allergy;
                $patient->update(['allergies' => $allergies]);
            }
            return response()->json(['status' => true, 'data' => $allergies]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to add allergy.'], 500);
        }
    }

    /**
     * GET /api/mobile/doctor/clinics
     * Returns list of clinics for referral form dropdowns.
     */
    public function getClinics()
    {
        $clinics = Clinic::orderBy('name')->get(['id', 'name']);
        return response()->json(['status' => true, 'data' => $clinics]);
    }

    /**
     * GET /api/mobile/doctor/doctors
     * Returns list of doctors for referral form dropdowns.
     */
    public function getDoctors(Request $request)
    {
        $query = Staff::with('user')
            ->whereHas('roles', function ($q) {
                $q->where('role_name', 'like', '%doctor%')
                  ->orWhere('role_name', 'like', '%physician%');
            });

        if ($request->has('clinic_id')) {
            $query->where('clinic_id', $request->clinic_id);
        }

        $doctors = $query->get()->map(function ($s) {
            return [
                'id'   => $s->id,
                'name' => $s->user->name ?? 'Unknown',
            ];
        });

        return response()->json(['status' => true, 'data' => $doctors]);
    }

    private function endOldContinuingEncounters()
    {
        $threshold = Carbon::now()->subHours(appsettings('consultation_cycle_duration', 24));
        DoctorQueue::where('status', QueueStatus::IN_CONSULTATION)
            ->where('created_at', '<', $threshold)
            ->update(['status' => QueueStatus::COMPLETED]);
    }

    private function labStatusLabel($status): string
    {
        return match ((int) $status) {
            0 => 'Dismissed',
            1 => 'Requested',
            2 => 'Billed',
            3 => 'Sample Taken',
            4 => 'Result Ready',
            5 => 'Pending Approval',
            6 => 'Rejected',
            default => 'Unknown',
        };
    }

    private function imagingStatusLabel($status): string
    {
        return match ((int) $status) {
            0 => 'Dismissed',
            1 => 'Requested',
            2 => 'Billed',
            3 => 'Result Ready',
            5 => 'Pending Approval',
            6 => 'Rejected',
            default => 'Unknown',
        };
    }

    private function prescriptionStatusLabel($status): string
    {
        return match ((int) $status) {
            0 => 'Dismissed',
            1 => 'Requested',
            2 => 'Billed',
            3 => 'Ready for Dispensing',
            4 => 'Dispensed',
            default => 'Unknown',
        };
    }

    // ═══════════════════════════════════════════════════════════════
    //  Doctor Profile & Settings
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /api/mobile/doctor/profile
     * Returns the authenticated doctor's full profile.
     */
    public function doctorProfile()
    {
        try {
            $user = Auth::user();
            $staff = Staff::with(['specialization', 'clinic', 'department'])
                ->where('user_id', $user->id)
                ->first();

            if (!$staff) {
                return response()->json(['status' => false, 'message' => 'Staff profile not found.'], 404);
            }

            return response()->json([
                'status' => true,
                'data' => [
                    'user' => [
                        'id'        => $user->id,
                        'surname'   => $user->surname,
                        'firstname' => $user->firstname,
                        'othername' => $user->othername,
                        'email'     => $user->email,
                        'name'      => $user->name,
                    ],
                    'staff' => [
                        'id'                             => $staff->id,
                        'gender'                         => $staff->gender,
                        'date_of_birth'                  => $staff->date_of_birth,
                        'phone_number'                   => $staff->phone_number,
                        'home_address'                   => $staff->home_address,
                        'photo'                          => $staff->photo,
                        'department'                     => $staff->department?->name ?? null,
                        'designation'                    => $staff->job_title ?? null,
                        'specialization_id'              => $staff->specialization_id,
                        'specialization_name'            => $staff->specialization->name ?? null,
                        'clinic_id'                      => $staff->clinic_id,
                        'clinic_name'                    => $staff->clinic->name ?? null,
                        'emergency_contact_name'         => $staff->emergency_contact_name,
                        'emergency_contact_phone'        => $staff->emergency_contact_phone,
                        'emergency_contact_relationship' => $staff->emergency_contact_relationship,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile doctorProfile error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to load profile.'], 500);
        }
    }

    /**
     * PUT /api/mobile/doctor/profile
     * Updates the doctor's profile (User + Staff fields).
     */
    public function updateDoctorProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'surname'                        => 'sometimes|required|string|max:255',
            'firstname'                      => 'sometimes|required|string|max:255',
            'othername'                       => 'nullable|string|max:255',
            'gender'                          => 'sometimes|required|in:Male,Female,Others',
            'date_of_birth'                   => 'nullable|date|before:today',
            'phone_number'                    => 'nullable|string|max:20',
            'home_address'                    => 'nullable|string|max:500',
            'emergency_contact_name'          => 'nullable|string|max:255',
            'emergency_contact_phone'         => 'nullable|string|max:20',
            'emergency_contact_relationship'  => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = Auth::user();
            $staff = Staff::where('user_id', $user->id)->first();
            if (!$staff) {
                return response()->json(['status' => false, 'message' => 'Staff profile not found.'], 404);
            }

            // Update User model fields
            $userFields = array_filter($request->only(['surname', 'firstname', 'othername']), function ($v) {
                return $v !== null && $v !== '';
            });
            if (!empty($userFields)) {
                $user->update($userFields);
            }

            // Update Staff model fields
            $staffFieldKeys = [
                'gender', 'date_of_birth', 'phone_number', 'home_address',
                'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship',
            ];
            foreach ($staffFieldKeys as $field) {
                if ($request->has($field)) {
                    $value = $request->input($field);
                    if ($field === 'gender' && empty($value)) continue;
                    $staff->$field = $value === '' ? null : $value;
                }
            }
            $staff->save();

            return response()->json(['status' => true, 'message' => 'Profile updated successfully.']);
        } catch (\Exception $e) {
            Log::error('Mobile updateDoctorProfile error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to update profile.'], 500);
        }
    }

    /**
     * POST /api/mobile/doctor/change-password
     */
    public function changeDoctorPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = Auth::user();

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json(['status' => false, 'message' => 'Current password is incorrect.'], 422);
            }

            $user->update(['password' => Hash::make($request->password)]);

            return response()->json(['status' => true, 'message' => 'Password updated successfully.']);
        } catch (\Exception $e) {
            Log::error('Mobile changeDoctorPassword error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to change password.'], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  My Investigations (cross-patient lab + imaging by this doctor)
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /api/mobile/doctor/my-investigations
     * Returns lab + imaging orders placed by the current doctor.
     * Params: type (lab|imaging|all), status, page, per_page
     */
    public function myInvestigations(Request $request)
    {
        try {
            $doctor = Staff::where('user_id', Auth::id())->first();
            if (!$doctor) {
                return response()->json(['status' => false, 'message' => 'Staff profile not found.'], 404);
            }

            $type = $request->input('type', 'all');
            $statusFilter = $request->input('status');
            $perPage = (int) $request->input('per_page', 20);

            $labs = collect();
            $imaging = collect();

            if ($type === 'all' || $type === 'lab') {
                $labQuery = LabServiceRequest::with(['service', 'patient.user', 'encounter'])
                    ->where('doctor_id', Auth::id())
                    ->where('status', '>', 0);

                if ($statusFilter !== null) {
                    $labQuery->where('status', (int) $statusFilter);
                }

                $labs = $labQuery->orderBy('created_at', 'DESC')
                    ->limit($type === 'all' ? 50 : $perPage)
                    ->get()
                    ->map(function ($lab) {
                        return [
                            'id'            => $lab->id,
                            'type'          => 'lab',
                            'service_name'  => $lab->service->service_name ?? 'Unknown',
                            'patient_name'  => $lab->patient && $lab->patient->user ? $lab->patient->user->name : 'Unknown',
                            'patient_id'    => $lab->patient_id,
                            'encounter_id'  => $lab->encounter_id,
                            'status'        => (int) $lab->status,
                            'status_label'  => $this->labStatusLabel($lab->status),
                            'result'        => $lab->result,
                            'result_data'   => $lab->result_data,
                            'result_date'   => $lab->result_date,
                            'note'          => $lab->note,
                            'created_at'    => $lab->created_at->toIso8601String(),
                        ];
                    });
            }

            if ($type === 'all' || $type === 'imaging') {
                $imgQuery = ImagingServiceRequest::with(['service', 'patient.user', 'encounter'])
                    ->where('doctor_id', Auth::id())
                    ->where('status', '>', 0);

                if ($statusFilter !== null) {
                    $imgQuery->where('status', (int) $statusFilter);
                }

                $imaging = $imgQuery->orderBy('created_at', 'DESC')
                    ->limit($type === 'all' ? 50 : $perPage)
                    ->get()
                    ->map(function ($img) {
                        return [
                            'id'            => $img->id,
                            'type'          => 'imaging',
                            'service_name'  => $img->service->service_name ?? 'Unknown',
                            'patient_name'  => $img->patient && $img->patient->user ? $img->patient->user->name : 'Unknown',
                            'patient_id'    => $img->patient_id,
                            'encounter_id'  => $img->encounter_id,
                            'status'        => (int) $img->status,
                            'status_label'  => $this->imagingStatusLabel($img->status),
                            'result'        => $img->result,
                            'result_data'   => $img->result_data,
                            'result_date'   => $img->result_date,
                            'note'          => $img->note,
                            'created_at'    => $img->created_at->toIso8601String(),
                        ];
                    });
            }

            // Merge and sort by created_at descending
            $combined = $labs->concat($imaging)
                ->sortByDesc('created_at')
                ->values();

            return response()->json([
                'status' => true,
                'data'   => $combined,
                'meta'   => [
                    'total'     => $combined->count(),
                    'lab_count' => $labs->count(),
                    'imaging_count' => $imaging->count(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile myInvestigations error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['status' => false, 'message' => 'Failed to load investigations.'], 500);
        }
    }

    /**
     * DELETE /api/mobile/doctor/patient/{patient}/allergies/{index}
     * Remove an allergy by its index in the JSON array.
     */
    public function deletePatientAllergy(Patient $patient, $index)
    {
        try {
            $raw = $patient->allergies;
            $allergies = is_array($raw) ? $raw : [];
            $index = (int) $index;

            if ($index < 0 || $index >= count($allergies)) {
                return response()->json(['status' => false, 'message' => 'Invalid allergy index.'], 422);
            }

            array_splice($allergies, $index, 1);
            $patient->update(['allergies' => array_values($allergies)]);

            return response()->json(['status' => true, 'data' => array_values($allergies)]);
        } catch (\Exception $e) {
            Log::error('Mobile deletePatientAllergy error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to delete allergy.'], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  Nursing Notes (mobile-friendly JSON, not DataTables HTML)
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /api/mobile/doctor/patient/{patientId}/nursing-notes
     */
    public function getNursingNotes($patientId, Request $request)
    {
        try {
            $query = NursingNote::with(['type', 'createdBy'])
                ->where('patient_id', $patientId)
                ->orderBy('created_at', 'desc');

            if ($request->filled('type_id')) {
                $query->where('nursing_note_type_id', $request->type_id);
            }

            $notes = $query->paginate(20);

            $editDuration = appsettings('note_edit_duration') ?? 60;

            $items = $notes->getCollection()->map(function ($note) use ($editDuration) {
                $canEdit = false;
                if ($note->created_at) {
                    $editDeadline = Carbon::parse($note->created_at)->addMinutes($editDuration);
                    $canEdit = Carbon::now()->lte($editDeadline) && Auth::id() == $note->created_by;
                }

                return [
                    'id'         => $note->id,
                    'type_name'  => $note->type->name ?? 'N/A',
                    'type_id'    => $note->nursing_note_type_id,
                    'note'       => $note->note,
                    'created_by' => $note->createdBy ? userfullname($note->createdBy->id) : 'N/A',
                    'created_at' => $note->created_at?->toIso8601String(),
                    'can_edit'   => $canEdit,
                ];
            });

            return response()->json([
                'status' => true,
                'data' => $items,
                'pagination' => [
                    'current_page' => $notes->currentPage(),
                    'last_page'    => $notes->lastPage(),
                    'total'        => $notes->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile getNursingNotes error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to load nursing notes.'], 500);
        }
    }

    /**
     * GET /api/mobile/doctor/nursing-note-types
     */
    public function getNoteTypes()
    {
        $types = NursingNoteType::all(['id', 'name', 'template']);
        return response()->json(['status' => true, 'data' => $types]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  Clinic Note Templates
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /api/mobile/doctor/clinic-note-templates?clinic_id=X
     */
    public function getClinicNoteTemplates(Request $request)
    {
        try {
            $clinicId = $request->input('clinic_id');

            $templates = ClinicNoteTemplate::active()
                ->forClinic($clinicId)
                ->orderBy('category')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'description', 'content', 'category', 'clinic_id']);

            $grouped = $templates->groupBy('category')->map(function ($items, $category) {
                return [
                    'category' => $category,
                    'templates' => $items->map(function ($t) {
                        return [
                            'id'          => $t->id,
                            'name'        => $t->name,
                            'description' => $t->description,
                            'content'     => $t->content,
                            'is_global'   => is_null($t->clinic_id),
                        ];
                    })->values(),
                ];
            })->values();

            return response()->json([
                'status'  => true,
                'groups' => $grouped,
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile getClinicNoteTemplates error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to load templates.'], 500);
        }
    }
}
