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
        {{-- Error/Success Messages --}}
        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4">
            <i class="mdi mdi-alert-circle mr-2"></i>
            <strong>Error:</strong> {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        @endif

        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4">
            <i class="mdi mdi-check-circle mr-2"></i>
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        @endif

        @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-4">
            <i class="mdi mdi-alert-circle mr-2"></i>
            <strong>Validation Errors:</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        @endif

        <div class="row">
            <div class="col-lg-8">
                <!-- Liability Summary -->
                <div class="card-modern mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="mdi mdi-credit-card-clock mr-2"></i>{{ $liability->liability_number }} - {{ $liability->creditor_name }}
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
                                <h5 class="mb-0">{{ $liability->interest_rate }}% p.a.</h5>
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
                <div class="alert alert-info border-left border-info mb-4" style="border-left-width: 4px !important;">
                    <div class="d-flex align-items-center">
                        <i class="mdi mdi-calendar-clock mdi-36px mr-3 text-info"></i>
                        <div>
                            <strong>Next Scheduled Payment #{{ $nextPayment->payment_number }}</strong><br>
                            <span class="text-dark h5">₦{{ number_format($nextPayment->scheduled_payment, 2) }}</span> due on
                            <strong>{{ \Carbon\Carbon::parse($nextPayment->due_date)->format('M d, Y') }}</strong>
                            <br>
                            <small class="text-muted">
                                Principal: ₦{{ number_format($nextPayment->principal_portion, 2) }} |
                                Interest: ₦{{ number_format($nextPayment->interest_portion, 2) }} |
                                Balance After: ₦{{ number_format($nextPayment->closing_balance, 2) }}
                            </small>
                        </div>
                    </div>
                </div>
                @else
                <div class="alert alert-success mb-4">
                    <i class="mdi mdi-check-circle mr-2"></i>
                    <strong>No pending payments!</strong> This liability may be fully paid off.
                </div>
                @endif

                <!-- Payment Form -->
                <div class="card-modern">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="mdi mdi-cash-plus mr-2"></i>Record Payment</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-light border mb-4" style="font-size: 0.9rem;">
                            <i class="mdi mdi-information-outline mr-1 text-info"></i>
                            <strong>How it works:</strong> Recording a payment automatically creates a journal entry:
                            <br>
                            <span class="text-success"><strong>DEBIT:</strong></span> Liability Account (reduces debt) + Interest Expense (6300) |
                            <span class="text-danger"><strong>CREDIT:</strong></span> Bank Account (cash outflow)
                        </div>

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
                                                <option value="{{ $bank->id }}" data-name="{{ $bank->name }}" {{ old('bank_id') == $bank->id ? 'selected' : '' }}>
                                                    {{ $bank->name }} {{ $bank->account_number ? '('.$bank->account_number.')' : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">Bank account from which payment is made</small>
                                        @error('bank_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="principal_portion">Principal Portion <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="number" name="principal_portion" id="principal_portion"
                                                   class="form-control @error('principal_portion') is-invalid @enderror"
                                                   value="{{ old('principal_portion', $nextPayment->principal_portion ?? 0) }}"
                                                   step="0.01" min="0" required>
                                        </div>
                                        <small class="text-muted">Amount that reduces your debt</small>
                                        @error('principal_portion')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="interest_portion">Interest Portion <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="number" name="interest_portion" id="interest_portion"
                                                   class="form-control @error('interest_portion') is-invalid @enderror"
                                                   value="{{ old('interest_portion', $nextPayment->interest_portion ?? 0) }}"
                                                   step="0.01" min="0" required>
                                        </div>
                                        <small class="text-muted">Interest expense for this period</small>
                                        @error('interest_portion')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="late_fee">Late Fee</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="number" name="late_fee" id="late_fee"
                                                   class="form-control @error('late_fee') is-invalid @enderror"
                                                   value="{{ old('late_fee', 0) }}"
                                                   step="0.01" min="0">
                                        </div>
                                        <small class="text-muted">Any penalty charges</small>
                                        @error('late_fee')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="actual_payment">Total Amount Paid <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text bg-success text-white">₦</span>
                                            </div>
                                            <input type="number" name="actual_payment" id="actual_payment"
                                                   class="form-control form-control-lg @error('actual_payment') is-invalid @enderror"
                                                   value="{{ old('actual_payment', $nextPayment->scheduled_payment ?? 0) }}"
                                                   step="0.01" min="0.01" required readonly
                                                   style="font-weight: bold; font-size: 1.1rem;">
                                        </div>
                                        @error('actual_payment')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="payment_reference">Payment Reference</label>
                                        <input type="text" name="payment_reference" id="payment_reference"
                                               class="form-control @error('payment_reference') is-invalid @enderror"
                                               value="{{ old('payment_reference') }}" placeholder="Check/Transfer reference number">
                                        @error('payment_reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <textarea name="notes" id="notes"
                                          class="form-control @error('notes') is-invalid @enderror"
                                          rows="2" placeholder="Optional notes about this payment...">{{ old('notes') }}</textarea>
                                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="{{ route('accounting.liabilities.show', $liability->id) }}" class="btn btn-secondary">
                                    <i class="mdi mdi-arrow-left"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="mdi mdi-check"></i> Record Payment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sidebar with JE Preview -->
            <div class="col-lg-4">
                <!-- Balance Preview -->
                <div class="card-modern mb-3">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="mdi mdi-calculator mr-2"></i>Balance Preview</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted">Balance Before Payment</small>
                            <h5 class="mb-0">₦{{ number_format($liability->current_balance, 2) }}</h5>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Principal Paid</small>
                            <h5 id="preview-principal" class="mb-0 text-danger">- ₦0.00</h5>
                        </div>
                        <hr>
                        <div>
                            <small class="text-muted">Balance After Payment</small>
                            <h4 id="preview-balance" class="mb-0 text-success">₦{{ number_format($liability->current_balance, 2) }}</h4>
                        </div>
                        <div id="paid-off-badge" class="mt-2 d-none">
                            <span class="badge badge-success px-3 py-2"><i class="mdi mdi-check-all mr-1"></i> PAID OFF!</span>
                        </div>
                    </div>
                </div>

                <!-- Journal Entry Preview -->
                <div class="card-modern sticky-top" style="top: 20px;">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="mdi mdi-book-open-variant mr-2"></i>Journal Entry Preview</h5>
                    </div>
                    <div class="card-body">
                        <small class="text-muted d-block mb-2">
                            <i class="mdi mdi-check-circle text-success"></i>
                            This entry is <strong>automatically created</strong> when you submit:
                        </small>
                        <table class="table table-sm mb-0" style="font-size: 0.85rem;">
                            <thead style="background: #28a745; color: white;">
                                <tr>
                                    <th style="width: 50%;">Account</th>
                                    <th class="text-right" style="width: 25%;">Debit</th>
                                    <th class="text-right" style="width: 25%;">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <i class="mdi mdi-credit-card-clock mr-1"></i>
                                        <span id="je-liability-name">{{ $liabilityAccount->name ?? 'Loan Payable' }}</span>
                                        <br><small class="text-muted">({{ $liabilityAccount->code ?? '2000' }})</small>
                                    </td>
                                    <td class="text-right font-weight-bold" id="je-principal">₦0.00</td>
                                    <td class="text-right text-muted">-</td>
                                </tr>
                                <tr>
                                    <td>
                                        <i class="mdi mdi-percent mr-1"></i>
                                        <span id="je-interest-name">Interest Expense</span>
                                        <br><small class="text-muted">(6300)</small>
                                    </td>
                                    <td class="text-right" id="je-interest">₦0.00</td>
                                    <td class="text-right text-muted">-</td>
                                </tr>
                                <tr id="je-late-fee-row" class="d-none">
                                    <td>
                                        <i class="mdi mdi-alert mr-1"></i>
                                        Late Fee Expense
                                    </td>
                                    <td class="text-right" id="je-late-fee">₦0.00</td>
                                    <td class="text-right text-muted">-</td>
                                </tr>
                                <tr class="table-danger">
                                    <td>
                                        <i class="mdi mdi-bank mr-1"></i>
                                        <span id="je-bank-name">Bank Account</span>
                                    </td>
                                    <td class="text-right text-muted">-</td>
                                    <td class="text-right font-weight-bold" id="je-credit">₦0.00</td>
                                </tr>
                            </tbody>
                            <tfoot style="background: #f8f9fa; font-weight: 600;">
                                <tr>
                                    <td>TOTALS</td>
                                    <td class="text-right" id="je-debit-total">₦0.00</td>
                                    <td class="text-right" id="je-credit-total">₦0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                        <div class="alert alert-light border mt-2 mb-0 py-1 px-2" style="font-size: 0.75rem;">
                            <i class="mdi mdi-link mr-1"></i>
                            JE will be linked to payment record for audit trail
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
    var currentBalance = {{ $liability->current_balance }};

    function updateTotal() {
        var principal = parseFloat($('#principal_portion').val()) || 0;
        var interest = parseFloat($('#interest_portion').val()) || 0;
        var lateFee = parseFloat($('#late_fee').val()) || 0;
        var total = principal + interest + lateFee;

        $('#actual_payment').val(total.toFixed(2));

        // Update balance preview
        $('#preview-principal').text('- ₦' + principal.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        var newBalance = currentBalance - principal;
        $('#preview-balance').text('₦' + newBalance.toLocaleString('en-NG', {minimumFractionDigits: 2}));

        if (newBalance <= 0) {
            $('#preview-balance').text('₦0.00');
            $('#paid-off-badge').removeClass('d-none');
        } else {
            $('#paid-off-badge').addClass('d-none');
        }
    }

    function updateJePreview() {
        var principal = parseFloat($('#principal_portion').val()) || 0;
        var interest = parseFloat($('#interest_portion').val()) || 0;
        var lateFee = parseFloat($('#late_fee').val()) || 0;
        var total = principal + interest + lateFee;

        $('#je-principal').text('₦' + principal.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#je-interest').text('₦' + interest.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#je-credit').text('₦' + total.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#je-debit-total').text('₦' + total.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#je-credit-total').text('₦' + total.toLocaleString('en-NG', {minimumFractionDigits: 2}));

        // Show/hide late fee row
        if (lateFee > 0) {
            $('#je-late-fee-row').removeClass('d-none');
            $('#je-late-fee').text('₦' + lateFee.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        } else {
            $('#je-late-fee-row').addClass('d-none');
        }
    }

    // Update bank name in JE preview
    $('#bank_id').on('change', function() {
        var selected = $(this).find(':selected');
        var name = selected.data('name') || 'Bank Account';
        $('#je-bank-name').text(name);
    });

    $('#principal_portion, #interest_portion, #late_fee').on('change keyup', function() {
        updateTotal();
        updateJePreview();
    });

    // Initial update
    updateTotal();
    updateJePreview();

    // Trigger bank name if already selected
    if ($('#bank_id').val()) {
        $('#bank_id').trigger('change');
    }
});
</script>
@endpush
