<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountGroup;
use Illuminate\Support\Facades\DB;

class GainLossDisposalAccountsSeeder extends Seeder
{
    /**
     * Seed gain and loss on disposal accounts for fixed assets.
     */
    public function run(): void
    {
        // Find appropriate account groups
        $otherIncomeGroup = AccountGroup::where('code', '42')->first(); // Other Income
        $adminExpensesGroup = AccountGroup::where('code', '53')->first(); // Administrative Expenses

        if (!$otherIncomeGroup) {
            $this->command->error('Other Income group (42) not found!');
            return;
        }

        if (!$adminExpensesGroup) {
            $this->command->error('Administrative Expenses group (53) not found!');
            return;
        }

        // Create or update Gain on Disposal account
        $gainAccount = Account::updateOrCreate(
            ['code' => '4220'],
            [
                'account_group_id' => $otherIncomeGroup->id,
                'name' => 'Gain on Disposal of Assets',
                'description' => 'Gains realized from disposal of fixed assets above book value',
                'is_system' => false,
                'is_active' => true,
                'is_bank_account' => false,
            ]
        );

        if ($gainAccount->wasRecentlyCreated) {
            $this->command->info("✓ Created: {$gainAccount->code} - {$gainAccount->name}");
        } else {
            $this->command->info("✓ Updated: {$gainAccount->code} - {$gainAccount->name}");
        }

        // Create or update Loss on Disposal account
        $lossAccount = Account::updateOrCreate(
            ['code' => '6900'],
            [
                'account_group_id' => $adminExpensesGroup->id,
                'name' => 'Loss on Disposal of Assets',
                'description' => 'Losses realized from disposal of fixed assets below book value',
                'is_system' => false,
                'is_active' => true,
                'is_bank_account' => false,
            ]
        );

        if ($lossAccount->wasRecentlyCreated) {
            $this->command->info("✓ Created: {$lossAccount->code} - {$lossAccount->name}");
        } else {
            $this->command->info("✓ Updated: {$lossAccount->code} - {$lossAccount->name}");
        }

        $this->command->newLine();
        $this->command->info('=== Summary ===');
        $this->command->info("Gain Account: {$gainAccount->code} - {$gainAccount->name}");
        $this->command->info("Loss Account: {$lossAccount->code} - {$lossAccount->name}");
    }
}
