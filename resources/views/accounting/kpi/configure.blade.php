@extends('admin.layouts.app')
@section('title', 'KPI Dashboard Configuration')
@section('page_name', 'Accounting')
@section('subpage_name', 'KPI Configuration')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'KPI Dashboard', 'url' => route('accounting.kpi.dashboard'), 'icon' => 'mdi-chart-box'],
    ['label' => 'Configure', 'url' => '#', 'icon' => 'mdi-cog']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Header -->
        <div class="card card-modern mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h4 class="mb-0"><i class="mdi mdi-view-dashboard-edit mr-2"></i>Dashboard Configuration</h4>
                        <small class="text-muted">Configure which KPIs appear on the dashboard</small>
                    </div>
                    <div class="col-md-6 text-right">
                        <a href="{{ route('accounting.kpi.dashboard') }}" class="btn btn-outline-primary">
                            <i class="mdi mdi-view-dashboard"></i> View Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <form action="{{ route('accounting.kpi.configure.save') }}" method="POST" id="configForm">
            @csrf
            <div class="row">
                <!-- KPI Selection -->
                <div class="col-lg-8">
                    @foreach($kpisByCategory as $category => $kpis)
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
                                {{ ucfirst($category) }} KPIs
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th width="50">Show</th>
                                            <th>KPI</th>
                                            <th width="120">Order</th>
                                            <th width="150">Chart Type</th>
                                        </tr>
                                    </thead>
                                    <tbody class="sortable-list" data-category="{{ $category }}">
                                        @foreach($kpis as $kpi)
                                        <tr data-id="{{ $kpi->id }}" class="sortable-item">
                                            <td>
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input"
                                                           id="show_{{ $kpi->id }}"
                                                           name="kpis[{{ $kpi->id }}][show_on_dashboard]"
                                                           value="1"
                                                           {{ $kpi->show_on_dashboard ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="show_{{ $kpi->id }}"></label>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-secondary mr-2">{{ $kpi->kpi_code }}</span>
                                                {{ $kpi->kpi_name }}
                                                @if(!$kpi->is_active)
                                                    <span class="badge badge-warning">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm"
                                                       name="kpis[{{ $kpi->id }}][display_order]"
                                                       value="{{ $kpi->display_order }}"
                                                       min="0" style="width: 80px;">
                                            </td>
                                            <td>
                                                <select class="form-control form-control-sm"
                                                        name="kpis[{{ $kpi->id }}][chart_type]">
                                                    <option value="gauge" {{ $kpi->chart_type == 'gauge' ? 'selected' : '' }}>Gauge</option>
                                                    <option value="line" {{ $kpi->chart_type == 'line' ? 'selected' : '' }}>Line</option>
                                                    <option value="bar" {{ $kpi->chart_type == 'bar' ? 'selected' : '' }}>Bar</option>
                                                    <option value="number" {{ $kpi->chart_type == 'number' ? 'selected' : '' }}>Number</option>
                                                </select>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endforeach

                    @if($kpisByCategory->isEmpty())
                    <div class="card card-modern">
                        <div class="card-body text-center py-5">
                            <i class="mdi mdi-chart-box-outline mdi-48px text-muted"></i>
                            <h5 class="mt-3">No KPIs Defined</h5>
                            <p class="text-muted">Create KPIs to configure the dashboard.</p>
                            <a href="{{ route('accounting.kpi.create') }}" class="btn btn-primary">
                                <i class="mdi mdi-plus"></i> Create KPI
                            </a>
                        </div>
                    </div>
                    @endif
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Quick Actions -->
                    <div class="card card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-lightning-bolt mr-2"></i>Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <button type="button" class="btn btn-outline-primary btn-block mb-2" id="selectAll">
                                <i class="mdi mdi-checkbox-multiple-marked"></i> Show All
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-block mb-2" id="deselectAll">
                                <i class="mdi mdi-checkbox-multiple-blank-outline"></i> Hide All
                            </button>
                            <button type="button" class="btn btn-outline-info btn-block" id="resetOrder">
                                <i class="mdi mdi-sort-ascending"></i> Reset Order
                            </button>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="card card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-information mr-2"></i>Summary</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted">Total KPIs:</td>
                                    <td class="text-right" id="totalKpis">{{ $kpisByCategory->flatten()->count() }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Shown on Dashboard:</td>
                                    <td class="text-right" id="shownKpis">{{ $kpisByCategory->flatten()->where('show_on_dashboard', true)->count() }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Active KPIs:</td>
                                    <td class="text-right">{{ $kpisByCategory->flatten()->where('is_active', true)->count() }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div class="card card-modern">
                        <div class="card-body">
                            <button type="submit" class="btn btn-success btn-block mb-2">
                                <i class="mdi mdi-check"></i> Save Configuration
                            </button>
                            <a href="{{ route('accounting.kpi.dashboard') }}" class="btn btn-secondary btn-block">
                                <i class="mdi mdi-arrow-left"></i> Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Update shown count
    function updateShownCount() {
        var count = $('input[name*="show_on_dashboard"]:checked').length;
        $('#shownKpis').text(count);
    }

    $('input[name*="show_on_dashboard"]').on('change', updateShownCount);

    // Select/Deselect All
    $('#selectAll').on('click', function() {
        $('input[name*="show_on_dashboard"]').prop('checked', true);
        updateShownCount();
    });

    $('#deselectAll').on('click', function() {
        $('input[name*="show_on_dashboard"]').prop('checked', false);
        updateShownCount();
    });

    // Reset Order
    $('#resetOrder').on('click', function() {
        $('input[name*="display_order"]').each(function(index) {
            $(this).val(index);
        });
        toastr.info('Display order reset');
    });

    // Form Submit
    $('#configForm').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var btn = form.find('button[type="submit"]');

        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving...');

        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: form.serialize(),
            success: function(response) {
                toastr.success('Configuration saved successfully');
                setTimeout(function() {
                    window.location.href = '{{ route("accounting.kpi.dashboard") }}';
                }, 1000);
            },
            error: function(xhr) {
                toastr.error('Failed to save configuration');
                btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save Configuration');
            }
        });
    });
});
</script>
@endpush
