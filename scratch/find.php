<?php
$bladeFile = '/home/mrapollos/Documents/work/corehealth_v2/resources/views/admin/audit/workbench.blade.php';
$content = file_get_contents($bladeFile);
$start = strpos($content, '{{-- Panel: Module 1 Financials --}}');
$end = strpos($content, '{{-- Drawer Overlay --}}');
echo "Start: $start, End: $end\n";

// Count instances
echo "Count Panel: " . substr_count($content, '{{-- Panel: Module 1 Financials --}}') . "\n";
echo "Count Drawer: " . substr_count($content, '{{-- Drawer Overlay --}}') . "\n";
