{{--
    Cash Flow Forecast Details
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.6
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'Cash Flow Forecast: ' . $forecast->name)
@section('page_name', 'Accounting')
@section('subpage_name', 'Forecast Details')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Cash Flow Forecast', 'url' => route('accounting.cash-flow-forecast.index'), 'icon' => 'mdi-chart-timeline-variant'],
        ['label' => $forecast->name, 'url' => '#', 'icon' => 'mdi-eye']
    ]
])

<style>
.forecast-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 20px;
}
.forecast-header h3 { margin: 0; font-weight: 600; }
.info-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
    margin-bottom: 20px;
}
.info-card h6 {
    font-weight: 600;
    color: #333;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f1f1f1;
}
.stat-box {
    text-align: center;
    padding: 15px;
    border-radius: 8px;
    background: #f8f9fa;
}
.stat-box .amount { font-size: 1.3rem; font-weight: 700; }
.stat-box .label { color: #666; font-size: 0.8rem; }
.period-row {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 10px;
    overflow: hidden;
}
.period-header {
    padding: 12px 15px;
    background: #f8f9fa;
    cursor: pointer;
}
.period-header:hover { background: #e9ecef; }
.period-body { padding: 15px; display: none; border-top: 1px solid #e9ecef; }
.period-row.expanded .period-body { display: block; }
.balance-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    margin-bottom: 20px;
}
.balance-card .amount { font-size: 1.8rem; font-weight: 700; }
.variance-positive { color: #28a745; }
.variance-negative { color: #dc3545; }
</style>

<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <!-- Header -->
    <div class="forecast-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h3>{{ $forecast->name }}</h3>
                <div class="mt-2">
                    <span class="badge badge-light mr-2">
                        {{ \Carbon\Carbon::parse($forecast->start_date)->format('M d, Y') }} -
                        {{ \Carbon\Carbon::parse($forecast->end_date)->format('M d, Y') }}
                    </span>
                    <span class="badge badge-{{ $forecast->status == 'active' ? 'success' : ($forecast->status == 'draft' ? 'secondary' : 'dark') }}">
                        {{ ucfirst($forecast->status) }}
                    </span>
                    <span class="badge badge-light ml-1">{{ ucfirst($forecast->frequency) }}</span>
                </div>
            </div>
            <div class="col-md-4 text-md-right mt-3 mt-md-0">
                @if($forecast->status == 'draft')
                    <button class="btn btn-success btn-sm" id="activateForecast">
                        <i class="mdi mdi-check"></i> Activate
                    </button>
                @endif
                <button class="btn btn-light btn-sm" onclick="window.print()">
                    <i class="mdi mdi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row">
        <div class="col-md-3 mb-3">
            <div class="stat-box" style="background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);">
                <div class="amount text-success">₦{{ number_format($currentCash, 2) }}</div>
                <div class="label">Current Cash Position</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-box" style="background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);">
                <div class="amount text-info">₦{{ number_format($periodsWithBalance->sum('forecasted_inflows'), 2) }}</div>
                <div class="label">Total Forecasted Inflows</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-box" style="background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);">
                <div class="amount text-danger">₦{{ number_format($periodsWithBalance->sum('forecasted_outflows'), 2) }}</div>
                <div class="label">Total Forecasted Outflows</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            @php
                $netFlow = $periodsWithBalance->sum('forecasted_inflows') - $periodsWithBalance->sum('forecasted_outflows');
            @endphp
            <div class="stat-box" style="background: linear-gradient(135deg, {{ $netFlow >= 0 ? '#d4edda' : '#f8d7da' }} 0%, {{ $netFlow >= 0 ? '#c3e6cb' : '#f5c6cb' }} 100%);">
                <div class="amount {{ $netFlow >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ $netFlow >= 0 ? '+' : '' }}₦{{ number_format($netFlow, 2) }}
                </div>
                <div class="label">Net Cash Flow</div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Chart -->
            <div class="info-card">
                <h6><i class="mdi mdi-chart-line mr-2"></i>Cash Flow Projection</h6>
                <canvas id="forecastChart" height="250"></canvas>
            </div>

            <!-- Periods -->
            <div class="info-card">
                <h6><i class="mdi mdi-calendar-month mr-2"></i>Forecast Periods ({{ $periodsWithBalance->count() }})</h6>

                @foreach($periodsWithBalance as $period)
                    @php
                        $isPast = \Carbon\Carbon::parse($period->period_end)->lt(now());
                        $hasVariance = $period->variance !== null;
                    @endphp
                    <div class="period-row {{ $loop->first ? 'expanded' : '' }}">
                        <div class="period-header" data-toggle="period">
                            <div class="row align-items-center">
                                <div class="col-md-2">
                                    <strong>
                                        {{ \Carbon\Carbon::parse($period->period_start)->format('M d') }} -
                                        {{ \Carbon\Carbon::parse($period->period_end)->format('M d') }}
                                    </strong>
                                    @if($isPast)
                                        <span class="badge badge-secondary badge-sm ml-1">Past</span>
                                    @endif
                                </div>
                                <div class="col-md-2 text-right">
                                    <small class="text-muted">Inflows</small><br>
                                    <span class="text-success">₦{{ number_format($period->forecasted_inflows, 0) }}</span>
                                </div>
                                <div class="col-md-2 text-right">
                                    <small class="text-muted">Outflows</small><br>
                                    <span class="text-danger">₦{{ number_format($period->forecasted_outflows, 0) }}</span>
                                </div>
                                <div class="col-md-2 text-right">
                                    <small class="text-muted">Net Flow</small><br>
                                    <span class="{{ $period->net_cash_flow >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ $period->net_cash_flow >= 0 ? '+' : '' }}₦{{ number_format($period->net_cash_flow, 0) }}
                                    </span>
                                </div>
                                <div class="col-md-2 text-right">
                                    <small class="text-muted">Ending Balance</small><br>
                                    <strong class="{{ $period->ending_balance >= 0 ? 'text-success' : 'text-danger' }}">
                                        ₦{{ number_format($period->ending_balance, 0) }}
                                    </strong>
                                </div>
                                <div class="col-md-2 text-right">
                                    @if($hasVariance)
                                        <small class="text-muted">Variance</small><br>
                                        <span class="{{ $period->variance >= 0 ? 'variance-positive' : 'variance-negative' }}">
                                            {{ $period->variance >= 0 ? '+' : '' }}₦{{ number_format($period->variance, 0) }}
                                        </span>
                                    @else
                                        <i class="mdi mdi-chevron-down"></i>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="period-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-2">Forecasted</h6>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Inflows:</span>
                                        <strong class="text-success">₦{{ number_format($period->forecasted_inflows, 2) }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Outflows:</span>
                                        <strong class="text-danger">₦{{ number_format($period->forecasted_outflows, 2) }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Net:</span>
                                        <strong class="{{ $period->net_cash_flow >= 0 ? 'text-success' : 'text-danger' }}">
                                            ₦{{ number_format($period->net_cash_flow, 2) }}
                                        </strong>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-2">Actual (if recorded)</h6>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Inflows:</span>
                                        <strong class="text-success">₦{{ number_format($period->actual_inflows, 2) }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Outflows:</span>
                                        <strong class="text-danger">₦{{ number_format($period->actual_outflows, 2) }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Net:</span>
                                        @php
                                            $actualNet = $period->actual_inflows - $period->actual_outflows;
                                        @endphp
                                        <strong class="{{ $actualNet >= 0 ? 'text-success' : 'text-danger' }}">
                                            ₦{{ number_format($actualNet, 2) }}
                                        </strong>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <a href="{{ route('accounting.cash-flow-forecast.periods.edit', $period->id) }}" class="btn btn-outline-primary btn-sm">
                                    <i class="mdi mdi-pencil mr-1"></i> Edit Forecast
                                </a>
                                <button class="btn btn-outline-info btn-sm update-actuals" data-id="{{ $period->id }}"
                                        data-inflows="{{ $period->actual_inflows }}" data-outflows="{{ $period->actual_outflows }}">
                                    <i class="mdi mdi-refresh mr-1"></i> Update Actuals
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Projected Ending Cash -->
            @php
                $projectedEnding = $periodsWithBalance->last()->ending_balance ?? $currentCash;
            @endphp
            <div class="balance-card" style="background: linear-gradient(135deg, {{ $projectedEnding >= 0 ? '#28a745' : '#dc3545' }} 0%, {{ $projectedEnding >= 0 ? '#20c997' : '#c82333' }} 100%);">
                <div class="label">Projected Ending Cash</div>
                <div class="amount">₦{{ number_format($projectedEnding, 2) }}</div>
                <div class="label">{{ \Carbon\Carbon::parse($forecast->end_date)->format('M d, Y') }}</div>
            </div>

            <!-- Forecast Info -->
            <div class="info-card">
                <h6><i class="mdi mdi-information-outline mr-2"></i>Forecast Details</h6>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Status:</span>
                    <span class="badge badge-{{ $forecast->status == 'active' ? 'success' : 'secondary' }}">
                        {{ ucfirst($forecast->status) }}
                    </span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Frequency:</span>
                    <strong>{{ ucfirst($forecast->frequency) }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Periods:</span>
                    <strong>{{ $periodsWithBalance->count() }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Created By:</span>
                    <strong>{{ $forecast->createdBy->name ?? 'System' }}</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Created:</span>
                    <strong>{{ $forecast->created_at->format('M d, Y') }}</strong>
                </div>
                @if($forecast->description)
                    <hr>
                    <small class="text-muted">{{ $forecast->description }}</small>
                @endif
            </div>

            <!-- Actions -->
            <div class="info-card">
                <h6><i class="mdi mdi-cog mr-2"></i>Actions</h6>
                @if($forecast->status == 'draft')
                    <button class="btn btn-success btn-block btn-sm mb-2" id="activateForecastBtn">
                        <i class="mdi mdi-check mr-1"></i> Activate Forecast
                    </button>
                @endif
                <a href="{{ route('accounting.cash-flow-forecast.export.pdf', $forecast->id) }}" class="btn btn-outline-info btn-block btn-sm mb-2">
                    <i class="mdi mdi-file-pdf mr-1"></i> Export PDF
                </a>
                <a href="{{ route('accounting.cash-flow-forecast.export.excel', $forecast->id) }}" class="btn btn-outline-success btn-block btn-sm mb-2">
                    <i class="mdi mdi-file-excel mr-1"></i> Export Excel
                </a>
                <a href="{{ route('accounting.cash-flow-forecast.index') }}" class="btn btn-outline-secondary btn-block btn-sm">
                    <i class="mdi mdi-arrow-left mr-1"></i> Back to List
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Update Actuals Modal -->
<div class="modal fade" id="actualsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="actualsForm">
                <div class="modal-header">
                    <h5 class="modal-title">Update Actual Cash Flows</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="actualsPeriodId">
                    <div class="form-group">
                        <label>Actual Inflows</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">₦</span>
                            </div>
                            <input type="number" id="actualInflows" class="form-control" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Actual Outflows</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">₦</span>
                            </div>
                            <input type="number" id="actualOutflows" class="form-control" step="0.01" min="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="mdi mdi-content-save mr-1"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    // Toggle period
    $(document).on('click', '[data-toggle="period"]', function() {
        $(this).closest('.period-row').toggleClass('expanded');
    });

    // Chart
    var chartData = @json($chartData);

    new Chart(document.getElementById('forecastChart'), {
        type: 'line',
        data: {
            labels: chartData.map(d => d.period),
            datasets: [
                {
                    label: 'Ending Balance',
                    data: chartData.map(d => d.ending_balance),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    fill: true,
                    tension: 0.3,
                    yAxisID: 'y'
                },
                {
                    label: 'Forecasted Inflows',
                    data: chartData.map(d => d.forecasted_inflows),
                    borderColor: '#28a745',
                    backgroundColor: 'transparent',
                    borderDash: [5, 5],
                    tension: 0.3,
                    yAxisID: 'y1'
                },
                {
                    label: 'Forecasted Outflows',
                    data: chartData.map(d => d.forecasted_outflows),
                    borderColor: '#dc3545',
                    backgroundColor: 'transparent',
                    borderDash: [5, 5],
                    tension: 0.3,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            },
            scales: {
                y: {
                    type: 'linear',
                    position: 'left',
                    title: { display: true, text: 'Balance' },
                    ticks: {
                        callback: function(value) {
                            return '₦' + (value / 1000) + 'K';
                        }
                    }
                },
                y1: {
                    type: 'linear',
                    position: 'right',
                    title: { display: true, text: 'Cash Flows' },
                    grid: { drawOnChartArea: false },
                    ticks: {
                        callback: function(value) {
                            return '₦' + (value / 1000) + 'K';
                        }
                    }
                }
            }
        }
    });

    // Activate forecast
    $('#activateForecast, #activateForecastBtn').on('click', function() {
        if (confirm('Activate this forecast? This will deactivate any currently active forecast.')) {
            $.ajax({
                url: '{{ route('accounting.cash-flow-forecast.show', $forecast->id) }}/activate',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        location.reload();
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function() {
                    toastr.error('Failed to activate forecast');
                }
            });
        }
    });

    // Update actuals modal
    $('.update-actuals').on('click', function() {
        var id = $(this).data('id');
        var inflows = $(this).data('inflows');
        var outflows = $(this).data('outflows');

        $('#actualsPeriodId').val(id);
        $('#actualInflows').val(inflows);
        $('#actualOutflows').val(outflows);

        $('#actualsModal').modal('show');
    });

    // Save actuals
    $('#actualsForm').on('submit', function(e) {
        e.preventDefault();
        var id = $('#actualsPeriodId').val();

        $.ajax({
            url: '/accounting/cash-flow-forecast/periods/' + id + '/actuals',
            type: 'PUT',
            data: {
                _token: '{{ csrf_token() }}',
                actual_inflows: $('#actualInflows').val(),
                actual_outflows: $('#actualOutflows').val()
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    $('#actualsModal').modal('hide');
                    location.reload();
                } else {
                    toastr.error(response.message);
                }
            },
            error: function() {
                toastr.error('Failed to update actuals');
            }
        });
    });
});
</script>
@endpush
