<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\PayHead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * HRMS Implementation Plan - Section 7.2
 * Pay Head Controller - CRUD for salary additions/deductions
 */
class PayHeadController extends Controller
{
    public function index(Request $request)
    {
        $query = PayHead::withCount('salaryProfileItems');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $payHeads = $query->orderBy('type')->orderBy('sort_order')->paginate(20);
        $types = PayHead::getTypes();

        return view('admin.hr.pay-heads.index', compact('payHeads', 'types'));
    }

    public function create()
    {
        $types = PayHead::getTypes();
        $calculationTypes = PayHead::getCalculationTypes();
        $calculationBases = PayHead::getCalculationBases();

        return view('admin.hr.pay-heads.create', compact('types', 'calculationTypes', 'calculationBases'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:pay_heads,name',
            'code' => 'required|string|max:20|unique:pay_heads,code',
            'description' => 'nullable|string|max:500',
            'type' => 'required|in:addition,deduction',
            'calculation_type' => 'required|in:fixed,percentage,formula',
            'calculation_base' => 'required_if:calculation_type,percentage|nullable|in:basic_salary,gross_salary',
            'default_value' => 'nullable|numeric|min:0',
            'is_taxable' => 'boolean',
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        PayHead::create([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'type' => $request->type,
            'calculation_type' => $request->calculation_type,
            'calculation_base' => $request->calculation_base,
            'default_value' => $request->default_value ?? 0,
            'is_taxable' => $request->boolean('is_taxable'),
            'is_mandatory' => $request->boolean('is_mandatory'),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $request->sort_order ?? 0,
        ]);

        return redirect()->route('hr.pay-heads.index')
            ->with('success', 'Pay head created successfully.');
    }

    public function show(PayHead $payHead)
    {
        $payHead->load(['salaryProfileItems.salaryProfile.staff.user']);

        return view('admin.hr.pay-heads.show', compact('payHead'));
    }

    public function edit(PayHead $payHead)
    {
        $types = PayHead::getTypes();
        $calculationTypes = PayHead::getCalculationTypes();
        $calculationBases = PayHead::getCalculationBases();

        return view('admin.hr.pay-heads.edit', compact('payHead', 'types', 'calculationTypes', 'calculationBases'));
    }

    public function update(Request $request, PayHead $payHead)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:pay_heads,name,' . $payHead->id,
            'code' => 'required|string|max:20|unique:pay_heads,code,' . $payHead->id,
            'description' => 'nullable|string|max:500',
            'type' => 'required|in:addition,deduction',
            'calculation_type' => 'required|in:fixed,percentage,formula',
            'calculation_base' => 'required_if:calculation_type,percentage|nullable|in:basic_salary,gross_salary',
            'default_value' => 'nullable|numeric|min:0',
            'is_taxable' => 'boolean',
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $payHead->update([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'type' => $request->type,
            'calculation_type' => $request->calculation_type,
            'calculation_base' => $request->calculation_base,
            'default_value' => $request->default_value ?? 0,
            'is_taxable' => $request->boolean('is_taxable'),
            'is_mandatory' => $request->boolean('is_mandatory'),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $request->sort_order ?? 0,
        ]);

        return redirect()->route('hr.pay-heads.index')
            ->with('success', 'Pay head updated successfully.');
    }

    public function destroy(PayHead $payHead)
    {
        // Check if pay head is in use
        if ($payHead->salaryProfileItems()->exists() || $payHead->payrollItemDetails()->exists()) {
            return back()->with('error', 'Cannot delete pay head that is in use. Consider deactivating it instead.');
        }

        $payHead->delete();

        return redirect()->route('hr.pay-heads.index')
            ->with('success', 'Pay head deleted successfully.');
    }
}
