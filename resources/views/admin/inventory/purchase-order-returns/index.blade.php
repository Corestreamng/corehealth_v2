@extends('admin.layouts.app')
@section('title', 'PO Returns')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'PO Returns')

@section('content')
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<style>
    .status-badge { font-size: 0.75rem; padding: 0.35em 0.65em; border-radius: 0.25rem; }
    .status-pending { background-color: #ffc107; color: #212529; }
    .status-approved { background-color: #28a745; color: white; }
    .status-rejected { background-color: #dc3545; color: white; }
</style>

<div id="content-wrapper">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-0">Purchase Order Returns</h3>
                <p class="text-muted mb-0">Manage returns to suppliers from received POs</p>
            </div>
            <div>
                <a href="{{ route('inventory.store-workbench.tally-card') }}" class="btn btn-secondary btn-sm">
                    <i class="mdi mdi-arrow-left"></i> Back to Tally Card
                </a>
            </div>
        </div>

        <div class="card shadow-sm border-0" style="border-radius:12px;">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="po-returns-table" class="table table-sm table-hover w-100">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>PO #</th>
                                <th>Supplier</th>
                                <th>Product</th>
                                <th>Batch</th>
                                <th>Qty</th>
                                <th>Status</th>
                                <th>Returned By</th>
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
    $('#po-returns-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('inventory.po-returns.datatables') }}",
        columns: [
            { data: "created_at", name: "created_at" },
            { data: "purchase_order", name: "purchase_order.po_number" },
            { data: "supplier", name: "purchase_order.supplier.company_name" },
            { data: "product", name: "product.product_name" },
            { data: "batch", name: "batch.batch_number" },
            { data: "quantity", name: "quantity" },
            { data: "status", name: "status" },
            { data: "returned_by", name: "recorder.name" },
            { data: "actions", name: "actions", orderable: false, searchable: false }
        ],
        order: [[0, 'desc']]
    });
});

function approvePOReturn(id) {
    if (confirm('Approve this PO return record? This will deduct the items from store stock.')) {
        $.post(`/inventory/purchase-order-returns/${id}/approve`, { _token: '{{ csrf_token() }}' })
            .done(function(r) {
                toastr.success(r.message || 'PO Return approved');
                $('#po-returns-table').DataTable().ajax.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to approve');
            });
    }
}

function rejectPOReturn(id) {
    var reason = prompt('Reason for rejection:');
    if (reason) {
        $.post(`/inventory/purchase-order-returns/${id}/reject`, { _token: '{{ csrf_token() }}', reason: reason })
            .done(function(r) {
                toastr.success(r.message || 'PO Return rejected');
                $('#po-returns-table').DataTable().ajax.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to reject');
            });
    }
}
</script>
@endsection
