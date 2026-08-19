<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernelConsole = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernelConsole->bootstrap();

echo "=== VALIDATING CASHIERS ===\n";
try {
    $cashiers = \App\Models\User::role(['SUPERADMIN', 'ADMIN', 'ACCOUNTS', 'BILLER'])
        ->orderBy('firstname')
        ->get()
        ->mapWithKeys(fn($u) => [$u->id => trim($u->firstname . ' ' . ($u->othername ?? '') . ' ' . $u->surname)]);
    echo "Success: Fetched " . $cashiers->count() . " cashiers.\n";
    if ($cashiers->count() > 0) {
        echo "Sample Cashier: " . $cashiers->first() . "\n";
    }
} catch (\Exception $e) {
    echo "Error fetching cashiers: " . $e->getMessage() . "\n";
}

echo "\n=== VALIDATING ALL 12 MODULES & TABS ===\n";

$modules = [];
$controllers = glob(app_path('Http/Controllers/OpsAudit/*Controller.php'));
foreach ($controllers as $controller) {
    if (basename($controller) === 'OpsAuditBaseController.php') continue;
    
    // e.g. OpsAuditBillingController -> billing
    $module = strtolower(str_replace(['OpsAudit', 'Controller.php'], '', basename($controller)));
    $content = file_get_contents($controller);
    
    preg_match_all("/case\s+'([^']+)':/", $content, $matches);
    if (!empty($matches[1])) {
        $modules[$module] = array_unique($matches[1]);
    }
}


$user = \App\Models\User::first();
\Illuminate\Support\Facades\Auth::login($user); // Authenticate to bypass auth middleware

$kernel = app()->make(Illuminate\Contracts\Http\Kernel::class);

$allPassed = true;
$testCount = 1;

foreach ($modules as $module => $tabs) {
    foreach ($tabs as $tab) {
        // 1. Test AJAX Request
        $uri = "/ops-audit/{$module}/data/{$tab}";
        echo "Test #" . $testCount++ . ": Testing AJAX Data for [$module -> $tab] ($uri)...\n";
        $request = \Illuminate\Http\Request::create($uri, 'GET', ['start' => 0, 'length' => 10], [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $response = $kernel->handle($request);
        
        if ($response->getStatusCode() === 200) {
            $json = json_decode($response->getContent(), true);
            if (isset($json['data'])) {
                echo "    -> OK\n";
            } else {
                echo "    -> FAILED (No data key)\n";
                $allPassed = false;
            }
        } else {
            echo "    -> FAILED (Status {$response->getStatusCode()})\n";
            if ($response->getStatusCode() === 500) {
                echo "       ERROR: " . substr(strip_tags($response->getContent()), 0, 500) . "\n";
            }
            $allPassed = false;
        }

        // 2. Test Print Request
        echo "Test #" . $testCount++ . ": Testing Print View for [$module -> $tab] ($uri?action=print)...\n";
        $printRequest = \Illuminate\Http\Request::create($uri, 'GET', ['action' => 'print', 'tab' => $tab], [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $printResponse = $kernel->handle($printRequest);
        
        if ($printResponse->getStatusCode() === 200) {
            $content = $printResponse->getContent();
            if (strpos($content, 'OPS AUDIT') !== false) {
                echo "    -> OK\n";
            } else {
                echo "    -> FAILED (View content invalid)\n";
                $allPassed = false;
            }
        } else {
            echo "    -> FAILED (Status {$printResponse->getStatusCode()})\n";
            if ($printResponse->getStatusCode() === 500) {
                echo "       ERROR: " . substr(strip_tags($printResponse->getContent()), 0, 500) . "\n";
            }
            $allPassed = false;
        }
    }
}

if ($allPassed) {
    echo "\nALL TESTS PASSED SUCCESSFULLY!\n";
} else {
    echo "\nSOME TESTS FAILED!\n";
}
