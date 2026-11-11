<?php

namespace App\Interoperability\Transformers\Fhir;

use App\Interoperability\Helpers\InteropRouteHelper;
use App\Interoperability\Models\InteropExternalIdentifier;
use App\Models\Encounter;

class EncounterTransformer
{
    /**
     * Transform a CoreHealth Encounter model into an HL7 FHIR R4 Encounter resource.
     */
    public static function toFhir(Encounter $encounter): array
    {
        $encounter->loadMissing(['patient', 'doctor', 'treatmentPlan']);

        $fhirId = InteropExternalIdentifier::getOrCreateUuid(
            'FHIR',
            'Encounter',
            $encounter->id,
            InteropRouteHelper::fhirRoute('Encounter')
        );

        $patientUuid = InteropExternalIdentifier::getOrCreateUuid(
            'FHIR',
            'Patient',
            $encounter->patient_id,
            InteropRouteHelper::fhirRoute('Patient')
        );

        $practitionerUuid = $encounter->doctor_id ? InteropExternalIdentifier::getOrCreateUuid(
            'FHIR',
            'Practitioner',
            $encounter->doctor_id,
            InteropRouteHelper::fhirRoute('Practitioner')
        ) : null;

        // Status mapping
        $status = 'in-progress';
        if ($encounter->end_consultation || $encounter->closed_at) {
            $status = 'finished';
        }

        // Diagnosis / Conditions
        $diagnoses = [];
        if ($encounter->treatmentPlan) {
            $diagnoses[] = [
                'condition' => [
                    'display' => $encounter->treatmentPlan->plan_name ?? 'Clinical Diagnosis',
                ],
                'use' => [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/diagnosis-role',
                            'code' => 'AD',
                            'display' => 'Admission diagnosis',
                        ],
                    ],
                ],
            ];
        }

        return [
            'resourceType' => 'Encounter',
            'id' => $fhirId,
            'meta' => [
                'profile' => ['http://hl7.org/fhir/StructureDefinition/Encounter'],
                'lastUpdated' => $encounter->updated_at ? $encounter->updated_at->toIso8601String() : now()->toIso8601String(),
            ],
            'status' => $status,
            'class' => [
                'system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                'code' => 'AMB',
                'display' => 'ambulatory',
            ],
            'subject' => [
                'reference' => 'Patient/' . $patientUuid,
            ],
            'participant' => $practitionerUuid ? [
                [
                    'individual' => [
                        'reference' => 'Practitioner/' . $practitionerUuid,
                    ],
                ],
            ] : [],
            'period' => [
                'start' => $encounter->started_at ? $encounter->started_at->toIso8601String() : $encounter->created_at->toIso8601String(),
                'end' => $encounter->closed_at ? $encounter->closed_at->toIso8601String() : null,
            ],
            'reasonCode' => !empty($encounter->reasons_for_encounter) ? [
                [
                    'text' => $encounter->reasons_for_encounter,
                ],
            ] : [],
            'diagnosis' => $diagnoses,
        ];
    }
}
