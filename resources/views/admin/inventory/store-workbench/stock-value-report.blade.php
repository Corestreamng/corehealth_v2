@extends('admin.layouts.app')
@section('title', 'Stock Value Report')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Stock Value Report')

@section('content')
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<style>
    .report-header {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }
    .summary-card {
        background: #fff;
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        text-align: center;
    }
    .summary-card .value {
        font-size: 1.75rem;
        font-weight: 700;
    }
    .summary-card .label {
        color: #6c757d;
        font-size: 0.875rem;
    }
</style>
<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Header -->
        <div class="report-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">Stock Value Report</h4>
                    <p class="mb-0 opacity-75">{{ $store->store_name }}</p>
                </div>
                <div>
                    <select id="store-selector" class="form-control form-control-sm bg-light" style="width: auto;">
                        @foreach($stores as $s)
                            <option value="{{ $s->id }}" {{ $s->id == $store->id ? 'selected' : '' }}>{{ $s->store_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="value text-primary">{{ number_format($report['total_products'] ?? 0) }}</div>
                    <div class="label">Products</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="value text-info">{{ number_format($report['total_batches'] ?? 0) }}</div>
                    <div class="label">Active Batches</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="value text-success">{{ number_format($report['total_qty'] ?? 0) }}</div>
                    <div class="label">Total Units</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="value text-warning">₦{{ number_format($report['total_value'] ?? 0, 2) }}</div>
                    <div class="label">Total Value</div>
                </div>
            </div>
        </div>

        <!-- Detailed Report -->
        <div class="card-modern">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Stock Valuation by Product</h5>
                <button type="button" class="btn btn-outline-success btn-sm" onclick="exportReport()">
                    <i class="mdi mdi-download"></i> Export
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="value-table" class="table table-sm table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th class="text-center">Batches</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Avg. Cost</th>
                                <th class="text-right">Total Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($report['by_product'] ?? [] as $item)
                            <tr>
                                <td>{{ $item['product_name'] ?? 'Unknown' }}</td>
                                <td>{{ $item['category'] ?? '-' }}</td>
                                <td class="text-center">{{ $item['batch_count'] ?? 0 }}</td>
                                <td class="text-right">{{ number_format($item['total_qty'] ?? 0) }}</td>
                                <td class="text-right">₦{{ number_format($item['avg_cost'] ?? 0, 2) }}</td>
                                <td class="text-right font-weight-bold">₦{{ number_format($item['total_value'] ?? 0, 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">No stock data available</td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="bg-light">
                                <th colspan="3">TOTAL</th>
                                <th class="text-right">{{ number_format($report['total_qty'] ?? 0) }}</th>
                                <th class="text-right">-</th>
                                <th class="text-right">₦{{ number_format($report['total_value'] ?? 0, 2) }}</th>
                            </tr>
                        </tfoot>
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
    $('#value-table').DataTable({
        dom: 'Bfrtip',
        pageLength: 25,
        buttons: ['copy', 'excel', 'pdf', 'print'],
        order: [[5, 'desc']] // Sort by total value descending
    });

    $('#store-selector').on('change', function() {
        window.location.href = '{{ route('inventory.store-workbench.stock-value-report') }}?store_id=' + $(this).val();
    });
});

function exportReport() {
    window.location.href = '{{ route('inventory.store-workbench.stock-value-report') }}?store_id={{ $store->id }}&export=1';
}
</script>
@endsection
