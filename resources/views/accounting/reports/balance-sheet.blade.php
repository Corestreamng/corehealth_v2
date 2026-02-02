@extends('admin.layouts.app')

@section('title', 'Balance Sheet')
@section('page_name', 'Accounting')
@section('subpage_name', 'Balance Sheet')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Reports', 'url' => route('accounting.reports.index'), 'icon' => 'mdi-file-chart'],
    ['label' => 'Balance Sheet', 'url' => '#', 'icon' => 'mdi-file-document']
]])

<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Balance Sheet</h4>
            <p class="text-muted mb-0">Assets, liabilities and equity as of a date</p>
        </div>
        <div>
            <a href="{{ route('accounting.reports.index') }}" class="btn btn-outline-secondary mr-2">
                <i class="mdi mdi-arrow-left mr-1"></i> Back to Reports
            </a>
            <a href="{{ route('accounting.reports.balance-sheet', array_merge(request()->all(), ['export' => 'pdf'])) }}"
               class="btn btn-danger mr-1">
                <i class="mdi mdi-file-pdf-box mr-1"></i> Export PDF
            </a>
            <a href="{{ route('accounting.reports.balance-sheet', array_merge(request()->all(), ['export' => 'excel'])) }}"
               class="btn btn-success">
                <i class="mdi mdi-file-excel mr-1"></i> Export Excel
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card-modern shadow mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('accounting.reports.balance-sheet') }}" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">As of Date</label>
                    <input type="date" name="as_of_date" class="form-control"
                           value="{{ request('as_of_date', $asOfDate->format('Y-m-d')) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fiscal Period</label>
                    <select name="fiscal_period_id" class="form-select">
                        <option value="">-- Custom Date --</option>
                        @foreach($fiscalPeriods as $period)
                            <option value="{{ $period->id }}" {{ request('fiscal_period_id') == $period->id ? 'selected' : '' }}>
                                {{ $period->name }} (Ending {{ $period->end_date->format('M d, Y') }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i> Generate
                    </button>
                    <a href="{{ route('accounting.reports.balance-sheet') }}" class="btn btn-outline-secondary">
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
            <h5 class="mb-1">Balance Sheet</h5>
            <p class="text-muted mb-0">As of {{ $asOfDate->format('F d, Y') }}</p>
        </div>
        <div class="card-body">
            @php
                // Data structure from ReportService:
                // $report['assets']['groups'] = [{name, accounts: [{id, code, name, balance}], total}]
                // $report['liabilities']['groups'] = same structure
                // $report['equity']['groups'] = same structure
                // $report['total_assets'], $report['total_liabilities'], $report['total_equity']

                $assetGroups = $report['assets']['groups'] ?? [];
                $liabilityGroups = $report['liabilities']['groups'] ?? [];
                $equityGroups = $report['equity']['groups'] ?? [];

                $totalAssets = $report['total_assets'] ?? $report['assets']['total'] ?? 0;
                $totalLiabilities = $report['total_liabilities'] ?? $report['liabilities']['total'] ?? 0;
                $totalEquity = $report['total_equity'] ?? $report['equity']['total'] ?? 0;
            @endphp

            <div class="row">
                <!-- Assets Column -->
                <div class="col-md-6 border-end">
                    <h4 class="text-primary border-bottom pb-2 mb-3">
                        <i class="fas fa-building me-2"></i>ASSETS
                    </h4>

                    @php $calculatedAssets = 0; @endphp
                    @if(is_array($assetGroups) && count($assetGroups) > 0)
                        @foreach($assetGroups as $group)
                            <div class="mb-4">
                                <h6 class="text-secondary fw-bold">{{ $group['name'] ?? 'Assets' }}</h6>
                                <table class="table table-sm table-borderless">
                                    <tbody>
                                        @foreach($group['accounts'] ?? [] as $account)
                                            <tr>
                                                <td class="ps-3">{{ $account['code'] ?? '' }} - {{ $account['name'] ?? '' }}</td>
                                                <td class="text-end" style="width: 120px;">
                                                    {{ number_format($account['balance'] ?? 0, 2) }}
                                                </td>
                                            </tr>
                                            @php $calculatedAssets += ($account['balance'] ?? 0); @endphp
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="border-top">
                                            <td class="fw-bold">Total {{ $group['name'] ?? '' }}</td>
                                            <td class="text-end fw-bold">{{ number_format($group['total'] ?? 0, 2) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @endforeach
                    @else
                        <div class="mb-4">
                            <p class="text-muted text-center">No assets recorded</p>
                        </div>
                    @endif

                    <!-- Total Assets -->
                    <div class="bg-primary text-white p-3 rounded">
                        <div class="d-flex justify-content-between">
                            <h5 class="mb-0">TOTAL ASSETS</h5>
                            <h5 class="mb-0">₦ {{ number_format($totalAssets ?: $calculatedAssets, 2) }}</h5>
                        </div>
                    </div>
                </div>

                <!-- Liabilities & Equity Column -->
                <div class="col-md-6">
                    <h4 class="text-primary border-bottom pb-2 mb-3">
                        <i class="fas fa-balance-scale me-2"></i>LIABILITIES & EQUITY
                    </h4>

                    <!-- Liabilities Section -->
                    @php $calculatedLiabilities = 0; @endphp
                    @if(is_array($liabilityGroups) && count($liabilityGroups) > 0)
                        @foreach($liabilityGroups as $group)
                            <div class="mb-4">
                                <h6 class="text-secondary fw-bold">{{ $group['name'] ?? 'Liabilities' }}</h6>
                                <table class="table table-sm table-borderless">
                                    <tbody>
                                        @foreach($group['accounts'] ?? [] as $account)
                                            <tr>
                                                <td class="ps-3">{{ $account['code'] ?? '' }} - {{ $account['name'] ?? '' }}</td>
                                                <td class="text-end" style="width: 120px;">
                                                    {{ number_format($account['balance'] ?? 0, 2) }}
                                                </td>
                                            </tr>
                                            @php $calculatedLiabilities += ($account['balance'] ?? 0); @endphp
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="border-top">
                                            <td class="fw-bold">Total {{ $group['name'] ?? '' }}</td>
                                            <td class="text-end fw-bold">{{ number_format($group['total'] ?? 0, 2) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @endforeach
                    @else
                        <div class="mb-4">
                            <p class="text-muted text-center">No liabilities recorded</p>
                        </div>
                    @endif

                    @php $finalLiabilities = $totalLiabilities ?: $calculatedLiabilities; @endphp
                    <div class="bg-light p-2 rounded mb-4">
                        <div class="d-flex justify-content-between">
                            <strong>Total Liabilities</strong>
                            <strong>₦ {{ number_format($finalLiabilities, 2) }}</strong>
                        </div>
                    </div>

                    <!-- Equity Section -->
                    @php $calculatedEquity = 0; @endphp
                    @if(is_array($equityGroups) && count($equityGroups) > 0)
                        @foreach($equityGroups as $group)
                            <div class="mb-4">
                                <h6 class="text-secondary fw-bold">{{ $group['name'] ?? 'Equity' }}</h6>
                                <table class="table table-sm table-borderless">
                                    <tbody>
                                        @foreach($group['accounts'] ?? [] as $account)
                                            <tr>
                                                <td class="ps-3">{{ $account['code'] ?? '' }} - {{ $account['name'] ?? '' }}</td>
                                                <td class="text-end" style="width: 120px;">
                                                    {{ number_format($account['balance'] ?? 0, 2) }}
                                                </td>
                                            </tr>
                                            @php $calculatedEquity += ($account['balance'] ?? 0); @endphp
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="border-top">
                                            <td class="fw-bold">Total {{ $group['name'] ?? '' }}</td>
                                            <td class="text-end fw-bold">{{ number_format($group['total'] ?? 0, 2) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @endforeach
                    @else
                        <div class="mb-4">
                            <p class="text-muted text-center">No equity recorded</p>
                        </div>
                    @endif

                    @php $finalEquity = $totalEquity ?: $calculatedEquity; @endphp

                    <!-- Total Liabilities & Equity -->
                    <div class="bg-primary text-white p-3 rounded">
                        @php $totalLiabilitiesAndEquity = $finalLiabilities + $finalEquity; @endphp
                        <div class="d-flex justify-content-between">
                            <h5 class="mb-0">TOTAL LIABILITIES & EQUITY</h5>
                            <h5 class="mb-0">₦ {{ number_format($totalLiabilitiesAndEquity, 2) }}</h5>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Balance Check -->
            <div class="mt-4">
                @php
                    $finalAssets = $totalAssets ?: $calculatedAssets;
                    $difference = $finalAssets - $totalLiabilitiesAndEquity;
                @endphp
                @if(abs($difference) < 0.01)
                    <div class="alert alert-success text-center mb-0">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Balance Sheet is balanced.</strong> Assets equal Liabilities plus Equity.
                    </div>
                @else
                    <div class="alert alert-danger text-center mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Balance Sheet is out of balance!</strong><br>
                        Difference: ₦ {{ number_format(abs($difference), 2) }}
                    </div>
                @endif
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
