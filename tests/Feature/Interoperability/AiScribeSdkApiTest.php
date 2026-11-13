<?php

namespace Tests\Feature\Interoperability;

use App\Interoperability\Models\SdkClient;
use App\Models\Patient;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AiScribeSdkApiTest extends TestCase
{
    protected SdkClient $client;

    protected string $plainSecret = 'test_secret_12345';

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = SdkClient::updateOrCreate(
            ['client_id' => 'test-partner-client'],
            [
                'client_secret' => Hash::make($this->plainSecret),
                'name' => 'Test Partner Client',
                'organization' => 'Test Healthcare Ltd',
                'allowed_scopes' => ['*'],
                'rate_limit_rpm' => 300,
                'is_active' => true,
            ]
        );
    }

    /**
     * Test client credentials exchange rejects invalid credentials.
     */
    public function test_token_issue_fails_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/sdk/auth/token', [
            'client_id' => 'test-partner-client',
            'client_secret' => 'wrong_secret',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('resourceType', 'OperationOutcome');
    }

    /**
     * Test client credentials exchange succeeds with valid credentials.
     */
    public function test_token_issue_succeeds_with_valid_credentials(): void
    {
        $patient = Patient::first();
        $patientId = $patient ? $patient->id : null;

        $response = $this->postJson('/api/sdk/auth/token', [
            'client_id' => 'test-partner-client',
            'client_secret' => $this->plainSecret,
            'patient_id' => $patientId,
            'encounter_id' => 1017,
            'clinician_id' => 38,
            'scope' => 'scribe:process scribe:sign interop:compositions',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'sdk_token',
            'token_type',
            'expires_in',
            'scope',
            'patient_id',
            'encounter_id',
            'clinician_id',
        ]);

        $token = $response->json('sdk_token');
        $this->assertStringStartsWith('SKT_', $token);
    }

    /**
     * Test unauthenticated access to scribe session endpoints is strictly rejected.
     */
    public function test_unauthenticated_requests_to_scribe_endpoints_are_rejected(): void
    {
        $response = $this->postJson('/api/sdk/scribe/session', []);
        $response->assertStatus(401);
        $response->assertJsonPath('resourceType', 'OperationOutcome');
    }

    /**
     * Test full Scribe lifecycle: session init -> dictation process -> sign note -> compositions handoff.
     */
    public function test_full_scribe_sdk_lifecycle(): void
    {
        $patient = Patient::first();
        $patientId = $patient ? $patient->id : null;

        // 1. Acquire token
        $authResponse = $this->postJson('/api/sdk/auth/token', [
            'client_id' => 'test-partner-client',
            'client_secret' => $this->plainSecret,
            'patient_id' => $patientId,
            'encounter_id' => 1017,
            'clinician_id' => 38,
        ]);
        $authResponse->assertStatus(200);
        $sdkToken = $authResponse->json('sdk_token');

        // 2. Initialize Scribe Session
        $sessionResponse = $this->withHeader('Authorization', "Bearer {$sdkToken}")
            ->postJson('/api/sdk/scribe/session', [
                'locale' => 'en-NG',
            ]);
        $sessionResponse->assertStatus(201);
        $sessionResponse->assertJsonPath('status', 'IDLE');

        // 3. Process Dictation Text into Structured SOAP
        $processResponse = $this->withHeader('Authorization', "Bearer {$sdkToken}")
            ->postJson('/api/sdk/scribe/process', [
                'transcript' => 'Patient presented with headache and hypertension. Prescribed Metformin 500mg and requested Full Blood Count test.',
            ]);
        $processResponse->assertStatus(200);
        $processResponse->assertJsonPath('success', true);
        $this->assertNotEmpty($processResponse->json('structured_sections'));

        // 4. Sign the Consultation Note
        $signResponse = $this->withHeader('Authorization', "Bearer {$sdkToken}")
            ->postJson('/api/sdk/scribe/sign', [
                'final_signed_note' => 'Patient presented with headache. Exam: BP 130/85. Plan: Metformin 500mg, review in 4 weeks.',
                'original_transcript' => 'Dictated clinical findings...',
            ]);
        $signResponse->assertStatus(200);
        $signResponse->assertJsonPath('success', true);
        $this->assertNotNull($signResponse->json('audit_id'));

        // 5. Fetch openEHR + FHIR Compositions Handoff
        $compResponse = $this->withHeader('Authorization', "Bearer {$sdkToken}")
            ->postJson("/api/sdk/scribe/session/{$sdkToken}/compositions", [
                'cross_border' => false,
            ]);
        $compResponse->assertStatus(200);
        $compResponse->assertJsonStructure([
            'openehr_composition',
            'fhir_bundle',
            'signed_at',
            'final_signed_note',
        ]);
        $this->assertEquals('Bundle', $compResponse->json('fhir_bundle.resourceType'));
        $this->assertEquals('COMPOSITION', $compResponse->json('openehr_composition._type'));

        // 6. Test Token Revocation
        $revokeResponse = $this->postJson('/api/sdk/auth/revoke', [
            'token' => $sdkToken,
        ]);
        $revokeResponse->assertStatus(200);
        $revokeResponse->assertJsonPath('revoked', true);

        // 7. Verify revoked token is now rejected
        $rejectedResponse = $this->withHeader('Authorization', "Bearer {$sdkToken}")
            ->postJson('/api/sdk/scribe/session', []);
        $rejectedResponse->assertStatus(401);
    }
}
