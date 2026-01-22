@extends('admin.layouts.app')
@section('title', 'Store Workbench')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Store Workbench')

@php
    $hosColor = appsettings('hos_color') ?? '#0066cc';
@endphp

@section('content')
<style>
    /* ===== Store Workbench Page Styles ===== */
    .workbench-page { font-family: 'Inter', -apple-system, sans-serif; }

    /* Header Card */
    .workbench-header-card {
        background: linear-gradient(135deg, {{ $hosColor }} 0%, #5a9fd4 100%);
        border-radius: 12px;
        padding: 28px 32px;
        color: white;
        margin-bottom: 28px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        position: relative;
        overflow: hidden;
    }
    .workbench-header-card::before {
        content: '';
        position: absolute;
        right: -50px;
        top: -50px;
        width: 200px;
        height: 200px;
        background: rgba(255,255,255,0.08);
        border-radius: 50%;
    }
    .workbench-header-card::after {
        content: '';
        position: absolute;
        right: 60px;
        bottom: -80px;
        width: 160px;
        height: 160px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
    }
    .workbench-header-card h1 {
        font-size: 1.85rem;
        font-weight: 700;
        margin: 0 0 6px 0;
        position: relative;
        z-index: 1;
    }
    .workbench-header-card .header-subtitle {
        opacity: 0.9;
        font-size: 0.95rem;
        position: relative;
        z-index: 1;
    }
    .store-selector-wrapper {
        position: relative;
        z-index: 1;
    }
    .store-selector {
        background: rgba(255,255,255,0.2);
        border: 1px solid rgba(255,255,255,0.3);
        color: white;
        padding: 10px 16px;
        border-radius: 8px;
        font-size: 0.95rem;
        font-weight: 500;
        min-width: 200px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .store-selector:hover, .store-selector:focus {
        background: rgba(255,255,255,0.3);
        outline: none;
    }
    .store-selector option { color: #212529; background: white; }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 28px;
    }
    @media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 576px) { .stats-grid { grid-template-columns: 1fr; } }

    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 20px 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        display: flex;
        align-items: center;
        gap: 16px;
        transition: all 0.2s;
        border: 1px solid #f0f0f0;
    }
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.1);
    }
    .stat-icon {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    .stat-icon.primary { background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); color: #1976d2; }
    .stat-icon.success { background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); color: #388e3c; }
    .stat-icon.warning { background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%); color: #f57c00; }
    .stat-icon.danger { background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%); color: #d32f2f; }
    .stat-content { flex: 1; }
    .stat-value { font-size: 1.75rem; font-weight: 700; line-height: 1.2; }
    .stat-value.primary { color: #1976d2; }
    .stat-value.success { color: #388e3c; }
    .stat-value.warning { color: #f57c00; }
    .stat-value.danger { color: #d32f2f; }
    .stat-label { color: #6c757d; font-size: 0.85rem; font-weight: 500; margin-top: 2px; }

    /* Section Card */
    .section-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        overflow: hidden;
        border: 1px solid #f0f0f0;
    }
    .section-card-header {
        background: #f8f9fa;
        padding: 18px 24px;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .section-card-header h5 {
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
        color: #333;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .section-card-header h5 i { color: {{ $hosColor }}; font-size: 1.2rem; }
    .section-card-body { padding: 24px; }
    .section-card-body.p-0 { padding: 0; }

    /* Quick Actions Grid */
    .actions-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
    }
    @media (max-width: 992px) { .actions-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 576px) { .actions-grid { grid-template-columns: 1fr; } }

    .action-card {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 12px;
        padding: 20px;
        text-decoration: none;
        color: inherit;
        display: flex;
        align-items: flex-start;
        gap: 16px;
        transition: all 0.25s ease;
        position: relative;
        overflow: hidden;
    }
    .action-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: {{ $hosColor }};
        transform: scaleY(0);
        transition: transform 0.25s ease;
    }
    .action-card:hover {
        text-decoration: none;
        color: inherit;
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        border-color: {{ $hosColor }}20;
    }
    .action-card:hover::before { transform: scaleY(1); }

    .action-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
    }
    .action-icon.primary { background: #e3f2fd; color: #1976d2; }
    .action-icon.success { background: #e8f5e9; color: #388e3c; }
    .action-icon.info { background: #e0f7fa; color: #0097a7; }
    .action-icon.warning { background: #fff8e1; color: #f57c00; }
    .action-icon.secondary { background: #f5f5f5; color: #616161; }
    .action-icon.purple { background: #f3e5f5; color: #7b1fa2; }

    .action-content h6 {
        margin: 0 0 4px 0;
        font-weight: 600;
        font-size: 0.95rem;
        color: #333;
    }
    .action-content p {
        margin: 0;
        font-size: 0.8rem;
        color: #6c757d;
        line-height: 1.4;
    }

    /* Alert Cards */
    .alert-item {
        padding: 14px 20px;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background 0.2s;
    }
    .alert-item:hover { background: #fafafa; }
    .alert-item:last-child { border-bottom: none; }
    .alert-item.critical {
        border-left: 4px solid #dc3545;
        background: #fff5f5;
    }
    .alert-item.warning {
        border-left: 4px solid #ffc107;
        background: #fffdf5;
    }
    .alert-item-info h6 { margin: 0 0 3px 0; font-weight: 600; font-size: 0.9rem; color: #333; }
    .alert-item-info small { color: #6c757d; font-size: 0.8rem; }
    .alert-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .alert-badge.danger { background: #f8d7da; color: #721c24; }
    .alert-badge.warning { background: #fff3cd; color: #856404; }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #6c757d;
    }
    .empty-state .empty-icon {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: #e8f5e9;
        color: #4caf50;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        margin: 0 auto 16px;
    }
    .empty-state p { margin: 0; font-weight: 500; }

    /* Activity Table */
    .activity-table { margin: 0; }
    .activity-table thead th {
        background: #f8f9fa;
        border-top: none;
        border-bottom: 2px solid #e9ecef;
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6c757d;
        padding: 14px 16px;
    }
    .activity-table tbody td {
        padding: 14px 16px;
        vertical-align: middle;
        border-color: #f0f0f0;
        font-size: 0.9rem;
    }
    .activity-table tbody tr:hover { background: #fafafa; }
    .type-badge {
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    .type-badge.in { background: #d4edda; color: #155724; }
    .type-badge.out { background: #f8d7da; color: #721c24; }
    .type-badge.adjustment { background: #fff3cd; color: #856404; }
</style>

<div class="container-fluid workbench-page">
    {{-- Header Card --}}
    <div class="workbench-header-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1><i class="mdi mdi-store mr-2"></i>{{ $store->store_name ?? 'Store Workbench' }}</h1>
                <p class="header-subtitle mb-0">
                    <i class="mdi mdi-map-marker mr-1"></i> {{ $store->location ?? 'Manage inventory, batches, and stock movements' }}
                </p>
            </div>
            <div class="col-md-4 text-md-right mt-3 mt-md-0">
                <div class="store-selector-wrapper">
                    <select id="store-selector" class="store-selector">
                        @foreach($stores as $s)
                        <option value="{{ $s->id }}" {{ isset($store) && $store->id == $s->id ? 'selected' : '' }}>
                            {{ $s->store_name }}
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Stats Grid --}}
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="mdi mdi-package-variant"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value primary">{{ $stats['total_products'] ?? 0 }}</div>
                <div class="stat-label">Total Products</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="mdi mdi-layers-triple"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value success">{{ $stats['total_batches'] ?? 0 }}</div>
                <div class="stat-label">Active Batches</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="mdi mdi-alert-outline"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value warning">{{ $stats['low_stock'] ?? 0 }}</div>
                <div class="stat-label">Low Stock Items</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon danger">
                <i class="mdi mdi-clock-alert-outline"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value danger">{{ $stats['expiring_soon'] ?? 0 }}</div>
                <div class="stat-label">Expiring Soon</div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Main Content --}}
        <div class="col-lg-8 mb-4">
            {{-- Quick Actions Section --}}
            <div class="section-card mb-4">
                <div class="section-card-header">
                    <h5><i class="mdi mdi-lightning-bolt"></i> Quick Actions</h5>
                </div>
                <div class="section-card-body">
                    <div class="actions-grid">
                        <a href="{{ route('inventory.store-workbench.stock-overview') }}?store_id={{ $store->id ?? '' }}" class="action-card">
                            <div class="action-icon primary">
                                <i class="mdi mdi-view-list-outline"></i>
                            </div>
                            <div class="action-content">
                                <h6>Stock Overview</h6>
                                <p>View all products and batch details</p>
                            </div>
                        </a>

                        <a href="{{ route('inventory.store-workbench.manual-batch-form') }}?store_id={{ $store->id ?? '' }}" class="action-card">
                            <div class="action-icon success">
                                <i class="mdi mdi-plus-circle-outline"></i>
                            </div>
                            <div class="action-content">
                                <h6>Add Batch</h6>
                                <p>Manually add a new stock batch</p>
                            </div>
                        </a>

                        <a href="{{ route('inventory.requisitions.create') }}?to_store_id={{ $store->id ?? '' }}" class="action-card">
                            <div class="action-icon info">
                                <i class="mdi mdi-swap-horizontal"></i>
                            </div>
                            <div class="action-content">
                                <h6>Request Stock</h6>
                                <p>Create requisition from another store</p>
                            </div>
                        </a>

                        <a href="{{ route('inventory.store-workbench.expiry-report') }}?store_id={{ $store->id ?? '' }}" class="action-card">
                            <div class="action-icon warning">
                                <i class="mdi mdi-calendar-clock"></i>
                            </div>
                            <div class="action-content">
                                <h6>Expiry Report</h6>
                                <p>View products nearing expiry</p>
                            </div>
                        </a>

                        <a href="{{ route('inventory.store-workbench.stock-value-report') }}?store_id={{ $store->id ?? '' }}" class="action-card">
                            <div class="action-icon secondary">
                                <i class="mdi mdi-calculator"></i>
                            </div>
                            <div class="action-content">
                                <h6>Stock Value</h6>
                                <p>Calculate total inventory value</p>
                            </div>
                        </a>

                        <a href="{{ route('inventory.purchase-orders.create') }}?store_id={{ $store->id ?? '' }}" class="action-card">
                            <div class="action-icon purple">
                                <i class="mdi mdi-cart-plus"></i>
                            </div>
                            <div class="action-content">
                                <h6>New Purchase Order</h6>
                                <p>Order stock from suppliers</p>
                            </div>
                        </a>

                        <a href="{{ route('suppliers.index') }}" class="action-card">
                            <div class="action-icon info">
                                <i class="mdi mdi-truck-delivery"></i>
                            </div>
                            <div class="action-content">
                                <h6>Manage Suppliers</h6>
                                <p>View and manage supplier records</p>
                            </div>
                        </a>

                        <a href="{{ route('products.index') }}" class="action-card">
                            <div class="action-icon secondary">
                                <i class="mdi mdi-package-variant"></i>
                            </div>
                            <div class="action-content">
                                <h6>Manage Products</h6>
                                <p>Add or edit product catalog</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Recent Activity Section --}}
            <div class="section-card">
                <div class="section-card-header">
                    <h5><i class="mdi mdi-history"></i> Recent Stock Movements</h5>
                    <a href="#" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="section-card-body p-0">
                    <div class="table-responsive">
                        <table class="table activity-table mb-0">
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
                                    <td>
                                        <strong>{{ Str::limit($transaction->batch->product->product_name ?? 'N/A', 25) }}</strong>
                                    </td>
                                    <td>
                                        <span class="type-badge {{ $transaction->transaction_type }}">
                                            {{ ucfirst($transaction->transaction_type) }}
                                        </span>
                                    </td>
                                    <td><strong>{{ $transaction->quantity }}</strong></td>
                                    <td><code>{{ $transaction->batch->batch_number ?? 'N/A' }}</code></td>
                                    <td>{{ $transaction->user->name ?? 'System' }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <div class="empty-icon">
                                                <i class="mdi mdi-clipboard-text-outline"></i>
                                            </div>
                                            <p>No recent transactions</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Alerts Sidebar --}}
        <div class="col-lg-4">
            {{-- Expiring Soon --}}
            <div class="section-card mb-4">
                <div class="section-card-header">
                    <h5><i class="mdi mdi-clock-alert-outline text-danger"></i> Expiring Soon</h5>
                    <a href="{{ route('inventory.store-workbench.expiry-report') }}?store_id={{ $store->id ?? '' }}" class="btn btn-sm btn-outline-danger">View All</a>
                </div>
                <div class="section-card-body p-0">
                    @forelse($expiringBatches ?? [] as $batch)
                    <div class="alert-item {{ $batch->days_until_expiry <= 30 ? 'critical' : 'warning' }}">
                        <div class="alert-item-info">
                            <h6>{{ Str::limit($batch->product->product_name ?? 'N/A', 28) }}</h6>
                            <small><i class="mdi mdi-barcode mr-1"></i>{{ $batch->batch_number }} &bull; {{ $batch->current_qty ?? 0 }} units</small>
                        </div>
                        <span class="alert-badge {{ $batch->days_until_expiry <= 30 ? 'danger' : 'warning' }}">
                            {{ $batch->days_until_expiry }} days
                        </span>
                    </div>
                    @empty
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="mdi mdi-check"></i>
                        </div>
                        <p>No items expiring soon</p>
                    </div>
                    @endforelse
                </div>
            </div>

            {{-- Low Stock --}}
            <div class="section-card">
                <div class="section-card-header">
                    <h5><i class="mdi mdi-alert-outline text-warning"></i> Low Stock</h5>
                    <a href="{{ route('inventory.store-workbench.stock-overview') }}?store_id={{ $store->id ?? '' }}&filter=low" class="btn btn-sm btn-outline-warning">View All</a>
                </div>
                <div class="section-card-body p-0">
                    @forelse($lowStockItems ?? [] as $item)
                    <div class="alert-item warning">
                        <div class="alert-item-info">
                            <h6>{{ Str::limit($item->product->product_name ?? 'N/A', 28) }}</h6>
                            <small><i class="mdi mdi-package-variant mr-1"></i>{{ $item->product->product_code ?? 'N/A' }}</small>
                        </div>
                        <div class="text-right">
                            <span class="alert-badge warning">{{ $item->current_quantity ?? 0 }} left</span>
                            @if($item->reorder_level)
                            <br><small class="text-muted">Min: {{ $item->reorder_level }}</small>
                            @endif
                        </div>
                    </div>
                    @empty
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="mdi mdi-check"></i>
                        </div>
                        <p>Stock levels are healthy</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function() {
    // Store selector change
    $('#store-selector').on('change', function() {
        window.location.href = '{{ route("inventory.store-workbench.index") }}?store_id=' + $(this).val();
    });
});
</script>
@endpush
