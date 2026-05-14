<?php

namespace App\Http\Traits;

use App\Models\Encounter;
use App\Models\LabServiceRequest;
use App\Models\ImagingServiceRequest;
use App\Models\ProductRequest;
use App\Models\Procedure;
use App\Models\ProcedureItem;
use App\Models\ProductOrServiceRequest;
use App\Models\Service;
use App\Models\Product;
use App\Models\Patient;
use App\Models\ServicePrice;
use App\Models\AdmissionRequest;
use App\Models\DoctorQueue;
use App\Models\QueueStatus;
use App\Services\QueueStatusService;
use App\Helpers\HmoHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait ClinicalOrdersTrait
{
    /* ═══════════════════════════════════════════
       LABORATORY (LabServiceRequest model)
       ═══════════════════════════════════════════ */

    /**
     * Create a single lab service request.
     *
     * @param int         $serviceId    services.id
     * @param string|null $note         clinical note
     * @param int         $patientId    patients.id
     * @param int|null    $encounterId  encounters.id (null for nurse)
     * @param array       $extra        Extra fields (service_request_id, status, etc)
     * @return LabServiceRequest
     */
    protected function addSingleLab(int $serviceId, ?string $note, int $patientId, ?int $encounterId, array $extra = []): LabServiceRequest
    {
        $lab = new LabServiceRequest();
        $lab->service_id   = $serviceId;
        $lab->note         = $note;
        $lab->patient_id   = $patientId;
        $lab->encounter_id = $encounterId; // null for nurse
        $lab->doctor_id    = Auth::id();

        if (isset($extra['service_request_id'])) {
            $lab->service_request_id = $extra['service_request_id'];
        }
        if (isset($extra['status'])) {
            $lab->status = $extra['status'];
        }
        if (isset($extra['billed_by'])) {
            $lab->billed_by = $extra['billed_by'];
        }
        if (isset($extra['billed_date'])) {
            $lab->billed_date = $extra['billed_date'];
        }

        $lab->save();

        return $lab;
    }

    /**
     * Update a single lab's clinical note (debounced auto-save).
     */
    protected function updateSingleLabNote(int $id, ?string $note): LabServiceRequest
    {
        $lab = LabServiceRequest::findOrFail($id);
        $lab->note = $note;
        $lab->save();
        return $lab;
    }

    /**
     * Remove a single lab service request (validated + soft-delete with audit).
     *
     * @param int         $id
     * @param string|null $reason  Optional deletion reason
     * @throws \RuntimeException when deletion is not allowed
     */
    protected function removeSingleLab(int $id, ?string $reason = null): void
    {
        $lab = LabServiceRequest::findOrFail($id);
        $check = $this->canDeleteClinicalRequest('lab', $lab);

        if (!$check['allowed']) {
            throw new \RuntimeException($check['reason']);
        }

        $lab->deleted_by      = Auth::id();
        $lab->deletion_reason = $reason ?? 'Removed by requester';
        $lab->save();
        $lab->delete();
    }

    /* ═══════════════════════════════════════════
       IMAGING  (ImagingServiceRequest model)
       ═══════════════════════════════════════════ */

    /**
     * Create a single imaging service request.
     *
     * @param int         $serviceId    services.id
     * @param string|null $note         clinical note
     * @param int         $patientId    patients.id
     * @param int|null    $encounterId  encounters.id (null for nurse)
     * @param array       $extra        Extra fields
     * @return ImagingServiceRequest
     */
    protected function addSingleImaging(int $serviceId, ?string $note, int $patientId, ?int $encounterId, array $extra = []): ImagingServiceRequest
    {
        $imaging = new ImagingServiceRequest();
        $imaging->service_id   = $serviceId;
        $imaging->note         = $note;
        $imaging->patient_id   = $patientId;
        $imaging->encounter_id = $encounterId;
        $imaging->doctor_id    = Auth::id();

        if (isset($extra['service_request_id'])) {
            $imaging->service_request_id = $extra['service_request_id'];
        }
        if (isset($extra['status'])) {
            $imaging->status = $extra['status'];
        }
        if (isset($extra['billed_by'])) {
            $imaging->billed_by = $extra['billed_by'];
        }
        if (isset($extra['billed_date'])) {
            $imaging->billed_date = $extra['billed_date'];
        }

        $imaging->save();

        return $imaging;
    }

    /**
     * Update a single imaging's clinical note (debounced auto-save).
     */
    protected function updateSingleImagingNote(int $id, ?string $note): ImagingServiceRequest
    {
        $imaging = ImagingServiceRequest::findOrFail($id);
        $imaging->note = $note;
        $imaging->save();
        return $imaging;
    }

    /**
     * Remove a single imaging service request (validated + soft-delete with audit).
     *
     * @param int         $id
     * @param string|null $reason  Optional deletion reason
     * @throws \RuntimeException when deletion is not allowed
     */
    protected function removeSingleImaging(int $id, ?string $reason = null): void
    {
        $imaging = ImagingServiceRequest::findOrFail($id);
        $check = $this->canDeleteClinicalRequest('imaging', $imaging);

        if (!$check['allowed']) {
            throw new \RuntimeException($check['reason']);
        }

        $imaging->deleted_by      = Auth::id();
        $imaging->deletion_reason = $reason ?? 'Removed by requester';
        $imaging->save();
        $imaging->delete();
    }

    /* ═══════════════════════════════════════════
       PRESCRIPTIONS  (ProductRequest model)
       ═══════════════════════════════════════════ */

    /**
     * Create a single prescription (product request).
     *
     * @param int         $productId    products.id
     * @param string      $dose         pipe-delimited dose or empty
     * @param int         $patientId    patients.id
     * @param int|null    $encounterId  encounters.id (null for nurse)
     * @param array       $extra        Extra fields
     * @return ProductRequest
     */
    protected function addSinglePrescription(int $productId, ?string $dose, int $patientId, ?int $encounterId, array $extra = []): ProductRequest
    {
        $presc = new ProductRequest();
        $presc->product_id    = $productId;
        $presc->dose          = $dose ?? '';
        $presc->patient_id    = $patientId;
        $presc->encounter_id  = $encounterId;
        $presc->doctor_id     = Auth::id();

        if (isset($extra['status'])) {
            $presc->status = $extra['status'];
        }
        if (isset($extra['billed_by'])) {
            $presc->billed_by = $extra['billed_by'];
        }
        if (isset($extra['billed_date'])) {
            $presc->billed_date = $extra['billed_date'];
        }
        if (isset($extra['product_request_id'])) {
            $presc->product_request_id = $extra['product_request_id'];
        }

        $presc->save();

        return $presc;
    }

    /**
     * Update a single prescription's dose (debounced auto-save).
     */
    protected function updateSinglePrescriptionDose(int $id, ?string $dose): ProductRequest
    {
        $presc = ProductRequest::findOrFail($id);
        $presc->dose = $dose ?? '';
        $presc->save();
        return $presc;
    }

    /**
     * Remove a single prescription (validated + soft-delete with audit).
     */
    protected function removeSinglePrescription(int $id, ?string $reason = null): void
    {
        $presc = ProductRequest::findOrFail($id);
        $check = $this->canDeleteClinicalRequest('prescription', $presc);

        if (!$check['allowed']) {
            throw new \RuntimeException($check['reason']);
        }

        $presc->deleted_by      = Auth::id();
        $presc->deletion_reason = $reason ?? 'Removed by requester';
        $presc->save();
        $presc->delete();
    }

    /* ═══════════════════════════════════════════
       PROCEDURES (Procedure model)
       ═══════════════════════════════════════════ */

    /**
     * Add a single procedure and its billing entry.
     *
     * @param array       $data
     * @param int         $patientId
     * @param int|null    $encounterId
     * @param int|null    $admissionRequestId
     * @param array       $extra        Extra billing fields (is_bundle_item, parent_id)
     * @return Procedure
     */
    protected function addSingleProcedure(array $data, int $patientId, ?int $encounterId, ?int $admissionRequestId = null, array $extra = []): Procedure
    {
        $service = Service::with('price', 'procedureDefinition')->find($data['service_id']);

        if (!$service) {
            throw new \InvalidArgumentException('Service not found: ' . $data['service_id']);
        }

        // 1. Create Procedure record
        $procedure = new Procedure();
        $procedure->service_id       = $service->id;
        $procedure->patient_id       = $patientId;
        $procedure->encounter_id     = $encounterId;         // null for nurse
        $procedure->requested_by     = Auth::id();
        $procedure->requested_on     = now();
        $procedure->priority         = $data['priority'] ?? 'routine';
        $procedure->procedure_status = Procedure::STATUS_REQUESTED;
        $procedure->pre_notes        = $data['pre_notes'] ?? null;
        $procedure->pre_notes_by     = !empty($data['pre_notes']) ? Auth::id() : null;

        if (!empty($data['scheduled_date'])) {
            $procedure->scheduled_date   = $data['scheduled_date'];
            $procedure->procedure_status = Procedure::STATUS_SCHEDULED;
        }

        if ($service->procedureDefinition) {
            $procedure->procedure_definition_id = $service->procedureDefinition->id;
        }

        $procedure->save();

        // 2. Create billing entry (ProductOrServiceRequest)
        $basePrice = optional($service->price)->sale_price ?? 0;

        $coverage = null;
        if (!($extra['is_bundle_item'] ?? false)) {
            try {
                $coverage = HmoHelper::applyHmoTariff($patientId, null, $service->id);
            } catch (\Exception $e) {
                Log::warning('HmoHelper::applyHmoTariff failed: ' . $e->getMessage());
                $coverage = null;
            }
        }

        $patient = Patient::find($patientId);

        $billing = new ProductOrServiceRequest();
        $billing->type                 = 'service';
        $billing->service_id           = $service->id;
        $billing->user_id              = $patient->user_id;
        $billing->staff_user_id        = Auth::id();
        $billing->created_by           = Auth::id();
        $billing->order_date           = now();

        if ($encounterId) {
            $billing->encounter_id          = $encounterId;
            $billing->admission_request_id  = $admissionRequestId;
        }

        if ($extra['is_bundle_item'] ?? false) {
            $billing->payable_amount = 0;
            $billing->claims_amount  = 0;
            $billing->coverage_mode  = $extra['coverage_mode'] ?? 'none';
            $billing->parent_id      = $extra['parent_id'] ?? null;
            $billing->is_bundle_item = true;
        } elseif ($coverage && ($coverage['coverage_mode'] ?? '') === 'hmo') {
            $billing->payable_amount = $coverage['payable_amount'];
            $billing->claims_amount = $coverage['claims_amount'];
            $billing->coverage_mode = $coverage['coverage_mode'];
            $billing->hmo_id        = $coverage['hmo_id'] ?? null;
            $billing->validation_status = $coverage['validation_status'] ?? 'pending';
        } else {
            $billing->payable_amount = $basePrice;
            $billing->claims_amount = 0;
            $billing->coverage_mode = 'cash';
        }

        $billing->save();

        // 3. Link billing to procedure
        $procedure->product_or_service_request_id = $billing->id;
        $procedure->save();

        return $procedure;
    }

    /**
     * Remove a single procedure + its billing entry (validated + soft-delete with audit).
     */
    protected function removeSingleProcedure(int $id, ?string $reason = null): void
    {
        $procedure = Procedure::findOrFail($id);
        $check = $this->canDeleteClinicalRequest('procedure', $procedure);

        if (!$check['allowed']) {
            throw new \RuntimeException($check['reason']);
        }

        if ($procedure->product_or_service_request_id) {
            $billing = ProductOrServiceRequest::find($procedure->product_or_service_request_id);
            if ($billing && !$billing->payment_id) {
                $billing->delete();
            }
        }

        $procedure->cancellation_reason = $reason ?? 'Removed by requester';
        $procedure->cancelled_by        = Auth::id();
        $procedure->cancelled_at        = now();
        $procedure->procedure_status     = Procedure::STATUS_CANCELLED;
        $procedure->save();
        $procedure->delete();
    }

    /* ═══════════════════════════════════════════
       UTILITIES
       ═══════════════════════════════════════════ */

    /**
     * Check if a clinical request can be deleted.
     */
    protected function canDeleteClinicalRequest(string $type, $model): array
    {
        $editWindowMinutes = appsettings('note_edit_window', 30);
        $withinWindow = $model->created_at && now()->diffInMinutes($model->created_at) <= $editWindowMinutes;
        $isAuthor = Auth::id() == ($model->doctor_id ?? $model->requested_by);

        if (!$isAuthor) {
            return ['allowed' => false, 'reason' => 'You are not the author of this request.'];
        }

        if (!$withinWindow) {
            return ['allowed' => false, 'reason' => 'The edit/delete window has expired.'];
        }

        // Check if billed (except for procedures which have linked billing already)
        if ($type !== 'procedure') {
            if ($model->service_request_id || $model->product_request_id || $model->billed_by) {
                return ['allowed' => false, 'reason' => 'This request has already been billed.'];
            }
        } else {
            // For procedures, check if payment exists
            $billing = ProductOrServiceRequest::find($model->product_or_service_request_id);
            if ($billing && $billing->payment_id) {
                return ['allowed' => false, 'reason' => 'The billing entry for this procedure has been paid.'];
            }
        }

        return ['allowed' => true];
    }

    /**
     * Apply a treatment plan.
     */
    protected function applyTreatmentPlan(int $planId, int $patientId, ?int $encounterId): \Illuminate\Support\Collection
    {
        return DB::transaction(function () use ($planId, $patientId, $encounterId) {
            $plan = \App\Models\TreatmentPlan::with('items')->findOrFail($planId);
            $results = [
                'labs'          => [],
                'imaging'       => [],
                'prescriptions' => [],
                'procedures'    => [],
            ];

            foreach ($plan->items as $item) {
                switch ($item->item_type) {
                    case 'lab':
                        $results['labs'][] = $this->addSingleLab($item->reference_id, $item->note, $patientId, $encounterId);
                        break;
                    case 'imaging':
                        $results['imaging'][] = $this->addSingleImaging($item->reference_id, $item->note, $patientId, $encounterId);
                        break;
                    case 'product':
                        $results['prescriptions'][] = $this->addSinglePrescription($item->reference_id, $item->dose, $patientId, $encounterId);
                        break;
                    case 'procedure':
                        $results['procedures'][] = $this->addSingleProcedure([
                            'service_id' => $item->reference_id,
                            'priority'   => $item->priority ?? 'routine',
                            'pre_notes'  => $item->note,
                        ], $patientId, $encounterId);
                        break;
                }
            }

            return collect($results);
        });
    }

    /**
     * Fetch the last N encounters for a patient.
     */
    protected function recentEncountersForPatient(int $patientId, int $limit = 5, ?int $exceptId = null): \Illuminate\Support\Collection
    {
        $encounters = Encounter::where('patient_id', $patientId)
            ->when($exceptId, fn($q) => $q->where('id', '!=', $exceptId))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $encounters->map(function ($enc) {
            return [
                'id'         => $enc->id,
                'date'       => $enc->created_at->format('d M Y, h:i A'),
                'doctor'     => $enc->doctor_id ? userfullname($enc->doctor_id) : 'N/A',
                'lab_count'  => LabServiceRequest::where('encounter_id', $enc->id)->count(),
                'imaging_count' => ImagingServiceRequest::where('encounter_id', $enc->id)->count(),
                'rx_count'   => ProductRequest::where('encounter_id', $enc->id)->count(),
                'proc_count' => Procedure::where('encounter_id', $enc->id)->count(),
            ];
        });
    }

    /**
     * Apply a service combo.
     */
    protected function applyServiceCombo(Service $combo, int $patientId, ?int $encounterId): array
    {
        return DB::transaction(function () use ($combo, $patientId, $encounterId) {
            $patient = Patient::findOrFail($patientId);
            $staffId = Auth::id();

            // 1. Create Parent Billing Request
            $parentRequest = new ProductOrServiceRequest();
            $parentRequest->user_id       = $patient->user_id;
            $parentRequest->staff_user_id = $staffId;
            $parentRequest->created_by    = $staffId;
            $parentRequest->service_id    = $combo->id;
            $parentRequest->qty           = 1;
            $parentRequest->encounter_id  = $encounterId;
            $parentRequest->order_date    = now();

            try {
                $hmoData = HmoHelper::applyHmoTariff($patientId, null, $combo->id);
                if ($hmoData) {
                    $parentRequest->payable_amount   = $hmoData['payable_amount'];
                    $parentRequest->claims_amount    = $hmoData['claims_amount'];
                    $parentRequest->coverage_mode    = $hmoData['coverage_mode'];
                    $parentRequest->validation_status = $hmoData['validation_status'];
                }
            } catch (\Exception $e) {
                $price = ServicePrice::where('service_id', $combo->id)->value('sale_price') ?? 0;
                $parentRequest->payable_amount = $price;
                $parentRequest->coverage_mode  = 'cash';
            }
            $parentRequest->save();

            $results = ['parent' => $parentRequest, 'items' => []];

            foreach ($combo->bundleItems as $bundleItem) {
                $childRequest = null;
                $clinicalRecord = null;

                if ($bundleItem->item_type === 'service') {
                    $service = Service::find($bundleItem->item_id);
                    if (!$service) continue;

                    $childRequest = new ProductOrServiceRequest();
                    $childRequest->user_id        = $patient->user_id;
                    $childRequest->staff_user_id  = $staffId;
                    $childRequest->created_by     = $staffId;
                    $childRequest->service_id     = $service->id;
                    $childRequest->qty            = $bundleItem->qty;
                    $childRequest->encounter_id   = $encounterId;
                    $childRequest->payable_amount = 0;
                    $childRequest->claims_amount  = 0;
                    $childRequest->coverage_mode  = $parentRequest->coverage_mode;
                    $childRequest->parent_id      = $parentRequest->id;
                    $childRequest->is_bundle_item = true;
                    $childRequest->save();

                    if ($service->isLab()) {
                        $clinicalRecord = $this->addSingleLab($service->id, $bundleItem->note, $patientId, $encounterId, [
                            'service_request_id' => $childRequest->id,
                            'status'             => 2,
                            'billed_by'          => $staffId,
                            'billed_date'        => now()
                        ]);
                    } elseif ($service->isImaging()) {
                        $clinicalRecord = $this->addSingleImaging($service->id, $bundleItem->note, $patientId, $encounterId, [
                            'service_request_id' => $childRequest->id,
                            'status'             => 2,
                            'billed_by'          => $staffId,
                            'billed_date'        => now()
                        ]);
                    } elseif ($service->isProcedure()) {
                        $clinicalRecord = $this->addSingleProcedure([
                            'service_id' => $service->id,
                            'priority'   => 'routine',
                            'pre_notes'  => $bundleItem->note,
                        ], $patientId, $encounterId, null, [
                            'is_bundle_item' => true,
                            'parent_id'      => $parentRequest->id,
                            'coverage_mode'  => $parentRequest->coverage_mode
                        ]);
                    }
                } elseif ($bundleItem->item_type === 'product') {
                    $product = Product::find($bundleItem->item_id);
                    if (!$product) continue;

                    $childRequest = new ProductOrServiceRequest();
                    $childRequest->user_id        = $patient->user_id;
                    $childRequest->staff_user_id  = $staffId;
                    $childRequest->created_by     = $staffId;
                    $childRequest->product_id     = $product->id;
                    $childRequest->qty            = $bundleItem->qty;
                    $childRequest->encounter_id   = $encounterId;
                    $childRequest->payable_amount = 0;
                    $childRequest->claims_amount  = 0;
                    $childRequest->coverage_mode  = $parentRequest->coverage_mode;
                    $childRequest->parent_id      = $parentRequest->id;
                    $childRequest->is_bundle_item = true;
                    $childRequest->save();

                    $clinicalRecord = $this->addSinglePrescription($product->id, $bundleItem->dose, $patientId, $encounterId, [
                        'status'             => 2,
                        'billed_by'          => $staffId,
                        'billed_date'        => now(),
                        'product_request_id' => $childRequest->id
                    ]);
                }

                $results['items'][] = ['billing' => $childRequest, 'clinical' => $clinicalRecord];
            }

            return $results;
        });
    }

    /**
     * Remove a service combo (bundle) and mark items as removed
     * Only works if bundle hasn't been paid and items not delivered
     */
    protected function removeServiceCombo(int $parentRequestId): array
    {
        return DB::transaction(function () use ($parentRequestId) {
            $parent = ProductOrServiceRequest::findOrFail($parentRequestId);
            
            // Check if combo was already paid (payment_id not null means payment has been recorded)
            if ($parent->payment_id !== null) {
                return [
                    'success' => false,
                    'message' => 'Cannot remove paid combo. Contact billing for refund requests.'
                ];
            }

            $staffId = Auth::id();

            // Mark parent as removed
            $parent->update([
                'removed_by' => $staffId,
                'removed_at' => now()
            ]);

            // Mark all child items as removed
            $childCount = ProductOrServiceRequest::where('parent_id', $parent->id)
                ->update([
                    'removed_by' => $staffId,
                    'removed_at' => now()
                ]);

            return [
                'success' => true,
                'message' => "Combo removed successfully. {$childCount} items removed.",
                'parentRequestId' => $parent->id
            ];
        });
    }
}
