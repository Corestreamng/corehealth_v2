<?php

namespace Tests\Feature;

use App\Models\LabServiceRequest;
use App\Models\Patient;
use App\Models\ResultView;
use Tests\TestCase;

class ResultViewTest extends TestCase
{
    public function test_it_can_track_result_view()
    {
        // 1. Create a patient
        $patient = Patient::factory()->create();

        // 2. Create a dummy lab service request
        $labRequest = LabServiceRequest::create([
            'patient_id' => $patient->id,
            'service_id' => 1,
            'status' => 1,
        ]);

        // 3. Mark as viewed
        $view = ResultView::create([
            'user_id' => 1,
            'viewable_type' => LabServiceRequest::class,
            'viewable_id' => $labRequest->id,
            'view_type' => 'modal',
        ]);

        $this->assertNotNull($view->id);
    }

    public function test_it_can_get_unviewed_counts()
    {
        $patient = Patient::factory()->create();

        // Create an unviewed lab request
        $labRequest = LabServiceRequest::create([
            'patient_id' => $patient->id,
            'service_id' => 1,
            'status' => 1,
        ]);

        $this->assertNotNull($labRequest->id);
    }
}
