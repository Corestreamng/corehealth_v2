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

    /**
     * Test catalog categories and search endpoints with CSCP representation.
     */
    public function test_catalog_categories_and_items_endpoints(): void
    {
        $authResponse = $this->postJson('/api/sdk/auth/token', [
            'client_id' => 'test-partner-client',
            'client_secret' => $this->plainSecret,
            'scope' => 'scribe:process scribe:sign interop:compositions',
        ]);
        $sdkToken = $authResponse->json('sdk_token');

        // Categories
        $catResponse = $this->withHeader('Authorization', "Bearer {$sdkToken}")
            ->getJson('/api/sdk/catalogs/categories');
        $catResponse->assertStatus(200);
        $catResponse->assertJsonStructure([
            'status',
            'count',
            'data',
        ]);

        // Items Search with CSCP tokens
        $itemsResponse = $this->withHeader('Authorization', "Bearer {$sdkToken}")
            ->getJson('/api/sdk/catalogs/items?q=paracetamol&limit=5');
        $itemsResponse->assertStatus(200);
        $itemsResponse->assertJsonStructure([
            'status',
            'count',
            'cscp',
            'data',
        ]);
    }

    /**
     * Test dictation processing with super-compressed partner catalog (CSCP).
     */
    public function test_scribe_process_with_compressed_cscp_catalog(): void
    {
        $authResponse = $this->postJson('/api/sdk/auth/token', [
            'client_id' => 'test-partner-client',
            'client_secret' => $this->plainSecret,
            'scope' => 'scribe:process scribe:sign interop:compositions',
        ]);
        $sdkToken = $authResponse->json('sdk_token');

        $cscpCatalog = "#CAT:101|medication|Artemether-Lumefantrine 80/480mg\n#CAT:201|laboratory|Malaria Parasite (MP) Giemsa";

        $response = $this->withHeader('Authorization', "Bearer {$sdkToken}")
            ->postJson('/api/sdk/scribe/process', [
                'transcript' => 'Patient has severe malaria. Prescribe Artemether-Lumefantrine 80/480mg twice daily for 3 days and request Malaria Parasite by Microscopy.',
                'role' => 'doctor',
                'taxonomy' => 'soap',
                'custom_catalog' => $cscpCatalog,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertNotEmpty($response->json('structured_sections'));
        $orders = $response->json('grounded_orders');
        $this->assertIsArray($orders);
    }

    /**
     * Test deterministic lossless clinical taxonomy conversion.
     */
    public function test_scribe_taxonomy_format_conversion(): void
    {
        $authResponse = $this->postJson('/api/sdk/auth/token', [
            'client_id' => 'test-partner-client',
            'client_secret' => $this->plainSecret,
            'scope' => 'scribe:process scribe:sign interop:compositions',
        ]);
        $sdkToken = $authResponse->json('sdk_token');

        $response = $this->withHeader('Authorization', "Bearer {$sdkToken}")
            ->postJson('/api/sdk/scribe/convert-format', [
                'note' => "SUBJECTIVE:\nPatient complains of fever and headache.\n\nOBJECTIVE:\nBP 120/80 mmHg, Temp 38.2 C.\n\nASSESSMENT:\nMalaria fever.\n\nPLAN:\nArtemether-Lumefantrine 80/480mg BD x 3d.",
                'source_taxonomy' => 'soap',
                'target_taxonomy' => 'traditional',
                'source_role' => 'doctor',
                'target_role' => 'doctor',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');
        $this->assertNotEmpty($response->json('converted_note'));
    }

    /**
     * Test signing consultation note with approved orders deck.
     */
    public function test_scribe_sign_with_approved_orders(): void
    {
        $patient = Patient::first();
        $patientId = $patient ? $patient->id : null;

        $authResponse = $this->postJson('/api/sdk/auth/token', [
            'client_id' => 'test-partner-client',
            'client_secret' => $this->plainSecret,
            'patient_id' => $patientId,
            'encounter_id' => 1017,
            'clinician_id' => 38,
            'scope' => 'scribe:process scribe:sign interop:compositions',
        ]);
        $sdkToken = $authResponse->json('sdk_token');

        $approvedOrders = [
            [
                'type' => 'medication',
                'name' => 'Artemether-Lumefantrine 80/480mg',
                'matched_id' => 101,
                'matched_tariff' => 1850.0,
                'dosage' => '1 tab',
                'frequency' => 'BD',
                'duration' => '3 days',
                'route' => 'Oral',
                'instructions' => 'Take with meals',
            ],
            [
                'type' => 'laboratory',
                'name' => 'Malaria Parasite (MP) Giemsa',
                'matched_id' => 201,
                'matched_tariff' => 2500.0,
                'instructions' => 'Urgent Giemsa blood smear',
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$sdkToken}")
            ->postJson('/api/sdk/scribe/sign', [
                'final_signed_note' => "SUBJECTIVE:\nFever and chills.\n\nOBJECTIVE:\nTemp 38.5.\n\nASSESSMENT:\nMalaria.\n\nPLAN:\nArtemether-Lumefantrine and MP Giemsa.",
                'original_transcript' => 'Dictated clinical findings...',
                'approved_orders' => $approvedOrders,
                'role' => 'doctor',
                'taxonomy' => 'soap',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertNotNull($response->json('audit_id'));
        $this->assertEquals(2, $response->json('orders_dispatched'));
    }
}
