@extends('admin.layouts.app')
@section('title', 'New Liability')
@section('page_name', 'Accounting')
@section('subpage_name', 'New Liability')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Liabilities', 'url' => route('accounting.liabilities.index'), 'icon' => 'mdi-credit-card-clock'],
    ['label' => 'New Liability', 'url' => '#', 'icon' => 'mdi-plus']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-8">
                <form action="{{ route('accounting.liabilities.store') }}" method="POST" id="liability-form">
                    @csrf

                    <!-- Basic Information -->
                    <div class="card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-information mr-2"></i>Liability Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="liability_type">Liability Type <span class="text-danger">*</span></label>
                                        <select name="liability_type" id="liability_type" class="form-control @error('liability_type') is-invalid @enderror" required>
                                            <option value="">Select Type</option>
                                            <option value="loan" {{ old('liability_type') == 'loan' ? 'selected' : '' }}>Loan</option>
                                            <option value="mortgage" {{ old('liability_type') == 'mortgage' ? 'selected' : '' }}>Mortgage</option>
                                            <option value="bond" {{ old('liability_type') == 'bond' ? 'selected' : '' }}>Bond</option>
                                            <option value="deferred_revenue" {{ old('liability_type') == 'deferred_revenue' ? 'selected' : '' }}>Deferred Revenue</option>
                                            <option value="other" {{ old('liability_type') == 'other' ? 'selected' : '' }}>Other</option>
                                        </select>
                                        @error('liability_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="reference_number">Reference Number</label>
                                        <input type="text" name="reference_number" id="reference_number"
                                               class="form-control @error('reference_number') is-invalid @enderror"
                                               value="{{ old('reference_number') }}" placeholder="Loan/Contract reference">
                                        @error('reference_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="creditor_name">Creditor Name <span class="text-danger">*</span></label>
                                        <input type="text" name="creditor_name" id="creditor_name"
                                               class="form-control @error('creditor_name') is-invalid @enderror"
                                               value="{{ old('creditor_name') }}" placeholder="Bank/Lender name" required>
                                        @error('creditor_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="creditor_contact">Creditor Contact</label>
                                        <input type="text" name="creditor_contact" id="creditor_contact"
                                               class="form-control @error('creditor_contact') is-invalid @enderror"
                                               value="{{ old('creditor_contact') }}" placeholder="Phone/Email">
                                        @error('creditor_contact')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Terms -->
                    <div class="card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-cash-multiple mr-2"></i>Financial Terms</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="principal_amount">Principal Amount <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="number" name="principal_amount" id="principal_amount"
                                                   class="form-control @error('principal_amount') is-invalid @enderror"
                                                   value="{{ old('principal_amount') }}" step="0.01" min="0" required>
                                        </div>
                                        @error('principal_amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="interest_rate">Interest Rate <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="number" name="interest_rate" id="interest_rate"
                                                   class="form-control @error('interest_rate') is-invalid @enderror"
                                                   value="{{ old('interest_rate') }}" step="0.01" min="0" max="100" required>
                                            <div class="input-group-append">
                                                <span class="input-group-text">% p.a.</span>
                                            </div>
                                        </div>
                                        @error('interest_rate')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="interest_type">Interest Type <span class="text-danger">*</span></label>
                                        <select name="interest_type" id="interest_type" class="form-control @error('interest_type') is-invalid @enderror" required>
                                            <option value="fixed" {{ old('interest_type') == 'fixed' ? 'selected' : '' }}>Fixed</option>
                                            <option value="variable" {{ old('interest_type') == 'variable' ? 'selected' : '' }}>Variable</option>
                                        </select>
                                        @error('interest_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="start_date">Start Date <span class="text-danger">*</span></label>
                                        <input type="date" name="start_date" id="start_date"
                                               class="form-control @error('start_date') is-invalid @enderror"
                                               value="{{ old('start_date', date('Y-m-d')) }}" required>
                                        @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="term_months">Term (Months) <span class="text-danger">*</span></label>
                                        <input type="number" name="term_months" id="term_months"
                                               class="form-control @error('term_months') is-invalid @enderror"
                                               value="{{ old('term_months', 12) }}" min="1" required>
                                        @error('term_months')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="maturity_date">Maturity Date <span class="text-danger">*</span></label>
                                        <input type="date" name="maturity_date" id="maturity_date"
                                               class="form-control @error('maturity_date') is-invalid @enderror"
                                               value="{{ old('maturity_date') }}" required>
                                        @error('maturity_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="payment_frequency">Payment Frequency <span class="text-danger">*</span></label>
                                        <select name="payment_frequency" id="payment_frequency" class="form-control @error('payment_frequency') is-invalid @enderror" required>
                                            <option value="monthly" {{ old('payment_frequency') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                            <option value="quarterly" {{ old('payment_frequency') == 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                                            <option value="semi_annually" {{ old('payment_frequency') == 'semi_annually' ? 'selected' : '' }}>Semi-Annually</option>
                                            <option value="annually" {{ old('payment_frequency') == 'annually' ? 'selected' : '' }}>Annually</option>
                                        </select>
                                        @error('payment_frequency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Accounting -->
                    <div class="card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-book-open mr-2"></i>Accounting Mapping</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="account_id">Liability Account <span class="text-danger">*</span></label>
                                        <select name="account_id" id="account_id" class="form-control select2 @error('account_id') is-invalid @enderror" required>
                                            <option value="">Select Account</option>
                                            @foreach($accounts as $account)
                                                <option value="{{ $account->id }}" {{ old('account_id') == $account->id ? 'selected' : '' }}>
                                                    {{ $account->code }} - {{ $account->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="interest_expense_account_id">Interest Expense Account <span class="text-danger">*</span></label>
                                        <select name="interest_expense_account_id" id="interest_expense_account_id" class="form-control select2 @error('interest_expense_account_id') is-invalid @enderror" required>
                                            <option value="">Select Account</option>
                                            @foreach($expenseAccounts as $account)
                                                <option value="{{ $account->id }}" {{ old('interest_expense_account_id') == $account->id ? 'selected' : '' }}>
                                                    {{ $account->code }} - {{ $account->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('interest_expense_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Collateral & Notes -->
                    <div class="card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-shield-check mr-2"></i>Collateral & Notes</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="collateral_description">Collateral Description</label>
                                        <textarea name="collateral_description" id="collateral_description"
                                                  class="form-control @error('collateral_description') is-invalid @enderror"
                                                  rows="2" placeholder="Describe collateral if any">{{ old('collateral_description') }}</textarea>
                                        @error('collateral_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="collateral_value">Collateral Value</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="number" name="collateral_value" id="collateral_value"
                                                   class="form-control @error('collateral_value') is-invalid @enderror"
                                                   value="{{ old('collateral_value') }}" step="0.01" min="0">
                                        </div>
                                        @error('collateral_value')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <textarea name="notes" id="notes"
                                          class="form-control @error('notes') is-invalid @enderror"
                                          rows="3" placeholder="Additional notes...">{{ old('notes') }}</textarea>
                                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('accounting.liabilities.index') }}" class="btn btn-secondary">
                            <i class="mdi mdi-arrow-left"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="mdi mdi-check"></i> Create Liability
                        </button>
                    </div>
                </form>
            </div>

            <!-- Sidebar Summary -->
            <div class="col-lg-4">
                <div class="card-modern sticky-top" style="top: 20px;">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="mdi mdi-calculator mr-2"></i>Payment Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted">Principal Amount</small>
                            <h4 id="summary-principal" class="mb-0">₦0.00</h4>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Interest Rate</small>
                            <h5 id="summary-rate" class="mb-0">0%</h5>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Term</small>
                            <h5 id="summary-term" class="mb-0">0 months</h5>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <small class="text-muted">Estimated Payment</small>
                            <h4 id="summary-payment" class="mb-0 text-primary">₦0.00</h4>
                            <small id="summary-frequency" class="text-muted">per month</small>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Total Interest</small>
                            <h5 id="summary-total-interest" class="mb-0 text-danger">₦0.00</h5>
                        </div>
                        <div>
                            <small class="text-muted">Total Repayment</small>
                            <h4 id="summary-total" class="mb-0">₦0.00</h4>
                        </div>
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
    $('.select2').select2({ width: '100%' });

    // Auto-calculate maturity date
    $('#start_date, #term_months').on('change', function() {
        var startDate = $('#start_date').val();
        var termMonths = parseInt($('#term_months').val()) || 0;

        if (startDate && termMonths > 0) {
            var maturity = new Date(startDate);
            maturity.setMonth(maturity.getMonth() + termMonths);
            $('#maturity_date').val(maturity.toISOString().split('T')[0]);
        }
        updateSummary();
    });

    // Update summary on any change
    $('#principal_amount, #interest_rate, #term_months, #payment_frequency').on('change keyup', function() {
        updateSummary();
    });

    function updateSummary() {
        var principal = parseFloat($('#principal_amount').val()) || 0;
        var rate = parseFloat($('#interest_rate').val()) || 0;
        var termMonths = parseInt($('#term_months').val()) || 0;
        var frequency = $('#payment_frequency').val();

        var periodsPerYear = {
            'monthly': 12,
            'quarterly': 4,
            'semi_annually': 2,
            'annually': 1
        };
        var frequencyLabels = {
            'monthly': 'per month',
            'quarterly': 'per quarter',
            'semi_annually': 'semi-annually',
            'annually': 'per year'
        };

        var n = periodsPerYear[frequency] || 12;
        var totalPayments = (termMonths / 12) * n;
        var periodicRate = (rate / 100) / n;

        var payment = 0;
        var totalInterest = 0;
        var total = 0;

        if (principal > 0 && totalPayments > 0) {
            if (periodicRate > 0) {
                payment = principal * (periodicRate * Math.pow(1 + periodicRate, totalPayments))
                         / (Math.pow(1 + periodicRate, totalPayments) - 1);
            } else {
                payment = principal / totalPayments;
            }
            total = payment * totalPayments;
            totalInterest = total - principal;
        }

        $('#summary-principal').text('₦' + principal.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#summary-rate').text(rate + '% p.a.');
        $('#summary-term').text(termMonths + ' months');
        $('#summary-payment').text('₦' + payment.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#summary-frequency').text(frequencyLabels[frequency] || 'per month');
        $('#summary-total-interest').text('₦' + totalInterest.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#summary-total').text('₦' + total.toLocaleString('en-NG', {minimumFractionDigits: 2}));
    }
});
</script>
@endpush
