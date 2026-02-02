@extends('admin.layouts.app')

@section('title', 'Edit Account')
@section('page_name', 'Accounting')
@section('subpage_name', 'Edit Account')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Chart of Accounts', 'url' => route('accounting.chart-of-accounts.index'), 'icon' => 'mdi-file-tree'],
    ['label' => 'Edit Account', 'url' => '#', 'icon' => 'mdi-pencil']
]])

<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Edit Account</h4>
            <p class="text-muted mb-0">{{ $account->full_code }} - {{ $account->name }}</p>
        </div>
        <div>
            <a href="{{ route('accounting.chart-of-accounts.show', $account->id) }}" class="btn btn-outline-secondary">
                <i class="mdi mdi-arrow-left mr-1"></i> Back
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div modern shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Account Information</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('accounting.chart-of-accounts.update', $account->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label class="form-label">Account Group <span class="text-danger">*</span></label>
                            <select name="account_group_id" class="form-control @error('account_group_id') is-invalid @enderror" required>
                                <option value="">Select Group</option>
                                @foreach($groups as $group)
                                    <option value="{{ $group->id }}"
                                            {{ old('account_group_id', $account->account_group_id) == $group->id ? 'selected' : '' }}>
                                        {{ $group->accountClass->name ?? '' }} - {{ $group->code }} - {{ $group->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('account_group_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Code <span class="text-danger">*</span></label>
                                <input type="text" name="account_code" class="form-control @error('account_code') is-invalid @enderror"
                                       value="{{ old('account_code', $account->code) }}" required>
                                @error('account_code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name', $account->name) }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                                      rows="3">{{ old('description', $account->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_bank_account" id="isBankAccount"
                                       value="1" {{ old('is_bank_account', $account->is_bank_account) ? 'checked' : '' }}>
                                <label class="form-check-label" for="isBankAccount">
                                    This is a bank account
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                                       value="1" {{ old('is_active', $account->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="isActive">
                                    Account is active
                                </label>
                            </div>
                            <small class="text-muted">Inactive accounts cannot be used in new transactions.</small>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('accounting.chart-of-accounts.show', $account->id) }}" class="btn btn-secondary">
                                <i class="mdi mdi-close mr-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-check mr-1"></i> Update Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div modern shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Account Details</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th>Current Balance:</th>
                            <td class="text-end">₦ {{ number_format($account->getBalance(), 2) }}</td>
                        </tr>
                        <tr>
                            <th>Transaction Count:</th>
                            <td class="text-end">{{ $account->journalLines()->count() }}</td>
                        </tr>
                        <tr>
                            <th>Created:</th>
                            <td class="text-end">{{ $account->created_at->format('M d, Y') }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div modern shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Guidelines</h6>
                </div>
                <div class="card-body">
                    <ul class="small mb-0 pl-3">
                        <li class="mb-2">Changing the account code will affect all reports and references.</li>
                        <li class="mb-2">Deactivating an account prevents it from being used in new transactions.</li>
                        <li>Existing transactions will not be affected by changes.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
