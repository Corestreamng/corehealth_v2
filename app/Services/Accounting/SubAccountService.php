<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountSubAccount;
use Illuminate\Support\Facades\Log;

/**
 * SubAccountService
 *
 * Centralized helper for creating and retrieving sub-accounts.
 * Used by observers to ensure consistent sub-account management.
 *
 * Reference: BANK_CASH_STATEMENT_IMPLEMENTATION.md - Part 7.5.1
 *
 * Sub-accounts provide:
 * - Formal subsidiary ledger tracking
 * - Entity-specific balances (HMO AR, Supplier AP, Patient AR)
 * - Granular reporting by entity
 */
class SubAccountService
{
    /**
     * Get or create HMO sub-account under AR-HMO (1110)
     *
     * Used by:
     * - ProductOrServiceRequestObserver (HMO revenue recognition)
     * - HmoRemittanceObserver (HMO payment receipt)
     *
     * @param mixed $hmo HMO model instance
     * @return AccountSubAccount|null
     */
    public function getOrCreateHmoSubAccount($hmo): ?AccountSubAccount
    {
        if (!$hmo) return null;

        $arHmo = Account::where('code', '1110')->first();
        if (!$arHmo) {
            Log::warning('SubAccountService: AR-HMO account (1110) not found');
            return null;
        }

        return AccountSubAccount::firstOrCreate(
            ['account_id' => $arHmo->id, 'hmo_id' => $hmo->id],
            [
                'code' => "1110.HMO.{$hmo->id}",
                'name' => $hmo->name ?? $hmo->company_name ?? "HMO #{$hmo->id}",
                'is_active' => true,
            ]
        );
    }

    /**
     * Get or create Supplier sub-account under AP (2100)
     *
     * Used by:
     * - PurchaseOrderObserver (AP creation on receipt)
     * - PurchaseOrderPaymentObserver (AP clearance)
     * - ExpenseObserver (AP creation for credit expenses)
     *
     * @param mixed $supplier Supplier model instance
     * @return AccountSubAccount|null
     */
    public function getOrCreateSupplierSubAccount($supplier): ?AccountSubAccount
    {
        if (!$supplier) return null;

        $ap = Account::where('code', '2100')->first();
        if (!$ap) {
            Log::warning('SubAccountService: Accounts Payable (2100) not found');
            return null;
        }

        return AccountSubAccount::firstOrCreate(
            ['account_id' => $ap->id, 'supplier_id' => $supplier->id],
            [
                'code' => "2100.SUP.{$supplier->id}",
                'name' => $supplier->name ?? $supplier->company_name ?? "Supplier #{$supplier->id}",
                'is_active' => true,
            ]
        );
    }

    /**
     * Get or create Patient sub-account under AR (1200)
     *
     * Used by:
     * - PaymentObserver (if tracking patient-level AR)
     * - InvoiceObserver (if billing creates AR)
     *
     * @param mixed $patient Patient model instance
     * @return AccountSubAccount|null
     */
    public function getOrCreatePatientSubAccount($patient): ?AccountSubAccount
    {
        if (!$patient) return null;

        $ar = Account::where('code', '1200')->first();
        if (!$ar) {
            Log::warning('SubAccountService: Accounts Receivable (1200) not found');
            return null;
        }

        $name = $patient->full_name
            ?? trim(($patient->first_name ?? '') . ' ' . ($patient->last_name ?? ''))
            ?: "Patient #{$patient->id}";

        return AccountSubAccount::firstOrCreate(
            ['account_id' => $ar->id, 'patient_id' => $patient->id],
            [
                'code' => "1200.PAT.{$patient->id}",
                'name' => $name,
                'is_active' => true,
            ]
        );
    }

    /**
     * Get or create Product sub-account under a given account
     *
     * Used for product-level tracking under Inventory or COGS
     *
     * @param Account $account Parent GL account
     * @param mixed $product Product model instance
     * @return AccountSubAccount|null
     */
    public function getOrCreateProductSubAccount(Account $account, $product): ?AccountSubAccount
    {
        if (!$product) return null;

        return AccountSubAccount::firstOrCreate(
            ['account_id' => $account->id, 'product_id' => $product->id],
            [
                'code' => "{$account->code}.PRD.{$product->id}",
                'name' => $product->name ?? "Product #{$product->id}",
                'is_active' => true,
            ]
        );
    }

    /**
     * Get or create Service sub-account under Revenue (4xxx)
     *
     * Used for service-level revenue tracking
     *
     * @param Account $account Parent revenue account
     * @param mixed $service Service model instance
     * @return AccountSubAccount|null
     */
    public function getOrCreateServiceSubAccount(Account $account, $service): ?AccountSubAccount
    {
        if (!$service) return null;

        return AccountSubAccount::firstOrCreate(
            ['account_id' => $account->id, 'service_id' => $service->id],
            [
                'code' => "{$account->code}.SVC.{$service->id}",
                'name' => $service->name ?? "Service #{$service->id}",
                'is_active' => true,
            ]
        );
    }

    /**
     * Get or create Service Category sub-account under Revenue
     *
     * Used for category-level revenue tracking (Lab, Imaging, etc.)
     *
     * @param Account $account Parent revenue account
     * @param mixed $category ServiceCategory model instance
     * @return AccountSubAccount|null
     */
    public function getOrCreateServiceCategorySubAccount(Account $account, $category): ?AccountSubAccount
    {
        if (!$category) return null;

        return AccountSubAccount::firstOrCreate(
            ['account_id' => $account->id, 'service_category_id' => $category->id],
            [
                'code' => "{$account->code}.CAT.{$category->id}",
                'name' => $category->name ?? "Category #{$category->id}",
                'is_active' => true,
            ]
        );
    }

    /**
     * Get sub-account balance
     *
     * @param AccountSubAccount $subAccount
     * @param string|null $asOfDate Optional date for point-in-time balance
     * @return float
     */
    public function getSubAccountBalance(AccountSubAccount $subAccount, ?string $asOfDate = null): float
    {
        $query = $subAccount->journalEntryLines()
            ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('status', 'posted');
                if ($asOfDate) {
                    $q->where('entry_date', '<=', $asOfDate);
                }
            });

        $debits = (clone $query)->sum('debit_amount');
        $credits = (clone $query)->sum('credit_amount');

        // For asset/expense accounts: Debit - Credit
        // For liability/equity/revenue accounts: Credit - Debit
        // Assuming this is used for AR/AP which are asset/liability
        $account = $subAccount->account;

        if (in_array($account->account_group?->type, ['asset', 'expense'])) {
            return $debits - $credits;
        }

        return $credits - $debits;
    }
}
