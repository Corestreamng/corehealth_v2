<?php

namespace Database\Seeders;

use App\Models\V1ResultTemplate;
use Illuminate\Database\Seeder;

class V1ResultTemplateSeeder extends Seeder
{
    /**
     * Seed V1 result templates based on physical laboratory report forms.
     */
    public function run(): void
    {
        $this->command->info('Seeding V1 Result Templates...');
        $count = 0;

        $jsonPath = database_path('data/v1_result_templates.json');
        if (!file_exists($jsonPath)) {
            $this->command->error("Template data file not found: {$jsonPath}");

            return;
        }

        $templates = json_decode(file_get_contents($jsonPath), true) ?: [];

        foreach ($templates as $t) {
            $template = V1ResultTemplate::updateOrCreate(
                ['name' => $t['name']],
                [
                    'description' => $t['description'],
                    'content' => $t['content'],
                    'category' => $t['category'],
                    'sort_order' => $t['sort_order'],
                    'is_active' => true,
                    'created_by' => 1,
                ]
            );
            if ($template->wasRecentlyCreated) {
                $count++;
                $this->command->line("  + [{$t['category']}] {$t['name']}");
            } else {
                $this->command->line("  = [{$t['category']}] {$t['name']} (updated)");
            }
        }

        $this->command->info("V1 Result Templates seeded: {$count}");
    }
}
