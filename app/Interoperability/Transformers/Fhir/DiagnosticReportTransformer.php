<?php

namespace App\Interoperability\Transformers\Fhir;

use App\Interoperability\Helpers\InteropRouteHelper;
use App\Interoperability\Models\InteropExternalIdentifier;
use App\Interoperability\Models\InteropTerminologyMap;
use App\Models\LabServiceRequest;

class DiagnosticReportTransformer
{
    /**
     * Transform a LabServiceRequest with results into an HL7 FHIR R4 DiagnosticReport resource.
     */
    public static function toFhir(LabServiceRequest $lab): array
    {
        $lab->loadMissing(['service', 'encounter', 'patient']);

        $fhirId = InteropExternalIdentifier::getOrCreateUuid(
            'FHIR',
            'DiagnosticReport',
            $lab->id,
            InteropRouteHelper::fhirRoute('DiagnosticReport')
        );

        $patientUuid = InteropExternalIdentifier::getOrCreateUuid(
            'FHIR',
            'Patient',
            $lab->patient_id,
            InteropRouteHelper::fhirRoute('Patient')
        );

        $encounterUuid = $lab->encounter_id ? InteropExternalIdentifier::getOrCreateUuid(
            'FHIR',
            'Encounter',
            $lab->encounter_id,
            InteropRouteHelper::fhirRoute('Encounter')
        ) : null;

        $loincMap = InteropTerminologyMap::where('standard', 'LOINC')
            ->where('local_entity_type', 'service')
            ->where('local_entity_id', $lab->service_id)
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
            'code' => (string) $lab->service_id,
            'display' => $lab->service->service_name ?? 'Diagnostic Test',
        ];

        // Status: registered | partial | preliminary | final | amended | corrected | appended | cancelled | entered-in-error | unknown
        $status = 'preliminary';
        if ($lab->status == 2 && !empty($lab->result)) {
            $status = 'final';
        } elseif ($lab->status == 3) {
            $status = 'cancelled';
        }

        $conclusion = is_array($lab->result) ? json_encode($lab->result) : (string) ($lab->result ?? 'Pending laboratory processing');

        return [
            'resourceType' => 'DiagnosticReport',
            'id' => $fhirId,
            'meta' => [
                'profile' => ['http://hl7.org/fhir/StructureDefinition/DiagnosticReport'],
                'lastUpdated' => $lab->updated_at ? $lab->updated_at->toIso8601String() : now()->toIso8601String(),
            ],
            'status' => $status,
            'category' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/v2-0074',
                            'code' => 'LAB',
                            'display' => 'Laboratory',
                        ],
                    ],
                ],
            ],
            'code' => [
                'coding' => $codings,
                'text' => $lab->service->service_name ?? 'Laboratory Investigation',
            ],
            'subject' => [
                'reference' => 'Patient/' . $patientUuid,
            ],
            'encounter' => $encounterUuid ? [
                'reference' => 'Encounter/' . $encounterUuid,
            ] : null,
            'effectiveDateTime' => $lab->sampled_at ?? ($lab->created_at ? $lab->created_at->toIso8601String() : now()->toIso8601String()),
            'issued' => $lab->approved_at ?? ($lab->updated_at ? $lab->updated_at->toIso8601String() : now()->toIso8601String()),
            'conclusion' => $conclusion,
        ];
    }
}
