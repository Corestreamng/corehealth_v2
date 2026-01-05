<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Patient;
use App\Models\LabServiceRequest;
use App\Models\ProductOrServiceRequest;
use App\Models\VitalSign;
use App\Models\Encounter;
use App\Models\ProductRequest;
use App\Models\service;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
                'age' => $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->age : 'N/A',
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

        return response()->json([
            'billing' => $billingCount,
            'sample' => $sampleCount,
            'results' => $resultCount,
            'total' => $billingCount + $sampleCount + $resultCount,
        ]);
    }

    /**
     * Get patient's pending requests
     */
    public function getPatientRequests($patientId)
    {
        $patient = Patient::with('user')->findOrFail($patientId);

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

        return response()->json([
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->user->surname . ' ' . $patient->user->firstname,
                'file_no' => $patient->file_no,
                'age' => $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->age : 'N/A',
                'gender' => $patient->gender ?? 'N/A',
                'blood_type' => $patient->blood_type ?? 'N/A',
                'phone' => $patient->user->phone ?? 'N/A',
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

        $encounters = Encounter::with(['doctor'])
            ->where('patient_id', $patientId)
            ->whereNotNull('notes')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $notes = $encounters->map(function ($encounter) {
            return [
                'id' => $encounter->id,
                'date' => $encounter->created_at->format('M d, Y - h:i A'),
                'doctor' => $encounter->doctor ? $encounter->doctor->firstname . ' ' . $encounter->doctor->surname : 'N/A',
                'diagnosis' => $encounter->diagnosis ?? 'N/A',
                'notes' => $encounter->notes,
                'notes_preview' => \Illuminate\Support\Str::limit(strip_tags($encounter->notes), 150),
            ];
        });

        return response()->json($notes);
    }

    /**
     * Get patient's recent medications
     */
    public function getPatientMedications($patientId, Request $request)
    {
        $limit = $request->get('limit', 20);
        $status = $request->get('status', 'all'); // active, all, stopped

        $query = ProductRequest::with(['product', 'encounter.doctor'])
            ->where('patient_id', $patientId)
            ->where('request_type', 'prescription')
            ->orderBy('created_at', 'desc');

        if ($status === 'active') {
            $query->whereNull('stopped_at');
        } elseif ($status === 'stopped') {
            $query->whereNotNull('stopped_at');
        }

        $medications = $query->limit($limit)->get();

        $result = $medications->map(function ($med) {
            return [
                'id' => $med->id,
                'drug_name' => $med->product ? $med->product->product_name : 'N/A',
                'dosage' => $med->dose ?? 'N/A',
                'frequency' => $med->frequency ?? 'N/A',
                'status' => $med->stopped_at ? 'stopped' : 'active',
                'started' => $med->created_at->format('M d, Y'),
                'stopped' => $med->stopped_at ? \Carbon\Carbon::parse($med->stopped_at)->format('M d, Y') : null,
                'doctor' => $med->encounter && $med->encounter->doctor ? $med->encounter->doctor->firstname . ' ' . $med->encounter->doctor->surname : 'N/A',
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
                $billReq->save();

                // Update lab request status to billed (2)
                $labRequest->update([
                    'status' => 2,
                    'billed_by' => Auth::id(),
                    'billed_date' => now(),
                    'service_request_id' => $billReq->id,
                ]);
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

                // Update lab request status to sample taken (3)
                $labRequest->update([
                    'status' => 3,
                    'sample_taken_by' => Auth::id(),
                    'sample_date' => now(),
                    'sample_taken' => true
                ]);
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
}
