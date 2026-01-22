@extends('admin.layouts.app')
@section('title', 'Supplier Performance Report')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Supplier Performance')

@push('styles')
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<style>
    .filter-card {
        background: #fff;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .performance-chart {
        height: 300px;
        margin-bottom: 1.5rem;
    }
    .top-supplier {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1rem;
    }
</style>
@endpush

@section('content')
<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-0">Supplier Performance Report</h3>
                <p class="text-muted mb-0">Analyzing supplier contributions and volumes</p>
            </div>
            <div>
                <a href="{{ route('suppliers.reports.index') }}" class="btn btn-secondary">
                    <i class="mdi mdi-arrow-left"></i> Back to Reports
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <form method="GET" action="{{ route('suppliers.reports.performance') }}" class="row align-items-end">
                <div class="col-md-4">
                    <label>Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
                </div>
                <div class="col-md-4">
                    <label>End Date</label>
                    <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="mdi mdi-filter"></i> Apply Filter
                    </button>
                    <a href="{{ route('suppliers.reports.performance') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>

        @if($suppliers->isNotEmpty())
        <!-- Top Supplier -->
        @php $topSupplier = $suppliers->first(); @endphp
        <div class="top-supplier">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="mb-1"><i class="mdi mdi-trophy"></i> Top Supplier</h4>
                    <h2 class="mb-0">{{ $topSupplier['company_name'] }}</h2>
                    <p class="mb-0 opacity-75">
                        {{ $topSupplier['total_batches'] }} batches |
                        {{ number_format($topSupplier['total_items']) }} items |
                        {{ $topSupplier['products_supplied'] }} products
                    </p>
                </div>
                <div class="col-md-4 text-right">
                    <h3 class="mb-0">₦{{ number_format($topSupplier['total_value'], 2) }}</h3>
                    <small>Total Value</small>
                </div>
            </div>
        </div>
        @endif

        <!-- Performance Table -->
        <div class="card-modern">
            <div class="card-header">
                <h5 class="mb-0">All Suppliers Performance ({{ $startDate }} to {{ $endDate }})</h5>
            </div>
            <div class="card-body">
                <table id="performance-table" class="table table-sm table-bordered table-striped w-100">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Supplier</th>
                            <th>Total Batches</th>
                            <th>Total Items</th>
                            <th>Products Supplied</th>
                            <th>Avg. Cost Price</th>
                            <th>Total Value</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($suppliers as $index => $supplier)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>
                                <a href="{{ route('suppliers.show', $supplier['id']) }}">
                                    {{ $supplier['company_name'] }}
                                </a>
                            </td>
                            <td>{{ $supplier['total_batches'] }}</td>
                            <td>{{ number_format($supplier['total_items']) }}</td>
                            <td>{{ $supplier['products_supplied'] }}</td>
                            <td>₦{{ number_format($supplier['avg_cost'], 2) }}</td>
                            <td><strong>₦{{ number_format($supplier['total_value'], 2) }}</strong></td>
                            <td>
                                <a href="{{ route('suppliers.reports.batches', ['supplier_id' => $supplier['id']]) }}"
                                   class="btn btn-sm btn-info">
                                    <i class="fa fa-eye"></i> Batches
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-light font-weight-bold">
                            <td colspan="2">Totals</td>
                            <td>{{ $suppliers->sum('total_batches') }}</td>
                            <td>{{ number_format($suppliers->sum('total_items')) }}</td>
                            <td>-</td>
                            <td>-</td>
                            <td>₦{{ number_format($suppliers->sum('total_value'), 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script>
$(function() {
    $('#performance-table').DataTable({
        dom: 'Bfrtip',
        buttons: ['pageLength', 'copy', 'excel', 'pdf', 'print'],
        pageLength: 25,
        order: [[6, 'desc']] // Sort by total value
    });
});
</script>
@endsection
