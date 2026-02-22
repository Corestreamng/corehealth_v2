<?php

namespace App\Http\Traits;

use App\Models\LabServiceRequest;
use App\Models\ImagingServiceRequest;
use App\Models\ProductRequest;
use App\Models\Procedure;
use App\Models\ProductOrServiceRequest;
use App\Models\service;
use App\Models\patient;
use App\Helpers\HmoHelper;
use App\Models\Encounter;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ClinicalOrdersTrait — Shared single-item CRUD for clinical orders.
 *
 * Ref:  CLINICAL_ORDERS_PLAN.md §3.1
 *
 * Used by:
 *   - EncounterController   (doctor — encounter-bound, encounter_id set)
 *   - NursingWorkbenchController (nurse — patient-bound, encounter_id = null)
 *
 * Column references verified against models:
 *   LabServiceRequest:       service_id, note, patient_id, encounter_id, doctor_id, status, priority
 *   ImagingServiceRequest:   service_id, note, patient_id, encounter_id, doctor_id, status, priority
 *   ProductRequest:          product_id, dose, patient_id, encounter_id, doctor_id
 *   Procedure:               service_id, patient_id, encounter_id, requested_by, requested_on,
 *                            priority, procedure_status, pre_notes, pre_notes_by,
 *                            scheduled_date, procedure_definition_id, product_or_service_request_id
 *   ProductOrServiceRequest: type, service_id, user_id, staff_user_id, encounter_id,
 *                            admission_request_id, created_by, order_date,
 *                            amount, claims_amount, coverage_mode, hmo_id
 */
trait ClinicalOrdersTrait
{
    /* ═══════════════════════════════════════════
       LABS  (LabServiceRequest model)
       ═══════════════════════════════════════════ */

    /**
     * Create a single lab service request.
     *
     * @param int         $serviceId    services.id
     * @param string|null $note         clinical note
     * @param int         $patientId    patients.id
     * @param int|null    $encounterId  encounters.id (null for nurse)
     * @return LabServiceRequest
     */
    protected function addSingleLab(int $serviceId, ?string $note, int $patientId, ?int $encounterId): LabServiceRequest
    {
        $lab = new LabServiceRequest();
        $lab->service_id   = $serviceId;
        $lab->note         = $note;
        $lab->patient_id   = $patientId;
        $lab->encounter_id = $encounterId; // null for nurse
        $lab->doctor_id    = Auth::id();
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
     * Remove a single lab service request (owned by current user).
     */
    protected function removeSingleLab(int $id): void
    {
        LabServiceRequest::where('id', $id)
            ->where('doctor_id', Auth::id())
            ->delete();
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
     * @return ImagingServiceRequest
     */
    protected function addSingleImaging(int $serviceId, ?string $note, int $patientId, ?int $encounterId): ImagingServiceRequest
    {
        $imaging = new ImagingServiceRequest();
        $imaging->service_id   = $serviceId;
        $imaging->note         = $note;
        $imaging->patient_id   = $patientId;
        $imaging->encounter_id = $encounterId;
        $imaging->doctor_id    = Auth::id();
        $imaging->save();

        return $imaging;
    }

    /**
     * Update a single imaging request's clinical note (debounced auto-save).
     */
    protected function updateSingleImagingNote(int $id, ?string $note): ImagingServiceRequest
    {
        $imaging = ImagingServiceRequest::findOrFail($id);
        $imaging->note = $note;
        $imaging->save();
        return $imaging;
    }

    /**
     * Remove a single imaging service request (owned by current user).
     */
    protected function removeSingleImaging(int $id): void
    {
        ImagingServiceRequest::where('id', $id)
            ->where('doctor_id', Auth::id())
            ->delete();
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
     * @return ProductRequest
     */
    protected function addSinglePrescription(int $productId, ?string $dose, int $patientId, ?int $encounterId): ProductRequest
    {
        $presc = new ProductRequest();
        $presc->product_id    = $productId;
        $presc->dose          = $dose ?? '';
        $presc->patient_id    = $patientId;
        $presc->encounter_id  = $encounterId;
        $presc->doctor_id     = Auth::id();
        $presc->save();

        return $presc;
    }

    /**
     * Update a prescription's dose (for debounced auto-save).
     * Ref: Plan §4.3 — Two-phase medication save.
     */
    protected function updatePrescriptionDose(int $id, string $dose): ProductRequest
    {
        $presc = ProductRequest::findOrFail($id);
        $presc->dose = $dose;
        $presc->save();

        return $presc;
    }

    /**
     * Remove a single prescription (owned by current user).
     */
    protected function removeSinglePrescription(int $id): void
    {
        ProductRequest::where('id', $id)
            ->where('doctor_id', Auth::id())
            ->delete();
    }

    /* ═══════════════════════════════════════════
       PROCEDURES  (Procedure + ProductOrServiceRequest models)
       Billing logic extracted from existing:
         - EncounterController::saveProcedures()      (line 2790)
         - NursingWorkbenchController::saveNurseProcedures() (line 4651)
       ═══════════════════════════════════════════ */

    /**
     * Create a single procedure with billing entry.
     *
     * @param array    $data  { service_id, priority, scheduled_date?, pre_notes? }
     * @param int      $patientId
     * @param int|null $encounterId        null for nurse
     * @param int|null $admissionRequestId null for nurse
     * @return Procedure
     */
    protected function addSingleProcedure(array $data, int $patientId, ?int $encounterId, ?int $admissionRequestId = null): Procedure
    {
        $service = service::with('price', 'procedureDefinition')->find($data['service_id']);

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
        $procedure->priority         = $data['priority'];
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
        try {
            $coverage = HmoHelper::applyHmoTariff($patientId, null, $service->id);
        } catch (\Exception $e) {
            Log::warning('HmoHelper::applyHmoTariff failed for patient ' . $patientId . ', service ' . $service->id . ': ' . $e->getMessage());
            $coverage = null;
        }

        $patient = patient::find($patientId);

        $billing = new ProductOrServiceRequest();
        $billing->type                 = 'service';
        $billing->service_id           = $service->id;
        $billing->user_id              = $patient->user_id;
        $billing->staff_user_id        = Auth::id();
        $billing->created_by           = Auth::id();
        $billing->order_date           = now();

        // Only set encounter/admission on billing if available (doctor has them, nurse doesn't)
        if ($encounterId) {
            $billing->encounter_id          = $encounterId;
            $billing->admission_request_id  = $admissionRequestId;
        }

        if ($coverage && ($coverage['coverage_mode'] ?? '') === 'hmo') {
            $billing->amount        = $coverage['payable_amount'];
            $billing->claims_amount = $coverage['claims_amount'];
            $billing->coverage_mode = 'hmo';
            $billing->hmo_id        = $coverage['hmo_id'] ?? null;
        } else {
            $billing->amount        = $basePrice;
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
     * Remove a single procedure + its billing entry (owned by current user).
     */
    protected function removeSingleProcedure(int $id): void
    {
        $procedure = Procedure::where('id', $id)
            ->where('requested_by', Auth::id())
            ->first();

        if ($procedure) {
            // Also remove billing entry if linked
            if ($procedure->product_or_service_request_id) {
                ProductOrServiceRequest::where('id', $procedure->product_or_service_request_id)->delete();
            }
            $procedure->delete();
        }
    }

    /* ═══════════════════════════════════════════
       RE-PRESCRIBE FROM PREVIOUS ENCOUNTERS  (Plan §5.1)
       ═══════════════════════════════════════════ */

    /**
     * Re-prescribe (copy) items from a previous encounter/history into the current one.
     *
     * @param string      $type          'labs' | 'imaging' | 'prescriptions' | 'procedures'
     * @param array       $sourceIds     IDs of the original records to copy
     * @param int         $patientId     Target patient
     * @param int|null    $encounterId   Target encounter (null for nurse)
     * @param array       $doseOverrides Optional dose overrides keyed by source ID
     * @return \Illuminate\Support\Collection  Newly created records
     */
    protected function rePrescribeItems(
        string $type,
        array  $sourceIds,
        int    $patientId,
        ?int   $encounterId,
        array  $doseOverrides = []
    ): \Illuminate\Support\Collection {
        return DB::transaction(function () use ($type, $sourceIds, $patientId, $encounterId, $doseOverrides) {
            $created = collect();

            switch ($type) {
                case 'labs':
                    $originals = LabServiceRequest::whereIn('id', $sourceIds)->get();
                    foreach ($originals as $orig) {
                        $new = $this->addSingleLab(
                            $orig->service_id,
                            $orig->note,
                            $patientId,
                            $encounterId
                        );
                        $created->push($new);
                    }
                    break;

                case 'imaging':
                    $originals = ImagingServiceRequest::whereIn('id', $sourceIds)->get();
                    foreach ($originals as $orig) {
                        $new = $this->addSingleImaging(
                            $orig->service_id,
                            $orig->note,
                            $patientId,
                            $encounterId
                        );
                        $created->push($new);
                    }
                    break;

                case 'prescriptions':
                    $originals = ProductRequest::whereIn('id', $sourceIds)->get();
                    foreach ($originals as $orig) {
                        $dose = $doseOverrides[$orig->id] ?? $orig->dose;
                        $new = $this->addSinglePrescription(
                            $orig->product_id,
                            $dose ?? '',
                            $patientId,
                            $encounterId
                        );
                        $created->push($new);
                    }
                    break;

                case 'procedures':
                    $originals = Procedure::whereIn('id', $sourceIds)->get();
                    foreach ($originals as $orig) {
                        $new = $this->addSingleProcedure(
                            [
                                'service_id'     => $orig->service_id,
                                'priority'       => $orig->priority ?? 'routine',
                                'pre_notes'      => $orig->pre_notes,
                                'scheduled_date' => null,
                            ],
                            $patientId,
                            $encounterId
                        );
                        $created->push($new);
                    }
                    break;

                default:
                    throw new \InvalidArgumentException("Unsupported re-prescribe type: {$type}");
            }

            return $created;
        });
    }

    /* ═══════════════════════════════════════════
       APPLY TREATMENT PLAN  (Plan §6.3)
       ═══════════════════════════════════════════ */

    /**
     * Apply a treatment plan — creates real orders for each plan item.
     *
     * @param \App\Models\TreatmentPlan $plan
     * @param int                       $patientId
     * @param int|null                  $encounterId  null for nurse
     * @param array                     $selectedItemIds  Optional subset — if empty, apply all items
     * @return \Illuminate\Support\Collection  Newly created records, keyed by type
     */
    protected function applyTreatmentPlan(
        \App\Models\TreatmentPlan $plan,
        int $patientId,
        ?int $encounterId,
        array $selectedItemIds = []
    ): \Illuminate\Support\Collection {
        $items = $plan->items;
        if (!empty($selectedItemIds)) {
            $items = $items->whereIn('id', $selectedItemIds);
        }

        return DB::transaction(function () use ($items, $patientId, $encounterId) {
            $results = ['labs' => [], 'imaging' => [], 'prescriptions' => [], 'procedures' => []];

            foreach ($items as $item) {
                switch ($item->item_type) {
                    case 'lab':
                        $record = $this->addSingleLab($item->reference_id, $item->note, $patientId, $encounterId);
                        $results['labs'][] = $record;
                        break;
                    case 'imaging':
                        $record = $this->addSingleImaging($item->reference_id, $item->note, $patientId, $encounterId);
                        $results['imaging'][] = $record;
                        break;
                    case 'medication':
                        $record = $this->addSinglePrescription($item->reference_id, $item->dose ?? '', $patientId, $encounterId);
                        $results['prescriptions'][] = $record;
                        break;
                    case 'procedure':
                        $record = $this->addSingleProcedure(
                            [
                                'service_id'     => $item->reference_id,
                                'priority'       => $item->priority ?? 'routine',
                                'pre_notes'      => $item->note,
                                'scheduled_date' => null,
                            ],
                            $patientId,
                            $encounterId
                        );
                        $results['procedures'][] = $record;
                        break;
                }
            }

            return collect($results);
        });
    }

    /* ═══════════════════════════════════════════
       RECENT ENCOUNTERS (Plan §5.3)
       ═══════════════════════════════════════════ */

    /**
     * Fetch the last N encounters for a patient with item counts per type.
     * Used by "Re-prescribe from encounter" dropdown.
     *
     * @param int $patientId
     * @param int $limit            defaults to 5
     * @param int|null $exceptId    encounter ID to exclude (current encounter)
     * @return \Illuminate\Support\Collection
     */
    protected function recentEncountersForPatient(int $patientId, int $limit = 5, ?int $exceptId = null): \Illuminate\Support\Collection
    {
        $encounters = Encounter::where('patient_id', $patientId)
            ->when($exceptId, fn($q) => $q->where('id', '!=', $exceptId))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'created_at', 'doctor_id']);

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
     * Get all items from a specific encounter, grouped by type.
     * Used for "Re-prescribe all from encounter" feature.
     *
     * @param int $encounterId
     * @return array  ['labs' => [...], 'imaging' => [...], 'prescriptions' => [...], 'procedures' => [...]]
     */
    protected function getEncounterItems(int $encounterId): array
    {
        $labs = LabServiceRequest::where('encounter_id', $encounterId)
            ->with('service:id,service_name,service_code')
            ->get(['id', 'service_id', 'note']);

        $imaging = ImagingServiceRequest::where('encounter_id', $encounterId)
            ->with('service:id,service_name,service_code')
            ->get(['id', 'service_id', 'note']);

        $prescriptions = ProductRequest::where('encounter_id', $encounterId)
            ->with('product:id,product_name')
            ->get(['id', 'product_id', 'dose']);

        $procedures = Procedure::where('encounter_id', $encounterId)
            ->get(['id', 'service_id', 'priority', 'pre_notes']);

        return [
            'labs' => $labs->map(fn($l) => [
                'id' => $l->id,
                'service_id' => $l->service_id,
                'name' => optional($l->service)->service_name ?? 'Unknown',
                'note' => $l->note,
            ])->toArray(),
            'imaging' => $imaging->map(fn($i) => [
                'id' => $i->id,
                'service_id' => $i->service_id,
                'name' => optional($i->service)->service_name ?? 'Unknown',
                'note' => $i->note,
            ])->toArray(),
            'prescriptions' => $prescriptions->map(fn($p) => [
                'id' => $p->id,
                'product_id' => $p->product_id,
                'name' => optional($p->product)->product_name ?? 'Unknown',
                'dose' => $p->dose,
            ])->toArray(),
            'procedures' => $procedures->map(function ($proc) {
                $svc = service::find($proc->service_id);
                return [
                    'id'         => $proc->id,
                    'service_id' => $proc->service_id,
                    'name'       => optional($svc)->service_name ?? 'Unknown',
                    'priority'   => $proc->priority,
                    'note'       => $proc->pre_notes,
                ];
            })->toArray(),
        ];
    }
}
