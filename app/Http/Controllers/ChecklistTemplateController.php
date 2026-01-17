<?php

namespace App\Http\Controllers;

use App\Models\ChecklistTemplate;
use App\Models\ChecklistTemplateItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;
use RealRashid\SweetAlert\Facades\Alert;

class ChecklistTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('admin.checklist-templates.index');
    }

    /**
     * Get checklist templates list for DataTable.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function listTemplates()
    {
        $templates = ChecklistTemplate::withCount('items')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return DataTables::of($templates)
            ->addIndexColumn()
            ->addColumn('type_badge', function ($template) {
                $color = $template->type == 'admission' ? 'success' : 'info';
                $icon = $template->type == 'admission' ? 'mdi-login' : 'mdi-logout';
                return '<span class="badge badge-' . $color . '"><i class="mdi ' . $icon . '"></i> ' . ucfirst($template->type) . '</span>';
            })
            ->addColumn('status_badge', function ($template) {
                return $template->is_active
                    ? '<span class="badge badge-success">Active</span>'
                    : '<span class="badge badge-secondary">Inactive</span>';
            })
            ->addColumn('items_count', function ($template) {
                $required = $template->items->where('is_required', true)->count();
                return '<span class="badge badge-primary">' . $template->items_count . ' items</span> ' .
                       '<small class="text-muted">(' . $required . ' required)</small>';
            })
            ->addColumn('edit', function ($template) {
                return '<a href="' . route('checklist-templates.edit', $template->id) . '" class="btn btn-info btn-sm"><i class="fa fa-pencil"></i> Edit</a>';
            })
            ->addColumn('delete', function ($template) {
                return '<button type="button" class="delete-modal btn btn-danger btn-sm" data-toggle="modal" data-id="' . $template->id . '"><i class="fa fa-trash"></i> Delete</button>';
            })
            ->rawColumns(['type_badge', 'status_badge', 'items_count', 'edit', 'delete'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.checklist-templates.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'type' => 'required|in:admission,discharge',
            'description' => 'nullable|string',
            'is_active' => 'nullable',
            'items' => 'nullable|array',
            'items.*.item_text' => 'nullable|string|max:255',
            'items.*.guidance' => 'nullable|string',
            'items.*.is_required' => 'nullable',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $template = ChecklistTemplate::create([
                'name' => $request->name,
                'type' => $request->type,
                'description' => $request->description,
                'is_active' => $request->has('is_active') ? 1 : 0,
            ]);

            // Add items if provided
            if ($request->has('items') && is_array($request->items)) {
                $sortOrder = 1;
                foreach ($request->items as $itemData) {
                    // Skip items without item_text
                    if (empty($itemData['item_text'])) {
                        continue;
                    }
                    ChecklistTemplateItem::create([
                        'template_id' => $template->id,
                        'item_text' => trim($itemData['item_text']),
                        'guidance' => $itemData['guidance'] ?? null,
                        'is_required' => !empty($itemData['is_required']) ? 1 : 0,
                        'sort_order' => $sortOrder++,
                    ]);
                }
            }

            DB::commit();

            Alert::success('Success', 'Checklist template "' . $template->name . '" created successfully!');
            return redirect()->route('checklist-templates.index');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Failed to create template: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ChecklistTemplate  $checklistTemplate
     * @return \Illuminate\Http\Response
     */
    public function show(ChecklistTemplate $checklistTemplate)
    {
        $checklistTemplate->load('items');
        return view('admin.checklist-templates.show', compact('checklistTemplate'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\ChecklistTemplate  $checklistTemplate
     * @return \Illuminate\Http\Response
     */
    public function edit(ChecklistTemplate $checklistTemplate)
    {
        $checklistTemplate->load(['items' => function ($query) {
            $query->orderBy('sort_order');
        }]);
        return view('admin.checklist-templates.edit', compact('checklistTemplate'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ChecklistTemplate  $checklistTemplate
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ChecklistTemplate $checklistTemplate)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'type' => 'required|in:admission,discharge',
            'description' => 'nullable|string',
            'is_active' => 'nullable',
            'items' => 'nullable|array',
            'items.*.id' => 'nullable|integer',
            'items.*.item_text' => 'nullable|string|max:255',
            'items.*.guidance' => 'nullable|string',
            'items.*.is_required' => 'nullable',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $checklistTemplate->update([
                'name' => $request->name,
                'type' => $request->type,
                'description' => $request->description,
                'is_active' => $request->has('is_active') ? 1 : 0,
            ]);

            // Get existing item IDs
            $existingItemIds = $checklistTemplate->items->pluck('id')->toArray();
            $updatedItemIds = [];

            // Update or create items
            if ($request->has('items') && is_array($request->items)) {
                $sortOrder = 1;
                foreach ($request->items as $itemData) {
                    // Skip items without item_text
                    if (empty($itemData['item_text'])) {
                        continue;
                    }

                    if (!empty($itemData['id'])) {
                        // Update existing item
                        $item = ChecklistTemplateItem::find($itemData['id']);
                        if ($item && $item->template_id == $checklistTemplate->id) {
                            $item->update([
                                'item_text' => trim($itemData['item_text']),
                                'guidance' => $itemData['guidance'] ?? null,
                                'is_required' => !empty($itemData['is_required']) ? 1 : 0,
                                'sort_order' => $sortOrder++,
                            ]);
                            $updatedItemIds[] = $item->id;
                        }
                    } else {
                        // Create new item
                        $newItem = ChecklistTemplateItem::create([
                            'template_id' => $checklistTemplate->id,
                            'item_text' => trim($itemData['item_text']),
                            'guidance' => $itemData['guidance'] ?? null,
                            'is_required' => !empty($itemData['is_required']) ? 1 : 0,
                            'sort_order' => $sortOrder++,
                        ]);
                        $updatedItemIds[] = $newItem->id;
                    }
                }
            }

            // Delete items that were removed
            $itemsToDelete = array_diff($existingItemIds, $updatedItemIds);
            if (!empty($itemsToDelete)) {
                ChecklistTemplateItem::whereIn('id', $itemsToDelete)->delete();
            }

            DB::commit();

            Alert::success('Success', 'Checklist template "' . $checklistTemplate->name . '" updated successfully!');
            return redirect()->route('checklist-templates.index');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Failed to update template: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ChecklistTemplate  $checklistTemplate
     * @return \Illuminate\Http\Response
     */
    public function destroy(ChecklistTemplate $checklistTemplate)
    {
        try {
            $templateName = $checklistTemplate->name;

            // Delete all items first
            $checklistTemplate->items()->delete();

            // Delete template
            $checklistTemplate->delete();

            return response()->json([
                'success' => true,
                'message' => 'Checklist template "' . $templateName . '" deleted successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add item to template via AJAX.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ChecklistTemplate  $template
     * @return \Illuminate\Http\JsonResponse
     */
    public function addItem(Request $request, ChecklistTemplate $template)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_required' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $maxOrder = $template->items()->max('sort_order') ?? 0;

        $item = ChecklistTemplateItem::create([
            'checklist_template_id' => $template->id,
            'name' => $request->name,
            'description' => $request->description,
            'is_required' => $request->has('is_required'),
            'sort_order' => $maxOrder + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Item added successfully!',
            'item' => $item
        ]);
    }

    /**
     * Update item via AJAX.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ChecklistTemplateItem  $item
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateItem(Request $request, ChecklistTemplateItem $item)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_required' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $item->update([
            'name' => $request->name,
            'description' => $request->description,
            'is_required' => $request->has('is_required'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Item updated successfully!',
            'item' => $item
        ]);
    }

    /**
     * Delete item via AJAX.
     *
     * @param  \App\Models\ChecklistTemplateItem  $item
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteItem(ChecklistTemplateItem $item)
    {
        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item deleted successfully!'
        ]);
    }
}
