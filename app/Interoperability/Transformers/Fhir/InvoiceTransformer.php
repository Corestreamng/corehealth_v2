<?php

namespace App\Interoperability\Transformers\Fhir;

use App\Interoperability\Helpers\InteropRouteHelper;
use App\Interoperability\Models\InteropExternalIdentifier;
use App\Models\ProductOrServiceRequest;

class InvoiceTransformer
{
    /**
     * Transform a ProductOrServiceRequest / billing line into an HL7 FHIR R4 Invoice resource.
     */
    public static function toFhir(ProductOrServiceRequest $bill): array
    {
        $bill->loadMissing(['user.patient_profile', 'service', 'product']);

        $fhirId = InteropExternalIdentifier::getOrCreateUuid(
            'FHIR',
            'Invoice',
            $bill->id,
            InteropRouteHelper::fhirRoute('Invoice')
        );

        $patient = $bill->user ? $bill->user->patient_profile : null;
        $patientUuid = $patient ? InteropExternalIdentifier::getOrCreateUuid(
            'FHIR',
            'Patient',
            $patient->id,
            InteropRouteHelper::fhirRoute('Patient')
        ) : null;

        $title = $bill->service->service_name ?? ($bill->product->product_name ?? 'Hospital Charge');
        $amount = (float) ($bill->payable_amount ?? ($bill->service->price->sale_price ?? 0));

        // Status: draft | issued | balanced | cancelled | entered-in-error
        $status = 'issued';
        if ($bill->payment_id) {
            $status = 'balanced'; // Paid
        }

        return [
            'resourceType' => 'Invoice',
            'id' => $fhirId,
            'meta' => [
                'profile' => ['http://hl7.org/fhir/StructureDefinition/Invoice'],
                'lastUpdated' => $bill->updated_at ? $bill->updated_at->toIso8601String() : now()->toIso8601String(),
            ],
            'status' => $status,
            'subject' => $patientUuid ? [
                'reference' => 'Patient/' . $patientUuid,
            ] : null,
            'date' => $bill->created_at ? $bill->created_at->toIso8601String() : now()->toIso8601String(),
            'totalNet' => [
                'value' => $amount,
                'currency' => 'NGN',
            ],
            'totalGross' => [
                'value' => $amount,
                'currency' => 'NGN',
            ],
            'lineItem' => [
                [
                    'sequence' => 1,
                    'chargeItemCodeableConcept' => [
                        'text' => $title,
                    ],
                    'priceComponent' => [
                        [
                            'type' => 'base',
                            'amount' => [
                                'value' => $amount,
                                'currency' => 'NGN',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
