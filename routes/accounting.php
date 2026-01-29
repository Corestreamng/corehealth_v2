<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Accounting\AccountingController;
use App\Http\Controllers\Accounting\JournalEntryController;
use App\Http\Controllers\Accounting\ChartOfAccountsController;
use App\Http\Controllers\Accounting\ReportController;
use App\Http\Controllers\Accounting\CreditNoteController;
use App\Http\Controllers\Accounting\OpeningBalanceController;

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
        Route::post('/{id}/approve', [CreditNoteController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [CreditNoteController::class, 'reject'])->name('reject');
        Route::post('/{id}/apply', [CreditNoteController::class, 'apply'])->name('apply');

        // AJAX
        Route::get('/api/patient/{patientId}/invoices', [CreditNoteController::class, 'getPatientInvoices'])->name('api.patient-invoices');
    });

});
