<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Accounting\Account;

try {
    echo "=== Account Groups ===\n\n";
    $groups = DB::table('account_groups')->orderBy('code')->get();
    foreach($groups as $g) {
        echo "{$g->id}. [{$g->code}] {$g->name}\n";
    }

    echo "\n=== Existing Income/Expense Accounts ===\n\n";
    echo "Income Accounts (4xxx):\n";
    $income = DB::table('accounts')
        ->where('code', 'LIKE', '4%')
        ->whereNull('deleted_at')
        ->orderBy('code')
        ->get();
    foreach($income as $acc) {
        echo "  {$acc->code} - {$acc->name} (Group: {$acc->account_group_id})\n";
    }

    echo "\nExpense Accounts (6xxx):\n";
    $expenses = DB::table('accounts')
        ->where('code', 'LIKE', '6%')
        ->whereNull('deleted_at')
        ->orderBy('code')
        ->limit(10)
        ->get();
    foreach($expenses as $acc) {
        echo "  {$acc->code} - {$acc->name} (Group: {$acc->account_group_id})\n";
    }

    echo "\n=== Creating Missing Accounts ===\n\n";

    // Find the correct groups for income and expenses
    $incomeGroup = DB::table('account_groups')
        ->where('code', 'LIKE', '4%')
        ->orWhere('name', 'LIKE', '%income%')
        ->first();

    $expenseGroup = DB::table('account_groups')
        ->where('code', 'LIKE', '6%')
        ->orWhere('name', 'LIKE', '%expense%')
        ->first();

    if (!$incomeGroup) {
        echo "ERROR: Cannot find income account group!\n";
    } else {
        echo "Income Group: {$incomeGroup->name} (ID: {$incomeGroup->id})\n";
    }

    if (!$expenseGroup) {
        echo "ERROR: Cannot find expense account group!\n";
    } else {
        echo "Expense Group: {$expenseGroup->name} (ID: {$expenseGroup->id})\n";
    }

    // Check if gain/loss accounts exist
    $gainAcc = Account::where('code', '4210')->first();
    $lossAcc = Account::where('code', '6900')->first();

    if ($gainAcc) {
        echo "\n✓ Gain Account exists: {$gainAcc->code} - {$gainAcc->name}\n";
    }

    if (!$lossAcc && $expenseGroup) {
        echo "\n✗ Loss Account (6900) not found. Creating...\n";

        $newLoss = Account::create([
            'account_group_id' => $expenseGroup->id,
            'code' => '6900',
            'name' => 'Loss on Disposal of Assets',
            'description' => 'Losses realized from disposal of fixed assets below book value',
            'is_system' => false,
            'is_active' => true,
            'is_bank_account' => false,
        ]);

        echo "✓ Created: {$newLoss->code} - {$newLoss->name}\n";
    } elseif ($lossAcc) {
        echo "\n✓ Loss Account exists: {$lossAcc->code} - {$lossAcc->name}\n";
    }

    // Also create a proper Gain account if needed
    $properGainAcc = Account::where('code', '4220')->first();
    if (!$properGainAcc && $incomeGroup) {
        echo "\n✗ Gain on Disposal Account (4220) not found. Creating...\n";

        $newGain = Account::create([
            'account_group_id' => $incomeGroup->id,
            'code' => '4220',
            'name' => 'Gain on Disposal of Assets',
            'description' => 'Gains realized from disposal of fixed assets above book value',
            'is_system' => false,
            'is_active' => true,
            'is_bank_account' => false,
        ]);

        echo "✓ Created: {$newGain->code} - {$newGain->name}\n";
    }

    echo "\n=== Summary ===\n";
    echo "Gain Account to use: 4220 - Gain on Disposal of Assets\n";
    echo "Loss Account to use: 6900 - Loss on Disposal of Assets\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
