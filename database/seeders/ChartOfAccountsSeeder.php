<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Accounting\AccountClass;
use App\Models\Accounting\AccountGroup;
use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\AccountingPeriod;
use Carbon\Carbon;

/**
 * Chart of Accounts Seeder
 *
 * Reference: Accounting System Plan §11 - Seeders
 *
 * Seeds the standard chart of accounts for a healthcare facility.
 */
class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Account Classes (5 standard classes)
        $classes = $this->createAccountClasses();

        // Create Account Groups for each class
        $groups = $this->createAccountGroups($classes);

        // Create Individual Accounts
        $this->createAccounts($groups);

        // Create initial Fiscal Year and Periods
        $this->createFiscalYear();

        $this->command->info('Chart of Accounts seeded successfully!');
    }

    /**
     * Create the 5 standard account classes.
     */
    protected function createAccountClasses(): array
    {
        $classesData = [
            [
                'code' => '1',
                'name' => 'Assets',
                'normal_balance' => 'debit',
                'display_order' => 1,
                'is_temporary' => false,
            ],
            [
                'code' => '2',
                'name' => 'Liabilities',
                'normal_balance' => 'credit',
                'display_order' => 2,
                'is_temporary' => false,
            ],
            [
                'code' => '3',
                'name' => 'Equity',
                'normal_balance' => 'credit',
                'display_order' => 3,
                'is_temporary' => false,
            ],
            [
                'code' => '4',
                'name' => 'Income',
                'normal_balance' => 'credit',
                'display_order' => 4,
                'is_temporary' => true,
            ],
            [
                'code' => '5',
                'name' => 'Expenses',
                'normal_balance' => 'debit',
                'display_order' => 5,
                'is_temporary' => true,
            ],
        ];

        $classes = [];
        foreach ($classesData as $data) {
            $classes[$data['code']] = AccountClass::firstOrCreate(
                ['code' => $data['code']],
                $data
            );
            $this->command->info("Created account class: {$data['name']}");
        }

        return $classes;
    }

    /**
     * Create account groups for each class.
     */
    protected function createAccountGroups(array $classes): array
    {
        $groupsData = [
            // Asset Groups
            '1' => [
                ['code' => '10', 'name' => 'Current Assets', 'description' => 'Assets expected to be converted to cash within a year', 'display_order' => 1],
                ['code' => '11', 'name' => 'Receivables', 'description' => 'Amounts owed by patients and HMOs', 'display_order' => 2],
                ['code' => '12', 'name' => 'Inventory', 'description' => 'Medical supplies and pharmacy stock', 'display_order' => 3],
                ['code' => '13', 'name' => 'Fixed Assets', 'description' => 'Long-term tangible assets', 'display_order' => 4],
                ['code' => '14', 'name' => 'Accumulated Depreciation', 'description' => 'Contra asset accounts', 'display_order' => 5],
            ],
            // Liability Groups
            '2' => [
                ['code' => '20', 'name' => 'Current Liabilities', 'description' => 'Obligations due within a year', 'display_order' => 1],
                ['code' => '21', 'name' => 'Payables', 'description' => 'Amounts owed to suppliers', 'display_order' => 2],
                ['code' => '22', 'name' => 'Long-term Liabilities', 'description' => 'Obligations due beyond a year', 'display_order' => 3],
            ],
            // Equity Groups
            '3' => [
                ['code' => '30', 'name' => 'Capital', 'description' => 'Owner\'s capital contributions', 'display_order' => 1],
                ['code' => '31', 'name' => 'Retained Earnings', 'description' => 'Accumulated profits/losses', 'display_order' => 2],
            ],
            // Income Groups
            '4' => [
                ['code' => '40', 'name' => 'Service Revenue', 'description' => 'Income from healthcare services', 'display_order' => 1],
                ['code' => '41', 'name' => 'Product Revenue', 'description' => 'Income from pharmacy sales', 'display_order' => 2],
                ['code' => '42', 'name' => 'Other Income', 'description' => 'Non-operating income', 'display_order' => 3],
            ],
            // Expense Groups
            '5' => [
                ['code' => '50', 'name' => 'Cost of Goods Sold', 'description' => 'Direct costs of products sold', 'display_order' => 1],
                ['code' => '51', 'name' => 'Personnel Expenses', 'description' => 'Salaries, wages, and benefits', 'display_order' => 2],
                ['code' => '52', 'name' => 'Operating Expenses', 'description' => 'General operating costs', 'display_order' => 3],
                ['code' => '53', 'name' => 'Administrative Expenses', 'description' => 'Administrative and office costs', 'display_order' => 4],
                ['code' => '54', 'name' => 'Financial Expenses', 'description' => 'Interest and bank charges', 'display_order' => 5],
            ],
        ];

        $groups = [];
        foreach ($groupsData as $classCode => $classGroups) {
            foreach ($classGroups as $groupData) {
                $groups[$groupData['code']] = AccountGroup::firstOrCreate(
                    ['code' => $groupData['code']],
                    array_merge($groupData, ['account_class_id' => $classes[$classCode]->id])
                );
                $this->command->info("Created account group: {$groupData['name']}");
            }
        }

        return $groups;
    }

    /**
     * Create individual accounts.
     */
    protected function createAccounts(array $groups): void
    {
        $accountsData = [
            // Current Assets (Group 10)
            '10' => [
                ['code' => '1010', 'name' => 'Cash in Hand'],
                ['code' => '1020', 'name' => 'Bank Account', 'is_bank_account' => true],
                ['code' => '1025', 'name' => 'Cheques Receivable'],
                ['code' => '1030', 'name' => 'Petty Cash'],
            ],
            // Receivables (Group 11)
            '11' => [
                ['code' => '1100', 'name' => 'Accounts Receivable - Patients'],
                ['code' => '1110', 'name' => 'Accounts Receivable - HMO'],
                ['code' => '1120', 'name' => 'Accounts Receivable - Corporate'],
                ['code' => '1190', 'name' => 'Allowance for Doubtful Debts'],
                ['code' => '1200', 'name' => 'Accounts Receivable', 'description' => 'General AR control account'],
            ],
            // Inventory (Group 12)
            '12' => [
                ['code' => '1300', 'name' => 'Inventory - Pharmacy'],
                ['code' => '1310', 'name' => 'Inventory - Medical Supplies'],
                ['code' => '1320', 'name' => 'Inventory - Laboratory'],
            ],
            // Fixed Assets (Group 13)
            '13' => [
                ['code' => '1400', 'name' => 'Medical Equipment'],
                ['code' => '1410', 'name' => 'Furniture & Fixtures'],
                ['code' => '1420', 'name' => 'Computer Equipment'],
                ['code' => '1430', 'name' => 'Vehicles'],
                ['code' => '1440', 'name' => 'Building'],
                ['code' => '1450', 'name' => 'Land'],
                ['code' => '1460', 'name' => 'Other Fixed Assets', 'description' => 'General fixed assets not classified elsewhere'],
            ],
            // Accumulated Depreciation (Group 14)
            '14' => [
                ['code' => '1500', 'name' => 'Accumulated Depreciation - Medical Equipment'],
                ['code' => '1510', 'name' => 'Accumulated Depreciation - Furniture'],
                ['code' => '1520', 'name' => 'Accumulated Depreciation - Computers'],
                ['code' => '1530', 'name' => 'Accumulated Depreciation - Vehicles'],
                ['code' => '1540', 'name' => 'Accumulated Depreciation - Building'],
            ],
            // Current Liabilities (Group 20)
            '20' => [
                ['code' => '2000', 'name' => 'Short-term Loans'],
                ['code' => '2010', 'name' => 'Accrued Expenses'],
                ['code' => '2020', 'name' => 'Unearned Revenue'],
                ['code' => '2030', 'name' => 'Tax Payable'],
                ['code' => '2040', 'name' => 'Pension Payable'],
            ],
            // Payables (Group 21)
            '21' => [
                ['code' => '2100', 'name' => 'Accounts Payable'],
                ['code' => '2110', 'name' => 'Accounts Payable - Suppliers'],
                ['code' => '2200', 'name' => 'Customer Deposits', 'description' => 'Patient wallet credits'],
            ],
            // Long-term Liabilities (Group 22)
            '22' => [
                ['code' => '2300', 'name' => 'Long-term Loans'],
                ['code' => '2310', 'name' => 'Lease Obligations'],
            ],
            // Capital (Group 30)
            '30' => [
                ['code' => '3000', 'name' => 'Owner\'s Capital'],
                ['code' => '3010', 'name' => 'Share Capital'],
                ['code' => '3020', 'name' => 'Additional Paid-in Capital'],
            ],
            // Retained Earnings (Group 31)
            '31' => [
                ['code' => '3100', 'name' => 'Retained Earnings'],
                ['code' => '3110', 'name' => 'Current Year Earnings'],
            ],
            // Service Revenue (Group 40)
            '40' => [
                ['code' => '4000', 'name' => 'Revenue', 'description' => 'General revenue control'],
                ['code' => '4010', 'name' => 'Consultation Revenue'],
                ['code' => '4020', 'name' => 'Pharmacy Revenue'],
                ['code' => '4030', 'name' => 'Laboratory Revenue'],
                ['code' => '4040', 'name' => 'Imaging Revenue'],
                ['code' => '4050', 'name' => 'Procedure Revenue'],
                ['code' => '4060', 'name' => 'Admission Revenue'],
                ['code' => '4070', 'name' => 'Theatre Revenue'],
                ['code' => '4080', 'name' => 'Emergency Revenue'],
            ],
            // Product Revenue (Group 41)
            '41' => [
                ['code' => '4100', 'name' => 'Retail Sales'],
            ],
            // Other Income (Group 42)
            '42' => [
                ['code' => '4200', 'name' => 'Interest Income'],
                ['code' => '4210', 'name' => 'Other Income'],
                ['code' => '4220', 'name' => 'Discount Received'],
            ],
            // Cost of Goods Sold (Group 50)
            '50' => [
                ['code' => '5000', 'name' => 'Cost of Goods Sold'],
                ['code' => '5010', 'name' => 'Cost of Pharmacy Sales'],
                ['code' => '5020', 'name' => 'Cost of Medical Supplies Used'],
                ['code' => '5030', 'name' => 'Damaged Goods Write-off', 'description' => 'Damaged inventory written off'],
                ['code' => '5040', 'name' => 'Expired Stock Write-off', 'description' => 'Expired inventory written off'],
                ['code' => '5050', 'name' => 'Theft/Shrinkage', 'description' => 'Inventory loss due to theft or shrinkage'],
                ['code' => '5060', 'name' => 'Loss on Returns', 'description' => 'Non-restockable returns loss'],
            ],
            // Personnel Expenses (Group 51)
            '51' => [
                ['code' => '6000', 'name' => 'Salaries & Wages'],
                ['code' => '6010', 'name' => 'Staff Benefits'],
                ['code' => '6020', 'name' => 'Pension Contributions'],
                ['code' => '6030', 'name' => 'Medical Allowance'],
                ['code' => '6040', 'name' => 'Salaries & Wages Expense', 'description' => 'For automated payroll entries'],
            ],
            // Operating Expenses (Group 52)
            '52' => [
                ['code' => '6100', 'name' => 'Rent Expense'],
                ['code' => '6110', 'name' => 'Utilities Expense'],
                ['code' => '6120', 'name' => 'Repairs & Maintenance'],
                ['code' => '6130', 'name' => 'Insurance Expense'],
                ['code' => '6140', 'name' => 'Security Expense'],
                ['code' => '6150', 'name' => 'Cleaning & Sanitation'],
            ],
            // Administrative Expenses (Group 53)
            '53' => [
                ['code' => '6200', 'name' => 'Office Supplies'],
                ['code' => '6210', 'name' => 'Telephone & Internet'],
                ['code' => '6220', 'name' => 'Professional Fees'],
                ['code' => '6230', 'name' => 'Training & Development'],
                ['code' => '6240', 'name' => 'Travel & Transportation'],
                ['code' => '6250', 'name' => 'Advertising & Marketing'],
                ['code' => '6260', 'name' => 'Depreciation Expense'],
                ['code' => '6270', 'name' => 'Cash Over/Short', 'description' => 'Petty cash variances from reconciliation'],
                ['code' => '6090', 'name' => 'Miscellaneous Expenses'],
            ],
            // Financial Expenses (Group 54)
            '54' => [
                ['code' => '6300', 'name' => 'Interest Expense'],
                ['code' => '6310', 'name' => 'Bank Charges'],
                ['code' => '6320', 'name' => 'Bad Debt Expense'],
            ],
        ];

        foreach ($accountsData as $groupCode => $accounts) {
            foreach ($accounts as $accountData) {
                Account::firstOrCreate(
                    ['code' => $accountData['code']],
                    array_merge([
                        'account_group_id' => $groups[$groupCode]->id,
                        'is_active' => true,
                        'is_bank_account' => false,
                        'is_system' => false,
                    ], $accountData)
                );
                $this->command->info("Created account: {$accountData['code']} - {$accountData['name']}");
            }
        }
    }

    /**
     * Create initial fiscal year and monthly periods.
     */
    protected function createFiscalYear(): void
    {
        $currentYear = now()->year;
        $startDate = Carbon::create($currentYear, 1, 1);
        $endDate = Carbon::create($currentYear, 12, 31);

        $fiscalYear = FiscalYear::firstOrCreate(
            ['year_name' => "FY {$currentYear}"],
            [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'open',
            ]
        );

        $this->command->info("Created fiscal year: FY {$currentYear}");

        // Create monthly periods
        for ($month = 1; $month <= 12; $month++) {
            $periodStart = Carbon::create($currentYear, $month, 1);
            $periodEnd = $periodStart->copy()->endOfMonth();

            AccountingPeriod::firstOrCreate(
                [
                    'fiscal_year_id' => $fiscalYear->id,
                    'period_number' => $month,
                ],
                [
                    'period_name' => $periodStart->format('F Y'),
                    'start_date' => $periodStart,
                    'end_date' => $periodEnd,
                    'status' => 'open',
                    'is_adjustment_period' => false,
                ]
            );
            $this->command->info("Created period: {$periodStart->format('F Y')}");
        }

        $this->command->info('Fiscal year and periods seeded successfully!');
    }
}
