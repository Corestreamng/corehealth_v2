@extends('admin.layouts.app')
@section('title', 'Store Damages')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Store Damages')

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
                <h3 class="mb-0">Store Damages</h3>
                <p class="text-muted mb-0">Review and manage reported product damages</p>
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
                    <table id="damages-table" class="table table-sm table-hover w-100">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Batch</th>
                                <th>Store</th>
                                <th>Qty</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Recorded By</th>
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
    $('#damages-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('inventory.store-damages.datatables') }}",
        columns: [
            { data: "created_at", name: "created_at" },
            { data: "product", name: "product.product_name" },
            { data: "batch", name: "batch.batch_number" },
            { data: "store", name: "store.store_name" },
            { data: "qty_damaged", name: "qty_damaged" },
            { data: "damage_type", name: "damage_type" },
            { data: "status", name: "status" },
            { data: "recorded_by", name: "recorder.name" },
            { data: "actions", name: "actions", orderable: false, searchable: false }
        ],
        order: [[0, 'desc']]
    });
});

function approveDamage(id) {
    if (confirm('Approve this damage record? This will permanently deduct the stock.')) {
        $.post(`/inventory/store-damages/${id}/approve`, { _token: '{{ csrf_token() }}' })
            .done(function(r) {
                toastr.success(r.message || 'Damage approved');
                $('#damages-table').DataTable().ajax.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to approve');
            });
    }
}

function rejectDamage(id) {
    var reason = prompt('Reason for rejection:');
    if (reason) {
        $.post(`/inventory/store-damages/${id}/reject`, { _token: '{{ csrf_token() }}', reason: reason })
            .done(function(r) {
                toastr.success(r.message || 'Damage rejected');
                $('#damages-table').DataTable().ajax.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to reject');
            });
    }
}
</script>
@endsection
