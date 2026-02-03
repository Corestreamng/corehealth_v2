<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "=== Fixed Asset Disposal Type Enum ===\n\n";

    $result = DB::select("SHOW COLUMNS FROM fixed_asset_disposals WHERE Field = 'disposal_type'");

    if (!empty($result)) {
        $type = $result[0]->Type;
        echo "Column Type: $type\n\n";

        // Extract enum values
        if (preg_match("/^enum\((.+)\)$/", $type, $matches)) {
            $values = str_getcsv($matches[1], ',', "'");
            echo "Allowed Values:\n";
            foreach ($values as $value) {
                echo "  - $value\n";
            }
        }
    }

    echo "\n=== Model Constants ===\n\n";
    $reflection = new ReflectionClass(\App\Models\Accounting\FixedAssetDisposal::class);
    $constants = $reflection->getConstants();
    foreach ($constants as $name => $value) {
        if (strpos($name, 'TYPE_') === 0) {
            echo "$name = '$value'\n";
        }
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
