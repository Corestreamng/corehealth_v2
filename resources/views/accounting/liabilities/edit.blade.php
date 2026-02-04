@extends('admin.layouts.app')
@section('title', 'Edit Liability')
@section('page_name', 'Accounting')
@section('subpage_name', 'Edit Liability')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Liabilities', 'url' => route('accounting.liabilities.index'), 'icon' => 'mdi-credit-card-clock'],
    ['label' => $liability->liability_number, 'url' => route('accounting.liabilities.show', $liability->id), 'icon' => 'mdi-eye'],
    ['label' => 'Edit', 'url' => '#', 'icon' => 'mdi-pencil']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="alert alert-info mb-4">
                    <i class="mdi mdi-information mr-2"></i>
                    <strong>Note:</strong> Core financial terms (principal, dates, frequency) cannot be modified after creation.
                    Contact your administrator if restructuring is needed.
                </div>

                @if($paidPayments > 0)
                <div class="alert alert-warning mb-4">
                    <i class="mdi mdi-alert mr-2"></i>
                    <strong>Payments Already Made:</strong> {{ $paidPayments }} of {{ $totalPayments }} payments have been recorded.
                    <br>
                    <small>If you change the interest rate, only the <strong>{{ $totalPayments - $paidPayments }} remaining unpaid payments</strong> will be recalculated.
                    Already paid payments and their journal entries will remain unchanged to preserve the audit trail.</small>
                </div>
                @endif

                <form action="{{ route('accounting.liabilities.update', $liability->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <!-- Creditor Information -->
                    <div class="card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-account-tie mr-2"></i>Creditor Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="creditor_name">Creditor Name <span class="text-danger">*</span></label>
                                        <input type="text" name="creditor_name" id="creditor_name"
                                               class="form-control @error('creditor_name') is-invalid @enderror"
                                               value="{{ old('creditor_name', $liability->creditor_name) }}" required>
                                        @error('creditor_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="creditor_contact">Creditor Contact</label>
                                        <input type="text" name="creditor_contact" id="creditor_contact"
                                               class="form-control @error('creditor_contact') is-invalid @enderror"
                                               value="{{ old('creditor_contact', $liability->creditor_contact) }}">
                                        @error('creditor_contact')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="reference_number">Reference Number</label>
                                <input type="text" name="reference_number" id="reference_number"
                                       class="form-control @error('reference_number') is-invalid @enderror"
                                       value="{{ old('reference_number', $liability->reference_number) }}">
                                @error('reference_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    <!-- Interest Rate -->
                    <div class="card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-percent mr-2"></i>Interest Rate</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="interest_rate">Interest Rate (% per annum) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" name="interest_rate" id="interest_rate"
                                           class="form-control @error('interest_rate') is-invalid @enderror"
                                           value="{{ old('interest_rate', $liability->interest_rate) }}"
                                           data-original="{{ $liability->interest_rate }}"
                                           step="0.01" min="0" max="100" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                @error('interest_rate')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <small class="text-muted">
                                    @if($paidPayments > 0)
                                        <i class="mdi mdi-information-outline text-warning"></i>
                                        Changing this will recalculate only the {{ $totalPayments - $paidPayments }} unpaid payments
                                        based on current balance of ₦{{ number_format($liability->current_balance, 2) }}
                                    @else
                                        Changing this will recalculate all {{ $totalPayments }} scheduled payments
                                    @endif
                                </small>
                            </div>
                            <div id="rate-change-preview" class="alert alert-light border d-none mt-3">
                                <i class="mdi mdi-calculator mr-1"></i>
                                <strong>Preview:</strong>
                                <span id="rate-change-text"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Collateral -->
                    <div class="card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-shield-check mr-2"></i>Collateral</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="collateral_description">Collateral Description</label>
                                        <textarea name="collateral_description" id="collateral_description"
                                                  class="form-control @error('collateral_description') is-invalid @enderror"
                                                  rows="2">{{ old('collateral_description', $liability->collateral_description) }}</textarea>
                                        @error('collateral_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="collateral_value">Collateral Value</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="number" name="collateral_value" id="collateral_value"
                                                   class="form-control @error('collateral_value') is-invalid @enderror"
                                                   value="{{ old('collateral_value', $liability->collateral_value) }}"
                                                   step="0.01" min="0">
                                        </div>
                                        @error('collateral_value')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-note-text mr-2"></i>Notes</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-group mb-0">
                                <textarea name="notes" id="notes"
                                          class="form-control @error('notes') is-invalid @enderror"
                                          rows="3">{{ old('notes', $liability->notes) }}</textarea>
                                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('accounting.liabilities.show', $liability->id) }}" class="btn btn-secondary">
                            <i class="mdi mdi-arrow-left"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="mdi mdi-check"></i> Update Liability
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    var originalRate = parseFloat($('#interest_rate').data('original'));
    var currentBalance = {{ $liability->current_balance }};
    var unpaidPayments = {{ $totalPayments - $paidPayments }};
    var paidPayments = {{ $paidPayments }};

    $('#interest_rate').on('change keyup', function() {
        var newRate = parseFloat($(this).val()) || 0;
        var rateChanged = Math.abs(newRate - originalRate) > 0.0001;

        if (rateChanged && unpaidPayments > 0) {
            // Simple estimate: monthly payment calculation
            var monthlyRate = newRate / 100 / 12;
            var estimatedPayment = 0;

            if (monthlyRate > 0) {
                estimatedPayment = currentBalance * (monthlyRate * Math.pow(1 + monthlyRate, unpaidPayments))
                                 / (Math.pow(1 + monthlyRate, unpaidPayments) - 1);
            } else {
                estimatedPayment = currentBalance / unpaidPayments;
            }

            var changeText = 'Rate changed from ' + originalRate.toFixed(2) + '% to ' + newRate.toFixed(2) + '%. ';
            changeText += 'Estimated new payment: ₦' + estimatedPayment.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2});

            if (paidPayments > 0) {
                changeText += ' (for remaining ' + unpaidPayments + ' payments)';
            }

            $('#rate-change-text').text(changeText);
            $('#rate-change-preview').removeClass('d-none');
        } else {
            $('#rate-change-preview').addClass('d-none');
        }
    });
});
</script>
@endpush
