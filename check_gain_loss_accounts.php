<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Accounting\Account;

try {
    echo "=== Searching for Gain/Loss Accounts ===\n\n";

    // Search for accounts that might be gain/loss on disposal
    echo "Accounts in 4xxx range (Income/Gains):\n";
    $incomeAccounts = DB::table('accounts')
        ->where('code', 'LIKE', '4%')
        ->whereNull('deleted_at')
        ->orderBy('code')
        ->get(['code', 'name', 'account_group_id']);

    foreach($incomeAccounts as $acc) {
        if (stripos($acc->name, 'gain') !== false ||
            stripos($acc->name, 'disposal') !== false ||
            stripos($acc->name, 'other') !== false) {
            echo "  {$acc->code} - {$acc->name}\n";
        }
    }

    echo "\nAccounts in 6xxx range (Expenses/Losses):\n";
    $expenseAccounts = DB::table('accounts')
        ->where('code', 'LIKE', '6%')
        ->whereNull('deleted_at')
        ->orderBy('code')
        ->get(['code', 'name', 'account_group_id']);

    foreach($expenseAccounts as $acc) {
        if (stripos($acc->name, 'loss') !== false ||
            stripos($acc->name, 'disposal') !== false ||
            stripos($acc->name, 'other') !== false) {
            echo "  {$acc->code} - {$acc->name}\n";
        }
    }

    echo "\n=== Checking Specific Codes ===\n";
    $codes = ['4200', '4210', '4900', '6900', '6910', '6950'];
    foreach($codes as $code) {
        $acc = Account::where('code', $code)->first();
        if ($acc) {
            echo "{$code}: {$acc->name}\n";
        } else {
            echo "{$code}: NOT FOUND\n";
        }
    }

    echo "\n=== Account Groups ===\n";
    $groups = DB::table('account_groups')->get(['id', 'code', 'name', 'type']);
    foreach($groups as $group) {
        echo "{$group->id}. {$group->code} - {$group->name} ({$group->type})\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
