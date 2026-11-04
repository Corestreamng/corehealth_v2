<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UpdatePolishPromptCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'llm:update-polish-prompt';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the note polishing prompt in the application_status table with the latest system default';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $prompt = config('llm.prompts.polish_note');

        if (empty($prompt)) {
            $this->error('No prompt found in config/llm.php under prompts.polish_note.');

            return 1;
        }

        $record = DB::table('application_status')->first();

        if (!$record) {
            $this->error('No record found in application_status table.');

            return 1;
        }

        $llmConfig = is_string($record->llm_config)
            ? json_decode($record->llm_config, true)
            : (is_array($record->llm_config) ? $record->llm_config : []);

        if (!isset($llmConfig['system_prompts']) || !is_array($llmConfig['system_prompts'])) {
            $llmConfig['system_prompts'] = [];
        }

        $llmConfig['system_prompts']['polish_note'] = $prompt;

        DB::table('application_status')
            ->where('id', $record->id)
            ->update([
                'llm_config' => json_encode($llmConfig),
                'updated_at' => now(),
            ]);

        Cache::forget('app_settings');

        $this->info('Successfully updated the note polishing prompt in application_status table!');
        $this->line('Prompt length: ' . strlen($prompt) . ' characters.');

        return 0;
    }
}
