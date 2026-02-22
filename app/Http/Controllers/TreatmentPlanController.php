<?php

namespace App\Http\Controllers;

use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\Product;
use App\Models\service;
use App\Http\Traits\ClinicalOrdersTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * TreatmentPlanController — CRUD + apply endpoints for treatment plans.
 *
 * Ref: CLINICAL_ORDERS_PLAN.md §6.3
 */
class TreatmentPlanController extends Controller
{
    use ClinicalOrdersTrait;

    /**
     * GET /treatment-plans
     * List treatment plans visible to the current user.
     * Query params: ?specialty=&search=&page=
     */
    public function index(Request $request)
    {
        $query = TreatmentPlan::active()
            ->visibleTo(Auth::id())
            ->bySpecialty($request->input('specialty'))
            ->with(['creator:id,surname,firstname,othername', 'items'])
            ->withCount('items');

        if ($search = $request->input('search')) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $plans = $query->orderBy('name')->paginate(20);

        // Enrich items with display names
        $plans->getCollection()->transform(function ($plan) {
            $plan->items->each(function ($item) {
                $item->display_name = $item->display_name; // triggers accessor
            });
            return $plan;
        });

        return response()->json($plans);
    }

    /**
     * POST /treatment-plans
     * Create a new treatment plan with items.
     *
     * Expects: { name, description?, specialty?, is_global?, items: [{ item_type, reference_id, dose?, note?, priority?, sort_order? }] }
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'                  => 'required|string|max:255',
            'description'           => 'nullable|string',
            'specialty'             => 'nullable|string|max:100',
            'is_global'             => 'nullable|boolean',
            'items'                 => 'required|array|min:1',
            'items.*.item_type'     => 'required|in:lab,imaging,medication,procedure',
            'items.*.reference_id'  => 'required|integer',
            'items.*.dose'          => 'nullable|string|max:500',
            'items.*.note'          => 'nullable|string',
            'items.*.priority'      => 'nullable|string|max:20',
            'items.*.sort_order'    => 'nullable|integer',
        ]);

        $plan = TreatmentPlan::create([
            'name'        => $request->input('name'),
            'description' => $request->input('description'),
            'specialty'   => $request->input('specialty'),
            'created_by'  => Auth::id(),
            'is_global'   => $request->input('is_global', false),
        ]);

        foreach ($request->input('items') as $i => $itemData) {
            TreatmentPlanItem::create([
                'treatment_plan_id' => $plan->id,
                'item_type'         => $itemData['item_type'],
                'reference_id'      => $itemData['reference_id'],
                'dose'              => $itemData['dose'] ?? null,
                'note'              => $itemData['note'] ?? null,
                'priority'          => $itemData['priority'] ?? null,
                'sort_order'        => $itemData['sort_order'] ?? $i,
            ]);
        }

        $plan->load('items');

        return response()->json([
            'success' => true,
            'plan'    => $plan,
            'message' => "Treatment plan '{$plan->name}' created with " . $plan->items->count() . ' items',
        ], 201);
    }

    /**
     * GET /treatment-plans/{id}
     * Show a treatment plan with items and resolved names/prices.
     */
    public function show(TreatmentPlan $treatmentPlan)
    {
        $treatmentPlan->load(['items', 'creator:id,surname,firstname,othername']);

        // Resolve names and prices for each item
        $enrichedItems = $treatmentPlan->items->map(function ($item) {
            $enriched = $item->toArray();
            if ($item->item_type === 'medication') {
                $product = Product::with('price')->find($item->reference_id);
                $enriched['display_name'] = $product->product_name ?? 'Unknown';
                $enriched['price'] = optional(optional($product)->price)->current_sale_price ?? 0;
            } else {
                $svc = service::with('price')->find($item->reference_id);
                $enriched['display_name'] = $svc->service_name ?? 'Unknown';
                $enriched['price'] = optional(optional($svc)->price)->sale_price ?? 0;
            }
            return $enriched;
        });

        return response()->json([
            'success' => true,
            'plan'    => [
                'id'          => $treatmentPlan->id,
                'name'        => $treatmentPlan->name,
                'description' => $treatmentPlan->description,
                'specialty'   => $treatmentPlan->specialty,
                'is_global'   => $treatmentPlan->is_global,
                'created_by'  => $treatmentPlan->creator->name ?? 'N/A',
                'items'       => $enrichedItems,
            ],
        ]);
    }

    /**
     * PUT /treatment-plans/{id}
     * Update a treatment plan (name, description, items).
     */
    public function update(Request $request, TreatmentPlan $treatmentPlan)
    {
        // Only creator can update
        if ($treatmentPlan->created_by !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name'                  => 'sometimes|required|string|max:255',
            'description'           => 'nullable|string',
            'specialty'             => 'nullable|string|max:100',
            'is_global'             => 'nullable|boolean',
            'items'                 => 'sometimes|required|array|min:1',
            'items.*.item_type'     => 'required_with:items|in:lab,imaging,medication,procedure',
            'items.*.reference_id'  => 'required_with:items|integer',
            'items.*.dose'          => 'nullable|string|max:500',
            'items.*.note'          => 'nullable|string',
            'items.*.priority'      => 'nullable|string|max:20',
            'items.*.sort_order'    => 'nullable|integer',
        ]);

        $treatmentPlan->update($request->only(['name', 'description', 'specialty', 'is_global']));

        // Replace items if provided
        if ($request->has('items')) {
            $treatmentPlan->items()->delete();
            foreach ($request->input('items') as $i => $itemData) {
                TreatmentPlanItem::create([
                    'treatment_plan_id' => $treatmentPlan->id,
                    'item_type'         => $itemData['item_type'],
                    'reference_id'      => $itemData['reference_id'],
                    'dose'              => $itemData['dose'] ?? null,
                    'note'              => $itemData['note'] ?? null,
                    'priority'          => $itemData['priority'] ?? null,
                    'sort_order'        => $itemData['sort_order'] ?? $i,
                ]);
            }
        }

        $treatmentPlan->load('items');

        return response()->json([
            'success' => true,
            'plan'    => $treatmentPlan,
            'message' => 'Treatment plan updated',
        ]);
    }

    /**
     * DELETE /treatment-plans/{id}
     * Archive (soft-delete) a treatment plan.
     */
    public function destroy(TreatmentPlan $treatmentPlan)
    {
        if ($treatmentPlan->created_by !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $treatmentPlan->update(['status' => 'archived']);

        return response()->json(['success' => true, 'message' => 'Treatment plan archived']);
    }

    /**
     * POST /encounters/{encounter}/apply-treatment-plan
     * Apply a treatment plan to an encounter (doctor scope).
     *
     * Expects: { treatment_plan_id, selected_item_ids?: [...] }
     */
    public function applyToEncounter(Request $request, \App\Models\Encounter $encounter)
    {
        $request->validate([
            'treatment_plan_id'   => 'required|integer|exists:treatment_plans,id',
            'selected_item_ids'   => 'nullable|array',
            'selected_item_ids.*' => 'integer',
        ]);

        $plan = TreatmentPlan::active()->findOrFail($request->input('treatment_plan_id'));

        try {
            $results = $this->applyTreatmentPlan(
                $plan,
                $encounter->patient_id,
                $encounter->id,
                $request->input('selected_item_ids', [])
            );

            $totalCount = $results->reduce(fn($carry, $items) => $carry + count($items), 0);

            return response()->json([
                'success' => true,
                'results' => $results->map(fn($items) => collect($items)->map(fn($r) => ['id' => $r->id])),
                'count'   => $totalCount,
                'message' => "{$totalCount} item(s) added from '{$plan->name}'",
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /nursing-workbench/clinical-requests/apply-treatment-plan
     * Apply a treatment plan for a patient (nurse scope, no encounter).
     *
     * Expects: { patient_id, treatment_plan_id, selected_item_ids?: [...] }
     */
    public function applyForNurse(Request $request)
    {
        $request->validate([
            'patient_id'          => 'required|integer',
            'treatment_plan_id'   => 'required|integer|exists:treatment_plans,id',
            'selected_item_ids'   => 'nullable|array',
            'selected_item_ids.*' => 'integer',
        ]);

        $plan = TreatmentPlan::active()->findOrFail($request->input('treatment_plan_id'));

        try {
            $results = $this->applyTreatmentPlan(
                $plan,
                $request->input('patient_id'),
                null,
                $request->input('selected_item_ids', [])
            );

            $totalCount = $results->reduce(fn($carry, $items) => $carry + count($items), 0);

            return response()->json([
                'success' => true,
                'results' => $results->map(fn($items) => collect($items)->map(fn($r) => ['id' => $r->id])),
                'count'   => $totalCount,
                'message' => "{$totalCount} item(s) added from '{$plan->name}'",
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /treatment-plans/from-current
     * Quick-save current selection as a new treatment plan template.
     *
     * Expects: { name, description?, specialty?, is_global?, items: [...] }
     * Same as store() but semantically different — called from "Save as Template" button.
     */
    public function fromCurrent(Request $request)
    {
        return $this->store($request);
    }
}
