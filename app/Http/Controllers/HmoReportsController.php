<?php

namespace App\Http\Controllers;

use App\Models\Hmo;
use App\Models\HmoRemittance;
use App\Models\ProductOrServiceRequest;
use App\Models\PatientProfile;
use App\Models\patient;
use App\Models\serviceCategory;
use App\Models\ProductCategory;
use App\Models\service;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Barryvdh\DomPDF\Facade\Pdf;

class HmoReportsController extends Controller
{
    /**
     * Display the HMO Reports page.
     */
    public function index()
    {
        $hmos = Hmo::orderBy('name')->get();
        $serviceCategories = ServiceCategory::orderBy('category_name')->get();
        $productCategories = ProductCategory::orderBy('category_name')->get();
        return view('admin.hmo.reports', compact('hmos', 'serviceCategories', 'productCategories'));
    }

    /**
     * Get claims report data via AJAX.
     */
    public function getClaimsReport(Request $request)
    {
        $query = ProductOrServiceRequest::with([
            'user.patient_profile.hmo',
            'service.price',
            'product.price',
            'validator',
            'staff'
        ])
        ->whereHas('user.patient_profile', function($q) {
            $q->whereNotNull('hmo_id');
        })
        ->whereNotNull('coverage_mode')
        ->where('claims_amount', '>', 0);

        // Apply filters
        if ($request->filled('hmo_id')) {
            $query->whereHas('user.patient_profile', function($q) use ($request) {
                $q->where('hmo_id', $request->hmo_id);
            });
        }

        if ($request->filled('status')) {
            $query->where('validation_status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('service_type')) {
            if ($request->service_type === 'product') {
                $query->whereNotNull('product_id');
            } elseif ($request->service_type === 'service') {
                $query->whereNotNull('service_id');
            }
        }

        // Service category filter
        if ($request->filled('service_category_id')) {
            $query->whereHas('service', function($q) use ($request) {
                $q->where('category_id', $request->service_category_id);
            });
        }

        // Product category filter
        if ($request->filled('product_category_id')) {
            $query->whereHas('product', function($q) use ($request) {
                $q->where('category_id', $request->product_category_id);
            });
        }

        if ($request->filled('submission_status')) {
            if ($request->submission_status === 'submitted') {
                $query->whereNotNull('submitted_to_hmo_at');
            } elseif ($request->submission_status === 'not_submitted') {
                $query->whereNull('submitted_to_hmo_at');
            }
        }

        if ($request->filled('payment_status')) {
            if ($request->payment_status === 'paid') {
                $query->whereNotNull('hmo_remittance_id');
            } elseif ($request->payment_status === 'unpaid') {
                $query->whereNull('hmo_remittance_id');
            }
        }

        $claims = $query->orderBy('created_at', 'DESC')->get();

        return DataTables::of($claims)
            ->addIndexColumn()
            ->addColumn('patient_name', function ($claim) {
                return userfullname($claim->user_id) ?? 'N/A';
            })
            ->addColumn('file_no', function ($claim) {
                return $claim->user->patient_profile->file_no ?? 'N/A';
            })
            ->addColumn('hmo_no', function ($claim) {
                return $claim->user->patient_profile->hmo_no ?? 'N/A';
            })
            ->addColumn('hmo_name', function ($claim) {
                return $claim->user->patient_profile->hmo->name ?? 'N/A';
            })
            ->addColumn('service_date', function ($claim) {
                return $claim->created_at ? Carbon::parse($claim->created_at)->format('M d, Y') : 'N/A';
            })
            ->addColumn('item_type', function ($claim) {
                if ($claim->product_id) return 'Product';
                if ($claim->service_id) return 'Service';
                return 'N/A';
            })
            ->addColumn('item_name', function ($claim) {
                if ($claim->product_id && $claim->product) return $claim->product->product_name;
                if ($claim->service_id && $claim->service) return $claim->service->service_name;
                return 'N/A';
            })
            ->addColumn('auth_code_display', function ($claim) {
                return $claim->auth_code ?? '-';
            })
            ->addColumn('qty_display', function ($claim) {
                return $claim->qty ?? 1;
            })
            ->addColumn('unit_price', function ($claim) {
                if ($claim->product_id && $claim->product && $claim->product->price) {
                    return number_format($claim->product->price->current_sale_price, 2);
                }
                if ($claim->service_id && $claim->service && $claim->service->price) {
                    return number_format($claim->service->price->sale_price, 2);
                }
                return '0.00';
            })
            ->addColumn('claim_amount', function ($claim) {
                return number_format($claim->claims_amount, 2);
            })
            ->addColumn('status_badge', function ($claim) {
                $statusMap = [
                    'pending' => '<span class="badge badge-warning">Pending</span>',
                    'approved' => '<span class="badge badge-success">Approved</span>',
                    'rejected' => '<span class="badge badge-danger">Rejected</span>'
                ];
                return $statusMap[$claim->validation_status] ?? '<span class="badge badge-secondary">Unknown</span>';
            })
            ->addColumn('validated_by_name', function ($claim) {
                return $claim->validator ? userfullname($claim->validated_by) : '-';
            })
            ->addColumn('submission_badge', function ($claim) {
                if ($claim->submitted_to_hmo_at) {
                    return '<span class="badge badge-info">Submitted</span>';
                }
                return '<span class="badge badge-secondary">Not Submitted</span>';
            })
            ->addColumn('payment_badge', function ($claim) {
                if ($claim->hmo_remittance_id) {
                    return '<span class="badge badge-success">Paid</span>';
                }
                return '<span class="badge badge-warning">Unpaid</span>';
            })
            ->rawColumns(['status_badge', 'submission_badge', 'payment_badge'])
            ->make(true);
    }

    /**
     * Get outstanding claims summary by HMO.
     */
    public function getOutstandingReport(Request $request)
    {
        $hmos = Hmo::all();

        $outstandingData = [];

        foreach ($hmos as $hmo) {
            // Get all approved claims for this HMO
            $claims = ProductOrServiceRequest::whereHas('user.patient_profile', function($q) use ($hmo) {
                $q->where('hmo_id', $hmo->id);
            })
            ->whereNotNull('coverage_mode')
            ->where('claims_amount', '>', 0)
            ->where('validation_status', 'approved')
            ->get();

            // Get total remittances for this HMO
            $totalRemittances = HmoRemittance::where('hmo_id', $hmo->id)->sum('amount');

            $totalClaims = $claims->sum('claims_amount');
            $paidClaims = $claims->whereNotNull('hmo_remittance_id')->sum('claims_amount');
            $outstanding = $totalClaims - $paidClaims;

            // Aging buckets
            $now = Carbon::now();
            $aging = [
                'current' => 0,    // 0-30 days
                '31_60' => 0,      // 31-60 days
                '61_90' => 0,      // 61-90 days
                'over_90' => 0     // 90+ days
            ];

            foreach ($claims->whereNull('hmo_remittance_id') as $claim) {
                $days = $claim->created_at ? Carbon::parse($claim->created_at)->diffInDays($now) : 0;
                if ($days <= 30) {
                    $aging['current'] += $claim->claims_amount;
                } elseif ($days <= 60) {
                    $aging['31_60'] += $claim->claims_amount;
                } elseif ($days <= 90) {
                    $aging['61_90'] += $claim->claims_amount;
                } else {
                    $aging['over_90'] += $claim->claims_amount;
                }
            }

            if ($totalClaims > 0) {
                $outstandingData[] = [
                    'hmo_id' => $hmo->id,
                    'hmo_name' => $hmo->name,
                    'total_claims' => $totalClaims,
                    'paid' => $paidClaims,
                    'outstanding' => $outstanding,
                    'total_remittances' => $totalRemittances,
                    'aging_current' => $aging['current'],
                    'aging_31_60' => $aging['31_60'],
                    'aging_61_90' => $aging['61_90'],
                    'aging_over_90' => $aging['over_90'],
                ];
            }
        }

        return response()->json([
            'data' => $outstandingData,
            'summary' => [
                'total_claims' => collect($outstandingData)->sum('total_claims'),
                'total_paid' => collect($outstandingData)->sum('paid'),
                'total_outstanding' => collect($outstandingData)->sum('outstanding'),
            ]
        ]);
    }

    /**
     * Get patient claims history.
     */
    public function getPatientReport(Request $request, $patientId)
    {
        $patient = patient::with(['user', 'hmo'])->findOrFail($patientId);

        $claims = ProductOrServiceRequest::with([
            'service.price',
            'product.price',
            'validator'
        ])
        ->where('user_id', $patient->user_id)
        ->whereNotNull('coverage_mode')
        ->where('claims_amount', '>', 0)
        ->orderBy('created_at', 'DESC')
        ->get();

        return response()->json([
            'patient' => [
                'name' => userfullname($patient->user_id),
                'file_no' => $patient->file_no,
                'hmo_no' => $patient->hmo_no,
                'hmo_name' => $patient->hmo->name ?? 'N/A',
            ],
            'claims' => $claims->map(function($claim) {
                return [
                    'id' => $claim->id,
                    'date' => $claim->created_at ? Carbon::parse($claim->created_at)->format('M d, Y H:i') : 'N/A',
                    'type' => $claim->product_id ? 'Product' : 'Service',
                    'item' => $claim->product ? $claim->product->product_name : ($claim->service ? $claim->service->service_name : 'N/A'),
                    'qty' => $claim->qty,
                    'auth_code' => $claim->auth_code ?? '-',
                    'claim_amount' => number_format($claim->claims_amount, 2),
                    'patient_pays' => number_format($claim->payable_amount, 2),
                    'status' => $claim->validation_status,
                    'validated_by' => $claim->validator ? userfullname($claim->validated_by) : '-',
                ];
            }),
            'summary' => [
                'total_claims' => number_format($claims->where('validation_status', 'approved')->sum('claims_amount'), 2),
                'total_patient_paid' => number_format($claims->sum('payable_amount'), 2),
                'approved_count' => $claims->where('validation_status', 'approved')->count(),
                'rejected_count' => $claims->where('validation_status', 'rejected')->count(),
                'pending_count' => $claims->where('validation_status', 'pending')->count(),
            ]
        ]);
    }

    /**
     * Get monthly summary report.
     */
    public function getMonthlySummary(Request $request)
    {
        $year = $request->input('year', Carbon::now()->year);
        $month = $request->input('month', Carbon::now()->month);

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $claims = ProductOrServiceRequest::with(['user.patient_profile.hmo'])
            ->whereNotNull('coverage_mode')
            ->where('claims_amount', '>', 0)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        // Group by HMO
        $byHmo = $claims->groupBy(function($claim) {
            return $claim->user->patient_profile->hmo->name ?? 'Unknown';
        })->map(function($group, $hmoName) {
            return [
                'hmo_name' => $hmoName,
                'total_claims' => $group->sum('claims_amount'),
                'approved' => $group->where('validation_status', 'approved')->sum('claims_amount'),
                'rejected' => $group->where('validation_status', 'rejected')->sum('claims_amount'),
                'pending' => $group->where('validation_status', 'pending')->sum('claims_amount'),
                'count' => $group->count(),
            ];
        })->values();

        // Group by service type
        $byType = [
            'products' => $claims->whereNotNull('product_id')->sum('claims_amount'),
            'services' => $claims->whereNotNull('service_id')->sum('claims_amount'),
        ];

        // Get remittances for the month
        $remittances = HmoRemittance::whereBetween('payment_date', [$startDate, $endDate])->sum('amount');

        return response()->json([
            'period' => $startDate->format('F Y'),
            'summary' => [
                'total_claims' => number_format($claims->sum('claims_amount'), 2),
                'approved_total' => number_format($claims->where('validation_status', 'approved')->sum('claims_amount'), 2),
                'rejected_total' => number_format($claims->where('validation_status', 'rejected')->sum('claims_amount'), 2),
                'pending_total' => number_format($claims->where('validation_status', 'pending')->sum('claims_amount'), 2),
                'total_remittances' => number_format($remittances, 2),
                'claims_count' => $claims->count(),
                'approved_count' => $claims->where('validation_status', 'approved')->count(),
                'rejected_count' => $claims->where('validation_status', 'rejected')->count(),
            ],
            'by_hmo' => $byHmo,
            'by_type' => $byType,
        ]);
    }

    /**
     * Get remittances list.
     */
    public function getRemittances(Request $request)
    {
        $query = HmoRemittance::with(['hmo', 'creator']);

        if ($request->filled('hmo_id')) {
            $query->where('hmo_id', $request->hmo_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('payment_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('payment_date', '<=', $request->date_to);
        }

        $remittances = $query->orderBy('payment_date', 'DESC')->get();

        return DataTables::of($remittances)
            ->addIndexColumn()
            ->addColumn('hmo_name', function ($rem) {
                return $rem->hmo->name ?? 'N/A';
            })
            ->addColumn('amount_formatted', function ($rem) {
                return '₦' . number_format($rem->amount, 2);
            })
            ->addColumn('payment_date_formatted', function ($rem) {
                return $rem->payment_date ? Carbon::parse($rem->payment_date)->format('M d, Y') : 'N/A';
            })
            ->addColumn('period', function ($rem) {
                if ($rem->period_from && $rem->period_to) {
                    return Carbon::parse($rem->period_from)->format('M d') . ' - ' . Carbon::parse($rem->period_to)->format('M d, Y');
                }
                return '-';
            })
            ->addColumn('created_by_name', function ($rem) {
                return userfullname($rem->created_by) ?? 'System';
            })
            ->addColumn('actions', function ($rem) {
                return '<button class="btn btn-sm btn-info view-remittance-btn" data-id="' . $rem->id . '"><i class="fa fa-eye"></i></button>
                        <button class="btn btn-sm btn-warning edit-remittance-btn" data-id="' . $rem->id . '"><i class="fa fa-edit"></i></button>
                        <button class="btn btn-sm btn-danger delete-remittance-btn" data-id="' . $rem->id . '"><i class="fa fa-trash"></i></button>';
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    /**
     * Store a new remittance.
     */
    public function storeRemittance(Request $request)
    {
        $request->validate([
            'hmo_id' => 'required|exists:hmos,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'reference_number' => 'nullable|string|max:100',
            'payment_method' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'period_from' => 'nullable|date',
            'period_to' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $remittance = HmoRemittance::create([
                'hmo_id' => $request->hmo_id,
                'amount' => $request->amount,
                'payment_date' => $request->payment_date,
                'reference_number' => $request->reference_number,
                'payment_method' => $request->payment_method,
                'bank_name' => $request->bank_name,
                'period_from' => $request->period_from,
                'period_to' => $request->period_to,
                'notes' => $request->notes,
                'created_by' => Auth::id(),
            ]);

            // If claim_ids are provided, link them to this remittance
            if ($request->filled('claim_ids') && is_array($request->claim_ids)) {
                ProductOrServiceRequest::whereIn('id', $request->claim_ids)
                    ->whereHas('user.patient_profile', function($q) use ($request) {
                        $q->where('hmo_id', $request->hmo_id);
                    })
                    ->update(['hmo_remittance_id' => $remittance->id]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Remittance recorded successfully',
                'data' => $remittance
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to record remittance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a single remittance.
     */
    public function showRemittance($id)
    {
        $remittance = HmoRemittance::with(['hmo', 'creator', 'claims.user.patient_profile', 'claims.product', 'claims.service'])
            ->findOrFail($id);

        return response()->json([
            'remittance' => [
                'id' => $remittance->id,
                'hmo_id' => $remittance->hmo_id,
                'hmo_name' => $remittance->hmo->name ?? 'N/A',
                'amount' => number_format($remittance->amount, 2),
                'reference_number' => $remittance->reference_number,
                'payment_method' => $remittance->payment_method,
                'bank_name' => $remittance->bank_name,
                'payment_date' => $remittance->payment_date ? Carbon::parse($remittance->payment_date)->format('Y-m-d') : null,
                'period_from' => $remittance->period_from ? Carbon::parse($remittance->period_from)->format('Y-m-d') : null,
                'period_to' => $remittance->period_to ? Carbon::parse($remittance->period_to)->format('Y-m-d') : null,
                'notes' => $remittance->notes,
                'created_by' => userfullname($remittance->created_by),
                'created_at' => Carbon::parse($remittance->created_at)->format('M d, Y H:i'),
            ],
            'claims' => $remittance->claims->map(function($claim) {
                return [
                    'id' => $claim->id,
                    'patient' => userfullname($claim->user_id),
                    'item' => $claim->product ? $claim->product->product_name : ($claim->service ? $claim->service->service_name : 'N/A'),
                    'amount' => number_format($claim->claims_amount, 2),
                ];
            }),
            'claims_total' => number_format($remittance->claims->sum('claims_amount'), 2),
        ]);
    }

    /**
     * Update a remittance.
     */
    public function updateRemittance(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'reference_number' => 'nullable|string|max:100',
            'payment_method' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'period_from' => 'nullable|date',
            'period_to' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $remittance = HmoRemittance::findOrFail($id);

        $remittance->update([
            'amount' => $request->amount,
            'payment_date' => $request->payment_date,
            'reference_number' => $request->reference_number,
            'payment_method' => $request->payment_method,
            'bank_name' => $request->bank_name,
            'period_from' => $request->period_from,
            'period_to' => $request->period_to,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Remittance updated successfully',
            'data' => $remittance
        ]);
    }

    /**
     * Delete a remittance.
     */
    public function deleteRemittance($id)
    {
        $remittance = HmoRemittance::findOrFail($id);

        // Unlink claims from this remittance
        ProductOrServiceRequest::where('hmo_remittance_id', $id)
            ->update(['hmo_remittance_id' => null]);

        $remittance->delete();

        return response()->json([
            'success' => true,
            'message' => 'Remittance deleted successfully'
        ]);
    }

    /**
     * Mark claims as submitted to HMO.
     */
    public function markAsSubmitted(Request $request)
    {
        $request->validate([
            'claim_ids' => 'required|array',
            'claim_ids.*' => 'exists:product_or_service_requests,id',
            'batch_reference' => 'nullable|string|max:100',
        ]);

        $batchRef = $request->batch_reference ?? 'BATCH-' . Carbon::now()->format('YmdHis');

        ProductOrServiceRequest::whereIn('id', $request->claim_ids)
            ->update([
                'submitted_to_hmo_at' => Carbon::now(),
                'hmo_submission_batch' => $batchRef,
            ]);

        return response()->json([
            'success' => true,
            'message' => count($request->claim_ids) . ' claims marked as submitted',
            'batch_reference' => $batchRef,
        ]);
    }

    /**
     * Link claims to a remittance (mark as paid by HMO).
     */
    public function linkClaimsToRemittance(Request $request)
    {
        $request->validate([
            'remittance_id' => 'required|exists:hmo_remittances,id',
            'claim_ids' => 'required|array',
            'claim_ids.*' => 'exists:product_or_service_requests,id',
        ]);

        ProductOrServiceRequest::whereIn('id', $request->claim_ids)
            ->update(['hmo_remittance_id' => $request->remittance_id]);

        return response()->json([
            'success' => true,
            'message' => count($request->claim_ids) . ' claims linked to remittance',
        ]);
    }

    /**
     * Get print data for claims report.
     */
    public function getPrintData(Request $request)
    {
        $query = ProductOrServiceRequest::with([
            'user.patient_profile.hmo',
            'service.price',
            'product.price',
            'validator',
        ])
        ->whereNotNull('coverage_mode')
        ->where('claims_amount', '>', 0);

        // Apply same filters as getClaimsReport
        if ($request->filled('hmo_id')) {
            $query->whereHas('user.patient_profile', function($q) use ($request) {
                $q->where('hmo_id', $request->hmo_id);
            });
        }

        if ($request->filled('status')) {
            $query->where('validation_status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $claims = $query->orderBy('created_at', 'DESC')->get();

        // Get HMO name for title
        $hmoName = 'All HMOs';
        if ($request->filled('hmo_id')) {
            $hmo = Hmo::find($request->hmo_id);
            $hmoName = $hmo ? $hmo->name : 'Unknown HMO';
        }

        // Get app settings for branding
        $settings = appsettings();

        return response()->json([
            'hospital' => [
                'name' => $settings->site_name ?? config('app.name'),
                'address' => $settings->contact_address ?? '',
                'phones' => $settings->contact_phones ?? '',
                'emails' => $settings->contact_emails ?? '',
                'logo' => $settings->logo ?? null,
            ],
            'report' => [
                'title' => 'HMO Claims Submission Report',
                'hmo_name' => $hmoName,
                'period' => ($request->date_from ?? 'Start') . ' to ' . ($request->date_to ?? 'Present'),
                'generated_at' => Carbon::now()->format('M d, Y H:i'),
                'generated_by' => userfullname(Auth::id()),
            ],
            'claims' => $claims->map(function($claim, $index) {
                return [
                    'sn' => $index + 1,
                    'patient_name' => userfullname($claim->user_id),
                    'file_no' => $claim->user->patient_profile->file_no ?? 'N/A',
                    'hmo_no' => $claim->user->patient_profile->hmo_no ?? 'N/A',
                    'service_date' => $claim->created_at ? Carbon::parse($claim->created_at)->format('M d, Y') : 'N/A',
                    'item' => $claim->product ? $claim->product->product_name : ($claim->service ? $claim->service->service_name : 'N/A'),
                    'type' => $claim->product_id ? 'Product' : 'Service',
                    'qty' => $claim->qty ?? 1,
                    'auth_code' => $claim->auth_code ?? '-',
                    'claim_amount' => number_format($claim->claims_amount, 2),
                    'status' => ucfirst($claim->validation_status),
                    'validated_by' => $claim->validator ? userfullname($claim->validated_by) : '-',
                ];
            }),
            'summary' => [
                'total_claims' => number_format($claims->sum('claims_amount'), 2),
                'total_approved' => number_format($claims->where('validation_status', 'approved')->sum('claims_amount'), 2),
                'total_count' => $claims->count(),
                'approved_count' => $claims->where('validation_status', 'approved')->count(),
            ],
        ]);
    }

    /**
     * Export claims to Excel.
     */
    public function exportExcel(Request $request)
    {
        $query = ProductOrServiceRequest::with([
            'user.patient_profile.hmo',
            'service',
            'product',
            'validator',
        ])
        ->whereNotNull('coverage_mode')
        ->where('claims_amount', '>', 0);

        // Apply filters
        if ($request->filled('hmo_id')) {
            $query->whereHas('user.patient_profile', function($q) use ($request) {
                $q->where('hmo_id', $request->hmo_id);
            });
        }

        if ($request->filled('status')) {
            $query->where('validation_status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $claims = $query->orderBy('created_at', 'DESC')->get();

        $filename = 'hmo_claims_report_' . Carbon::now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($claims) {
            $file = fopen('php://output', 'w');

            // Header row
            fputcsv($file, [
                'S/N',
                'Patient Name',
                'File No',
                'HMO No',
                'HMO Name',
                'Service Date',
                'Item Type',
                'Item Name',
                'Qty',
                'Auth Code',
                'Claim Amount',
                'Patient Pays',
                'Status',
                'Validated By',
                'Validated At',
                'Submitted To HMO',
            ]);

            $sn = 1;
            foreach ($claims as $claim) {
                fputcsv($file, [
                    $sn++,
                    userfullname($claim->user_id),
                    $claim->user->patient_profile->file_no ?? 'N/A',
                    $claim->user->patient_profile->hmo_no ?? 'N/A',
                    $claim->user->patient_profile->hmo->name ?? 'N/A',
                    $claim->created_at ? Carbon::parse($claim->created_at)->format('Y-m-d') : '',
                    $claim->product_id ? 'Product' : 'Service',
                    $claim->product ? $claim->product->product_name : ($claim->service ? $claim->service->service_name : 'N/A'),
                    $claim->qty ?? 1,
                    $claim->auth_code ?? '',
                    $claim->claims_amount,
                    $claim->payable_amount,
                    ucfirst($claim->validation_status),
                    $claim->validator ? userfullname($claim->validated_by) : '',
                    $claim->validated_at ? Carbon::parse($claim->validated_at)->format('Y-m-d H:i') : '',
                    $claim->submitted_to_hmo_at ? Carbon::parse($claim->submitted_to_hmo_at)->format('Y-m-d') : '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Search patients for report.
     */
    public function searchPatients(Request $request)
    {
        $search = $request->input('q', '');

        $patients = patient::with(['user', 'hmo'])
            ->whereHas('user', function($q) use ($search) {
                $q->where('fname', 'like', "%$search%")
                  ->orWhere('lname', 'like', "%$search%");
            })
            ->orWhere('file_no', 'like', "%$search%")
            ->orWhere('hmo_no', 'like', "%$search%")
            ->limit(20)
            ->get();

        return response()->json($patients->map(function($p) {
            return [
                'id' => $p->id,
                'text' => userfullname($p->user_id) . ' (' . ($p->file_no ?? 'No File#') . ')',
                'file_no' => $p->file_no,
                'hmo_name' => $p->hmo->name ?? 'N/A',
            ];
        }));
    }

    /**
     * Get service utilization report.
     */
    public function getUtilizationReport(Request $request)
    {
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->input('date_to', Carbon::now()->format('Y-m-d'));

        // Base query for HMO claims
        $baseQuery = ProductOrServiceRequest::whereNotNull('coverage_mode')
            ->where('claims_amount', '>', 0)
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59']);

        // Top 10 Services by revenue
        $topServices = ProductOrServiceRequest::with(['service.category'])
            ->whereNotNull('coverage_mode')
            ->where('claims_amount', '>', 0)
            ->whereNotNull('service_id')
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->select('service_id', DB::raw('SUM(claims_amount) as total_revenue'), DB::raw('COUNT(*) as count'))
            ->groupBy('service_id')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get()
            ->map(function($item) {
                $service = service::with('category')->find($item->service_id);
                return [
                    'name' => $service ? $service->service_name : 'Unknown',
                    'category' => $service && $service->category ? $service->category->category_name : 'Uncategorized',
                    'revenue' => $item->total_revenue,
                    'count' => $item->count,
                ];
            });

        // Top 10 Products by revenue
        $topProducts = ProductOrServiceRequest::with(['product.category'])
            ->whereNotNull('coverage_mode')
            ->where('claims_amount', '>', 0)
            ->whereNotNull('product_id')
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->select('product_id', DB::raw('SUM(claims_amount) as total_revenue'), DB::raw('COUNT(*) as count'))
            ->groupBy('product_id')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get()
            ->map(function($item) {
                $product = Product::with('category')->find($item->product_id);
                return [
                    'name' => $product ? $product->product_name : 'Unknown',
                    'category' => $product && $product->category ? $product->category->category_name : 'Uncategorized',
                    'revenue' => $item->total_revenue,
                    'count' => $item->count,
                ];
            });

        // Service Category breakdown
        $serviceByCategory = ProductOrServiceRequest::whereNotNull('coverage_mode')
            ->where('product_or_service_requests.claims_amount', '>', 0)
            ->whereNotNull('service_id')
            ->whereBetween('product_or_service_requests.created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->join('services', 'product_or_service_requests.service_id', '=', 'services.id')
            ->join('service_categories', 'services.category_id', '=', 'service_categories.id')
            ->select('service_categories.category_name', DB::raw('SUM(product_or_service_requests.claims_amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('service_categories.id', 'service_categories.category_name')
            ->orderByDesc('total')
            ->get();

        // Product Category breakdown
        $productByCategory = ProductOrServiceRequest::whereNotNull('coverage_mode')
            ->where('product_or_service_requests.claims_amount', '>', 0)
            ->whereNotNull('product_id')
            ->whereBetween('product_or_service_requests.created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->join('products', 'product_or_service_requests.product_id', '=', 'products.id')
            ->join('product_categories', 'products.category_id', '=', 'product_categories.id')
            ->select('product_categories.category_name', DB::raw('SUM(product_or_service_requests.claims_amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('product_categories.id', 'product_categories.category_name')
            ->orderByDesc('total')
            ->get();

        // Summary totals
        $totalServices = (clone $baseQuery)->whereNotNull('service_id')->sum('claims_amount');
        $totalProducts = (clone $baseQuery)->whereNotNull('product_id')->sum('claims_amount');
        $totalClaims = $totalServices + $totalProducts;
        $totalCount = (clone $baseQuery)->count();

        return response()->json([
            'period' => [
                'from' => Carbon::parse($dateFrom)->format('M d, Y'),
                'to' => Carbon::parse($dateTo)->format('M d, Y'),
            ],
            'summary' => [
                'total_claims' => $totalClaims,
                'total_services' => $totalServices,
                'total_products' => $totalProducts,
                'total_count' => $totalCount,
            ],
            'top_services' => $topServices,
            'top_products' => $topProducts,
            'service_categories' => $serviceByCategory,
            'product_categories' => $productByCategory,
        ]);
    }

    /**
     * Get auth code tracker data.
     */
    public function getAuthCodeReport(Request $request)
    {
        $query = ProductOrServiceRequest::with([
            'user.patient_profile.hmo',
            'service',
            'product',
        ])
        ->whereNotNull('coverage_mode')
        ->where('claims_amount', '>', 0);

        // Filter by HMO
        if ($request->filled('hmo_id')) {
            $query->whereHas('user.patient_profile', function($q) use ($request) {
                $q->where('hmo_id', $request->hmo_id);
            });
        }

        // Filter by auth code status
        if ($request->filled('auth_status')) {
            if ($request->auth_status === 'with_code') {
                $query->whereNotNull('auth_code')->where('auth_code', '!=', '');
            } elseif ($request->auth_status === 'without_code') {
                $query->where(function($q) {
                    $q->whereNull('auth_code')->orWhere('auth_code', '');
                });
            }
        }

        // Filter by date
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $claims = $query->orderBy('created_at', 'DESC')->get();

        return DataTables::of($claims)
            ->addIndexColumn()
            ->addColumn('patient_name', function ($claim) {
                return userfullname($claim->user_id) ?? 'N/A';
            })
            ->addColumn('hmo_no', function ($claim) {
                return $claim->user->patient_profile->hmo_no ?? 'N/A';
            })
            ->addColumn('hmo_name', function ($claim) {
                return $claim->user->patient_profile->hmo->name ?? 'N/A';
            })
            ->addColumn('service_date', function ($claim) {
                return $claim->created_at ? Carbon::parse($claim->created_at)->format('M d, Y H:i') : 'N/A';
            })
            ->addColumn('item_name', function ($claim) {
                if ($claim->product_id && $claim->product) return $claim->product->product_name;
                if ($claim->service_id && $claim->service) return $claim->service->service_name;
                return 'N/A';
            })
            ->addColumn('auth_code_display', function ($claim) {
                if ($claim->auth_code) {
                    return '<span class="badge badge-success">' . $claim->auth_code . '</span>';
                }
                return '<span class="badge badge-secondary">No Code</span>';
            })
            ->addColumn('claim_amount', function ($claim) {
                return '₦' . number_format($claim->claims_amount, 2);
            })
            ->addColumn('status_badge', function ($claim) {
                $statusMap = [
                    'pending' => '<span class="badge badge-warning">Pending</span>',
                    'approved' => '<span class="badge badge-success">Approved</span>',
                    'rejected' => '<span class="badge badge-danger">Rejected</span>'
                ];
                return $statusMap[$claim->validation_status] ?? '<span class="badge badge-secondary">Unknown</span>';
            })
            ->rawColumns(['auth_code_display', 'status_badge'])
            ->make(true);
    }

    /**
     * Export claims to PDF.
     */
    public function exportPdf(Request $request)
    {
        $query = ProductOrServiceRequest::with([
            'user.patient_profile.hmo',
            'service',
            'product',
            'validator',
        ])
        ->whereNotNull('coverage_mode')
        ->where('claims_amount', '>', 0);

        // Apply filters
        if ($request->filled('hmo_id')) {
            $query->whereHas('user.patient_profile', function($q) use ($request) {
                $q->where('hmo_id', $request->hmo_id);
            });
        }

        if ($request->filled('status')) {
            $query->where('validation_status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $claims = $query->orderBy('created_at', 'DESC')->get();

        // Get HMO name for title
        $hmoName = 'All HMOs';
        if ($request->filled('hmo_id')) {
            $hmo = Hmo::find($request->hmo_id);
            $hmoName = $hmo ? $hmo->name : 'Unknown HMO';
        }

        $settings = appsettings();

        $data = [
            'hospital' => [
                'name' => $settings->site_name ?? config('app.name'),
                'address' => $settings->contact_address ?? '',
                'phones' => $settings->contact_phones ?? '',
                'emails' => $settings->contact_emails ?? '',
                'logo' => $settings->logo ?? null,
                'color' => $settings->hos_color ?? '#0066cc',
            ],
            'report' => [
                'title' => 'HMO Claims Report',
                'hmo_name' => $hmoName,
                'period' => ($request->date_from ?? 'Start') . ' to ' . ($request->date_to ?? 'Present'),
                'generated_at' => Carbon::now()->format('M d, Y H:i'),
                'generated_by' => userfullname(Auth::id()),
            ],
            'claims' => $claims,
            'summary' => [
                'total_claims' => $claims->sum('claims_amount'),
                'total_approved' => $claims->where('validation_status', 'approved')->sum('claims_amount'),
                'total_count' => $claims->count(),
                'approved_count' => $claims->where('validation_status', 'approved')->count(),
            ],
        ];

        $pdf = Pdf::loadView('admin.hmo.reports_pdf', $data);
        $pdf->setPaper('a4', 'landscape');

        return $pdf->download('hmo_claims_report_' . Carbon::now()->format('Y-m-d_His') . '.pdf');
    }

    /**
     * Get patient claims for print.
     */
    public function getPatientPrintData(Request $request, $patientId)
    {
        $patient = patient::with(['user', 'hmo'])->findOrFail($patientId);

        $claims = ProductOrServiceRequest::with([
            'service.price',
            'product.price',
            'validator'
        ])
        ->where('user_id', $patient->user_id)
        ->whereNotNull('coverage_mode')
        ->where('claims_amount', '>', 0)
        ->orderBy('created_at', 'DESC')
        ->get();

        $settings = appsettings();

        return response()->json([
            'hospital' => [
                'name' => $settings->site_name ?? config('app.name'),
                'address' => $settings->contact_address ?? '',
                'phones' => $settings->contact_phones ?? '',
                'emails' => $settings->contact_emails ?? '',
                'logo' => $settings->logo ?? null,
                'color' => $settings->hos_color ?? '#0066cc',
            ],
            'patient' => [
                'name' => userfullname($patient->user_id),
                'file_no' => $patient->file_no,
                'hmo_no' => $patient->hmo_no,
                'hmo_name' => $patient->hmo->name ?? 'N/A',
            ],
            'claims' => $claims->map(function($claim, $index) {
                return [
                    'sn' => $index + 1,
                    'date' => $claim->created_at ? Carbon::parse($claim->created_at)->format('M d, Y') : 'N/A',
                    'type' => $claim->product_id ? 'Product' : 'Service',
                    'item' => $claim->product ? $claim->product->product_name : ($claim->service ? $claim->service->service_name : 'N/A'),
                    'qty' => $claim->qty ?? 1,
                    'auth_code' => $claim->auth_code ?? '-',
                    'claim_amount' => number_format($claim->claims_amount, 2),
                    'patient_pays' => number_format($claim->payable_amount, 2),
                    'status' => ucfirst($claim->validation_status),
                ];
            }),
            'summary' => [
                'total_claims' => number_format($claims->where('validation_status', 'approved')->sum('claims_amount'), 2),
                'total_patient_paid' => number_format($claims->sum('payable_amount'), 2),
                'total_count' => $claims->count(),
            ],
            'generated_at' => Carbon::now()->format('M d, Y H:i'),
            'generated_by' => userfullname(Auth::id()),
        ]);
    }
}
