@extends('admin.layouts.app')
@section('title', 'Audit Logs')
@section('page_name', 'Audit Logs')
@section('subpage_name', 'System Activity Audit Trail')

@section('styles')
<link rel="stylesheet" href="{{ asset('/plugins/dataT/datatables.css') }}">
<style>
    .stat-card {
        transition: transform 0.2s;
    }
    .stat-card:hover {
        transform: translateY(-5px);
    }
</style>
@endsection

@section('content')
<div id="content-wrapper">
    <div class="container-fluid">

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card-modern bg-primary text-white">
                    <div class="card-body">
                        <h6 class="card-title">Total Audits</h6>
                        <h3 id="stat-total">-</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-modern bg-success text-white">
                    <div class="card-body">
                        <h6 class="card-title">Today</h6>
                        <h3 id="stat-today">-</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-modern bg-info text-white">
                    <div class="card-body">
                        <h6 class="card-title">This Week</h6>
                        <h3 id="stat-week">-</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-modern bg-warning text-white">
                    <div class="card-body">
                        <h6 class="card-title">This Month</h6>
                        <h3 id="stat-month">-</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="card-modern mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fa fa-filter"></i> Filters
                    <button class="btn btn-sm btn-outline-secondary float-right" id="reset-filters">
                        <i class="fa fa-redo"></i> Reset
                    </button>
                </h5>
            </div>
            <div class="card-body">
                <form id="filter-form">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Model Type</label>
                                <select class="form-control" id="model_type" name="model_type">
                                    <option value="">All Models</option>
                                    @foreach($modelTypes as $model)
                                        <option value="{{ $model['full'] }}">{{ $model['short'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Event Type</label>
                                <select class="form-control" id="event_type" name="event_type">
                                    <option value="">All Events</option>
                                    @foreach($eventTypes as $event)
                                        <option value="{{ $event }}">{{ ucfirst($event) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>User</label>
                                <select class="form-control" id="user_id" name="user_id">
                                    <option value="">All Users</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user['id'] }}">{{ $user['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Search</label>
                                <input type="text" class="form-control" id="search_text" name="search_text" placeholder="Search in data...">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>From Date</label>
                                <input type="date" class="form-control" id="from_date" name="from_date">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>To Date</label>
                                <input type="date" class="form-control" id="to_date" name="to_date">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="button" class="btn btn-primary btn-block" id="apply-filters">
                                    <i class="fa fa-search"></i> Apply Filters
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="button" class="btn btn-success btn-block" id="export-btn">
                                    <i class="fa fa-download"></i> Export CSV
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Data Table Card -->
        <div class="card-modern">
            <div class="card-header">
                <h5 class="mb-0"><i class="fa fa-list"></i> Audit Logs</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="audit-logs-table" class="table table-sm table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date/Time</th>
                                <th>User</th>
                                <th>Model</th>
                                <th>Event</th>
                                <th>Changes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Audit Details Modal -->
<div class="modal fade" id="audit-details-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fa fa-info-circle"></i> Audit Details
                </h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-bordered">
                            <tr>
                                <th width="35%">Audit ID:</th>
                                <td id="detail-id"></td>
                            </tr>
                            <tr>
                                <th>Event:</th>
                                <td id="detail-event"></td>
                            </tr>
                            <tr>
                                <th>Model:</th>
                                <td id="detail-model"></td>
                            </tr>
                            <tr>
                                <th>Model ID:</th>
                                <td id="detail-model-id"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-bordered">
                            <tr>
                                <th width="35%">User:</th>
                                <td id="detail-user"></td>
                            </tr>
                            <tr>
                                <th>IP Address:</th>
                                <td id="detail-ip"></td>
                            </tr>
                            <tr>
                                <th>Date/Time:</th>
                                <td id="detail-datetime"></td>
                            </tr>
                            <tr>
                                <th>URL:</th>
                                <td id="detail-url" style="word-break: break-all;"></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <hr>

                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fa fa-backward text-warning"></i> Old Values</h6>
                        <div class="card-modern">
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <pre id="detail-old-values" style="font-size: 12px; margin: 0;"></pre>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fa fa-forward text-success"></i> New Values</h6>
                        <div class="card-modern">
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <pre id="detail-new-values" style="font-size: 12px; margin: 0;"></pre>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3" id="detail-user-agent-row">
                    <div class="col-12">
                        <h6><i class="fa fa-desktop"></i> User Agent</h6>
                        <div class="card-modern">
                            <div class="card-body">
                                <small id="detail-user-agent" style="word-break: break-all;"></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}"></script>
<script>
$(document).ready(function() {
    // Load statistics
    loadStats();

    // Initialize DataTable
    const table = $('#audit-logs-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("audit-logs.data") }}',
            data: function(d) {
                d.model_type = $('#model_type').val();
                d.event_type = $('#event_type').val();
                d.user_id = $('#user_id').val();
                d.from_date = $('#from_date').val();
                d.to_date = $('#to_date').val();
                d.search_text = $('#search_text').val();
            }
        },
        columns: [
            { data: 'id', name: 'id' },
            { data: 'created_at', name: 'created_at' },
            { data: 'user_id', name: 'user_id' },
            { data: 'auditable_type', name: 'auditable_type' },
            { data: 'event', name: 'event' },
            { data: 'changes', name: 'changes', orderable: false, searchable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]]
    });

    // Apply filters
    $('#apply-filters').on('click', function() {
        table.ajax.reload();
    });

    // Reset filters
    $('#reset-filters').on('click', function() {
        $('#filter-form')[0].reset();
        table.ajax.reload();
        loadStats();
    });

    // View details
    $(document).on('click', '.view-details', function() {
        const auditId = $(this).data('audit-id');
        loadAuditDetails(auditId);
    });

    // View changes (same as details for now)
    $(document).on('click', '.view-changes', function() {
        const auditId = $(this).data('audit-id');
        loadAuditDetails(auditId);
    });

    // Export
    $('#export-btn').on('click', function() {
        const params = new URLSearchParams({
            model_type: $('#model_type').val() || '',
            event_type: $('#event_type').val() || '',
            user_id: $('#user_id').val() || '',
            from_date: $('#from_date').val() || '',
            to_date: $('#to_date').val() || ''
        });

        window.location.href = '{{ route("audit-logs.export") }}?' + params.toString();
    });

    // Load statistics
    function loadStats() {
        $.get('{{ route("audit-logs.stats") }}', function(data) {
            $('#stat-total').text(formatNumber(data.total));
            $('#stat-today').text(formatNumber(data.today));
            $('#stat-week').text(formatNumber(data.this_week));
            $('#stat-month').text(formatNumber(data.this_month));
        }).fail(function() {
            console.error('Failed to load statistics');
        });
    }

    // Load audit details
    function loadAuditDetails(auditId) {
        $.get('{{ route("audit-logs.show", ":id") }}'.replace(':id', auditId), function(data) {
            $('#detail-id').text(data.id);
            $('#detail-event').html('<span class="badge badge-primary">' + data.event + '</span>');
            $('#detail-model').text(data.model);
            $('#detail-model-id').text(data.model_id);
            $('#detail-user').text(data.user);
            $('#detail-ip').text(data.ip_address);
            $('#detail-datetime').text(data.created_at);
            $('#detail-url').text(data.url || 'N/A');
            $('#detail-user-agent').text(data.user_agent || 'N/A');

            // Format old and new values as JSON
            $('#detail-old-values').text(
                Object.keys(data.old_values).length > 0
                    ? JSON.stringify(data.old_values, null, 2)
                    : 'No old values'
            );

            $('#detail-new-values').text(
                Object.keys(data.new_values).length > 0
                    ? JSON.stringify(data.new_values, null, 2)
                    : 'No new values'
            );

            $('#audit-details-modal').modal('show');
        }).fail(function() {
            alert('Failed to load audit details');
        });
    }

    // Format number with commas
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
});
</script>
@endsection
