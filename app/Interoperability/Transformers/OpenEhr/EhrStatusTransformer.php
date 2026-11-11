<?php

namespace App\Interoperability\Transformers\OpenEhr;

use App\Interoperability\Helpers\InteropRouteHelper;
use App\Interoperability\Models\InteropExternalIdentifier;
use App\Models\Patient;

class EhrStatusTransformer
{
    /**
     * Transform a Patient record into openEHR canonical EHR object.
     */
    public static function toEhr(Patient $patient): array
    {
        $ehrId = InteropExternalIdentifier::getOrCreateUuid(
            'OPENEHR',
            'Ehr',
            $patient->id,
            InteropRouteHelper::openEhrRoute('ehr')
        );

        $ehrStatusId = InteropExternalIdentifier::getOrCreateUuid(
            'OPENEHR',
            'EhrStatus',
            $patient->id,
            InteropRouteHelper::openEhrRoute('ehr_status')
        );

        return [
            'system_id' => [
                'value' => 'corehealth.hospital.ehr',
            ],
            'ehr_id' => [
                'value' => $ehrId,
            ],
            'ehr_status' => [
                '_type' => 'EHR_STATUS',
                'name' => [
                    '_type' => 'DV_TEXT',
                    'value' => 'EHR Status for Patient ' . ($patient->file_no ?? $patient->id),
                ],
                'uid' => [
                    '_type' => 'OBJECT_VERSION_ID',
                    'value' => "{$ehrStatusId}::corehealth.hospital.ehr::1",
                ],
                'subject' => [
                    'external_ref' => [
                        'id' => [
                            '_type' => 'GENERIC_ID',
                            'value' => (string) $patient->id,
                            'scheme' => 'corehealth_patient_id',
                        ],
                        'namespace' => 'corehealth',
                        'type' => 'PERSON',
                    ],
                ],
                'is_queryable' => true,
                'is_modifiable' => true,
            ],
            'time_created' => [
                'value' => $patient->created_at ? $patient->created_at->toIso8601String() : now()->toIso8601String(),
            ],
        ];
    }
}
