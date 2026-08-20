<?php

$dir = __DIR__ . '/app/Http/Controllers/OpsAudit';
$files = glob($dir . '/*.php');

foreach ($files as $file) {
    if (basename($file) === 'OpsAuditBaseController.php') continue;

    $content = file_get_contents($file);

    // Replace product?->product_name ... with $this->renderItemDetails($row)
    $content = preg_replace(
        '/\'product\'\s*=>\s*\$row->product\?->product_name.*?,/',
        "'product' => \$this->renderItemDetails(\$row),",
        $content
    );
    
    // Replace procedure mapping in Surgery
    $content = preg_replace(
        '/\'procedure\'\s*=>\s*\$row->is_free_form\s*\?\s*\$row->free_form_name\s*:\s*\(\$row->service\?->service_name\s*\?\?\s*\'-\'\),/',
        "'procedure' => \$this->renderItemDetails(\$row),",
        $content
    );
    $content = preg_replace(
        '/\'procedure\'\s*=>\s*\$row->service\?->service_name.*?,/',
        "'procedure' => \$this->renderItemDetails(\$row),",
        $content
    );
    
    // Replace item mapping in HmoController
    $content = preg_replace(
        '/\'item\'\s*=>\s*\$item,/',
        "'item' => \$this->renderItemDetails(\$row),",
        $content
    );

    // Eager load fixes in with([...]) ONLY
    $content = preg_replace_callback(
        '/with\(\[\s*(.*?)\s*\]\)/s',
        function ($matches) {
            $arrayContent = $matches[1];
            $arrayContent = preg_replace('/\'product\'\s*(?=[,])/', "'product.category'", $arrayContent);
            $arrayContent = preg_replace('/\'service\'\s*(?=[,])/', "'service.category'", $arrayContent);
            
            // For POSR
            if (strpos($arrayContent, "'product_or_service_request'") !== false && strpos($arrayContent, "'product_or_service_request.product.category'") === false) {
                $arrayContent = str_replace("'product_or_service_request'", "'product_or_service_request', 'product_or_service_request.product.category', 'product_or_service_request.service.category'", $arrayContent);
            }
            if (strpos($arrayContent, "'productOrServiceRequest'") !== false && strpos($arrayContent, "'productOrServiceRequest.product.category'") === false) {
                $arrayContent = str_replace("'productOrServiceRequest'", "'productOrServiceRequest', 'productOrServiceRequest.product.category', 'productOrServiceRequest.service.category'", $arrayContent);
            }

            return "with([\n" . $arrayContent . "\n])";
        },
        $content
    );

    file_put_contents($file, $content);
}

echo "Mappers updated.\n";
