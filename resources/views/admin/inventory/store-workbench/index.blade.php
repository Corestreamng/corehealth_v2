@extends('admin.layouts.app')
@section('title', 'Store Workbench')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Store Workbench')

@push('styles')
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<style>
    :root {
        --hospital-primary: {{ appsettings('hos_color', '#007bff') }};
    }
    
    .workbench-header {
        background: linear-gradient(135deg, var(--hospital-primary) 0%, #5a32a3 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }
    
    .stat-card {
        background: #fff;
        border-radius: 8px;
        padding: 1.25rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        height: 100%;
    }
    .stat-card .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    .stat-card .stat-value {
        font-size: 1.75rem;
        font-weight: 700;
    }
    .stat-card .stat-label {
        color: #6c757d;
        font-size: 0.85rem;
    }
    
    .action-card {
        background: #fff;
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 1rem;
        transition: all 0.2s;
        cursor: pointer;
        text-decoration: none;
        display: block;
        color: inherit;
    }
    .action-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: translateY(-2px);
        text-decoration: none;
        color: inherit;
    }
    .action-card .action-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        margin-bottom: 1rem;
    }
    .action-card h5 {
        margin-bottom: 0.5rem;
    }
    .action-card p {
        color: #6c757d;
        font-size: 0.875rem;
        margin-bottom: 0;
    }
    
    .expiry-alert {
        padding: 0.75rem 1rem;
        border-radius: 8px;
        margin-bottom: 0.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .expiry-alert.critical {
        background: #f8d7da;
        border-left: 4px solid #dc3545;
    }
    .expiry-alert.warning {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
    }
    
    .low-stock-item {
        padding: 0.75rem;
        border-bottom: 1px solid #e9ecef;
    }
    .low-stock-item:last-child {
        border-bottom: none;
    }
    
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 1rem;
    }
    
    .store-selector {
        background: rgba(255,255,255,0.15);
        border: none;
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 4px;
        font-size: 1rem;
    }
    .store-selector option {
        color: #212529;
    }
</style>
@endpush

@section('content')
<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Header -->
        <div class="workbench-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-1">Store Workbench</h2>
                    <p class="mb-0 opacity-75">Manage inventory, batches, and stock movements</p>
                </div>
                <div class="col-md-4 text-right">
                    <select id="store-selector" class="store-selector">
                        @foreach($stores as $store)
                        <option value="{{ $store->id }}" {{ $selectedStore && $selectedStore->id == $store->id ? 'selected' : '' }}>
                            {{ $store->store_name }}
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value text-primary" id="total-products">{{ $stats['total_products'] ?? 0 }}</div>
                            <div class="stat-label">Total Products</div>
                        </div>
                        <div class="stat-icon bg-primary-light text-primary">
                            <i class="mdi mdi-package-variant"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value text-success" id="total-batches">{{ $stats['total_batches'] ?? 0 }}</div>
                            <div class="stat-label">Active Batches</div>
                        </div>
                        <div class="stat-icon bg-success-light text-success">
                            <i class="mdi mdi-layers"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value text-warning" id="low-stock">{{ $stats['low_stock'] ?? 0 }}</div>
                            <div class="stat-label">Low Stock Items</div>
                        </div>
                        <div class="stat-icon bg-warning-light text-warning">
                            <i class="mdi mdi-alert"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value text-danger" id="expiring-soon">{{ $stats['expiring_soon'] ?? 0 }}</div>
                            <div class="stat-label">Expiring Soon</div>
                        </div>
                        <div class="stat-icon bg-danger-light text-danger">
                            <i class="mdi mdi-clock-alert"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Main Actions -->
            <div class="col-md-8">
                <h5 class="mb-3">Quick Actions</h5>
                <div class="quick-actions mb-4">
                    <a href="{{ route('inventory.store-workbench.stock-overview') }}?store_id={{ $selectedStore->id ?? '' }}" class="action-card">
                        <div class="action-icon bg-primary-light text-primary">
                            <i class="mdi mdi-view-list"></i>
                        </div>
                        <h5>Stock Overview</h5>
                        <p>View all products and their batch details</p>
                    </a>
                    
                    <a href="{{ route('inventory.store-workbench.manual-batch-form') }}?store_id={{ $selectedStore->id ?? '' }}" class="action-card">
                        <div class="action-icon bg-success-light text-success">
                            <i class="mdi mdi-plus-box"></i>
                        </div>
                        <h5>Add Batch</h5>
                        <p>Manually add a new stock batch</p>
                    </a>
                    
                    <a href="{{ route('inventory.requisitions.create') }}?to_store_id={{ $selectedStore->id ?? '' }}" class="action-card">
                        <div class="action-icon bg-info-light text-info">
                            <i class="mdi mdi-swap-horizontal"></i>
                        </div>
                        <h5>Request Stock</h5>
                        <p>Create requisition from another store</p>
                    </a>
                    
                    <a href="{{ route('inventory.store-workbench.expiry-report') }}?store_id={{ $selectedStore->id ?? '' }}" class="action-card">
                        <div class="action-icon bg-warning-light text-warning">
                            <i class="mdi mdi-calendar-clock"></i>
                        </div>
                        <h5>Expiry Report</h5>
                        <p>View products nearing expiry</p>
                    </a>
                    
                    <a href="{{ route('inventory.store-workbench.stock-value-report') }}?store_id={{ $selectedStore->id ?? '' }}" class="action-card">
                        <div class="action-icon bg-secondary-light text-secondary">
                            <i class="mdi mdi-currency-ngn"></i>
                        </div>
                        <h5>Stock Value</h5>
                        <p>Calculate total inventory value</p>
                    </a>
                    
                    <a href="{{ route('inventory.purchase-orders.create') }}?store_id={{ $selectedStore->id ?? '' }}" class="action-card">
                        <div class="action-icon bg-purple-light text-purple">
                            <i class="mdi mdi-cart-plus"></i>
                        </div>
                        <h5>New Purchase Order</h5>
                        <p>Order stock from suppliers</p>
                    </a>
                </div>

                <!-- Recent Activity -->
                <div class="card-modern">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Stock Movements</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Product</th>
                                        <th>Type</th>
                                        <th>Qty</th>
                                        <th>Batch</th>
                                        <th>By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentTransactions ?? [] as $transaction)
                                    <tr>
                                        <td>{{ $transaction->created_at->format('M d, H:i') }}</td>
                                        <td>{{ $transaction->batch->product->product_name ?? 'N/A' }}</td>
                                        <td>
                                            <span class="badge badge-{{ $transaction->transaction_type == 'in' ? 'success' : ($transaction->transaction_type == 'out' ? 'danger' : 'warning') }}">
                                                {{ ucfirst($transaction->transaction_type) }}
                                            </span>
                                        </td>
                                        <td>{{ $transaction->quantity }}</td>
                                        <td>{{ $transaction->batch->batch_number ?? 'N/A' }}</td>
                                        <td>{{ $transaction->user->name ?? 'System' }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No recent transactions</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alerts Sidebar -->
            <div class="col-md-4">
                <!-- Expiring Soon -->
                <div class="card-modern mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="mdi mdi-clock-alert text-danger"></i> Expiring Soon</h5>
                        <a href="{{ route('inventory.store-workbench.expiry-report') }}?store_id={{ $selectedStore->id ?? '' }}" class="btn btn-sm btn-outline-danger">View All</a>
                    </div>
                    <div class="card-body p-0">
                        @forelse($expiringBatches ?? [] as $batch)
                        <div class="expiry-alert {{ $batch->days_until_expiry <= 30 ? 'critical' : 'warning' }}">
                            <div>
                                <strong>{{ $batch->product->product_name }}</strong>
                                <br>
                                <small>{{ $batch->batch_number }} - {{ $batch->current_quantity }} units</small>
                            </div>
                            <div class="text-right">
                                <span class="badge badge-{{ $batch->days_until_expiry <= 30 ? 'danger' : 'warning' }}">
                                    {{ $batch->days_until_expiry }} days
                                </span>
                            </div>
                        </div>
                        @empty
                        <div class="p-3 text-center text-muted">
                            <i class="mdi mdi-check-circle mdi-48px text-success"></i>
                            <p class="mb-0 mt-2">No items expiring soon</p>
                        </div>
                        @endforelse
                    </div>
                </div>

                <!-- Low Stock -->
                <div class="card-modern">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="mdi mdi-alert text-warning"></i> Low Stock</h5>
                        <a href="{{ route('inventory.store-workbench.stock-overview') }}?store_id={{ $selectedStore->id ?? '' }}&filter=low" class="btn btn-sm btn-outline-warning">View All</a>
                    </div>
                    <div class="card-body p-0">
                        @forelse($lowStockProducts ?? [] as $product)
                        <div class="low-stock-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>{{ $product->product_name }}</strong>
                                    <br>
                                    <small class="text-muted">{{ $product->product_code }}</small>
                                </div>
                                <div class="text-right">
                                    <span class="badge badge-warning">{{ $product->available_qty }} left</span>
                                    @if($product->reorder_level)
                                    <br><small class="text-muted">Reorder at {{ $product->reorder_level }}</small>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @empty
                        <div class="p-3 text-center text-muted">
                            <i class="mdi mdi-check-circle mdi-48px text-success"></i>
                            <p class="mb-0 mt-2">Stock levels are healthy</p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function() {
    // Store selector change
    $('#store-selector').on('change', function() {
        window.location.href = '{{ route("inventory.store-workbench.index") }}?store_id=' + $(this).val();
    });
});
</script>
@endsection
