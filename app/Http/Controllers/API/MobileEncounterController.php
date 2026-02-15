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
use App\Models\patient;
use App\Models\Procedure;
use App\Models\ProductOrServiceRequest;
use App\Models\ProductRequest;
use App\Models\Staff;
use App\Models\VitalSign;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MobileEncounterController extends Controller
{
    /**
     * GET /api/mobile/doctor/queues
     *
     * Returns a JSON queue listing for the current doctor.
     * Params: status (1=new, 2=continuing, 3=previous), start_date, end_date, page, per_page
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

            $status = $request->input('status', 1);
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $perPage = $request->input('per_page', 30);

            // Auto-close old continuing encounters
            if ($status == 2) {
                $this->endOldContinuingEncounters();
            }

            $query = DoctorQueue::where(function ($q) use ($doc) {
                if ($doc->clinic_id) {
                    $q->where('clinic_id', $doc->clinic_id);
                }
                $q->orWhere('staff_id', $doc->id);
            });

            if ($status == 3) {
                $query->where('status', '>', 2);
            } else {
                $query->where('status', $status);
            }

            // For continuing, also apply time threshold
            if ($status == 2) {
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
                $patient = patient::with('user', 'hmo')->find($queue->patient_id);
                $clinic = Clinic::find($queue->clinic_id);
                $reqEntry = ProductOrServiceRequest::find($queue->request_entry_id);
                $deliveryCheck = $reqEntry
                    ? HmoHelper::canDeliverService($reqEntry)
                    : ['can_deliver' => true, 'reason' => 'Ready', 'hint' => ''];

                $statusLabels = [1 => 'New', 2 => 'Continuing', 3 => 'Completed'];

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
                    'doctor_name'       => userfullname($queue->staff_id),
                    'status'            => (int) $queue->status,
                    'status_label'      => $statusLabels[$queue->status] ?? 'Unknown',
                    'vitals_taken'      => (bool) $queue->vitals_taken,
                    'request_entry_id'  => $queue->request_entry_id,
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

            $patient = patient::with('user', 'hmo')->find($request->patient_id);
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

            // Load existing diagnosis if any
            $existingDiagnosis = [];
            if ($encounter->reasons_for_encounter) {
                $existingDiagnosis = array_filter(explode(',', $encounter->reasons_for_encounter));
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
                        'completed'                       => (bool) $encounter->completed,
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
            $patient = patient::with('user', 'hmo')->find($encounter->patient_id);
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

            $labs = LabServiceRequest::with('service')
                ->where('encounter_id', $encounter->id)
                ->where('status', '>', 0)
                ->orderBy('created_at', 'DESC')
                ->get()
                ->map(function ($lab) {
                    return [
                        'id'           => $lab->id,
                        'service_name' => $lab->service->service_name ?? 'Unknown',
                        'service_code' => $lab->service->service_code ?? '',
                        'note'         => $lab->note,
                        'status'       => (int) $lab->status,
                        'status_label' => $this->labStatusLabel($lab->status),
                        'result'       => $lab->result,
                        'result_data'  => $lab->result_data,
                        'created_at'   => $lab->created_at->toIso8601String(),
                    ];
                });

            $imaging = ImagingServiceRequest::with('service')
                ->where('encounter_id', $encounter->id)
                ->where('status', '>', 0)
                ->orderBy('created_at', 'DESC')
                ->get()
                ->map(function ($img) {
                    return [
                        'id'           => $img->id,
                        'service_name' => $img->service->service_name ?? 'Unknown',
                        'service_code' => $img->service->service_code ?? '',
                        'note'         => $img->note,
                        'status'       => (int) $img->status,
                        'status_label' => $this->imagingStatusLabel($img->status),
                        'result'       => $img->result,
                        'result_data'  => $img->result_data,
                        'created_at'   => $img->created_at->toIso8601String(),
                    ];
                });

            $prescriptions = ProductRequest::with('product')
                ->where('encounter_id', $encounter->id)
                ->where('status', '>', 0)
                ->orderBy('created_at', 'DESC')
                ->get()
                ->map(function ($rx) {
                    return [
                        'id'           => $rx->id,
                        'product_name' => $rx->product->product_name ?? 'Unknown',
                        'product_code' => $rx->product->product_code ?? '',
                        'dose'         => $rx->dose,
                        'qty'          => $rx->qty,
                        'status'       => (int) $rx->status,
                        'status_label' => $this->prescriptionStatusLabel($rx->status),
                        'created_at'   => $rx->created_at->toIso8601String(),
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
                        'reasons_for_encounter'           => $encounter->reasons_for_encounter,
                        'reasons_for_encounter_comment_1' => $encounter->reasons_for_encounter_comment_1,
                        'reasons_for_encounter_comment_2' => $encounter->reasons_for_encounter_comment_2,
                        'created_at'                      => $encounter->created_at->toIso8601String(),
                        'updated_at'                      => $encounter->updated_at->toIso8601String(),
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
                        'hmo_name'         => $patient->hmo->name ?? 'N/A',
                        'hmo_no'           => $patient->hmo_no ?? '',
                        'allergies'        => $patient->allergies ?? [],
                        'next_of_kin_name' => $patient->next_of_kin_name ?? '',
                        'next_of_kin_phone' => $patient->next_of_kin_phone ?? '',
                    ],
                    'vitals'        => $vitals,
                    'labs'          => $labs,
                    'imaging'       => $imaging,
                    'prescriptions' => $prescriptions,
                    'procedures'    => $procedures,
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
    public function encounterHistory(patient $patient)
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
    public function labHistory(patient $patient)
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
    public function imagingHistory(patient $patient)
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
    public function prescriptionHistory(patient $patient)
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
    public function procedureHistory(patient $patient)
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

    private function endOldContinuingEncounters()
    {
        $threshold = Carbon::now()->subHours(appsettings('consultation_cycle_duration', 24));
        DoctorQueue::where('status', 2)
            ->where('created_at', '<', $threshold)
            ->update(['status' => 3]);
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
