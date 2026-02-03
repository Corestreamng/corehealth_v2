<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Accounting\AccountingController;
use App\Http\Controllers\Accounting\JournalEntryController;
use App\Http\Controllers\Accounting\ChartOfAccountsController;
use App\Http\Controllers\Accounting\ReportController;
use App\Http\Controllers\Accounting\CreditNoteController;
use App\Http\Controllers\Accounting\OpeningBalanceController;
// New Accounting Modules
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

/*
|--------------------------------------------------------------------------
| Accounting Routes
|--------------------------------------------------------------------------
|
| Reference: Accounting System Plan §8 - Routes
|
| All accounting-related routes for the double-entry bookkeeping system.
|
*/

Route::prefix('accounting')->name('accounting.')->middleware(['auth', 'verified'])->group(function () {

    // Dashboard
    Route::get('/', [AccountingController::class, 'index'])->name('dashboard');

    // Fiscal Periods Management
    Route::get('/periods', [AccountingController::class, 'periods'])->name('periods');
    Route::post('/fiscal-years', [AccountingController::class, 'createFiscalYear'])->name('fiscal-years.store');
    Route::post('/periods/{periodId}/close', [AccountingController::class, 'closePeriod'])->name('periods.close');
    Route::post('/fiscal-years/{yearId}/close', [AccountingController::class, 'closeFiscalYear'])->name('fiscal-years.close');

    // Opening Balances
    Route::prefix('opening-balances')->name('opening-balances.')->group(function () {
        Route::get('/datatable', [OpeningBalanceController::class, 'datatable'])->name('datatable');
        Route::get('/', [OpeningBalanceController::class, 'index'])->name('index');
        Route::get('/create', [OpeningBalanceController::class, 'create'])->name('create');
        Route::post('/', [OpeningBalanceController::class, 'store'])->name('store');
        Route::put('/{accountId}', [OpeningBalanceController::class, 'update'])->name('update');
        Route::get('/api/accounts', [OpeningBalanceController::class, 'getAccounts'])->name('api.accounts');
    });

    // Journal Entries
    Route::prefix('journal-entries')->name('journal-entries.')->group(function () {
        // DataTables endpoint (must be before {id} routes)
        Route::get('/datatable', [JournalEntryController::class, 'datatable'])->name('datatable');

        // Bulk actions
        Route::post('/bulk-approve', [JournalEntryController::class, 'bulkApprove'])->name('bulk-approve');
        Route::post('/bulk-post', [JournalEntryController::class, 'bulkPost'])->name('bulk-post');

        Route::get('/', [JournalEntryController::class, 'index'])->name('index');
        Route::get('/create', [JournalEntryController::class, 'create'])->name('create');
        Route::post('/', [JournalEntryController::class, 'store'])->name('store');
        Route::get('/{id}', [JournalEntryController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [JournalEntryController::class, 'edit'])->name('edit');
        Route::put('/{id}', [JournalEntryController::class, 'update'])->name('update');
        Route::delete('/{id}', [JournalEntryController::class, 'destroy'])->name('destroy');

        // Workflow actions
        Route::post('/{id}/submit', [JournalEntryController::class, 'submit'])->name('submit');
        Route::post('/{id}/approve', [JournalEntryController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [JournalEntryController::class, 'reject'])->name('reject');
        Route::post('/{id}/post', [JournalEntryController::class, 'post'])->name('post');
        Route::post('/{id}/reverse', [JournalEntryController::class, 'reverse'])->name('reverse');
        Route::post('/{id}/request-edit', [JournalEntryController::class, 'requestEdit'])->name('request-edit');

        // Edit Request Management
        Route::post('/edit-requests/{editId}/approve', [JournalEntryController::class, 'approveEditRequest'])->name('edit-requests.approve');
        Route::post('/edit-requests/{editId}/reject', [JournalEntryController::class, 'rejectEditRequest'])->name('edit-requests.reject');
    });

    // Chart of Accounts
    Route::prefix('chart-of-accounts')->name('chart-of-accounts.')->group(function () {
        // DataTables endpoint (must be before {id} routes)
        Route::get('/datatable', [ChartOfAccountsController::class, 'datatable'])->name('datatable');

        Route::get('/', [ChartOfAccountsController::class, 'index'])->name('index');
        Route::get('/create', [ChartOfAccountsController::class, 'create'])->name('create');
        Route::post('/', [ChartOfAccountsController::class, 'store'])->name('store');
        Route::get('/{id}', [ChartOfAccountsController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [ChartOfAccountsController::class, 'edit'])->name('edit');
        Route::put('/{id}', [ChartOfAccountsController::class, 'update'])->name('update');
        Route::post('/{id}/deactivate', [ChartOfAccountsController::class, 'deactivate'])->name('deactivate');
        Route::post('/{id}/activate', [ChartOfAccountsController::class, 'activate'])->name('activate');

        // Account Groups
        Route::get('/groups/create', [ChartOfAccountsController::class, 'createGroup'])->name('groups.create');
        Route::post('/groups', [ChartOfAccountsController::class, 'storeGroup'])->name('groups.store');
        Route::put('/groups/{id}', [ChartOfAccountsController::class, 'updateGroup'])->name('groups.update');

        // Sub-Accounts
        Route::get('/{accountId}/sub-accounts', [ChartOfAccountsController::class, 'subAccounts'])->name('sub-accounts');
        Route::post('/{accountId}/sub-accounts', [ChartOfAccountsController::class, 'storeSubAccount'])->name('sub-accounts.store');

        // AJAX
        Route::get('/api/accounts', [ChartOfAccountsController::class, 'getAccountsJson'])->name('api.accounts');
    });

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/trial-balance', [ReportController::class, 'trialBalance'])->name('trial-balance');
        Route::get('/profit-loss', [ReportController::class, 'profitAndLoss'])->name('profit-loss');
        Route::get('/balance-sheet', [ReportController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('/cash-flow', [ReportController::class, 'cashFlow'])->name('cash-flow');
        Route::get('/general-ledger', [ReportController::class, 'generalLedger'])->name('general-ledger');
        Route::get('/account-activity', [ReportController::class, 'accountActivity'])->name('account-activity');
        Route::get('/bank-statement', [ReportController::class, 'bankStatement'])->name('bank-statement');
        Route::get('/aged-receivables', [ReportController::class, 'agedReceivables'])->name('aged-receivables');
        Route::get('/aged-payables', [ReportController::class, 'agedPayables'])->name('aged-payables');
        Route::get('/daily-audit', [ReportController::class, 'dailyAudit'])->name('daily-audit');

        // Saved Filters
        Route::post('/filters', [ReportController::class, 'saveFilter'])->name('filters.store');
        Route::delete('/filters/{id}', [ReportController::class, 'deleteFilter'])->name('filters.destroy');
        Route::get('/filters/{id}', [ReportController::class, 'loadFilter'])->name('filters.show');
    });

    // Credit Notes
    Route::prefix('credit-notes')->name('credit-notes.')->group(function () {
        // DataTables endpoint (must be before {id} routes)
        Route::get('/datatable', [CreditNoteController::class, 'datatable'])->name('datatable');

        // Bulk actions
        Route::post('/bulk-approve', [CreditNoteController::class, 'bulkApprove'])->name('bulk-approve');

        Route::get('/', [CreditNoteController::class, 'index'])->name('index');
        Route::get('/create', [CreditNoteController::class, 'create'])->name('create');
        Route::post('/', [CreditNoteController::class, 'store'])->name('store');
        Route::get('/{id}', [CreditNoteController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [CreditNoteController::class, 'edit'])->name('edit');
        Route::put('/{id}', [CreditNoteController::class, 'update'])->name('update');
        Route::post('/{id}/submit', [CreditNoteController::class, 'submit'])->name('submit');
        Route::post('/{id}/approve', [CreditNoteController::class, 'approve'])->name('approve');
        Route::post('/{id}/void', [CreditNoteController::class, 'void'])->name('void');
        Route::post('/{id}/process', [CreditNoteController::class, 'process'])->name('process');

        // AJAX
        Route::get('/api/patient/{patientId}/payments', [CreditNoteController::class, 'getPatientPayments'])->name('api.patient-payments');
    });

    // ==========================================
    // NEW ACCOUNTING MODULES (Role-Based Access)
    // ==========================================

    // === PETTY CASH MODULE ===
    Route::prefix('petty-cash')->name('petty-cash.')->middleware(['role:SUPERADMIN|ADMIN|ACCOUNTS'])->group(function () {
        Route::get('/', [PettyCashController::class, 'index'])->name('index');
        Route::get('/datatable', [PettyCashController::class, 'datatable'])->name('datatable');

        // Funds Management
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
    Route::prefix('transfers')->name('transfers.')->middleware(['role:SUPERADMIN|ADMIN|ACCOUNTS'])->group(function () {
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
    Route::prefix('bank-reconciliation')->name('bank-reconciliation.')->middleware(['role:SUPERADMIN|ADMIN|ACCOUNTS|AUDIT'])->group(function () {
        Route::get('/', [BankReconciliationController::class, 'index'])->name('index');
        Route::get('/datatable', [BankReconciliationController::class, 'datatable'])->name('datatable');
        Route::get('/create/{bank}', [BankReconciliationController::class, 'create'])->name('create');
        Route::post('/store/{bank}', [BankReconciliationController::class, 'store'])->name('store');
        Route::get('/{reconciliation}', [BankReconciliationController::class, 'show'])->name('show');
        Route::get('/{reconciliation}/edit', [BankReconciliationController::class, 'edit'])->name('edit');
        Route::put('/{reconciliation}', [BankReconciliationController::class, 'update'])->name('update');
        Route::post('/{reconciliation}/import', [BankReconciliationController::class, 'importStatement'])->name('import');

        // Statement Upload & Visual Reconciliation
        Route::post('/{reconciliation}/upload-statement', [BankReconciliationController::class, 'uploadStatement'])->name('upload-statement');
        Route::put('/{reconciliation}/update-details', [BankReconciliationController::class, 'updateDetails'])->name('update-details');
        Route::get('/{reconciliation}/statements', [BankReconciliationController::class, 'getStatements'])->name('statements');
        Route::get('/{reconciliation}/statement/{import}', [BankReconciliationController::class, 'getStatementContent'])->name('statement-content');
        Route::delete('/{reconciliation}/statement/{import}', [BankReconciliationController::class, 'deleteStatement'])->name('delete-statement');
        Route::post('/{reconciliation}/statement-item', [BankReconciliationController::class, 'addStatementItem'])->name('add-statement-item');

        // Matching
        Route::post('/{reconciliation}/match', [BankReconciliationController::class, 'matchItems'])->name('match');
        Route::post('/{reconciliation}/unmatch', [BankReconciliationController::class, 'unmatchItems'])->name('unmatch');
        Route::post('/{reconciliation}/outstanding', [BankReconciliationController::class, 'markOutstanding'])->name('outstanding');
        Route::post('/{reconciliation}/submit-review', [BankReconciliationController::class, 'submitForReview'])->name('submit-review');
        Route::post('/{reconciliation}/auto-match', [BankReconciliationController::class, 'autoMatch'])->name('auto-match');
        Route::post('/{reconciliation}/manual-match', [BankReconciliationController::class, 'manualMatch'])->name('manual-match');
        Route::post('/items/{item}/unmatch', [BankReconciliationController::class, 'unmatch'])->name('item-unmatch');
        Route::post('/{reconciliation}/adjustment', [BankReconciliationController::class, 'addAdjustment'])->name('adjustment');
        Route::post('/{reconciliation}/finalize', [BankReconciliationController::class, 'finalize'])->name('finalize');
        Route::post('/{reconciliation}/approve', [BankReconciliationController::class, 'approve'])->name('approve');
        Route::get('/{reconciliation}/export/pdf', [BankReconciliationController::class, 'exportPdf'])->name('export.pdf');
        Route::get('/{reconciliation}/export/excel', [BankReconciliationController::class, 'exportExcel'])->name('export.excel');
    });

    // === PATIENT DEPOSITS ===
    Route::prefix('patient-deposits')->name('patient-deposits.')->middleware(['role:SUPERADMIN|ADMIN|ACCOUNTS|BILLER'])->group(function () {
        Route::get('/', [PatientDepositController::class, 'index'])->name('index');
        Route::get('/datatable', [PatientDepositController::class, 'datatable'])->name('datatable');
        Route::get('/create', [PatientDepositController::class, 'create'])->name('create');
        Route::post('/', [PatientDepositController::class, 'store'])->name('store');
        Route::get('/search-patients', [PatientDepositController::class, 'searchPatients'])->name('search-patients');
        Route::get('/patient/{patient}/summary', [PatientDepositController::class, 'getPatientSummary'])->name('patient-summary');
        Route::get('/{patientDeposit}', [PatientDepositController::class, 'show'])->name('show');
        Route::get('/{patientDeposit}/print-receipt', [PatientDepositController::class, 'printReceipt'])->name('print-receipt');
        Route::post('/{patientDeposit}/apply', [PatientDepositController::class, 'apply'])->name('apply');
        Route::post('/{patientDeposit}/refund', [PatientDepositController::class, 'refund'])->name('refund');
        Route::get('/export', [PatientDepositController::class, 'export'])->name('export');
    });

    // === CASH FLOW FORECAST ===
    Route::prefix('cash-flow-forecast')->name('cash-flow-forecast.')->middleware(['role:SUPERADMIN|ADMIN|ACCOUNTS'])->group(function () {
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
    Route::prefix('fixed-assets')->name('fixed-assets.')->middleware(['role:SUPERADMIN|ADMIN|ACCOUNTS'])->group(function () {
        Route::get('/', [FixedAssetController::class, 'index'])->name('index');
        Route::get('/datatable', [FixedAssetController::class, 'datatable'])->name('datatable');
        Route::get('/create', [FixedAssetController::class, 'create'])->name('create');
        Route::post('/', [FixedAssetController::class, 'store'])->name('store');
        Route::get('/search', [FixedAssetController::class, 'searchAssets'])->name('search');
        Route::get('/export', [FixedAssetController::class, 'export'])->name('export');

        // Categories (before {fixedAsset} routes)
        Route::get('/categories', [FixedAssetController::class, 'categories'])->name('categories.index');
        Route::post('/categories', [FixedAssetController::class, 'storeCategory'])->name('categories.store');
        Route::get('/categories/{category}/defaults', [FixedAssetController::class, 'getCategoryDefaults'])->name('categories.defaults');

        // Depreciation
        Route::post('/depreciation/run', [FixedAssetController::class, 'runDepreciation'])->name('depreciation.run');

        // Asset CRUD
        Route::get('/{fixedAsset}', [FixedAssetController::class, 'show'])->name('show');
        Route::get('/{fixedAsset}/edit', [FixedAssetController::class, 'edit'])->name('edit');
        Route::put('/{fixedAsset}', [FixedAssetController::class, 'update'])->name('update');
        Route::get('/{fixedAsset}/json', [FixedAssetController::class, 'getAsset'])->name('json');
        Route::get('/{fixedAsset}/get-asset', [FixedAssetController::class, 'getAsset'])->name('get-asset');
        Route::get('/{fixedAsset}/depreciation-history', [FixedAssetController::class, 'getDepreciationHistory'])->name('depreciation-history');
        Route::post('/{fixedAsset}/void', [FixedAssetController::class, 'void'])->name('void');
        Route::post('/{fixedAsset}/dispose', [FixedAssetController::class, 'dispose'])->name('dispose');
    });

    // === LIABILITIES ===
    Route::prefix('liabilities')->name('liabilities.')->middleware(['role:SUPERADMIN|ADMIN|ACCOUNTS'])->group(function () {
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
    Route::prefix('leases')->name('leases.')->middleware(['role:SUPERADMIN|ADMIN|ACCOUNTS'])->group(function () {
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
    Route::prefix('cost-centers')->name('cost-centers.')->middleware(['role:SUPERADMIN|ADMIN|ACCOUNTS'])->group(function () {
        Route::get('/', [CostCenterController::class, 'index'])->name('index');
        Route::get('/datatable', [CostCenterController::class, 'datatable'])->name('datatable');
        Route::get('/create', [CostCenterController::class, 'create'])->name('create');
        Route::post('/', [CostCenterController::class, 'store'])->name('store');
        Route::get('/{costCenter}', [CostCenterController::class, 'show'])->name('show');
        Route::get('/{costCenter}/edit', [CostCenterController::class, 'edit'])->name('edit');
        Route::put('/{costCenter}', [CostCenterController::class, 'update'])->name('update');
        Route::get('/{costCenter}/budgets', [CostCenterController::class, 'budgets'])->name('budgets');
        Route::post('/{costCenter}/budgets', [CostCenterController::class, 'storeBudget'])->name('budgets.store');
        Route::put('/{costCenter}/budgets/{budget}', [CostCenterController::class, 'updateBudget'])->name('budgets.update');
        Route::get('/allocations/index', [CostCenterController::class, 'allocations'])->name('allocations.index');
        Route::post('/allocations', [CostCenterController::class, 'storeAllocation'])->name('allocations.store');
        Route::post('/allocations/run', [CostCenterController::class, 'runAllocation'])->name('allocations.run');
        Route::get('/{costCenter}/report', [CostCenterController::class, 'report'])->name('report');
        Route::get('/{costCenter}/report/pdf', [CostCenterController::class, 'exportReportPdf'])->name('report.pdf');
        Route::get('/{costCenter}/report/excel', [CostCenterController::class, 'exportReportExcel'])->name('report.excel');
        Route::get('/export/pdf', [CostCenterController::class, 'exportPdf'])->name('export.pdf');
        Route::get('/export/excel', [CostCenterController::class, 'exportExcel'])->name('export.excel');
    });

    // === CAPEX (Capital Expenditure) ===
    Route::prefix('capex')->name('capex.')->middleware(['role:SUPERADMIN|ADMIN|ACCOUNTS'])->group(function () {
        Route::get('/', [CapexController::class, 'index'])->name('index');
        Route::get('/datatable', [CapexController::class, 'datatable'])->name('datatable');
        Route::get('/create', [CapexController::class, 'create'])->name('create');
        Route::post('/', [CapexController::class, 'store'])->name('store');
        Route::get('/budget-overview', [CapexController::class, 'budgetOverview'])->name('budget-overview');
        Route::get('/export', [CapexController::class, 'export'])->name('export');
        Route::get('/{capex}', [CapexController::class, 'show'])->name('show');
        Route::get('/{capex}/edit', [CapexController::class, 'edit'])->name('edit');
        Route::put('/{capex}', [CapexController::class, 'update'])->name('update');
        Route::post('/{capex}/submit', [CapexController::class, 'submit'])->name('submit');
        Route::post('/{capex}/approve', [CapexController::class, 'approve'])->name('approve');
        Route::post('/{capex}/reject', [CapexController::class, 'reject'])->name('reject');
        Route::post('/{capex}/request-revision', [CapexController::class, 'requestRevision'])->name('request-revision');
        Route::post('/{capex}/start', [CapexController::class, 'startExecution'])->name('start');
        Route::post('/{capex}/expense', [CapexController::class, 'recordExpense'])->name('record-expense');
        Route::post('/{capex}/complete', [CapexController::class, 'complete'])->name('complete');
    });

    // === BUDGETS ===
    Route::prefix('budgets')->name('budgets.')->middleware(['role:SUPERADMIN|ADMIN|ACCOUNTS'])->group(function () {
        Route::get('/', [BudgetController::class, 'index'])->name('index');
        Route::get('/datatable', [BudgetController::class, 'datatable'])->name('datatable');
        Route::get('/create', [BudgetController::class, 'create'])->name('create');
        Route::post('/', [BudgetController::class, 'store'])->name('store');
        Route::get('/variance-report', [BudgetController::class, 'varianceReport'])->name('variance-report');
        Route::get('/{budget}', [BudgetController::class, 'show'])->name('show');
        Route::get('/{budget}/edit', [BudgetController::class, 'edit'])->name('edit');
        Route::put('/{budget}', [BudgetController::class, 'update'])->name('update');
        Route::post('/{budget}/submit', [BudgetController::class, 'submit'])->name('submit');
        Route::post('/{budget}/approve', [BudgetController::class, 'approve'])->name('approve');
        Route::post('/{budget}/reject', [BudgetController::class, 'reject'])->name('reject');
        Route::get('/{budget}/export', [BudgetController::class, 'export'])->name('export');
    });

    // === FINANCIAL KPIs ===
    Route::prefix('kpi')->name('kpi.')->middleware(['role:SUPERADMIN|ADMIN|ACCOUNTS|AUDIT'])->group(function () {
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

    // === STATUTORY REMITTANCES (PAYE, Pension, NHF, etc.) ===
    Route::prefix('statutory-remittances')->name('statutory-remittances.')->middleware(['role:SUPERADMIN|ADMIN|ACCOUNTS'])->group(function () {
        Route::get('/', [\App\Http\Controllers\Accounting\StatutoryRemittanceController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Accounting\StatutoryRemittanceController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Accounting\StatutoryRemittanceController::class, 'store'])->name('store');
        Route::get('/balances', [\App\Http\Controllers\Accounting\StatutoryRemittanceController::class, 'getOutstandingBalances'])->name('balances');
        Route::get('/{statutoryRemittance}', [\App\Http\Controllers\Accounting\StatutoryRemittanceController::class, 'show'])->name('show');
        Route::get('/{statutoryRemittance}/edit', [\App\Http\Controllers\Accounting\StatutoryRemittanceController::class, 'edit'])->name('edit');
        Route::put('/{statutoryRemittance}', [\App\Http\Controllers\Accounting\StatutoryRemittanceController::class, 'update'])->name('update');
        Route::delete('/{statutoryRemittance}', [\App\Http\Controllers\Accounting\StatutoryRemittanceController::class, 'destroy'])->name('destroy');
        Route::post('/{statutoryRemittance}/submit', [\App\Http\Controllers\Accounting\StatutoryRemittanceController::class, 'submit'])->name('submit');
        Route::post('/{statutoryRemittance}/approve', [\App\Http\Controllers\Accounting\StatutoryRemittanceController::class, 'approve'])->name('approve');
        Route::post('/{statutoryRemittance}/pay', [\App\Http\Controllers\Accounting\StatutoryRemittanceController::class, 'pay'])->name('pay');
        Route::post('/{statutoryRemittance}/void', [\App\Http\Controllers\Accounting\StatutoryRemittanceController::class, 'void'])->name('void');
    });

});
