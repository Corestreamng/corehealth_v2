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
        $this->assertStringContainsString('ORDER GROUNDING & SAFETY', $prompt);

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
}
