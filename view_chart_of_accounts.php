<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "=================================================\n";
    echo "CHART OF ACCOUNTS - BY GROUP\n";
    echo "=================================================\n\n";

    $groups = DB::table('account_groups')
        ->orderBy('code')
        ->get();

    foreach($groups as $group) {
        echo "┌─────────────────────────────────────────────\n";
        echo "│ GROUP: [{$group->code}] {$group->name}\n";
        echo "│ ID: {$group->id}\n";
        echo "└─────────────────────────────────────────────\n";

        $accounts = DB::table('accounts')
            ->where('account_group_id', $group->id)
            ->whereNull('deleted_at')
            ->orderBy('code')
            ->get();

        if ($accounts->count() > 0) {
            foreach($accounts as $acc) {
                $bankTag = $acc->is_bank_account ? ' [BANK]' : '';
                $systemTag = $acc->is_system ? ' [SYSTEM]' : '';
                echo "  {$acc->code} - {$acc->name}{$bankTag}{$systemTag}\n";
            }
        } else {
            echo "  (No accounts)\n";
        }
        echo "\n";
    }

    echo "=================================================\n";
    echo "SUMMARY\n";
    echo "=================================================\n";
    echo "Total Groups: {$groups->count()}\n";
    $totalAccounts = DB::table('accounts')->whereNull('deleted_at')->count();
    echo "Total Active Accounts: {$totalAccounts}\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
