@extends('admin.layouts.app')
@section('title', 'Edit Petty Cash Fund')
@section('page_name', 'Accounting')
@section('subpage_name', 'Edit Fund')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Petty Cash', 'url' => route('accounting.petty-cash.index'), 'icon' => 'mdi-cash-register'],
    ['label' => 'Funds', 'url' => route('accounting.petty-cash.funds.index'), 'icon' => 'mdi-wallet'],
    ['label' => $fund->fund_name, 'url' => route('accounting.petty-cash.funds.show', $fund->id), 'icon' => 'mdi-eye'],
    ['label' => 'Edit', 'url' => '#', 'icon' => 'mdi-pencil']
]])

<div class="container-fluid">
    <div class="row">
        <!-- Main Form -->
        <div class="col-lg-8">
            <div class="card card-modern">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="mdi mdi-pencil mr-2"></i>Edit Petty Cash Fund</h5>
                </div>
                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <form action="{{ route('accounting.petty-cash.funds.update', $fund->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Fund Name <span class="text-danger">*</span></label>
                                    <input type="text" name="fund_name" class="form-control @error('fund_name') is-invalid @enderror"
                                           value="{{ old('fund_name', $fund->fund_name) }}" required placeholder="e.g., Main Office Petty Cash">
                                    @error('fund_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Fund Code</label>
                                    <input type="text" name="fund_code" class="form-control @error('fund_code') is-invalid @enderror"
                                           value="{{ old('fund_code', $fund->fund_code) }}" placeholder="Fund code">
                                    @error('fund_code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
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
                                            <option value="{{ $account->id }}" {{ old('account_id', $fund->account_id) == $account->id ? 'selected' : '' }}>
                                                {{ $account->account_number }} - {{ $account->account_name }}
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
                                            <option value="{{ $user->id }}" {{ old('custodian_user_id', $fund->custodian_user_id) == $user->id ? 'selected' : '' }}>
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
                                            <option value="{{ $dept->id }}" {{ old('department_id', $fund->department_id) == $dept->id ? 'selected' : '' }}>
                                                {{ $dept->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('department_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Status <span class="text-danger">*</span></label>
                                    <select name="status" class="form-control @error('status') is-invalid @enderror" required>
                                        <option value="active" {{ old('status', $fund->status) == 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="suspended" {{ old('status', $fund->status) == 'suspended' ? 'selected' : '' }}>Suspended</option>
                                        <option value="closed" {{ old('status', $fund->status) == 'closed' ? 'selected' : '' }}>Closed</option>
                                    </select>
                                    @error('status')
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
                                    <input type="number" name="fund_limit" class="form-control @error('fund_limit') is-invalid @enderror"
                                           value="{{ old('fund_limit', $fund->fund_limit) }}" step="0.01" min="0" required>
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
                                           value="{{ old('transaction_limit', $fund->transaction_limit) }}" step="0.01" min="0" required>
                                    @error('transaction_limit')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">Maximum amount per single disbursement</small>
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
                                               name="requires_approval" value="1" {{ old('requires_approval', $fund->requires_approval) ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="requires_approval">Require Approval for Disbursements</label>
                                    </div>
                                    <small class="form-text text-muted">If enabled, disbursements require approval before processing</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Auto-Approve Threshold (₦)</label>
                                    <input type="number" name="approval_threshold" class="form-control @error('approval_threshold') is-invalid @enderror"
                                           value="{{ old('approval_threshold', $fund->approval_threshold) }}" step="0.01" min="0">
                                    @error('approval_threshold')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">Amounts below this are auto-approved (0 = always require approval)</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes about this fund...">{{ old('notes', $fund->notes) }}</textarea>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('accounting.petty-cash.funds.show', $fund->id) }}" class="btn btn-secondary">
                                <i class="mdi mdi-arrow-left"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-check"></i> Update Fund
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Fund Summary -->
            <div class="card card-modern mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="mdi mdi-information mr-2"></i>Fund Summary</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted">Fund Code:</td>
                            <td><strong>{{ $fund->fund_code }}</strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Current Balance:</td>
                            <td><strong class="text-success">₦{{ number_format($fund->current_balance, 2) }}</strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Fund Limit:</td>
                            <td>₦{{ number_format($fund->fund_limit, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Status:</td>
                            <td>
                                @php
                                    $statusColors = ['active' => 'success', 'suspended' => 'warning', 'closed' => 'danger'];
                                @endphp
                                <span class="badge badge-{{ $statusColors[$fund->status] ?? 'secondary' }}">
                                    {{ ucfirst($fund->status) }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Created:</td>
                            <td>{{ $fund->created_at->format('M d, Y') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Last Updated:</td>
                            <td>{{ $fund->updated_at->format('M d, Y H:i') }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Balance Warning -->
            @if($fund->current_balance > $fund->fund_limit)
            <div class="alert alert-warning">
                <i class="mdi mdi-alert"></i>
                <strong>Balance Exceeds Limit</strong>
                <p class="mb-0 small">Current balance (₦{{ number_format($fund->current_balance, 2) }}) exceeds the fund limit.</p>
            </div>
            @endif

            <!-- Quick Links -->
            <div class="card card-modern">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="mdi mdi-link mr-2"></i>Quick Links</h5>
                </div>
                <div class="card-body">
                    <a href="{{ route('accounting.petty-cash.funds.show', $fund->id) }}" class="btn btn-outline-info btn-block mb-2">
                        <i class="mdi mdi-eye"></i> View Fund Details
                    </a>
                    <a href="{{ route('accounting.petty-cash.transactions.index', $fund->id) }}" class="btn btn-outline-secondary btn-block mb-2">
                        <i class="mdi mdi-history"></i> View Transactions
                    </a>
                    <a href="{{ route('accounting.petty-cash.disbursement.create', $fund->id) }}" class="btn btn-outline-warning btn-block mb-2">
                        <i class="mdi mdi-cash-minus"></i> New Disbursement
                    </a>
                    <a href="{{ route('accounting.petty-cash.replenishment.create', $fund->id) }}" class="btn btn-outline-success btn-block">
                        <i class="mdi mdi-cash-plus"></i> Replenish Fund
                    </a>
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
});
</script>
@endpush
