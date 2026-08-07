<?php

namespace App\Http\Controllers;

use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\Product;
use App\Models\Service;
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
                $svc = Service::with('price')->find($item->reference_id);
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
            'diagnosis_data'        => 'nullable|string',
            'diagnosis_status'      => 'nullable|string|max:50',
            'diagnosis_course'      => 'nullable|string|max:50',
            'problem_text'          => 'nullable|string|max:500',
            'icd_code'              => 'nullable|string|max:20',
            'goal'                  => 'nullable|string',
            'priority'              => 'nullable|in:low,medium,high,urgent',
            'specialty'             => 'nullable|string|max:100',
            'is_global'             => 'nullable|boolean',
            'visibility'            => 'nullable|array',
            'items'                 => 'sometimes|required|array|min:1',
            'items.*.item_type'     => 'required_with:items|in:lab,imaging,medication,procedure',
            'items.*.reference_id'  => 'required_with:items|integer',
            'items.*.dose'          => 'nullable|string|max:500',
            'items.*.note'          => 'nullable|string',
            'items.*.priority'      => 'nullable|string|max:20',
            'items.*.sort_order'    => 'nullable|integer',
        ]);

        $updateData = $request->only([
            'name', 'description', 'specialty', 'is_global', 
            'diagnosis_status', 'diagnosis_course', 'problem_text', 
            'icd_code', 'goal', 'priority', 'visibility'
        ]);

        if ($request->has('diagnosis_data')) {
            $updateData['diagnosis_data'] = is_string($request->input('diagnosis_data'))
                ? json_decode($request->input('diagnosis_data'), true)
                : $request->input('diagnosis_data');
        }

        $treatmentPlan->update($updateData);

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
            'message' => 'Treatment plan updated successfully',
        ]);
    }

    /**
     * PUT /treatment-plans/{treatmentPlan}/retire
     * Retire or complete a treatment plan.
     */
    public function retire(Request $request, TreatmentPlan $treatmentPlan)
    {
        $request->validate([
            'retirement_reason' => 'required|string|max:100',
            'retirement_notes'  => 'nullable|string|max:1000',
        ]);

        $reason = $request->input('retirement_reason');
        $newStatus = ($reason === 'goal_achieved') ? 'completed' : 'retired';

        $treatmentPlan->update([
            'status'            => $newStatus,
            'retired_at'        => now(),
            'retired_by'        => auth()->id(),
            'retirement_reason' => $reason,
            'retirement_notes'  => $request->input('retirement_notes'),
        ]);

        // Invalidate Patient Context cache for LLM
        if ($treatmentPlan->patient_id) {
            app(\App\Services\PatientContextService::class)->invalidateContextCache($treatmentPlan->patient_id);
        }

        return response()->json([
            'success' => true,
            'plan'    => $treatmentPlan->fresh(['creator', 'retirer']),
            'message' => 'Treatment plan has been ' . ($newStatus === 'completed' ? 'completed' : 'retired') . ' successfully.',
        ]);
    }

    /**
     * POST /treatment-plans/{treatmentPlan}/delink-item
     * Unlink a specific order request item from this plan.
     */
    public function delinkItem(Request $request, TreatmentPlan $treatmentPlan)
    {
        $request->validate([
            'item_type' => 'required|string|in:labs,imaging,medications,procedures,non_pharm,referrals,admissions,notes',
            'item_id'   => 'required|integer',
        ]);

        $type = $request->input('item_type');
        $id = (int) $request->input('item_id');

        $modelClass = match ($type) {
            'labs'        => \App\Models\LabServiceRequest::class,
            'imaging'     => \App\Models\ImagingServiceRequest::class,
            'medications' => \App\Models\ProductRequest::class,
            'procedures'  => \App\Models\Procedure::class,
            'non_pharm'   => \App\Models\NonPharmOrder::class,
            'referrals'   => \App\Models\SpecialistReferral::class,
            'admissions'  => \App\Models\AdmissionRequest::class,
            'notes'       => \App\Models\Encounter::class,
        };

        $item = $modelClass::where('treatment_plan_id', $treatmentPlan->id)->find($id);

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Linked order item not found on this plan'], 404);
        }

        $item->update([
            'treatment_plan_id'   => null,
            'treatment_plan_name' => null,
        ]);

        $progress = $treatmentPlan->computeProgress();

        return response()->json([
            'success'          => true,
            'message'          => 'Order unlinked from treatment plan successfully',
            'progress_percent' => $progress,
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

    /* ═══════════════════════════════════════════
       Patient-Scoped Endpoints (Plan Upgrade v2)
       ═══════════════════════════════════════════ */

    /**
     * GET /patients/{patient}/treatment-plans
     * All treatment plans for a patient (across all encounters), with optional DB search.
     * Shows originating doctor name + clinic.
     */
    public function forPatient(Request $request, \App\Models\Patient $patient)
    {
        $query = TreatmentPlan::forPatient($patient->id)
            ->with(['creator:id,surname,firstname,othername', 'clinic:id,name', 'items'])
            ->withCount('items');

        if ($search = trim($request->get('search'))) {
            $term = '%' . strtolower($search) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(goal) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(problem_text) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(icd_code) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(diagnosis_data) LIKE ?', [$term])
                  ->orWhereHas('creator', function ($q2) use ($term) {
                      $q2->whereRaw('LOWER(surname) LIKE ?', [$term])
                         ->orWhereRaw('LOWER(firstname) LIKE ?', [$term]);
                  });
            });
        }

        $plans = $query->orderByDesc('created_at')->get();

        $user = Auth::user();
        $role = $request->get('role') ?: $request->get('workbench_role');

        $plans = $plans->filter(function ($plan) use ($user, $role) {
            return $plan->isAccessibleBy($user, $role);
        })->values();

        // Enrich each plan with linked items count + progress
        $plans->each(function ($plan) {
            $plan->computeProgress();
            $linked = $plan->linkedItems();
            $plan->linked_counts = $linked->map(fn($items) => $items->count());
            $plan->total_linked = $linked->flatten()->count();
        });

        return response()->json([
            'success' => true,
            'plans'   => $plans,
        ]);
    }

    /**
     * POST /patients/{patient}/treatment-plans
     * Create a new patient-scoped plan (linked to current encounter + clinic).
     */
    public function storeForPatient(Request $request, \App\Models\Patient $patient)
    {
        $request->validate([
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string',
            'diagnosis_data'   => 'nullable|string',
            'diagnosis_status' => 'nullable|string|max:50',
            'diagnosis_course' => 'nullable|string|max:50',
            'problem_text'     => 'nullable|string|max:500',
            'icd_code'         => 'nullable|string|max:20',
            'goal'             => 'nullable|string',
            'priority'         => 'nullable|in:low,medium,high,urgent',
            'visibility'       => 'nullable|array',
            'encounter_id'     => 'nullable|integer',
            'clinic_id'        => 'nullable|integer',
            'items'            => 'nullable|array',
            'items.*.item_type'    => 'required_with:items|in:lab,imaging,medication,procedure,non_pharm,referral,admission,encounter_note',
            'items.*.reference_id' => 'nullable|integer',
            'items.*.dose'         => 'nullable|string|max:500',
            'items.*.note'         => 'nullable|string',
            'items.*.priority'     => 'nullable|string|max:20',
            'items.*.sort_order'   => 'nullable|integer',
        ]);

        $clinicId = $request->input('clinic_id');
        $encounterId = $request->input('encounter_id');
        
        if (!$clinicId && $encounterId) {
            $encounter = \App\Models\Encounter::with('queue')->find($encounterId);
            if ($encounter && $encounter->queue) {
                $clinicId = $encounter->queue->clinic_id;
            }
        }

        $plan = TreatmentPlan::create([
            'name'             => $request->input('name'),
            'description'      => $request->input('description'),
            'diagnosis_data'   => $request->has('diagnosis_data') ? json_decode($request->input('diagnosis_data'), true) : null,
            'diagnosis_status' => $request->input('diagnosis_status'),
            'diagnosis_course' => $request->input('diagnosis_course'),
            'problem_text'     => $request->input('problem_text'),
            'icd_code'         => $request->input('icd_code'),
            'goal'             => $request->input('goal'),
            'priority'         => $request->input('priority', 'medium'),
            'visibility'       => $request->input('visibility', []),
            'patient_id'       => $patient->id,
            'encounter_id'     => $encounterId,
            'clinic_id'        => $clinicId,
            'created_by'       => Auth::id(),
        ]);

        // Create items if provided
        if ($request->has('items')) {
            foreach ($request->input('items') as $i => $itemData) {
                TreatmentPlanItem::create([
                    'treatment_plan_id' => $plan->id,
                    'item_type'         => $itemData['item_type'],
                    'reference_id'      => $itemData['reference_id'] ?? null,
                    'dose'              => $itemData['dose'] ?? null,
                    'note'              => $itemData['note'] ?? null,
                    'priority'          => $itemData['priority'] ?? null,
                    'sort_order'        => $itemData['sort_order'] ?? $i,
                ]);
            }
        }

        $plan->load('items');

        return response()->json([
            'success' => true,
            'plan'    => $plan,
            'message' => "Treatment plan '{$plan->name}' created for patient",
        ], 201);
    }

    /**
     * PUT /treatment-plans/{treatmentPlan}/progress
     * Recompute or manually update progress percent.
     */
    public function updateProgress(Request $request, TreatmentPlan $treatmentPlan)
    {
        if ($request->has('progress_percent')) {
            // Manual override
            $request->validate(['progress_percent' => 'required|integer|min:0|max:100']);
            $treatmentPlan->update(['progress_percent' => $request->input('progress_percent')]);
            $percent = $request->input('progress_percent');
        } else {
            // Auto-compute from linked items
            $percent = $treatmentPlan->computeProgress();
        }

        return response()->json([
            'success'          => true,
            'progress_percent' => $percent,
            'message'          => "Progress updated to {$percent}%",
        ]);
    }

    /**
     * GET /treatment-plans/{treatmentPlan}/linked-items
     * Fetch all items across all 8 tables linked to this plan.
     * Returns grouped + status-enriched data for the viewer modal.
     */
    public function getItemProgressScore($item, string $type): int
    {
        $requireLabApproval = (bool) appsettings('lab_results_require_approval', false);
        $requireImgApproval = (bool) appsettings('imaging_results_require_approval', false);

        switch ($type) {
            case 'labs':
            case 'imaging':
                $st = (int) ($item->status ?? 0);
                $hasResult = !empty($item->result) || !empty($item->result_by) || !empty($item->result_date);
                $isFreeForm = !empty($item->is_free_form);
                $requiresApproval = ($type === 'labs') ? $requireLabApproval : $requireImgApproval;

                if ($st >= 4 || !empty($item->approved_by) || !empty($item->approved_at) || ($hasResult && (!$requiresApproval || $isFreeForm)) || ($st >= 3 && !$requiresApproval)) {
                    return 100;
                } elseif ($st == 3 || $hasResult) {
                    return 75;
                } elseif ($st == 2 || !empty($item->billed_by) || !empty($item->billed_date) || !empty($item->billing_id) || !empty($item->biller_id) || (isset($item->payment_status) && $item->payment_status === 'paid')) {
                    return 50;
                } elseif ($st == 6) {
                    return 0;
                } else {
                    return 25;
                }

            case 'medications':
                $st = (int) ($item->status ?? 0);
                $isFreeForm = !empty($item->is_free_form);
                $isDispensed = !empty($item->dispensed_by) || !empty($item->dispense_date) || $st >= 3 || !empty($item->dispensed);
                $isBilled = !empty($item->billed_by) || !empty($item->billed_date) || $st == 2 || (isset($item->payment_status) && $item->payment_status === 'paid');

                if ($isDispensed) {
                    return 100;
                } elseif ($isFreeForm && $isBilled) {
                    return 75;
                } elseif ($isBilled) {
                    return 50;
                } else {
                    return 25;
                }

            case 'procedures':
                $pStatus = strtolower($item->procedure_status ?? '');
                $st = (int) ($item->status ?? 0);
                $hasPostNotes = !empty($item->post_notes) || !empty($item->post_notes_by);

                if ($pStatus === 'completed' || $st >= 4 || $hasPostNotes) {
                    return 100;
                } elseif ($pStatus === 'in_progress' || $st == 3 || !empty($item->actual_start_time)) {
                    return 75;
                } elseif ($pStatus === 'scheduled' || !empty($item->scheduled_date) || !empty($item->billed_by) || !empty($item->billed_on) || $st == 2) {
                    return 50;
                } elseif ($pStatus === 'cancelled' || $st == 6 || !empty($item->cancelled_at)) {
                    return 0;
                } else {
                    return 25;
                }

            case 'non_pharm':
                $st = strtolower($item->status ?? '');
                if ($st === 'completed' || !empty($item->completed_by) || !empty($item->completed_at)) {
                    return 100;
                } elseif ($st === 'active' || $st === 'in_progress') {
                    return 50;
                } elseif ($st === 'discontinued' || $st === 'cancelled' || !empty($item->discontinued_at)) {
                    return 0;
                } else {
                    return 25;
                }

            case 'referrals':
                $st = strtolower($item->status ?? '');
                if ($st === 'completed') {
                    return 100;
                } elseif ($st === 'booked' || $st === 'referred_out' || !empty($item->actioned_by) || !empty($item->actioned_at) || !empty($item->appointment_id)) {
                    return 50;
                } elseif ($st === 'declined' || $st === 'cancelled') {
                    return 0;
                } else {
                    return 25;
                }

            case 'admissions':
                $aStatus = strtolower($item->admission_status ?? '');
                if ((bool)$item->discharged || $aStatus === 'discharged' || !empty($item->discharged_by) || !empty($item->discharge_date)) {
                    return 100;
                } elseif ($aStatus === 'discharge_requested') {
                    return 75;
                } elseif ($aStatus === 'admitted' || !empty($item->bed_id) || !empty($item->bed_assigned_by) || !empty($item->bed_assign_date)) {
                    return 50;
                } elseif ($aStatus === 'cancelled') {
                    return 0;
                } else {
                    return 25;
                }

            case 'notes':
                if ((bool)$item->completed || !empty($item->doctor_signoff_at)) {
                    return 100;
                } else {
                    return 50;
                }

            default:
                return 25;
        }
    }

    public function linkedItems(Request $request, TreatmentPlan $treatmentPlan)
    {
        $role = $request->get('role') ?: $request->get('workbench_role');
        if (!$treatmentPlan->isAccessibleBy(Auth::user(), $role)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this treatment plan.',
            ], 403);
        }

        $treatmentPlan->load(['creator:id,surname,firstname,othername', 'clinic:id,name', 'items']);

        $linked = $treatmentPlan->linkedItems();
        $self = $this;

        // Status label & progress percentage mapping helper
        $enriched = $linked->map(function ($items, $type) use ($self) {
            return $items->map(function ($item) use ($type, $self) {
                $arr = $item->toArray();
                $arr['_order_name'] = match ($type) {
                    'labs', 'imaging' => $item->service ? $item->service->service_name : ($item->service_name ?? $item->free_form_name ?? 'Unknown Service'),
                    'medications'     => $item->product ? $item->product->product_name : ($item->item_name ?? $item->free_form_name ?? 'Unknown Medication'),
                    'procedures'      => $item->free_form_name ?? ($item->service ? $item->service->service_name : ($item->procedureDefinition ? $item->procedureDefinition->name : 'Procedure Request')),
                    'non_pharm'       => $item->instructions ?? $item->category ?? 'Care Order',
                    'referrals'       => $item->reason ?? 'Specialist Referral',
                    'admissions'      => $item->admission_reason ?? 'Hospital Admission',
                    'notes'           => 'Clinical Encounter Note',
                    default           => 'Order Item',
                };
                $arr['_status_label'] = match ($type) {
                    'labs', 'imaging' => match ((int) $item->status) {
                        1 => 'Ordered', 2 => 'Billed', 3 => 'Results Entered',
                        4 => 'Completed', 5 => 'Pending Approval', 6 => 'Rejected',
                        default => 'Pending',
                    },
                    'medications' => $item->dispensed_by ? 'Dispensed' : ($item->billed_by ? 'Billed' : 'Pending'),
                    'procedures'  => ucfirst(str_replace('_', ' ', $item->procedure_status ?? 'requested')),
                    'non_pharm'   => ucfirst($item->status ?? 'active'),
                    'referrals'   => ucfirst($item->status ?? 'pending'),
                    'admissions'  => $item->discharged ? 'Discharged' : ucfirst(str_replace('_', ' ', $item->admission_status ?? 'pending')),
                    'notes'       => $item->completed ? 'Completed' : 'In Progress',
                    default       => 'Unknown',
                };
                $arr['_item_progress_percent'] = $self->getItemProgressScore($item, $type);
                $arr['_type'] = $type;
                return $arr;
            });
        });

        // Compute fresh progress
        $progress = $treatmentPlan->computeProgress();

        return response()->json([
            'success'          => true,
            'plan'             => $treatmentPlan,
            'linked_items'     => $enriched,
            'progress_percent' => $progress,
            'total_items'      => $enriched->flatten(1)->count(),
        ]);
    }

    /**
     * POST /treatment-plans/{treatmentPlan}/attach-note
     * Associate an encounter's notes with this plan.
     */
    public function attachNote(Request $request, TreatmentPlan $treatmentPlan)
    {
        $request->validate([
            'encounter_id' => 'required|integer|exists:encounters,id',
        ]);

        $encounter = \App\Models\Encounter::findOrFail($request->input('encounter_id'));
        $encounter->update([
            'treatment_plan_id'   => $treatmentPlan->id,
            'treatment_plan_name' => $treatmentPlan->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Notes associated with plan '{$treatmentPlan->name}'",
        ]);
    }
}
