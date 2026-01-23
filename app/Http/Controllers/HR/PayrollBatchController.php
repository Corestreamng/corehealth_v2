<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\PayrollBatch;
use App\Models\Staff;
use App\Services\PayrollService;
use App\Services\HrAttachmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * HRMS Implementation Plan - Section 7.2
 * Payroll Batch Controller
 */
class PayrollBatchController extends Controller
{
    protected PayrollService $payrollService;
    protected HrAttachmentService $attachmentService;

    public function __construct(PayrollService $payrollService, HrAttachmentService $attachmentService)
    {
        $this->payrollService = $payrollService;
        $this->attachmentService = $attachmentService;
    }

    public function index(Request $request)
    {
        $query = PayrollBatch::with(['createdBy', 'approvedBy'])
            ->withCount('items');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('year')) {
            $query->whereYear('pay_period_start', $request->year);
        }
        if ($request->filled('month')) {
            $query->whereMonth('pay_period_start', $request->month);
        }

        $batches = $query->latest()->paginate(20);
        $statuses = PayrollBatch::getStatuses();
        $stats = $this->payrollService->getPayrollStats();

        return view('admin.hr.payroll.index', compact('batches', 'statuses', 'stats'));
    }

    public function create()
    {
        // Check for existing draft
        $existingDraft = PayrollBatch::draft()->first();
        if ($existingDraft) {
            return redirect()->route('hr.payroll.edit', $existingDraft)
                ->with('info', 'You have an existing draft payroll batch. Complete or delete it before creating a new one.');
        }

        return view('admin.hr.payroll.create');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'pay_period_start' => 'required|date',
            'pay_period_end' => 'required|date|after:pay_period_start',
            'payment_date' => 'nullable|date|after_or_equal:pay_period_end',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $batch = $this->payrollService->createBatch($request->all(), auth()->user());

            return redirect()->route('hr.payroll.edit', $batch)
                ->with('success', 'Payroll batch created. Now generate payroll items.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show(PayrollBatch $payrollBatch)
    {
        $payrollBatch->load([
            'items.staff.user',
            'items.details',
            'createdBy',
            'submittedBy',
            'approvedBy',
            'rejectedBy',
            'expense',
            'attachments.uploadedBy'
        ]);

        return view('admin.hr.payroll.show', compact('payrollBatch'));
    }

    public function edit(PayrollBatch $payrollBatch)
    {
        if (!$payrollBatch->canEdit()) {
            return redirect()->route('hr.payroll.show', $payrollBatch)
                ->with('error', 'This batch can no longer be edited.');
        }

        $payrollBatch->load(['items.staff.user', 'items.details']);
        $availableStaff = Staff::active()->withSalaryProfile()
            ->with('user', 'currentSalaryProfile')
            ->get();

        return view('admin.hr.payroll.edit', compact('payrollBatch', 'availableStaff'));
    }

    public function update(Request $request, PayrollBatch $payrollBatch)
    {
        if (!$payrollBatch->canEdit()) {
            return back()->with('error', 'This batch can no longer be edited.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'pay_period_start' => 'required|date',
            'pay_period_end' => 'required|date|after:pay_period_start',
            'payment_date' => 'nullable|date|after_or_equal:pay_period_end',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $payrollBatch->update($request->only(['name', 'pay_period_start', 'pay_period_end', 'payment_date']));

        return redirect()->route('hr.payroll.edit', $payrollBatch)
            ->with('success', 'Payroll batch updated.');
    }

    public function destroy(PayrollBatch $payrollBatch)
    {
        if (!$payrollBatch->canEdit()) {
            return back()->with('error', 'Cannot delete batch that has been submitted.');
        }

        // Delete items first
        foreach ($payrollBatch->items as $item) {
            $item->details()->delete();
        }
        $payrollBatch->items()->delete();
        $payrollBatch->delete();

        return redirect()->route('hr.payroll.index')
            ->with('success', 'Payroll batch deleted.');
    }

    public function generate(Request $request, PayrollBatch $payrollBatch)
    {
        if (!$payrollBatch->canEdit()) {
            return back()->with('error', 'Cannot generate items for submitted batch.');
        }

        $request->validate([
            'staff_ids' => 'nullable|array',
            'staff_ids.*' => 'exists:staff,id',
        ]);

        try {
            $count = $this->payrollService->generateBatchItems(
                $payrollBatch,
                $request->staff_ids
            );

            return back()->with('success', "Generated payroll for {$count} staff members.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function submit(PayrollBatch $payrollBatch)
    {
        try {
            $this->payrollService->submitBatch($payrollBatch, auth()->user());

            return redirect()->route('hr.payroll.show', $payrollBatch)
                ->with('success', 'Payroll batch submitted for approval.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function approve(Request $request, PayrollBatch $payrollBatch)
    {
        try {
            $this->payrollService->approveBatch(
                $payrollBatch,
                auth()->user(),
                $request->comments
            );

            return redirect()->route('hr.payroll.show', $payrollBatch)
                ->with('success', 'Payroll batch approved. Expense record created.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, PayrollBatch $payrollBatch)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        try {
            $this->payrollService->rejectBatch(
                $payrollBatch,
                auth()->user(),
                $request->reason
            );

            return redirect()->route('hr.payroll.show', $payrollBatch)
                ->with('success', 'Payroll batch rejected.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function payslips(PayrollBatch $payrollBatch)
    {
        $payrollBatch->load(['items.staff.user', 'items.details.payHead']);

        $payslips = $payrollBatch->items->map(function ($item) {
            return $this->payrollService->getPayslipData($item);
        });

        return view('admin.hr.payroll.payslips', compact('payrollBatch', 'payslips'));
    }

    public function export(PayrollBatch $payrollBatch)
    {
        // Export to CSV/Excel
        $payrollBatch->load(['items.staff.user', 'items.details']);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"payroll-{$payrollBatch->batch_number}.csv\"",
        ];

        $callback = function () use ($payrollBatch) {
            $file = fopen('php://output', 'w');

            // Header row
            fputcsv($file, [
                'Employee ID', 'Name', 'Department', 'Basic Salary',
                'Additions', 'Deductions', 'Gross Salary', 'Net Salary',
                'Bank Name', 'Account Number', 'Account Name'
            ]);

            foreach ($payrollBatch->items as $item) {
                fputcsv($file, [
                    $item->staff->employee_id ?? $item->staff->id,
                    $item->staff->user->name ?? 'Unknown',
                    $item->staff->department ?? 'N/A',
                    number_format($item->basic_salary, 2),
                    number_format($item->total_additions, 2),
                    number_format($item->total_deductions, 2),
                    number_format($item->gross_salary, 2),
                    number_format($item->net_salary, 2),
                    $item->bank_name ?? 'N/A',
                    $item->bank_account_number ?? 'N/A',
                    $item->bank_account_name ?? 'N/A',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
