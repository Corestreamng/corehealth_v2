<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Organization;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Log;

class OrganizationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('admin.organizations.index');
    }

    /**
     * Get data for DataTables
     */
    public function data()
    {
        $organizations = Organization::query();

        return DataTables::of($organizations)
            ->addColumn('status_badge', function ($row) {
                if ($row->status == 1) {
                    return '<span class="badge bg-success">Active</span>';
                }
                return '<span class="badge bg-danger">Inactive</span>';
            })
            ->addColumn('balance_formatted', function ($row) {
                return '₦' . number_format($row->balance, 2);
            })
            ->addColumn('credit_limit_formatted', function ($row) {
                return '₦' . number_format($row->credit_limit, 2);
            })
            ->addColumn('action', function ($row) {
                return '
                    <button class="btn btn-sm btn-primary edit-org" data-id="' . $row->id . '">
                        <i class="mdi mdi-pencil"></i> Edit
                    </button>
                    <a href="' . route('organizations.show', $row->id) . '" class="btn btn-sm btn-info text-white">
                        <i class="mdi mdi-eye"></i> View
                    </a>
                ';
            })
            ->rawColumns(['status_badge', 'action'])
            ->make(true);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:organizations,name',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'credit_limit' => 'nullable|numeric|min:0',
            'status' => 'required|boolean',
        ]);

        try {
            Organization::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'credit_limit' => $request->credit_limit ?? 0,
                'status' => $request->status,
                'balance' => 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Organization created successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create organization: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create organization'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $organization = Organization::findOrFail($id);
        return view('admin.organizations.show', compact('organization'));
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
        $request->validate([
            'name' => 'required|string|max:255|unique:organizations,name,' . $id,
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'credit_limit' => 'nullable|numeric|min:0',
            'status' => 'required|boolean',
        ]);

        try {
            $organization = Organization::findOrFail($id);
            $organization->update([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'credit_limit' => $request->credit_limit ?? 0,
                'status' => $request->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Organization updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update organization: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update organization'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $organization = Organization::findOrFail($id);
            
            // Prevent deletion if they have bills
            if ($organization->bills()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete organization with associated bills. Please deactivate instead.'
                ], 400);
            }

            $organization->delete();

            return response()->json([
                'success' => true,
                'message' => 'Organization deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete organization: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete organization'
            ], 500);
        }
    }

    /**
     * View organization bills.
     */
    public function bills($id)
    {
        $organization = Organization::findOrFail($id);
        
        $bills = \App\Models\OrganizationBill::with(['patient', 'checkoutPayment'])
            ->where('organization_id', $id)
            ->where('outstanding_amount', '>', 0)
            ->orderBy('created_at', 'desc')
            ->get();

        $banks = \App\Models\Bank::where('status', 1)->get();

        return view('admin.organizations.bills', compact('organization', 'bills', 'banks'));
    }

    /**
     * Settle an organization bill.
     */
    public function settleBill(Request $request, $id)
    {
        if (!auth()->user()->hasAnyRole(['SUPERADMIN', 'ADMIN', 'super-admin', 'ACCOUNTS', 'accounts', 'AUDITOR', 'auditor'])) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'bill_ids' => 'required|array',
            'bill_ids.*' => 'exists:organization_bills,id',
            'payment_method' => 'required|in:CASH,POS,TRANSFER,CHEQUE,WAIVER',
            'bank_id' => 'required_if:payment_method,POS,TRANSFER,CHEQUE|nullable|exists:banks,id',
            'amount_paid' => 'required|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $organization = Organization::findOrFail($id);
        $billIds = $request->bill_ids;
        $paymentMethod = $request->payment_method;
        $bankId = $request->bank_id;
        $amountPaid = floatval($request->amount_paid);
        $discountAmount = floatval($request->discount_amount ?? 0);

        if ($paymentMethod === 'WAIVER') {
            $amountPaid = 0; // Waivers don't have actual payments
        }

        $bills = \App\Models\OrganizationBill::whereIn('id', $billIds)
            ->where('organization_id', $id)
            ->where('outstanding_amount', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get();

        if ($bills->isEmpty()) {
            return redirect()->back()->with('error', 'No outstanding bills found for settlement.');
        }

        try {
            // Create the clearing payment transaction in database
            \Illuminate\Support\Facades\DB::transaction(function() use ($bills, $organization, $paymentMethod, $bankId, $amountPaid, $discountAmount, $request) {
                $ref = 'ORG-SETTL-' . strtoupper(uniqid());
                
                $payment = \App\Models\Payment::create([
                    'payment_type' => 'ORGANIZATION_BILL_SETTLEMENT',
                    'payment_method' => $paymentMethod,
                    'bank_id' => $bankId,
                    'total' => $amountPaid,
                    'total_discount' => $discountAmount,
                    'reference_no' => $ref,
                    'status' => 'settled',
                    'user_id' => auth()->id(),
                    'notes' => 'Settlement for ' . $organization->name . ' by ' . auth()->user()->surname . '. ' . $request->notes,
                ]);

                // Allocate amount sequentially across selected bills
                $remainingPayment = $amountPaid;
                $remainingDiscount = $discountAmount;
                $totalSettled = 0;

                foreach ($bills as $bill) {
                    if ($remainingPayment <= 0 && $remainingDiscount <= 0) {
                        break;
                    }

                    $outstanding = floatval($bill->outstanding_amount);
                    
                    // Max we can allocate to this bill is its outstanding amount
                    $allocatedDiscount = min($remainingDiscount, $outstanding);
                    $remainingForPayment = $outstanding - $allocatedDiscount;
                    $allocatedPayment = min($remainingPayment, $remainingForPayment);

                    $totalAllocated = $allocatedDiscount + $allocatedPayment;
                    $totalSettled += $totalAllocated;

                    $bill->outstanding_amount = $outstanding - $totalAllocated;
                    $bill->discount_amount = floatval($bill->discount_amount) + $allocatedDiscount;

                    if ($bill->outstanding_amount <= 0) {
                        $bill->status = 'paid';
                        $bill->settled_at = now();
                    } else {
                        $bill->status = 'pending';
                    }

                    $bill->settlement_payment_id = $payment->id;
                    $bill->save();

                    \Illuminate\Support\Facades\DB::table('org_bill_pay_allocs')->insert([
                        'organization_bill_id' => $bill->id,
                        'payment_id'           => $payment->id,
                        'amount_allocated'     => $allocatedPayment,
                        'discount_allocated'   => $allocatedDiscount,
                        'created_at'           => now(),
                        'updated_at'           => now(),
                    ]);

                    $remainingDiscount -= $allocatedDiscount;
                    $remainingPayment -= $allocatedPayment;
                }

                // Update organization balance (reduce by the total settled amount)
                $organization->balance = max(0, $organization->balance - $totalSettled);
                $organization->save();
            });

            return redirect()->back()->with('success', 'Organization bills settled successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to settle organization bills: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to settle bills: ' . $e->getMessage());
        }
    }
}
