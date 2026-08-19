<?php

$controllers = [
    'OpsAuditImagingController.php',
    'OpsAuditLabController.php',
    'OpsAuditSurgeryController.php',
    'OpsAuditMorgueController.php',
    'OpsAuditNursingController.php',
];

$dir = "/home/mrapollos/Documents/work/corehealth_v2/app/Http/Controllers/OpsAudit/";

foreach ($controllers as $c) {
    $file = $dir . $c;
    if (!file_exists($file)) continue;
    
    $content = file_get_contents($file);
    
    $content = preg_replace_callback("/\bfunction\s+billsData\s*\([\s\S]*?(protected\s+function|$)/i", function($matches) {
        $block = $matches[0];
        $block = str_replace("'productOrServiceRequest.payment.user'", "'payment.user'", $block);
        $block = str_replace("'serviceRequest.payment.user'", "'payment.user'", $block);
        $block = preg_replace("/applyPaymentFilters\(\\\$query,\s*\\\$request,\s*'productOrServiceRequest'\)/", "applyPaymentFilters(\$query, \$request, '')", $block);
        $block = preg_replace("/applyPaymentFilters\(\\\$query,\s*\\\$request,\s*'serviceRequest'\)/", "applyPaymentFilters(\$query, \$request, '')", $block);
        $block = preg_replace("/applyPaymentFilters\(\\\$query,\s*\\\$request,\s*'self_payment'\)/", "applyPaymentFilters(\$query, \$request, '')", $block);
        return $block;
    }, $content);
    
    file_put_contents($file, $content);
}

// Maternity Babies, Immunizations, Postnatal, Anc
$file = $dir . 'OpsAuditMaternityController.php';
$content = file_get_contents($file);

// ancData
$content = preg_replace_callback("/\bfunction\s+ancData\s*\([\s\S]*?(protected\s+function|$)/i", function($matches) {
    $block = $matches[0];
    $block = str_replace(",\n            'serviceRequest.payment.user'", "", $block);
    $block = str_replace("'serviceRequest.payment.user',", "", $block);
    $block = preg_replace("/applyPaymentFilters\(\\\$query,\s*\\\$request,\s*'serviceRequest'\)/", "applyPaymentFilters(\$query, \$request, 'encounter.productOrServiceRequest')", $block);
    return $block;
}, $content);

// babiesData
$content = preg_replace_callback("/\bfunction\s+babiesData\s*\([\s\S]*?(protected\s+function|$)/i", function($matches) {
    $block = $matches[0];
    $block = str_replace("'encounter.productOrServiceRequest.payment.user'", "'enrollment.serviceRequest.payment.user'", $block);
    $block = preg_replace("/applyPaymentFilters\(\\\$query,\s*\\\$request,\s*'encounter\.productOrServiceRequest'\)/", "applyPaymentFilters(\$query, \$request, 'enrollment.serviceRequest')", $block);
    return $block;
}, $content);

// immunizationsData
$content = preg_replace_callback("/\bfunction\s+immunizationsData\s*\([\s\S]*?(protected\s+function|$)/i", function($matches) {
    $block = $matches[0];
    $block = str_replace("'encounter.productOrServiceRequest.payment.user'", "'enrollment.serviceRequest.payment.user'", $block);
    $block = preg_replace("/applyPaymentFilters\(\\\$query,\s*\\\$request,\s*'encounter\.productOrServiceRequest'\)/", "applyPaymentFilters(\$query, \$request, 'enrollment.serviceRequest')", $block);
    return $block;
}, $content);

file_put_contents($file, $content);


// Pharmacy Stock
$file = $dir . 'OpsAuditPharmacyController.php';
$content = file_get_contents($file);
$content = preg_replace_callback("/\bfunction\s+stockData\s*\([\s\S]*?(protected\s+function|$)/i", function($matches) {
    $block = $matches[0];
    $block = str_replace(",\n            'productOrServiceRequest.payment.user'", "", $block);
    $block = str_replace("'productOrServiceRequest.payment.user',", "", $block);
    $block = preg_replace("/\s*\\\$this->applyPaymentFilters\(\\\$query,\s*\\\$request,\s*'productOrServiceRequest'\);/", "", $block);
    return $block;
}, $content);
file_put_contents($file, $content);

// Nursing Notes
$file = $dir . 'OpsAuditNursingController.php';
$content = file_get_contents($file);
$content = preg_replace_callback("/\bfunction\s+notesData\s*\([\s\S]*?(protected\s+function|$)/i", function($matches) {
    $block = $matches[0];
    $block = str_replace(",\n            'productOrServiceRequest.payment.user'", "", $block);
    $block = str_replace("'productOrServiceRequest.payment.user',", "", $block);
    $block = preg_replace("/\s*\\\$this->applyPaymentFilters\(\\\$query,\s*\\\$request,\s*'productOrServiceRequest'\);/", "", $block);
    return $block;
}, $content);
file_put_contents($file, $content);

echo "Fixed.\n";
