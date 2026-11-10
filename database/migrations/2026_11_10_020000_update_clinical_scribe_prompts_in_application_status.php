<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $scribePrompt = config('llm.prompts.clinical_scribe');
        $convertPrompt = config('llm.prompts.convert_taxonomy');

        $record = DB::table('application_status')->first();
        if ($record) {
            $llmConfig = is_string($record->llm_config)
                ? json_decode($record->llm_config, true)
                : (is_array($record->llm_config) ? $record->llm_config : []);

            if (!isset($llmConfig['system_prompts']) || !is_array($llmConfig['system_prompts'])) {
                $llmConfig['system_prompts'] = [];
            }

            $llmConfig['system_prompts']['clinical_scribe'] = $scribePrompt;
            $llmConfig['system_prompts']['convert_taxonomy'] = $convertPrompt;
            $llmConfig['summary_auto_open'] = false;

            DB::table('application_status')
                ->where('id', $record->id)
                ->update(['llm_config' => json_encode($llmConfig)]);

            Cache::forget('app_settings');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reversal needed for prompt updates
    }
};
