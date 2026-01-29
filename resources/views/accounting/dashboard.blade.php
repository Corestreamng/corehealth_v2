@extends('admin.layouts.app')
@section('title', 'Accounting Dashboard')
@section('page_name', 'Accounting')
@section('subpage_name', 'Dashboard')

@section('content')
<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Period Info Banner -->
        @if($currentPeriod)
        <div class="alert alert-info d-flex justify-content-between align-items-center mb-3">
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
        <div class="alert alert-warning mb-3">
            <i class="mdi mdi-alert mr-2"></i>
            <strong>No Active Period!</strong> Please create a fiscal year and period to start recording transactions.
            <a href="{{ route('accounting.periods') }}" class="btn btn-warning btn-sm ml-2">Create Now</a>
        </div>
        @endif

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
                                                <td>{{ $account['code'] }} - {{ Str::limit($account['name'], 25) }}</td>
                                                <td class="text-right">{{ $account['debit'] > 0 ? number_format($account['debit'], 2) : '-' }}</td>
                                                <td class="text-right">{{ $account['credit'] > 0 ? number_format($account['credit'], 2) : '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="font-weight-bold">
                                        <tr class="table-secondary">
                                            <td>Total</td>
                                            <td class="text-right">₦{{ number_format($trialBalance['total_debits'] ?? 0, 2) }}</td>
                                            <td class="text-right">₦{{ number_format($trialBalance['total_credits'] ?? 0, 2) }}</td>
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
    .quick-link-card {
        transition: all 0.2s ease;
        border-radius: 8px;
    }
    .quick-link-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
</style>
@endpush
