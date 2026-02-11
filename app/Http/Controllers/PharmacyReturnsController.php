<?php

namespace App\Http\Controllers;

use App\Models\PharmacyReturn;
use App\Models\ProductRequest;
use App\Models\ProductOrServiceRequest;
use App\Models\patient;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class PharmacyReturnsController extends Controller
{
    /**
     * Display returns list page or return stats for AJAX.
     */
    public function index(Request $request)
    {
        if ($request->ajax() && $request->has('stats_only')) {
            $total = PharmacyReturn::count();
            $pending = PharmacyReturn::where('status', 'pending')->count();
            $approved = PharmacyReturn::where('status', 'approved')->count();
            $rejected = PharmacyReturn::where('status', 'rejected')->count();
            $completed = PharmacyReturn::where('status', 'completed')->count();
            $totalValue = PharmacyReturn::whereIn('status', ['approved', 'completed'])->sum('refund_amount');

            return response()->json([
                'stats' => [
                    'total' => $total,
                    'pending' => $pending,
                    'approved' => $approved,
                    'rejected' => $rejected,
                    'completed' => $completed,
                    'total_value' => $totalValue,
                ]
            ]);
        }

        return view('admin.pharmacy.returns.index');
    }

    /**
     * Show form to create new return.
     */
    public function create()
    {
        return view('admin.pharmacy.returns.create');
    }

    /**
     * Search for dispensed items that can be returned.
     */
    public function searchDispensedItems(Request $request)
    {
        $query = $request->get('term');
        $patientId = $request->get('patient_id');

        $items = ProductRequest::with(['product', 'patient.user', 'productOrServiceRequest', 'dispensedFromStore'])
            ->where('status', 3) // Only dispensed items
            ->whereNotNull('dispense_date')
            ->when($patientId, function($q) use ($patientId) {
                return $q->where('patient_id', $patientId);
            })
            ->when($query, function($q) use ($query) {
                return $q->where(function($outer) use ($query) {
                    $outer->whereHas('product', function($q2) use ($query) {
                        $q2->where('product_name', 'like', "%{$query}%");
                    })
                    ->orWhereHas('patient.user', function($q2) use ($query) {
                        $q2->where('firstname', 'like', "%{$query}%")
                           ->orWhere('surname', 'like', "%{$query}%");
                    })
                    ->orWhereHas('patient', function($q2) use ($query) {
                        $q2->where('file_no', 'like', "%{$query}%");
                    });
                });
            })
            ->orderBy('dispense_date', 'desc')
            ->limit(20)
            ->get();

        return response()->json($items->map(function($item) {
            $billReq = $item->productOrServiceRequest;
            return [
                'id' => $item->id,
                'product_name' => $item->product->product_name ?? 'Unknown',
                'patient_name' => $item->patient->user->name ?? 'Unknown',
                'patient_id' => $item->patient_id,
                'file_number' => $item->patient->file_no ?? '',
                'qty' => $item->qty,
                'dispensed_date' => $item->dispense_date ? (is_string($item->dispense_date) ? $item->dispense_date : $item->dispense_date->format('M d, Y h:i A')) : '',
                'store' => $item->dispensedFromStore->store_name ?? 'Unknown Store',
                'amount' => $billReq ? $billReq->payable_amount + $billReq->claims_amount : 0,
                'payable_amount' => $billReq ? $billReq->payable_amount : 0,
                'claims_amount' => $billReq ? $billReq->claims_amount : 0,
                'bill_request_id' => $billReq ? $billReq->id : null,
            ];
        }));
    }

    /**
     * Store a new return.
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_request_id' => 'required|exists:product_requests,id',
            'qty_returned' => 'required|numeric|min:0.01',
            'return_condition' => 'required|in:good,damaged,expired,wrong_item',
            'return_reason' => 'required|string|min:10',
        ]);

        // Prevent duplicate returns for the same dispensed item
        $existingReturn = PharmacyReturn::where('product_request_id', $request->product_request_id)
            ->whereIn('status', ['pending', 'approved', 'completed'])
            ->first();
        if ($existingReturn) {
            return response()->json([
                'success' => false,
                'message' => 'A return already exists for this dispensed item (Return #' . $existingReturn->id . ')'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $productRequest = ProductRequest::with(['productOrServiceRequest', 'patient', 'product'])
                ->findOrFail($request->product_request_id);

            // Validate qty
            if ($request->qty_returned > $productRequest->qty) {
                return response()->json([
                    'success' => false,
                    'message' => 'Return quantity cannot exceed dispensed quantity'
                ], 422);
            }

            $billRequest = $productRequest->productOrServiceRequest;
            if (!$billRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Billing record not found for this item'
                ], 404);
            }

            // Calculate refund amount (proportional to qty)
            $totalAmount = $billRequest->payable_amount + $billRequest->claims_amount;
            $unitAmount = $productRequest->qty > 0 ? $totalAmount / $productRequest->qty : 0;
            $refundAmount = $unitAmount * $request->qty_returned;

            // Split refund for HMO patients
            $refundToPatient = $billRequest->payable_amount > 0
                ? ($billRequest->payable_amount / $totalAmount) * $refundAmount
                : 0;
            $refundToHmo = $billRequest->claims_amount > 0
                ? ($billRequest->claims_amount / $totalAmount) * $refundAmount
                : 0;

            // Determine if item can be restocked
            // 'good' and 'wrong_item' are restockable conditions
            $restock = in_array($request->return_condition, ['good', 'wrong_item']);

            // Create return record
            $return = PharmacyReturn::create([
                'product_request_id' => $productRequest->id,
                'product_or_service_request_id' => $billRequest->id,
                'patient_id' => $productRequest->patient_id,
                'product_id' => $productRequest->product_id,
                'store_id' => $productRequest->dispensed_from_store_id,
                'batch_id' => $productRequest->dispensed_from_batch_id,
                'qty_returned' => $request->qty_returned,
                'original_qty' => $productRequest->qty,
                'refund_amount' => $refundAmount,
                'original_amount' => $totalAmount,
                'return_condition' => $request->return_condition,
                'return_reason' => $request->return_reason,
                'restock' => $restock,
                'refund_to_patient' => $refundToPatient,
                'refund_to_hmo' => $refundToHmo,
                'status' => 'pending',
                'created_by' => Auth::id(),
            ]);

            // Update ProductRequest status to returned (4)
            $productRequest->update([
                'status' => 4,
                'returned_by' => Auth::id(),
                'returned_date' => now(),
                'returned_qty' => $request->qty_returned,
                'refund_amount' => $refundAmount,
                'return_reason' => $request->return_reason,
                'return_condition' => $request->return_condition,
            ]);

            DB::commit();

            Log::info('PharmacyReturn created', [
                'return_id' => $return->id,
                'product_request_id' => $productRequest->id,
                'qty_returned' => $request->qty_returned,
                'refund_amount' => $refundAmount,
                'created_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Return request created successfully. Awaiting approval.',
                'return' => $return,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create pharmacy return', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create return: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show return details.
     */
    public function show($id, Request $request)
    {
        $return = PharmacyReturn::with([
            'productRequest',
            'billRequest',
            'patient.user',
            'product',
            'store',
            'batch',
            'creator',
            'approver',
            'journalEntry.lines.account'
        ])->findOrFail($id);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'return' => [
                    'id' => $return->id,
                    'patient_name' => $return->patient->user->name ?? 'N/A',
                    'file_no' => $return->patient->file_no ?? '',
                    'product_name' => $return->product->product_name ?? 'N/A',
                    'product_id' => $return->product_id,
                    'store_name' => $return->store->store_name ?? 'N/A',
                    'store_id' => $return->store_id,
                    'batch_number' => $return->batch->batch_number ?? 'N/A',
                    'batch_id' => $return->batch_id,
                    'qty_returned' => $return->qty_returned,
                    'original_qty' => $return->original_qty,
                    'refund_amount' => $return->refund_amount,
                    'original_amount' => $return->original_amount,
                    'refund_to_patient' => $return->refund_to_patient,
                    'refund_to_hmo' => $return->refund_to_hmo,
                    'return_condition' => $return->return_condition,
                    'return_reason' => $return->return_reason,
                    'restock' => $return->restock,
                    'status' => $return->status,
                    'created_by' => $return->creator->name ?? 'N/A',
                    'approved_by' => $return->approver->name ?? null,
                    'approval_notes' => $return->approval_notes,
                    'created_at' => $return->created_at->format('M d, Y h:i A'),
                    'approved_at' => $return->approved_at ? $return->approved_at->format('M d, Y h:i A') : null,
                    'journal_entry' => $return->journalEntry ? [
                        'id' => $return->journalEntry->id,
                        'reference' => $return->journalEntry->reference ?? 'JE-' . $return->journalEntry->id,
                        'description' => $return->journalEntry->description,
                        'status' => $return->journalEntry->status ?? null,
                        'lines' => $return->journalEntry->lines->map(function($line) {
                            return [
                                'account_name' => $line->account->name ?? 'N/A',
                                'account_code' => $line->account->code ?? '',
                                'debit' => $line->debit_amount,
                                'credit' => $line->credit_amount,
                                'description' => $line->description,
                            ];
                        })
                    ] : null,
                ]
            ]);
        }

        return view('admin.pharmacy.returns.show', compact('return'));
    }

    /**
     * Approve a return (unit head/manager).
     */
    public function approve($id, Request $request)
    {
        $request->validate([
            'approval_notes' => 'nullable|string',
        ]);

        try {
            $return = PharmacyReturn::findOrFail($id);

            if ($return->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending returns can be approved'
                ], 422);
            }

            $return->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'approval_notes' => $request->approval_notes,
            ]);

            // Observer will create journal entry automatically
            // Auto-restock for good condition items (WF-2)
            // Uses StockBatch::addStock() which records a transaction and triggers
            // StockBatchObserver → syncStoreStock() → auto-syncs store_stocks + global stocks
            $restocked = false;
            if ($return->restock && $return->batch_id) {
                $batch = $return->batch;
                if ($batch) {
                    $batch->addStock(
                        $return->qty_returned,
                        'return',
                        PharmacyReturn::class,
                        $return->id,
                        "Return #{$return->id} - Restocking {$return->qty_returned} units"
                    );
                    // StockBatchObserver auto-syncs store_stocks and global stocks
                    $return->update(['status' => 'completed']);
                    $restocked = true;
                }
            }

            // Credit patient wallet with refund amount
            // The JE was already created by PharmacyReturnObserver (CR: Customer Deposits 2200).
            // We create an ACC_DEPOSIT payment record for statement visibility only,
            // linking it to the return's JE so PaymentObserver skips duplicate JE creation.
            $refundToPatient = $return->refund_to_patient > 0
                ? $return->refund_to_patient
                : $return->refund_amount;

            // Reload to get JE created by observer
            $return->refresh();
            $return->load(['journalEntry', 'product']);

            if ($refundToPatient > 0) {
                $productName = $return->product->product_name ?? 'Unknown';

                // 1. Update wallet balance
                $patientAccount = \App\Models\PatientAccount::firstOrCreate(
                    ['patient_id' => $return->patient_id],
                    ['balance' => 0]
                );
                $patientAccount->increment('balance', $refundToPatient);

                // 2. Create ACC_DEPOSIT payment for patient statement visibility
                //    Link to existing JE so PaymentObserver does NOT create a duplicate
                $refNo = generate_invoice_no();
                \App\Models\payment::create([
                    'patient_id' => $return->patient_id,
                    'user_id' => Auth::id(),
                    'total' => $refundToPatient,
                    'reference_no' => $refNo,
                    'payment_type' => 'ACC_DEPOSIT',
                    'payment_method' => 'REFUND',
                    'journal_entry_id' => $return->journal_entry_id, // Link to return JE — prevents duplicate
                ]);

                Log::info('PharmacyReturn: Patient wallet credited via ACC_DEPOSIT', [
                    'return_id' => $return->id,
                    'patient_id' => $return->patient_id,
                    'amount_credited' => $refundToPatient,
                    'payment_ref' => $refNo,
                    'linked_je_id' => $return->journal_entry_id,
                ]);
            }

            Log::info('PharmacyReturn approved', [
                'return_id' => $return->id,
                'approved_by' => Auth::id(),
                'auto_restocked' => $restocked,
            ]);

            $message = 'Return approved successfully.';
            if ($restocked) {
                $message .= ' Stock restocked automatically.';
            }
            if ($refundToPatient > 0) {
                $message .= ' ₦' . number_format($refundToPatient, 2) . ' credited to patient wallet.';
            }
            if ($return->journalEntry) {
                $message .= ' JE Ref: ' . ($return->journalEntry->reference ?? 'JE-' . $return->journalEntry->id);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'journal_entry_id' => $return->journal_entry_id,
                'auto_restocked' => $restocked,
                'wallet_credited' => $refundToPatient,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to approve return', [
                'return_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve return: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a return.
     */
    public function reject($id, Request $request)
    {
        $request->validate([
            'rejection_reason' => 'required|string|min:10',
        ]);

        try {
            $return = PharmacyReturn::findOrFail($id);

            if ($return->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending returns can be rejected'
                ], 422);
            }

            $return->update([
                'status' => 'rejected',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'approval_notes' => $request->rejection_reason,
            ]);

            // Revert ProductRequest status back to dispensed
            $return->productRequest->update([
                'status' => 3,
                'returned_by' => null,
                'returned_date' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Return rejected.',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to reject return', [
                'return_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reject return: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process refund (mark as completed after payment).
     */
    public function processRefund($id)
    {
        try {
            $return = PharmacyReturn::findOrFail($id);

            if ($return->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only approved returns can be refunded'
                ], 422);
            }

            // Restock if condition is good
            if ($return->restock && $return->batch_id) {
                $batch = $return->batch;
                if ($batch) {
                    $batch->increment('current_qty', $return->qty_returned);
                    Log::info('Stock restocked from return', [
                        'return_id' => $return->id,
                        'batch_id' => $return->batch_id,
                        'qty_restocked' => $return->qty_returned,
                    ]);
                }
            }

            $return->update(['status' => 'completed']);

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully.',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process refund', [
                'return_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process refund: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * DataTables endpoint for returns list.
     */
    public function datatables(Request $request)
    {
        $query = PharmacyReturn::with([
            'patient.user',
            'product',
            'store',
            'creator',
            'approver'
        ]);

        // Filters
        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        if ($request->has('from_date') && $request->from_date) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date') && $request->to_date) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('patient_name', function($return) {
                return $return->patient->user->name ?? 'N/A';
            })
            ->addColumn('product_name', function($return) {
                return $return->product->product_name ?? 'N/A';
            })
            ->addColumn('store_name', function($return) {
                return $return->store->store_name ?? 'N/A';
            })
            ->addColumn('created_by_name', function($return) {
                return $return->creator->name ?? 'N/A';
            })
            ->addColumn('approved_by_name', function($return) {
                return $return->approver->name ?? 'N/A';
            })
            ->addColumn('item_info', function($return) {
                $patient = e($return->patient->user->name ?? 'N/A');
                $product = e($return->product->product_name ?? 'N/A');
                return '<strong>' . $product . '</strong>'
                    . '<br><small class="text-muted"><i class="mdi mdi-account"></i> ' . $patient . '</small>';
            })
            ->addColumn('details_info', function($return) {
                $date = $return->created_at ? $return->created_at->format('M d, Y') : '-';
                $refund = '₦' . number_format($return->refund_amount, 2);
                return '<span class="font-weight-bold">' . $return->qty_returned . '</span> returned'
                    . '<br><small class="text-success">' . $refund . '</small>'
                    . '<br><small class="text-muted">' . $date . '</small>';
            })
            ->addColumn('status_info', function($return) {
                $condBadges = [
                    'good' => '<span class="badge badge-success">Good</span>',
                    'damaged' => '<span class="badge badge-danger">Damaged</span>',
                    'expired' => '<span class="badge badge-warning">Expired</span>',
                    'wrong_item' => '<span class="badge badge-info">Wrong</span>',
                ];
                $statusBadges = [
                    'pending' => '<span class="badge badge-warning">Pending</span>',
                    'approved' => '<span class="badge badge-success">Approved</span>',
                    'rejected' => '<span class="badge badge-danger">Rejected</span>',
                    'completed' => '<span class="badge badge-info">Completed</span>',
                ];
                $cond = $condBadges[$return->return_condition] ?? $return->return_condition;
                $status = $statusBadges[$return->status] ?? $return->status;
                return $cond . ' ' . $status;
            })
            ->addColumn('condition_badge', function($return) {
                $badges = [
                    'good' => '<span class="badge badge-success">Good</span>',
                    'damaged' => '<span class="badge badge-danger">Damaged</span>',
                    'expired' => '<span class="badge badge-warning">Expired</span>',
                    'wrong_item' => '<span class="badge badge-info">Wrong Item</span>',
                ];
                return $badges[$return->return_condition] ?? $return->return_condition;
            })
            ->addColumn('status_badge', function($return) {
                $badges = [
                    'pending' => '<span class="badge badge-warning">Pending</span>',
                    'approved' => '<span class="badge badge-success">Approved</span>',
                    'rejected' => '<span class="badge badge-danger">Rejected</span>',
                    'completed' => '<span class="badge badge-info">Completed</span>',
                ];
                return $badges[$return->status] ?? $return->status;
            })
            ->addColumn('actions', function($return) {
                $html = '<div class="btn-group">';
                $html .= '<button class="btn btn-sm btn-info btn-view-return" data-id="' . $return->id . '" title="View Details"><i class="mdi mdi-eye"></i></button>';

                if ($return->status === 'pending') {
                    $html .= '<button class="btn btn-sm btn-success btn-approve-return" data-id="' . $return->id . '" title="Approve"><i class="mdi mdi-check"></i></button>';
                    $html .= '<button class="btn btn-sm btn-danger btn-reject-return" data-id="' . $return->id . '" title="Reject"><i class="mdi mdi-close"></i></button>';
                }

                $html .= '</div>';
                return $html;
            })
            ->rawColumns(['condition_badge', 'status_badge', 'item_info', 'details_info', 'status_info', 'actions'])
            ->make(true);
    }
}
