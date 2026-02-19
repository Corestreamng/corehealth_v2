<?php

namespace App\Http\Controllers;

use App\Models\ClinicNoteTemplate;
use App\Models\Clinic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class ClinicNoteTemplateController extends Controller
{
    /**
     * Display the templates management page (admin).
     */
    public function index(Request $request)
    {
        $clinics = Clinic::orderBy('name')->get();
        return view('admin.clinic_note_templates.index', compact('clinics'));
    }

    /**
     * DataTable server-side data for the admin listing.
     */
    public function data(Request $request)
    {
        $query = ClinicNoteTemplate::query()
            ->with('clinic:id,name', 'creator:id,name');

        if ($request->has('clinic_id') && $request->clinic_id !== '') {
            if ($request->clinic_id === 'global') {
                $query->whereNull('clinic_id');
            } else {
                $query->where('clinic_id', $request->clinic_id);
            }
        }

        if ($request->has('category') && $request->category !== '') {
            $query->where('category', $request->category);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('clinic_name', function ($row) {
                return $row->clinic ? $row->clinic->name : '<span class="badge bg-info">Global</span>';
            })
            ->addColumn('status', function ($row) {
                return $row->is_active
                    ? '<span class="badge bg-success">Active</span>'
                    : '<span class="badge bg-secondary">Inactive</span>';
            })
            ->addColumn('creator_name', function ($row) {
                return $row->creator ? $row->creator->name : '-';
            })
            ->addColumn('actions', function ($row) {
                $editBtn = "<button class='btn btn-sm btn-warning me-1' onclick='editTemplate({$row->id})'><i class='mdi mdi-pencil'></i></button>";
                $toggleBtn = $row->is_active
                    ? "<button class='btn btn-sm btn-secondary me-1' onclick='toggleTemplate({$row->id}, false)'><i class='mdi mdi-eye-off'></i></button>"
                    : "<button class='btn btn-sm btn-success me-1' onclick='toggleTemplate({$row->id}, true)'><i class='mdi mdi-eye'></i></button>";
                $deleteBtn = "<button class='btn btn-sm btn-danger' onclick='deleteTemplate({$row->id})'><i class='fa fa-trash'></i></button>";
                return $editBtn . $toggleBtn . $deleteBtn;
            })
            ->rawColumns(['clinic_name', 'status', 'actions'])
            ->make(true);
    }

    /**
     * Store a newly created template.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'clinic_id' => 'nullable|exists:clinics,id',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:500',
                'content' => 'required|string',
                'category' => 'nullable|string|max:100',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
            ]);

            $validated['created_by'] = Auth::id();
            $validated['category'] = $validated['category'] ?? 'General';
            $validated['sort_order'] = $validated['sort_order'] ?? 0;
            $validated['is_active'] = $validated['is_active'] ?? true;

            $template = ClinicNoteTemplate::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Template created successfully.',
                'template' => $template,
            ]);
        } catch (\Exception $e) {
            Log::error('Template creation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create template: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a single template (for editing).
     */
    public function show(ClinicNoteTemplate $clinic_note_template)
    {
        return response()->json([
            'success' => true,
            'template' => $clinic_note_template,
        ]);
    }

    /**
     * Update the specified template.
     */
    public function update(Request $request, ClinicNoteTemplate $clinic_note_template)
    {
        try {
            $validated = $request->validate([
                'clinic_id' => 'nullable|exists:clinics,id',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:500',
                'content' => 'required|string',
                'category' => 'nullable|string|max:100',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
            ]);

            $clinic_note_template->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Template updated successfully.',
                'template' => $clinic_note_template,
            ]);
        } catch (\Exception $e) {
            Log::error('Template update failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update template: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle the active status of a template.
     */
    public function toggle(ClinicNoteTemplate $clinic_note_template)
    {
        try {
            $clinic_note_template->is_active = !$clinic_note_template->is_active;
            $clinic_note_template->save();

            return response()->json([
                'success' => true,
                'message' => 'Template ' . ($clinic_note_template->is_active ? 'activated' : 'deactivated') . ' successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle template: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete the specified template.
     */
    public function destroy(ClinicNoteTemplate $clinic_note_template)
    {
        try {
            $clinic_note_template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete template: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get active templates for a specific clinic (used by doctor encounter form).
     * Returns templates for the given clinic + global templates.
     */
    public function getByClinic(Request $request)
    {
        try {
            $clinicId = $request->input('clinic_id');

            $templates = ClinicNoteTemplate::active()
                ->forClinic($clinicId)
                ->orderBy('category')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'description', 'content', 'category', 'clinic_id']);

            // Group by category
            $grouped = $templates->groupBy('category')->map(function ($items, $category) {
                return [
                    'category' => $category,
                    'templates' => $items->map(function ($t) {
                        return [
                            'id' => $t->id,
                            'name' => $t->name,
                            'description' => $t->description,
                            'content' => $t->content,
                            'is_global' => is_null($t->clinic_id),
                        ];
                    })->values(),
                ];
            })->values();

            return response()->json([
                'success' => true,
                'groups' => $grouped,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load templates: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load templates.',
            ], 500);
        }
    }
}
