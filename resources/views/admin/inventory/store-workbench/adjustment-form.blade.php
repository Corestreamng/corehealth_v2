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
        <div class="adjustment-card">
            <div class="card-header">
                <h5 class="mb-0">Stock Adjustment</h5>
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

                    <!-- Quantity -->
                    <div class="form-group">
                        <label for="qty">Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="qty" id="qty" class="form-control @error('qty') is-invalid @enderror"
                               min="1" max="{{ $batch->current_qty }}" value="{{ old('qty', 1) }}" required>
                        <small class="text-muted">Max: {{ $batch->current_qty }} (for subtract)</small>
                        @error('qty')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
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
function selectType(type) {
    // Remove selected class from all
    document.querySelectorAll('.adjustment-type-btn').forEach(btn => {
        btn.classList.remove('selected');
    });

    // Add selected class to clicked
    document.querySelector('.adjustment-type-btn.' + type).classList.add('selected');
    document.getElementById('type-' + type).checked = true;

    // Update max quantity for subtract
    const qtyInput = document.getElementById('qty');
    if (type === 'subtract') {
        qtyInput.max = {{ $batch->current_qty }};
    } else {
        qtyInput.max = 99999;
    }

    // Enable submit button
    document.getElementById('submitBtn').disabled = false;
}

// Form submission via AJAX
document.getElementById('adjustmentForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
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
