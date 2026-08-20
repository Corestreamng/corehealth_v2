<?php

$dir = __DIR__ . '/app/Http/Controllers/OpsAudit';
$files = glob($dir . '/*.php');

foreach ($files as $file) {
    if (basename($file) === 'OpsAuditBaseController.php') continue;

    $content = file_get_contents($file);

    // Regex to match $this->applyPaymentFilters($query, $request, 'SOMETHING');
    $content = preg_replace_callback(
        '/\\$this->applyPaymentFilters\\(\\$query,\\s*\\$request,\\s*(["\'])(.*?)\\1\\);/m',
        function ($matches) {
            $relation = $matches[2];
            $itemRelation = $relation === 'self_payment' ? 'product_or_service_request' : $relation;
            
            // Avoid duplicate injection
            $newLine = "        \$this->applyItemFilters(\$query, \$request, '{$itemRelation}');";
            return $matches[0] . "\n" . $newLine;
        },
        $content
    );

    file_put_contents($file, $content);
}

echo "Injection complete.\n";
