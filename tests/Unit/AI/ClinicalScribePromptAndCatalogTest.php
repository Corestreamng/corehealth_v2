<?php

namespace Tests\Unit\AI;

use App\Models\Patient;
use App\Services\OrderCatalogRegistryService;
use Mockery;
use PHPUnit\Framework\TestCase;

class ClinicalScribePromptAndCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = require __DIR__ . '/../../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that clinical scribe prompt contains strict directives and all required taxonomies.
     */
    public function test_clinical_scribe_prompt_contains_all_taxonomies()
    {
        $config = require __DIR__ . '/../../../config/llm.php';
        $prompt = $config['prompts']['clinical_scribe'] ?? '';

        $this->assertNotEmpty($prompt);
        $this->assertStringContainsString('STRICT JSON OUTPUT ONLY', $prompt);
        $this->assertStringContainsString('ZERO INFORMATION LOSS', $prompt);
        $this->assertStringContainsString('ORDER GROUNDING', $prompt);
        $this->assertStringContainsString('SERVICE CATEGORIES', $prompt);

        // Doctor taxonomies
        $this->assertStringContainsString('clerking', $prompt);
        $this->assertStringContainsString('Presenting Complaint', $prompt);
        $this->assertStringContainsString('History of Presenting Complaint', $prompt);
        $this->assertStringContainsString('soap', $prompt);
        $this->assertStringContainsString('pomr', $prompt);

        // Nurse taxonomies
        $this->assertStringContainsString('dar', $prompt);
        $this->assertStringContainsString('Focus Data', $prompt);
        $this->assertStringContainsString('sbar', $prompt);
        $this->assertStringContainsString('soapie', $prompt);

        // Maternity taxonomies
        $this->assertStringContainsString('anc', $prompt);
        $this->assertStringContainsString('labor', $prompt);
        $this->assertStringContainsString('pnc', $prompt);
        $this->assertStringContainsString('neonatal', $prompt);
    }

    /**
     * Test that OrderCatalogRegistryService generates category prompt context.
     */
    public function test_order_catalog_registry_returns_category_context()
    {
        $registry = new OrderCatalogRegistryService();
        $context = $registry->getCategoryPromptContext();

        $this->assertNotEmpty($context);
        $this->assertStringContainsString('AVAILABLE CATALOG CATEGORIES', $context);
        $this->assertStringContainsString('SERVICES', $context);
        $this->assertStringContainsString('PRODUCTS', $context);
    }

    /**
     * Test that OrderCatalogRegistryService flags patient allergy conflicts in orders.
     */
    public function test_order_catalog_registry_flags_allergy_conflict()
    {
        $registry = new OrderCatalogRegistryService();
        $rawOrders = [
            [
                'order_type' => 'PRODUCT',
                'item_name' => 'Amoxicillin 500mg',
                'dosage' => '500mg',
                'frequency' => 'TDS',
            ],
            [
                'order_type' => 'SERVICE',
                'item_name' => 'Full Blood Count',
            ],
        ];

        $patientAllergies = ['Penicillin', 'Amoxicillin'];
        $grounded = $registry->groundOrders($rawOrders, $patientAllergies);

        $this->assertCount(2, $grounded);
        $amoxOrder = $grounded[0];
        $this->assertNotNull($amoxOrder['safety_warning']);
        $this->assertStringContainsString('allergy conflict', $amoxOrder['safety_warning']);
    }

    /**
     * Test that OrderCatalogRegistryService formats referral and follow-up orders properly.
     */
    public function test_order_catalog_registry_formats_referral_and_follow_up()
    {
        $registry = new OrderCatalogRegistryService();
        $rawOrders = [
            [
                'order_type' => 'REFERRAL',
                'item_name' => 'Cardiology Consultation',
                'specialization' => 'Cardiology',
                'urgency' => 'urgent',
                'reason' => 'Severe uncontrolled hypertension',
            ],
            [
                'order_type' => 'FOLLOW_UP',
                'item_name' => 'Clinic Review',
                'duration_days' => 14,
                'reason' => 'Evaluate medication response in 2 weeks',
            ],
        ];

        $grounded = $registry->groundOrders($rawOrders, []);

        $this->assertCount(2, $grounded);
        $this->assertEquals('REFERRAL', $grounded[0]['order_type']);
        $this->assertEquals('referral_queue', $grounded[0]['target_queue']);
        $this->assertEquals('urgent', $grounded[0]['urgency']);

        $this->assertEquals('FOLLOW_UP', $grounded[1]['order_type']);
        $this->assertEquals('appointment_queue', $grounded[1]['target_queue']);
        $this->assertEquals(14, $grounded[1]['duration_days']);
    }

    /**
     * Test that clinical terms and acronyms (CXR, FBC, USS) expand correctly.
     */
    public function test_clinical_terms_extraction_expands_acronyms()
    {
        $registry = new OrderCatalogRegistryService();
        $text = "Patient presents with persistent cough. Need CXR, urgent FBC, and pelvic USS.";
        $terms = $registry->extractClinicalSearchTerms($text);

        $this->assertContains('cxr', $terms);
        $this->assertContains('chest x-ray', $terms);
        $this->assertContains('fbc', $terms);
        $this->assertContains('full blood count', $terms);
        $this->assertContains('uss', $terms);
        $this->assertContains('ultrasound', $terms);
    }

    /**
     * Test that OrderCatalogRegistryService distinguishes service categories (Lab vs Imaging vs Procedure).
     */
    public function test_order_catalog_registry_classifies_service_types()
    {
        $registry = new OrderCatalogRegistryService();
        $categoryIds = $registry->getAppSettingCategoryIds();

        // 1. Imaging test
        $imagingMatch = $registry->matchCatalogItem('Chest X-Ray (PA View)', 'SERVICE', 'imaging');
        $this->assertEquals('SERVICE', $imagingMatch['order_type']);
        $this->assertEquals('imaging', $imagingMatch['service_type']);
        $this->assertEquals($categoryIds['imaging'], $imagingMatch['category_id']);
        $this->assertEquals('imaging_queue', $imagingMatch['route_target']);

        // 2. Lab test
        $labMatch = $registry->matchCatalogItem('Full Blood Count', 'SERVICE', 'lab');
        $this->assertEquals('SERVICE', $labMatch['order_type']);
        $this->assertEquals('lab', $labMatch['service_type']);
        $this->assertEquals($categoryIds['lab'], $labMatch['category_id']);
        $this->assertEquals('lab_queue', $labMatch['route_target']);

        // 3. Procedure
        $procMatch = $registry->matchCatalogItem('Wound Dressing (Major)', 'SERVICE', 'procedure');
        $this->assertEquals('SERVICE', $procMatch['order_type']);
        $this->assertEquals('procedure', $procMatch['service_type']);
        $this->assertEquals($categoryIds['procedure'], $procMatch['category_id']);
        $this->assertEquals('procedure_queue', $procMatch['route_target']);
    }

    /**
     * Test that OrderCatalogRegistryService flags unmatched services as free-form with proper department queue.
     */
    public function test_order_catalog_registry_handles_free_form_properly()
    {
        $registry = new OrderCatalogRegistryService();
        $categoryIds = $registry->getAppSettingCategoryIds();

        // Fictional imaging test that doesn't exist in catalog
        $customImaging = $registry->matchCatalogItem('Novel Quantum Positron Scan XYZ', 'SERVICE', 'imaging');
        $this->assertTrue($customImaging['is_free_form']);
        $this->assertFalse($customImaging['is_matched']);
        $this->assertNull($customImaging['catalog_id']);
        $this->assertEquals('imaging', $customImaging['service_type']);
        $this->assertEquals($categoryIds['imaging'], $customImaging['category_id']);
        $this->assertEquals('imaging_queue', $customImaging['route_target']);

        // Fictional lab test that doesn't exist in catalog
        $customLab = $registry->matchCatalogItem('Rare Gene Variant PCR XYZ', 'SERVICE', 'lab');
        $this->assertTrue($customLab['is_free_form']);
        $this->assertFalse($customLab['is_matched']);
        $this->assertNull($customLab['catalog_id']);
        $this->assertEquals('lab', $customLab['service_type']);
        $this->assertEquals($categoryIds['lab'], $customLab['category_id']);
        $this->assertEquals('lab_queue', $customLab['route_target']);
    }

    /**
     * Test that OrderCatalogRegistryService expands stool MCS and antibiotic synonyms.
     */
    public function test_extracts_stool_mcs_and_antibiotic_synonyms()
    {
        $registry = new OrderCatalogRegistryService();
        $terms = $registry->extractClinicalSearchTerms('Patient needs stool MCS and an antibiotic');

        $this->assertContains('stool microscopy', $terms);
        $this->assertContains('microscopy culture sensitivity', $terms);
        $this->assertContains('amoxicillin', $terms);
    }

    /**
     * Test that LlmController extracts and repairs truncated LLM JSON payload.
     */
    public function test_repairs_truncated_llm_json_payload()
    {
        $controller = app(\App\Http\Controllers\LlmController::class);
        $refMethod = new \ReflectionMethod($controller, 'extractJsonPayload');
        $refMethod->setAccessible(true);

        // Truncated string matching exact user failure case
        $truncatedRaw = "```json\n{\n  \"summary_headline\": \"Appendicitis update with stool MCS\",\n  \"taxonomy_type\": \"clerking\",\n  \"structured_sections\": [\n    {\n      \"key\": \"PC\",\n      \"title\": \"Presenting Complaint\",\n      \"content\": \"Severe abdominal pain\"\n    },\n    {\n      \"key\": \"HPC\",\n      \"title\": \"History of Presenting Complaint\",\n      \"content\": \"Pain for 6 hours\"\n    },\n    {";

        $parsed = $refMethod->invoke($controller, $truncatedRaw);

        $this->assertNotNull($parsed);
        $this->assertIsArray($parsed);
        $this->assertEquals('Appendicitis update with stool MCS', $parsed['summary_headline']);
        $this->assertCount(2, $parsed['structured_sections']);
        $this->assertEquals('PC', $parsed['structured_sections'][0]['key']);
        $this->assertEquals('HPC', $parsed['structured_sections'][1]['key']);
    }

    /**
     * Test that LlmController salvages orders and sections from truncated JSON.
     */
    public function test_repairs_truncated_llm_json_and_salvages_extracted_orders()
    {
        $controller = app(\App\Http\Controllers\LlmController::class);
        $refMethod = new \ReflectionMethod($controller, 'extractJsonPayload');
        $refMethod->setAccessible(true);

        $truncatedRaw = "{\n  \"summary_headline\": \"Appendicitis with Stool MCS\",\n  \"taxonomy_type\": \"clerking\",\n  \"extracted_orders\": [\n    {\n      \"order_type\": \"SERVICE\",\n      \"item_name\": \"Stool Microscopy\",\n      \"service_type\": \"lab\"\n    }\n  ],\n  \"structured_sections\": [\n    {\n      \"key\": \"PC\",\n      \"title\": \"Presenting Complaint\",\n      \"content\": \"Abdominal pain\"\n    }\n  ";

        $parsed = $refMethod->invoke($controller, $truncatedRaw);

        $this->assertNotNull($parsed);
        $this->assertIsArray($parsed);
        $this->assertCount(1, $parsed['extracted_orders']);
        $this->assertEquals('Stool Microscopy', $parsed['extracted_orders'][0]['item_name']);
        $this->assertCount(1, $parsed['structured_sections']);
    }

    /**
     * Test that patient demographics chunk includes today's clinical date.
     */
    public function test_patient_context_includes_current_date()
    {
        $service = app(\App\Services\PatientContextService::class);
        $patient = new \App\Models\Patient();
        $patient->dob = '2000-01-01';
        $patient->gender = 'female';
        $patient->blood_group = 'O+';
        $patient->genotype = 'AA';

        $refMethod = new \ReflectionMethod($service, 'buildDemographicsChunk');
        $refMethod->setAccessible(true);

        $chunk = $refMethod->invoke($service, $patient);

        $this->assertStringContainsString('Current Date:', $chunk);
        $this->assertStringContainsString(now()->format('Y-m-d'), $chunk);
    }
}
