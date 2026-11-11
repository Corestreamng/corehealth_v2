<?php

namespace App\Interoperability\Transformers\Fhir;

use App\Interoperability\Helpers\InteropRouteHelper;
use App\Interoperability\Models\InteropExternalIdentifier;
use App\Models\Staff;
use App\Models\User;

class PractitionerTransformer
{
    /**
     * Transform a Staff or User model to FHIR R4 Practitioner.
     */
    public static function toFhir($staffOrUser): array
    {
        $user = ($staffOrUser instanceof Staff) ? $staffOrUser->user : $staffOrUser;
        $staff = ($staffOrUser instanceof Staff) ? $staffOrUser : ($user->staff ?? null);

        $fhirId = InteropExternalIdentifier::getOrCreateUuid(
            'FHIR',
            'Practitioner',
            $user->id,
            InteropRouteHelper::fhirRoute('Practitioner')
        );

        $identifiers = [
            [
                'use' => 'usual',
                'type' => [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/v2-0203',
                            'code' => 'EI',
                            'display' => 'Employee number',
                        ],
                    ],
                ],
                'system' => InteropRouteHelper::identifierSystem('staff_id'),
                'value' => (string) ($staff->employee_id ?? $user->id),
            ],
        ];

        $names = [
            [
                'use' => 'official',
                'prefix' => [$staff->title ?? 'Dr.'],
                'family' => $user->surname ?? '',
                'given' => array_filter([$user->firstname ?? '', $user->othername ?? '']),
                'text' => trim(($user->surname ?? '') . ' ' . ($user->firstname ?? '') . ' ' . ($user->othername ?? '')),
            ],
        ];

        $telecom = [];
        if (!empty($user->phone_number)) {
            $telecom[] = [
                'system' => 'phone',
                'value' => $user->phone_number,
                'use' => 'work',
            ];
        }
        if (!empty($user->email)) {
            $telecom[] = [
                'system' => 'email',
                'value' => $user->email,
                'use' => 'work',
            ];
        }

        $gender = 'unknown';
        if ($staff && !empty($staff->gender)) {
            $g = strtolower($staff->gender);
            $gender = in_array($g, ['male', 'm']) ? 'male' : (in_array($g, ['female', 'f']) ? 'female' : 'other');
        }

        return [
            'resourceType' => 'Practitioner',
            'id' => $fhirId,
            'meta' => [
                'lastUpdated' => $user->updated_at ? $user->updated_at->toIso8601String() : now()->toIso8601String(),
                'profile' => ['http://hl7.org/fhir/StructureDefinition/Practitioner'],
            ],
            'identifier' => $identifiers,
            'active' => true,
            'name' => $names,
            'telecom' => $telecom,
            'gender' => $gender,
        ];
    }
}
