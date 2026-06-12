@extends('admin.layouts.app')
@section('title', 'Requisition Returns')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Requisition Returns')

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
                <h3 class="mb-0">Requisition Returns</h3>
                <p class="text-muted mb-0">Manage returns from fulfilled requisitions</p>
            </div>
            <div>
                @if(request('requisition_id'))
                <a href="{{ route('inventory.requisitions.show', request('requisition_id')) }}" class="btn btn-secondary btn-sm">
                    <i class="mdi mdi-arrow-left"></i> Back to Requisition
                </a>
                @else
                <a href="{{ route('inventory.store-workbench.tally-card') }}" class="btn btn-secondary btn-sm">
                    <i class="mdi mdi-arrow-left"></i> Back to Tally Card
                </a>
                @endif
            </div>
        </div>

        <div class="card shadow-sm border-0" style="border-radius:12px;">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="returns-table" class="table table-sm table-hover w-100">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Requisition #</th>
                                <th>Product</th>
                                <th>Batch</th>
                                <th>Qty</th>
                                <th>Store</th>
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
    $('#returns-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('inventory.requisition-returns.datatables') }}",
        columns: [
            { data: "created_at", name: "created_at" },
            { data: "requisition", name: "requisition.requisition_number" },
            { data: "product", name: "product.product_name" },
            { data: "batch", name: "batch.batch_number" },
            { data: "qty_returned", name: "qty_returned" },
            { data: "store", name: "store.store_name" },
            { data: "status", name: "status" },
            { data: "returned_by", name: "recorder.name" },
            { data: "actions", name: "actions", orderable: false, searchable: false }
        ],
        order: [[0, 'desc']]
    });
});

function approveReturn(id) {
    if (confirm('Approve this return record? This will add the items back to store stock.')) {
        $.post(`/inventory/requisition-returns/${id}/approve`, { _token: '{{ csrf_token() }}' })
            .done(function(r) {
                toastr.success(r.message || 'Return approved');
                $('#returns-table').DataTable().ajax.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to approve');
            });
    }
}

function rejectReturn(id) {
    var reason = prompt('Reason for rejection:');
    if (reason) {
        $.post(`/inventory/requisition-returns/${id}/reject`, { _token: '{{ csrf_token() }}', reason: reason })
            .done(function(r) {
                toastr.success(r.message || 'Return rejected');
                $('#returns-table').DataTable().ajax.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to reject');
            });
    }
}
</script>
@endsection
