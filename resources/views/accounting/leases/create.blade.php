@extends('admin.layouts.app')
@section('title', 'Create Lease')
@section('page_name', 'Accounting')
@section('subpage_name', 'New Lease Agreement')

@push('styles')
<style>
    /* Select2 Fixes for consistency */
    .select2-container--bootstrap4 .select2-selection--single {
        height: calc(1.5em + 0.75rem + 2px) !important;
        padding: 0.375rem 0.75rem !important;
        border: 1px solid #ced4da !important;
        border-radius: 0.25rem !important;
    }
    .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
        line-height: 1.5 !important;
        padding-left: 0 !important;
        color: #495057 !important;
    }
    .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
        height: calc(1.5em + 0.75rem) !important;
    }
    .select2-container--bootstrap4 .select2-selection--single .select2-selection__placeholder {
        color: #6c757d !important;
    }
    .select2-container {
        width: 100% !important;
    }
    .select2-dropdown {
        border-color: #ced4da !important;
    }
    .select2-container--bootstrap4 .select2-results__option--highlighted[aria-selected] {
        background-color: #0d6efd !important;
    }
    /* Required field indicator */
    .required-field::after {
        content: " *";
        color: #dc3545;
    }
    /* Section headers */
    .section-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1rem 1.25rem;
        border-radius: 0.5rem 0.5rem 0 0;
        margin: -1px -1px 0 -1px;
    }
    .section-header.primary { background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); }
    .section-header.info { background: linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%); }
    .section-header.warning { background: linear-gradient(135deg, #ffc107 0%, #d39e00 100%); color: #212529; }
    .section-header.secondary { background: linear-gradient(135deg, #6c757d 0%, #495057 100%); }
    .section-header.success { background: linear-gradient(135deg, #198754 0%, #146c43 100%); }
    /* Card styling */
    .card-modern {
        border: none;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        border-radius: 0.5rem;
        overflow: hidden;
    }
    /* Calculator panel */
    .calculator-panel {
        background: linear-gradient(180deg, #f8f9fa 0%, #fff 100%);
    }
    .calculator-panel .value-display {
        font-size: 1.25rem;
        font-weight: 600;
        color: #212529;
    }
    .calculator-panel .value-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6c757d;
    }
    /* JE Preview table */
    .je-preview-table th {
        background-color: #f1f3f5;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    /* Form helper text */
    .form-text {
        font-size: 0.75rem;
    }
    /* Sticky sidebar */
    .sticky-sidebar {
        position: sticky;
        top: 80px;
    }
    /* Account mapping info */
    .account-info-badge {
        font-size: 0.65rem;
        padding: 0.15rem 0.4rem;
        vertical-align: middle;
    }
</style>
@endpush

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Leases', 'url' => route('accounting.leases.index'), 'icon' => 'mdi-file-document-edit'],
    ['label' => 'New Lease', 'url' => '#', 'icon' => 'mdi-plus']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        {{-- Alert Boxes --}}
        <div id="alert-container">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-check-circle mr-2"></i>{{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-alert-circle mr-2"></i>{{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
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
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            @endif
        </div>

        {{-- Quick Guide --}}
        <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
            <div class="d-flex align-items-start">
                <i class="mdi mdi-information-outline mdi-24px mr-3 mt-1"></i>
                <div>
                    <h6 class="alert-heading mb-1">Creating a New Lease (IFRS 16)</h6>
                    <p class="mb-2 small">Complete the form below to register a new lease agreement. The system will automatically:</p>
                    <ul class="mb-0 small pl-3">
                        <li>Calculate the <strong>Present Value</strong> of lease payments (for finance/operating leases)</li>
                        <li>Generate the <strong>initial recognition Journal Entry</strong> on activation</li>
                        <li>Create the full <strong>payment schedule</strong> with interest/principal split</li>
                    </ul>
                </div>
            </div>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>

        <form action="{{ route('accounting.leases.store') }}" method="POST" id="lease-form">
            @csrf
            <div class="row">
                {{-- Main Form Column --}}
                <div class="col-lg-8">
                    {{-- SECTION 1: Lease Classification --}}
                    <div class="card-modern mb-4">
                        <div class="section-header primary">
                            <h5 class="mb-0"><i class="mdi mdi-numeric-1-circle mr-2"></i>Lease Classification</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="lease_type" class="required-field font-weight-bold">Lease Type</label>
                                        <select name="lease_type" id="lease_type" class="form-control" required>
                                            <option value="">-- Select Lease Type --</option>
                                            <option value="finance" {{ old('lease_type') == 'finance' ? 'selected' : '' }}>Finance Lease (Full IFRS 16)</option>
                                            <option value="operating" {{ old('lease_type') == 'operating' ? 'selected' : '' }}>Operating Lease (Full IFRS 16)</option>
                                            <option value="short_term" {{ old('lease_type') == 'short_term' ? 'selected' : '' }}>Short-Term (≤12 months) - Exempt</option>
                                            <option value="low_value" {{ old('lease_type') == 'low_value' ? 'selected' : '' }}>Low-Value Asset - Exempt</option>
                                        </select>
                                        <small class="form-text text-muted">Determines accounting treatment under IFRS 16</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="status">Initial Status</label>
                                        <select name="status" id="status" class="form-control">
                                            <option value="draft" {{ old('status', 'draft') == 'draft' ? 'selected' : '' }}>Draft (No JE Created)</option>
                                            <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active (Create JE Immediately)</option>
                                        </select>
                                        <small class="form-text text-muted">Draft leases can be activated later</small>
                                    </div>
                                </div>
                            </div>

                            {{-- IFRS 16 Treatment Alert --}}
                            <div id="ifrs16-alert" class="alert d-none mb-0">
                                <h6 class="alert-heading mb-2"><i class="mdi mdi-book-open-variant mr-1"></i>IFRS 16 Treatment</h6>
                                <div id="ifrs16-content"></div>
                            </div>
                        </div>
                    </div>

                    {{-- SECTION 2: Asset Details --}}
                    <div class="card-modern mb-4">
                        <div class="section-header info">
                            <h5 class="mb-0"><i class="mdi mdi-numeric-2-circle mr-2"></i>Asset Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="leased_item" class="required-field font-weight-bold">Leased Asset Name</label>
                                        <input type="text" name="leased_item" id="leased_item" class="form-control"
                                               value="{{ old('leased_item') }}"
                                               placeholder="e.g., Office Building - Block A, Medical Equipment MRI Scanner" required>
                                        <small class="form-text text-muted">Descriptive name for the leased asset</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="department_id">Department</label>
                                        <select name="department_id" id="department_id" class="form-control select2-basic">
                                            <option value="">-- Optional --</option>
                                            @foreach($departments as $dept)
                                                <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea name="description" id="description" class="form-control" rows="2"
                                          placeholder="Additional details about the leased asset (optional)">{{ old('description') }}</textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-0">
                                        <label for="asset_location">Asset Location</label>
                                        <input type="text" name="asset_location" id="asset_location" class="form-control"
                                               value="{{ old('asset_location') }}"
                                               placeholder="Physical location of the asset">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- SECTION 3: Lessor Information --}}
                    <div class="card-modern mb-4">
                        <div class="section-header" style="background: linear-gradient(135deg, #6f42c1 0%, #563d7c 100%);">
                            <h5 class="mb-0"><i class="mdi mdi-numeric-3-circle mr-2"></i>Lessor (Landlord/Supplier)</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="lessor_id">Select from Suppliers</label>
                                        <select name="lessor_id" id="lessor_id" class="form-control select2-supplier">
                                            <option value="">-- Select Existing Supplier --</option>
                                            @foreach($suppliers as $supplier)
                                                <option value="{{ $supplier->id }}"
                                                        data-name="{{ $supplier->company_name }}"
                                                        {{ old('lessor_id') == $supplier->id ? 'selected' : '' }}>
                                                    {{ $supplier->company_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="form-text text-muted">Or enter manually below</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="lessor_name">Lessor Name</label>
                                        <input type="text" name="lessor_name" id="lessor_name" class="form-control"
                                               value="{{ old('lessor_name') }}"
                                               placeholder="Enter lessor name manually">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-0">
                                        <label for="lessor_contact">Contact Information</label>
                                        <input type="text" name="lessor_contact" id="lessor_contact" class="form-control"
                                               value="{{ old('lessor_contact') }}"
                                               placeholder="Phone number or email">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- SECTION 4: Payment Terms --}}
                    <div class="card-modern mb-4">
                        <div class="section-header success">
                            <h5 class="mb-0"><i class="mdi mdi-numeric-4-circle mr-2"></i>Payment Terms</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="commencement_date" class="required-field font-weight-bold">Commencement Date</label>
                                        <input type="date" name="commencement_date" id="commencement_date"
                                               class="form-control" value="{{ old('commencement_date') }}" required>
                                        <small class="form-text text-muted">When lease starts</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="end_date" class="required-field font-weight-bold">End Date</label>
                                        <input type="date" name="end_date" id="end_date"
                                               class="form-control" value="{{ old('end_date') }}" required>
                                        <small class="form-text text-muted">When lease ends</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Lease Term</label>
                                        <div class="form-control-plaintext">
                                            <span id="lease-term-display" class="badge badge-info badge-pill px-3 py-2">-- months</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="monthly_payment" class="required-field font-weight-bold">Monthly Payment</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="number" name="monthly_payment" id="monthly_payment"
                                                   class="form-control" step="0.01" min="0.01"
                                                   value="{{ old('monthly_payment') }}" required
                                                   placeholder="0.00">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="annual_rent_increase_rate">Annual Escalation</label>
                                        <div class="input-group">
                                            <input type="number" name="annual_rent_increase_rate" id="annual_rent_increase_rate"
                                                   class="form-control" step="0.01" min="0" max="100"
                                                   value="{{ old('annual_rent_increase_rate', 0) }}"
                                                   placeholder="0">
                                            <div class="input-group-append">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">Annual rent increase rate</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="incremental_borrowing_rate" class="required-field font-weight-bold">Discount Rate (IBR)</label>
                                        <div class="input-group">
                                            <input type="number" name="incremental_borrowing_rate" id="incremental_borrowing_rate"
                                                   class="form-control" step="0.01" min="0.01" max="100"
                                                   value="{{ old('incremental_borrowing_rate', 15) }}" required
                                                   placeholder="15">
                                            <div class="input-group-append">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">Incremental borrowing rate for PV calculation</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-0">
                                        <label for="initial_direct_costs">Initial Direct Costs</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="number" name="initial_direct_costs" id="initial_direct_costs"
                                                   class="form-control" step="0.01" min="0"
                                                   value="{{ old('initial_direct_costs', 0) }}"
                                                   placeholder="0.00">
                                        </div>
                                        <small class="form-text text-muted">Costs to negotiate/obtain lease (added to ROU asset)</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-0">
                                        <label for="lease_incentives_received">Lease Incentives Received</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="number" name="lease_incentives_received" id="lease_incentives_received"
                                                   class="form-control" step="0.01" min="0"
                                                   value="{{ old('lease_incentives_received', 0) }}"
                                                   placeholder="0.00">
                                        </div>
                                        <small class="form-text text-muted">Incentives from lessor (deducted from ROU asset)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- SECTION 5: Lease Options (Collapsible) --}}
                    <div class="card-modern mb-4">
                        <div class="section-header warning" data-toggle="collapse" data-target="#optionsCollapse" style="cursor: pointer;">
                            <h5 class="mb-0 d-flex justify-content-between align-items-center">
                                <span><i class="mdi mdi-numeric-5-circle mr-2"></i>Lease Options (Optional)</span>
                                <i class="mdi mdi-chevron-down"></i>
                            </h5>
                        </div>
                        <div id="optionsCollapse" class="collapse">
                            <div class="card-body">
                                {{-- Purchase Option --}}
                                <div class="custom-control custom-checkbox mb-3">
                                    <input type="checkbox" class="custom-control-input" name="has_purchase_option"
                                           id="has_purchase_option" value="1" {{ old('has_purchase_option') ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold" for="has_purchase_option">
                                        Has Purchase Option
                                    </label>
                                </div>
                                <div id="purchase-fields" class="ml-4 pl-2 border-left border-primary {{ old('has_purchase_option') ? '' : 'd-none' }}">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="purchase_option_amount">Purchase Option Amount</label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text">₦</span>
                                                    </div>
                                                    <input type="number" name="purchase_option_amount" id="purchase_option_amount"
                                                           class="form-control" step="0.01" min="0"
                                                           value="{{ old('purchase_option_amount') }}">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <div class="custom-control custom-checkbox mt-4">
                                                    <input type="checkbox" class="custom-control-input"
                                                           name="purchase_option_reasonably_certain"
                                                           id="purchase_option_reasonably_certain" value="1"
                                                           {{ old('purchase_option_reasonably_certain') ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="purchase_option_reasonably_certain">
                                                        Reasonably certain to exercise
                                                    </label>
                                                </div>
                                                <small class="form-text text-muted">If checked, included in PV calculation</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <hr>

                                {{-- Termination Option --}}
                                <div class="custom-control custom-checkbox mb-3">
                                    <input type="checkbox" class="custom-control-input" name="has_termination_option"
                                           id="has_termination_option" value="1" {{ old('has_termination_option') ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold" for="has_termination_option">
                                        Has Early Termination Option
                                    </label>
                                </div>
                                <div id="termination-fields" class="ml-4 pl-2 border-left border-warning {{ old('has_termination_option') ? '' : 'd-none' }}">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="earliest_termination_date">Earliest Termination Date</label>
                                                <input type="date" name="earliest_termination_date" id="earliest_termination_date"
                                                       class="form-control" value="{{ old('earliest_termination_date') }}">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="termination_penalty">Termination Penalty</label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text">₦</span>
                                                    </div>
                                                    <input type="number" name="termination_penalty" id="termination_penalty"
                                                           class="form-control" step="0.01" min="0"
                                                           value="{{ old('termination_penalty') }}">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <hr>

                                {{-- Residual Value --}}
                                <div class="form-group mb-0">
                                    <label for="residual_value_guarantee">Residual Value Guarantee</label>
                                    <div class="input-group" style="max-width: 300px;">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">₦</span>
                                        </div>
                                        <input type="number" name="residual_value_guarantee" id="residual_value_guarantee"
                                               class="form-control" step="0.01" min="0"
                                               value="{{ old('residual_value_guarantee', 0) }}">
                                    </div>
                                    <small class="form-text text-muted">Amount guaranteed at end of lease (included in PV)</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- SECTION 6: GL Account Mapping --}}
                    <div class="card-modern mb-4">
                        <div class="section-header secondary">
                            <h5 class="mb-0"><i class="mdi mdi-numeric-6-circle mr-2"></i>GL Account Mapping</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-light border mb-4">
                                <i class="mdi mdi-lightbulb-on-outline mr-1 text-warning"></i>
                                <strong>Default accounts are pre-selected based on IFRS 16 requirements.</strong>
                                Change only if you need specific account mapping for this lease.
                            </div>

                            @php
                                // Find default account IDs
                                $defaultRouAsset = $rouAssetAccounts->firstWhere('code', '1460');
                                $defaultLiability = $liabilityAccounts->firstWhere('code', '2310');
                                $defaultDepreciation = $expenseAccounts->firstWhere('code', '6260');
                                $defaultInterest = $expenseAccounts->firstWhere('code', '6300');
                            @endphp

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="rou_asset_account_id" class="required-field">
                                            ROU Asset Account
                                            <span class="badge badge-info account-info-badge">Asset 1xxx</span>
                                        </label>
                                        <select name="rou_asset_account_id" id="rou_asset_account_id" class="form-control select2-account" required>
                                            <option value="">-- Select Account --</option>
                                            @foreach($rouAssetAccounts as $account)
                                                <option value="{{ $account->id }}"
                                                    {{ old('rou_asset_account_id', $defaultRouAsset->id ?? '') == $account->id ? 'selected' : '' }}>
                                                    {{ $account->code }} - {{ $account->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="form-text text-muted">Right-of-Use Asset (default: 1460 - Other Fixed Assets)</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="lease_liability_account_id" class="required-field">
                                            Lease Liability Account
                                            <span class="badge badge-warning account-info-badge text-dark">Liability 2xxx</span>
                                        </label>
                                        <select name="lease_liability_account_id" id="lease_liability_account_id" class="form-control select2-account" required>
                                            <option value="">-- Select Account --</option>
                                            @foreach($liabilityAccounts as $account)
                                                <option value="{{ $account->id }}"
                                                    {{ old('lease_liability_account_id', $defaultLiability->id ?? '') == $account->id ? 'selected' : '' }}>
                                                    {{ $account->code }} - {{ $account->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="form-text text-muted">Lease Obligation (default: 2310 - Lease Obligations)</small>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-md-0">
                                        <label for="depreciation_account_id" class="required-field">
                                            Depreciation Expense
                                            <span class="badge badge-danger account-info-badge">Expense 5xxx/6xxx</span>
                                        </label>
                                        <select name="depreciation_account_id" id="depreciation_account_id" class="form-control select2-account" required>
                                            <option value="">-- Select Account --</option>
                                            @foreach($expenseAccounts as $account)
                                                <option value="{{ $account->id }}"
                                                    {{ old('depreciation_account_id', $defaultDepreciation->id ?? '') == $account->id ? 'selected' : '' }}>
                                                    {{ $account->code }} - {{ $account->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="form-text text-muted">ROU Depreciation (default: 6260 - Depreciation Expense)</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-0">
                                        <label for="interest_account_id" class="required-field">
                                            Interest Expense
                                            <span class="badge badge-danger account-info-badge">Expense 5xxx/6xxx</span>
                                        </label>
                                        <select name="interest_account_id" id="interest_account_id" class="form-control select2-account" required>
                                            <option value="">-- Select Account --</option>
                                            @foreach($expenseAccounts as $account)
                                                <option value="{{ $account->id }}"
                                                    {{ old('interest_account_id', $defaultInterest->id ?? '') == $account->id ? 'selected' : '' }}>
                                                    {{ $account->code }} - {{ $account->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="form-text text-muted">Interest on Liability (default: 6300 - Interest Expense)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Notes Section --}}
                    <div class="card-modern mb-4">
                        <div class="card-body">
                            <div class="form-group mb-0">
                                <label for="notes"><i class="mdi mdi-note-text mr-1"></i>Additional Notes</label>
                                <textarea name="notes" id="notes" class="form-control" rows="2"
                                          placeholder="Any additional notes about this lease agreement">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Sidebar Calculator --}}
                <div class="col-lg-4">
                    <div class="card-modern sticky-sidebar calculator-panel">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="mdi mdi-calculator-variant mr-2"></i>IFRS 16 Calculator</h5>
                        </div>
                        <div class="card-body">
                            {{-- Lease Summary --}}
                            <div class="mb-3">
                                <span class="value-label">Lease Term</span>
                                <div class="value-display text-primary" id="calc-term">-- months</div>
                            </div>
                            <div class="mb-3">
                                <span class="value-label">Total Lease Payments</span>
                                <div class="value-display" id="calc-total">₦0.00</div>
                            </div>

                            <hr>

                            {{-- IFRS 16 Values --}}
                            <div class="mb-3">
                                <span class="value-label">Present Value (Lease Liability)</span>
                                <div class="value-display text-success" id="calc-pv">₦0.00</div>
                            </div>
                            <div class="mb-3">
                                <span class="value-label">Initial ROU Asset Value</span>
                                <div class="value-display text-info" id="calc-rou">₦0.00</div>
                            </div>

                            <hr>

                            {{-- Monthly Amounts --}}
                            <div class="row">
                                <div class="col-6">
                                    <span class="value-label">Monthly Depreciation</span>
                                    <div class="font-weight-bold" id="calc-depr">₦0.00</div>
                                </div>
                                <div class="col-6">
                                    <span class="value-label">Total Interest</span>
                                    <div class="font-weight-bold" id="calc-interest">₦0.00</div>
                                </div>
                            </div>

                            <hr>

                            {{-- JE Preview --}}
                            <div id="je-preview">
                                <h6 class="value-label mb-2">
                                    <i class="mdi mdi-book-open-page-variant mr-1"></i>JOURNAL ENTRY PREVIEW
                                </h6>

                                {{-- Standard JE (Finance/Operating) --}}
                                <div id="je-standard" class="d-none">
                                    <p class="small text-muted mb-2">Initial Recognition (on Activation):</p>
                                    <table class="table table-sm table-bordered je-preview-table mb-2">
                                        <thead>
                                            <tr>
                                                <th>Account</th>
                                                <th class="text-right" style="width: 90px;">Debit</th>
                                                <th class="text-right" style="width: 90px;">Credit</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><small class="text-info">ROU Asset</small></td>
                                                <td class="text-right font-weight-bold" id="je-rou-dr">₦0</td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <td><small class="text-warning">Lease Liability</small></td>
                                                <td></td>
                                                <td class="text-right font-weight-bold" id="je-liability-cr">₦0</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                {{-- Exempt JE (Short-term/Low-value) --}}
                                <div id="je-exempt" class="d-none">
                                    <div class="alert alert-success py-2 small mb-2">
                                        <i class="mdi mdi-check-circle mr-1"></i>
                                        <strong>IFRS 16 Exempt</strong> - No initial recognition
                                    </div>
                                    <p class="small text-muted mb-2">Each Monthly Payment:</p>
                                    <table class="table table-sm table-bordered je-preview-table mb-2">
                                        <thead>
                                            <tr>
                                                <th>Account</th>
                                                <th class="text-right" style="width: 90px;">Debit</th>
                                                <th class="text-right" style="width: 90px;">Credit</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><small class="text-danger">Rent Expense</small></td>
                                                <td class="text-right font-weight-bold" id="je-rent-dr">₦0</td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <td><small class="text-secondary">Bank/Cash</small></td>
                                                <td></td>
                                                <td class="text-right font-weight-bold" id="je-cash-cr">₦0</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div id="je-placeholder" class="text-center text-muted py-3">
                                    <i class="mdi mdi-information-outline mdi-24px"></i>
                                    <p class="small mb-0">Select lease type to see JE preview</p>
                                </div>
                            </div>

                            <hr>

                            {{-- Action Buttons --}}
                            <button type="submit" class="btn btn-success btn-lg btn-block mb-2">
                                <i class="mdi mdi-content-save mr-1"></i> Create Lease
                            </button>
                            <a href="{{ route('accounting.leases.index') }}" class="btn btn-outline-secondary btn-block">
                                <i class="mdi mdi-arrow-left mr-1"></i> Back to Leases
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Initialize Select2 with consistent styling
    function initSelect2() {
        $('.select2-basic').select2({
            theme: 'bootstrap4',
            allowClear: true,
            placeholder: '-- Select --',
            width: '100%'
        });

        $('.select2-supplier').select2({
            theme: 'bootstrap4',
            allowClear: true,
            placeholder: '-- Select Existing Supplier --',
            width: '100%'
        });

        $('.select2-account').select2({
            theme: 'bootstrap4',
            allowClear: false,
            placeholder: '-- Select Account --',
            width: '100%'
        });
    }
    initSelect2();

    // Auto-fill lessor name when supplier selected
    $('#lessor_id').on('change', function() {
        var selectedOption = $(this).find(':selected');
        var name = selectedOption.data('name');
        if (name) {
            $('#lessor_name').val(name);
        }
    });

    // Toggle option fields
    $('#has_purchase_option').on('change', function() {
        $('#purchase-fields').toggleClass('d-none', !this.checked);
    });

    $('#has_termination_option').on('change', function() {
        $('#termination-fields').toggleClass('d-none', !this.checked);
    });

    // IFRS 16 treatment explanations
    var treatments = {
        'finance': {
            title: 'Finance Lease - Full IFRS 16 Recognition',
            content: '<ul class="mb-0 pl-3 small">' +
                '<li>Recognize <strong>ROU Asset</strong> at present value + direct costs - incentives</li>' +
                '<li>Recognize <strong>Lease Liability</strong> at present value of payments</li>' +
                '<li>Monthly <strong>depreciation</strong> (straight-line) and <strong>interest expense</strong></li>' +
                '</ul>',
            class: 'alert-primary'
        },
        'operating': {
            title: 'Operating Lease - Full IFRS 16 Recognition',
            content: '<ul class="mb-0 pl-3 small">' +
                '<li>Same recognition as Finance Lease under IFRS 16</li>' +
                '<li>Recognize <strong>ROU Asset</strong> and <strong>Lease Liability</strong></li>' +
                '<li>Monthly <strong>depreciation</strong> and <strong>interest expense</strong></li>' +
                '</ul>',
            class: 'alert-info'
        },
        'short_term': {
            title: 'Short-Term Lease - IFRS 16 Exemption',
            content: '<ul class="mb-0 pl-3 small">' +
                '<li><strong>No ROU Asset or Liability</strong> recognized</li>' +
                '<li>Payments expensed as <strong>Rent Expense</strong> (straight-line)</li>' +
                '<li>Simplified accounting for leases ≤ 12 months</li>' +
                '</ul>',
            class: 'alert-success'
        },
        'low_value': {
            title: 'Low-Value Lease - IFRS 16 Exemption',
            content: '<ul class="mb-0 pl-3 small">' +
                '<li><strong>No ROU Asset or Liability</strong> recognized</li>' +
                '<li>Payments expensed as <strong>Rent Expense</strong></li>' +
                '<li>For assets with new value ≤ US$5,000 equivalent</li>' +
                '</ul>',
            class: 'alert-success'
        }
    };

    // Handle lease type change
    $('#lease_type').on('change', function() {
        var type = $(this).val();
        var $alert = $('#ifrs16-alert');
        var $content = $('#ifrs16-content');

        if (type && treatments[type]) {
            var info = treatments[type];
            $content.html('<strong>' + info.title + '</strong>' + info.content);
            $alert.removeClass('d-none alert-primary alert-info alert-success alert-warning')
                  .addClass(info.class);
        } else {
            $alert.addClass('d-none');
        }

        updateJePreview();
        calculate();
    });

    // Update JE preview
    function updateJePreview() {
        var type = $('#lease_type').val();
        var isExempt = (type === 'short_term' || type === 'low_value');

        $('#je-placeholder').toggleClass('d-none', !!type);
        $('#je-standard').toggleClass('d-none', !type || isExempt);
        $('#je-exempt').toggleClass('d-none', !type || !isExempt);
    }

    // Calculate lease term display
    function updateLeaseTerm() {
        var start = $('#commencement_date').val();
        var end = $('#end_date').val();

        if (start && end) {
            var startDate = new Date(start);
            var endDate = new Date(end);
            var months = (endDate.getFullYear() - startDate.getFullYear()) * 12 +
                         (endDate.getMonth() - startDate.getMonth());

            if (months > 0) {
                $('#lease-term-display').text(months + ' months');
                return months;
            }
        }
        $('#lease-term-display').text('-- months');
        return 0;
    }

    // Main calculation function
    function calculate() {
        var months = updateLeaseTerm();
        var payment = parseFloat($('#monthly_payment').val()) || 0;
        var escalation = parseFloat($('#annual_rent_increase_rate').val()) || 0;
        var ibr = parseFloat($('#incremental_borrowing_rate').val()) || 15;
        var directCosts = parseFloat($('#initial_direct_costs').val()) || 0;
        var incentives = parseFloat($('#lease_incentives_received').val()) || 0;
        var leaseType = $('#lease_type').val();

        if (months <= 0 || payment <= 0) {
            $('#calc-term').text('-- months');
            $('#calc-total, #calc-pv, #calc-rou, #calc-depr, #calc-interest').text('₦0.00');
            return;
        }

        // Calculate totals
        var monthlyRate = (ibr / 100) / 12;
        var totalPayments = 0;
        var pvPayments = 0;
        var currentPayment = payment;

        for (var i = 1; i <= months; i++) {
            if (escalation > 0 && i > 1 && (i - 1) % 12 === 0) {
                currentPayment *= (1 + escalation / 100);
            }
            totalPayments += currentPayment;
            pvPayments += currentPayment / Math.pow(1 + monthlyRate, i);
        }

        var liability = pvPayments;
        var rouAsset = pvPayments + directCosts - incentives;
        var monthlyDepr = rouAsset / months;
        var totalInterest = totalPayments - liability;

        // Format currency
        function fmt(n) {
            return '₦' + n.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        // Update displays
        $('#calc-term').text(months + ' months');
        $('#calc-total').text(fmt(totalPayments));
        $('#calc-pv').text(fmt(liability));
        $('#calc-rou').text(fmt(rouAsset));
        $('#calc-depr').text(fmt(monthlyDepr));
        $('#calc-interest').text(fmt(totalInterest));

        // Update JE preview amounts
        var isExempt = (leaseType === 'short_term' || leaseType === 'low_value');
        if (isExempt) {
            $('#je-rent-dr, #je-cash-cr').text(fmt(payment));
        } else {
            $('#je-rou-dr').text(fmt(rouAsset));
            $('#je-liability-cr').text(fmt(liability));
        }
    }

    // Bind calculation triggers
    $('#commencement_date, #end_date, #monthly_payment, #annual_rent_increase_rate, #incremental_borrowing_rate, #initial_direct_costs, #lease_incentives_received')
        .on('change input', calculate);

    // Initial calculation
    calculate();
    updateJePreview();
});
</script>
@endpush
