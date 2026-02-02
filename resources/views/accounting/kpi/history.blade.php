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
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Header -->
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
                                @if($latestValue)
                                    @php
                                        $statusColors = [
                                            'normal' => 'success',
                                            'warning' => 'warning',
                                            'critical' => 'danger',
                                        ];
                                        $statusColor = $statusColors[$latestValue->status] ?? 'secondary';
                                    @endphp
                                    <h3 class="mb-0 text-{{ $statusColor }}">
                                        @switch($kpi->unit)
                                            @case('percentage')
                                                {{ number_format($latestValue->value, 2) }}%
                                                @break
                                            @case('ratio')
                                                {{ number_format($latestValue->value, 2) }}x
                                                @break
                                            @case('currency')
                                                ₦{{ number_format($latestValue->value, 0) }}
                                                @break
                                            @case('days')
                                                {{ number_format($latestValue->value, 0) }} days
                                                @break
                                            @default
                                                {{ number_format($latestValue->value, 2) }}
                                        @endswitch
                                    </h3>
                                    <small class="text-muted">Latest Value</small>
                                @else
                                    <h4 class="text-muted mb-0">No Data</h4>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Trend Chart -->
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

                <!-- History Table -->
                <div class="card-modern card-modern">
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
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($record->calculation_date)->format('M d, Y') }}</td>
                                    <td class="text-right">
                                        @switch($kpi->unit)
                                            @case('percentage')
                                                {{ number_format($record->value, 2) }}%
                                                @break
                                            @case('ratio')
                                                {{ number_format($record->value, 2) }}x
                                                @break
                                            @case('currency')
                                                ₦{{ number_format($record->value, 0) }}
                                                @break
                                            @case('days')
                                                {{ number_format($record->value, 0) }}
                                                @break
                                            @default
                                                {{ number_format($record->value, 2) }}
                                        @endswitch
                                    </td>
                                    <td class="text-right">
                                        @if($record->change_percentage !== null)
                                            <span class="{{ $record->change_percentage >= 0 ? 'text-success' : 'text-danger' }}">
                                                <i class="mdi {{ $record->change_percentage >= 0 ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i>
                                                {{ number_format(abs($record->change_percentage), 1) }}%
                                            </span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @php
                                            $statusColors = ['normal' => 'success', 'warning' => 'warning', 'critical' => 'danger'];
                                        @endphp
                                        <span class="badge badge-{{ $statusColors[$record->status] ?? 'secondary' }}">
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

                        @if($history->hasPages())
                        <div class="d-flex justify-content-center mt-3">
                            {{ $history->links() }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- KPI Details -->
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
                                    @switch($kpi->unit)
                                        @case('percentage')
                                            Percentage (%)
                                            @break
                                        @case('ratio')
                                            Ratio (x)
                                            @break
                                        @case('currency')
                                            Currency (₦)
                                            @break
                                        @case('days')
                                            Days
                                            @break
                                        @default
                                            {{ ucfirst($kpi->unit) }}
                                    @endswitch
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Frequency:</td>
                                <td>{{ ucfirst($kpi->calculation_frequency) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Target:</td>
                                <td>
                                    @if($kpi->target_value)
                                        {{ number_format($kpi->target_value, 2) }}
                                        @if($kpi->unit === 'percentage')%@endif
                                        @if($kpi->unit === 'ratio')x@endif
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Comparison:</td>
                                <td>
                                    @if($kpi->comparison_operator === 'higher_better')
                                        <i class="mdi mdi-arrow-up text-success"></i> Higher is Better
                                    @elseif($kpi->comparison_operator === 'lower_better')
                                        <i class="mdi mdi-arrow-down text-success"></i> Lower is Better
                                    @else
                                        <i class="mdi mdi-target"></i> Target Range
                                    @endif
                                </td>
                            </tr>
                        </table>
                        @if($kpi->description)
                        <hr>
                        <p class="text-muted mb-0 small">{{ $kpi->description }}</p>
                        @endif
                    </div>
                </div>

                <!-- Thresholds -->
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

                <!-- Statistics -->
                @if($statistics)
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
                @endif

                <!-- Actions -->
                <div class="card-modern card-modern">
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
    // Trend Chart
    var ctx = document.getElementById('trendChart').getContext('2d');
    var chartData = @json($chartData);

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.labels,
            datasets: [{
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
            }
            @if($kpi->target_value)
            ,{
                label: 'Target',
                data: Array(chartData.labels.length).fill({{ $kpi->target_value }}),
                borderColor: '#28a745',
                borderWidth: 2,
                borderDash: [5, 5],
                fill: false,
                pointRadius: 0
            }
            @endif
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            var value = context.parsed.y;
                            @if($kpi->unit === 'percentage')
                                return context.dataset.label + ': ' + value.toFixed(2) + '%';
                            @elseif($kpi->unit === 'ratio')
                                return context.dataset.label + ': ' + value.toFixed(2) + 'x';
                            @elseif($kpi->unit === 'currency')
                                return context.dataset.label + ': ₦' + value.toLocaleString();
                            @else
                                return context.dataset.label + ': ' + value.toFixed(2);
                            @endif
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
    @endif

    // Calculate Button
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
