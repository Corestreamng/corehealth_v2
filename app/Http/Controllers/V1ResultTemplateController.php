<?php

namespace App\Http\Controllers;

use App\Models\V1ResultTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class V1ResultTemplateController extends Controller
{
    // ─── API: used by result-entry modals ──────────────────────────────────

    /**
     * Get all active V1 result templates grouped by category.
     * Used by the result entry modal to populate the template selector.
     * Optional ?type=lab|imaging to filter by template_type.
     */
    public function getTemplates(Request $request)
    {
        $type = $request->query('type'); // 'lab', 'imaging', or null

        $query = V1ResultTemplate::active()
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('name');

        if (in_array($type, ['lab', 'imaging'], true)) {
            $query->whereIn('template_type', [$type, 'both']);
        }

        $templates = $query->get(['id', 'name', 'description', 'content', 'category', 'template_type']);

        $grouped = $templates->groupBy('category')->map(function ($items, $category) {
            return [
                'category' => $category,
                'templates' => $items->map(function ($t) {
                    return [
                        'id' => $t->id,
                        'name' => $t->name,
                        'description' => $t->description,
                        'content' => $t->content,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'groups' => $grouped,
        ]);
    }

    // ─── Admin Management ───────────────────────────────────────────────────

    /**
     * Display the admin template management page.
     */
    public function index()
    {
        return view('admin.result_templates.index');
    }

    /**
     * DataTable server-side data for admin listing.
     */
    public function dtData(Request $request)
    {
        $query = V1ResultTemplate::query()->with('creator:id,firstname,surname,othername');

        if ($request->filled('filter_type')) {
            $query->where('template_type', $request->filter_type);
        }

        if ($request->filled('filter_category')) {
            $query->where('category', $request->filter_category);
        }

        if ($request->filled('filter_status')) {
            $query->where('is_active', $request->filter_status === 'active');
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('type_badge', function ($row) {
                $map = [
                    'lab'     => ['bg-primary', 'flask', 'Lab'],
                    'imaging' => ['bg-warning text-dark', 'x-ray', 'Imaging'],
                    'both'    => ['bg-success', 'check-all', 'Both'],
                ];
                [$cls, $icon, $label] = $map[$row->template_type] ?? ['bg-secondary', 'help', $row->template_type];
                return "<span class='badge {$cls}'><i class='mdi mdi-{$icon}'></i> {$label}</span>";
            })
            ->addColumn('status_badge', function ($row) {
                return $row->is_active
                    ? '<span class="badge bg-success">Active</span>'
                    : '<span class="badge bg-secondary">Inactive</span>';
            })
            ->addColumn('creator_name', function ($row) {
                return $row->creator ? $row->creator->name : '—';
            })
            ->addColumn('actions', function ($row) {
                $edit   = "<button class='btn btn-sm btn-warning me-1' title='Edit' onclick='editTemplate({$row->id})'><i class='mdi mdi-pencil'></i></button>";
                $toggle = $row->is_active
                    ? "<button class='btn btn-sm btn-secondary me-1' title='Deactivate' onclick='toggleTemplate({$row->id})'><i class='mdi mdi-eye-off'></i></button>"
                    : "<button class='btn btn-sm btn-success me-1' title='Activate' onclick='toggleTemplate({$row->id})'><i class='mdi mdi-eye'></i></button>";
                $del    = "<button class='btn btn-sm btn-danger' title='Delete' onclick='deleteTemplate({$row->id})'><i class='fa fa-trash'></i></button>";
                return $edit . $toggle . $del;
            })
            ->rawColumns(['type_badge', 'status_badge', 'actions'])
            ->make(true);
    }

    /**
     * Store a new V1 result template.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'          => 'required|string|max:255',
                'description'   => 'nullable|string|max:500',
                'content'       => 'required|string',
                'category'      => 'nullable|string|max:100',
                'template_type' => 'required|in:lab,imaging,both',
                'sort_order'    => 'nullable|integer|min:0',
                'is_active'     => 'nullable|boolean',
            ]);

            $validated['created_by'] = Auth::id();
            $validated['category']   = $validated['category'] ?? 'General';
            $validated['sort_order'] = $validated['sort_order'] ?? 0;
            $validated['is_active']  = $validated['is_active'] ?? true;

            $template = V1ResultTemplate::create($validated);

            return response()->json([
                'success'  => true,
                'message'  => 'Template created successfully.',
                'template' => $template,
            ]);
        } catch (\Exception $e) {
            Log::error('V1ResultTemplate creation failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create template: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Return a single template for the edit modal.
     */
    public function show(V1ResultTemplate $v1_result_template)
    {
        return response()->json([
            'success'  => true,
            'template' => $v1_result_template,
        ]);
    }

    /**
     * Update an existing V1 result template.
     */
    public function update(Request $request, V1ResultTemplate $v1_result_template)
    {
        try {
            $validated = $request->validate([
                'name'          => 'required|string|max:255',
                'description'   => 'nullable|string|max:500',
                'content'       => 'required|string',
                'category'      => 'nullable|string|max:100',
                'template_type' => 'required|in:lab,imaging,both',
                'sort_order'    => 'nullable|integer|min:0',
                'is_active'     => 'nullable|boolean',
            ]);

            $v1_result_template->update($validated);

            return response()->json([
                'success'  => true,
                'message'  => 'Template updated successfully.',
                'template' => $v1_result_template,
            ]);
        } catch (\Exception $e) {
            Log::error('V1ResultTemplate update failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update template: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Toggle a template's active status.
     */
    public function toggle(V1ResultTemplate $v1_result_template)
    {
        try {
            $v1_result_template->is_active = !$v1_result_template->is_active;
            $v1_result_template->save();

            return response()->json([
                'success' => true,
                'message' => 'Template ' . ($v1_result_template->is_active ? 'activated' : 'deactivated') . ' successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to toggle template: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete a V1 result template.
     */
    public function destroy(V1ResultTemplate $v1_result_template)
    {
        try {
            $v1_result_template->delete();
            return response()->json(['success' => true, 'message' => 'Template deleted successfully.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete template: ' . $e->getMessage()], 500);
        }
    }
}
