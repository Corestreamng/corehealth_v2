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
}
