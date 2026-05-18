<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\LabServiceRequest;
use App\Models\ImagingServiceRequest;
use App\Models\ResultView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_track_result_view()
    {
        // 1. Create a patient
        $patient = Patient::factory()->create();

        // 2. Create a dummy lab service request
        $labRequest = LabServiceRequest::create([
            'patient_id' => $patient->id,
            'service_id' => 1,
            'status' => 'completed',
            'result_status' => 'completed',
            'result_released' => 1,
        ]);

        // 3. Track result view via POST request
        $response = $this->postJson(route('result-views.store'), [
            'viewable_type' => LabServiceRequest::class,
            'viewable_id' => $labRequest->id,
            'view_type' => 'modal',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // 4. Assert it is tracked in the database
        $this->assertDatabaseHas('result_views', [
            'viewable_type' => LabServiceRequest::class,
            'viewable_id' => $labRequest->id,
            'view_type' => 'modal',
        ]);
    }

    public function test_it_can_get_unviewed_counts()
    {
        $patient = Patient::factory()->create();

        // Create an unviewed lab request
        $labRequest = LabServiceRequest::create([
            'patient_id' => $patient->id,
            'service_id' => 1,
            'status' => 'completed',
            'result_status' => 'completed',
            'result_released' => 1,
        ]);

        // Create an unviewed imaging request
        $imagingRequest = ImagingServiceRequest::create([
            'patient_id' => $patient->id,
            'service_id' => 2,
            'status' => 'completed',
            'result_status' => 'completed',
            'result_released' => 1,
        ]);

        // Call the unviewed counts route
        $response = $this->getJson(route('result-views.unviewed', ['patient_id' => $patient->id]));

        $response->assertStatus(200);
        $response->assertJson([
            'lab_unviewed' => 1,
            'imaging_unviewed' => 1,
        ]);

        // View the lab request
        ResultView::create([
            'viewable_type' => LabServiceRequest::class,
            'viewable_id' => $labRequest->id,
            'view_type' => 'modal',
            'user_id' => 1,
        ]);

        // Count should decrease
        $response = $this->getJson(route('result-views.unviewed', ['patient_id' => $patient->id]));
        $response->assertJson([
            'lab_unviewed' => 0,
            'imaging_unviewed' => 1,
        ]);
    }
}
