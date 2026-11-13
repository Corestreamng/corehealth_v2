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
                    @else
                    <a href="javascript:history.back()" class="btn btn-light btn-sm mr-2">
                        <i class="mdi mdi-arrow-left"></i> Back
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
                            @foreach($stores as $store)
                            <option value="{{ $store->id }}" {{ (request('store_id', $selectedStore?->id) == $store->id) ? 'selected' : '' }}>
                                {{ $store->store_name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <a href="{{ route('inventory.store-workbench.manual-batch-form', ['product_id' => $product->id, 'store_id' => $selectedStore?->id]) }}" class="btn btn-success btn-sm">
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
                    @if($batch->is_donation)
                        <span class="badge badge-success ml-1"><i class="mdi mdi-gift-outline mr-1"></i>Donation</span>
                    @endif
                    @if($batch->isVoided())
                        <span class="badge badge-danger ml-1"><i class="mdi mdi-close-octagon mr-1"></i>VOIDED</span>
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
                    <div class="value">
                        ₦{{ number_format($batch->cost_price, 2) }}
                        @if($batch->is_donation)
                            <small class="badge badge-success ml-1" style="font-size:0.65rem;">Donation</small>
                        @endif
                    </div>
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
                        @if($batch->is_donation)
                            Donation
                        @elseif($batch->purchaseOrderItem)
                            PO #{{ $batch->purchaseOrderItem->purchaseOrder->po_number ?? 'N/A' }}
                        @else
                            {{ ucfirst($batch->source ?? 'Manual') }}
                        @endif
                    </div>
                </div>
                <div class="detail-item">
                    <label>{{ $batch->is_donation ? 'Donor / Supplier' : 'Supplier' }}</label>
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

            <div class="mt-3 pt-3 border-top d-flex flex-wrap align-items-center">
                @if(!$batch->isVoided())
                    <a href="{{ route('inventory.store-workbench.adjustment-form', $batch->id) }}" class="btn btn-sm btn-outline-primary mr-2 mb-1">
                        <i class="mdi mdi-pencil mr-1"></i>Adjust Stock
                    </a>

                    <button type="button" class="btn btn-sm btn-outline-info mr-2 mb-1" onclick="openEditBatchModal({{ $batch->id }}, '{{ addslashes($batch->batch_number) }}', '{{ addslashes($batch->batch_name ?? '') }}', {{ $batch->supplier_id ?? 'null' }}, {{ $batch->cost_price }}, '{{ $batch->expiry_date?->format('Y-m-d') ?? '' }}', {{ $batch->current_qty }}, '{{ addslashes($batch->notes ?? '') }}')">
                        <i class="mdi mdi-pencil-box-outline mr-1"></i>Edit Batch
                    </button>

                    @if($batch->current_qty > 0)
                    <button type="button" class="btn btn-sm btn-outline-purple mr-2 mb-1" style="color:#7c3aed; border-color:#7c3aed;" onclick="openReturnBatchModal({{ $batch->id }}, '{{ addslashes($batch->batch_number) }}', '{{ addslashes($product->product_name) }}', {{ $batch->current_qty }}, {{ $batch->cost_price }}, {{ $batch->supplier_id ?? 'null' }}, '{{ addslashes($batch->supplier?->company_name ?? 'N/A') }}')">
                        <i class="mdi mdi-undo-variant mr-1"></i>Return to Supplier
                    </button>
                    @endif

                    @if($batch->sold_qty == 0)
                    <button type="button" class="btn btn-sm btn-outline-danger mr-2 mb-1" onclick="openVoidBatchModal({{ $batch->id }}, '{{ addslashes($batch->batch_number) }}', {{ $batch->current_qty }}, {{ $batch->cost_price * $batch->current_qty }})">
                        <i class="mdi mdi-close-octagon mr-1"></i>Void (In Error)
                    </button>
                    @endif

                    @if($expiryStatus === 'expired' || $batch->current_qty == 0)
                    <button type="button" class="btn btn-sm btn-outline-danger mr-2 mb-1" onclick="writeOffBatch({{ $batch->id }})">
                        <i class="mdi mdi-delete mr-1"></i>Write Off
                    </button>
                    @endif
                @else
                    <span class="badge badge-danger p-2"><i class="mdi mdi-close-octagon mr-1"></i>Voided on {{ $batch->voided_at?->format('M d, Y H:i') }}: {{ $batch->void_reason }}</span>
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

{{-- Modal Edit Batch --}}
<div class="modal fade" id="modal-edit-batch" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:12px; border:none; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
            <div class="modal-header" style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                <h5 class="modal-title font-weight-bold"><i class="mdi mdi-pencil-box-outline text-primary mr-2"></i>Edit Batch Metadata</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-edit-batch">
                @csrf
                <input type="hidden" name="batch_id" id="edit-batch-id">
                <div class="modal-body p-4">
                    <div class="alert alert-warning py-2 px-3 mb-3 d-flex align-items-center" style="font-size:0.83rem;">
                        <i class="mdi mdi-lock mr-2" style="font-size:1.3rem;"></i>
                        <div><strong>Quantity is locked:</strong> Batch stock count cannot be edited directly here. To change quantities, please use the audited <strong>Adjust Stock (+/-)</strong> flow.</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">Batch Number <span class="text-danger">*</span></label>
                            <input type="text" name="batch_number" id="edit-batch-number" class="form-control" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">Batch Label / Name</label>
                            <input type="text" name="batch_name" id="edit-batch-name" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">Supplier <span class="text-danger">*</span></label>
                            <select name="supplier_id" id="edit-batch-supplier" class="form-control" required>
                                <option value="">— Select Supplier —</option>
                                @foreach($suppliers as $sup)
                                <option value="{{ $sup->id }}">{{ $sup->company_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="small font-weight-bold">Cost Price (₦) <span class="text-danger">*</span></label>
                            <input type="number" name="cost_price" id="edit-batch-cost-price" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="small font-weight-bold">Current Stock (Locked)</label>
                            <input type="text" id="edit-batch-qty-display" class="form-control bg-light font-weight-bold text-primary" disabled>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="small font-weight-bold">Expiry Date</label>
                            <input type="date" name="expiry_date" id="edit-batch-expiry-date" class="form-control">
                        </div>
                        <div class="col-md-8 form-group">
                            <label class="small font-weight-bold">Notes</label>
                            <textarea name="notes" id="edit-batch-notes" class="form-control" rows="1"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-save-edit-batch">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Void Batch --}}
<div class="modal fade" id="modal-void-batch" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:12px; border:none; box-shadow:0 10px 25px rgba(0,0,0,0.15);">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title font-weight-bold"><i class="mdi mdi-close-octagon-outline mr-2"></i>Void Batch (Recorded in Error)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-void-batch">
                @csrf
                <input type="hidden" name="batch_id" id="void-batch-id">
                <div class="modal-body p-4">
                    <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:0.83rem;">
                        <strong>Data Entry Correction:</strong> Use this ONLY for batches entered by mistake that never physically existed. This zeroes the batch and automatically reverses the intake General Ledger entry.
                    </div>
                    <div class="p-3 mb-3 bg-light rounded" style="font-size:0.85rem;">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Batch Number:</span>
                            <strong id="void-batch-number-display">—</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Quantity to Void:</span>
                            <strong id="void-batch-qty-display" class="text-danger">—</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Total Value to Reverse:</span>
                            <strong id="void-batch-value-display">—</strong>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold">Reason for Voiding <span class="text-danger">*</span></label>
                        <textarea name="reason" id="void-batch-reason" class="form-control" rows="2" required placeholder="Why did this batch never physically exist?"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm" id="btn-confirm-void-batch">Confirm Void Batch</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Return Batch --}}
<div class="modal fade" id="modal-return-batch" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:12px; border:none; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
            <div class="modal-header bg-light">
                <h5 class="modal-title font-weight-bold" style="color:#7c3aed;"><i class="mdi mdi-undo-variant mr-2"></i>Return Batch to Supplier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-return-batch">
                @csrf
                <input type="hidden" name="batch_id" id="return-batch-id">
                <input type="hidden" name="supplier_id" id="return-batch-supplier-id">
                <div class="modal-body p-4">
                    <div class="p-3 mb-3 bg-light rounded" style="font-size:0.85rem;">
                        <div class="row">
                            <div class="col-md-4">
                                <span class="text-muted small d-block">PRODUCT</span>
                                <strong id="return-product-name">—</strong>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted small d-block">BATCH # / SUPPLIER</span>
                                <strong id="return-batch-supplier-info">—</strong>
                            </div>
                            <div class="col-md-4 text-right">
                                <span class="text-muted small d-block">AVAILABLE UNITS</span>
                                <strong id="return-available-qty" class="text-primary font-weight-bold" style="font-size:1.1rem;">0</strong>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="small font-weight-bold">Quantity to Return <span class="text-danger">*</span></label>
                            <input type="number" name="qty_returned" id="return-qty" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="small font-weight-bold">Return Reason <span class="text-danger">*</span></label>
                            <select name="return_reason" id="return-reason" class="form-control" required>
                                <option value="damaged">Damaged / Broken in Transit</option>
                                <option value="expired_near">Near Expiry / Short Shelf Life</option>
                                <option value="recalled">Manufacturer Recall</option>
                                <option value="wrong_item">Wrong Product Delivered</option>
                                <option value="excess">Excess Delivery</option>
                                <option value="quality_issue">Quality / Discoloration Issue</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="small font-weight-bold">Settlement Mode <span class="text-danger">*</span></label>
                            <select name="settlement_type" id="return-settlement-type" class="form-control" required>
                                <option value="ap_credit_note">AP Credit Note (Reduce Debt)</option>
                                <option value="cash_refund">Cash Refund Received</option>
                                <option value="receivable">Supplier Refund Due (AR)</option>
                                <option value="no_financial_impact">No Financial Impact (Donation)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold">Notes / Waybill</label>
                        <textarea name="return_notes" id="return-notes" class="form-control" rows="2" placeholder="Waybill info or reason details…"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" id="btn-submit-return" style="background:#7c3aed; color:#fff;">Process Return</button>
                </div>
            </form>
        </div>
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

    // Form Edit Batch Submit
    $('#form-edit-batch').on('submit', function(e) {
        e.preventDefault();
        var batchId = $('#edit-batch-id').val();
        var btn = $('#btn-save-edit-batch').prop('disabled', true).text('Saving…');

        $.post(`/inventory/store-workbench/batch/${batchId}/update`, $(this).serialize())
            .done(function(res) {
                toastr.success(res.message || 'Batch updated successfully');
                $('#modal-edit-batch').modal('hide');
                location.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to update batch');
            })
            .always(function() {
                btn.prop('disabled', false).text('Save Changes');
            });
    });

    // Form Void Batch Submit
    $('#form-void-batch').on('submit', function(e) {
        e.preventDefault();
        var batchId = $('#void-batch-id').val();
        var btn = $('#btn-confirm-void-batch').prop('disabled', true).text('Voiding…');

        $.post(`/inventory/store-workbench/batch/${batchId}/void`, $(this).serialize())
            .done(function(res) {
                toastr.success(res.message || 'Batch voided successfully');
                $('#modal-void-batch').modal('hide');
                location.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to void batch');
            })
            .always(function() {
                btn.prop('disabled', false).text('Confirm Void Batch');
            });
    });

    // Form Return Batch Submit
    $('#form-return-batch').on('submit', function(e) {
        e.preventDefault();
        var batchId = $('#return-batch-id').val();
        var btn = $('#btn-submit-return').prop('disabled', true).text('Processing…');

        $.post(`/inventory/store-workbench/batch/${batchId}/return`, $(this).serialize())
            .done(function(res) {
                toastr.success(res.message || 'Batch return processed successfully');
                $('#modal-return-batch').modal('hide');
                location.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to process return');
            })
            .always(function() {
                btn.prop('disabled', false).text('Process Return');
            });
    });
});

function openEditBatchModal(id, batchNumber, batchName, supplierId, costPrice, expiryDate, qty, notes) {
    $('#edit-batch-id').val(id);
    $('#edit-batch-number').val(batchNumber);
    $('#edit-batch-name').val(batchName);
    $('#edit-batch-supplier').val(supplierId || '');
    $('#edit-batch-cost-price').val(costPrice);
    $('#edit-batch-expiry-date').val(expiryDate);
    $('#edit-batch-qty-display').val(qty + ' units (Locked)');
    $('#edit-batch-notes').val(notes);
    $('#modal-edit-batch').modal('show');
}

function openVoidBatchModal(id, batchNumber, qty, value) {
    $('#void-batch-id').val(id);
    $('#void-batch-number-display').text(batchNumber);
    $('#void-batch-qty-display').text(qty + ' units');
    $('#void-batch-value-display').text('₦' + parseFloat(value).toLocaleString('en-US', {minimumFractionDigits: 2}));
    $('#void-batch-reason').val('');
    $('#modal-void-batch').modal('show');
}

function openReturnBatchModal(id, batchNumber, productName, qty, unitCost, supplierId, supplierName) {
    $('#return-batch-id').val(id);
    $('#return-batch-supplier-id').val(supplierId || '');
    $('#return-product-name').text(productName);
    $('#return-batch-supplier-info').text(batchNumber + ' (' + supplierName + ')');
    $('#return-available-qty').text(qty);
    $('#return-qty').val(1).attr('max', qty);
    $('#return-notes').val('');
    $('#modal-return-batch').modal('show');
}

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
