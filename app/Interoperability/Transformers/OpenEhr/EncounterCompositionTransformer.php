<?php

namespace App\Interoperability\Transformers\OpenEhr;

use App\Interoperability\Helpers\InteropRouteHelper;
use App\Interoperability\Models\InteropExternalIdentifier;
use App\Models\Encounter;
use App\Models\VitalSign;

class EncounterCompositionTransformer
{
    /**
     * Transform a CoreHealth Encounter into a canonical openEHR COMPOSITION.encounter.v1.
     */
    public static function toComposition(Encounter $encounter): array
    {
        $encounter->loadMissing(['patient', 'doctor', 'treatmentPlan', 'productRequests.product', 'labRequests.service']);

        $compositionId = InteropExternalIdentifier::getOrCreateUuid(
            'OPENEHR',
            'Composition',
            $encounter->id,
            InteropRouteHelper::openEhrRoute('composition')
        );

        $content = [];

        // Latest vitals for this patient / encounter
        $vital = VitalSign::where('patient_id', $encounter->patient_id)
            ->where('created_at', '<=', $encounter->created_at->addDay())
            ->orderBy('id', 'desc')
            ->first();

        if ($vital) {
            // Blood Pressure Archetype (openEHR-EHR-OBSERVATION.blood_pressure.v2)
            if (!empty($vital->blood_pressure) && str_contains($vital->blood_pressure, '/')) {
                $parts = explode('/', trim($vital->blood_pressure));
                $systolic = isset($parts[0]) && is_numeric(trim($parts[0])) ? (float) trim($parts[0]) : null;
                $diastolic = isset($parts[1]) && is_numeric(trim($parts[1])) ? (float) trim($parts[1]) : null;

                if ($systolic !== null && $diastolic !== null) {
                    $content[] = [
                        '_type' => 'OBSERVATION',
                        'name' => ['_type' => 'DV_TEXT', 'value' => 'Blood pressure'],
                        'archetype_node_id' => 'openEHR-EHR-OBSERVATION.blood_pressure.v2',
                        'data' => [
                            '_type' => 'HISTORY',
                            'archetype_node_id' => 'at0001',
                            'events' => [
                                [
                                    '_type' => 'POINT_EVENT',
                                    'archetype_node_id' => 'at0002',
                                    'time' => ['value' => $vital->created_at ? $vital->created_at->toIso8601String() : now()->toIso8601String()],
                                    'data' => [
                                        '_type' => 'ITEM_TREE',
                                        'archetype_node_id' => 'at0003',
                                        'items' => [
                                            [
                                                '_type' => 'ELEMENT',
                                                'archetype_node_id' => 'at0004',
                                                'name' => ['_type' => 'DV_TEXT', 'value' => 'Systolic'],
                                                'value' => [
                                                    '_type' => 'DV_QUANTITY',
                                                    'magnitude' => $systolic,
                                                    'units' => 'mm[Hg]',
                                                ],
                                            ],
                                            [
                                                '_type' => 'ELEMENT',
                                                'archetype_node_id' => 'at0005',
                                                'name' => ['_type' => 'DV_TEXT', 'value' => 'Diastolic'],
                                                'value' => [
                                                    '_type' => 'DV_QUANTITY',
                                                    'magnitude' => $diastolic,
                                                    'units' => 'mm[Hg]',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ];
                }
            }

            // Pulse / Heart Rate Archetype (openEHR-EHR-OBSERVATION.pulse.v2)
            if (!empty($vital->heart_rate) && is_numeric($vital->heart_rate)) {
                $content[] = [
                    '_type' => 'OBSERVATION',
                    'name' => ['_type' => 'DV_TEXT', 'value' => 'Pulse/Heart beat'],
                    'archetype_node_id' => 'openEHR-EHR-OBSERVATION.pulse.v2',
                    'data' => [
                        '_type' => 'HISTORY',
                        'archetype_node_id' => 'at0002',
                        'events' => [
                            [
                                '_type' => 'POINT_EVENT',
                                'archetype_node_id' => 'at0003',
                                'time' => ['value' => $vital->created_at ? $vital->created_at->toIso8601String() : now()->toIso8601String()],
                                'data' => [
                                    '_type' => 'ITEM_TREE',
                                    'archetype_node_id' => 'at0001',
                                    'items' => [
                                        [
                                            '_type' => 'ELEMENT',
                                            'archetype_node_id' => 'at0004',
                                            'name' => ['_type' => 'DV_TEXT', 'value' => 'Rate'],
                                            'value' => [
                                                '_type' => 'DV_QUANTITY',
                                                'magnitude' => (float) $vital->heart_rate,
                                                'units' => '/min',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];
            }

            // Temperature Archetype (openEHR-EHR-OBSERVATION.body_temperature.v2)
            if (!empty($vital->temp) && is_numeric($vital->temp)) {
                $content[] = [
                    '_type' => 'OBSERVATION',
                    'name' => ['_type' => 'DV_TEXT', 'value' => 'Body temperature'],
                    'archetype_node_id' => 'openEHR-EHR-OBSERVATION.body_temperature.v2',
                    'data' => [
                        '_type' => 'HISTORY',
                        'archetype_node_id' => 'at0002',
                        'events' => [
                            [
                                '_type' => 'POINT_EVENT',
                                'archetype_node_id' => 'at0003',
                                'time' => ['value' => $vital->created_at ? $vital->created_at->toIso8601String() : now()->toIso8601String()],
                                'data' => [
                                    '_type' => 'ITEM_TREE',
                                    'archetype_node_id' => 'at0001',
                                    'items' => [
                                        [
                                            '_type' => 'ELEMENT',
                                            'archetype_node_id' => 'at0004',
                                            'name' => ['_type' => 'DV_TEXT', 'value' => 'Temperature'],
                                            'value' => [
                                                '_type' => 'DV_QUANTITY',
                                                'magnitude' => (float) $vital->temp,
                                                'units' => 'Cel',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];
            }
        }

        // Diagnosis / Problems (openEHR-EHR-EVALUATION.problem_diagnosis.v1)
        if ($encounter->treatmentPlan) {
            $plan = $encounter->treatmentPlan;
            $content[] = [
                '_type' => 'EVALUATION',
                'name' => ['_type' => 'DV_TEXT', 'value' => 'Problem/Diagnosis'],
                'archetype_node_id' => 'openEHR-EHR-EVALUATION.problem_diagnosis.v1',
                'data' => [
                    '_type' => 'ITEM_TREE',
                    'archetype_node_id' => 'at0001',
                    'items' => [
                        [
                            '_type' => 'ELEMENT',
                            'archetype_node_id' => 'at0002',
                            'name' => ['_type' => 'DV_TEXT', 'value' => 'Problem/Diagnosis name'],
                            'value' => [
                                '_type' => 'DV_TEXT',
                                'value' => $plan->plan_name ?? 'Clinical Diagnosis',
                            ],
                        ],
                    ],
                ],
            ];
        }

        // Medications (openEHR-EHR-INSTRUCTION.medication_order.v3)
        if ($encounter->productRequests) {
            foreach ($encounter->productRequests as $rx) {
                $content[] = [
                    '_type' => 'INSTRUCTION',
                    'name' => ['_type' => 'DV_TEXT', 'value' => 'Medication order'],
                    'archetype_node_id' => 'openEHR-EHR-INSTRUCTION.medication_order.v3',
                    'activities' => [
                        [
                            '_type' => 'ACTIVITY',
                            'archetype_node_id' => 'at0001',
                            'description' => [
                                '_type' => 'ITEM_TREE',
                                'archetype_node_id' => 'at0002',
                                'items' => [
                                    [
                                        '_type' => 'ELEMENT',
                                        'archetype_node_id' => 'at0070',
                                        'name' => ['_type' => 'DV_TEXT', 'value' => 'Medication item'],
                                        'value' => [
                                            '_type' => 'DV_TEXT',
                                            'value' => $rx->product->product_name ?? 'Medication',
                                        ],
                                    ],
                                    [
                                        '_type' => 'ELEMENT',
                                        'archetype_node_id' => 'at0044',
                                        'name' => ['_type' => 'DV_TEXT', 'value' => 'Dose directions'],
                                        'value' => [
                                            '_type' => 'DV_TEXT',
                                            'value' => $rx->dose ?? '1 stat',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];
            }
        }

        return [
            '_type' => 'COMPOSITION',
            'archetype_node_id' => 'openEHR-EHR-COMPOSITION.encounter.v1',
            'name' => [
                '_type' => 'DV_TEXT',
                'value' => 'Clinical Encounter',
            ],
            'uid' => [
                '_type' => 'OBJECT_VERSION_ID',
                'value' => "{$compositionId}::corehealth.hospital.ehr::1",
            ],
            'language' => [
                '_type' => 'CODE_PHRASE',
                'terminology_id' => ['value' => 'ISO_639-1'],
                'code_string' => 'en',
            ],
            'territory' => [
                '_type' => 'CODE_PHRASE',
                'terminology_id' => ['value' => 'ISO_3166-1'],
                'code_string' => 'NG',
            ],
            'category' => [
                '_type' => 'DV_CODED_TEXT',
                'value' => 'event',
                'defining_code' => [
                    '_type' => 'CODE_PHRASE',
                    'terminology_id' => ['value' => 'openehr'],
                    'code_string' => '433',
                ],
            ],
            'composer' => [
                '_type' => 'PARTY_IDENTIFIED',
                'name' => $encounter->doctor ? userfullname($encounter->doctor->id) : 'Attending Physician',
            ],
            'context' => [
                '_type' => 'EVENT_CONTEXT',
                'start_time' => [
                    '_type' => 'DV_DATE_TIME',
                    'value' => $encounter->started_at ? $encounter->started_at->toIso8601String() : $encounter->created_at->toIso8601String(),
                ],
                'setting' => [
                    '_type' => 'DV_CODED_TEXT',
                    'value' => 'other care',
                    'defining_code' => [
                        '_type' => 'CODE_PHRASE',
                        'terminology_id' => ['value' => 'openehr'],
                        'code_string' => '238',
                    ],
                ],
            ],
            'content' => $content,
        ];
    }
}
