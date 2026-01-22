@extends('admin.layouts.app')
@section('title', 'Supplier Details')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Supplier Details')

@section('content')
<style>
    .supplier-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .stat-card {
        background: #fff;
        padding: 1.25rem;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        text-align: center;
    }
    .stat-card h3 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 600;
    }
    .stat-card small {
        color: #6c757d;
    }
    .info-section {
        background: #fff;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .info-section h5 {
        border-bottom: 2px solid #007bff;
        padding-bottom: 0.5rem;
        margin-bottom: 1rem;
    }
    .info-row {
        display: flex;
        margin-bottom: 0.75rem;
    }
    .info-label {
        width: 150px;
        font-weight: 500;
        color: #6c757d;
    }
    .info-value {
        flex: 1;
    }
    .batch-list {
        max-height: 400px;
        overflow-y: auto;
    }
    .batch-item {
        background: #f8f9fa;
        padding: 0.75rem;
        border-radius: 4px;
        margin-bottom: 0.5rem;
        border-left: 3px solid #007bff;
    }
</style>
<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Supplier Header -->
        <div class="supplier-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-1">{{ $supplier->company_name }}</h3>
                    <p class="mb-0 opacity-75">
                        @if($supplier->contact_person)
                            <i class="mdi mdi-account"></i> {{ $supplier->contact_person }} |
                        @endif
                        <i class="mdi mdi-phone"></i> {{ $supplier->phone }}
                        @if($supplier->email)
                            | <i class="mdi mdi-email"></i> {{ $supplier->email }}
                        @endif
                    </p>
                </div>
                <div>
                    <a href="{{ route('suppliers.edit', $supplier->id) }}" class="btn btn-light btn-sm mr-2">
                        <i class="mdi mdi-pencil"></i> Edit
                    </a>
                    <a href="{{ route('suppliers.index') }}" class="btn btn-light btn-sm">
                        <i class="mdi mdi-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stat-grid">
            <div class="stat-card">
                <h3 class="text-primary">{{ $stats['total_batches'] }}</h3>
                <small>Total Batches</small>
            </div>
            <div class="stat-card">
                <h3 class="text-success">{{ $stats['active_batches'] }}</h3>
                <small>Active Batches</small>
            </div>
            <div class="stat-card">
                <h3 class="text-info">₦{{ number_format($stats['total_supplied_value'], 2) }}</h3>
                <small>Total Supplied Value</small>
            </div>
            <div class="stat-card">
                <h3 class="text-warning">{{ $stats['total_po_count'] }}</h3>
                <small>Purchase Orders</small>
            </div>
            <div class="stat-card">
                <h3 class="text-secondary">{{ $stats['pending_po_count'] }}</h3>
                <small>Pending POs</small>
            </div>
        </div>

        <div class="row">
            <!-- Contact & Bank Info -->
            <div class="col-md-5">
                <div class="info-section">
                    <h5><i class="mdi mdi-information"></i> Contact Information</h5>
                    <div class="info-row">
                        <span class="info-label">Address</span>
                        <span class="info-value">{{ $supplier->address ?: '-' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Alt. Phone</span>
                        <span class="info-value">{{ $supplier->alt_phone ?: '-' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Tax Number</span>
                        <span class="info-value">{{ $supplier->tax_number ?: '-' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Payment Terms</span>
                        <span class="info-value">{{ ucfirst(str_replace('_', ' ', $supplier->payment_terms ?? '-')) }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Credit Limit</span>
                        <span class="info-value">₦{{ number_format($supplier->credit_limit ?? 0, 2) }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status</span>
                        <span class="info-value">
                            @if($supplier->status)
                                <span class="badge badge-success">Active</span>
                            @else
                                <span class="badge badge-secondary">Inactive</span>
                            @endif
                        </span>
                    </div>
                </div>

                <div class="info-section">
                    <h5><i class="mdi mdi-bank"></i> Bank Details</h5>
                    <div class="info-row">
                        <span class="info-label">Bank</span>
                        <span class="info-value">{{ $supplier->bank_name ?: '-' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Account #</span>
                        <span class="info-value">{{ $supplier->bank_account_number ?: '-' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Account Name</span>
                        <span class="info-value">{{ $supplier->bank_account_name ?: '-' }}</span>
                    </div>
                </div>

                @if($supplier->notes)
                <div class="info-section">
                    <h5><i class="mdi mdi-note-text"></i> Notes</h5>
                    <p class="mb-0">{{ $supplier->notes }}</p>
                </div>
                @endif
            </div>

            <!-- Recent Batches -->
            <div class="col-md-7">
                <div class="info-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="mdi mdi-package-variant"></i> Recent Batches</h5>
                        <a href="{{ route('suppliers.reports.batches', ['supplier_id' => $supplier->id]) }}" class="btn btn-sm btn-outline-primary">
                            View All
                        </a>
                    </div>
                    <div class="batch-list">
                        @forelse($recentBatches as $batch)
                        <div class="batch-item">
                            <div class="d-flex justify-content-between">
                                <strong>{{ $batch->product->product_name ?? 'Unknown' }}</strong>
                                <span class="text-muted">{{ $batch->created_at->format('M d, Y') }}</span>
                            </div>
                            <small class="text-muted">
                                Batch: {{ $batch->batch_number }} |
                                Store: {{ $batch->store->store_name ?? '-' }} |
                                Qty: {{ $batch->initial_qty }} |
                                Cost: ₦{{ number_format($batch->cost_price, 2) }}
                            </small>
                        </div>
                        @empty
                        <p class="text-muted text-center py-3">No batches from this supplier yet</p>
                        @endforelse
                    </div>
                </div>

                <div class="info-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="mdi mdi-file-document"></i> Recent Purchase Orders</h5>
                        @if($supplier->purchaseOrders->count() > 0)
                        <a href="{{ route('inventory.purchase-orders.index', ['supplier_id' => $supplier->id]) }}" class="btn btn-sm btn-outline-primary">
                            View All
                        </a>
                        @endif
                    </div>
                    @forelse($recentPOs as $po)
                    <div class="batch-item">
                        <div class="d-flex justify-content-between">
                            <strong>PO #{{ $po->po_number ?? $po->id }}</strong>
                            <span class="badge badge-{{ $po->status == 'completed' ? 'success' : ($po->status == 'pending' ? 'warning' : 'secondary') }}">
                                {{ ucfirst($po->status) }}
                            </span>
                        </div>
                        <small class="text-muted">
                            {{ $po->created_at->format('M d, Y') }} |
                            Total: ₦{{ number_format($po->total_amount ?? 0, 2) }}
                        </small>
                    </div>
                    @empty
                    <p class="text-muted text-center py-3">No purchase orders yet</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
