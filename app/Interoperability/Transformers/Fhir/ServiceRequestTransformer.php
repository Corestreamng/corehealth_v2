<?php

namespace App\Interoperability\Transformers\Fhir;

use App\Interoperability\Helpers\InteropRouteHelper;
use App\Interoperability\Models\InteropExternalIdentifier;
use App\Interoperability\Models\InteropTerminologyMap;
use App\Models\LabServiceRequest;

class ServiceRequestTransformer
{
    /**
     * Transform a LabServiceRequest into an HL7 FHIR R4 ServiceRequest resource.
     */
    public static function toFhir(LabServiceRequest $request): array
    {
        $request->loadMissing(['service', 'encounter']);

        $fhirId = InteropExternalIdentifier::getOrCreateUuid(
            'FHIR',
            'ServiceRequest',
            $request->id,
            InteropRouteHelper::fhirRoute('ServiceRequest')
        );

        $patientUuid = InteropExternalIdentifier::getOrCreateUuid(
            'FHIR',
            'Patient',
            $request->patient_id,
            InteropRouteHelper::fhirRoute('Patient')
        );

        $encounterUuid = $request->encounter_id ? InteropExternalIdentifier::getOrCreateUuid(
            'FHIR',
            'Encounter',
            $request->encounter_id,
            InteropRouteHelper::fhirRoute('Encounter')
        ) : null;

        // Terminology lookup
        $loincMap = InteropTerminologyMap::where('standard', 'LOINC')
            ->where('local_entity_type', 'service')
            ->where('local_entity_id', $request->service_id)
            ->first();

        $codings = [];
        if ($loincMap) {
            $codings[] = [
                'system' => 'http://loinc.org',
                'code' => $loincMap->standard_code,
                'display' => $loincMap->standard_display,
            ];
        }

        $codings[] = [
            'system' => url('/services'),
            'code' => (string) $request->service_id,
            'display' => $request->service->service_name ?? 'Diagnostic Service',
        ];

        // Status mapping
        $status = 'active';
        if ($request->status == 2) {
            $status = 'completed';
        } elseif ($request->status == 3) {
            $status = 'revoked';
        }

        return [
            'resourceType' => 'ServiceRequest',
            'id' => $fhirId,
            'meta' => [
                'profile' => ['http://hl7.org/fhir/StructureDefinition/ServiceRequest'],
                'lastUpdated' => $request->updated_at ? $request->updated_at->toIso8601String() : now()->toIso8601String(),
            ],
            'status' => $status,
            'intent' => 'order',
            'category' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://snomed.info/sct',
                            'code' => '108252007',
                            'display' => 'Laboratory procedure',
                        ],
                    ],
                ],
            ],
            'code' => [
                'coding' => $codings,
                'text' => $request->service->service_name ?? 'Laboratory Service',
            ],
            'subject' => [
                'reference' => 'Patient/' . $patientUuid,
            ],
            'encounter' => $encounterUuid ? [
                'reference' => 'Encounter/' . $encounterUuid,
            ] : null,
            'authoredOn' => $request->created_at ? $request->created_at->toIso8601String() : now()->toIso8601String(),
            'note' => !empty($request->note) ? [
                ['text' => $request->note],
            ] : [],
        ];
    }
}
