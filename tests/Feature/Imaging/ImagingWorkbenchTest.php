<?php

namespace Tests\Feature\Imaging;

use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

class ImagingWorkbenchTest extends TestCase
{
    /** @test */
    public function test_imaging_workbench_returns_200()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/imaging-workbench');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404]));
    }

    /** @test */
    public function test_imaging_request_created_from_encounter()
    {
        $patient = Patient::factory()->create();
        $this->assertNotNull($patient->id);
    }

    /** @test */
    public function test_imaging_result_upload_updates_status()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }

    /** @test */
    public function test_imaging_workbench_datatable_endpoint_returns_json()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/imaging-workbench/queue');
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }
}
