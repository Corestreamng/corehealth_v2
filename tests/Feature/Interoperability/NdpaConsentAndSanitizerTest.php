<?php

namespace Tests\Feature\Interoperability;

use App\Interoperability\Models\InteropTerminologyMap;
use App\Interoperability\Models\PatientConsent;
use App\Interoperability\Services\NdprSanitizerService;
use App\Models\Patient;
use Tests\TestCase;

class NdpaConsentAndSanitizerTest extends TestCase
{
    /**
     * Test recording patient consent per NDPA 2023.
     */
    public function test_can_record_patient_consent_via_api(): void
    {
        $patient = Patient::first();
        if (!$patient) {
            $this->markTestSkipped('No patient found in test database.');
        }

        $payload = [
            'patient_id' => $patient->id,
            'consent_type' => 'CLOUD_AI',
            'ndpa_lawful_basis' => 'EXPLICIT_CONSENT',
            'notes' => 'Patient agreed to AI-assisted clinical summarization.',
        ];

        $response = $this->postJson('/api/ndpa/v1/consent', $payload);

        $this->assertTrue(in_array($response->status(), [200, 201]));
        $response->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('patient_consents', [
            'patient_id' => $patient->id,
            'consent_type' => 'CLOUD_AI',
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * Test retrieving patient consents.
     */
    public function test_can_retrieve_patient_consents(): void
    {
        $patient = Patient::first();
        if (!$patient) {
            $this->markTestSkipped('No patient found in test database.');
        }

        $consent = PatientConsent::create([
            'patient_id' => $patient->id,
            'consent_type' => 'TREATMENT',
            'status' => 'ACTIVE',
            'ndpa_lawful_basis' => 'EXPLICIT_CONSENT',
            'granted_at' => now(),
        ]);

        $response = $this->getJson("/api/ndpa/v1/consent/{$patient->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');
        $this->assertTrue(count($response->json('consents')) >= 1);
    }

    /**
     * Test revoking patient consent per NDPA Section 34.
     */
    public function test_can_revoke_patient_consent(): void
    {
        $patient = Patient::first();
        if (!$patient) {
            $this->markTestSkipped('No patient found in test database.');
        }

        $consent = PatientConsent::create([
            'patient_id' => $patient->id,
            'consent_type' => 'RESEARCH',
            'status' => 'ACTIVE',
            'ndpa_lawful_basis' => 'EXPLICIT_CONSENT',
            'granted_at' => now(),
        ]);

        $response = $this->deleteJson("/api/ndpa/v1/consent/{$consent->id}");

        $response->assertStatus(200);
        $this->assertDatabaseHas('patient_consents', [
            'id' => $consent->id,
            'status' => 'REVOKED',
        ]);
    }

    /**
     * Test NDPR Sanitizer strips direct PII (name, phone, email, NIN, MRN).
     */
    public function test_ndpr_sanitizer_redacts_direct_pii_from_clinical_notes(): void
    {
        $patient = Patient::with('user')->first();
        if (!$patient) {
            $this->markTestSkipped('No patient found in test database.');
        }

        $sanitizer = new NdprSanitizerService();

        $rawText = "Patient {$patient->user->surname} presented with fever. Call relative on 08031234567 or email doctor@hospital.com. NIN: 12345678901. Chart: PAT-9921.";
        $cleaned = $sanitizer->sanitizeClinicalText($rawText, $patient);

        $this->assertStringNotContainsString($patient->user->surname, $cleaned);
        $this->assertStringNotContainsString('08031234567', $cleaned);
        $this->assertStringNotContainsString('doctor@hospital.com', $cleaned);
        $this->assertStringNotContainsString('12345678901', $cleaned);
        $this->assertStringNotContainsString('PAT-9921', $cleaned);
        $this->assertStringContainsString('[REDACTED_PHONE]', $cleaned);
        $this->assertStringContainsString('[REDACTED_EMAIL]', $cleaned);
        $this->assertStringContainsString('[REDACTED_NIN]', $cleaned);
    }

    /**
     * Test deterministic pseudonymization and re-identification.
     */
    public function test_ndpr_sanitizer_generates_and_resolves_deterministic_pseudonyms(): void
    {
        $patient = Patient::first();
        if (!$patient) {
            $this->markTestSkipped('No patient found in test database.');
        }

        $sanitizer = new NdprSanitizerService();

        $pseudo1 = $sanitizer->getPatientPseudonym($patient->id);
        $pseudo2 = $sanitizer->getPatientPseudonym($patient->id);

        $this->assertEquals($pseudo1, $pseudo2);
        $resolvedId = $sanitizer->resolvePseudonym($pseudo1);
        $this->assertEquals($patient->id, $resolvedId);
    }

    /**
     * Test terminology mapping query scopes.
     */
    public function test_interop_terminology_map_scopes(): void
    {
        $bpMap = InteropTerminologyMap::code('LOINC', '85354-9')->first();
        $this->assertNotNull($bpMap);
        $this->assertEquals('blood_pressure', $bpMap->local_key);

        $tempMap = InteropTerminologyMap::code('LOINC', '8310-5')->first();
        $this->assertNotNull($tempMap);
        $this->assertEquals('temp', $tempMap->local_key);
    }
}
