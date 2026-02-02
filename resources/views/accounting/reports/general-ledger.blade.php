@extends('admin.layouts.app')

@section('title', 'General Ledger')
@section('page_name', 'Accounting')
@section('subpage_name', 'General Ledger')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Reports', 'url' => route('accounting.reports.index'), 'icon' => 'mdi-file-chart'],
    ['label' => 'General Ledger', 'url' => '#', 'icon' => 'mdi-book-multiple']
]])

<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">General Ledger</h4>
            <p class="text-muted mb-0">Detailed account activity and running balances</p>
        </div>
        <div>
            <a href="{{ route('accounting.reports.index') }}" class="btn btn-outline-secondary mr-2">
                <i class="mdi mdi-arrow-left mr-1"></i> Back to Reports
            </a>
            <a href="{{ route('accounting.reports.general-ledger', array_merge(request()->all(), ['export' => 'pdf'])) }}"
               class="btn btn-danger mr-1">
                <i class="mdi mdi-file-pdf-box mr-1"></i> Export PDF
            </a>
            <a href="{{ route('accounting.reports.general-ledger', array_merge(request()->all(), ['export' => 'excel'])) }}"
               class="btn btn-success">
                <i class="mdi mdi-file-excel mr-1"></i> Export Excel
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card-modern shadow mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('accounting.reports.general-ledger') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Account</label>
                    <select name="account_id" class="form-select">
                        <option value="">All Accounts</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" {{ request('account_id') == $account->id ? 'selected' : '' }}>
                                {{ $account->code }} - {{ $account->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control"
                           value="{{ request('start_date', $startDate->format('Y-m-d')) }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control"
                           value="{{ request('end_date', $endDate->format('Y-m-d')) }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Fiscal Period</label>
                    <select name="fiscal_period_id" class="form-select">
                        <option value="">-- Custom --</option>
                        @foreach($fiscalPeriods as $period)
                            <option value="{{ $period->id }}" {{ request('fiscal_period_id') == $period->id ? 'selected' : '' }}>
                                {{ $period->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i> Generate
                    </button>
                    <a href="{{ route('accounting.reports.general-ledger') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-sync me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Report -->
    <div class="card-modern shadow">
        <div class="card-header py-3 text-center bg-white">
            <h4 class="mb-1">{{ config('app.name', 'CoreHealth') }}</h4>
            <h5 class="mb-1">General Ledger</h5>
            <p class="text-muted mb-0">
                {{ $startDate->format('F d, Y') }} to {{ $endDate->format('F d, Y') }}
            </p>
        </div>
        <div class="card-body">
            @forelse($ledgerData as $accountData)
                <div class="mb-5">
                    <div class="d-flex justify-content-between align-items-center bg-light p-3 rounded mb-3">
                        <div>
                            <h5 class="mb-0">{{ $accountData['account']['code'] }} - {{ $accountData['account']['name'] }}</h5>
                            <small class="text-muted">{{ $accountData['account']['group'] ?? '' }}</small>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">Opening Balance</small>
                            <h6 class="mb-0">{{ number_format($accountData['opening_balance'], 2) }}</h6>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Entry #</th>
                                    <th>Description</th>
                                    <th>Reference</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                    <th class="text-end">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($accountData['transactions'] as $transaction)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($transaction['date'])->format('M d, Y') }}</td>
                                        <td>
                                            <a href="{{ route('accounting.journal-entries.show', $transaction['entry_id']) }}">
                                                {{ $transaction['entry_number'] }}
                                            </a>
                                        </td>
                                        <td>{{ Str::limit($transaction['description'], 40) }}</td>
                                        <td>{{ $transaction['source_type'] ?? '-' }}</td>
                                        <td class="text-end">
                                            {{ $transaction['debit'] > 0 ? number_format($transaction['debit'], 2) : '-' }}
                                        </td>
                                        <td class="text-end">
                                            {{ $transaction['credit'] > 0 ? number_format($transaction['credit'], 2) : '-' }}
                                        </td>
                                        <td class="text-end fw-bold">
                                            {{ number_format($transaction['running_balance'], 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-3">
                                            No transactions in this period
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td colspan="4">Totals / Closing Balance</td>
                                    <td class="text-end">{{ number_format($accountData['total_debit'], 2) }}</td>
                                    <td class="text-end">{{ number_format($accountData['total_credit'], 2) }}</td>
                                    <td class="text-end">{{ number_format($accountData['closing_balance'], 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @empty
                <div class="text-center py-5">
                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                    <h5>No Data Available</h5>
                    <p class="text-muted">Select an account or adjust the date range to view ledger entries.</p>
                </div>
            @endforelse
        </div>
        <div class="card-footer text-muted small">
            <div class="row">
                <div class="col-md-6">
                    Generated on: {{ now()->format('F d, Y H:i:s') }}
                </div>
                <div class="col-md-6 text-end">
                    Generated by: {{ auth()->user()->name ?? 'System' }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
