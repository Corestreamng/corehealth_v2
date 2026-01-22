@extends('admin.layouts.app')
@section('title', 'Purchase Orders')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Purchase Orders')

@section('content')
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<style>
    .status-badge {
        font-size: 0.75rem;
        padding: 0.35em 0.65em;
        border-radius: 0.25rem;
    }
    .status-draft { background-color: #6c757d; color: white; }
    .status-submitted { background-color: #17a2b8; color: white; }
    .status-approved { background-color: #28a745; color: white; }
    .status-partial { background-color: #ffc107; color: #212529; }
    .status-received { background-color: #007bff; color: white; }
    .status-cancelled { background-color: #dc3545; color: white; }

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
</style>
<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="summary-card bg-white shadow-sm">
                    <h5>Total POs</h5>
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
                    <h5>Awaiting Receipt</h5>
                    <div class="value text-info">{{ $stats['awaiting_receipt'] ?? 0 }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card bg-white shadow-sm">
                    <h5>This Month Value</h5>
                    <div class="value text-success">₦{{ number_format($stats['monthly_value'] ?? 0, 2) }}</div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="card-modern">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Purchase Orders</h3>
                    <div>
                        @can('purchase-orders.create')
                        <a href="{{ route('inventory.purchase-orders.create') }}" class="btn btn-primary btn-sm">
                            <i class="mdi mdi-plus"></i> New Purchase Order
                        </a>
                        @endcan
                    </div>
                </div>
            </div>

            <div class="card-body">
                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <select id="status-filter" class="form-control form-control-sm">
                            <option value="">All Statuses</option>
                            <option value="draft">Draft</option>
                            <option value="submitted">Submitted</option>
                            <option value="approved">Approved</option>
                            <option value="partial_received">Partially Received</option>
                            <option value="received">Received</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="store-filter" class="form-control form-control-sm">
                            <option value="">All Stores</option>
                            @foreach($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="date" id="date-from" class="form-control form-control-sm" placeholder="From Date">
                    </div>
                    <div class="col-md-3">
                        <input type="date" id="date-to" class="form-control form-control-sm" placeholder="To Date">
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="po-table" class="table table-sm table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>PO Number</th>
                                <th>Date</th>
                                <th>Supplier</th>
                                <th>Store</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
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
    var table = $('#po-table').DataTable({
        dom: 'Bfrtip',
        iDisplayLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        buttons: ['pageLength', 'copy', 'excel', 'pdf', 'print'],
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('inventory.purchase-orders.index') }}",
            type: "GET",
            data: function(d) {
                d.status = $('#status-filter').val();
                d.store_id = $('#store-filter').val();
                d.date_from = $('#date-from').val();
                d.date_to = $('#date-to').val();
            }
        },
        columns: [
            { data: "po_number", name: "po_number" },
            { data: "order_date", name: "order_date" },
            { data: "supplier_name", name: "supplier_name" },
            { data: "store", name: "store.store_name" },
            { data: "items_count", name: "items_count", orderable: false },
            { data: "total_amount", name: "total_amount" },
            { data: "status", name: "status" },
            { data: "actions", name: "actions", orderable: false, searchable: false }
        ],
        order: [[1, 'desc']]
    });

    // Filters
    $('#status-filter, #store-filter, #date-from, #date-to').on('change', function() {
        table.ajax.reload();
    });
});

function deletePO(id) {
    if (confirm('Are you sure you want to delete this Purchase Order?')) {
        $.ajax({
            url: `/inventory/purchase-orders/${id}`,
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                toastr.success(response.message || 'Purchase Order deleted');
                $('#po-table').DataTable().ajax.reload();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to delete');
            }
        });
    }
}

function submitPO(id) {
    if (confirm('Submit this Purchase Order for approval?')) {
        $.post(`/inventory/purchase-orders/${id}/submit`, { _token: '{{ csrf_token() }}' })
            .done(function(response) {
                toastr.success(response.message || 'Purchase Order submitted');
                $('#po-table').DataTable().ajax.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to submit');
            });
    }
}

function approvePO(id) {
    if (confirm('Approve this Purchase Order?')) {
        $.post(`/inventory/purchase-orders/${id}/approve`, { _token: '{{ csrf_token() }}' })
            .done(function(response) {
                toastr.success(response.message || 'Purchase Order approved');
                $('#po-table').DataTable().ajax.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to approve');
            });
    }
}

function cancelPO(id) {
    var reason = prompt('Please enter cancellation reason:');
    if (reason) {
        $.post(`/inventory/purchase-orders/${id}/cancel`, {
            _token: '{{ csrf_token() }}',
            cancellation_reason: reason
        })
            .done(function(response) {
                toastr.success(response.message || 'Purchase Order cancelled');
                $('#po-table').DataTable().ajax.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to cancel');
            });
    }
}
</script>
@endsection
