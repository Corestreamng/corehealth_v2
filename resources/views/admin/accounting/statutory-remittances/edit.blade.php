@extends('admin.layouts.app')
@section('title', 'Edit Statutory Remittance')
@section('page_name', 'Accounting')
@section('subpage_name', 'Statutory Remittances')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="mdi mdi-bank-transfer text-primary mr-2"></i>
                Edit Remittance: {{ $remittance->reference_number }}
            </h4>
            <p class="text-muted mb-0">Modify remittance details</p>
        </div>
        <a href="{{ route('accounting.statutory-remittances.index') }}" class="btn btn-outline-secondary">
            <i class="mdi mdi-arrow-left"></i> Back to List
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card-modern">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="mdi mdi-form-textbox mr-2"></i>Remittance Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('accounting.statutory-remittances.update', $remittance) }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <!-- Deduction Type -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Deduction Type *</label>
                                    <select name="pay_head_id" class="form-control @error('pay_head_id') is-invalid @enderror" required>
                                        <option value="">Select Deduction Type</option>
                                        @foreach($payHeads as $payHead)
                                        <option value="{{ $payHead->id }}"
                                            {{ old('pay_head_id', $remittance->pay_head_id) == $payHead->id ? 'selected' : '' }}>
                                            {{ $payHead->name }} ({{ $payHead->code }})
                                        </option>
                                        @endforeach
                                    </select>
                                    @error('pay_head_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Amount -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Amount (₦) *</label>
                                    <input type="number" name="amount" step="0.01" min="0.01"
                                           class="form-control @error('amount') is-invalid @enderror"
                                           value="{{ old('amount', $remittance->amount) }}" required>
                                    @error('amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Period From -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Period From *</label>
                                    <input type="date" name="period_from"
                                           class="form-control @error('period_from') is-invalid @enderror"
                                           value="{{ old('period_from', $remittance->period_from->format('Y-m-d')) }}" required>
                                    @error('period_from')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Period To -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Period To *</label>
                                    <input type="date" name="period_to"
                                           class="form-control @error('period_to') is-invalid @enderror"
                                           value="{{ old('period_to', $remittance->period_to->format('Y-m-d')) }}" required>
                                    @error('period_to')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Due Date -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Due Date</label>
                                    <input type="date" name="due_date"
                                           class="form-control @error('due_date') is-invalid @enderror"
                                           value="{{ old('due_date', $remittance->due_date?->format('Y-m-d')) }}">
                                    @error('due_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <hr>
                        <h6 class="text-muted mb-3"><i class="mdi mdi-office-building mr-1"></i> Payee Details</h6>

                        <div class="row">
                            <!-- Payee Name -->
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="form-label">Payee Name *</label>
                                    <input type="text" name="payee_name"
                                           class="form-control @error('payee_name') is-invalid @enderror"
                                           value="{{ old('payee_name', $remittance->payee_name) }}"
                                           placeholder="e.g., Federal Inland Revenue Service (FIRS)" required>
                                    @error('payee_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Payee Bank -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Payee Bank Name</label>
                                    <input type="text" name="payee_bank_name"
                                           class="form-control @error('payee_bank_name') is-invalid @enderror"
                                           value="{{ old('payee_bank_name', $remittance->payee_bank_name) }}"
                                           placeholder="e.g., Central Bank of Nigeria">
                                    @error('payee_bank_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Payee Account Number -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Payee Account Number</label>
                                    <input type="text" name="payee_account_number"
                                           class="form-control @error('payee_account_number') is-invalid @enderror"
                                           value="{{ old('payee_account_number', $remittance->payee_account_number) }}"
                                           placeholder="e.g., 1234567890">
                                    @error('payee_account_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- Notes -->
                        <div class="form-group">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control @error('notes') is-invalid @enderror"
                                      rows="3" placeholder="Additional notes...">{{ old('notes', $remittance->notes) }}</textarea>
                            @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-0">
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-content-save mr-1"></i> Update Remittance
                            </button>
                            <a href="{{ route('accounting.statutory-remittances.show', $remittance) }}" class="btn btn-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Status Card -->
            <div class="card-modern">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="mdi mdi-information-outline mr-2"></i>Status Information</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted">Status:</td>
                            <td><span class="badge badge-{{ $remittance->status_badge }}">{{ ucfirst($remittance->status) }}</span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Reference:</td>
                            <td>{{ $remittance->reference_number }}</td>
                        </tr>
                        @if($remittance->preparedBy)
                        <tr>
                            <td class="text-muted">Prepared By:</td>
                            <td>{{ $remittance->preparedBy->name }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="text-muted">Created:</td>
                            <td>{{ $remittance->created_at->format('M d, Y H:i') }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="alert alert-info mt-3">
                <i class="mdi mdi-information mr-1"></i>
                <strong>Note:</strong> Only draft and pending remittances can be edited. Once approved or paid, changes are not allowed.
            </div>
        </div>
    </div>
</div>
@endsection
