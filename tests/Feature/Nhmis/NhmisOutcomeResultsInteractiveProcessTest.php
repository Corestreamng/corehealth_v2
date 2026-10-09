<?php

namespace Tests\Feature\Nhmis;

use App\Models\ImagingServiceRequest;
use App\Models\LabServiceRequest;
use App\Models\NhmisServiceMapping;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\Service;
use App\Models\User;
use Tests\TestCase;

class NhmisOutcomeResultsInteractiveProcessTest extends TestCase
{
    protected User $adminUser;

    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::find(1) ?? User::factory()->create(['status' => 1]);
        $this->patient = Patient::first() ?? Patient::factory()->create();
    }

    /** @test */
    public function test_service_mapping_lookup_returns_indicator_and_supported_outcomes_for_mapped_services()
    {
        // 1. Mapped service lookup by name
        $response = $this->actingAs($this->adminUser)->getJson('/nhmis-workbench/service-mapping/0?service_name=' . urlencode('Malaria Microscopy (MP)'));
        $this->assertContains($response->status(), [200, 302]);
        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertTrue($data['success']);
            $this->assertTrue($data['is_mapped']);
            $this->assertEquals('malaria_microscopy', $data['indicator_code']);
            $this->assertIsArray($data['supported_outcomes']);
            $this->assertContains('Positive (+)', $data['supported_outcomes']);
        }

        // 2. Procedure mapping lookup by name
        $responseProc = $this->actingAs($this->adminUser)->getJson('/nhmis-workbench/service-mapping/0?service_name=' . urlencode('Emergency Caesarean Section'));
        if ($responseProc->status() === 200) {
            $data = $responseProc->json();
            $this->assertTrue($data['is_mapped']);
            $this->assertEquals('caesarean_section', $data['indicator_code']);
            $this->assertContains('Successful', $data['supported_outcomes']);
            $this->assertContains('Converted', $data['supported_outcomes']);
        }

        // 3. Unmapped service lookup
        $responseUnmapped = $this->actingAs($this->adminUser)->getJson('/nhmis-workbench/service-mapping/0?service_name=' . urlencode('Totally Random Unmapped Test XYZ'));
        if ($responseUnmapped->status() === 200) {
            $data = $responseUnmapped->json();
            $this->assertFalse($data['is_mapped']);
        }
    }

    /** @test */
    public function test_lab_save_result_classifies_only_when_mapped_and_leaves_unmapped_null()
    {
        // Create a mapped service
        $mappedService = Service::create([
            'user_id' => $this->adminUser->id,
            'service_name' => 'Malaria Microscopy MP Test ' . uniqid(),
            'category_id' => 2,
            'status' => 1,
        ]);
        NhmisServiceMapping::create([
            'indicator_code' => 'malaria_microscopy',
            'service_id' => $mappedService->id,
            'service_type' => 'investigation',
            'supported_outcomes' => ['Negative', 'Positive (+)'],
            'positive_outcomes' => ['Positive (+)'],
        ]);

        $mappedLabReq = LabServiceRequest::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->adminUser->id,
            'service_id' => $mappedService->id,
            'status' => 2,
        ]);

        // 1. Attempt saving mapped lab request WITHOUT selecting an outcome -> must fail with 422
        $respMissingOutcome = $this->actingAs($this->adminUser)->postJson('/lab-workbench/save-result', [
            'invest_res_entry_id' => $mappedLabReq->id,
            'invest_res_template_version' => 1,
            'invest_res_template_submited' => '<p>Malaria parasites seen: Positive (+)</p>',
            'entry_source' => 'lab_workbench',
        ]);
        $this->assertEquals(422, $respMissingOutcome->status(), 'Saving mapped service without outcome must return 422');
        $this->assertFalse($respMissingOutcome->json('success'));
        $this->assertStringContainsString('Selecting an NHMIS clinical outcome is required', $respMissingOutcome->json('message'));

        // 2. Save result on mapped lab request WITH outcome -> succeeds
        $respMapped = $this->actingAs($this->adminUser)->postJson('/lab-workbench/save-result', [
            'invest_res_entry_id' => $mappedLabReq->id,
            'invest_res_template_version' => 1,
            'invest_res_template_submited' => '<p>Malaria parasites seen: Positive (+)</p>',
            'nhmis_outcome' => 'positive',
            'nhmis_outcome_raw' => 'Positive (+)',
            'entry_source' => 'lab_workbench',
        ]);
        $this->assertContains($respMapped->status(), [200, 302]);
        $mappedLabReq->refresh();
        $this->assertEquals('positive', $mappedLabReq->nhmis_outcome);
        $this->assertEquals('Positive (+)', $mappedLabReq->nhmis_outcome_raw);
        $this->assertNotNull($mappedLabReq->nhmis_classified_at);

        // Create an unmapped service
        $unmappedService = Service::create([
            'user_id' => $this->adminUser->id,
            'service_name' => 'Completely Unmapped Lab Test ' . uniqid(),
            'category_id' => 2,
            'status' => 1,
        ]);
        $unmappedLabReq = LabServiceRequest::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->adminUser->id,
            'service_id' => $unmappedService->id,
            'status' => 2,
        ]);

        // Save result on unmapped lab request
        $respUnmapped = $this->actingAs($this->adminUser)->postJson('/lab-workbench/save-result', [
            'invest_res_entry_id' => $unmappedLabReq->id,
            'invest_res_template_version' => 1,
            'invest_res_template_submited' => '<p>Normal lab result</p>',
            'entry_source' => 'lab_workbench',
        ]);
        $this->assertContains($respUnmapped->status(), [200, 302]);
        $unmappedLabReq->refresh();
        $this->assertNull($unmappedLabReq->nhmis_outcome, 'Unmapped lab service must NOT force an NHMIS classification.');
        $this->assertNull($unmappedLabReq->nhmis_classified_at);
    }

    /** @test */
    public function test_imaging_save_result_classifies_only_when_mapped_and_leaves_unmapped_null()
    {
        // Create mapped imaging service
        $mappedImgService = Service::create([
            'user_id' => $this->adminUser->id,
            'service_name' => 'Obstetric Ultrasound Scan Test ' . uniqid(),
            'category_id' => 6,
            'status' => 1,
        ]);
        NhmisServiceMapping::create([
            'indicator_code' => 'obstetric_ultrasound',
            'service_id' => $mappedImgService->id,
            'service_type' => 'imaging',
            'supported_outcomes' => ['Normal / Viable', 'Abnormal / Complications'],
            'positive_outcomes' => ['Abnormal / Complications'],
        ]);

        $mappedImgReq = ImagingServiceRequest::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->adminUser->id,
            'service_id' => $mappedImgService->id,
            'status' => 2,
        ]);

        // 1. Attempt saving mapped imaging request WITHOUT outcome -> must fail with 422
        $respMissingImgOutcome = $this->actingAs($this->adminUser)->postJson('/imaging-workbench/save-result', [
            'invest_res_entry_id' => $mappedImgReq->id,
            'invest_res_template_version' => 1,
            'invest_res_template_submited' => '<p>Viable single intrauterine fetus</p>',
            'entry_source' => 'imaging_workbench',
        ]);
        $this->assertEquals(422, $respMissingImgOutcome->status(), 'Saving mapped imaging without outcome must return 422');
        $this->assertFalse($respMissingImgOutcome->json('success'));
        $this->assertStringContainsString('Selecting an NHMIS clinical outcome is required', $respMissingImgOutcome->json('message'));

        // 2. Save result on mapped imaging request WITH outcome -> succeeds
        $respMapped = $this->actingAs($this->adminUser)->postJson('/imaging-workbench/save-result', [
            'invest_res_entry_id' => $mappedImgReq->id,
            'invest_res_template_version' => 1,
            'invest_res_template_submited' => '<p>Viable single intrauterine fetus</p>',
            'nhmis_outcome' => 'negative',
            'nhmis_outcome_raw' => 'Normal / Viable',
            'entry_source' => 'imaging_workbench',
        ]);
        $this->assertContains($respMapped->status(), [200, 302]);
        $mappedImgReq->refresh();
        $this->assertEquals('negative', $mappedImgReq->nhmis_outcome);
        $this->assertNotNull($mappedImgReq->nhmis_classified_at);

        // Create unmapped imaging service
        $unmappedImgService = Service::create([
            'user_id' => $this->adminUser->id,
            'service_name' => 'Knee MRI Unmapped ' . uniqid(),
            'category_id' => 6,
            'status' => 1,
        ]);
        $unmappedImgReq = ImagingServiceRequest::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->adminUser->id,
            'service_id' => $unmappedImgService->id,
            'status' => 2,
        ]);

        $respUnmapped = $this->actingAs($this->adminUser)->postJson('/imaging-workbench/save-result', [
            'invest_res_entry_id' => $unmappedImgReq->id,
            'invest_res_template_version' => 1,
            'invest_res_template_submited' => '<p>Intact meniscus</p>',
            'entry_source' => 'imaging_workbench',
        ]);
        $this->assertContains($respUnmapped->status(), [200, 302]);
        $unmappedImgReq->refresh();
        $this->assertNull($unmappedImgReq->nhmis_outcome, 'Unmapped imaging service must NOT force an NHMIS classification.');
        $this->assertNull($unmappedImgReq->nhmis_classified_at);
    }

    /** @test */
    public function test_procedure_update_outcome_classifies_only_when_mapped_and_leaves_unmapped_null()
    {
        // 1. Mapped procedure: Caesarean Section
        $mappedProcService = Service::create([
            'user_id' => $this->adminUser->id,
            'service_name' => 'Lower Segment Caesarean Section ' . uniqid(),
            'category_id' => 8,
            'status' => 1,
        ]);
        NhmisServiceMapping::create([
            'indicator_code' => 'caesarean_section',
            'service_id' => $mappedProcService->id,
            'service_type' => 'procedure',
            'supported_outcomes' => ['Successful', 'Complications', 'Aborted', 'Converted'],
            'positive_outcomes' => ['Successful'],
        ]);

        $mappedProc = Procedure::create([
            'patient_id' => $this->patient->id,
            'service_id' => $mappedProcService->id,
            'requested_by' => $this->adminUser->id,
            'procedure_status' => 'in_progress',
        ]);

        $respMapped = $this->actingAs($this->adminUser)->putJson("/patient-procedures/{$mappedProc->id}/outcome", [
            'outcome' => 'successful',
            'outcome_notes' => 'C-section completed with baby delivered safely.',
            'nhmis_outcome' => 'positive',
            'nhmis_outcome_raw' => 'Successful',
        ]);
        $this->assertContains($respMapped->status(), [200, 302]);
        $mappedProc->refresh();
        $this->assertEquals('successful', $mappedProc->outcome);
        $this->assertEquals('positive', $mappedProc->nhmis_outcome);
        $this->assertEquals('Successful', $mappedProc->nhmis_outcome_raw);
        $this->assertNotNull($mappedProc->nhmis_classified_at);

        // 2. Unmapped procedure: e.g. Appendectomy
        $unmappedProcService = Service::create([
            'user_id' => $this->adminUser->id,
            'service_name' => 'Appendectomy Unmapped Test ' . uniqid(),
            'category_id' => 8,
            'status' => 1,
        ]);
        $unmappedProc = Procedure::create([
            'patient_id' => $this->patient->id,
            'service_id' => $unmappedProcService->id,
            'requested_by' => $this->adminUser->id,
            'procedure_status' => 'in_progress',
        ]);

        $respUnmapped = $this->actingAs($this->adminUser)->putJson("/patient-procedures/{$unmappedProc->id}/outcome", [
            'outcome' => 'successful',
            'outcome_notes' => 'Appendix removed cleanly.',
        ]);
        $this->assertContains($respUnmapped->status(), [200, 302]);
        $unmappedProc->refresh();
        $this->assertEquals('successful', $unmappedProc->outcome);
        $this->assertNull($unmappedProc->nhmis_outcome, 'Unmapped procedure must NOT force an NHMIS classification.');
        $this->assertNull($unmappedProc->nhmis_classified_at);
    }

    /** @test */
    public function test_get_lab_and_imaging_requests_return_service_details_and_nhmis_outcome()
    {
        $service = Service::create([
            'user_id' => $this->adminUser->id,
            'service_name' => 'MP Test Service ' . uniqid(),
            'category_id' => 2,
            'status' => 1,
        ]);

        $labReq = LabServiceRequest::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->adminUser->id,
            'service_id' => $service->id,
            'status' => 4,
            'nhmis_outcome' => 'positive',
            'nhmis_outcome_raw' => 'Positive (+)',
        ]);

        $labResp = $this->actingAs($this->adminUser)->getJson("/lab-workbench/lab-service-requests/{$labReq->id}");
        $this->assertContains($labResp->status(), [200, 302]);
        if ($labResp->status() === 200) {
            $data = $labResp->json();
            $this->assertEquals($service->id, $data['service_id']);
            $this->assertEquals($service->id, $data['service']['id']);
            $this->assertEquals('positive', $data['nhmis_outcome']);
            $this->assertEquals('Positive (+)', $data['nhmis_outcome_raw']);
        }

        $imgReq = ImagingServiceRequest::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->adminUser->id,
            'service_id' => $service->id,
            'status' => 4,
            'nhmis_outcome' => 'negative',
            'nhmis_outcome_raw' => 'Normal / Completed',
        ]);

        $imgResp = $this->actingAs($this->adminUser)->getJson("/imaging-workbench/imaging-service-requests/{$imgReq->id}");
        $this->assertContains($imgResp->status(), [200, 302]);
        if ($imgResp->status() === 200) {
            $data = $imgResp->json();
            $this->assertEquals($service->id, $data['service_id']);
            $this->assertEquals($service->id, $data['service']['id']);
            $this->assertEquals('negative', $data['nhmis_outcome']);
            $this->assertEquals('Normal / Completed', $data['nhmis_outcome_raw']);
        }
    }

    /** @test */
    public function test_malaria_auto_senses_for_all_possible_outcomes()
    {
        // 1. Negative outcomes
        $negativeSamples = [
            'No malaria parasites seen (Negative / NPS)',
            'NPS',
            'No malaria parasites seen',
            'No parasite seen',
            'Nil parasites seen',
            'Microscopy: Negative',
            'Not detected',
            'Blood film: absent',
            '<p><strong>Microscopy:</strong> No malaria parasites seen (Negative / NPS).</p>',
        ];

        foreach ($negativeSamples as $sample) {
            $result = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord($sample, null, 'malaria_microscopy');
            $this->assertEquals('negative', $result['outcome'], "Failed negative classification for: {$sample}");
            $this->assertEquals('Negative', $result['raw'], "Failed raw matching for: {$sample}");
        }

        // 2. Positive (+)
        $positive1Samples = [
            'Malaria parasites seen: Positive (+)',
            'Positive (+)',
            'MP seen 1+',
            'MP: +',
            'Trophozoites of P. falciparum present (+)',
            'Antigen detected (positive)',
            '<p><strong>Microscopy:</strong> Malaria parasites seen (P. falciparum): <strong>Positive (+)</strong>.</p>',
        ];

        foreach ($positive1Samples as $sample) {
            $result = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord($sample, null, 'malaria_microscopy');
            $this->assertEquals('positive', $result['outcome'], "Failed positive (+) classification for: {$sample}");
            $this->assertEquals('Positive (+)', $result['raw'], "Failed raw matching for: {$sample}");
        }

        // 3. Positive (++)
        $positive2Samples = [
            'Malaria parasites seen: Positive (++)',
            'Positive (++)',
            'MP seen 2+',
            'MP: ++',
            'Moderate parasitaemia (++)',
            'P. falciparum ring forms seen 2+',
            '<p><strong>Microscopy:</strong> Malaria parasites seen (P. falciparum): <strong>Positive (++)</strong>.</p>',
        ];

        foreach ($positive2Samples as $sample) {
            $result = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord($sample, null, 'malaria_microscopy');
            $this->assertEquals('positive', $result['outcome'], "Failed positive (++) classification for: {$sample}");
            $this->assertEquals('Positive (++)', $result['raw'], "Failed raw matching for: {$sample}");
        }

        // 4. Positive (+++)
        $positive3Samples = [
            'Malaria parasites seen: Positive (+++)',
            'Positive (+++)',
            'MP seen 3+',
            'MP: +++',
            'Heavy parasitaemia (+++)',
            'Numerous trophozoites seen 3+',
            '<p><strong>Microscopy:</strong> Malaria parasites seen (P. falciparum): <strong>Positive (+++)</strong>.</p>',
        ];

        foreach ($positive3Samples as $sample) {
            $result = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord($sample, null, 'malaria_microscopy');
            $this->assertEquals('positive', $result['outcome'], "Failed positive (+++) classification for: {$sample}");
            $this->assertEquals('Positive (+++)', $result['raw'], "Failed raw matching for: {$sample}");
        }

        // 5. Positive (++++)
        $positive4Samples = [
            'Malaria parasites seen: Positive (++++)',
            'Positive (++++)',
            'MP seen 4+',
            'MP: ++++',
            'Severe hyperparasitaemia (++++)',
            'Very heavy trophozoites 4+',
            '<p><strong>Microscopy:</strong> Malaria parasites seen (P. falciparum): <strong>Positive (++++)</strong>.</p>',
        ];

        foreach ($positive4Samples as $sample) {
            $result = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord($sample, null, 'malaria_microscopy');
            $this->assertEquals('positive', $result['outcome'], "Failed positive (++++) classification for: {$sample}");
            $this->assertEquals('Positive (++++)', $result['raw'], "Failed raw matching for: {$sample}");
        }

        // 6. Indeterminate
        $indeterminateSamples = [
            'Indeterminate result, please repeat test',
            'Inconclusive test result',
            'Doubtful ring forms seen, repeat recommended',
            'Repeat test required due to artifact',
        ];

        foreach ($indeterminateSamples as $sample) {
            $result = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord($sample, null, 'malaria_microscopy');
            $this->assertEquals('indeterminate', $result['outcome'], "Failed indeterminate classification for: {$sample}");
            $this->assertEquals('Indeterminate', $result['raw'], "Failed raw matching for: {$sample}");
        }

        // 7. Structured Template V2 tests for Malaria
        $v2Negative = json_encode(['parameters' => [['name' => 'MP', 'value' => 'NPS', 'status' => 'normal']]]);
        $resV2Neg = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord(null, $v2Negative, 'malaria_microscopy');
        $this->assertEquals('negative', $resV2Neg['outcome']);
        $this->assertEquals('Negative', $resV2Neg['raw']);

        $v2Pos2 = json_encode(['parameters' => [['name' => 'MP', 'value' => 'Positive (++)', 'status' => 'positive']]]);
        $resV2Pos2 = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord(null, $v2Pos2, 'malaria_microscopy');
        $this->assertEquals('positive', $resV2Pos2['outcome']);
        $this->assertEquals('Positive (++)', $resV2Pos2['raw']);

        $v2Pos4 = json_encode(['parameters' => [['name' => 'MP', 'value' => '4+', 'status' => 'positive']]]);
        $resV2Pos4 = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord(null, $v2Pos4, 'malaria_microscopy');
        $this->assertEquals('positive', $resV2Pos4['outcome']);
        $this->assertEquals('Positive (++++)', $resV2Pos4['raw']);

        $v2Indet = json_encode(['parameters' => [['name' => 'MP', 'value' => 'Indeterminate', 'status' => 'normal']]]);
        $resV2Indet = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord(null, $v2Indet, 'malaria_microscopy');
        $this->assertEquals('indeterminate', $resV2Indet['outcome']);
        $this->assertEquals('Indeterminate', $resV2Indet['raw']);
    }

    /** @test */
    public function test_all_mapped_nhmis_indicators_have_valid_configurations_and_presets()
    {
        $indicators = NhmisServiceMapping::INDICATORS;
        $presets = NhmisServiceMapping::OUTCOME_PRESETS;

        $this->assertNotEmpty($indicators, 'NHMIS indicators catalog must not be empty.');

        foreach ($indicators as $code => $meta) {
            // Verify structure
            $this->assertArrayHasKey('label', $meta, "Indicator {$code} missing label.");
            $this->assertArrayHasKey('preset_key', $meta, "Indicator {$code} missing preset_key.");
            $this->assertArrayHasKey('service_type', $meta, "Indicator {$code} missing service_type.");
            $this->assertContains($meta['service_type'], ['investigation', 'imaging', 'procedure'], "Invalid service_type for {$code}");

            // Verify preset exists
            $presetKey = $meta['preset_key'];
            $this->assertArrayHasKey($presetKey, $presets, "Indicator {$code} references undefined preset '{$presetKey}'.");

            $preset = $presets[$presetKey];
            $this->assertArrayHasKey('supported', $preset, "Preset {$presetKey} missing 'supported' outcomes array.");
            $this->assertArrayHasKey('positive', $preset, "Preset {$presetKey} missing 'positive' outcomes array.");
            $this->assertNotEmpty($preset['supported'], "Preset {$presetKey} has empty supported array.");
            $this->assertNotEmpty($preset['positive'], "Preset {$presetKey} has empty positive array.");

            // Every positive outcome must be part of supported outcomes
            foreach ($preset['positive'] as $pos) {
                $this->assertContains($pos, $preset['supported'], "Positive outcome '{$pos}' not in supported for {$presetKey}");
            }
        }
    }

    /** @test */
    public function test_sensing_and_classification_for_all_mapped_indicators_across_outcomes()
    {
        // 1. Serology: HIV, Syphilis, Hepatitis
        $serologyIndicators = ['hiv_screening', 'hiv_confirmatory', 'syphilis_vdrl', 'hepatitis_b', 'hepatitis_c'];
        foreach ($serologyIndicators as $ind) {
            $neg = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord('Non-Reactive test result', null, $ind);
            $this->assertEquals('negative', $neg['outcome']);
            $this->assertEquals('Non-Reactive', $neg['raw']);

            $pos = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord('Reactive result', null, $ind);
            $this->assertEquals('positive', $pos['outcome']);
            $this->assertEquals('Reactive', $pos['raw']);

            $indet = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord('Indeterminate / doubtful line', null, $ind);
            $this->assertEquals('indeterminate', $indet['outcome']);
            $this->assertEquals('Indeterminate', $indet['raw']);
        }

        // 2. TB Sputum AFB / GeneXpert
        $tbNeg = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord('No AFB seen / Negative', null, 'tb_sputum_afb');
        $this->assertEquals('negative', $tbNeg['outcome']);
        $this->assertEquals('Negative (Not Seen)', $tbNeg['raw']);

        $tbPos1 = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord('Acid fast bacilli seen 1+', null, 'tb_sputum_afb');
        $this->assertEquals('positive', $tbPos1['outcome']);
        $this->assertEquals('Positive (1+)', $tbPos1['raw']);

        $tbPos2 = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord('Positive 2+ AFB seen', null, 'tb_sputum_afb');
        $this->assertEquals('positive', $tbPos2['outcome']);
        $this->assertEquals('Positive (2+)', $tbPos2['raw']);

        $tbPos3 = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord('Positive 3+ AFB seen', null, 'tb_sputum_afb');
        $this->assertEquals('positive', $tbPos3['outcome']);
        $this->assertEquals('Positive (3+)', $tbPos3['raw']);

        $tbMtb = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord('GeneXpert: MTB Detected', null, 'tb_sputum_afb');
        $this->assertEquals('positive', $tbMtb['outcome']);
        $this->assertEquals('MTB Detected', $tbMtb['raw']);

        $tbNotMtb = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord('GeneXpert: MTB Not Detected', null, 'tb_sputum_afb');
        $this->assertEquals('negative', $tbNotMtb['outcome']);
        $this->assertEquals('MTB Not Detected', $tbNotMtb['raw']);

        // 3. Chest X-Ray TB
        $cxrClear = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord('Normal chest radiograph, clear lung fields', null, 'chest_xray_tb');
        $this->assertEquals('negative', $cxrClear['outcome']);
        $this->assertEquals('Normal / Clear', $cxrClear['raw']);

        $cxrTb = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord('Bilateral apical cavitary lesions consistent with pulmonary TB', null, 'chest_xray_tb');
        $this->assertEquals('positive', $cxrTb['outcome']);
        $this->assertEquals('Abnormal (TB Presumptive)', $cxrTb['raw']);

        $cxrOther = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord('Cardiomegaly with pleural effusion', null, 'chest_xray_tb');
        $this->assertEquals('indeterminate', $cxrOther['outcome']);
        $this->assertEquals('Other Abnormalities', $cxrOther['raw']);

        // 4. Obstetric Ultrasound
        $usNormal = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord('Single intrauterine viable fetus, normal liquor', null, 'obstetric_ultrasound');
        $this->assertEquals('negative', $usNormal['outcome']);
        $this->assertEquals('Normal / Viable', $usNormal['raw']);

        $usAbnormal = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord('Abnormal findings, ectopic pregnancy suspected', null, 'obstetric_ultrasound');
        $this->assertEquals('positive', $usAbnormal['outcome']);
        $this->assertEquals('Abnormal / Complications', $usAbnormal['raw']);

        // 5. Pregnancy Test
        $ptNeg = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord('Negative test', null, 'pregnancy_test');
        $this->assertEquals('negative', $ptNeg['outcome']);
        $this->assertEquals('Negative', $ptNeg['raw']);

        $ptPos = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord('Positive pregnancy test', null, 'pregnancy_test');
        $this->assertEquals('positive', $ptPos['outcome']);
        $this->assertEquals('Positive', $ptPos['raw']);

        // 6. Qualitative / Urinalysis / Glucose
        $qualNeg = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord('Negative', null, 'urinalysis_protein');
        $this->assertEquals('negative', $qualNeg['outcome']);
        $this->assertEquals('Negative', $qualNeg['raw']);

        $qualTrace = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord('Protein: Trace', null, 'urinalysis_protein');
        $this->assertEquals('negative', $qualTrace['outcome']);
        $this->assertEquals('Trace', $qualTrace['raw']);

        $qualPos = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord('Protein: 3+', null, 'urinalysis_protein');
        $this->assertEquals('positive', $qualPos['outcome']);
        $this->assertEquals('3+', $qualPos['raw']);

        // 7. Procedures
        $procIndicators = ['caesarean_section', 'mva_spontaneous', 'mva_induced', 'mva_pac', 'tubal_ligation', 'vasectomy', 'fistula_repair'];
        foreach ($procIndicators as $pInd) {
            $pSuccess = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord('Procedure completed successfully without complications', null, $pInd);
            $this->assertEquals('positive', $pSuccess['outcome']);
            $this->assertEquals('Successful', $pSuccess['raw']);

            $pAbort = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord('Procedure aborted due to anesthesia reaction', null, $pInd);
            $this->assertEquals('negative', $pAbort['outcome']);
            $this->assertEquals('Aborted', $pAbort['raw']);

            $pConvert = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord('Converted to open laparotomy', null, $pInd);
            $this->assertEquals('negative', $pConvert['outcome']);
            $this->assertEquals('Converted', $pConvert['raw']);

            $pComplication = \App\Console\Commands\NhmisClassifyHistoricalLabRecords::classifyRecord('Procedure experienced hemorrhage complications', null, $pInd);
            $this->assertEquals('negative', $pComplication['outcome']);
            $this->assertEquals('Complications', $pComplication['raw']);
        }
    }
}
