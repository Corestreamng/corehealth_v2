<?php

namespace App\Http\Controllers;

use App\Models\AdmissionRequest;
use App\Models\Clinic;
use App\Models\DoctorQueue;
use App\Models\Encounter;
use App\Models\Hmo;
use App\Models\ImagingServiceRequest;
use App\Models\LabServiceRequest;
use App\Models\NursingNote;
use App\Models\NursingNoteType;
use App\Models\patient;
use App\Models\ProductOrServiceRequest;
use App\Models\ProductRequest;
use App\Models\ReasonForEncounter;
use App\Models\Staff;
use App\Models\User;
use Carbon\Carbon;
use App\Helpers\HmoHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;

class EncounterController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('admin.doctors.my_queues');
    }

    public function NewEncounterList(Request $request)
    {
        try {
            // Fetch the currently logged-in doctor
            $doc = Staff::where('user_id', Auth::id())->first();

            // Retrieve date range from the request
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            // Build the base query
            $queueQuery = DoctorQueue::where(function ($q) use ($doc) {
                $q->where('clinic_id', $doc->clinic_id)
                    ->orWhere('staff_id', $doc->id);
            })
                ->where('status', 1);

            // Apply date filtering if both dates are provided
            if ($startDate && $endDate) {
                $queueQuery->whereBetween('created_at', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay(),
                ]);
            }

            // Get the filtered results
            $queue = $queueQuery->orderBy('created_at', 'DESC')->get();

            return DataTables::of($queue)
                ->addIndexColumn()
                ->editColumn('fullname', function ($queue) {
                    $patient = patient::find($queue->patient_id);
                    return userfullname($patient->user_id);
                })
                ->editColumn('created_at', function ($note) {
                    return date('h:i a D M j, Y', strtotime($note->created_at));
                })
                ->editColumn('hmo_id', function ($queue) {
                    $patient = patient::find($queue->patient_id);
                    return Hmo::find($patient->hmo_id)->name ?? 'N/A';
                })
                ->editColumn('clinic_id', function ($queue) {
                    $clinic = Clinic::find($queue->clinic_id);
                    return $clinic->name ?? 'N/A';
                })
                ->editColumn('staff_id', function ($queue) use ($doc) {
                    return userfullname($doc->user_id);
                })
                ->addColumn('file_no', function ($queue) {
                    $patient = patient::find($queue->patient_id);
                    return $patient->file_no;
                })
                ->addColumn('view', function ($queue) {
                    $reqEntry = ProductOrServiceRequest::find($queue->request_entry_id);
                    $deliveryCheck = $reqEntry ? HmoHelper::canDeliverService($reqEntry) : ['can_deliver' => true, 'reason' => 'Ready', 'hint' => ''];

                    $url = route(
                        'encounters.create',
                        ['patient_id' => $queue->patient_id, 'req_entry_id' => $queue->request_entry_id, 'queue_id' => $queue->id]
                    );

                    if (!$deliveryCheck['can_deliver']) {
                        $title = e($deliveryCheck['hint'] ?? $deliveryCheck['reason']);
                        return '<button class="btn btn-secondary btn-sm" disabled title="' . $title . '"><i class="fa fa-ban"></i> Encounter</button>';
                    }

                    return '<a href="' . $url . '" class="btn btn-success btn-sm" ><i class="fa fa-street-view"></i> Encounter</a>';
                })
                ->addColumn('delivery_status', function ($queue) {
                    $reqEntry = ProductOrServiceRequest::find($queue->request_entry_id);
                    $deliveryCheck = $reqEntry ? HmoHelper::canDeliverService($reqEntry) : ['can_deliver' => true, 'reason' => 'Ready', 'hint' => ''];

                    $badgeClass = $deliveryCheck['can_deliver'] ? 'bg-success' : 'bg-danger';
                    $label = $deliveryCheck['can_deliver'] ? 'Ready' : $deliveryCheck['reason'];
                    $title = e($deliveryCheck['hint'] ?? '');

                    return '<span class="badge ' . $badgeClass . '" title="' . $title . '">' . e($label) . '</span>';
                })
                ->rawColumns(['fullname', 'view', 'delivery_status'])
                ->make(true);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['exception' => $e]);

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function endOldEncounterReq()
    {
        $currentDateTime = Carbon::now();
        $timeThreshold = $currentDateTime->subHours(appsettings('consultation_cycle_duration', 24));

        $q = DoctorQueue::where('status', 2)
            ->where('created_at', '<', $timeThreshold)->get();
        foreach ($q as $r) {
            $r->update([
                'status' => 3,
            ]);
        }
    }

    public function PrevEncounterList(Request $request)
    {
        try {
            $doc = Staff::where('user_id', Auth::id())->first();

            // Handle case where staff record doesn't exist
            if (!$doc) {
                return DataTables::of(collect([]))->make(true);
            }

            // Get start and end dates from request, fallback to null
            $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
            $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;

            $queueQuery = DoctorQueue::where(function ($q) use ($doc) {
                if ($doc->clinic_id) {
                    $q->where('clinic_id', $doc->clinic_id);
                }
                $q->orWhere('staff_id', $doc->id);
            })
                ->where('status', '>', '2');

            // Apply date range filter if provided
            if ($startDate) {
                $queueQuery->where('created_at', '>=', $startDate->startOfDay());
            }
            if ($endDate) {
                $queueQuery->where('created_at', '<=', $endDate->endOfDay());
            }

            $queue = $queueQuery->orderBy('created_at', 'DESC')->get();

            return DataTables::of($queue)
                ->addIndexColumn()
                ->editColumn('fullname', function ($queue) {
                    $patient = patient::find($queue->patient_id);
                    return userfullname($patient->user_id);
                })
                ->editColumn('created_at', function ($note) {
                    return date('h:i a D M j, Y', strtotime($note->created_at));
                })
                ->editColumn('hmo_id', function ($queue) {
                    $patient = patient::find($queue->patient_id);
                    return Hmo::find($patient->hmo_id)->name ?? 'N/A';
                })
                ->editColumn('clinic_id', function ($queue) {
                    $clinic = Clinic::find($queue->clinic_id);
                    return $clinic->name ?? 'N/A';
                })
                ->editColumn('staff_id', function ($queue) use ($doc) {
                    return userfullname($doc->user_id);
                })
                ->addColumn('file_no', function ($queue) {
                    $patient = patient::find($queue->patient_id);
                    return $patient?->file_no;
                })
                ->addColumn('view', function ($queue) {
                    $url = route('patient.show', $queue->patient_id);
                    return '<a href="' . $url . '" class="btn btn-success btn-sm"><i class="fa fa-street-view"></i> View</a>';
                })
                ->addColumn('delivery_status', function () {
                    return '<span class="badge bg-secondary" title="Completed encounter">Completed</span>';
                })
                ->rawColumns(['fullname', 'view', 'delivery_status'])
                ->make(true);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function ContEncounterList(Request $request)
    {
        try {
            $this->endOldEncounterReq();
            $doc = Staff::where('user_id', Auth::id())->first();

            $timeThreshold = Carbon::now()->subHours(appsettings('consultation_cycle_duration', 24));

            // Get start and end dates from request, fallback to null
            $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
            $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;

            $queueQuery = DoctorQueue::where(function ($q) use ($doc) {
                $q->where('clinic_id', $doc->clinic_id);
                $q->orWhere('staff_id', $doc->id);
            })
                ->where('status', 2)
                ->where('created_at', '>=', $timeThreshold);

            // Apply date range filter if provided
            if ($startDate) {
                $queueQuery->where('created_at', '>=', $startDate->startOfDay());
            }
            if ($endDate) {
                $queueQuery->where('created_at', '<=', $endDate->endOfDay());
            }

            $queue = $queueQuery->orderBy('created_at', 'DESC')->get();

            return DataTables::of($queue)
                ->addIndexColumn()
                ->editColumn('fullname', function ($queue) {
                    $patient = patient::find($queue->patient_id);
                    return userfullname($patient->user_id);
                })
                ->editColumn('created_at', function ($note) {
                    return date('h:i a D M j, Y', strtotime($note->created_at));
                })
                ->editColumn('hmo_id', function ($queue) {
                    $patient = patient::find($queue->patient_id);
                    return Hmo::find($patient->hmo_id)->name ?? 'N/A';
                })
                ->editColumn('clinic_id', function ($queue) {
                    $clinic = Clinic::find($queue->clinic_id);
                    return $clinic->name ?? 'N/A';
                })
                ->editColumn('staff_id', function ($queue) use ($doc) {
                    return userfullname($doc->user_id);
                })
                ->addColumn('file_no', function ($queue) {
                    $patient = patient::find($queue->patient_id);
                    return $patient->file_no;
                })
                ->addColumn('view', function ($queue) {
                    $reqEntry = ProductOrServiceRequest::find($queue->request_entry_id);
                    $deliveryCheck = $reqEntry ? HmoHelper::canDeliverService($reqEntry) : ['can_deliver' => true, 'reason' => 'Ready', 'hint' => ''];

                    $url = route('encounters.create', [
                        'patient_id' => $queue->patient_id,
                        'req_entry_id' => $queue->request_entry_id,
                        'queue_id' => $queue->id
                    ]);

                    if (!$deliveryCheck['can_deliver']) {
                        $title = e($deliveryCheck['hint'] ?? $deliveryCheck['reason']);
                        return '<button class="btn btn-secondary btn-sm" disabled title="' . $title . '"><i class="fa fa-ban"></i> Encounter</button>';
                    }

                    return '<a href="' . $url . '" class="btn btn-success btn-sm"><i class="fa fa-street-view"></i> Encounter</a>';
                })
                ->addColumn('delivery_status', function ($queue) {
                    $reqEntry = ProductOrServiceRequest::find($queue->request_entry_id);
                    $deliveryCheck = $reqEntry ? HmoHelper::canDeliverService($reqEntry) : ['can_deliver' => true, 'reason' => 'Ready', 'hint' => ''];

                    $badgeClass = $deliveryCheck['can_deliver'] ? 'bg-success' : 'bg-danger';
                    $label = $deliveryCheck['can_deliver'] ? 'Ready' : $deliveryCheck['reason'];
                    $title = e($deliveryCheck['hint'] ?? '');

                    return '<span class="badge ' . $badgeClass . '" title="' . $title . '">' . e($label) . '</span>';
                })
                ->rawColumns(['fullname', 'view', 'delivery_status'])
                ->make(true);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function allPrevEncounters()
    {
        return view('admin.encounters.index');
    }

    public function AllprevEncounterList(Request $request)
    {
        try {
            $query = Encounter::query()
                ->when($request->filled(['start_date', 'end_date']), function ($query) use ($request) {
                    return $query->whereBetween('created_at', [
                        $request->start_date . ' 00:00:00',
                        $request->end_date . ' 23:59:59'
                    ]);
                })
                ->orderBy('created_at', 'DESC');

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('fullname', function ($queue) {
                    return ($queue->patient) ? userfullname($queue->patient->user_id) : 'N/A';
                })
                ->editColumn('created_at', function ($note) {
                    return date('h:i a D M j, Y', strtotime($note->created_at));
                })
                ->addColumn('hmo_id', function ($queue) {
                    $patient = Patient::find($queue->patient_id);

                    if (!$patient) return 'N/A';
                    if (!$patient->hmo_id) return 'N/A';

                    $hmo = Hmo::find($patient->hmo_id);
                    return $hmo ? $hmo->name : 'N/A';
                })
                ->addColumn('clinic_id', function ($queue) {
                    $clinic = Clinic::find($queue->clinic_id);
                    return $clinic ? $clinic->name : 'N/A';
                })
                ->editColumn('doctor_id', function ($queue) {
                    return $queue->doctor_id ? userfullname($queue->doctor_id) : 'N/A';
                })
                ->addColumn('file_no', function ($queue) {
                    $patient = Patient::find($queue->patient_id);
                    return $patient ? $patient->file_no : 'N/A';
                })
                ->addColumn('view', function ($queue) {
                    $url = route('patient.show', $queue->patient_id);
                    return '<a href="' . $url . '" class="btn btn-success btn-sm"><i class="fa fa-street-view"></i> View</a>';
                })
                ->rawColumns(['view'])
                ->make(true);
        } catch (\Exception $e) {
            Log::error('Error in AllprevEncounterList: ' . $e->getMessage(), [
                'exception' => $e,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date
            ]);

            return response()->json([
                'error' => 'An error occurred while fetching the data. Please try again.'
            ], 500);
        }
    }

    public function investigationHistoryList($patient_id)
    {
        $his = LabServiceRequest::with(['service', 'encounter', 'patient', 'patient.user', 'productOrServiceRequest', 'doctor', 'biller', 'results_person'])
            ->where('status', '>', 0)->where('patient_id', $patient_id)->orderBy('created_at', 'DESC')->get();

        // dd($pc);
        return DataTables::of($his)
            ->addColumn('info', function ($his) {
                $str = '<div class="card-modern mb-2" style="border-left: 4px solid #0d6efd;">';
                $str .= '<div class="card-body p-3">';

                // Header with service name and status
                $str .= '<div class="d-flex justify-content-between align-items-start mb-3">';
                $str .= "<h6 class='mb-0'><span class='badge bg-success'>" . (($his->service) ? $his->service->service_name : 'N/A') . '</span></h6>';

                // Status badges
                $str .= '<div>';
                $statusBadge = '';
                if ($his->result) {
                    $statusBadge = "<span class='badge bg-info'>Result Available</span>";
                } elseif ($his->sample_taken_by) {
                    $statusBadge = "<span class='badge bg-warning'>Sample Taken</span>";
                } elseif ($his->billed_by) {
                    $statusBadge = "<span class='badge bg-primary'>Billed</span>";
                } else {
                    $statusBadge = "<span class='badge bg-secondary'>Pending</span>";
                }
                $str .= $statusBadge;

                // HMO Coverage Badge
                if ($his->productOrServiceRequest && $his->productOrServiceRequest->coverage_mode) {
                    $coverageClass = $his->productOrServiceRequest->coverage_mode === 'express' ? 'success' :
                                   ($his->productOrServiceRequest->coverage_mode === 'primary' ? 'warning' : 'danger');
                    $str .= " <span class='badge bg-{$coverageClass}'>HMO: " . strtoupper($his->productOrServiceRequest->coverage_mode) . '</span>';
                }
                $str .= '</div>';
                $str .= '</div>';

                // Timeline section
                $str .= '<div class="mb-3"><small>';
                $str .= '<div class="mb-2"><i class="mdi mdi-account-arrow-right text-primary"></i> <b>Requested by:</b> '
                    . ((isset($his->doctor_id) && $his->doctor_id != null) ? (userfullname($his->doctor_id) . ' <span class="text-muted">(' . date('h:i a D M j, Y', strtotime($his->created_at)) . ')</span>') : "<span class='badge bg-secondary'>N/A</span>");
                $str .= '</div>';

                $str .= '<div class="mb-2"><i class="mdi mdi-cash-multiple text-success"></i> <b>Billed by:</b> '
                    . ((isset($his->billed_by) && $his->billed_by != null) ? (userfullname($his->billed_by) . ' <span class="text-muted">(' . date('h:i a D M j, Y', strtotime($his->billed_date)) . ')</span>') : "<span class='badge bg-secondary'>Not billed</span>");
                $str .= '</div>';

                $str .= '<div class="mb-2"><i class="mdi mdi-test-tube text-warning"></i> <b>Sample taken by:</b> '
                    . ((isset($his->sample_taken_by) && $his->sample_taken_by != null) ? (userfullname($his->sample_taken_by) . ' <span class="text-muted">(' . date('h:i a D M j, Y', strtotime($his->sample_date)) . ')</span>') : "<span class='badge bg-secondary'>Not taken</span>");
                $str .= '</div>';

                $str .= '<div class="mb-2"><i class="mdi mdi-clipboard-check text-info"></i> <b>Results by:</b> '
                    . ((isset($his->result_by) && $his->result_by != null) ? (userfullname($his->result_by) . ' <span class="text-muted">(' . date('h:i a D M j, Y', strtotime($his->result_date)) . ')</span>') : "<span class='badge bg-secondary'>Awaiting Results</span>");
                $str .= '</div>';
                $str .= '</small></div>';

                // Results section
                if ($his->result) {
                    $str .= '<div class="alert alert-light mb-2"><small><b>Result:</b><br>' . $his->result . '</small></div>';
                }

                // Request note
                if (isset($his->note) && $his->note != null) {
                    $str .= '<div class="mb-2"><small><i class="mdi mdi-note-text"></i> <b>Note:</b> ' . $his->note . '</small></div>';
                }

                // Add attachments if any
                if ($his->attachments) {
                    $attachments = is_string($his->attachments) ? json_decode($his->attachments, true) : $his->attachments;
                    if (!empty($attachments)) {
                        $str .= "<div class='mb-2'><small><b><i class='mdi mdi-paperclip'></i> Attachments:</b> ";
                        foreach ($attachments as $attachment) {
                            $url = asset('storage/' . $attachment['path']);
                            $icon = $this->getFileIcon($attachment['type']);
                            $str .= "<a href='{$url}' target='_blank' class='badge bg-info text-white me-1'>{$icon} {$attachment['name']}</a> ";
                        }
                        $str .= "</small></div>";
                    }
                }

                // Check delivery status (payment + HMO validation)
                $canDeliver = true;
                $deliveryCheck = null;
                if ($his->productOrServiceRequest) {
                    $deliveryCheck = \App\Helpers\HmoHelper::canDeliverService($his->productOrServiceRequest);
                    $canDeliver = $deliveryCheck['can_deliver'];
                    if (!$canDeliver) {
                        $str .= "<div class='alert alert-warning py-2 mb-2 mt-2'><small>";
                        $str .= "<i class='fa fa-exclamation-triangle'></i> <b>" . $deliveryCheck['reason'] . "</b><br>";
                        $str .= $deliveryCheck['hint'];
                        $str .= "</small></div>";
                    }
                }

                // Action buttons
                $str .= '<div class="btn-group mt-2" role="group">';

                // Check if result can be edited (within edit window)
                $canEdit = false;
                if ($his->result_date) {
                    $resultDate = Carbon::parse($his->result_date);
                    $editDuration = appsettings('result_edit_duration') ?? 60;
                    $editDeadline = $resultDate->copy()->addMinutes($editDuration);
                    $canEdit = Carbon::now()->lessThanOrEqualTo($editDeadline) && $canDeliver;
                }

                // Add edit button if within edit window
                if ($canEdit) {
                    // Prepare attachments JSON for edit button
                    $attachmentsJson = '';
                    if ($his->attachments) {
                        $attachments = is_string($his->attachments) ? json_decode($his->attachments, true) : $his->attachments;
                        $attachmentsJson = htmlspecialchars(json_encode($attachments));
                    }

                    $str .= "
                        <button type='button' class='btn btn-warning btn-sm' onclick='editLabResult(this)'
                            data-id='{$his->id}'
                            data-service-name='" . (($his->service) ? $his->service->service_name : 'N/A') . "'
                            data-result='" . htmlspecialchars($his->result) . "'
                            data-template='" . htmlspecialchars($his->result) . "'
                            data-attachments='" . $attachmentsJson . "'>
                            <i class='mdi mdi-pencil'></i> Edit
                        </button>";
                }

                $str .= "
                    <button type='button' class='btn btn-info btn-sm' onclick='setResViewInModal(this)'
                        data-service-name = '" . (($his->service) ? $his->service->service_name : 'N/A') . "'
                        data-result = '" . htmlspecialchars($his->result) . "'
                        data-result-obj = '" . htmlspecialchars($his) . "'>
                        <i class='mdi mdi-eye'></i> View
                    </button>
                    <a href='" . route('service-requests.show', $his->id) . "' target='_blank' class='btn btn-primary btn-sm'>
                        <i class='mdi mdi-printer'></i> Print
                    </a>";

                // Show delete button only if:
                // 1. Current user is the doctor who created the request
                // 2. Status is pending (1) or in progress (2)
                // 3. Not yet billed
                // 4. No results entered yet
                $canDelete = (Auth::id() == $his->doctor_id)
                    && ($his->status == 1 || $his->status == 2)
                    && empty($his->billed_by)
                    && empty($his->result);

                if ($canDelete) {
                    $serviceName = $his->service ? $his->service->service_name : 'N/A';
                    $str .= "<button type='button' class='btn btn-danger btn-sm'
                        onclick='deleteLabRequest({$his->id}, {$his->encounter_id}, \"{$serviceName}\")'
                        title='Delete this request'>
                        <i class='fa fa-trash'></i> Delete
                    </button>";
                }

                $str .= '</div>'; // Close btn-group
                $str .= '</div>'; // Close card-body
                $str .= '</div>'; // Close card

                return $str;
            })
            ->rawColumns(['info'])
            ->make(true);
    }

    public function investBillList($patient_id)
    {
        $his = LabServiceRequest::with(['service', 'encounter', 'patient', 'productOrServiceRequest', 'doctor', 'biller'])
            ->where('status', '=', 1)->where('patient_id', $patient_id)->orderBy('created_at', 'DESC')->get();

        // dd($pc);
        return DataTables::of($his)
            ->addIndexColumn()
            ->addColumn('select', function ($h) {
                $str = "<input type='checkbox' name='selectedInvestBillRows[]' onclick='checkInvestBillRow(this)' data-price = '" . (($h->service) ? $h->service->price->sale_price : 'N/A') . "' value='$h->id' class='form-control'> ";

                return $str;
            })
            ->editColumn('created_at', function ($h) {
                $str = '<small>';
                $str .= '<b >Requested by: </b>' . ((isset($h->doctor_id) && $h->doctor_id != null) ? (userfullname($h->doctor_id) . ' (' . date('h:i a D M j, Y', strtotime($h->created_at)) . ')') : "<span class='badge badge-secondary'>N/A</span>");
                $str .= '<br><br><b >Last Updated On:</b> ' . date('h:i a D M j, Y', strtotime($h->updated_at));
                $str .= '<br><br><b >Billed by:</b> ' . ((isset($h->billed_by) && $h->billed_by != null) ? (userfullname($h->billed_by) . ' (' . date('h:i a D M j, Y', strtotime($h->billed_date)) . ')') : "<span class='badge badge-secondary'>Not billed</span>");
                $str .= '<br><br><b >Sample taken by:</b> ' . ((isset($h->sample_taken_by) && $h->sample_taken_by != null) ? (userfullname($h->sample_taken_by) . ' (' . date('h:i a D M j, Y', strtotime($h->sample_date)) . ')') : "<span class='badge badge-secondary'>Not taken</span>");
                $str .= '<br><br><b >Results by:</b> ' . ((isset($h->result_by) && $h->result_by != null) ? (userfullname($h->result_by) . ' (' . date('h:i a D M j, Y', strtotime($h->result_date)) . ')') : "<span class='badge badge-secondary'>Awaiting Results</span>");
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

    public function imagingHistoryList($patient_id)
    {
        $his = \App\Models\ImagingServiceRequest::with(['service', 'encounter', 'patient', 'patient.user', 'productOrServiceRequest', 'doctor', 'biller', 'results_person'])
            ->where('status', '>', 0)->where('patient_id', $patient_id)->orderBy('created_at', 'DESC')->get();

        return DataTables::of($his)
            ->addColumn('info', function ($his) {
                $str = '<div class="card-modern mb-2" style="border-left: 4px solid #0d6efd;">';
                $str .= '<div class="card-body p-3">';

                // Header with service name and status
                $str .= '<div class="d-flex justify-content-between align-items-start mb-3">';
                $str .= "<h6 class='mb-0'><span class='badge bg-success'>" . (($his->service) ? $his->service->service_name : 'N/A') . '</span></h6>';

                // Status badges
                $str .= '<div>';
                $statusBadge = '';
                if ($his->result) {
                    $statusBadge = "<span class='badge bg-info'>Result Available</span>";
                } elseif ($his->billed_by) {
                    $statusBadge = "<span class='badge bg-primary'>Billed</span>";
                } else {
                    $statusBadge = "<span class='badge bg-secondary'>Pending</span>";
                }
                $str .= $statusBadge;

                // HMO Coverage Badge
                if ($his->productOrServiceRequest && $his->productOrServiceRequest->coverage_mode) {
                    $coverageClass = $his->productOrServiceRequest->coverage_mode === 'express' ? 'success' :
                                   ($his->productOrServiceRequest->coverage_mode === 'primary' ? 'warning' : 'danger');
                    $str .= " <span class='badge bg-{$coverageClass}'>HMO: " . strtoupper($his->productOrServiceRequest->coverage_mode) . '</span>';
                }
                $str .= '</div>';
                $str .= '</div>';

                // Timeline section
                $str .= '<div class="mb-3"><small>';
                $str .= '<div class="mb-2"><i class="mdi mdi-account-arrow-right text-primary"></i> <b>Requested by:</b> '
                    . ((isset($his->doctor_id) && $his->doctor_id != null) ? (userfullname($his->doctor_id) . ' <span class="text-muted">(' . date('h:i a D M j, Y', strtotime($his->created_at)) . ')</span>') : "<span class='badge bg-secondary'>N/A</span>");
                $str .= '</div>';

                $str .= '<div class="mb-2"><i class="mdi mdi-cash-multiple text-success"></i> <b>Billed by:</b> '
                    . ((isset($his->billed_by) && $his->billed_by != null) ? (userfullname($his->billed_by) . ' <span class="text-muted">(' . date('h:i a D M j, Y', strtotime($his->billed_date)) . ')</span>') : "<span class='badge bg-secondary'>Not billed</span>");
                $str .= '</div>';

                $str .= '<div class="mb-2"><i class="mdi mdi-clipboard-check text-info"></i> <b>Results by:</b> '
                    . ((isset($his->result_by) && $his->result_by != null) ? (userfullname($his->result_by) . ' <span class="text-muted">(' . date('h:i a D M j, Y', strtotime($his->result_date)) . ')</span>') : "<span class='badge bg-secondary'>Awaiting Results</span>");
                $str .= '</div>';
                $str .= '</small></div>';

                // Results section
                if ($his->result) {
                    $str .= '<div class="alert alert-light mb-2"><small><b>Result:</b><br>' . $his->result . '</small></div>';
                }

                // Request note
                if (isset($his->note) && $his->note != null) {
                    $str .= '<div class="mb-2"><small><i class="mdi mdi-note-text"></i> <b>Note:</b> ' . $his->note . '</small></div>';
                }

                // Add attachments if any
                if ($his->attachments) {
                    $attachments = is_string($his->attachments) ? json_decode($his->attachments, true) : $his->attachments;
                    if (!empty($attachments)) {
                        $str .= "<div class='mb-2'><small><b><i class='mdi mdi-paperclip'></i> Attachments:</b> ";
                        foreach ($attachments as $attachment) {
                            $url = asset('storage/' . $attachment['path']);
                            $icon = $this->getFileIcon($attachment['type']);
                            $str .= "<a href='{$url}' target='_blank' class='badge bg-info text-white me-1'>{$icon} {$attachment['name']}</a> ";
                        }
                        $str .= "</small></div>";
                    }
                }

                // Check delivery status (payment + HMO validation)
                $canDeliver = true;
                $deliveryCheck = null;
                if ($his->productOrServiceRequest) {
                    $deliveryCheck = \App\Helpers\HmoHelper::canDeliverService($his->productOrServiceRequest);
                    $canDeliver = $deliveryCheck['can_deliver'];
                    if (!$canDeliver) {
                        $str .= "<div class='alert alert-warning py-2 mb-2 mt-2'><small>";
                        $str .= "<i class='fa fa-exclamation-triangle'></i> <b>" . $deliveryCheck['reason'] . "</b><br>";
                        $str .= $deliveryCheck['hint'];
                        $str .= "</small></div>";
                    }
                }

                // Action buttons
                $str .= '<div class="btn-group mt-2" role="group">';

                // Check if result can be edited (within edit window)
                $canEdit = false;
                if ($his->result_date) {
                    $resultDate = Carbon::parse($his->result_date);
                    $editDuration = appsettings('result_edit_duration') ?? 60;
                    $editDeadline = $resultDate->copy()->addMinutes($editDuration);
                    $canEdit = Carbon::now()->lessThanOrEqualTo($editDeadline) && $canDeliver;
                }

                // Add edit button if within edit window
                if ($canEdit) {
                    // Prepare attachments JSON for edit button
                    $attachmentsJson = '';
                    if ($his->attachments) {
                        $attachments = is_string($his->attachments) ? json_decode($his->attachments, true) : $his->attachments;
                        $attachmentsJson = htmlspecialchars(json_encode($attachments));
                    }

                    $str .= "
                        <button type='button' class='btn btn-warning btn-sm' onclick='editImagingResult(this)'
                            data-id='{$his->id}'
                            data-service-name='" . (($his->service) ? $his->service->service_name : 'N/A') . "'
                            data-result='" . htmlspecialchars($his->result) . "'
                            data-template='" . htmlspecialchars($his->result) . "'
                            data-attachments='" . $attachmentsJson . "'>
                            <i class='mdi mdi-pencil'></i> Edit
                        </button>";
                }

                $str .= "
                    <button type='button' class='btn btn-info btn-sm' onclick='setImagingResViewInModal(this)'
                        data-service-name = '" . (($his->service) ? $his->service->service_name : 'N/A') . "'
                        data-result = '" . htmlspecialchars($his->result) . "'
                        data-result-obj = '" . htmlspecialchars($his) . "'>
                        <i class='mdi mdi-eye'></i> View
                    </button>
                    <a href='" . route('imaging-requests.show', $his->id) . "' target='_blank' class='btn btn-primary btn-sm'>
                        <i class='mdi mdi-printer'></i> Print
                    </a>";

                // Show delete button only if:
                // 1. Current user is the doctor who created the request
                // 2. Status is pending (1) or in progress (2)
                // 3. Not yet billed
                // 4. No results entered yet
                $canDelete = (Auth::id() == $his->doctor_id)
                    && ($his->status == 1 || $his->status == 2)
                    && empty($his->billed_by)
                    && empty($his->result);

                if ($canDelete) {
                    $serviceName = $his->service ? $his->service->service_name : 'N/A';
                    $str .= "<button type='button' class='btn btn-danger btn-sm'
                        onclick='deleteImagingRequest({$his->id}, {$his->encounter_id}, \"{$serviceName}\")'
                        title='Delete this request'>
                        <i class='fa fa-trash'></i> Delete
                    </button>";
                }

                $str .= '</div>'; // Close btn-group
                $str .= '</div>'; // Close card-body
                $str .= '</div>'; // Close card

                return $str;
            })
            ->rawColumns(['info'])
            ->make(true);
    }

    public function imagingBillList($patient_id)
    {
        $his = \App\Models\ImagingServiceRequest::with(['service', 'encounter', 'patient', 'productOrServiceRequest', 'doctor', 'biller'])
            ->where('status', '=', 1)->where('patient_id', $patient_id)->orderBy('created_at', 'DESC')->get();

        return DataTables::of($his)
            ->addIndexColumn()
            ->addColumn('select', function ($h) {
                $str = "<input type='checkbox' name='selectedImagingBillRows[]' onclick='checkImagingBillRow(this)' data-price = '" . (($h->service) ? $h->service->price->sale_price : 'N/A') . "' value='$h->id' class='form-control'> ";
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

    public function investSampleList($patient_id)
    {
        $his = LabServiceRequest::with(['service', 'encounter', 'patient', 'productOrServiceRequest', 'doctor', 'biller'])
            ->where('status', '=', 2)->where('patient_id', $patient_id)->orderBy('created_at', 'DESC')->get();

        // dd($pc);
        return DataTables::of($his)
            ->addIndexColumn()
            ->addColumn('select', function ($h) {
                $str = "<input type='checkbox' name='selectedInvestSampleRows[]' onclick='checkInvestSampleRow(this)' data-price = '" . $h->service->price->sale_price . "' value='$h->id' class='form-control'> ";

                return $str;
            })
            ->editColumn('created_at', function ($h) {
                $str = '<small>';
                $str .= '<b >Requested by: </b>' . ((isset($h->doctor_id) && $h->doctor_id != null) ? (userfullname($h->doctor_id) . ' (' . date('h:i a D M j, Y', strtotime($h->created_at)) . ')') : "<span class='badge badge-secondary'>N/A</span>");
                $str .= '<br><br><b >Last Updated On:</b> ' . date('h:i a D M j, Y', strtotime($h->updated_at));
                $str .= '<br><br><b >Billed by:</b> ' . ((isset($h->billed_by) && $h->billed_by != null) ? (userfullname($h->billed_by) . ' (' . date('h:i a D M j, Y', strtotime($h->billed_date)) . ')') : "<span class='badge badge-secondary'>Not billed</span>");
                $str .= '<br><br><b >Sample taken by:</b> ' . ((isset($h->sample_taken_by) && $h->sample_taken_by != null) ? (userfullname($h->sample_taken_by) . ' (' . date('h:i a D M j, Y', strtotime($h->sample_date)) . ')') : "<span class='badge badge-secondary'>Not taken</span>");
                $str .= '<br><br><b >Results by:</b> ' . ((isset($h->result_by) && $h->result_by != null) ? (userfullname($h->result_by) . ' (' . date('h:i a D M j, Y', strtotime($h->result_date)) . ')') : "<span class='badge badge-secondary'>Awaiting Results</span>");
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

    public function prescBillList($patient_id)
    {
        $items = ProductRequest::with(['product.price', 'product.category', 'encounter', 'patient', 'productOrServiceRequest', 'doctor', 'biller'])
            ->where('status', 1)->where('patient_id', $patient_id)->orderBy('created_at', 'DESC')->get();

        return DataTables::of($items)
            ->addIndexColumn()
            ->addColumn('id', function ($item) {
                return $item->id;
            })
            ->addColumn('product_id', function ($item) {
                return $item->product_id;
            })
            ->addColumn('product_name', function ($item) {
                return optional($item->product)->product_name ?? 'Unknown';
            })
            ->addColumn('product_code', function ($item) {
                return optional($item->product)->product_code ?? '';
            })
            ->addColumn('dose', function ($item) {
                return $item->dose ?? 'N/A';
            })
            ->addColumn('qty', function ($item) {
                return $item->qty ?? 1;
            })
            ->addColumn('price', function ($item) {
                return optional(optional($item->product)->price)->current_sale_price ?? 0;
            })
            ->addColumn('payable_amount', function ($item) {
                $posr = $item->productOrServiceRequest;
                return $posr ? ($posr->payable_amount ?? 0) : (optional(optional($item->product)->price)->current_sale_price ?? 0);
            })
            ->addColumn('claims_amount', function ($item) {
                return optional($item->productOrServiceRequest)->claims_amount ?? 0;
            })
            ->addColumn('coverage_mode', function ($item) {
                return optional($item->productOrServiceRequest)->coverage_mode ?? 'none';
            })
            ->addColumn('requested_by', function ($item) {
                return $item->doctor_id ? userfullname($item->doctor_id) : 'N/A';
            })
            ->addColumn('requested_at', function ($item) {
                return $item->created_at ? date('M j, Y h:i A', strtotime($item->created_at)) : '';
            })
            ->addColumn('billed_by', function ($item) {
                return $item->billed_by ? userfullname($item->billed_by) : null;
            })
            ->addColumn('billed_at', function ($item) {
                return $item->billed_date ? date('M j, Y h:i A', strtotime($item->billed_date)) : '';
            })
            ->addColumn('global_stock', function ($item) {
                if (!$item->product_id) return 0;
                // Get stock from StockBatch (source of truth)
                return (int) \App\Models\StockBatch::where('product_id', $item->product_id)
                    ->where('current_qty', '>', 0)
                    ->sum('current_qty');
            })
            ->addColumn('store_stocks', function ($item) {
                if (!$item->product_id) return [];
                // Get stock grouped by store from StockBatch
                $storeStockData = \App\Models\StockBatch::where('product_id', $item->product_id)
                    ->where('current_qty', '>', 0)
                    ->selectRaw('store_id, SUM(current_qty) as total_qty')
                    ->groupBy('store_id')
                    ->orderByDesc('total_qty')
                    ->get();

                $storeStocks = [];
                foreach ($storeStockData as $batch) {
                    $store = \App\Models\Store::find($batch->store_id);
                    $storeStocks[] = [
                        'store_id' => $batch->store_id,
                        'store_name' => $store ? $store->store_name : 'Unknown Store',
                        'quantity' => (int) $batch->total_qty
                    ];
                }
                return $storeStocks;
            })
            ->make(true);
    }

    public function prescDispenseList($patient_id)
    {
        $items = ProductRequest::with(['product.price', 'product.category', 'encounter', 'patient', 'productOrServiceRequest.payment', 'doctor', 'biller'])
            ->where('status', 2)->where('patient_id', $patient_id)->orderBy('created_at', 'DESC')->get();

        return DataTables::of($items)
            ->addIndexColumn()
            ->addColumn('id', function ($item) {
                return $item->id;
            })
            ->addColumn('product_id', function ($item) {
                return $item->product_id;
            })
            ->addColumn('product_name', function ($item) {
                return optional($item->product)->product_name ?? 'Unknown';
            })
            ->addColumn('product_code', function ($item) {
                return optional($item->product)->product_code ?? '';
            })
            ->addColumn('dose', function ($item) {
                return $item->dose ?? 'N/A';
            })
            ->addColumn('qty', function ($item) {
                return $item->qty ?? 1;
            })
            ->addColumn('price', function ($item) {
                return optional(optional($item->product)->price)->current_sale_price ?? 0;
            })
            ->addColumn('payable_amount', function ($item) {
                $posr = $item->productOrServiceRequest;
                return $posr ? ($posr->payable_amount ?? 0) : (optional(optional($item->product)->price)->current_sale_price ?? 0);
            })
            ->addColumn('claims_amount', function ($item) {
                return optional($item->productOrServiceRequest)->claims_amount ?? 0;
            })
            ->addColumn('coverage_mode', function ($item) {
                return optional($item->productOrServiceRequest)->coverage_mode ?? 'none';
            })
            ->addColumn('is_paid', function ($item) {
                return optional(optional($item->productOrServiceRequest)->payment)->payment_status === 'paid';
            })
            ->addColumn('is_validated', function ($item) {
                return optional($item->productOrServiceRequest)->validation_status === 'validated';
            })
            ->addColumn('can_dispense', function ($item) {
                $posr = $item->productOrServiceRequest;
                if (!$posr) return true; // No POSR means self-pay, can dispense
                $isPaid = optional($posr->payment)->payment_status === 'paid';
                $isValidated = $posr->validation_status === 'validated';
                return $isPaid || $isValidated;
            })
            ->addColumn('requested_by', function ($item) {
                return $item->doctor_id ? userfullname($item->doctor_id) : 'N/A';
            })
            ->addColumn('requested_at', function ($item) {
                return $item->created_at ? date('M j, Y h:i A', strtotime($item->created_at)) : '';
            })
            ->addColumn('billed_by', function ($item) {
                return $item->billed_by ? userfullname($item->billed_by) : null;
            })
            ->addColumn('billed_at', function ($item) {
                return $item->billed_date ? date('M j, Y h:i A', strtotime($item->billed_date)) : '';
            })
            ->addColumn('global_stock', function ($item) {
                if (!$item->product_id) return 0;
                // Get stock from StockBatch (source of truth)
                return (int) \App\Models\StockBatch::where('product_id', $item->product_id)
                    ->where('current_qty', '>', 0)
                    ->sum('current_qty');
            })
            ->addColumn('store_stocks', function ($item) {
                if (!$item->product_id) return [];
                // Get stock grouped by store from StockBatch
                $storeStockData = \App\Models\StockBatch::where('product_id', $item->product_id)
                    ->where('current_qty', '>', 0)
                    ->selectRaw('store_id, SUM(current_qty) as total_qty')
                    ->groupBy('store_id')
                    ->orderByDesc('total_qty')
                    ->get();

                $storeStocks = [];
                foreach ($storeStockData as $batch) {
                    $store = \App\Models\Store::find($batch->store_id);
                    $storeStocks[] = [
                        'store_id' => $batch->store_id,
                        'store_name' => $store ? $store->store_name : 'Unknown Store',
                        'quantity' => (int) $batch->total_qty
                    ];
                }
                return $storeStocks;
            })
            ->make(true);
    }

    /**
     * Get billed items that are PENDING payment or HMO validation
     * (status=2 but NOT ready to dispense)
     */
    public function prescPendingList($patient_id)
    {
        $items = ProductRequest::with(['product.price', 'product.category', 'encounter', 'patient', 'productOrServiceRequest.payment', 'doctor', 'biller'])
            ->where('status', 2)
            ->where('patient_id', $patient_id)
            ->whereHas('productOrServiceRequest', function($q) {
                $q->where(function($query) {
                    // Items awaiting payment (payable > 0 and not paid - payment_id is null)
                    $query->where('payable_amount', '>', 0)
                          ->whereNull('payment_id');
                })->orWhere(function($query) {
                    // Items awaiting HMO validation (claims > 0 and not validated)
                    $query->where('claims_amount', '>', 0)
                          ->where(function($q2) {
                              $q2->whereNull('validation_status')
                                 ->orWhereNotIn('validation_status', ['validated', 'approved']);
                          });
                });
            })
            ->orderBy('created_at', 'DESC')
            ->get();

        return DataTables::of($items)
            ->addIndexColumn()
            ->addColumn('id', function ($item) {
                return $item->id;
            })
            ->addColumn('product_id', function ($item) {
                return $item->product_id;
            })
            ->addColumn('product_name', function ($item) {
                return optional($item->product)->product_name ?? 'Unknown';
            })
            ->addColumn('product_code', function ($item) {
                return optional($item->product)->product_code ?? '';
            })
            ->addColumn('dose', function ($item) {
                return $item->dose ?? 'N/A';
            })
            ->addColumn('qty', function ($item) {
                return $item->qty ?? 1;
            })
            ->addColumn('price', function ($item) {
                return optional(optional($item->product)->price)->current_sale_price ?? 0;
            })
            ->addColumn('payable_amount', function ($item) {
                $posr = $item->productOrServiceRequest;
                return $posr ? ($posr->payable_amount ?? 0) : (optional(optional($item->product)->price)->current_sale_price ?? 0);
            })
            ->addColumn('claims_amount', function ($item) {
                return optional($item->productOrServiceRequest)->claims_amount ?? 0;
            })
            ->addColumn('coverage_mode', function ($item) {
                return optional($item->productOrServiceRequest)->coverage_mode ?? 'none';
            })
            ->addColumn('is_paid', function ($item) {
                // Payment exists if payment_id is not null
                return optional($item->productOrServiceRequest)->payment_id !== null;
            })
            ->addColumn('is_validated', function ($item) {
                $status = optional($item->productOrServiceRequest)->validation_status;
                return in_array($status, ['validated', 'approved']);
            })
            ->addColumn('pending_reason', function ($item) {
                $posr = $item->productOrServiceRequest;
                if (!$posr) return '';
                $reasons = [];
                $isPaid = $posr->payment_id !== null;
                $isValidated = in_array($posr->validation_status, ['validated', 'approved']);
                if ($posr->payable_amount > 0 && !$isPaid) {
                    $reasons[] = 'Awaiting Payment';
                }
                if ($posr->claims_amount > 0 && !$isValidated) {
                    $reasons[] = 'Awaiting HMO Validation';
                }
                return implode(', ', $reasons);
            })
            ->addColumn('requested_by', function ($item) {
                return $item->doctor_id ? userfullname($item->doctor_id) : 'N/A';
            })
            ->addColumn('requested_at', function ($item) {
                return $item->created_at ? date('M j, Y h:i A', strtotime($item->created_at)) : '';
            })
            ->addColumn('billed_by', function ($item) {
                return $item->billed_by ? userfullname($item->billed_by) : null;
            })
            ->addColumn('billed_at', function ($item) {
                return $item->billed_date ? date('M j, Y h:i A', strtotime($item->billed_date)) : '';
            })
            ->addColumn('global_stock', function ($item) {
                if (!$item->product_id) return 0;
                // Get stock from StockBatch (source of truth)
                return (int) \App\Models\StockBatch::where('product_id', $item->product_id)
                    ->where('current_qty', '>', 0)
                    ->sum('current_qty');
            })
            ->addColumn('store_stocks', function ($item) {
                if (!$item->product_id) return [];
                // Get stock grouped by store from StockBatch
                $storeStockData = \App\Models\StockBatch::where('product_id', $item->product_id)
                    ->where('current_qty', '>', 0)
                    ->selectRaw('store_id, SUM(current_qty) as total_qty')
                    ->groupBy('store_id')
                    ->orderByDesc('total_qty')
                    ->get();

                $storeStocks = [];
                foreach ($storeStockData as $batch) {
                    $store = \App\Models\Store::find($batch->store_id);
                    $storeStocks[] = [
                        'store_id' => $batch->store_id,
                        'store_name' => $store ? $store->store_name : 'Unknown Store',
                        'quantity' => (int) $batch->total_qty
                    ];
                }
                return $storeStocks;
            })
            ->make(true);
    }

    /**
     * Get billed items that are READY for dispense
     * (status=2 AND paid/validated as needed)
     */
    public function prescReadyList($patient_id)
    {
        $items = ProductRequest::with(['product.price', 'product.category', 'encounter', 'patient', 'productOrServiceRequest.payment', 'doctor', 'biller', 'procedureItem.procedure.service'])
            ->where('status', 2)
            ->where('patient_id', $patient_id)
            ->where(function($q) {
                // Items without POSR (direct billing) - always ready
                $q->whereDoesntHave('productOrServiceRequest')
                  // OR items with POSR that are ready
                  ->orWhereHas('productOrServiceRequest', function($query) {
                      $query->where(function($q2) {
                          // Cash items: payable > 0, claims = 0 or null, paid (payment_id not null)
                          $q2->where('payable_amount', '>', 0)
                             ->where(function($q3) {
                                 $q3->where('claims_amount', '<=', 0)->orWhereNull('claims_amount');
                             })
                             ->whereNotNull('payment_id');
                      })->orWhere(function($q2) {
                          // Full HMO items: payable = 0 or null, claims > 0, validated
                          $q2->where(function($q3) {
                                 $q3->where('payable_amount', '<=', 0)->orWhereNull('payable_amount');
                             })
                             ->where('claims_amount', '>', 0)
                             ->whereIn('validation_status', ['validated', 'approved']);
                      })->orWhere(function($q2) {
                          // Co-pay items: payable > 0, claims > 0, both paid and validated
                          $q2->where('payable_amount', '>', 0)
                             ->where('claims_amount', '>', 0)
                             ->whereIn('validation_status', ['validated', 'approved'])
                             ->whereNotNull('payment_id');
                      });
                  })
                  // OR bundled procedure items (no separate billing, procedure covers it)
                  ->orWhereHas('procedureItem', function($query) {
                      $query->where('is_bundled', true);
                  });
            })
            ->orderBy('created_at', 'DESC')
            ->get();

        return DataTables::of($items)
            ->addIndexColumn()
            ->addColumn('id', function ($item) {
                return $item->id;
            })
            ->addColumn('product_id', function ($item) {
                return $item->product_id;
            })
            ->addColumn('product_name', function ($item) {
                return optional($item->product)->product_name ?? 'Unknown';
            })
            ->addColumn('product_code', function ($item) {
                return optional($item->product)->product_code ?? '';
            })
            ->addColumn('dose', function ($item) {
                return $item->dose ?? 'N/A';
            })
            ->addColumn('qty', function ($item) {
                return $item->qty ?? 1;
            })
            ->addColumn('price', function ($item) {
                return optional(optional($item->product)->price)->current_sale_price ?? 0;
            })
            ->addColumn('payable_amount', function ($item) {
                $posr = $item->productOrServiceRequest;
                return $posr ? ($posr->payable_amount ?? 0) : (optional(optional($item->product)->price)->current_sale_price ?? 0);
            })
            ->addColumn('claims_amount', function ($item) {
                return optional($item->productOrServiceRequest)->claims_amount ?? 0;
            })
            ->addColumn('coverage_mode', function ($item) {
                return optional($item->productOrServiceRequest)->coverage_mode ?? 'none';
            })
            ->addColumn('is_paid', function ($item) {
                // Payment exists if payment_id is not null
                return optional($item->productOrServiceRequest)->payment_id !== null;
            })
            ->addColumn('is_validated', function ($item) {
                $status = optional($item->productOrServiceRequest)->validation_status;
                return in_array($status, ['validated', 'approved']);
            })
            ->addColumn('is_bundled', function ($item) {
                return $item->procedureItem && $item->procedureItem->is_bundled;
            })
            ->addColumn('procedure_name', function ($item) {
                if ($item->procedureItem) {
                    return optional(optional($item->procedureItem->procedure)->service)->service_name ?? 'Procedure';
                }
                return null;
            })
            ->addColumn('requested_by', function ($item) {
                return $item->doctor_id ? userfullname($item->doctor_id) : 'N/A';
            })
            ->addColumn('requested_at', function ($item) {
                return $item->created_at ? date('M j, Y h:i A', strtotime($item->created_at)) : '';
            })
            ->addColumn('billed_by', function ($item) {
                return $item->billed_by ? userfullname($item->billed_by) : null;
            })
            ->addColumn('billed_at', function ($item) {
                return $item->billed_date ? date('M j, Y h:i A', strtotime($item->billed_date)) : '';
            })
            ->addColumn('global_stock', function ($item) {
                if (!$item->product_id) return 0;
                // Get stock from StockBatch (source of truth)
                return (int) \App\Models\StockBatch::where('product_id', $item->product_id)
                    ->where('current_qty', '>', 0)
                    ->sum('current_qty');
            })
            ->addColumn('store_stocks', function ($item) {
                if (!$item->product_id) return [];
                // Get stock grouped by store from StockBatch
                $storeStockData = \App\Models\StockBatch::where('product_id', $item->product_id)
                    ->where('current_qty', '>', 0)
                    ->selectRaw('store_id, SUM(current_qty) as total_qty')
                    ->groupBy('store_id')
                    ->orderByDesc('total_qty')
                    ->get();

                $storeStocks = [];
                foreach ($storeStockData as $batch) {
                    $store = \App\Models\Store::find($batch->store_id);
                    $storeStocks[] = [
                        'store_id' => $batch->store_id,
                        'store_name' => $store ? $store->store_name : 'Unknown Store',
                        'quantity' => (int) $batch->total_qty
                    ];
                }
                return $storeStocks;
            })
            ->make(true);
    }

    public function prescHistoryList($patient_id)
    {
        // Show ALL prescription requests (not just dispensed) for complete history
        $items = ProductRequest::with(['product.price', 'product.category', 'encounter', 'patient', 'productOrServiceRequest.payment', 'doctor', 'biller', 'dispenser'])
            ->where('patient_id', $patient_id)
            ->orderBy('created_at', 'DESC')
            ->get();

        return DataTables::of($items)
            ->addIndexColumn()
            ->addColumn('id', function ($item) {
                return $item->id;
            })
            ->addColumn('product_id', function ($item) {
                return $item->product_id;
            })
            ->addColumn('status', function ($item) {
                return $item->status;
            })
            ->addColumn('product_name', function ($item) {
                return optional($item->product)->product_name ?? 'Unknown';
            })
            ->addColumn('product_code', function ($item) {
                return optional($item->product)->product_code ?? '';
            })
            ->addColumn('dose', function ($item) {
                return $item->dose ?? 'N/A';
            })
            ->addColumn('qty', function ($item) {
                return $item->qty ?? 1;
            })
            ->addColumn('price', function ($item) {
                return optional(optional($item->product)->price)->current_sale_price ?? 0;
            })
            ->addColumn('payable_amount', function ($item) {
                $posr = $item->productOrServiceRequest;
                return $posr ? ($posr->payable_amount ?? 0) : (optional(optional($item->product)->price)->current_sale_price ?? 0);
            })
            ->addColumn('claims_amount', function ($item) {
                return optional($item->productOrServiceRequest)->claims_amount ?? 0;
            })
            ->addColumn('coverage_mode', function ($item) {
                return optional($item->productOrServiceRequest)->coverage_mode ?? 'none';
            })
            ->addColumn('is_paid', function ($item) {
                return optional($item->productOrServiceRequest)->payment_id !== null;
            })
            ->addColumn('is_validated', function ($item) {
                $status = optional($item->productOrServiceRequest)->validation_status;
                return in_array($status, ['validated', 'approved']);
            })
            ->addColumn('requested_by', function ($item) {
                return $item->doctor_id ? userfullname($item->doctor_id) : 'N/A';
            })
            ->addColumn('requested_at', function ($item) {
                return $item->created_at ? date('M j, Y h:i A', strtotime($item->created_at)) : '';
            })
            ->addColumn('billed_by', function ($item) {
                return $item->billed_by ? userfullname($item->billed_by) : null;
            })
            ->addColumn('billed_at', function ($item) {
                return $item->billed_date ? date('M j, Y h:i A', strtotime($item->billed_date)) : '';
            })
            ->addColumn('dispensed_by', function ($item) {
                return $item->dispensed_by ? userfullname($item->dispensed_by) : null;
            })
            ->addColumn('dispensed_at', function ($item) {
                return $item->dispense_date ? date('M j, Y h:i A', strtotime($item->dispense_date)) : '';
            })
            ->addColumn('info', function ($item) {
                // Build info HTML for new_encounter.blade.php compatibility
                $productName = optional($item->product)->product_name ?? 'Unknown';
                $productCode = optional($item->product)->product_code ?? '';
                $dose = $item->dose ?? 'N/A';
                $qty = $item->qty ?? 1;
                $price = optional(optional($item->product)->price)->current_sale_price ?? 0;
                $requestedBy = $item->doctor_id ? userfullname($item->doctor_id) : 'N/A';
                $requestedAt = $item->created_at ? date('M j, Y h:i A', strtotime($item->created_at)) : 'N/A';
                $billedBy = $item->billed_by ? userfullname($item->billed_by) : null;
                $billedAt = $item->billed_date ? date('M j, Y h:i A', strtotime($item->billed_date)) : null;
                $dispensedBy = $item->dispensed_by ? userfullname($item->dispensed_by) : null;
                $dispensedAt = $item->dispense_date ? date('M j, Y h:i A', strtotime($item->dispense_date)) : null;

                // Determine status badge based on status value
                $status = $item->status;
                $statusBadge = '';
                $statusInfo = '';

                if ($status == 0) {
                    $statusBadge = "<span class='badge bg-danger'>Dismissed</span>";
                    $statusInfo = "<div class='mt-1 text-muted small'><i class='mdi mdi-account'></i> Requested by: {$requestedBy} on {$requestedAt}</div>";
                } elseif ($status == 1) {
                    $statusBadge = "<span class='badge bg-warning text-dark'>Unbilled</span>";
                    $statusInfo = "<div class='mt-1 text-muted small'><i class='mdi mdi-account'></i> Requested by: {$requestedBy} on {$requestedAt}</div>";
                } elseif ($status == 2) {
                    // Check if ready to dispense or awaiting payment/validation
                    $payableAmount = optional($item->productOrServiceRequest)->payable_amount ?? 0;
                    $claimsAmount = optional($item->productOrServiceRequest)->claims_amount ?? 0;
                    $isPaid = optional($item->productOrServiceRequest)->payment_id !== null;
                    $validationStatus = optional($item->productOrServiceRequest)->validation_status;
                    $isValidated = in_array($validationStatus, ['validated', 'approved']);

                    $pendingReasons = [];
                    if ($payableAmount > 0 && !$isPaid) {
                        $pendingReasons[] = 'Payment';
                    }
                    if ($claimsAmount > 0 && !$isValidated) {
                        $pendingReasons[] = 'HMO Validation';
                    }

                    if (count($pendingReasons) > 0) {
                        $statusBadge = "<span class='badge bg-info'>Awaiting " . implode(' & ', $pendingReasons) . "</span>";
                    } else {
                        $statusBadge = "<span class='badge bg-success'>Ready to Dispense</span>";
                    }

                    $statusInfo = "<div class='mt-1 text-muted small'><i class='mdi mdi-account'></i> Requested by: {$requestedBy} on {$requestedAt}</div>";
                    if ($billedBy) {
                        $statusInfo .= "<div class='text-muted small'><i class='mdi mdi-receipt'></i> Billed by: {$billedBy} on {$billedAt}</div>";
                    }
                } elseif ($status == 3) {
                    $statusBadge = "<span class='badge bg-secondary'>Dispensed</span>";
                    $statusInfo = "<div class='mt-1 text-muted small'><i class='mdi mdi-account'></i> Requested by: {$requestedBy} on {$requestedAt}</div>";
                    if ($billedBy) {
                        $statusInfo .= "<div class='text-muted small'><i class='mdi mdi-receipt'></i> Billed by: {$billedBy} on {$billedAt}</div>";
                    }
                    if ($dispensedBy) {
                        $statusInfo .= "<div class='text-muted small'><i class='mdi mdi-truck-delivery'></i> Dispensed by: {$dispensedBy} on {$dispensedAt}</div>";
                    }
                }

                return "
                    <div class='p-2 border-bottom'>
                        <div class='d-flex justify-content-between'>
                            <strong>{$productName}</strong>
                            {$statusBadge}
                        </div>
                        <small class='text-muted'>{$productCode}</small>
                        <div class='mt-1'>
                            <span><i class='mdi mdi-pill'></i> {$dose}</span>
                            <span class='ms-2'><i class='mdi mdi-numeric'></i> Qty: {$qty}</span>
                            <span class='ms-2'><i class='mdi mdi-cash'></i> ₦" . number_format($price, 2) . "</span>
                        </div>
                        {$statusInfo}
                    </div>
                ";
            })
            ->rawColumns(['info'])
            ->make(true);
    }

    public function EncounterHistoryList($patient_id)
    {
        // Get the current encounter ID to exclude if provided
        $excludeEncounterId = request()->get('exclude_encounter_id');

        $query = Encounter::with(['doctor.staff_profile.specialization', 'patient'])
            ->where('patient_id', $patient_id)
            ->where('notes', '!=', null)
            ->where('completed', true) // Only show completed encounters
            ->orderBy('created_at', 'DESC');

        // Exclude the current encounter if specified
        if ($excludeEncounterId) {
            $query->where('id', '!=', $excludeEncounterId);
        }

        $hist = $query->get();

        return DataTables::of($hist)
            ->addColumn('info', function ($hist) {
                $str = '<div class="card-modern mb-2" style="border-left: 4px solid #0d6efd;">';
                $str .= '<div class="card-body p-3">';

                // Header with doctor name and status
                $str .= '<div class="d-flex justify-content-between align-items-start mb-3">';
                $str .= "<div>";
                $str .= "<h6 class='mb-0'><i class='mdi mdi-account-circle'></i> <span class='text-primary'>" . userfullname($hist->doctor_id) . "</span></h6>";
                $specialty = $hist->doctor->staff_profile->specialization->name ?? 'General Practitioner';
                $str .= "<small class='text-muted' style='margin-left: 1.5rem; display: block; margin-top: 2px;'>" . $specialty . "</small>";
                $str .= "</div>";
                $str .= "<span class='badge bg-info'>" . date('h:i a D M j, Y', strtotime($hist->created_at)) . "</span>";
                $str .= '</div>';

                // Reasons for encounter
                if ($hist->reasons_for_encounter != '') {
                    $reasons_for_encounter = explode(',', $hist->reasons_for_encounter);
                    $str .= '<div class="mb-3">';
                    $str .= '<small><b><i class="mdi mdi-format-list-bulleted"></i> Reason(s) for Encounter/Diagnosis (ICPC-2):</b></small><br>';
                    foreach ($reasons_for_encounter as $reason) {
                        $str .= "<span class='badge bg-light text-dark me-1 mb-1'>" . trim($reason) . "</span>";
                    }
                    $str .= '</div>';
                }

                // Diagnosis comments
                if ($hist->reasons_for_encounter_comment_1) {
                    $str .= '<div class="mb-2"><small><b><i class="mdi mdi-comment-text"></i> Diagnosis Comment 1:</b> ' . $hist->reasons_for_encounter_comment_1 . '</small></div>';
                }
                if ($hist->reasons_for_encounter_comment_2) {
                    $str .= '<div class="mb-2"><small><b><i class="mdi mdi-comment-text"></i> Diagnosis Comment 2:</b> ' . $hist->reasons_for_encounter_comment_2 . '</small></div>';
                }

                // Main notes
                $str .= '<div class="alert alert-light mb-2"><small><b><i class="mdi mdi-note-text"></i> Clinical Notes:</b><br>' . $hist->notes . '</small></div>';

                // Action buttons
                $str .= '<div class="btn-group mt-2" role="group">';

                // Check if encounter can be edited (within edit window)
                $canEdit = false;
                if ($hist->created_at) {
                    $createdDate = Carbon::parse($hist->created_at);
                    $editDuration = appsettings('note_edit_duration') ?? 60;
                    $editDeadline = $createdDate->copy()->addMinutes($editDuration);
                    $canEdit = Carbon::now()->lessThanOrEqualTo($editDeadline);
                }

                // Add edit button if within edit window and user is the creator
                if ($canEdit && Auth::id() == $hist->doctor_id) {
                    $notesEscaped = htmlspecialchars($hist->notes, ENT_QUOTES);
                    $reasonsEscaped = htmlspecialchars($hist->reasons_for_encounter ?? '');
                    $comment1Escaped = htmlspecialchars($hist->reasons_for_encounter_comment_1 ?? '');
                    $comment2Escaped = htmlspecialchars($hist->reasons_for_encounter_comment_2 ?? '');
                    $isWardRound = $hist->admission_request_id ? 'true' : 'false';

                    $str .= "<button type='button' class='btn btn-warning btn-sm' onclick='editEncounterNote(this)'
                        data-id='{$hist->id}'
                        data-notes='{$notesEscaped}'
                        data-reasons='{$reasonsEscaped}'
                        data-comment1='{$comment1Escaped}'
                        data-comment2='{$comment2Escaped}'
                        data-is-ward-round='{$isWardRound}'>
                        <i class='mdi mdi-pencil'></i> Edit
                    </button>";
                }

                // Show delete button only if within edit window and user is the creator
                $canDelete = $canEdit && (Auth::id() == $hist->doctor_id);

                if ($canDelete) {
                    $encounterDate = date('M j, Y', strtotime($hist->created_at));
                    $str .= "<button type='button' class='btn btn-danger btn-sm'
                        onclick='deleteEncounter({$hist->id}, \"{$encounterDate}\")'
                        title='Delete this encounter note'>
                        <i class='fa fa-trash'></i> Delete
                    </button>";
                }

                $str .= '</div>'; // Close btn-group
                $str .= '</div>'; // Close card-body
                $str .= '</div>'; // Close card

                return $str;
            })
            ->rawColumns(['info'])
            ->make(true);
    }

    /**
     * autosave notes.
     */
    public function autosaveNotes(Request $request)
    {
        try {
            $request->validate([
                'encounter_id' => 'required',
                'notes' => 'required',
            ]);

            $encounter = Encounter::where('id', $request->encounter_id)->update([
                'notes' => $request->notes,
            ]);

            return response()->json(['success']);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['exception' => $e]);

            return response()->json(['failed'], 500);
        }
    }

    /**
     * Delete an encounter note (soft delete).
     */
    public function deleteEncounter(Request $request, Encounter $encounter)
    {
        try {
            // Check permissions
            if (Auth::id() != $encounter->doctor_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to delete this encounter note.'
                ], 403);
            }

            // Check if within edit window
            $createdDate = Carbon::parse($encounter->created_at);
            $editDuration = appsettings('note_edit_duration') ?? 60;
            $editDeadline = $createdDate->copy()->addMinutes($editDuration);

            if (Carbon::now()->greaterThan($editDeadline)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The edit window for this encounter has expired.'
                ], 403);
            }

            // Validate deletion reason
            $request->validate([
                'reason' => 'required|string|max:500'
            ]);

            // Perform soft delete
            $encounter->deleted_by = Auth::id();
            $encounter->deletion_reason = $request->reason;
            $encounter->save();
            $encounter->delete();

            return response()->json([
                'success' => true,
                'message' => 'Encounter note deleted successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Encounter deletion failed: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete encounter: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update encounter notes.
     */
    public function updateEncounterNotes(Request $request, Encounter $encounter)
    {
        try {
            // Check permissions
            if (Auth::id() != $encounter->doctor_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to edit this encounter note.'
                ], 403);
            }

            // Check if within edit window
            $createdDate = Carbon::parse($encounter->created_at);
            $editDuration = appsettings('note_edit_duration') ?? 60;
            $editDeadline = $createdDate->copy()->addMinutes($editDuration);

            if (Carbon::now()->greaterThan($editDeadline)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The edit window for this encounter has expired.'
                ], 403);
            }

            // Check if diagnosis is applicable
            $diagnosisApplicable = $request->input('diagnosis_applicable', true);

            // Validate request
            $validationRules = [
                'notes' => 'required|string',
                'diagnosis_applicable' => 'nullable|boolean',
            ];

            // Only require diagnosis fields if applicable
            if ($diagnosisApplicable) {
                $validationRules['reasons_for_encounter'] = 'nullable|string';
                $validationRules['reasons_for_encounter_comment_1'] = 'nullable|string';
                $validationRules['reasons_for_encounter_comment_2'] = 'nullable|string';
            }

            $request->validate($validationRules);

            // Process reasons only if diagnosis is applicable
            $reasonsString = null;
            $comment1 = null;
            $comment2 = null;

            if ($diagnosisApplicable) {
                $reasonsString = $request->reasons_for_encounter;
                $comment1 = $request->reasons_for_encounter_comment_1;
                $comment2 = $request->reasons_for_encounter_comment_2;

                if ($reasonsString) {
                    $reasonsArray = explode(',', $reasonsString);
                    $processedReasons = [];

                    foreach ($reasonsArray as $reason) {
                        $reason = trim($reason);
                        if (empty($reason)) continue;

                        // Check if this is a custom reason (doesn't match existing pattern)
                        $existingReason = ReasonForEncounter::where(function($query) use ($reason) {
                            $query->where('code', 'LIKE', $reason.'%')
                                ->orWhereRaw("CONCAT(code, '-', name) = ?", [$reason]);
                        })->first();

                        if (!$existingReason && !empty($reason)) {
                            // Create custom reason
                            ReasonForEncounter::createCustomReason($reason);
                        }

                        $processedReasons[] = $reason;
                    }

                    $reasonsString = implode(',', $processedReasons);
                }
            }

            // Update encounter
            $encounter->update([
                'notes' => $request->notes,
                'reasons_for_encounter' => $reasonsString,
                'reasons_for_encounter_comment_1' => $comment1,
                'reasons_for_encounter_comment_2' => $comment2,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Encounter note updated successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Encounter update failed: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update encounter: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        try {
            $doctor = Staff::where('user_id', Auth::id())->first();
            $patient = patient::find(request()->get('patient_id'));
            $clinic = Clinic::find($doctor->clinic_id);
            $req_entry = ProductOrServiceRequest::find(request()->get('req_entry_id'));
            $admission_exists = AdmissionRequest::where('patient_id', request()->get('patient_id'))->where('discharged', 0)->first();
            $queue_id = $request->get('queue_id');

            $reasons_for_encounter_list = ReasonForEncounter::all();
            $reasons_for_encounter_cat_list = ReasonForEncounter::distinct()->get(['category']);
            $reasons_for_encounter_sub_cat_list = ReasonForEncounter::distinct()->get(['sub_category', 'category']);

            // dd($reasons_for_encounter_cat_list);

            // Find or create encounter specific to this service request/queue
            $encounterQuery = Encounter::where('doctor_id', $doctor->id)
                ->where('patient_id', $patient->id)
                ->where('completed', false);

            // If we have a service request (req_entry_id), use it to identify the encounter
            if ($req_entry) {
                $encounterQuery->where('service_request_id', $req_entry->id);
            }

            $encounter = $encounterQuery->first();

            if (!$encounter) {
                $encounter = new Encounter();
                $encounter->doctor_id = $doctor->id;
                $encounter->service_request_id = $req_entry ? $req_entry->id : null;
                $encounter->service_id = $req_entry ? $req_entry->service_id : null;
                $encounter->patient_id = $patient->id;
                $encounter->save();
            } else {
                // Update existing encounter (in case doctor or service changed)
                $encounter->doctor_id = $doctor->id;
                $encounter->service_request_id = $req_entry ? $req_entry->id : null;
                $encounter->service_id = $req_entry ? $req_entry->service_id : null;
                $encounter->patient_id = $patient->id;
                $encounter->update();
            }

            if ($encounter) {
                if (null != $admission_exists) {
                    $admission_exists_ = 1;
                } else {
                    $admission_exists_ = 0;
                }

                // dd($admission_exists_);

                if ($request->get('admission_req_id') != '' || $admission_exists_ == 1) {
                    $admission_request = AdmissionRequest::where('id', $request->admission_req_id)->where('discharged', 0)->first() ?? $admission_exists;
                    // for nursing notes
                    $patient_id = $patient->id;
                    $patient = patient::find($patient_id);

                    $observation_note = NursingNote::with(['patient', 'createdBy', 'type'])
                        ->where('patient_id', $patient_id)
                        ->where('completed', false)
                        ->where('nursing_note_type_id', 1)
                        ->first() ?? null;

                    $observation_note_template = NursingNoteType::find(1);

                    $treatment_sheet = NursingNote::with(['patient', 'createdBy', 'type'])
                        ->where('patient_id', $patient_id)
                        ->where('completed', false)
                        ->where('nursing_note_type_id', 2)
                        ->first() ?? null;

                    $treatment_sheet_template = NursingNoteType::find(2);

                    $io_chart = NursingNote::with(['patient', 'createdBy', 'type'])
                        ->where('patient_id', $patient_id)
                        ->where('completed', false)
                        ->where('nursing_note_type_id', 3)
                        ->first() ?? null;

                    $io_chart_template = NursingNoteType::find(3);
                    // dd($io_chart_template);

                    $labour_record = NursingNote::with(['patient', 'createdBy', 'type'])
                        ->where('patient_id', $patient_id)
                        ->where('completed', false)
                        ->where('nursing_note_type_id', 4)
                        ->first() ?? null;

                    $labour_record_template = NursingNoteType::find(4);

                    $others_record = NursingNote::with(['patient', 'createdBy', 'type'])
                        ->where('patient_id', $patient_id)
                        ->where('completed', false)
                        ->where('nursing_note_type_id', 5)
                        ->first() ?? null;

                    $others_record_template = NursingNoteType::find(5);

                    return view('admin.doctors.new_encounter')->with([
                        'patient' => $patient,
                        'doctor' => $doctor,
                        'clinic' => $clinic,
                        'req_entry' => $req_entry,
                        'admission_request' => $admission_request,
                        'observation_note' => $observation_note,
                        'treatment_sheet' => $treatment_sheet,
                        'io_chart' => $io_chart,
                        'labour_record' => $labour_record,
                        'others_record' => $others_record,
                        'observation_note_template' => $observation_note_template,
                        'treatment_sheet_template' => $treatment_sheet_template,
                        'io_chart_template' => $io_chart_template,
                        'labour_record_template' => $labour_record_template,
                        'others_record_template' => $others_record_template,
                        'admission_exists_' => $admission_exists_,
                        'encounter' => $encounter,
                        'reasons_for_encounter_list' => $reasons_for_encounter_list,
                        'reasons_for_encounter_cat_list' => $reasons_for_encounter_cat_list,
                        'reasons_for_encounter_sub_cat_list' => $reasons_for_encounter_sub_cat_list,
                    ]);
                } else {
                    return view('admin.doctors.new_encounter')->with([
                        'patient' => $patient,
                        'doctor' => $doctor,
                        'clinic' => $clinic,
                        'req_entry' => $req_entry,
                        'admission_exists_' => $admission_exists_,
                        'encounter' => $encounter,
                        'reasons_for_encounter_list' => $reasons_for_encounter_list,
                        'reasons_for_encounter_cat_list' => $reasons_for_encounter_cat_list,
                        'reasons_for_encounter_sub_cat_list' => $reasons_for_encounter_sub_cat_list,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['exception' => $e]);

            return redirect()->back()->withInput()->withMessage('An error occurred ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // dd($request->all());
        try {
            if (appsettings('requirediagnosis') == 1) {
                $request->validate([
                    'doctor_diagnosis' => 'required|string',
                    'consult_presc_dose' => 'nullable|array|required_with:consult_presc_id',
                    'consult_presc_id' => 'nullable|array|required_with:consult_presc_dose',
                    'consult_invest_note' => 'nullable|array',
                    'consult_invest_id' => 'nullable|array',
                    'consult_presc_dose.*' => 'required_with:consult_presc_dose',
                    'consult_presc_id.*' => 'required_with:consult_presc_id',
                    'consult_invest_note.*' => 'nullable',
                    'consult_invest_id.*' => 'required_with:consult_invest_id',
                    'admit_note' => 'nullable|string',
                    'consult_admit' => 'nullable',
                    'req_entry_service_id' => 'required',
                    'req_entry_id' => 'required',
                    'patient_id' => 'required',
                    'queue_id' => 'required',
                    'end_consultation' => 'nullable',
                    'encounter_id' => 'required',
                    'reasons_for_encounter' => 'required',
                    'reasons_for_encounter_comment_1' => 'required',
                    'reasons_for_encounter_comment_2' => 'required',
                ]);
            } else {
                $request->validate([
                    'doctor_diagnosis' => 'required|string',
                    'consult_presc_dose' => 'nullable|array|required_with:consult_presc_id',
                    'consult_presc_id' => 'nullable|array|required_with:consult_presc_dose',
                    'consult_invest_note' => 'nullable|array',
                    'consult_invest_id' => 'nullable|array',
                    'consult_imaging_note' => 'nullable|array',
                    'consult_imaging_id' => 'nullable|array',
                    'consult_presc_dose.*' => 'required_with:consult_presc_dose',
                    'consult_presc_id.*' => 'required_with:consult_presc_id',
                    'consult_invest_note.*' => 'nullable',
                    'consult_invest_id.*' => 'required_with:consult_invest_id',
                    'consult_imaging_note.*' => 'nullable',
                    'consult_imaging_id.*' => 'required_with:consult_imaging_id',
                    'admit_note' => 'nullable|string',
                    'consult_admit' => 'nullable',
                    'req_entry_service_id' => 'required',
                    'req_entry_id' => 'required',
                    'patient_id' => 'required',
                    'queue_id' => 'required',
                    'end_consultation' => 'nullable',
                    'encounter_id' => 'required',
                ]);
            }

            if (isset($request->consult_presc_id) && isset($request->consult_presc_dose)) {
                if (count($request->consult_presc_id) !== count($request->consult_presc_dose)) {
                    $msg = 'Please fill out dosages for all selected products';

                    return redirect()->back()->withInput()->withMessage($msg)->withMessageType('warning');
                }
            }

            if (isset($request->consult_invest_id) && isset($request->consult_invest_note)) {
                if (count($request->consult_invest_id) !== count($request->consult_invest_note)) {
                    $msg = 'Please fill out notes for all selected services';

                    return redirect()->back()->withInput()->withMessage($msg)->withMessageType('warning');
                }
            }

            if (isset($request->consult_imaging_id) && isset($request->consult_imaging_note)) {
                if (count($request->consult_imaging_id) !== count($request->consult_imaging_note)) {
                    $msg = 'Please fill out notes for all selected imaging services';

                    return redirect()->back()->withInput()->withMessage($msg)->withMessageType('warning');
                }
            }

            // Find the patient
            $patient = patient::findOrFail($request->patient_id);

            if (null != $patient->dhis_consult_enrollment_id && null != $patient->dhis_consult_tracker_id && $patient->dhis_consult_enrollment_id != '' && $patient->dhis_consult_tracker_id != '') {
                // Get current time in the required format
                $currentTime = Carbon::now()->format('Y-m-d\TH:i:s.000');

                if (appsettings('goonline', 0) == 1) {

                    // Prepare the data values for reasons for encounter
                    // Loop through each reason for encounter and create an event
                    foreach ($request->reasons_for_encounter as $reason) {
                        $dataValues = [
                            [
                                'dataElement' => appsettings('dhis_tracked_entity_program_event_dataelement'),
                                'value' => $reason,
                            ],
                        ];

                        Http::withBasicAuth('admin', 'district')
                            ->post(appsettings('dhis_api_url') . '/tracker?importStrategy=CREATE&async=false', [
                                'events' => [
                                    [
                                        'dataValues' => $dataValues,
                                        'enrollmentStatus' => 'ACTIVE',
                                        'occurredAt' => $currentTime,
                                        'orgUnit' => appsettings('dhis_org_unit'),
                                        'program' => appsettings('dhis_tracked_entity_program'),
                                        'programStage' => appsettings('dhis_tracked_entity_program_stage2'),
                                        'scheduledAt' => $currentTime,
                                        'status' => 'COMPLETED',
                                        'enrollment' => $patient->dhis_consult_enrollment_id,
                                        'trackedEntity' => $patient->dhis_consult_tracker_id,
                                    ],
                                ],
                            ]);
                    }
                }
            }

            DB::beginTransaction();
            $encounter = Encounter::where('id', $request->encounter_id)->first();
            if ($request->req_entry_service_id == null || $request->req_entry_service_id == 'ward_round') {
                $encounter->service_id = null;
                $encounter->service_request_id = null;
            } else {
                $encounter->service_id = $request->req_entry_service_id;
                $encounter->service_request_id = $request->req_entry_id;
            }
            if ($request->admission_request_id != '') {
                $encounter->admission_request_id = $request->admission_request_id;
            }
            $encounter->doctor_id = Auth::id();
            $encounter->patient_id = $request->patient_id;
            if (appsettings('requirediagnosis', 0)) {
                $encounter->reasons_for_encounter = implode(',', $request->reasons_for_encounter);
                $encounter->reasons_for_encounter_comment_1 = $request->reasons_for_encounter_comment_1;
                $encounter->reasons_for_encounter_comment_2 = $request->reasons_for_encounter_comment_2;
            }
            $encounter->notes = $request->doctor_diagnosis;
            $encounter->completed = true;
            $encounter->update();
            // dd($encounter);
            if (isset($request->consult_invest_id) && count($request->consult_invest_id) > 0) {
                for ($r = 0; $r < count($request->consult_invest_id); ++$r) {
                    $invest = new LabServiceRequest();
                    $invest->service_id = $request->consult_invest_id[$r];
                    $invest->note = $request->consult_invest_note[$r];
                    $invest->encounter_id = $encounter->id;
                    $invest->patient_id = $request->patient_id;
                    $invest->doctor_id = Auth::id();
                    $invest->save();

                    $req_entr = new ProductOrServiceRequest();
                }
            }

            if (isset($request->consult_imaging_id) && count($request->consult_imaging_id) > 0) {
                for ($r = 0; $r < count($request->consult_imaging_id); ++$r) {
                    $imaging = new ImagingServiceRequest();
                    $imaging->service_id = $request->consult_imaging_id[$r];
                    $imaging->note = $request->consult_imaging_note[$r];
                    $imaging->encounter_id = $encounter->id;
                    $imaging->patient_id = $request->patient_id;
                    $imaging->doctor_id = Auth::id();
                    $imaging->save();
                }
            }

            if (isset($request->consult_presc_id) && count($request->consult_presc_id) > 0) {
                for ($r = 0; $r < count($request->consult_presc_id); ++$r) {
                    $presc = new ProductRequest();
                    $presc->product_id = $request->consult_presc_id[$r];
                    $presc->dose = $request->consult_presc_dose[$r];
                    $presc->encounter_id = $encounter->id;
                    $presc->patient_id = $request->patient_id;
                    $presc->doctor_id = Auth::id();
                    $presc->save();
                }
            }

            if ($request->queue_id != 'ward_round') {
                $queue = DoctorQueue::where('id', $request->queue_id)->update([
                    'status' => (($request->end_consultation && $request->end_consultation == '1') ? 3 : 2),
                ]);
            }

            if ($request->consult_admit && $request->consult_admit == '1') {
                $admit = new AdmissionRequest();
                $admit->encounter_id = $encounter->id;
                $admit->doctor_id = Auth::id();
                $admit->patient_id = $request->patient_id;
                $admit->note = $request->admit_note;
                $admit->save();
            }
            DB::commit();

            if ($request->queue_id != 'ward_round' && $request->queue_id != '') {
                // Send to CoreHealth SuperAdmin
                $queue = DoctorQueue::where('id', $request->queue_id)->first();
                // dd($queue);

                if (appsettings('goonline', 0) == 1) {
                    $response = Http::withBasicAuth(
                        appsettings('COREHMS_SUPERADMIN_USERNAME'),
                        appsettings('COREHMS_SUPERADMIN_PASS')
                    )->withHeaders([
                        'Content-Type' => 'application/json',
                    ])->post(appsettings('COREHMS_SUPERADMIN_URL') . '/event-notification.php?notification_type=consultation', [
                        'category' => $queue->clinic->name,
                        'health_case' => $request->reasons_for_encounter[0] ?? null
                    ]);

                    Log::info("sent api request For encounter, ", [$response->body()]);
                }
            }
            return redirect()->route('encounters.index')->with(['message' => 'Encounter Notes Saved Successfully', 'message_type' => 'success']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage(), ['exception' => $e]);

            return redirect()->back()->withInput()->withMessage('An error occurred ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Encounter $encounter) {}

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Encounter $encounter) {}

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Encounter $encounter) {}

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Encounter $encounter) {}

    /**
     * Get file icon based on file type
     *
     * @param string $fileType
     * @return string
     */
    private function getFileIcon($fileType)
    {
        if (strpos($fileType, 'pdf') !== false) {
            return "<i class='mdi mdi-file-pdf text-danger'></i>";
        } elseif (strpos($fileType, 'word') !== false || strpos($fileType, 'doc') !== false) {
            return "<i class='mdi mdi-file-word text-primary'></i>";
        } elseif (strpos($fileType, 'image') !== false) {
            return "<i class='mdi mdi-file-image text-success'></i>";
        } else {
            return "<i class='mdi mdi-file text-secondary'></i>";
        }
    }

    /**
     * Save diagnosis and notes for encounter via AJAX
     */
    public function saveDiagnosis(Request $request, Encounter $encounter)
    {
        try {
            // Check if diagnosis is applicable
            $diagnosisApplicable = $request->input('diagnosis_applicable', true);

            $validationRules = [
                'doctor_diagnosis' => 'nullable|string',
                'diagnosis_applicable' => 'nullable|boolean',
            ];

            // Only require diagnosis fields if diagnosis is applicable
            if (appsettings('requirediagnosis', 0) && $diagnosisApplicable) {
                $validationRules['reasons_for_encounter'] = 'required|array|min:1';
                $validationRules['reasons_for_encounter_comment_1'] = 'required|string';
                $validationRules['reasons_for_encounter_comment_2'] = 'required|string';
            }

            $validated = $request->validate($validationRules);

            // Only save diagnosis fields if diagnosis is applicable
            if (appsettings('requirediagnosis', 0) && $diagnosisApplicable && $request->has('reasons_for_encounter')) {
                $encounter->reasons_for_encounter = implode(',', $request->reasons_for_encounter);
                $encounter->reasons_for_encounter_comment_1 = $request->reasons_for_encounter_comment_1;
                $encounter->reasons_for_encounter_comment_2 = $request->reasons_for_encounter_comment_2;

                // Process custom reasons
                $reasonsArray = $request->reasons_for_encounter;
                foreach ($reasonsArray as $reason) {
                    $reason = trim($reason);
                    if (empty($reason)) continue;

                    // Check if custom reason (starts with 'custom:')
                    if (strpos($reason, 'custom:') === 0) {
                        $customReasonText = str_replace('custom:', '', $reason);
                        ReasonForEncounter::createCustomReason($customReasonText);
                    }
                }
            } else {
                // Clear diagnosis fields when not applicable
                $encounter->reasons_for_encounter = null;
                $encounter->reasons_for_encounter_comment_1 = null;
                $encounter->reasons_for_encounter_comment_2 = null;
            }

            $encounter->notes = $request->doctor_diagnosis ?? '';
            $encounter->save();

            return response()->json([
                'success' => true,
                'message' => 'Diagnosis and notes saved successfully'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $e->validator->errors()->all()),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving diagnosis: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save lab service requests for encounter via AJAX
     */
    public function saveLabs(Request $request, Encounter $encounter)
    {
        try {
            $request->validate([
                'consult_invest_id' => 'required|array',
                'consult_invest_id.*' => 'required|integer',
                'consult_invest_note' => 'required|array',
                'consult_invest_note.*' => 'nullable|string',
            ]);

            if (count($request->consult_invest_id) !== count($request->consult_invest_note)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please fill out notes for all selected services'
                ], 422);
            }

            // Delete existing lab requests for this encounter (to avoid duplicates on re-save)
            LabServiceRequest::where('encounter_id', $encounter->id)->delete();

            // Save new lab requests
            for ($r = 0; $r < count($request->consult_invest_id); ++$r) {
                $invest = new LabServiceRequest();
                $invest->service_id = $request->consult_invest_id[$r];
                $invest->note = $request->consult_invest_note[$r];
                $invest->encounter_id = $encounter->id;
                $invest->patient_id = $encounter->patient_id;
                $invest->doctor_id = Auth::id();
                $invest->save();
            }

            return response()->json([
                'success' => true,
                'message' => count($request->consult_invest_id) . ' lab service(s) saved successfully',
                'count' => count($request->consult_invest_id)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving lab requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save imaging service requests for encounter via AJAX
     */
    public function saveImaging(Request $request, Encounter $encounter)
    {
        try {
            $request->validate([
                'consult_imaging_id' => 'required|array',
                'consult_imaging_id.*' => 'required|integer',
                'consult_imaging_note' => 'required|array',
                'consult_imaging_note.*' => 'nullable|string',
            ]);

            if (count($request->consult_imaging_id) !== count($request->consult_imaging_note)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please fill out notes for all selected imaging services'
                ], 422);
            }

            // Delete existing imaging requests for this encounter
            ImagingServiceRequest::where('encounter_id', $encounter->id)->delete();

            // Save new imaging requests
            for ($r = 0; $r < count($request->consult_imaging_id); ++$r) {
                $imaging = new ImagingServiceRequest();
                $imaging->service_id = $request->consult_imaging_id[$r];
                $imaging->note = $request->consult_imaging_note[$r];
                $imaging->encounter_id = $encounter->id;
                $imaging->patient_id = $encounter->patient_id;
                $imaging->doctor_id = Auth::id();
                $imaging->save();
            }

            return response()->json([
                'success' => true,
                'message' => count($request->consult_imaging_id) . ' imaging service(s) saved successfully',
                'count' => count($request->consult_imaging_id)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving imaging requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save prescription/medications for encounter via AJAX
     */
    public function savePrescriptions(Request $request, Encounter $encounter)
    {
        try {
            $request->validate([
                'consult_presc_id' => 'required|array|min:1',
                'consult_presc_id.*' => 'required|integer',
                'consult_presc_dose' => 'required|array|min:1',
                'consult_presc_dose.*' => 'nullable|string',
            ]);

            if (count($request->consult_presc_id) !== count($request->consult_presc_dose)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mismatch between products and dosages'
                ], 422);
            }

            // Check for empty doses and warn
            $emptyDoses = [];
            foreach ($request->consult_presc_dose as $index => $dose) {
                if (empty(trim($dose))) {
                    $emptyDoses[] = $index + 1;
                }
            }

            // Delete existing prescriptions for this encounter
            ProductRequest::where('encounter_id', $encounter->id)->delete();

            // Save new prescriptions
            for ($r = 0; $r < count($request->consult_presc_id); ++$r) {
                $presc = new ProductRequest();
                $presc->product_id = $request->consult_presc_id[$r];
                $presc->dose = $request->consult_presc_dose[$r] ?? '';
                $presc->encounter_id = $encounter->id;
                $presc->patient_id = $encounter->patient_id;
                $presc->doctor_id = Auth::id();
                $presc->save();
            }

            $message = count($request->consult_presc_id) . ' prescription(s) saved successfully';
            if (!empty($emptyDoses)) {
                $message .= '. Warning: Item(s) ' . implode(', ', $emptyDoses) . ' have no dosage specified.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'count' => count($request->consult_presc_id),
                'empty_doses' => $emptyDoses
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $e->validator->errors()->all()),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving prescriptions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Finalize encounter - mark as complete and handle admission/queue
     */
    public function finalizeEncounter(Request $request, Encounter $encounter)
    {
        try {
            $request->validate([
                'end_consultation' => 'nullable|boolean',
                'consult_admit' => 'nullable|boolean',
                'admit_note' => 'nullable|string',
                'queue_id' => 'required',
            ]);

            DB::beginTransaction();

            // Mark encounter as complete
            $encounter->completed = true;
            $encounter->doctor_id = Auth::id();
            $encounter->save();

            // Handle queue status
            if ($request->queue_id != 'ward_round') {
                DoctorQueue::where('id', $request->queue_id)->update([
                    'status' => (($request->end_consultation && $request->end_consultation == '1') ? 3 : 2),
                ]);
            }

            // Handle admission request
            if ($request->consult_admit && $request->consult_admit == '1') {
                $admit = new AdmissionRequest();
                $admit->encounter_id = $encounter->id;
                $admit->patient_id = $encounter->patient_id;
                $admit->note = $request->admit_note;
                $admit->doctor_id = Auth::id();
                $admit->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Encounter completed successfully',
                'redirect' => route('encounters.index')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error finalizing encounter: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get encounter summary with all saved data
     */
    public function getEncounterSummary(Encounter $encounter)
    {
        try {
            // Get diagnosis/notes
            $diagnosis = [
                'saved' => !empty($encounter->notes) || !empty($encounter->reasons_for_encounter),
                'notes' => $encounter->notes,
                'reasons' => $encounter->reasons_for_encounter,
                'comment_1' => $encounter->reasons_for_encounter_comment_1,
                'comment_2' => $encounter->reasons_for_encounter_comment_2,
            ];

            // Get lab requests
            $labs = LabServiceRequest::where('encounter_id', $encounter->id)
                ->with('service')
                ->get()
                ->map(function($lab) {
                    return [
                        'id' => $lab->id,
                        'name' => $lab->service->service_name ?? 'N/A',
                        'code' => $lab->service->service_code ?? '',
                        'note' => $lab->note,
                        'status' => $lab->status ?? 1,
                        'created_at' => $lab->created_at->format('M d, Y H:i')
                    ];
                });

            // Get imaging requests
            $imaging = ImagingServiceRequest::where('encounter_id', $encounter->id)
                ->with('service')
                ->get()
                ->map(function($img) {
                    return [
                        'id' => $img->id,
                        'name' => $img->service->service_name ?? 'N/A',
                        'code' => $img->service->service_code ?? '',
                        'note' => $img->note,
                        'status' => $img->status ?? 1,
                        'created_at' => $img->created_at->format('M d, Y H:i')
                    ];
                });

            // Get prescriptions
            $prescriptions = ProductRequest::where('encounter_id', $encounter->id)
                ->with('product')
                ->get()
                ->map(function($presc) {
                    return [
                        'id' => $presc->id,
                        'name' => $presc->product->product_name ?? 'N/A',
                        'dose' => $presc->dose,
                        'status' => $presc->status ?? 1,
                        'created_at' => $presc->created_at->format('M d, Y H:i')
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'diagnosis' => $diagnosis,
                    'labs' => $labs,
                    'imaging' => $imaging,
                    'prescriptions' => $prescriptions,
                    'encounter' => [
                        'id' => $encounter->id,
                        'completed' => $encounter->completed,
                        'created_at' => $encounter->created_at->format('M d, Y H:i'),
                        'updated_at' => $encounter->updated_at->format('M d, Y H:i'),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching encounter summary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a lab service request (soft delete)
     */
    public function deleteLab(Request $request, Encounter $encounter, LabServiceRequest $lab)
    {
        try {
            // Validate that lab belongs to this encounter
            if ($lab->encounter_id != $encounter->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'This lab request does not belong to this encounter'
                ], 403);
            }

            // Validate that current user is the creator
            if ($lab->doctor_id != Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only delete your own requests'
                ], 403);
            }

            // Validate that request is not yet processed (status 1 or 2)
            if ($lab->status > 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete: Lab results have already been entered',
                    'reason' => 'results_entered'
                ], 403);
            }

            // Validate that request has not been billed
            if ($lab->billed_by || $lab->billed_date) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete: This request has already been billed',
                    'reason' => 'already_billed'
                ], 403);
            }

            // Validate deletion reason
            $request->validate([
                'reason' => 'required|string|max:500'
            ]);

            // Soft delete the request
            $lab->deleted_by = Auth::id();
            $lab->deletion_reason = $request->reason;
            $lab->save();
            $lab->delete();

            return response()->json([
                'success' => true,
                'message' => 'Lab request deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting lab request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an imaging service request (soft delete)
     */
    public function deleteImaging(Request $request, Encounter $encounter, ImagingServiceRequest $imaging)
    {
        try {
            // Validate that imaging belongs to this encounter
            if ($imaging->encounter_id != $encounter->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'This imaging request does not belong to this encounter'
                ], 403);
            }

            // Validate that current user is the creator
            if ($imaging->doctor_id != Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only delete your own requests'
                ], 403);
            }

            // Validate that request is not yet processed (status 1 or 2)
            if ($imaging->status > 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete: Imaging results have already been entered',
                    'reason' => 'results_entered'
                ], 403);
            }

            // Validate that request has not been billed
            if ($imaging->billed_by || $imaging->billed_date) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete: This request has already been billed',
                    'reason' => 'already_billed'
                ], 403);
            }

            // Validate deletion reason
            $request->validate([
                'reason' => 'required|string|max:500'
            ]);

            // Soft delete the request
            $imaging->deleted_by = Auth::id();
            $imaging->deletion_reason = $request->reason;
            $imaging->save();
            $imaging->delete();

            return response()->json([
                'success' => true,
                'message' => 'Imaging request deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting imaging request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a prescription request (soft delete)
     */
    public function deletePrescription(Request $request, Encounter $encounter, ProductRequest $prescription)
    {
        try {
            // Validate that prescription belongs to this encounter
            if ($prescription->encounter_id != $encounter->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'This prescription does not belong to this encounter'
                ], 403);
            }

            // Validate that current user is the creator
            if ($prescription->doctor_id != Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only delete your own prescriptions'
                ], 403);
            }

            // Validate that prescription is not yet dispensed (status 1 or 2)
            if ($prescription->status > 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete: This prescription has already been dispensed',
                    'reason' => 'already_dispensed'
                ], 403);
            }

            // Validate that prescription has not been billed
            if ($prescription->billed_by || $prescription->billed_date) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete: This prescription has already been billed',
                    'reason' => 'already_billed'
                ], 403);
            }

            // Validate deletion reason
            $request->validate([
                'reason' => 'required|string|max:500'
            ]);

            // Soft delete the prescription
            $prescription->deleted_by = Auth::id();
            $prescription->deletion_reason = $request->reason;
            $prescription->save();
            $prescription->delete();

            return response()->json([
                'success' => true,
                'message' => 'Prescription deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting prescription: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Live search for reasons for encounter (ICPC-2 diagnosis codes)
     */
    public function liveSearchReasons(Request $request)
    {
        $request->validate([
            'term' => 'required|string|min:2'
        ]);

        $searchTerm = $request->term;

        $reasons = ReasonForEncounter::where(function ($query) use ($searchTerm) {
            $query->where('name', 'LIKE', "%{$searchTerm}%")
                ->orWhere('code', 'LIKE', "%{$searchTerm}%")
                ->orWhere('category', 'LIKE', "%{$searchTerm}%")
                ->orWhere('sub_category', 'LIKE', "%{$searchTerm}%");
        })
        ->orderByRaw("CASE WHEN code LIKE '{$searchTerm}%' THEN 1 WHEN name LIKE '{$searchTerm}%' THEN 2 ELSE 3 END")
        ->orderBy('code', 'ASC')
        ->limit(20)
        ->get()
        ->map(function ($reason) {
            return [
                'id' => $reason->id,
                'code' => $reason->code,
                'name' => $reason->name,
                'category' => $reason->category,
                'sub_category' => $reason->sub_category,
                'display' => $reason->code . ' - ' . $reason->name,
                'value' => $reason->code . '-' . $reason->name // For compatibility
            ];
        });

        return response()->json($reasons);
    }

    /**
     * Save procedure requests for encounter via AJAX
     */
    public function saveProcedures(Request $request, Encounter $encounter)
    {
        try {
            $request->validate([
                'procedures' => 'required|array|min:1',
                'procedures.*.service_id' => 'required|integer|exists:services,id',
                'procedures.*.priority' => 'required|in:routine,urgent,emergency',
                'procedures.*.scheduled_date' => 'nullable|date',
                'procedures.*.pre_notes' => 'nullable|string|max:2000',
            ]);

            $savedCount = 0;

            foreach ($request->procedures as $procedureData) {
                $service = \App\Models\service::with('price')->find($procedureData['service_id']);

                if (!$service) {
                    continue;
                }

                // Create the procedure record
                $procedure = new \App\Models\Procedure();
                $procedure->service_id = $service->id;
                $procedure->encounter_id = $encounter->id;
                $procedure->patient_id = $encounter->patient_id;
                $procedure->requested_by = Auth::id();
                $procedure->requested_on = now();
                $procedure->priority = $procedureData['priority'];
                $procedure->procedure_status = \App\Models\Procedure::STATUS_REQUESTED;
                $procedure->pre_notes = $procedureData['pre_notes'] ?? null;
                $procedure->pre_notes_by = $procedureData['pre_notes'] ? Auth::id() : null;

                if (!empty($procedureData['scheduled_date'])) {
                    $procedure->scheduled_date = $procedureData['scheduled_date'];
                    $procedure->procedure_status = \App\Models\Procedure::STATUS_SCHEDULED;
                }

                // Get procedure definition if linked
                if ($service->procedureDefinition) {
                    $procedure->procedure_definition_id = $service->procedureDefinition->id;
                }

                $procedure->save();

                // Create billing entry (ProductOrServiceRequest)
                $basePrice = optional($service->price)->sale_price ?? 0;

                // Check HMO coverage
                $coverage = null;
                try {
                    $coverage = \App\Helpers\HmoHelper::applyHmoTariff($encounter->patient_id, null, $service->id);
                } catch (\Exception $e) {
                    $coverage = null;
                }

                // Get patient's user_id for the billing entry
                $patient = \App\Models\patient::find($encounter->patient_id);

                $billingEntry = new \App\Models\ProductOrServiceRequest();
                $billingEntry->type = 'service';
                $billingEntry->service_id = $service->id;
                $billingEntry->user_id = $patient->user_id;
                $billingEntry->staff_user_id = Auth::id();
                $billingEntry->encounter_id = $encounter->id;
                $billingEntry->admission_request_id = $encounter->admission_request_id;
                $billingEntry->created_by = Auth::id();
                $billingEntry->order_date = now();

                if ($coverage && $coverage['coverage_mode'] === 'hmo') {
                    $billingEntry->amount = $coverage['payable_amount'];
                    $billingEntry->claims_amount = $coverage['claims_amount'];
                    $billingEntry->coverage_mode = 'hmo';
                    $billingEntry->hmo_id = $coverage['hmo_id'] ?? null;
                } else {
                    $billingEntry->amount = $basePrice;
                    $billingEntry->claims_amount = 0;
                    $billingEntry->coverage_mode = 'cash';
                }

                $billingEntry->save();

                // Link billing to procedure
                $procedure->product_or_service_request_id = $billingEntry->id;
                $procedure->save();

                $savedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => $savedCount . ' procedure(s) requested successfully',
                'count' => $savedCount
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(', ', $e->validator->errors()->all())
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving procedures: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get procedure history list for DataTable
     */
    public function procedureHistoryList(Request $request, $patient_id)
    {
        $procedures = \App\Models\Procedure::where('patient_id', $patient_id)
            ->with(['service', 'requestedByUser', 'encounter', 'productOrServiceRequest'])
            ->orderBy('requested_on', 'DESC')
            ->get();

        return DataTables::of($procedures)
            ->addColumn('procedure', function ($proc) {
                $name = optional($proc->service)->service_name ?? 'Unknown';
                $code = optional($proc->service)->service_code ?? '';
                return "<strong>{$name}</strong><br><small class='text-muted'>{$code}</small>";
            })
            ->addColumn('priority', function ($proc) {
                $priorityClass = "priority-{$proc->priority}";
                $label = ucfirst($proc->priority);
                return "<span class='priority-badge {$priorityClass}'>{$label}</span>";
            })
            ->addColumn('status', function ($proc) {
                $statusClass = "status-{$proc->procedure_status}";
                $label = \App\Models\Procedure::STATUSES[$proc->procedure_status] ?? ucfirst($proc->procedure_status);
                return "<span class='status-badge {$statusClass}'>{$label}</span>";
            })
            ->addColumn('date', function ($proc) {
                $requestedDate = $proc->requested_on ? $proc->requested_on->format('d M Y H:i') : 'N/A';
                $scheduledDate = $proc->scheduled_date ? $proc->scheduled_date->format('d M Y') : null;
                $html = "<small>{$requestedDate}</small>";
                if ($scheduledDate) {
                    $html .= "<br><small class='text-info'><i class='fa fa-calendar'></i> Scheduled: {$scheduledDate}</small>";
                }
                return $html;
            })
            ->addColumn('actions', function ($proc) {
                $detailsUrl = route('patient-procedures.show', $proc->id);
                $openDetailsBtn = "<a href='{$detailsUrl}' target='_blank' class='btn btn-sm btn-primary' title='View Details'><i class='fa fa-external-link-alt'></i> Details</a>";

                $deleteBtn = '';

                // Delete only available to creator and within edit window
                $currentUserId = auth()->id();
                $isCreator = $proc->requested_by === $currentUserId;
                $editWindowMinutes = appsettings('note_edit_window', 30);
                $withinEditWindow = $proc->created_at && now()->diffInMinutes($proc->created_at) <= $editWindowMinutes;

                // Only allow deletion for requested status, by creator, within edit window
                if ($proc->procedure_status === \App\Models\Procedure::STATUS_REQUESTED && $isCreator && $withinEditWindow) {
                    $serviceName = addslashes(optional($proc->service)->service_name ?? 'Procedure');
                    $deleteBtn = " <button class='btn btn-sm btn-outline-danger' onclick='deleteProcedureRequest({$proc->id}, {$proc->encounter_id}, \"{$serviceName}\")' title='Delete Request'><i class='fa fa-trash'></i></button>";
                }

                return "<div class='btn-group btn-group-sm' role='group'>" . $openDetailsBtn . $deleteBtn . "</div>";
            })
            ->rawColumns(['procedure', 'priority', 'status', 'date', 'actions'])
            ->make(true);
    }

    /**
     * Get procedure details
     */
    public function getProcedureDetails(\App\Models\Procedure $procedure)
    {
        $procedure->load([
            'service',
            'procedureDefinition',
            'requestedByUser',
            'billedByUser',
            'preNotesBy',
            'postNotesBy',
            'cancelledByUser',
            'teamMembers.user',
            'notes.createdBy',
            'productOrServiceRequest'
        ]);

        return response()->json($procedure);
    }

    /**
     * Delete a procedure request
     */
    public function deleteProcedure(Request $request, Encounter $encounter, \App\Models\Procedure $procedure)
    {
        try {
            // Verify the procedure belongs to this encounter
            if ($procedure->encounter_id !== $encounter->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Procedure does not belong to this encounter'
                ], 403);
            }

            // Only allow deletion of requested/scheduled procedures
            if (!in_array($procedure->procedure_status, [\App\Models\Procedure::STATUS_REQUESTED, \App\Models\Procedure::STATUS_SCHEDULED])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete a procedure that is already in progress or completed',
                    'reason' => 'Procedure status: ' . $procedure->procedure_status
                ], 403);
            }

            // Validate deletion reason
            $request->validate([
                'reason' => 'required|string|max:500'
            ]);

            // Delete associated billing entry if exists
            if ($procedure->product_or_service_request_id) {
                $billingEntry = \App\Models\ProductOrServiceRequest::find($procedure->product_or_service_request_id);
                if ($billingEntry && !$billingEntry->paid) {
                    $billingEntry->delete();
                }
            }

            // Soft delete the procedure
            $procedure->cancellation_reason = $request->reason;
            $procedure->cancelled_by = Auth::id();
            $procedure->cancelled_at = now();
            $procedure->procedure_status = \App\Models\Procedure::STATUS_CANCELLED;
            $procedure->save();
            $procedure->delete();

            return response()->json([
                'success' => true,
                'message' => 'Procedure request deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting procedure: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update procedure details (status, scheduled date, etc.)
     */
    public function updateProcedure(Request $request, \App\Models\Procedure $procedure)
    {
        try {
            $request->validate([
                'procedure_status' => 'sometimes|in:' . implode(',', array_keys(\App\Models\Procedure::STATUSES)),
                'priority' => 'sometimes|in:' . implode(',', array_keys(\App\Models\Procedure::PRIORITIES)),
                'scheduled_date' => 'sometimes|nullable|date',
                'scheduled_time' => 'sometimes|nullable|date_format:H:i',
                'operating_room' => 'sometimes|nullable|string|max:100',
                'outcome' => 'sometimes|nullable|in:' . implode(',', array_keys(\App\Models\Procedure::OUTCOMES)),
                'outcome_notes' => 'sometimes|nullable|string|max:2000',
                'pre_notes' => 'sometimes|nullable|string|max:2000',
                'post_notes' => 'sometimes|nullable|string|max:2000',
            ]);

            // Update allowed fields
            $fillable = ['procedure_status', 'priority', 'scheduled_date', 'scheduled_time',
                         'operating_room', 'outcome', 'outcome_notes', 'pre_notes', 'post_notes'];

            foreach ($fillable as $field) {
                if ($request->has($field)) {
                    $procedure->$field = $request->$field;

                    // Track who wrote notes
                    if ($field === 'pre_notes' && $request->$field) {
                        $procedure->pre_notes_by = Auth::id();
                    }
                    if ($field === 'post_notes' && $request->$field) {
                        $procedure->post_notes_by = Auth::id();
                    }
                }
            }

            // Handle status transitions
            if ($request->has('procedure_status')) {
                $newStatus = $request->procedure_status;

                if ($newStatus === \App\Models\Procedure::STATUS_IN_PROGRESS && !$procedure->actual_start_time) {
                    $procedure->actual_start_time = now();
                }

                if ($newStatus === \App\Models\Procedure::STATUS_COMPLETED && !$procedure->actual_end_time) {
                    $procedure->actual_end_time = now();
                }
            }

            $procedure->save();

            return response()->json([
                'success' => true,
                'message' => 'Procedure updated successfully',
                'procedure' => $procedure->load(['service', 'teamMembers.user', 'notes.createdBy'])
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(', ', $e->validator->errors()->all())
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating procedure: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get procedure team members
     */
    public function getProcedureTeam(\App\Models\Procedure $procedure)
    {
        $teamMembers = $procedure->teamMembers()->with('user')->get();

        return response()->json([
            'success' => true,
            'team' => $teamMembers,
            'roles' => \App\Models\ProcedureTeamMember::ROLES
        ]);
    }

    /**
     * Add a team member to a procedure
     */
    public function addProcedureTeamMember(Request $request, \App\Models\Procedure $procedure)
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'role' => 'required|in:' . implode(',', array_keys(\App\Models\ProcedureTeamMember::ROLES)),
                'custom_role' => 'nullable|required_if:role,other|string|max:100',
                'is_lead' => 'sometimes',
                'notes' => 'nullable|string|max:500',
            ]);

            // Convert is_lead to boolean
            $isLead = filter_var($request->is_lead, FILTER_VALIDATE_BOOLEAN);

            // Check if user is already in the team with the same role
            $existing = $procedure->teamMembers()
                ->where('user_id', $request->user_id)
                ->where('role', $request->role)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'This team member already has this role assigned'
                ], 422);
            }

            $teamMember = new \App\Models\ProcedureTeamMember();
            $teamMember->procedure_id = $procedure->id;
            $teamMember->user_id = $request->user_id;
            $teamMember->role = $request->role;
            $teamMember->custom_role = $request->role === 'other' ? $request->custom_role : null;
            $teamMember->is_lead = $isLead;
            $teamMember->notes = $request->notes;
            $teamMember->save();

            return response()->json([
                'success' => true,
                'message' => 'Team member added successfully',
                'member' => $teamMember->load('user')
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(', ', $e->validator->errors()->all())
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding team member: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a procedure team member
     */
    public function updateProcedureTeamMember(Request $request, \App\Models\Procedure $procedure, \App\Models\ProcedureTeamMember $member)
    {
        try {
            // Verify member belongs to this procedure
            if ($member->procedure_id !== $procedure->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team member does not belong to this procedure'
                ], 403);
            }

            $request->validate([
                'role' => 'sometimes|in:' . implode(',', array_keys(\App\Models\ProcedureTeamMember::ROLES)),
                'custom_role' => 'nullable|required_if:role,other|string|max:100',
                'is_lead' => 'sometimes|boolean',
                'notes' => 'nullable|string|max:500',
            ]);

            if ($request->has('role')) {
                $member->role = $request->role;
                $member->custom_role = $request->role === 'other' ? $request->custom_role : null;
            }
            if ($request->has('is_lead')) {
                $member->is_lead = $request->is_lead;
            }
            if ($request->has('notes')) {
                $member->notes = $request->notes;
            }

            $member->save();

            return response()->json([
                'success' => true,
                'message' => 'Team member updated successfully',
                'member' => $member->load('user')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating team member: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove a team member from a procedure
     */
    public function deleteProcedureTeamMember(\App\Models\Procedure $procedure, \App\Models\ProcedureTeamMember $member)
    {
        try {
            // Verify member belongs to this procedure
            if ($member->procedure_id !== $procedure->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team member does not belong to this procedure'
                ], 403);
            }

            $member->delete();

            return response()->json([
                'success' => true,
                'message' => 'Team member removed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error removing team member: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get procedure notes
     */
    public function getProcedureNotes(\App\Models\Procedure $procedure)
    {
        $notes = $procedure->notes()->with('createdBy')->orderBy('created_at', 'DESC')->get();

        return response()->json([
            'success' => true,
            'notes' => $notes,
            'types' => \App\Models\ProcedureNote::NOTE_TYPES
        ]);
    }

    /**
     * Add a note to a procedure
     */
    public function addProcedureNote(Request $request, \App\Models\Procedure $procedure)
    {
        try {
            $request->validate([
                'note_type' => 'required|in:' . implode(',', array_keys(\App\Models\ProcedureNote::NOTE_TYPES)),
                'title' => 'required|string|max:200',
                'content' => 'required|string',
            ]);

            $note = new \App\Models\ProcedureNote();
            $note->procedure_id = $procedure->id;
            $note->note_type = $request->note_type;
            $note->title = $request->title;
            $note->content = $request->content;
            $note->created_by = Auth::id();
            $note->save();

            return response()->json([
                'success' => true,
                'message' => 'Note added successfully',
                'note' => $note->load('createdBy')
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(', ', $e->validator->errors()->all())
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding note: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a procedure note
     */
    public function updateProcedureNote(Request $request, \App\Models\Procedure $procedure, \App\Models\ProcedureNote $note)
    {
        try {
            // Verify note belongs to this procedure
            if ($note->procedure_id !== $procedure->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Note does not belong to this procedure'
                ], 403);
            }

            $request->validate([
                'note_type' => 'sometimes|in:' . implode(',', array_keys(\App\Models\ProcedureNote::NOTE_TYPES)),
                'title' => 'sometimes|string|max:200',
                'content' => 'sometimes|string',
            ]);

            if ($request->has('note_type')) {
                $note->note_type = $request->note_type;
            }
            if ($request->has('title')) {
                $note->title = $request->title;
            }
            if ($request->has('content')) {
                $note->content = $request->content;
            }

            $note->save();

            return response()->json([
                'success' => true,
                'message' => 'Note updated successfully',
                'note' => $note->load('createdBy')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating note: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a procedure note
     */
    public function deleteProcedureNote(\App\Models\Procedure $procedure, \App\Models\ProcedureNote $note)
    {
        try {
            // Verify note belongs to this procedure
            if ($note->procedure_id !== $procedure->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Note does not belong to this procedure'
                ], 403);
            }

            $note->delete();

            return response()->json([
                'success' => true,
                'message' => 'Note deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting note: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a procedure with optional refund
     */
    public function cancelProcedure(Request $request, \App\Models\Procedure $procedure)
    {
        try {
            // Cannot cancel completed procedures
            if ($procedure->procedure_status === \App\Models\Procedure::STATUS_COMPLETED) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot cancel a completed procedure'
                ], 403);
            }

            // Already cancelled
            if ($procedure->procedure_status === \App\Models\Procedure::STATUS_CANCELLED) {
                return response()->json([
                    'success' => false,
                    'message' => 'Procedure is already cancelled'
                ], 403);
            }

            $request->validate([
                'cancellation_reason' => 'required|string|max:1000',
                'refund_eligible' => 'sometimes'
            ]);

            // Convert string boolean to actual boolean
            $refundEligible = filter_var($request->refund_eligible, FILTER_VALIDATE_BOOLEAN);

            // Update procedure status
            $procedure->procedure_status = \App\Models\Procedure::STATUS_CANCELLED;
            $procedure->cancellation_reason = $request->cancellation_reason;
            $procedure->cancelled_by = Auth::id();
            $procedure->cancelled_at = now();
            $procedure->save();

            // Handle refund if applicable
            $refundMessage = '';
            if ($refundEligible && $procedure->product_or_service_request_id) {
                $billing = \App\Models\ProductOrServiceRequest::find($procedure->product_or_service_request_id);
                if ($billing && $billing->paid) {
                    // Create refund/credit entry
                    $patient = $procedure->patient;
                    if ($patient && $patient->patientAccount) {
                        $patient->patientAccount->increment('balance', $billing->price);
                        $refundMessage = ' A credit of ₦' . number_format($billing->price, 2) . ' has been added to the patient account.';
                    }
                    $billing->update(['refunded' => true, 'refund_reason' => $request->cancellation_reason]);
                } elseif ($billing && !$billing->paid) {
                    // Just delete unpaid billing entry
                    $billing->delete();
                    $refundMessage = ' Unpaid billing entry has been removed.';
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Procedure cancelled successfully.' . $refundMessage
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(', ', $e->validator->errors()->all())
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error cancelling procedure: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Print procedure details
     */
    public function printProcedure(\App\Models\Procedure $procedure)
    {
        $procedure->load([
            'patient',
            'encounter',
            'service',
            'procedureDefinition.procedureCategory',
            'teamMembers.user',
            'notes.createdBy',
            'requestedByUser',
            'billedByUser'
        ]);

        return view('admin.doctors.procedures.print', compact('procedure'));
    }
}
