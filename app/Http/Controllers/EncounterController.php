<?php

namespace App\Http\Controllers;

use App\Enums\QueueStatus;
use App\Models\AdmissionRequest;
use App\Models\Clinic;
use App\Models\DoctorQueue;
use App\Models\Encounter;
use App\Models\Hmo;
use App\Models\ImagingServiceRequest;
use App\Models\LabServiceRequest;
use App\Models\NursingNote;
use App\Models\NursingNoteType;
use App\Models\Patient;
use App\Models\ProductOrServiceRequest;
use App\Models\ProductRequest;
use App\Models\ReasonForEncounter;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Services\QueueStatusService;
use Carbon\Carbon;
use App\Helpers\HmoHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;
use App\Http\Traits\ClinicalOrdersTrait;
use App\Models\Procedure;

class EncounterController extends Controller
{
    use ClinicalOrdersTrait;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Clinics & doctors for referral filter dropdowns
        $filterClinics  = Clinic::orderBy('name')->get(['id', 'name']);
        $filterDoctors  = Staff::whereHas('user')
            ->orderBy('id')
            ->get(['id', 'user_id']);

        return view('admin.doctors.my_queues', compact('filterClinics', 'filterDoctors'));
    }

    public function NewEncounterList(Request $request)
    {
        try {
            // Fetch the currently logged-in doctor (with user for display)
            $doc = Staff::with('user')->where('user_id', Auth::id())->first();

            // Retrieve date range from the request
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            // Build the base query — eager-load relationships to avoid N+1
            $queueQuery = DoctorQueue::with(['patient.user', 'patient.hmo', 'clinic', 'request_entry'])
                ->where(function ($q) use ($doc) {
                    $q->whereIn('clinic_id', $doc->all_clinic_ids)
                        ->orWhere('staff_id', $doc->id);
                })
                ->where('status', QueueStatus::WAITING);

            // Apply date filtering if both dates are provided
            if ($startDate && $endDate) {
                $queueQuery->whereBetween('created_at', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay(),
                ]);
            }

            // Get the filtered results — emergency patients first
            $queue = $queueQuery
                ->orderByRaw("FIELD(IFNULL(priority,'routine'), 'emergency', 'urgent', 'routine') ASC")
                ->orderBy('created_at', 'DESC')
                ->get();

            return DataTables::of($queue)
                ->addIndexColumn()
                ->editColumn('fullname', function ($queue) {
                    $patient = $queue->patient;
                    $user = $patient ? $patient->user : null;
                    $name = $user ? trim(($user->surname ?? '') . ' ' . ($user->firstname ?? '') . ' ' . ($user->othername ?? '')) : 'N/A';
                    if ($queue->priority === 'emergency') {
                        return '<span class="text-danger fw-bold"><i class="fa fa-exclamation-triangle"></i> ' . e($name) . '</span>';
                    }
                    return e($name);
                })
                ->addColumn('priority', function ($queue) {
                    $badges = [
                        'emergency' => '<span class="badge bg-danger"><i class="fa fa-bolt"></i> Emergency</span>',
                        'urgent'    => '<span class="badge bg-warning text-dark">Urgent</span>',
                        'routine'   => '<span class="badge bg-secondary">Routine</span>',
                    ];
                    $badge = $badges[$queue->priority] ?? $badges['routine'];
                    if ($queue->triage_note) {
                        $badge .= ' <a href="#" class="text-info triage-note-btn" data-bs-toggle="popover" data-bs-trigger="hover focus" title="Triage Note" data-bs-content="' . e(Str::limit($queue->triage_note, 300)) . '"><i class="fa fa-notes-medical"></i></a>';
                    }
                    return $badge;
                })
                ->editColumn('created_at', function ($note) {
                    return date('h:i a D M j, Y', strtotime($note->created_at));
                })
                ->editColumn('hmo_id', function ($queue) {
                    $hmo = $queue->patient ? $queue->patient->hmo : null;
                    return $hmo->name ?? 'N/A';
                })
                ->editColumn('clinic_id', function ($queue) {
                    return $queue->clinic->name ?? 'N/A';
                })
                ->editColumn('staff_id', function ($queue) use ($doc) {
                    $docUser = $doc->user;
                    return $docUser ? trim(($docUser->surname ?? '') . ' ' . ($docUser->firstname ?? '') . ' ' . ($docUser->othername ?? '')) : 'N/A';
                })
                ->addColumn('file_no', function ($queue) {
                    return $queue->patient->file_no ?? 'N/A';
                })
                ->addColumn('view', function ($queue) {
                    $reqEntry = $queue->request_entry;
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
                ->addColumn('source', function ($queue) {
                    $icons = [
                        'appointment' => '<span class="badge bg-purple-subtle text-purple source-badge"><i class="mdi mdi-calendar-check"></i> Scheduled</span>',
                        'emergency'   => '<span class="badge bg-danger-subtle text-danger source-badge"><i class="mdi mdi-ambulance"></i> Emergency</span>',
                    ];
                    return $icons[$queue->source ?? ''] ?? '<span class="badge bg-secondary-subtle text-secondary source-badge"><i class="mdi mdi-walk"></i> Walk-in</span>';
                })
                ->addColumn('status_badge', function ($queue) {
                    $badge = QueueStatus::badge($queue->status);
                    // Add mini-timer for in-consultation (ISO 8601 for reliable JS parsing)
                    if ($queue->status == QueueStatus::IN_CONSULTATION && $queue->consultation_started_at) {
                        $startedIso = \Carbon\Carbon::parse($queue->consultation_started_at)->toIso8601String();
                        $pausedAtIso = $queue->last_paused_at ? \Carbon\Carbon::parse($queue->last_paused_at)->toIso8601String() : '';
                        $badge .= ' <span class="badge bg-success-subtle text-success mini-timer" data-started="' . $startedIso . '" data-paused-seconds="' . ($queue->consultation_paused_seconds ?? 0) . '" data-is-paused="' . ($queue->is_paused ? '1' : '0') . '" data-last-paused-at="' . $pausedAtIso . '"><i class="mdi mdi-timer"></i> <span class="timer-value">00:00:00</span></span>';
                    }
                    return $badge;
                })
                ->rawColumns(['fullname', 'view', 'priority', 'source', 'status_badge'])
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

        $q = DoctorQueue::where('status', QueueStatus::VITALS_PENDING)
            ->where('created_at', '<', $timeThreshold)->get();
        foreach ($q as $r) {
            $r->update([
                'status' => QueueStatus::READY,
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
                    $q->whereIn('clinic_id', $doc->all_clinic_ids);
                }
                $q->orWhere('staff_id', $doc->id);
            })
                ->whereIn('status', [QueueStatus::IN_CONSULTATION, QueueStatus::COMPLETED]);

            // Apply date range filter if provided
            if ($startDate) {
                $queueQuery->where('created_at', '>=', $startDate->startOfDay());
            }
            if ($endDate) {
                $queueQuery->where('created_at', '<=', $endDate->endOfDay());
            }

            // Apply clinic filter
            if ($request->filled('clinic_id')) {
                $queueQuery->where('clinic_id', $request->clinic_id);
            }

            // Apply HMO filter (join via patient)
            if ($request->filled('hmo_id')) {
                $hmoId = $request->hmo_id;
                $queueQuery->whereHas('patient', function ($q) use ($hmoId) {
                    $q->where('hmo_id', $hmoId);
                });
            }

            $queue = $queueQuery->orderBy('created_at', 'DESC')->get();

            return DataTables::of($queue)
                ->addIndexColumn()
                ->editColumn('fullname', function ($queue) {
                    $patient = Patient::find($queue->patient_id);
                    return userfullname($patient->user_id);
                })
                ->editColumn('created_at', function ($note) {
                    return date('h:i a D M j, Y', strtotime($note->created_at));
                })
                ->editColumn('hmo_id', function ($queue) {
                    $patient = Patient::find($queue->patient_id);
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
                    $patient = Patient::find($queue->patient_id);
                    return $patient?->file_no;
                })
                ->addColumn('view', function ($queue) {
                    $url = url('encounters/create') . '?patient_id=' . $queue->patient_id;
                    if ($queue->request_entry_id) {
                        $url .= '&req_entry_id=' . $queue->request_entry_id;
                    }
                    $url .= '&queue_id=' . $queue->id;
                    return '<a href="' . e($url) . '" class="btn btn-success btn-sm"><i class="fa fa-street-view"></i> View</a>';
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
                $q->whereIn('clinic_id', $doc->all_clinic_ids);
                $q->orWhere('staff_id', $doc->id);
            })
                ->where('status', QueueStatus::VITALS_PENDING)
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
                    $patient = Patient::find($queue->patient_id);
                    return userfullname($patient->user_id);
                })
                ->editColumn('created_at', function ($note) {
                    return date('h:i a D M j, Y', strtotime($note->created_at));
                })
                ->editColumn('hmo_id', function ($queue) {
                    $patient = Patient::find($queue->patient_id);
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
                    $patient = Patient::find($queue->patient_id);
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
                ->addColumn('priority', function ($queue) {
                    $badges = [
                        'emergency' => '<span class="badge bg-danger"><i class="fa fa-bolt"></i> Emergency</span>',
                        'urgent'    => '<span class="badge bg-warning text-dark">Urgent</span>',
                        'routine'   => '<span class="badge bg-secondary">Routine</span>',
                    ];
                    return $badges[$queue->priority] ?? $badges['routine'];
                })
                ->addColumn('source', function ($queue) {
                    $icons = [
                        'appointment' => '<span class="badge bg-purple-subtle text-purple source-badge"><i class="mdi mdi-calendar-check"></i> Scheduled</span>',
                        'emergency'   => '<span class="badge bg-danger-subtle text-danger source-badge"><i class="mdi mdi-ambulance"></i> Emergency</span>',
                    ];
                    return $icons[$queue->source ?? ''] ?? '<span class="badge bg-secondary-subtle text-secondary source-badge"><i class="mdi mdi-walk"></i> Walk-in</span>';
                })
                ->addColumn('status_badge', function ($queue) {
                    $badge = QueueStatus::badge($queue->status);
                    if ($queue->status == QueueStatus::IN_CONSULTATION && $queue->consultation_started_at) {
                        $startedIso = \Carbon\Carbon::parse($queue->consultation_started_at)->toIso8601String();
                        $pausedAtIso = $queue->last_paused_at ? \Carbon\Carbon::parse($queue->last_paused_at)->toIso8601String() : '';
                        $badge .= ' <span class="badge bg-success-subtle text-success mini-timer" data-started="' . $startedIso . '" data-paused-seconds="' . ($queue->consultation_paused_seconds ?? 0) . '" data-is-paused="' . ($queue->is_paused ? '1' : '0') . '" data-last-paused-at="' . $pausedAtIso . '"><i class="mdi mdi-timer"></i> <span class="timer-value">00:00:00</span></span>';
                    }
                    return $badge;
                })
                ->rawColumns(['fullname', 'view', 'priority', 'source', 'status_badge'])
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
                ->addColumn('patient_link', function ($queue) {
                    return $queue->patient_id ? route('patient.show', $queue->patient_id) : '';
                })
                ->addColumn('view', function ($queue) {
                    $showUrl = route('encounters.show', $queue->id);
                    return '<a href="' . e($showUrl) . '" class="btn btn-primary btn-sm"><i class="mdi mdi-eye-outline"></i> View</a>';
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
        $his = LabServiceRequest::with(['service', 'encounter', 'patient', 'patient.user', 'productOrServiceRequest.parent.service', 'productOrServiceRequest.parent.children.service', 'productOrServiceRequest.parent.children.product', 'productOrServiceRequest', 'doctor', 'biller', 'results_person', 'resultViews'])
            ->where('status', '>', 0)
            ->where('patient_id', $patient_id)
            ->where(function ($query) {
                $query->whereNull('service_request_id')
                    ->orWhereHas('productOrServiceRequest', function ($posr) {
                        $posr->whereNull('removed_at');
                    });
            })
            ->orderBy('created_at', 'DESC')
            ->get();

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
                if ($his->status == 5) {
                    $statusBadge = "<span class='badge' style='background-color: #6f42c1; color: #fff;'>Pending Approval</span>";
                } elseif ($his->status == 6) {
                    $statusBadge = "<span class='badge bg-danger'><i class='mdi mdi-close-circle'></i> Rejected</span>";
                } elseif ($his->result) {
                    $statusBadge = "<span class='badge bg-info'>Result Available</span>";
                } elseif ($his->sample_taken_by) {
                    $statusBadge = "<span class='badge bg-warning'>Sample Taken</span>";
                } elseif ($his->billed_by) {
                    $statusBadge = "<span class='badge bg-primary'>Billed</span>";
                } else {
                    $statusBadge = "<span class='badge bg-secondary'>Pending</span>";
                }
                $str .= $statusBadge;
                // Combo badge (our service combo system — distinct from procedure is_bundled)
                if ($his->productOrServiceRequest && $his->productOrServiceRequest->is_bundle_item) {
                    $str .= " <span class='badge bg-secondary ms-1'><i class='mdi mdi-link-variant'></i> Combo</span>";
                }
                // Self-Performed badge — only when doctor explicitly opted in
                if ($his->self_perform_intent) {
                    $str .= " <span class='badge bg-info ms-1'><i class='mdi mdi-account-check'></i> Self-Performed</span>";
                }

                // HMO Coverage Badge
                if ($his->productOrServiceRequest && $his->productOrServiceRequest->coverage_mode) {
                    $coverageClass = $his->productOrServiceRequest->coverage_mode === 'express' ? 'success' : ($his->productOrServiceRequest->coverage_mode === 'primary' ? 'warning' : 'danger');
                    $str .= " <span class='badge bg-{$coverageClass}'>HMO: " . strtoupper($his->productOrServiceRequest->coverage_mode) . '</span>';
                }
                $str .= '</div>';
                $str .= '</div>';

                // Combo info block — View Details + Remove buttons (for combo items)
                if ($his->productOrServiceRequest && $his->productOrServiceRequest->is_bundle_item && $his->productOrServiceRequest->parent_id) {
                    $parentReq = $his->productOrServiceRequest->parent;
                    if ($parentReq) {
                        $bundleName    = optional($parentReq->service)->service_name ?? 'Combo';
                        $bundlePayable = $parentReq->payable_amount ?? 0;
                        $bundleClaims  = $parentReq->claims_amount ?? 0;
                        $parentId      = $parentReq->id;
                        $isPaid        = $parentReq->payment_id !== null;
                        $isCreator     = $parentReq->staff_user_id == Auth::id();
                        $childrenArr   = $parentReq->children->map(function ($c) {
                            return ['name' => optional($c->service)->service_name ?? optional($c->product)->product_name ?? 'Item', 'qty' => $c->qty ?? 1, 'price' => $c->payable_amount ?? $c->amount ?? 0];
                        })->values()->toArray();
                        $bundleDataJson  = htmlspecialchars(json_encode(['name' => $bundleName, 'payable_amount' => $bundlePayable, 'claims_amount' => $bundleClaims, 'items' => $childrenArr]), ENT_QUOTES);
                        $removeItemsJson = htmlspecialchars(json_encode($childrenArr), ENT_QUOTES);
                        $removeUrl       = url('/service-combo/remove-bundle');
                        $bundleNameEsc   = htmlspecialchars($bundleName, ENT_QUOTES);
                        $str .= "<div class='bundle-info-block mb-2 p-2 bg-light rounded'>";
                        $str .= "<small class='text-muted d-block mb-1'><i class='mdi mdi-link-variant'></i> <strong>Combo: {$bundleNameEsc}</strong> &mdash; &#8358;" . number_format($bundlePayable, 2) . " patient / &#8358;" . number_format($bundleClaims, 2) . " claims</small>";
                        $str .= "<div class='d-flex gap-1 flex-wrap'>";
                        $str .= "<button type='button' class='btn btn-outline-primary btn-sm' onclick='window.BundleViewModal && BundleViewModal.show({$bundleDataJson})' title='View combo details'><i class='fa fa-info-circle'></i> View Combo</button>";
                        if (!$isPaid && $isCreator) {
                            $str .= "<button type='button' class='btn btn-outline-danger btn-sm' data-parent-id='{$parentId}' data-bundle-name='{$bundleNameEsc}' data-items='{$removeItemsJson}' data-remove-url='{$removeUrl}' onclick='showBundleRemove(this)' title='Remove this combo'><i class='fa fa-trash'></i> Remove Combo</button>";
                        }
                        $str .= "</div></div>";
                    }
                }

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

                // Lab Number
                if (isset($his->lab_number) && $his->lab_number) {
                    $str .= '<div class="mb-2"><i class="mdi mdi-tag-text text-info"></i> <b>Lab Number:</b> <span class="badge bg-info text-white">' . e($his->lab_number) . '</span></div>';
                }

                $str .= '</small></div>';

                // Results section — hide for pending approval / rejected status
                if ($his->status == 5) {
                    $str .= '<div class="alert alert-warning mb-2"><small><i class="mdi mdi-clock-outline"></i> <b>Result is pending approval by a supervisor.</b></small></div>';
                } elseif ($his->status == 6) {
                    $str .= '<div class="alert alert-danger mb-2"><small><i class="mdi mdi-close-circle"></i> <b>Result was rejected and sent back for revision.</b>';
                    if ($his->rejection_reason) {
                        $str .= '<br>Reason: ' . e($his->rejection_reason);
                    }
                    $str .= '</small></div>';
                } elseif ($his->result) {
                    $viewCount = $his->resultViews->unique('user_id')->count();
                    $viewText = $viewCount == 1 ? '1 staff' : "{$viewCount} staff members";
                    $badgeClass = $viewCount > 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle';
                    $badgeIcon = $viewCount > 0 ? 'mdi-eye-check-outline' : 'mdi-eye-off-outline';
                    $labelText = $viewCount > 0 ? "Viewed by {$viewText}" : 'Unviewed';
                    $str .= '<div class="alert alert-light mb-2 p-2 d-flex align-items-center gap-2"><span class="badge ' . $badgeClass . ' p-1 px-2"><i class="mdi ' . $badgeIcon . ' me-1"></i> ' . $labelText . '</span></div>';
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
                    if (!$canDeliver && $his->billed_by != Auth::id()) {
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

                // Enter Result button for doctors/nurses who requested the investigation
                $canEnterResult = false;
                if (empty($his->result) && ($canDeliver || $his->self_perform_intent) && $his->status >= 2 && Auth::id() == $his->doctor_id) {
                    $user = Auth::user();
                    if (($user->hasRole('DOCTOR') && appsettings('doctor_can_enter_lab_result'))
                        || ($user->hasRole('NURSE') && appsettings('nurse_can_enter_lab_result'))
                    ) {
                        $canEnterResult = true;
                    }
                }
                if ($canEnterResult) {
                    $str .= "
                        <button type='button' class='btn btn-success btn-sm' onclick='enterLabResult({$his->id})'>
                            <i class='mdi mdi-flask-outline'></i> Enter Result
                        </button>";
                }

                // "Perform Investigation" button — status == 1 (unbilled), requester only
                $canPerformInvestigation = false;
                if (empty($his->result) && $his->status == 1 && Auth::id() == $his->doctor_id) {
                    $user = Auth::user();
                    if (($user->hasRole('DOCTOR') && e(appsettings('doctor_can_enter_lab_result')))
                        || ($user->hasRole('NURSE') && e(appsettings('nurse_can_enter_lab_result')))
                    ) {
                        $canPerformInvestigation = true;
                    }
                }
                // "Perform Investigation" button — combo item (status == 2, is_bundle_item), not yet claimed
                $canClaimComboPerform = false;
                if (
                     empty($his->result)
                     && $his->status == 2
                     && !$his->self_perform_intent
                     && Auth::id() == $his->doctor_id
                     && $his->productOrServiceRequest
                     && $his->productOrServiceRequest->is_bundle_item
                ) {
                    $user = Auth::user();
                    if (($user->hasRole('DOCTOR') && appsettings('doctor_can_enter_lab_result'))
                        || ($user->hasRole('NURSE') && appsettings('nurse_can_enter_lab_result'))
                    ) {
                        $canClaimComboPerform = true;
                    }
                }
                if ($canPerformInvestigation) {
                    $piServiceName = htmlspecialchars(optional($his->service)->service_name ?? 'N/A', ENT_QUOTES);
                    $piPrice       = optional(optional($his->service)->price)->sale_price ?? 0;
                    $piCovMode     = '';
                    $piPayable     = $piPrice;
                    $piClaims      = 0;
                    try {
                        $hmoEst = \App\Helpers\HmoHelper::applyHmoTariff($his->patient_id, null, $his->service_id);
                        if ($hmoEst) {
                            $piCovMode = $hmoEst['coverage_mode'] ?? '';
                            $piPayable = $hmoEst['payable_amount'] ?? $piPrice;
                            $piClaims  = $hmoEst['claims_amount'] ?? 0;
                        }
                    } catch (\Exception $e) {
                        // Silently fall back to full price
                    }
                    $str .= "
                        <button type='button' class='btn btn-warning btn-sm'
                            onclick='performInvestigation(this)'
                            data-type='lab'
                            data-request-id='{$his->id}'
                            data-patient-id='{$his->patient_id}'
                            data-service-name='{$piServiceName}'
                            data-price='{$piPrice}'
                            data-coverage-mode='{$piCovMode}'
                            data-payable='{$piPayable}'
                            data-claims='{$piClaims}'>
                            <i class='mdi mdi-flask-outline'></i> Perform Investigation
                        </button>";
                }
                if ($canClaimComboPerform) {
                    $comboServiceName = htmlspecialchars(optional($his->service)->service_name ?? 'N/A', ENT_QUOTES);
                    $str .= "
                        <button type='button' class='btn btn-warning btn-sm'
                            onclick='claimComboPerform({$his->id}, &quot;lab&quot;, &quot;{$comboServiceName}&quot;, this)'
                            title='This test was auto-billed as part of a combo. Click to confirm you will self-perform it.'>
                            <i class='mdi mdi-flask-outline'></i> Perform Investigation
                        </button>";
                }

                $str .= "
                    <button type='button' class='btn btn-info btn-sm' onclick='setResViewInModal(this)'
                        data-service-name = '" . (($his->service) ? $his->service->service_name : 'N/A') . "'
                        data-result = '" . htmlspecialchars($his->result) . "'
                        data-result-obj = '" . htmlspecialchars($his) . "'
                        data-viewable-type='lab'
                        data-viewable-id='{$his->id}'>
                        <i class='mdi mdi-eye'></i> View
                    </button>
                    <a href='" . route('service-requests.show', $his->id) . "' target='_blank' class='btn btn-primary btn-sm' onclick='trackResultPrint(\"lab\", {$his->id})'>
                        <i class='mdi mdi-printer'></i> Print
                    </a>";

                // Show delete button only if:
                // 1. Current user is the requester
                // 2. Status is pending (1) or in progress (2), not yet billed, no results
                // 3. Within the note_edit_window
                $editWindowMinutes = appsettings('note_edit_window', 30);
                $withinWindow = $his->created_at && now()->diffInMinutes($his->created_at) <= $editWindowMinutes;
                $canDelete = (Auth::id() == $his->doctor_id)
                    && ($his->status == 1 || $his->status == 2)
                    && empty($his->billed_by)
                    && empty($his->result)
                    && $withinWindow;

                if ($canDelete) {
                    $serviceName = $his->service ? $his->service->service_name : 'N/A';
                    if ($his->encounter_id) {
                        $str .= "<button type='button' class='btn btn-danger btn-sm'
                            onclick='deleteLabRequest({$his->id}, {$his->encounter_id}, \"{$serviceName}\")'
                            title='Delete this request'>
                            <i class='fa fa-trash'></i> Delete
                        </button>";
                    } else {
                        // Nurse-created item (no encounter) — use nurse route
                        $str .= "<button type='button' class='btn btn-danger btn-sm'
                            onclick='deleteNurseClinicalRequest(\"lab\", {$his->id}, \"{$serviceName}\")'
                            title='Delete this request'>
                            <i class='fa fa-trash'></i> Delete
                        </button>";
                    }
                }

                // Re-order button (Plan §5.2)
                $roName = htmlspecialchars(($his->service) ? $his->service->service_name : 'N/A', ENT_QUOTES);
                $roSvcId = $his->service_id;
                $roPrice = optional(optional($his->service)->price)->sale_price ?? 0;
                $roCov   = optional($his->productOrServiceRequest)->coverage_mode ?? '';
                $roPay   = optional($his->productOrServiceRequest)->payable_amount ?? $roPrice;
                $roClaim = optional($his->productOrServiceRequest)->claims_amount ?? 0;
                $str .= "<button type='button' class='btn btn-outline-primary btn-sm re-order-btn ms-1'
                    data-type='labs'
                    data-service-id='{$roSvcId}'
                    data-name='{$roName}'
                    data-price='{$roPrice}'
                    data-coverage-mode='{$roCov}'
                    data-payable='{$roPay}'
                    data-claims='{$roClaim}'
                    title='Add to current lab requests'>
                    <i class='fa fa-redo'></i> Re-order
                </button>";

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
        $his = \App\Models\ImagingServiceRequest::with(['service', 'encounter', 'patient', 'patient.user', 'productOrServiceRequest.parent.service', 'productOrServiceRequest.parent.children.service', 'productOrServiceRequest.parent.children.product', 'productOrServiceRequest', 'doctor', 'biller', 'results_person', 'resultViews'])
            ->where('status', '>', 0)
            ->where('patient_id', $patient_id)
            ->where(function ($query) {
                $query->whereNull('service_request_id')
                    ->orWhereHas('productOrServiceRequest', function ($posr) {
                        $posr->whereNull('removed_at');
                    });
            })
            ->orderBy('created_at', 'DESC')
            ->get();

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
                if ($his->status == 5) {
                    $statusBadge = "<span class='badge' style='background-color: #6f42c1; color: #fff;'>Pending Approval</span>";
                } elseif ($his->status == 6) {
                    $statusBadge = "<span class='badge bg-danger'><i class='mdi mdi-close-circle'></i> Rejected</span>";
                } elseif ($his->result) {
                    $statusBadge = "<span class='badge bg-info'>Result Available</span>";
                } elseif ($his->billed_by) {
                    $statusBadge = "<span class='badge bg-primary'>Billed</span>";
                } else {
                    $statusBadge = "<span class='badge bg-secondary'>Pending</span>";
                }
                $str .= $statusBadge;
                // Combo badge (our service combo system — distinct from procedure is_bundled)
                if ($his->productOrServiceRequest && $his->productOrServiceRequest->is_bundle_item) {
                    $str .= " <span class='badge bg-secondary ms-1'><i class='mdi mdi-link-variant'></i> Combo</span>";
                }
                // Self-Performed badge — only when doctor explicitly opted in
                if ($his->self_perform_intent) {
                    $str .= " <span class='badge bg-info ms-1'><i class='mdi mdi-account-check'></i> Self-Performed</span>";
                }

                // HMO Coverage Badge
                if ($his->productOrServiceRequest && $his->productOrServiceRequest->coverage_mode) {
                    $coverageClass = $his->productOrServiceRequest->coverage_mode === 'express' ? 'success' : ($his->productOrServiceRequest->coverage_mode === 'primary' ? 'warning' : 'danger');
                    $str .= " <span class='badge bg-{$coverageClass}'>HMO: " . strtoupper($his->productOrServiceRequest->coverage_mode) . '</span>';
                }
                $str .= '</div>';
                $str .= '</div>';

                // Combo info block — View Details + Remove buttons (for combo items)
                if ($his->productOrServiceRequest && $his->productOrServiceRequest->is_bundle_item && $his->productOrServiceRequest->parent_id) {
                    $parentReq = $his->productOrServiceRequest->parent;
                    if ($parentReq) {
                        $bundleName    = optional($parentReq->service)->service_name ?? 'Combo';
                        $bundlePayable = $parentReq->payable_amount ?? 0;
                        $bundleClaims  = $parentReq->claims_amount ?? 0;
                        $parentId      = $parentReq->id;
                        $isPaid        = $parentReq->payment_id !== null;
                        $isCreator     = $parentReq->staff_user_id == Auth::id();
                        $childrenArr   = $parentReq->children->map(function ($c) {
                            return ['name' => optional($c->service)->service_name ?? optional($c->product)->product_name ?? 'Item', 'qty' => $c->qty ?? 1, 'price' => $c->payable_amount ?? $c->amount ?? 0];
                        })->values()->toArray();
                        $bundleDataJson  = htmlspecialchars(json_encode(['name' => $bundleName, 'payable_amount' => $bundlePayable, 'claims_amount' => $bundleClaims, 'items' => $childrenArr]), ENT_QUOTES);
                        $removeItemsJson = htmlspecialchars(json_encode($childrenArr), ENT_QUOTES);
                        $removeUrl       = url('/service-combo/remove-bundle');
                        $bundleNameEsc   = htmlspecialchars($bundleName, ENT_QUOTES);
                        $str .= "<div class='bundle-info-block mb-2 p-2 bg-light rounded'>";
                        $str .= "<small class='text-muted d-block mb-1'><i class='mdi mdi-link-variant'></i> <strong>Combo: {$bundleNameEsc}</strong> &mdash; &#8358;" . number_format($bundlePayable, 2) . " patient / &#8358;" . number_format($bundleClaims, 2) . " claims</small>";
                        $str .= "<div class='d-flex gap-1 flex-wrap'>";
                        $str .= "<button type='button' class='btn btn-outline-primary btn-sm' onclick='window.BundleViewModal && BundleViewModal.show({$bundleDataJson})' title='View combo details'><i class='fa fa-info-circle'></i> View Combo</button>";
                        if (!$isPaid && $isCreator) {
                            $str .= "<button type='button' class='btn btn-outline-danger btn-sm' data-parent-id='{$parentId}' data-bundle-name='{$bundleNameEsc}' data-items='{$removeItemsJson}' data-remove-url='{$removeUrl}' onclick='showBundleRemove(this)' title='Remove this combo'><i class='fa fa-trash'></i> Remove Combo</button>";
                        }
                        $str .= "</div></div>";
                    }
                }

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

                // Results section — hide for pending approval / rejected status
                if ($his->status == 5) {
                    $str .= '<div class="alert alert-warning mb-2"><small><i class="mdi mdi-clock-outline"></i> <b>Result is pending approval by a supervisor.</b></small></div>';
                } elseif ($his->status == 6) {
                    $str .= '<div class="alert alert-danger mb-2"><small><i class="mdi mdi-close-circle"></i> <b>Result was rejected and sent back for revision.</b>';
                    if ($his->rejection_reason) {
                        $str .= '<br>Reason: ' . e($his->rejection_reason);
                    }
                    $str .= '</small></div>';
                } elseif ($his->result) {
                    $viewCount = $his->resultViews->unique('user_id')->count();
                    $viewText = $viewCount == 1 ? '1 staff' : "{$viewCount} staff members";
                    $badgeClass = $viewCount > 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle';
                    $badgeIcon = $viewCount > 0 ? 'mdi-eye-check-outline' : 'mdi-eye-off-outline';
                    $labelText = $viewCount > 0 ? "Viewed by {$viewText}" : 'Unviewed';
                    $str .= '<div class="alert alert-light mb-2 p-2 d-flex align-items-center gap-2"><span class="badge ' . $badgeClass . ' p-1 px-2"><i class="mdi ' . $badgeIcon . ' me-1"></i> ' . $labelText . '</span></div>';
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
                    if (!$canDeliver && $his->billed_by != Auth::id()) {
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

                // Enter Result button for doctors/nurses who requested the imaging
                $canEnterImagingResult = false;
                if (empty($his->result) && ($canDeliver || $his->self_perform_intent) && $his->status >= 2 && Auth::id() == $his->doctor_id) {
                    $user = Auth::user();
                    if (($user->hasRole('DOCTOR') && appsettings('doctor_can_enter_imaging_result'))
                        || ($user->hasRole('NURSE') && e(appsettings('nurse_can_enter_imaging_result')))
                    ) {
                        $canEnterImagingResult = true;
                    }
                }
                if ($canEnterImagingResult) {
                    $str .= "
                        <button type='button' class='btn btn-success btn-sm' onclick='enterImagingResult({$his->id})'>
                            <i class='mdi mdi-radiology-box-outline'></i> Enter Result
                        </button>";
                }

                // "Perform Investigation" button — status == 1 (unbilled), requester only
                $canPerformImagingInvestigation = false;
                if (empty($his->result) && $his->status == 1 && Auth::id() == $his->doctor_id) {
                    $user = Auth::user();
                    if (($user->hasRole('DOCTOR') && appsettings('doctor_can_enter_imaging_result'))
                        || ($user->hasRole('NURSE') && appsettings('nurse_can_enter_imaging_result'))
                    ) {
                        $canPerformImagingInvestigation = true;
                    }
                }
                // "Perform Investigation" button — combo item (status == 2, is_bundle_item), not yet claimed
                $canClaimComboImagingPerform = false;
                if (
                    empty($his->result)
                    && $his->status == 2
                    && !$his->self_perform_intent
                    && Auth::id() == $his->doctor_id
                    && $his->productOrServiceRequest
                    && $his->productOrServiceRequest->is_bundle_item
                ) {
                    $user = Auth::user();
                    if (($user->hasRole('DOCTOR') && appsettings('doctor_can_enter_imaging_result'))
                        || ($user->hasRole('NURSE') && appsettings('nurse_can_enter_imaging_result'))
                    ) {
                        $canClaimComboImagingPerform = true;
                    }
                }
                if ($canPerformImagingInvestigation) {
                    $piServiceName = htmlspecialchars(optional($his->service)->service_name ?? 'N/A', ENT_QUOTES);
                    $piPrice       = optional(optional($his->service)->price)->sale_price ?? 0;
                    $piCovMode     = '';
                    $piPayable     = $piPrice;
                    $piClaims      = 0;
                    try {
                        $hmoEst = \App\Helpers\HmoHelper::applyHmoTariff($his->patient_id, null, $his->service_id);
                        if ($hmoEst) {
                            $piCovMode = $hmoEst['coverage_mode'] ?? '';
                            $piPayable = $hmoEst['payable_amount'] ?? $piPrice;
                            $piClaims  = $hmoEst['claims_amount'] ?? 0;
                        }
                    } catch (\Exception $e) {
                        // Silently fall back to full price
                    }
                    $str .= "
                        <button type='button' class='btn btn-warning btn-sm'
                            onclick='performInvestigation(this)'
                            data-type='imaging'
                            data-request-id='{$his->id}'
                            data-patient-id='{$his->patient_id}'
                            data-service-name='{$piServiceName}'
                            data-price='{$piPrice}'
                            data-coverage-mode='{$piCovMode}'
                            data-payable='{$piPayable}'
                            data-claims='{$piClaims}'>
                            <i class='mdi mdi-radiology-box-outline'></i> Perform Investigation
                        </button>";
                }
                if ($canClaimComboImagingPerform) {
                    $comboServiceName = htmlspecialchars(optional($his->service)->service_name ?? 'N/A', ENT_QUOTES);
                    $str .= "
                        <button type='button' class='btn btn-warning btn-sm'
                            onclick='claimComboPerform({$his->id}, &quot;imaging&quot;, &quot;{$comboServiceName}&quot;, this)'
                            title='This imaging was auto-billed as part of a combo. Click to confirm you will self-perform it.'>
                            <i class='mdi mdi-radiology-box-outline'></i> Perform Investigation
                        </button>";
                }

                $str .= "
                    <button type='button' class='btn btn-info btn-sm' onclick='setImagingResViewInModal(this)'
                        data-service-name = '" . (($his->service) ? $his->service->service_name : 'N/A') . "'
                        data-result = '" . htmlspecialchars($his->result) . "'
                        data-result-obj = '" . htmlspecialchars($his) . "'
                        data-viewable-type='imaging'
                        data-viewable-id='{$his->id}'>
                        <i class='mdi mdi-eye'></i> View
                    </button>
                    <a href='" . route('imaging-requests.show', $his->id) . "' target='_blank' class='btn btn-primary btn-sm' onclick='trackResultPrint(\"imaging\", {$his->id})'>
                        <i class='mdi mdi-printer'></i> Print
                    </a>";

                // Show delete button only if:
                // 1. Current user is the requester
                // 2. Status is pending (1) or in progress (2), not yet billed, no results
                // 3. Within the note_edit_window
                $editWindowMinutes = appsettings('note_edit_window', 30);
                $withinWindow = $his->created_at && now()->diffInMinutes($his->created_at) <= $editWindowMinutes;
                $canDelete = (Auth::id() == $his->doctor_id)
                    && ($his->status == 1 || $his->status == 2)
                    && empty($his->billed_by)
                    && empty($his->result)
                    && $withinWindow;

                if ($canDelete) {
                    $serviceName = $his->service ? $his->service->service_name : 'N/A';
                    if ($his->encounter_id) {
                        $str .= "<button type='button' class='btn btn-danger btn-sm'
                            onclick='deleteImagingRequest({$his->id}, {$his->encounter_id}, \"{$serviceName}\")'
                            title='Delete this request'>
                            <i class='fa fa-trash'></i> Delete
                        </button>";
                    } else {
                        // Nurse-created item (no encounter) — use nurse route
                        $str .= "<button type='button' class='btn btn-danger btn-sm'
                            onclick='deleteNurseClinicalRequest(\"imaging\", {$his->id}, \"{$serviceName}\")'
                            title='Delete this request'>
                            <i class='fa fa-trash'></i> Delete
                        </button>";
                    }
                }

                // Re-order button (Plan §5.2)
                $roName = htmlspecialchars(($his->service) ? $his->service->service_name : 'N/A', ENT_QUOTES);
                $roSvcId = $his->service_id;
                $roPrice = optional(optional($his->service)->price)->sale_price ?? 0;
                $roCov   = optional($his->productOrServiceRequest)->coverage_mode ?? '';
                $roPay   = optional($his->productOrServiceRequest)->payable_amount ?? $roPrice;
                $roClaim = optional($his->productOrServiceRequest)->claims_amount ?? 0;
                $str .= "<button type='button' class='btn btn-outline-primary btn-sm re-order-btn ms-1'
                    data-type='imaging'
                    data-service-id='{$roSvcId}'
                    data-name='{$roName}'
                    data-price='{$roPrice}'
                    data-coverage-mode='{$roCov}'
                    data-payable='{$roPay}'
                    data-claims='{$roClaim}'
                    title='Add to current imaging requests'>
                    <i class='fa fa-redo'></i> Re-order
                </button>";

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
        $items = ProductRequest::with([
            'product.price', 'product.category', 'encounter', 'patient', 'productOrServiceRequest', 'doctor', 'biller',
            'adaptedFromProduct', 'adapter', 'qtyAdjuster'
        ])
            ->where('status', 1)->where('patient_id', $patient_id)->orderBy('created_at', 'DESC')->get();

        // Batch-load HMO tariff previews so estimate badges show correct values before billing
        $patient = Patient::find($patient_id);
        $tariffMap = [];
        if ($patient && $patient->hmo_id) {
            $productIds = $items->pluck('product_id')->unique()->filter()->values()->toArray();
            $previews = HmoHelper::batchPreviewTariffs($patient->hmo_id, $productIds);
            $tariffMap = $previews['products'];
        }

        $tariffTotals = [];
        foreach ($items as $item) {
            $t = $tariffMap[$item->product_id] ?? null;
            if (!$t) continue;
            $qty = $item->qty ?? 1;
            $tariffTotals[$item->id] = [
                'payable_amount' => round($t['payable_amount'] * $qty, 2),
                'claims_amount'  => round($t['claims_amount'] * $qty, 2),
                'coverage_mode'  => $t['coverage_mode'] ?? 'none',
            ];
        }

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
            ->addColumn('payable_amount', function ($item) use ($tariffTotals, $patient) {
                if ($patient && $patient->hmo_id && isset($tariffTotals[$item->id])) {
                    return $tariffTotals[$item->id]['payable_amount'];
                }
                $posr = $item->productOrServiceRequest;
                if ($posr) return $posr->payable_amount ?? 0;
                $cashPrice = optional(optional($item->product)->price)->current_sale_price ?? 0;
                return $cashPrice * ($item->qty ?? 1);
            })
            ->addColumn('claims_amount', function ($item) use ($tariffTotals, $patient) {
                if ($patient && $patient->hmo_id && isset($tariffTotals[$item->id])) {
                    return $tariffTotals[$item->id]['claims_amount'];
                }
                return optional($item->productOrServiceRequest)->claims_amount ?? 0;
            })
            ->addColumn('coverage_mode', function ($item) use ($tariffTotals, $patient) {
                if ($patient && $patient->hmo_id && isset($tariffTotals[$item->id])) {
                    return $tariffTotals[$item->id]['coverage_mode'] ?? 'primary';
                }
                $posr = $item->productOrServiceRequest;
                if ($posr && !empty($posr->coverage_mode)) return $posr->coverage_mode;
                return 'cash';
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
                // Get stock from StockBatch (source of truth) - filtered by Hub & Satellite stores only
                return (int) \App\Models\StockBatch::where('product_id', $item->product_id)
                    ->where('current_qty', '>', 0)
                    ->whereHas('store', function ($q) {
                        $q->whereIn('distribution_role', [\App\Models\Store::ROLE_PHARMACY_HUB, \App\Models\Store::ROLE_PHARMACY_SATELLITE]);
                    })
                    ->sum('current_qty');
            })
            ->addColumn('store_stocks', function ($item) {
                if (!$item->product_id) return [];
                // Get stock grouped by store from StockBatch - filtered by Hub & Satellite stores only
                $storeStockData = \App\Models\StockBatch::where('product_id', $item->product_id)
                    ->where('current_qty', '>', 0)
                    ->whereHas('store', function ($q) {
                        $q->whereIn('distribution_role', [\App\Models\Store::ROLE_PHARMACY_HUB, \App\Models\Store::ROLE_PHARMACY_SATELLITE]);
                    })
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
            ->addColumn('tariff_preview', function ($item) use ($tariffMap, $patient) {
                if (!$patient || !$patient->hmo_id) return null;
                $t = $tariffMap[$item->product_id] ?? null;
                if (!$t) return ['no_tariff' => true];
                $qty = $item->qty ?? 1;
                return [
                    'payable_amount' => round($t['payable_amount'] * $qty, 2),
                    'claims_amount'  => round($t['claims_amount'] * $qty, 2),
                    'coverage_mode'  => $t['coverage_mode'],
                ];
            })
            ->addColumn('adapted_from_product_name', function ($item) {
                return optional($item->adaptedFromProduct)->product_name;
            })
            ->addColumn('adapted_from_product_code', function ($item) {
                return optional($item->adaptedFromProduct)->product_code;
            })
            ->addColumn('adaptation_note', function ($item) {
                return $item->adaptation_note;
            })
            ->addColumn('adapted_at', function ($item) {
                return $item->adapted_at ? $item->adapted_at->format('M j, Y h:i A') : null;
            })
            ->addColumn('adapted_by_name', function ($item) {
                return $item->adapted_by ? userfullname($item->adapted_by) : null;
            })
            ->addColumn('qty_adjusted_from', function ($item) {
                return $item->qty_adjusted_from;
            })
            ->addColumn('qty_adjustment_reason', function ($item) {
                return $item->qty_adjustment_reason;
            })
            ->addColumn('qty_adjusted_at', function ($item) {
                return $item->qty_adjusted_at ? $item->qty_adjusted_at->format('M j, Y h:i A') : null;
            })
            ->addColumn('qty_adjusted_by_name', function ($item) {
                return $item->qty_adjusted_by ? userfullname($item->qty_adjusted_by) : null;
            })
            ->make(true);
    }

    public function prescDispenseList($patient_id)
    {
        $items = ProductRequest::with([
            'product.price', 'product.category', 'encounter', 'patient', 'productOrServiceRequest.payment', 'doctor', 'biller',
            'adaptedFromProduct', 'adapter', 'qtyAdjuster'
        ])
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
                // Get stock from StockBatch (source of truth) - filtered by Hub & Satellite stores only
                return (int) \App\Models\StockBatch::where('product_id', $item->product_id)
                    ->where('current_qty', '>', 0)
                    ->whereHas('store', function ($q) {
                        $q->whereIn('distribution_role', [\App\Models\Store::ROLE_PHARMACY_HUB, \App\Models\Store::ROLE_PHARMACY_SATELLITE]);
                    })
                    ->sum('current_qty');
            })
            ->addColumn('store_stocks', function ($item) {
                if (!$item->product_id) return [];
                // Get stock grouped by store from StockBatch - filtered by Hub & Satellite stores only
                $storeStockData = \App\Models\StockBatch::where('product_id', $item->product_id)
                    ->where('current_qty', '>', 0)
                    ->whereHas('store', function ($q) {
                        $q->whereIn('distribution_role', [\App\Models\Store::ROLE_PHARMACY_HUB, \App\Models\Store::ROLE_PHARMACY_SATELLITE]);
                    })
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
            ->addColumn('adapted_from_product_name', function ($item) {
                return optional($item->adaptedFromProduct)->product_name;
            })
            ->addColumn('adapted_from_product_code', function ($item) {
                return optional($item->adaptedFromProduct)->product_code;
            })
            ->addColumn('adaptation_note', function ($item) {
                return $item->adaptation_note;
            })
            ->addColumn('adapted_at', function ($item) {
                return $item->adapted_at ? $item->adapted_at->format('M j, Y h:i A') : null;
            })
            ->addColumn('adapted_by_name', function ($item) {
                return $item->adapted_by ? userfullname($item->adapted_by) : null;
            })
            ->addColumn('qty_adjusted_from', function ($item) {
                return $item->qty_adjusted_from;
            })
            ->addColumn('qty_adjustment_reason', function ($item) {
                return $item->qty_adjustment_reason;
            })
            ->addColumn('qty_adjusted_at', function ($item) {
                return $item->qty_adjusted_at ? $item->qty_adjusted_at->format('M j, Y h:i A') : null;
            })
            ->addColumn('qty_adjusted_by_name', function ($item) {
                return $item->qty_adjusted_by ? userfullname($item->qty_adjusted_by) : null;
            })
            ->make(true);
    }

    /**
     * Get billed items that are PENDING payment or HMO validation
     * (status=2 but NOT ready to dispense)
     */
    public function prescPendingList($patient_id)
    {
        $items = ProductRequest::with([
            'product.price', 'product.category', 'encounter', 'patient', 'productOrServiceRequest.payment', 'doctor', 'biller',
            'adaptedFromProduct', 'adapter', 'qtyAdjuster'
        ])
            ->where('status', 2)
            ->where('patient_id', $patient_id)
            ->whereHas('productOrServiceRequest', function ($q) {
                $q->where(function ($query) {
                    // Items awaiting payment (payable > 0 and not paid - payment_id is null)
                    $query->where('payable_amount', '>', 0)
                        ->whereNull('payment_id');
                })->orWhere(function ($query) {
                    // Items awaiting HMO validation (claims > 0 and not validated)
                    $query->where('claims_amount', '>', 0)
                        ->where(function ($q2) {
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
                // Get stock from StockBatch (source of truth) - filtered by Hub & Satellite stores only
                return (int) \App\Models\StockBatch::where('product_id', $item->product_id)
                    ->where('current_qty', '>', 0)
                    ->whereHas('store', function ($q) {
                        $q->whereIn('distribution_role', [\App\Models\Store::ROLE_PHARMACY_HUB, \App\Models\Store::ROLE_PHARMACY_SATELLITE]);
                    })
                    ->sum('current_qty');
            })
            ->addColumn('store_stocks', function ($item) {
                if (!$item->product_id) return [];
                // Get stock grouped by store from StockBatch - filtered by Hub & Satellite stores only
                $storeStockData = \App\Models\StockBatch::where('product_id', $item->product_id)
                    ->where('current_qty', '>', 0)
                    ->whereHas('store', function ($q) {
                        $q->whereIn('distribution_role', [\App\Models\Store::ROLE_PHARMACY_HUB, \App\Models\Store::ROLE_PHARMACY_SATELLITE]);
                    })
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
            ->addColumn('adapted_from_product_name', function ($item) {
                return optional($item->adaptedFromProduct)->product_name;
            })
            ->addColumn('adapted_from_product_code', function ($item) {
                return optional($item->adaptedFromProduct)->product_code;
            })
            ->addColumn('adaptation_note', function ($item) {
                return $item->adaptation_note;
            })
            ->addColumn('adapted_at', function ($item) {
                return $item->adapted_at ? $item->adapted_at->format('M j, Y h:i A') : null;
            })
            ->addColumn('adapted_by_name', function ($item) {
                return $item->adapted_by ? userfullname($item->adapted_by) : null;
            })
            ->addColumn('qty_adjusted_from', function ($item) {
                return $item->qty_adjusted_from;
            })
            ->addColumn('qty_adjustment_reason', function ($item) {
                return $item->qty_adjustment_reason;
            })
            ->addColumn('qty_adjusted_at', function ($item) {
                return $item->qty_adjusted_at ? $item->qty_adjusted_at->format('M j, Y h:i A') : null;
            })
            ->addColumn('qty_adjusted_by_name', function ($item) {
                return $item->qty_adjusted_by ? userfullname($item->qty_adjusted_by) : null;
            })
            ->make(true);
    }

    /**
     * Get billed items that are READY for dispense
     * (status=2 AND paid/validated as needed)
     */
    public function prescReadyList($patient_id)
    {
        $items = ProductRequest::with([
            'product.price', 'product.category', 'encounter', 'patient', 'productOrServiceRequest.payment', 'doctor', 'biller', 'procedureItem.procedure.service',
            'adaptedFromProduct', 'adapter', 'qtyAdjuster'
        ])
            ->where('status', 2)
            ->where('patient_id', $patient_id)
            ->where(function ($q) {
                // Items without POSR (direct billing) - always ready
                $q->whereDoesntHave('productOrServiceRequest')
                    // OR items with POSR that are ready
                    ->orWhereHas('productOrServiceRequest', function ($query) {
                        $query->where(function ($q2) {
                            // Cash items: payable > 0, claims = 0 or null, paid (payment_id not null)
                            $q2->where('payable_amount', '>', 0)
                                ->where(function ($q3) {
                                    $q3->where('claims_amount', '<=', 0)->orWhereNull('claims_amount');
                                })
                                ->whereNotNull('payment_id');
                        })->orWhere(function ($q2) {
                            // Full HMO items: payable = 0 or null, claims > 0, validated
                            $q2->where(function ($q3) {
                                $q3->where('payable_amount', '<=', 0)->orWhereNull('payable_amount');
                            })
                                ->where('claims_amount', '>', 0)
                                ->whereIn('validation_status', ['validated', 'approved']);
                        })->orWhere(function ($q2) {
                            // Co-pay items: payable > 0, claims > 0, both paid and validated
                            $q2->where('payable_amount', '>', 0)
                                ->where('claims_amount', '>', 0)
                                ->whereIn('validation_status', ['validated', 'approved'])
                                ->whereNotNull('payment_id');
                        });
                    })
                    // OR bundled procedure items (no separate billing, procedure covers it)
                    ->orWhereHas('procedureItem', function ($query) {
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
                // Get stock from StockBatch (source of truth) - filtered by Hub & Satellite stores only
                return (int) \App\Models\StockBatch::where('product_id', $item->product_id)
                    ->where('current_qty', '>', 0)
                    ->whereHas('store', function ($q) {
                        $q->whereIn('distribution_role', [\App\Models\Store::ROLE_PHARMACY_HUB, \App\Models\Store::ROLE_PHARMACY_SATELLITE]);
                    })
                    ->sum('current_qty');
            })
            ->addColumn('store_stocks', function ($item) {
                if (!$item->product_id) return [];
                // Get stock grouped by store from StockBatch - filtered by Hub & Satellite stores only
                $storeStockData = \App\Models\StockBatch::where('product_id', $item->product_id)
                    ->where('current_qty', '>', 0)
                    ->whereHas('store', function ($q) {
                        $q->whereIn('distribution_role', [\App\Models\Store::ROLE_PHARMACY_HUB, \App\Models\Store::ROLE_PHARMACY_SATELLITE]);
                    })
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
            ->addColumn('adapted_from_product_name', function ($item) {
                return optional($item->adaptedFromProduct)->product_name;
            })
            ->addColumn('adapted_from_product_code', function ($item) {
                return optional($item->adaptedFromProduct)->product_code;
            })
            ->addColumn('adaptation_note', function ($item) {
                return $item->adaptation_note;
            })
            ->addColumn('adapted_at', function ($item) {
                return $item->adapted_at ? $item->adapted_at->format('M j, Y h:i A') : null;
            })
            ->addColumn('adapted_by_name', function ($item) {
                return $item->adapted_by ? userfullname($item->adapted_by) : null;
            })
            ->addColumn('qty_adjusted_from', function ($item) {
                return $item->qty_adjusted_from;
            })
            ->addColumn('qty_adjustment_reason', function ($item) {
                return $item->qty_adjustment_reason;
            })
            ->addColumn('qty_adjusted_at', function ($item) {
                return $item->qty_adjusted_at ? $item->qty_adjusted_at->format('M j, Y h:i A') : null;
            })
            ->addColumn('qty_adjusted_by_name', function ($item) {
                return $item->qty_adjusted_by ? userfullname($item->qty_adjusted_by) : null;
            })
            ->make(true);
    }

    public function prescHistoryList($patient_id)
    {
        // Show ALL prescription requests (not just dispensed) for complete history
        $items = ProductRequest::with(['product.price', 'product.category', 'encounter', 'patient', 'productOrServiceRequest.payment', 'productOrServiceRequest.parent.service', 'productOrServiceRequest.parent.children.service', 'productOrServiceRequest.parent.children.product', 'doctor', 'biller', 'dispenser', 'adaptedFromProduct', 'adapter', 'qtyAdjuster'])
            ->where('patient_id', $patient_id)
            ->where(function ($query) {
                $query->whereNull('product_request_id')
                    ->orWhereHas('productOrServiceRequest', function ($posr) {
                        $posr->whereNull('removed_at');
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
            ->addColumn('global_stock', function ($item) {
                if (!$item->product_id) return 0;
                // Get stock from StockBatch (source of truth) - filtered by Hub & Satellite stores only
                return (int) \App\Models\StockBatch::where('product_id', $item->product_id)
                    ->where('current_qty', '>', 0)
                    ->whereHas('store', function ($q) {
                        $q->whereIn('distribution_role', [\App\Models\Store::ROLE_PHARMACY_HUB, \App\Models\Store::ROLE_PHARMACY_SATELLITE]);
                    })
                    ->sum('current_qty');
            })
            ->addColumn('store_stocks', function ($item) {
                if (!$item->product_id) return [];
                // Get stock grouped by store from StockBatch - filtered by Hub & Satellite stores only
                $storeStockData = \App\Models\StockBatch::where('product_id', $item->product_id)
                    ->where('current_qty', '>', 0)
                    ->whereHas('store', function ($q) {
                        $q->whereIn('distribution_role', [\App\Models\Store::ROLE_PHARMACY_HUB, \App\Models\Store::ROLE_PHARMACY_SATELLITE]);
                    })
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

                // Render Adaptation Details if applicable
                $adaptationHtml = '';
                if ($item->adapted_from_product_id) {
                    $originalProductName = optional($item->adaptedFromProduct)->product_name ?? 'Unknown';
                    $adaptationNote = htmlspecialchars($item->adaptation_note ?? '', ENT_QUOTES);
                    $adaptedBy = $item->adapted_by ? userfullname($item->adapted_by) : 'N/A';
                    $adaptedAt = $item->adapted_at ? date('M j, Y h:i A', strtotime($item->adapted_at)) : 'N/A';
                    
                    $adaptationHtml = "
                        <div class='mt-1 p-2 bg-light rounded border-start border-warning'>
                            <small class='text-warning d-block'><strong><i class='mdi mdi-swap-horizontal'></i> Adapted Prescription</strong></small>
                            <small class='text-muted d-block'>Original Drug: <strong>{$originalProductName}</strong></small>
                            <small class='text-muted d-block'>Reason: <em>{$adaptationNote}</em></small>
                            <small class='text-muted d-block'>Adapted by: {$adaptedBy} on {$adaptedAt}</small>
                        </div>
                    ";
                }

                // Render Quantity Adjustment Details if applicable
                $qtyAdjustmentHtml = '';
                if ($item->qty_adjusted_from !== null) {
                    $qtyAdjustedFrom = $item->qty_adjusted_from;
                    $qtyAdjustmentReason = htmlspecialchars($item->qty_adjustment_reason ?? '', ENT_QUOTES);
                    $qtyAdjustedBy = $item->qty_adjusted_by ? userfullname($item->qty_adjusted_by) : 'N/A';
                    $qtyAdjustedAt = $item->qty_adjusted_at ? date('M j, Y h:i A', strtotime($item->qty_adjusted_at)) : 'N/A';

                    $qtyAdjustmentHtml = "
                        <div class='mt-1 p-2 bg-light rounded border-start border-info'>
                            <small class='text-info d-block'><strong><i class='mdi mdi-scale-balance'></i> Quantity Adjusted</strong></small>
                            <small class='text-muted d-block'>Original Requested Qty: <strong>{$qtyAdjustedFrom}</strong></small>
                            <small class='text-muted d-block'>Adjusted Qty: <strong>{$qty}</strong></small>
                            <small class='text-muted d-block'>Reason: <em>{$qtyAdjustmentReason}</em></small>
                            <small class='text-muted d-block'>Adjusted by: {$qtyAdjustedBy} on {$qtyAdjustedAt}</small>
                        </div>
                    ";
                }

                // Custom quantity layout based on dispense status
                $qtyDisplay = $status == 3 
                    ? "<span class='ms-2 text-success'><i class='mdi mdi-check-circle-outline'></i> Qty Dispensed: <strong>{$qty}</strong></span>"
                    : "<span class='ms-2'><i class='mdi mdi-numeric'></i> Qty: {$qty}</span>";

                // Re-prescribe button data (Plan §5.2)
                $roName  = htmlspecialchars($productName, ENT_QUOTES);
                $roDose  = htmlspecialchars($dose, ENT_QUOTES);
                $roCov   = optional($item->productOrServiceRequest)->coverage_mode ?? '';
                $roPay   = optional($item->productOrServiceRequest)->payable_amount ?? $price;
                $roClaim = optional($item->productOrServiceRequest)->claims_amount ?? 0;

                // Delete button — only if requester, within edit window, not yet billed/dispensed
                $editWindowMinutes = appsettings('note_edit_window', 30);
                $withinWindow = $item->created_at && now()->diffInMinutes($item->created_at) <= $editWindowMinutes;
                $canDeletePresc = (Auth::id() == $item->doctor_id)
                    && ($status == 1 || $status == 2)
                    && empty($item->billed_by)
                    && $withinWindow;

                $deleteBtn = '';
                if ($canDeletePresc) {
                    if ($item->encounter_id) {
                        $deleteBtn = "<button type='button' class='btn btn-danger btn-sm me-1'
                            onclick='deletePrescription({$item->id}, {$item->encounter_id}, \"{$roName}\")'
                            title='Delete this prescription'>
                            <i class='fa fa-trash'></i> Delete
                        </button>";
                    } else {
                        // Nurse-created item (no encounter) — use nurse route
                        $deleteBtn = "<button type='button' class='btn btn-danger btn-sm me-1'
                            onclick='deleteNurseClinicalRequest(\"prescription\", {$item->id}, \"{$roName}\")'
                            title='Delete this prescription'>
                            <i class='fa fa-trash'></i> Delete
                        </button>";
                    }
                }

                // Combo info block (if this prescription is a combo child)
                $bundleHtml = '';
                $posr = $item->productOrServiceRequest;
                if ($posr && $posr->is_bundle_item && $posr->parent_id) {
                    $parentReq = $posr->parent;
                    if ($parentReq) {
                        $bName    = optional($parentReq->service)->service_name ?? 'Combo';
                        $bPay     = $parentReq->payable_amount ?? 0;
                        $bClaims  = $parentReq->claims_amount ?? 0;
                        $bId      = $parentReq->id;
                        $bPaid    = $parentReq->payment_id !== null;
                        $bCreator = $parentReq->staff_user_id == Auth::id();
                        $bChildren = $parentReq->children->map(function ($c) {
                            return ['name' => optional($c->service)->service_name ?? optional($c->product)->product_name ?? 'Item', 'qty' => $c->qty ?? 1, 'price' => $c->payable_amount ?? $c->amount ?? 0];
                        })->values()->toArray();
                        $bDataJson = htmlspecialchars(json_encode(['name' => $bName, 'payable_amount' => $bPay, 'claims_amount' => $bClaims, 'items' => $bChildren]), ENT_QUOTES);
                        $bItemsJson = htmlspecialchars(json_encode($bChildren), ENT_QUOTES);
                        $bRemoveUrl = url('/service-combo/remove-bundle');
                        $bNameEsc   = htmlspecialchars($bName, ENT_QUOTES);
                        $bundleHtml .= "<div class='bundle-info-block mt-1 mb-1 p-2 bg-light rounded'>";
                        $bundleHtml .= "<small class='text-muted d-block mb-1'><i class='mdi mdi-link-variant'></i> <strong>Combo: {$bNameEsc}</strong> &mdash; &#8358;" . number_format($bPay, 2) . " patient / &#8358;" . number_format($bClaims, 2) . " claims</small>";
                        $bundleHtml .= "<div class='d-flex gap-1 flex-wrap'>";
                        $bundleHtml .= "<button type='button' class='btn btn-outline-primary btn-sm' onclick='window.BundleViewModal && BundleViewModal.show({$bDataJson})' title='View combo details'><i class='fa fa-info-circle'></i> View Combo</button>";
                        if (!$bPaid && $bCreator) {
                            $bundleHtml .= "<button type='button' class='btn btn-outline-danger btn-sm' data-parent-id='{$bId}' data-bundle-name='{$bNameEsc}' data-items='{$bItemsJson}' data-remove-url='{$bRemoveUrl}' onclick='showBundleRemove(this)' title='Remove this combo'><i class='fa fa-trash'></i> Remove Combo</button>";
                        }
                        $bundleHtml .= "</div></div>";
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
                            {$qtyDisplay}
                            <span class='ms-2'><i class='mdi mdi-cash'></i> ₦" . number_format($price, 2) . "</span>
                        </div>
                        {$statusInfo}
                        {$bundleHtml}
                        {$adaptationHtml}
                        {$qtyAdjustmentHtml}
                        <div class='mt-1'>
                            {$deleteBtn}
                            <button type='button' class='btn btn-outline-primary btn-sm re-order-btn'
                                data-type='prescriptions'
                                data-product-id='{$item->product_id}'
                                data-name='{$roName}'
                                data-price='{$price}'
                                data-dose='{$roDose}'
                                data-coverage-mode='{$roCov}'
                                data-payable='{$roPay}'
                                data-claims='{$roClaim}'
                                title='Add to current prescriptions'>
                                <i class='fa fa-redo'></i> Re-prescribe
                            </button>
                        </div>
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
            ->orderBy('created_at', 'DESC');

        // Exclude the current encounter if specified
        if ($excludeEncounterId) {
            $query->where('id', '!=', $excludeEncounterId);
        }

        $hist = $query->get();

        return DataTables::of($hist)
            ->addColumn('info', function ($hist) {
                $str = '<div class="card-modern mb-2" style="border-left: 4px solid ' . ($hist->completed ? '#0d6efd' : '#ffc107') . ';">';
                $str .= '<div class="card-body p-3">';

                // Header with doctor name and status
                $str .= '<div class="d-flex justify-content-between align-items-start mb-3">';
                $str .= "<div>";
                $str .= "<h6 class='mb-0'><i class='mdi mdi-account-circle'></i> <span class='text-primary'>" . userfullname($hist->doctor_id) . "</span></h6>";
                $specialty = $hist->doctor->staff_profile->specialization->name ?? 'General Practitioner';
                $str .= "<small class='text-muted' style='margin-left: 1.5rem; display: block; margin-top: 2px;'>" . $specialty . "</small>";
                $str .= "</div>";
                $str .= '<div class="d-flex flex-column align-items-end gap-1">';
                $str .= "<span class='badge bg-info'>" . date('h:i a D M j, Y', strtotime($hist->created_at)) . "</span>";
                if (!$hist->completed) {
                    $str .= "<span class='badge bg-warning text-dark'><i class='mdi mdi-clock-outline'></i> In Progress</span>";
                }
                $str .= '</div>';
                $str .= '</div>';

                // Reasons for encounter
                if ($hist->reasons_for_encounter != '') {
                    $rawReasons = $hist->reasons_for_encounter;
                    $decodedReasons = json_decode($rawReasons, true);

                    $str .= '<div class="mb-3">';
                    $str .= '<small><b><i class="mdi mdi-format-list-bulleted"></i> Reason(s) for Encounter/Diagnosis (ICPC-2):</b></small><br>';

                    if (is_array($decodedReasons) && isset($decodedReasons[0]['code'])) {
                        // New JSON format with per-diagnosis comments
                        $str .= '<table class="table table-sm table-bordered mb-1" style="font-size:0.8rem;">';
                        $str .= '<thead><tr><th>Code</th><th>Diagnosis</th><th>Status</th><th>Course</th></tr></thead><tbody>';
                        foreach ($decodedReasons as $dx) {
                            $code = htmlspecialchars($dx['code'] ?? '');
                            $name = htmlspecialchars($dx['name'] ?? '');
                            $c1 = htmlspecialchars($dx['comment_1'] ?? '');
                            $c2 = htmlspecialchars($dx['comment_2'] ?? '');
                            $c1Badge = $c1 ? "<span class='badge bg-secondary'>{$c1}</span>" : '<span class="text-muted">-</span>';
                            $c2Badge = $c2 ? "<span class='badge bg-secondary'>{$c2}</span>" : '<span class="text-muted">-</span>';
                            $str .= "<tr><td><code>{$code}</code></td><td>{$name}</td><td>{$c1Badge}</td><td>{$c2Badge}</td></tr>";
                        }
                        $str .= '</tbody></table>';
                    } else {
                        // Legacy comma-separated format
                        $reasons_for_encounter = explode(',', $rawReasons);
                        foreach ($reasons_for_encounter as $reason) {
                            $str .= "<span class='badge bg-light text-dark me-1 mb-1'>" . trim($reason) . "</span>";
                        }
                    }
                    $str .= '</div>';
                }

                // Diagnosis comments (legacy global - only show if no per-diagnosis JSON)
                $hasPerDiagJson = false;
                if ($hist->reasons_for_encounter) {
                    $checkJson = json_decode($hist->reasons_for_encounter, true);
                    $hasPerDiagJson = is_array($checkJson) && isset($checkJson[0]['code']);
                }
                if (!$hasPerDiagJson && $hist->reasons_for_encounter_comment_1) {
                    $str .= '<div class="mb-2"><small><b><i class="mdi mdi-comment-text"></i> Diagnosis Comment 1:</b> ' . $hist->reasons_for_encounter_comment_1 . '</small></div>';
                }
                if (!$hasPerDiagJson && $hist->reasons_for_encounter_comment_2) {
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
                'notes' => 'nullable',
            ]);

            $updateData = [
                'notes' => $request->notes ?? '',
            ];

            // Also autosave diagnosis data if provided
            if ($request->has('reasons_for_encounter_data')) {
                $diagnosisData = $request->input('reasons_for_encounter_data');
                if ($diagnosisData && $diagnosisData !== '[]') {
                    $updateData['reasons_for_encounter'] = $diagnosisData;
                }
            }

            Encounter::where('id', $request->encounter_id)->update($updateData);

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
                // Check for per-diagnosis comments JSON (new format)
                $perDiagnosisComments = $request->input('per_diagnosis_comments');
                if ($perDiagnosisComments) {
                    $perDiagJson = json_decode($perDiagnosisComments, true);
                    if (is_array($perDiagJson) && count($perDiagJson) > 0) {
                        $reasonsString = json_encode($perDiagJson);
                    }
                }

                // Fallback to legacy comma-separated format
                if (!$reasonsString) {
                    $reasonsString = $request->reasons_for_encounter;
                }

                $comment1 = $request->reasons_for_encounter_comment_1;
                $comment2 = $request->reasons_for_encounter_comment_2;

                // Process custom reasons from comma-separated or JSON
                $reasonsToCheck = [];
                if ($reasonsString) {
                    $decoded = json_decode($reasonsString, true);
                    if (is_array($decoded) && isset($decoded[0]['code'])) {
                        // JSON format - extract names for custom reason check
                        foreach ($decoded as $item) {
                            $reasonsToCheck[] = ($item['code'] ?? '') . '-' . ($item['name'] ?? '');
                        }
                    } else {
                        // Legacy comma-separated
                        $reasonsToCheck = explode(',', $reasonsString);
                    }

                    foreach ($reasonsToCheck as $reason) {
                        $reason = trim($reason);
                        if (empty($reason)) continue;

                        $existingReason = ReasonForEncounter::where(function ($query) use ($reason) {
                            $query->where('code', 'LIKE', $reason . '%')
                                ->orWhereRaw("CONCAT(code, '-', name) = ?", [$reason]);
                        })->first();

                        if (!$existingReason && !empty($reason)) {
                            ReasonForEncounter::createCustomReason($reason);
                        }
                    }
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
            $patient = Patient::with('user')->find(request()->get('patient_id'));
            $queue_id = $request->get('queue_id');
            $doctorQueue = $queue_id ? DoctorQueue::with('clinic')->find($queue_id) : null;
            $clinic = ($doctorQueue && $doctorQueue->clinic) ? $doctorQueue->clinic : Clinic::find($doctor->clinic_id);
            $req_entry = ProductOrServiceRequest::find(request()->get('req_entry_id'));
            if (!$req_entry && $doctorQueue && $doctorQueue->request_entry_id) {
                $req_entry = ProductOrServiceRequest::find($doctorQueue->request_entry_id);
            }
            $admission_exists = AdmissionRequest::where('patient_id', request()->get('patient_id'))->where('discharged', 0)->first();

            // Single query — derive categories/subcategories in memory
            $reasons_for_encounter_list = ReasonForEncounter::all();
            $reasons_for_encounter_cat_list = $reasons_for_encounter_list->unique('category')->values();
            $reasons_for_encounter_sub_cat_list = $reasons_for_encounter_list->unique(fn($r) => $r->sub_category . '|' . $r->category)->values();

            // dd($reasons_for_encounter_cat_list);

            // Find or create encounter specific to this service request/queue
            $encounterQuery = Encounter::where('doctor_id', $doctor->id)
                ->where('patient_id', $patient->id)
                ->where('completed', false);

            if ($doctorQueue) {
                // Try to find encounter specifically for this queue first
                $encounter = (clone $encounterQuery)->where('queue_id', $doctorQueue->id)->first();
                
                // If not found, try to find an encounter with the same service request
                if (!$encounter && $req_entry) {
                    $encounter = (clone $encounterQuery)->where('service_request_id', $req_entry->id)->first();
                }
                
                // Fallback: get any active encounter for this patient/doctor that doesn't have a queue_id or has the same queue_id
                if (!$encounter) {
                    $encounter = (clone $encounterQuery)->whereNull('queue_id')->first();
                }
            } else {
                if ($req_entry) {
                    $encounterQuery->where('service_request_id', $req_entry->id);
                }
                $encounter = $encounterQuery->first();
            }

            if (!$encounter) {
                $encounter = new Encounter();
                $encounter->doctor_id = $doctor->id;
                $encounter->service_request_id = $req_entry ? $req_entry->id : null;
                $encounter->service_id = $req_entry ? $req_entry->service_id : null;
                $encounter->patient_id = $patient->id;
                $encounter->queue_id = $doctorQueue ? $doctorQueue->id : null;
                $encounter->started_at = now();
                $encounter->save();

                // Transition queue to IN_CONSULTATION
                if ($doctorQueue && $doctorQueue->status !== QueueStatus::IN_CONSULTATION) {
                    try {
                        $queueService = app(QueueStatusService::class);
                        $queueService->transition($doctorQueue, QueueStatus::IN_CONSULTATION);
                    } catch (\Exception $transitionEx) {
                        // Fallback: direct update
                        $doctorQueue->update([
                            'status' => QueueStatus::IN_CONSULTATION,
                            'consultation_started_at' => $doctorQueue->consultation_started_at ?? now(),
                        ]);
                    }
                }
            } else {
                // Update existing encounter (in case doctor or service changed)
                $encounter->doctor_id = $doctor->id;
                $encounter->service_request_id = $req_entry ? $req_entry->id : null;
                $encounter->service_id = $req_entry ? $req_entry->service_id : null;
                $encounter->patient_id = $patient->id;
                if (!$encounter->queue_id && $doctorQueue) {
                    $encounter->queue_id = $doctorQueue->id;
                }
                $encounter->update();
            }

            if ($encounter) {
                if (null != $admission_exists) {
                    $admission_exists_ = 1;
                } else {
                    $admission_exists_ = 0;
                }

                // Pre-load data for inline blade queries (avoid N+1 in view)
                $allClinics = Clinic::orderBy('name')->get();
                $doctorStaffList = Staff::whereHas('user', function ($q) {
                    $q->whereHas('roles', fn($r) => $r->where('name', 'DOCTOR'));
                })->with('user:id,surname,firstname,othername')->orderBy('id')->get();
                $patientWeight = \App\Models\VitalSign::where('patient_id', $patient->id)
                    ->whereNotNull('weight')->where('weight', '>', 0)
                    ->orderBy('created_at', 'desc')->value('weight');

                // Dynamic ranges (Plan §B.1)
                $ageDays = $patient->dob ? now()->diffInDays($patient->dob) : null;
                $gender = $patient->gender;
                $dynamicRanges = [];
                if ($ageDays !== null) {
                    $rangeKeys = ['temp', 'heart_rate', 'resp_rate', 'spo2', 'bp_sys', 'bp_dia', 'sugar'];
                    foreach ($rangeKeys as $rk) {
                        $range = \App\Models\VitalRange::resolve($rk, $ageDays, $gender);
                        if ($range) {
                            $dynamicRanges[$rk] = $range->toArray();
                        }
                    }
                }

                if ($request->get('admission_req_id') != '' || $admission_exists_ == 1) {
                    $admission_request = AdmissionRequest::where('id', $request->admission_req_id)->where('discharged', 0)->first() ?? $admission_exists;
                    // for nursing notes
                    $patient_id = $patient->id;

                    // Single query for all nursing notes (types 1-5), keyed by type_id
                    $nursingNotes = NursingNote::with(['patient', 'createdBy', 'type'])
                        ->where('patient_id', $patient_id)
                        ->where('completed', false)
                        ->whereIn('nursing_note_type_id', [1, 2, 3, 4, 5])
                        ->get()
                        ->keyBy('nursing_note_type_id');

                    $observation_note   = $nursingNotes->get(1);
                    $treatment_sheet    = $nursingNotes->get(2);
                    $io_chart           = $nursingNotes->get(3);
                    $labour_record      = $nursingNotes->get(4);
                    $others_record      = $nursingNotes->get(5);

                    // Single query for all nursing note templates
                    $noteTemplates = NursingNoteType::whereIn('id', [1, 2, 3, 4, 5])->get()->keyBy('id');

                    $observation_note_template = $noteTemplates->get(1);
                    $treatment_sheet_template  = $noteTemplates->get(2);
                    $io_chart_template         = $noteTemplates->get(3);
                    $labour_record_template    = $noteTemplates->get(4);
                    $others_record_template    = $noteTemplates->get(5);

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
                        'doctorQueue' => $doctorQueue,
                        'reasons_for_encounter_list' => $reasons_for_encounter_list,
                        'reasons_for_encounter_cat_list' => $reasons_for_encounter_cat_list,
                        'reasons_for_encounter_sub_cat_list' => $reasons_for_encounter_sub_cat_list,
                        'allClinics' => $allClinics,
                        'doctorStaffList' => $doctorStaffList,
                        'patientWeight' => $patientWeight,
                        'vitals_template' => $clinic ? $clinic->vitals_template : null,
                        'clinic_name' => $clinic ? $clinic->name : null,
                        'dynamic_ranges' => $dynamicRanges,
                    ]);
                } else {
                    return view('admin.doctors.new_encounter')->with([
                        'patient' => $patient,
                        'doctor' => $doctor,
                        'clinic' => $clinic,
                        'req_entry' => $req_entry,
                        'admission_exists_' => $admission_exists_,
                        'encounter' => $encounter,
                        'doctorQueue' => $doctorQueue,
                        'reasons_for_encounter_list' => $reasons_for_encounter_list,
                        'reasons_for_encounter_cat_list' => $reasons_for_encounter_cat_list,
                        'reasons_for_encounter_sub_cat_list' => $reasons_for_encounter_sub_cat_list,
                        'allClinics' => $allClinics,
                        'doctorStaffList' => $doctorStaffList,
                        'patientWeight' => $patientWeight,
                        'vitals_template' => $clinic ? $clinic->vitals_template : null,
                        'clinic_name' => $clinic ? $clinic->name : null,
                        'dynamic_ranges' => $dynamicRanges,
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
            $patient = Patient::findOrFail($request->patient_id);

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
                // Check if per-diagnosis comments JSON is sent (new format)
                $perDiagnosisComments = $request->input('per_diagnosis_comments');
                if ($perDiagnosisComments) {
                    $perDiagJson = json_decode($perDiagnosisComments, true);
                    if (is_array($perDiagJson) && count($perDiagJson) > 0) {
                        $encounter->reasons_for_encounter = json_encode($perDiagJson);
                    } else {
                        $encounter->reasons_for_encounter = implode(',', $request->reasons_for_encounter);
                    }
                } else {
                    $encounter->reasons_for_encounter = implode(',', $request->reasons_for_encounter);
                }
                $encounter->reasons_for_encounter_comment_1 = $request->reasons_for_encounter_comment_1;
                $encounter->reasons_for_encounter_comment_2 = $request->reasons_for_encounter_comment_2;
            }
            $encounter->notes = $request->doctor_diagnosis;
            $encounter->completed = true;
            $encounter->update();

            // Determine emergency priority from the doctor queue
            $queuePriority = null;
            if ($request->queue_id && $request->queue_id !== 'ward_round') {
                $queuePriority = DoctorQueue::where('id', $request->queue_id)->value('priority');
            }

            // dd($encounter);
            if (isset($request->consult_invest_id) && count($request->consult_invest_id) > 0) {
                for ($r = 0; $r < count($request->consult_invest_id); ++$r) {
                    $invest = new LabServiceRequest();
                    $invest->service_id = $request->consult_invest_id[$r];
                    $invest->note = $request->consult_invest_note[$r];
                    $invest->encounter_id = $encounter->id;
                    $invest->patient_id = $request->patient_id;
                    $invest->doctor_id = Auth::id();
                    if ($queuePriority && $queuePriority !== 'routine') {
                        $invest->priority = $queuePriority;
                    }
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
                    if ($queuePriority && $queuePriority !== 'routine') {
                        $imaging->priority = $queuePriority;
                    }
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
     * Display a single encounter with its labs, imaging, procedures and prescriptions.
     */
    public function show(Encounter $encounter)
    {
        $encounter->load([
            'patient.user',
            'patient.hmo',
            'doctor',
            'labRequests.service',
            'imagingRequests.service',
            'productRequests.product',
        ]);

        $procedures = Procedure::with('procedureDefinition')
            ->where('encounter_id', $encounter->id)
            ->get();

        $diagnosisItems = [];
        if ($encounter->reasons_for_encounter) {
            $decoded = json_decode($encounter->reasons_for_encounter, true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    $diagnosisItems[] = is_array($item)
                        ? ($item['name'] ?? ($item['value'] ?? ''))
                        : $item;
                }
            } else {
                $diagnosisItems[] = $encounter->reasons_for_encounter;
            }
        }

        return view('admin.encounters.show', compact('encounter', 'procedures', 'diagnosisItems'));
    }

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
                // Check if per-diagnosis comments JSON is sent (new format)
                $perDiagnosisComments = $request->input('per_diagnosis_comments');
                if ($perDiagnosisComments) {
                    $perDiagJson = json_decode($perDiagnosisComments, true);
                    if (is_array($perDiagJson) && count($perDiagJson) > 0) {
                        // Store as JSON with per-diagnosis comments
                        $encounter->reasons_for_encounter = json_encode($perDiagJson);
                    } else {
                        // Fallback to comma-separated
                        $encounter->reasons_for_encounter = implode(',', $request->reasons_for_encounter);
                    }
                } else {
                    // Legacy: comma-separated
                    $encounter->reasons_for_encounter = implode(',', $request->reasons_for_encounter);
                }

                // Legacy global comments (kept for backward compatibility)
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
                'consult_presc_id.*' => 'required|integer|exists:products,id',
                'consult_presc_dose' => 'required|array|min:1',
                'consult_presc_dose.*' => 'nullable|string',
            ]);

            // Validate all selected products are drugs (prescriptions should only contain drugs)
            $nonDrugs = \App\Models\Product::whereIn('id', $request->consult_presc_id)
                ->where('product_type', '!=', 'drug')
                ->pluck('product_name');
            if ($nonDrugs->isNotEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only drug-type products can be prescribed. Non-drug items: ' . $nonDrugs->implode(', ')
                ], 422);
            }

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
                'outcome' => 'nullable|string',
                'death_record' => 'nullable|array',
            ]);

            DB::beginTransaction();

            // Mark encounter as complete
            $encounter->completed = true;
            $encounter->completed_at = now();
            $encounter->doctor_id = Auth::id();

            if ($request->outcome) {
                $encounter->outcome = $request->outcome;
            }
            $encounter->save();

            // Handle Death Record
            if ($request->outcome && str_starts_with($request->outcome, 'death')) {
                $patient = $encounter->patient;
                $patient->is_deceased = true;
                $patient->save();

                $dr = $request->death_record;
                \App\Models\DeathRecord::updateOrCreate(
                    ['patient_id' => $patient->id],
                    [
                        'encounter_id' => $encounter->id,
                        'death_type' => ($request->outcome === 'death_bid' ? 'BID' : 'RIP'),
                        'date_of_death' => $dr['date'] ?? now()->toDateString(),
                        'time_of_death' => $dr['time'] ?? now()->toTimeString(),
                        'cause_of_death_primary' => $dr['cause'] ?? 'Unknown',
                        'certified_by_doctor_id' => $dr['certified_by'] ?? Auth::id(),
                        'last_office_done' => false,
                        'disposition' => 'pending'
                    ]
                );
            }

            // Handle queue status using QueueStatusService
            if ($request->queue_id != 'ward_round') {
                $queue = DoctorQueue::find($request->queue_id);
                if ($queue) {
                    $endConsultation = $request->end_consultation && $request->end_consultation == '1';
                    $newStatus = $endConsultation ? QueueStatus::COMPLETED : QueueStatus::IN_CONSULTATION;

                    try {
                        $queueService = app(QueueStatusService::class);
                        $queueService->transition($queue, $newStatus);
                    } catch (\Exception $transitionEx) {
                        // Fallback: direct update if transition fails (e.g. already in target state)
                        Log::warning('QueueStatusService transition failed in finalizeEncounter, using fallback', [
                            'queue_id' => $queue->id,
                            'target_status' => $newStatus,
                            'error' => $transitionEx->getMessage(),
                        ]);
                        $queue->update(['status' => $newStatus]);
                    }
                }
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
            // Get all encounter IDs associated with the same queue visit or request entry to show all actions across sessions
            $encounterIds = Encounter::where(function ($q) use ($encounter) {
                if ($encounter->queue_id) {
                    $q->where('queue_id', $encounter->queue_id);
                }
                if ($encounter->service_request_id) {
                    $q->orWhere('service_request_id', $encounter->service_request_id);
                }
            })
            ->pluck('id')
            ->push($encounter->id)
            ->unique()
            ->toArray();

            // Get diagnosis/notes
            $diagnosis = [
                'saved' => !empty($encounter->notes) || !empty($encounter->reasons_for_encounter),
                'notes' => $encounter->notes,
                'reasons' => $encounter->reasons_for_encounter,
                'comment_1' => $encounter->reasons_for_encounter_comment_1,
                'comment_2' => $encounter->reasons_for_encounter_comment_2,
            ];

            // Get lab requests
            $labs = LabServiceRequest::whereIn('encounter_id', $encounterIds)
                ->with('service')
                ->get()
                ->map(function ($lab) {
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
            $imaging = ImagingServiceRequest::whereIn('encounter_id', $encounterIds)
                ->with('service')
                ->get()
                ->map(function ($img) {
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
            $prescriptions = ProductRequest::whereIn('encounter_id', $encounterIds)
                ->with('product')
                ->get()
                ->map(function ($presc) {
                    return [
                        'id' => $presc->id,
                        'name' => $presc->product->product_name ?? 'N/A',
                        'dose' => $presc->dose,
                        'status' => $presc->status ?? 1,
                        'created_at' => $presc->created_at->format('M d, Y H:i')
                    ];
                });

            // Get procedures
            $procedures = \App\Models\Procedure::whereIn('encounter_id', $encounterIds)
                ->with('service')
                ->get()
                ->map(function ($proc) {
                    return [
                        'id' => $proc->id,
                        'name' => $proc->service->service_name ?? 'N/A',
                        'code' => $proc->service->service_code ?? '',
                        'priority' => $proc->priority,
                        'status' => $proc->procedure_status,
                        'created_at' => $proc->created_at->format('M d, Y H:i')
                    ];
                });

            // Get referrals
            $referrals = \App\Models\SpecialistReferral::whereIn('encounter_id', $encounterIds)
                ->with(['targetClinic', 'targetDoctor.user'])
                ->get()
                ->map(function ($ref) {
                    $target = '';
                    if ($ref->referral_type === 'internal') {
                        $clinicName = $ref->targetClinic->name ?? 'Specialist';
                        $docName = $ref->targetDoctor ? userfullname($ref->targetDoctor->user_id) : 'Any Doctor';
                        $target = "Internal Referral to {$clinicName} ({$docName})";
                    } else {
                        $target = "External Referral to {$ref->external_facility_name}" . ($ref->external_doctor_name ? " (Dr. {$ref->external_doctor_name})" : "");
                    }
                    return [
                        'id' => $ref->id,
                        'type' => $ref->referral_type,
                        'target' => $target,
                        'reason' => $ref->reason,
                        'urgency' => $ref->urgency,
                        'created_at' => $ref->created_at->format('M d, Y H:i')
                    ];
                });

            // Get care plans
            $carePlans = \App\Models\NonPharmOrder::whereIn('encounter_id', $encounterIds)
                ->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'category' => $order->category,
                        'target_executor' => $order->target_executor,
                        'instructions' => $order->instructions,
                        'frequency' => $order->frequency,
                        'duration' => $order->duration,
                        'status' => $order->status,
                        'created_at' => $order->created_at->format('M d, Y H:i')
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'diagnosis' => $diagnosis,
                    'labs' => $labs,
                    'imaging' => $imaging,
                    'prescriptions' => $prescriptions,
                    'procedures' => $procedures,
                    'referrals' => $referrals,
                    'care_plans' => $carePlans,
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
            'q' => 'required|string|min:2'
        ]);

        $searchTerm = $request->q;

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
                $service = \App\Models\Service::with('price')->find($procedureData['service_id']);

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
                $patient = \App\Models\Patient::find($encounter->patient_id);

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
                    if ($proc->encounter_id) {
                        $deleteBtn = " <button class='btn btn-sm btn-outline-danger' onclick='deleteProcedureRequest({$proc->id}, {$proc->encounter_id}, \"{$serviceName}\")' title='Delete Request'><i class='fa fa-trash'></i></button>";
                    } else {
                        // Nurse-created item (no encounter) — use nurse route
                        $deleteBtn = " <button class='btn btn-sm btn-outline-danger' onclick='deleteNurseClinicalRequest(\"procedure\", {$proc->id}, \"{$serviceName}\")' title='Delete Request'><i class='fa fa-trash'></i></button>";
                    }
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
            $fillable = [
                'procedure_status',
                'priority',
                'scheduled_date',
                'scheduled_time',
                'operating_room',
                'outcome',
                'outcome_notes',
                'pre_notes',
                'post_notes'
            ];

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
                    if ($patient && $patient->account) {
                        $patient->account->increment('balance', $billing->price);
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

    /* ═══════════════════════════════════════════════════════════════
     * Single-item add endpoints (auto-save — Plan §3.2)
     * Thin wrappers around ClinicalOrdersTrait methods.
     * ═══════════════════════════════════════════════════════════════ */

    /**
     * Add a single lab request for the encounter.
     * POST encounters/{encounter}/add-lab
     */
    /**
     * Apply a service combo bundle to the encounter.
     */
    public function applyCombo(Request $request, Encounter $encounter)
    {
        try {
            $request->validate([
                "service_id" => "required|exists:services,id",
            ]);

            $service = Service::findOrFail($request->service_id);
            if (!$service->is_combo) {
                return response()->json(["success" => false, "message" => "Selected service is not a combo."]);
            }

            $result = $this->applyServiceCombo($service, $encounter->patient_id, $encounter->id);

            return response()->json([
                "success" => true,
                "message" => "Combo applied successfully",
                "data" => $result
            ]);
        } catch (\Exception $e) {
            Log::error("Error applying combo: " . $e->getMessage());
            return response()->json([
                "success" => false,
                "message" => "Error applying combo: " . $e->getMessage()
            ], 500);
        }
    }

    public function removeBundle(Request $request, Encounter $encounter)
    {
        try {
            $request->validate([
                'parent_request_id' => 'required|integer|exists:product_or_service_requests,id'
            ]);

            $parentRequest = ProductOrServiceRequest::findOrFail($request->parent_request_id);

            // Verify this combo belongs to this encounter and is a combo item
            if ($parentRequest->encounter_id !== $encounter->id || !$parentRequest->is_bundle_item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid combo or permission denied'
                ], 403);
            }

            $result = $this->removeServiceCombo($parentRequest->id);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error("Error removing combo: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error removing combo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generic remove-bundle endpoint — no encounter context required.
     * Validates that the current user created the bundle before removing.
     */
    public function removeBundleGeneric(Request $request)
    {
        try {
            $request->validate([
                'parent_request_id' => 'required|integer|exists:product_or_service_requests,id'
            ]);

            $parentRequest = ProductOrServiceRequest::findOrFail($request->parent_request_id);

            // Only the staff member who created the combo may remove it
            if ($parentRequest->staff_user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permission denied: only the person who applied this combo may remove it.'
                ], 403);
            }

            $result = $this->removeServiceCombo($parentRequest->id);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message']
            ], $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            Log::error("Error removing combo (generic): " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error removing combo: ' . $e->getMessage()
            ], 500);
        }
    }

    public function addSingleLabRequest(Request $request, Encounter $encounter)
    {
        try {
            $request->validate(['service_id' => 'required|integer']);
            $lab = $this->addSingleLab(
                $request->input('service_id'),
                $request->input('note'),
                $encounter->patient_id,
                $encounter->id
            );
            return response()->json([
                'success' => true,
                'id' => $lab->id,
                'item' => ['id' => $lab->id, 'service_id' => $lab->service_id, 'note' => $lab->note, 'created_at' => $lab->created_at],
                'message' => 'Lab added'
            ]);
        } catch (\Exception $e) {
            Log::error('addSingleLabRequest: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Add a single imaging request for the encounter.
     * POST encounters/{encounter}/add-imaging
     */
    public function addSingleImagingRequest(Request $request, Encounter $encounter)
    {
        try {
            $request->validate(['service_id' => 'required|integer']);
            $imaging = $this->addSingleImaging(
                $request->input('service_id'),
                $request->input('note'),
                $encounter->patient_id,
                $encounter->id
            );
            return response()->json([
                'success' => true,
                'id' => $imaging->id,
                'item' => ['id' => $imaging->id, 'service_id' => $imaging->service_id, 'note' => $imaging->note, 'created_at' => $imaging->created_at],
                'message' => 'Imaging added'
            ]);
        } catch (\Exception $e) {
            Log::error('addSingleImagingRequest: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Add a single prescription for the encounter.
     * POST encounters/{encounter}/add-prescription
     */
    public function addSinglePrescriptionRequest(Request $request, Encounter $encounter)
    {
        try {
            $request->validate(['product_id' => 'required|integer']);
            $presc = $this->addSinglePrescription(
                $request->input('product_id'),
                $request->input('dose', ''),
                $encounter->patient_id,
                $encounter->id
            );
            return response()->json([
                'success' => true,
                'id' => $presc->id,
                'item' => ['id' => $presc->id, 'product_id' => $presc->product_id, 'dose' => $presc->dose, 'created_at' => $presc->created_at],
                'message' => 'Prescription added'
            ]);
        } catch (\Exception $e) {
            Log::error('addSinglePrescriptionRequest: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update prescription dose (debounced auto-save — Plan §4.3).
     * PUT encounters/{encounter}/prescriptions/{prescription}/dose
     */
    public function updatePrescriptionDoseRequest(Request $request, Encounter $encounter, ProductRequest $prescription)
    {
        try {
            if ($prescription->encounter_id != $encounter->id) {
                return response()->json(['success' => false, 'message' => 'Prescription does not belong to this encounter'], 403);
            }
            $presc = $this->updateSinglePrescriptionDose($prescription->id, $request->input('dose', ''));
            return response()->json(['success' => true, 'id' => $presc->id, 'message' => 'Dose updated']);
        } catch (\Exception $e) {
            Log::error('updatePrescriptionDoseRequest: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Add a single procedure for the encounter.
     * POST encounters/{encounter}/add-procedure
     */
    public function addSingleProcedureRequest(Request $request, Encounter $encounter)
    {
        try {
            $request->validate(['service_id' => 'required|integer', 'priority' => 'required|string']);
            $procedure = $this->addSingleProcedure(
                $request->only(['service_id', 'priority', 'scheduled_date', 'pre_notes']),
                $encounter->patient_id,
                $encounter->id,
                $encounter->admission_request_id
            );
            return response()->json([
                'success' => true,
                'id' => $procedure->id,
                'item' => ['id' => $procedure->id, 'service_id' => $procedure->service_id, 'priority' => $procedure->priority, 'created_at' => $procedure->created_at],
                'message' => 'Procedure added'
            ]);
        } catch (\Exception $e) {
            Log::error('addSingleProcedureRequest: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update a lab's clinical note (debounced auto-save).
     * PUT encounters/{encounter}/labs/{lab}/note
     */
    public function updateLabNoteRequest(Request $request, Encounter $encounter, LabServiceRequest $lab)
    {
        try {
            if ($lab->encounter_id != $encounter->id) {
                return response()->json(['success' => false, 'message' => 'Lab does not belong to this encounter'], 403);
            }
            $lab = $this->updateSingleLabNote($lab->id, $request->input('note', ''));
            return response()->json(['success' => true, 'id' => $lab->id, 'message' => 'Note updated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update an imaging request's clinical note (debounced auto-save).
     * PUT encounters/{encounter}/imaging/{imaging}/note
     */
    public function updateImagingNoteRequest(Request $request, Encounter $encounter, ImagingServiceRequest $imaging)
    {
        try {
            if ($imaging->encounter_id != $encounter->id) {
                return response()->json(['success' => false, 'message' => 'Imaging does not belong to this encounter'], 403);
            }
            $imaging = $this->updateSingleImagingNote($imaging->id, $request->input('note', ''));
            return response()->json(['success' => true, 'id' => $imaging->id, 'message' => 'Note updated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Re-prescribe items from a previous encounter (Plan §5.1).
     * POST /encounters/{encounter}/re-prescribe
     * Expects: { source_type: 'labs'|'imaging'|'prescriptions', source_ids: [...], adjust_doses?: {...} }
     */
    public function rePrescribe(Request $request, Encounter $encounter)
    {
        $request->validate([
            'source_type' => 'required|in:labs,imaging,prescriptions,procedures',
            'source_ids'  => 'required|array|min:1',
            'source_ids.*' => 'integer',
            'adjust_doses' => 'nullable|array',
        ]);

        try {
            $created = $this->rePrescribeItems(
                $request->input('source_type'),
                $request->input('source_ids'),
                $encounter->patient_id,
                $encounter->id,
                $request->input('adjust_doses', [])
            );

            return response()->json([
                'success' => true,
                'items'   => $created->map(fn($item) => ['id' => $item->id]),
                'count'   => $created->count(),
                'message' => $created->count() . ' item(s) re-prescribed'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Fetch recent encounters for a patient with item counts.
     * GET encounters/{encounter}/recent-encounters
     * Plan §5.3 — "Re-prescribe from encounter" dropdown data.
     */
    public function recentEncounters(Encounter $encounter)
    {
        $encounters = $this->recentEncountersForPatient(
            $encounter->patient_id,
            5,
            $encounter->id  // exclude current
        );
        return response()->json(['success' => true, 'encounters' => $encounters]);
    }

    /**
     * Get all items from a specific encounter (for re-prescribe preview).
     * GET encounters/{encounter}/encounter-items/{sourceEncounter}
     * Plan §5.3
     */
    public function encounterItems(Encounter $encounter, int $sourceEncounter)
    {
        $items = $this->getEncounterItems($sourceEncounter);
        return response()->json(['success' => true, 'items' => $items]);
    }

    /**
     * Get a date-grouped timeline of all tracked clinical models for the patient.
     * GET encounters/{encounter}/clinical-story/timeline
     *
     * Returns dates that have at least one record across all tracked models,
     * with counts per category, plus encounter sub-group info per date.
     */
    public function getClinicalStoryTimeline(Request $request, Encounter $encounter)
    {
        try {
            $patientId = $encounter->patient_id;
            $dateFrom = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : null;
            $dateTo   = $request->filled('date_to')   ? Carbon::parse($request->date_to)->endOfDay()     : null;
            $encounterFilter = $request->input('encounter_filter');

            // Check maternity enrollment
            $maternityEnrollment = \App\Models\MaternityEnrollment::where('patient_id', $patientId)
                ->orderBy('created_at', 'desc')
                ->first();
            $hasMaternity = !empty($maternityEnrollment);

            // Fetch all patient encounters for date overlap helper matching
            $allEncountersForMatch = Encounter::where('patient_id', $patientId)
                ->orderBy('started_at', 'asc')
                ->get();

            // Helper to match a record timestamp to an encounter id
            $matchEncounter = function ($timestamp) use ($allEncountersForMatch) {
                if (!$timestamp) return null;
                $time = Carbon::parse($timestamp);
                foreach ($allEncountersForMatch as $enc) {
                    $start = $enc->started_at;
                    $end = $enc->completed_at;
                    if ($start && $time->greaterThanOrEqualTo($start)) {
                        if (!$end || $time->lessThanOrEqualTo($end)) {
                            return $enc->id;
                        }
                    }
                }
                return null;
            };

            // Collect all dates + category counts from each tracked model.
            $dateMap = [];

            // Helper: add date entries from a query with robust encounter filtering
            $addDates = function ($model, $dateColumn, $category) use ($patientId, $dateFrom, $dateTo, $encounterFilter, $matchEncounter, &$dateMap) {
                $query = $model::where('patient_id', $patientId);
                if ($dateFrom) $query->where($dateColumn, '>=', $dateFrom);
                if ($dateTo)   $query->where($dateColumn, '<=', $dateTo);
                
                $hasEncounterIdCol = in_array('encounter_id', (new $model)->getFillable() ?? []);
                if ($encounterFilter && $hasEncounterIdCol) {
                    $query->where('encounter_id', $encounterFilter);
                }

                $items = $query->get();

                foreach ($items as $item) {
                    $dateVal = $item->$dateColumn;
                    if (!$dateVal) continue;
                    $d = Carbon::parse($dateVal)->format('Y-m-d');
                    
                    if ($encounterFilter && !$hasEncounterIdCol) {
                        $matchedId = $matchEncounter($dateVal);
                        if (intval($matchedId) !== intval($encounterFilter)) {
                            continue;
                        }
                    }

                    $dateMap[$d][$category] = ($dateMap[$d][$category] ?? 0) + 1;
                }
            };

            // 1. Vitals
            $addDates(\App\Models\VitalSign::class, 'time_taken', 'vitals');

            // 2. Clinical Notes (from encounters table)
            $encQuery = Encounter::where('patient_id', $patientId);
            $anchorCol = 'COALESCE(started_at, created_at)';
            if ($dateFrom) $encQuery->whereRaw("$anchorCol >= ?", [$dateFrom]);
            if ($dateTo)   $encQuery->whereRaw("$anchorCol <= ?", [$dateTo]);
            if ($encounterFilter) {
                $encQuery->where('id', $encounterFilter);
            }
            $encRows = $encQuery->selectRaw("id, DATE($anchorCol) as anchor_date, DATE(updated_at) as note_date, notes")
                ->get();
            foreach ($encRows as $row) {
                if (!$row->anchor_date) continue;
                $dateMap[$row->anchor_date]['clinical_notes'] = ($dateMap[$row->anchor_date]['clinical_notes'] ?? 0) + 1;
                if (!empty($row->notes) && $row->note_date && $row->note_date !== $row->anchor_date) {
                    $dateMap[$row->note_date]['clinical_notes'] = ($dateMap[$row->note_date]['clinical_notes'] ?? 0) + 1;
                }
            }

            // 3. Nursing Notes
            $addDates(\App\Models\NursingNote::class, 'created_at', 'nursing_notes');

            // 4. Medication Administration
            $addDates(\App\Models\MedicationAdministration::class, 'administered_at', 'med_admin');

            // 5. Intake & Output
            $addDates(\App\Models\IntakeOutputPeriod::class, 'started_at', 'intake_output');

            // 6. Injections (two models combined)
            $addDates(\App\Models\InjectionAdministration::class, 'administered_at', 'injections');
            $addDates(\App\Models\ImmunizationRecord::class, 'administered_at', 'injections');

            // 7. Labs
            $labQuery = LabServiceRequest::where('patient_id', $patientId)->where('status', '>', 0);
            if ($dateFrom) $labQuery->where('created_at', '>=', $dateFrom);
            if ($dateTo)   $labQuery->where('created_at', '<=', $dateTo);
            if ($encounterFilter) {
                $labQuery->where('encounter_id', $encounterFilter);
            }
            $labCounts = $labQuery->selectRaw("DATE(created_at) as d, COUNT(*) as c")
                ->groupBy('d')->pluck('c', 'd');
            foreach ($labCounts as $date => $count) {
                if (!$date) continue;
                $dateMap[$date]['labs'] = ($dateMap[$date]['labs'] ?? 0) + $count;
            }

            // 8. Imaging
            $imgQuery = ImagingServiceRequest::where('patient_id', $patientId)->where('status', '>', 0);
            if ($dateFrom) $imgQuery->where('created_at', '>=', $dateFrom);
            if ($dateTo)   $imgQuery->where('created_at', '<=', $dateTo);
            if ($encounterFilter) {
                $imgQuery->where('encounter_id', $encounterFilter);
            }
            $imgCounts = $imgQuery->selectRaw("DATE(created_at) as d, COUNT(*) as c")
                ->groupBy('d')->pluck('c', 'd');
            foreach ($imgCounts as $date => $count) {
                if (!$date) continue;
                $dateMap[$date]['imaging'] = ($dateMap[$date]['imaging'] ?? 0) + $count;
            }

            // 9. Prescriptions
            $addDates(ProductRequest::class, 'created_at', 'prescriptions');

            // 10. Care Plans (NonPharmOrder)
            $addDates(\App\Models\NonPharmOrder::class, 'created_at', 'care_plans');

            // 11. Procedures
            $addDates(\App\Models\Procedure::class, 'created_at', 'procedures');

            // 12. Admissions
            $addDates(AdmissionRequest::class, 'created_at', 'admissions');

            // 13. Referrals
            $addDates(\App\Models\SpecialistReferral::class, 'created_at', 'referrals');

            // 14. Maternity (if enrolled)
            if ($hasMaternity) {
                $addDates(\App\Models\AncVisit::class, 'visit_date', 'anc_visits');
                $addDates(\App\Models\DeliveryRecord::class, 'delivery_date', 'delivery');
                $addDates(\App\Models\PostnatalVisit::class, 'visit_date', 'postnatal');
            }

            // Build encounter sub-group info per date
            $encountersByDate = [];
            $encAllQuery = Encounter::where('patient_id', $patientId)
                ->with(['doctor', 'service']);
            if ($dateFrom) $encAllQuery->whereRaw("COALESCE(started_at, created_at) >= ?", [$dateFrom]);
            if ($dateTo)   $encAllQuery->whereRaw("COALESCE(started_at, created_at) <= ?", [$dateTo]);
            $allEncounters = $encAllQuery->orderByRaw('COALESCE(started_at, created_at) DESC')->get();
            foreach ($allEncounters as $enc) {
                $anchor = $enc->started_at ?? $enc->created_at;
                if (!$anchor) continue;
                $d = Carbon::parse($anchor)->format('Y-m-d');
                $encountersByDate[$d][] = [
                    'id' => $enc->id,
                    'doctor_name' => $enc->doctor ? userfullname($enc->doctor->id) : 'Any Doctor',
                    'clinic_name' => $enc->service ? $enc->service->service_name : 'Consultation',
                    'started_at' => Carbon::parse($anchor)->format('H:i'),
                    'completed' => (bool) $enc->completed,
                ];
            }

            // Sort dates descending and paginate
            krsort($dateMap);
            $allDates = array_keys($dateMap);
            $page = max(1, (int) $request->input('page', 1));
            $perPage = 15;
            $offset = ($page - 1) * $perPage;
            $pagedDates = array_slice($allDates, $offset, $perPage);
            $totalDates = count($allDates);
            $hasMore = ($offset + $perPage) < $totalDates;

            $timeline = [];
            foreach ($pagedDates as $date) {
                $timeline[] = [
                    'date' => $date,
                    'date_formatted' => Carbon::parse($date)->format('D, M d, Y'),
                    'categories' => $dateMap[$date],
                    'encounters' => $encountersByDate[$date] ?? [],
                ];
            }

            $consultations = $allEncounters->map(function ($enc) {
                $anchor = $enc->started_at ?? $enc->created_at;
                return [
                    'id' => $enc->id,
                    'clinic_name' => $enc->service ? $enc->service->service_name : 'Consultation',
                    'doctor_name' => $enc->doctor ? userfullname($enc->doctor->id) : 'Any Doctor',
                    'date_formatted' => $anchor ? Carbon::parse($anchor)->format('M d, Y') : 'Unknown Date',
                ];
            });

            return response()->json([
                'success' => true,
                'timeline' => $timeline,
                'consultations' => $consultations,
                'pagination' => [
                    'current_page' => $page,
                    'total_dates' => $totalDates,
                    'has_more' => $hasMore,
                ],
                'maternity_enrolled' => $hasMaternity,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching clinical story timeline: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get a date-grouped timeline of all tracked clinical models for the patient.
     * GET patients/{patient}/clinical-story/timeline
     */
    public function getPatientClinicalStoryTimeline(Request $request, $patient)
    {
        try {
            $patientId = is_object($patient) ? $patient->id : intval($patient);
            $dateFrom = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : null;
            $dateTo   = $request->filled('date_to')   ? Carbon::parse($request->date_to)->endOfDay()     : null;
            $encounterFilter = $request->input('encounter_filter');

            // Check maternity enrollment
            $maternityEnrollment = \App\Models\MaternityEnrollment::where('patient_id', $patientId)
                ->orderBy('created_at', 'desc')
                ->first();
            $hasMaternity = !empty($maternityEnrollment);

            // Fetch all patient encounters for date overlap helper matching
            $allEncountersForMatch = Encounter::where('patient_id', $patientId)
                ->orderBy('started_at', 'asc')
                ->get();

            // Helper to match a record timestamp to an encounter id
            $matchEncounter = function ($timestamp) use ($allEncountersForMatch) {
                if (!$timestamp) return null;
                $time = Carbon::parse($timestamp);
                foreach ($allEncountersForMatch as $enc) {
                    $start = $enc->started_at;
                    $end = $enc->completed_at;
                    if ($start && $time->greaterThanOrEqualTo($start)) {
                        if (!$end || $time->lessThanOrEqualTo($end)) {
                            return $enc->id;
                        }
                    }
                }
                return null;
            };

            // Collect all dates + category counts from each tracked model.
            $dateMap = [];

            // Helper: add date entries from a query with robust encounter filtering
            $addDates = function ($model, $dateColumn, $category) use ($patientId, $dateFrom, $dateTo, $encounterFilter, $matchEncounter, &$dateMap) {
                $query = $model::where('patient_id', $patientId);
                if ($dateFrom) $query->where($dateColumn, '>=', $dateFrom);
                if ($dateTo)   $query->where($dateColumn, '<=', $dateTo);
                
                $hasEncounterIdCol = in_array('encounter_id', (new $model)->getFillable() ?? []);
                if ($encounterFilter && $hasEncounterIdCol) {
                    $query->where('encounter_id', $encounterFilter);
                }

                $items = $query->get();

                foreach ($items as $item) {
                    $dateVal = $item->$dateColumn;
                    if (!$dateVal) continue;
                    $d = Carbon::parse($dateVal)->format('Y-m-d');
                    
                    if ($encounterFilter && !$hasEncounterIdCol) {
                        $matchedId = $matchEncounter($dateVal);
                        if (intval($matchedId) !== intval($encounterFilter)) {
                            continue;
                        }
                    }

                    $dateMap[$d][$category] = ($dateMap[$d][$category] ?? 0) + 1;
                }
            };

            // 1. Vitals
            $addDates(\App\Models\VitalSign::class, 'time_taken', 'vitals');

            // 2. Clinical Notes (from encounters table)
            $encQuery = Encounter::where('patient_id', $patientId);
            $anchorCol = 'COALESCE(started_at, created_at)';
            if ($dateFrom) $encQuery->whereRaw("$anchorCol >= ?", [$dateFrom]);
            if ($dateTo)   $encQuery->whereRaw("$anchorCol <= ?", [$dateTo]);
            if ($encounterFilter) {
                $encQuery->where('id', $encounterFilter);
            }
            $encRows = $encQuery->selectRaw("id, DATE($anchorCol) as anchor_date, DATE(updated_at) as note_date, notes")
                ->get();
            foreach ($encRows as $row) {
                if (!$row->anchor_date) continue;
                $dateMap[$row->anchor_date]['clinical_notes'] = ($dateMap[$row->anchor_date]['clinical_notes'] ?? 0) + 1;
                if (!empty($row->notes) && $row->note_date && $row->note_date !== $row->anchor_date) {
                    $dateMap[$row->note_date]['clinical_notes'] = ($dateMap[$row->note_date]['clinical_notes'] ?? 0) + 1;
                }
            }

            // 3. Nursing Notes
            $addDates(\App\Models\NursingNote::class, 'created_at', 'nursing_notes');

            // 4. Medication Administration
            $addDates(\App\Models\MedicationAdministration::class, 'administered_at', 'med_admin');

            // 5. Intake & Output
            $addDates(\App\Models\IntakeOutputPeriod::class, 'started_at', 'intake_output');

            // 6. Injections (two models combined)
            $addDates(\App\Models\InjectionAdministration::class, 'administered_at', 'injections');
            $addDates(\App\Models\ImmunizationRecord::class, 'administered_at', 'injections');

            // 7. Labs
            $labQuery = LabServiceRequest::where('patient_id', $patientId)->where('status', '>', 0);
            if ($dateFrom) $labQuery->where('created_at', '>=', $dateFrom);
            if ($dateTo)   $labQuery->where('created_at', '<=', $dateTo);
            if ($encounterFilter) {
                $labQuery->where('encounter_id', $encounterFilter);
            }
            $labCounts = $labQuery->selectRaw("DATE(created_at) as d, COUNT(*) as c")
                ->groupBy('d')->pluck('c', 'd');
            foreach ($labCounts as $date => $count) {
                if (!$date) continue;
                $dateMap[$date]['labs'] = ($dateMap[$date]['labs'] ?? 0) + $count;
            }

            // 8. Imaging
            $imgQuery = ImagingServiceRequest::where('patient_id', $patientId)->where('status', '>', 0);
            if ($dateFrom) $imgQuery->where('created_at', '>=', $dateFrom);
            if ($dateTo)   $imgQuery->where('created_at', '<=', $dateTo);
            if ($encounterFilter) {
                $imgQuery->where('encounter_id', $encounterFilter);
            }
            $imgCounts = $imgQuery->selectRaw("DATE(created_at) as d, COUNT(*) as c")
                ->groupBy('d')->pluck('c', 'd');
            foreach ($imgCounts as $date => $count) {
                if (!$date) continue;
                $dateMap[$date]['imaging'] = ($dateMap[$date]['imaging'] ?? 0) + $count;
            }

            // 9. Prescriptions
            $addDates(ProductRequest::class, 'created_at', 'prescriptions');

            // 10. Care Plans (NonPharmOrder)
            $addDates(\App\Models\NonPharmOrder::class, 'created_at', 'care_plans');

            // 11. Procedures
            $addDates(\App\Models\Procedure::class, 'created_at', 'procedures');

            // 12. Admissions
            $addDates(AdmissionRequest::class, 'created_at', 'admissions');

            // 13. Referrals
            $addDates(\App\Models\SpecialistReferral::class, 'created_at', 'referrals');

            // 14. Maternity (if enrolled)
            if ($hasMaternity) {
                $addDates(\App\Models\AncVisit::class, 'visit_date', 'anc_visits');
                $addDates(\App\Models\DeliveryRecord::class, 'delivery_date', 'delivery');
                $addDates(\App\Models\PostnatalVisit::class, 'visit_date', 'postnatal');
            }

            // Build encounter sub-group info per date
            $encountersByDate = [];
            $encAllQuery = Encounter::where('patient_id', $patientId)
                ->with(['doctor', 'service']);
            if ($dateFrom) $encAllQuery->whereRaw("COALESCE(started_at, created_at) >= ?", [$dateFrom]);
            if ($dateTo)   $encAllQuery->whereRaw("COALESCE(started_at, created_at) <= ?", [$dateTo]);
            $allEncounters = $encAllQuery->orderByRaw('COALESCE(started_at, created_at) DESC')->get();
            foreach ($allEncounters as $enc) {
                $anchor = $enc->started_at ?? $enc->created_at;
                if (!$anchor) continue;
                $d = Carbon::parse($anchor)->format('Y-m-d');
                $encountersByDate[$d][] = [
                    'id' => $enc->id,
                    'doctor_name' => $enc->doctor ? userfullname($enc->doctor->id) : 'Any Doctor',
                    'clinic_name' => $enc->service ? $enc->service->service_name : 'Consultation',
                    'started_at' => Carbon::parse($anchor)->format('H:i'),
                    'completed' => (bool) $enc->completed,
                ];
            }

            // Sort dates descending and paginate
            krsort($dateMap);
            $allDates = array_keys($dateMap);
            $page = max(1, (int) $request->input('page', 1));
            $perPage = 15;
            $offset = ($page - 1) * $perPage;
            $pagedDates = array_slice($allDates, $offset, $perPage);
            $totalDates = count($allDates);
            $hasMore = ($offset + $perPage) < $totalDates;

            $timeline = [];
            foreach ($pagedDates as $date) {
                $timeline[] = [
                    'date' => $date,
                    'date_formatted' => Carbon::parse($date)->format('D, M d, Y'),
                    'categories' => $dateMap[$date],
                    'encounters' => $encountersByDate[$date] ?? [],
                ];
            }

            $consultations = $allEncounters->map(function ($enc) {
                $anchor = $enc->started_at ?? $enc->created_at;
                return [
                    'id' => $enc->id,
                    'clinic_name' => $enc->service ? $enc->service->service_name : 'Consultation',
                    'doctor_name' => $enc->doctor ? userfullname($enc->doctor->id) : 'Any Doctor',
                    'date_formatted' => $anchor ? Carbon::parse($anchor)->format('M d, Y') : 'Unknown Date',
                ];
            });

            return response()->json([
                'success' => true,
                'timeline' => $timeline,
                'consultations' => $consultations,
                'pagination' => [
                    'current_page' => $page,
                    'total_dates' => $totalDates,
                    'has_more' => $hasMore,
                ],
                'maternity_enrolled' => $hasMaternity,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching clinical story timeline for patient: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get clinical story category items for a patient.
     * GET encounters/{encounter}/clinical-story
     *
     * Supports filtering by:
     *  - category (required)
     *  - date_filter (YYYY-MM-DD) — returns items for that specific date
     *  - encounter_filter — returns items for a specific encounter ID
     *  - date_from / date_to — broad range filter
     */
    protected function getPatientClinicalStoryCommon(Request $request, $patientId)
    {
        try {
            $patientId = $patientId;
            $category = $request->input('category');
            $encounterFilter = $request->input('encounter_filter');
            $dateFilter = $request->input('date_filter'); // YYYY-MM-DD — single day filter
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            // If date_filter is set, override dateFrom/dateTo to that single day
            if ($dateFilter) {
                $dateFrom = $dateFilter;
                $dateTo = $dateFilter;
            }

            if (!$category) {
                return response()->json(['success' => false, 'message' => 'Category is required'], 400);
            }

            // Fetch all patient encounters for date overlap helper matching
            $allEncounters = Encounter::where('patient_id', $patientId)
                ->orderBy('started_at', 'asc')
                ->get();

            // Helper to match a record timestamp to an encounter id
            $matchEncounter = function ($timestamp) use ($allEncounters) {
                if (!$timestamp) return null;
                $time = Carbon::parse($timestamp);
                foreach ($allEncounters as $enc) {
                    $start = $enc->started_at;
                    $end = $enc->completed_at;
                    if ($start && $time->greaterThanOrEqualTo($start)) {
                        if (!$end || $time->lessThanOrEqualTo($end)) {
                            return $enc->id;
                        }
                    }
                }
                return null;
            };

            $data = [];

            // Query by category
            switch ($category) {
                case 'vitals':
                    $query = \App\Models\VitalSign::where('patient_id', $patientId)
                        ->with('takenBy');
                    if ($dateFrom) $query->where('time_taken', '>=', Carbon::parse($dateFrom)->startOfDay());
                    if ($dateTo) $query->where('time_taken', '<=', Carbon::parse($dateTo)->endOfDay());
                    $items = $query->orderBy('time_taken', 'desc')->get();
                    
                    $data = $items->map(function ($item) use ($matchEncounter) {
                        $encId = $matchEncounter($item->time_taken);
                        $takenBy = $item->takenBy ? userfullname($item->takenBy->id) : 'N/A';

                        $html = '<div class="card-modern mb-2" style="border-left: 4px solid #dc3545;">';
                        $html .= '<div class="card-body p-3">';
                        $html .= '<div class="d-flex justify-content-between align-items-center mb-2">';
                        $html .= '<h6 class="mb-0"><i class="mdi mdi-heart-pulse text-danger"></i> Vital Signs</h6>';
                        $html .= '<span class="badge bg-info">' . $item->time_taken->format('h:i a D M j, Y') . '</span>';
                        $html .= '</div>';
                        $html .= '<div class="row g-2 mb-2">';
                        $vFields = [
                            ['BP', $item->blood_pressure, 'mmHg', 'mdi-blood-bag'],
                            ['Temp', $item->temp, '°C', 'mdi-thermometer'],
                            ['HR', $item->heart_rate, 'bpm', 'mdi-heart'],
                            ['SpO2', $item->spo2, '%', 'mdi-pulse'],
                            ['RR', $item->resp_rate, '/min', 'mdi-lungs'],
                            ['Weight', $item->weight, 'kg', 'mdi-weight-kilogram'],
                            ['Height', $item->height, 'cm', 'mdi-human-male-height'],
                            ['BMI', $item->bmi, '', 'mdi-calculator'],
                            ['Blood Sugar', $item->blood_sugar, '', 'mdi-water'],
                            ['Pain', $item->pain_score, '/10', 'mdi-alert-circle'],
                        ];
                        foreach ($vFields as $f) {
                            if ($f[1] !== null && $f[1] !== '') {
                                $html .= '<div class="col-4"><small><i class="mdi ' . $f[3] . ' text-muted"></i> <b>' . $f[0] . ':</b> ' . $f[1] . ' ' . $f[2] . '</small></div>';
                            }
                        }
                        $html .= '</div>';
                        if ($item->other_notes) $html .= '<div class="alert alert-light mb-1 p-2"><small><i class="mdi mdi-note-text"></i> ' . e($item->other_notes) . '</small></div>';
                        $html .= '<div class="text-end small text-muted border-top pt-2 mt-2"><i class="mdi mdi-account"></i> ' . $takenBy . '</div>';
                        $html .= '</div></div>';

                        return [
                            'id' => $item->id,
                            'encounter_id' => $encId,
                            'taken_by_name' => $takenBy,
                            'date' => $item->time_taken->format('M d, Y H:i'),
                            'timestamp' => $item->time_taken->toIso8601String(),
                            'info_html' => $html
                        ];
                    });
                    break;

                case 'clinical_notes':
                    // Use COALESCE(started_at, created_at) as anchor.
                    // When date_filter is set, return encounters whose anchor date OR note date matches.
                    $query = Encounter::where('patient_id', $patientId)
                        ->with(['doctor.staff_profile.specialization']);

                    if ($dateFilter) {
                        // Show encounters created on this date OR whose notes were written on this date
                        $query->where(function ($q) use ($dateFilter) {
                            $q->whereRaw("DATE(COALESCE(started_at, created_at)) = ?", [$dateFilter])
                              ->orWhere(function ($q2) use ($dateFilter) {
                                  $q2->whereNotNull('notes')
                                     ->where('notes', '!=', '')
                                     ->whereRaw("DATE(updated_at) = ?", [$dateFilter]);
                              });
                        });
                    } else {
                        if ($dateFrom) $query->whereRaw("COALESCE(started_at, created_at) >= ?", [Carbon::parse($dateFrom)->startOfDay()]);
                        if ($dateTo)   $query->whereRaw("COALESCE(started_at, created_at) <= ?", [Carbon::parse($dateTo)->endOfDay()]);
                    }

                    $items = $query->orderByRaw('COALESCE(started_at, created_at) DESC')->get();

                    $data = $items->map(function ($item) use ($dateFilter) {
                        $anchor = $item->started_at ?? $item->created_at;
                        $anchorDate = $anchor ? Carbon::parse($anchor)->format('Y-m-d') : null;
                        $noteDate = $item->updated_at ? $item->updated_at->format('Y-m-d') : null;
                        $notesOnDiffDay = !empty($item->notes) && $noteDate && $anchorDate && $noteDate !== $anchorDate;

                        // Build info_html matching EncounterHistoryList card format
                        $borderColor = $item->completed ? '#0d6efd' : '#ffc107';
                        $html = '<div class="card-modern mb-2" style="border-left: 4px solid ' . $borderColor . ';">';
                        $html .= '<div class="card-body p-3">';

                        // Header: doctor + date + status
                        $doctorName = $item->doctor ? userfullname($item->doctor->id) : 'Any Doctor';
                        $specialty = $item->doctor && $item->doctor->staff_profile && $item->doctor->staff_profile->specialization
                            ? $item->doctor->staff_profile->specialization->name : 'General Practitioner';
                        $html .= '<div class="d-flex justify-content-between align-items-start mb-3">';
                        $html .= '<div>';
                        $html .= '<h6 class="mb-0"><i class="mdi mdi-account-circle"></i> <span class="text-primary">' . $doctorName . '</span></h6>';
                        $html .= '<small class="text-muted" style="margin-left: 1.5rem; display: block; margin-top: 2px;">' . $specialty . '</small>';
                        $html .= '</div>';
                        $html .= '<div class="d-flex flex-column align-items-end gap-1">';
                        $html .= '<span class="badge bg-info">' . ($anchor ? Carbon::parse($anchor)->format('h:i a D M j, Y') : '') . '</span>';
                        if (!$item->completed) {
                            $html .= '<span class="badge bg-warning text-dark"><i class="mdi mdi-clock-outline"></i> In Progress</span>';
                        }
                        $html .= '</div></div>';

                        // Show "notes written on [date]" badge when viewing from encounter's anchor date
                        if ($notesOnDiffDay && $dateFilter === $anchorDate) {
                            $html .= '<div class="mb-2"><span class="badge bg-light text-dark border"><i class="mdi mdi-pencil-outline"></i> Notes written on ' . Carbon::parse($noteDate)->format('M d, Y') . '</span></div>';
                        }
                        // Show "encounter from [date]" badge when viewing from note's written date
                        if ($notesOnDiffDay && $dateFilter === $noteDate) {
                            $html .= '<div class="mb-2"><span class="badge bg-light text-dark border"><i class="mdi mdi-calendar-arrow-left"></i> Encounter from ' . Carbon::parse($anchorDate)->format('M d, Y') . '</span></div>';
                        }

                        // Reasons for encounter / Diagnosis
                        if ($item->reasons_for_encounter != '') {
                            $rawReasons = $item->reasons_for_encounter;
                            $decodedReasons = json_decode($rawReasons, true);
                            $html .= '<div class="mb-3">';
                            $html .= '<small><b><i class="mdi mdi-format-list-bulleted"></i> Reason(s) for Encounter/Diagnosis (ICPC-2):</b></small><br>';
                            if (is_array($decodedReasons) && isset($decodedReasons[0]['code'])) {
                                $html .= '<table class="table table-sm table-bordered mb-1" style="font-size:0.8rem;">';
                                $html .= '<thead><tr><th>Code</th><th>Diagnosis</th><th>Status</th><th>Course</th></tr></thead><tbody>';
                                foreach ($decodedReasons as $dx) {
                                    $code = htmlspecialchars($dx['code'] ?? '');
                                    $name = htmlspecialchars($dx['name'] ?? '');
                                    $c1 = htmlspecialchars($dx['comment_1'] ?? '');
                                    $c2 = htmlspecialchars($dx['comment_2'] ?? '');
                                    $c1Badge = $c1 ? "<span class='badge bg-secondary'>{$c1}</span>" : '<span class="text-muted">-</span>';
                                    $c2Badge = $c2 ? "<span class='badge bg-secondary'>{$c2}</span>" : '<span class="text-muted">-</span>';
                                    $html .= "<tr><td><code>{$code}</code></td><td>{$name}</td><td>{$c1Badge}</td><td>{$c2Badge}</td></tr>";
                                }
                                $html .= '</tbody></table>';
                            } else {
                                $reasons = explode(',', $rawReasons);
                                foreach ($reasons as $reason) {
                                    $html .= "<span class='badge bg-light text-dark me-1 mb-1'>" . trim($reason) . "</span>";
                                }
                            }
                            $html .= '</div>';
                        }

                        // Legacy diagnosis comments
                        $hasPerDiagJson = false;
                        if ($item->reasons_for_encounter) {
                            $checkJson = json_decode($item->reasons_for_encounter, true);
                            $hasPerDiagJson = is_array($checkJson) && isset($checkJson[0]['code']);
                        }
                        if (!$hasPerDiagJson && $item->reasons_for_encounter_comment_1) {
                            $html .= '<div class="mb-2"><small><b><i class="mdi mdi-comment-text"></i> Diagnosis Comment 1:</b> ' . htmlspecialchars($item->reasons_for_encounter_comment_1) . '</small></div>';
                        }
                        if (!$hasPerDiagJson && $item->reasons_for_encounter_comment_2) {
                            $html .= '<div class="mb-2"><small><b><i class="mdi mdi-comment-text"></i> Diagnosis Comment 2:</b> ' . htmlspecialchars($item->reasons_for_encounter_comment_2) . '</small></div>';
                        }

                        // Notes
                        $html .= '<div class="alert alert-light mb-2"><small><b><i class="mdi mdi-note-text"></i> Clinical Notes:</b><br>' . ($item->notes ?: '<em class="text-muted">No notes yet</em>') . '</small></div>';

                        $html .= '</div></div>';

                        return [
                            'id' => $item->id,
                            'encounter_id' => $item->id,
                            'doctor_name' => $doctorName,
                            'date' => $anchor ? Carbon::parse($anchor)->format('M d, Y H:i') : null,
                            'timestamp' => $anchor ? Carbon::parse($anchor)->toIso8601String() : null,
                            'info_html' => $html
                        ];
                    });
                    break;

                case 'nursing_notes':
                    $query = \App\Models\NursingNote::where('patient_id', $patientId)->with(['createdBy', 'type']);
                    if ($dateFrom) $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
                    if ($dateTo) $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
                    $items = $query->orderBy('created_at', 'desc')->get();

                    $data = $items->map(function ($item) use ($matchEncounter) {
                        $nurse = $item->createdBy ? userfullname($item->createdBy->id) : 'N/A';
                        $typeName = $item->type ? $item->type->name : 'General Note';

                        $html = '<div class="card-modern mb-2" style="border-left: 4px solid #20c997;">';
                        $html .= '<div class="card-body p-3">';
                        $html .= '<div class="d-flex justify-content-between align-items-center mb-2">';
                        $html .= '<div><span class="badge bg-success me-2">' . e($typeName) . '</span><small class="text-muted">' . $item->created_at->format('h:i a D M j, Y') . '</small></div>';
                        $html .= '</div>';
                        $html .= '<div class="p-2 bg-light rounded mb-2">' . ($item->note ?: '<em class="text-muted">No content</em>') . '</div>';
                        $html .= '<div class="text-end small text-muted border-top pt-2"><i class="mdi mdi-account"></i> ' . $nurse . '</div>';
                        $html .= '</div></div>';

                        return [
                            'id' => $item->id,
                            'encounter_id' => $matchEncounter($item->created_at),
                            'created_by' => $nurse,
                            'date' => $item->created_at->format('M d, Y H:i'),
                            'timestamp' => $item->created_at->toIso8601String(),
                            'info_html' => $html
                        ];
                    });
                    break;

                case 'med_admin':
                    $query = \App\Models\MedicationAdministration::where('patient_id', $patientId)->with(['product', 'administeredBy']);
                    if ($dateFrom) $query->where('administered_at', '>=', Carbon::parse($dateFrom)->startOfDay());
                    if ($dateTo) $query->where('administered_at', '<=', Carbon::parse($dateTo)->endOfDay());
                    $items = $query->orderBy('administered_at', 'desc')->get();

                    $data = $items->map(function ($item) use ($matchEncounter) {
                        $pName = $item->product ? $item->product->product_name : ($item->external_drug_name ?? 'Unknown Drug');
                        $admin = $item->administeredBy ? userfullname($item->administeredBy->id) : 'N/A';
                        $dt = $item->administered_at ? Carbon::parse($item->administered_at) : null;

                        $html = '<div class="card-modern mb-2" style="border-left: 4px solid #0dcaf0;">';
                        $html .= '<div class="card-body p-3">';
                        $html .= '<div class="d-flex justify-content-between align-items-center mb-2">';
                        $html .= '<strong><i class="mdi mdi-pill"></i> ' . e($pName) . '</strong>';
                        $html .= '<span class="badge bg-info">' . ($dt ? $dt->format('h:i a D M j, Y') : '') . '</span>';
                        $html .= '</div>';
                        $html .= '<div class="row g-1 mb-2">';
                        if ($item->dose) $html .= '<div class="col-auto"><small><b>Dose:</b> ' . e($item->dose) . '</small></div>';
                        if ($item->qty) $html .= '<div class="col-auto"><small><b>Qty:</b> ' . e($item->qty) . '</small></div>';
                        if ($item->route) $html .= '<div class="col-auto"><small><b>Route:</b> ' . e($item->route) . '</small></div>';
                        $html .= '</div>';
                        if ($item->comment) $html .= '<div class="alert alert-light mb-1 p-2"><small>' . e($item->comment) . '</small></div>';
                        $html .= '<div class="text-end small text-muted border-top pt-2"><i class="mdi mdi-account"></i> Administered by: ' . $admin . '</div>';
                        $html .= '</div></div>';

                        return [
                            'id' => $item->id,
                            'encounter_id' => $matchEncounter($item->administered_at),
                            'administered_by' => $admin,
                            'date' => $dt ? $dt->format('M d, Y H:i') : null,
                            'timestamp' => $dt ? $dt->toIso8601String() : null,
                            'info_html' => $html
                        ];
                    });
                    break;

                case 'intake_output':
                    $query = \App\Models\IntakeOutputPeriod::where('patient_id', $patientId)->with(['records', 'nurse']);
                    if ($dateFrom) $query->where('started_at', '>=', Carbon::parse($dateFrom)->startOfDay());
                    if ($dateTo) $query->where('started_at', '<=', Carbon::parse($dateTo)->endOfDay());
                    $items = $query->orderBy('started_at', 'desc')->get();

                    $data = $items->map(function ($item) use ($matchEncounter) {
                        $nurse = $item->nurse ? userfullname($item->nurse->id) : 'N/A';
                        $startDt = $item->started_at ? Carbon::parse($item->started_at) : null;
                        $endDt = $item->ended_at ? Carbon::parse($item->ended_at) : null;

                        $html = '<div class="card-modern mb-2" style="border-left: 4px solid #6610f2;">';
                        $html .= '<div class="card-body p-3">';
                        $html .= '<div class="d-flex justify-content-between align-items-center mb-2">';
                        $html .= '<h6 class="mb-0"><i class="mdi mdi-cup-water text-purple"></i> ' . e($item->type ?: 'I/O Period') . '</h6>';
                        $html .= '<div><span class="badge bg-info">' . ($startDt ? $startDt->format('h:i a M j') : '') . '</span>';
                        $html .= ' → <span class="badge ' . ($endDt ? 'bg-secondary' : 'bg-warning text-dark') . '">' . ($endDt ? $endDt->format('h:i a M j') : 'Ongoing') . '</span></div>';
                        $html .= '</div>';

                        if ($item->records->count() > 0) {
                            $html .= '<table class="table table-sm table-bordered mb-1" style="font-size:0.8rem;">';
                            $html .= '<thead><tr><th>Type</th><th>Amount</th><th>Description</th><th>Time</th></tr></thead><tbody>';
                            foreach ($item->records as $r) {
                                $typeBadge = strtolower($r->type) === 'intake' ? 'bg-success' : 'bg-danger';
                                $html .= '<tr><td><span class="badge ' . $typeBadge . '">' . e($r->type) . '</span></td>';
                                $html .= '<td>' . e($r->amount) . '</td><td>' . e($r->description ?: '-') . '</td>';
                                $html .= '<td><small>' . ($r->recorded_at ? Carbon::parse($r->recorded_at)->format('h:i a') : '-') . '</small></td></tr>';
                            }
                            $html .= '</tbody></table>';
                        } else {
                            $html .= '<div class="text-muted small">No records logged yet.</div>';
                        }
                        $html .= '<div class="text-end small text-muted border-top pt-2"><i class="mdi mdi-account"></i> ' . $nurse . '</div>';
                        $html .= '</div></div>';

                        return [
                            'id' => $item->id,
                            'encounter_id' => $matchEncounter($item->started_at),
                            'nurse_name' => $nurse,
                            'date' => $startDt ? $startDt->format('M d, Y H:i') : null,
                            'timestamp' => $startDt ? $startDt->toIso8601String() : null,
                            'info_html' => $html
                        ];
                    });
                    break;

                case 'injections':
                    $injQuery = \App\Models\InjectionAdministration::where('patient_id', $patientId)->with(['product', 'administeredBy']);
                    if ($dateFrom) $injQuery->where('administered_at', '>=', Carbon::parse($dateFrom)->startOfDay());
                    if ($dateTo) $injQuery->where('administered_at', '<=', Carbon::parse($dateTo)->endOfDay());
                    $injections = $injQuery->orderBy('administered_at', 'desc')->get();

                    $immQuery = \App\Models\ImmunizationRecord::where('patient_id', $patientId)->with(['product', 'administeredBy']);
                    if ($dateFrom) $immQuery->where('administered_at', '>=', Carbon::parse($dateFrom)->startOfDay());
                    if ($dateTo) $immQuery->where('administered_at', '<=', Carbon::parse($dateTo)->endOfDay());
                    $immunizations = $immQuery->orderBy('administered_at', 'desc')->get();

                    $buildInjHtml = function ($pName, $type, $dose, $route, $site, $batch, $notes, $admin, $dt) {
                        $color = $type === 'Immunization' ? '#198754' : '#e83e8c';
                        $h = '<div class="card-modern mb-2" style="border-left: 4px solid ' . $color . ';">';
                        $h .= '<div class="card-body p-3">';
                        $h .= '<div class="d-flex justify-content-between align-items-center mb-2">';
                        $h .= '<div><strong><i class="mdi mdi-needle"></i> ' . e($pName) . '</strong></div>';
                        $h .= '<div><span class="badge ' . ($type === 'Immunization' ? 'bg-success' : 'bg-pink') . '">' . $type . '</span>';
                        $h .= ' <span class="badge bg-info">' . ($dt ? $dt->format('h:i a M j, Y') : '') . '</span></div>';
                        $h .= '</div>';
                        $h .= '<div class="row g-1 mb-2">';
                        if ($dose) $h .= '<div class="col-auto"><small><b>Dose:</b> ' . e($dose) . '</small></div>';
                        if ($route) $h .= '<div class="col-auto"><small><b>Route:</b> ' . e($route) . '</small></div>';
                        if ($site) $h .= '<div class="col-auto"><small><b>Site:</b> ' . e($site) . '</small></div>';
                        if ($batch) $h .= '<div class="col-auto"><small><b>Batch:</b> ' . e($batch) . '</small></div>';
                        $h .= '</div>';
                        if ($notes) $h .= '<div class="alert alert-light mb-1 p-2"><small>' . e($notes) . '</small></div>';
                        $h .= '<div class="text-end small text-muted border-top pt-2"><i class="mdi mdi-account"></i> ' . $admin . '</div>';
                        $h .= '</div></div>';
                        return $h;
                    };

                    $mappedInjections = $injections->map(function ($item) use ($matchEncounter, $buildInjHtml) {
                        $pName = $item->product ? $item->product->product_name : ($item->external_drug_name ?? 'Unknown Injectable');
                        $admin = $item->administeredBy ? userfullname($item->administeredBy->id) : 'N/A';
                        $dt = $item->administered_at ? Carbon::parse($item->administered_at) : null;
                        return [
                            'id' => 'inj_' . $item->id,
                            'encounter_id' => $matchEncounter($item->administered_at),
                            'administered_by' => $admin,
                            'date' => $dt ? $dt->format('M d, Y H:i') : null,
                            'timestamp' => $dt ? $dt->toIso8601String() : null,
                            'info_html' => $buildInjHtml($pName, 'Injection', $item->dose, $item->route, $item->site, $item->batch_number, $item->notes, $admin, $dt)
                        ];
                    });

                    $mappedImmunizations = $immunizations->map(function ($item) use ($matchEncounter, $buildInjHtml) {
                        $pName = $item->vaccine_name ?: ($item->product ? $item->product->product_name : 'Unknown Vaccine');
                        $doseStr = $item->dose_number ? "Dose #{$item->dose_number} ({$item->dose})" : $item->dose;
                        $admin = $item->administeredBy ? userfullname($item->administeredBy->id) : 'N/A';
                        $dt = $item->administered_at ? Carbon::parse($item->administered_at) : null;
                        return [
                            'id' => 'imm_' . $item->id,
                            'encounter_id' => $matchEncounter($item->administered_at),
                            'administered_by' => $admin,
                            'date' => $dt ? $dt->format('M d, Y H:i') : null,
                            'timestamp' => $dt ? $dt->toIso8601String() : null,
                            'info_html' => $buildInjHtml($pName, 'Immunization', $doseStr, $item->route, $item->site, $item->batch_number, $item->notes, $admin, $dt)
                        ];
                    });

                    $data = $mappedInjections->concat($mappedImmunizations)->sortByDesc('timestamp')->values()->all();
                    break;

                case 'labs':
                    $query = LabServiceRequest::where('patient_id', $patientId)
                        ->with(['service', 'doctor', 'results_person', 'resultViews']);
                    if ($dateFrom) $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
                    if ($dateTo) $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
                    $items = $query->orderBy('created_at', 'desc')->get();

                    $statusLabels = [0 => ['Dismissed','bg-danger'], 1 => ['Unbilled','bg-warning text-dark'], 2 => ['Billed/Pending','bg-info'], 3 => ['Sample Taken','bg-primary'], 4 => ['Completed','bg-success'], 5 => ['Pending Approval','bg-warning'], 6 => ['Rejected','bg-danger']];

                    $data = $items->map(function ($item) use ($matchEncounter, $statusLabels) {
                        $sName = $item->service ? $item->service->service_name : 'N/A';
                        $sCode = $item->service ? $item->service->service_code : '';
                        $sl = $statusLabels[$item->status] ?? ['Unknown','bg-secondary'];
                        $doctorName = $item->doctor ? userfullname($item->doctor->id) : 'N/A';

                        $html = '<div class="card-modern mb-2" style="border-left: 4px solid ' . ($item->status >= 4 ? '#198754' : '#0d6efd') . ';">';
                        $html .= '<div class="card-body p-3">';
                        $html .= '<div class="d-flex justify-content-between align-items-start mb-2">';
                        $html .= '<div><h6 class="mb-0"><i class="fa fa-flask text-primary"></i> ' . $sName . '</h6>';
                        if ($sCode) $html .= '<small class="text-muted">' . $sCode . '</small>';
                        $html .= '</div>';
                        $html .= '<span class="badge ' . $sl[1] . '">' . $sl[0] . '</span>';
                        $html .= '</div>';

                        // Timeline
                        $html .= '<div class="mb-2"><small>';
                        $html .= '<div class="mb-1"><i class="mdi mdi-account-arrow-right text-primary"></i> <b>Requested by:</b> ' . $doctorName . ' <span class="text-muted">(' . date('h:i a D M j, Y', strtotime($item->created_at)) . ')</span></div>';
                        if ($item->billed_by) $html .= '<div class="mb-1"><i class="mdi mdi-cash-multiple text-success"></i> <b>Billed by:</b> ' . userfullname($item->billed_by) . ' <span class="text-muted">(' . date('h:i a D M j, Y', strtotime($item->billed_date)) . ')</span></div>';
                        if ($item->sample_taken_by) $html .= '<div class="mb-1"><i class="mdi mdi-test-tube text-warning"></i> <b>Sample by:</b> ' . userfullname($item->sample_taken_by) . ' <span class="text-muted">(' . date('h:i a D M j, Y', strtotime($item->sample_date)) . ')</span></div>';
                        if ($item->result_by) $html .= '<div class="mb-1"><i class="mdi mdi-clipboard-check text-info"></i> <b>Results by:</b> ' . userfullname($item->result_by) . ' <span class="text-muted">(' . date('h:i a D M j, Y', strtotime($item->result_date)) . ')</span></div>';
                        $html .= '</small></div>';

                        if ($item->note) $html .= '<div class="mb-2"><small><i class="mdi mdi-note-text"></i> <b>Note:</b> ' . e($item->note) . '</small></div>';
                        if ($item->result) $html .= '<div class="alert alert-light mb-1 p-2"><small>' . $item->result . '</small></div>';

                        $html .= '</div></div>';

                        return [
                            'id' => $item->id,
                            'encounter_id' => $item->encounter_id ?? $matchEncounter($item->created_at),
                            'name' => $sName,
                            'status' => $sl[0],
                            'doctor_name' => $doctorName,
                            'date' => $item->created_at->format('M d, Y H:i'),
                            'timestamp' => $item->created_at->toIso8601String(),
                            'info_html' => $html
                        ];
                    });
                    break;

                case 'imaging':
                    $query = ImagingServiceRequest::where('patient_id', $patientId)
                        ->with(['service', 'doctor', 'results_person', 'resultViews']);
                    if ($dateFrom) $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
                    if ($dateTo) $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
                    $items = $query->orderBy('created_at', 'desc')->get();

                    $imgStatusLabels = [0 => ['Dismissed','bg-danger'], 1 => ['Unbilled','bg-warning text-dark'], 2 => ['Billed/Pending','bg-info'], 3 => ['In Progress','bg-primary'], 4 => ['Completed','bg-success'], 5 => ['Pending Approval','bg-warning'], 6 => ['Rejected','bg-danger']];

                    $data = $items->map(function ($item) use ($matchEncounter, $imgStatusLabels) {
                        $sName = $item->service ? $item->service->service_name : 'N/A';
                        $sCode = $item->service ? $item->service->service_code : '';
                        $sl = $imgStatusLabels[$item->status] ?? ['Unknown','bg-secondary'];
                        $doctorName = $item->doctor ? userfullname($item->doctor->id) : 'N/A';

                        $html = '<div class="card-modern mb-2" style="border-left: 4px solid ' . ($item->status >= 4 ? '#198754' : '#6f42c1') . ';">';
                        $html .= '<div class="card-body p-3">';
                        $html .= '<div class="d-flex justify-content-between align-items-start mb-2">';
                        $html .= '<div><h6 class="mb-0"><i class="fa fa-x-ray text-purple"></i> ' . $sName . '</h6>';
                        if ($sCode) $html .= '<small class="text-muted">' . $sCode . '</small>';
                        $html .= '</div>';
                        $html .= '<span class="badge ' . $sl[1] . '">' . $sl[0] . '</span>';
                        $html .= '</div>';

                        $html .= '<div class="mb-2"><small>';
                        $html .= '<div class="mb-1"><i class="mdi mdi-account-arrow-right text-primary"></i> <b>Requested by:</b> ' . $doctorName . ' <span class="text-muted">(' . date('h:i a D M j, Y', strtotime($item->created_at)) . ')</span></div>';
                        if ($item->billed_by) $html .= '<div class="mb-1"><i class="mdi mdi-cash-multiple text-success"></i> <b>Billed by:</b> ' . userfullname($item->billed_by) . ' <span class="text-muted">(' . date('h:i a D M j, Y', strtotime($item->billed_date)) . ')</span></div>';
                        if ($item->result_by) $html .= '<div class="mb-1"><i class="mdi mdi-clipboard-check text-info"></i> <b>Results by:</b> ' . userfullname($item->result_by) . ' <span class="text-muted">(' . date('h:i a D M j, Y', strtotime($item->result_date)) . ')</span></div>';
                        $html .= '</small></div>';

                        if ($item->note) $html .= '<div class="mb-2"><small><i class="mdi mdi-note-text"></i> <b>Note:</b> ' . e($item->note) . '</small></div>';
                        if ($item->result) $html .= '<div class="alert alert-light mb-1 p-2"><small>' . $item->result . '</small></div>';

                        $html .= '</div></div>';

                        return [
                            'id' => $item->id,
                            'encounter_id' => $item->encounter_id ?? $matchEncounter($item->created_at),
                            'name' => $sName,
                            'status' => $sl[0],
                            'doctor_name' => $doctorName,
                            'date' => $item->created_at->format('M d, Y H:i'),
                            'timestamp' => $item->created_at->toIso8601String(),
                            'info_html' => $html
                        ];
                    });
                    break;

                case 'prescriptions':
                    $query = ProductRequest::where('patient_id', $patientId)
                        ->with(['product', 'doctor']);
                    if ($dateFrom) $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
                    if ($dateTo) $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
                    $items = $query->orderBy('created_at', 'desc')->get();

                    $prescStatusLabels = [0 => ['Dismissed','bg-danger'], 1 => ['Unbilled','bg-warning text-dark'], 2 => ['Billed','bg-info'], 3 => ['Dispensed','bg-success']];

                    $data = $items->map(function ($item) use ($matchEncounter, $prescStatusLabels) {
                        $pName = $item->product ? $item->product->product_name : 'N/A';
                        $pCode = $item->product ? ($item->product->product_code ?? '') : '';
                        $dose = $item->dose ?? '';
                        $qty = $item->qty ?? '';
                        $sl = $prescStatusLabels[$item->status] ?? ['Unknown','bg-secondary'];
                        $doctorName = $item->doctor ? userfullname($item->doctor->id) : 'N/A';

                        $html = '<div class="card-modern mb-2" style="border-left: 4px solid ' . ($item->status == 3 ? '#198754' : '#fd7e14') . ';">';
                        $html .= '<div class="card-body p-3">';
                        $html .= '<div class="d-flex justify-content-between align-items-start mb-2">';
                        $html .= '<div><strong>' . $pName . '</strong>';
                        if ($pCode) $html .= ' <small class="text-muted">' . $pCode . '</small>';
                        $html .= '</div>';
                        $html .= '<span class="badge ' . $sl[1] . '">' . $sl[0] . '</span>';
                        $html .= '</div>';
                        $html .= '<div class="mt-1">';
                        $html .= '<span><i class="mdi mdi-pill"></i> ' . ($dose ?: '<em class="text-muted">No dose</em>') . '</span>';
                        $html .= '<span class="ms-2"><i class="mdi mdi-numeric"></i> Qty: ' . ($qty ?: '-') . '</span>';
                        $html .= '</div>';
                        $html .= '<div class="mt-1 text-muted small"><i class="mdi mdi-account"></i> Requested by: ' . $doctorName . ' on ' . date('h:i a D M j, Y', strtotime($item->created_at)) . '</div>';
                        if ($item->billed_by) $html .= '<div class="text-muted small"><i class="mdi mdi-receipt"></i> Billed by: ' . userfullname($item->billed_by) . ' on ' . date('h:i a D M j, Y', strtotime($item->billed_date)) . '</div>';
                        if ($item->dispensed_by) $html .= '<div class="text-muted small"><i class="mdi mdi-truck-delivery"></i> Dispensed by: ' . userfullname($item->dispensed_by) . ' on ' . date('h:i a D M j, Y', strtotime($item->dispensed_date)) . '</div>';
                        $html .= '</div></div>';

                        return [
                            'id' => $item->id,
                            'encounter_id' => $item->encounter_id ?? $matchEncounter($item->created_at),
                            'name' => $pName,
                            'dose' => $dose,
                            'qty' => $qty,
                            'status' => $sl[0],
                            'doctor_name' => $doctorName,
                            'date' => $item->created_at->format('M d, Y H:i'),
                            'timestamp' => $item->created_at->toIso8601String(),
                            'info_html' => $html
                        ];
                    });
                    break;

                case 'care_plans':
                    $query = \App\Models\NonPharmOrder::where('patient_id', $patientId);
                    if ($dateFrom) $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
                    if ($dateTo) $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
                    $items = $query->orderBy('created_at', 'desc')->get();

                    $data = $items->map(function ($item) use ($matchEncounter) {
                        $sBadge = $item->status === 'completed' ? 'bg-success' : ($item->status === 'cancelled' ? 'bg-danger' : 'bg-warning text-dark');
                        $html = '<div class="card-modern mb-2" style="border-left: 4px solid #20c997;"><div class="card-body p-3">';
                        $html .= '<div class="d-flex justify-content-between align-items-center mb-2"><strong><i class="mdi mdi-clipboard-list"></i> ' . e($item->category ?: 'Order') . '</strong>';
                        $html .= '<span class="badge ' . $sBadge . '">' . e($item->status ?: 'Active') . '</span></div>';
                        if ($item->instructions) $html .= '<div class="p-2 bg-light rounded mb-2"><small>' . e($item->instructions) . '</small></div>';
                        $html .= '<div class="row g-1 mb-1">';
                        if ($item->target_executor) $html .= '<div class="col-auto"><small><b>For:</b> ' . e($item->target_executor) . '</small></div>';
                        if ($item->frequency) $html .= '<div class="col-auto"><small><b>Freq:</b> ' . e($item->frequency) . '</small></div>';
                        if ($item->duration) $html .= '<div class="col-auto"><small><b>Duration:</b> ' . e($item->duration) . '</small></div>';
                        $html .= '</div><div class="text-muted small mt-1"><i class="mdi mdi-clock"></i> ' . $item->created_at->format('h:i a D M j, Y') . '</div>';
                        $html .= '</div></div>';
                        return ['id' => $item->id, 'encounter_id' => $item->encounter_id ?? $matchEncounter($item->created_at), 'date' => $item->created_at->format('M d, Y H:i'), 'timestamp' => $item->created_at->toIso8601String(), 'info_html' => $html];
                    });
                    break;

                case 'procedures':
                    $query = \App\Models\Procedure::where('patient_id', $patientId)->with(['service', 'requestedByUser']);
                    if ($dateFrom) $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
                    if ($dateTo) $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
                    $items = $query->orderBy('created_at', 'desc')->get();

                    $data = $items->map(function ($item) use ($matchEncounter) {
                        $sName = $item->service ? $item->service->service_name : 'N/A';
                        $doctor = $item->requestedByUser ? userfullname($item->requestedByUser->id) : 'N/A';
                        $pStat = $item->procedure_status ?? 'Pending';
                        $sBadge = strtolower($pStat) === 'completed' ? 'bg-success' : 'bg-info';
                        $html = '<div class="card-modern mb-2" style="border-left: 4px solid #6f42c1;"><div class="card-body p-3">';
                        $html .= '<div class="d-flex justify-content-between align-items-start mb-2"><div><h6 class="mb-0"><i class="mdi mdi-medical-bag"></i> ' . e($sName) . '</h6></div>';
                        $html .= '<span class="badge ' . $sBadge . '">' . e($pStat) . '</span></div>';
                        if ($item->priority) $html .= '<div class="mb-1"><small><b>Priority:</b> <span class="badge bg-secondary">' . e($item->priority) . '</span></small></div>';
                        if ($item->pre_notes) $html .= '<div class="mb-2"><small class="fw-bold">Pre-Op:</small><div class="p-2 bg-light rounded small">' . $item->pre_notes . '</div></div>';
                        if ($item->post_notes) $html .= '<div class="mb-2"><small class="fw-bold">Post-Op:</small><div class="p-2 bg-light rounded small">' . $item->post_notes . '</div></div>';
                        $html .= '<div class="text-end small text-muted border-top pt-2"><i class="mdi mdi-account"></i> ' . $doctor . ' · ' . $item->created_at->format('h:i a M j, Y') . '</div>';
                        $html .= '</div></div>';
                        return ['id' => $item->id, 'encounter_id' => $item->encounter_id ?? $matchEncounter($item->created_at), 'doctor_name' => $doctor, 'date' => $item->created_at->format('M d, Y H:i'), 'timestamp' => $item->created_at->toIso8601String(), 'info_html' => $html];
                    });
                    break;

                case 'admissions':
                    $query = AdmissionRequest::where('patient_id', $patientId)->with(['service', 'doctor', 'bed', 'bed.wardRelation']);
                    if ($dateFrom) $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
                    if ($dateTo) $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
                    $items = $query->orderBy('created_at', 'desc')->get();

                    $data = $items->map(function ($item) use ($matchEncounter) {
                        $wardName = $item->bed && $item->bed->wardRelation ? $item->bed->wardRelation->name : ($item->bed->ward ?? 'Any Ward');
                        $bedName = $item->bed ? $item->bed->name : 'Any Bed';
                        $doctor = $item->doctor ? userfullname($item->doctor->id) : 'N/A';
                        $sLabel = $item->workflow_status_label ?? $item->admission_status ?? 'Admitted';
                        $html = '<div class="card-modern mb-2" style="border-left: 4px solid #fd7e14;"><div class="card-body p-3">';
                        $html .= '<div class="d-flex justify-content-between align-items-start mb-2">';
                        $html .= '<div><h6 class="mb-0"><i class="mdi mdi-bed"></i> ' . e($item->admission_reason ?: 'Admission') . '</h6>';
                        $html .= '<small class="text-muted">' . e($wardName) . ' · Bed: ' . e($bedName) . '</small></div>';
                        $html .= '<span class="badge bg-info">' . e($sLabel) . '</span></div>';
                        if ($item->chief_complaint) $html .= '<div class="mb-2"><small><b>Chief Complaint:</b> ' . e($item->chief_complaint) . '</small></div>';
                        if ($item->days_admitted) $html .= '<div class="mb-1"><small><b>Duration:</b> ' . $item->days_admitted . ' days</small></div>';
                        if ($item->discharge_reason) $html .= '<div class="mb-1"><small><b>Discharge:</b> ' . e($item->discharge_reason) . '</small></div>';
                        if ($item->discharge_note) $html .= '<div class="alert alert-light mb-1 p-2"><small>' . $item->discharge_note . '</small></div>';
                        $html .= '<div class="text-end small text-muted border-top pt-2"><i class="mdi mdi-account"></i> ' . $doctor . ' · ' . $item->created_at->format('h:i a M j, Y') . '</div>';
                        $html .= '</div></div>';
                        return ['id' => $item->id, 'encounter_id' => $item->encounter_id ?? $matchEncounter($item->created_at), 'doctor_name' => $doctor, 'date' => $item->created_at->format('M d, Y H:i'), 'timestamp' => $item->created_at->toIso8601String(), 'info_html' => $html];
                    });
                    break;

                case 'referrals':
                    $query = \App\Models\SpecialistReferral::where('patient_id', $patientId)
                        ->with(['targetClinic', 'targetDoctor.user', 'referringDoctor.user']);
                    if ($dateFrom) $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
                    if ($dateTo) $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
                    $items = $query->orderBy('created_at', 'desc')->get();

                    $data = $items->map(function ($item) use ($matchEncounter) {
                        $isInt = $item->referral_type === 'internal';
                        $target = $isInt
                            ? ($item->targetClinic->name ?? 'Specialist') . ' (' . ($item->targetDoctor ? userfullname($item->targetDoctor->user_id) : 'Any Doctor') . ')'
                            : ($item->external_facility_name ?? '') . ($item->external_doctor_name ? " (Dr. {$item->external_doctor_name})" : '');
                        $referrer = $item->referringDoctor && $item->referringDoctor->user ? userfullname($item->referringDoctor->user->id) : 'N/A';

                        $html = '<div class="card-modern mb-2" style="border-left: 4px solid #0d6efd;"><div class="card-body p-3">';
                        $html .= '<div class="d-flex justify-content-between align-items-start mb-2">';
                        $html .= '<div><strong><i class="mdi mdi-account-arrow-right"></i> ' . e($target) . '</strong></div>';
                        $html .= '<div><span class="badge ' . ($isInt ? 'bg-primary' : 'bg-warning text-dark') . '">' . ucfirst($item->referral_type ?? '') . '</span>';
                        if ($item->urgency) $html .= ' <span class="badge bg-danger">' . e($item->urgency) . '</span>';
                        $html .= '</div></div>';
                        if ($item->reason) $html .= '<div class="p-2 bg-light rounded mb-2"><small>' . e($item->reason) . '</small></div>';
                        $html .= '<div class="text-end small text-muted border-top pt-2"><i class="mdi mdi-account"></i> Referred by: ' . $referrer . ' · ' . $item->created_at->format('h:i a M j, Y') . '</div>';
                        $html .= '</div></div>';
                        return ['id' => $item->id, 'encounter_id' => $item->encounter_id ?? $matchEncounter($item->created_at), 'doctor_name' => $referrer, 'date' => $item->created_at->format('M d, Y H:i'), 'timestamp' => $item->created_at->toIso8601String(), 'info_html' => $html];
                    });
                    break;

                case 'anc_visits':
                    $query = \App\Models\AncVisit::where('patient_id', $patientId)->with('seenBy');
                    if ($dateFrom) $query->where('visit_date', '>=', Carbon::parse($dateFrom)->startOfDay());
                    if ($dateTo) $query->where('visit_date', '<=', Carbon::parse($dateTo)->endOfDay());
                    $items = $query->orderBy('visit_date', 'desc')->get();

                    $data = $items->map(function ($item) use ($matchEncounter) {
                        $doctor = $item->seenBy ? userfullname($item->seenBy->id) : 'N/A';
                        $dt = $item->visit_date;
                        $html = '<div class="card-modern mb-2" style="border-left: 4px solid #e83e8c;"><div class="card-body p-3">';
                        $html .= '<div class="d-flex justify-content-between align-items-center mb-2">';
                        $html .= '<h6 class="mb-0"><i class="mdi mdi-baby-carriage"></i> ANC Visit #' . ($item->visit_number ?? '?') . '</h6>';
                        $html .= '<div>';
                        if ($item->visit_type) $html .= '<span class="badge bg-pink me-1">' . e($item->visit_type) . '</span>';
                        $html .= '<span class="badge bg-info">' . ($dt ? $dt->format('M j, Y') : '') . '</span></div></div>';
                        $ga = $item->getGestationalAge();
                        if ($ga) $html .= '<div class="mb-2"><span class="badge bg-light text-dark border"><i class="mdi mdi-calendar-clock"></i> GA: ' . e($ga) . '</span></div>';
                        $html .= '<div class="row g-2 mb-2">';
                        $fields = [['Weight', $item->weight_kg, 'kg'], ['BP', $item->getBloodPressure(), ''], ['Fundal Ht', $item->fundal_height_cm, 'cm'], ['FHR', $item->fetal_heart_rate, 'bpm'], ['Presentation', $item->presentation, ''], ['Foetal Mvt', $item->foetal_movement, ''], ['Oedema', $item->oedema, ''], ['Urine Protein', $item->urine_protein, ''], ['Urine Glucose', $item->urine_glucose, ''], ['Hb', $item->haemoglobin, 'g/dL']];
                        foreach ($fields as $f) { if ($f[1] !== null && $f[1] !== '') $html .= '<div class="col-4"><small><b>' . $f[0] . ':</b> ' . e($f[1]) . ' ' . $f[2] . '</small></div>'; }
                        $html .= '</div>';
                        if ($item->clinical_notes) $html .= '<div class="alert alert-light mb-1 p-2"><small>' . e($item->clinical_notes) . '</small></div>';
                        if ($item->next_appointment) $html .= '<div class="small text-muted mb-1"><i class="mdi mdi-calendar-check"></i> Next: ' . $item->next_appointment->format('M d, Y') . '</div>';
                        $html .= '<div class="text-end small text-muted border-top pt-2"><i class="mdi mdi-account"></i> ' . $doctor . '</div>';
                        $html .= '</div></div>';
                        return ['id' => $item->id, 'encounter_id' => $item->encounter_id ?? $matchEncounter($item->visit_date), 'doctor_name' => $doctor, 'date' => $dt ? $dt->format('M d, Y') : null, 'timestamp' => $dt ? $dt->toIso8601String() : null, 'info_html' => $html];
                    });
                    break;

                case 'delivery':
                    $query = \App\Models\DeliveryRecord::where('patient_id', $patientId)->with(['deliveredBy', 'babies']);
                    if ($dateFrom) $query->where('delivery_date', '>=', Carbon::parse($dateFrom)->startOfDay());
                    if ($dateTo) $query->where('delivery_date', '<=', Carbon::parse($dateTo)->endOfDay());
                    $items = $query->orderBy('delivery_date', 'desc')->get();

                    $data = $items->map(function ($item) use ($matchEncounter) {
                        $doctor = $item->deliveredBy ? userfullname($item->deliveredBy->id) : 'N/A';
                        $dt = $item->delivery_date;
                        $html = '<div class="card-modern mb-2" style="border-left: 4px solid #dc3545;"><div class="card-body p-3">';
                        $html .= '<div class="d-flex justify-content-between align-items-center mb-2">';
                        $html .= '<h6 class="mb-0"><i class="mdi mdi-human-pregnant"></i> Delivery Record</h6>';
                        $html .= '<span class="badge bg-danger">' . ($dt ? $dt->format('M j, Y') : '') . ($item->delivery_time ? ' ' . Carbon::parse($item->delivery_time)->format('h:i A') : '') . '</span></div>';
                        $html .= '<div class="row g-2 mb-2">';
                        $dFields = [['Type', $item->type_of_delivery], ['Place', $item->place_of_delivery], ['Labour', $item->duration_of_labour_hours ? $item->duration_of_labour_hours . 'h' : null], ['Blood Loss', $item->blood_loss_ml ? $item->blood_loss_ml . 'ml' : null], ['Placenta', $item->placenta_complete ? 'Complete' : 'Incomplete'], ['Episiotomy', $item->episiotomy]];
                        foreach ($dFields as $f) { if ($f[1]) $html .= '<div class="col-4"><small><b>' . $f[0] . ':</b> ' . e($f[1]) . '</small></div>'; }
                        $html .= '</div>';
                        if ($item->complications) $html .= '<div class="mb-2"><small><b>Complications:</b> <span class="text-danger">' . e($item->complications) . '</span></small></div>';
                        if ($item->babies->count() > 0) {
                            $html .= '<table class="table table-sm table-bordered mb-1" style="font-size:0.8rem;"><thead><tr><th>#</th><th>Sex</th><th>Weight</th><th>APGAR</th><th>Feeding</th></tr></thead><tbody>';
                            foreach ($item->babies as $b) {
                                $html .= '<tr><td>' . e($b->birth_order) . '</td><td>' . e($b->sex) . '</td><td>' . e($b->birth_weight_kg) . ' kg</td><td>' . e($b->getApgarSummary()) . '</td><td>' . e($b->feeding_method) . '</td></tr>';
                            }
                            $html .= '</tbody></table>';
                        }
                        if ($item->notes) $html .= '<div class="alert alert-light mb-1 p-2"><small>' . e($item->notes) . '</small></div>';
                        $html .= '<div class="text-end small text-muted border-top pt-2"><i class="mdi mdi-account"></i> Delivered by: ' . $doctor . '</div>';
                        $html .= '</div></div>';
                        return ['id' => $item->id, 'encounter_id' => $item->encounter_id ?? $matchEncounter($item->delivery_date), 'doctor_name' => $doctor, 'date' => $dt ? $dt->format('M d, Y') : null, 'timestamp' => $dt ? $dt->toIso8601String() : null, 'info_html' => $html];
                    });
                    break;

                case 'postnatal':
                    $query = \App\Models\PostnatalVisit::where('patient_id', $patientId)->with('seenBy');
                    if ($dateFrom) $query->where('visit_date', '>=', Carbon::parse($dateFrom)->startOfDay());
                    if ($dateTo) $query->where('visit_date', '<=', Carbon::parse($dateTo)->endOfDay());
                    $items = $query->orderBy('visit_date', 'desc')->get();

                    $data = $items->map(function ($item) use ($matchEncounter) {
                        $doctor = $item->seenBy ? userfullname($item->seenBy->id) : 'N/A';
                        $dt = $item->visit_date;
                        $html = '<div class="card-modern mb-2" style="border-left: 4px solid #d63384;"><div class="card-body p-3">';
                        $html .= '<div class="d-flex justify-content-between align-items-center mb-2">';
                        $html .= '<h6 class="mb-0"><i class="mdi mdi-mother-heart"></i> Postnatal Visit</h6>';
                        $html .= '<div>';
                        if ($item->visit_type) $html .= '<span class="badge bg-pink me-1">' . e($item->visit_type) . '</span>';
                        if ($item->days_postpartum) $html .= '<span class="badge bg-light text-dark border me-1">Day ' . e($item->days_postpartum) . '</span>';
                        $html .= '<span class="badge bg-info">' . ($dt ? $dt->format('M j, Y') : '') . '</span></div></div>';
                        // Mother assessment
                        $html .= '<div class="mb-2"><small class="fw-bold text-muted">Mother</small></div><div class="row g-2 mb-2">';
                        $mFields = [['Condition', $item->general_condition], ['BP', $item->blood_pressure], ['Temp', $item->temperature_c ? $item->temperature_c . '°C' : null], ['Uterus', $item->uterus_assessment], ['Lochia', $item->lochia], ['Emotional', $item->emotional_wellbeing]];
                        foreach ($mFields as $f) { if ($f[1]) $html .= '<div class="col-4"><small><b>' . $f[0] . ':</b> ' . e($f[1]) . '</small></div>'; }
                        $html .= '</div>';
                        // Baby assessment
                        $html .= '<div class="mb-2"><small class="fw-bold text-muted">Baby</small></div><div class="row g-2 mb-2">';
                        $bFields = [['Weight', $item->baby_weight_kg ? $item->baby_weight_kg . ' kg' : null], ['Feeding', $item->baby_feeding], ['Cord', $item->cord_status]];
                        foreach ($bFields as $f) { if ($f[1]) $html .= '<div class="col-4"><small><b>' . $f[0] . ':</b> ' . e($f[1]) . '</small></div>'; }
                        $html .= '</div>';
                        if ($item->clinical_notes) $html .= '<div class="alert alert-light mb-1 p-2"><small>' . e($item->clinical_notes) . '</small></div>';
                        $html .= '<div class="text-end small text-muted border-top pt-2"><i class="mdi mdi-account"></i> ' . $doctor . '</div>';
                        $html .= '</div></div>';
                        return ['id' => $item->id, 'encounter_id' => $item->encounter_id ?? $matchEncounter($item->visit_date), 'doctor_name' => $doctor, 'date' => $dt ? $dt->format('M d, Y') : null, 'timestamp' => $dt ? $dt->toIso8601String() : null, 'info_html' => $html];
                    });
                    break;
            }

            // Post-filtering by encounter if specified
            if ($encounterFilter) {
                $data = collect($data)->filter(function ($item) use ($encounterFilter) {
                    if ($encounterFilter === 'unassociated') {
                        return is_null($item['encounter_id']);
                    }
                    return intval($item['encounter_id']) === intval($encounterFilter);
                })->values();
            } else {
                $data = collect($data)->values();
            }

            return response()->json([
                'success' => true,
                'category' => $category,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching clinical story category: ' . ($category ?? 'none') . ' - ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Public wrapper to fetch clinical story category items via Encounter.
     */
    public function getPatientClinicalStory(Request $request, Encounter $encounter)
    {
        return $this->getPatientClinicalStoryCommon($request, $encounter->patient_id);
    }

    /**
     * Public fallback to fetch clinical story category items via Patient ID directly.
     */
    public function getPatientClinicalStoryFallback(Request $request, $patient)
    {
        $patientId = is_object($patient) ? $patient->id : intval($patient);
        return $this->getPatientClinicalStoryCommon($request, $patientId);
    }
}
