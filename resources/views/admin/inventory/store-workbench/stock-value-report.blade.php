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
    .store-breakdown-card {
        background: #fff;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1rem;
        border-left: 4px solid #28a745;
    }
</style>
<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Header -->
        <div class="report-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">Stock Value Report</h4>
                    <p class="mb-0 opacity-75">{{ $store ? $store->store_name : 'All Stores' }}</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <select id="store-selector" class="form-control form-control-sm bg-light mr-2" style="width: auto;">
                        <option value="" {{ !$store ? 'selected' : '' }}>-- All Stores --</option>
                        @foreach($stores as $s)
                            <option value="{{ $s->id }}" {{ $store && $s->id == $store->id ? 'selected' : '' }}>{{ $s->store_name }}</option>
                        @endforeach
                    </select>
                    @hasanyrole('SUPERADMIN|ADMIN|STORE')
                    <a href="{{ route('inventory.store-workbench.index') }}{{ $store ? '?store_id=' . $store->id : '' }}" class="btn btn-light btn-sm">
                        <i class="mdi mdi-arrow-left"></i> Back to Workbench
                    </a>
                    @endhasanyrole
                </div>
            </div>
        </div>

        @if(!$store && !empty($report['stores']))
        <!-- Store Breakdown (only shown when viewing all stores) -->
        <div class="row mb-4">
            <div class="col-12">
                <h5 class="mb-3"><i class="mdi mdi-store"></i> Value by Store</h5>
            </div>
            @foreach($report['stores'] as $storeData)
            <div class="col-md-4 mb-3">
                <div class="store-breakdown-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">{{ $storeData['store']->store_name ?? 'Unknown Store' }}</h6>
                            <small class="text-muted">{{ $storeData['product_count'] ?? 0 }} products</small>
                        </div>
                        <div class="text-right">
                            <div class="h5 text-success mb-0">₦{{ number_format($storeData['total_value'] ?? 0, 2) }}</div>
                            <small class="text-muted">{{ number_format($storeData['total_qty'] ?? 0) }} units</small>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="summary-card">
                    <div class="value text-primary">{{ number_format(count($report['products'] ?? [])) }}</div>
                    <div class="label">Products</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="summary-card">
                    <div class="value text-info">{{ number_format(collect($report['products'] ?? [])->sum('total_qty')) }}</div>
                    <div class="label">Total Units</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="summary-card">
                    <div class="value text-success">₦{{ number_format($report['total_value'] ?? 0, 2) }}</div>
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
                                <th>Code</th>
                                @if(!$store)
                                <th>Stores</th>
                                @endif
                                <th class="text-right">Qty</th>
                                <th class="text-right">Avg. Cost</th>
                                <th class="text-right">Total Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($report['products'] ?? [] as $item)
                            <tr>
                                <td>{{ $item['product']->product_name ?? 'Unknown' }}</td>
                                <td><small class="text-muted">{{ $item['product']->product_code ?? '-' }}</small></td>
                                @if(!$store)
                                <td>
                                    @foreach($item['stores'] ?? [] as $storeInfo)
                                        <span class="badge badge-light" title="{{ number_format($storeInfo['qty']) }} units">
                                            {{ $storeInfo['store']->store_name ?? 'Unknown' }}
                                        </span>
                                    @endforeach
                                </td>
                                @endif
                                <td class="text-right">{{ number_format($item['total_qty'] ?? 0) }}</td>
                                <td class="text-right">
                                    @php $avgCost = $item['total_qty'] > 0 ? $item['total_value'] / $item['total_qty'] : 0; @endphp
                                    ₦{{ number_format($avgCost, 2) }}
                                </td>
                                <td class="text-right font-weight-bold">₦{{ number_format($item['total_value'] ?? 0, 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ $store ? 5 : 6 }}" class="text-center text-muted">No stock data available</td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="bg-light">
                                <th colspan="{{ $store ? 2 : 3 }}">TOTAL</th>
                                <th class="text-right">{{ number_format(collect($report['products'] ?? [])->sum('total_qty')) }}</th>
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
        order: [[{{ $store ? 4 : 5 }}, 'desc']] // Sort by total value descending
    });

    $('#store-selector').on('change', function() {
        var storeId = $(this).val();
        var url = '{{ route('inventory.store-workbench.stock-value-report') }}';
        if (storeId) {
            url += '?store_id=' + storeId;
        }
        window.location.href = url;
    });
});

function exportReport() {
    var url = '{{ route('inventory.store-workbench.stock-value-report') }}?export=1';
    @if($store)
    url += '&store_id={{ $store->id }}';
    @endif
    window.location.href = url;
}
</script>
@endsection
