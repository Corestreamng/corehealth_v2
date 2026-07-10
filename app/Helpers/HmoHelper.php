<?php

namespace App\Helpers;

use App\Models\HmoTariff;
use App\Models\Patient;

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
        $patient = Patient::with('hmo')->find($patientId);

        if (!$patient || !$patient->hmo_id) {
            return null; // Not an HMO patient
        }

        // Check for Tariff Override
        $overrideData = self::getTariffOverrideData($patient->hmo_id, $patient->hmo->hmo_scheme_id ?? null, $productId, $serviceId);
        if ($overrideData) {
            return $overrideData;
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
     * Batch-load tariff previews for unbilled items.
     * Returns a map keyed by product_id or service_id.
     *
     * @param int $hmoId
     * @param array $productIds
     * @param array $serviceIds
     * @return array ['products' => [product_id => tariff], 'services' => [service_id => tariff]]
     */
    public static function batchPreviewTariffs($hmoId, array $productIds = [], array $serviceIds = [])
    {
        $result = ['products' => [], 'services' => []];

        if (!$hmoId) {
            return $result;
        }

        $hmo = \App\Models\Hmo::find($hmoId);
        $schemeId = $hmo->hmo_scheme_id ?? null;

        if (!empty($productIds)) {
            foreach ($productIds as $productId) {
                $overrideData = self::getTariffOverrideData($hmoId, $schemeId, $productId, null);
                if ($overrideData) {
                    $result['products'][$productId] = [
                        'payable_amount' => (float) $overrideData['payable_amount'],
                        'claims_amount'  => (float) $overrideData['claims_amount'],
                        'coverage_mode'  => $overrideData['coverage_mode'],
                    ];
                }
            }
            
            // Get non-overridden products
            $remainingProductIds = array_diff($productIds, array_keys($result['products']));
            if (!empty($remainingProductIds)) {
                $tariffs = HmoTariff::where('hmo_id', $hmoId)
                    ->whereIn('product_id', $remainingProductIds)
                    ->whereNull('service_id')
                    ->get();
                foreach ($tariffs as $t) {
                    $result['products'][$t->product_id] = [
                        'payable_amount' => (float) $t->payable_amount,
                        'claims_amount'  => (float) $t->claims_amount,
                        'coverage_mode'  => $t->coverage_mode,
                    ];
                }
            }
        }

        if (!empty($serviceIds)) {
            foreach ($serviceIds as $serviceId) {
                $overrideData = self::getTariffOverrideData($hmoId, $schemeId, null, $serviceId);
                if ($overrideData) {
                    $result['services'][$serviceId] = [
                        'payable_amount' => (float) $overrideData['payable_amount'],
                        'claims_amount'  => (float) $overrideData['claims_amount'],
                        'coverage_mode'  => $overrideData['coverage_mode'],
                    ];
                }
            }
            
            // Get non-overridden services
            $remainingServiceIds = array_diff($serviceIds, array_keys($result['services']));
            if (!empty($remainingServiceIds)) {
                $tariffs = HmoTariff::where('hmo_id', $hmoId)
                    ->whereIn('service_id', $remainingServiceIds)
                    ->whereNull('product_id')
                    ->get();
                foreach ($tariffs as $t) {
                    $result['services'][$t->service_id] = [
                        'payable_amount' => (float) $t->payable_amount,
                        'claims_amount'  => (float) $t->claims_amount,
                        'coverage_mode'  => $t->coverage_mode,
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Check if a TariffOverride applies and calculate the overridden tariff
     */
    private static function getTariffOverrideData($hmoId, $schemeId, $productId, $serviceId)
    {
        $targetType = $productId ? 'product' : 'service';
        $targetId = $productId ?: $serviceId;
        $categoryId = null;
        $basePrice = 0;

        if ($productId) {
            $product = \App\Models\Product::with('price')->find($productId);
            if (!$product) return null;
            $categoryId = $product->category_id;
            
            // Use cost from latest active stock batch, fallback to product price
            $batch = \App\Models\StockBatch::active()->where('product_id', $productId)->latest()->first();
            if ($batch && $batch->cost_price > 0) {
                $basePrice = $batch->cost_price;
            } else {
                $basePrice = $product->price->current_sale_price ?? 0;
            }
        } else {
            $service = \App\Models\Service::with('price')->find($serviceId);
            if (!$service) return null;
            $categoryId = $service->category_id;
            $basePrice = $service->price->sale_price ?? 0;
        }

        $overrides = \App\Models\TariffOverride::where('is_active', 1)
            ->where(function($q) use ($hmoId, $schemeId) {
                $q->where('hmo_id', $hmoId)
                  ->orWhere('hmo_scheme_id', $schemeId);
            })
            ->where(function($q) use ($targetType, $targetId, $categoryId) {
                $q->where(function($q2) use ($targetType, $targetId) {
                    $q2->where('target_type', $targetType)->where('target_id', $targetId);
                })
                ->orWhere(function($q2) use ($targetType, $categoryId) {
                    $q2->where('target_type', $targetType . '_category')->where('target_id', $categoryId);
                });
            })
            ->get();

        if ($overrides->isEmpty()) return null;

        // Sort overrides by priority:
        // 1. Exact HMO + Exact Product/Service
        // 2. Exact HMO + Category
        // 3. Scheme + Exact Product/Service
        // 4. Scheme + Category
        $override = $overrides->sortBy(function($o) use ($targetType) {
            $score = 0;
            if ($o->hmo_id) $score += 10;
            if ($o->target_type === $targetType) $score += 5;
            return -$score;
        })->first();

        if (!$override) return null;

        // Calculate payable amount
        $payableAmount = 0;
        if ($override->override_type === 'percentage') {
            $payableAmount = $basePrice * ($override->amount / 100);
        } else {
            $payableAmount = $override->amount;
        }

        $claimsAmount = max(0, $basePrice - $payableAmount);

        // Fallback coverage mode to express
        $coverageMode = 'express';
        $existingTariff = \App\Models\HmoTariff::where('hmo_id', $hmoId)
            ->where(function($q) use ($productId, $serviceId) {
                if ($productId) {
                    $q->where('product_id', $productId)->whereNull('service_id');
                } else {
                    $q->where('service_id', $serviceId)->whereNull('product_id');
                }
            })->first();
        
        if ($existingTariff) {
            $coverageMode = $existingTariff->coverage_mode;
        }

        return [
            'payable_amount' => round($payableAmount, 2),
            'claims_amount' => round($claimsAmount, 2),
            'coverage_mode' => $coverageMode,
            'validation_status' => $coverageMode === 'express' ? 'approved' : 'pending',
        ];
    }

    /**
     * Get the display name for a product/service in HMO context.
     *
     * If the HMO tariff has a custom display_name, use that;
     * otherwise fall back to the original product_name / service_name.
     *
     * @param \App\Models\ProductOrServiceRequest $request  Must have product/service eager-loaded
     * @param \App\Models\HmoTariff|null          $tariff   Optionally pass a pre-loaded tariff to avoid extra query
     * @return string
     */
    public static function getDisplayName($request, $tariff = null)
    {
        // Try the pre-loaded tariff first
        if ($tariff && !empty($tariff->display_name)) {
            return $tariff->display_name;
        }

        // If no tariff passed, look it up (only when HMO context exists)
        if (!$tariff && $request->hmo_id) {
            $lookedUp = HmoTariff::where('hmo_id', $request->hmo_id)
                ->when($request->product_id, fn($q) => $q->where('product_id', $request->product_id)->whereNull('service_id'))
                ->when($request->service_id, fn($q) => $q->where('service_id', $request->service_id)->whereNull('product_id'))
                ->value('display_name');

            if ($lookedUp) {
                return $lookedUp;
            }
        }

        // Fallback to original product/service name
        if ($request->product_id && $request->product) {
            return $request->product->product_name ?? 'N/A';
        }
        if ($request->service_id && $request->service) {
            return $request->service->service_name ?? 'N/A';
        }

        return 'N/A';
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

        // If claims amount is 0 or null, HMO has nothing to validate — allow access
        if (empty($request->claims_amount) || $request->claims_amount <= 0) {
            return true;
        }

        // Primary and Secondary - must be approved or awaiting_code (validated but auth code pending)
        if (in_array($request->coverage_mode, ['primary', 'secondary'])) {
            return in_array($request->validation_status, ['approved', 'awaiting_code']);
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
        // 1. If this is a bundle item, check the parent's status (Service Combo System)
        if ($request->is_bundle_item && $request->parent_id) {
            $parent = $request->parent;
            if ($parent) {
                $parentStatus = self::canDeliverService($parent);
                if (!$parentStatus['can_deliver']) {
                    return [
                        'can_deliver' => false,
                        'reason' => 'Bundle Payment/Approval Required',
                        'hint' => 'This item is part of a bundle. ' . $parentStatus['hint']
                    ];
                }
            }
        }

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

            // awaiting_code = validated by officer, auth code pending — allow delivery
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
        // 1. Check for Service Combo (New System)
        $posr = null;
        switch ($itemType) {
            case 'lab':
                $posr = \App\Models\LabServiceRequest::find($itemId)?->productOrServiceRequest;
                break;
            case 'imaging':
                $posr = \App\Models\ImagingServiceRequest::find($itemId)?->productOrServiceRequest;
                break;
            case 'product':
                $posr = \App\Models\ProductRequest::find($itemId)?->productOrServiceRequest;
                break;
        }

        if ($posr && $posr->is_bundle_item && $posr->parent_id) {
            $parent = $posr->parent;
            return [
                'is_bundled' => true,
                'procedure_id' => $parent->id,
                'procedure_name' => optional($parent->service)->service_name ?? 'Service Bundle',
                'procedure_item' => $parent, // ProductOrServiceRequest model
                'bundle_type' => 'service_combo'
            ];
        }

        // 2. Fallback to Procedure Item (Legacy/Surgical System)
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
            'procedure_item' => $procedureItem, // ProcedureItem model
            'bundle_type' => 'legacy_procedure'
        ];
    }

    /**
     * Check if a bundled item can be delivered based on procedure payment status
     * Spec Reference: PROCEDURE_MODULE_DESIGN_PLAN.md Part 3.7, Task 80
     *
     * @param \App\Models\ProcedureItem $procedureItem
     * @return array ['can_deliver' => bool, 'reason' => string, 'hint' => string]
     */
    public static function canDeliverBundledItem($item)
    {
        // 1. If passed a ProductOrServiceRequest (Service Combo System), use canDeliverService
        if ($item instanceof \App\Models\ProductOrServiceRequest) {
            return self::canDeliverService($item);
        }

        // 2. Logic for ProcedureItem (Legacy/Surgical System)
        $procedureItem = $item;
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
