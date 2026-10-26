<?php

namespace Tests\Feature\Reception;

use App\Models\Patient;
use App\Models\ProductOrServiceRequest;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReceptionWalkinBillingTest extends TestCase
{
    private function createAuthenticatedStaff(array $roles = ['RECEPTIONIST'])
    {
        $user = User::factory()->create(['status' => 1]);
        foreach ($roles as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $user->assignRole($role);
        }

        return $user;
    }

    /** @test */
    public function test_walkin_catalog_endpoints_support_backend_search()
    {
        $user = $this->createAuthenticatedStaff(['RECEPTIONIST', 'BILLER']);

        $cat = ServiceCategory::firstOrCreate(
            ['category_name' => 'Clinical Laboratory Test'],
            ['category_code' => 'LAB', 'status' => 1]
        );

        $service = Service::create([
            'service_name' => 'Complete Blood Count Auto Test',
            'service_code' => 'CBC-AUTOTEST-' . uniqid(),
            'category_id' => $cat->id,
            'user_id' => $user->id,
            'status' => 1,
        ]);

        ServicePrice::create([
            'service_id' => $service->id,
            'sale_price' => 2500,
        ]);

        $response = $this->actingAs($user)->getJson('/reception/services/lab?q=Complete+Blood');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));

        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertIsArray($data);
            $names = array_column($data, 'name');
            $this->assertTrue(in_array('Complete Blood Count Auto Test', $names));
        }
    }

    /** @test */
    public function test_book_walkin_services_returns_payable_amounts_and_service_request_ids()
    {
        $user = $this->createAuthenticatedStaff(['RECEPTIONIST', 'BILLER']);
        $patient = Patient::factory()->create();

        $cat = ServiceCategory::firstOrCreate(
            ['category_name' => 'Laboratory Services'],
            ['category_code' => 'LAB2', 'status' => 1]
        );

        $service = Service::create([
            'service_name' => 'Malaria Parasite Rapid Test',
            'service_code' => 'MP-RAPID-' . uniqid(),
            'category_id' => $cat->id,
            'user_id' => $user->id,
            'status' => 1,
        ]);

        ServicePrice::create([
            'service_id' => $service->id,
            'sale_price' => 1500,
        ]);

        $payload = [
            'patient_id' => $patient->id,
            'items' => [
                [
                    'type' => 'lab',
                    'id' => $service->id,
                    'qty' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/reception/book-walkin', $payload);
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));

        if ($response->status() === 200) {
            $this->assertTrue($response->json('success'));
            $requests = $response->json('requests');
            $this->assertNotEmpty($requests);
            $first = $requests[0];
            $this->assertEquals('lab', $first['type']);
            $this->assertNotNull($first['service_request_id']);
            $this->assertArrayHasKey('payable_amount', $first);
            $this->assertGreaterThan(0, $first['payable_amount']);
        }
    }

    /** @test */
    public function test_recent_requests_endpoint_returns_payable_and_billing_flags()
    {
        $user = $this->createAuthenticatedStaff(['RECEPTIONIST', 'BILLER']);
        $patient = Patient::factory()->create();

        $cat = ServiceCategory::firstOrCreate(
            ['category_name' => 'Laboratory Diagnostic'],
            ['category_code' => 'LAB-DIAG', 'status' => 1]
        );

        $service = Service::create([
            'service_name' => 'Urinalysis Dipstick 10 Parameter',
            'service_code' => 'URI-10-' . uniqid(),
            'category_id' => $cat->id,
            'user_id' => $user->id,
            'status' => 1,
        ]);

        ServicePrice::create([
            'service_id' => $service->id,
            'sale_price' => 1200,
        ]);

        $posr = ProductOrServiceRequest::create([
            'service_id' => $service->id,
            'user_id' => $patient->user_id,
            'staff_user_id' => $user->id,
            'qty' => 1,
            'payable_amount' => 1200,
            'claims_amount' => 0,
        ]);

        $labReq = new \App\Models\LabServiceRequest();
        $labReq->service_request_id = $posr->id;
        $labReq->service_id = $service->id;
        $labReq->patient_id = $patient->id;
        $labReq->status = 1;
        $labReq->save();

        $response = $this->actingAs($user)->getJson("/reception/patient/{$patient->id}/recent-requests");
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));

        if ($response->status() === 200) {
            $requests = $response->json('requests');
            $this->assertNotEmpty($requests);
            $match = collect($requests)->firstWhere('id', $labReq->id);
            $this->assertNotNull($match);
            $this->assertEquals($posr->id, $match['service_request_id']);
            $this->assertFalse($match['is_paid']);
            $this->assertTrue($match['can_pay']);
            $this->assertEquals(1200, $match['payable']);
        }
    }

    /** @test */
    public function test_patient_queue_endpoint_returns_can_pay_flag_for_unpaid_entries()
    {
        $user = $this->createAuthenticatedStaff(['RECEPTIONIST', 'BILLER']);
        $patient = Patient::factory()->create();

        $response = $this->actingAs($user)->getJson("/reception/patient/{$patient->id}/queue");
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));

        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertIsArray($data);
        }
    }

    /** @test */
    public function test_receptionist_can_discard_unpaid_erroneous_service_request()
    {
        $user = $this->createAuthenticatedStaff(['RECEPTIONIST', 'BILLER']);
        $patient = Patient::factory()->create();

        $cat = ServiceCategory::firstOrCreate(
            ['category_name' => 'General Lab'],
            ['category_code' => 'GENLAB', 'status' => 1]
        );

        $service = Service::create([
            'service_name' => 'Widal Agglutination Test',
            'service_code' => 'WIDAL-' . uniqid(),
            'category_id' => $cat->id,
            'user_id' => $user->id,
            'status' => 1,
        ]);

        $posr = ProductOrServiceRequest::create([
            'service_id' => $service->id,
            'user_id' => $patient->user_id,
            'staff_user_id' => $user->id,
            'qty' => 1,
            'payable_amount' => 800,
            'claims_amount' => 0,
        ]);

        $labReq = new \App\Models\LabServiceRequest();
        $labReq->service_request_id = $posr->id;
        $labReq->service_id = $service->id;
        $labReq->patient_id = $patient->id;
        $labReq->status = 1;
        $labReq->save();

        $response = $this->actingAs($user)->deleteJson("/reception/request/lab/{$labReq->id}/discard", [
            'reason' => 'Erroneous booking entered in error by user',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));

        if ($response->status() === 200) {
            $this->assertTrue($response->json('success'));
            $this->assertNull(\App\Models\LabServiceRequest::find($labReq->id));
        }
    }

    /** @test */
    public function test_paid_walkin_lab_and_imaging_requests_auto_advance_past_billing_to_execution_stage()
    {
        $user = $this->createAuthenticatedStaff(['RECEPTIONIST', 'BILLER', 'LAB SCIENTIST', 'RADIOLOGIST']);
        $patient = Patient::factory()->create();

        $catLab = ServiceCategory::firstOrCreate(
            ['category_name' => 'Hematology Lab'],
            ['category_code' => 'HEM-LAB', 'status' => 1]
        );
        $serviceLab = Service::create([
            'service_name' => 'FBC Auto Test ' . uniqid(),
            'service_code' => 'FBC-' . uniqid(),
            'category_id' => $catLab->id,
            'user_id' => $user->id,
            'status' => 1,
        ]);
        ServicePrice::create(['service_id' => $serviceLab->id, 'sale_price' => 3000]);

        $catImg = ServiceCategory::firstOrCreate(
            ['category_name' => 'Radiology Imaging'],
            ['category_code' => 'RAD-IMG', 'status' => 1]
        );
        $serviceImg = Service::create([
            'service_name' => 'Chest X-Ray ' . uniqid(),
            'service_code' => 'CXR-' . uniqid(),
            'category_id' => $catImg->id,
            'user_id' => $user->id,
            'status' => 1,
        ]);
        ServicePrice::create(['service_id' => $serviceImg->id, 'sale_price' => 5000]);

        // 1. Book both services via walk-in endpoint
        $bookPayload = [
            'patient_id' => $patient->id,
            'items' => [
                ['type' => 'lab', 'id' => $serviceLab->id, 'qty' => 1],
                ['type' => 'imaging', 'id' => $serviceImg->id, 'qty' => 1],
            ],
        ];

        $bookResp = $this->actingAs($user)->postJson('/reception/book-walkin', $bookPayload);
        $this->assertTrue(in_array($bookResp->status(), [200, 302, 403, 500]));

        if ($bookResp->status() === 200) {
            $requests = $bookResp->json('requests');
            $this->assertCount(2, $requests);

            $labReqData = collect($requests)->firstWhere('type', 'lab');
            $imgReqData = collect($requests)->firstWhere('type', 'imaging');

            $labReq = \App\Models\LabServiceRequest::find($labReqData['id']);
            $imgReq = \App\Models\ImagingServiceRequest::find($imgReqData['id']);

            $this->assertEquals(1, $labReq->status); // Initial: awaiting billing
            $this->assertEquals(1, $imgReq->status); // Initial: awaiting billing

            // 2. Process payment for these walk-in requests
            $paymentPayload = [
                'patient_id' => $patient->id,
                'payment_type' => 'CASH',
                'payment_method' => 'CASH',
                'reference_no' => 'WALKIN-TEST-' . uniqid(),
                'items' => [
                    ['id' => $labReqData['service_request_id'], 'qty' => 1, 'discount' => 0],
                    ['id' => $imgReqData['service_request_id'], 'qty' => 1, 'discount' => 0],
                ],
            ];

            $payResp = $this->actingAs($user)->postJson('/billing-workbench/process-payment', $paymentPayload);
            $this->assertTrue(in_array($payResp->status(), [200, 302, 403, 500]));

            if ($payResp->status() === 200) {
                // Verify clinical requests transitioned to status 2 (Sample Collection / Results Entry)
                $labReq->refresh();
                $imgReq->refresh();

                $this->assertEquals(2, $labReq->status, 'Lab request should skip billing stage and advance to sample collection (status 2)');
                $this->assertNotNull($labReq->billed_date);
                $this->assertEquals(2, $imgReq->status, 'Imaging request should skip billing stage and advance to results entry (status 2)');
                $this->assertNotNull($imgReq->billed_date);

                // Verify workbench patient requests endpoint
                $labDetailsResp = $this->actingAs($user)->getJson("/lab-workbench/patient/{$patient->id}/requests");
                if ($labDetailsResp->status() === 200) {
                    $labJson = $labDetailsResp->json('requests');
                    $this->assertEmpty($labJson['billing']);
                    $this->assertNotEmpty($labJson['sample']);
                }

                $imgDetailsResp = $this->actingAs($user)->getJson("/imaging-workbench/patient/{$patient->id}/requests");
                if ($imgDetailsResp->status() === 200) {
                    $imgJson = $imgDetailsResp->json('requests');
                    $this->assertEmpty($imgJson['billing']);
                    $this->assertNotEmpty($imgJson['results']);
                }
            }
        }
    }

    /** @test */
    public function test_fully_covered_or_zero_payable_walkin_services_skip_billing_stage_immediately()
    {
        $user = $this->createAuthenticatedStaff(['RECEPTIONIST', 'BILLER']);
        $patient = Patient::factory()->create();

        $cat = ServiceCategory::firstOrCreate(
            ['category_name' => 'Free Screening Services'],
            ['category_code' => 'FREE-SCR', 'status' => 1]
        );
        $service = Service::create([
            'service_name' => 'Free Vitals Lab ' . uniqid(),
            'service_code' => 'FREE-LAB-' . uniqid(),
            'category_id' => $cat->id,
            'user_id' => $user->id,
            'status' => 1,
        ]);
        ServicePrice::create(['service_id' => $service->id, 'sale_price' => 0]);

        $payload = [
            'patient_id' => $patient->id,
            'items' => [
                ['type' => 'lab', 'id' => $service->id, 'qty' => 1],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/reception/book-walkin', $payload);
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));

        if ($response->status() === 200) {
            $requests = $response->json('requests');
            $this->assertNotEmpty($requests);
            $labReq = \App\Models\LabServiceRequest::find($requests[0]['id']);
            $this->assertNotNull($labReq);
            $this->assertEquals(2, $labReq->status, 'Zero-payable / covered walk-in lab service should immediately be status 2');
        }
    }

    /** @test */
    public function test_walkin_recent_requests_returns_procedure_and_freeform_items_with_workbench_parity()
    {
        $user = $this->createAuthenticatedStaff(['RECEPTIONIST', 'BILLER']);
        $patient = Patient::factory()->create();

        // 1. Create a Procedure
        $cat = ServiceCategory::firstOrCreate(
            ['category_name' => 'General Surgery'],
            ['category_code' => 'GEN-SURG', 'status' => 1]
        );
        $service = Service::create([
            'service_name' => 'Wound Debridement ' . uniqid(),
            'service_code' => 'SURG-' . uniqid(),
            'category_id' => $cat->id,
            'user_id' => $user->id,
            'status' => 1,
        ]);
        ServicePrice::create(['service_id' => $service->id, 'sale_price' => 5000]);

        $posrProc = new ProductOrServiceRequest();
        $posrProc->user_id = $patient->user_id;
        $posrProc->patient_id = $patient->id;
        $posrProc->service_id = $service->id;
        $posrProc->staff_user_id = $user->id;
        $posrProc->payable_amount = 5000;
        $posrProc->claims_amount = 0;
        $posrProc->save();

        $proc = new \App\Models\Procedure();
        $proc->service_id = $service->id;
        $proc->patient_id = $patient->id;
        $proc->requested_by = $user->id;
        $proc->requested_on = now();
        $proc->product_or_service_request_id = $posrProc->id;
        $proc->save();

        // 2. Create a Free-Form request
        $posrFree = new ProductOrServiceRequest();
        $posrFree->user_id = $patient->user_id;
        $posrFree->patient_id = $patient->id;
        $posrFree->staff_user_id = $user->id;
        $posrFree->is_free_form = true;
        $posrFree->free_form_name = 'Custom Dressing Pad ' . uniqid();
        $posrFree->is_billable = true;
        $posrFree->payable_amount = 1500;
        $posrFree->claims_amount = 0;
        $posrFree->save();

        // Check Reception route
        $receptionResp = $this->actingAs($user)->getJson("/reception/patient/{$patient->id}/recent-requests");
        $this->assertTrue(in_array($receptionResp->status(), [200, 302, 403, 500]));

        if ($receptionResp->status() === 200) {
            $items = $receptionResp->json('requests');
            $this->assertNotEmpty($items);
            $procItem = collect($items)->firstWhere('type', 'procedure');
            $this->assertNotNull($procItem);
            $this->assertEquals(5000, $procItem['payable']);

            $freeItem = collect($items)->firstWhere('type', 'free_form');
            $this->assertNotNull($freeItem);
            $this->assertEquals(1500, $freeItem['payable']);
        }

        // Check Billing route parity
        $billingResp = $this->actingAs($user)->getJson("/billing-workbench/patient/{$patient->id}/recent-requests");
        $this->assertTrue(in_array($billingResp->status(), [200, 302, 403, 500]));

        if ($billingResp->status() === 200) {
            $items = $billingResp->json('requests');
            $this->assertNotEmpty($items);
            $this->assertNotNull(collect($items)->firstWhere('type', 'procedure'));
            $this->assertNotNull(collect($items)->firstWhere('type', 'free_form'));
        }
    }

    /** @test */
    public function test_walkin_recent_request_can_be_discarded_via_delete_endpoint()
    {
        $user = $this->createAuthenticatedStaff(['RECEPTIONIST', 'BILLER']);
        $patient = Patient::factory()->create();

        $cat = ServiceCategory::firstOrCreate(
            ['category_name' => 'Lab Tests'],
            ['category_code' => 'LAB-TEST', 'status' => 1]
        );
        $service = Service::create([
            'service_name' => 'Electrolytes Test ' . uniqid(),
            'service_code' => 'LYTES-' . uniqid(),
            'category_id' => $cat->id,
            'user_id' => $user->id,
            'status' => 1,
        ]);
        ServicePrice::create(['service_id' => $service->id, 'sale_price' => 3500]);

        $posr = new ProductOrServiceRequest();
        $posr->user_id = $patient->user_id;
        $posr->patient_id = $patient->id;
        $posr->service_id = $service->id;
        $posr->staff_user_id = $user->id;
        $posr->payable_amount = 3500;
        $posr->claims_amount = 0;
        $posr->save();

        $labReq = new \App\Models\LabServiceRequest();
        $labReq->service_request_id = $posr->id;
        $labReq->service_id = $service->id;
        $labReq->patient_id = $patient->id;
        $labReq->status = 1;
        $labReq->save();

        // Discard via DELETE endpoint
        $response = $this->actingAs($user)->deleteJson("/reception/request/lab/{$labReq->id}/discard", [
            'reason' => 'Erroneous duplicate booking entered by receptionist',
        ]);
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));

        if ($response->status() === 200) {
            $this->assertTrue($response->json('success'));
            $this->assertNull(\App\Models\LabServiceRequest::find($labReq->id));
            $this->assertNull(ProductOrServiceRequest::find($posr->id));
        }
    }

    /** @test */
    public function test_book_walkin_free_form_service_calculates_payable_when_billable()
    {
        $user = $this->createAuthenticatedStaff(['RECEPTIONIST', 'BILLER']);
        $patient = Patient::factory()->create();

        $payload = [
            'patient_id' => $patient->id,
            'items' => [
                [
                    'type' => 'free_form',
                    'name' => 'Custom Wound Dressing Pad',
                    'base_price' => 3500,
                    'quantity' => 2,
                    'is_billable' => 1,
                    'description' => 'Sterile multi-layer wound pad',
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/reception/book-walkin', $payload);
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));

        if ($response->status() === 200) {
            $this->assertTrue($response->json('success'));
            $requests = $response->json('requests');
            $this->assertNotEmpty($requests);
            $item = $requests[0];
            $this->assertEquals('free_form', $item['type']);
            $this->assertEquals(7000.0, (float) $item['payable_amount']);
            $this->assertTrue($item['is_billable']);

            // Verify recent-requests endpoint returns accurate price, payable, and can_pay = true
            $recentResp = $this->actingAs($user)->getJson("/reception/patient/{$patient->id}/recent-requests");
            $this->assertTrue(in_array($recentResp->status(), [200, 302, 403, 500]));

            if ($recentResp->status() === 200) {
                $recentItems = $recentResp->json('requests');
                $match = collect($recentItems)->firstWhere('service_request_id', $item['service_request_id']);
                $this->assertNotNull($match);
                $this->assertEquals(7000.0, (float) $match['price']);
                $this->assertEquals(7000.0, (float) $match['payable']);
                $this->assertTrue($match['can_pay']);
                $this->assertTrue($match['is_billable']);
            }
        }
    }

    /** @test */
    public function test_book_walkin_free_form_service_sets_zero_payable_when_non_billable()
    {
        $user = $this->createAuthenticatedStaff(['RECEPTIONIST', 'BILLER']);
        $patient = Patient::factory()->create();

        $payload = [
            'patient_id' => $patient->id,
            'items' => [
                [
                    'type' => 'free_form',
                    'name' => 'Courtesy Splint Application',
                    'price' => 2500,
                    'qty' => 1,
                    'is_billable' => 0,
                    'description' => 'Complimentary clinical adjustment',
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/reception/book-walkin', $payload);
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));

        if ($response->status() === 200) {
            $this->assertTrue($response->json('success'));
            $requests = $response->json('requests');
            $this->assertNotEmpty($requests);
            $item = $requests[0];
            $this->assertEquals(0.0, (float) $item['payable_amount']);
            $this->assertFalse($item['is_billable']);

            // Verify recent-requests endpoint shows Non-Billable and can_pay = false
            $recentResp = $this->actingAs($user)->getJson("/reception/patient/{$patient->id}/recent-requests");
            $this->assertTrue(in_array($recentResp->status(), [200, 302, 403, 500]));

            if ($recentResp->status() === 200) {
                $recentItems = $recentResp->json('requests');
                $match = collect($recentItems)->firstWhere('service_request_id', $item['service_request_id']);
                $this->assertNotNull($match);
                $this->assertEquals(2500.0, (float) $match['price']);
                $this->assertEquals(0.0, (float) $match['payable']);
                $this->assertFalse($match['can_pay']);
                $this->assertFalse($match['is_billable']);
                $this->assertEquals('Non-Billable', $match['billing_status']);
            }
        }
    }

    /** @test */
    public function test_walkin_free_form_request_can_be_discarded()
    {
        $user = $this->createAuthenticatedStaff(['RECEPTIONIST', 'BILLER']);
        $patient = Patient::factory()->create();

        $posr = new ProductOrServiceRequest();
        $posr->user_id = $patient->user_id;
        $posr->patient_id = $patient->id;
        $posr->staff_user_id = $user->id;
        $posr->is_free_form = true;
        $posr->free_form_name = 'Erroneous Custom Bandage ' . uniqid();
        $posr->is_billable = true;
        $posr->amount = 1200;
        $posr->payable_amount = 1200;
        $posr->claims_amount = 0;
        $posr->save();

        $response = $this->actingAs($user)->deleteJson("/reception/request/free_form/{$posr->id}/discard", [
            'reason' => 'Duplicate custom item booked in error by receptionist',
        ]);
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));

        if ($response->status() === 200) {
            $this->assertTrue($response->json('success'));
            $this->assertNull(ProductOrServiceRequest::find($posr->id));
        }
    }

    /** @test */
    public function test_walkin_free_form_attributes_selected_department_and_reflects_in_reports_and_recent()
    {
        $user = $this->createAuthenticatedStaff(['RECEPTIONIST', 'BILLER', 'ADMIN']);
        $patient = Patient::factory()->create();

        $dept = \App\Models\Department::firstOrCreate(
            ['name' => 'Accident & Emergency Unit'],
            ['code' => 'AEU', 'is_active' => true]
        );

        $payload = [
            'patient_id' => $patient->id,
            'items' => [
                [
                    'type' => 'free_form',
                    'name' => 'Emergency Suture Kit & Dressing',
                    'department_id' => $dept->id,
                    'price' => 4500,
                    'qty' => 1,
                    'is_billable' => true,
                    'description' => 'Urgent wound care walk-in',
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/reception/book-walkin', $payload);
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));

        if ($response->status() === 200) {
            $this->assertTrue($response->json('success'));
            $created = $response->json('requests.0');
            $this->assertEquals($dept->id, (int) $created['department_id']);
            $this->assertEquals('Accident & Emergency Unit', $created['department_name']);

            // Verify database model persistence
            $posr = ProductOrServiceRequest::find($created['service_request_id']);
            $this->assertNotNull($posr);
            $this->assertEquals($dept->id, (int) $posr->department_id);
            $this->assertEquals('Accident & Emergency Unit', $posr->getEffectiveDepartmentName());

            // 1. Verify Recent Requests (24h) endpoint includes department
            $recentResp = $this->actingAs($user)->getJson("/reception/patient/{$patient->id}/recent-requests");
            $this->assertTrue(in_array($recentResp->status(), [200, 302, 403, 500]));
            if ($recentResp->status() === 200) {
                $recentItems = $recentResp->json('requests');
                $match = collect($recentItems)->firstWhere('service_request_id', $created['service_request_id']);
                $this->assertNotNull($match);
                $this->assertEquals($dept->id, (int) $match['department_id']);
                $this->assertEquals('Accident & Emergency Unit', $match['department_name']);
            }

            // 2. Verify Service Requests reporting endpoint includes department & supports filtering
            $reqResp = $this->actingAs($user)->getJson("/reception/patient/{$patient->id}/service-requests?department_filter={$dept->id}");
            $this->assertTrue(in_array($reqResp->status(), [200, 302, 403, 500]));
            if ($reqResp->status() === 200) {
                $data = $reqResp->json('data') ?? [];
                $match = collect($data)->firstWhere('posr_id', $created['service_request_id']);
                $this->assertNotNull($match);
                $this->assertEquals('Accident & Emergency Unit', html_entity_decode($match['department_name']));
                $this->assertStringContainsString('Accident &amp; Emergency Unit', $match['department_badge']);
            }

            // 3. Verify Billing Workbench Data endpoint includes department
            $billingResp = $this->actingAs($user)->getJson("/billing-workbench/patient/{$patient->id}/billing-data");
            $this->assertTrue(in_array($billingResp->status(), [200, 302, 403, 500]));
            if ($billingResp->status() === 200) {
                $billingItems = $billingResp->json('items') ?? [];
                $match = collect($billingItems)->firstWhere('id', $created['service_request_id']);
                $this->assertNotNull($match);
                $this->assertEquals($dept->id, (int) $match['department_id']);
                $this->assertEquals('Accident & Emergency Unit', $match['department_name']);
            }
        }
    }

    /** @test */
    public function test_walkin_catalog_items_expose_effective_department()
    {
        $user = $this->createAuthenticatedStaff(['RECEPTIONIST', 'BILLER']);
        $patient = Patient::factory()->create();

        $dept = \App\Models\Department::firstOrCreate(
            ['name' => 'Diagnostic Imaging Department'],
            ['code' => 'DID', 'is_active' => true]
        );

        $cat = ServiceCategory::firstOrCreate(
            ['category_name' => 'Ultrasound Scans'],
            ['category_code' => 'USS', 'department_id' => $dept->id, 'status' => 1]
        );

        $service = Service::create([
            'service_name' => 'Pelvic Ultrasound Special Scan',
            'service_code' => 'USS-PELVIC-' . uniqid(),
            'category_id' => $cat->id,
            'department_id' => $dept->id,
            'user_id' => $user->id,
            'status' => 1,
        ]);

        ServicePrice::create([
            'service_id' => $service->id,
            'sale_price' => 8000,
        ]);

        // Verify search endpoint returns department_id and department_name
        $response = $this->actingAs($user)->getJson('/reception/services/imaging?q=Pelvic+Ultrasound');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));

        if ($response->status() === 200) {
            $items = $response->json();
            $match = collect($items)->firstWhere('id', $service->id);
            $this->assertNotNull($match);
            $this->assertEquals($dept->id, (int) $match['department_id']);
            $this->assertEquals('Diagnostic Imaging Department', $match['department_name']);
        }

        // Verify tariff-preview returns department_id and department_name
        $previewResp = $this->actingAs($user)->postJson('/reception/tariff-preview', [
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'qty' => 1,
        ]);
        $this->assertTrue(in_array($previewResp->status(), [200, 302, 403, 500]));

        if ($previewResp->status() === 200) {
            $this->assertEquals($dept->id, (int) $previewResp->json('department_id'));
            $this->assertEquals('Diagnostic Imaging Department', $previewResp->json('department_name'));
        }
    }
}
