# Accounting System UI/UX Implementation Plan
**Date:** January 31, 2026  
**System:** CoreHealth v2 - Complete Module Implementation

---

## Executive Summary

This document provides a comprehensive implementation plan for the UI/UX, Controllers, and Routes for the newly created accounting tables. All implementations will:

1. **Follow existing patterns** from `accounting/dashboard.blade.php`, `journal-entries/index.blade.php`
2. **Use consistent navigation** with `accounting.partials.breadcrumb`
3. **Extend admin layout** with `@extends('admin.layouts.app')`
4. **Export via PDF/Excel** using existing `ExcelExportService` and PDF layout patterns
5. **Use AJAX + DataTables** for all list views
6. **Include comprehensive stats** at top of each view
7. **Implement advanced filters** on all data tables
8. **Integrate with accounting dashboard** as navigation cards

---

## Table of Contents

1. [Petty Cash Module](#1-petty-cash-module)
2. [Inter-Account Transfers Module](#2-inter-account-transfers-module)
3. [Bank Reconciliation Module](#3-bank-reconciliation-module)
4. [Patient Deposits Module](#4-patient-deposits-module)
5. [Cash Flow Forecasting Module](#5-cash-flow-forecasting-module)
6. [Fixed Assets Module](#6-fixed-assets-module)
7. [Liabilities & Leases Module](#7-liabilities--leases-module)
8. [Cost Center Module](#8-cost-center-module)
9. [CAPEX Projects Module](#9-capex-projects-module)
10. [Budget Module](#10-budget-module)
11. [Financial KPI Dashboard](#11-financial-kpi-dashboard)
12. [Dashboard Integration](#12-dashboard-integration)
13. [Route Definitions](#13-route-definitions)
14. [Permission Setup](#14-permission-setup)

---

## Database Tables Verified ✓

| Table | Columns Count | Status |
|-------|--------------|--------|
| petty_cash_funds | 16 | ✓ |
| petty_cash_transactions | 25 | ✓ |
| petty_cash_reconciliations | 18 | ✓ |
| inter_account_transfers | 30 | ✓ |
| bank_reconciliations | 32 | ✓ |
| bank_reconciliation_items | 16 | ✓ |
| bank_statement_imports | 16 | ✓ |
| patient_deposits | 26 | ✓ |
| patient_deposit_applications | 14 | ✓ |
| cash_flow_forecasts | 15 | ✓ |
| cash_flow_forecast_periods | 14 | ✓ |
| cash_flow_forecast_items | 12 | ✓ |
| cash_flow_recurring_patterns | 17 | ✓ |
| fixed_asset_categories | 12 | ✓ |
| fixed_assets | 41 | ✓ |
| fixed_asset_depreciations | 18 | ✓ |
| fixed_asset_disposals | 22 | ✓ |
| fixed_asset_transfers | 14 | ✓ |
| equipment_maintenance_schedules | 21 | ✓ |
| liability_schedules | 26 | ✓ |
| liability_payment_schedules | 12 | ✓ |
| leases | 41 | ✓ |
| lease_payment_schedules | 14 | ✓ |
| lease_modifications | 16 | ✓ |
| cost_centers | 13 | ✓ |
| cost_center_budgets | 11 | ✓ |
| cost_center_allocations | 10 | ✓ |
| cost_allocation_runs | 11 | ✓ |
| cost_allocation_details | 8 | ✓ |
| capex_projects | 31 | ✓ |
| capex_project_expenses | 13 | ✓ |
| budgets | 18 | ✓ |
| budget_lines | 15 | ✓ |
| budget_revisions | 10 | ✓ |
| financial_kpis | 18 | ✓ |
| financial_kpi_values | 9 | ✓ |
| financial_kpi_alerts | 11 | ✓ |
| dashboard_configs | 10 | ✓ |

---

## 1. PETTY CASH MODULE

### 1.1 Table Columns Reference

**petty_cash_funds:**
- id, fund_name, fund_code, account_id, custodian_user_id, department_id
- fund_limit, transaction_limit, current_balance, requires_approval
- approval_threshold, status, notes, created_at, updated_at, deleted_at

**petty_cash_transactions:**
- id, fund_id, transaction_number, transaction_type (disbursement/replenishment/adjustment)
- transaction_date, amount, description, payee_name, receipt_number
- category_id, expense_account_id, journal_entry_id
- status (pending/approved/rejected/voided), requested_by, approved_by
- approved_at, notes, attachments, created_at, updated_at, deleted_at

### 1.2 Controller Implementation

**File:** `app/Http/Controllers/Accounting/PettyCashController.php`

```php
class PettyCashController extends Controller
{
    // Constructor with permissions
    // - petty-cash.view
    // - petty-cash.funds.create
    // - petty-cash.funds.edit
    // - petty-cash.transactions.create
    // - petty-cash.transactions.approve
    // - petty-cash.reconcile
    
    // Methods:
    // 1. index() - Dashboard with stats + DataTable
    // 2. fundsIndex() - List all funds
    // 3. fundsDatatable() - AJAX DataTable for funds
    // 4. fundsCreate() - Create fund form
    // 5. fundsStore() - Store new fund
    // 6. fundsEdit($id) - Edit fund form
    // 7. fundsUpdate($id) - Update fund
    // 8. transactionsIndex($fundId) - Transactions for a fund
    // 9. transactionsDatatable($fundId) - AJAX DataTable
    // 10. disbursementCreate($fundId) - New disbursement form
    // 11. disbursementStore($fundId) - Process disbursement
    // 12. replenishmentCreate($fundId) - Replenishment form
    // 13. replenishmentStore($fundId) - Process replenishment
    // 14. approve($transactionId) - Approve transaction
    // 15. reject($transactionId) - Reject with reason
    // 16. reconcile($fundId) - Reconciliation form
    // 17. storeReconciliation($fundId) - Store reconciliation
    // 18. exportPdf($fundId) - Export fund report
    // 19. exportExcel($fundId) - Export to Excel
```

### 1.3 Views Structure

```
resources/views/accounting/petty-cash/
├── index.blade.php          # Dashboard with all funds overview
├── funds/
│   ├── index.blade.php      # Funds list with DataTable
│   ├── create.blade.php     # Create fund form
│   ├── edit.blade.php       # Edit fund form
│   └── show.blade.php       # Fund details + transactions
├── transactions/
│   ├── index.blade.php      # Transactions list (filterable)
│   ├── disbursement.blade.php  # Disbursement form
│   ├── replenishment.blade.php # Replenishment form
│   └── show.blade.php       # Transaction details + JE link
├── reconciliations/
│   ├── index.blade.php      # Reconciliation history
│   └── create.blade.php     # New reconciliation
├── reports/
│   └── fund-report.blade.php # Fund activity report
└── partials/
    ├── fund-card.blade.php  # Fund summary card
    ├── stats-row.blade.php  # Stats cards row
    └── filters.blade.php    # Advanced filters
```

### 1.4 Dashboard Stats (index.blade.php)

```
┌─────────────────┬──────────────────┬─────────────────┬─────────────────┐
│ Total Funds     │ Total Balance    │ Today's Trans.  │ Pending Approvals│
│ 5 Active        │ ₦2,450,000       │ 12 transactions │ 3 awaiting      │
└─────────────────┴──────────────────┴─────────────────┴─────────────────┘
┌─────────────────┬──────────────────┬─────────────────┬─────────────────┐
│ This Month      │ Disbursements    │ Replenishments  │ Low Balance     │
│ ₦850,000 used   │ ₦780,000         │ ₦400,000        │ 2 funds below   │
└─────────────────┴──────────────────┴─────────────────┴─────────────────┘
```

### 1.5 Advanced Filters

- Fund: [Select Fund]
- Date Range: [From] - [To]
- Transaction Type: [All/Disbursement/Replenishment/Adjustment]
- Status: [All/Pending/Approved/Rejected/Voided]
- Category: [Select Expense Category]
- Amount Range: [Min] - [Max]
- Custodian: [Select User]

---

## 2. INTER-ACCOUNT TRANSFERS MODULE

### 2.1 Table Columns Reference

**inter_account_transfers:**
- id, transfer_number, from_bank_id, to_bank_id, from_account_id, to_account_id
- journal_entry_id, transfer_date, amount, reference, description
- transfer_method (internal/electronic/cheque), is_same_bank
- expected_clearance_date, actual_clearance_date, transfer_fee, fee_account_id
- status (pending/approved/in_transit/cleared/failed/cancelled)
- initiated_by, approved_by, approved_at, initiated_at, cleared_at
- failure_reason, cancelled_by, cancelled_at, notes

### 2.2 Controller Implementation

**File:** `app/Http/Controllers/Accounting/TransferController.php`

```php
class TransferController extends Controller
{
    // Permissions:
    // - transfers.view
    // - transfers.create
    // - transfers.approve
    // - transfers.confirm-clearance
    
    // Methods:
    // 1. index() - Transfers list with stats
    // 2. datatable() - AJAX DataTable
    // 3. create() - New transfer form
    // 4. store() - Process transfer request
    // 5. show($id) - Transfer details
    // 6. approve($id) - Approve transfer
    // 7. reject($id) - Reject with reason
    // 8. confirmClearance($id) - Mark as cleared
    // 9. cancel($id) - Cancel pending transfer
    // 10. exportPdf() - Export transfers report
    // 11. exportExcel() - Export to Excel
```

### 2.3 Views Structure

```
resources/views/accounting/transfers/
├── index.blade.php          # List with stats + DataTable
├── create.blade.php         # New transfer form
├── show.blade.php           # Transfer details + JE link
└── partials/
    ├── stats-row.blade.php  # Stats cards
    ├── filters.blade.php    # Advanced filters
    └── transfer-card.blade.php # Transfer summary
```

### 2.4 Dashboard Stats

```
┌─────────────────┬──────────────────┬─────────────────┬─────────────────┐
│ Total Transfers │ This Month       │ In Transit      │ Pending Approval│
│ 156 total       │ ₦45,000,000      │ ₦5,200,000      │ 4 awaiting      │
└─────────────────┴──────────────────┴─────────────────┴─────────────────┘
┌─────────────────┬──────────────────┬─────────────────┬─────────────────┐
│ Cleared Today   │ Transfer Fees    │ Failed          │ Avg. Clearance  │
│ ₦12,500,000     │ ₦45,600          │ 2 this month    │ 1.5 days        │
└─────────────────┴──────────────────┴─────────────────┴─────────────────┘
```

### 2.5 Advanced Filters

- From Bank: [Select Bank]
- To Bank: [Select Bank]
- Date Range: [From] - [To]
- Status: [All/Pending/Approved/In Transit/Cleared/Failed/Cancelled]
- Transfer Method: [All/Internal/Electronic/Cheque]
- Amount Range: [Min] - [Max]
- Initiated By: [Select User]

---

## 3. BANK RECONCILIATION MODULE

### 3.1 Table Columns Reference

**bank_reconciliations:**
- id, bank_id, account_id, fiscal_period_id, reconciliation_number
- statement_date, statement_period_from, statement_period_to
- statement_opening_balance, statement_closing_balance
- gl_opening_balance, gl_closing_balance
- outstanding_deposits, outstanding_checks, deposits_in_transit
- unrecorded_charges, unrecorded_credits, bank_errors, book_errors
- variance, status (draft/in_progress/reconciled/discrepancy/approved)
- adjustment_entry_ids (JSON), notes, prepared_by, reviewed_by
- reviewed_at, approved_by, approved_at, finalized_at

**bank_reconciliation_items:**
- id, reconciliation_id, journal_entry_line_id, item_type
- transaction_date, bank_reference, gl_reference, description
- bank_amount, gl_amount, match_status (matched/unmatched/partial)
- matched_at, notes

### 3.2 Controller Implementation

**File:** `app/Http/Controllers/Accounting/BankReconciliationController.php`

```php
class BankReconciliationController extends Controller
{
    // Permissions:
    // - bank-reconciliation.view
    // - bank-reconciliation.create
    // - bank-reconciliation.import
    // - bank-reconciliation.match
    // - bank-reconciliation.approve
    
    // Methods:
    // 1. index() - All reconciliations list
    // 2. datatable() - AJAX DataTable
    // 3. create($bankId) - Start new reconciliation
    // 4. store($bankId) - Create reconciliation record
    // 5. show($id) - Reconciliation workspace
    // 6. importStatement($id) - Upload CSV/Excel statement
    // 7. parseStatement(Request) - AJAX parse uploaded file
    // 8. autoMatch($id) - Auto-match transactions
    // 9. manualMatch(Request) - Manual match selection
    // 10. unmatch($itemId) - Unmatch item
    // 11. addAdjustment($id) - Create adjustment JE
    // 12. finalize($id) - Complete reconciliation
    // 13. approve($id) - Approve reconciliation
    // 14. exportPdf($id) - Export reconciliation report
    // 15. exportExcel($id) - Export to Excel
```

### 3.3 Views Structure

```
resources/views/accounting/bank-reconciliation/
├── index.blade.php              # Reconciliations list
├── create.blade.php             # Start reconciliation
├── workspace.blade.php          # Main reconciliation UI
├── import.blade.php             # Statement import
├── partials/
│   ├── stats-row.blade.php      # Summary stats
│   ├── gl-transactions.blade.php # GL side transactions
│   ├── bank-transactions.blade.php # Bank side transactions
│   ├── matched-items.blade.php  # Already matched
│   ├── outstanding-items.blade.php # Outstanding
│   └── adjustment-modal.blade.php # Create adjustment
└── reports/
    └── reconciliation-report.blade.php
```

### 3.4 Reconciliation Workspace UI

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Bank Reconciliation: Zenith Bank - Jan 2026         [Import Statement] │
├─────────────────────────────────────────────────────────────────────────┤
│  Statement Balance: ₦5,234,890.00  │  GL Balance: ₦5,245,390.00        │
│  Variance: ₦10,500.00 (Need to reconcile)                              │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌──────────────────────────┐    ┌──────────────────────────┐         │
│  │   GL TRANSACTIONS        │    │   BANK STATEMENT         │         │
│  │   (Unmatched: 15)        │    │   (Unmatched: 12)        │         │
│  ├──────────────────────────┤    ├──────────────────────────┤         │
│  │ ☐ Jan 5  +₦50,000   ref1 │◄──►│ ☐ Jan 5  +₦50,000  CHQ  │         │
│  │ ☐ Jan 8  -₦25,000   ref2 │    │ ☐ Jan 8  -₦25,000  TRF  │         │
│  │ ☐ Jan 10 +₦100,000  ref3 │    │ ☐ Jan 12 -₦500    CHG   │ ← Charge│
│  │ ☐ Jan 15 -₦75,000   ref4 │    │ ☐ Jan 15 -₦75,000  DBT  │         │
│  └──────────────────────────┘    └──────────────────────────┘         │
│                                                                         │
│  [Auto Match] [Match Selected] [Add Adjustment Entry]                   │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│  Matched Items: 45  │  Outstanding Deposits: 3  │  Outstanding Checks: 2│
│  Adjustments Needed: ₦10,500                                            │
├─────────────────────────────────────────────────────────────────────────┤
│  [Save Progress]  [Finalize Reconciliation]  [Export PDF]               │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 4. PATIENT DEPOSITS MODULE

### 4.1 Table Columns Reference

**patient_deposits:**
- id, patient_id, admission_id, encounter_id, deposit_number, deposit_date
- amount, utilized_amount, refunded_amount, balance (virtual)
- journal_entry_id, deposit_type (admission/procedure/surgery/general)
- payment_method, bank_id, payment_reference, receipt_number
- received_by, status (active/fully_applied/refunded/expired/cancelled)
- refund_journal_entry_id, refund_reason, refunded_by, refunded_at, notes

**patient_deposit_applications:**
- id, deposit_id, payment_id, invoice_id, encounter_id
- applied_amount, applied_date, applied_by, notes

### 4.2 Controller Implementation

**File:** `app/Http/Controllers/Accounting/PatientDepositController.php`

```php
class PatientDepositController extends Controller
{
    // Permissions:
    // - patient-deposits.view
    // - patient-deposits.create
    // - patient-deposits.apply
    // - patient-deposits.refund
    
    // Methods:
    // 1. index() - Deposits list with stats
    // 2. datatable() - AJAX DataTable
    // 3. create() - New deposit form (search patient first)
    // 4. store() - Record deposit
    // 5. show($id) - Deposit details + applications
    // 6. apply($id) - Apply to payment/invoice
    // 7. storeApplication($id) - Process application
    // 8. refund($id) - Refund form
    // 9. processRefund($id) - Process refund
    // 10. patientDeposits($patientId) - Patient's deposits (AJAX)
    // 11. exportPdf() - Export deposits report
    // 12. exportExcel() - Export to Excel
    // 13. printReceipt($id) - Print deposit receipt
```

### 4.3 Views Structure

```
resources/views/accounting/patient-deposits/
├── index.blade.php          # Deposits list with stats
├── create.blade.php         # New deposit form
├── show.blade.php           # Deposit details + applications
├── apply.blade.php          # Apply deposit to bill
├── refund.blade.php         # Refund form
├── partials/
│   ├── stats-row.blade.php  # Summary stats
│   ├── filters.blade.php    # Advanced filters
│   ├── patient-search.blade.php # Patient search modal
│   └── applications-table.blade.php # Applications list
└── reports/
    └── deposits-report.blade.php
```

### 4.4 Dashboard Stats

```
┌─────────────────┬──────────────────┬─────────────────┬─────────────────┐
│ Total Active    │ Total Balance    │ Today's Deposits│ Applied Today   │
│ 234 deposits    │ ₦8,450,000       │ ₦1,250,000      │ ₦890,000        │
└─────────────────┴──────────────────┴─────────────────┴─────────────────┘
┌─────────────────┬──────────────────┬─────────────────┬─────────────────┐
│ This Month      │ Refunds          │ Expired         │ Pending Refunds │
│ ₦15,200,000     │ ₦420,000         │ ₦50,000         │ 5 requests      │
└─────────────────┴──────────────────┴─────────────────┴─────────────────┘
```

---

## 5. CASH FLOW FORECASTING MODULE

### 5.1 Table Columns Reference

**cash_flow_forecasts:**
- id, forecast_name, forecast_code, forecast_type (short/medium/long_term)
- period_type (weekly/monthly/quarterly), start_date, end_date
- base_cash_balance, notes, status (draft/active/archived)
- created_by

**cash_flow_forecast_periods:**
- id, forecast_id, period_number, period_start_date, period_end_date
- opening_balance, projected_inflows, projected_outflows
- projected_closing_balance, actual_inflows, actual_outflows
- actual_closing_balance, variance_explanation, is_locked

**cash_flow_forecast_items:**
- id, forecast_period_id, account_id, cash_flow_category
- item_description, forecasted_amount, actual_amount
- source_type (manual/recurring/scheduled/historical/commitment)
- source_reference, notes

### 5.2 Controller Implementation

**File:** `app/Http/Controllers/Accounting/CashFlowForecastController.php`

```php
class CashFlowForecastController extends Controller
{
    // Methods:
    // 1. index() - Forecasts list
    // 2. datatable() - AJAX DataTable
    // 3. create() - New forecast wizard
    // 4. store() - Create forecast + periods
    // 5. show($id) - Forecast view with periods
    // 6. editPeriod($periodId) - Edit period items
    // 7. updatePeriod($periodId) - Save period items
    // 8. addItem($periodId) - Add forecast item
    // 9. updateActuals($periodId) - Record actuals
    // 10. patterns() - Recurring patterns management
    // 11. exportPdf($id) - Export forecast
    // 12. exportExcel($id) - Export to Excel
```

### 5.3 Views Structure

```
resources/views/accounting/cash-flow-forecast/
├── index.blade.php          # Forecasts list
├── create.blade.php         # New forecast wizard
├── show.blade.php           # Forecast view with chart
├── period-edit.blade.php    # Edit period items
├── patterns/
│   ├── index.blade.php      # Recurring patterns
│   └── create.blade.php     # New pattern
└── partials/
    ├── stats-row.blade.php
    ├── forecast-chart.blade.php  # Chart.js visualization
    └── period-card.blade.php
```

### 5.4 Forecast Visualization

```
Cash Flow Forecast: Q1 2026
┌─────────────────────────────────────────────────────────────────────────┐
│  ▲                                                                      │
│  │    ████                     ████                                     │
│  │    ████    ████            ████                                      │
│  │    ████    ████   ████    ████    ████                              │
│  │────████────████───████────████────████──────────────────────────────│
│  │                    ████            ████   ████                       │
│  │                    ████                   ████                       │
│  ▼                                                                      │
│       Jan      Feb     Mar     Apr     May     Jun                      │
│                                                                         │
│  ■ Projected Inflows  ■ Projected Outflows  ─ Net Cash Position        │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 6. FIXED ASSETS MODULE

### 6.1 Table Columns Reference

**fixed_asset_categories:**
- id, code, name, description, depreciation_method, useful_life_years
- salvage_value_percentage, asset_account_id, accumulated_depreciation_account_id
- depreciation_expense_account_id, is_active

**fixed_assets:**
- id, asset_number, name, description, category_id, account_id, journal_entry_id
- source_type (purchase_order/manual/donation/transfer), source_id
- acquisition_cost, additional_costs, total_cost, salvage_value
- depreciable_amount, accumulated_depreciation, book_value
- depreciation_method, useful_life_years, useful_life_months
- monthly_depreciation, acquisition_date, in_service_date
- last_depreciation_date, disposal_date, serial_number, model_number
- manufacturer, location, department_id, custodian_user_id
- warranty_expiry_date, warranty_provider, insurance_policy_number
- insurance_expiry_date, supplier_id, invoice_number
- status (active/inactive/disposed/under_maintenance), notes

**fixed_asset_depreciations:**
- id, fixed_asset_id, journal_entry_id, depreciation_date
- year_number, month_number, depreciation_amount
- accumulated_depreciation_before, accumulated_depreciation_after
- book_value_before, book_value_after, calculation_method
- notes, processed_by

**fixed_asset_disposals:**
- id, fixed_asset_id, disposal_number, disposal_type
- disposal_date, net_book_value_at_disposal, disposal_proceeds
- gain_loss, journal_entry_id, disposal_reason, buyer_name
- buyer_contact, disposal_method, authorization_reference
- authorized_by, status (pending/approved/completed/cancelled)
- processed_by, approved_by, approved_at, notes

### 6.2 Controller Implementation

**File:** `app/Http/Controllers/Accounting/FixedAssetController.php`

```php
class FixedAssetController extends Controller
{
    // Permissions:
    // - fixed-assets.view
    // - fixed-assets.create
    // - fixed-assets.edit
    // - fixed-assets.depreciate
    // - fixed-assets.dispose
    // - fixed-assets.transfer
    
    // Methods:
    // 1. index() - Asset register with stats
    // 2. datatable() - AJAX DataTable
    // 3. create() - New asset form
    // 4. store() - Register asset
    // 5. show($id) - Asset details + depreciation history
    // 6. edit($id) - Edit asset
    // 7. update($id) - Update asset
    // 8. categories() - Manage categories
    // 9. categoryStore() - Create category
    // 10. categoryUpdate($id) - Update category
    // 11. runDepreciation() - Run monthly depreciation
    // 12. depreciationPreview() - Preview before running
    // 13. dispose($id) - Disposal form
    // 14. processDisposal($id) - Process disposal
    // 15. transfer($id) - Transfer form
    // 16. processTransfer($id) - Process transfer
    // 17. maintenanceSchedule($id) - Maintenance schedules
    // 18. exportPdf() - Export asset register
    // 19. exportExcel() - Export to Excel
    // 20. depreciationReport() - Depreciation schedule report
```

### 6.3 Views Structure

```
resources/views/accounting/fixed-assets/
├── index.blade.php              # Asset register with stats
├── create.blade.php             # New asset form
├── show.blade.php               # Asset details
├── edit.blade.php               # Edit asset
├── categories/
│   ├── index.blade.php          # Categories list
│   └── create.blade.php         # New category
├── depreciation/
│   ├── index.blade.php          # Depreciation schedule
│   ├── run.blade.php            # Run depreciation
│   └── history.blade.php        # Depreciation history
├── disposal/
│   ├── index.blade.php          # Disposals list
│   ├── create.blade.php         # New disposal
│   └── show.blade.php           # Disposal details
├── transfers/
│   ├── index.blade.php          # Transfers list
│   └── create.blade.php         # New transfer
├── maintenance/
│   ├── index.blade.php          # Maintenance schedules
│   └── create.blade.php         # New schedule
├── reports/
│   ├── register.blade.php       # Full asset register
│   └── depreciation-schedule.blade.php
└── partials/
    ├── stats-row.blade.php
    ├── filters.blade.php
    ├── asset-card.blade.php
    └── depreciation-chart.blade.php
```

### 6.4 Dashboard Stats

```
┌─────────────────┬──────────────────┬─────────────────┬─────────────────┐
│ Total Assets    │ Total Cost       │ Net Book Value  │ Monthly Depr.   │
│ 456 active      │ ₦125,000,000     │ ₦89,500,000     │ ₦1,850,000      │
└─────────────────┴──────────────────┴─────────────────┴─────────────────┘
┌─────────────────┬──────────────────┬─────────────────┬─────────────────┐
│ YTD Depreciation│ Disposals YTD    │ Under Maint.    │ Warranty Expiring│
│ ₦22,200,000     │ 12 assets        │ 8 assets        │ 15 in 30 days   │
└─────────────────┴──────────────────┴─────────────────┴─────────────────┘
```

### 6.5 Advanced Filters

- Category: [Select Category]
- Department: [Select Department]
- Location: [Text input]
- Status: [Active/Inactive/Disposed/Under Maintenance]
- Acquisition Date: [From] - [To]
- Cost Range: [Min] - [Max]
- Book Value Range: [Min] - [Max]
- Depreciation Method: [Straight Line/Declining Balance/etc.]
- Custodian: [Select User]

---

## 7. LIABILITIES & LEASES MODULE

### 7.1 Table Columns Reference

**liability_schedules:**
- id, liability_number, liability_type (loan/mortgage/bond/deferred_revenue/other)
- description, creditor_name, creditor_contact
- liability_account_id, interest_account_id, principal_account_id
- original_amount, current_balance, interest_rate, interest_type
- start_date, maturity_date, term_months
- payment_frequency, payment_amount, next_payment_date
- status, notes, created_by

**leases (IFRS 16):**
- id, lease_number, lease_type (operating/finance), leased_item, description
- lessor_id, lessor_name, lessor_contact
- rou_asset_account_id, lease_liability_account_id
- depreciation_account_id, interest_account_id
- commencement_date, end_date, lease_term_months
- monthly_payment, annual_rent_increase_rate, incremental_borrowing_rate
- total_lease_payments, initial_rou_asset_value, initial_lease_liability
- current_rou_asset_value, accumulated_rou_depreciation, current_lease_liability
- initial_direct_costs, lease_incentives_received
- has_purchase_option, purchase_option_amount, purchase_option_reasonably_certain
- has_termination_option, earliest_termination_date, termination_penalty
- residual_value_guarantee, asset_location, department_id, status, notes

### 7.2 Controller Implementation

**File:** `app/Http/Controllers/Accounting/LiabilityController.php`

```php
class LiabilityController extends Controller
{
    // Liabilities Methods:
    // 1. index() - Liabilities list with stats
    // 2. datatable() - AJAX DataTable
    // 3. create() - New liability form
    // 4. store() - Create liability + amortization schedule
    // 5. show($id) - Liability details + payment schedule
    // 6. recordPayment($id) - Record payment
    // 7. amortizationSchedule($id) - View/export schedule
    // 8. exportPdf() - Export liabilities report
```

**File:** `app/Http/Controllers/Accounting/LeaseController.php`

```php
class LeaseController extends Controller
{
    // Lease Methods (IFRS 16):
    // 1. index() - Leases list with stats
    // 2. datatable() - AJAX DataTable
    // 3. create() - New lease wizard
    // 4. store() - Create lease + calculate ROU/liability
    // 5. show($id) - Lease details
    // 6. recordPayment($id) - Record lease payment
    // 7. runDepreciation() - ROU asset depreciation
    // 8. modification($id) - Lease modification
    // 9. terminate($id) - Early termination
    // 10. paymentSchedule($id) - View payment schedule
    // 11. exportPdf() - Export leases report
```

### 7.3 Views Structure

```
resources/views/accounting/liabilities/
├── index.blade.php
├── create.blade.php
├── show.blade.php
├── payment.blade.php
└── partials/
    ├── stats-row.blade.php
    └── amortization-table.blade.php

resources/views/accounting/leases/
├── index.blade.php
├── create.blade.php           # Multi-step wizard
├── show.blade.php
├── payment.blade.php
├── modification.blade.php
├── partials/
│   ├── stats-row.blade.php
│   ├── rou-depreciation.blade.php
│   └── payment-schedule.blade.php
└── reports/
    └── ifrs16-disclosure.blade.php
```

### 7.4 IFRS 16 Lease Wizard Steps

```
Step 1: Lease Details      Step 2: Payment Terms      Step 3: Options
┌──────────────────────┐   ┌──────────────────────┐   ┌──────────────────────┐
│ • Lease Type         │   │ • Monthly Payment    │   │ • Purchase Option    │
│ • Leased Item        │   │ • Annual Increase %  │   │ • Termination Option │
│ • Lessor Details     │   │ • Payment Frequency  │   │ • Residual Value     │
│ • Commencement Date  │   │ • Borrowing Rate %   │   │ • Guarantees         │
│ • Lease Term         │   │ • First Payment Date │   │                      │
└──────────────────────┘   └──────────────────────┘   └──────────────────────┘
                                                              ↓
Step 4: Review & Confirm                              Step 5: Complete
┌────────────────────────────────────────────────┐   ┌──────────────────────┐
│ Initial ROU Asset Value:     ₦12,500,000       │   │ ✓ Lease Created      │
│ Initial Lease Liability:     ₦12,500,000       │   │ ✓ ROU Asset Recorded │
│ Monthly Depreciation:        ₦208,333          │   │ ✓ Liability Recorded │
│ Total Interest Over Term:    ₦2,750,000        │   │ ✓ JE Posted          │
│                                                │   │                      │
│ [Generate Payment Schedule] [Confirm]          │   │ [View Lease Details] │
└────────────────────────────────────────────────┘   └──────────────────────┘
```

---

## 8. COST CENTER MODULE

### 8.1 Table Columns Reference

**cost_centers:**
- id, code, name, description, type (direct/indirect/administrative)
- parent_id, department_id, manager_user_id, budget_amount
- is_active, level, path

**cost_center_budgets:**
- id, cost_center_id, fiscal_year_id, account_id
- budgeted_amount, actual_amount, variance, notes

**cost_center_allocations:**
- id, from_cost_center_id, to_cost_center_id, account_id
- allocation_basis, allocation_percentage, notes

### 8.2 Controller Implementation

**File:** `app/Http/Controllers/Accounting/CostCenterController.php`

```php
class CostCenterController extends Controller
{
    // Methods:
    // 1. index() - Cost centers tree view
    // 2. datatable() - Flat list DataTable
    // 3. create() - New cost center
    // 4. store() - Create cost center
    // 5. show($id) - Cost center details + expenses
    // 6. edit($id) - Edit cost center
    // 7. budgets($id) - Manage budgets
    // 8. allocations() - Allocation rules
    // 9. runAllocation() - Run cost allocation
    // 10. report($id) - Cost center report
    // 11. exportPdf() - Export cost centers
    // 12. exportExcel() - Export to Excel
```

### 8.3 Views Structure

```
resources/views/accounting/cost-centers/
├── index.blade.php          # Tree view + flat list
├── create.blade.php
├── show.blade.php           # Cost center details
├── edit.blade.php
├── budgets/
│   ├── index.blade.php
│   └── edit.blade.php
├── allocations/
│   ├── index.blade.php
│   ├── create.blade.php
│   └── run.blade.php
├── reports/
│   └── cost-center-report.blade.php
└── partials/
    ├── stats-row.blade.php
    ├── tree-view.blade.php
    └── expense-chart.blade.php
```

---

## 9. CAPEX PROJECTS MODULE

### 9.1 Table Columns Reference

**capex_projects:**
- id, project_code, project_name, description
- project_type (equipment/facility/technology/vehicle/other)
- department_id, fixed_asset_category_id
- estimated_cost, approved_budget, actual_cost, committed_cost, remaining_budget
- proposed_date, approved_date, expected_start_date, expected_completion_date
- actual_start_date, actual_completion_date
- requested_by, approved_by, justification, rejection_reason
- status (draft/pending_approval/approved/rejected/in_progress/completed/cancelled)
- completion_percentage, expected_benefits
- expected_annual_savings, expected_payback_months, expected_roi_percentage, notes

**capex_project_expenses:**
- id, project_id, expense_id, purchase_order_id, amount
- expense_date, description, category, approved_by, notes

### 9.2 Controller Implementation

**File:** `app/Http/Controllers/Accounting/CapexController.php`

```php
class CapexController extends Controller
{
    // Methods:
    // 1. index() - Projects list with stats
    // 2. datatable() - AJAX DataTable
    // 3. create() - New project proposal
    // 4. store() - Submit project
    // 5. show($id) - Project details + expenses
    // 6. edit($id) - Edit project
    // 7. approve($id) - Approve project
    // 8. reject($id) - Reject with reason
    // 9. addExpense($id) - Link expense to project
    // 10. updateProgress($id) - Update completion %
    // 11. complete($id) - Mark as completed
    // 12. createAsset($id) - Create fixed asset from CAPEX
    // 13. exportPdf() - Export CAPEX report
    // 14. exportExcel() - Export to Excel
```

### 9.3 Views Structure

```
resources/views/accounting/capex/
├── index.blade.php          # Projects list with stats
├── create.blade.php         # New project form
├── show.blade.php           # Project details + progress
├── edit.blade.php
├── approve.blade.php        # Approval workflow
├── expenses/
│   ├── index.blade.php      # Project expenses
│   └── add.blade.php        # Add expense
├── reports/
│   └── capex-report.blade.php
└── partials/
    ├── stats-row.blade.php
    ├── filters.blade.php
    ├── progress-chart.blade.php
    └── budget-vs-actual.blade.php
```

### 9.4 Dashboard Stats

```
┌─────────────────┬──────────────────┬─────────────────┬─────────────────┐
│ Active Projects │ Total Budget     │ Spent YTD       │ Pending Approval│
│ 12 in progress  │ ₦85,000,000      │ ₦42,500,000     │ 4 projects      │
└─────────────────┴──────────────────┴─────────────────┴─────────────────┘
┌─────────────────┬──────────────────┬─────────────────┬─────────────────┐
│ Completed YTD   │ Budget Variance  │ Avg. ROI        │ Overdue         │
│ 8 projects      │ -₦2,500,000      │ 18.5%           │ 2 projects      │
└─────────────────┴──────────────────┴─────────────────┴─────────────────┘
```

---

## 10. BUDGET MODULE

### 10.1 Table Columns Reference

**budgets:**
- id, budget_code, budget_name, description
- budget_type (annual/quarterly/project/department)
- fiscal_year_id, department_id, cost_center_id
- start_date, end_date, total_budget, total_actual, variance
- status (draft/pending_approval/approved/active/closed)
- prepared_by, approved_by, approved_at, notes

**budget_lines:**
- id, budget_id, account_id, line_description
- jan through dec (monthly amounts), total
- actual_jan through actual_dec, actual_total
- variance, notes

**budget_revisions:**
- id, budget_id, revision_number, revision_date
- original_amount, revised_amount, change_amount
- revision_reason, approved_by, approved_at

### 10.2 Controller Implementation

**File:** `app/Http/Controllers/Accounting/BudgetController.php`

```php
class BudgetController extends Controller
{
    // Methods:
    // 1. index() - Budgets list with stats
    // 2. datatable() - AJAX DataTable
    // 3. create() - New budget wizard
    // 4. store() - Create budget
    // 5. show($id) - Budget details with variance
    // 6. edit($id) - Edit budget lines
    // 7. updateLine($lineId) - Update single line
    // 8. submit($id) - Submit for approval
    // 9. approve($id) - Approve budget
    // 10. revise($id) - Create revision
    // 11. copyFromPrevious($fiscalYearId) - Copy last year's budget
    // 12. importExcel() - Import budget from Excel
    // 13. exportPdf($id) - Export budget report
    // 14. exportExcel($id) - Export to Excel
    // 15. varianceReport($id) - Budget vs Actual report
```

### 10.3 Views Structure

```
resources/views/accounting/budgets/
├── index.blade.php          # Budgets list
├── create.blade.php         # Multi-step wizard
├── show.blade.php           # Budget overview
├── edit.blade.php           # Edit budget lines
├── revisions/
│   ├── index.blade.php
│   └── create.blade.php
├── reports/
│   ├── variance-report.blade.php
│   └── budget-vs-actual.blade.php
├── import.blade.php         # Excel import
└── partials/
    ├── stats-row.blade.php
    ├── monthly-grid.blade.php   # 12-month input grid
    ├── variance-chart.blade.php
    └── filters.blade.php
```

### 10.4 Budget Entry Grid

```
┌────────────────────────────────────────────────────────────────────────────────┐
│  Budget: Annual Operating Budget 2026                    [Import Excel] [Save] │
├────────────────────────────────────────────────────────────────────────────────┤
│ Account          │ Jan    │ Feb    │ Mar    │ Apr    │ ...  │ Dec    │ Total  │
├──────────────────┼────────┼────────┼────────┼────────┼──────┼────────┼────────┤
│ 4100 - Revenue   │500,000 │520,000 │480,000 │550,000 │ ...  │600,000 │6,200,000│
│   Actual         │485,000 │512,000 │        │        │      │        │997,000 │
│   Variance       │-15,000 │-8,000  │        │        │      │        │-23,000 │
├──────────────────┼────────┼────────┼────────┼────────┼──────┼────────┼────────┤
│ 6100 - Salaries  │200,000 │200,000 │200,000 │200,000 │ ...  │200,000 │2,400,000│
│   Actual         │198,000 │205,000 │        │        │      │        │403,000 │
│   Variance       │+2,000  │-5,000  │        │        │      │        │-3,000  │
└──────────────────┴────────┴────────┴────────┴────────┴──────┴────────┴────────┘
```

---

## 11. FINANCIAL KPI DASHBOARD

### 11.1 Table Columns Reference

**financial_kpis:**
- id, kpi_code, kpi_name, category (liquidity/profitability/efficiency/leverage)
- description, calculation_formula, unit (currency/percentage/ratio/days)
- frequency (daily/weekly/monthly/quarterly), target_value
- warning_threshold_low, warning_threshold_high
- critical_threshold_low, critical_threshold_high
- display_order, show_on_dashboard, is_active, chart_type

**financial_kpi_values:**
- id, kpi_id, period_date, period_type (daily/weekly/monthly/quarterly)
- calculated_value, previous_value, change_amount, change_percentage
- calculated_at, calculated_by

**financial_kpi_alerts:**
- id, kpi_id, kpi_value_id, alert_type (warning/critical)
- alert_message, threshold_breached, actual_value
- is_acknowledged, acknowledged_by, acknowledged_at, notes

### 11.2 Controller Implementation

**File:** `app/Http/Controllers/Accounting/KpiController.php`

```php
class KpiController extends Controller
{
    // Methods:
    // 1. dashboard() - KPI dashboard with charts
    // 2. index() - KPI definitions list
    // 3. create() - New KPI definition
    // 4. store() - Create KPI
    // 5. edit($id) - Edit KPI
    // 6. calculate() - Calculate all KPIs
    // 7. calculateSingle($id) - Calculate single KPI
    // 8. history($id) - KPI history with chart
    // 9. alerts() - Active alerts list
    // 10. acknowledgeAlert($alertId) - Acknowledge alert
    // 11. configure() - Dashboard configuration
    // 12. exportPdf() - Export KPI report
```

### 11.3 Views Structure

```
resources/views/accounting/kpi/
├── dashboard.blade.php      # Main KPI dashboard
├── index.blade.php          # KPI definitions
├── create.blade.php
├── edit.blade.php
├── history.blade.php        # KPI trend analysis
├── alerts.blade.php         # Active alerts
├── configure.blade.php      # Dashboard configuration
└── partials/
    ├── kpi-card.blade.php       # Single KPI display
    ├── trend-chart.blade.php    # Trend visualization
    ├── gauge-chart.blade.php    # Gauge visualization
    └── alert-badge.blade.php
```

### 11.4 KPI Dashboard Layout

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  FINANCIAL KPI DASHBOARD                             Period: January 2026   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  LIQUIDITY                           PROFITABILITY                          │
│  ┌─────────────┐ ┌─────────────┐    ┌─────────────┐ ┌─────────────┐        │
│  │Current Ratio│ │Quick Ratio  │    │Net Margin   │ │ROA          │        │
│  │    2.15     │ │    1.85     │    │   12.5%     │ │    8.2%     │        │
│  │  ↑ 0.12    │ │  ↓ 0.05    │    │  ↑ 1.2%    │ │  ↑ 0.5%    │        │
│  │   ███████  │ │   ██████   │    │   ████████  │ │   ███████   │        │
│  └─────────────┘ └─────────────┘    └─────────────┘ └─────────────┘        │
│                                                                             │
│  EFFICIENCY                          LEVERAGE                               │
│  ┌─────────────┐ ┌─────────────┐    ┌─────────────┐ ┌─────────────┐        │
│  │AR Days      │ │AP Days      │    │Debt Ratio   │ │Interest Cov │        │
│  │    32 days  │ │    28 days  │    │   0.45      │ │    4.5x     │        │
│  │  ↓ 3 days  │ │  → 0 days  │    │  → 0.00    │ │  ↑ 0.3x    │        │
│  │   ██████   │ │   █████    │    │   ████████  │ │   ███████   │        │
│  └─────────────┘ └─────────────┘    └─────────────┘ └─────────────┘        │
│                                                                             │
│  ALERTS (3 Active)                                                          │
│  ⚠️ Current Ratio below target (2.15 < 2.50)                                │
│  🔴 AR Days exceeds 30 day threshold                                        │
│  ⚠️ Cash Balance below minimum                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 12. DASHBOARD INTEGRATION

### 12.1 Updated Accounting Dashboard Navigation Cards

Add to `resources/views/accounting/dashboard.blade.php`:

```blade
<!-- New Navigation Cards Row -->
<div class="row mb-4">
    <div class="col-12">
        <h5 class="mb-3"><i class="mdi mdi-view-grid mr-2"></i>Advanced Modules</h5>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <a href="{{ route('accounting.petty-cash.index') }}" class="nav-card nav-card-orange">
            <div class="nav-card-icon"><i class="mdi mdi-cash-register"></i></div>
            <div class="nav-card-content">
                <h6>Petty Cash</h6>
                <p>Manage petty cash funds</p>
            </div>
            <i class="mdi mdi-chevron-right nav-card-arrow"></i>
        </a>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <a href="{{ route('accounting.transfers.index') }}" class="nav-card nav-card-indigo">
            <div class="nav-card-icon"><i class="mdi mdi-bank-transfer"></i></div>
            <div class="nav-card-content">
                <h6>Bank Transfers</h6>
                <p>Inter-account transfers</p>
            </div>
            <i class="mdi mdi-chevron-right nav-card-arrow"></i>
        </a>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <a href="{{ route('accounting.bank-reconciliation.index') }}" class="nav-card nav-card-teal">
            <div class="nav-card-icon"><i class="mdi mdi-bank-check"></i></div>
            <div class="nav-card-content">
                <h6>Bank Reconciliation</h6>
                <p>Reconcile statements</p>
            </div>
            <i class="mdi mdi-chevron-right nav-card-arrow"></i>
        </a>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <a href="{{ route('accounting.patient-deposits.index') }}" class="nav-card nav-card-lime">
            <div class="nav-card-icon"><i class="mdi mdi-account-cash"></i></div>
            <div class="nav-card-content">
                <h6>Patient Deposits</h6>
                <p>Advance payments</p>
            </div>
            <i class="mdi mdi-chevron-right nav-card-arrow"></i>
        </a>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <a href="{{ route('accounting.fixed-assets.index') }}" class="nav-card nav-card-brown">
            <div class="nav-card-icon"><i class="mdi mdi-package-variant-closed"></i></div>
            <div class="nav-card-content">
                <h6>Fixed Assets</h6>
                <p>Asset register & depreciation</p>
            </div>
            <i class="mdi mdi-chevron-right nav-card-arrow"></i>
        </a>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <a href="{{ route('accounting.leases.index') }}" class="nav-card nav-card-blue-grey">
            <div class="nav-card-icon"><i class="mdi mdi-file-document-edit"></i></div>
            <div class="nav-card-content">
                <h6>Leases (IFRS 16)</h6>
                <p>Lease management</p>
            </div>
            <i class="mdi mdi-chevron-right nav-card-arrow"></i>
        </a>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <a href="{{ route('accounting.cost-centers.index') }}" class="nav-card nav-card-deep-purple">
            <div class="nav-card-icon"><i class="mdi mdi-sitemap"></i></div>
            <div class="nav-card-content">
                <h6>Cost Centers</h6>
                <p>Cost allocation</p>
            </div>
            <i class="mdi mdi-chevron-right nav-card-arrow"></i>
        </a>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <a href="{{ route('accounting.capex.index') }}" class="nav-card nav-card-amber">
            <div class="nav-card-icon"><i class="mdi mdi-office-building"></i></div>
            <div class="nav-card-content">
                <h6>CAPEX Projects</h6>
                <p>Capital expenditure</p>
            </div>
            <i class="mdi mdi-chevron-right nav-card-arrow"></i>
        </a>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <a href="{{ route('accounting.budgets.index') }}" class="nav-card nav-card-green">
            <div class="nav-card-icon"><i class="mdi mdi-calculator-variant"></i></div>
            <div class="nav-card-content">
                <h6>Budgets</h6>
                <p>Budget management</p>
            </div>
            <i class="mdi mdi-chevron-right nav-card-arrow"></i>
        </a>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <a href="{{ route('accounting.kpi.dashboard') }}" class="nav-card nav-card-red">
            <div class="nav-card-icon"><i class="mdi mdi-gauge"></i></div>
            <div class="nav-card-content">
                <h6>Financial KPIs</h6>
                <p>Performance metrics</p>
            </div>
            <i class="mdi mdi-chevron-right nav-card-arrow"></i>
        </a>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <a href="{{ route('accounting.cash-flow-forecast.index') }}" class="nav-card nav-card-cyan">
            <div class="nav-card-icon"><i class="mdi mdi-chart-timeline-variant"></i></div>
            <div class="nav-card-content">
                <h6>Cash Flow Forecast</h6>
                <p>Projections & planning</p>
            </div>
            <i class="mdi mdi-chevron-right nav-card-arrow"></i>
        </a>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <a href="{{ route('accounting.liabilities.index') }}" class="nav-card nav-card-pink">
            <div class="nav-card-icon"><i class="mdi mdi-credit-card-clock"></i></div>
            <div class="nav-card-content">
                <h6>Liabilities</h6>
                <p>Loans & obligations</p>
            </div>
            <i class="mdi mdi-chevron-right nav-card-arrow"></i>
        </a>
    </div>
</div>
```

---

## 13. ROUTE DEFINITIONS

### 13.1 Accounting Routes File

**File:** `routes/accounting.php`

```php
<?php

use App\Http\Controllers\Accounting\AccountingController;
use App\Http\Controllers\Accounting\PettyCashController;
use App\Http\Controllers\Accounting\TransferController;
use App\Http\Controllers\Accounting\BankReconciliationController;
use App\Http\Controllers\Accounting\PatientDepositController;
use App\Http\Controllers\Accounting\CashFlowForecastController;
use App\Http\Controllers\Accounting\FixedAssetController;
use App\Http\Controllers\Accounting\LiabilityController;
use App\Http\Controllers\Accounting\LeaseController;
use App\Http\Controllers\Accounting\CostCenterController;
use App\Http\Controllers\Accounting\CapexController;
use App\Http\Controllers\Accounting\BudgetController;
use App\Http\Controllers\Accounting\KpiController;

Route::prefix('accounting')->name('accounting.')->middleware(['auth'])->group(function () {

    // === PETTY CASH ===
    Route::prefix('petty-cash')->name('petty-cash.')->group(function () {
        Route::get('/', [PettyCashController::class, 'index'])->name('index');
        Route::get('/datatable', [PettyCashController::class, 'datatable'])->name('datatable');
        
        // Funds
        Route::get('/funds', [PettyCashController::class, 'fundsIndex'])->name('funds.index');
        Route::get('/funds/datatable', [PettyCashController::class, 'fundsDatatable'])->name('funds.datatable');
        Route::get('/funds/create', [PettyCashController::class, 'fundsCreate'])->name('funds.create');
        Route::post('/funds', [PettyCashController::class, 'fundsStore'])->name('funds.store');
        Route::get('/funds/{fund}', [PettyCashController::class, 'fundsShow'])->name('funds.show');
        Route::get('/funds/{fund}/edit', [PettyCashController::class, 'fundsEdit'])->name('funds.edit');
        Route::put('/funds/{fund}', [PettyCashController::class, 'fundsUpdate'])->name('funds.update');
        
        // Transactions
        Route::get('/funds/{fund}/transactions', [PettyCashController::class, 'transactionsIndex'])->name('transactions.index');
        Route::get('/funds/{fund}/transactions/datatable', [PettyCashController::class, 'transactionsDatatable'])->name('transactions.datatable');
        Route::get('/funds/{fund}/disbursement', [PettyCashController::class, 'disbursementCreate'])->name('disbursement.create');
        Route::post('/funds/{fund}/disbursement', [PettyCashController::class, 'disbursementStore'])->name('disbursement.store');
        Route::get('/funds/{fund}/replenishment', [PettyCashController::class, 'replenishmentCreate'])->name('replenishment.create');
        Route::post('/funds/{fund}/replenishment', [PettyCashController::class, 'replenishmentStore'])->name('replenishment.store');
        Route::post('/transactions/{transaction}/approve', [PettyCashController::class, 'approve'])->name('transactions.approve');
        Route::post('/transactions/{transaction}/reject', [PettyCashController::class, 'reject'])->name('transactions.reject');
        
        // Reconciliation
        Route::get('/funds/{fund}/reconcile', [PettyCashController::class, 'reconcile'])->name('reconcile');
        Route::post('/funds/{fund}/reconcile', [PettyCashController::class, 'storeReconciliation'])->name('reconcile.store');
        
        // Export
        Route::get('/funds/{fund}/export/pdf', [PettyCashController::class, 'exportPdf'])->name('export.pdf');
        Route::get('/funds/{fund}/export/excel', [PettyCashController::class, 'exportExcel'])->name('export.excel');
    });

    // === INTER-ACCOUNT TRANSFERS ===
    Route::prefix('transfers')->name('transfers.')->group(function () {
        Route::get('/', [TransferController::class, 'index'])->name('index');
        Route::get('/datatable', [TransferController::class, 'datatable'])->name('datatable');
        Route::get('/create', [TransferController::class, 'create'])->name('create');
        Route::post('/', [TransferController::class, 'store'])->name('store');
        Route::get('/{transfer}', [TransferController::class, 'show'])->name('show');
        Route::post('/{transfer}/approve', [TransferController::class, 'approve'])->name('approve');
        Route::post('/{transfer}/reject', [TransferController::class, 'reject'])->name('reject');
        Route::post('/{transfer}/confirm-clearance', [TransferController::class, 'confirmClearance'])->name('confirm-clearance');
        Route::post('/{transfer}/cancel', [TransferController::class, 'cancel'])->name('cancel');
        Route::get('/export/pdf', [TransferController::class, 'exportPdf'])->name('export.pdf');
        Route::get('/export/excel', [TransferController::class, 'exportExcel'])->name('export.excel');
    });

    // === BANK RECONCILIATION ===
    Route::prefix('bank-reconciliation')->name('bank-reconciliation.')->group(function () {
        Route::get('/', [BankReconciliationController::class, 'index'])->name('index');
        Route::get('/datatable', [BankReconciliationController::class, 'datatable'])->name('datatable');
        Route::get('/create/{bank}', [BankReconciliationController::class, 'create'])->name('create');
        Route::post('/store/{bank}', [BankReconciliationController::class, 'store'])->name('store');
        Route::get('/{reconciliation}', [BankReconciliationController::class, 'show'])->name('show');
        Route::post('/{reconciliation}/import', [BankReconciliationController::class, 'importStatement'])->name('import');
        Route::post('/{reconciliation}/auto-match', [BankReconciliationController::class, 'autoMatch'])->name('auto-match');
        Route::post('/{reconciliation}/manual-match', [BankReconciliationController::class, 'manualMatch'])->name('manual-match');
        Route::post('/items/{item}/unmatch', [BankReconciliationController::class, 'unmatch'])->name('unmatch');
        Route::post('/{reconciliation}/adjustment', [BankReconciliationController::class, 'addAdjustment'])->name('adjustment');
        Route::post('/{reconciliation}/finalize', [BankReconciliationController::class, 'finalize'])->name('finalize');
        Route::post('/{reconciliation}/approve', [BankReconciliationController::class, 'approve'])->name('approve');
        Route::get('/{reconciliation}/export/pdf', [BankReconciliationController::class, 'exportPdf'])->name('export.pdf');
        Route::get('/{reconciliation}/export/excel', [BankReconciliationController::class, 'exportExcel'])->name('export.excel');
    });

    // === PATIENT DEPOSITS ===
    Route::prefix('patient-deposits')->name('patient-deposits.')->group(function () {
        Route::get('/', [PatientDepositController::class, 'index'])->name('index');
        Route::get('/datatable', [PatientDepositController::class, 'datatable'])->name('datatable');
        Route::get('/create', [PatientDepositController::class, 'create'])->name('create');
        Route::post('/', [PatientDepositController::class, 'store'])->name('store');
        Route::get('/{deposit}', [PatientDepositController::class, 'show'])->name('show');
        Route::get('/{deposit}/apply', [PatientDepositController::class, 'apply'])->name('apply');
        Route::post('/{deposit}/apply', [PatientDepositController::class, 'storeApplication'])->name('apply.store');
        Route::get('/{deposit}/refund', [PatientDepositController::class, 'refund'])->name('refund');
        Route::post('/{deposit}/refund', [PatientDepositController::class, 'processRefund'])->name('refund.process');
        Route::get('/patient/{patient}', [PatientDepositController::class, 'patientDeposits'])->name('patient');
        Route::get('/export/pdf', [PatientDepositController::class, 'exportPdf'])->name('export.pdf');
        Route::get('/export/excel', [PatientDepositController::class, 'exportExcel'])->name('export.excel');
        Route::get('/{deposit}/receipt', [PatientDepositController::class, 'printReceipt'])->name('receipt');
    });

    // === CASH FLOW FORECAST ===
    Route::prefix('cash-flow-forecast')->name('cash-flow-forecast.')->group(function () {
        Route::get('/', [CashFlowForecastController::class, 'index'])->name('index');
        Route::get('/datatable', [CashFlowForecastController::class, 'datatable'])->name('datatable');
        Route::get('/create', [CashFlowForecastController::class, 'create'])->name('create');
        Route::post('/', [CashFlowForecastController::class, 'store'])->name('store');
        Route::get('/{forecast}', [CashFlowForecastController::class, 'show'])->name('show');
        Route::get('/periods/{period}/edit', [CashFlowForecastController::class, 'editPeriod'])->name('periods.edit');
        Route::put('/periods/{period}', [CashFlowForecastController::class, 'updatePeriod'])->name('periods.update');
        Route::post('/periods/{period}/items', [CashFlowForecastController::class, 'addItem'])->name('periods.items.store');
        Route::put('/periods/{period}/actuals', [CashFlowForecastController::class, 'updateActuals'])->name('periods.actuals');
        Route::get('/patterns', [CashFlowForecastController::class, 'patterns'])->name('patterns.index');
        Route::post('/patterns', [CashFlowForecastController::class, 'storePattern'])->name('patterns.store');
        Route::get('/{forecast}/export/pdf', [CashFlowForecastController::class, 'exportPdf'])->name('export.pdf');
        Route::get('/{forecast}/export/excel', [CashFlowForecastController::class, 'exportExcel'])->name('export.excel');
    });

    // === FIXED ASSETS ===
    Route::prefix('fixed-assets')->name('fixed-assets.')->group(function () {
        Route::get('/', [FixedAssetController::class, 'index'])->name('index');
        Route::get('/datatable', [FixedAssetController::class, 'datatable'])->name('datatable');
        Route::get('/create', [FixedAssetController::class, 'create'])->name('create');
        Route::post('/', [FixedAssetController::class, 'store'])->name('store');
        Route::get('/{asset}', [FixedAssetController::class, 'show'])->name('show');
        Route::get('/{asset}/edit', [FixedAssetController::class, 'edit'])->name('edit');
        Route::put('/{asset}', [FixedAssetController::class, 'update'])->name('update');
        
        // Categories
        Route::get('/categories/index', [FixedAssetController::class, 'categories'])->name('categories.index');
        Route::get('/categories/datatable', [FixedAssetController::class, 'categoriesDatatable'])->name('categories.datatable');
        Route::post('/categories', [FixedAssetController::class, 'categoryStore'])->name('categories.store');
        Route::put('/categories/{category}', [FixedAssetController::class, 'categoryUpdate'])->name('categories.update');
        
        // Depreciation
        Route::get('/depreciation/index', [FixedAssetController::class, 'depreciationIndex'])->name('depreciation.index');
        Route::get('/depreciation/preview', [FixedAssetController::class, 'depreciationPreview'])->name('depreciation.preview');
        Route::post('/depreciation/run', [FixedAssetController::class, 'runDepreciation'])->name('depreciation.run');
        Route::get('/depreciation/history/{asset}', [FixedAssetController::class, 'depreciationHistory'])->name('depreciation.history');
        
        // Disposal
        Route::get('/{asset}/dispose', [FixedAssetController::class, 'dispose'])->name('dispose');
        Route::post('/{asset}/dispose', [FixedAssetController::class, 'processDisposal'])->name('dispose.process');
        Route::get('/disposals/index', [FixedAssetController::class, 'disposalsIndex'])->name('disposals.index');
        Route::get('/disposals/datatable', [FixedAssetController::class, 'disposalsDatatable'])->name('disposals.datatable');
        
        // Transfers
        Route::get('/{asset}/transfer', [FixedAssetController::class, 'transfer'])->name('transfer');
        Route::post('/{asset}/transfer', [FixedAssetController::class, 'processTransfer'])->name('transfer.process');
        Route::get('/transfers/index', [FixedAssetController::class, 'transfersIndex'])->name('transfers.index');
        
        // Maintenance
        Route::get('/{asset}/maintenance', [FixedAssetController::class, 'maintenanceSchedule'])->name('maintenance');
        Route::post('/{asset}/maintenance', [FixedAssetController::class, 'storeMaintenanceSchedule'])->name('maintenance.store');
        
        // Reports
        Route::get('/reports/register', [FixedAssetController::class, 'registerReport'])->name('reports.register');
        Route::get('/reports/depreciation', [FixedAssetController::class, 'depreciationReport'])->name('reports.depreciation');
        Route::get('/export/pdf', [FixedAssetController::class, 'exportPdf'])->name('export.pdf');
        Route::get('/export/excel', [FixedAssetController::class, 'exportExcel'])->name('export.excel');
    });

    // === LIABILITIES ===
    Route::prefix('liabilities')->name('liabilities.')->group(function () {
        Route::get('/', [LiabilityController::class, 'index'])->name('index');
        Route::get('/datatable', [LiabilityController::class, 'datatable'])->name('datatable');
        Route::get('/create', [LiabilityController::class, 'create'])->name('create');
        Route::post('/', [LiabilityController::class, 'store'])->name('store');
        Route::get('/{liability}', [LiabilityController::class, 'show'])->name('show');
        Route::get('/{liability}/edit', [LiabilityController::class, 'edit'])->name('edit');
        Route::put('/{liability}', [LiabilityController::class, 'update'])->name('update');
        Route::get('/{liability}/payment', [LiabilityController::class, 'payment'])->name('payment');
        Route::post('/{liability}/payment', [LiabilityController::class, 'recordPayment'])->name('payment.store');
        Route::get('/{liability}/schedule', [LiabilityController::class, 'amortizationSchedule'])->name('schedule');
        Route::get('/export/pdf', [LiabilityController::class, 'exportPdf'])->name('export.pdf');
        Route::get('/export/excel', [LiabilityController::class, 'exportExcel'])->name('export.excel');
    });

    // === LEASES (IFRS 16) ===
    Route::prefix('leases')->name('leases.')->group(function () {
        Route::get('/', [LeaseController::class, 'index'])->name('index');
        Route::get('/datatable', [LeaseController::class, 'datatable'])->name('datatable');
        Route::get('/create', [LeaseController::class, 'create'])->name('create');
        Route::post('/', [LeaseController::class, 'store'])->name('store');
        Route::get('/{lease}', [LeaseController::class, 'show'])->name('show');
        Route::get('/{lease}/edit', [LeaseController::class, 'edit'])->name('edit');
        Route::put('/{lease}', [LeaseController::class, 'update'])->name('update');
        Route::get('/{lease}/payment', [LeaseController::class, 'payment'])->name('payment');
        Route::post('/{lease}/payment', [LeaseController::class, 'recordPayment'])->name('payment.store');
        Route::post('/run-depreciation', [LeaseController::class, 'runDepreciation'])->name('depreciation.run');
        Route::get('/{lease}/modification', [LeaseController::class, 'modification'])->name('modification');
        Route::post('/{lease}/modification', [LeaseController::class, 'storeModification'])->name('modification.store');
        Route::post('/{lease}/terminate', [LeaseController::class, 'terminate'])->name('terminate');
        Route::get('/{lease}/schedule', [LeaseController::class, 'paymentSchedule'])->name('schedule');
        Route::get('/reports/ifrs16', [LeaseController::class, 'ifrs16Report'])->name('reports.ifrs16');
        Route::get('/export/pdf', [LeaseController::class, 'exportPdf'])->name('export.pdf');
        Route::get('/export/excel', [LeaseController::class, 'exportExcel'])->name('export.excel');
    });

    // === COST CENTERS ===
    Route::prefix('cost-centers')->name('cost-centers.')->group(function () {
        Route::get('/', [CostCenterController::class, 'index'])->name('index');
        Route::get('/datatable', [CostCenterController::class, 'datatable'])->name('datatable');
        Route::get('/create', [CostCenterController::class, 'create'])->name('create');
        Route::post('/', [CostCenterController::class, 'store'])->name('store');
        Route::get('/{costCenter}', [CostCenterController::class, 'show'])->name('show');
        Route::get('/{costCenter}/edit', [CostCenterController::class, 'edit'])->name('edit');
        Route::put('/{costCenter}', [CostCenterController::class, 'update'])->name('update');
        Route::get('/{costCenter}/budgets', [CostCenterController::class, 'budgets'])->name('budgets');
        Route::post('/{costCenter}/budgets', [CostCenterController::class, 'storeBudget'])->name('budgets.store');
        Route::get('/allocations/index', [CostCenterController::class, 'allocations'])->name('allocations.index');
        Route::post('/allocations', [CostCenterController::class, 'storeAllocation'])->name('allocations.store');
        Route::post('/allocations/run', [CostCenterController::class, 'runAllocation'])->name('allocations.run');
        Route::get('/{costCenter}/report', [CostCenterController::class, 'report'])->name('report');
        Route::get('/export/pdf', [CostCenterController::class, 'exportPdf'])->name('export.pdf');
        Route::get('/export/excel', [CostCenterController::class, 'exportExcel'])->name('export.excel');
    });

    // === CAPEX PROJECTS ===
    Route::prefix('capex')->name('capex.')->group(function () {
        Route::get('/', [CapexController::class, 'index'])->name('index');
        Route::get('/datatable', [CapexController::class, 'datatable'])->name('datatable');
        Route::get('/create', [CapexController::class, 'create'])->name('create');
        Route::post('/', [CapexController::class, 'store'])->name('store');
        Route::get('/{project}', [CapexController::class, 'show'])->name('show');
        Route::get('/{project}/edit', [CapexController::class, 'edit'])->name('edit');
        Route::put('/{project}', [CapexController::class, 'update'])->name('update');
        Route::post('/{project}/approve', [CapexController::class, 'approve'])->name('approve');
        Route::post('/{project}/reject', [CapexController::class, 'reject'])->name('reject');
        Route::get('/{project}/expenses', [CapexController::class, 'expenses'])->name('expenses');
        Route::post('/{project}/expenses', [CapexController::class, 'addExpense'])->name('expenses.add');
        Route::put('/{project}/progress', [CapexController::class, 'updateProgress'])->name('progress');
        Route::post('/{project}/complete', [CapexController::class, 'complete'])->name('complete');
        Route::post('/{project}/create-asset', [CapexController::class, 'createAsset'])->name('create-asset');
        Route::get('/reports/capex', [CapexController::class, 'capexReport'])->name('reports.index');
        Route::get('/export/pdf', [CapexController::class, 'exportPdf'])->name('export.pdf');
        Route::get('/export/excel', [CapexController::class, 'exportExcel'])->name('export.excel');
    });

    // === BUDGETS ===
    Route::prefix('budgets')->name('budgets.')->group(function () {
        Route::get('/', [BudgetController::class, 'index'])->name('index');
        Route::get('/datatable', [BudgetController::class, 'datatable'])->name('datatable');
        Route::get('/create', [BudgetController::class, 'create'])->name('create');
        Route::post('/', [BudgetController::class, 'store'])->name('store');
        Route::get('/{budget}', [BudgetController::class, 'show'])->name('show');
        Route::get('/{budget}/edit', [BudgetController::class, 'edit'])->name('edit');
        Route::put('/{budget}', [BudgetController::class, 'update'])->name('update');
        Route::put('/lines/{line}', [BudgetController::class, 'updateLine'])->name('lines.update');
        Route::post('/{budget}/submit', [BudgetController::class, 'submit'])->name('submit');
        Route::post('/{budget}/approve', [BudgetController::class, 'approve'])->name('approve');
        Route::get('/{budget}/revise', [BudgetController::class, 'revise'])->name('revise');
        Route::post('/{budget}/revise', [BudgetController::class, 'storeRevision'])->name('revise.store');
        Route::post('/copy-from/{fiscalYear}', [BudgetController::class, 'copyFromPrevious'])->name('copy');
        Route::get('/import', [BudgetController::class, 'importForm'])->name('import');
        Route::post('/import', [BudgetController::class, 'importExcel'])->name('import.store');
        Route::get('/{budget}/variance', [BudgetController::class, 'varianceReport'])->name('variance');
        Route::get('/{budget}/export/pdf', [BudgetController::class, 'exportPdf'])->name('export.pdf');
        Route::get('/{budget}/export/excel', [BudgetController::class, 'exportExcel'])->name('export.excel');
    });

    // === FINANCIAL KPIs ===
    Route::prefix('kpi')->name('kpi.')->group(function () {
        Route::get('/', [KpiController::class, 'dashboard'])->name('dashboard');
        Route::get('/definitions', [KpiController::class, 'index'])->name('index');
        Route::get('/datatable', [KpiController::class, 'datatable'])->name('datatable');
        Route::get('/create', [KpiController::class, 'create'])->name('create');
        Route::post('/', [KpiController::class, 'store'])->name('store');
        Route::get('/{kpi}/edit', [KpiController::class, 'edit'])->name('edit');
        Route::put('/{kpi}', [KpiController::class, 'update'])->name('update');
        Route::post('/calculate', [KpiController::class, 'calculate'])->name('calculate');
        Route::post('/{kpi}/calculate', [KpiController::class, 'calculateSingle'])->name('calculate.single');
        Route::get('/{kpi}/history', [KpiController::class, 'history'])->name('history');
        Route::get('/alerts', [KpiController::class, 'alerts'])->name('alerts');
        Route::post('/alerts/{alert}/acknowledge', [KpiController::class, 'acknowledgeAlert'])->name('alerts.acknowledge');
        Route::get('/configure', [KpiController::class, 'configure'])->name('configure');
        Route::post('/configure', [KpiController::class, 'saveConfig'])->name('configure.save');
        Route::get('/export/pdf', [KpiController::class, 'exportPdf'])->name('export.pdf');
    });

});
```

### 13.2 Include Routes in RouteServiceProvider

Add to `app/Providers/RouteServiceProvider.php` boot method:

```php
Route::middleware('web')
    ->group(base_path('routes/accounting.php'));
```

---

## 14. SIMPLIFIED ROLE-BASED ACCESS

### 14.1 Permission Strategy - Role-Based Only

**IMPORTANT:** Instead of creating 50+ granular permissions, we will use the existing role-based access pattern from the sidebar. This approach:

1. **Simplifies maintenance** - No need to manage dozens of permissions
2. **Follows existing patterns** - Uses same approach as other modules
3. **Uses existing roles** - ACCOUNTS, BILLER, ADMIN, SUPERADMIN, AUDIT

### 14.2 Role Assignments for New Modules

| Module | Roles with Access |
|--------|-------------------|
| Petty Cash | SUPERADMIN, ADMIN, ACCOUNTS |
| Inter-Account Transfers | SUPERADMIN, ADMIN, ACCOUNTS |
| Bank Reconciliation | SUPERADMIN, ADMIN, ACCOUNTS, AUDIT |
| Patient Deposits | SUPERADMIN, ADMIN, ACCOUNTS, BILLER |
| Cash Flow Forecast | SUPERADMIN, ADMIN, ACCOUNTS |
| Fixed Assets | SUPERADMIN, ADMIN, ACCOUNTS |
| Liabilities | SUPERADMIN, ADMIN, ACCOUNTS |
| Leases (IFRS 16) | SUPERADMIN, ADMIN, ACCOUNTS |
| Cost Centers | SUPERADMIN, ADMIN, ACCOUNTS |
| CAPEX Projects | SUPERADMIN, ADMIN, ACCOUNTS |
| Budgets | SUPERADMIN, ADMIN, ACCOUNTS |
| Financial KPIs | SUPERADMIN, ADMIN, ACCOUNTS, AUDIT |

### 14.3 Implementation in Controllers

**Controller Constructor Pattern:**

```php
// For ACCOUNTS-only modules
public function __construct()
{
    $this->middleware(['auth', 'role:SUPERADMIN|ADMIN|ACCOUNTS']);
}

// For modules accessible to BILLER too (like Patient Deposits)
public function __construct()
{
    $this->middleware(['auth', 'role:SUPERADMIN|ADMIN|ACCOUNTS|BILLER']);
}

// For audit-related modules
public function __construct()
{
    $this->middleware(['auth', 'role:SUPERADMIN|ADMIN|ACCOUNTS|AUDIT']);
}
```

### 14.4 Sidebar Navigation Pattern

**Add items to existing Accountant Section (sidebar.blade.php around line 910):**

```blade
@hasanyrole('SUPERADMIN|ADMIN|ACCOUNTS')
<li class="nav-item">
    <a class="nav-link {{ request()->routeIs('accounting.petty-cash.*') ? 'active' : '' }}" 
       href="{{ route('accounting.petty-cash.index') }}">
        <i class="mdi mdi-cash-register"></i> Petty Cash
    </a>
</li>
<li class="nav-item">
    <a class="nav-link {{ request()->routeIs('accounting.transfers.*') ? 'active' : '' }}" 
       href="{{ route('accounting.transfers.index') }}">
        <i class="mdi mdi-bank-transfer"></i> Bank Transfers
    </a>
</li>
@endhasanyrole
```

### 14.5 Blade View Role Checks

**For actions within views:**

```blade
@hasanyrole('SUPERADMIN|ADMIN|ACCOUNTS')
    <a href="{{ route('accounting.xxx.create') }}" class="btn btn-primary">
        <i class="mdi mdi-plus"></i> Create New
    </a>
@endhasanyrole

{{-- For approval buttons (SUPERADMIN/ADMIN only) --}}
@hasanyrole('SUPERADMIN|ADMIN')
    <button class="btn btn-success btn-sm" onclick="approve({{ $item->id }})">
        <i class="mdi mdi-check"></i> Approve
    </button>
@endhasanyrole
```

### 14.6 API/Route Middleware

**In routes/accounting.php:**

```php
Route::prefix('accounting')->name('accounting.')->middleware(['auth'])->group(function () {

    // Accounting core - ACCOUNTS role required
    Route::middleware(['role:SUPERADMIN|ADMIN|ACCOUNTS'])->group(function () {
        // Petty Cash
        Route::prefix('petty-cash')->name('petty-cash.')->group(function () { ... });
        
        // Transfers
        Route::prefix('transfers')->name('transfers.')->group(function () { ... });
        
        // Fixed Assets
        Route::prefix('fixed-assets')->name('fixed-assets.')->group(function () { ... });
        
        // Budgets, Cost Centers, CAPEX, Leases, Liabilities, Cash Flow
        // ...
    });
    
    // Reconciliation - Include AUDIT role
    Route::middleware(['role:SUPERADMIN|ADMIN|ACCOUNTS|AUDIT'])->group(function () {
        Route::prefix('bank-reconciliation')->name('bank-reconciliation.')->group(function () { ... });
        Route::prefix('kpi')->name('kpi.')->group(function () { ... });
    });
    
    // Patient Deposits - Include BILLER role
    Route::middleware(['role:SUPERADMIN|ADMIN|ACCOUNTS|BILLER'])->group(function () {
        Route::prefix('patient-deposits')->name('patient-deposits.')->group(function () { ... });
    });
});
```

### 14.7 No Seeder Required

Since we're using existing roles only, **NO permission seeder is required**. The roles already exist:

- SUPERADMIN - Full access
- ADMIN - Administrative access
- ACCOUNTS - Accounting department
- BILLER - Billing staff (limited access)
- AUDIT - Audit/compliance team

This approach ensures all new modules integrate seamlessly with the existing permission architecture without additional setup complexity.

---

## IMPLEMENTATION PRIORITY ORDER

### Phase 1: Foundation (Week 1)
1. ✅ Routes file creation
2. ✅ Permission seeder update
3. ⬜ Petty Cash Controller & Views
4. ⬜ Inter-Account Transfers Controller & Views
5. ⬜ Dashboard integration (navigation cards)

### Phase 2: Cash Management (Week 2)
6. ⬜ Bank Reconciliation Controller & Views
7. ⬜ Patient Deposits Controller & Views
8. ⬜ Cash Flow Forecasting Controller & Views

### Phase 3: Asset Management (Week 3)
9. ⬜ Fixed Assets Controller & Views (Full module)
10. ⬜ Depreciation automation
11. ⬜ Asset disposal workflow

### Phase 4: Liabilities & Cost (Week 4)
12. ⬜ Liabilities Controller & Views
13. ⬜ Leases (IFRS 16) Controller & Views
14. ⬜ Cost Centers Controller & Views

### Phase 5: Planning & Analysis (Week 5)
15. ⬜ CAPEX Projects Controller & Views
16. ⬜ Budget Module Controller & Views
17. ⬜ Financial KPI Dashboard

### Phase 6: Polish & Integration (Week 6)
18. ⬜ PDF Export templates for all modules
19. ⬜ Excel Export methods for all modules
20. ⬜ Advanced filters testing
21. ⬜ DataTables optimization
22. ⬜ Permission testing
23. ⬜ Final dashboard integration

---

## COMMON PATTERNS TO FOLLOW

### DataTable AJAX Pattern

```javascript
$(document).ready(function() {
    var table = $('#data-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('accounting.xxx.datatable') }}",
            data: function(d) {
                d.status = $('#status-filter').val();
                d.date_from = $('#date-from').val();
                d.date_to = $('#date-to').val();
                // Add more filters
            }
        },
        columns: [
            { data: 'id', name: 'id' },
            // Define columns
        ],
        buttons: ['pageLength', 'copy', 'excel', 'pdf', 'print'],
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]]
    });

    // Filter change handlers
    $('.filter-control').on('change', function() {
        table.ajax.reload();
    });
});
```

### Stats Card Pattern

```blade
<div class="row mb-3">
    <div class="col-md-3 col-sm-6">
        <div class="stat-card border-left border-primary" style="border-left-width: 4px !important;">
            <h5><i class="mdi mdi-icon mr-1"></i> Label</h5>
            <div class="value text-primary">{{ number_format($value) }}</div>
            <small class="text-muted">Description</small>
        </div>
    </div>
    <!-- Repeat for other stats -->
</div>
```

### Export Button Pattern

```blade
<div class="btn-group">
    <a href="{{ route('accounting.xxx.export.pdf', request()->query()) }}" class="btn btn-danger btn-sm">
        <i class="mdi mdi-file-pdf"></i> PDF
    </a>
    <a href="{{ route('accounting.xxx.export.excel', request()->query()) }}" class="btn btn-success btn-sm">
        <i class="mdi mdi-file-excel"></i> Excel
    </a>
</div>
```

---

## END OF IMPLEMENTATION PLAN

This document serves as the complete blueprint for implementing the UI/UX layer for the CoreHealth v2 Accounting System enhancement. All implementations must follow the established patterns, use AJAX with DataTables, and integrate seamlessly with the existing accounting module.
