<?php

namespace App\Interoperability\Transformers\Fhir;

use App\Interoperability\Helpers\InteropRouteHelper;

class CapabilityStatementTransformer
{
    /**
     * Generate the official FHIR R4 CapabilityStatement metadata.
     */
    public static function build(): array
    {
        return [
            'resourceType' => 'CapabilityStatement',
            'id' => 'corehealth-fhir-r4',
            'url' => InteropRouteHelper::fhirRoute('metadata'),
            'version' => '2.6.0',
            'name' => 'CoreHealthFHIRCapabilityStatement',
            'title' => 'CoreHealth v2 Interoperability FHIR R4 Server',
            'status' => 'active',
            'date' => now()->toIso8601String(),
            'publisher' => 'CoreHealth Clinical Informatics Working Group',
            'kind' => 'instance',
            'software' => [
                'name' => 'CoreHealth v2 Clinical EHR',
                'version' => '2.6.0',
            ],
            'implementation' => [
                'description' => 'CoreHealth v2 Isolated Multi-Standard Interoperability Translation Engine',
                'url' => url('/api/fhir/r4'),
            ],
            'fhirVersion' => '4.0.1',
            'format' => ['application/fhir+json', 'application/json'],
            'rest' => [
                [
                    'mode' => 'server',
                    'security' => [
                        'cors' => true,
                        'service' => [
                            [
                                'coding' => [
                                    [
                                        'system' => 'http://terminology.hl7.org/CodeSystem/restful-security-service',
                                        'code' => 'SMART-on-FHIR',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'resource' => [
                        [
                            'type' => 'Patient',
                            'profile' => 'http://hl7.org/fhir/StructureDefinition/Patient',
                            'interaction' => [
                                ['code' => 'read'],
                                ['code' => 'search-type'],
                                ['code' => 'create'],
                                ['code' => 'update'],
                            ],
                            'searchParam' => [
                                ['name' => '_id', 'type' => 'token'],
                                ['name' => 'identifier', 'type' => 'token'],
                                ['name' => 'name', 'type' => 'string'],
                                ['name' => 'family', 'type' => 'string'],
                                ['name' => 'given', 'type' => 'string'],
                                ['name' => 'gender', 'type' => 'token'],
                                ['name' => 'birthdate', 'type' => 'date'],
                            ],
                        ],
                        [
                            'type' => 'Practitioner',
                            'profile' => 'http://hl7.org/fhir/StructureDefinition/Practitioner',
                            'interaction' => [
                                ['code' => 'read'],
                                ['code' => 'search-type'],
                            ],
                        ],
                        [
                            'type' => 'Observation',
                            'profile' => 'http://hl7.org/fhir/StructureDefinition/vitalsigns',
                            'interaction' => [
                                ['code' => 'read'],
                                ['code' => 'search-type'],
                                ['code' => 'create'],
                            ],
                            'searchParam' => [
                                ['name' => 'patient', 'type' => 'reference'],
                                ['name' => 'code', 'type' => 'token'],
                                ['name' => 'date', 'type' => 'date'],
                            ],
                        ],
                        [
                            'type' => 'Encounter',
                            'profile' => 'http://hl7.org/fhir/StructureDefinition/Encounter',
                            'interaction' => [
                                ['code' => 'read'],
                                ['code' => 'search-type'],
                                ['code' => 'create'],
                                ['code' => 'update'],
                            ],
                        ],
                        [
                            'type' => 'MedicationRequest',
                            'profile' => 'http://hl7.org/fhir/StructureDefinition/MedicationRequest',
                            'interaction' => [
                                ['code' => 'read'],
                                ['code' => 'search-type'],
                                ['code' => 'create'],
                            ],
                        ],
                        [
                            'type' => 'ServiceRequest',
                            'profile' => 'http://hl7.org/fhir/StructureDefinition/ServiceRequest',
                            'interaction' => [
                                ['code' => 'read'],
                                ['code' => 'search-type'],
                                ['code' => 'create'],
                            ],
                        ],
                        [
                            'type' => 'DiagnosticReport',
                            'profile' => 'http://hl7.org/fhir/StructureDefinition/DiagnosticReport',
                            'interaction' => [
                                ['code' => 'read'],
                                ['code' => 'search-type'],
                                ['code' => 'update'],
                            ],
                        ],
                        [
                            'type' => 'Invoice',
                            'profile' => 'http://hl7.org/fhir/StructureDefinition/Invoice',
                            'interaction' => [
                                ['code' => 'read'],
                                ['code' => 'search-type'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
