{{--
    Create Fixed Asset
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.6
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'Add Fixed Asset')
@section('page_name', 'Accounting')
@section('subpage_name', 'Add Fixed Asset')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Fixed Assets', 'url' => route('accounting.fixed-assets.index'), 'icon' => 'mdi-domain'],
        ['label' => 'Add New', 'url' => '#', 'icon' => 'mdi-plus']
    ]
])

<style>
.form-section {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 25px;
    margin-bottom: 20px;
}
.form-section h6 {
    font-weight: 600;
    color: #333;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f1f1f1;
}
.category-card {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 15px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
    height: 100%;
}
.category-card:hover {
    border-color: #667eea;
    background: #f8f9ff;
}
.category-card.selected {
    border-color: #667eea;
    background: linear-gradient(135deg, #667eea22 0%, #764ba222 100%);
}
.category-card .icon {
    font-size: 2rem;
    margin-bottom: 10px;
    color: #667eea;
}
.category-card .name {
    font-weight: 600;
}
.category-card .life {
    font-size: 0.8rem;
    color: #666;
}
.je-preview {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    border-left: 4px solid #667eea;
}
.cost-summary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 20px;
}
.cost-summary .amount {
    font-size: 2rem;
    font-weight: 700;
}
</style>

<div class="container-fluid">
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <form action="{{ route('accounting.fixed-assets.store') }}" method="POST" id="asset-form">
        @csrf

        <div class="row">
            <div class="col-lg-8">
                <!-- Category Selection -->
                <div class="form-section">
                    <h6><i class="mdi mdi-folder-star mr-2"></i>Asset Category</h6>
                    <div class="row">
                        @foreach($categories as $category)
                            <div class="col-md-4 mb-3">
                                <div class="category-card {{ $selectedCategory && $selectedCategory->id == $category->id ? 'selected' : '' }}"
                                     data-category-id="{{ $category->id }}"
                                     data-life="{{ $category->default_useful_life_years }}"
                                     data-method="{{ $category->default_depreciation_method }}"
                                     data-salvage="{{ $category->default_salvage_percentage }}"
                                     data-depreciable="{{ $category->is_depreciable ? 1 : 0 }}"
                                     data-asset-account="{{ $category->assetAccount?->name }}"
                                     data-asset-account-code="{{ $category->assetAccount?->code }}">
                                    <div class="icon">
                                        @php
                                            $icons = [
                                                'BLDG' => 'mdi-office-building',
                                                'FURN' => 'mdi-table-furniture',
                                                'COMP' => 'mdi-desktop-classic',
                                                'MED' => 'mdi-medical-bag',
                                                'VEH' => 'mdi-car',
                                                'default' => 'mdi-domain'
                                            ];
                                            $iconCode = substr($category->code ?? '', 0, 4);
                                        @endphp
                                        <i class="mdi {{ $icons[$iconCode] ?? $icons['default'] }}"></i>
                                    </div>
                                    <div class="name">{{ $category->name }}</div>
                                    <div class="life">{{ $category->default_useful_life_years }} years useful life</div>
                                    @if(!$category->is_depreciable)
                                        <span class="badge badge-secondary mt-1">Non-Depreciable</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <input type="hidden" name="category_id" id="category_id" value="{{ $selectedCategory?->id ?? old('category_id') }}" required>
                    @error('category_id')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Basic Information -->
                <div class="form-section">
                    <h6><i class="mdi mdi-information-outline mr-2"></i>Basic Information</h6>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Asset Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name') }}" required placeholder="e.g., Dell Latitude 5520 Laptop">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Serial Number</label>
                                <input type="text" name="serial_number" class="form-control @error('serial_number') is-invalid @enderror"
                                       value="{{ old('serial_number') }}" placeholder="e.g., ABC123XYZ">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Model Number</label>
                                <input type="text" name="model_number" class="form-control"
                                       value="{{ old('model_number') }}" placeholder="e.g., Model 5520">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Manufacturer</label>
                                <input type="text" name="manufacturer" class="form-control"
                                       value="{{ old('manufacturer') }}" placeholder="e.g., Dell Inc.">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Supplier</label>
                                <select name="supplier_id" class="form-control select2">
                                    <option value="">Select Supplier</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->company_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Additional details about the asset">{{ old('description') }}</textarea>
                    </div>
                </div>

                <!-- Cost Information -->
                <div class="form-section">
                    <h6><i class="mdi mdi-currency-ngn mr-2"></i>Cost Information</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Acquisition Cost <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₦</span>
                                    </div>
                                    <input type="number" name="acquisition_cost" id="acquisition_cost"
                                           class="form-control @error('acquisition_cost') is-invalid @enderror"
                                           value="{{ old('acquisition_cost') }}" step="0.01" min="0.01" required>
                                </div>
                                @error('acquisition_cost')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Additional Costs</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₦</span>
                                    </div>
                                    <input type="number" name="additional_costs" id="additional_costs"
                                           class="form-control" value="{{ old('additional_costs', 0) }}" step="0.01" min="0">
                                </div>
                                <small class="text-muted">Installation, shipping, etc.</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Salvage Value</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₦</span>
                                    </div>
                                    <input type="number" name="salvage_value" id="salvage_value"
                                           class="form-control" value="{{ old('salvage_value') }}" step="0.01" min="0">
                                </div>
                                <small class="text-muted">Leave blank to auto-calculate</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Invoice Number</label>
                                <input type="text" name="invoice_number" class="form-control"
                                       value="{{ old('invoice_number') }}" placeholder="e.g., INV-2024-001">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Depreciation Settings -->
                <div class="form-section">
                    <h6><i class="mdi mdi-chart-bell-curve mr-2"></i>Depreciation Settings</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Depreciation Method <span class="text-danger">*</span></label>
                                <select name="depreciation_method" id="depreciation_method" class="form-control" required>
                                    @foreach($depreciationMethods as $key => $label)
                                        <option value="{{ $key }}" {{ old('depreciation_method', $selectedCategory?->default_depreciation_method) == $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Useful Life (Years) <span class="text-danger">*</span></label>
                                <input type="number" name="useful_life_years" id="useful_life_years"
                                       class="form-control @error('useful_life_years') is-invalid @enderror"
                                       value="{{ old('useful_life_years', $selectedCategory?->default_useful_life_years ?? 5) }}"
                                       min="1" max="100" required>
                                @error('useful_life_years')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Monthly Depreciation</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₦</span>
                                    </div>
                                    <input type="text" id="monthly_depreciation_display" class="form-control" readonly disabled>
                                </div>
                                <small class="text-muted">Auto-calculated</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dates & Location -->
                <div class="form-section">
                    <h6><i class="mdi mdi-calendar-clock mr-2"></i>Dates & Location</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Acquisition Date <span class="text-danger">*</span></label>
                                <input type="date" name="acquisition_date" class="form-control @error('acquisition_date') is-invalid @enderror"
                                       value="{{ old('acquisition_date', now()->format('Y-m-d')) }}" required>
                                @error('acquisition_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>In-Service Date</label>
                                <input type="date" name="in_service_date" class="form-control"
                                       value="{{ old('in_service_date', now()->format('Y-m-d')) }}">
                                <small class="text-muted">When depreciation starts</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Location</label>
                                <input type="text" name="location" class="form-control"
                                       value="{{ old('location') }}" placeholder="e.g., Building A, Room 101">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Department</label>
                                <select name="department_id" class="form-control select2">
                                    <option value="">Select Department</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>
                                            {{ $dept->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Custodian</label>
                                <select name="custodian_user_id" class="form-control select2">
                                    <option value="">Select Custodian</option>
                                    @foreach($custodians as $user)
                                        <option value="{{ $user->id }}" {{ old('custodian_user_id') == $user->id ? 'selected' : '' }}>
                                            {{ ucwords($user->surname . ' ' . $user->firstname . ($user->othername ? ' ' . $user->othername : '')) }} ({{ $user->email }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Warranty & Insurance -->
                <div class="form-section">
                    <h6><i class="mdi mdi-shield-check mr-2"></i>Warranty & Insurance</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Warranty Expiry</label>
                                <input type="date" name="warranty_expiry_date" class="form-control"
                                       value="{{ old('warranty_expiry_date') }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Warranty Provider</label>
                                <input type="text" name="warranty_provider" class="form-control"
                                       value="{{ old('warranty_provider') }}" placeholder="e.g., Dell Support">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Insurance Policy Number</label>
                                <input type="text" name="insurance_policy_number" class="form-control"
                                       value="{{ old('insurance_policy_number') }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Insurance Expiry</label>
                                <input type="date" name="insurance_expiry_date" class="form-control"
                                       value="{{ old('insurance_expiry_date') }}">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="form-section">
                    <h6><i class="mdi mdi-note-text mr-2"></i>Notes</h6>
                    <div class="form-group mb-0">
                        <textarea name="notes" class="form-control" rows="3" placeholder="Any additional notes about this asset">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Cost Summary -->
                <div class="cost-summary mb-4">
                    <div class="text-center">
                        <div class="opacity-75 mb-1">Total Cost</div>
                        <div class="amount" id="total-cost-display">₦0.00</div>
                        <div class="opacity-75 mt-2">Depreciable Amount</div>
                        <div id="depreciable-display">₦0.00</div>
                    </div>
                </div>

                <!-- JE Preview -->
                <div class="form-section">
                    <h6><i class="mdi mdi-book-open mr-2"></i>Acquisition Journal Entry</h6>
                    <div class="alert alert-light border mb-3">
                        <div class="text-muted small mb-2">Auto-posted on save</div>
                        <div class="mb-2 d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge badge-primary px-2 py-1">DEBIT</span>
                                <span id="je-asset-account-name" class="ml-2 font-weight-bold">Fixed Asset Account</span>
                                <br><small class="text-muted ml-5" id="je-asset-account-code"></small>
                            </div>
                            <span class="font-weight-bold text-primary" id="je-debit-display">₦0.00</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge badge-success px-2 py-1">CREDIT</span>
                                <span id="je-bank-account-name" class="ml-2 font-weight-bold">Bank/Cash Account</span>
                                <br><small class="text-muted ml-5" id="je-bank-account-code"></small>
                            </div>
                            <span class="font-weight-bold text-success" id="je-credit-display">₦0.00</span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between small">
                            <span class="text-muted">Total</span>
                            <span class="font-weight-bold" id="je-total-display">₦0.00</span>
                        </div>
                    </div>
                    <small class="text-muted">
                        <i class="mdi mdi-information-outline mr-1"></i>
                        This journal entry will be automatically posted when you save the asset.
                    </small>
                </div>

                <!-- Depreciation Preview -->
                <div class="form-section">
                    <h6><i class="mdi mdi-chart-timeline mr-2"></i>Depreciation Preview</h6>
                    <table class="table table-sm table-borderless" id="depreciation-preview">
                        <tr>
                            <td>Total Cost</td>
                            <td class="text-right" id="dp-total-cost">₦0.00</td>
                        </tr>
                        <tr>
                            <td>Salvage Value</td>
                            <td class="text-right" id="dp-salvage">₦0.00</td>
                        </tr>
                        <tr>
                            <td>Depreciable Amount</td>
                            <td class="text-right" id="dp-depreciable">₦0.00</td>
                        </tr>
                        <tr>
                            <td>Useful Life</td>
                            <td class="text-right" id="dp-life">0 years</td>
                        </tr>
                        <tr class="border-top">
                            <td><strong>Monthly Depreciation</strong></td>
                            <td class="text-right"><strong id="dp-monthly">₦0.00</strong></td>
                        </tr>
                        <tr>
                            <td><strong>Annual Depreciation</strong></td>
                            <td class="text-right"><strong id="dp-annual">₦0.00</strong></td>
                        </tr>
                    </table>
                </div>

                <!-- Actions -->
                <div class="form-section">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="mdi mdi-content-save mr-1"></i> Save Fixed Asset
                        </button>
                        <a href="{{ route('accounting.fixed-assets.index') }}" class="btn btn-outline-secondary btn-block">
                            <i class="mdi mdi-close mr-1"></i> Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        placeholder: 'Select an option',
        allowClear: true,
        width: '100%'
    });

    // Category selection
    $('.category-card').on('click', function() {
        $('.category-card').removeClass('selected');
        $(this).addClass('selected');

        var categoryId = $(this).data('category-id');
        var life = $(this).data('life');
        var method = $(this).data('method');
        var salvage = $(this).data('salvage');
        var assetAccount = $(this).data('asset-account');
        var assetAccountCode = $(this).data('asset-account-code');

        $('#category_id').val(categoryId);
        $('#useful_life_years').val(life);
        $('#depreciation_method').val(method);

        // Update JE preview with actual account names
        if (assetAccount) {
            $('#je-asset-account-name').text(assetAccount);
            $('#je-asset-account-code').text(assetAccountCode);
        }

        // Auto-calculate salvage if percentage provided
        if (salvage > 0) {
            updateCostCalculations();
        }
    });

    // Bank account selection - update JE preview
    $('#bank_account_id').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var accountName = selectedOption.text();
        if (accountName && accountName !== 'Select Payment Account') {
            $('#je-bank-account-name').text(accountName.split(' - ')[1] || 'Bank Account');
            $('#je-bank-account-code').text(accountName.split(' - ')[0] || '');
        }
    });

    // Cost calculation on input change
    $('#acquisition_cost, #additional_costs, #salvage_value, #useful_life_years').on('input change', function() {
        updateCostCalculations();
    });

    function updateCostCalculations() {
        var acquisitionCost = parseFloat($('#acquisition_cost').val()) || 0;
        var additionalCosts = parseFloat($('#additional_costs').val()) || 0;
        var salvageValue = parseFloat($('#salvage_value').val()) || 0;
        var usefulLife = parseInt($('#useful_life_years').val()) || 1;

        var totalCost = acquisitionCost + additionalCosts;
        var depreciableAmount = totalCost - salvageValue;
        var monthlyDepreciation = depreciableAmount / (usefulLife * 12);
        var annualDepreciation = monthlyDepreciation * 12;

        // Format currency
        var formatCurrency = function(val) {
            return '₦' + val.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        };

        // Update displays
        $('#total-cost-display').text(formatCurrency(totalCost));
        $('#depreciable-display').text(formatCurrency(depreciableAmount));
        $('#monthly_depreciation_display').val(monthlyDepreciation.toFixed(2));

        // JE Preview
        $('#je-debit-display').text(formatCurrency(totalCost));
        $('#je-credit-display').text(formatCurrency(totalCost));
        $('#je-total-display').text(formatCurrency(totalCost));

        // Depreciation Preview
        $('#dp-total-cost').text(formatCurrency(totalCost));
        $('#dp-salvage').text(formatCurrency(salvageValue));
        $('#dp-depreciable').text(formatCurrency(depreciableAmount));
        $('#dp-life').text(usefulLife + ' years');
        $('#dp-monthly').text(formatCurrency(monthlyDepreciation));
        $('#dp-annual').text(formatCurrency(annualDepreciation));
    }

    // Validate form before submit
    $('#asset-form').on('submit', function(e) {
        if (!$('#category_id').val()) {
            e.preventDefault();
            toastr.error('Please select an asset category');
            return false;
        }
    });

    // Initial calculation
    updateCostCalculations();
});
</script>
@endpush
