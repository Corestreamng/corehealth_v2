<?php

namespace Tests\Feature\Interoperability;

use App\Interoperability\Models\InteropExternalIdentifier;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

class OpenEhrApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $user = User::first();
        if ($user) {
            $this->actingAs($user, 'sanctum');
        }
    }

    /**
     * Test creating and reading an EHR.
     */
    public function test_openehr_ehr_create_and_read(): void
    {
        $patient = Patient::first();
        if (!$patient) {
            $this->markTestSkipped('No patient found in database.');
        }

        $createResponse = $this->postJson('/api/openehr/v1/ehr', ['patient_id' => $patient->id]);
        $this->assertTrue(in_array($createResponse->status(), [200, 201]));
        $createResponse->assertJsonPath('system_id.value', 'corehealth.hospital.ehr');

        $ehrId = $createResponse->json('ehr_id.value');
        $this->assertNotEmpty($ehrId);

        $readResponse = $this->getJson("/api/openehr/v1/ehr/{$ehrId}");
        $readResponse->assertStatus(200);
        $readResponse->assertJsonPath('ehr_id.value', $ehrId);
    }

    /**
     * Test searching EHR by subject ID.
     */
    public function test_openehr_ehr_search_by_subject(): void
    {
        $patient = Patient::first();
        if (!$patient) {
            $this->markTestSkipped('No patient found in database.');
        }

        $response = $this->getJson("/api/openehr/v1/ehr?subject_id={$patient->id}&subject_namespace=corehealth");
        $response->assertStatus(200);
        $response->assertJsonPath('ehr_status.subject.external_ref.id.value', (string) $patient->id);
    }

    /**
     * Test reading canonical openEHR COMPOSITION.encounter.v1.
     */
    public function test_openehr_composition_read(): void
    {
        $encounter = Encounter::first();
        if (!$encounter) {
            $this->markTestSkipped('No encounter found in database.');
        }

        $ehrId = InteropExternalIdentifier::getOrCreateUuid('OPENEHR', 'Ehr', $encounter->patient_id);
        $compositionUid = InteropExternalIdentifier::getOrCreateUuid('OPENEHR', 'Composition', $encounter->id);

        $response = $this->getJson("/api/openehr/v1/ehr/{$ehrId}/composition/{$compositionUid}::corehealth.hospital.ehr::1");
        $response->assertStatus(200);
        $response->assertJsonPath('_type', 'COMPOSITION');
        $response->assertJsonPath('archetype_node_id', 'openEHR-EHR-COMPOSITION.encounter.v1');
    }

    /**
     * Test inbound openEHR COMPOSITION creation proxies VitalSignController.
     */
    public function test_openehr_inbound_composition_create_proxies_vitals(): void
    {
        $patient = Patient::first();
        if (!$patient) {
            $this->markTestSkipped('No patient found in database.');
        }

        $ehrId = InteropExternalIdentifier::getOrCreateUuid('OPENEHR', 'Ehr', $patient->id);

        $compositionPayload = [
            '_type' => 'COMPOSITION',
            'archetype_node_id' => 'openEHR-EHR-COMPOSITION.encounter.v1',
            'name' => ['_type' => 'DV_TEXT', 'value' => 'Routine openEHR Health Checkup'],
            'content' => [
                [
                    '_type' => 'OBSERVATION',
                    'name' => ['_type' => 'DV_TEXT', 'value' => 'Blood pressure'],
                    'archetype_node_id' => 'openEHR-EHR-OBSERVATION.blood_pressure.v2',
                    'data' => [
                        'events' => [
                            [
                                'data' => [
                                    'items' => [
                                        [
                                            '_type' => 'ELEMENT',
                                            'archetype_node_id' => 'at0004',
                                            'name' => ['_type' => 'DV_TEXT', 'value' => 'Systolic'],
                                            'value' => ['_type' => 'DV_QUANTITY', 'magnitude' => 135, 'units' => 'mm[Hg]'],
                                        ],
                                        [
                                            '_type' => 'ELEMENT',
                                            'archetype_node_id' => 'at0005',
                                            'name' => ['_type' => 'DV_TEXT', 'value' => 'Diastolic'],
                                            'value' => ['_type' => 'DV_QUANTITY', 'magnitude' => 88, 'units' => 'mm[Hg]'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    '_type' => 'OBSERVATION',
                    'name' => ['_type' => 'DV_TEXT', 'value' => 'Pulse'],
                    'archetype_node_id' => 'openEHR-EHR-OBSERVATION.pulse.v2',
                    'data' => [
                        'events' => [
                            [
                                'data' => [
                                    'items' => [
                                        [
                                            '_type' => 'ELEMENT',
                                            'archetype_node_id' => 'at0004',
                                            'name' => ['_type' => 'DV_TEXT', 'value' => 'Rate'],
                                            'value' => ['_type' => 'DV_QUANTITY', 'magnitude' => 82, 'units' => '/min'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson("/api/openehr/v1/ehr/{$ehrId}/composition", $compositionPayload);

        $this->assertTrue(in_array($response->status(), [200, 201]));
        $response->assertJsonPath('_type', 'COMPOSITION');

        $this->assertDatabaseHas('vital_signs', [
            'patient_id' => $patient->id,
            'blood_pressure' => '135/88',
            'heart_rate' => 82,
        ]);
    }

    /**
     * Test executing Archetype Query Language (AQL).
     */
    public function test_openehr_aql_query_execution(): void
    {
        $aql = "SELECT o/data[at0001]/events[at0002]/data[at0003]/items[at0004]/value/magnitude AS systolic, o/data[at0001]/events[at0002]/data[at0003]/items[at0005]/value/magnitude AS diastolic FROM EHR e CONTAINS COMPOSITION c CONTAINS OBSERVATION o [openEHR-EHR-OBSERVATION.blood_pressure.v2]";

        $response = $this->postJson('/api/openehr/v1/query/aql', ['q' => $aql]);

        $response->assertStatus(200);
        $response->assertJsonPath('meta._type', 'RESULT_SET');
        $this->assertEquals($aql, $response->json('q'));
        $this->assertNotEmpty($response->json('columns'));
    }
}
