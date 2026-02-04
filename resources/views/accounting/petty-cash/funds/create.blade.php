@extends('admin.layouts.app')
@section('title', 'Create Petty Cash Fund')
@section('page_name', 'Accounting')
@section('subpage_name', 'New Fund')

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
        background-color: #667eea !important;
    }
</style>
@endpush

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Petty Cash', 'url' => route('accounting.petty-cash.index'), 'icon' => 'mdi-cash-register'],
    ['label' => 'Funds', 'url' => route('accounting.petty-cash.funds.index'), 'icon' => 'mdi-wallet'],
    ['label' => 'New Fund', 'url' => '#', 'icon' => 'mdi-plus']
]])

<div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card-modern card-modern">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="mdi mdi-plus-circle mr-2"></i>Create New Petty Cash Fund</h5>
                    </div>
                    <div class="card-body">
                        @if(session('error'))
                            <div class="alert alert-danger">{{ session('error') }}</div>
                        @endif

                        <form action="{{ route('accounting.petty-cash.funds.store') }}" method="POST">
                            @csrf

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Fund Name <span class="text-danger">*</span></label>
                                        <input type="text" name="fund_name" class="form-control @error('fund_name') is-invalid @enderror"
                                               value="{{ old('fund_name') }}" required placeholder="e.g., Main Office Petty Cash">
                                        @error('fund_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Fund Code</label>
                                        <input type="text" name="fund_code" class="form-control @error('fund_code') is-invalid @enderror"
                                               value="{{ old('fund_code') }}" placeholder="Auto-generated if empty">
                                        @error('fund_code')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="form-text text-muted">Leave blank for auto-generation</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>GL Account <span class="text-danger">*</span></label>
                                        <select name="account_id" class="form-control select2 @error('account_id') is-invalid @enderror" required>
                                            <option value="">Select Account</option>
                                            @foreach($accounts as $account)
                                                <option value="{{ $account->id }}" {{ old('account_id') == $account->id ? 'selected' : '' }}>
                                                    {{ $account->code }} - {{ $account->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('account_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Custodian <span class="text-danger">*</span></label>
                                        <select name="custodian_user_id" class="form-control select2 @error('custodian_user_id') is-invalid @enderror" required>
                                            <option value="">Select Custodian</option>
                                            @foreach($custodians as $user)
                                                <option value="{{ $user->id }}" {{ old('custodian_user_id') == $user->id ? 'selected' : '' }}>
                                                    {{ $user->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('custodian_user_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Department</label>
                                        <select name="department_id" class="form-control select2 @error('department_id') is-invalid @enderror">
                                            <option value="">Select Department (Optional)</option>
                                            @foreach($departments as $dept)
                                                <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>
                                                    {{ $dept->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('department_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">
                            <h6 class="mb-3"><i class="mdi mdi-currency-ngn mr-2"></i>Financial Limits</h6>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Fund Limit (₦) <span class="text-danger">*</span></label>
                                        <input type="number" name="fund_limit" id="fund_limit" class="form-control @error('fund_limit') is-invalid @enderror"
                                               value="{{ old('fund_limit', 100000) }}" step="0.01" min="0" required>
                                        @error('fund_limit')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="form-text text-muted">Maximum amount this fund can hold</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Transaction Limit (₦) <span class="text-danger">*</span></label>
                                        <input type="number" name="transaction_limit" class="form-control @error('transaction_limit') is-invalid @enderror"
                                               value="{{ old('transaction_limit', 10000) }}" step="0.01" min="0" required>
                                        @error('transaction_limit')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="form-text text-muted">Maximum amount per single disbursement</small>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">
                            <h6 class="mb-3"><i class="mdi mdi-cash-plus mr-2"></i>Initial Funding (Optional)</h6>
                            <div class="alert alert-info py-2">
                                <i class="mdi mdi-information mr-1"></i>
                                <small>You can optionally fund this petty cash immediately. This will create a replenishment transaction and journal entry.</small>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Initial Amount (₦)</label>
                                        <input type="number" name="initial_funding" id="initial_funding" class="form-control @error('initial_funding') is-invalid @enderror"
                                               value="{{ old('initial_funding') }}" step="0.01" min="0" placeholder="0.00">
                                        @error('initial_funding')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="form-text text-muted">Leave empty or 0 to skip initial funding</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group" id="funding_source_group" style="display: none;">
                                        <label>Funding Source <span class="text-danger">*</span></label>
                                        <select name="funding_source" id="funding_source" class="form-control @error('funding_source') is-invalid @enderror">
                                            <option value="">-- Select Source --</option>
                                            <option value="cash" {{ old('funding_source') == 'cash' ? 'selected' : '' }}>Cash on Hand</option>
                                            <option value="bank" {{ old('funding_source') == 'bank' ? 'selected' : '' }}>Bank Transfer</option>
                                        </select>
                                        @error('funding_source')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group" id="funding_bank_group" style="display: none;">
                                        <label>Source Bank <span class="text-danger">*</span></label>
                                        <select name="funding_bank_id" id="funding_bank_id" class="form-control select2 @error('funding_bank_id') is-invalid @enderror">
                                            <option value="">-- Select Bank --</option>
                                            @foreach($banks as $bank)
                                                <option value="{{ $bank->id }}" {{ old('funding_bank_id') == $bank->id ? 'selected' : '' }}>
                                                    {{ $bank->name }} ({{ $bank->account_number }})
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('funding_bank_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">
                            <h6 class="mb-3"><i class="mdi mdi-shield-check mr-2"></i>Approval Settings</h6>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="requires_approval"
                                                   name="requires_approval" value="1" {{ old('requires_approval', true) ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="requires_approval">Require Approval for Disbursements</label>
                                        </div>
                                        <small class="form-text text-muted">If enabled, disbursements require approval before processing</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Auto-Approve Threshold (₦)</label>
                                        <input type="number" name="approval_threshold" class="form-control @error('approval_threshold') is-invalid @enderror"
                                               value="{{ old('approval_threshold', 0) }}" step="0.01" min="0">
                                        @error('approval_threshold')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="form-text text-muted">Amounts below this are auto-approved (0 = always require approval)</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Notes</label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes about this fund...">{{ old('notes') }}</textarea>
                            </div>

                            <hr class="my-4">

                            <div class="d-flex justify-content-between">
                                <a href="{{ route('accounting.petty-cash.funds.index') }}" class="btn btn-secondary">
                                    <i class="mdi mdi-arrow-left"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="mdi mdi-check"></i> Create Fund
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $('.select2').select2({
        theme: 'bootstrap4',
        width: '100%'
    });

    // Handle initial funding visibility
    function toggleFundingFields() {
        var amount = parseFloat($('#initial_funding').val()) || 0;
        if (amount > 0) {
            $('#funding_source_group').show();
            toggleBankField();
        } else {
            $('#funding_source_group').hide();
            $('#funding_bank_group').hide();
        }
    }

    function toggleBankField() {
        var source = $('#funding_source').val();
        if (source === 'bank') {
            $('#funding_bank_group').show();
        } else {
            $('#funding_bank_group').hide();
        }
    }

    // Set initial funding to fund limit by default when user clicks on it
    $('#initial_funding').on('focus', function() {
        if (!$(this).val()) {
            $(this).val($('#fund_limit').val());
            toggleFundingFields();
        }
    });

    $('#initial_funding').on('input change', toggleFundingFields);
    $('#funding_source').on('change', toggleBankField);

    // Initialize on page load (for old() values)
    toggleFundingFields();
});
</script>
@endpush
