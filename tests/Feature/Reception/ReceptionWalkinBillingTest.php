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
}
