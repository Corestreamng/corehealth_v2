@extends('admin.layouts.app')

@section('title', 'Balance Sheet')
@section('page_name', 'Accounting')
@section('subpage_name', 'Balance Sheet')

@section('content')
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
            <a href="{{ route('accounting.reports.balance-sheet', ['format' => 'pdf'] + request()->all()) }}"
               class="btn btn-danger mr-1" target="_blank">
                <i class="mdi mdi-file-pdf-box mr-1"></i> Export PDF
            </a>
            <a href="{{ route('accounting.reports.balance-sheet', ['format' => 'excel'] + request()->all()) }}"
               class="btn btn-success">
                <i class="mdi mdi-file-excel mr-1"></i> Export Excel
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow mb-4">
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
    <div class="card shadow">
        <div class="card-header py-3 text-center bg-white">
            <h4 class="mb-1">{{ config('app.name', 'CoreHealth') }}</h4>
            <h5 class="mb-1">Balance Sheet</h5>
            <p class="text-muted mb-0">As of {{ $asOfDate->format('F d, Y') }}</p>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- Assets Column -->
                <div class="col-md-6 border-end">
                    <h4 class="text-primary border-bottom pb-2 mb-3">
                        <i class="fas fa-building me-2"></i>ASSETS
                    </h4>

                    <!-- Current Assets -->
                    <div class="mb-4">
                        <h6 class="text-secondary fw-bold">Current Assets</h6>
                        <table class="table table-sm table-borderless">
                            <tbody>
                                @php $totalCurrentAssets = 0; @endphp
                                @foreach($data['current_assets'] ?? [] as $item)
                                    <tr>
                                        <td class="ps-3">{{ $item['name'] }}</td>
                                        <td class="text-end" style="width: 120px;">
                                            {{ number_format($item['balance'], 2) }}
                                        </td>
                                    </tr>
                                    @php $totalCurrentAssets += $item['balance']; @endphp
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-top">
                                    <td class="fw-bold">Total Current Assets</td>
                                    <td class="text-end fw-bold">{{ number_format($totalCurrentAssets, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Fixed Assets -->
                    <div class="mb-4">
                        <h6 class="text-secondary fw-bold">Fixed Assets</h6>
                        <table class="table table-sm table-borderless">
                            <tbody>
                                @php $totalFixedAssets = 0; @endphp
                                @foreach($data['fixed_assets'] ?? [] as $item)
                                    <tr>
                                        <td class="ps-3">{{ $item['name'] }}</td>
                                        <td class="text-end" style="width: 120px;">
                                            {{ number_format($item['balance'], 2) }}
                                        </td>
                                    </tr>
                                    @php $totalFixedAssets += $item['balance']; @endphp
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-top">
                                    <td class="fw-bold">Total Fixed Assets</td>
                                    <td class="text-end fw-bold">{{ number_format($totalFixedAssets, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Other Assets -->
                    @if(!empty($data['other_assets']))
                        <div class="mb-4">
                            <h6 class="text-secondary fw-bold">Other Assets</h6>
                            <table class="table table-sm table-borderless">
                                <tbody>
                                    @php $totalOtherAssets = 0; @endphp
                                    @foreach($data['other_assets'] ?? [] as $item)
                                        <tr>
                                            <td class="ps-3">{{ $item['name'] }}</td>
                                            <td class="text-end" style="width: 120px;">
                                                {{ number_format($item['balance'], 2) }}
                                            </td>
                                        </tr>
                                        @php $totalOtherAssets += $item['balance']; @endphp
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="border-top">
                                        <td class="fw-bold">Total Other Assets</td>
                                        <td class="text-end fw-bold">{{ number_format($totalOtherAssets, 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        @php $totalOtherAssets = 0; @endphp
                    @endif

                    <!-- Total Assets -->
                    <div class="bg-primary text-white p-3 rounded">
                        @php $totalAssets = $totalCurrentAssets + $totalFixedAssets + $totalOtherAssets; @endphp
                        <div class="d-flex justify-content-between">
                            <h5 class="mb-0">TOTAL ASSETS</h5>
                            <h5 class="mb-0">₦ {{ number_format($totalAssets, 2) }}</h5>
                        </div>
                    </div>
                </div>

                <!-- Liabilities & Equity Column -->
                <div class="col-md-6">
                    <h4 class="text-primary border-bottom pb-2 mb-3">
                        <i class="fas fa-balance-scale me-2"></i>LIABILITIES & EQUITY
                    </h4>

                    <!-- Current Liabilities -->
                    <div class="mb-4">
                        <h6 class="text-secondary fw-bold">Current Liabilities</h6>
                        <table class="table table-sm table-borderless">
                            <tbody>
                                @php $totalCurrentLiabilities = 0; @endphp
                                @foreach($data['current_liabilities'] ?? [] as $item)
                                    <tr>
                                        <td class="ps-3">{{ $item['name'] }}</td>
                                        <td class="text-end" style="width: 120px;">
                                            {{ number_format($item['balance'], 2) }}
                                        </td>
                                    </tr>
                                    @php $totalCurrentLiabilities += $item['balance']; @endphp
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-top">
                                    <td class="fw-bold">Total Current Liabilities</td>
                                    <td class="text-end fw-bold">{{ number_format($totalCurrentLiabilities, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Long-term Liabilities -->
                    @if(!empty($data['long_term_liabilities']))
                        <div class="mb-4">
                            <h6 class="text-secondary fw-bold">Long-term Liabilities</h6>
                            <table class="table table-sm table-borderless">
                                <tbody>
                                    @php $totalLongTermLiabilities = 0; @endphp
                                    @foreach($data['long_term_liabilities'] ?? [] as $item)
                                        <tr>
                                            <td class="ps-3">{{ $item['name'] }}</td>
                                            <td class="text-end" style="width: 120px;">
                                                {{ number_format($item['balance'], 2) }}
                                            </td>
                                        </tr>
                                        @php $totalLongTermLiabilities += $item['balance']; @endphp
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="border-top">
                                        <td class="fw-bold">Total Long-term Liabilities</td>
                                        <td class="text-end fw-bold">{{ number_format($totalLongTermLiabilities, 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        @php $totalLongTermLiabilities = 0; @endphp
                    @endif

                    @php $totalLiabilities = $totalCurrentLiabilities + $totalLongTermLiabilities; @endphp
                    <div class="bg-light p-2 rounded mb-4">
                        <div class="d-flex justify-content-between">
                            <strong>Total Liabilities</strong>
                            <strong>{{ number_format($totalLiabilities, 2) }}</strong>
                        </div>
                    </div>

                    <!-- Equity -->
                    <div class="mb-4">
                        <h6 class="text-secondary fw-bold">Stockholders' Equity</h6>
                        <table class="table table-sm table-borderless">
                            <tbody>
                                @php $totalEquity = 0; @endphp
                                @foreach($data['equity'] ?? [] as $item)
                                    <tr>
                                        <td class="ps-3">{{ $item['name'] }}</td>
                                        <td class="text-end" style="width: 120px;">
                                            {{ number_format($item['balance'], 2) }}
                                        </td>
                                    </tr>
                                    @php $totalEquity += $item['balance']; @endphp
                                @endforeach
                                <!-- Current Period Net Income -->
                                <tr>
                                    <td class="ps-3">Current Period Net Income</td>
                                    <td class="text-end" style="width: 120px;">
                                        {{ number_format($data['net_income'] ?? 0, 2) }}
                                    </td>
                                </tr>
                                @php $totalEquity += ($data['net_income'] ?? 0); @endphp
                            </tbody>
                            <tfoot>
                                <tr class="border-top">
                                    <td class="fw-bold">Total Equity</td>
                                    <td class="text-end fw-bold">{{ number_format($totalEquity, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Total Liabilities & Equity -->
                    <div class="bg-primary text-white p-3 rounded">
                        @php $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquity; @endphp
                        <div class="d-flex justify-content-between">
                            <h5 class="mb-0">TOTAL LIABILITIES & EQUITY</h5>
                            <h5 class="mb-0">₦ {{ number_format($totalLiabilitiesAndEquity, 2) }}</h5>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Balance Check -->
            <div class="mt-4">
                @php $difference = $totalAssets - $totalLiabilitiesAndEquity; @endphp
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
