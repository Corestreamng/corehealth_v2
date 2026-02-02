<?php

namespace App\Observers\Accounting;

use App\Models\Bank;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Bank Observer
 *
 * Automatically creates a GL account when a bank is created.
 *
 * Journal Entry Pattern:
 *   When a bank is created without an account_id, this observer:
 *   1. Creates a new GL Account under the "Cash & Bank" or "Bank" account group
 *   2. Links the bank to the newly created account
 *
 * This ensures all banks are properly linked to GL accounts for reconciliation.
 */
class BankObserver
{
    /**
     * Handle the Bank "created" event.
     */
    public function created(Bank $bank): void
    {
        // Skip if bank already has an account_id
        if ($bank->account_id) {
            Log::info('BankObserver: Bank already has account_id', [
                'bank_id' => $bank->id,
                'account_id' => $bank->account_id
            ]);
            return;
        }

        try {
            DB::beginTransaction();

            // Find the bank account group (typically under Assets > Current Assets > Cash & Bank)
            $bankGroup = AccountGroup::whereHas('accountClass', function ($q) {
                $q->where('name', 'LIKE', '%ASSET%');
            })->where(function ($q) {
                $q->where('name', 'LIKE', '%Bank%')
                  ->orWhere('name', 'LIKE', '%Cash%');
            })->first();

            if (!$bankGroup) {
                // Try to find any asset group as fallback
                $bankGroup = AccountGroup::whereHas('accountClass', function ($q) {
                    $q->where('name', 'LIKE', '%ASSET%');
                })->first();
            }

            if (!$bankGroup) {
                Log::warning('BankObserver: No suitable account group found for bank', [
                    'bank_id' => $bank->id,
                    'bank_name' => $bank->name
                ]);
                DB::rollBack();
                return;
            }

            // Generate unique account code
            $lastAccount = Account::where('account_group_id', $bankGroup->id)
                ->orderBy('code', 'desc')
                ->first();

            $baseCode = $bankGroup->code ?? '1000';
            if ($lastAccount) {
                $lastCode = intval($lastAccount->code);
                $newCode = str_pad($lastCode + 1, strlen($lastAccount->code), '0', STR_PAD_LEFT);
            } else {
                $newCode = $baseCode . '01';
            }

            // Create the GL account for this bank
            $account = Account::create([
                'account_group_id' => $bankGroup->id,
                'code' => $newCode,
                'name' => 'Bank - ' . $bank->name,
                'description' => "GL Account for {$bank->name}" . ($bank->account_number ? " ({$bank->account_number})" : ''),
                'bank_id' => $bank->id,
                'is_active' => true,
                'is_system' => false,
                'is_bank_account' => true,
            ]);

            // Update the bank with the new account_id
            $bank->update(['account_id' => $account->id]);

            Log::info('BankObserver: Created GL account for bank', [
                'bank_id' => $bank->id,
                'bank_name' => $bank->name,
                'account_id' => $account->id,
                'account_code' => $account->code,
                'account_name' => $account->name
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('BankObserver: Failed to create GL account for bank', [
                'bank_id' => $bank->id,
                'bank_name' => $bank->name,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the Bank "updated" event.
     */
    public function updated(Bank $bank): void
    {
        // If bank name changed and has an account, update the account name
        if ($bank->isDirty('name') && $bank->account_id) {
            try {
                $account = Account::find($bank->account_id);
                if ($account) {
                    $account->update([
                        'name' => 'Bank - ' . $bank->name,
                        'description' => "GL Account for {$bank->name}" . ($bank->account_number ? " ({$bank->account_number})" : ''),
                    ]);

                    Log::info('BankObserver: Updated GL account name', [
                        'bank_id' => $bank->id,
                        'account_id' => $account->id,
                        'new_name' => $account->name
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('BankObserver: Failed to update GL account name', [
                    'bank_id' => $bank->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // If account_id was just assigned (was null, now set), update the account's bank_id
        if ($bank->isDirty('account_id') && $bank->account_id) {
            try {
                $account = Account::find($bank->account_id);
                if ($account && !$account->bank_id) {
                    $account->update([
                        'bank_id' => $bank->id,
                        'is_bank_account' => true,
                    ]);

                    Log::info('BankObserver: Linked GL account to bank', [
                        'bank_id' => $bank->id,
                        'account_id' => $account->id
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('BankObserver: Failed to link GL account to bank', [
                    'bank_id' => $bank->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
