@extends('admin.layouts.app')
@section('title', 'Add Manual Batch')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Add Manual Batch')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/select2.min.css') }}">
<style>
    .form-section {
        background: #fff;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .form-section h5 {
        border-bottom: 2px solid #007bff;
        padding-bottom: 0.5rem;
        margin-bottom: 1rem;
    }
</style>
<div id="content-wrapper">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-0">Add Manual Batch</h3>
                <p class="text-muted mb-0">{{ $store->store_name ?? 'Select Store' }}</p>
            </div>
            @hasanyrole('SUPERADMIN|ADMIN|STORE')
            <a href="{{ route('inventory.store-workbench.index') }}{{ request('store_id') ? '?store_id=' . request('store_id') : '' }}" class="btn btn-secondary btn-sm">
                <i class="mdi mdi-arrow-left"></i> Back to Workbench
            </a>
            @endhasanyrole
        </div>
        <div class="row justify-content-center">
            <div class="col-md-8">
                {{-- Flash Messages --}}
                @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-check-circle mr-2"></i>{{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                @endif

                @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-alert-circle mr-2"></i>{{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                @endif

                @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-alert-circle mr-2"></i><strong>Please fix the following errors:</strong>
                    <ul class="mb-0 mt-2">
                        @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                @endif

                <form method="POST" action="{{ route('inventory.store-workbench.create-manual-batch') }}">
                    @csrf

                    <div class="form-section">
                        <h5><i class="mdi mdi-plus-box"></i> Add Manual Stock Batch</h5>
                        <p class="text-muted">Use this form to manually add stock that was not received through a Purchase Order.</p>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="store_id">Store <span class="text-danger">*</span></label>
                                    <select name="store_id" id="store_id" class="form-control @error('store_id') is-invalid @enderror" required>
                                        <option value="">Select Store</option>
                                        @foreach($stores as $s)
                                        <option value="{{ $s->id }}" {{ old('store_id', request('store_id', $store->id ?? '')) == $s->id ? 'selected' : '' }}>
                                            {{ $s->store_name }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @error('store_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="product_id">Product <span class="text-danger">*</span></label>
                                    <select name="product_id" id="product_id" class="form-control product-select @error('product_id') is-invalid @enderror" required>
                                        <option value="">Search product...</option>
                                    </select>
                                    @error('product_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="supplier_id">Supplier</label>
                                    <select name="supplier_id" id="supplier_id" class="form-control supplier-select @error('supplier_id') is-invalid @enderror">
                                        <option value="">Select supplier (optional)...</option>
                                    </select>
                                    @error('supplier_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">
                                        <a href="{{ route('suppliers.create') }}" target="_blank">+ Add new supplier</a>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="batch_number">Batch Number <span class="text-danger">*</span></label>
                                    <input type="text" name="batch_number" id="batch_number"
                                           class="form-control @error('batch_number') is-invalid @enderror"
                                           value="{{ old('batch_number') }}"
                                           placeholder="e.g., BTH001, LOT2026A"
                                           required>
                                    @error('batch_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">
                                        <i class="mdi mdi-information-outline"></i>
                                        Batch Name: <strong id="batch-name-preview">-</strong>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="quantity">Quantity <span class="text-danger">*</span></label>
                                    <input type="number" name="quantity" id="quantity"
                                           class="form-control @error('quantity') is-invalid @enderror"
                                           value="{{ old('quantity', 1) }}" min="1" required>
                                    @error('quantity')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="received_date">Received Date</label>
                                    <input type="date" name="received_date" id="received_date"
                                           class="form-control"
                                           value="{{ old('received_date', date('Y-m-d')) }}">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="expiry_date">Expiry Date</label>
                                    <input type="date" name="expiry_date" id="expiry_date"
                                           class="form-control @error('expiry_date') is-invalid @enderror"
                                           value="{{ old('expiry_date') }}">
                                    @error('expiry_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="manufacture_date">Manufacture Date</label>
                                    <input type="date" name="manufacture_date" id="manufacture_date"
                                           class="form-control"
                                           value="{{ old('manufacture_date') }}">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="cost_price">Cost Price (per unit)</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">₦</span>
                                        </div>
                                        <input type="number" name="cost_price" id="cost_price"
                                               class="form-control"
                                               value="{{ old('cost_price', 0) }}" step="0.01" min="0">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="reference_type">Reason/Reference</label>
                                    <select name="reference_type" id="reference_type" class="form-control">
                                        <option value="manual_entry">Manual Entry</option>
                                        <option value="opening_stock">Opening Stock</option>
                                        <option value="donation">Donation</option>
                                        <option value="transfer_in">Transfer In</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea name="notes" id="notes" rows="2" class="form-control" placeholder="Any additional notes about this batch...">{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="d-flex justify-content-between">
                            <div>
                                @if(request('product_id'))
                                <a href="{{ route('products.index') }}" class="btn btn-outline-primary mr-2">
                                    <i class="mdi mdi-package-variant"></i> Products
                                </a>
                                @endif
                                <a href="{{ route('inventory.store-workbench.index') }}?store_id={{ request('store_id') }}" class="btn btn-secondary">
                                    <i class="mdi mdi-arrow-left"></i> Cancel
                                </a>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-plus"></i> Add Batch
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('assets/js/select2.min.js') }}"></script>
<script>
$(function() {
    // Product search with Select2
    var productSelect = $('#product_id').select2({
        placeholder: 'Search product...',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: '{{ route("inventory.purchase-orders.search-products") }}',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { q: params.term };
            },
            processResults: function(data) {
                return {
                    results: data.map(function(item) {
                        return {
                            id: item.id,
                            text: item.product_name + ' (' + item.product_code + ')'
                        };
                    })
                };
            }
        }
    });

    // Supplier search with Select2
    var supplierSelect = $('#supplier_id').select2({
        placeholder: 'Select supplier...',
        allowClear: true,
        minimumInputLength: 1,
        ajax: {
            url: '{{ route("suppliers.search") }}',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { q: params.term };
            },
            processResults: function(data) {
                return {
                    results: data.map(function(item) {
                        return {
                            id: item.id,
                            text: item.text
                        };
                    })
                };
            }
        }
    });

    // Batch name preview - updates as user types batch number
    function updateBatchNamePreview() {
        var batchNumber = $('#batch_number').val();
        if (batchNumber) {
            var now = new Date();
            var timestamp = now.getFullYear().toString() +
                ('0' + (now.getMonth() + 1)).slice(-2) +
                ('0' + now.getDate()).slice(-2) +
                ('0' + now.getHours()).slice(-2) +
                ('0' + now.getMinutes()).slice(-2) +
                ('0' + now.getSeconds()).slice(-2);
            $('#batch-name-preview').text(batchNumber + '-' + timestamp);
        } else {
            $('#batch-name-preview').text('-');
        }
    }

    $('#batch_number').on('input', updateBatchNamePreview);
    updateBatchNamePreview(); // Initial call in case of old() value

    // Pre-select product if provided
    @if(isset($selectedProduct) && $selectedProduct)
    var preselectedProduct = {
        id: {{ $selectedProduct->id }},
        text: '{{ $selectedProduct->product_name }} ({{ $selectedProduct->product_code }})'
    };
    var newOption = new Option(preselectedProduct.text, preselectedProduct.id, true, true);
    productSelect.append(newOption).trigger('change');
    @endif
});
</script>
@endsection
