<?php

namespace Database\Seeders;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Salaries Payable Account Seeder
 *
 * Reference: BANK_CASH_STATEMENT_IMPLEMENTATION.md - Part 7.1.1
 *
 * Creates the Salaries Payable (2050) account required for
 * two-stage payroll accrual accounting.
 *
 * Usage:
 *   php artisan db:seed --class=SalariesPayableAccountSeeder
 */
class SalariesPayableAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            // Check if account already exists
            $existing = Account::where('code', '2050')->first();

            if ($existing) {
                $this->command->info('Account 2050 (Salaries Payable) already exists - skipped');
                Log::info('SalariesPayableAccountSeeder: Account 2050 already exists', [
                    'account_id' => $existing->id,
                    'name' => $existing->name
                ]);
                DB::rollBack();
                return;
            }

            // Find the Current Liabilities group (should be 21xx range)
            // Look for existing AP (2100) to find the group
            $apAccount = Account::where('code', '2100')->first();
            $groupId = $apAccount?->account_group_id;

            if (!$groupId) {
                // Try to find by name
                $group = AccountGroup::where('name', 'like', '%Current Liabilities%')
                    ->orWhere('name', 'like', '%Liabilities%')
                    ->first();
                $groupId = $group?->id;
            }

            if (!$groupId) {
                $this->command->error('Could not find Current Liabilities account group');
                Log::error('SalariesPayableAccountSeeder: Failed to find Current Liabilities group');
                DB::rollBack();
                return;
            }

            // Create the Salaries Payable account
            $account = Account::create([
                'account_group_id' => $groupId,
                'code' => '2050',
                'name' => 'Salaries Payable',
                'description' => 'Accrued salaries and wages liability for employees. Used in two-stage payroll accounting - credited when payroll is approved, debited when paid.',
                'is_active' => true,
                'is_system' => true,
                'is_bank_account' => false,
            ]);

            DB::commit();

            $this->command->info("Created Account 2050 (Salaries Payable) with ID: {$account->id}");
            Log::info('SalariesPayableAccountSeeder: Created account', [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'group_id' => $groupId
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Failed to create Salaries Payable account: ' . $e->getMessage());
            Log::error('SalariesPayableAccountSeeder: Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
