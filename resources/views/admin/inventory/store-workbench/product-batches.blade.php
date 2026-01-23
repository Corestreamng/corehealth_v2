@extends('admin.layouts.app')
@section('title', 'Product Batches')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Product Batches')

@section('content')
<style>
    .batch-card {
        background: #fff;
        border-radius: 8px;
        padding: 1.25rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 1rem;
        border-left: 4px solid #007bff;
    }
    .batch-card.expired {
        border-left-color: #dc3545;
        background: #fff5f5;
    }
    .batch-card.expiring-soon {
        border-left-color: #ffc107;
        background: #fffdf5;
    }
    .batch-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }
    .batch-number {
        font-weight: 600;
        font-size: 1.1rem;
    }
    .batch-status {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
    }
    .batch-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
    }
    .detail-item label {
        display: block;
        font-size: 0.75rem;
        color: #6c757d;
        margin-bottom: 0.25rem;
    }
    .detail-item .value {
        font-weight: 600;
    }
    .product-header {
        background: linear-gradient(135deg, #007bff 0%, #5a32a3 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }
    .stat-card {
        background: #fff;
        border-radius: 8px;
        padding: 1rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
</style>
<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Product Header -->
        <div class="product-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">{{ $product->product_name }}</h4>
                    <p class="mb-0 opacity-75">
                        <i class="mdi mdi-barcode mr-1"></i>{{ $product->product_code }}
                        @if($selectedStore)
                            - {{ $selectedStore->store_name }}
                        @else
                            - All Stores
                        @endif
                    </p>
                </div>
                <div>
                    @hasanyrole('SUPERADMIN|ADMIN|STORE')
                    <a href="{{ route('inventory.store-workbench.index') }}{{ $selectedStore ? '?store_id=' . $selectedStore->id : '' }}" class="btn btn-light btn-sm mr-2">
                        <i class="mdi mdi-arrow-left"></i> Workbench
                    </a>
                    @endhasanyrole
                    <a href="{{ route('inventory.store-workbench.stock-overview') }}{{ $selectedStore ? '?store_id=' . $selectedStore->id . '&product_id=' . $product->id : '?product_id=' . $product->id }}" class="btn btn-light btn-sm">
                        <i class="mdi mdi-view-list-outline"></i> Stock Overview
                    </a>
                </div>
            </div>
        </div>

        <!-- Store Filter -->
        <div class="card-modern mb-3">
            <div class="card-body py-2">
                <div class="row align-items-center">
                    <div class="col-md-3">
                        <select id="store-filter" class="form-control form-control-sm">
                            <option value="">All Stores</option>
                            @foreach($stores as $store)
                            <option value="{{ $store->id }}" {{ request('store_id') == $store->id ? 'selected' : '' }}>
                                {{ $store->store_name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <a href="{{ route('inventory.store-workbench.manual-batch-form', ['product_id' => $product->id]) }}" class="btn btn-success btn-sm">
                            <i class="mdi mdi-plus"></i> Add Batch
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h3 class="mb-1">{{ $totalBatches }}</h3>
                    <small class="text-muted">Total Batches</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h3 class="mb-1 text-success">{{ $totalStock }}</h3>
                    <small class="text-muted">Total Stock</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    @php
                        $expiredCount = $batches->filter(fn($b) => isset($b->expiry_status['status']) && $b->expiry_status['status'] === 'expired')->count();
                    @endphp
                    <h3 class="mb-1 text-danger">{{ $expiredCount }}</h3>
                    <small class="text-muted">Expired Batches</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    @php
                        $avgCost = $batches->avg('cost_price') ?? 0;
                    @endphp
                    <h3 class="mb-1">₦{{ number_format($avgCost, 2) }}</h3>
                    <small class="text-muted">Avg. Cost Price</small>
                </div>
            </div>
        </div>

        <!-- Batch List -->
        <h5 class="mb-3">Batches (FIFO Order)</h5>

        @forelse($batches as $batch)
        @php
            $expiryStatus = $batch->expiry_status['status'] ?? 'ok';
            $cardClass = match($expiryStatus) {
                'expired' => 'expired',
                'critical', 'warning' => 'expiring-soon',
                default => ''
            };
        @endphp
        <div class="batch-card {{ $cardClass }}">
            <div class="batch-header">
                <div>
                    <span class="batch-number">{{ $batch->batch_number }}</span>
                    @if($batch->batch_name)
                        <small class="text-muted ml-2">({{ $batch->batch_name }})</small>
                    @endif
                    @if(!$selectedStore && $batch->store)
                        <span class="badge badge-info ml-2">{{ $batch->store->store_name }}</span>
                    @endif
                </div>
                <div>
                    @if($expiryStatus === 'expired')
                        <span class="batch-status bg-danger text-white">Expired</span>
                    @elseif(in_array($expiryStatus, ['critical', 'warning']))
                        <span class="batch-status bg-warning text-dark">{{ $batch->expiry_status['label'] ?? 'Expiring Soon' }}</span>
                    @else
                        <span class="batch-status bg-success text-white">Active</span>
                    @endif
                </div>
            </div>

            <div class="batch-details">
                <div class="detail-item">
                    <label>Current Qty</label>
                    <div class="value text-primary">{{ $batch->current_qty }}</div>
                </div>
                <div class="detail-item">
                    <label>Initial Qty</label>
                    <div class="value">{{ $batch->initial_qty }}</div>
                </div>
                <div class="detail-item">
                    <label>Cost Price</label>
                    <div class="value">₦{{ number_format($batch->cost_price, 2) }}</div>
                </div>
                <div class="detail-item">
                    <label>Expiry Date</label>
                    <div class="value {{ $expiryStatus === 'expired' ? 'text-danger' : '' }}">
                        {{ $batch->expiry_date ? $batch->expiry_date->format('M d, Y') : 'N/A' }}
                    </div>
                </div>
                <div class="detail-item">
                    <label>Received Date</label>
                    <div class="value">{{ $batch->received_date?->format('M d, Y') ?? $batch->created_at->format('M d, Y') }}</div>
                </div>
                <div class="detail-item">
                    <label>Source</label>
                    <div class="value">
                        @if($batch->purchaseOrderItem)
                            PO #{{ $batch->purchaseOrderItem->purchaseOrder->po_number ?? 'N/A' }}
                        @else
                            {{ ucfirst($batch->source ?? 'Manual') }}
                        @endif
                    </div>
                </div>
                <div class="detail-item">
                    <label>Supplier</label>
                    <div class="value">
                        @if($batch->supplier)
                            <a href="{{ route('suppliers.show', $batch->supplier_id) }}" class="text-primary">
                                {{ $batch->supplier->company_name }}
                            </a>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-3 pt-3 border-top">
                <a href="{{ route('inventory.store-workbench.adjustment-form', $batch->id) }}" class="btn btn-sm btn-outline-primary">
                    <i class="mdi mdi-pencil"></i> Adjust Stock
                </a>
                @if($expiryStatus === 'expired' || $batch->current_qty == 0)
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="writeOffBatch({{ $batch->id }})">
                    <i class="mdi mdi-delete"></i> Write Off
                </button>
                @endif
            </div>
        </div>
        @empty
        <div class="alert alert-info">
            <i class="mdi mdi-information"></i> No batches found for this product{{ $selectedStore ? ' in ' . $selectedStore->store_name : '' }}.
            <a href="{{ route('inventory.store-workbench.manual-batch-form', ['product_id' => $product->id]) }}" class="alert-link">Add a batch now</a>
        </div>
        @endforelse
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function() {
    $('#store-filter').on('change', function() {
        var storeId = $(this).val();
        var url = new URL(window.location.href);
        if (storeId) {
            url.searchParams.set('store_id', storeId);
        } else {
            url.searchParams.delete('store_id');
        }
        window.location.href = url.toString();
    });
});

function writeOffBatch(batchId) {
    var reason = prompt('Enter write-off reason:');
    if (reason) {
        $.post(`/inventory/store-workbench/batch/${batchId}/write-off-expired`, {
            _token: '{{ csrf_token() }}',
            notes: reason
        })
        .done(function(response) {
            toastr.success(response.message || 'Batch written off successfully');
            location.reload();
        })
        .fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to write off batch');
        });
    }
}
</script>
@endsection
