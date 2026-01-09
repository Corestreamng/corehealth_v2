<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\VaccineScheduleTemplate;
use App\Models\VaccineScheduleItem;
use App\Models\VaccineProductMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;

class VaccineScheduleController extends Controller
{
    /**
     * Display the vaccine schedule configuration page.
     */
    public function index()
    {
        $templates = VaccineScheduleTemplate::withCount('items')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        $defaultTemplate = VaccineScheduleTemplate::getDefault();
        $productMappings = VaccineProductMapping::with('product')
            ->where('is_active', true)
            ->get()
            ->groupBy('vaccine_name');

        return view('admin.vaccine-schedule.index', compact('templates', 'defaultTemplate', 'productMappings'));
    }

    /**
     * Get templates list for DataTable.
     */
    public function getTemplates(Request $request)
    {
        $templates = VaccineScheduleTemplate::withCount('items')
            ->orderBy('is_default', 'desc')
            ->orderBy('name');

        return DataTables::of($templates)
            ->addColumn('status_badge', function ($template) {
                $badges = [];
                if ($template->is_default) {
                    $badges[] = '<span class="badge badge-primary">Default</span>';
                }
                $badges[] = $template->is_active
                    ? '<span class="badge badge-success">Active</span>'
                    : '<span class="badge badge-secondary">Inactive</span>';
                return implode(' ', $badges);
            })
            ->addColumn('actions', function ($template) {
                $buttons = '<div class="btn-group btn-group-sm">';
                $buttons .= '<button class="btn btn-info btn-view-template" data-id="' . $template->id . '" title="View"><i class="mdi mdi-eye"></i></button>';
                $buttons .= '<button class="btn btn-primary btn-edit-template" data-id="' . $template->id . '" title="Edit"><i class="mdi mdi-pencil"></i></button>';
                if (!$template->is_default) {
                    $buttons .= '<button class="btn btn-success btn-set-default" data-id="' . $template->id . '" title="Set as Default"><i class="mdi mdi-star"></i></button>';
                    $buttons .= '<button class="btn btn-danger btn-delete-template" data-id="' . $template->id . '" title="Delete"><i class="mdi mdi-delete"></i></button>';
                }
                $buttons .= '</div>';
                return $buttons;
            })
            ->rawColumns(['status_badge', 'actions'])
            ->make(true);
    }

    /**
     * Store a new template.
     */
    public function storeTemplate(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:vaccine_schedule_templates,name',
            'description' => 'nullable|string',
            'country' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        $validated['created_by'] = Auth::id();
        $validated['is_active'] = $request->has('is_active');

        $template = VaccineScheduleTemplate::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Template created successfully',
            'template' => $template,
        ]);
    }

    /**
     * Get a template with its items.
     */
    public function getTemplate($id)
    {
        $template = VaccineScheduleTemplate::with(['items' => function ($query) {
            $query->orderBy('age_days')->orderBy('sort_order');
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'template' => $template,
        ]);
    }

    /**
     * Update a template.
     */
    public function updateTemplate(Request $request, $id)
    {
        $template = VaccineScheduleTemplate::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:vaccine_schedule_templates,name,' . $id,
            'description' => 'nullable|string',
            'country' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $template->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Template updated successfully',
            'template' => $template,
        ]);
    }

    /**
     * Set a template as default.
     */
    public function setDefaultTemplate($id)
    {
        $template = VaccineScheduleTemplate::findOrFail($id);
        $template->setAsDefault();

        return response()->json([
            'success' => true,
            'message' => 'Template set as default successfully',
        ]);
    }

    /**
     * Delete a template.
     */
    public function deleteTemplate($id)
    {
        $template = VaccineScheduleTemplate::findOrFail($id);

        if ($template->is_default) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete the default template',
            ], 400);
        }

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully',
        ]);
    }

    /**
     * Get schedule items for a template.
     */
    public function getScheduleItems($templateId)
    {
        $items = VaccineScheduleItem::where('template_id', $templateId)
            ->orderBy('age_days')
            ->orderBy('sort_order')
            ->get();

        // Group by age_display for easier viewing
        $grouped = $items->groupBy('age_display');

        return response()->json([
            'success' => true,
            'items' => $items,
            'grouped' => $grouped,
        ]);
    }

    /**
     * Store a new schedule item.
     */
    public function storeScheduleItem(Request $request)
    {
        $validated = $request->validate([
            'template_id' => 'required|exists:vaccine_schedule_templates,id',
            'vaccine_name' => 'required|string|max:255',
            'vaccine_code' => 'nullable|string|max:50',
            'dose_number' => 'required|integer|min:0',
            'dose_label' => 'nullable|string|max:50',
            'age_days' => 'required|integer|min:0',
            'age_display' => 'required|string|max:100',
            'route' => 'nullable|string|max:50',
            'site' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'sort_order' => 'integer|min:0',
            'is_required' => 'boolean',
        ]);

        $validated['is_required'] = $request->has('is_required');
        $validated['sort_order'] = $request->input('sort_order', 0);

        $item = VaccineScheduleItem::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Schedule item created successfully',
            'item' => $item,
        ]);
    }

    /**
     * Update a schedule item.
     */
    public function updateScheduleItem(Request $request, $id)
    {
        $item = VaccineScheduleItem::findOrFail($id);

        $validated = $request->validate([
            'vaccine_name' => 'required|string|max:255',
            'vaccine_code' => 'nullable|string|max:50',
            'dose_number' => 'required|integer|min:0',
            'dose_label' => 'nullable|string|max:50',
            'age_days' => 'required|integer|min:0',
            'age_display' => 'required|string|max:100',
            'route' => 'nullable|string|max:50',
            'site' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'sort_order' => 'integer|min:0',
            'is_required' => 'boolean',
        ]);

        $validated['is_required'] = $request->has('is_required');

        $item->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Schedule item updated successfully',
            'item' => $item,
        ]);
    }

    /**
     * Delete a schedule item.
     */
    public function deleteScheduleItem($id)
    {
        $item = VaccineScheduleItem::findOrFail($id);
        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Schedule item deleted successfully',
        ]);
    }

    /**
     * Get product mappings for DataTable.
     */
    public function getProductMappings(Request $request)
    {
        $mappings = VaccineProductMapping::with('product')
            ->orderBy('vaccine_name')
            ->orderBy('is_primary', 'desc');

        return DataTables::of($mappings)
            ->addColumn('product_name', function ($mapping) {
                return $mapping->product ? $mapping->product->name : 'N/A';
            })
            ->addColumn('status_badge', function ($mapping) {
                $badges = [];
                if ($mapping->is_primary) {
                    $badges[] = '<span class="badge badge-primary">Primary</span>';
                }
                $badges[] = $mapping->is_active
                    ? '<span class="badge badge-success">Active</span>'
                    : '<span class="badge badge-secondary">Inactive</span>';
                return implode(' ', $badges);
            })
            ->addColumn('actions', function ($mapping) {
                $buttons = '<div class="btn-group btn-group-sm">';
                $buttons .= '<button class="btn btn-primary btn-edit-mapping" data-id="' . $mapping->id . '" title="Edit"><i class="mdi mdi-pencil"></i></button>';
                if (!$mapping->is_primary) {
                    $buttons .= '<button class="btn btn-success btn-set-primary-mapping" data-id="' . $mapping->id . '" title="Set as Primary"><i class="mdi mdi-star"></i></button>';
                }
                $buttons .= '<button class="btn btn-danger btn-delete-mapping" data-id="' . $mapping->id . '" title="Delete"><i class="mdi mdi-delete"></i></button>';
                $buttons .= '</div>';
                return $buttons;
            })
            ->rawColumns(['status_badge', 'actions'])
            ->make(true);
    }

    /**
     * Get all unique vaccine names from all templates.
     */
    public function getVaccineNames()
    {
        $vaccineNames = VaccineScheduleItem::select('vaccine_name')
            ->distinct()
            ->orderBy('vaccine_name')
            ->pluck('vaccine_name');

        return response()->json([
            'success' => true,
            'vaccine_names' => $vaccineNames,
        ]);
    }

    /**
     * Store a new product mapping.
     */
    public function storeProductMapping(Request $request)
    {
        $validated = $request->validate([
            'vaccine_name' => 'required|string|max:255',
            'product_id' => 'required|exists:products,id',
            'is_primary' => 'boolean',
        ]);

        // Check if mapping already exists
        $existing = VaccineProductMapping::where('vaccine_name', $validated['vaccine_name'])
            ->where('product_id', $validated['product_id'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'This product is already mapped to this vaccine',
            ], 400);
        }

        $validated['is_primary'] = $request->has('is_primary');

        // If setting as primary, unset other primaries
        if ($validated['is_primary']) {
            VaccineProductMapping::where('vaccine_name', $validated['vaccine_name'])
                ->update(['is_primary' => false]);
        }

        $mapping = VaccineProductMapping::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Product mapping created successfully',
            'mapping' => $mapping->load('product'),
        ]);
    }

    /**
     * Update a product mapping.
     */
    public function updateProductMapping(Request $request, $id)
    {
        $mapping = VaccineProductMapping::findOrFail($id);

        $validated = $request->validate([
            'vaccine_name' => 'required|string|max:255',
            'product_id' => 'required|exists:products,id',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $validated['is_primary'] = $request->has('is_primary');
        $validated['is_active'] = $request->has('is_active');

        // If setting as primary, unset other primaries
        if ($validated['is_primary'] && !$mapping->is_primary) {
            VaccineProductMapping::where('vaccine_name', $validated['vaccine_name'])
                ->where('id', '!=', $id)
                ->update(['is_primary' => false]);
        }

        $mapping->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Product mapping updated successfully',
            'mapping' => $mapping->load('product'),
        ]);
    }

    /**
     * Set a product mapping as primary.
     */
    public function setPrimaryMapping($id)
    {
        $mapping = VaccineProductMapping::findOrFail($id);
        $mapping->setAsPrimary();

        return response()->json([
            'success' => true,
            'message' => 'Product mapping set as primary',
        ]);
    }

    /**
     * Delete a product mapping.
     */
    public function deleteProductMapping($id)
    {
        $mapping = VaccineProductMapping::findOrFail($id);
        $mapping->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product mapping deleted successfully',
        ]);
    }

    /**
     * Search products for mapping (used in select2/autocomplete).
     */
    public function searchProducts(Request $request)
    {
        $search = $request->input('q', '');

        $products = Product::where('name', 'like', "%{$search}%")
            ->orWhere('sku', 'like', "%{$search}%")
            ->select('id', 'name', 'sku')
            ->limit(20)
            ->get();

        return response()->json([
            'results' => $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'text' => $product->name . ($product->sku ? " ({$product->sku})" : ''),
                ];
            }),
        ]);
    }

    /**
     * Duplicate a template.
     */
    public function duplicateTemplate($id)
    {
        $original = VaccineScheduleTemplate::with('items')->findOrFail($id);

        $newTemplate = VaccineScheduleTemplate::create([
            'name' => $original->name . ' (Copy)',
            'description' => $original->description,
            'is_default' => false,
            'is_active' => true,
            'country' => $original->country,
            'created_by' => Auth::id(),
        ]);

        // Duplicate all items
        foreach ($original->items as $item) {
            $newItem = $item->replicate();
            $newItem->template_id = $newTemplate->id;
            $newItem->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Template duplicated successfully',
            'template' => $newTemplate,
        ]);
    }

    /**
     * Export template to JSON.
     */
    public function exportTemplate($id)
    {
        $template = VaccineScheduleTemplate::with('items')->findOrFail($id);

        $export = [
            'name' => $template->name,
            'description' => $template->description,
            'country' => $template->country,
            'items' => $template->items->map(function ($item) {
                return [
                    'vaccine_name' => $item->vaccine_name,
                    'vaccine_code' => $item->vaccine_code,
                    'dose_number' => $item->dose_number,
                    'dose_label' => $item->dose_label,
                    'age_days' => $item->age_days,
                    'age_display' => $item->age_display,
                    'route' => $item->route,
                    'site' => $item->site,
                    'notes' => $item->notes,
                    'sort_order' => $item->sort_order,
                    'is_required' => $item->is_required,
                ];
            }),
        ];

        return response()->json($export)
            ->header('Content-Disposition', 'attachment; filename="' . \Str::slug($template->name) . '.json"');
    }

    /**
     * Import template from JSON.
     */
    public function importTemplate(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:json|max:1024',
        ]);

        $content = json_decode(file_get_contents($request->file('file')->path()), true);

        if (!isset($content['name']) || !isset($content['items'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid template file format',
            ], 400);
        }

        // Create template
        $template = VaccineScheduleTemplate::create([
            'name' => $content['name'] . ' (Imported)',
            'description' => $content['description'] ?? null,
            'is_default' => false,
            'is_active' => true,
            'country' => $content['country'] ?? null,
            'created_by' => Auth::id(),
        ]);

        // Create items
        foreach ($content['items'] as $itemData) {
            VaccineScheduleItem::create(array_merge($itemData, [
                'template_id' => $template->id,
            ]));
        }

        return response()->json([
            'success' => true,
            'message' => 'Template imported successfully',
            'template' => $template,
        ]);
    }
}
