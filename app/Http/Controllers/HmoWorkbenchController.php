<?php

namespace App\Http\Controllers;

use App\Models\ProductOrServiceRequest;
use App\Models\Hmo;
use App\Models\patient;
use App\Models\VitalSign;
use App\Models\Encounter;
use App\Models\ProductRequest;
use App\Models\LabServiceRequest;
use App\Models\ImagingServiceRequest;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;
use Carbon\Carbon;

class HmoWorkbenchController extends Controller
{
    // Common rejection reasons
    const REJECTION_REASONS = [
        'not_covered' => 'Service not covered under patient\'s plan',
        'pre_existing' => 'Pre-existing condition exclusion',
        'waiting_period' => 'Waiting period not yet met',
        'limit_exceeded' => 'Annual/benefit limit exceeded',
        'no_preauth' => 'No pre-authorization obtained',
        'documentation' => 'Insufficient documentation provided',
        'duplicate' => 'Duplicate claim submission',
        'expired_enrollment' => 'Patient enrollment expired',
        'other' => 'Other (specify in notes)'
    ];

    /**
     * Display the HMO workbench page.
     */
    public function index()
    {
        $hmos = Hmo::where('status', 1)->orderBy('name', 'ASC')->get();
        $rejectionReasons = self::REJECTION_REASONS;
        return view('admin.hmo.workbench', compact('hmos', 'rejectionReasons'));
    }

    /**
     * Get HMO requests for DataTables with filters.
     */
    public function getRequests(Request $request)
    {
        $query = ProductOrServiceRequest::with([
            'user.patient_profile.hmo',
            'service.price',
            'product.price',
            'validator',
            'staff', // For "Requested By" info
            'procedure.procedureDefinition' // For Procedure items
        ])
        ->whereHas('user.patient_profile', function($q) {
            $q->whereNotNull('hmo_id');
        })
        ->whereNotNull('coverage_mode'); // Only HMO requests

        // Tab filters - each tab has different logic
        if ($request->filled('tab')) {
            switch ($request->tab) {
                case 'pending':
                    $query->where('validation_status', 'pending')
                          ->whereIn('coverage_mode', ['primary', 'secondary'])
                          ->where('claims_amount', '>', 0) // Skip items with 0 claims - no validation needed
                          ->where(function($q) {
                              $q->whereNull('payment_id')
                                ->orWhereNotNull('payment_id'); // Include both paid and unpaid
                          });
                    break;
                case 'express':
                    $query->where('coverage_mode', 'express')
                          ->whereNull('payment_id');
                    break;
                case 'approved':
                    $query->where('validation_status', 'approved');
                    break;
                case 'rejected':
                    $query->where('validation_status', 'rejected');
                    break;
                case 'claims':
                    $query->whereNotNull('payment_id')
                          ->where('claims_amount', '>', 0);
                    break;
                // 'all' - no filter
            }
        }

        // Quick filter presets
        if ($request->filled('preset')) {
            switch ($request->preset) {
                case 'overdue':
                    $query->where('validation_status', 'pending')
                          ->where('created_at', '<', Carbon::now()->subHours(4));
                    break;
                case 'high_value':
                    $query->where('claims_amount', '>', 50000);
                    break;
                case 'today_approved':
                    $query->where('validation_status', 'approved')
                          ->whereDate('validated_at', today());
                    break;
                case 'today_rejected':
                    $query->where('validation_status', 'rejected')
                          ->whereDate('validated_at', today());
                    break;
            }
        }

        // Additional filters
        if ($request->filled('hmo_id')) {
            $query->whereHas('user.patient_profile', function($q) use ($request) {
                $q->where('hmo_id', $request->hmo_id);
            });
        }

        if ($request->filled('coverage_mode')) {
            $query->where('coverage_mode', $request->coverage_mode);
        }

        // Service Type filter - Products, Services, or Procedures
        if ($request->filled('service_type')) {
            switch ($request->service_type) {
                case 'product':
                    // Has product_id and no linked procedure
                    $query->whereNotNull('product_id')
                          ->whereDoesntHave('procedure');
                    break;
                case 'service':
                    // Has service_id and no linked procedure
                    $query->whereNotNull('service_id')
                          ->whereDoesntHave('procedure');
                    break;
                case 'procedure':
                    // Has a linked procedure
                    $query->whereHas('procedure');
                    break;
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('id', 'LIKE', "%{$search}%")
                  ->orWhereHas('user', function($q2) use ($search) {
                      $q2->where('firstname', 'LIKE', "%{$search}%")
                         ->orWhere('surname', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('user.patient_profile', function($q2) use ($search) {
                      $q2->where('file_no', 'LIKE', "%{$search}%")
                         ->orWhere('hmo_no', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('service', function($q2) use ($search) {
                      $q2->where('service_name', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('product', function($q2) use ($search) {
                      $q2->where('product_name', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('procedure.procedureDefinition', function($q2) use ($search) {
                      $q2->where('name', 'LIKE', "%{$search}%");
                  });
            });
        }

        $requests = $query->orderBy('created_at', 'DESC')->get();

        return DataTables::of($requests)
            ->addIndexColumn()
            ->addColumn('checkbox', function ($req) {
                return '<input type="checkbox" class="batch-select-checkbox" data-id="' . $req->id . '" style="transform: scale(1.5); cursor: pointer;">';
            })
            // Column 1: Patient & Actions (combined)
            ->addColumn('patient_info', function ($req) {
                if ($req->user && $req->user->patient_profile) {
                    $name = userfullname($req->user_id);
                    $fileNo = $req->user->patient_profile->file_no ?? 'N/A';
                    $hmoNo = $req->user->patient_profile->hmo_no ?? '';
                    $hmoName = $req->user->patient_profile->hmo->name ?? 'N/A';
                    $patientId = $req->user->patient_profile->id;

                    $html = "<strong>$name</strong>";
                    $html .= "<br><small class=\"text-muted\">File: $fileNo</small>";
                    $html .= $hmoNo ? " | <small class=\"text-info\">HMO#: $hmoNo</small>" : '';
                    $html .= "<br><small class=\"text-primary\"><i class=\"fa fa-hospital\"></i> $hmoName</small>";

                    // Action buttons with full labels
                    $html .= '<div class="btn-group-vertical btn-group-sm mt-2 w-100">';

                    // View details button
                    $html .= '<button type="button" class="btn btn-info btn-sm view-details-btn mb-1" data-id="' . $req->id . '">
                        <i class="fa fa-eye"></i> View Details
                    </button>';

                    // Clinical context button
                    $html .= '<button type="button" class="btn btn-outline-info btn-sm clinical-context-btn mb-1" data-patient-id="' . $patientId . '">
                        <i class="fa fa-heartbeat"></i> Clinical Context
                    </button>';

                    // Patient history button
                    $html .= '<button type="button" class="btn btn-outline-secondary btn-sm history-btn mb-1" data-patient-id="' . $patientId . '">
                        <i class="fa fa-history"></i> HMO History
                    </button>';

                    // Approve/Reject buttons for pending requests
                    if ($req->validation_status === 'pending' && in_array($req->coverage_mode, ['primary', 'secondary'])) {
                        $html .= '<button type="button" class="btn btn-success btn-sm approve-btn mb-1" data-id="' . $req->id . '" data-mode="' . $req->coverage_mode . '">
                            <i class="fa fa-check"></i> Approve
                        </button>
                        <button type="button" class="btn btn-danger btn-sm reject-btn mb-1" data-id="' . $req->id . '">
                            <i class="fa fa-times"></i> Reject
                        </button>';
                    }

                    // Re-approve button for rejected requests
                    if ($req->validation_status === 'rejected') {
                        $canReverse = $this->checkServiceDeliveryStatus($req);
                        if ($canReverse['can_reverse']) {
                            $html .= '<button type="button" class="btn btn-warning btn-sm reapprove-btn mb-1" data-id="' . $req->id . '" data-mode="' . $req->coverage_mode . '">
                                <i class="fa fa-undo"></i> Re-approve
                            </button>';
                        }
                    }

                    // Reverse approval button for approved requests
                    if ($req->validation_status === 'approved') {
                        $canReverse = $this->checkServiceDeliveryStatus($req);
                        if ($canReverse['can_reverse']) {
                            $html .= '<button type="button" class="btn btn-warning btn-sm reverse-btn mb-1" data-id="' . $req->id . '">
                                <i class="fa fa-undo"></i> Reverse Approval
                            </button>';
                        }
                    }

                    $html .= '</div>';
                    return $html;
                }
                return 'N/A';
            })
            // Column 2: Request Info (combined: ID, Date, SLA, Requested By)
            ->addColumn('request_info', function ($req) {
                // Get the staff who made the request
                $requestedBy = 'System';
                if ($req->staff_user_id) {
                    $requestedBy = userfullname($req->staff_user_id);
                    if (!$requestedBy && $req->staff) {
                        $requestedBy = $req->staff->fname . ' ' . $req->staff->lname;
                    }
                }

                $date = $req->created_at ? $req->created_at->format('M d, Y H:i') : 'N/A';

                // SLA indicator
                $slaHtml = '';
                if ($req->validation_status === 'pending') {
                    $hours = $req->created_at->diffInHours(now());
                    if ($hours < 2) {
                        $slaHtml = '<span class="badge badge-success" title="Within SLA"><i class="fa fa-clock"></i> ' . $hours . 'h</span>';
                    } elseif ($hours < 4) {
                        $slaHtml = '<span class="badge badge-warning" title="Approaching SLA"><i class="fa fa-clock"></i> ' . $hours . 'h</span>';
                    } else {
                        $slaHtml = '<span class="badge badge-danger" title="SLA Breached"><i class="fa fa-exclamation-triangle"></i> ' . $hours . 'h</span>';
                    }
                }

                $html = "<strong>#$req->id</strong> $slaHtml";
                $html .= "<br><small class=\"text-muted\"><i class=\"fa fa-calendar\"></i> $date</small>";
                $html .= "<br><small class=\"text-secondary\"><i class=\"fa fa-user-md\"></i> By: $requestedBy</small>";

                return $html;
            })
            // Column 3: Item Details (combined: Type, Name, Qty)
            ->addColumn('item_details', function ($req) {
                $itemName = 'N/A';
                $itemType = 'N/A';
                $badgeColor = 'secondary';

                // Check if this is a procedure billing entry first
                $procedure = $req->procedure;
                if ($procedure) {
                    $itemName = $procedure->procedureDefinition->name ?? 'Unknown Procedure';
                    $itemType = 'Procedure';
                    $badgeColor = 'purple';
                } elseif ($req->product_id && $req->product) {
                    $itemName = $req->product->product_name;
                    $itemType = 'Product';
                    $badgeColor = 'success';
                } elseif ($req->service_id && $req->service) {
                    $itemName = $req->service->service_name;
                    $itemType = 'Service';
                    $badgeColor = 'info';
                }

                $html = "<span class=\"badge badge-$badgeColor\">$itemType</span>";
                $html .= "<br><strong>$itemName</strong>";
                $html .= "<br><small class=\"text-muted\">Qty: $req->qty</small>";

                // Add procedure-specific info
                if ($procedure) {
                    $procedureUrl = route('patient-procedures.show', $procedure->id);
                    $html .= "<br><a href=\"{$procedureUrl}\" class=\"btn btn-xs btn-outline-primary mt-1\"><i class=\"fa fa-external-link-alt\"></i> View Procedure</a>";
                }

                return $html;
            })
            // Column 4: Pricing (combined: Original, Claims, Payable)
            ->addColumn('pricing_info', function ($req) {
                $originalPrice = 'N/A';
                if ($req->product_id && $req->product && $req->product->price) {
                    $originalPrice = '₦' . number_format($req->product->price->current_sale_price, 2);
                } elseif ($req->service_id && $req->service && $req->service->price) {
                    $originalPrice = '₦' . number_format($req->service->price->sale_price, 2);
                }

                $claimsAmount = '₦' . number_format($req->claims_amount, 2);
                $payableAmount = '₦' . number_format($req->payable_amount, 2);

                $html = "<small class=\"text-muted\">Original:</small> $originalPrice";
                $html .= "<br><small class=\"text-success\"><strong>HMO:</strong></small> <strong class=\"text-success\">$claimsAmount</strong>";
                $html .= "<br><small class=\"text-warning\"><strong>Patient:</strong></small> <strong class=\"text-warning\">$payableAmount</strong>";

                return $html;
            })
            // Column 5: Coverage & Payment (combined)
            ->addColumn('coverage_payment', function ($req) {
                $badgeColor = $req->coverage_mode === 'express' ? 'success' :
                             ($req->coverage_mode === 'primary' ? 'warning' : 'danger');
                $coverageBadge = '<span class="badge badge-' . $badgeColor . '">' . strtoupper($req->coverage_mode) . '</span>';

                $paymentBadge = $req->payment_id
                    ? '<span class="badge badge-success"><i class="fa fa-check"></i> Paid</span>'
                    : '<span class="badge badge-secondary"><i class="fa fa-clock"></i> Unpaid</span>';

                return "$coverageBadge<br>$paymentBadge";
            })
            // Column 6: Status & Validation (combined)
            ->addColumn('status_validation', function ($req) {
                $statusMap = [
                    'pending' => 'warning',
                    'approved' => 'success',
                    'rejected' => 'danger'
                ];
                $statusColor = $statusMap[$req->validation_status] ?? 'secondary';
                $statusBadge = '<span class="badge badge-' . $statusColor . '">' . strtoupper($req->validation_status) . '</span>';

                $html = $statusBadge;

                if ($req->validated_at && $req->validator) {
                    $validatorName = userfullname($req->validated_by);
                    $date = Carbon::parse($req->validated_at)->format('M d H:i');
                    $html .= "<br><small class=\"text-muted\">$validatorName</small>";
                    $html .= "<br><small class=\"text-muted\">$date</small>";
                } else {
                    $html .= "<br><small class=\"text-muted\">Awaiting validation</small>";
                }

                return $html;
            })
            ->rawColumns(['checkbox', 'patient_info', 'request_info', 'item_details', 'pricing_info', 'coverage_payment', 'status_validation'])
            ->make(true);
    }

    /**
     * Check if a service has been delivered (results entered, encounter completed, etc.)
     */
    private function checkServiceDeliveryStatus($request)
    {
        // Check if it's a lab service
        $labRequest = LabServiceRequest::where('service_request_id', $request->id)->first();
        if ($labRequest) {
            if ($labRequest->result || $labRequest->status >= 3) {
                return ['can_reverse' => false, 'reason' => 'Lab results have been entered'];
            }
        }

        // Check if it's an imaging service
        $imagingRequest = ImagingServiceRequest::where('service_request_id', $request->id)->first();
        if ($imagingRequest) {
            if ($imagingRequest->result || $imagingRequest->status >= 3) {
                return ['can_reverse' => false, 'reason' => 'Imaging results have been entered'];
            }
        }

        // Check if it's a pharmacy product (dispensed)
        if ($request->product_id) {
            $productReq = ProductRequest::where('product_request_id', $request->id)->first();
            if ($productReq && $productReq->dispensed_by) {
                return ['can_reverse' => false, 'reason' => 'Product has been dispensed'];
            }
        }

        return ['can_reverse' => true, 'reason' => null];
    }

    /**
     * Get single request details for modal.
     */
    public function show($id)
    {
        $request = ProductOrServiceRequest::with([
            'user.patient_profile.hmo',
            'service.price',
            'product.price',
            'validator',
            'audits'
        ])->findOrFail($id);

        // Get delivery status
        $deliveryStatus = $this->checkServiceDeliveryStatus($request);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $request->id,
                'patient_name' => $request->user ? userfullname($request->user_id) : 'N/A',
                'patient_id' => $request->user && $request->user->patient_profile ? $request->user->patient_profile->id : null,
                'file_no' => $request->user && $request->user->patient_profile ? $request->user->patient_profile->file_no : 'N/A',
                'hmo_no' => $request->user && $request->user->patient_profile ? $request->user->patient_profile->hmo_no : '',
                'hmo_name' => $request->user && $request->user->patient_profile && $request->user->patient_profile->hmo
                    ? $request->user->patient_profile->hmo->name : 'N/A',
                'item_type' => $request->product_id ? 'Product' : 'Service',
                'item_name' => $request->product_id
                    ? ($request->product ? $request->product->product_name : 'N/A')
                    : ($request->service ? $request->service->service_name : 'N/A'),
                'qty' => $request->qty,
                'original_price' => $request->product_id && $request->product && $request->product->price
                    ? $request->product->price->current_sale_price
                    : ($request->service_id && $request->service && $request->service->price
                        ? $request->service->price->sale_price
                        : 0),
                'claims_amount' => $request->claims_amount,
                'payable_amount' => $request->payable_amount,
                'coverage_mode' => $request->coverage_mode,
                'validation_status' => $request->validation_status,
                'auth_code' => $request->auth_code,
                'validation_notes' => $request->validation_notes,
                'validated_by_name' => $request->validator ? userfullname($request->validated_by) : null,
                'validated_at' => $request->validated_at ? Carbon::parse($request->validated_at)->format('Y-m-d H:i:s') : null,
                'created_at' => $request->created_at ? Carbon::parse($request->created_at)->format('Y-m-d H:i:s') : null,
                'payment_id' => $request->payment_id,
                'can_reverse' => $deliveryStatus['can_reverse'],
                'reverse_reason' => $deliveryStatus['reason'],
                'audits' => $request->audits ? $request->audits->map(function($audit) {
                    return [
                        'event' => $audit->event,
                        'old_values' => $audit->old_values,
                        'new_values' => $audit->new_values,
                        'user' => $audit->user ? userfullname($audit->user_id) : 'System',
                        'created_at' => $audit->created_at->format('Y-m-d H:i:s'),
                    ];
                }) : [],
            ]
        ]);
    }

    /**
     * Get patient visit history for fraud detection
     */
    public function getPatientHistory($patientId)
    {
        $patient = patient::findOrFail($patientId);

        $history = ProductOrServiceRequest::with(['service', 'product', 'validator'])
            ->where('user_id', $patient->user_id)
            ->whereNotNull('coverage_mode')
            ->orderBy('created_at', 'DESC')
            ->limit(20)
            ->get()
            ->map(function($req) {
                return [
                    'id' => $req->id,
                    'date' => Carbon::parse($req->created_at)->format('Y-m-d H:i'),
                    'item' => $req->product_id
                        ? ($req->product ? $req->product->product_name : 'N/A')
                        : ($req->service ? $req->service->service_name : 'N/A'),
                    'type' => $req->product_id ? 'Product' : 'Service',
                    'claims_amount' => $req->claims_amount,
                    'payable_amount' => $req->payable_amount,
                    'coverage_mode' => $req->coverage_mode,
                    'validation_status' => $req->validation_status,
                    'validated_by' => $req->validator ? userfullname($req->validated_by) : null,
                ];
            });

        // Calculate summary stats
        $totalClaims = ProductOrServiceRequest::where('user_id', $patient->user_id)
            ->whereNotNull('coverage_mode')
            ->where('validation_status', 'approved')
            ->sum('claims_amount');

        $totalVisits = ProductOrServiceRequest::where('user_id', $patient->user_id)
            ->whereNotNull('coverage_mode')
            ->count();

        $thisMonthClaims = ProductOrServiceRequest::where('user_id', $patient->user_id)
            ->whereNotNull('coverage_mode')
            ->where('validation_status', 'approved')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('claims_amount');

        return response()->json([
            'history' => $history,
            'summary' => [
                'total_claims' => $totalClaims,
                'total_visits' => $totalVisits,
                'this_month_claims' => $thisMonthClaims,
            ]
        ]);
    }

    /**
     * Get patient vitals for clinical context
     */
    public function getPatientVitals($patientId)
    {
        $vitals = VitalSign::where('patient_id', $patientId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($v) {
                return [
                    'id' => $v->id,
                    'created_at' => $v->created_at,
                    'blood_pressure' => $v->blood_pressure,
                    'temp' => $v->temp,
                    'heart_rate' => $v->heart_rate,
                    'resp_rate' => $v->resp_rate,
                    'other_notes' => $v->other_notes,
                    'time_taken' => $v->time_taken,
                ];
            });

        return response()->json($vitals);
    }

    /**
     * Get patient clinical notes
     */
    public function getPatientNotes($patientId)
    {
        $patient = patient::findOrFail($patientId);

        $encounters = Encounter::with(['doctor.staff_profile.specialization'])
            ->where('patient_id', $patientId)
            ->whereNotNull('notes')
            ->where('completed', true)
            ->orderBy('created_at', 'desc')
            ->limit(10)
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
                'notes' => $encounter->notes,
                'notes_preview' => \Illuminate\Support\Str::limit(strip_tags($encounter->notes), 150),
            ];
        });

        return response()->json($notes);
    }

    /**
     * Get patient medications
     */
    public function getPatientMedications($patientId)
    {
        $patient = patient::findOrFail($patientId);

        $meds = ProductRequest::with(['product', 'doctor', 'biller', 'dispenser'])
            ->where('patient_id', $patientId)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $result = $meds->map(function ($med) {
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
                'freq' => $med->freq ?? 'N/A',
                'duration' => $med->duration ?? 'N/A',
                'status' => $status,
                'requested_date' => $med->created_at->format('h:i a D M j, Y'),
                'doctor' => $med->doctor ? $med->doctor->firstname . ' ' . $med->doctor->surname : 'N/A',
            ];
        });

        return response()->json($result);
    }

    /**
     * Approve an HMO request.
     */
    public function approveRequest(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'validation_notes' => 'nullable|string|max:500',
            'auth_code' => 'required_if:coverage_mode,secondary|nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $hmoRequest = ProductOrServiceRequest::findOrFail($id);

            // For secondary coverage, auth code is mandatory
            if ($hmoRequest->coverage_mode === 'secondary' && empty($request->auth_code)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authorization code is required for secondary coverage'
                ], 422);
            }

            $hmoRequest->update([
                'validation_status' => 'approved',
                'validated_by' => Auth::id(),
                'validated_at' => now(),
                'validation_notes' => $request->validation_notes,
                'auth_code' => $request->auth_code,
            ]);

            DB::commit();

            // Send notification to HMO executives group
            $this->sendHmoNotification(
                "Request #{$id} Approved",
                "HMO request for " . ($hmoRequest->service ? $hmoRequest->service->service_name : ($hmoRequest->product ? $hmoRequest->product->product_name : 'Unknown')) . " has been approved by " . userfullname(Auth::id())
            );

            return response()->json([
                'success' => true,
                'message' => 'Request approved successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error approving request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject an HMO request.
     */
    public function rejectRequest(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string',
            'validation_notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed. Rejection reason is required.',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $hmoRequest = ProductOrServiceRequest::findOrFail($id);

            // Build rejection note from reason code and custom notes
            $reasonText = self::REJECTION_REASONS[$request->rejection_reason] ?? $request->rejection_reason;
            $fullNotes = $reasonText;
            if ($request->validation_notes) {
                $fullNotes .= "\n\nAdditional notes: " . $request->validation_notes;
            }

            $hmoRequest->update([
                'validation_status' => 'rejected',
                'validated_by' => Auth::id(),
                'validated_at' => now(),
                'validation_notes' => $fullNotes,
            ]);

            DB::commit();

            // Send notification
            $this->sendHmoNotification(
                "Request #{$id} Rejected",
                "HMO request for " . ($hmoRequest->service ? $hmoRequest->service->service_name : ($hmoRequest->product ? $hmoRequest->product->product_name : 'Unknown')) . " has been rejected. Reason: " . $reasonText
            );

            return response()->json([
                'success' => true,
                'message' => 'Request rejected successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reverse an approved request (set back to pending)
     */
    public function reverseApproval(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Reason is required for reversal',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $hmoRequest = ProductOrServiceRequest::findOrFail($id);

            // Check if service has been delivered
            $deliveryStatus = $this->checkServiceDeliveryStatus($hmoRequest);
            if (!$deliveryStatus['can_reverse']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot reverse: ' . $deliveryStatus['reason']
                ], 422);
            }

            $previousStatus = $hmoRequest->validation_status;
            $previousNotes = $hmoRequest->validation_notes;

            $hmoRequest->update([
                'validation_status' => 'pending',
                'validated_by' => null,
                'validated_at' => null,
                'validation_notes' => "Reversed from '{$previousStatus}' by " . userfullname(Auth::id()) . " on " . now()->format('Y-m-d H:i') . "\nReason: " . $request->reason . "\n\nPrevious notes: " . $previousNotes,
                'auth_code' => null,
            ]);

            DB::commit();

            // Send notification
            $this->sendHmoNotification(
                "Request #{$id} Reversed",
                "HMO request approval has been reversed by " . userfullname(Auth::id()) . ". Reason: " . $request->reason
            );

            return response()->json([
                'success' => true,
                'message' => 'Request reversed to pending status'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error reversing request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Re-approve a rejected request
     */
    public function reapproveRequest(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'validation_notes' => 'nullable|string|max:500',
            'auth_code' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $hmoRequest = ProductOrServiceRequest::findOrFail($id);

            // Check if service has been delivered
            $deliveryStatus = $this->checkServiceDeliveryStatus($hmoRequest);
            if (!$deliveryStatus['can_reverse']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot re-approve: ' . $deliveryStatus['reason']
                ], 422);
            }

            // For secondary coverage, auth code is mandatory
            if ($hmoRequest->coverage_mode === 'secondary' && empty($request->auth_code)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authorization code is required for secondary coverage'
                ], 422);
            }

            $previousNotes = $hmoRequest->validation_notes;

            $hmoRequest->update([
                'validation_status' => 'approved',
                'validated_by' => Auth::id(),
                'validated_at' => now(),
                'validation_notes' => "Re-approved by " . userfullname(Auth::id()) . " on " . now()->format('Y-m-d H:i') . ($request->validation_notes ? "\nNotes: " . $request->validation_notes : "") . "\n\nPrevious rejection: " . $previousNotes,
                'auth_code' => $request->auth_code,
            ]);

            DB::commit();

            // Send notification
            $this->sendHmoNotification(
                "Request #{$id} Re-approved",
                "Previously rejected HMO request has been re-approved by " . userfullname(Auth::id())
            );

            return response()->json([
                'success' => true,
                'message' => 'Request re-approved successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error re-approving request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch approve multiple requests
     */
    public function batchApprove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'request_ids' => 'required|array',
            'request_ids.*' => 'exists:product_or_service_requests,id',
            'validation_notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $approved = 0;
            $skipped = 0;
            $errors = [];

            foreach ($request->request_ids as $id) {
                $hmoRequest = ProductOrServiceRequest::find($id);

                if (!$hmoRequest) {
                    $skipped++;
                    continue;
                }

                // Skip if not pending or if secondary without auth code
                if ($hmoRequest->validation_status !== 'pending') {
                    $skipped++;
                    continue;
                }

                if ($hmoRequest->coverage_mode === 'secondary') {
                    $errors[] = "Request #{$id} requires auth code (secondary coverage)";
                    $skipped++;
                    continue;
                }

                $hmoRequest->update([
                    'validation_status' => 'approved',
                    'validated_by' => Auth::id(),
                    'validated_at' => now(),
                    'validation_notes' => $request->validation_notes ?? 'Batch approved',
                ]);

                $approved++;
            }

            DB::commit();

            // Send notification
            if ($approved > 0) {
                $this->sendHmoNotification(
                    "Batch Approval: {$approved} Requests",
                    userfullname(Auth::id()) . " batch approved {$approved} HMO requests"
                );
            }

            return response()->json([
                'success' => true,
                'message' => "{$approved} request(s) approved" . ($skipped > 0 ? ", {$skipped} skipped" : ""),
                'approved' => $approved,
                'skipped' => $skipped,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error in batch approval: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch reject multiple requests
     */
    public function batchReject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'request_ids' => 'required|array',
            'request_ids.*' => 'exists:product_or_service_requests,id',
            'rejection_reason' => 'required|string',
            'validation_notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed. Rejection reason is required.',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $rejected = 0;
            $skipped = 0;

            $reasonText = self::REJECTION_REASONS[$request->rejection_reason] ?? $request->rejection_reason;
            $fullNotes = $reasonText;
            if ($request->validation_notes) {
                $fullNotes .= "\n\nAdditional notes: " . $request->validation_notes;
            }

            foreach ($request->request_ids as $id) {
                $hmoRequest = ProductOrServiceRequest::find($id);

                if (!$hmoRequest || $hmoRequest->validation_status !== 'pending') {
                    $skipped++;
                    continue;
                }

                $hmoRequest->update([
                    'validation_status' => 'rejected',
                    'validated_by' => Auth::id(),
                    'validated_at' => now(),
                    'validation_notes' => $fullNotes,
                ]);

                $rejected++;
            }

            DB::commit();

            // Send notification
            if ($rejected > 0) {
                $this->sendHmoNotification(
                    "Batch Rejection: {$rejected} Requests",
                    userfullname(Auth::id()) . " batch rejected {$rejected} HMO requests. Reason: " . $reasonText
                );
            }

            return response()->json([
                'success' => true,
                'message' => "{$rejected} request(s) rejected" . ($skipped > 0 ? ", {$skipped} skipped" : ""),
                'rejected' => $rejected,
                'skipped' => $skipped
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error in batch rejection: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get queue counts for dashboard cards.
     */
    public function getQueueCounts()
    {
        // Base query for HMO patients
        $baseQuery = function() {
            return ProductOrServiceRequest::whereHas('user.patient_profile', function($q) {
                $q->whereNotNull('hmo_id');
            });
        };

        $counts = [
            'pending' => $baseQuery()
                ->where('validation_status', 'pending')
                ->whereIn('coverage_mode', ['primary', 'secondary'])
                ->where('claims_amount', '>', 0) // Skip 0 claims - no validation needed
                ->count(),

            'express' => $baseQuery()
                ->where('coverage_mode', 'express')
                ->count(),

            'approved' => $baseQuery()
                ->where('validation_status', 'approved')
                ->count(),

            'rejected' => $baseQuery()
                ->where('validation_status', 'rejected')
                ->count(),

            'claims' => $baseQuery()
                ->whereIn('validation_status', ['approved', 'pending'])
                ->where('claims_amount', '>', 0)
                ->count(),

            'all' => $baseQuery()->count(),

            'approved_today' => $baseQuery()
                ->where('validation_status', 'approved')
                ->whereDate('validated_at', today())
                ->count(),

            'rejected_today' => $baseQuery()
                ->where('validation_status', 'rejected')
                ->whereDate('validated_at', today())
                ->count(),

            'overdue' => $baseQuery()
                ->where('validation_status', 'pending')
                ->whereIn('coverage_mode', ['primary', 'secondary'])
                ->where('created_at', '<', Carbon::now()->subHours(4))
                ->count(),
        ];

        return response()->json($counts);
    }

    /**
     * Get financial summary for dashboard
     */
    public function getFinancialSummary()
    {
        $today = today();
        $thisMonth = now()->startOfMonth();

        $summary = [
            'pending_claims_total' => ProductOrServiceRequest::whereHas('user.patient_profile', function($q) {
                    $q->whereNotNull('hmo_id');
                })
                ->where('validation_status', 'pending')
                ->whereNotNull('coverage_mode')
                ->sum('claims_amount'),

            'approved_today_total' => ProductOrServiceRequest::whereHas('user.patient_profile', function($q) {
                    $q->whereNotNull('hmo_id');
                })
                ->where('validation_status', 'approved')
                ->whereDate('validated_at', $today)
                ->sum('claims_amount'),

            'rejected_today_total' => ProductOrServiceRequest::whereHas('user.patient_profile', function($q) {
                    $q->whereNotNull('hmo_id');
                })
                ->where('validation_status', 'rejected')
                ->whereDate('validated_at', $today)
                ->sum('claims_amount'),

            'monthly_claims_total' => ProductOrServiceRequest::whereHas('user.patient_profile', function($q) {
                    $q->whereNotNull('hmo_id');
                })
                ->where('validation_status', 'approved')
                ->where('validated_at', '>=', $thisMonth)
                ->sum('claims_amount'),

            'monthly_by_hmo' => ProductOrServiceRequest::whereHas('user.patient_profile', function($q) {
                    $q->whereNotNull('hmo_id');
                })
                ->where('validation_status', 'approved')
                ->where('validated_at', '>=', $thisMonth)
                ->join('users', 'product_or_service_requests.user_id', '=', 'users.id')
                ->join('patients', 'users.id', '=', 'patients.user_id')
                ->join('hmos', 'patients.hmo_id', '=', 'hmos.id')
                ->select('hmos.name as hmo_name', DB::raw('SUM(claims_amount) as total'))
                ->groupBy('hmos.id', 'hmos.name')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
        ];

        return response()->json($summary);
    }

    /**
     * Export claims report
     */
    public function exportClaimsReport(Request $request)
    {
        $query = ProductOrServiceRequest::with([
            'user.patient_profile.hmo',
            'service',
            'product',
            'validator'
        ])
        ->whereHas('user.patient_profile', function($q) {
            $q->whereNotNull('hmo_id');
        })
        ->where('validation_status', 'approved')
        ->whereNotNull('coverage_mode');

        // Apply date filters
        if ($request->filled('date_from')) {
            $query->whereDate('validated_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('validated_at', '<=', $request->date_to);
        }
        if ($request->filled('hmo_id')) {
            $query->whereHas('user.patient_profile', function($q) use ($request) {
                $q->where('hmo_id', $request->hmo_id);
            });
        }

        $claims = $query->orderBy('validated_at', 'DESC')->get();

        // Generate CSV
        $filename = 'hmo_claims_report_' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($claims) {
            $file = fopen('php://output', 'w');

            // Header row
            fputcsv($file, [
                'Request ID',
                'Date',
                'Patient Name',
                'File No',
                'HMO No',
                'HMO Name',
                'Item Type',
                'Item Name',
                'Qty',
                'Claims Amount',
                'Payable Amount',
                'Coverage Mode',
                'Auth Code',
                'Validated By',
                'Validated At'
            ]);

            foreach ($claims as $claim) {
                fputcsv($file, [
                    $claim->id,
                    $claim->created_at->format('Y-m-d'),
                    $claim->user ? userfullname($claim->user_id) : 'N/A',
                    $claim->user && $claim->user->patient_profile ? $claim->user->patient_profile->file_no : 'N/A',
                    $claim->user && $claim->user->patient_profile ? $claim->user->patient_profile->hmo_no : 'N/A',
                    $claim->user && $claim->user->patient_profile && $claim->user->patient_profile->hmo ? $claim->user->patient_profile->hmo->name : 'N/A',
                    $claim->product_id ? 'Product' : 'Service',
                    $claim->product_id ? ($claim->product ? $claim->product->product_name : 'N/A') : ($claim->service ? $claim->service->service_name : 'N/A'),
                    $claim->qty,
                    $claim->claims_amount,
                    $claim->payable_amount,
                    $claim->coverage_mode,
                    $claim->auth_code ?? '',
                    $claim->validator ? userfullname($claim->validated_by) : 'N/A',
                    $claim->validated_at ? Carbon::parse($claim->validated_at)->format('Y-m-d H:i') : ''
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Send notification to HMO executives group
     */
    private function sendHmoNotification($title, $message)
    {
        try {
            // Get or create HMO executives conversation
            $conversation = $this->getHmoExecutivesConversation();

            if ($conversation) {
                ChatMessage::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => Auth::id() ?? 1, // System user if no auth
                    'body' => "🏥 **{$title}**\n\n{$message}\n\n_" . now()->format('h:i A, M j') . "_",
                    'type' => 'text',
                ]);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the main operation
            \Log::warning('Failed to send HMO notification: ' . $e->getMessage());
        }
    }

    /**
     * Get or create HMO executives messenger group
     */
    private function getHmoExecutivesConversation()
    {
        return Cache::remember('hmo_executives_conversation', 3600, function() {
            $conversation = ChatConversation::where('title', 'HMO Executives')->first();

            if (!$conversation) {
                // Create the group
                $conversation = ChatConversation::create([
                    'title' => 'HMO Executives',
                    'is_group' => true,
                ]);
            }

            return $conversation;
        });
    }
}
