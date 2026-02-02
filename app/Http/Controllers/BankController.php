<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class BankController extends Controller
{
    /**
     * Display the banks configuration page.
     */
    public function index()
    {
        $banks = Bank::all();
        return view('admin.banks.index', compact('banks'));
    }

    /**
     * Get list of banks for DataTable.
     */
    public function list()
    {
        $banks = Bank::query();

        return DataTables::of($banks)
            ->addIndexColumn()
            ->addColumn('status', function ($bank) {
                return $bank->is_active
                    ? '<span class="badge badge-success">Active</span>'
                    : '<span class="badge badge-secondary">Inactive</span>';
            })
            ->addColumn('actions', function ($bank) {
                $toggleBtnClass = $bank->is_active ? 'btn-warning' : 'btn-success';
                $toggleIcon = $bank->is_active ? 'mdi-toggle-switch-off' : 'mdi-toggle-switch';
                $toggleTitle = $bank->is_active ? 'Deactivate' : 'Activate';

                return '
                    <button type="button" class="btn btn-sm btn-primary edit-bank" data-id="' . $bank->id . '"
                        data-name="' . e($bank->name) . '"
                        data-account_number="' . e($bank->account_number) . '"
                        data-account_name="' . e($bank->account_name) . '"
                        data-bank_code="' . e($bank->bank_code) . '"
                        data-description="' . e($bank->description) . '"
                        data-account_id="' . ($bank->account_id ?? '') . '"
                        data-is_active="' . ($bank->is_active ? '1' : '0') . '" title="Edit">
                        <i class="mdi mdi-pencil"></i>
                    </button>
                    <button type="button" class="btn btn-sm ' . $toggleBtnClass . ' delete-bank" data-id="' . $bank->id . '" data-name="' . e($bank->name) . '" title="' . $toggleTitle . '">
                        <i class="mdi ' . $toggleIcon . '"></i>
                    </button>
                ';
            })
            ->rawColumns(['status', 'actions'])
            ->make(true);
    }

    /**
     * Store a newly created bank.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'account_name' => 'nullable|string|max:255',
            'bank_code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'account_id' => 'nullable|exists:accounts,id',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $bank = Bank::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Bank created successfully',
            'bank' => $bank
        ]);
    }

    /**
     * Update the specified bank.
     */
    public function update(Request $request, Bank $bank)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'account_name' => 'nullable|string|max:255',
            'bank_code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'account_id' => 'nullable|exists:accounts,id',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $bank->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Bank updated successfully',
            'bank' => $bank
        ]);
    }

    /**
     * Toggle bank active status (soft delete).
     */
    public function destroy(Bank $bank)
    {
        $bank->is_active = !$bank->is_active;
        $bank->save();

        $status = $bank->is_active ? 'activated' : 'deactivated';

        return response()->json([
            'success' => true,
            'message' => "Bank {$status} successfully",
            'is_active' => $bank->is_active
        ]);
    }

    /**
     * Get active banks for dropdown/select.
     */
    public function getActiveBanks()
    {
        $banks = Bank::active()->get(['id', 'name', 'account_number', 'account_name']);

        return response()->json([
            'success' => true,
            'banks' => $banks
        ]);
    }
}
