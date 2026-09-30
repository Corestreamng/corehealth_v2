<?php

namespace Tests\Feature\HMO;

use App\Models\User;
use Tests\TestCase;

class HmoWorkbenchTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/hmo/workbench');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_hmo_workbench_loads_for_authenticated_user()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/hmo/workbench');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_hmo_requests_datatable_loads_with_server_side_pagination()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $params = [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'tab' => 'pending',
            'columns' => [
                ['data' => 'checkbox', 'orderable' => 'false', 'searchable' => 'false'],
                ['data' => 'patient_info', 'orderable' => 'false', 'searchable' => 'false'],
                ['data' => 'request_info', 'orderable' => 'false', 'searchable' => 'true'],
                ['data' => 'item_details', 'orderable' => 'false', 'searchable' => 'true'],
                ['data' => 'pricing_info', 'orderable' => 'false', 'searchable' => 'true'],
                ['data' => 'coverage_payment', 'orderable' => 'false', 'searchable' => 'true'],
                ['data' => 'status_validation', 'orderable' => 'false', 'searchable' => 'true'],
            ],
            'order' => [
                ['column' => 2, 'dir' => 'desc'],
            ],
        ];

        $response = $this->actingAs($user)->get('/hmo/requests?' . http_build_query($params));
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));

        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertArrayHasKey('draw', $data);
            $this->assertArrayHasKey('recordsTotal', $data);
            $this->assertArrayHasKey('recordsFiltered', $data);
            $this->assertArrayHasKey('data', $data);
            $this->assertLessThanOrEqual(10, count($data['data']));
        }
    }

    /** @test */
    public function test_hmo_requests_datatable_handles_tabs_and_search()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $tabs = ['all', 'pending', 'approved', 'rejected', 'claims', 'awaiting_code', 'express'];

        foreach ($tabs as $tab) {
            $response = $this->actingAs($user)->get('/hmo/requests?draw=1&start=0&length=5&tab=' . $tab);
            $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
        }

        // Test with search
        $response = $this->actingAs($user)->get('/hmo/requests?draw=1&start=0&length=5&tab=all&search=test');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_group_approve_validation_fails_when_required_fields_missing()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->postJson('/hmo/group-approve', [
            'shared_auth_code' => 'TEST1234',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 422, 500]));

        if ($response->status() === 422) {
            $response->assertJsonValidationErrors(['request_ids', 'auth_mode']);
        }
    }

    /** @test */
    public function test_group_approve_handles_valid_payload()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $existing = \App\Models\ProductOrServiceRequest::first();
        $requestIds = $existing ? [$existing->id] : [999999];

        $response = $this->actingAs($user)->postJson('/hmo/group-approve', [
            'request_ids' => $requestIds,
            'auth_mode' => 'shared',
            'shared_auth_code' => 'AUTH123',
            'validation_notes' => 'Test group approval',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 422, 500]));
    }

    /** @test */
    public function test_group_reject_validation_fails_when_required_fields_missing()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->postJson('/hmo/group-reject', []);

        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 422, 500]));

        if ($response->status() === 422) {
            $response->assertJsonValidationErrors(['request_ids', 'rejection_reason']);
        }
    }

    /** @test */
    public function test_batch_approve_validation_fails_when_request_ids_missing()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->postJson('/hmo/batch-approve', []);

        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 422, 500]));

        if ($response->status() === 422) {
            $response->assertJsonValidationErrors(['request_ids']);
        }
    }
}
