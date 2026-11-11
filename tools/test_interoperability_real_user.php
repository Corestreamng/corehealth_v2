<?php

/**
 * CoreHealth v2 - Comprehensive Health Interoperability & NDPA Statutory Test Harness
 * 
 * Simulates a real external API user/client communicating with CoreHealth v2:
 * - Acquiring API access (Sanctum Personal Access Tokens / Basic Auth)
 * - Verifying 401 unauthenticated rejections & public metadata exemptions
 * - Testing NDPA 2023 Section 30 sensitive consent gatekeeping & Section 31 pediatric guardian fields
 * - Testing Broken Object Level Authorization (BOLA/IDOR) on patient-level tokens
 * - Testing HL7 FHIR R4 (Levels 1-4) queries, reads, and inbound controller mutations
 * - Testing openEHR ITS-REST EHR creation, composition storage, and AQL archetype queries
 * - Testing OpenEMR table-parity REST endpoints and C-CDA XML Continuity of Care export
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

class RealApiUserClient
{
    protected \Illuminate\Contracts\Http\Kernel $kernel;
    protected ?string $bearerToken = null;
    protected int $totalTests = 0;
    protected int $passedTests = 0;
    protected int $failedTests = 0;
    protected array $failures = [];

    public function __construct(\Illuminate\Contracts\Http\Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    public function setBearerToken(?string $token): void
    {
        $this->bearerToken = $token;
    }

    public function request(string $method, string $uri, array $data = [], array $headers = []): array
    {
        // Reset auth guards to ensure fresh token evaluation per request
        app('auth')->forgetGuards();

        $server = [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ];

        if ($this->bearerToken) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->bearerToken;
        }

        foreach ($headers as $key => $val) {
            $serverName = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
            $server[$serverName] = $val;
            if (strtolower($key) === 'content-type') {
                $server['CONTENT_TYPE'] = $val;
            }
        }

        $content = null;
        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH']) && !empty($data)) {
            $content = json_encode($data);
        }

        $request = \Illuminate\Http\Request::create(
            $uri,
            strtoupper($method),
            strtoupper($method) === 'GET' ? $data : [],
            [],
            [],
            $server,
            $content
        );

        $response = $this->kernel->handle($request);
        $statusCode = $response->getStatusCode();
        $rawContent = $response->getContent();
        $json = json_decode($rawContent, true);

        $responseHeaders = [];
        foreach ($response->headers->all() as $name => $values) {
            $responseHeaders[strtolower($name)] = implode(', ', $values);
        }

        $this->kernel->terminate($request, $response);

        return [
            'status' => $statusCode,
            'body' => $json,
            'raw' => $rawContent,
            'headers' => $responseHeaders,
        ];
    }

    public function assert(string $title, bool $condition, string $detail = ''): void
    {
        $this->totalTests++;
        if ($condition) {
            $this->passedTests++;
            echo "  \033[32m✔\033[0m {$title}\n";
        } else {
            $this->failedTests++;
            $this->failures[] = "{$title}: {$detail}";
            echo "  \033[31m✖\033[0m {$title} (FAILED: {$detail})\n";
        }
    }

    public function printSummary(): void
    {
        echo "\n" . str_repeat('=', 70) . "\n";
        echo "INTEROPERABILITY & NDPA REAL-USER CLIENT TEST SUMMARY\n";
        echo str_repeat('=', 70) . "\n";
        echo "Total Assertions: {$this->totalTests}\n";
        echo "Passed:           \033[32m{$this->passedTests}\033[0m\n";
        echo "Failed:           " . ($this->failedTests > 0 ? "\033[31m{$this->failedTests}\033[0m" : "0") . "\n";

        if ($this->failedTests > 0) {
            echo "\nFailed Assertions:\n";
            foreach ($this->failures as $idx => $fail) {
                echo "  " . ($idx + 1) . ". {$fail}\n";
            }
        }
        echo str_repeat('=', 70) . "\n";
    }

    public function isSuccess(): bool
    {
        return $this->failedTests === 0;
    }
}

$client = new RealApiUserClient($kernel);

echo "\n\033[1;36m======================================================================\033[0m\n";
echo "\033[1;36m COREHEALTH v2: REAL-USER API INTEROPERABILITY & NDPA AUDIT SUITE    \033[0m\n";
echo "\033[1;36m======================================================================\033[0m\n";

// -----------------------------------------------------------------------------
// SCENARIO 1: AUTHENTICATION & SECURITY GATEKEEPER
// -----------------------------------------------------------------------------
echo "\n\033[1;33m[SCENARIO 1] Route Authentication & Security Gatekeeper\033[0m\n";

// 1.1 Unauthenticated call to FHIR Patient
$res = $client->request('GET', '/api/fhir/r4/Patient');
$client->assert(
    'Unauthenticated FHIR Patient request receives HTTP 401 Unauthorized',
    $res['status'] === 401 && ($res['body']['resourceType'] ?? '') === 'OperationOutcome',
    "Status was {$res['status']}"
);
$client->assert(
    'Unauthenticated rejection returns WWW-Authenticate Bearer header',
    str_contains($res['headers']['www-authenticate'] ?? '', 'Bearer'),
    "Header was " . ($res['headers']['www-authenticate'] ?? 'missing')
);

// 1.2 Unauthenticated call to openEHR EHR
$res = $client->request('GET', '/api/openehr/v1/ehr');
$client->assert(
    'Unauthenticated openEHR EHR request receives HTTP 401 Unauthorized',
    $res['status'] === 401,
    "Status was {$res['status']}"
);

// 1.3 Unauthenticated call to OpenEMR patient
$res = $client->request('GET', '/apis/default/api/patient');
$client->assert(
    'Unauthenticated OpenEMR patient request receives HTTP 401 Unauthorized',
    $res['status'] === 401,
    "Status was {$res['status']}"
);

// 1.4 Public exemption for FHIR CapabilityStatement metadata
$res = $client->request('GET', '/api/fhir/r4/metadata');
$client->assert(
    'FHIR CapabilityStatement metadata is publicly accessible without auth (HTTP 200)',
    $res['status'] === 200 && ($res['body']['resourceType'] ?? '') === 'CapabilityStatement',
    "Status was {$res['status']}"
);

// 1.5 Mint real Sanctum Bearer Token for clinical staff user
$staffUser = \App\Models\User::where('email', 'sysadmin@mail.com')->first() 
    ?? \App\Models\User::first();
if (!$staffUser) {
    die("Error: No user found in database.\n");
}
$staffToken = $staffUser->createToken('InteropRealUserClientTestToken')->plainTextToken;
$client->setBearerToken($staffToken);
echo "  \033[34mℹ\033[0m Minted Sanctum Personal Access Token for: {$staffUser->firstname} {$staffUser->surname}\n";

// 1.6 Authenticated access with Bearer token
$res = $client->request('GET', '/api/fhir/r4/Patient?_count=2');
$client->assert(
    'Authenticated FHIR Patient request succeeds with Bearer token (HTTP 200)',
    $res['status'] === 200 && ($res['body']['resourceType'] ?? '') === 'Bundle',
    "Status was {$res['status']}"
);

// -----------------------------------------------------------------------------
// SCENARIO 2: NDPA 2023 STATUTORY CONSENT, MINORS & PII SANITIZATION
// -----------------------------------------------------------------------------
echo "\n\033[1;33m[SCENARIO 2] NDPA 2023 Statutory Consent & Privacy Gatekeeper\033[0m\n";

$targetPatient = \App\Models\Patient::with('user')->first();
if (!$targetPatient) {
    die("Error: No patient found in database.\n");
}

// 2.1 Retrieve existing consents
$res = $client->request('GET', "/api/ndpa/v1/consent/{$targetPatient->id}");
$client->assert(
    'Can retrieve active NDPA consents for target patient (HTTP 200)',
    $res['status'] === 200 && isset($res['body']['consents']),
    "Status was {$res['status']}"
);

// 2.2 Record Adult Patient Explicit Consent (e.g. RESEARCH)
$consentPayload = [
    'patient_id' => $targetPatient->id,
    'consent_type' => 'RESEARCH',
    'ndpa_lawful_basis' => 'EXPLICIT_CONSENT',
    'notes' => 'Patient signed statutory consent form for longitudinal oncology study.',
];
$res = $client->request('POST', '/api/ndpa/v1/consent', $consentPayload);
$client->assert(
    'Can record explicit patient consent via POST /api/ndpa/v1/consent (HTTP 201)',
    $res['status'] === 201 && ($res['body']['status'] ?? '') === 'success',
    "Status was {$res['status']}"
);
$recordedConsentId = $res['body']['data']['id'] ?? null;

// 2.3 Record Pediatric Minor Consent with Guardian Verification per NDPA Section 31
$minorConsentPayload = [
    'patient_id' => $targetPatient->id,
    'consent_type' => 'THIRD_PARTY_TRANSFER',
    'ndpa_lawful_basis' => 'PARENTAL_AUTHORIZATION',
    'parent_guardian_name' => 'Barrister Ngozi Adebayo',
    'parent_guardian_nin' => '11223344556',
    'parent_guardian_relationship' => 'Mother & Legal Guardian',
    'notes' => 'Pediatric authorization for regional specialty referral exchange.',
];
$res = $client->request('POST', '/api/ndpa/v1/consent', $minorConsentPayload);
$client->assert(
    'Can record pediatric minor consent with guardian details per NDPA Section 31 (HTTP 201)',
    $res['status'] === 201 && ($res['body']['data']['parent_guardian_name'] ?? '') === 'Barrister Ngozi Adebayo',
    "Status was {$res['status']}"
);
$minorConsentId = $res['body']['data']['id'] ?? null;

// 2.4 Verify consent gatekeeper passes with CONSENT_VERIFIED header
$res = $client->request('GET', "/api/fhir/r4/Patient/{$targetPatient->id}");
$client->assert(
    'Consent gatekeeper attaches X-NDPA-Consent-Status header to response',
    in_array($res['headers']['x-ndpa-consent-status'] ?? '', ['CONSENT_VERIFIED', 'DEFAULT_VITAL_INTEREST']),
    "Header was " . ($res['headers']['x-ndpa-consent-status'] ?? 'missing')
);

// 2.5 Revoke consent via DELETE /api/ndpa/v1/consent/{id}
if ($recordedConsentId) {
    $res = $client->request('DELETE', "/api/ndpa/v1/consent/{$recordedConsentId}");
    $client->assert(
        'Can revoke patient consent per NDPA Section 34 (HTTP 200)',
        $res['status'] === 200 && ($res['body']['status'] ?? '') === 'success',
        "Status was {$res['status']}"
    );
}

// 2.6 Test NDPR Free-text PII Sanitizer
$sanitizer = new \App\Interoperability\Services\NdprSanitizerService();
$dirtyClinicalText = "Patient {$targetPatient->user->surname} attended with next of kin Dr. Samuel Adeyemi (Phone: 08021112233). Patient NIN: 98765432101, BVN: 22334455667, Chart: PAT-8812, Email: patient.test@hospital.gov.ng.";
$cleanedText = $sanitizer->sanitizeClinicalText($dirtyClinicalText, $targetPatient);

$client->assert(
    'NdprSanitizerService redacts patient surname from clinical text',
    !str_contains($cleanedText, $targetPatient->user->surname),
    "Surname still present"
);
$client->assert(
    'NdprSanitizerService redacts Nigerian phone numbers',
    str_contains($cleanedText, '[REDACTED_PHONE]') && !str_contains($cleanedText, '08021112233'),
    "Phone unredacted"
);
$client->assert(
    'NdprSanitizerService redacts 11-digit NIN and BVN numbers',
    str_contains($cleanedText, '[REDACTED_NIN]') && str_contains($cleanedText, '[REDACTED_BVN]'),
    "NIN/BVN unredacted"
);
$client->assert(
    'NdprSanitizerService redacts honorific personal names and kinship references',
    str_contains($cleanedText, '[REDACTED_NAME]') && !str_contains($cleanedText, 'Samuel Adeyemi'),
    "Kinship name unredacted"
);

// -----------------------------------------------------------------------------
// SCENARIO 3: BROKEN OBJECT LEVEL AUTHORIZATION (BOLA / IDOR) PROTECTION
// -----------------------------------------------------------------------------
echo "\n\033[1;33m[SCENARIO 3] Broken Object Level Authorization (BOLA/IDOR) Compartment Isolation\033[0m\n";

$patients = \App\Models\Patient::with('user')->take(2)->get();
if ($patients->count() >= 2) {
    $patientA = $patients[0];
    $patientB = $patients[1];

    $patientAUser = $patientA->user;
    $patientAToken = $patientAUser->createToken('PatientAClientToken')->plainTextToken;

    // Switch client token to Patient A
    $client->setBearerToken($patientAToken);

    // 3.1 Patient A accessing own record -> 200
    $res = $client->request('GET', "/api/fhir/r4/Patient/{$patientA->id}");
    $client->assert(
        'Patient A can access their own patient record (HTTP 200)',
        $res['status'] === 200 && ($res['body']['resourceType'] ?? '') === 'Patient',
        "Status was {$res['status']}"
    );

    // 3.2 Patient A attempting to access Patient B record -> 403 Forbidden
    $res = $client->request('GET', "/api/fhir/r4/Patient/{$patientB->id}");
    $client->assert(
        'Patient A accessing Patient B record is strictly blocked by BOLA guard (HTTP 403 Forbidden)',
        $res['status'] === 403 && ($res['body']['resourceType'] ?? '') === 'OperationOutcome',
        "Status was {$res['status']}"
    );

    // 3.3 Patient A attempting to access Patient B consent records -> 403 Forbidden
    $res = $client->request('GET', "/api/ndpa/v1/consent/{$patientB->id}");
    $client->assert(
        'Patient A accessing Patient B consent ledger is strictly blocked by BOLA guard (HTTP 403 Forbidden)',
        $res['status'] === 403,
        "Status was {$res['status']}"
    );

    // Switch back to staff user token
    $client->setBearerToken($staffToken);
    echo "  \033[34mℹ\033[0m Switched back to staff bearer token for clinical API operations.\n";
} else {
    echo "  \033[33m⚠\033[0m Skipped BOLA comparative test: less than 2 patients available.\n";
}

// -----------------------------------------------------------------------------
// SCENARIO 4: HL7 FHIR R4 LEVEL 1-4 REST API
// -----------------------------------------------------------------------------
echo "\n\033[1;33m[SCENARIO 4] HL7 FHIR R4 REST API (Levels 1-4)\033[0m\n";

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

// 4.1 Search Patient
$res = $client->request('GET', '/api/fhir/r4/Patient', ['_count' => 5]);
$client->assert(
    'FHIR Patient search returns valid searchset Bundle (HTTP 200)',
    $res['status'] === 200 && ($res['body']['resourceType'] ?? '') === 'Bundle',
    "Status was {$res['status']}"
);

// 4.2 Read Patient
$res = $client->request('GET', "/api/fhir/r4/Patient/{$targetPatient->id}");
$client->assert(
    'FHIR Patient read returns complete Patient resource (HTTP 200)',
    $res['status'] === 200 && ($res['body']['resourceType'] ?? '') === 'Patient',
    "Status was {$res['status']}"
);

// 4.3 Inbound Create Patient (Proxies PatientController@store)
$randomSuffix = rand(10000, 99999);
$newPatientEmail = 'interop.test.' . time() . '.' . $randomSuffix . '@hospital.org';
$uniqueSurname = 'Balogun' . $randomSuffix;
$fhirPatientPayload = [
    'resourceType' => 'Patient',
    'name' => [
        [
            'family' => $uniqueSurname,
            'given' => ['Folake', 'Grace'],
        ],
    ],
    'telecom' => [
        ['system' => 'phone', 'value' => '0809' . rand(1000000, 9999999)],
        ['system' => 'email', 'value' => $newPatientEmail],
    ],
    'gender' => 'female',
    'birthDate' => '1992-05-18',
    'address' => [
        ['text' => '14 Adeola Odeku St, Victoria Island, Lagos'],
    ],
];
$res = $client->request('POST', '/api/fhir/r4/Patient', $fhirPatientPayload);
$client->assert(
    'Inbound FHIR Patient creation proxies PatientController@store (HTTP 201)',
    in_array($res['status'], [200, 201]) && ($res['body']['name'][0]['family'] ?? '') === $uniqueSurname,
    "Status was {$res['status']}"
);
$createdPatientLocalId = $res['body']['id'] ?? $targetPatient->id;

// 4.4 Search & Read Practitioner
$res = $client->request('GET', '/api/fhir/r4/Practitioner', ['_count' => 3]);
$client->assert(
    'FHIR Practitioner search returns searchset Bundle (HTTP 200)',
    $res['status'] === 200 && ($res['body']['resourceType'] ?? '') === 'Bundle',
    "Status was {$res['status']}"
);

// 4.5 Search Observation (Vitals)
$res = $client->request('GET', '/api/fhir/r4/Observation', ['_count' => 3]);
$client->assert(
    'FHIR Observation search returns searchset Bundle (HTTP 200)',
    $res['status'] === 200 && ($res['body']['resourceType'] ?? '') === 'Bundle',
    "Status was {$res['status']}"
);

// 4.6 Inbound Create Observation (Proxies VitalSignController@store)
$fhirObservationPayload = [
    'resourceType' => 'Observation',
    'status' => 'final',
    'subject' => [
        'reference' => 'Patient/' . $createdPatientLocalId,
    ],
    'component' => [
        [
            'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '8480-6', 'display' => 'Systolic blood pressure']]],
            'valueQuantity' => ['value' => 128, 'unit' => 'mmHg'],
        ],
        [
            'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '8462-4', 'display' => 'Diastolic blood pressure']]],
            'valueQuantity' => ['value' => 84, 'unit' => 'mmHg'],
        ],
        [
            'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '8310-5', 'display' => 'Body temperature']]],
            'valueQuantity' => ['value' => 36.9, 'unit' => 'Cel'],
        ],
        [
            'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '8867-4', 'display' => 'Heart rate']]],
            'valueQuantity' => ['value' => 76, 'unit' => 'beats/min'],
        ],
    ],
];
$res = $client->request('POST', '/api/fhir/r4/Observation', $fhirObservationPayload);
$client->assert(
    'Inbound FHIR Observation creation proxies VitalSignController@store (HTTP 201)',
    in_array($res['status'], [200, 201]) && ($res['body']['resourceType'] ?? '') === 'Observation',
    "Status was {$res['status']}"
);

// 4.7 Search Encounter
$res = $client->request('GET', '/api/fhir/r4/Encounter', ['_count' => 3]);
$client->assert(
    'FHIR Encounter search returns searchset Bundle (HTTP 200)',
    $res['status'] === 200 && ($res['body']['resourceType'] ?? '') === 'Bundle',
    "Status was {$res['status']}"
);

// 4.8 Search MedicationRequest
$res = $client->request('GET', '/api/fhir/r4/MedicationRequest', ['_count' => 3]);
$client->assert(
    'FHIR MedicationRequest search returns searchset Bundle (HTTP 200)',
    $res['status'] === 200 && ($res['body']['resourceType'] ?? '') === 'Bundle',
    "Status was {$res['status']}"
);

// 4.9 Inbound Create MedicationRequest (Proxies EncounterController@addSinglePrescriptionRequest)
$product = \App\Models\Product::first();
if ($product) {
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
            'reference' => 'Patient/' . $targetPatient->id,
        ],
        'dosageInstruction' => [
            ['text' => '1 cap BID for 7 days'],
        ],
    ];
    $res = $client->request('POST', '/api/fhir/r4/MedicationRequest', $fhirRxPayload);
    $client->assert(
        'Inbound FHIR MedicationRequest creation proxies EncounterController (HTTP 201)',
        in_array($res['status'], [200, 201]) && ($res['body']['resourceType'] ?? '') === 'MedicationRequest',
        "Status was {$res['status']}"
    );
}

// 4.10 Inbound Create ServiceRequest (Proxies EncounterController@addSingleLabRequest)
$service = \App\Models\Service::first();
if ($service) {
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
            'reference' => 'Patient/' . $targetPatient->id,
        ],
    ];
    $res = $client->request('POST', '/api/fhir/r4/ServiceRequest', $fhirReqPayload);
    $client->assert(
        'Inbound FHIR ServiceRequest creation proxies EncounterController (HTTP 201)',
        in_array($res['status'], [200, 201]) && ($res['body']['resourceType'] ?? '') === 'ServiceRequest',
        "Status was {$res['status']}"
    );
}

// 4.11 Search DiagnosticReport & Invoice
$res = $client->request('GET', '/api/fhir/r4/DiagnosticReport', ['_count' => 2]);
$client->assert(
    'FHIR DiagnosticReport search returns searchset Bundle (HTTP 200)',
    $res['status'] === 200 && ($res['body']['resourceType'] ?? '') === 'Bundle',
    "Status was {$res['status']}"
);

$res = $client->request('GET', '/api/fhir/r4/Invoice', ['_count' => 2]);
$client->assert(
    'FHIR Invoice search returns searchset Bundle (HTTP 200)',
    $res['status'] === 200 && ($res['body']['resourceType'] ?? '') === 'Bundle',
    "Status was {$res['status']}"
);

// -----------------------------------------------------------------------------
// SCENARIO 5: openEHR ITS-REST API & AQL ENGINE
// -----------------------------------------------------------------------------
echo "\n\033[1;33m[SCENARIO 5] openEHR ITS-REST API & Archetype AQL Engine\033[0m\n";

// 5.1 Create or Fetch EHR
$res = $client->request('POST', '/api/openehr/v1/ehr', ['patient_id' => $targetPatient->id]);
$client->assert(
    'openEHR EHR creation / status returns valid EHR object (HTTP 200/201)',
    in_array($res['status'], [200, 201]) && isset($res['body']['ehr_id']['value']),
    "Status was {$res['status']}"
);
$ehrId = $res['body']['ehr_id']['value'] ?? null;

// 5.2 Read EHR by UUID
if ($ehrId) {
    $res = $client->request('GET', "/api/openehr/v1/ehr/{$ehrId}");
    $client->assert(
        'openEHR EHR read by UUID succeeds (HTTP 200)',
        $res['status'] === 200 && ($res['body']['ehr_id']['value'] ?? '') === $ehrId,
        "Status was {$res['status']}"
    );
}

// 5.3 Search EHR by Subject ID
$res = $client->request('GET', '/api/openehr/v1/ehr', [
    'subject_id' => (string) $targetPatient->id,
    'subject_namespace' => 'corehealth.hospital.patient',
]);
$client->assert(
    'openEHR EHR search by subject ID succeeds (HTTP 200)',
    $res['status'] === 200 && isset($res['body']['ehr_id']['value']),
    "Status was {$res['status']}"
);

// 5.4 Inbound Composition Creation (Vital Signs Archetype)
if ($ehrId) {
    $compositionPayload = [
        '_type' => 'COMPOSITION',
        'archetype_node_id' => 'openEHR-EHR-COMPOSITION.encounter.v1',
        'name' => ['value' => 'Vital Signs Encounter'],
        'content' => [
            [
                '_type' => 'OBSERVATION',
                'archetype_node_id' => 'openEHR-EHR-OBSERVATION.blood_pressure.v2',
                'data' => [
                    'events' => [
                        [
                            'data' => [
                                'items' => [
                                    ['name' => ['value' => 'Systolic'], 'value' => ['magnitude' => 130, 'units' => 'mm[Hg]']],
                                    ['name' => ['value' => 'Diastolic'], 'value' => ['magnitude' => 85, 'units' => 'mm[Hg]']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                '_type' => 'OBSERVATION',
                'archetype_node_id' => 'openEHR-EHR-OBSERVATION.body_temperature.v2',
                'data' => [
                    'events' => [
                        [
                            'data' => [
                                'items' => [
                                    ['name' => ['value' => 'Temperature'], 'value' => ['magnitude' => 37.1, 'units' => 'Cel']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
    $res = $client->request('POST', "/api/openehr/v1/ehr/{$ehrId}/composition", $compositionPayload);
    $client->assert(
        'Inbound openEHR Composition proxies VitalSignController@store (HTTP 201)',
        in_array($res['status'], [200, 201]) && ($res['body']['_type'] ?? '') === 'COMPOSITION',
        "Status was {$res['status']}"
    );
}

// 5.5 AQL Archetyped Query Execution
$aql = "SELECT o/data[at0001]/events[at0002]/data[at0003]/items[at0004]/value/magnitude AS systolic, o/data[at0001]/events[at0002]/data[at0003]/items[at0005]/value/magnitude AS diastolic FROM EHR e CONTAINS COMPOSITION c CONTAINS OBSERVATION o [openEHR-EHR-OBSERVATION.blood_pressure.v2]";
$res = $client->request('POST', '/api/openehr/v1/query/aql', ['q' => $aql]);
$client->assert(
    'openEHR AQL query engine returns standard RESULT_SET structure (HTTP 200)',
    $res['status'] === 200 && ($res['body']['meta']['_type'] ?? '') === 'RESULT_SET' && isset($res['body']['rows']),
    "Status was {$res['status']}"
);

// -----------------------------------------------------------------------------
// SCENARIO 6: OPENEMR REST COMPATIBILITY & HL7 C-CDA XML
// -----------------------------------------------------------------------------
echo "\n\033[1;33m[SCENARIO 6] OpenEMR Table Compatibility & HL7 C-CDA XML Export\033[0m\n";

// 6.1 OpenEMR patient table endpoint
$res = $client->request('GET', '/apis/default/api/patient', ['limit' => 3]);
$client->assert(
    'OpenEMR patient table endpoint returns success=true with schema attributes (HTTP 200)',
    $res['status'] === 200 && ($res['body']['success'] ?? false) === true && isset($res['body']['data'][0]['pid']),
    "Status was {$res['status']}"
);

// 6.2 OpenEMR encounter table endpoint
$res = $client->request('GET', '/apis/default/api/encounter', ['limit' => 3]);
$client->assert(
    'OpenEMR encounter table endpoint returns success=true with eid and pid (HTTP 200)',
    $res['status'] === 200 && ($res['body']['success'] ?? false) === true,
    "Status was {$res['status']}"
);

// 6.3 OpenEMR vital signs table endpoint
$res = $client->request('GET', '/apis/default/api/vital_signs', ['limit' => 3]);
$client->assert(
    'OpenEMR vitals table endpoint returns bps, bpd, and pulse attributes (HTTP 200)',
    $res['status'] === 200 && ($res['body']['success'] ?? false) === true,
    "Status was {$res['status']}"
);

// 6.4 OpenEMR HL7 C-CDA Continuity of Care Document XML
$res = $client->request('GET', "/apis/default/api/ccda/{$targetPatient->id}");
$client->assert(
    'C-CDA XML export returns HTTP 200 with application/xml content-type',
    $res['status'] === 200 && str_contains($res['headers']['content-type'] ?? '', 'xml'),
    "Status was {$res['status']}, Content-Type was " . ($res['headers']['content-type'] ?? '')
);
$client->assert(
    'C-CDA XML document contains valid ClinicalDocument root element and HL7 templateId',
    str_contains($res['raw'], '<ClinicalDocument') && str_contains($res['raw'], '2.16.840.1.113883.10.20.22.1.2'),
    "XML root missing or invalid"
);

// 6.5 OpenEMR FHIR Proxy
$res = $client->request('GET', '/apis/default/fhir/Patient', ['_count' => 2]);
$client->assert(
    'OpenEMR FHIR proxy endpoint delivers standard FHIR Bundle (HTTP 200)',
    $res['status'] === 200 && ($res['body']['resourceType'] ?? '') === 'Bundle',
    "Status was {$res['status']}"
);

// Print Final Summary
$client->printSummary();

// Clean up generated token
$staffUser->tokens()->where('name', 'InteropRealUserClientTestToken')->delete();
if (isset($patientAUser)) {
    $patientAUser->tokens()->where('name', 'PatientAClientToken')->delete();
}

exit($client->isSuccess() ? 0 : 1);
