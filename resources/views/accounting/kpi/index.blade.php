@extends('admin.layouts.app')
@section('title', 'KPI Definitions')
@section('page_name', 'Accounting')
@section('subpage_name', 'KPI Definitions')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'KPI Dashboard', 'url' => route('accounting.kpi.dashboard'), 'icon' => 'mdi-chart-box'],
    ['label' => 'KPI Definitions', 'url' => '#', 'icon' => 'mdi-format-list-bulleted']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Header -->
        <div class="card-modern mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h4 class="mb-0"><i class="mdi mdi-gauge mr-2"></i>KPI Definitions</h4>
                        <small class="text-muted">Manage financial Key Performance Indicators</small>
                    </div>
                    <div class="col-md-6 text-right">
                        <div class="btn-group mr-2">
                            <a href="{{ route('accounting.kpi.export.pdf', request()->query()) }}" class="btn btn-danger btn-sm">
                                <i class="mdi mdi-file-pdf"></i> PDF
                            </a>
                        </div>
                        <a href="{{ route('accounting.kpi.dashboard') }}" class="btn btn-outline-primary mr-2">
                            <i class="mdi mdi-view-dashboard"></i> Dashboard
                        </a>
                        @hasanyrole('SUPERADMIN|ADMIN|ACCOUNTS')
                        <a href="{{ route('accounting.kpi.create') }}" class="btn btn-success">
                            <i class="mdi mdi-plus"></i> New KPI
                        </a>
                        @endhasanyrole
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card-modern mb-4">
            <div class="card-body py-3">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <select id="filter_category" class="form-control form-control-sm">
                            <option value="">All Categories</option>
                            <option value="liquidity">Liquidity</option>
                            <option value="profitability">Profitability</option>
                            <option value="efficiency">Efficiency</option>
                            <option value="solvency">Solvency</option>
                            <option value="leverage">Leverage</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <select id="filter_frequency" class="form-control form-control-sm">
                            <option value="">All Frequencies</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <select id="filter_status" class="form-control form-control-sm">
                            <option value="">All Status</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2 text-right">
                        <button type="button" class="btn btn-secondary btn-sm" id="resetFilters">
                            <i class="mdi mdi-refresh"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="card-modern card-modern">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="kpisTable">
                    <thead class="thead-light">
                        <tr>
                            <th width="100">Code</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th>Frequency</th>
                            <th>Target</th>
                            <th width="80">Dashboard</th>
                            <th width="80">Status</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    var table = $('#kpisTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("accounting.kpi.datatable") }}',
            data: function(d) {
                d.category = $('#filter_category').val();
                d.frequency = $('#filter_frequency').val();
                d.is_active = $('#filter_status').val();
            }
        },
        columns: [
            { data: 'kpi_code', name: 'kpi_code' },
            { data: 'kpi_name', name: 'kpi_name' },
            {
                data: 'category',
                name: 'category',
                render: function(data) {
                    var badges = {
                        'liquidity': 'info',
                        'profitability': 'success',
                        'efficiency': 'primary',
                        'solvency': 'warning',
                        'leverage': 'secondary'
                    };
                    return '<span class="badge badge-' + (badges[data] || 'dark') + '">' +
                           data.charAt(0).toUpperCase() + data.slice(1) + '</span>';
                }
            },
            {
                data: 'unit',
                name: 'unit',
                render: function(data) {
                    var icons = {
                        'percentage': '%',
                        'ratio': 'x',
                        'currency': '₦',
                        'days': 'days',
                        'number': '#'
                    };
                    return icons[data] || data;
                }
            },
            {
                data: 'frequency',
                name: 'frequency',
                render: function(data) {
                    return data ? data.charAt(0).toUpperCase() + data.slice(1) : '-';
                }
            },
            {
                data: 'target_value',
                name: 'target_value',
                render: function(data, type, row) {
                    if (!data) return '-';
                    if (row.unit === 'percentage') return data + '%';
                    if (row.unit === 'ratio') return data + 'x';
                    return data;
                }
            },
            {
                data: 'show_on_dashboard',
                name: 'show_on_dashboard',
                render: function(data) {
                    return data ? '<i class="mdi mdi-check-circle text-success"></i>' :
                                  '<i class="mdi mdi-minus-circle text-muted"></i>';
                },
                className: 'text-center'
            },
            {
                data: 'is_active',
                name: 'is_active',
                render: function(data) {
                    return data ? '<span class="badge badge-success">Active</span>' :
                                  '<span class="badge badge-secondary">Inactive</span>';
                },
                className: 'text-center'
            },
            {
                data: 'id',
                name: 'id',
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    return '<div class="btn-group btn-group-sm">' +
                        '<a href="/accounting/kpi/' + data + '/history" class="btn btn-outline-info" title="History">' +
                            '<i class="mdi mdi-chart-line"></i>' +
                        '</a>' +
                        '<a href="/accounting/kpi/' + data + '/edit" class="btn btn-outline-primary" title="Edit">' +
                            '<i class="mdi mdi-pencil"></i>' +
                        '</a>' +
                        '<button type="button" class="btn btn-outline-success btn-calculate" data-id="' + data + '" title="Calculate">' +
                            '<i class="mdi mdi-calculator"></i>' +
                        '</button>' +
                    '</div>';
                }
            }
        ],
        order: [[0, 'asc']],
        pageLength: 25
    });

    // Filters
    $('#filter_category, #filter_frequency, #filter_status').on('change', function() {
        table.draw();
    });

    $('#resetFilters').on('click', function() {
        $('#filter_category, #filter_frequency, #filter_status').val('');
        table.draw();
    });

    // Calculate single KPI
    $(document).on('click', '.btn-calculate', function() {
        var id = $(this).data('id');
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i>');

        $.ajax({
            url: '/accounting/kpi/' + id + '/calculate',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                toastr.success('KPI calculated successfully');
                table.draw(false);
            },
            error: function(xhr) {
                toastr.error('Failed to calculate KPI');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="mdi mdi-calculator"></i>');
            }
        });
    });
});
</script>
@endpush
