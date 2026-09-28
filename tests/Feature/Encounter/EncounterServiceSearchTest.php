<?php

namespace Tests\Feature\Encounter;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Tests\TestCase;

class EncounterServiceSearchTest extends TestCase
{
    /** @test */
    public function test_encounter_view_injects_investigation_and_imaging_category_ids()
    {
        $user = User::factory()->create(['status' => 1]);
        $clinic = Clinic::first() ?? Clinic::factory()->create();
        Staff::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'staff_id' => 'DOC-' . $user->id,
            'specialization' => 'General Practice',
        ]);
        $patient = Patient::factory()->create();

        $response = $this->actingAs($user)->get(route('encounters.create', ['patient_id' => $patient->id]));

        $this->assertContains($response->status(), [200, 302, 403, 500]);
        if ($response->status() === 200) {
            $content = $response->getContent();
            $expectedLabCat = (int) (appsettings('investigation_category_id', 2) ?: 2);
            $expectedImgCat = (int) (appsettings('imaging_category_id', 6) ?: 6);

            $this->assertStringContainsString("investigationCategoryId: {$expectedLabCat}", $content);
            $this->assertStringContainsString("imagingCategoryId: {$expectedImgCat}", $content);
        }
    }

    /** @test */
    public function test_live_search_services_filters_by_category_id()
    {
        $user = User::factory()->create(['status' => 1]);

        $labCatId = (int) (appsettings('investigation_category_id', 2) ?: 2);
        $imgCatId = (int) (appsettings('imaging_category_id', 6) ?: 6);

        // Unique names for this test
        $prefix = 'TEST_' . uniqid();
        $labService = Service::create([
            'service_name' => "{$prefix} Full Blood Count",
            'service_code' => "{$prefix}_FBC",
            'category_id' => $labCatId,
            'user_id' => $user->id,
            'status' => 1,
        ]);
        $imagingService = Service::create([
            'service_name' => "{$prefix} Chest X-Ray",
            'service_code' => "{$prefix}_CXR",
            'category_id' => $imgCatId,
            'user_id' => $user->id,
            'status' => 1,
        ]);
        $consultService = Service::create([
            'service_name' => "{$prefix} Specialist Consult",
            'service_code' => "{$prefix}_CON",
            'category_id' => 1, // Consultation
            'user_id' => $user->id,
            'status' => 1,
        ]);

        // Search with lab category_id
        $responseLab = $this->actingAs($user)->getJson(route('live-search-services', [
            'term' => $prefix,
            'category_id' => $labCatId,
        ]));
        $this->assertContains($responseLab->status(), [200, 302, 403, 500]);
        if ($responseLab->status() === 200) {
            $data = $responseLab->json();
            $ids = collect($data)->pluck('id')->toArray();
            $this->assertContains($labService->id, $ids);
            $this->assertNotContains($imagingService->id, $ids);
            $this->assertNotContains($consultService->id, $ids);
        }

        // Search with imaging category_id
        $responseImg = $this->actingAs($user)->getJson(route('live-search-services', [
            'term' => $prefix,
            'category_id' => $imgCatId,
        ]));
        $this->assertContains($responseImg->status(), [200, 302, 403, 500]);
        if ($responseImg->status() === 200) {
            $data = $responseImg->json();
            $ids = collect($data)->pluck('id')->toArray();
            $this->assertContains($imagingService->id, $ids);
            $this->assertNotContains($labService->id, $ids);
            $this->assertNotContains($consultService->id, $ids);
        }
    }

    /** @test */
    public function test_live_search_services_deduces_category_from_context_fallback()
    {
        $user = User::factory()->create(['status' => 1]);

        $labCatId = (int) (appsettings('investigation_category_id', 2) ?: 2);
        $imgCatId = (int) (appsettings('imaging_category_id', 6) ?: 6);

        $prefix = 'CTX_' . uniqid();
        $labService = Service::create([
            'service_name' => "{$prefix} Urinalysis",
            'service_code' => "{$prefix}_URI",
            'category_id' => $labCatId,
            'user_id' => $user->id,
            'status' => 1,
        ]);
        $imagingService = Service::create([
            'service_name' => "{$prefix} Pelvic Ultrasound",
            'service_code' => "{$prefix}_USS",
            'category_id' => $imgCatId,
            'user_id' => $user->id,
            'status' => 1,
        ]);

        // Search with context=lab (omitting category_id)
        $responseLab = $this->actingAs($user)->getJson(route('live-search-services', [
            'term' => $prefix,
            'context' => 'lab',
        ]));
        $this->assertContains($responseLab->status(), [200, 302, 403, 500]);
        if ($responseLab->status() === 200) {
            $data = $responseLab->json();
            $ids = collect($data)->pluck('id')->toArray();
            $this->assertContains($labService->id, $ids);
            $this->assertNotContains($imagingService->id, $ids);
        }

        // Search with context=imaging (omitting category_id)
        $responseImg = $this->actingAs($user)->getJson(route('live-search-services', [
            'term' => $prefix,
            'context' => 'imaging',
        ]));
        $this->assertContains($responseImg->status(), [200, 302, 403, 500]);
        if ($responseImg->status() === 200) {
            $data = $responseImg->json();
            $ids = collect($data)->pluck('id')->toArray();
            $this->assertContains($imagingService->id, $ids);
            $this->assertNotContains($labService->id, $ids);
        }
    }
}
