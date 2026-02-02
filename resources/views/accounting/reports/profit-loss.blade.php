@extends('admin.layouts.app')

@section('title', 'Profit & Loss Statement')
@section('page_name', 'Accounting')
@section('subpage_name', 'Profit & Loss')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Reports', 'url' => route('accounting.reports.index'), 'icon' => 'mdi-file-chart'],
    ['label' => 'Profit & Loss', 'url' => '#', 'icon' => 'mdi-chart-line']
]])

<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Profit & Loss Statement</h4>
            <p class="text-muted mb-0">Income and expenses for a period</p>
        </div>
        <div>
            <a href="{{ route('accounting.reports.index') }}" class="btn btn-outline-secondary mr-2">
                <i class="mdi mdi-arrow-left mr-1"></i> Back to Reports
            </a>
            <a href="{{ route('accounting.reports.profit-loss', array_merge(request()->all(), ['export' => 'pdf'])) }}"
               class="btn btn-danger mr-1">
                <i class="mdi mdi-file-pdf-box mr-1"></i> Export PDF
            </a>
            <a href="{{ route('accounting.reports.profit-loss', array_merge(request()->all(), ['export' => 'excel'])) }}"
               class="btn btn-success">
                <i class="mdi mdi-file-excel mr-1"></i> Export Excel
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card-modern shadow mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('accounting.reports.profit-loss') }}" class="row g-3">
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
                    <a href="{{ route('accounting.reports.profit-loss') }}" class="btn btn-outline-secondary">
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
            <h5 class="mb-1">Profit & Loss Statement</h5>
            <p class="text-muted mb-0">
                For the period {{ $startDate->format('F d, Y') }} to {{ $endDate->format('F d, Y') }}
            </p>
        </div>
        <div class="card-body">
            <!-- Revenue Section -->
            <div class="mb-4">
                <h5 class="text-success border-bottom pb-2">
                    <i class="fas fa-arrow-up me-2"></i>Revenue
                </h5>
                <table class="table table-sm">
                    <tbody>
                        @foreach($report['income']['groups'] ?? [] as $group)
                            <tr class="bg-light">
                                <td colspan="2"><strong>{{ $group['name'] }}</strong></td>
                            </tr>
                            @foreach($group['accounts'] as $account)
                            <tr>
                                <td class="ps-4">{{ $account['code'] }} - {{ $account['name'] }}</td>
                                <td class="text-end" style="width: 150px;">
                                    {{ number_format($account['balance'], 2) }}
                                </td>
                            </tr>
                            @endforeach
                            <tr class="fw-bold">
                                <td class="ps-4">Total {{ $group['name'] }}</td>
                                <td class="text-end">{{ number_format($group['total'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold bg-light">
                            <td>Total Revenue</td>
                            <td class="text-end text-success">₦ {{ number_format($report['total_income'] ?? 0, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Operating Expenses Section -->
            <div class="mb-4">
                <h5 class="text-danger border-bottom pb-2">
                    <i class="fas fa-arrow-down me-2"></i>Operating Expenses
                </h5>
                <table class="table table-sm">
                    <tbody>
                        @foreach($report['expenses']['groups'] ?? [] as $group)
                            <tr class="bg-light">
                                <td colspan="2"><strong>{{ $group['name'] }}</strong></td>
                            </tr>
                            @foreach($group['accounts'] as $account)
                            <tr>
                                <td class="ps-4">{{ $account['code'] }} - {{ $account['name'] }}</td>
                                <td class="text-end" style="width: 150px;">
                                    {{ number_format($account['balance'], 2) }}
                                </td>
                            </tr>
                            @endforeach
                            <tr class="fw-bold">
                                <td class="ps-4">Total {{ $group['name'] }}</td>
                                <td class="text-end">{{ number_format($group['total'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold bg-light">
                            <td>Total Operating Expenses</td>
                            <td class="text-end text-danger">₦ {{ number_format($report['total_expenses'] ?? 0, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Net Income -->
            <div class="mt-4">
                <table class="table table-bordered">
                    <tr class="table-dark fw-bold fs-5">
                        <td><i class="fas fa-money-bill-wave me-2"></i>Net Income (Loss)</td>
                        <td class="text-end" style="width: 180px;">
                            <span class="{{ ($report['net_income'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                ₦ {{ number_format($report['net_income'] ?? 0, 2) }}
                            </span>
                        </td>
                    </tr>
                </table>
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
