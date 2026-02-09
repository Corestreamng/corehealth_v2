@extends('admin.layouts.app')
@section('title', 'KPI History - ' . $kpi->kpi_name)
@section('page_name', 'Accounting')
@section('subpage_name', 'KPI History')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'KPI Dashboard', 'url' => route('accounting.kpi.dashboard'), 'icon' => 'mdi-chart-box'],
    ['label' => $kpi->kpi_name, 'url' => '#', 'icon' => 'mdi-chart-line']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        <div class="row">
            {{-- Main Content --}}
            <div class="col-lg-8">
                {{-- Header --}}
                <div class="card-modern mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="mb-1">{{ $kpi->kpi_name }}</h4>
                                <small class="text-muted">
                                    <span class="badge badge-secondary mr-2">{{ $kpi->kpi_code }}</span>
                                    <span class="badge badge-info">{{ ucfirst($kpi->category) }}</span>
                                </small>
                            </div>
                            <div class="col-md-4 text-right">
                                @php
                                    $statusColors = [
                                        'normal' => 'success',
                                        'warning' => 'warning',
                                        'critical' => 'danger',
                                    ];
                                    $statusColor = isset($latestValue) ? ($statusColors[$latestValue->status] ?? 'secondary') : 'secondary';
                                @endphp
                                @isset($latestValue)
                                    <h3 class="mb-0 text-{{ $statusColor }}">
                                        @php
                                            $val = number_format($latestValue->value, 2);
                                            $unit = $kpi->unit;
                                        @endphp
                                        @if($unit === 'percentage')
                                            {{ $val }}%
                                        @elseif($unit === 'ratio')
                                            {{ $val }}x
                                        @elseif($unit === 'currency')
                                            ₦{{ number_format($latestValue->value, 0) }}
                                        @elseif($unit === 'days')
                                            {{ number_format($latestValue->value, 0) }} days
                                        @else
                                            {{ $val }}
                                        @endif
                                    </h3>
                                    <small class="text-muted">Latest Value</small>
                                @else
                                    <h4 class="text-muted mb-0">No Data</h4>
                                @endisset
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Trend Chart --}}
                <div class="card-modern mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="mdi mdi-chart-line mr-2"></i>Historical Trend</h5>
                    </div>
                    <div class="card-body">
                        @if($history->count() > 0)
                            <div style="height: 300px;">
                                <canvas id="trendChart"></canvas>
                            </div>
                        @else
                            <div class="text-center py-5 text-muted">
                                <i class="mdi mdi-chart-line-stacked mdi-48px"></i>
                                <p class="mt-2">No historical data available</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- History Table --}}
                <div class="card-modern">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="mdi mdi-history mr-2"></i>Value History</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>Date</th>
                                    <th class="text-right">Value</th>
                                    <th class="text-right">Change</th>
                                    <th class="text-center">Status</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($history as $record)
                                    @php
                                        $recVal = $record->value;
                                        $recUnit = $kpi->unit;
                                    @endphp
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($record->calculation_date)->format('M d, Y') }}</td>
                                        <td class="text-right">
                                            @if($recUnit === 'percentage')
                                                {{ number_format($recVal, 2) }}%
                                            @elseif($recUnit === 'ratio')
                                                {{ number_format($recVal, 2) }}x
                                            @elseif($recUnit === 'currency')
                                                ₦{{ number_format($recVal, 0) }}
                                            @elseif($recUnit === 'days')
                                                {{ number_format($recVal, 0) }}
                                            @else
                                                {{ number_format($recVal, 2) }}
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if($record->change_percentage !== null)
                                                @php $chgPct = $record->change_percentage; @endphp
                                                <span class="{{ $chgPct >= 0 ? 'text-success' : 'text-danger' }}">
                                                    <i class="mdi {{ $chgPct >= 0 ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i>
                                                    {{ number_format(abs($chgPct), 1) }}%
                                                </span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @php
                                                $rowColors = ['normal' => 'success', 'warning' => 'warning', 'critical' => 'danger'];
                                            @endphp
                                            <span class="badge badge-{{ $rowColors[$record->status] ?? 'secondary' }}">
                                                {{ ucfirst($record->status) }}
                                            </span>
                                        </td>
                                        <td>{{ $record->notes ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            No history records found
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>

                        @if(method_exists($history, 'hasPages') && $history->hasPages())
                            <div class="d-flex justify-content-center mt-3">
                                {{ $history->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="col-lg-4">
                {{-- KPI Details --}}
                <div class="card-modern mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="mdi mdi-information mr-2"></i>KPI Details</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted">Code:</td>
                                <td><strong>{{ $kpi->kpi_code }}</strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Category:</td>
                                <td>{{ ucfirst($kpi->category) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Unit:</td>
                                <td>
                                    @php $detailUnit = $kpi->unit; @endphp
                                    @if($detailUnit === 'percentage')
                                        Percentage (%)
                                    @elseif($detailUnit === 'ratio')
                                        Ratio (x)
                                    @elseif($detailUnit === 'currency')
                                        Currency (₦)
                                    @elseif($detailUnit === 'days')
                                        Days
                                    @else
                                        {{ ucfirst($detailUnit) }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Frequency:</td>
                                <td>{{ ucfirst($kpi->frequency) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Target:</td>
                                <td>
                                    @if($kpi->target_value)
                                        {{ number_format($kpi->target_value, 2) }}{{ $kpi->unit === 'percentage' ? '%' : '' }}{{ $kpi->unit === 'ratio' ? 'x' : '' }}
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Comparison:</td>
                                <td>
                                    <i class="mdi mdi-arrow-up text-success"></i> Higher is Better
                                </td>
                            </tr>
                        </table>
                        @if($kpi->description)
                            <hr>
                            <p class="text-muted mb-0 small">{{ $kpi->description }}</p>
                        @endif
                    </div>
                </div>

                {{-- Thresholds --}}
                <div class="card-modern mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="mdi mdi-alert-circle mr-2"></i>Thresholds</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <h6 class="text-warning">Warning</h6>
                                <p class="mb-0">
                                    @if($kpi->warning_threshold_low || $kpi->warning_threshold_high)
                                        {{ $kpi->warning_threshold_low ?? '-' }} to {{ $kpi->warning_threshold_high ?? '-' }}
                                    @else
                                        Not set
                                    @endif
                                </p>
                            </div>
                            <div class="col-6">
                                <h6 class="text-danger">Critical</h6>
                                <p class="mb-0">
                                    @if($kpi->critical_threshold_low || $kpi->critical_threshold_high)
                                        {{ $kpi->critical_threshold_low ?? '-' }} to {{ $kpi->critical_threshold_high ?? '-' }}
                                    @else
                                        Not set
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Statistics --}}
                @isset($statistics)
                    <div class="card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-chart-box mr-2"></i>Statistics</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted">Min Value:</td>
                                    <td class="text-right">{{ number_format($statistics->min_value, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Max Value:</td>
                                    <td class="text-right">{{ number_format($statistics->max_value, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Average:</td>
                                    <td class="text-right">{{ number_format($statistics->avg_value, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Total Records:</td>
                                    <td class="text-right">{{ $statistics->total_records }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                @endisset

                {{-- Actions --}}
                <div class="card-modern">
                    <div class="card-body">
                        <button type="button" class="btn btn-success btn-block mb-2" id="calculateBtn">
                            <i class="mdi mdi-calculator"></i> Calculate Now
                        </button>
                        <a href="{{ route('accounting.kpi.edit', $kpi->id) }}" class="btn btn-outline-primary btn-block mb-2">
                            <i class="mdi mdi-pencil"></i> Edit KPI
                        </a>
                        <a href="{{ route('accounting.kpi.dashboard') }}" class="btn btn-secondary btn-block">
                            <i class="mdi mdi-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
$(document).ready(function() {
    @if($history->count() > 0)
    (function() {
        var ctx = document.getElementById('trendChart').getContext('2d');
        var chartData = @json($chartData);
        var targetValue = {{ $kpi->target_value ?? 'null' }};

        var datasets = [{
            label: '{{ $kpi->kpi_name }}',
            data: chartData.values,
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: chartData.statusColors,
            pointBorderColor: chartData.statusColors,
            pointRadius: 5
        }];

        if (targetValue !== null) {
            datasets.push({
                label: 'Target',
                data: Array(chartData.labels.length).fill(targetValue),
                borderColor: '#28a745',
                borderWidth: 2,
                borderDash: [5, 5],
                fill: false,
                pointRadius: 0
            });
        }

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var value = context.parsed.y;
                                var unit = '{{ $kpi->unit }}';
                                if (unit === 'percentage') return context.dataset.label + ': ' + value.toFixed(2) + '%';
                                if (unit === 'ratio') return context.dataset.label + ': ' + value.toFixed(2) + 'x';
                                if (unit === 'currency') return context.dataset.label + ': ₦' + value.toLocaleString();
                                return context.dataset.label + ': ' + value.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    y: { beginAtZero: false, grid: { color: 'rgba(0,0,0,0.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    })();
    @endif

    $('#calculateBtn').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Calculating...');

        $.ajax({
            url: '/accounting/kpi/calculate-single/{{ $kpi->id }}',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                toastr.success('KPI calculated successfully');
                location.reload();
            },
            error: function(xhr) {
                toastr.error('Failed to calculate KPI');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="mdi mdi-calculator"></i> Calculate Now');
            }
        });
    });
});
</script>
@endpush
