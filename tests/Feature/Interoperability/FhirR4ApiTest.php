<?php

namespace Tests\Feature\Interoperability;

use App\Models\Patient;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\VitalSign;
use Tests\TestCase;

class FhirR4ApiTest extends TestCase
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
     * Test unauthenticated access is strictly rejected with 401 and OperationOutcome.
     */
    public function test_unauthenticated_requests_are_rejected(): void
    {
        app('auth')->forgetGuards();

        $response = $this->getJson('/api/fhir/r4/Patient');
        $response->assertStatus(401);
        $response->assertJsonPath('resourceType', 'OperationOutcome');
        $response->assertHeader('WWW-Authenticate', 'Bearer realm="CoreHealth Interoperability API"');
    }

    /**
     * Test FHIR R4 CapabilityStatement Metadata endpoint is public.
     */
    public function test_capability_statement_metadata(): void
    {
        app('auth')->forgetGuards();

        $response = $this->getJson('/api/fhir/r4/metadata');

        $response->assertStatus(200);
        $response->assertJsonPath('resourceType', 'CapabilityStatement');
        $response->assertJsonPath('fhirVersion', '4.0.1');
        $response->assertJsonPath('status', 'active');
    }

    /**
     * Test searching and reading FHIR Patients.
     */
    public function test_patient_search_and_read(): void
    {
        $patient = Patient::with('user')->first();
        if (!$patient) {
            $this->markTestSkipped('No patient in database.');
        }

        // Search
        $response = $this->getJson('/api/fhir/r4/Patient?_count=5');
        $response->assertStatus(200);
        $response->assertJsonPath('resourceType', 'Bundle');
        $response->assertJsonPath('type', 'searchset');
        $this->assertNotEmpty($response->json('entry'));

        // Read by local ID (fallback)
        $readResponse = $this->getJson("/api/fhir/r4/Patient/{$patient->id}");
        $readResponse->assertStatus(200);
        $readResponse->assertJsonPath('resourceType', 'Patient');
        $this->assertEquals($patient->user->surname, $readResponse->json('name.0.family'));
    }

    /**
     * Test inbound create FHIR Patient proxies PatientController@store.
     */
    public function test_inbound_create_patient_proxies_patient_controller(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response([
                'bundleReport' => [
                    'typeReportMap' => [
                        'TRACKED_ENTITY' => ['objectReports' => [['uid' => 'mock-te-uid']]],
                        'ENROLLMENT' => ['objectReports' => [['uid' => 'mock-en-uid']]],
                    ],
                ],
            ], 200),
        ]);

        $uniqueMrn = 'TEST-MRN-' . rand(100000, 999999);
        $uniqueEmail = 'john.doe.' . rand(100000, 999999) . '@test-hms.local';

        $fhirPatientPayload = [
            'resourceType' => 'Patient',
            'identifier' => [
                [
                    'type' => [
                        'coding' => [
                            ['system' => 'http://terminology.hl7.org/CodeSystem/v2-0203', 'code' => 'MR'],
                        ],
                    ],
                    'system' => 'http://corehealth.org/identifiers/mrn',
                    'value' => $uniqueMrn,
                ],
            ],
            'name' => [
                [
                    'family' => 'Eze',
                    'given' => ['Chukwudi', 'Paul'],
                ],
            ],
            'telecom' => [
                ['system' => 'phone', 'value' => '08099887766'],
                ['system' => 'email', 'value' => $uniqueEmail],
            ],
            'gender' => 'male',
            'birthDate' => '1992-08-20',
            'address' => [
                ['text' => '12 Marina Street, Lagos Island, Lagos'],
            ],
            'contact' => [
                [
                    'name' => ['text' => 'Amaka Eze'],
                    'telecom' => [['system' => 'phone', 'value' => '08011223344']],
                ],
            ],
        ];

        $response = $this->postJson('/api/fhir/r4/Patient', $fhirPatientPayload);

        $this->assertTrue(in_array($response->status(), [200, 201]));
        $response->assertJsonPath('resourceType', 'Patient');
        $response->assertJsonPath('name.0.family', 'Eze');

        $this->assertDatabaseHas('users', ['email' => $uniqueEmail]);
        $this->assertDatabaseHas('patients', ['file_no' => $uniqueMrn]);
    }

    /**
     * Test searching and reading FHIR Observations (Vital Signs).
     */
    public function test_observation_vitals_search_and_read(): void
    {
        $vital = VitalSign::first();
        if (!$vital) {
            $this->markTestSkipped('No vital sign record found in database.');
        }

        $response = $this->getJson('/api/fhir/r4/Observation?_count=5');
        $response->assertStatus(200);
        $response->assertJsonPath('resourceType', 'Bundle');

        $readResponse = $this->getJson("/api/fhir/r4/Observation/{$vital->id}");
        $readResponse->assertStatus(200);
        $readResponse->assertJsonPath('resourceType', 'Observation');
        $readResponse->assertJsonPath('category.0.coding.0.code', 'vital-signs');
    }

    /**
     * Test inbound create FHIR Observation proxies VitalSignController@store.
     */
    public function test_inbound_create_observation_proxies_vital_controller(): void
    {
        $patient = Patient::first();
        if (!$patient) {
            $this->markTestSkipped('No patient found in database.');
        }

        $fhirObsPayload = [
            'resourceType' => 'Observation',
            'status' => 'final',
            'category' => [
                ['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/observation-category', 'code' => 'vital-signs']]],
            ],
            'subject' => [
                'reference' => 'Patient/' . $patient->id,
            ],
            'component' => [
                [
                    'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '8480-6', 'display' => 'Systolic blood pressure']]],
                    'valueQuantity' => ['value' => 125, 'unit' => 'mmHg'],
                ],
                [
                    'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '8462-4', 'display' => 'Diastolic blood pressure']]],
                    'valueQuantity' => ['value' => 82, 'unit' => 'mmHg'],
                ],
                [
                    'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '8310-5', 'display' => 'Body temperature']]],
                    'valueQuantity' => ['value' => 36.8, 'unit' => '°C'],
                ],
                [
                    'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '8867-4', 'display' => 'Heart rate']]],
                    'valueQuantity' => ['value' => 74, 'unit' => 'beats/min'],
                ],
                [
                    'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '29463-7', 'display' => 'Body weight']]],
                    'valueQuantity' => ['value' => 71.5, 'unit' => 'kg'],
                ],
            ],
        ];

        $response = $this->postJson('/api/fhir/r4/Observation', $fhirObsPayload);

        $this->assertTrue(in_array($response->status(), [200, 201]));
        $response->assertJsonPath('resourceType', 'Observation');

        $this->assertDatabaseHas('vital_signs', [
            'patient_id' => $patient->id,
            'blood_pressure' => '125/82',
            'temp' => 36.8,
            'heart_rate' => 74,
            'weight' => 71.5,
        ]);
    }

    /**
     * Test inbound create FHIR MedicationRequest proxies EncounterController@addSinglePrescriptionRequest.
     */
    public function test_inbound_create_medication_request_proxies_encounter_controller(): void
    {
        $patient = Patient::first();
        $product = Product::first();
        if (!$patient || !$product) {
            $this->markTestSkipped('Missing patient or product fixture.');
        }

        $fhirRxPayload = [
            'resourceType' => 'MedicationRequest',
            'status' => 'active',
            'intent' => 'order',
            'medicationCodeableConcept' => [
                'coding' => [
                    ['system' => url('/products'), 'code' => (string) $product->id, 'display' => $product->product_name],
                ],
                'text' => $product->product_name,
            ],
            'subject' => [
                'reference' => 'Patient/' . $patient->id,
            ],
            'dosageInstruction' => [
                ['text' => '1 tab TDS for 5 days'],
            ],
        ];

        $response = $this->postJson('/api/fhir/r4/MedicationRequest', $fhirRxPayload);

        $this->assertTrue(in_array($response->status(), [200, 201]));
        $response->assertJsonPath('resourceType', 'MedicationRequest');

        $this->assertDatabaseHas('product_requests', [
            'patient_id' => $patient->id,
            'product_id' => $product->id,
            'dose' => '1 tab TDS for 5 days',
        ]);
    }

    /**
     * Test inbound create FHIR ServiceRequest proxies EncounterController@addSingleLabRequest.
     */
    public function test_inbound_create_service_request_proxies_encounter_controller(): void
    {
        $patient = Patient::first();
        $service = Service::first();
        if (!$patient || !$service) {
            $this->markTestSkipped('Missing patient or service fixture.');
        }

        $fhirReqPayload = [
            'resourceType' => 'ServiceRequest',
            'status' => 'active',
            'intent' => 'order',
            'code' => [
                'coding' => [
                    ['system' => url('/services'), 'code' => (string) $service->id, 'display' => $service->service_name],
                ],
                'text' => $service->service_name,
            ],
            'subject' => [
                'reference' => 'Patient/' . $patient->id,
            ],
            'note' => [
                ['text' => 'Urgent STAT investigation required'],
            ],
        ];

        $response = $this->postJson('/api/fhir/r4/ServiceRequest', $fhirReqPayload);

        $this->assertTrue(in_array($response->status(), [200, 201]));
        $response->assertJsonPath('resourceType', 'ServiceRequest');

        $this->assertDatabaseHas('lab_service_requests', [
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'note' => 'Urgent STAT investigation required',
        ]);
    }
}
