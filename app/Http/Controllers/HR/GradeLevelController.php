<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\GradeLevel;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class GradeLevelController extends Controller
{
    public function index()
    {
        $gradeLevels = GradeLevel::withCount('staff')->ordered()->get();
        return view('admin.hr.grade-levels.index', compact('gradeLevels'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:grade_levels,name',
            'level' => 'required|integer|min:1',
            'step' => 'required|integer|min:1',
            'min_years_to_next' => 'nullable|integer|min:1',
            'retirement_age' => 'nullable|integer|min:40|max:75',
            'max_years_of_service' => 'nullable|integer|min:10|max:45',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0',
        ]);

        GradeLevel::create($request->only([
            'name', 'level', 'step', 'description',
            'min_years_to_next', 'retirement_age', 'max_years_of_service',
            'min_salary', 'max_salary', 'is_active',
        ]));

        Alert::success('Success', 'Grade level created successfully.');
        return redirect()->route('hr.grade-levels.index');
    }

    public function update(Request $request, GradeLevel $gradeLevel)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:grade_levels,name,' . $gradeLevel->id,
            'level' => 'required|integer|min:1',
            'step' => 'required|integer|min:1',
            'min_years_to_next' => 'nullable|integer|min:1',
            'retirement_age' => 'nullable|integer|min:40|max:75',
            'max_years_of_service' => 'nullable|integer|min:10|max:45',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0',
        ]);

        $gradeLevel->update($request->only([
            'name', 'level', 'step', 'description',
            'min_years_to_next', 'retirement_age', 'max_years_of_service',
            'min_salary', 'max_salary', 'is_active',
        ]));

        // Recalculate exit dates for all staff on this grade level
        $gradeLevel->staff->each(fn($s) => $s->recalculateExitDates());

        Alert::success('Success', 'Grade level updated successfully.');
        return redirect()->route('hr.grade-levels.index');
    }

    public function destroy(GradeLevel $gradeLevel)
    {
        if ($gradeLevel->staff()->count() > 0) {
            Alert::error('Error', 'Cannot delete grade level with assigned staff.');
            return redirect()->route('hr.grade-levels.index');
        }
        $gradeLevel->delete();
        Alert::success('Success', 'Grade level deleted.');
        return redirect()->route('hr.grade-levels.index');
    }
}
