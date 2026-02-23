<?php

namespace App\Http\Controllers;

use App\Helpers\HmoHelper;

use Illuminate\Http\Request;
use App\Models\LabServiceRequest;
use App\Models\AdmissionRequest;
use App\Models\Clinic;
use App\Models\Encounter;
use App\Models\DoctorQueue;
use Yajra\DataTables\DataTables;
use App\Models\User;
use App\Models\Staff;
use App\Models\Hmo;
use Illuminate\Support\Facades\Auth;
use App\Models\patient;
use App\Models\Product;
use App\Models\ProductOrServiceRequest;
use App\Models\service;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LabServiceRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (request('history') == 1) {
            return view('admin.lab_service_requests.history');
        } else {
            return view('admin.lab_service_requests.index');
        }
    }

    /**
     * save result of lab request
     */
    public function saveResult(Request $request)
    {
        // dd($request->all());
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
                    return redirect()->back()->with([
                        'message' => "Edit window has expired. Results can only be edited within {$editDuration} minutes of submission.",
                        'message_type' => 'error'
                    ]);
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

            $requiresApproval = appsettings('lab_results_require_approval');

            if ($requiresApproval && !$isEdit) {
                // Save to pending columns — result not visible until approved
                $updateData = [
                    'pending_result' => $resultHtml,
                    'pending_result_data' => $resultData,
                    'pending_attachments' => !empty($allAttachments) ? json_encode($allAttachments) : null,
                    'status' => 5, // Pending Approval
                    'result_date' => date('Y-m-d H:i:s'),
                    'result_by' => Auth::id(),
                    'rejected_by' => null,
                    'rejected_at' => null,
                    'rejection_reason' => null,
                ];
            } else {
                // Original behavior — save directly to live columns
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

                // If re-submitting after rejection, clear pending columns
                if ($labRequest->status == 6) {
                    $updateData['pending_result'] = null;
                    $updateData['pending_result_data'] = null;
                    $updateData['pending_attachments'] = null;
                    $updateData['rejected_by'] = null;
                    $updateData['rejected_at'] = null;
                    $updateData['rejection_reason'] = null;
                }
            }

            $req = LabServiceRequest::where('id', $request->invest_res_entry_id)->update($updateData);
            DB::commit();

            $message = $isEdit ? "Results Updated Successfully" : ($requiresApproval ? "Results saved — pending approval" : "Results Saved Successfully");
            return redirect()->back()->with(['message' => $message, 'message_type' => 'success']);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage() . ' line' . $e->getLine());
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
     * bill selected service requets
     */

    public function bill(Request $request)
    {
        try {
            $request->validate([
                'consult_invest_note' => 'nullable|array|required_with:addedInvestBillRows',
                'addedInvestBillRows' => 'nullable|array|required_with:consult_invest_note',
                'selectedInvestBillRows' => 'array',
                'patient_user_id' => 'required',
                'patient_id' => 'required'
            ]);

            if (isset($request->dismiss_invest_bill) && isset($request->selectedInvestBillRows)) {
                DB::beginTransaction();
                for ($i = 0; $i < count($request->selectedInvestBillRows); $i++) {
                    LabServiceRequest::where('id', $request->selectedInvestBillRows[$i])->update([
                        'status' => 0
                    ]);
                }
                DB::commit();
                return redirect()->back()->with(['message' => "Service Requests Dismissed Successfully", 'message_type' => 'success']);
            } else {
                DB::beginTransaction();
                if (isset($request->selectedInvestBillRows)) {
                    for ($i = 0; $i < count($request->selectedInvestBillRows); $i++) {
                        $lab_req = LabServiceRequest::where('id', $request->selectedInvestBillRows[$i])->first();
                        $prod_id = $lab_req->service->id;
                        $bill_req = new ProductOrServiceRequest;
                        $bill_req->user_id = $request->patient_user_id;
                        $bill_req->staff_user_id = Auth::id();
                        $bill_req->service_id = $prod_id;

                        // Apply HMO tariff if patient has HMO
                        try {
                            $patient = patient::where('user_id', $request->patient_user_id)->first();
                            if ($patient) {
                                $hmoData = HmoHelper::applyHmoTariff($patient->id, null, $prod_id);
                                if ($hmoData) {
                                    $bill_req->payable_amount = $hmoData['payable_amount'];
                                    $bill_req->claims_amount = $hmoData['claims_amount'];
                                    $bill_req->coverage_mode = $hmoData['coverage_mode'];
                                    $bill_req->validation_status = $hmoData['validation_status'];
                                }
                            }
                        } catch (\Exception $e) {
                            DB::rollBack();
                            return redirect()->back()->withErrors(['error' => 'HMO Tariff Error: ' . $e->getMessage()])->withInput();
                        }

                        $bill_req->save();


                        LabServiceRequest::where('id', $request->selectedInvestBillRows[$i])->update([
                            'status' => 2,
                            'billed_by' => Auth::id(),
                            'billed_date' => date('Y-m-d H:i:s'),
                            'service_request_id' => $bill_req->id,
                        ]);
                    }
                }
                if (isset($request->addedInvestBillRows)) {
                    for ($i = 0; $i < count($request->addedInvestBillRows); $i++) {
                        $bill_req = new ProductOrServiceRequest;
                        $bill_req->user_id = $request->patient_user_id;
                        $bill_req->staff_user_id = Auth::id();
                        $bill_req->service_id = $request->addedInvestBillRows[$i];

                        // Apply HMO tariff if patient has HMO
                        try {
                            $patient = patient::where('user_id', $request->patient_user_id)->first();
                            if ($patient) {
                                $hmoData = HmoHelper::applyHmoTariff($patient->id, null, $request->addedInvestBillRows[$i]);
                                if ($hmoData) {
                                    $bill_req->payable_amount = $hmoData['payable_amount'];
                                    $bill_req->claims_amount = $hmoData['claims_amount'];
                                    $bill_req->coverage_mode = $hmoData['coverage_mode'];
                                    $bill_req->validation_status = $hmoData['validation_status'];
                                }
                            }
                        } catch (\Exception $e) {
                            DB::rollBack();
                            return redirect()->back()->withErrors(['error' => 'HMO Tariff Error: ' . $e->getMessage()])->withInput();
                        }

                        $bill_req->save();

                        $inves = new LabServiceRequest();
                        $inves->service_id = $request->addedInvestBillRows[$i];
                        $inves->note = $request->consult_invest_note[$i];
                        // $inves->encounter_id = $encounter->id;
                        $inves->billed_by = Auth::id();
                        $inves->billed_date = date('Y-m-d H:i:s');
                        $inves->patient_id = $request->patient_id;
                        $inves->doctor_id = Auth::id();
                        $inves->service_request_id = $bill_req->id;
                        $inves->status = 2;
                        $inves->save();
                    }
                }
                DB::commit();
                return redirect()->back()->with(['message' => "Service Requests Billed Successfully", 'message_type' => 'success']);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage() . 'line' . $e->getLine());
        }
    }

    public function takeSample(Request $request)
    {
        try {
            $request->validate([
                'selectedInvestSampleRows' => 'array',
                'patient_user_id' => 'required',
                'patient_id' => 'required'
            ]);

            if (isset($request->dismiss_invest_sample) && isset($request->selectedInvestSampleRows)) {
                DB::beginTransaction();
                for ($i = 0; $i < count($request->selectedInvestSampleRows); $i++) {
                    LabServiceRequest::where('id', $request->selectedInvestSampleRows[$i])->update([
                        'status' => 0
                    ]);
                }
                DB::commit();
                return redirect()->back()->with(['message' => "Service Requests Dismissed Successfully", 'message_type' => 'success']);
            } else {
                DB::beginTransaction();
                if (isset($request->selectedInvestSampleRows)) {
                    for ($i = 0; $i < count($request->selectedInvestSampleRows); $i++) {
                        $labRequest = LabServiceRequest::with('productOrServiceRequest')->findOrFail($request->selectedInvestSampleRows[$i]);

                        // Check HMO access control
                        if ($labRequest->productOrServiceRequest) {
                            if (!\App\Helpers\HmoHelper::canPatientAccessService($labRequest->productOrServiceRequest)) {
                                DB::rollBack();
                                return redirect()->back()->with([
                                    'message' => 'Service requires HMO approval for Request ID: ' . $labRequest->id . '. Please contact HMO executive for validation.',
                                    'message_type' => 'error'
                                ]);
                            }
                        }

                        LabServiceRequest::where('id', $request->selectedInvestSampleRows[$i])->update([
                            'status' => 3,
                            'sample_taken_by' => Auth::id(),
                            'sample_date' => date('Y-m-d H:i:s'),
                            'sample_taken' => true
                        ]);
                    }
                }

                DB::commit();
                return redirect()->back()->with(['message' => "Service Requests Sample Taken Successfully", 'message_type' => 'success']);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withMessage("An error occurred " . $e->getMessage() . 'line' . $e->getLine());
        }
    }

    public function investResList($patient_id)
    {
        $his = LabServiceRequest::with(['service', 'encounter', 'patient', 'productOrServiceRequest', 'doctor', 'biller'])
            ->where('status', '=', 3)->where('patient_id', $patient_id)->orderBy('created_at', 'DESC')->get();
        //dd($pc);
        return Datatables::of($his)
            ->addIndexColumn()
            ->addColumn('select', function ($h) {
                $hasV2Template = !empty($h?->service->result_template_v2);
                $templateV2Json = $hasV2Template ? htmlspecialchars(json_encode($h?->service->result_template_v2), ENT_QUOTES, 'UTF-8') : '';

                $str = "
                    <button type='button' class='btn btn-primary' onclick='setResTempInModal(this)'
                        data-service-name = '" . $h?->service->service_name . "'
                        data-template = '" . htmlspecialchars($h?->service->template) . "'
                        data-template-v2 = '" . $templateV2Json . "'
                        data-id='$h?->id'>
                        Enter Result
                    </button>";
                return $str;
            })
            ->editColumn('created_at', function ($h) {
                $str = "<small>";
                $str .= "<b >Requested by: </b>" . ((isset($h?->doctor_id)  && $h?->doctor_id != null) ? (userfullname($h?->doctor_id) . ' (' . date('h:i a D M j, Y', strtotime($h?->created_at)) . ')') : "<span class='badge badge-secondary'>N/A</span>");
                $str .= "<br><br><b >Last Updated On:</b> " . date('h:i a D M j, Y', strtotime($h?->updated_at));
                $str .= "<br><br><b >Billed by:</b> " . ((isset($h?->billed_by) && $h?->billed_by != null) ? (userfullname($h?->billed_by) . ' (' . date('h:i a D M j, Y', strtotime($h?->billed_date)) . ')') : "<span class='badge badge-secondary'>Not billed</span>");
                $str .= "<br><br><b >Sample taken by:</b> " . ((isset($h?->sample_taken_by) && $h?->sample_taken_by != null) ? (userfullname($h?->sample_taken_by) . ' (' . date('h:i a D M j, Y', strtotime($h?->sample_date)) . ')') : "<span class='badge badge-secondary'>Not taken</span>");
                $str .= "<br><br><b >Results by:</b> " . ((isset($h?->result_by) && $h?->result_by != null) ? (userfullname($h?->result_by) . ' (' . date('h:i a D M j, Y', strtotime($h?->result_date)) . ')') : "<span class='badge badge-secondary'>Awaiting Results</span>");
                $str .= "<br><br><b >Request Note:</b> " . ((isset($h?->note) && $h?->note != null) ? ($h?->note) : "<span class='badge badge-secondary'>N/A</span><br>");
                $str .= "</small>";
                return $str;
            })
            ->editColumn('result', function ($his) {
                $str = "<span class = 'badge badge-success'>" . (($his?->service) ? $his?->service->service_name : "N/A") . "</span><hr>";
                $str .= $his?->result ?? 'N/A';

                // Add attachments if any
                if ($his->attachments) {
                    $attachments = is_string($his->attachments) ? json_decode($his->attachments, true) : $his->attachments;
                    if (!empty($attachments)) {
                        $str .= "<hr><b><i class='mdi mdi-paperclip'></i> Attachments:</b><br>";
                        foreach ($attachments as $attachment) {
                            $url = asset('storage/' . $attachment['path']);
                            $icon = $this->getFileIcon($attachment['type']);
                            $str .= "<a href='{$url}' target='_blank' class='badge badge-info mr-1'>{$icon} {$attachment['name']}</a> ";
                        }
                    }
                }
                return $str;
            })
            ->rawColumns(['created_at', 'result', 'select'])
            ->make(true);
    }

    public function investQueueList(Request $request)
    {
        try {
            // Validate start and end dates
            $startDate = Carbon::parse($request->input('start_date'));
            $endDate = Carbon::parse($request->input('end_date'));

            // Build query with eager loading and filters
            $query = LabServiceRequest::with([
                'service',
                'encounter',
                'patient.user',
                'patient.hmo',
                'productOrServiceRequest',
                'doctor',
                'biller'
            ])
                ->whereIn('status', [1, 2, 3]);

            // Apply date range filter if provided
            if ($startDate && $endDate) {
                $query->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()]);
            }

            $requests = $query->orderByDesc('created_at')->get();

            // Generate DataTable response
            return Datatables::of($requests)
                ->addIndexColumn()
                ->addColumn('select', function ($request) {
                    if (!$request->patient) {
                        return '<span class="badge badge-danger">Invalid Patient Data</span>';
                    }

                    $url = route('patient.show', [$request->patient->id, 'section' => 'investigationsCardBody']);
                    return "<a class='btn btn-primary' href='{$url}'>view</a>";
                })
                ->editColumn('patient_id', function ($request) {
                    if (!$request->patient) {
                        return '<span class="badge badge-danger">Patient data not found</span>';
                    }

                    $str = "<small>";
                    $str .= "<b>Patient: </b>" . (($request->patient->user) ? userfullname($request->patient->user->id) : "N/A");
                    $str .= "<br><br><b>File No: </b>" . ($request->patient->file_no ?? "N/A");
                    $str .= "<br><br><b>Insurance/HMO: </b>" . (($request->patient->hmo) ? $request->patient->hmo->name : "N/A");
                    $str .= "<br><br><b>HMO Number: </b>" . ($request->patient->hmo_no ?? "N/A");
                    $str .= "</small>";

                    return $str;
                })
                ->editColumn('created_at', function ($request) {
                    $str = "<small>";

                    $str .= "<b>Requested by: </b>" .
                        (isset($request->doctor_id) ?
                            userfullname($request->doctor_id) . ' (' . $this->formatDateTime($request->created_at) . ')' :
                            "<span class='badge badge-secondary'>N/A</span>");

                    $str .= "<br><br><b>Last Updated On: </b>" . $this->formatDateTime($request->updated_at);

                    $str .= "<br><br><b>Billed by: </b>" .
                        (isset($request->billed_by) ?
                            userfullname($request->billed_by) . ' (' . $this->formatDateTime($request->billed_date) . ')' :
                            "<span class='badge badge-secondary'>Not billed</span>");

                    $str .= "<br><br><b>Sample taken by: </b>" .
                        (isset($request->sample_taken_by) ?
                            userfullname($request->sample_taken_by) . ' (' . $this->formatDateTime($request->sample_date) . ')' :
                            "<span class='badge badge-secondary'>Not taken</span>");

                    $str .= "<br><br><b>Results by: </b>" .
                        (isset($request->result_by) ?
                            userfullname($request->result_by) . ' (' . $this->formatDateTime($request->result_date) . ')' :
                            "<span class='badge badge-secondary'>Awaiting Results</span>");

                    $str .= "<br><br><b>Request Note: </b>" .
                        (isset($request->note) && $request->note != null ?
                            $request->note :
                            "<span class='badge badge-secondary'>N/A</span>");

                    $str .= "</small>";
                    return $str;
                })
                ->editColumn('result', function ($request) {
                    $str = "<span class='badge badge-success'>" .
                        (optional($request->service)->service_name ?? "N/A") .
                        "</span><hr>";
                    $str .= $request->result ?? 'N/A';

                    // Add attachments if any
                    if ($request->attachments) {
                        $attachments = is_string($request->attachments) ? json_decode($request->attachments, true) : $request->attachments;
                        if (!empty($attachments)) {
                            $str .= "<hr><b><i class='mdi mdi-paperclip'></i> Attachments:</b><br>";
                            foreach ($attachments as $attachment) {
                                $url = asset('storage/' . $attachment['path']);
                                $icon = $this->getFileIcon($attachment['type']);
                                $str .= "<a href='{$url}' target='_blank' class='badge badge-info mr-1'>{$icon} {$attachment['name']}</a> ";
                            }
                        }
                    }
                    return $str;
                })
                ->rawColumns(['created_at', 'result', 'select', 'patient_id'])
                ->make(true);
        } catch (\Exception $e) {
            Log::error('Lab Service Request Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'An error occurred while processing the request.',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }


    /**
     * Format datetime to consistent format
     *
     * @param string|null $datetime
     * @return string
     */
    private function formatDateTime(?string $datetime): string
    {
        return $datetime
            ? date('h:i a D M j, Y', strtotime($datetime))
            : 'N/A';
    }

    /**
     * Get file icon based on extension
     *
     * @param string $extension
     * @return string
     */
    private function getFileIcon(string $extension): string
    {
        $icons = [
            'pdf' => '<i class="mdi mdi-file-pdf"></i>',
            'doc' => '<i class="mdi mdi-file-word"></i>',
            'docx' => '<i class="mdi mdi-file-word"></i>',
            'jpg' => '<i class="mdi mdi-file-image"></i>',
            'jpeg' => '<i class="mdi mdi-file-image"></i>',
            'png' => '<i class="mdi mdi-file-image"></i>',
        ];
        return $icons[$extension] ?? '<i class="mdi mdi-file"></i>';
    }


    public function investHistoryList(Request $request)
    {
        // Base query to fetch lab service requests with status 4
        $query = LabServiceRequest::with([
            'service',
            'encounter',
            'patient',
            'productOrServiceRequest',
            'doctor',
            'biller'
        ])->where('status', '=', 4);

        // Apply date filters if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        // Fetch filtered or unfiltered results
        $his = $query->orderBy('created_at', 'DESC')->get();

        return Datatables::of($his)
            ->addIndexColumn()
            ->addColumn('select', function ($h) {
                $url = route('patient.show', [$h?->patient->id, 'section' => 'investigationsCardBody']);
                $str = "
                <a class='btn btn-primary' href='$url'>
                    view
                </a>";
                return $str;
            })
            ->editColumn('patient_id', function ($h) {
                $str = "<small>";
                $str .= "<b >Patient </b> :" . (($h?->patient->user) ? userfullname($h?->patient->user->id) : "N/A");
                $str .= "<br><br><b >File No </b> : " . (($h?->patient) ? $h?->patient->file_no : "N/A");
                $str .= "<br><br><b >Insurance/HMO :</b> : " . (($h?->patient->hmo) ? $h?->patient->hmo->name : "N/A");
                $str .= "<br><br><b >HMO Number :</b> : " . (($h?->patient->hmo_no) ? $h?->patient->hmo_no : "N/A");
                $str .= "</small>";
                return $str;
            })
            ->editColumn('created_at', function ($h) {
                $str = "<small>";
                $str .= "<b >Requested by: </b>" . ((isset($h?->doctor_id)  && $h?->doctor_id != null) ? (userfullname($h?->doctor_id) . ' (' . date('h:i a D M j, Y', strtotime($h?->created_at)) . ')') : "<span class='badge badge-secondary'>N/A</span>");
                $str .= "<br><br><b >Last Updated On:</b> " . date('h:i a D M j, Y', strtotime($h?->updated_at));
                $str .= "<br><br><b >Billed by:</b> " . ((isset($h?->billed_by) && $h?->billed_by != null) ? (userfullname($h?->billed_by) . ' (' . date('h:i a D M j, Y', strtotime($h?->billed_date)) . ')') : "<span class='badge badge-secondary'>Not billed</span>");
                $str .= "<br><br><b >Sample taken by:</b> " . ((isset($h?->sample_taken_by) && $h?->sample_taken_by != null) ? (userfullname($h?->sample_taken_by) . ' (' . date('h:i a D M j, Y', strtotime($h?->sample_date)) . ')') : "<span class='badge badge-secondary'>Not taken</span>");
                $str .= "<br><br><b >Results by:</b> " . ((isset($h?->result_by) && $h?->result_by != null) ? (userfullname($h?->result_by) . ' (' . date('h:i a D M j, Y', strtotime($h?->result_date)) . ')') : "<span class='badge badge-secondary'>Awaiting Results</span>");
                $str .= "<br><br><b >Request Note:</b> " . ((isset($h?->note) && $h?->note != null) ? ($h?->note) : "<span class='badge badge-secondary'>N/A</span><br>");
                $str .= "</small>";
                return $str;
            })
            ->editColumn('result', function ($his) {
                $str = "<span class = 'badge badge-success'>" . (($his?->service) ? $his?->service->service_name : "N/A") . "</span><hr>";
                $str .= $his?->result ?? 'N/A';

                // Add attachments if any
                if ($his->attachments) {
                    $attachments = is_string($his->attachments) ? json_decode($his->attachments, true) : $his->attachments;
                    if (!empty($attachments)) {
                        $str .= "<hr><b><i class='mdi mdi-paperclip'></i> Attachments:</b><br>";
                        foreach ($attachments as $attachment) {
                            $url = asset('storage/' . $attachment['path']);
                            $icon = $this->getFileIcon($attachment['type']);
                            $str .= "<a href='{$url}' target='_blank' class='badge badge-info mr-1'>{$icon} {$attachment['name']}</a> ";
                        }
                    }
                }

                $view_url = route('service-requests.show', $his?->id);
                $str .= "<br><a href='$view_url' class = 'btn btn-primary btn-sm' target='_blank'><i class='fa fa-print'></i> Print</a>";
                return $str;
            })
            ->rawColumns(['created_at', 'result', 'select', 'patient_id'])
            ->make(true);
    }



    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $req = LabServiceRequest::where('id', $id)->first();

        return view('admin.lab_service_requests.show', ['req' => $req]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
