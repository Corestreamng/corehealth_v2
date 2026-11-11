<?php

namespace App\Interoperability\Transformers\Fhir;

use App\Interoperability\Helpers\InteropRouteHelper;
use App\Interoperability\Models\InteropExternalIdentifier;
use App\Models\Patient;

class PatientTransformer
{
    /**
     * Transform a CoreHealth Patient model to a standard HL7 FHIR R4 Patient resource.
     */
    public static function toFhir(Patient $patient): array
    {
        $patient->loadMissing(['user', 'hmo']);
        $user = $patient->user;

        $fhirId = InteropExternalIdentifier::getOrCreateUuid(
            'FHIR',
            'Patient',
            $patient->id,
            InteropRouteHelper::fhirRoute('Patient')
        );

        $identifiers = [];
        // CoreHealth Internal ID
        $identifiers[] = [
            'use' => 'usual',
            'type' => [
                'coding' => [
                    [
                        'system' => 'http://terminology.hl7.org/CodeSystem/v2-0203',
                        'code' => 'PI',
                        'display' => 'Patient Internal Identifier',
                    ],
                ],
            ],
            'system' => InteropRouteHelper::identifierSystem('patient_id'),
            'value' => (string) $patient->id,
        ];

        // Hospital Medical Record Number (MRN / file_no)
        if (!empty($patient->file_no)) {
            $identifiers[] = [
                'use' => 'official',
                'type' => [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/v2-0203',
                            'code' => 'MR',
                            'display' => 'Medical Record Number',
                        ],
                    ],
                ],
                'system' => InteropRouteHelper::identifierSystem('mrn'),
                'value' => $patient->file_no,
            ];
        }

        // HMO Insurance Number
        if (!empty($patient->hmo_no)) {
            $identifiers[] = [
                'use' => 'secondary',
                'type' => [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/v2-0203',
                            'code' => 'MB',
                            'display' => 'Member Number',
                        ],
                    ],
                ],
                'system' => $patient->hmo ? url('/hmo/' . $patient->hmo->id) : InteropRouteHelper::identifierSystem('hmo'),
                'value' => $patient->hmo_no,
            ];
        }

        // Names
        $givenNames = [];
        if ($user && !empty($user->firstname)) {
            $givenNames[] = $user->firstname;
        }
        if ($user && !empty($user->othername) && trim($user->othername) !== '') {
            $givenNames[] = trim($user->othername);
        }

        $names = [
            [
                'use' => 'official',
                'family' => $user->surname ?? 'Unknown',
                'given' => $givenNames,
                'text' => trim(($user->surname ?? '') . ' ' . ($user->firstname ?? '') . ' ' . ($user->othername ?? '')),
            ],
        ];

        // Telecom (Phone & Email)
        $telecom = [];
        $phone = $patient->phone_no ?? ($user->phone_number ?? null);
        if ($phone) {
            $telecom[] = [
                'system' => 'phone',
                'value' => $phone,
                'use' => 'mobile',
            ];
        }
        if ($user && !empty($user->email)) {
            $telecom[] = [
                'system' => 'email',
                'value' => $user->email,
                'use' => 'home',
            ];
        }

        // Gender mapping
        $gender = 'unknown';
        if ($patient->gender) {
            $lowerGender = strtolower(trim($patient->gender));
            if (in_array($lowerGender, ['male', 'm'])) {
                $gender = 'male';
            } elseif (in_array($lowerGender, ['female', 'f'])) {
                $gender = 'female';
            } elseif (in_array($lowerGender, ['other', 'transgender'])) {
                $gender = 'other';
            }
        }

        // Addresses
        $addresses = [];
        if (!empty($patient->address)) {
            $addresses[] = [
                'use' => 'home',
                'type' => 'physical',
                'text' => $patient->address,
                'line' => [$patient->address],
                'country' => $patient->nationality ?? 'Nigeria',
            ];
        }

        // Contacts (Next of Kin)
        $contacts = [];
        if (!empty($patient->next_of_kin_name)) {
            $contactTelecom = [];
            if (!empty($patient->next_of_kin_phone)) {
                $contactTelecom[] = [
                    'system' => 'phone',
                    'value' => $patient->next_of_kin_phone,
                    'use' => 'mobile',
                ];
            }
            $contacts[] = [
                'relationship' => [
                    [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/v2-0131',
                                'code' => 'N',
                                'display' => 'Next-of-Kin',
                            ],
                        ],
                    ],
                ],
                'name' => [
                    'text' => $patient->next_of_kin_name,
                ],
                'telecom' => $contactTelecom,
                'address' => !empty($patient->next_of_kin_address) ? [
                    'text' => $patient->next_of_kin_address,
                ] : null,
            ];
        }

        // Extensions (Blood Group & Genotype per Nigerian Clinical Standards)
        $extensions = [];
        if (!empty($patient->blood_group)) {
            $extensions[] = [
                'url' => 'http://corehealth.org/fhir/StructureDefinition/patient-bloodGroup',
                'valueString' => $patient->blood_group,
            ];
        }
        if (!empty($patient->genotype)) {
            $extensions[] = [
                'url' => 'http://corehealth.org/fhir/StructureDefinition/patient-genotype',
                'valueString' => $patient->genotype,
            ];
        }
        if (!empty($patient->ethnicity)) {
            $extensions[] = [
                'url' => 'http://corehealth.org/fhir/StructureDefinition/patient-ethnicity',
                'valueString' => $patient->ethnicity,
            ];
        }

        return [
            'resourceType' => 'Patient',
            'id' => $fhirId,
            'meta' => [
                'versionId' => (string) $patient->updated_at->timestamp,
                'lastUpdated' => $patient->updated_at->toIso8601String(),
                'profile' => ['http://hl7.org/fhir/StructureDefinition/Patient'],
            ],
            'identifier' => $identifiers,
            'active' => true,
            'name' => $names,
            'telecom' => $telecom,
            'gender' => $gender,
            'birthDate' => $patient->dob ? date('Y-m-d', strtotime($patient->dob)) : null,
            'address' => $addresses,
            'contact' => $contacts,
            'extension' => $extensions,
        ];
    }
}
