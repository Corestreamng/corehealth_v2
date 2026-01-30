<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get first account with activity
$account = \App\Models\Accounting\Account::whereHas('journalLines')->first();
echo "Test URL: http://localhost:8000/accounting/reports/general-ledger?account_id={$account->id}&start_date=2026-01-01&end_date=2026-01-30\n";
echo "Account: [{$account->code}] {$account->name}\n";
