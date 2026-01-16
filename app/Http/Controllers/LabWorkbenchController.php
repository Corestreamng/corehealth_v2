<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\patient;
use App\Models\LabServiceRequest;
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

class LabWorkbenchController extends Controller
{
    /**
     * Display the lab workbench main page
     */
    public function index()
    {
        // Check permission
        if (!Auth::user()->can('see-investigations')) {
            abort(403, 'Unauthorized access to Lab Workbench');
        }

        return view('admin.lab.workbench');
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
            $pendingCount = LabServiceRequest::where('patient_id', $patient->id)
                ->whereIn('status', [1, 2, 3])
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
     * Get queue counts (billing, sample, results)
     */
    public function getQueueCounts()
    {
        $billingCount = LabServiceRequest::where('status', 1)->count();
        $sampleCount = LabServiceRequest::where('status', 2)->count();
        $resultCount = LabServiceRequest::where('status', 3)->count();
        $completedCount = LabServiceRequest::where('status', 4)->count();

        return response()->json([
            'billing' => $billingCount,
            'sample' => $sampleCount,
            'results' => $resultCount,
            'completed' => $completedCount,
            'total' => $billingCount + $sampleCount + $resultCount,
        ]);
    }

    /**
     * Get patient's pending requests
     */
    public function getPatientRequests($patientId)
    {
        $patient = Patient::with(['user', 'hmo.scheme'])->findOrFail($patientId);

        // Get all pending investigation requests
        $requests = LabServiceRequest::with(['service', 'doctor', 'biller', 'patient'])
            ->where('patient_id', $patientId)
            ->whereIn('status', [1, 2, 3])
            ->orderBy('created_at', 'desc')
            ->get();

        // Group by status
        $billing = $requests->where('status', 1)->values();
        $sample = $requests->where('status', 2)->values();
        $results = $requests->where('status', 3)->values();

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
                'sample' => $sample,
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
     * Get lab queue data for DataTable
     */
    public function getLabQueue(Request $request)
    {
        try {
            // Base query with all necessary relationships
            $query = LabServiceRequest::with([
                'service',
                'patient.user',
                'patient.hmo.scheme',
                'doctor',
                'biller',
                'resultBy',
                'productOrServiceRequest' // Add product/service request for delivery check
            ]);

            // Filter by status if provided
            if ($request->has('status') && $request->status !== 'all') {
                $statuses = explode(',', $request->status);
                $query->whereIn('status', $statuses);
            } else {
                // Default to pending statuses (1, 2, 3)
                $query->whereIn('status', [1, 2, 3]);
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
                        'sample_taken_by' => $request->sample_taken_by ? userfullname($request->sample_taken_by) : null,
                        'sample_taken_at' => $this->formatDateTime($request->sample_date),
                        'result_by' => $request->resultBy ? $request->resultBy->surname . ' ' . $request->resultBy->firstname : null,
                        'result_at' => $this->formatDateTime($request->result_date),
                        'updated_at' => $this->formatDateTime($request->updated_at),
                        'delivery_check' => $deliveryCheck, // Add delivery status
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
     * Get queue counts by status
     */
    // public function getQueueCounts(Request $request)
    // {
    //     try {
    //         $billing = LabServiceRequest::where('status', 1)->count();
    //         $sample = LabServiceRequest::where('status', 2)->count();
    //         $results = LabServiceRequest::where('status', 3)->count();
    //         $total = $billing + $sample + $results;

    //         return response()->json([
    //             'billing' => $billing,
    //             'sample' => $sample,
    //             'results' => $results,
    //             'total' => $total
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => $e->getMessage()], 500);
    //     }
    // }

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
        $status = $request->get('status', 'all'); // all, pending, billed, dispensed

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
            // Determine status
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
     * Record billing for selected lab requests
     */
    public function recordBilling(Request $request)
    {
        try {
            $request->validate([
                'request_ids' => 'required|array',
                'request_ids.*' => 'exists:lab_service_requests,id',
                'patient_id' => 'required|exists:patients,id'
            ]);

            DB::beginTransaction();

            foreach ($request->request_ids as $requestId) {
                $labRequest = LabServiceRequest::findOrFail($requestId);

                // Create ProductOrServiceRequest for billing
                $billReq = new ProductOrServiceRequest();
                $billReq->user_id = $labRequest->patient->user_id;
                $billReq->staff_user_id = Auth::id();
                $billReq->service_id = $labRequest->service_id;

                // Apply HMO tariff if patient has HMO
                try {
                    $hmoData = HmoHelper::applyHmoTariff(
                        $labRequest->patient_id,
                        null,
                        $labRequest->service_id
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

                // Update lab request status to billed (2)
                $labRequest->update([
                    'status' => 2,
                    'billed_by' => Auth::id(),
                    'billed_date' => now(),
                    'service_request_id' => $billReq->id,
                ]);

                // Log audit
                $this->logAudit($labRequest->id, 'billing', 'Lab request billed');
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
     * Record sample collection for selected lab requests
     */
    public function collectSample(Request $request)
    {
        try {
            $request->validate([
                'request_ids' => 'required|array',
                'request_ids.*' => 'exists:lab_service_requests,id',
                'patient_id' => 'required|exists:patients,id'
            ]);

            DB::beginTransaction();

            foreach ($request->request_ids as $requestId) {
                $labRequest = LabServiceRequest::findOrFail($requestId);

                // Check HMO access control
                if ($labRequest->productOrServiceRequest) {
                    if (!HmoHelper::canPatientAccessService($labRequest->productOrServiceRequest)) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'Service requires HMO approval. Request ID: ' . $labRequest->id . '. Please contact HMO executive for validation.'
                        ], 403);
                    }
                }

                // Update lab request status to sample taken (3)
                $labRequest->update([
                    'status' => 3,
                    'sample_taken_by' => Auth::id(),
                    'sample_date' => now(),
                    'sample_taken' => true
                ]);

                // Log audit
                $this->logAudit($labRequest->id, 'sample_collection', 'Sample collected');
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($request->request_ids) . ' sample(s) collected successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error recording sample collection: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dismiss/cancel selected lab requests
     */
    public function dismissRequests(Request $request)
    {
        try {
            $request->validate([
                'request_ids' => 'required|array',
                'request_ids.*' => 'exists:lab_service_requests,id',
                'patient_id' => 'required|exists:patients,id'
            ]);

            DB::beginTransaction();

            foreach ($request->request_ids as $requestId) {
                $labRequest = LabServiceRequest::findOrFail($requestId);

                // Update lab request status to dismissed (0)
                $labRequest->update([
                    'status' => 0
                ]);
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
     * Get single lab request with service details
     */
    public function getLabRequest($id)
    {
        try {
            $request = LabServiceRequest::with(['service', 'patient.user'])
                ->findOrFail($id);

            return response()->json([
                'id' => $request->id,
                'patient_id' => $request->patient_id,
                'service' => [
                    'name' => $request->service->service_name ?? 'N/A',
                    'template_version' => !empty($request->service->result_template_v2) ? 2 : 1,
                    'template_body' => $request->service->template ?? '',
                    'template_structure' => $request->service->result_template_v2 ?? null
                ],
                'status' => $request->status,
                'result' => $request->result,
                'result_data' => $request->result_data,
                'result_document' => $request->result_document ?? null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading lab request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attachments for a lab request
     */
    public function getRequestAttachments($id)
    {
        try {
            $request = LabServiceRequest::findOrFail($id);

            // Assuming attachments are stored in a JSON field or related table
            // Adjust based on your actual attachment storage mechanism
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
     * Save result of lab request (Copied from LabServiceRequestController but returns JSON)
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

            $labRequest = LabServiceRequest::findOrFail($request->invest_res_entry_id);

            // Check if service can be delivered (payment + HMO validation)
            if ($labRequest->productOrServiceRequest) {
                $deliveryCheck = \App\Helpers\HmoHelper::canDeliverService($labRequest->productOrServiceRequest);
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
            if ($isEdit && $labRequest->result_date) {
                $resultDate = Carbon::parse($labRequest->result_date);
                $editDuration = appsettings('result_edit_duration') ?? 60; // Default 60 minutes
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
                // V2 Template: Store structured data and generate HTML for display
                $structuredData = json_decode($request->invest_res_template_data, true);

                if ($structuredData) {
                    // Get the service template for generating HTML
                    $service = service::find($labRequest->service_id);
                    $template = $service->result_template_v2;

                    if ($template && isset($template['parameters'])) {
                        // Calculate status for each parameter and generate HTML
                        $enhancedData = [];
                        $htmlResult = '<div class="lab-result-v2">';
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

                                // Generate HTML row
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
                //make all contenteditable section uneditable
                $resultHtml = str_replace('contenteditable="true"', 'contenteditable="false"', $resultHtml);
                $resultHtml = str_replace("contenteditable='true'", "contenteditable='false'", $resultHtml);
                $resultHtml = str_replace('contenteditable = "true"', 'contenteditable="false"', $resultHtml);
                $resultHtml = str_replace("contenteditable ='true'", "contenteditable='false'", $resultHtml);
                $resultHtml = str_replace('contenteditable= "true"', 'contenteditable="false"', $resultHtml);

                //remove all black borders and replace with gray
                $resultHtml = str_replace(' black', ' gray', $resultHtml);
            }

            // Handle file uploads
            $attachments = [];
            $existingAttachments = $labRequest->attachments;
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
                        // Delete physical file
                        $filePath = storage_path('app/public/' . $existingAttachments[$index]['path']);
                        if (file_exists($filePath)) {
                            @unlink($filePath);
                        }
                        unset($existingAttachments[$index]);
                    }
                }
                $existingAttachments = array_values($existingAttachments); // Re-index array
            }

            if ($request->hasFile('result_attachments')) {
                foreach ($request->file('result_attachments') as $file) {
                    $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $file->storeAs('public/lab_results', $fileName);
                    $attachments[] = [
                        'name' => $file->getClientOriginalName(),
                        'path' => 'lab_results/' . $fileName,
                        'size' => $file->getSize(),
                        'type' => $file->getClientOriginalExtension()
                    ];
                }
            }

            // Merge existing and new attachments
            $allAttachments = array_merge($existingAttachments, $attachments);

            DB::beginTransaction();

            $updateData = [
                'result' => $resultHtml,
                'result_data' => $resultData,
                'attachments' => !empty($allAttachments) ? json_encode($allAttachments) : null,
                'status' => 4
            ];

            // Only update result_date and result_by if this is not an edit
            if (!$isEdit) {
                $updateData['result_date'] = date('Y-m-d H:i:s');
                $updateData['result_by'] = Auth::id();
            }

            $req = LabServiceRequest::where('id', $request->invest_res_entry_id)->update($updateData);

            // Log audit trail
            $action = $isEdit ? 'edit' : 'result_entry';
            $description = $isEdit ? 'Result edited' : 'Result entered';
            $this->logAudit($request->invest_res_entry_id, $action, $description);

            DB::commit();

            $message = $isEdit ? "Results Updated Successfully" : "Results Saved Successfully";
            return response()->json([
                'success' => true,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => "An error occurred " . $e->getMessage()
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
     * Soft delete a lab request with reason
     */
    public function deleteRequest(Request $request, $id)
    {
        try {
            $request->validate([
                'reason' => 'required|string|min:10'
            ]);

            $labRequest = LabServiceRequest::findOrFail($id);

            // Check if user has permission to delete
            if (Auth::id() != $labRequest->doctor_id && !Auth::user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to delete this request.'
                ], 403);
            }

            // Check if request can be deleted (no billing, no results)
            if ($labRequest->billed_by || $labRequest->result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete a billed request or one with results.'
                ], 400);
            }

            $labRequest->deleted_by = Auth::id();
            $labRequest->deletion_reason = $request->reason;
            $labRequest->save();
            $labRequest->delete();

            // Log audit
            $this->logAudit($id, 'delete', 'Lab request deleted', null, [
                'reason' => $request->reason
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Lab request deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a deleted lab request
     */
    public function restoreRequest($id)
    {
        try {
            $labRequest = LabServiceRequest::withTrashed()->findOrFail($id);

            if (!$labRequest->trashed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This request is not deleted.'
                ], 400);
            }

            $labRequest->restore();
            $labRequest->deleted_by = null;
            $labRequest->deletion_reason = null;
            $labRequest->save();

            // Log audit
            $this->logAudit($id, 'restore', 'Lab request restored from trash');

            return response()->json([
                'success' => true,
                'message' => 'Lab request restored successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error restoring request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dismiss a lab request with reason
     */
    public function dismissRequest(Request $request, $id)
    {
        try {
            $request->validate([
                'reason' => 'required|string|min:10'
            ]);

            $labRequest = LabServiceRequest::findOrFail($id);

            $labRequest->dismissed_at = now();
            $labRequest->dismissed_by = Auth::id();
            $labRequest->dismiss_reason = $request->reason;
            $labRequest->status = 0; // Set to dismissed status
            $labRequest->save();

            // Log audit
            $this->logAudit($id, 'dismiss', 'Lab request dismissed', null, [
                'reason' => $request->reason
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Lab request dismissed successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error dismissing request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a dismissed lab request
     */
    public function undismissRequest($id)
    {
        try {
            $labRequest = LabServiceRequest::findOrFail($id);

            if (!$labRequest->dismissed_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'This request is not dismissed.'
                ], 400);
            }

            // Restore to appropriate status based on progress
            $newStatus = 1; // Default to billing
            if ($labRequest->billed_by) {
                $newStatus = 2; // Sample collection
            }
            if ($labRequest->sample_taken_by) {
                $newStatus = 3; // Result entry
            }

            $labRequest->dismissed_at = null;
            $labRequest->dismissed_by = null;
            $labRequest->dismiss_reason = null;
            $labRequest->status = $newStatus;
            $labRequest->save();

            // Log audit
            $this->logAudit($id, 'undismiss', 'Lab request restored from dismissed');

            return response()->json([
                'success' => true,
                'message' => 'Lab request restored successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error restoring request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get deleted requests
     */
    public function getDeletedRequests($patientId = null)
    {
        try {
            $query = LabServiceRequest::onlyTrashed()
                ->with(['service', 'doctor', 'patient.user']);

            if ($patientId) {
                $query->where('patient_id', $patientId);
            }

            $requests = $query->orderBy('deleted_at', 'desc')->get();

            return response()->json($requests);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading deleted requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dismissed requests
     */
    public function getDismissedRequests($patientId = null)
    {
        try {
            $query = LabServiceRequest::whereNotNull('dismissed_at')
                ->with(['service', 'doctor', 'patient.user']);

            if ($patientId) {
                $query->where('patient_id', $patientId);
            }

            $requests = $query->orderBy('dismissed_at', 'desc')->get();

            return response()->json($requests);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading dismissed requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get audit logs for a request or patient
     */
    public function getAuditLogs(Request $request)
    {
        try {
            $query = \App\Models\LabWorkbenchAuditLog::with(['user', 'labServiceRequest.service']);

            if ($request->has('lab_service_request_id')) {
                $query->where('lab_service_request_id', $request->lab_service_request_id);
            }

            if ($request->has('patient_id')) {
                $query->whereHas('labServiceRequest', function ($q) use ($request) {
                    $q->where('patient_id', $request->patient_id);
                });
            }

            if ($request->has('action')) {
                $query->where('action', $request->action);
            }

            if ($request->has('from_date')) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }

            if ($request->has('to_date')) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }

            $logs = $query->orderBy('created_at', 'desc')->paginate(50);

            return response()->json($logs);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading audit logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Log audit trail
     */
    private function logAudit($labServiceRequestId, $action, $description = null, $oldValues = null, $newValues = null)
    {
        try {
            \App\Models\LabWorkbenchAuditLog::create([
                'lab_service_request_id' => $labServiceRequestId,
                'user_id' => Auth::id(),
                'action' => $action,
                'description' => $description,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Audit log error: ' . $e->getMessage());
        }
    }

    /**
     * Store a new lab request from workbench
     */
    public function storeLabRequest(Request $request)
    {
        try {
            $request->validate([
                'patient_id' => 'required|exists:patients,id',
                'service_ids' => 'required|array',
                'service_ids.*' => 'exists:services,id',
                'clinical_notes' => 'nullable|string',
                'special_instructions' => 'nullable|string',
                'urgency' => 'nullable|in:routine,urgent,stat',
                'priority' => 'nullable|in:normal,high'
            ]);

            DB::beginTransaction();

            $createdRequests = [];
            foreach ($request->service_ids as $index => $serviceId) {
                $labRequest = new LabServiceRequest();
                $labRequest->service_id = $serviceId;
                $labRequest->patient_id = $request->patient_id;
                $labRequest->doctor_id = Auth::id();
                $labRequest->note = $request->clinical_notes[$index] ?? $request->clinical_notes ?? '';
                $labRequest->status = 1; // Billing status
                $labRequest->urgency = $request->urgency ?? 'routine';
                $labRequest->priority = $request->priority ?? 'normal';
                $labRequest->special_instructions = $request->special_instructions ?? '';
                $labRequest->save();

                $createdRequests[] = $labRequest->id;

                // Log audit
                $this->logAudit($labRequest->id, 'create', 'Lab request created from workbench');
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($createdRequests) . ' lab request(s) created successfully',
                'request_ids' => $createdRequests
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error creating lab request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get lab reports with filters for DataTable
     */
    public function getLabReports(Request $request)
    {
        try {
            $query = LabServiceRequest::with([
                'service',
                'patient.user',
                'patient.hmo.scheme',
                'doctor',
                'biller',
                'resultBy'
            ]);

            // Apply filters
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('service_id')) {
                $query->where('service_id', $request->service_id);
            }

            if ($request->filled('doctor_id')) {
                $query->where('doctor_id', $request->doctor_id);
            }

            if ($request->filled('hmo_id')) {
                $query->whereHas('patient', function ($q) use ($request) {
                    $q->where('hmo_id', $request->hmo_id);
                });
            }

            if ($request->filled('patient_search')) {
                $search = $request->patient_search;
                $query->whereHas('patient', function ($q) use ($search) {
                    $q->where('file_no', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('surname', 'like', "%{$search}%")
                                ->orWhere('firstname', 'like', "%{$search}%");
                        });
                });
            }

            return Datatables::of($query)
                ->addIndexColumn()
                ->editColumn('created_at', function ($row) {
                    return $this->formatDateTime($row->created_at);
                })
                ->addColumn('file_no', function ($row) {
                    return $row->patient->file_no ?? 'N/A';
                })
                ->addColumn('patient_name', function ($row) {
                    return $row->patient && $row->patient->user
                        ? $row->patient->user->surname . ' ' . $row->patient->user->firstname
                        : 'N/A';
                })
                ->addColumn('service_name', function ($row) {
                    return $row->service->service_name ?? 'N/A';
                })
                ->addColumn('doctor_name', function ($row) {
                    return $row->doctor ? $row->doctor->surname . ' ' . $row->doctor->firstname : 'N/A';
                })
                ->addColumn('hmo_name', function ($row) {
                    return $row->patient && $row->patient->hmo ? $row->patient->hmo->name : 'N/A';
                })
                ->addColumn('status_badge', function ($row) {
                    $badges = [
                        1 => '<span class="badge badge-warning">Awaiting Billing</span>',
                        2 => '<span class="badge badge-info">Awaiting Sample</span>',
                        3 => '<span class="badge badge-primary">Awaiting Results</span>',
                        4 => '<span class="badge badge-success">Completed</span>'
                    ];
                    return $badges[$row->status] ?? '<span class="badge badge-secondary">Unknown</span>';
                })
                ->addColumn('tat', function ($row) {
                    if ($row->status == 4 && $row->result_date) {
                        $created = Carbon::parse($row->created_at);
                        $completed = Carbon::parse($row->result_date);
                        $hours = $created->diffInHours($completed);
                        return $hours . 'h';
                    }
                    return 'N/A';
                })
                ->addColumn('actions', function ($row) {
                    return '<button class="btn btn-sm btn-info view-request-details" data-id="' . $row->id . '">
                        <i class="mdi mdi-eye"></i>
                    </button>';
                })
                ->rawColumns(['status_badge', 'actions'])
                ->make(true);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while fetching reports.',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Get doctors who have made lab requests
     */
    public function getRequestingDoctors()
    {
        try {
            $doctors = LabServiceRequest::with('doctor')
                ->select('doctor_id')
                ->distinct()
                ->whereNotNull('doctor_id')
                ->get()
                ->pluck('doctor')
                ->filter()
                ->map(function($doctor) {
                    return [
                        'id' => $doctor->id,
                        'name' => $doctor->surname . ' ' . $doctor->firstname
                    ];
                })
                ->sortBy('name')
                ->values();

            return response()->json($doctors);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load doctors',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Get HMOs grouped by scheme for filter dropdown
     */
    public function getHmosForFilter()
    {
        try {
            $hmos = \App\Models\Hmo::with('scheme')
                ->orderBy('name')
                ->get()
                ->groupBy(function($hmo) {
                    return $hmo->scheme ? $hmo->scheme->name : 'Uncategorized';
                })
                ->map(function($group) {
                    return $group->map(function($hmo) {
                        return [
                            'id' => $hmo->id,
                            'name' => $hmo->name
                        ];
                    })->values();
                });

            return response()->json($hmos);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load HMOs',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Get lab services for filter dropdown
     */
    public function getLabServicesForFilter()
    {
        try {
            // Get investigation/lab service category ID from app settings
            $labCategoryId = appsettings()->investigation_service_cat_id ?? null;

            $query = \App\Models\service::orderBy('service_name');

            // Filter by lab/investigation category if configured
            if ($labCategoryId) {
                $query->where('service_cat_id', $labCategoryId);
            }

            $services = $query->get()
                ->map(function($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->service_name
                    ];
                });

            return response()->json($services);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load services',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Get lab statistics for reports dashboard
     */
    public function getLabStatistics(Request $request)
    {
        try {
            // Apply same date/filter logic as reports
            $query = LabServiceRequest::query();

            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Apply other filters if present
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }
            if ($request->has('service_id') && $request->service_id) {
                $query->where('service_id', $request->service_id);
            }
            if ($request->has('doctor_id') && $request->doctor_id) {
                $query->where('doctor_id', $request->doctor_id);
            }

            // Status counts (global, ignoring status filter for the pie chart usually, but here we filter everything based on user selection?
            // Usually charts show decomposition of the current selection. If I select status=Pending, the chart is boring (100% Pending).
            // But let's keep consistency: The base query applies to summary numbers.
            // For charts, we might want to relax the status filter if we want to show distribution,
            // but for "Revenue" and "Total" matching the filter is correct.

            $totalRequests = (clone $query)->count();
            $completed = (clone $query)->where('status', 4)->count();
            $pending = (clone $query)->whereIn('status', [1, 2, 3])->count();

            // Average TAT
             $avgTAT = (clone $query)->where('status', 4)
                ->whereNotNull('result_date')
                ->whereNotNull('created_at')
                ->get()
                ->map(function ($req) {
                    $created = \Carbon\Carbon::parse($req->created_at);
                    $completed = \Carbon\Carbon::parse($req->result_date);
                    return $created->diffInHours($completed);
                })
                ->average();

            // Revenue calculation disabled
            $estimatedRevenue = 0;
                // Let's look at `topServices` logic again.

            // Requests by status (Format for JS: [{status: 1, count: 10}, ...])
            $byStatus = (clone $query)
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->map(function($item) {
                     return [
                         'status' => $item->status,
                         'count' => $item->count
                     ];
                });

            // Monthly trends (last 6 months)
            $monthlyTrends = [];
            for ($i = 5; $i >= 0; $i--) {
                $month = Carbon::now()->subMonths($i);
                // Filters should apply here too?
                // Usually trends ignore the date filter (fixed range) or respect if wider.
                // Let's just use the basic query without date filters for the trend or it will be empty if date range is small.
                // But we must apply other filters (doctor, service).

                $trendQuery = LabServiceRequest::query();
                if ($request->has('doctor_id') && $request->doctor_id) $trendQuery->where('doctor_id', $request->doctor_id);
                // ... apply other non-date filters ...

                $count = $trendQuery->whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->count();

                $monthlyTrends[] = [
                    'month' => $month->format('M Y'),
                    'count' => $count
                ];
            }

            // Top services
            $topServices = LabServiceRequest::with('service')
                ->select('service_id', DB::raw('count(*) as total'))
                ->groupBy('service_id')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'name' => $item->service->service_name ?? 'N/A', // JS expects 'name'
                        'count' => $item->total,
                        'revenue' => 0 // Placeholder
                    ];
                });

            // Top doctors
            $topDoctors = LabServiceRequest::with('doctor')
                ->select('doctor_id', DB::raw('count(*) as total'))
                ->whereNotNull('doctor_id')
                ->groupBy('doctor_id')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'doctor' => $item->doctor, // Pass full object for JS ({firstname, surname})
                        'count' => $item->total,
                        'revenue' => 0 // Placeholder
                    ];
                });

            return response()->json([
                'summary' => [
                    'total_requests' => $totalRequests,
                    'completed_requests' => $completed,
                    'pending_requests' => $pending,
                    'estimated_revenue' => 0,
                    'avg_tat' => $avgTAT ? round($avgTAT) : 0
                ],
                'by_status' => $byStatus,
                'monthly_trends' => $monthlyTrends,
                'top_services' => $topServices,
                'top_doctors' => $topDoctors
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while fetching statistics.',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }
}
