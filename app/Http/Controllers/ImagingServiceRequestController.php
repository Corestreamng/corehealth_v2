<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ImagingServiceRequest;
use App\Models\Encounter;
use App\Models\service;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Auth;
use App\Models\ProductOrServiceRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImagingServiceRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (request('history') == 1) {
            return view('admin.imaging_service_requests.history');
        } else {
            return view('admin.imaging_service_requests.index');
        }
    }

    /**
     * Save result of imaging request
     */
    public function saveResult(Request $request)
    {
        try {
            $request->validate([
                'imaging_res_template_submited' => 'required|string',
                'imaging_res_entry_id' => 'required',
                'imaging_res_template_version' => 'required|in:1,2',
                'imaging_res_template_data' => 'nullable|string',
                'result_attachments.*' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx'
            ]);

            $imagingRequest = ImagingServiceRequest::findOrFail($request->imaging_res_entry_id);
            $isEdit = $request->imaging_res_is_edit == '1';
            $templateVersion = $request->imaging_res_template_version;

            // If this is an edit, check if we're within the edit time window
            if ($isEdit && $imagingRequest->result_date) {
                $resultDate = Carbon::parse($imagingRequest->result_date);
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
            $resultHtml = $request->imaging_res_template_submited;
            $resultData = null;

            if ($templateVersion == '2' && $request->imaging_res_template_data) {
                // V2 Template: Store structured data and generate HTML for display
                $structuredData = json_decode($request->imaging_res_template_data, true);

                if ($structuredData) {
                    // Get the service template for generating HTML
                    $service = \App\Models\service::find($imagingRequest->service_id);
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
                $resultHtml = str_replace('contenteditable="true"', 'contenteditable="false"', $resultHtml);
                $resultHtml = str_replace("contenteditable='true'", "contenteditable='false'", $resultHtml);
                $resultHtml = str_replace('contenteditable = "true"', 'contenteditable="false"', $resultHtml);
                $resultHtml = str_replace("contenteditable ='true'", "contenteditable='false'", $resultHtml);
                $resultHtml = str_replace('contenteditable= "true"', 'contenteditable="false"', $resultHtml);
                $resultHtml = str_replace(' black', ' gray', $resultHtml);
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
                'status' => 4
            ];

            if (!$isEdit) {
                $updateData['result_date'] = date('Y-m-d H:i:s');
                $updateData['result_by'] = Auth::id();
            }

            $req = ImagingServiceRequest::where('id', $request->imaging_res_entry_id)->update($updateData);
            DB::commit();

            $message = $isEdit ? "Results Updated Successfully" : "Results Saved Successfully";
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
     * Bill selected service requests
     */
    public function bill(Request $request)
    {
        try {
            $request->validate([
                'consult_imaging_note' => 'nullable|array|required_with:addedImagingBillRows',
                'addedImagingBillRows' => 'nullable|array|required_with:consult_imaging_note',
                'selectedImagingBillRows' => 'array',
                'patient_user_id' => 'required',
                'patient_id' => 'required'
            ]);

            if (isset($request->dismiss_imaging_bill) && isset($request->selectedImagingBillRows)) {
                DB::beginTransaction();
                for ($i = 0; $i < count($request->selectedImagingBillRows); $i++) {
                    ImagingServiceRequest::where('id', $request->selectedImagingBillRows[$i])->update([
                        'status' => 0
                    ]);
                }
                DB::commit();
                return redirect()->back()->with(['message' => "Service Requests Dismissed Successfully", 'message_type' => 'success']);
            } else {
                DB::beginTransaction();
                if (isset($request->selectedImagingBillRows)) {
                    for ($i = 0; $i < count($request->selectedImagingBillRows); $i++) {
                        $prod_id = ImagingServiceRequest::where('id', $request->selectedImagingBillRows[$i])->first()->service->id;
                        $bill_req = new ProductOrServiceRequest;
                        $bill_req->user_id = $request->patient_user_id;
                        $bill_req->staff_user_id = Auth::id();
                        $bill_req->service_id = $prod_id;
                        $bill_req->save();

                        ImagingServiceRequest::where('id', $request->selectedImagingBillRows[$i])->update([
                            'status' => 2,
                            'billed_by' => Auth::id(),
                            'billed_date' => date('Y-m-d H:i:s'),
                            'service_request_id' => $bill_req->id,
                        ]);
                    }
                }
                if (isset($request->addedImagingBillRows)) {
                    for ($i = 0; $i < count($request->addedImagingBillRows); $i++) {
                        $bill_req = new ProductOrServiceRequest;
                        $bill_req->user_id = $request->patient_user_id;
                        $bill_req->staff_user_id = Auth::id();
                        $bill_req->service_id = $request->addedImagingBillRows[$i];
                        $bill_req->save();

                        $imaging = new ImagingServiceRequest();
                        $imaging->service_id = $request->addedImagingBillRows[$i];
                        $imaging->note = $request->consult_imaging_note[$i];
                        $imaging->billed_by = Auth::id();
                        $imaging->billed_date = date('Y-m-d H:i:s');
                        $imaging->patient_id = $request->patient_id;
                        $imaging->doctor_id = Auth::id();
                        $imaging->service_request_id = $bill_req->id;
                        $imaging->status = 2;
                        $imaging->save();
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

    public function imagingResList($patient_id)
    {
        $his = ImagingServiceRequest::with(['service', 'encounter', 'patient', 'productOrServiceRequest', 'doctor', 'biller'])
            ->where('status', '=', 2)->where('patient_id', $patient_id)->orderBy('created_at', 'DESC')->get();

        return Datatables::of($his)
            ->addIndexColumn()
            ->addColumn('select', function ($h) {
                $str = "
                    <button type='button' class='btn btn-primary' onclick='setImagingResTempInModal(this)' data-service-name = '" . $h?->service->service_name . "' data-template = '" . htmlspecialchars($h?->service->template) . "' data-id='$h?->id'>
                        Enter Result
                    </button>";
                return $str;
            })
            ->editColumn('created_at', function ($h) {
                $str = "<small>";
                $str .= "<b >Requested by: </b>" . ((isset($h?->doctor_id)  && $h?->doctor_id != null) ? (userfullname($h?->doctor_id) . ' (' . date('h:i a D M j, Y', strtotime($h?->created_at)) . ')') : "<span class='badge badge-secondary'>N/A</span>");
                $str .= "<br><br><b >Last Updated On:</b> " . date('h:i a D M j, Y', strtotime($h?->updated_at));
                $str .= "<br><br><b >Billed by:</b> " . ((isset($h?->billed_by) && $h?->billed_by != null) ? (userfullname($h?->billed_by) . ' (' . date('h:i a D M j, Y', strtotime($h?->billed_date)) . ')') : "<span class='badge badge-secondary'>Not billed</span>");
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

    public function imagingQueueList(Request $request)
    {
        try {
            // Validate start and end dates
            $startDate = Carbon::parse($request->input('start_date'));
            $endDate = Carbon::parse($request->input('end_date'));

            // Build query with eager loading and filters
            $query = ImagingServiceRequest::with([
                'service',
                'encounter',
                'patient.user',
                'patient.hmo',
                'productOrServiceRequest',
                'doctor',
                'biller'
            ])
                ->whereIn('status', [1, 2]);

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

                    $url = route('patient.show', [$request->patient->id, 'section' => 'imagingCardBody']);
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
            Log::error('Imaging Service Request Error: ' . $e->getMessage(), [
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

    public function imagingHistoryList(Request $request)
    {
        // Base query to fetch imaging service requests with status 4
        $query = ImagingServiceRequest::with([
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
                $url = route('patient.show', [$h?->patient->id, 'section' => 'imagingCardBody']);
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

                $view_url = route('imaging-requests.show', $his?->id);
                $str .= "<br><a href='$view_url' class = 'btn btn-primary btn-sm' target='_blank'><i class='fa fa-print'></i> Print</a>";
                return $str;
            })
            ->rawColumns(['created_at', 'result', 'select', 'patient_id'])
            ->make(true);
    }

    public function imagingBillList($patient_id)
    {
        $his = ImagingServiceRequest::with(['service', 'encounter', 'patient', 'productOrServiceRequest', 'doctor', 'biller'])
            ->where('status', '=', 1)->where('patient_id', $patient_id)->orderBy('created_at', 'DESC')->get();

        return DataTables::of($his)
            ->addIndexColumn()
            ->addColumn('select', function ($h) {
                $str = "<input type='checkbox' name='selectedImagingBillRows[]' onclick='checkImagingBillRow(this)' data-price = '" . $h->service->price->sale_price . "' value='$h->id' class='form-control'> ";
                return $str;
            })
            ->editColumn('created_at', function ($h) {
                $str = '<small>';
                $str .= '<b >Requested by: </b>' . ((isset($h->doctor_id) && $h->doctor_id != null) ? (userfullname($h->doctor_id) . ' (' . date('h:i a D M j, Y', strtotime($h->created_at)) . ')') : "<span class='badge badge-secondary'>N/A</span>");
                $str .= '<br><br><b >Last Updated On:</b> ' . date('h:i a D M j, Y', strtotime($h->updated_at));
                $str .= '<br><br><b >Request Note:</b> ' . ((isset($h->note) && $h->note != null) ? ($h->note) : "<span class='badge badge-secondary'>N/A</span><br>");
                $str .= '</small>';
                return $str;
            })
            ->editColumn('result', function ($his) {
                $str = "<span class = 'badge badge-success'>" . (($his->service) ? $his->service->service_name : 'N/A') . '</span><hr>';
                $str .= $his->result ?? 'N/A';
                return $str;
            })
            ->rawColumns(['created_at', 'result', 'select'])
            ->make(true);
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

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $req = ImagingServiceRequest::where('id', $id)->first();
        return view('admin.imaging_service_requests.show', ['req' => $req]);
    }
}
