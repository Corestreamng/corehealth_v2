<?php

namespace Tests\Unit\AI;

use App\Http\Controllers\LlmController;
use App\Services\LlmGatewayService;
use App\Services\PatientContextService;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\TestCase;

class NotePolishingPromptTest extends TestCase
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
     * Test that the default note polishing prompt exists and contains strict fidelity directives.
     */
    public function test_default_prompt_contains_critical_directives()
    {
        $config = require __DIR__ . '/../../../config/llm.php';
        $prompt = $config['prompts']['polish_note'] ?? '';

        $this->assertNotEmpty($prompt, 'Default polish_note prompt should not be empty.');
        $this->assertStringContainsString('ZERO INFORMATION LOSS', $prompt);
        $this->assertStringContainsString('NO VAGUE GENERALIZATIONS', $prompt);
        $this->assertStringContainsString('NO HALLUCINATION', $prompt);
        $this->assertStringContainsString('NO CONVERSATIONAL FILLER', $prompt);
    }

    /**
     * Test that all three required modalities are explicitly covered in the prompt.
     */
    public function test_default_prompt_contains_all_three_modality_adaptations()
    {
        $config = require __DIR__ . '/../../../config/llm.php';
        $prompt = $config['prompts']['polish_note'] ?? '';

        // Modality A: Doctor-Patient Conversation / Ambient Dialogue
        $this->assertStringContainsString('MODALITY A: DOCTOR-PATIENT CONVERSATION / AMBIENT DIALOGUE', $prompt);
        $this->assertStringContainsString('Chief Complaint (CC)', $prompt);
        $this->assertStringContainsString('History of Present Illness (HPI)', $prompt);
        $this->assertStringContainsString('Review of Systems (ROS)', $prompt);
        $this->assertStringContainsString('Physical Examination', $prompt);
        $this->assertStringContainsString('Assessment & Clinical Impression', $prompt);
        $this->assertStringContainsString('Management & Treatment Plan', $prompt);

        // Modality B: Chaotic Nurse Dictation / Bedside Shift Report
        $this->assertStringContainsString('MODALITY B: CHAOTIC NURSE DICTATION / BEDSIDE SHIFT REPORT', $prompt);
        $this->assertStringContainsString('Clinical Status & Shift Overview', $prompt);
        $this->assertStringContainsString('Vital Signs & Hemodynamic Monitoring', $prompt);
        $this->assertStringContainsString('Pain Assessment & Analgesic Response', $prompt);
        $this->assertStringContainsString('Surgical Site, Wound Care & Drains', $prompt);
        $this->assertStringContainsString('Lines, Tubes & Fluid Balance (I/O)', $prompt);
        $this->assertStringContainsString('Nursing Interventions & Patient Care', $prompt);
        $this->assertStringContainsString('Physician Communication & Orders', $prompt);

        // Modality C: Doctor Memo Style / Terse Shorthand / Telegraphic Bullets
        $this->assertStringContainsString('MODALITY C: DOCTOR MEMO STYLE / TERSE SHORTHAND', $prompt);
        $this->assertStringContainsString('Patient Demographics & Baseline Status', $prompt);
        $this->assertStringContainsString('Prior Medications & Adherence Evaluation', $prompt);
        $this->assertStringContainsString('Diagnostic Workup, Bedside Tests & Imaging', $prompt);
        $this->assertStringContainsString('Diagnostic Impression & Clinical Staging', $prompt);
        $this->assertStringContainsString('Comprehensive Management Plan', $prompt);
    }

    /**
     * Test that LlmController cleans and normalizes HTML before sending to LLM.
     */
    public function test_llm_controller_normalizes_html_note_content()
    {
        $gateway = Mockery::mock(LlmGatewayService::class);
        $contextService = Mockery::mock(PatientContextService::class);

        $gateway->shouldReceive('isEnabled')->andReturn(true);
        $gateway->shouldReceive('getConfig')->andReturn([
            'system_prompts' => ['polish_note' => 'Default system prompt'],
        ]);

        $gateway->shouldReceive('complete')
            ->once()
            ->withArgs(function ($systemPrompt, $userMessage, $options) {
                // Ensure HTML tags were stripped and replaced with newlines
                return !str_contains($userMessage, '<p>') &&
                    !str_contains($userMessage, '<br>') &&
                    str_contains($userMessage, "Doctor: Hello Mr. Smith\n\nPatient: My chest hurts") &&
                    $options['max_tokens'] === 4096 &&
                    $options['temperature'] === 0.2;
            })
            ->andReturn([
                'content' => "### Chief Complaint\nChest pain.",
                'model' => 'test-model',
                'provider' => 'test-provider',
            ]);

        $controller = new LlmController($gateway, $contextService);

        $request = Request::create('/llm/polish-note', 'POST', [
            'note_content' => '<p>Doctor: Hello Mr. Smith</p><p>Patient: My chest hurts</p>',
        ]);

        $response = $controller->polishNote($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertStringContainsString('### Chief Complaint', $data['polished_content']);
    }

    /**
     * Test that LlmController passes note_type when provided.
     */
    public function test_llm_controller_passes_note_type()
    {
        $gateway = Mockery::mock(LlmGatewayService::class);
        $contextService = Mockery::mock(PatientContextService::class);

        $gateway->shouldReceive('isEnabled')->andReturn(true);
        $gateway->shouldReceive('getConfig')->andReturn([
            'system_prompts' => ['polish_note' => 'System prompt'],
        ]);

        $gateway->shouldReceive('complete')
            ->once()
            ->withArgs(function ($systemPrompt, $userMessage, $options) {
                return str_contains($userMessage, 'TARGET CLINICAL PERSPECTIVE / NOTE TYPE: NURSE');
            })
            ->andReturn([
                'content' => "### Clinical Status\nPatient stable.",
                'model' => 'test-model',
                'provider' => 'test-provider',
            ]);

        $controller = new LlmController($gateway, $contextService);

        $request = Request::create('/llm/polish-note', 'POST', [
            'note_content' => 'Vitals stable. IV running.',
            'note_type' => 'nurse',
        ]);

        $response = $controller->polishNote($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
    }

    /**
     * Test fallback when system_prompts.polish_note in DB is empty string or whitespace.
     */
    public function test_llm_controller_falls_back_when_db_prompt_is_empty_or_whitespace()
    {
        $config = require __DIR__ . '/../../../config/llm.php';
        $defaultPrompt = $config['prompts']['polish_note'];

        $gateway = Mockery::mock(LlmGatewayService::class);
        $contextService = Mockery::mock(PatientContextService::class);

        $gateway->shouldReceive('isEnabled')->andReturn(true);
        // Stored config has empty whitespace prompt
        $gateway->shouldReceive('getConfig')->andReturn([
            'system_prompts' => ['polish_note' => "   \n  "],
        ]);

        $gateway->shouldReceive('complete')
            ->once()
            ->withArgs(function ($systemPrompt, $userMessage, $options) use ($defaultPrompt) {
                // If config helper is available or fallback works, it should not be empty whitespace
                return !empty(trim($systemPrompt));
            })
            ->andReturn([
                'content' => "Polished note content",
                'model' => 'test-model',
                'provider' => 'test-provider',
            ]);

        $controller = new LlmController($gateway, $contextService);

        $request = Request::create('/llm/polish-note', 'POST', [
            'note_content' => 'Sample note',
        ]);

        $response = $controller->polishNote($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
    }

    /**
     * Test that disabled gateway returns 403.
     */
    public function test_llm_controller_returns_403_when_disabled()
    {
        $gateway = Mockery::mock(LlmGatewayService::class);
        $contextService = Mockery::mock(PatientContextService::class);

        $gateway->shouldReceive('isEnabled')->andReturn(false);

        $controller = new LlmController($gateway, $contextService);

        $request = Request::create('/llm/polish-note', 'POST', [
            'note_content' => 'Sample note',
        ]);

        $response = $controller->polishNote($request);
        $this->assertEquals(403, $response->getStatusCode());
    }
}
