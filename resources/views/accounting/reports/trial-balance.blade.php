@extends('admin.layouts.app')

@section('title', 'Trial Balance')
@section('page_name', 'Accounting')
@section('subpage_name', 'Trial Balance')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Reports', 'url' => route('accounting.reports.index'), 'icon' => 'mdi-file-chart'],
    ['label' => 'Trial Balance', 'url' => '#', 'icon' => 'mdi-scale-balance']
]])

<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Trial Balance</h4>
            <p class="text-muted mb-0">Account balances at a specific point in time</p>
        </div>
        <div>
            <a href="{{ route('accounting.reports.index') }}" class="btn btn-outline-secondary mr-2">
                <i class="mdi mdi-arrow-left mr-1"></i> Back to Reports
            </a>
            <a href="{{ route('accounting.reports.trial-balance', array_merge(request()->all(), ['export' => 'pdf'])) }}"
               class="btn btn-danger mr-1">
                <i class="mdi mdi-file-pdf-box mr-1"></i> Export PDF
            </a>
            <a href="{{ route('accounting.reports.trial-balance', array_merge(request()->all(), ['export' => 'excel'])) }}"
               class="btn btn-success">
                <i class="mdi mdi-file-excel mr-1"></i> Export Excel
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card-modern shadow mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('accounting.reports.trial-balance') }}">
                <div class="row align-items-end">
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Period</label>
                        <select name="period_id" class="form-select">
                            <option value="">Select Period...</option>
                            @foreach($periods as $p)
                                <option value="{{ $p->id }}" {{ ($period && $p->id == $period->id) ? 'selected' : '' }}>
                                    {{ $p->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label">As of Date</label>
                        <input type="date" name="as_of_date" class="form-control" value="{{ $asOfDate->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-2 mb-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-sync me-1"></i> Generate
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Report -->
    <div class="card-modern shadow">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                Trial Balance as of {{ $asOfDate->format('F d, Y') }}
                @if($period)
                    <span class="text-muted">({{ $period->name }})</span>
                @endif
            </h6>
        </div>
        <div class="card-body">
            @php
                // Data structure from ReportService:
                // $report['accounts'] = [{account_id, account_code, account_name, class_name, group_name, debit, credit}]
                // $report['total_debit'], $report['total_credit'], $report['is_balanced']
            @endphp
            @if(isset($report['accounts']) && count($report['accounts']) > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Account Code</th>
                                <th>Account Name</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $currentClass = null; @endphp
                            @foreach($report['accounts'] as $account)
                                @if(($account['class_name'] ?? '') !== $currentClass)
                                    @php $currentClass = $account['class_name'] ?? ''; @endphp
                                    <tr class="table-secondary">
                                        <td colspan="4"><strong>{{ $currentClass }}</strong></td>
                                    </tr>
                                @endif
                                <tr>
                                    <td><code>{{ $account['account_code'] ?? $account['code'] ?? '' }}</code></td>
                                    <td>
                                        <a href="{{ route('accounting.chart-of-accounts.show', $account['account_id'] ?? $account['id'] ?? 0) }}">
                                            {{ $account['account_name'] ?? $account['name'] ?? '' }}
                                        </a>
                                    </td>
                                    <td class="text-end">
                                        @if(($account['debit'] ?? 0) > 0)
                                            ₦{{ number_format($account['debit'], 2) }}
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if(($account['credit'] ?? 0) > 0)
                                            ₦{{ number_format($account['credit'], 2) }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-dark">
                            <tr>
                                <td colspan="2"><strong>TOTAL</strong></td>
                                <td class="text-end"><strong>₦{{ number_format($report['total_debit'] ?? $report['total_debits'] ?? 0, 2) }}</strong></td>
                                <td class="text-end"><strong>₦{{ number_format($report['total_credit'] ?? $report['total_credits'] ?? 0, 2) }}</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Balance Check -->
                <div class="mt-4">
                    @php
                        $totalDebit = $report['total_debit'] ?? $report['total_debits'] ?? 0;
                        $totalCredit = $report['total_credit'] ?? $report['total_credits'] ?? 0;
                    @endphp
                    @if(abs($totalDebit - $totalCredit) < 0.01)
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Balanced!</strong> Total debits equal total credits.
                        </div>
                    @else
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Out of Balance!</strong>
                            Difference: ₦{{ number_format(abs($totalDebit - $totalCredit), 2) }}
                        </div>
                    @endif
                </div>
            @else
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-file-invoice fa-3x mb-3"></i>
                    <p>No data available for the selected period.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
