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
        $additions = PayHead::where('type', 'addition')->orderBy('sort_order')->get();
        $deductions = PayHead::where('type', 'deduction')->orderBy('sort_order')->get();
        $types = PayHead::getTypes();

        return view('admin.hr.pay-heads.index', compact('additions', 'deductions', 'types'));
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
            'percentage_of' => 'required_if:calculation_type,percentage|nullable|in:basic,gross,basic_salary,gross_salary',
            'is_taxable' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $payHead = PayHead::create([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'type' => $request->type,
            'calculation_type' => $request->calculation_type,
            'percentage_of' => $request->percentage_of,
            'is_taxable' => $request->boolean('is_taxable'),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => PayHead::where('type', $request->type)->max('sort_order') + 1,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Pay head created successfully',
                'data' => $payHead
            ]);
        }

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
            'percentage_of' => 'required_if:calculation_type,percentage|nullable|in:basic,gross,basic_salary,gross_salary',
            'is_taxable' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $payHead->update([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'type' => $request->type,
            'calculation_type' => $request->calculation_type,
            'percentage_of' => $request->percentage_of,
            'is_taxable' => $request->boolean('is_taxable'),
            'is_active' => $request->boolean('is_active'),
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Pay head updated successfully',
                'data' => $payHead
            ]);
        }

        return redirect()->route('hr.pay-heads.index')
            ->with('success', 'Pay head updated successfully.');
    }

    public function destroy(Request $request, PayHead $payHead)
    {
        // Check if pay head is in use
        if ($payHead->salaryProfileItems()->exists()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete pay head that is in use. Consider deactivating it instead.'
                ], 422);
            }
            return back()->with('error', 'Cannot delete pay head that is in use. Consider deactivating it instead.');
        }

        $payHead->delete();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Pay head deleted successfully'
            ]);
        }

        return redirect()->route('hr.pay-heads.index')
            ->with('success', 'Pay head deleted successfully.');
    }
}
