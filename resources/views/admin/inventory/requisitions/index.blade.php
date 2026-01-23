@extends('admin.layouts.app')
@section('title', 'Store Requisitions')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Store Requisitions')

@section('content')
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<style>
    .status-badge {
        font-size: 0.75rem;
        padding: 0.35em 0.65em;
        border-radius: 0.25rem;
    }
    .status-pending { background-color: #ffc107; color: #212529; }
    .status-approved { background-color: #17a2b8; color: white; }
    .status-partial { background-color: #6f42c1; color: white; }
    .status-fulfilled { background-color: #28a745; color: white; }
    .status-rejected { background-color: #dc3545; color: white; }
    .status-cancelled { background-color: #6c757d; color: white; }

    .summary-card {
        border-radius: 8px;
        padding: 1.25rem;
        margin-bottom: 1rem;
    }
    .summary-card h5 {
        font-size: 0.85rem;
        color: #6c757d;
        margin-bottom: 0.5rem;
    }
    .summary-card .value {
        font-size: 1.5rem;
        font-weight: 600;
    }

    .queue-tabs .nav-link {
        border-radius: 0;
        border-bottom: 3px solid transparent;
        color: #6c757d;
    }
    .queue-tabs .nav-link.active {
        border-bottom-color: #007bff;
        color: #007bff;
        background: transparent;
    }
    .queue-tabs .nav-link:hover {
        border-bottom-color: #dee2e6;
    }
</style>
<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Header with Back Link -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-0">Store Requisitions</h3>
                <p class="text-muted mb-0">Manage inter-store stock transfer requests</p>
            </div>
            <div>
                @can('requisitions.create')
                <a href="{{ route('inventory.requisitions.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="mdi mdi-plus"></i> New Requisition
                </a>
                @endcan
                @hasanyrole('SUPERADMIN|ADMIN|STORE')
                <a href="{{ route('inventory.store-workbench.index') }}{{ request('store_id') ? '?store_id=' . request('store_id') : '' }}" class="btn btn-secondary btn-sm">
                    <i class="mdi mdi-arrow-left"></i> Back to Workbench
                </a>
                @endhasanyrole
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="summary-card bg-white shadow-sm">
                    <h5>Total Requisitions</h5>
                    <div class="value text-primary">{{ $stats['total'] ?? 0 }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card bg-white shadow-sm">
                    <h5>Pending Approval</h5>
                    <div class="value text-warning">{{ $stats['pending_approval'] ?? 0 }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card bg-white shadow-sm">
                    <h5>Awaiting Fulfillment</h5>
                    <div class="value text-info">{{ $stats['awaiting_fulfillment'] ?? 0 }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card bg-white shadow-sm">
                    <h5>Fulfilled This Month</h5>
                    <div class="value text-success">{{ $stats['fulfilled_this_month'] ?? 0 }}</div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="card-modern">
            <div class="card-body">
                <!-- Queue Tabs -->
                <ul class="nav queue-tabs mb-3">
                    <li class="nav-item">
                        <a class="nav-link {{ request('queue') == '' ? 'active' : '' }}" href="{{ route('inventory.requisitions.index') }}">
                            All Requisitions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request('queue') == 'pending-approval' ? 'active' : '' }}" href="{{ route('inventory.requisitions.index', ['queue' => 'pending-approval']) }}">
                            Pending Approval <span class="badge badge-warning">{{ $stats['pending_approval'] ?? 0 }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request('queue') == 'pending-fulfillment' ? 'active' : '' }}" href="{{ route('inventory.requisitions.index', ['queue' => 'pending-fulfillment']) }}">
                            Pending Fulfillment <span class="badge badge-info">{{ $stats['awaiting_fulfillment'] ?? 0 }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request('queue') == 'my-requisitions' ? 'active' : '' }}" href="{{ route('inventory.requisitions.index', ['queue' => 'my-requisitions']) }}">
                            My Requisitions
                        </a>
                    </li>
                </ul>

                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <select id="status-filter" class="form-control form-control-sm">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="partial">Partially Fulfilled</option>
                            <option value="fulfilled">Fulfilled</option>
                            <option value="rejected">Rejected</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select id="from-store-filter" class="form-control form-control-sm">
                            <option value="">All Source Stores</option>
                            @foreach($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select id="to-store-filter" class="form-control form-control-sm">
                            <option value="">All Destination Stores</option>
                            @foreach($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="requisition-table" class="table table-sm table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Requisition #</th>
                                <th>Date</th>
                                <th>From Store</th>
                                <th>To Store</th>
                                <th>Items</th>
                                <th>Status</th>
                                <th>Requested By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
<script>
$(function() {
    var table = $('#requisition-table').DataTable({
        dom: 'Bfrtip',
        iDisplayLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        buttons: ['pageLength', 'copy', 'excel', 'pdf', 'print'],
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('inventory.requisitions.index') }}",
            type: "GET",
            data: function(d) {
                d.status = $('#status-filter').val();
                d.from_store_id = $('#from-store-filter').val();
                d.to_store_id = $('#to-store-filter').val();
                d.queue = '{{ request('queue') }}';
            }
        },
        columns: [
            { data: "requisition_number", name: "requisition_number" },
            { data: "request_date", name: "created_at" },
            { data: "from_store", name: "fromStore.store_name" },
            { data: "to_store", name: "toStore.store_name" },
            { data: "items_count", name: "items_count", orderable: false },
            { data: "status", name: "status" },
            { data: "requested_by", name: "requester.name" },
            { data: "actions", name: "actions", orderable: false, searchable: false }
        ],
        order: [[1, 'desc']]
    });

    // Filters
    $('#status-filter, #from-store-filter, #to-store-filter').on('change', function() {
        table.ajax.reload();
    });
});

function approveRequisition(id) {
    if (confirm('Approve this requisition?')) {
        $.post(`/inventory/requisitions/${id}/approve`, { _token: '{{ csrf_token() }}' })
            .done(function(response) {
                toastr.success(response.message || 'Requisition approved');
                $('#requisition-table').DataTable().ajax.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to approve');
            });
    }
}

function rejectRequisition(id) {
    var reason = prompt('Please enter rejection reason:');
    if (reason) {
        $.post(`/inventory/requisitions/${id}/reject`, {
            _token: '{{ csrf_token() }}',
            rejection_reason: reason
        })
            .done(function(response) {
                toastr.success(response.message || 'Requisition rejected');
                $('#requisition-table').DataTable().ajax.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to reject');
            });
    }
}

function cancelRequisition(id) {
    if (confirm('Cancel this requisition?')) {
        $.post(`/inventory/requisitions/${id}/cancel`, { _token: '{{ csrf_token() }}' })
            .done(function(response) {
                toastr.success(response.message || 'Requisition cancelled');
                $('#requisition-table').DataTable().ajax.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to cancel');
            });
    }
}
</script>
@endsection
