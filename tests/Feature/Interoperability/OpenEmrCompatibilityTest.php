<?php

namespace Tests\Feature\Interoperability;

use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

class OpenEmrCompatibilityTest extends TestCase
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
     * Test OpenEMR patient_data table format API.
     */
    public function test_openemr_patient_endpoint_format(): void
    {
        $response = $this->getJson('/apis/default/api/patient?limit=5');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertNotEmpty($response->json('data'));

        $first = $response->json('data.0');
        $this->assertArrayHasKey('pid', $first);
        $this->assertArrayHasKey('fname', $first);
        $this->assertArrayHasKey('lname', $first);
        $this->assertArrayHasKey('DOB', $first);
        $this->assertArrayHasKey('sex', $first);
        $this->assertArrayHasKey('phone_cell', $first);
    }

    /**
     * Test OpenEMR form_encounter table format API.
     */
    public function test_openemr_encounter_endpoint_format(): void
    {
        $response = $this->getJson('/apis/default/api/encounter?limit=5');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertNotEmpty($response->json('data'));

        $first = $response->json('data.0');
        $this->assertArrayHasKey('encounter', $first);
        $this->assertArrayHasKey('pid', $first);
        $this->assertArrayHasKey('date', $first);
        $this->assertArrayHasKey('reason', $first);
    }

    /**
     * Test OpenEMR form_vitals table format API.
     */
    public function test_openemr_vitals_endpoint_format(): void
    {
        $response = $this->getJson('/apis/default/api/vital_signs?limit=5');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertNotEmpty($response->json('data'));

        $first = $response->json('data.0');
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('pid', $first);
        $this->assertArrayHasKey('bps', $first);
        $this->assertArrayHasKey('bpd', $first);
        $this->assertArrayHasKey('pulse', $first);
        $this->assertArrayHasKey('temperature', $first);
    }

    /**
     * Test exporting Continuity of Care Document (CCD) C-CDA XML.
     */
    public function test_openemr_ccda_continuity_of_care_document_export(): void
    {
        $patient = Patient::first();
        if (!$patient) {
            $this->markTestSkipped('No patient found in database.');
        }

        $response = $this->get("/apis/default/api/ccda/{$patient->id}");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $content = $response->getContent();
        $this->assertStringContainsString('<ClinicalDocument', $content);
        $this->assertStringContainsString('Continuity of Care Document (CCD)', $content);
        $this->assertStringContainsString('<recordTarget>', $content);
        $this->assertStringContainsString('Vital Signs', $content);
        $this->assertStringContainsStringIgnoringCase('Problem List', $content);
        $this->assertStringContainsString('Medications', $content);
    }

    /**
     * Test OpenEMR FHIR proxy endpoint.
     */
    public function test_openemr_fhir_proxy_endpoints(): void
    {
        $response = $this->getJson('/apis/default/fhir/Patient?_count=2');

        $response->assertStatus(200);
        $response->assertJsonPath('resourceType', 'Bundle');
    }
}
