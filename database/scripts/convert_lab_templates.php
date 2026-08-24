<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Service;
use App\Models\V1ResultTemplate;

echo "Starting lab template migration...\n";

// Get all investigation services that have a template
// category_id 2 is Investigation
$services = Service::where('category_id', 2)->whereNotNull('template')->get();

$count = 0;
$skipped = 0;

foreach ($services as $service) {
    $html = $service->template;
    
    if (empty(trim($html))) {
        $skipped++;
        continue;
    }

    // Replace <table class="table"> with <table class="table table-bordered">
    // Handle single and double quotes
    $html = preg_replace_callback('/<table[^>]*class\s*=\s*(["\'])(.*?)\1[^>]*>/i', function($matches) {
        $quote = $matches[1];
        $classes = $matches[2];
        if (strpos($classes, 'table-bordered') === false) {
            $classes .= ' table-bordered';
        }
        return str_replace($matches[1].$matches[2].$matches[1], $quote . trim($classes) . $quote, $matches[0]);
    }, $html);
    
    // If table has no class attribute at all
    $html = preg_replace('/<table(?![^>]*class\s*=)[^>]*>/i', '<table class="table table-bordered">', $html);

    // Strip contenteditable attributes
    $html = preg_replace('/\s*contenteditable\s*=\s*(["\'])(true|false)\1/i', '', $html);
    $html = preg_replace('/\s*contenteditable=(true|false)/i', '', $html);

    // Remove placeholder spans with border styling
    // Handle single and double quotes in style
    $html = preg_replace('/<span[^>]*style\s*=\s*(["\'])[^"\']*border:\s*1px\s+solid\s+black[^"\']*\1[^>]*>.*?<\/span>/is', '', $html);

    // Clean up any double spaces created by removing attributes
    $html = preg_replace('/\s{2,}/', ' ', $html);
    
    // Remove empty class="" attributes just in case
    $html = preg_replace('/\s*class\s*=\s*(["\'])\1/i', '', $html);
    
    $html = trim($html);

    $template = V1ResultTemplate::updateOrCreate(
        ['name' => $service->service_name],
        [
            'description'   => 'Migrated from legacy services table',
            'content'       => $html,
            'category'      => 'Lab', // User requested category as "Lab"
            'template_type' => 'lab',
            'is_active'     => true,
            'created_by'    => 1,
        ]
    );

    $count++;
}

echo "Migration completed.\n";
echo "Successfully processed {$count} templates.\n";
echo "Skipped {$skipped} empty templates.\n";
