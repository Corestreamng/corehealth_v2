@extends('admin.layouts.app')

@section('title', 'Create Account')
@section('page_name', 'Accounting')
@section('subpage_name', 'Create Account')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Chart of Accounts', 'url' => route('accounting.chart-of-accounts.index'), 'icon' => 'mdi-file-tree'],
    ['label' => 'Create Account', 'url' => '#', 'icon' => 'mdi-plus-circle']
]])

<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Create New Account</h4>
            <p class="text-muted mb-0">Add a new account to the chart of accounts</p>
        </div>
        <div>
            <a href="{{ route('accounting.chart-of-accounts.index') }}" class="btn btn-outline-secondary">
                <i class="mdi mdi-arrow-left mr-1"></i> Back
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Account Information</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('accounting.chart-of-accounts.store') }}" method="POST">
                        @csrf

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Class <span class="text-danger">*</span></label>
                                <select name="account_class_id" id="accountClass" class="form-control @error('account_class_id') is-invalid @enderror" required>
                                    <option value="">Select Class</option>
                                    @foreach($classes as $class)
                                        <option value="{{ $class->id }}" {{ old('account_class_id') == $class->id ? 'selected' : '' }}>
                                            {{ $class->code }} - {{ $class->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('account_class_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Group <span class="text-danger">*</span></label>
                                <select name="account_group_id" id="accountGroup" class="form-control @error('account_group_id') is-invalid @enderror" required>
                                    <option value="">Select Group</option>
                                    @foreach($groups as $classId => $classGroups)
                                        <optgroup label="{{ $classGroups->first()->accountClass->name ?? '' }}" data-class-id="{{ $classId }}">
                                            @foreach($classGroups as $group)
                                                <option value="{{ $group->id }}" data-class-id="{{ $classId }}" {{ old('account_group_id') == $group->id ? 'selected' : '' }}>
                                                    {{ $group->code }} - {{ $group->name }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                @error('account_group_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Code <span class="text-danger">*</span></label>
                                <input type="text" name="account_code" class="form-control @error('account_code') is-invalid @enderror"
                                       value="{{ old('account_code') }}" placeholder="e.g., 1010" required>
                                @error('account_code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name') }}" placeholder="e.g., Cash in Hand" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                                      rows="3" placeholder="Optional description...">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Normal Balance <span class="text-danger">*</span></label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="normal_balance" id="debitBalance"
                                           value="debit" {{ old('normal_balance', 'debit') == 'debit' ? 'checked' : '' }} required>
                                    <label class="form-check-label" for="debitBalance">Debit</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="normal_balance" id="creditBalance"
                                           value="credit" {{ old('normal_balance') == 'credit' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="creditBalance">Credit</label>
                                </div>
                            </div>
                            @error('normal_balance')
                                <div class="text-danger small">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_bank_account" id="isBankAccount"
                                       value="1" {{ old('is_bank_account') ? 'checked' : '' }}>
                                <label class="form-check-label" for="isBankAccount">
                                    This is a bank account
                                </label>
                            </div>
                        </div>

                        <div id="bankDetails" style="display: {{ old('is_bank_account') ? 'block' : 'none' }};">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Bank Name</label>
                                    <input type="text" name="bank_name" class="form-control @error('bank_name') is-invalid @enderror"
                                           value="{{ old('bank_name') }}" placeholder="e.g., First Bank">
                                    @error('bank_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Account Number</label>
                                    <input type="text" name="bank_account_number" class="form-control @error('bank_account_number') is-invalid @enderror"
                                           value="{{ old('bank_account_number') }}" placeholder="e.g., 0123456789">
                                    @error('bank_account_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('accounting.chart-of-accounts.index') }}" class="btn btn-secondary">
                                <i class="mdi mdi-close mr-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-check mr-1"></i> Create Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Guidelines</h6>
                </div>
                <div class="card-body">
                    <ul class="small mb-0 pl-3">
                        <li class="mb-2">Account codes should be unique and follow your numbering system.</li>
                        <li class="mb-2">Choose the correct account class and group for proper classification.</li>
                        <li class="mb-2">Normal balance indicates whether the account typically has a debit or credit balance.</li>
                        <li>Mark accounts as bank accounts to enable bank reconciliation features.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // Filter groups by selected class
    $('#accountClass').on('change', function() {
        var classId = $(this).val();
        $('#accountGroup optgroup').hide();
        $('#accountGroup option').prop('disabled', true);

        if (classId) {
            $('#accountGroup optgroup[data-class-id="' + classId + '"]').show();
            $('#accountGroup option[data-class-id="' + classId + '"]').prop('disabled', false);
        }

        $('#accountGroup').val('');
    });

    // Show/hide bank details
    $('#isBankAccount').on('change', function() {
        if ($(this).is(':checked')) {
            $('#bankDetails').slideDown();
        } else {
            $('#bankDetails').slideUp();
        }
    });

    // Trigger on load if class is selected
    if ($('#accountClass').val()) {
        $('#accountClass').trigger('change');
    }
});
</script>
@endpush
@endsection
