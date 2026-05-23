@extends('admin.layouts.app')
@section('title', 'Stock Adjustment')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Stock Adjustment')

@section('content')
<style>
    .adjustment-card {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        max-width: 600px;
        margin: 0 auto;
    }
    .batch-info {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }
    .batch-info-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }
    .batch-info-row:last-child {
        margin-bottom: 0;
    }
    .batch-info-label {
        color: #6c757d;
    }
    .batch-info-value {
        font-weight: 600;
    }
    .adjustment-type-btn {
        padding: 1rem;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .adjustment-type-btn.selected {
        border-width: 2px;
    }
    .adjustment-type-btn.add.selected {
        background: #d4edda;
        border-color: #28a745;
    }
    .adjustment-type-btn.subtract.selected {
        background: #f8d7da;
        border-color: #dc3545;
    }
</style>
<div id="content-wrapper">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-0">Stock Adjustment</h3>
                <p class="text-muted mb-0">{{ $batch->store->store_name ?? 'Store' }}</p>
            </div>
            @hasanyrole('SUPERADMIN|ADMIN|STORE')
            <a href="{{ route('inventory.store-workbench.index') }}?store_id={{ $batch->store_id }}" class="btn btn-secondary btn-sm">
                <i class="mdi mdi-arrow-left"></i> Back to Workbench
            </a>
            @else
            <a href="javascript:history.back()" class="btn btn-secondary btn-sm">
                <i class="mdi mdi-arrow-left"></i> Back
            </a>
            @endhasanyrole
        </div>
        <div class="adjustment-card">
            <div class="card-header">
                <h5 class="mb-0">Adjustment Details</h5>
            </div>
            <div class="card-body">
                <!-- Batch Info -->
                <div class="batch-info">
                    <div class="batch-info-row">
                        <span class="batch-info-label">Product</span>
                        <span class="batch-info-value">{{ $batch->product->product_name }}</span>
                    </div>
                    <div class="batch-info-row">
                        <span class="batch-info-label">Batch Number</span>
                        <span class="batch-info-value">{{ $batch->batch_number }}</span>
                    </div>
                    <div class="batch-info-row">
                        <span class="batch-info-label">Store</span>
                        <span class="batch-info-value">{{ $batch->store->store_name }}</span>
                    </div>
                    <div class="batch-info-row">
                        <span class="batch-info-label">Current Stock</span>
                        <span class="batch-info-value text-primary">{{ $batch->current_qty }}</span>
                    </div>
                    <div class="batch-info-row">
                        <span class="batch-info-label">Expiry Date</span>
                        <span class="batch-info-value">{{ $batch->expiry_date ? $batch->expiry_date->format('M d, Y') : 'N/A' }}</span>
                    </div>
                </div>

                <!-- Adjustment Form -->
                <form id="adjustmentForm" method="POST" action="{{ route('inventory.store-workbench.process-adjustment', $batch->id) }}">
                    @csrf

                    <!-- Adjustment Type -->
                    <div class="form-group">
                        <label class="mb-3">Adjustment Type <span class="text-danger">*</span></label>
                        <div class="row">
                            <div class="col-6">
                                <div class="adjustment-type-btn add border text-center" onclick="selectType('add')">
                                    <input type="radio" name="adjustment_type" value="add" class="d-none" id="type-add">
                                    <i class="mdi mdi-plus-circle text-success" style="font-size: 2rem;"></i>
                                    <div class="mt-2"><strong>Add Stock</strong></div>
                                    <small class="text-muted">Found/Returned items</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="adjustment-type-btn subtract border text-center" onclick="selectType('subtract')">
                                    <input type="radio" name="adjustment_type" value="subtract" class="d-none" id="type-subtract">
                                    <i class="mdi mdi-minus-circle text-danger" style="font-size: 2rem;"></i>
                                    <div class="mt-2"><strong>Subtract Stock</strong></div>
                                    <small class="text-muted">Damaged/Lost items</small>
                                </div>
                            </div>
                        </div>
                        @error('adjustment_type')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Quantity and Packaging -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="qty">Quantity <span class="text-danger">*</span></label>
                                <input type="number" name="qty" id="qty" class="form-control @error('qty') is-invalid @enderror"
                                       min="1" value="{{ old('qty', 1) }}" required>
                                <small class="text-muted" id="max-qty-hint">Max: {{ $batch->current_qty }}</small>
                                @error('qty')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="packaging_id">Packaging Unit</label>
                                <select id="packaging_id" class="form-control">
                                    <option value="" data-base="1">{{ $batch->product->base_unit_name ?? 'Base Unit' }}</option>
                                </select>
                                <small class="text-muted" id="base-equiv-hint" style="display:none;">= <strong id="base-equiv-qty">0</strong> units</small>
                            </div>
                        </div>
                    </div>

                    <!-- Reason -->
                    <div class="form-group">
                        <label for="reason">Reason <span class="text-danger">*</span></label>
                        <select name="reason" id="reason" class="form-control @error('reason') is-invalid @enderror" required>
                            <option value="">Select reason...</option>
                            <optgroup label="Add Stock">
                                <option value="Physical count correction (found)">Physical count correction (found)</option>
                                <option value="Returned by patient">Returned by patient</option>
                                <option value="Transfer from another location">Transfer from another location</option>
                                <option value="Other - add">Other</option>
                            </optgroup>
                            <optgroup label="Subtract Stock">
                                <option value="Physical count correction (loss)">Physical count correction (loss)</option>
                                <option value="Damaged">Damaged</option>
                                <option value="Expired">Expired</option>
                                <option value="Theft/Loss">Theft/Loss</option>
                                <option value="Sampling/Testing">Sampling/Testing</option>
                                <option value="Other - subtract">Other</option>
                            </optgroup>
                        </select>
                        @error('reason')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Notes -->
                    <div class="form-group">
                        <label for="notes">Additional Notes</label>
                        <textarea name="notes" id="notes" class="form-control" rows="2"
                                  placeholder="Optional additional details...">{{ old('notes') }}</textarea>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between">
                        <a href="{{ url()->previous() }}" class="btn btn-secondary">
                            <i class="mdi mdi-arrow-left"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                            <i class="mdi mdi-check"></i> Apply Adjustment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let selectedTypeVar = '';

function selectType(type) {
    selectedTypeVar = type;
    // Remove selected class from all
    document.querySelectorAll('.adjustment-type-btn').forEach(btn => {
        btn.classList.remove('selected');
    });

    // Add selected class to clicked
    document.querySelector('.adjustment-type-btn.' + type).classList.add('selected');
    document.getElementById('type-' + type).checked = true;

    updateMaxQty();

    // Enable submit button
    document.getElementById('submitBtn').disabled = false;
}

function updateMaxQty() {
    if (!selectedTypeVar) return;
    
    const qtyInput = document.getElementById('qty');
    const pkgSelect = document.getElementById('packaging_id');
    const baseFactor = parseFloat(pkgSelect.options[pkgSelect.selectedIndex].dataset.base) || 1;
    const maxHint = document.getElementById('max-qty-hint');
    
    if (selectedTypeVar === 'subtract') {
        const maxPkg = Math.floor({{ $batch->current_qty }} / baseFactor);
        qtyInput.max = maxPkg;
        maxHint.textContent = 'Max: ' + maxPkg + ' (based on current pieces)';
        if (parseInt(qtyInput.val || qtyInput.value) > maxPkg) {
            qtyInput.value = maxPkg;
        }
    } else {
        qtyInput.removeAttribute('max');
        maxHint.textContent = 'Enter quantity to add';
    }
    
    updateBaseEquiv();
}

function updateBaseEquiv() {
    const qtyInput = document.getElementById('qty');
    const pkgSelect = document.getElementById('packaging_id');
    const baseFactor = parseFloat(pkgSelect.options[pkgSelect.selectedIndex].dataset.base) || 1;
    const hint = document.getElementById('base-equiv-hint');
    const qtyValue = parseFloat(qtyInput.value) || 0;
    
    if (baseFactor > 1 && qtyValue > 0) {
        document.getElementById('base-equiv-qty').textContent = parseFloat((qtyValue * baseFactor).toFixed(4));
        hint.style.display = 'block';
    } else {
        hint.style.display = 'none';
    }
}

// Load packaging options
$(function() {
    const productId = {{ $batch->product_id }};
    const pkgSelect = document.getElementById('packaging_id');
    
    fetch('/products/' + productId + '/packagings')
    .then(response => response.json())
    .then(data => {
        const baseUnit = data.base_unit_name || 'pcs';
        pkgSelect.innerHTML = '<option value="" data-base="1">' + baseUnit + ' (base)</option>';
        
        if (data.packagings && data.packagings.length > 0) {
            data.packagings.forEach(pkg => {
                const opt = document.createElement('option');
                opt.value = pkg.id;
                opt.dataset.base = pkg.base_unit_qty;
                opt.textContent = pkg.name + ' (' + parseFloat(pkg.base_unit_qty) + ' ' + baseUnit + ')';
                pkgSelect.appendChild(opt);
            });
        }
    });
});

document.getElementById('packaging_id').addEventListener('change', updateMaxQty);
document.getElementById('qty').addEventListener('input', updateBaseEquiv);

// Form submission via AJAX
document.getElementById('adjustmentForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const pkgSelect = document.getElementById('packaging_id');
    const baseFactor = parseFloat(pkgSelect.options[pkgSelect.selectedIndex].dataset.base) || 1;
    const qtyInput = document.getElementById('qty');
    const originalQty = qtyInput.value;
    
    // Convert to base units for submission
    qtyInput.value = Math.round(parseFloat(originalQty) * baseFactor);

    const formData = new FormData(this);
    
    // Restore original qty for UI if needed (though we redirect)
    // qtyInput.value = originalQty;

    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="mdi mdi-loading mdi-spin"></i> Processing...';

    fetch(this.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            toastr.success(data.message || 'Adjustment applied successfully');
            setTimeout(() => {
                window.location.href = '{{ url()->previous() }}';
            }, 1000);
        } else {
            toastr.error(data.message || 'Failed to apply adjustment');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="mdi mdi-check"></i> Apply Adjustment';
        }
    })
    .catch(error => {
        toastr.error('An error occurred');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="mdi mdi-check"></i> Apply Adjustment';
    });
});
</script>
@endsection
