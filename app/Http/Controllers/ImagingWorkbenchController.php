<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Patient;
use App\Models\ImagingServiceRequest;
use App\Models\ProductOrServiceRequest;
use App\Models\VitalSign;
use App\Models\Encounter;
use App\Models\ProductRequest;
use App\Models\service;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Helpers\HmoHelper;

use Yajra\DataTables\DataTables;

class ImagingWorkbenchController extends Controller
{
    /**
     * Display the imaging workbench main page
     */
    public function index()
    {
        // Check permission
        if (!Auth::user()->can('see-investigations')) {
            abort(403, 'Unauthorized access to Imaging Workbench');
        }

        return view('admin.imaging.workbench');
    }

    /**
     * Search for patients (same logic as receptionist lookup)
     */
    public function searchPatients(Request $request)
    {
        $term = $request->get('term', '');

        if (strlen($term) < 2) {
            return response()->json([]);
        }

        $patients = Patient::with('user')
            ->where(function ($query) use ($term) {
                $query->whereHas('user', function ($userQuery) use ($term) {
                    $userQuery->where('surname', 'like', "%{$term}%")
                        ->orWhere('firstname', 'like', "%{$term}%")
                        ->orWhere('othername', 'like', "%{$term}%");
                })
                ->orWhere('file_no', 'like', "%{$term}%")
                ->orWhere('phone_no', 'like', "%{$term}%");
            })
            ->limit(10)
            ->get();

        $results = $patients->map(function ($patient) {
            // Count pending imaging requests (status 1 = awaiting billing, 2 = awaiting results)
            $pendingCount = ImagingServiceRequest::where('patient_id', $patient->id)
                ->whereIn('status', [1, 2])
                ->count();

            return [
                'id' => $patient->id,
                'name' => $patient->user->surname . ' ' . $patient->user->firstname . ' ' . $patient->user->othername,
                'file_no' => $patient->file_no,
                'age' => $patient->dob ? \Carbon\Carbon::parse($patient->dob)->age : 'N/A',
                'gender' => $patient->gender ?? 'N/A',
                'phone' => $patient->phone_no ?? 'N/A',
                'photo' => $patient->user->photo ?? 'avatar.png',
                'pending_count' => $pendingCount,
            ];
        });

        return response()->json($results);
    }

    /**
     * Get queue counts (billing, results) - No sample stage for imaging
     */
    public function getQueueCounts()
    {
        $billingCount = ImagingServiceRequest::where('status', 1)->count();
        $resultCount = ImagingServiceRequest::where('status', 2)->count();

        return response()->json([
            'billing' => $billingCount,
            'results' => $resultCount,
            'total' => $billingCount + $resultCount,
        ]);
    }

    /**
     * Get patient's pending imaging requests
     */
    public function getPatientRequests($patientId)
    {
        $patient = Patient::with(['user', 'hmo.scheme'])->findOrFail($patientId);

        // Get all pending imaging requests
        $requests = ImagingServiceRequest::with(['service', 'doctor', 'biller', 'patient', 'productOrServiceRequest'])
            ->where('patient_id', $patientId)
            ->whereIn('status', [1, 2])
            ->orderBy('created_at', 'desc')
            ->get();

        // Add delivery check for each request
        $requests = $requests->map(function ($request) {
            $deliveryCheck = null;
            if ($request->productOrServiceRequest) {
                $deliveryCheck = HmoHelper::canDeliverService($request->productOrServiceRequest);
            }
            $request->delivery_check = $deliveryCheck;
            return $request;
        });

        // Group by status - No sample stage for imaging
        $billing = $requests->where('status', 1)->values();
        $results = $requests->where('status', 2)->values();

        // Calculate detailed age
        $ageText = 'N/A';
        if ($patient->dob) {
            $dob = \Carbon\Carbon::parse($patient->dob);
            $now = \Carbon\Carbon::now();
            $years = $dob->diffInYears($now);
            $months = $dob->copy()->addYears($years)->diffInMonths($now);
            $days = $dob->copy()->addYears($years)->addMonths($months)->diffInDays($now);

            $ageParts = [];
            if ($years > 0) $ageParts[] = $years . 'y';
            if ($months > 0) $ageParts[] = $months . 'm';
            if ($days > 0) $ageParts[] = $days . 'd';
            $ageText = !empty($ageParts) ? implode(' ', $ageParts) : '0d';
        }

        return response()->json([
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->user->surname . ' ' . $patient->user->firstname,
                'file_no' => $patient->file_no,
                'age' => $ageText,
                'gender' => $patient->gender ?? 'N/A',
                'blood_group' => $patient->blood_group ?? 'N/A',
                'genotype' => $patient->genotype ?? 'N/A',
                'phone' => $patient->phone_no ?? 'N/A',
                'address' => $patient->address ?? 'N/A',
                'nationality' => $patient->nationality ?? 'N/A',
                'ethnicity' => $patient->ethnicity ?? 'N/A',
                'disability' => $patient->disability == 1 ? 'Yes' : 'No',
                'hmo' => $patient->hmo ? $patient->hmo->name : 'N/A',
                'hmo_category' => $patient->hmo && $patient->hmo->scheme ? $patient->hmo->scheme->name : 'N/A',
                'hmo_no' => $patient->hmo_no ?? 'N/A',
                'insurance_scheme' => $patient->insurance_scheme ?? 'N/A',
                'allergies' => $patient->allergies ?? [],
                'medical_history' => $patient->medical_history ?? 'N/A',
                'misc' => $patient->misc ?? 'N/A',
            ],
            'requests' => [
                'billing' => $billing,
                'results' => $results,
            ],
        ]);
    }

    /**
     * Get patient's recent vitals
     */
    public function getPatientVitals($patientId, Request $request)
    {
        $limit = $request->get('limit', 10);

        $vitals = VitalSign::where('patient_id', $patientId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json($vitals);
    }

    /**
     * Get patient's recent doctor notes
     */
    public function getPatientNotes($patientId, Request $request)
    {
        $limit = $request->get('limit', 10);

        $encounters = Encounter::with(['doctor.staff_profile.specialization'])
            ->where('patient_id', $patientId)
            ->whereNotNull('notes')
            ->where('completed', true)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
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
                'reasons_for_encounter_comment_1' => $encounter->reasons_for_encounter_comment_1,
                'reasons_for_encounter_comment_2' => $encounter->reasons_for_encounter_comment_2,
                'notes' => $encounter->notes,
                'notes_preview' => \Illuminate\Support\Str::limit(strip_tags($encounter->notes), 150),
            ];
        });

        return response()->json($notes);
    }

    /**
     * Get imaging queue data for DataTable
     */
    public function getImagingQueue(Request $request)
    {
        try {
            // Base query with all necessary relationships
            $query = ImagingServiceRequest::with([
                'service',
                'patient.user',
                'patient.hmo.scheme',
                'doctor',
                'biller',
                'resultBy',
                'productOrServiceRequest'
            ]);

            // Filter by status if provided
            if ($request->has('status') && $request->status !== 'all') {
                $statuses = explode(',', $request->status);
                $query->whereIn('status', $statuses);
            } else {
                // Default to pending statuses (1 = billing, 2 = results)
                $query->whereIn('status', [1, 2]);
            }

            // Apply date range filter if provided
            if ($request->has('start_date') && $request->has('end_date')) {
                $startDate = \Carbon\Carbon::parse($request->start_date)->startOfDay();
                $endDate = \Carbon\Carbon::parse($request->end_date)->endOfDay();
                $query->whereBetween('created_at', [$startDate, $endDate]);
            }

            $requests = $query->orderBy('created_at', 'desc')->get();

            return Datatables::of($requests)
                ->addIndexColumn()
                ->addColumn('card_data', function ($request) {
                    if (!$request->patient || !$request->patient->user) {
                        return [
                            'error' => true,
                            'message' => 'Invalid patient data'
                        ];
                    }

                    // Calculate age
                    $age = 'N/A';
                    if ($request->patient->dob) {
                        $dob = \Carbon\Carbon::parse($request->patient->dob);
                        $age = $dob->age . 'y';
                    }

                    // Check delivery status (payment + HMO validation)
                    $deliveryCheck = null;
                    if ($request->productOrServiceRequest) {
                        $deliveryCheck = \App\Helpers\HmoHelper::canDeliverService($request->productOrServiceRequest);
                    }

                    return [
                        'id' => $request->id,
                        'patient_id' => $request->patient->id,
                        'patient_name' => $request->patient->user->surname . ' ' . $request->patient->user->firstname,
                        'file_no' => $request->patient->file_no ?? 'N/A',
                        'age' => $age,
                        'gender' => $request->patient->gender ?? 'N/A',
                        'hmo' => $request->patient->hmo ? $request->patient->hmo->name : 'N/A',
                        'hmo_category' => $request->patient->hmo && $request->patient->hmo->scheme ? $request->patient->hmo->scheme->name : 'N/A',
                        'hmo_no' => $request->patient->hmo_no ?? 'N/A',
                        'service_name' => $request->service ? $request->service->service_name : 'N/A',
                        'status' => $request->status,
                        'note' => $request->note ?? null,
                        'result' => $request->result ?? null,
                        'attachments' => $request->attachments ?? [],
                        'requested_by' => $request->doctor ? $request->doctor->surname . ' ' . $request->doctor->firstname : 'N/A',
                        'requested_at' => $this->formatDateTime($request->created_at),
                        'billed_by' => $request->biller ? $request->biller->surname . ' ' . $request->biller->firstname : null,
                        'billed_at' => $this->formatDateTime($request->billed_date),
                        'result_by' => $request->resultBy ? $request->resultBy->surname . ' ' . $request->resultBy->firstname : null,
                        'result_at' => $this->formatDateTime($request->result_date),
                        'updated_at' => $this->formatDateTime($request->updated_at),
                        'delivery_check' => $deliveryCheck,
                    ];
                })
                ->rawColumns(['card_data'])
                ->make(true);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while fetching queue data.',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Format datetime to readable format
     */
    private function formatDateTime($datetime)
    {
        if (!$datetime) {
            return null;
        }
        return \Carbon\Carbon::parse($datetime)->format('h:i a D M j, Y');
    }

    /**
     * Get patient's recent medications
     */
    public function getPatientMedications($patientId, Request $request)
    {
        $limit = $request->get('limit', 20);
        $status = $request->get('status', 'all');

        $query = ProductRequest::with(['product', 'doctor', 'biller', 'dispenser'])
            ->where('patient_id', $patientId)
            ->whereNotNull('product_id')
            ->orderBy('created_at', 'desc');

        if ($status === 'pending') {
            $query->whereNull('billed_by');
        } elseif ($status === 'billed') {
            $query->whereNotNull('billed_by')->whereNull('dispensed_by');
        } elseif ($status === 'dispensed') {
            $query->whereNotNull('dispensed_by');
        }

        $medications = $query->limit($limit)->get();

        $result = $medications->map(function ($med) {
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
                'status' => $status,
                'requested_date' => $med->created_at->format('h:i a D M j, Y'),
                'billed_by' => $med->biller ? $med->biller->firstname . ' ' . $med->biller->surname : null,
                'billed_date' => $med->billed_date ? \Carbon\Carbon::parse($med->billed_date)->format('h:i a D M j, Y') : null,
                'dispensed_by' => $med->dispenser ? $med->dispenser->firstname . ' ' . $med->dispenser->surname : null,
                'dispensed_date' => $med->dispense_date ? \Carbon\Carbon::parse($med->dispense_date)->format('h:i a D M j, Y') : null,
                'doctor' => $med->doctor ? $med->doctor->firstname . ' ' . $med->doctor->surname : 'N/A',
            ];
        });

        return response()->json($result);
    }

    /**
     * Get patient's clinical context (all 3 panels)
     */
    public function getClinicalContext($patientId)
    {
        return response()->json([
            'vitals' => $this->getPatientVitals($patientId, new Request(['limit' => 10]))->getData(),
            'notes' => $this->getPatientNotes($patientId, new Request(['limit' => 10]))->getData(),
            'medications' => $this->getPatientMedications($patientId, new Request(['limit' => 20]))->getData(),
        ]);
    }

    /**
     * Record billing for selected imaging requests
     */
    public function recordBilling(Request $request)
    {
        try {
            $request->validate([
                'request_ids' => 'required|array',
                'request_ids.*' => 'exists:imaging_service_requests,id',
                'patient_id' => 'required|exists:patients,id'
            ]);

            DB::beginTransaction();

            foreach ($request->request_ids as $requestId) {
                $imagingRequest = ImagingServiceRequest::findOrFail($requestId);

                // Create ProductOrServiceRequest for billing
                $billReq = new ProductOrServiceRequest();
                $billReq->user_id = $imagingRequest->patient->user_id;
                $billReq->staff_user_id = Auth::id();
                $billReq->service_id = $imagingRequest->service_id;

                // Apply HMO tariff if patient has HMO
                try {
                    $hmoData = HmoHelper::applyHmoTariff(
                        $imagingRequest->patient_id,
                        null,
                        $imagingRequest->service_id
                    );
                    if ($hmoData) {
                        $billReq->payable_amount = $hmoData['payable_amount'];
                        $billReq->claims_amount = $hmoData['claims_amount'];
                        $billReq->coverage_mode = $hmoData['coverage_mode'];
                        $billReq->validation_status = $hmoData['validation_status'];
                    }
                } catch (\Exception $e) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'HMO Tariff Error: ' . $e->getMessage()
                    ], 400);
                }

                $billReq->save();

                // Update imaging request status to billed (2 = awaiting results)
                // No sample collection stage for imaging
                $imagingRequest->update([
                    'status' => 2,
                    'billed_by' => Auth::id(),
                    'billed_date' => now(),
                    'service_request_id' => $billReq->id,
                ]);

                // Log audit
                $this->logAudit($imagingRequest->id, 'billing', 'Imaging request billed');
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($request->request_ids) . ' request(s) billed successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error recording billing: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dismiss/cancel selected imaging requests
     */
    public function dismissRequests(Request $request)
    {
        try {
            $request->validate([
                'request_ids' => 'required|array',
                'request_ids.*' => 'exists:imaging_service_requests,id',
                'patient_id' => 'required|exists:patients,id'
            ]);

            DB::beginTransaction();

            foreach ($request->request_ids as $requestId) {
                $imagingRequest = ImagingServiceRequest::findOrFail($requestId);

                // Update imaging request status to dismissed (0)
                $imagingRequest->update([
                    'status' => 0
                ]);

                // Log audit
                $this->logAudit($imagingRequest->id, 'dismiss', 'Imaging request dismissed');
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($request->request_ids) . ' request(s) dismissed successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error dismissing requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single imaging request with service details
     */
    public function getImagingRequest($id)
    {
        try {
            $request = ImagingServiceRequest::with(['service', 'patient.user', 'doctor', 'resultBy'])
                ->findOrFail($id);

            return response()->json([
                'id' => $request->id,
                'patient_id' => $request->patient_id,
                'patient' => [
                    'file_no' => $request->patient->file_no ?? 'N/A',
                    'date_of_birth' => $request->patient->dob ?? null,
                    'gender' => $request->patient->gender ?? 'N/A',
                    'user' => [
                        'firstname' => $request->patient->user->firstname ?? 'N/A',
                        'surname' => $request->patient->user->surname ?? 'N/A'
                    ]
                ],
                'service' => [
                    'name' => $request->service->service_name ?? 'N/A',
                    'template_version' => !empty($request->service->result_template_v2) ? 2 : 1,
                    'template_body' => $request->service->template ?? '',
                    'template_structure' => $request->service->result_template_v2 ?? null
                ],
                'status' => $request->status,
                'result' => $request->result,
                'result_data' => $request->result_data,
                'result_date' => $request->result_date,
                'sample_date' => $request->sample_date ?? null,
                'attachments' => $request->attachments,
                'results_person' => [
                    'firstname' => $request->resultBy->firstname ?? 'N/A',
                    'surname' => $request->resultBy->surname ?? 'N/A'
                ],
                'doctor' => [
                    'firstname' => $request->doctor->firstname ?? 'N/A',
                    'surname' => $request->doctor->surname ?? 'N/A'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading imaging request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attachments for an imaging request
     */
    public function getRequestAttachments($id)
    {
        try {
            $request = ImagingServiceRequest::findOrFail($id);

            $attachments = [];

            if ($request->attachments) {
                $attachmentsData = is_string($request->attachments)
                    ? json_decode($request->attachments, true)
                    : $request->attachments;

                if (is_array($attachmentsData)) {
                    foreach ($attachmentsData as $att) {
                        $attachments[] = [
                            'id' => $att['id'] ?? uniqid(),
                            'filename' => $att['filename'] ?? ($att['name'] ?? 'Unknown'),
                            'url' => $att['url'] ?? (isset($att['path']) ? asset('storage/' . $att['path']) : '')
                        ];
                    }
                }
            }

            return response()->json($attachments);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading attachments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save result of imaging request
     */
    public function saveResult(Request $request)
    {
        try {
            $request->validate([
                'invest_res_template_submited' => 'required|string',
                'invest_res_entry_id' => 'required',
                'invest_res_template_version' => 'required|in:1,2',
                'invest_res_template_data' => 'nullable|string',
                'result_attachments.*' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx'
            ]);

            $imagingRequest = ImagingServiceRequest::findOrFail($request->invest_res_entry_id);

            // Check if service can be delivered (payment + HMO validation)
            if ($imagingRequest->productOrServiceRequest) {
                $deliveryCheck = \App\Helpers\HmoHelper::canDeliverService($imagingRequest->productOrServiceRequest);
                if (!$deliveryCheck['can_deliver']) {
                    return response()->json([
                        'success' => false,
                        'message' => $deliveryCheck['reason'],
                        'hint' => $deliveryCheck['hint']
                    ], 403);
                }
            }

            $isEdit = $request->invest_res_is_edit == '1';
            $templateVersion = $request->invest_res_template_version;

            // If this is an edit, check if we're within the edit time window
            if ($isEdit && $imagingRequest->result_date) {
                $resultDate = Carbon::parse($imagingRequest->result_date);
                $editDuration = appsettings('result_edit_duration') ?? 60;
                $editDeadline = $resultDate->addMinutes($editDuration);

                if (Carbon::now()->greaterThan($editDeadline)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Edit window has expired. Results can only be edited within {$editDuration} minutes of submission."
                    ], 403);
                }
            }

            // Process result based on template version
            $resultHtml = $request->invest_res_template_submited;
            $resultData = null;

            if ($templateVersion == '2' && $request->invest_res_template_data) {
                $structuredData = json_decode($request->invest_res_template_data, true);

                if ($structuredData) {
                    $service = service::find($imagingRequest->service_id);
                    $template = $service->result_template_v2;

                    if ($template && isset($template['parameters'])) {
                        $enhancedData = [];
                        $htmlResult = '<div class="imaging-result-v2">';
                        $htmlResult .= '<table class="table table-bordered">';
                        $htmlResult .= '<thead><tr><th>Parameter</th><th>Value</th><th>Reference Range</th><th>Status</th></tr></thead>';
                        $htmlResult .= '<tbody>';

                        foreach ($template['parameters'] as $param) {
                            if (isset($structuredData[$param['id']])) {
                                $value = $structuredData[$param['id']];
                                $status = $this->determineParameterStatus($param, $value);

                                $enhancedData[$param['id']] = [
                                    'value' => $value,
                                    'status' => $status
                                ];

                                $htmlResult .= '<tr>';
                                $htmlResult .= '<td><strong>' . htmlspecialchars($param['name']) . '</strong>';
                                if (isset($param['unit']) && $param['unit']) {
                                    $htmlResult .= ' <small>(' . htmlspecialchars($param['unit']) . ')</small>';
                                }
                                $htmlResult .= '</td>';
                                $htmlResult .= '<td>' . htmlspecialchars($this->formatValue($param['type'], $value)) . '</td>';
                                $htmlResult .= '<td>' . $this->formatReferenceRange($param) . '</td>';
                                $htmlResult .= '<td>' . $this->formatStatus($status) . '</td>';
                                $htmlResult .= '</tr>';
                            }
                        }

                        $htmlResult .= '</tbody></table></div>';
                        $resultHtml = $htmlResult;
                        $resultData = $enhancedData;
                    }
                }
            } else {
                // V1 Template: Process as before
                $resultHtml = str_replace('contenteditable="true"', 'contenteditable="false"', $resultHtml);
                $resultHtml = str_replace("contenteditable='true'", "contenteditable='false'", $resultHtml);
            }

            // Handle file uploads
            $attachments = [];
            $existingAttachments = $imagingRequest->attachments;
            if (is_string($existingAttachments)) {
                $existingAttachments = json_decode($existingAttachments, true) ?? [];
            } elseif (!is_array($existingAttachments)) {
                $existingAttachments = [];
            }

            // Handle attachment deletions
            if ($request->has('deleted_attachments')) {
                $deletedIndexes = json_decode($request->deleted_attachments, true) ?? [];
                foreach ($deletedIndexes as $index) {
                    if (isset($existingAttachments[$index])) {
                        $filePath = storage_path('app/public/' . $existingAttachments[$index]['path']);
                        if (file_exists($filePath)) {
                            @unlink($filePath);
                        }
                        unset($existingAttachments[$index]);
                    }
                }
                $existingAttachments = array_values($existingAttachments);
            }

            if ($request->hasFile('result_attachments')) {
                foreach ($request->file('result_attachments') as $file) {
                    $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $file->storeAs('public/imaging_results', $fileName);
                    $attachments[] = [
                        'name' => $file->getClientOriginalName(),
                        'path' => 'imaging_results/' . $fileName,
                        'size' => $file->getSize(),
                        'type' => $file->getClientOriginalExtension()
                    ];
                }
            }

            $allAttachments = array_merge($existingAttachments, $attachments);

            DB::beginTransaction();

            $updateData = [
                'result' => $resultHtml,
                'result_data' => $resultData,
                'attachments' => !empty($allAttachments) ? json_encode($allAttachments) : null,
                'status' => 4 // Completed
            ];

            if (!$isEdit) {
                $updateData['result_date'] = now();
                $updateData['result_by'] = Auth::id();
            }

            $imagingRequest->update($updateData);

            // Log audit
            $this->logAudit($imagingRequest->id, $isEdit ? 'result_edit' : 'result_entry', 'Imaging result ' . ($isEdit ? 'updated' : 'entered'));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $isEdit ? 'Results updated successfully' : 'Results saved successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error saving results: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Determine the status of a parameter value based on reference range
     */
    private function determineParameterStatus($param, $value)
    {
        if (!isset($param['reference_range'])) {
            return 'N/A';
        }

        $refRange = $param['reference_range'];
        $type = $param['type'];

        if ($type === 'integer' || $type === 'float') {
            if (isset($refRange['min']) && isset($refRange['max'])) {
                $numValue = floatval($value);
                if ($numValue < $refRange['min']) {
                    return 'Low';
                } elseif ($numValue > $refRange['max']) {
                    return 'High';
                } else {
                    return 'Normal';
                }
            }
        } elseif ($type === 'boolean') {
            if (isset($refRange['reference_value'])) {
                $boolValue = $value === true || $value === 'true';
                $refValue = $refRange['reference_value'] === true;
                return $boolValue === $refValue ? 'Normal' : 'Abnormal';
            }
        } elseif ($type === 'enum') {
            if (isset($refRange['reference_value'])) {
                return $value === $refRange['reference_value'] ? 'Normal' : 'Abnormal';
            }
        }

        return 'N/A';
    }

    /**
     * Format a value for display
     */
    private function formatValue($type, $value)
    {
        if ($value === null || $value === '') {
            return 'N/A';
        }

        if ($type === 'boolean') {
            return $value === true || $value === 'true' ? 'Yes/Positive' : 'No/Negative';
        }

        if ($type === 'float') {
            return number_format(floatval($value), 2);
        }

        return $value;
    }

    /**
     * Format reference range for display
     */
    private function formatReferenceRange($param)
    {
        if (!isset($param['reference_range'])) {
            return 'N/A';
        }

        $refRange = $param['reference_range'];
        $type = $param['type'];

        if ($type === 'integer' || $type === 'float') {
            if (isset($refRange['min']) && isset($refRange['max'])) {
                return $refRange['min'] . ' - ' . $refRange['max'];
            }
        } elseif ($type === 'boolean') {
            if (isset($refRange['reference_value'])) {
                return $refRange['reference_value'] ? 'Yes/Positive' : 'No/Negative';
            }
        } elseif ($type === 'enum') {
            if (isset($refRange['reference_value'])) {
                return $refRange['reference_value'];
            }
        } elseif (isset($refRange['text'])) {
            return $refRange['text'];
        }

        return 'N/A';
    }

    /**
     * Format status badge
     */
    private function formatStatus($status)
    {
        $badges = [
            'Normal' => '<span class="badge badge-success">Normal</span>',
            'High' => '<span class="badge badge-danger">High</span>',
            'Low' => '<span class="badge badge-warning">Low</span>',
            'Abnormal' => '<span class="badge badge-warning">Abnormal</span>',
            'N/A' => '<span class="badge badge-secondary">N/A</span>'
        ];

        return $badges[$status] ?? $status;
    }

    /**
     * Delete (soft) imaging request
     */
    public function deleteRequest($id)
    {
        try {
            $imagingRequest = ImagingServiceRequest::findOrFail($id);
            $imagingRequest->delete();

            $this->logAudit($id, 'delete', 'Imaging request deleted');

            return response()->json([
                'success' => true,
                'message' => 'Request deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore deleted imaging request
     */
    public function restoreRequest($id)
    {
        try {
            $imagingRequest = ImagingServiceRequest::withTrashed()->findOrFail($id);
            $imagingRequest->restore();

            $this->logAudit($id, 'restore', 'Imaging request restored');

            return response()->json([
                'success' => true,
                'message' => 'Request restored successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error restoring request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dismiss individual imaging request
     */
    public function dismissRequest($id)
    {
        try {
            $imagingRequest = ImagingServiceRequest::findOrFail($id);
            $imagingRequest->update(['status' => 0]);

            $this->logAudit($id, 'dismiss', 'Imaging request dismissed');

            return response()->json([
                'success' => true,
                'message' => 'Request dismissed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error dismissing request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Undismiss imaging request
     */
    public function undismissRequest($id)
    {
        try {
            $imagingRequest = ImagingServiceRequest::findOrFail($id);
            $imagingRequest->update(['status' => 1]);

            $this->logAudit($id, 'undismiss', 'Imaging request undismissed');

            return response()->json([
                'success' => true,
                'message' => 'Request undismissed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error undismissing request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get deleted imaging requests
     */
    public function getDeletedRequests($patientId = null)
    {
        try {
            $query = ImagingServiceRequest::onlyTrashed()
                ->with(['service', 'patient.user', 'doctor']);

            if ($patientId) {
                $query->where('patient_id', $patientId);
            }

            $requests = $query->orderBy('deleted_at', 'desc')->get();

            $result = $requests->map(function ($req) {
                return [
                    'id' => $req->id,
                    'service_name' => $req->service ? $req->service->service_name : 'N/A',
                    'patient_name' => $req->patient && $req->patient->user
                        ? $req->patient->user->surname . ' ' . $req->patient->user->firstname
                        : 'N/A',
                    'deleted_at' => $req->deleted_at->format('h:i a D M j, Y'),
                    'requested_by' => $req->doctor ? $req->doctor->surname . ' ' . $req->doctor->firstname : 'N/A',
                ];
            });

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading deleted requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dismissed imaging requests
     */
    public function getDismissedRequests($patientId = null)
    {
        try {
            $query = ImagingServiceRequest::where('status', 0)
                ->with(['service', 'patient.user', 'doctor']);

            if ($patientId) {
                $query->where('patient_id', $patientId);
            }

            $requests = $query->orderBy('updated_at', 'desc')->get();

            $result = $requests->map(function ($req) {
                return [
                    'id' => $req->id,
                    'service_name' => $req->service ? $req->service->service_name : 'N/A',
                    'patient_name' => $req->patient && $req->patient->user
                        ? $req->patient->user->surname . ' ' . $req->patient->user->firstname
                        : 'N/A',
                    'dismissed_at' => $req->updated_at->format('h:i a D M j, Y'),
                    'requested_by' => $req->doctor ? $req->doctor->surname . ' ' . $req->doctor->firstname : 'N/A',
                ];
            });

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading dismissed requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get imaging history for patient (completed requests)
     */
    public function getImagingHistoryList($patientId)
    {
        $history = ImagingServiceRequest::with(['service', 'doctor', 'biller', 'resultBy'])
            ->where('patient_id', $patientId)
            ->where('status', 4) // Completed
            ->orderBy('created_at', 'desc')
            ->get();

        return Datatables::of($history)
            ->addIndexColumn()
            ->addColumn('info', function ($req) {
                $serviceName = $req->service ? $req->service->service_name : 'N/A';
                $requestDate = $this->formatDateTime($req->created_at);
                $resultDate = $this->formatDateTime($req->result_date);
                $doctorName = $req->doctor ? $req->doctor->surname . ' ' . $req->doctor->firstname : 'N/A';
                $resultBy = $req->resultBy ? $req->resultBy->surname . ' ' . $req->resultBy->firstname : 'N/A';

                // Build attachments HTML
                $attachmentsHtml = '';
                if ($req->attachments) {
                    $attachments = is_string($req->attachments) ? json_decode($req->attachments, true) : $req->attachments;
                    if (!empty($attachments)) {
                        $attachmentsHtml = '<div class="mt-2"><strong>Attachments:</strong><br>';
                        foreach ($attachments as $att) {
                            $url = asset('storage/' . $att['path']);
                            $attachmentsHtml .= '<a href="' . $url . '" target="_blank" class="badge badge-info mr-1">';
                            $attachmentsHtml .= '<i class="fa fa-file"></i> ' . ($att['name'] ?? 'File') . '</a> ';
                        }
                        $attachmentsHtml .= '</div>';
                    }
                }

                return '
                    <div class="history-card p-3 mb-3 border rounded">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="mb-0"><span class="badge badge-primary">' . $serviceName . '</span></h6>
                            <button class="btn btn-sm btn-success view-invest-result-btn" data-request-id="' . $req->id . '">
                                <i class="fa fa-eye"></i> View Result
                            </button>
                        </div>
                        <div class="small text-muted">
                            <p class="mb-1"><strong>Requested:</strong> ' . $requestDate . ' by ' . $doctorName . '</p>
                            <p class="mb-1"><strong>Result:</strong> ' . $resultDate . ' by ' . $resultBy . '</p>
                        </div>
                        ' . $attachmentsHtml . '
                    </div>
                ';
            })
            ->rawColumns(['info'])
            ->make(true);
    }

    /**
     * Search imaging services
     */
    public function searchServices(Request $request)
    {
        $term = $request->get('term', '');
        $imagingCategory = appsettings('imaging_services_category', null);

        if (strlen($term) < 2) {
            return response()->json([]);
        }

        $query = service::where('service_name', 'like', "%{$term}%")
            ->where('is_active', 1);

        // Filter by imaging category if set
        if ($imagingCategory) {
            $query->where('category_id', $imagingCategory);
        }

        $services = $query->limit(15)->get();

        $results = $services->map(function ($service) {
            return [
                'id' => $service->id,
                'name' => $service->service_name,
                'price' => $service->price ? $service->price->sale_price : 0,
                'category' => $service->category ? $service->category->name : 'N/A'
            ];
        });

        return response()->json($results);
    }

    /**
     * Create new imaging request
     */
    public function createRequest(Request $request)
    {
        try {
            $request->validate([
                'patient_id' => 'required|exists:patients,id',
                'service_ids' => 'required|array',
                'service_ids.*' => 'exists:services,id',
                'notes' => 'nullable|array',
                'clinical_notes' => 'nullable|string',
                'special_instructions' => 'nullable|string',
                'urgency' => 'nullable|string|in:routine,urgent,stat',
                'priority' => 'nullable|string|in:normal,high',
            ]);

            DB::beginTransaction();

            $patient = Patient::findOrFail($request->patient_id);
            $notes = $request->notes ?? [];

            foreach ($request->service_ids as $index => $serviceId) {
                $imagingRequest = new ImagingServiceRequest();
                $imagingRequest->patient_id = $patient->id;
                $imagingRequest->service_id = $serviceId;
                $imagingRequest->doctor_id = Auth::id();
                // Use individual note if available, otherwise use clinical_notes
                $individualNote = isset($notes[$index]) && !empty($notes[$index]) ? $notes[$index] : '';
                $clinicalNotes = $request->clinical_notes ?? '';
                $imagingRequest->note = $individualNote ?: $clinicalNotes;
                $imagingRequest->status = 1; // Awaiting billing
                $imagingRequest->save();

                $this->logAudit($imagingRequest->id, 'create', 'Imaging request created from workbench');
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($request->service_ids) . ' imaging request(s) created successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error creating request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get audit log for imaging requests
     */
    public function getAuditLog(Request $request)
    {
        try {
            $query = DB::table('imaging_audit_log')
                ->join('users', 'imaging_audit_log.user_id', '=', 'users.id')
                ->select(
                    'imaging_audit_log.*',
                    DB::raw("CONCAT(users.surname, ' ', users.firstname) as user_name")
                )
                ->orderBy('imaging_audit_log.created_at', 'desc');

            if ($request->has('request_id')) {
                $query->where('imaging_audit_log.request_id', $request->request_id);
            }

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('imaging_audit_log.created_at', [
                    $request->start_date . ' 00:00:00',
                    $request->end_date . ' 23:59:59'
                ]);
            }

            $logs = $query->limit(500)->get();

            return response()->json($logs);
        } catch (\Exception $e) {
            // If audit log table doesn't exist, return empty array
            return response()->json([]);
        }
    }

    /**
     * Log audit entry
     */
    private function logAudit($requestId, $action, $description)
    {
        try {
            DB::table('imaging_audit_log')->insert([
                'request_id' => $requestId,
                'action' => $action,
                'description' => $description,
                'user_id' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } catch (\Exception $e) {
            // Silently fail if audit log table doesn't exist
            \Log::warning('Could not log imaging audit: ' . $e->getMessage());
        }
    }
}
