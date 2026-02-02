@extends('admin.layouts.app')
@section('title', 'KPI Alerts')
@section('page_name', 'Accounting')
@section('subpage_name', 'KPI Alerts')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'KPI Dashboard', 'url' => route('accounting.kpi.dashboard'), 'icon' => 'mdi-chart-box'],
    ['label' => 'Alerts', 'url' => '#', 'icon' => 'mdi-bell']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Header -->
        <div class="card-modern mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h4 class="mb-0"><i class="mdi mdi-bell-ring mr-2"></i>KPI Alerts</h4>
                        <small class="text-muted">Threshold breach notifications</small>
                    </div>
                    <div class="col-md-6 text-right">
                        <a href="{{ route('accounting.kpi.dashboard') }}" class="btn btn-outline-primary">
                            <i class="mdi mdi-view-dashboard"></i> Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card-modern h-100 border-left-danger">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon-box bg-danger rounded-circle p-3 mr-3">
                            <i class="mdi mdi-alert-circle text-white mdi-24px"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Critical Alerts</h6>
                            <h4 class="mb-0 text-danger">{{ $stats['critical'] ?? 0 }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card-modern h-100 border-left-warning">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon-box bg-warning rounded-circle p-3 mr-3">
                            <i class="mdi mdi-alert text-white mdi-24px"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Warning Alerts</h6>
                            <h4 class="mb-0 text-warning">{{ $stats['warning'] ?? 0 }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card-modern h-100 border-left-success">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon-box bg-success rounded-circle p-3 mr-3">
                            <i class="mdi mdi-check-circle text-white mdi-24px"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Acknowledged</h6>
                            <h4 class="mb-0 text-success">{{ $stats['acknowledged'] ?? 0 }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card-modern mb-4">
            <div class="card-body py-3">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <select id="filter_severity" class="form-control form-control-sm">
                            <option value="">All Severities</option>
                            <option value="critical">Critical</option>
                            <option value="warning">Warning</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <select id="filter_status" class="form-control form-control-sm">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="acknowledged">Acknowledged</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <input type="date" id="filter_date_from" class="form-control form-control-sm" placeholder="From Date">
                    </div>
                    <div class="col-md-3 mb-2">
                        <input type="date" id="filter_date_to" class="form-control form-control-sm" placeholder="To Date">
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts List -->
        <div class="card-modern card-modern">
            <div class="card-body">
                @forelse($alerts as $alert)
                <div class="alert alert-{{ $alert->severity === 'critical' ? 'danger' : 'warning' }}
                            {{ $alert->acknowledged_at ? 'alert-acknowledged' : '' }}
                            d-flex justify-content-between align-items-start mb-3">
                    <div class="d-flex">
                        <div class="mr-3">
                            @if($alert->severity === 'critical')
                                <i class="mdi mdi-alert-circle mdi-36px"></i>
                            @else
                                <i class="mdi mdi-alert mdi-36px"></i>
                            @endif
                        </div>
                        <div>
                            <h6 class="alert-heading mb-1">
                                {{ $alert->kpi_name ?? 'Unknown KPI' }}
                                <small class="text-muted">({{ $alert->kpi_code ?? '-' }})</small>
                            </h6>
                            <p class="mb-1">{{ $alert->message }}</p>
                            <small class="text-muted">
                                <i class="mdi mdi-clock"></i>
                                {{ \Carbon\Carbon::parse($alert->alert_date)->format('M d, Y H:i') }}
                                @if($alert->acknowledged_at)
                                    <span class="ml-3">
                                        <i class="mdi mdi-check"></i> Acknowledged by {{ $alert->acknowledged_by_name ?? 'Unknown' }}
                                        on {{ \Carbon\Carbon::parse($alert->acknowledged_at)->format('M d, Y H:i') }}
                                    </span>
                                @endif
                            </small>
                            <div class="mt-2">
                                <span class="badge badge-{{ $alert->severity === 'critical' ? 'danger' : 'warning' }}">
                                    {{ ucfirst($alert->severity) }}
                                </span>
                                <span class="badge badge-secondary ml-1">
                                    Value: {{ number_format($alert->kpi_value, 2) }}
                                </span>
                                <span class="badge badge-light ml-1">
                                    Threshold: {{ number_format($alert->threshold_value, 2) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-column">
                        @if(!$alert->acknowledged_at)
                        <button type="button" class="btn btn-sm btn-outline-success btn-acknowledge mb-2"
                                data-id="{{ $alert->id }}" title="Acknowledge">
                            <i class="mdi mdi-check"></i> Acknowledge
                        </button>
                        @endif
                        <a href="{{ route('accounting.kpi.history', $alert->financial_kpi_id) }}"
                           class="btn btn-sm btn-outline-info" title="View History">
                            <i class="mdi mdi-chart-line"></i> History
                        </a>
                    </div>
                </div>
                @empty
                <div class="text-center py-5 text-muted">
                    <i class="mdi mdi-bell-off mdi-48px"></i>
                    <h5 class="mt-3">No Alerts</h5>
                    <p>All KPIs are within normal thresholds.</p>
                </div>
                @endforelse

                @if($alerts->hasPages())
                <div class="d-flex justify-content-center mt-4">
                    {{ $alerts->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.alert-acknowledged {
    opacity: 0.7;
    border-style: dashed;
}
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    // Acknowledge Alert
    $(document).on('click', '.btn-acknowledge', function() {
        var btn = $(this);
        var alertId = btn.data('id');

        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i>');

        $.ajax({
            url: '/accounting/kpi/alerts/' + alertId + '/acknowledge',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                toastr.success('Alert acknowledged');
                location.reload();
            },
            error: function(xhr) {
                toastr.error('Failed to acknowledge alert');
                btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Acknowledge');
            }
        });
    });

    // Filters
    $('#filter_severity, #filter_status, #filter_date_from, #filter_date_to').on('change', function() {
        applyFilters();
    });

    function applyFilters() {
        var params = new URLSearchParams(window.location.search);

        var severity = $('#filter_severity').val();
        var status = $('#filter_status').val();
        var dateFrom = $('#filter_date_from').val();
        var dateTo = $('#filter_date_to').val();

        if (severity) params.set('severity', severity); else params.delete('severity');
        if (status) params.set('status', status); else params.delete('status');
        if (dateFrom) params.set('date_from', dateFrom); else params.delete('date_from');
        if (dateTo) params.set('date_to', dateTo); else params.delete('date_to');

        window.location.href = window.location.pathname + '?' + params.toString();
    }

    // Set filter values from URL
    var urlParams = new URLSearchParams(window.location.search);
    $('#filter_severity').val(urlParams.get('severity') || '');
    $('#filter_status').val(urlParams.get('status') || '');
    $('#filter_date_from').val(urlParams.get('date_from') || '');
    $('#filter_date_to').val(urlParams.get('date_to') || '');
});
</script>
@endpush
