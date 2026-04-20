<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\HR\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class UnitController extends Controller
{
    public function index()
    {
        $units = Unit::with(['department', 'headOfUnit'])->ordered()->get();
        $departments = Department::active()->ordered()->get();
        $users = User::whereHas('staff_profile')->orderBy('surname')->get();
        return view('admin.hr.units.index', compact('units', 'departments', 'users'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:20|unique:units,code',
            'department_id' => 'nullable|exists:departments,id',
            'head_of_unit_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
        ]);

        Unit::create($request->only(['name', 'code', 'department_id', 'head_of_unit_id', 'description', 'is_active']));
        Alert::success('Success', 'Unit created successfully.');
        return redirect()->route('hr.units.index');
    }

    public function update(Request $request, Unit $unit)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:20|unique:units,code,' . $unit->id,
            'department_id' => 'nullable|exists:departments,id',
            'head_of_unit_id' => 'nullable|exists:users,id',
        ]);

        $unit->update($request->only(['name', 'code', 'department_id', 'head_of_unit_id', 'description', 'is_active']));
        Alert::success('Success', 'Unit updated successfully.');
        return redirect()->route('hr.units.index');
    }

    public function destroy(Unit $unit)
    {
        if ($unit->staff()->count() > 0) {
            Alert::error('Error', 'Cannot delete unit with assigned staff.');
            return redirect()->route('hr.units.index');
        }
        $unit->delete();
        Alert::success('Success', 'Unit deleted.');
        return redirect()->route('hr.units.index');
    }

    /**
     * API: Get units for a department (used in staff forms)
     */
    public function forDepartment(Request $request)
    {
        $units = Unit::active()
            ->where('department_id', $request->department_id)
            ->ordered()
            ->get(['id', 'name', 'code']);
        return response()->json($units);
    }
}
