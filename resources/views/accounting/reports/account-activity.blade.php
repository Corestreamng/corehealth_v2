@extends('admin.layouts.app')

@section('title', 'Account Activity')
@section('page_name', 'Accounting')
@section('subpage_name', 'Account Activity')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Reports', 'url' => route('accounting.reports.index'), 'icon' => 'mdi-file-chart'],
    ['label' => 'Account Activity', 'url' => '#', 'icon' => 'mdi-history']
]])

<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Account Activity Report</h4>
            <p class="text-muted mb-0">
                @if($account)
                    Detailed transaction history for {{ $account->code }} - {{ $account->name }}
                @else
                    Select an account to view transaction history
                @endif
            </p>
        </div>
        <div>
            <a href="{{ route('accounting.reports.index') }}" class="btn btn-outline-secondary mr-2">
                <i class="mdi mdi-arrow-left mr-1"></i> Back to Reports
            </a>
            @if($account)
            <a href="{{ route('accounting.reports.account-activity', array_merge(request()->all(), ['export' => 'pdf'])) }}"
               class="btn btn-danger mr-1">
                <i class="mdi mdi-file-pdf-box mr-1"></i> Export PDF
            </a>
            <a href="{{ route('accounting.reports.account-activity', array_merge(request()->all(), ['export' => 'excel'])) }}"
               class="btn btn-success">
                <i class="mdi mdi-file-excel mr-1"></i> Export Excel
            </a>
            @endif
        </div>
    </div>

    <!-- Filters -->
    <div class="card-modern shadow mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('accounting.reports.account-activity') }}" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Account <span class="text-danger">*</span></label>
                    <select name="account_id" class="form-control" required>
                        <option value="">-- Select Account --</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ $account && $account->id == $acc->id ? 'selected' : '' }}>
                                {{ $acc->code }} - {{ $acc->name }}
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
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i> Generate
                    </button>
                    @if($account)
                    <a href="{{ route('accounting.reports.account-activity') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-sync me-1"></i> Reset
                    </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Report -->
    <div class="card-modern shadow">
        <div class="card-header py-3 text-center bg-white">
            <h4 class="mb-1">{{ config('app.name', 'CoreHealth') }}</h4>
            <h5 class="mb-1">Account Activity Report</h5>
            <p class="text-muted mb-0">
                {{ $startDate->format('F d, Y') }} to {{ $endDate->format('F d, Y') }}
            </p>
        </div>
        <div class="card-body">
            @if($activity)
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center bg-light p-3 rounded mb-3">
                        <div>
                            <h5 class="mb-0">{{ $activity['account']['code'] }} - {{ $activity['account']['name'] }}</h5>
                            <small class="text-muted">{{ $activity['account']['group'] ?? '' }}</small>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">Opening Balance</small>
                            <h6 class="mb-0">₦{{ number_format($activity['opening_balance'] ?? 0, 2) }}</h6>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 10%;">Date</th>
                                    <th style="width: 10%;">Entry #</th>
                                    <th style="width: 35%;">Description</th>
                                    <th style="width: 10%;">Reference</th>
                                    <th class="text-end" style="width: 12%;">Debit</th>
                                    <th class="text-end" style="width: 12%;">Credit</th>
                                    <th class="text-end" style="width: 12%;">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($activity['transactions'] ?? [] as $transaction)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($transaction['date'])->format('M d, Y') }}</td>
                                        <td>
                                            @if(isset($transaction['entry_id']))
                                                <a href="{{ route('accounting.journal-entries.show', $transaction['entry_id']) }}" class="text-primary">
                                                    {{ $transaction['entry_number'] }}
                                                </a>
                                            @else
                                                {{ $transaction['entry_number'] ?? '-' }}
                                            @endif
                                        </td>
                                        <td>{{ Str::limit($transaction['description'] ?? '-', 60) }}</td>
                                        <td>
                                            <small class="text-muted">{{ $transaction['source_type'] ?? '-' }}</small>
                                        </td>
                                        <td class="text-end">
                                            @if(($transaction['debit'] ?? 0) > 0)
                                                <span class="text-success fw-bold">₦{{ number_format($transaction['debit'], 2) }}</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if(($transaction['credit'] ?? 0) > 0)
                                                <span class="text-danger fw-bold">₦{{ number_format($transaction['credit'], 2) }}</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <strong class="{{ ($transaction['running_balance'] ?? 0) >= 0 ? 'text-dark' : 'text-danger' }}">
                                                ₦{{ number_format($transaction['running_balance'] ?? 0, 2) }}
                                            </strong>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                            No transactions found in this period
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td colspan="4" class="text-end">Period Totals / Closing Balance:</td>
                                    <td class="text-end text-success">₦{{ number_format($activity['total_debit'] ?? 0, 2) }}</td>
                                    <td class="text-end text-danger">₦{{ number_format($activity['total_credit'] ?? 0, 2) }}</td>
                                    <td class="text-end">₦{{ number_format($activity['closing_balance'] ?? 0, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Summary Stats -->
                    <div class="row mt-4">
                        <div class="col-md-3">
                            <div class="card-modern border-primary">
                                <div class="card-body text-center">
                                    <small class="text-muted d-block">Opening Balance</small>
                                    <h5 class="mb-0">₦{{ number_format($activity['opening_balance'] ?? 0, 2) }}</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card-modern border-success">
                                <div class="card-body text-center">
                                    <small class="text-muted d-block">Total Debits</small>
                                    <h5 class="mb-0 text-success">₦{{ number_format($activity['total_debit'] ?? 0, 2) }}</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card-modern border-danger">
                                <div class="card-body text-center">
                                    <small class="text-muted d-block">Total Credits</small>
                                    <h5 class="mb-0 text-danger">₦{{ number_format($activity['total_credit'] ?? 0, 2) }}</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card-modern border-dark">
                                <div class="card-body text-center">
                                    <small class="text-muted d-block">Closing Balance</small>
                                    <h5 class="mb-0">₦{{ number_format($activity['closing_balance'] ?? 0, 2) }}</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="text-center py-5">
                    <i class="fas fa-exclamation-circle fa-3x text-warning mb-3"></i>
                    <h5>No Account Selected</h5>
                    <p class="text-muted">Please select an account to view its activity.</p>
                </div>
            @endif
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
