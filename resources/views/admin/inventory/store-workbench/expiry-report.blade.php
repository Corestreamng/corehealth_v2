@extends('admin.layouts.app')
@section('title', 'Expiry Report')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Expiry Report')

@push('styles')
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<style>
    .expiry-card {
        border-radius: 8px;
        padding: 1.25rem;
        margin-bottom: 1rem;
    }
    .expiry-card h5 {
        font-size: 0.85rem;
        color: #6c757d;
        margin-bottom: 0.5rem;
    }
    .expiry-card .value {
        font-size: 1.5rem;
        font-weight: 600;
    }

    .expired-row {
        background-color: #f8d7da !important;
    }
    .critical-row {
        background-color: #fff3cd !important;
    }
    .warning-row {
        background-color: #fff9e6 !important;
    }

    .batch-actions .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
</style>
@endpush

@section('content')
<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-0">Expiry Report</h3>
                <p class="text-muted mb-0">{{ $selectedStore->store_name ?? 'All Stores' }}</p>
            </div>
            <div>
                <a href="{{ route('inventory.store-workbench.index') }}?store_id={{ $selectedStore->id ?? '' }}" class="btn btn-secondary btn-sm">
                    <i class="mdi mdi-arrow-left"></i> Back to Workbench
                </a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="expiry-card bg-danger text-white">
                    <h5 class="text-white-50">Already Expired</h5>
                    <div class="value">{{ $stats['expired'] ?? 0 }}</div>
                    <small>batches</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="expiry-card bg-warning">
                    <h5>Expiring in 30 days</h5>
                    <div class="value text-dark">{{ $stats['expiring_30'] ?? 0 }}</div>
                    <small>batches</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="expiry-card bg-info text-white">
                    <h5 class="text-white-50">Expiring in 90 days</h5>
                    <div class="value">{{ $stats['expiring_90'] ?? 0 }}</div>
                    <small>batches</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="expiry-card bg-white shadow-sm">
                    <h5>Total Value at Risk</h5>
                    <div class="value text-danger">₦{{ number_format($stats['value_at_risk'] ?? 0, 2) }}</div>
                    <small>in expiring batches</small>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card-modern mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <label class="small">Store</label>
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
                        <label class="small">Expiry Period</label>
                        <select id="period-filter" class="form-control form-control-sm">
                            <option value="all" {{ request('period') == 'all' ? 'selected' : '' }}>All</option>
                            <option value="expired" {{ request('period') == 'expired' ? 'selected' : '' }}>Already Expired</option>
                            <option value="30" {{ request('period') == '30' ? 'selected' : '' }}>Next 30 days</option>
                            <option value="60" {{ request('period') == '60' ? 'selected' : '' }}>Next 60 days</option>
                            <option value="90" {{ request('period') == '90' ? 'selected' : '' }}>Next 90 days</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="small">&nbsp;</label>
                        <button type="button" class="btn btn-primary btn-sm btn-block" onclick="applyFilters()">
                            <i class="mdi mdi-filter"></i> Apply Filters
                        </button>
                    </div>
                    <div class="col-md-3">
                        <label class="small">&nbsp;</label>
                        <button type="button" class="btn btn-outline-secondary btn-sm btn-block" onclick="exportReport()">
                            <i class="mdi mdi-download"></i> Export Report
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expiring Batches Table -->
        <div class="card-modern">
            <div class="card-header">
                <h5 class="mb-0">Expiring Batches</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="expiry-table" class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Batch #</th>
                                <th>Store</th>
                                <th class="text-center">Quantity</th>
                                <th>Expiry Date</th>
                                <th class="text-center">Days Left</th>
                                <th class="text-right">Value</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($batches ?? [] as $batch)
                            @php
                                $daysLeft = $batch->expiry_date ? now()->diffInDays($batch->expiry_date, false) : null;
                                $rowClass = '';
                                if ($daysLeft !== null) {
                                    if ($daysLeft < 0) $rowClass = 'expired-row';
                                    elseif ($daysLeft <= 30) $rowClass = 'critical-row';
                                    elseif ($daysLeft <= 90) $rowClass = 'warning-row';
                                }
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td>
                                    <strong>{{ $batch->product->product_name }}</strong>
                                    <br><small class="text-muted">{{ $batch->product->product_code }}</small>
                                </td>
                                <td>{{ $batch->batch_number }}</td>
                                <td>{{ $batch->store->store_name ?? 'N/A' }}</td>
                                <td class="text-center">{{ $batch->current_quantity }}</td>
                                <td>
                                    {{ $batch->expiry_date ? $batch->expiry_date->format('M d, Y') : 'N/A' }}
                                </td>
                                <td class="text-center">
                                    @if($daysLeft !== null)
                                        @if($daysLeft < 0)
                                        <span class="badge badge-danger">Expired {{ abs($daysLeft) }} days ago</span>
                                        @elseif($daysLeft == 0)
                                        <span class="badge badge-danger">Expires today</span>
                                        @elseif($daysLeft <= 30)
                                        <span class="badge badge-warning">{{ $daysLeft }} days</span>
                                        @else
                                        <span class="badge badge-info">{{ $daysLeft }} days</span>
                                        @endif
                                    @else
                                    <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    ₦{{ number_format($batch->current_quantity * ($batch->cost_price ?? 0), 2) }}
                                </td>
                                <td>
                                    <div class="batch-actions">
                                        @if($daysLeft !== null && $daysLeft < 0)
                                        <button type="button" class="btn btn-danger btn-sm"
                                                onclick="writeOffExpired({{ $batch->id }})">
                                            <i class="mdi mdi-delete"></i> Write Off
                                        </button>
                                        @else
                                        <a href="{{ route('inventory.store-workbench.adjustment-form', $batch) }}"
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="mdi mdi-pencil"></i> Adjust
                                        </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="mdi mdi-check-circle mdi-48px text-success"></i>
                                    <p class="text-muted mt-2">No expiring batches found</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if(method_exists($batches ?? collect(), 'links'))
                <div class="mt-3">
                    {{ $batches->appends(request()->query())->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function applyFilters() {
    var params = new URLSearchParams();
    if ($('#store-filter').val()) params.set('store_id', $('#store-filter').val());
    if ($('#period-filter').val()) params.set('period', $('#period-filter').val());

    window.location.href = '{{ route("inventory.store-workbench.expiry-report") }}?' + params.toString();
}

function writeOffExpired(batchId) {
    var reason = prompt('Reason for write-off (optional):');
    if (reason !== null) {
        $.post('/inventory/store-workbench/batch/' + batchId + '/write-off-expired', {
            _token: '{{ csrf_token() }}',
            reason: reason
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

function exportReport() {
    var params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.location.href = '{{ route("inventory.store-workbench.expiry-report") }}?' + params.toString();
}
</script>
@endsection
