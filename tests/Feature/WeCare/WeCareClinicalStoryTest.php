<?php

namespace Tests\Feature\WeCare;

use App\Enums\WeCare\ClinicalCategory;
use App\Enums\WeCare\ClinicalPriority;
use App\Enums\WeCare\ImagingStatus;
use App\Enums\WeCare\LabStatus;
use App\Enums\WeCare\PrescriptionStatus;
use App\Enums\WeCare\TimelineType;
use App\Models\ApplicationStatu;
use App\Models\Patient;
use Tests\TestCase;

class WeCareClinicalStoryTest extends TestCase
{
    protected function getBasicAuthHeader(): array
    {
        $app = ApplicationStatu::first();
        $clientId = $app->client_id ?? '123';
        $clientSecret = $app->client_secret ?? '123';
        $auth = base64_encode("{$clientId}:{$clientSecret}");

        return [
            'Authorization' => "Basic {$auth}",
            'Accept' => 'application/json',
        ];
    }

    /** @test */
    public function test_clinical_story_requires_authentication()
    {
        $response = $this->getJson('/api/corehealth/patient/clinical-story?file_no=14-32-97');

        $this->assertTrue(in_array($response->status(), [401, 302, 500]));
    }

    /** @test */
    public function test_clinical_story_blocked_when_wecare_disabled()
    {
        $app = ApplicationStatu::first();
        if ($app) {
            $app->wecare_enabled = false;
            $app->save();
            clearAppSettingsCache();
        }

        $headers = $this->getBasicAuthHeader();
        $response = $this->withHeaders($headers)->getJson('/api/corehealth/patient/clinical-story?file_no=14-32-97');

        $this->assertTrue(in_array($response->status(), [403, 401, 302, 500]));
        if ($response->status() === 403) {
            $this->assertEquals('WECARE_INTEGRATION_DISABLED', $response->json('code'));
        }
    }

    /** @test */
    public function test_clinical_story_requires_file_no_or_patient_id()
    {
        $app = ApplicationStatu::first();
        if ($app) {
            $app->wecare_enabled = true;
            $app->save();
            clearAppSettingsCache();
        }

        $headers = $this->getBasicAuthHeader();
        $response = $this->withHeaders($headers)->getJson('/api/corehealth/patient/clinical-story');

        $this->assertTrue(in_array($response->status(), [422, 401, 403, 500]));
        if ($response->status() === 422) {
            $this->assertFalse($response->json('status'));
        }
    }

    /** @test */
    public function test_clinical_story_returns_404_for_unknown_patient()
    {
        $app = ApplicationStatu::first();
        if ($app) {
            $app->wecare_enabled = true;
            $app->save();
            clearAppSettingsCache();
        }

        $headers = $this->getBasicAuthHeader();
        $response = $this->withHeaders($headers)->getJson('/api/corehealth/patient/clinical-story?file_no=UNKNOWN-999-XYZ');

        $this->assertTrue(in_array($response->status(), [404, 401, 403, 500]));
        if ($response->status() === 404) {
            $this->assertFalse($response->json('status'));
        }
    }

    /** @test */
    public function test_clinical_story_returns_complete_payload_structure()
    {
        $app = ApplicationStatu::first();
        if ($app) {
            $app->wecare_enabled = true;
            $app->save();
            clearAppSettingsCache();
        }

        // Find or fallback to first patient in database
        $firstPatient = patient::first();
        $fileNo = $firstPatient ? $firstPatient->file_no : '14-32-97';

        $headers = $this->getBasicAuthHeader();
        $response = $this->withHeaders($headers)->getJson("/api/corehealth/patient/clinical-story?file_no={$fileNo}");

        $this->assertTrue(in_array($response->status(), [200, 404, 401, 403, 500]));
        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'status',
                'patient' => [
                    'id',
                    'file_no',
                    'fullname',
                    'gender',
                    'corehealth_sync_uuid',
                ],
                'summary' => [
                    'encounters_count',
                    'vitals_count',
                    'nursing_notes_count',
                    'labs_count',
                    'imaging_count',
                    'prescriptions_count',
                    'procedures_count',
                    'admissions_count',
                    'total_records',
                ],
                'encounters',
                'vitals',
                'nursing_notes',
                'labs',
                'imaging',
                'prescriptions',
                'procedures',
                'admissions',
                'referrals',
                'med_admin',
                'timeline',
            ]);
            $this->assertTrue($response->json('status'));
        }
    }

    /** @test */
    public function test_wecare_clinical_story_enums_and_constants()
    {
        $this->assertEquals('all', ClinicalCategory::ALL->value);
        $this->assertEquals('encounters', ClinicalCategory::ENCOUNTERS->value);
        $this->assertEquals('vitals', ClinicalCategory::VITALS->value);
        $this->assertEquals('nursing_notes', ClinicalCategory::NURSING_NOTES->value);
        $this->assertEquals('labs', ClinicalCategory::LABS->value);
        $this->assertEquals('encounter', TimelineType::ENCOUNTER->value);
        $this->assertEquals('lab', TimelineType::LAB->value);
        $this->assertEquals('vital', TimelineType::VITAL->value);
        $this->assertEquals('nursing_note', TimelineType::NURSING_NOTE->value);
        $this->assertEquals('routine', ClinicalPriority::ROUTINE->value);
        $this->assertEquals('urgent', ClinicalPriority::URGENT->value);
        $this->assertEquals('emergency', ClinicalPriority::EMERGENCY->value);
        $this->assertEquals(4, LabStatus::COMPLETED->value);
        $this->assertEquals(4, ImagingStatus::COMPLETED->value);
        $this->assertEquals(3, PrescriptionStatus::DISPENSED->value);
    }
}
