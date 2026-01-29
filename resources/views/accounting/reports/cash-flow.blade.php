@extends('admin.layouts.app')

@section('title', 'Cash Flow Statement')
@section('page_name', 'Accounting')
@section('subpage_name', 'Cash Flow')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Cash Flow Statement</h4>
            <p class="text-muted mb-0">Operating, investing and financing activities</p>
        </div>
        <div>
            <a href="{{ route('accounting.reports.index') }}" class="btn btn-outline-secondary mr-2">
                <i class="mdi mdi-arrow-left mr-1"></i> Back to Reports
            </a>
            <a href="{{ route('accounting.reports.cash-flow', ['format' => 'pdf'] + request()->all()) }}"
               class="btn btn-danger mr-1" target="_blank">
                <i class="mdi mdi-file-pdf-box mr-1"></i> Export PDF
            </a>
            <a href="{{ route('accounting.reports.cash-flow', ['format' => 'excel'] + request()->all()) }}"
               class="btn btn-success">
                <i class="mdi mdi-file-excel mr-1"></i> Export Excel
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('accounting.reports.cash-flow') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control"
                           value="{{ request('start_date', $startDate->format('Y-m-d')) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control"
                           value="{{ request('end_date', $endDate->format('Y-m-d')) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fiscal Period</label>
                    <select name="fiscal_period_id" class="form-select">
                        <option value="">-- Custom Date Range --</option>
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
                    <a href="{{ route('accounting.reports.cash-flow') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-sync me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Report -->
    <div class="card shadow">
        <div class="card-header py-3 text-center bg-white">
            <h4 class="mb-1">{{ config('app.name', 'CoreHealth') }}</h4>
            <h5 class="mb-1">Statement of Cash Flows</h5>
            <p class="text-muted mb-0">
                For the period {{ $startDate->format('F d, Y') }} to {{ $endDate->format('F d, Y') }}
            </p>
        </div>
        <div class="card-body">
            <!-- Beginning Cash Balance -->
            <div class="mb-4 p-3 bg-light rounded">
                <div class="d-flex justify-content-between">
                    <span class="fw-bold">Beginning Cash Balance</span>
                    <span class="fw-bold">₦ {{ number_format($data['beginning_cash'] ?? 0, 2) }}</span>
                </div>
            </div>

            <!-- Operating Activities -->
            <div class="mb-4">
                <h5 class="text-primary border-bottom pb-2">
                    <i class="fas fa-industry me-2"></i>Cash Flows from Operating Activities
                </h5>
                <table class="table table-sm">
                    <tbody>
                        <tr>
                            <td class="ps-4">Net Income</td>
                            <td class="text-end" style="width: 150px;">{{ number_format($data['net_income'] ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="ps-4 fw-bold">Adjustments to reconcile net income:</td>
                            <td></td>
                        </tr>
                        @foreach($data['operating_adjustments'] ?? [] as $item)
                            <tr>
                                <td class="ps-5">{{ $item['name'] }}</td>
                                <td class="text-end">{{ number_format($item['amount'], 2) }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td class="ps-4 fw-bold">Changes in operating assets and liabilities:</td>
                            <td></td>
                        </tr>
                        @foreach($data['operating_changes'] ?? [] as $item)
                            <tr>
                                <td class="ps-5">{{ $item['name'] }}</td>
                                <td class="text-end">{{ number_format($item['amount'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold bg-light">
                            <td>Net Cash from Operating Activities</td>
                            <td class="text-end {{ ($data['operating_total'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($data['operating_total'] ?? 0, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Investing Activities -->
            <div class="mb-4">
                <h5 class="text-info border-bottom pb-2">
                    <i class="fas fa-chart-line me-2"></i>Cash Flows from Investing Activities
                </h5>
                <table class="table table-sm">
                    <tbody>
                        @forelse($data['investing'] ?? [] as $item)
                            <tr>
                                <td class="ps-4">{{ $item['name'] }}</td>
                                <td class="text-end" style="width: 150px;">{{ number_format($item['amount'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="ps-4 text-muted">No investing activities in this period</td>
                                <td class="text-end">0.00</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold bg-light">
                            <td>Net Cash from Investing Activities</td>
                            <td class="text-end {{ ($data['investing_total'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($data['investing_total'] ?? 0, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Financing Activities -->
            <div class="mb-4">
                <h5 class="text-warning border-bottom pb-2">
                    <i class="fas fa-hand-holding-usd me-2"></i>Cash Flows from Financing Activities
                </h5>
                <table class="table table-sm">
                    <tbody>
                        @forelse($data['financing'] ?? [] as $item)
                            <tr>
                                <td class="ps-4">{{ $item['name'] }}</td>
                                <td class="text-end" style="width: 150px;">{{ number_format($item['amount'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="ps-4 text-muted">No financing activities in this period</td>
                                <td class="text-end">0.00</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold bg-light">
                            <td>Net Cash from Financing Activities</td>
                            <td class="text-end {{ ($data['financing_total'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($data['financing_total'] ?? 0, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Net Change and Ending Balance -->
            <div class="mt-4">
                @php
                    $netChange = ($data['operating_total'] ?? 0) + ($data['investing_total'] ?? 0) + ($data['financing_total'] ?? 0);
                    $endingCash = ($data['beginning_cash'] ?? 0) + $netChange;
                @endphp

                <table class="table table-bordered">
                    <tr class="table-secondary">
                        <td class="fw-bold">Net Increase (Decrease) in Cash</td>
                        <td class="text-end fw-bold {{ $netChange >= 0 ? 'text-success' : 'text-danger' }}" style="width: 180px;">
                            ₦ {{ number_format($netChange, 2) }}
                        </td>
                    </tr>
                    <tr>
                        <td>Beginning Cash Balance</td>
                        <td class="text-end">₦ {{ number_format($data['beginning_cash'] ?? 0, 2) }}</td>
                    </tr>
                    <tr class="table-dark fw-bold fs-5">
                        <td><i class="fas fa-wallet me-2"></i>Ending Cash Balance</td>
                        <td class="text-end text-white">
                            ₦ {{ number_format($endingCash, 2) }}
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Reconciliation Note -->
            <div class="alert alert-info mt-4">
                <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Reconciliation Note</h6>
                <small>
                    The ending cash balance should reconcile with the total of all Cash and Bank accounts
                    in the Balance Sheet as of {{ $endDate->format('F d, Y') }}.
                </small>
            </div>
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
