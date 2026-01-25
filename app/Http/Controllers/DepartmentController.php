<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $departments = Department::with('headOfDepartment')->ordered()->get();
        return view('admin.departments.index', compact('departments'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $users = User::whereHas('staff_profile')
            ->orderBy('surname')
            ->get();
        return view('admin.departments.create', compact('users'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name',
            'code' => 'nullable|string|max:20|unique:departments,code',
            'description' => 'nullable|string|max:1000',
            'head_of_department_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        Department::create($validated);

        return redirect()->route('departments.index')
            ->with('success', 'Department created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Department $department)
    {
        $department->load(['headOfDepartment', 'staff.user']);
        return view('admin.departments.show', compact('department'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Department $department)
    {
        $users = User::whereHas('staff_profile')
            ->orderBy('surname')
            ->get();
        return view('admin.departments.edit', compact('department', 'users'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('departments')->ignore($department->id)],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('departments')->ignore($department->id)],
            'description' => 'nullable|string|max:1000',
            'head_of_department_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $department->update($validated);

        return redirect()->route('departments.index')
            ->with('success', 'Department updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Department $department)
    {
        // Check if department has staff
        if ($department->staff()->count() > 0) {
            return back()->with('error', 'Cannot delete department with assigned staff.');
        }

        $department->delete();

        return redirect()->route('departments.index')
            ->with('success', 'Department deleted successfully.');
    }

    /**
     * Get departments as JSON for AJAX
     */
    public function getAll()
    {
        $departments = Department::active()->ordered()->get(['id', 'name', 'code']);
        return response()->json($departments);
    }
}
