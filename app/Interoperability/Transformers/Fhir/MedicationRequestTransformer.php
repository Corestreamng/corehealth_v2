<?php

namespace App\Interoperability\Transformers\Fhir;

use App\Interoperability\Helpers\InteropRouteHelper;
use App\Interoperability\Models\InteropExternalIdentifier;
use App\Interoperability\Models\InteropTerminologyMap;
use App\Models\ProductRequest;

class MedicationRequestTransformer
{
    /**
     * Transform a ProductRequest model to an HL7 FHIR R4 MedicationRequest resource.
     */
    public static function toFhir(ProductRequest $productRequest): array
    {
        $productRequest->loadMissing(['product', 'encounter']);

        $fhirId = InteropExternalIdentifier::getOrCreateUuid(
            'FHIR',
            'MedicationRequest',
            $productRequest->id,
            InteropRouteHelper::fhirRoute('MedicationRequest')
        );

        $patientUuid = InteropExternalIdentifier::getOrCreateUuid(
            'FHIR',
            'Patient',
            $productRequest->patient_id,
            InteropRouteHelper::fhirRoute('Patient')
        );

        $encounterUuid = $productRequest->encounter_id ? InteropExternalIdentifier::getOrCreateUuid(
            'FHIR',
            'Encounter',
            $productRequest->encounter_id,
            InteropRouteHelper::fhirRoute('Encounter')
        ) : null;

        // Lookup WHO ATC terminology mapping
        $atcMap = InteropTerminologyMap::where('standard', 'ATC')
            ->where('local_entity_type', 'product')
            ->where('local_entity_id', $productRequest->product_id)
            ->first();

        $codings = [];
        if ($atcMap) {
            $codings[] = [
                'system' => 'http://www.whocc.no/atc',
                'code' => $atcMap->standard_code,
                'display' => $atcMap->standard_display,
            ];
        }

        $codings[] = [
            'system' => url('/products'),
            'code' => (string) $productRequest->product_id,
            'display' => $productRequest->product->product_name ?? 'Medication',
        ];

        // Status mapping
        $status = 'active';
        if ($productRequest->status == 2) {
            $status = 'completed'; // Dispensed
        } elseif ($productRequest->status == 3) {
            $status = 'cancelled';
        }

        return [
            'resourceType' => 'MedicationRequest',
            'id' => $fhirId,
            'meta' => [
                'profile' => ['http://hl7.org/fhir/StructureDefinition/MedicationRequest'],
                'lastUpdated' => $productRequest->updated_at ? $productRequest->updated_at->toIso8601String() : now()->toIso8601String(),
            ],
            'status' => $status,
            'intent' => 'order',
            'medicationCodeableConcept' => [
                'coding' => $codings,
                'text' => $productRequest->product->product_name ?? 'Medication',
            ],
            'subject' => [
                'reference' => 'Patient/' . $patientUuid,
            ],
            'encounter' => $encounterUuid ? [
                'reference' => 'Encounter/' . $encounterUuid,
            ] : null,
            'authoredOn' => $productRequest->created_at ? $productRequest->created_at->toIso8601String() : now()->toIso8601String(),
            'dosageInstruction' => [
                [
                    'text' => $productRequest->dose ?? 'As directed by physician',
                ],
            ],
        ];
    }
}
