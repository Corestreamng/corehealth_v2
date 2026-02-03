<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Checking Account structure:\n";
echo str_repeat("=", 80) . "\n\n";

$account = \App\Models\Accounting\Account::first();

if ($account) {
    echo "Sample Account:\n";
    echo "ID: {$account->id}\n";
    echo "Code: {$account->code}\n";
    echo "Name: {$account->name}\n";
    echo "Account Group ID: {$account->account_group_id}\n";
    if ($account->accountGroup) {
        echo "Account Group: {$account->accountGroup->name}\n";
        if ($account->accountGroup->accountClass) {
            echo "Account Class: {$account->accountGroup->accountClass->name}\n";
        }
    }
    echo "\nAll columns:\n";
    print_r($account->getAttributes());
} else {
    echo "No accounts found\n";
}
