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
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
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
            @else
            <a href="javascript:history.back()" class="btn btn-secondary btn-sm">
                <i class="mdi mdi-arrow-left"></i> Back
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
                                    <label for="supplier_id" id="manual_supplier_label">Supplier <span class="text-danger">*</span></label>
                                    <select name="supplier_id" id="supplier_id" class="form-control select2 supplier-select @error('supplier_id') is-invalid @enderror" required onchange="updateManualBatchGLPreview()">
                                        <option value="">— Select Supplier / Donor —</option>
                                        @foreach($suppliers as $sup)
                                        <option value="{{ $sup->id }}" {{ old('supplier_id') == $sup->id ? 'selected' : '' }}>
                                            {{ $sup->company_name }}{{ $sup->contact_person ? ' (' . $sup->contact_person . ')' : '' }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @error('supplier_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted" id="manual_supplier_hint">
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

                        {{-- Acquisition Type (General Ledger Impact) --}}
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group mb-3">
                                    <label class="font-weight-bold">Acquisition Type (General Ledger Impact) <span class="text-danger">*</span></label>
                                    <div class="btn-group btn-group-toggle d-flex" data-toggle="buttons">
                                        <label class="btn btn-outline-primary btn-sm active" style="border-radius:8px 0 0 8px; font-weight:600;">
                                            <input type="radio" name="acquisition_type" value="credit" checked onchange="updateManualBatchGLPreview()">
                                            <i class="mdi mdi-credit-card-outline mr-1"></i>On Credit (AP Debt)
                                        </label>
                                        <label class="btn btn-outline-info btn-sm" style="font-weight:600;">
                                            <input type="radio" name="acquisition_type" value="cash" onchange="updateManualBatchGLPreview()">
                                            <i class="mdi mdi-cash-multiple mr-1"></i>Petty Cash / Till
                                        </label>
                                        <label class="btn btn-outline-secondary btn-sm" style="border-radius:0 8px 8px 0; font-weight:600;">
                                            <input type="radio" name="acquisition_type" value="opening_stock" onchange="updateManualBatchGLPreview()">
                                            <i class="mdi mdi-book-open-outline mr-1"></i>Opening Stock (Equity)
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="packaging_qty">Quantity <span class="text-danger">*</span></label>
                                    <input type="number" name="packaging_qty" id="packaging_qty"
                                        class="form-control @error('quantity') is-invalid @enderror"
                                        value="{{ old('packaging_qty', old('quantity', 1)) }}" min="1" required
                                        placeholder="0" oninput="calculateManualBatchBaseQty()">
                                    <input type="hidden" name="quantity" id="quantity" value="{{ old('quantity', 1) }}">
                                    @error('quantity')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="batch_packaging">Packaging Unit</label>
                                    <select name="batch_packaging" id="batch_packaging" class="form-control">
                                        <option value="" data-base="1">Base Unit</option>
                                    </select>
                                    <small class="text-muted" id="batch-base-equiv" style="display:none;">= <strong id="batch-base-qty">0</strong> <span id="batch-base-unit-name">units</span></small>
                                </div>
                            </div>
                            <div class="col-md-3">
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
                                      <label for="cost_price" id="manual-cost-price-label">Cost Price (per packaging unit) <span class="text-danger">*</span></label>
                                      <div class="input-group">
                                          <div class="input-group-prepend">
                                              <span class="input-group-text">₦</span>
                                          </div>
                                          <input type="number" name="cost_price" id="cost_price"
                                              class="form-control"
                                              value="{{ old('cost_price', '') }}" step="0.01" min="0" required
                                              placeholder="0.00 — enter 0 for donations"
                                              oninput="updateManualBatchCostPreview()">
                                      </div>
                                      <div class="custom-control custom-checkbox mt-1">
                                          <input type="checkbox" class="custom-control-input" name="is_donation" id="manual_is_donation" value="1" {{ old('is_donation') || old('reference_type') === 'donation' ? 'checked' : '' }} onchange="toggleManualDonation(this)">
                                          <label class="custom-control-label text-success font-weight-bold" for="manual_is_donation" style="font-size: 0.8rem; cursor:pointer;">
                                              <i class="mdi mdi-gift-outline mr-1"></i>Mark as Donation (Cost: ₦0.00)
                                          </label>
                                      </div>
                                    <small class="text-muted d-block mt-1">
                                        <i class="mdi mdi-information-outline"></i>
                                        Enter cost per <strong>selected packaging unit</strong>. For donations, cost is ₦0.00.
                                    </small>
                                    <div id="manual-batch-cost-preview" class="mt-1" style="display:none; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:6px 10px; font-size:0.8rem;">
                                        <i class="mdi mdi-calculator text-success mr-1"></i>
                                        <span id="manual-batch-cost-preview-text"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="reference_type">Reason/Reference</label>
                                    <select name="reference_type" id="reference_type" class="form-control">
                                        <option value="manual_entry" {{ old('reference_type') == 'manual_entry' ? 'selected' : '' }}>Manual Entry</option>
                                        <option value="opening_stock" {{ old('reference_type') == 'opening_stock' ? 'selected' : '' }}>Opening Stock</option>
                                        <option value="donation" {{ old('reference_type') == 'donation' ? 'selected' : '' }}>Donation</option>
                                        <option value="transfer_in" {{ old('reference_type') == 'transfer_in' ? 'selected' : '' }}>Transfer In</option>
                                        <option value="other" {{ old('reference_type') == 'other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea name="notes" id="notes" rows="2" class="form-control" placeholder="Any additional notes about this batch...">{{ old('notes') }}</textarea>
                        </div>

                        {{-- Real-Time Double-Entry General Ledger Impact Preview --}}
                        <div class="row mt-3 mb-2">
                            <div class="col-12">
                                <div id="manual-journal-preview-card" style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:12px 16px; font-size:0.85rem;">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span style="font-weight:700; color:#334155; text-transform:uppercase; font-size:0.75rem; letter-spacing:0.4px;">
                                            <i class="mdi mdi-scale-balance mr-1 text-primary"></i>Real-time Ledger Impact (Double-Entry)
                                        </span>
                                        <span class="badge badge-success px-2 py-1" style="font-size:0.72rem;">Balanced (DR = CR)</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center py-1" style="border-bottom:1px dashed #e2e8f0;">
                                        <span class="text-muted"><i class="mdi mdi-arrow-up-bold-circle text-success mr-1"></i><strong>DEBIT:</strong> <span id="manual-preview-dr-acc">1310 - Inventory - Medical Supplies</span></span>
                                        <strong class="text-success" id="manual-preview-dr-amt">+₦0.00</strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center py-1">
                                        <span class="text-muted"><i class="mdi mdi-arrow-down-bold-circle text-danger mr-1"></i><strong>CREDIT:</strong> <span id="manual-preview-cr-acc">2110 - Accounts Payable (Select Supplier)</span></span>
                                        <strong class="text-danger" id="manual-preview-cr-amt">+₦0.00</strong>
                                    </div>
                                    <small class="text-muted d-block mt-2" id="manual-preview-explanation" style="font-size:0.75rem;">
                                        Increases inventory asset and creates accounts payable liability owed to selected supplier.
                                    </small>
                                </div>
                            </div>
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
                                <a href="javascript:history.back()" class="btn btn-secondary">
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
                    return {
                        q: params.term
                    };
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
            placeholder: '— Select Supplier / Donor —',
            allowClear: true,
            width: '100%'
        }).on('change', function() {
            if (typeof window.updateManualBatchGLPreview === 'function') {
                window.updateManualBatchGLPreview();
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

        // Load packaging options when product changes
        $('#product_id').on('change', function() {
            var productId = $(this).val();
            var $pkgSelect = $('#batch_packaging');
            $pkgSelect.html('<option value="" data-base="1">Base Unit</option>');
            $('#batch-base-equiv').hide();

            if (!productId) return;

            $.ajax({
                url: '{{ url('/products') }}/' + productId + '/packagings',
                method: 'GET',
                success: function(response) {
                    var baseUnit = response.base_unit_name || 'units';
                    $pkgSelect.html('<option value="" data-base="1">' + baseUnit + ' (base)</option>');
                    $('#batch-base-unit-name').text(baseUnit);

                    if (response.packagings && response.packagings.length > 0) {
                        var defaultFactor = 1;
                        response.packagings.forEach(function(pkg) {
                            var sel = '';
                            if (pkg.is_default_purchase) {
                                sel = ' selected';
                                defaultFactor = pkg.base_unit_qty;
                            }
                            $pkgSelect.append('<option value="' + pkg.id + '" data-base="' + pkg.base_unit_qty + '"' + sel + '>' + pkg.name + ' (' + parseFloat(pkg.base_unit_qty) + ' ' + baseUnit + ')</option>');
                        });
                        
                        $pkgSelect.data('prev-factor', defaultFactor);
                        
                        // Initial scale if default is selected
                        var costInput = $('#cost_price');
                        if ($('#manual_is_donation').is(':checked')) {
                            costInput.val('0.00');
                        } else {
                            var baseCost = parseFloat(costInput.val()) || 0;
                            if (baseCost > 0 && defaultFactor > 1) {
                                costInput.val(parseFloat((baseCost * defaultFactor).toFixed(4)));
                            }
                        }

                        updateBatchBaseEquiv();
                    }

                    if (response.allow_decimal_qty) {
                        $('#quantity').attr('step', 'any').attr('min', '0.01');
                    } else {
                        $('#quantity').attr('step', '1').attr('min', '1');
                    }
                }
            });
        });

        window.calculateManualBatchBaseQty = function() {
            var pkgQty = parseFloat($('#packaging_qty').val()) || 0;
            var base = parseFloat($('#batch_packaging').find(':selected').data('base')) || 1;
            var baseQty = Math.round(pkgQty * base);
            $('#quantity').val(baseQty);
            $('#batch-base-qty').text(baseQty);
            if (base > 1 && pkgQty > 0) {
                $('#batch-base-equiv').show();
            } else {
                $('#batch-base-equiv').hide();
            }
            if (typeof window.updateManualBatchGLPreview === 'function') {
                window.updateManualBatchGLPreview();
            }
        };

        window.updateBatchBaseEquiv = window.calculateManualBatchBaseQty;

        function handlePackagingChange() {
            var select = $('#batch_packaging');
            var prevFactor = parseFloat(select.data('prev-factor')) || 1;
            var newFactor = parseFloat(select.find(':selected').data('base')) || 1;
            var pkgText = select.find(':selected').text().trim() || 'packaging unit';
            var rawPkgName = pkgText.split('(')[0].trim();
            var baseUnitName = $('#batch-base-unit-name').text() || 'units';
            
            var labelStr = rawPkgName;
            if (newFactor > 1) {
                labelStr = rawPkgName + ' ~ ' + newFactor + ' ' + baseUnitName;
            } else if (rawPkgName === 'Base Unit' || rawPkgName === 'packaging unit') {
                labelStr = baseUnitName;
            }
            
            $('#manual-cost-price-label').html('Cost Price (per ' + labelStr + ') <span class="text-danger">*</span>');

            var costInput = $('#cost_price');
            var currentCost = parseFloat(costInput.val()) || 0;

            if (currentCost > 0) {
                var newCost = (currentCost / prevFactor) * newFactor;
                costInput.val(parseFloat(newCost.toFixed(4)));
            }

            select.data('prev-factor', newFactor);
            calculateManualBatchBaseQty();
        }

        $('#batch_packaging').on('change', function() {
            handlePackagingChange();
            updateManualBatchCostPreview();
            updateManualBatchGLPreview();
        });
        $('#packaging_qty').on('change input', function() {
            calculateManualBatchBaseQty();
            updateManualBatchGLPreview();
        });
        $('#cost_price, #store_id').on('input change', function() {
            updateManualBatchCostPreview();
            updateManualBatchGLPreview();
        });

        // Real-Time Double-Entry General Ledger Impact Preview
        window.updateManualBatchGLPreview = function() {
            var rawQty = parseFloat($('#packaging_qty').val() || $('#quantity').val()) || 0;
            var isDonation = $('#manual_is_donation').is(':checked') || $('#reference_type').val() === 'donation';
            var unitCost = isDonation ? 0 : (parseFloat($('#cost_price').val()) || 0);
            var totalVal = rawQty * unitCost;
            var formatted = '₦' + totalVal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});

            var acqType = $('input[name="acquisition_type"]:checked').val() || 'credit';
            var supText = $('#supplier_id option:selected').text().trim();
            if (supText.indexOf('Select') !== -1 || supText === '—') supText = '';

            // Detect store inventory account
            var storeText = ($('#store_id option:selected').text() || '').toLowerCase();
            var invAccountCode = '1310';
            var invAccountName = 'Inventory - Medical Supplies';
            if (storeText.indexOf('pharmacy') !== -1 || storeText.indexOf('drug') !== -1) {
                invAccountCode = '1300';
                invAccountName = 'Inventory - Pharmacy Stock';
            }

            if (isDonation) {
                $('#manual-preview-dr-acc').text(invAccountCode + ' - ' + invAccountName);
                $('#manual-preview-dr-amt').text('₦0.00');
                $('#manual-preview-cr-acc').text('No Financial Payable (Donation / Grant Stock)');
                $('#manual-preview-cr-amt').text('₦0.00');
                $('#manual-preview-explanation').text('Donation / free program stock logged at ₦0.00. Increases physical inventory count without creating a supplier liability or cash outflow.');
            } else if (acqType === 'credit') {
                $('#manual-preview-dr-acc').text(invAccountCode + ' - ' + invAccountName);
                $('#manual-preview-dr-amt').text(totalVal > 0 ? '+' + formatted : '₦0.00');
                var crLabel = '2110 - Accounts Payable' + (supText ? ' (' + supText + ')' : ' (Select Supplier)');
                $('#manual-preview-cr-acc').text(crLabel);
                $('#manual-preview-cr-amt').text(totalVal > 0 ? '+' + formatted : '₦0.00');
                $('#manual-preview-explanation').text('Creates a formal accounts payable liability owed to ' + (supText || 'the supplier') + ' in General Ledger Account 2110, increasing inventory asset in Account ' + invAccountCode + '.');
            } else if (acqType === 'cash') {
                $('#manual-preview-dr-acc').text(invAccountCode + ' - ' + invAccountName);
                $('#manual-preview-dr-amt').text(totalVal > 0 ? '+' + formatted : '₦0.00');
                $('#manual-preview-cr-acc').text('1030 - Petty Cash / Cash Till');
                $('#manual-preview-cr-amt').text(totalVal > 0 ? '-' + formatted : '₦0.00');
                $('#manual-preview-explanation').text('Records immediate payment from the cash till / petty cash fund (Account 1030), increasing inventory asset in Account ' + invAccountCode + ' with zero accounts payable liability.');
            } else if (acqType === 'opening_stock') {
                $('#manual-preview-dr-acc').text(invAccountCode + ' - ' + invAccountName);
                $('#manual-preview-dr-amt').text(totalVal > 0 ? '+' + formatted : '₦0.00');
                $('#manual-preview-cr-acc').text('3100 - Opening Balance Equity / Retained Earnings');
                $('#manual-preview-cr-amt').text(totalVal > 0 ? '+' + formatted : '₦0.00');
                $('#manual-preview-explanation').text('Balances against Opening Equity (Account 3100) for historical or go-live stock intake without creating a supplier payable or cash outflow.');
            }
        };

        // Reference type sync with donation
        $('#reference_type').on('change', function() {
            if ($(this).val() === 'donation') {
                if (!$('#manual_is_donation').is(':checked')) {
                    $('#manual_is_donation').prop('checked', true);
                    window.toggleManualDonation($('#manual_is_donation')[0]);
                }
            } else if ($('#manual_is_donation').is(':checked')) {
                $('#manual_is_donation').prop('checked', false);
                window.toggleManualDonation($('#manual_is_donation')[0]);
            }
            updateManualBatchGLPreview();
        });

        // Initialize donation state on load if checked or donation selected
        if ($('#manual_is_donation').is(':checked') || $('#reference_type').val() === 'donation') {
            $('#manual_is_donation').prop('checked', true);
            window.toggleManualDonation($('#manual_is_donation')[0]);
        }

        /**
         * Live preview of base-unit cost calculation for manual batch form.
         */
        window.updateManualBatchCostPreview = function() {
            var isDonation = $('#manual_is_donation').is(':checked') || $('#reference_type').val() === 'donation';
            var rawVal = $('#cost_price').val();
            var cost = parseFloat(rawVal) || 0;
            var factor = parseFloat($('#batch_packaging').find(':selected').data('base')) || 1;
            var pkgName = $('#batch_packaging').find(':selected').text().trim() || 'Base Unit';
            var $preview = $('#manual-batch-cost-preview');
            var $text = $('#manual-batch-cost-preview-text');

            if (isDonation || (rawVal !== '' && cost === 0)) {
                $text.html('<i class="mdi mdi-gift-outline text-success mr-1"></i><strong>' + (isDonation ? 'Donation Batch' : 'Zero-Cost Batch') + ':</strong> Cost price is fixed at <strong>₦0.00</strong>');
                $preview.show();
                return;
            }

            if (cost > 0 && factor > 1) {
                var baseUnitCost = (cost / factor).toFixed(4);
                $text.html(
                    '₦' + cost.toLocaleString() + ' per <strong>' + pkgName + '</strong> ÷ ' + factor + ' = ' +
                    '<strong>₦' + parseFloat(baseUnitCost).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:4}) + '</strong> per base unit (saved in DB)'
                );
                $preview.show();
            } else if (cost > 0) {
                $text.html('₦' + cost.toLocaleString(undefined, {minimumFractionDigits:2}) + ' per base unit — stored as-is');
                $preview.show();
            } else {
                $preview.hide();
            }
        };

        // Form submission: ensure base quantities and donation costs are synced
        $('form').on('submit', function() {
            var isDonation = $('#manual_is_donation').is(':checked') || $('#reference_type').val() === 'donation';
            if (isDonation) {
                $('#cost_price').val('0.00');
            }
            calculateManualBatchBaseQty();
        });

        // Initial GL preview call
        updateManualBatchGLPreview();
    });

    window.toggleManualDonation = function(checkbox) {
        var isChecked = $(checkbox).is(':checked');
        var $costInput = $('#cost_price');
        var $supplierLabel = $('#manual_supplier_label');
        var $supplierHint = $('#manual_supplier_hint');
        var $costLabel = $('#manual-cost-price-label');
        var $refType = $('#reference_type');
        var $notes = $('#notes');
        var $preview = $('#manual-batch-cost-preview');
        var $previewText = $('#manual-batch-cost-preview-text');

        if (isChecked) {
            $costInput.prop('disabled', false).prop('required', false).val('0.00');
            $costLabel.html('Cost Price <span class="badge badge-success ml-1"><i class="mdi mdi-gift-outline mr-1"></i>Donation (₦0.00)</span>');
            $supplierLabel.html('Donor / Supplier <span class="badge badge-success ml-1"><i class="mdi mdi-gift-outline mr-1"></i>Donor</span>');
            $supplierHint.html('<a href="{{ route("suppliers.create") }}" target="_blank">+ Add new donor / supplier</a>');
            if ($refType.val() !== 'donation') {
                $refType.val('donation');
            }
            if (!$notes.val().trim()) {
                $notes.val('Donation batch received');
            }
            $previewText.html('<i class="mdi mdi-gift-outline text-success mr-1"></i><strong>Donation Batch:</strong> Cost price set to <strong>₦0.00</strong>');
            $preview.show();
        } else {
            $costInput.prop('required', true);
            if ($costInput.val() === '0.00' || $costInput.val() === '0') {
                $costInput.val('');
            }
            var baseUnitName = $('#batch-base-unit-name').text() || 'packaging unit';
            $costLabel.html('Cost Price (per ' + baseUnitName + ') <span class="text-danger">*</span>');
            $supplierLabel.text('Supplier / Donor');
            $supplierHint.html('<a href="{{ route("suppliers.create") }}" target="_blank">+ Add new supplier</a>');
            if ($refType.val() === 'donation') {
                $refType.val('manual_entry');
            }
            if ($notes.val().trim() === 'Donation batch received') {
                $notes.val('');
            }
            $preview.hide();
            if (typeof window.updateManualBatchCostPreview === 'function') {
                window.updateManualBatchCostPreview();
            }
        }
        if (typeof window.updateManualBatchGLPreview === 'function') {
            window.updateManualBatchGLPreview();
        }
    };
</script>
@endsection