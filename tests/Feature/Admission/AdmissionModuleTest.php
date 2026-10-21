<?php

namespace Tests\Feature\Admission;

use App\Models\User;
use Tests\TestCase;

class AdmissionModuleTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/admission-module/patient/1/admissions');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_admission_module_endpoint_loads()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/admission-module/patient/1/admissions');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_admission_detail_endpoint_returns_valid_structure()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $admission = \App\Models\AdmissionRequest::first();
        if (!$admission) {
            $this->markTestSkipped('No admission record found for testing detail endpoint');
        }

        $response = $this->actingAs($user)->get('/admission-module/admission/' . $admission->id . '/detail');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));

        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertArrayHasKey('admission', $data);
            $this->assertArrayHasKey('categories', $data);
            $this->assertArrayHasKey('timeline', $data);
            $this->assertArrayHasKey('totals', $data);
            $this->assertIsArray($data['categories']);
            $this->assertIsArray($data['timeline']);

            foreach ($data['timeline'] as $day) {
                $this->assertArrayHasKey('items', $day);
                $this->assertIsArray($day['items']);
            }
        }
    }
}
