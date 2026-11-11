<?php

namespace App\Interoperability\Transformers\Fhir;

use App\Interoperability\Helpers\InteropRouteHelper;
use App\Interoperability\Models\InteropExternalIdentifier;
use App\Models\VitalSign;

class ObservationTransformer
{
    /**
     * Transform a VitalSign record into an HL7 FHIR R4 Observation resource (Vital Signs Panel).
     */
    public static function toFhir(VitalSign $vital): array
    {
        $fhirId = InteropExternalIdentifier::getOrCreateUuid(
            'FHIR',
            'Observation',
            $vital->id,
            InteropRouteHelper::fhirRoute('Observation')
        );

        $patientUuid = InteropExternalIdentifier::getOrCreateUuid(
            'FHIR',
            'Patient',
            $vital->patient_id,
            InteropRouteHelper::fhirRoute('Patient')
        );

        $components = [];

        // 1. Blood Pressure Components (Systolic & Diastolic)
        if (!empty($vital->blood_pressure) && str_contains($vital->blood_pressure, '/')) {
            $parts = explode('/', trim($vital->blood_pressure));
            $systolic = isset($parts[0]) && is_numeric(trim($parts[0])) ? (float) trim($parts[0]) : null;
            $diastolic = isset($parts[1]) && is_numeric(trim($parts[1])) ? (float) trim($parts[1]) : null;

            if ($systolic !== null) {
                $components[] = [
                    'code' => [
                        'coding' => [
                            [
                                'system' => 'http://loinc.org',
                                'code' => '8480-6',
                                'display' => 'Systolic blood pressure',
                            ],
                        ],
                    ],
                    'valueQuantity' => [
                        'value' => $systolic,
                        'unit' => 'mmHg',
                        'system' => 'http://unitsofmeasure.org',
                        'code' => 'mm[Hg]',
                    ],
                ];
            }

            if ($diastolic !== null) {
                $components[] = [
                    'code' => [
                        'coding' => [
                            [
                                'system' => 'http://loinc.org',
                                'code' => '8462-4',
                                'display' => 'Diastolic blood pressure',
                            ],
                        ],
                    ],
                    'valueQuantity' => [
                        'value' => $diastolic,
                        'unit' => 'mmHg',
                        'system' => 'http://unitsofmeasure.org',
                        'code' => 'mm[Hg]',
                    ],
                ];
            }
        }

        // 2. Heart Rate
        if (!empty($vital->heart_rate) && is_numeric($vital->heart_rate)) {
            $components[] = [
                'code' => [
                    'coding' => [
                        [
                            'system' => 'http://loinc.org',
                            'code' => '8867-4',
                            'display' => 'Heart rate',
                        ],
                    ],
                ],
                'valueQuantity' => [
                    'value' => (float) $vital->heart_rate,
                    'unit' => 'beats/min',
                    'system' => 'http://unitsofmeasure.org',
                    'code' => '/min',
                ],
            ];
        }

        // 3. Body Temperature
        if (!empty($vital->temp) && is_numeric($vital->temp)) {
            $components[] = [
                'code' => [
                    'coding' => [
                        [
                            'system' => 'http://loinc.org',
                            'code' => '8310-5',
                            'display' => 'Body temperature',
                        ],
                    ],
                ],
                'valueQuantity' => [
                    'value' => (float) $vital->temp,
                    'unit' => '°C',
                    'system' => 'http://unitsofmeasure.org',
                    'code' => 'Cel',
                ],
            ];
        }

        // 4. Respiratory Rate
        if (!empty($vital->resp_rate) && is_numeric($vital->resp_rate)) {
            $components[] = [
                'code' => [
                    'coding' => [
                        [
                            'system' => 'http://loinc.org',
                            'code' => '9279-1',
                            'display' => 'Respiratory rate',
                        ],
                    ],
                ],
                'valueQuantity' => [
                    'value' => (float) $vital->resp_rate,
                    'unit' => 'breaths/min',
                    'system' => 'http://unitsofmeasure.org',
                    'code' => '/min',
                ],
            ];
        }

        // 5. Body Weight
        if (!empty($vital->weight) && is_numeric($vital->weight)) {
            $components[] = [
                'code' => [
                    'coding' => [
                        [
                            'system' => 'http://loinc.org',
                            'code' => '29463-7',
                            'display' => 'Body weight',
                        ],
                    ],
                ],
                'valueQuantity' => [
                    'value' => (float) $vital->weight,
                    'unit' => 'kg',
                    'system' => 'http://unitsofmeasure.org',
                    'code' => 'kg',
                ],
            ];
        }

        // 6. Body Height
        if (!empty($vital->height) && is_numeric($vital->height)) {
            $components[] = [
                'code' => [
                    'coding' => [
                        [
                            'system' => 'http://loinc.org',
                            'code' => '8302-2',
                            'display' => 'Body height',
                        ],
                    ],
                ],
                'valueQuantity' => [
                    'value' => (float) $vital->height,
                    'unit' => 'cm',
                    'system' => 'http://unitsofmeasure.org',
                    'code' => 'cm',
                ],
            ];
        }

        // 7. BMI
        if (!empty($vital->bmi) && is_numeric($vital->bmi)) {
            $components[] = [
                'code' => [
                    'coding' => [
                        [
                            'system' => 'http://loinc.org',
                            'code' => '39156-5',
                            'display' => 'Body mass index (BMI) [Ratio]',
                        ],
                    ],
                ],
                'valueQuantity' => [
                    'value' => (float) $vital->bmi,
                    'unit' => 'kg/m²',
                    'system' => 'http://unitsofmeasure.org',
                    'code' => 'kg/m2',
                ],
            ];
        }

        // 8. Oxygen Saturation (SpO2)
        if (!empty($vital->spo2) && is_numeric($vital->spo2)) {
            $components[] = [
                'code' => [
                    'coding' => [
                        [
                            'system' => 'http://loinc.org',
                            'code' => '59408-5',
                            'display' => 'Oxygen saturation in Arterial blood by Pulse oximetry',
                        ],
                    ],
                ],
                'valueQuantity' => [
                    'value' => (float) $vital->spo2,
                    'unit' => '%',
                    'system' => 'http://unitsofmeasure.org',
                    'code' => '%',
                ],
            ];
        }

        // 9. Blood Sugar
        if (!empty($vital->blood_sugar) && is_numeric($vital->blood_sugar)) {
            $components[] = [
                'code' => [
                    'coding' => [
                        [
                            'system' => 'http://loinc.org',
                            'code' => '2339-0',
                            'display' => 'Glucose [Mass/volume] in Blood',
                        ],
                    ],
                ],
                'valueQuantity' => [
                    'value' => (float) $vital->blood_sugar,
                    'unit' => 'mg/dL',
                    'system' => 'http://unitsofmeasure.org',
                    'code' => 'mg/dL',
                ],
            ];
        }

        // 10. Pain Score
        if (!empty($vital->pain_score) && is_numeric($vital->pain_score)) {
            $components[] = [
                'code' => [
                    'coding' => [
                        [
                            'system' => 'http://loinc.org',
                            'code' => '72514-3',
                            'display' => 'Pain severity - 0-10 verbal numeric rating [Score] - Reported',
                        ],
                    ],
                ],
                'valueInteger' => (int) $vital->pain_score,
            ];
        }

        return [
            'resourceType' => 'Observation',
            'id' => $fhirId,
            'meta' => [
                'profile' => ['http://hl7.org/fhir/StructureDefinition/vitalsigns'],
                'lastUpdated' => $vital->updated_at ? $vital->updated_at->toIso8601String() : now()->toIso8601String(),
            ],
            'status' => 'final',
            'category' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/observation-category',
                            'code' => 'vital-signs',
                            'display' => 'Vital Signs',
                        ],
                    ],
                ],
            ],
            'code' => [
                'coding' => [
                    [
                        'system' => 'http://loinc.org',
                        'code' => '85354-9',
                        'display' => 'Blood pressure panel with all children optional',
                    ],
                ],
                'text' => 'Vital Signs Measurement Set',
            ],
            'subject' => [
                'reference' => 'Patient/' . $patientUuid,
            ],
            'effectiveDateTime' => $vital->time_taken ?? ($vital->created_at ? $vital->created_at->toIso8601String() : now()->toIso8601String()),
            'component' => $components,
            'note' => !empty($vital->other_notes) ? [
                ['text' => $vital->other_notes],
            ] : [],
        ];
    }
}
