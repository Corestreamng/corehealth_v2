@extends('admin.layouts.app')
@section('title', 'Financial KPI Dashboard')
@section('page_name', 'Accounting')
@section('subpage_name', 'KPI Dashboard')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'KPI Dashboard', 'url' => '#', 'icon' => 'mdi-chart-box']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Header Stats -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card card-modern h-100 border-left-primary">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon-box bg-primary rounded-circle p-3 mr-3">
                            <i class="mdi mdi-gauge text-white mdi-24px"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total KPIs</h6>
                            <h4 class="mb-0">{{ $stats['total_kpis'] }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card card-modern h-100 border-left-success">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon-box bg-success rounded-circle p-3 mr-3">
                            <i class="mdi mdi-check-circle text-white mdi-24px"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Healthy</h6>
                            <h4 class="mb-0 text-success">{{ $stats['healthy'] }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card card-modern h-100 border-left-warning">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon-box bg-warning rounded-circle p-3 mr-3">
                            <i class="mdi mdi-alert text-white mdi-24px"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Warning</h6>
                            <h4 class="mb-0 text-warning">{{ $stats['warning'] }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card card-modern h-100 border-left-danger">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon-box bg-danger rounded-circle p-3 mr-3">
                            <i class="mdi mdi-alert-circle text-white mdi-24px"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Critical</h6>
                            <h4 class="mb-0 text-danger">{{ $stats['critical'] }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions Bar -->
        <div class="card card-modern mb-4">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted">Last calculated: <strong>{{ date('M d, Y H:i') }}</strong></span>
                        @if($stats['active_alerts'] > 0)
                            <span class="badge badge-danger ml-3">
                                <i class="mdi mdi-bell-ring"></i> {{ $stats['active_alerts'] }} Active Alerts
                            </span>
                        @endif
                    </div>
                    <div>
                        <a href="{{ route('accounting.kpi.export.pdf', request()->query()) }}" class="btn btn-danger btn-sm mr-2">
                            <i class="mdi mdi-file-pdf"></i> Export PDF
                        </a>
                        <a href="{{ route('accounting.kpi.alerts') }}" class="btn btn-outline-warning btn-sm mr-2">
                            <i class="mdi mdi-bell"></i> View Alerts
                        </a>
                        <button type="button" class="btn btn-outline-success btn-sm mr-2" data-toggle="modal" data-target="#calculateModal">
                            <i class="mdi mdi-calculator"></i> Calculate All
                        </button>
                        <a href="{{ route('accounting.kpi.index') }}" class="btn btn-outline-primary btn-sm mr-2">
                            <i class="mdi mdi-cog"></i> Manage KPIs
                        </a>
                        <a href="{{ route('accounting.kpi.configure') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="mdi mdi-view-dashboard-edit"></i> Configure
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPIs by Category -->
        @foreach($groupedKpis as $category => $kpis)
        <div class="card card-modern mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    @php
                        $categoryIcons = [
                            'liquidity' => 'mdi-water',
                            'profitability' => 'mdi-currency-usd',
                            'efficiency' => 'mdi-speedometer',
                            'solvency' => 'mdi-shield-check',
                            'leverage' => 'mdi-scale-balance',
                        ];
                    @endphp
                    <i class="mdi {{ $categoryIcons[$category] ?? 'mdi-chart-box' }} mr-2"></i>
                    {{ ucfirst($category) }} Ratios
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    @foreach($kpis as $kpiData)
                    @php
                        $kpi = $kpiData['kpi'];
                        $latest = $kpiData['latest'];
                        $status = $kpiData['status'];
                        $history = $kpiData['history'];

                        $statusColors = [
                            'normal' => 'success',
                            'warning' => 'warning',
                            'critical' => 'danger',
                            'no-data' => 'secondary',
                        ];
                        $statusColor = $statusColors[$status] ?? 'secondary';
                    @endphp
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <div class="card h-100 border-{{ $statusColor }}">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <small class="text-muted">{{ $kpi->kpi_code }}</small>
                                    <span class="badge badge-{{ $statusColor }}">
                                        @if($status === 'normal')
                                            <i class="mdi mdi-check"></i>
                                        @elseif($status === 'warning')
                                            <i class="mdi mdi-alert"></i>
                                        @elseif($status === 'critical')
                                            <i class="mdi mdi-alert-circle"></i>
                                        @else
                                            <i class="mdi mdi-minus"></i>
                                        @endif
                                    </span>
                                </div>
                                <h6 class="card-title mb-2">{{ $kpi->kpi_name }}</h6>

                                @if($latest)
                                <h3 class="mb-1 text-{{ $statusColor }}">
                                    @switch($kpi->unit)
                                        @case('percentage')
                                            {{ number_format($latest->value, 2) }}%
                                            @break
                                        @case('ratio')
                                            {{ number_format($latest->value, 2) }}x
                                            @break
                                        @case('currency')
                                            ₦{{ number_format($latest->value, 0) }}
                                            @break
                                        @case('days')
                                            {{ number_format($latest->value, 0) }} days
                                            @break
                                        @default
                                            {{ number_format($latest->value, 2) }}
                                    @endswitch
                                </h3>

                                @if($latest->change_percentage !== null)
                                <small class="{{ $latest->change_percentage >= 0 ? 'text-success' : 'text-danger' }}">
                                    <i class="mdi {{ $latest->change_percentage >= 0 ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i>
                                    {{ number_format(abs($latest->change_percentage), 1) }}% from last period
                                </small>
                                @endif

                                <!-- Mini Trend Line -->
                                @if($history->count() > 1)
                                <div class="mt-3" style="height: 40px;">
                                    <canvas id="trend-{{ $kpi->id }}" data-values="{{ $history->pluck('value')->toJson() }}"></canvas>
                                </div>
                                @endif
                                @else
                                <h4 class="text-muted">No Data</h4>
                                <small class="text-muted">Run calculation to generate values</small>
                                @endif

                                @if($kpi->target_value)
                                <div class="mt-2">
                                    <small class="text-muted">Target:
                                        @switch($kpi->unit)
                                            @case('percentage')
                                                {{ number_format($kpi->target_value, 2) }}%
                                                @break
                                            @case('ratio')
                                                {{ number_format($kpi->target_value, 2) }}x
                                                @break
                                            @default
                                                {{ number_format($kpi->target_value, 2) }}
                                        @endswitch
                                    </small>
                                </div>
                                @endif
                            </div>
                            <div class="card-footer bg-transparent py-2">
                                <a href="{{ route('accounting.kpi.history', $kpi->id) }}" class="btn btn-sm btn-outline-secondary btn-block">
                                    <i class="mdi mdi-chart-line"></i> View History
                                </a>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endforeach

        @if($groupedKpis->isEmpty())
        <div class="card card-modern">
            <div class="card-body text-center py-5">
                <i class="mdi mdi-chart-box-outline mdi-48px text-muted"></i>
                <h5 class="mt-3">No KPIs Configured</h5>
                <p class="text-muted">Create KPI definitions to start tracking financial metrics.</p>
                <a href="{{ route('accounting.kpi.create') }}" class="btn btn-primary">
                    <i class="mdi mdi-plus"></i> Create KPI
                </a>
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Calculate Modal -->
<div class="modal fade" id="calculateModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('accounting.kpi.calculate') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="mdi mdi-calculator mr-2"></i>Calculate All KPIs</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="mdi mdi-information"></i>
                        This will calculate values for all active KPIs for the selected period.
                    </div>
                    <div class="form-group">
                        <label for="calculation_date">Calculation Date <span class="text-danger">*</span></label>
                        <input type="date" name="calculation_date" id="calculation_date" class="form-control"
                               value="{{ date('Y-m-d') }}" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="mdi mdi-calculator"></i> Calculate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize mini trend charts
    $('canvas[id^="trend-"]').each(function() {
        var ctx = this.getContext('2d');
        var values = $(this).data('values') || [];

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: values.map((_, i) => ''),
                datasets: [{
                    data: values,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0,123,255,0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { display: false },
                    y: { display: false }
                }
            }
        });
    });
});
</script>
@endpush
