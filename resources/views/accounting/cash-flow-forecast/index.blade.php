{{--
    Cash Flow Forecast Dashboard
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.6
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'Cash Flow Forecast')
@section('page_name', 'Accounting')
@section('subpage_name', 'Cash Flow Forecast')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Cash Flow Forecast', 'url' => '#', 'icon' => 'mdi-chart-timeline-variant']
    ]
])

<style>
.stat-card {
    border-radius: 10px;
    padding: 20px;
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-left: 4px solid;
    margin-bottom: 20px;
}
.stat-card .stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
.stat-card .stat-value { font-size: 1.5rem; font-weight: 700; color: #333; }
.stat-card .stat-label { color: #6c757d; font-size: 0.85rem; }
.filter-card {
    background: white;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.table-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
}
.forecast-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}
.forecast-card h4 { margin: 0; }
.cash-position {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
</style>

<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <!-- Stats Row -->
    <div class="row">
        <div class="col-md-3">
            <div class="stat-card" style="border-color: #28a745;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-value text-success">₦{{ number_format($stats['current_cash'], 0) }}</div>
                        <div class="stat-label">Current Cash Position</div>
                    </div>
                    <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                        <i class="mdi mdi-cash"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="border-color: #17a2b8;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-value text-info">₦{{ number_format($stats['forecasted_inflows'], 0) }}</div>
                        <div class="stat-label">Forecasted Inflows (3mo)</div>
                    </div>
                    <div class="stat-icon" style="background: rgba(23, 162, 184, 0.1); color: #17a2b8;">
                        <i class="mdi mdi-arrow-down"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="border-color: #dc3545;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-value text-danger">₦{{ number_format($stats['forecasted_outflows'], 0) }}</div>
                        <div class="stat-label">Forecasted Outflows (3mo)</div>
                    </div>
                    <div class="stat-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
                        <i class="mdi mdi-arrow-up"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="border-color: {{ $stats['projected_ending_cash'] >= 0 ? '#28a745' : '#dc3545' }};">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-value {{ $stats['projected_ending_cash'] >= 0 ? 'text-success' : 'text-danger' }}">
                            ₦{{ number_format($stats['projected_ending_cash'], 0) }}
                        </div>
                        <div class="stat-label">Projected Ending Cash</div>
                    </div>
                    <div class="stat-icon" style="background: rgba({{ $stats['projected_ending_cash'] >= 0 ? '40, 167, 69' : '220, 53, 69' }}, 0.1); color: {{ $stats['projected_ending_cash'] >= 0 ? '#28a745' : '#dc3545' }};">
                        <i class="mdi mdi-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Active Forecast Summary -->
            @if($stats['active_forecast'])
            <div class="forecast-card">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4>{{ $stats['active_forecast']->name }}</h4>
                        <p class="mb-0 opacity-75">
                            {{ \Carbon\Carbon::parse($stats['active_forecast']->start_date)->format('M d, Y') }} -
                            {{ \Carbon\Carbon::parse($stats['active_forecast']->end_date)->format('M d, Y') }}
                            ({{ $stats['period_count'] }} periods)
                        </p>
                    </div>
                    <div class="col-md-4 text-md-right">
                        <a href="{{ route('accounting.cash-flow-forecast.show', $stats['active_forecast']->id) }}" class="btn btn-light btn-sm">
                            <i class="mdi mdi-eye"></i> View Details
                        </a>
                    </div>
                </div>
            </div>
            @endif

            <!-- Filters -->
            <div class="filter-card">
                <form id="filterForm" class="row align-items-end">
                    <div class="col-md-4">
                        <label>Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="draft">Draft</option>
                            <option value="active">Active</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-primary btn-block" id="btnFilter">
                            <i class="mdi mdi-filter"></i> Filter
                        </button>
                    </div>
                    <div class="col-md-4">
                        <a href="{{ route('accounting.cash-flow-forecast.create') }}" class="btn btn-success btn-block">
                            <i class="mdi mdi-plus"></i> New Forecast
                        </a>
                    </div>
                </form>
            </div>

            <!-- DataTable -->
            <div class="table-card">
                <h6 class="font-weight-bold mb-3"><i class="mdi mdi-format-list-bulleted mr-2"></i>All Forecasts</h6>
                <div class="table-responsive">
                    <table id="forecastsTable" class="table table-hover" style="width:100%">
                    <thead class="thead-light">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Periods</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Cash Position Breakdown -->
            <div class="cash-position mb-3">
                <h6 class="font-weight-bold mb-3"><i class="mdi mdi-bank mr-2"></i>Cash Accounts</h6>
                @forelse($cashAccounts as $account)
                    @php
                        $balance = $account->currentBalance ?? 0;
                    @endphp
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>{{ $account->name }}</span>
                        <strong>₦{{ number_format($balance, 2) }}</strong>
                    </div>
                @empty
                    <p class="text-muted">No cash accounts configured</p>
                @endforelse
            </div>

            <!-- Quick Stats -->
            <div class="cash-position mb-3">
                <h6 class="font-weight-bold mb-3"><i class="mdi mdi-chart-pie mr-2"></i>Forecast Summary</h6>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span>Total Forecasts</span>
                    <strong>{{ $stats['total_forecasts'] }}</strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span>Active Forecasts</span>
                    <strong class="text-success">{{ $stats['active_forecasts'] }}</strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span>Net Forecast (3mo)</span>
                    <strong class="{{ $stats['net_forecast'] >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $stats['net_forecast'] >= 0 ? '+' : '' }}₦{{ number_format($stats['net_forecast'], 0) }}
                    </strong>
                </div>
                @if($stats['last_period_variance'] !== null)
                <div class="d-flex justify-content-between py-2">
                    <span>Last Period Variance</span>
                    <strong class="{{ $stats['last_period_variance'] >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $stats['last_period_variance'] >= 0 ? '+' : '' }}₦{{ number_format($stats['last_period_variance'], 0) }}
                    </strong>
                </div>
                @endif
            </div>

            <!-- Quick Actions -->
            <div class="cash-position">
                <h6 class="font-weight-bold mb-3"><i class="mdi mdi-cog mr-2"></i>Quick Actions</h6>
                <a href="{{ route('accounting.cash-flow-forecast.create') }}" class="btn btn-outline-success btn-block btn-sm mb-2">
                    <i class="mdi mdi-plus mr-1"></i> Create New Forecast
                </a>
                <a href="{{ route('accounting.cash-flow-forecast.patterns.index') }}" class="btn btn-outline-primary btn-block btn-sm mb-2">
                    <i class="mdi mdi-repeat mr-1"></i> Manage Patterns
                </a>
                <a href="{{ route('accounting.reports.cash-flow') }}" class="btn btn-outline-info btn-block btn-sm">
                    <i class="mdi mdi-file-chart mr-1"></i> Cash Flow Report
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('plugin_css')
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
@endpush

@push('scripts')
<!-- DataTables JS -->
<script src="{{ asset('/plugins/dataT/datatables.min.js') }}"></script>
<script>
$(document).ready(function() {
    var table = $('#forecastsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route('accounting.cash-flow-forecast.datatable') }}',
            data: function(d) {
                d.status = $('#status').val();
            }
        },
        columns: [
            { data: 'id', visible: false },
            { data: 'name' },
            { data: 'start_date' },
            { data: 'end_date' },
            { data: 'periods' },
            { data: 'status', className: 'text-center' },
            { data: 'created_at' },
            { data: 'actions', orderable: false, className: 'text-center' }
        ],
        order: [[0, 'desc']],
        pageLength: 10,
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
    });

    $('#btnFilter').on('click', function() {
        table.ajax.reload();
    });

    // Activate forecast
    $(document).on('click', '.activate-forecast', function() {
        var id = $(this).data('id');
        if (confirm('Activate this forecast? This will deactivate any currently active forecast.')) {
            $.ajax({
                url: '/accounting/cash-flow-forecast/' + id + '/activate',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        table.ajax.reload();
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
});
</script>
@endpush
