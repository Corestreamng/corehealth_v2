<?php

namespace App\Http\Controllers;

use App\Models\ProductOrServiceRequest;
use App\Models\Hmo;
use App\Models\patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;

class HmoWorkbenchController extends Controller
{
    /**
     * Display the HMO workbench page.
     */
    public function index()
    {
        $hmos = Hmo::where('status', 1)->orderBy('name', 'ASC')->get();
        return view('admin.hmo.workbench', compact('hmos'));
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
            'validator'
        ])
        ->whereHas('user.patient_profile', function($q) {
            $q->whereNotNull('hmo_id');
        })
        ->whereNull('payment_id') // Only unpaid requests
        ->whereNotNull('coverage_mode'); // Only HMO requests

        // Tab filters
        if ($request->filled('tab')) {
            switch ($request->tab) {
                case 'pending':
                    $query->where('validation_status', 'pending')
                          ->whereIn('coverage_mode', ['primary', 'secondary']);
                    break;
                case 'express':
                    $query->where('coverage_mode', 'express');
                    break;
                case 'approved':
                    $query->where('validation_status', 'approved');
                    break;
                case 'rejected':
                    $query->where('validation_status', 'rejected');
                    break;
                // 'all' - no filter
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
                      $q2->where('first_name', 'LIKE', "%{$search}%")
                         ->orWhere('last_name', 'LIKE', "%{$search}%")
                         ->orWhere('file_no', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('service', function($q2) use ($search) {
                      $q2->where('service_name', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('product', function($q2) use ($search) {
                      $q2->where('product_name', 'LIKE', "%{$search}%");
                  });
            });
        }

        $requests = $query->orderBy('created_at', 'DESC')->get();

        return DataTables::of($requests)
            ->addIndexColumn()
            ->addColumn('request_date', function ($req) {
                return $req->created_at ? $req->created_at->format('Y-m-d H:i') : '';
            })
            ->addColumn('patient_info', function ($req) {
                if ($req->user && $req->user->patient_profile) {
                    $name = userfullname($req->user_id);
                    $fileNo = $req->user->patient_profile->file_no ?? 'N/A';
                    $hmoNo = $req->user->patient_profile->hmo_no ?? '';
                    return "$name<br><small class=\"text-muted\">File: $fileNo</small>" .
                           ($hmoNo ? "<br><small class=\"text-info\">HMO#: $hmoNo</small>" : '');
                }
                return 'N/A';
            })
            ->addColumn('hmo_name', function ($req) {
                if ($req->user && $req->user->patient_profile && $req->user->patient_profile->hmo) {
                    return $req->user->patient_profile->hmo->name;
                }
                return 'N/A';
            })
            ->addColumn('item_name', function ($req) {
                if ($req->product_id) {
                    return $req->product ? $req->product->product_name : 'N/A';
                } elseif ($req->service_id) {
                    return $req->service ? $req->service->service_name : 'N/A';
                }
                return 'N/A';
            })
            ->addColumn('item_type', function ($req) {
                if ($req->product_id) {
                    return '<span class="badge badge-success">Product</span>';
                } elseif ($req->service_id) {
                    return '<span class="badge badge-info">Service</span>';
                }
                return '<span class="badge badge-secondary">N/A</span>';
            })
            ->addColumn('original_price', function ($req) {
                if ($req->product_id && $req->product && $req->product->price) {
                    return '₦' . number_format($req->product->price->current_sale_price, 2);
                } elseif ($req->service_id && $req->service && $req->service->price) {
                    return '₦' . number_format($req->service->price->sale_price, 2);
                }
                return 'N/A';
            })
            ->addColumn('coverage_badge', function ($req) {
                $badgeColor = $req->coverage_mode === 'express' ? 'success' :
                             ($req->coverage_mode === 'primary' ? 'warning' : 'danger');
                return '<span class="badge badge-' . $badgeColor . '">' . strtoupper($req->coverage_mode) . '</span>';
            })
            ->addColumn('claims_amount_formatted', function ($req) {
                return '₦' . number_format($req->claims_amount, 2);
            })
            ->addColumn('payable_amount_formatted', function ($req) {
                return '₦' . number_format($req->payable_amount, 2);
            })
            ->addColumn('status_badge', function ($req) {
                $status = $req->validation_status;
                $badgeMap = [
                    'pending' => 'warning',
                    'approved' => 'success',
                    'rejected' => 'danger'
                ];
                $color = $badgeMap[$status] ?? 'secondary';
                return '<span class="badge badge-' . $color . '">' . strtoupper($status) . '</span>';
            })
            ->addColumn('validated_info', function ($req) {
                if ($req->validated_at && $req->validator) {
                    $validatorName = userfullname($req->validated_by);
                    $date = $req->validated_at->format('Y-m-d H:i');
                    return "$validatorName<br><small class=\"text-muted\">$date</small>";
                }
                return '<span class="text-muted">Not yet validated</span>';
            })
            ->addColumn('actions', function ($req) {
                $buttons = '<button type="button" class="btn btn-sm btn-info view-details-btn" data-id="' . $req->id . '" title="View Details">
                    <i class="fa fa-eye"></i>
                </button>';

                if ($req->validation_status === 'pending' && in_array($req->coverage_mode, ['primary', 'secondary'])) {
                    $buttons .= ' <button type="button" class="btn btn-sm btn-success approve-btn" data-id="' . $req->id . '" data-mode="' . $req->coverage_mode . '" title="Approve">
                        <i class="fa fa-check"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger reject-btn" data-id="' . $req->id . '" title="Reject">
                        <i class="fa fa-times"></i>
                    </button>';
                }

                return $buttons;
            })
            ->rawColumns(['patient_info', 'item_type', 'coverage_badge', 'status_badge', 'validated_info', 'actions'])
            ->make(true);
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
            'validator'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $request->id,
                'patient_name' => $request->user ? userfullname($request->user_id) : 'N/A',
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
                'validated_at' => $request->validated_at ? $request->validated_at->format('Y-m-d H:i:s') : null,
                'created_at' => $request->created_at ? $request->created_at->format('Y-m-d H:i:s') : null,
            ]
        ]);
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

            // Check if already approved or rejected
            if (in_array($hmoRequest->validation_status, ['approved', 'rejected'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request already ' . $hmoRequest->validation_status
                ], 422);
            }

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
            'validation_notes' => 'required|string|max:500',
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

            // Check if already approved or rejected
            if (in_array($hmoRequest->validation_status, ['approved', 'rejected'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request already ' . $hmoRequest->validation_status
                ], 422);
            }

            $hmoRequest->update([
                'validation_status' => 'rejected',
                'validated_by' => Auth::id(),
                'validated_at' => now(),
                'validation_notes' => $request->validation_notes,
            ]);

            DB::commit();

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
     * Get queue counts for dashboard cards.
     */
    public function getQueueCounts()
    {
        $counts = [
            'pending' => ProductOrServiceRequest::whereHas('user.patient_profile', function($q) {
                    $q->whereNotNull('hmo_id');
                })
                ->whereNull('payment_id')
                ->where('validation_status', 'pending')
                ->whereIn('coverage_mode', ['primary', 'secondary'])
                ->count(),

            'express' => ProductOrServiceRequest::whereHas('user.patient_profile', function($q) {
                    $q->whereNotNull('hmo_id');
                })
                ->whereNull('payment_id')
                ->where('coverage_mode', 'express')
                ->count(),

            'approved_today' => ProductOrServiceRequest::whereHas('user.patient_profile', function($q) {
                    $q->whereNotNull('hmo_id');
                })
                ->whereNull('payment_id')
                ->where('validation_status', 'approved')
                ->whereDate('validated_at', today())
                ->count(),

            'rejected_today' => ProductOrServiceRequest::whereHas('user.patient_profile', function($q) {
                    $q->whereNotNull('hmo_id');
                })
                ->whereNull('payment_id')
                ->where('validation_status', 'rejected')
                ->whereDate('validated_at', today())
                ->count(),
        ];

        return response()->json($counts);
    }
}
