<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountClass;

/**
 * CashFlowClassifier
 *
 * Determines the cash flow category (operating/investing/financing) for journal entry lines.
 * Used by observers to auto-classify transactions for the Cash Flow Statement.
 *
 * Reference: BANK_CASH_STATEMENT_IMPLEMENTATION.md - Part 1B
 *
 * Classification Priority:
 * 1. Explicit override from observer (highest priority)
 * 2. Account's cash_flow_category_override
 * 3. Account Class's cash_flow_category
 * 4. Smart inference from transaction category field
 * 5. Default to 'operating' (most common in hospital context)
 */
class CashFlowClassifier
{
    // Cash flow categories (IAS 7 / IFRS)
    public const OPERATING = 'operating';
    public const INVESTING = 'investing';
    public const FINANCING = 'financing';

    /**
     * Category to cash flow mapping
     *
     * Maps transaction categories to their appropriate cash flow classification.
     * Most hospital transactions are operating activities.
     */
    public const CASH_FLOW_MAP = [
        // =============================================
        // OPERATING ACTIVITIES (day-to-day operations)
        // =============================================

        // Healthcare Revenue
        'consultation' => self::OPERATING,
        'pharmacy' => self::OPERATING,
        'lab' => self::OPERATING,
        'imaging' => self::OPERATING,
        'procedure' => self::OPERATING,
        'admission' => self::OPERATING,
        'outpatient' => self::OPERATING,
        'inpatient' => self::OPERATING,
        'emergency' => self::OPERATING,
        'dental' => self::OPERATING,
        'optical' => self::OPERATING,
        'physiotherapy' => self::OPERATING,
        'vaccination' => self::OPERATING,
        'immunization' => self::OPERATING,
        'injection' => self::OPERATING,
        'nursing' => self::OPERATING,
        'general' => self::OPERATING,

        // HMO/Insurance
        'hmo_remittance' => self::OPERATING,
        'hmo_claim' => self::OPERATING,
        'insurance_claim' => self::OPERATING,

        // Inventory & Suppliers
        'inventory_receipt' => self::OPERATING,
        'po_payment' => self::OPERATING,
        'supplier_payment' => self::OPERATING,

        // Payroll & Staff
        'payroll_expense' => self::OPERATING,
        'payroll_payment' => self::OPERATING,
        'salary' => self::OPERATING,
        'wages' => self::OPERATING,
        'bonus' => self::OPERATING,
        'allowance' => self::OPERATING,

        // Operating Expenses
        'general_expense' => self::OPERATING,
        'utilities' => self::OPERATING,
        'electricity' => self::OPERATING,
        'water' => self::OPERATING,
        'internet' => self::OPERATING,
        'telephone' => self::OPERATING,
        'rent' => self::OPERATING,
        'maintenance' => self::OPERATING,
        'repairs' => self::OPERATING,
        'cleaning' => self::OPERATING,
        'security' => self::OPERATING,
        'insurance_expense' => self::OPERATING,
        'advertising' => self::OPERATING,
        'marketing' => self::OPERATING,
        'office_supplies' => self::OPERATING,
        'medical_supplies' => self::OPERATING,
        'travel' => self::OPERATING,
        'training' => self::OPERATING,
        'professional_fees' => self::OPERATING,
        'legal_fees' => self::OPERATING,
        'audit_fees' => self::OPERATING,
        'bank_charges' => self::OPERATING,
        'tax_expense' => self::OPERATING,

        // =============================================
        // INVESTING ACTIVITIES (long-term assets)
        // =============================================
        'equipment_purchase' => self::INVESTING,
        'equipment_sale' => self::INVESTING,
        'asset_purchase' => self::INVESTING,
        'asset_sale' => self::INVESTING,
        'capital_expenditure' => self::INVESTING,
        'building_purchase' => self::INVESTING,
        'vehicle_purchase' => self::INVESTING,
        'furniture_purchase' => self::INVESTING,
        'computer_purchase' => self::INVESTING,
        'software_purchase' => self::INVESTING,
        'investment_purchase' => self::INVESTING,
        'investment_sale' => self::INVESTING,
        'property_purchase' => self::INVESTING,
        'property_sale' => self::INVESTING,
        'land_purchase' => self::INVESTING,

        // =============================================
        // FINANCING ACTIVITIES (capital structure)
        // =============================================
        'loan_receipt' => self::FINANCING,
        'loan_repayment' => self::FINANCING,
        'loan_principal' => self::FINANCING,
        'loan_interest' => self::OPERATING, // Interest is operating per IFRS
        'dividend_payment' => self::FINANCING,
        'dividend_received' => self::INVESTING, // Dividend received is investing
        'equity_injection' => self::FINANCING,
        'capital_contribution' => self::FINANCING,
        'share_issue' => self::FINANCING,
        'share_buyback' => self::FINANCING,
        'owner_withdrawal' => self::FINANCING,
        'owner_drawing' => self::FINANCING,
    ];

    /**
     * Determine cash flow category for a journal entry line
     *
     * @param array $lineData Line data with account_id, category, etc.
     * @param string|null $explicitOverride Override from observer (highest priority)
     * @return string 'operating', 'investing', or 'financing'
     */
    public function classify(array $lineData, ?string $explicitOverride = null): string
    {
        // Priority 1: Explicit override from observer
        if ($explicitOverride && $this->isValidCategory($explicitOverride)) {
            return $explicitOverride;
        }

        // Priority 2: Account's cash_flow_category_override
        if (!empty($lineData['account_id'])) {
            $account = Account::find($lineData['account_id']);

            if ($account?->cash_flow_category_override) {
                return $account->cash_flow_category_override;
            }

            // Priority 3: Account Class's cash_flow_category
            $classCategory = $account?->accountGroup?->accountClass?->cash_flow_category;
            if ($classCategory) {
                return $classCategory;
            }
        }

        // Priority 4: Infer from category field
        if (!empty($lineData['category'])) {
            $category = strtolower(trim($lineData['category']));
            if (isset(self::CASH_FLOW_MAP[$category])) {
                return self::CASH_FLOW_MAP[$category];
            }
        }

        // Priority 5: Default to operating (most common in hospital context)
        return self::OPERATING;
    }

    /**
     * Classify and return the category to be stored on the line
     *
     * Convenience method that observers can use directly
     *
     * @param int $accountId The account ID
     * @param string|null $category The transaction category
     * @param string|null $override Explicit override
     * @return string
     */
    public function classifyForLine(int $accountId, ?string $category = null, ?string $override = null): string
    {
        return $this->classify([
            'account_id' => $accountId,
            'category' => $category,
        ], $override);
    }

    /**
     * Check if a category string is valid
     */
    protected function isValidCategory(?string $category): bool
    {
        return in_array($category, [self::OPERATING, self::INVESTING, self::FINANCING]);
    }

    /**
     * Get all categories that map to a specific cash flow activity
     *
     * @param string $cashFlowActivity 'operating', 'investing', or 'financing'
     * @return array
     */
    public static function getCategoriesForActivity(string $cashFlowActivity): array
    {
        return array_keys(array_filter(
            self::CASH_FLOW_MAP,
            fn($activity) => $activity === $cashFlowActivity
        ));
    }

    /**
     * Determine if a category is typically an operating activity
     */
    public function isOperating(?string $category): bool
    {
        if (!$category) return true; // Default assumption
        return ($self::CASH_FLOW_MAP[$category] ?? self::OPERATING) === self::OPERATING;
    }

    /**
     * Determine if a category is typically an investing activity
     */
    public function isInvesting(?string $category): bool
    {
        if (!$category) return false;
        return ($self::CASH_FLOW_MAP[$category] ?? null) === self::INVESTING;
    }

    /**
     * Determine if a category is typically a financing activity
     */
    public function isFinancing(?string $category): bool
    {
        if (!$category) return false;
        return ($self::CASH_FLOW_MAP[$category] ?? null) === self::FINANCING;
    }
}
