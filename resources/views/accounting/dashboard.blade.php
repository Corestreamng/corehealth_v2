@extends('admin.layouts.app')
@section('title', 'Accounting Dashboard')
@section('page_name', 'Accounting')
@section('subpage_name', 'Dashboard')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Period Info Banner -->
        @if($currentPeriod)
        <div class="alert alert-info d-flex justify-content-between align-items-center mb-4">
            <div>
                <i class="mdi mdi-calendar-clock mr-2"></i>
                <strong>Current Period:</strong> {{ $currentPeriod->name }}
                ({{ $currentPeriod->start_date->format('M d') }} - {{ $currentPeriod->end_date->format('M d, Y') }})
            </div>
            <a href="{{ route('accounting.periods') }}" class="btn btn-outline-info btn-sm">
                <i class="mdi mdi-cog"></i> Manage Periods
            </a>
        </div>
        @else
        <div class="alert alert-warning mb-4">
            <i class="mdi mdi-alert mr-2"></i>
            <strong>No Active Period!</strong> Please create a fiscal year and period to start recording transactions.
            <a href="{{ route('accounting.periods') }}" class="btn btn-warning btn-sm ml-2">Create Now</a>
        </div>
        @endif

        <!-- Quick Actions Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card card-modern quick-actions-header">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0"><i class="mdi mdi-lightning-bolt mr-2"></i>Quick Actions</h5>
                            <span class="badge badge-primary">Frequently Used</span>
                        </div>
                        <div class="row">
                            @can('accounting.journal.create')
                            <div class="col-lg-2 col-md-4 col-6 mb-3">
                                <a href="{{ route('accounting.journal-entries.create') }}" class="quick-action-card bg-primary">
                                    <div class="icon-wrapper">
                                        <i class="mdi mdi-plus-circle"></i>
                                    </div>
                                    <span class="action-title">New Entry</span>
                                    <small class="action-desc">Create journal entry</small>
                                </a>
                            </div>
                            @endcan
                            <div class="col-lg-2 col-md-4 col-6 mb-3">
                                <a href="{{ route('accounting.journal-entries.index') }}" class="quick-action-card bg-info">
                                    <div class="icon-wrapper">
                                        <i class="mdi mdi-book-open-page-variant"></i>
                                    </div>
                                    <span class="action-title">Entries</span>
                                    <small class="action-desc">View all entries</small>
                                </a>
                            </div>
                            @can('accounting.credit-notes.create')
                            <div class="col-lg-2 col-md-4 col-6 mb-3">
                                <a href="{{ route('accounting.credit-notes.create') }}" class="quick-action-card bg-danger">
                                    <div class="icon-wrapper">
                                        <i class="mdi mdi-cash-refund"></i>
                                    </div>
                                    <span class="action-title">Credit Note</span>
                                    <small class="action-desc">Issue refund</small>
                                </a>
                            </div>
                            @endcan
                            <div class="col-lg-2 col-md-4 col-6 mb-3">
                                <a href="{{ route('accounting.reports.trial-balance') }}" class="quick-action-card bg-success">
                                    <div class="icon-wrapper">
                                        <i class="mdi mdi-scale-balance"></i>
                                    </div>
                                    <span class="action-title">Trial Balance</span>
                                    <small class="action-desc">Account balances</small>
                                </a>
                            </div>
                            <div class="col-lg-2 col-md-4 col-6 mb-3">
                                <a href="{{ route('accounting.reports.profit-loss') }}" class="quick-action-card bg-warning">
                                    <div class="icon-wrapper">
                                        <i class="mdi mdi-chart-line"></i>
                                    </div>
                                    <span class="action-title">P&L</span>
                                    <small class="action-desc">Income statement</small>
                                </a>
                            </div>
                            <div class="col-lg-2 col-md-4 col-6 mb-3">
                                <a href="{{ route('accounting.reports.balance-sheet') }}" class="quick-action-card bg-secondary">
                                    <div class="icon-wrapper">
                                        <i class="mdi mdi-file-document"></i>
                                    </div>
                                    <span class="action-title">Balance Sheet</span>
                                    <small class="action-desc">Financial position</small>
                                </a>
                            </div>
                            <div class="col-lg-2 col-md-4 col-6 mb-3">
                                <a href="{{ route('accounting.reports.bank-statement') }}" class="quick-action-card bg-cyan">
                                    <div class="icon-wrapper">
                                        <i class="mdi mdi-bank"></i>
                                    </div>
                                    <span class="action-title">Bank Statement</span>
                                    <small class="action-desc">Bank activity</small>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Cards -->
        <div class="row mb-4">
            <div class="col-12">
                <h5 class="mb-3"><i class="mdi mdi-view-grid mr-2"></i>Navigate To</h5>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <a href="{{ route('accounting.chart-of-accounts.index') }}" class="nav-card nav-card-primary">
                    <div class="nav-card-icon">
                        <i class="mdi mdi-file-tree"></i>
                    </div>
                    <div class="nav-card-content">
                        <h6>Chart of Accounts</h6>
                        <p>Manage account structure</p>
                    </div>
                    <i class="mdi mdi-chevron-right nav-card-arrow"></i>
                </a>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <a href="{{ route('accounting.reports.index') }}" class="nav-card nav-card-success">
                    <div class="nav-card-icon">
                        <i class="mdi mdi-chart-box"></i>
                    </div>
                    <div class="nav-card-content">
                        <h6>Financial Reports</h6>
                        <p>View all reports</p>
                    </div>
                    <i class="mdi mdi-chevron-right nav-card-arrow"></i>
                </a>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <a href="{{ route('accounting.reports.general-ledger') }}" class="nav-card nav-card-info">
                    <div class="nav-card-icon">
                        <i class="mdi mdi-book-multiple"></i>
                    </div>
                    <div class="nav-card-content">
                        <h6>General Ledger</h6>
                        <p>Account transactions</p>
                    </div>
                    <i class="mdi mdi-chevron-right nav-card-arrow"></i>
                </a>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <a href="{{ route('accounting.reports.bank-statement') }}" class="nav-card nav-card-cyan">
                    <div class="nav-card-icon">
                        <i class="mdi mdi-bank"></i>
                    </div>
                    <div class="nav-card-content">
                        <h6>Bank Statement</h6>
                        <p>Bank account activity</p>
                    </div>
                    <i class="mdi mdi-chevron-right nav-card-arrow"></i>
                </a>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <a href="{{ route('accounting.periods') }}" class="nav-card nav-card-warning">
                    <div class="nav-card-icon">
                        <i class="mdi mdi-calendar-range"></i>
                    </div>
                    <div class="nav-card-content">
                        <h6>Fiscal Periods</h6>
                        <p>Manage periods</p>
                    </div>
                    <i class="mdi mdi-chevron-right nav-card-arrow"></i>
                </a>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <a href="{{ route('accounting.reports.aged-receivables') }}" class="nav-card nav-card-danger">
                    <div class="nav-card-icon">
                        <i class="mdi mdi-clock-alert"></i>
                    </div>
                    <div class="nav-card-content">
                        <h6>Aged Receivables</h6>
                        <p>Outstanding receivables</p>
                    </div>
                    <i class="mdi mdi-chevron-right nav-card-arrow"></i>
                </a>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <a href="{{ route('accounting.reports.aged-payables') }}" class="nav-card nav-card-secondary">
                    <div class="nav-card-icon">
                        <i class="mdi mdi-account-clock"></i>
                    </div>
                    <div class="nav-card-content">
                        <h6>Aged Payables</h6>
                        <p>Outstanding payables</p>
                    </div>
                    <i class="mdi mdi-chevron-right nav-card-arrow"></i>
                </a>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <a href="{{ route('accounting.credit-notes.index') }}" class="nav-card nav-card-purple">
                    <div class="nav-card-icon">
                        <i class="mdi mdi-note-text"></i>
                    </div>
                    <div class="nav-card-content">
                        <h6>Credit Notes</h6>
                        <p>Manage refunds</p>
                    </div>
                    <i class="mdi mdi-chevron-right nav-card-arrow"></i>
                </a>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <a href="{{ route('accounting.reports.cash-flow') }}" class="nav-card nav-card-teal">
                    <div class="nav-card-icon">
                        <i class="mdi mdi-cash-multiple"></i>
                    </div>
                    <div class="nav-card-content">
                        <h6>Cash Flow</h6>
                        <p>Cash movements</p>
                    </div>
                    <i class="mdi mdi-chevron-right nav-card-arrow"></i>
                </a>
            </div>
        </div>

        <!-- Advanced Modules Section -->
        <div class="row mb-4">
            <div class="col-12">
                <h5 class="mb-3"><i class="mdi mdi-briefcase-account mr-2"></i>Advanced Modules</h5>
            </div>
            <!-- Bank Reconciliation -->
            <div class="col-lg-3 col-md-6 mb-3">
                <a href="{{ route('accounting.bank-reconciliation.index') }}" class="nav-card nav-card-info">
                    <div class="nav-card-icon">
                        <i class="mdi mdi-bank-check"></i>
                    </div>
                    <div class="nav-card-content">
                        <h6>Bank Reconciliation</h6>
                        <p>Match bank statements</p>
                    </div>
                    <i class="mdi mdi-chevron-right nav-card-arrow"></i>
                </a>
            </div>
            <!-- Patient Deposits -->
            <div class="col-lg-3 col-md-6 mb-3">
                <a href="{{ route('accounting.patient-deposits.index') }}" class="nav-card nav-card-success">
                    <div class="nav-card-icon">
                        <i class="mdi mdi-account-cash"></i>
                    </div>
                    <div class="nav-card-content">
                        <h6>Patient Deposits</h6>
                        <p>Manage prepayments</p>
                    </div>
                    <i class="mdi mdi-chevron-right nav-card-arrow"></i>
                </a>
            </div>
            <!-- Fixed Assets -->
            <div class="col-lg-3 col-md-6 mb-3">
                <a href="{{ route('accounting.fixed-assets.index') }}" class="nav-card nav-card-primary">
                    <div class="nav-card-icon">
                        <i class="mdi mdi-desktop-classic"></i>
                    </div>
                    <div class="nav-card-content">
                        <h6>Fixed Assets</h6>
                        <p>Asset management & depreciation</p>
                    </div>
                    <i class="mdi mdi-chevron-right nav-card-arrow"></i>
                </a>
            </div>
            <!-- Cost Centers -->
            <div class="col-lg-3 col-md-6 mb-3">
                <a href="{{ route('accounting.cost-centers.index') }}" class="nav-card nav-card-warning">
                    <div class="nav-card-icon">
                        <i class="mdi mdi-domain"></i>
                    </div>
                    <div class="nav-card-content">
                        <h6>Cost Centers</h6>
                        <p>Departmental tracking</p>
                    </div>
                    <i class="mdi mdi-chevron-right nav-card-arrow"></i>
                </a>
            </div>
            <!-- Budgets -->
            <div class="col-lg-3 col-md-6 mb-3">
                <a href="{{ route('accounting.budgets.index') }}" class="nav-card nav-card-cyan">
                    <div class="nav-card-icon">
                        <i class="mdi mdi-calculator-variant"></i>
                    </div>
                    <div class="nav-card-content">
                        <h6>Budgets</h6>
                        <p>Budget planning & variance</p>
                    </div>
                    <i class="mdi mdi-chevron-right nav-card-arrow"></i>
                </a>
            </div>
            <!-- Cash Flow Forecast -->
            <div class="col-lg-3 col-md-6 mb-3">
                <a href="{{ route('accounting.cash-flow-forecast.index') }}" class="nav-card nav-card-purple">
                    <div class="nav-card-icon">
                        <i class="mdi mdi-chart-timeline-variant"></i>
                    </div>
                    <div class="nav-card-content">
                        <h6>Cash Flow Forecast</h6>
                        <p>Liquidity projections</p>
                    </div>
                    <i class="mdi mdi-chevron-right nav-card-arrow"></i>
                </a>
            </div>
            <!-- Capital Expenditure -->
            <div class="col-lg-3 col-md-6 mb-3">
                <a href="{{ route('accounting.capex.index') }}" class="nav-card nav-card-danger">
                    <div class="nav-card-icon">
                        <i class="mdi mdi-factory"></i>
                    </div>
                    <div class="nav-card-content">
                        <h6>Capital Expenditure</h6>
                        <p>Capex management</p>
                    </div>
                    <i class="mdi mdi-chevron-right nav-card-arrow"></i>
                </a>
            </div>
            <!-- Liabilities -->
            <div class="col-lg-3 col-md-6 mb-3">
                <a href="{{ route('accounting.liabilities.index') }}" class="nav-card nav-card-secondary">
                    <div class="nav-card-icon">
                        <i class="mdi mdi-file-document-outline"></i>
                    </div>
                    <div class="nav-card-content">
                        <h6>Liabilities</h6>
                        <p>Loans & debt management</p>
                    </div>
                    <i class="mdi mdi-chevron-right nav-card-arrow"></i>
                </a>
            </div>
            <!-- Leases (IFRS 16) -->
            <div class="col-lg-3 col-md-6 mb-3">
                <a href="{{ route('accounting.leases.index') }}" class="nav-card nav-card-teal">
                    <div class="nav-card-icon">
                        <i class="mdi mdi-home-city"></i>
                    </div>
                    <div class="nav-card-content">
                        <h6>Leases (IFRS 16)</h6>
                        <p>ROU assets & lease liabilities</p>
                    </div>
                    <i class="mdi mdi-chevron-right nav-card-arrow"></i>
                </a>
            </div>
            <!-- Petty Cash -->
            <div class="col-lg-3 col-md-6 mb-3">
                <a href="{{ route('accounting.petty-cash.index') }}" class="nav-card nav-card-warning">
                    <div class="nav-card-icon">
                        <i class="mdi mdi-cash-register"></i>
                    </div>
                    <div class="nav-card-content">
                        <h6>Petty Cash</h6>
                        <p>Manage petty cash funds</p>
                    </div>
                    <i class="mdi mdi-chevron-right nav-card-arrow"></i>
                </a>
            </div>
            <!-- Fund Transfers -->
            <div class="col-lg-3 col-md-6 mb-3">
                <a href="{{ route('accounting.transfers.index') }}" class="nav-card nav-card-info">
                    <div class="nav-card-icon">
                        <i class="mdi mdi-bank-transfer"></i>
                    </div>
                    <div class="nav-card-content">
                        <h6>Fund Transfers</h6>
                        <p>Inter-account transfers</p>
                    </div>
                    <i class="mdi mdi-chevron-right nav-card-arrow"></i>
                </a>
            </div>
            <!-- Financial KPIs -->
            <div class="col-lg-3 col-md-6 mb-3">
                <a href="{{ route('accounting.kpi.dashboard') }}" class="nav-card nav-card-success">
                    <div class="nav-card-icon">
                        <i class="mdi mdi-gauge"></i>
                    </div>
                    <div class="nav-card-content">
                        <h6>Financial KPIs</h6>
                        <p>Key performance indicators</p>
                    </div>
                    <i class="mdi mdi-chevron-right nav-card-arrow"></i>
                </a>
            </div>
        </div>

        <!-- Stats Cards Row 1 -->
        <div class="row mb-3">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-primary" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-book-open-page-variant mr-1"></i> Total Entries</h5>
                    <div class="value text-primary">{{ number_format($stats['total_entries'] ?? 0) }}</div>
                    <small class="text-muted">This period</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-warning" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-clock-outline mr-1"></i> Pending Approval</h5>
                    <div class="value text-warning">{{ number_format($stats['pending_entries'] ?? 0) }}</div>
                    <small class="text-muted">Requires review</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-success" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-arrow-up-bold mr-1"></i> Monthly Revenue</h5>
                    <div class="value text-success">₦{{ number_format($stats['monthly_revenue'] ?? 0, 2) }}</div>
                    <small class="text-muted">Income accounts</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-danger" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-arrow-down-bold mr-1"></i> Monthly Expenses</h5>
                    <div class="value text-danger">₦{{ number_format($stats['monthly_expenses'] ?? 0, 2) }}</div>
                    <small class="text-muted">Expense accounts</small>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex flex-wrap gap-2">
                    @can('accounting.journal.create')
                    <a href="{{ route('accounting.journal-entries.create') }}" class="btn btn-primary">
                        <i class="mdi mdi-plus"></i> New Journal Entry
                    </a>
                    @endcan
                    @can('accounting.credit-notes.create')
                    <a href="{{ route('accounting.credit-notes.create') }}" class="btn btn-outline-danger">
                        <i class="mdi mdi-cash-refund"></i> New Credit Note
                    </a>
                    @endcan
                    <a href="{{ route('accounting.reports.trial-balance') }}" class="btn btn-outline-info">
                        <i class="mdi mdi-scale-balance"></i> Trial Balance
                    </a>
                    <a href="{{ route('accounting.reports.profit-loss') }}" class="btn btn-outline-success">
                        <i class="mdi mdi-chart-line"></i> P&L Statement
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Quick Trial Balance -->
            <div class="col-lg-6 mb-4">
                <div class="card-modern">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="mdi mdi-scale-balance mr-2"></i> Quick Trial Balance</h5>
                        <a href="{{ route('accounting.reports.trial-balance') }}" class="btn btn-sm btn-outline-primary">
                            Full Report
                        </a>
                    </div>
                    <div class="card-body">
                        @if($trialBalance && count($trialBalance['accounts'] ?? []) > 0)
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Account</th>
                                            <th class="text-right">Debit</th>
                                            <th class="text-right">Credit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach(array_slice($trialBalance['accounts'] ?? [], 0, 8) as $account)
                                            <tr>
                                                <td>{{ $account['account_code'] ?? '' }} - {{ Str::limit($account['account_name'] ?? $account['name'] ?? '', 25) }}</td>
                                                <td class="text-right">{{ ($account['debit'] ?? 0) > 0 ? number_format($account['debit'], 2) : '-' }}</td>
                                                <td class="text-right">{{ ($account['credit'] ?? 0) > 0 ? number_format($account['credit'], 2) : '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="font-weight-bold">
                                        <tr class="table-secondary">
                                            <td>Total</td>
                                            <td class="text-right">₦{{ number_format($trialBalance['total_debit'] ?? $trialBalance['total_debits'] ?? 0, 2) }}</td>
                                            <td class="text-right">₦{{ number_format($trialBalance['total_credit'] ?? $trialBalance['total_credits'] ?? 0, 2) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-4 text-muted">
                                <i class="mdi mdi-chart-box-outline mdi-48px"></i>
                                <p class="mt-2">No data for current period</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Pending Approvals -->
            <div class="col-lg-6 mb-4">
                <div class="card-modern">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="mdi mdi-clock-alert mr-2 text-warning"></i> Pending Approvals</h5>
                        <a href="{{ route('accounting.journal-entries.index', ['status' => 'pending']) }}" class="btn btn-sm btn-outline-warning">
                            View All
                        </a>
                    </div>
                    <div class="card-body">
                        @if(isset($pendingEntries) && $pendingEntries->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Entry #</th>
                                            <th>Date</th>
                                            <th>Description</th>
                                            <th class="text-right">Amount</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($pendingEntries as $entry)
                                            <tr>
                                                <td><code>{{ $entry->entry_number }}</code></td>
                                                <td>{{ $entry->entry_date->format('M d') }}</td>
                                                <td>{{ Str::limit($entry->description, 25) }}</td>
                                                <td class="text-right">₦{{ number_format($entry->lines->sum('debit_amount'), 2) }}</td>
                                                <td>
                                                    <a href="{{ route('accounting.journal-entries.show', $entry->id) }}" class="btn btn-sm btn-info">
                                                        <i class="mdi mdi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-4 text-muted">
                                <i class="mdi mdi-check-circle-outline mdi-48px text-success"></i>
                                <p class="mt-2">No pending approvals!</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Journal Entries -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card-modern">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="mdi mdi-history mr-2"></i> Recent Journal Entries</h5>
                        <a href="{{ route('accounting.journal-entries.index') }}" class="btn btn-sm btn-outline-primary">
                            View All
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Entry #</th>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th class="text-right">Amount</th>
                                        <th>Status</th>
                                        <th>Created By</th>
                                        <th width="60"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentEntries ?? [] as $entry)
                                        <tr>
                                            <td>
                                                <a href="{{ route('accounting.journal-entries.show', $entry->id) }}">
                                                    <code>{{ $entry->entry_number }}</code>
                                                </a>
                                            </td>
                                            <td>{{ $entry->entry_date->format('M d, Y') }}</td>
                                            <td>
                                                @php
                                                    $typeColors = ['manual' => 'info', 'automated' => 'secondary', 'reversal' => 'dark', 'adjustment' => 'warning'];
                                                @endphp
                                                <span class="badge badge-{{ $typeColors[$entry->entry_type] ?? 'secondary' }}">
                                                    {{ ucfirst($entry->entry_type) }}
                                                </span>
                                            </td>
                                            <td>{{ Str::limit($entry->description, 35) }}</td>
                                            <td class="text-right">₦{{ number_format($entry->lines->sum('debit_amount'), 2) }}</td>
                                            <td>
                                                @php
                                                    $statusColors = [
                                                        'draft' => 'secondary',
                                                        'pending' => 'warning',
                                                        'approved' => 'info',
                                                        'rejected' => 'danger',
                                                        'posted' => 'success',
                                                        'reversed' => 'dark',
                                                    ];
                                                @endphp
                                                <span class="badge badge-{{ $statusColors[$entry->status] ?? 'secondary' }}">
                                                    {{ ucfirst($entry->status) }}
                                                </span>
                                            </td>
                                            <td>{{ $entry->createdBy->name ?? 'System' }}</td>
                                            <td>
                                                <a href="{{ route('accounting.journal-entries.show', $entry->id) }}" class="btn btn-sm btn-outline-info">
                                                    <i class="mdi mdi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                <i class="mdi mdi-book-open-outline mdi-36px"></i>
                                                <p class="mt-2 mb-0">No journal entries yet</p>
                                                @can('accounting.journal.create')
                                                <a href="{{ route('accounting.journal-entries.create') }}" class="btn btn-primary btn-sm mt-2">
                                                    Create First Entry
                                                </a>
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="row">
            <div class="col-12">
                <div class="card-modern">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="mdi mdi-link-variant mr-2"></i> Quick Links</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 col-6 mb-3">
                                <a href="{{ route('accounting.chart-of-accounts.index') }}" class="btn btn-outline-primary btn-block quick-link-card w-100 py-3">
                                    <i class="mdi mdi-file-tree mdi-24px d-block mb-1"></i>
                                    Chart of Accounts
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="{{ route('accounting.reports.trial-balance') }}" class="btn btn-outline-success btn-block quick-link-card w-100 py-3">
                                    <i class="mdi mdi-scale-balance mdi-24px d-block mb-1"></i>
                                    Trial Balance
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="{{ route('accounting.reports.profit-loss') }}" class="btn btn-outline-info btn-block quick-link-card w-100 py-3">
                                    <i class="mdi mdi-chart-line mdi-24px d-block mb-1"></i>
                                    Profit & Loss
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="{{ route('accounting.reports.balance-sheet') }}" class="btn btn-outline-warning btn-block quick-link-card w-100 py-3">
                                    <i class="mdi mdi-file-document mdi-24px d-block mb-1"></i>
                                    Balance Sheet
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="{{ route('accounting.credit-notes.index') }}" class="btn btn-outline-danger btn-block quick-link-card w-100 py-3">
                                    <i class="mdi mdi-cash-refund mdi-24px d-block mb-1"></i>
                                    Credit Notes
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="{{ route('accounting.periods') }}" class="btn btn-outline-secondary btn-block quick-link-card w-100 py-3">
                                    <i class="mdi mdi-calendar-clock mdi-24px d-block mb-1"></i>
                                    Fiscal Periods
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="{{ route('accounting.reports.general-ledger') }}" class="btn btn-outline-dark btn-block quick-link-card w-100 py-3">
                                    <i class="mdi mdi-book-open-page-variant mdi-24px d-block mb-1"></i>
                                    General Ledger
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="{{ route('accounting.reports.index') }}" class="btn btn-outline-primary btn-block quick-link-card w-100 py-3">
                                    <i class="mdi mdi-chart-box mdi-24px d-block mb-1"></i>
                                    All Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .card-modern {
        border: none;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border-radius: 12px;
        margin-bottom: 1.5rem;
    }

    /* Quick Actions Cards */
    .quick-actions-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    .quick-actions-header h5 {
        color: white;
    }
    .quick-action-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 1.5rem 0.5rem;
        border-radius: 12px;
        text-decoration: none;
        color: white;
        transition: all 0.3s ease;
        text-align: center;
        height: 120px;
        position: relative;
        overflow: hidden;
    }
    .quick-action-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255,255,255,0);
        transition: all 0.3s ease;
    }
    .quick-action-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        color: white;
        text-decoration: none;
    }
    .quick-action-card:hover::before {
        background: rgba(255,255,255,0.1);
    }
    .quick-action-card .icon-wrapper {
        font-size: 2.5rem;
        margin-bottom: 0.5rem;
        position: relative;
        z-index: 1;
    }
    .quick-action-card .action-title {
        font-weight: 600;
        font-size: 0.95rem;
        display: block;
        position: relative;
        z-index: 1;
    }
    .quick-action-card .action-desc {
        font-size: 0.75rem;
        opacity: 0.9;
        display: block;
        position: relative;
        z-index: 1;
    }

    /* Navigation Cards */
    .nav-card {
        display: flex;
        align-items: center;
        padding: 1.25rem;
        background: white;
        border-radius: 12px;
        text-decoration: none;
        transition: all 0.3s ease;
        border: 2px solid transparent;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        height: 100%;
    }
    .nav-card:hover {
        transform: translateX(5px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        text-decoration: none;
    }
    .nav-card-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    .nav-card-content {
        flex: 1;
    }
    .nav-card-content h6 {
        margin: 0 0 0.25rem 0;
        font-size: 1rem;
        font-weight: 600;
        color: #212529;
    }
    .nav-card-content p {
        margin: 0;
        font-size: 0.8rem;
        color: #6c757d;
    }
    .nav-card-arrow {
        font-size: 1.5rem;
        opacity: 0.5;
        transition: all 0.3s ease;
    }
    .nav-card:hover .nav-card-arrow {
        opacity: 1;
        transform: translateX(3px);
    }

    /* Color variants for nav cards */
    .nav-card-primary .nav-card-icon { background: #e7f1ff; color: #007bff; }
    .nav-card-primary:hover { border-color: #007bff; }
    .nav-card-success .nav-card-icon { background: #d4edda; color: #28a745; }
    .nav-card-success:hover { border-color: #28a745; }
    .nav-card-info .nav-card-icon { background: #d1ecf1; color: #17a2b8; }
    .nav-card-info:hover { border-color: #17a2b8; }
    .nav-card-warning .nav-card-icon { background: #fff3cd; color: #ffc107; }
    .nav-card-warning:hover { border-color: #ffc107; }
    .nav-card-danger .nav-card-icon { background: #f8d7da; color: #dc3545; }
    .nav-card-danger:hover { border-color: #dc3545; }
    .nav-card-secondary .nav-card-icon { background: #e2e3e5; color: #6c757d; }
    .nav-card-secondary:hover { border-color: #6c757d; }
    .nav-card-purple .nav-card-icon { background: #e8dff5; color: #6f42c1; }
    .nav-card-purple:hover { border-color: #6f42c1; }
    .nav-card-teal .nav-card-icon { background: #d1f2eb; color: #20c997; }
    .nav-card-teal:hover { border-color: #20c997; }

    .stat-card {
        background: #fff;
        border-radius: 8px;
        padding: 1.25rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 1rem;
    }
    .stat-card h5 {
        font-size: 0.85rem;
        color: #6c757d;
        margin-bottom: 0.5rem;
    }
    .stat-card .value {
        font-size: 1.5rem;
        font-weight: 600;
    }
</style>
@endpush
