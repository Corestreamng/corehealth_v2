<?php

namespace App\Http\Controllers;

use App\Models\ProcedureCategory;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Auth;

class ProcedureCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('admin.procedure-categories.index');
    }

    /**
     * Get procedure categories for DataTable.
     */
    public function list(Request $request)
    {
        $query = ProcedureCategory::query();

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('code_badge', function ($row) {
                return '<span class="badge badge-pill badge-primary">' . $row->code . '</span>';
            })
            ->addColumn('status_badge', function ($row) {
                return $row->status
                    ? '<span class="badge badge-success">Active</span>'
                    : '<span class="badge badge-secondary">Inactive</span>';
            })
            ->addColumn('procedures_count', function ($row) {
                $count = $row->procedures()->count();
                return '<span class="badge badge-info">' . $count . '</span>';
            })
            ->addColumn('actions', function ($row) {
                $editUrl = route('procedure-categories.edit', $row->id);
                $deleteUrl = route('procedure-categories.destroy', $row->id);

                return '
                    <a href="' . $editUrl . '" class="btn btn-sm btn-secondary">
                        <i class="fa fa-edit"></i> Edit
                    </a>
                    <form action="' . $deleteUrl . '" method="POST" class="d-inline"
                          onsubmit="return confirm(\'Are you sure you want to delete this category?\');">
                        ' . csrf_field() . '
                        ' . method_field('DELETE') . '
                        <button type="submit" class="btn btn-sm btn-danger">
                            <i class="fa fa-trash"></i>
                        </button>
                    </form>
                ';
            })
            ->rawColumns(['code_badge', 'status_badge', 'procedures_count', 'actions'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.procedure-categories.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:procedure_categories,code',
            'description' => 'nullable|string',
            'status' => 'nullable|boolean',
        ]);

        $validated['status'] = $request->has('status') ? 1 : 0;

        ProcedureCategory::create($validated);

        return redirect()->route('procedure-categories.index')
            ->withMessage('Procedure category created successfully.')
            ->withMessageType('success');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $category = ProcedureCategory::findOrFail($id);
        return view('admin.procedure-categories.edit', compact('category'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $category = ProcedureCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:procedure_categories,code,' . $id,
            'description' => 'nullable|string',
            'status' => 'nullable|boolean',
        ]);

        $validated['status'] = $request->has('status') ? 1 : 0;

        $category->update($validated);

        return redirect()->route('procedure-categories.index')
            ->withMessage('Procedure category updated successfully.')
            ->withMessageType('success');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $category = ProcedureCategory::findOrFail($id);

        // Check if category has procedures
        if ($category->procedures()->count() > 0) {
            return redirect()->route('procedure-categories.index')
                ->withMessage('Cannot delete category with associated procedures.')
                ->withMessageType('danger');
        }

        $category->delete();

        return redirect()->route('procedure-categories.index')
            ->withMessage('Procedure category deleted successfully.')
            ->withMessageType('success');
    }
}
