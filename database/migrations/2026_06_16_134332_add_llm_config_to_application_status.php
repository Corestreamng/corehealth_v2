<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_status', function (Blueprint $table) {
            $table->json('llm_config')->nullable()->after('consent_template');
        });

        // Seed default config
        \Illuminate\Support\Facades\DB::table('application_status')->whereNull('llm_config')->update([
            'llm_config' => json_encode([
                'enabled' => false,
                'active_provider' => null,
                'active_model' => null,
                'summary_auto_open' => true,
                'summary_scope_months' => 3,
                'summary_scope_max_entries' => 50,
                'summary_voice_enabled' => true,
                'summary_voice_rate' => 1.0,
                'polish_note_enabled' => true,
                'providers' => [
                    'anthropic' => [
                        'enabled' => false,
                        'api_key' => '',
                        'base_url' => 'https://api.anthropic.com',
                        'default_model' => 'claude-sonnet-4-20250514',
                    ],
                    'openai' => [
                        'enabled' => false,
                        'api_key' => '',
                        'base_url' => 'https://api.openai.com/v1',
                        'default_model' => 'gpt-4o',
                    ],
                    'gemini' => [
                        'enabled' => false,
                        'api_key' => '',
                        'base_url' => 'https://generativelanguage.googleapis.com',
                        'default_model' => 'gemini-2.5-flash',
                    ],
                    'ollama' => [
                        'enabled' => false,
                        'api_key' => '',
                        'base_url' => 'http://localhost:11434',
                        'default_model' => 'llama3.1:8b',
                    ],
                    'huggingface' => [
                        'enabled' => false,
                        'api_key' => '',
                        'base_url' => 'https://api-inference.huggingface.co',
                        'default_model' => 'mistralai/Mistral-7B-Instruct-v0.3',
                    ],
                ],
                'system_prompts' => [
                    'patient_summary' => "You are a senior clinical AI assistant embedded in a hospital EHR system. Given the patient's clinical data below, produce a concise, well-structured clinical briefing suitable for a doctor beginning a consultation.\n\nFormat the summary with clear sections:\n1. PATIENT OVERVIEW (demographics, allergies, blood group)\n2. ACTIVE PROBLEMS & DIAGNOSES (with status and duration)\n3. CURRENT MEDICATIONS (with dose and frequency)\n4. RECENT VITAL SIGNS (last reading with trends noted)\n5. RECENT LAB & IMAGING RESULTS (flag abnormals)\n6. RECENT ENCOUNTERS & CLINICAL NOTES (key findings)\n7. ACTIVE ADMISSIONS, PROCEDURES, OR REFERRALS\n8. CLINICAL ALERTS (anything requiring immediate attention)\n\nBe factual and clinical. Do NOT fabricate data. If a section has no data, say 'No data available'. Use medical abbreviations where standard. Keep it under 800 words.",
                    'polish_note' => "You are a medical documentation specialist. Your task is to polish and improve the clinical note below while preserving ALL medical facts, diagnoses, and clinical observations.\n\nRules:\n1. Fix grammar, spelling, and punctuation errors\n2. Improve sentence structure for clinical clarity\n3. Use proper medical terminology where appropriate\n4. Maintain the original meaning and all clinical details\n5. Format using standard clinical note structure (if appropriate)\n6. Do NOT add information that wasn't in the original note\n7. Do NOT remove any clinical observations or diagnoses\n8. Keep the same general length (do not drastically shorten or expand)\n9. If the note references patient context, use it to improve accuracy of medical terms\n\nReturn ONLY the polished note, nothing else.",
                ],
                'rag_settings' => [
                    'chunk_size' => 512,
                    'chunk_overlap' => 50,
                    'top_k_results' => 10,
                    'similarity_threshold' => 0.7,
                    'cache_ttl_hours' => 24,
                ],
            ]),
        ]);
    }

    public function down(): void
    {
        Schema::table('application_status', function (Blueprint $table) {
            $table->dropColumn('llm_config');
        });
    }
};
