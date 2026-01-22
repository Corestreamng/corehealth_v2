@extends('admin.layouts.app')
@section('title', 'Supplier Batches Report')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Batches Report')

@push('styles')
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/select2.min.css') }}">
<style>
    .filter-card {
        background: #fff;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .summary-row {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .summary-card {
        flex: 1;
        background: #fff;
        padding: 1rem;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        text-align: center;
    }
    .summary-card h4 {
        margin: 0;
        font-weight: 600;
    }
    .summary-card small {
        color: #6c757d;
    }
</style>
@endpush

@section('content')
<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-0">Supplier Batches Report</h3>
                <p class="text-muted mb-0">
                    @if($supplierId && $supplier = $suppliers->firstWhere('id', $supplierId))
                        Showing batches from: <strong>{{ $supplier->company_name }}</strong>
                    @else
                        Showing batches from all suppliers
                    @endif
                </p>
            </div>
            <div>
                <a href="{{ route('suppliers.reports.index') }}" class="btn btn-secondary">
                    <i class="mdi mdi-arrow-left"></i> Back to Reports
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <form method="GET" action="{{ route('suppliers.reports.batches') }}" class="row align-items-end">
                <div class="col-md-3">
                    <label>Supplier</label>
                    <select name="supplier_id" id="supplier_id" class="form-control select2">
                        <option value="">All Suppliers</option>
                        @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ $supplierId == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->company_name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label>Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
                </div>
                <div class="col-md-3">
                    <label>End Date</label>
                    <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="mdi mdi-filter"></i> Filter
                    </button>
                    <a href="{{ route('suppliers.reports.batches') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- Summary -->
        <div class="summary-row">
            <div class="summary-card">
                <h4 class="text-primary">{{ $summary['total_batches'] }}</h4>
                <small>Total Batches</small>
            </div>
            <div class="summary-card">
                <h4 class="text-success">{{ number_format($summary['total_items']) }}</h4>
                <small>Total Items</small>
            </div>
            <div class="summary-card">
                <h4 class="text-info">₦{{ number_format($summary['total_value'], 2) }}</h4>
                <small>Total Value</small>
            </div>
            <div class="summary-card">
                <h4 class="text-warning">{{ $summary['suppliers_count'] }}</h4>
                <small>Suppliers</small>
            </div>
        </div>

        <!-- Batches Table -->
        <div class="card-modern">
            <div class="card-header">
                <h5 class="mb-0">Batches ({{ $startDate }} to {{ $endDate }})</h5>
            </div>
            <div class="card-body">
                <table id="batches-table" class="table table-sm table-bordered table-striped w-100">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Batch #</th>
                            <th>Product</th>
                            <th>Supplier</th>
                            <th>Store</th>
                            <th>Qty</th>
                            <th>Cost Price</th>
                            <th>Total Value</th>
                            <th>Expiry</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($batches as $index => $batch)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $batch->created_at->format('M d, Y') }}</td>
                            <td>
                                <a href="{{ route('inventory.store-workbench.product-batches', $batch->product_id) }}">
                                    {{ $batch->batch_number }}
                                </a>
                            </td>
                            <td>{{ $batch->product->product_name ?? '-' }}</td>
                            <td>
                                <a href="{{ route('suppliers.show', $batch->supplier_id) }}">
                                    {{ $batch->supplier->company_name ?? '-' }}
                                </a>
                            </td>
                            <td>{{ $batch->store->store_name ?? '-' }}</td>
                            <td>{{ $batch->initial_qty }}</td>
                            <td>₦{{ number_format($batch->cost_price, 2) }}</td>
                            <td><strong>₦{{ number_format($batch->initial_qty * $batch->cost_price, 2) }}</strong></td>
                            <td>
                                @if($batch->expiry_date)
                                    @if($batch->expiry_date->isPast())
                                        <span class="badge badge-danger">{{ $batch->expiry_date->format('M d, Y') }}</span>
                                    @elseif($batch->expiry_date->diffInDays(now()) <= 30)
                                        <span class="badge badge-warning">{{ $batch->expiry_date->format('M d, Y') }}</span>
                                    @else
                                        {{ $batch->expiry_date->format('M d, Y') }}
                                    @endif
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('assets/js/select2.min.js') }}"></script>
<script>
$(function() {
    $('.select2').select2({
        placeholder: 'Select supplier...',
        allowClear: true
    });

    $('#batches-table').DataTable({
        dom: 'Bfrtip',
        buttons: ['pageLength', 'copy', 'excel', 'pdf', 'print'],
        pageLength: 25,
        order: [[1, 'desc']] // Sort by date
    });
});
</script>
@endsection
