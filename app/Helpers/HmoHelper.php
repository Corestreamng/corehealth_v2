<?php

namespace App\Helpers;

use App\Models\HmoTariff;
use App\Models\patient;

class HmoHelper
{
    /**
     * Apply HMO tariff to a ProductOrServiceRequest
     *
     * @param int $patientId
     * @param int|null $productId
     * @param int|null $serviceId
     * @return array ['payable_amount', 'claims_amount', 'coverage_mode', 'validation_status'] or null
     * @throws \Exception If tariff not found for HMO patient
     */
    public static function applyHmoTariff($patientId, $productId = null, $serviceId = null)
    {
        $patient = patient::find($patientId);

        if (!$patient || !$patient->hmo_id) {
            return null; // Not an HMO patient
        }

        $tariff = HmoTariff::where('hmo_id', $patient->hmo_id)
            ->where(function($q) use ($productId, $serviceId) {
                if ($productId) {
                    $q->where('product_id', $productId)->whereNull('service_id');
                } else {
                    $q->where('service_id', $serviceId)->whereNull('product_id');
                }
            })
            ->first();

        if (!$tariff) {
            throw new \Exception("No HMO tariff found for this service/product. Please contact administrator.");
        }

        return [
            'payable_amount' => $tariff->payable_amount,
            'claims_amount' => $tariff->claims_amount,
            'coverage_mode' => $tariff->coverage_mode,
            'validation_status' => $tariff->coverage_mode === 'express' ? 'approved' : 'pending',
        ];
    }

    /**
     * Check if a patient can access a service based on HMO validation status
     *
     * @param \App\Models\ProductOrServiceRequest $request
     * @return bool
     */
    public static function canPatientAccessService($request)
    {
        // Non-HMO patients can always access
        if (is_null($request->coverage_mode)) {
            return true;
        }

        // Express coverage - auto approved
        if ($request->coverage_mode === 'express') {
            return true;
        }

        // Primary and Secondary - must be approved
        if (in_array($request->coverage_mode, ['primary', 'secondary'])) {
            return $request->validation_status === 'approved';
        }

        return false;
    }

    /**
     * Check if a service can be delivered based on payment and HMO validation
     *
     * @param \App\Models\ProductOrServiceRequest $request
     * @return array ['can_deliver' => bool, 'reason' => string, 'hint' => string]
     */
    public static function canDeliverService($request)
    {
        // Check if payable_amount exists and is greater than 0 but not paid
        if ($request->payable_amount !== null && $request->payable_amount > 0) {
            if (!$request->payment_id) {
                return [
                    'can_deliver' => false,
                    'reason' => 'Payment Required',
                    'hint' => sprintf(
                        'This service requires payment of ₦%s. Please complete payment before delivery.',
                        number_format($request->payable_amount, 2)
                    )
                ];
            }
        }

        // Check if claims_amount exists and is greater than 0 but not validated
        if ($request->claims_amount !== null && $request->claims_amount > 0) {
            if ($request->validation_status === 'pending') {
                return [
                    'can_deliver' => false,
                    'reason' => 'HMO Validation Pending',
                    'hint' => sprintf(
                        'This service requires HMO validation (Coverage: %s, Claims: ₦%s). Please wait for HMO executive approval.',
                        strtoupper($request->coverage_mode ?? 'N/A'),
                        number_format($request->claims_amount, 2)
                    )
                ];
            }

            if ($request->validation_status === 'rejected') {
                return [
                    'can_deliver' => false,
                    'reason' => 'HMO Validation Rejected',
                    'hint' => sprintf(
                        'This service was rejected by HMO. Rejection reason: %s. Contact HMO executive for clarification.',
                        $request->validation_notes ?? 'No reason provided'
                    )
                ];
            }
        }

        // All checks passed
        return [
            'can_deliver' => true,
            'reason' => 'Ready for Delivery',
            'hint' => 'This service is ready to be delivered.'
        ];
    }

    /**
     * Check if an item is part of a bundled procedure
     * Spec Reference: PROCEDURE_MODULE_DESIGN_PLAN.md Part 3.7, Task 79
     *
     * @param string $itemType ('lab', 'imaging', 'product')
     * @param int $itemId
     * @return array|null ['is_bundled' => bool, 'procedure_id' => int|null, 'procedure_name' => string|null]
     */
    public static function isBundledItem($itemType, $itemId)
    {
        $procedureItem = null;

        switch ($itemType) {
            case 'lab':
                $procedureItem = \App\Models\ProcedureItem::where('lab_service_request_id', $itemId)->first();
                break;
            case 'imaging':
                $procedureItem = \App\Models\ProcedureItem::where('imaging_service_request_id', $itemId)->first();
                break;
            case 'product':
                $procedureItem = \App\Models\ProcedureItem::where('product_request_id', $itemId)->first();
                break;
        }

        if (!$procedureItem) {
            return null;
        }

        $procedure = $procedureItem->procedure;
        $procedureName = $procedure ? optional($procedure->service)->service_name : null;

        return [
            'is_bundled' => $procedureItem->is_bundled,
            'procedure_id' => $procedureItem->procedure_id,
            'procedure_name' => $procedureName,
            'procedure_item' => $procedureItem,
        ];
    }

    /**
     * Check if a bundled item can be delivered based on procedure payment status
     * Spec Reference: PROCEDURE_MODULE_DESIGN_PLAN.md Part 3.7, Task 80
     *
     * @param \App\Models\ProcedureItem $procedureItem
     * @return array ['can_deliver' => bool, 'reason' => string, 'hint' => string]
     */
    public static function canDeliverBundledItem($procedureItem)
    {
        // If not bundled, use normal delivery check
        if (!$procedureItem->is_bundled) {
            // Non-bundled items have their own billing entry
            if ($procedureItem->productOrServiceRequest) {
                return self::canDeliverService($procedureItem->productOrServiceRequest);
            }
            return [
                'can_deliver' => true,
                'reason' => 'Ready for Delivery',
                'hint' => 'This item is ready to be delivered.'
            ];
        }

        // For bundled items, check the procedure's billing status
        $procedure = $procedureItem->procedure;

        if (!$procedure) {
            return [
                'can_deliver' => false,
                'reason' => 'Procedure Not Found',
                'hint' => 'The parent procedure for this bundled item could not be found.'
            ];
        }

        // Check if procedure is cancelled first
        if ($procedure->procedure_status === 'cancelled') {
            return [
                'can_deliver' => false,
                'reason' => 'Procedure Cancelled',
                'hint' => 'Cannot deliver items for a cancelled procedure.'
            ];
        }

        $procedureRequest = $procedure->productOrServiceRequest;

        // Handle legacy procedures without billing entry
        // Bundled items don't get billed separately - they're included in the procedure price
        // For legacy entries, allow delivery if procedure is in progress or completed
        if (!$procedureRequest) {
            $procedureName = optional($procedure->service)->service_name ?? 'Procedure';

            // Allow delivery for legacy procedures that are in progress or completed
            $allowedStatuses = ['in_progress', 'completed'];
            if (in_array($procedure->procedure_status, $allowedStatuses)) {
                return [
                    'can_deliver' => true,
                    'reason' => 'Legacy Procedure - Ready',
                    'hint' => sprintf(
                        'This item is bundled with "%s" (legacy entry - no billing record). Procedure status: %s.',
                        $procedureName,
                        ucfirst(str_replace('_', ' ', $procedure->procedure_status))
                    )
                ];
            }

            // For other statuses (requested, scheduled), wait for procedure to start
            return [
                'can_deliver' => false,
                'reason' => 'Procedure Not Started',
                'hint' => sprintf(
                    'This item is bundled with "%s". The procedure must be in progress or completed before bundled items can be delivered. Current status: %s.',
                    $procedureName,
                    ucfirst(str_replace('_', ' ', $procedure->procedure_status ?? 'unknown'))
                )
            ];
        }

        // Use the standard delivery check on the procedure's billing entry
        $deliveryCheck = self::canDeliverService($procedureRequest);

        if (!$deliveryCheck['can_deliver']) {
            $procedureName = optional($procedure->service)->service_name ?? 'Procedure';
            $deliveryCheck['hint'] = sprintf(
                'This item is bundled with "%s". %s',
                $procedureName,
                $deliveryCheck['hint']
            );
        }

        return $deliveryCheck;
    }
}
