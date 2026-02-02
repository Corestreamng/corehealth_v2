@extends('admin.layouts.app')
@section('title', 'Record Payment')
@section('page_name', 'Accounting')
@section('subpage_name', 'Record Payment')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Liabilities', 'url' => route('accounting.liabilities.index'), 'icon' => 'mdi-credit-card-clock'],
    ['label' => $liability->liability_number, 'url' => route('accounting.liabilities.show', $liability->id), 'icon' => 'mdi-eye'],
    ['label' => 'Record Payment', 'url' => '#', 'icon' => 'mdi-cash-plus']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Liability Summary -->
                <div class="card card-modern mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="mdi mdi-information mr-2"></i>{{ $liability->liability_number }} - {{ $liability->creditor_name }}
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <small class="text-muted">Principal</small>
                                <h5 class="mb-0">₦{{ number_format($liability->principal_amount, 2) }}</h5>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Current Balance</small>
                                <h5 class="mb-0 text-danger">₦{{ number_format($liability->current_balance, 2) }}</h5>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Interest Rate</small>
                                <h5 class="mb-0">{{ $liability->interest_rate }}%</h5>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Regular Payment</small>
                                <h5 class="mb-0 text-primary">₦{{ number_format($liability->regular_payment_amount, 2) }}</h5>
                            </div>
                        </div>
                    </div>
                </div>

                @if($nextPayment)
                <!-- Next Scheduled Payment -->
                <div class="alert alert-warning">
                    <i class="mdi mdi-calendar-clock mr-2"></i>
                    <strong>Next Scheduled Payment:</strong>
                    ₦{{ number_format($nextPayment->payment_amount, 2) }} due on {{ \Carbon\Carbon::parse($nextPayment->due_date)->format('M d, Y') }}
                    (Principal: ₦{{ number_format($nextPayment->principal_amount, 2) }}, Interest: ₦{{ number_format($nextPayment->interest_amount, 2) }})
                </div>
                @endif

                <!-- Payment Form -->
                <div class="card card-modern">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="mdi mdi-cash-plus mr-2"></i>Record Payment</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('accounting.liabilities.payment.store', $liability->id) }}" method="POST">
                            @csrf

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="payment_date">Payment Date <span class="text-danger">*</span></label>
                                        <input type="date" name="payment_date" id="payment_date"
                                               class="form-control @error('payment_date') is-invalid @enderror"
                                               value="{{ old('payment_date', date('Y-m-d')) }}" required>
                                        @error('payment_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="bank_id">Payment Bank <span class="text-danger">*</span></label>
                                        <select name="bank_id" id="bank_id" class="form-control @error('bank_id') is-invalid @enderror" required>
                                            <option value="">Select Bank</option>
                                            @foreach($banks as $bank)
                                                <option value="{{ $bank->id }}" {{ old('bank_id') == $bank->id ? 'selected' : '' }}>
                                                    {{ $bank->bank_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('bank_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="principal_paid">Principal Paid <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="number" name="principal_paid" id="principal_paid"
                                                   class="form-control @error('principal_paid') is-invalid @enderror"
                                                   value="{{ old('principal_paid', $nextPayment->principal_amount ?? 0) }}"
                                                   step="0.01" min="0" required>
                                        </div>
                                        @error('principal_paid')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="interest_paid">Interest Paid <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="number" name="interest_paid" id="interest_paid"
                                                   class="form-control @error('interest_paid') is-invalid @enderror"
                                                   value="{{ old('interest_paid', $nextPayment->interest_amount ?? 0) }}"
                                                   step="0.01" min="0" required>
                                        </div>
                                        @error('interest_paid')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="amount_paid">Total Amount Paid <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="number" name="amount_paid" id="amount_paid"
                                                   class="form-control @error('amount_paid') is-invalid @enderror"
                                                   value="{{ old('amount_paid', $nextPayment->payment_amount ?? 0) }}"
                                                   step="0.01" min="0.01" required readonly>
                                        </div>
                                        @error('amount_paid')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="payment_reference">Payment Reference</label>
                                <input type="text" name="payment_reference" id="payment_reference"
                                       class="form-control @error('payment_reference') is-invalid @enderror"
                                       value="{{ old('payment_reference') }}" placeholder="Check/Transfer reference number">
                                @error('payment_reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <textarea name="notes" id="notes"
                                          class="form-control @error('notes') is-invalid @enderror"
                                          rows="2" placeholder="Optional notes...">{{ old('notes') }}</textarea>
                                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <!-- Preview -->
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h6 class="mb-3"><i class="mdi mdi-eye mr-1"></i> Payment Preview</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="mb-1">Balance Before: <strong>₦{{ number_format($liability->current_balance, 2) }}</strong></p>
                                            <p class="mb-1">Principal Paid: <strong id="preview-principal">₦0.00</strong></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-1">Balance After: <strong id="preview-balance" class="text-success">₦{{ number_format($liability->current_balance, 2) }}</strong></p>
                                            <p class="mb-1">Remaining Payments: <strong id="preview-remaining">-</strong></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="{{ route('accounting.liabilities.show', $liability->id) }}" class="btn btn-secondary">
                                    <i class="mdi mdi-arrow-left"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="mdi mdi-check"></i> Record Payment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    function updateTotal() {
        var principal = parseFloat($('#principal_paid').val()) || 0;
        var interest = parseFloat($('#interest_paid').val()) || 0;
        var total = principal + interest;

        $('#amount_paid').val(total.toFixed(2));
        $('#preview-principal').text('₦' + principal.toLocaleString('en-NG', {minimumFractionDigits: 2}));

        var currentBalance = {{ $liability->current_balance }};
        var newBalance = currentBalance - principal;
        $('#preview-balance').text('₦' + newBalance.toLocaleString('en-NG', {minimumFractionDigits: 2}));

        if (newBalance <= 0) {
            $('#preview-balance').removeClass('text-success').addClass('text-primary').text('₦0.00 (Paid Off!)');
            $('#preview-remaining').text('0 payments');
        }
    }

    $('#principal_paid, #interest_paid').on('change keyup', updateTotal);
    updateTotal();
});
</script>
@endpush
