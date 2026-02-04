{{--
    Create Inter-Account Transfer
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 2
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'New Inter-Account Transfer')
@section('page_name', 'Accounting')
@section('subpage_name', 'New Transfer')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Transfers', 'url' => route('accounting.transfers.index'), 'icon' => 'mdi-bank-transfer'],
        ['label' => 'New Transfer', 'url' => '#', 'icon' => 'mdi-plus']
    ]
])

<style>
    .transfer-flow {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 30px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 10px;
        margin-bottom: 25px;
    }
    .bank-box {
        background: white;
        border: 2px solid #dee2e6;
        border-radius: 10px;
        padding: 20px;
        min-width: 280px;
        text-align: center;
        transition: all 0.3s ease;
    }
    .bank-box.from-bank {
        border-color: #dc3545;
    }
    .bank-box.from-bank.selected {
        border-color: #dc3545;
        box-shadow: 0 4px 15px rgba(220, 53, 69, 0.2);
    }
    .bank-box.to-bank {
        border-color: #28a745;
    }
    .bank-box.to-bank.selected {
        border-color: #28a745;
        box-shadow: 0 4px 15px rgba(40, 167, 69, 0.2);
    }
    .bank-box .bank-icon {
        font-size: 2.5rem;
        margin-bottom: 10px;
    }
    .bank-box .bank-name {
        font-size: 1.1rem;
        font-weight: 600;
        color: #333;
    }
    .bank-box .bank-balance {
        font-size: 0.9rem;
        color: #666;
        margin-top: 5px;
    }
    .transfer-arrow {
        font-size: 3rem;
        color: #007bff;
        margin: 0 40px;
        animation: pulse 1.5s infinite;
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    .amount-display {
        text-align: center;
        margin: 20px 0;
    }
    .amount-display .amount {
        font-size: 2rem;
        font-weight: 700;
        color: #007bff;
    }
    .method-card {
        border: 2px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .method-card:hover {
        border-color: #007bff;
        background: #f8f9fa;
    }
    .method-card.selected {
        border-color: #007bff;
        background: #e3f2fd;
    }
    .method-card input[type="radio"] {
        display: none;
    }
    .method-card .method-icon {
        font-size: 1.5rem;
        margin-right: 10px;
    }
    .method-card .method-name {
        font-weight: 600;
    }
    .method-card .method-desc {
        font-size: 0.85rem;
        color: #666;
    }
    .fee-section {
        background: #fff3cd;
        border-radius: 8px;
        padding: 15px;
        margin-top: 15px;
    }
</style>

<div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="mdi mdi-bank-transfer text-primary mr-2"></i>New Inter-Account Transfer</h4>
                <a href="{{ route('accounting.transfers.index') }}" class="btn btn-outline-secondary">
                    <i class="mdi mdi-arrow-left mr-1"></i> Back to List
                </a>
            </div>

            <form action="{{ route('accounting.transfers.store') }}" method="POST" id="transfer-form">
                @csrf

                <!-- Transfer Flow Visualization -->
                <div class="transfer-flow">
                    <div class="bank-box from-bank" id="from-bank-box">
                        <div class="bank-icon text-danger"><i class="mdi mdi-bank-minus"></i></div>
                        <div class="bank-name" id="from-bank-name">Select Source Bank</div>
                        <div class="bank-balance" id="from-bank-balance"></div>
                    </div>

                    <div class="transfer-arrow">
                        <i class="mdi mdi-arrow-right-bold"></i>
                    </div>

                    <div class="amount-display">
                        <div class="text-muted">Amount</div>
                        <div class="amount" id="amount-display">₦0.00</div>
                    </div>

                    <div class="transfer-arrow">
                        <i class="mdi mdi-arrow-right-bold"></i>
                    </div>

                    <div class="bank-box to-bank" id="to-bank-box">
                        <div class="bank-icon text-success"><i class="mdi mdi-bank-plus"></i></div>
                        <div class="bank-name" id="to-bank-name">Select Destination Bank</div>
                        <div class="bank-balance" id="to-bank-balance"></div>
                    </div>
                </div>

                <div class="row">
                    <!-- Left Column - Bank Selection -->
                    <div class="col-lg-6">
                        <div class="card-modern">
                            <div class="card-header bg-primary text-white">
                                <h3 class="card-title"><i class="mdi mdi-bank mr-2"></i>Transfer Details</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label>Source Bank (From) <span class="text-danger">*</span></label>
                                    <select name="from_bank_id" id="from_bank_id" class="form-control select2 @error('from_bank_id') is-invalid @enderror" required>
                                        <option value="">-- Select Source Bank --</option>
                                        @foreach($banks as $bank)
                                            <option value="{{ $bank->id }}"
                                                data-name="{{ $bank->bank_name }}"
                                                data-account="{{ $bank->account_number }}"
                                                {{ old('from_bank_id') == $bank->id ? 'selected' : '' }}>
                                                {{ $bank->bank_name }} - {{ $bank->account_number }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('from_bank_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label>Destination Bank (To) <span class="text-danger">*</span></label>
                                    <select name="to_bank_id" id="to_bank_id" class="form-control select2 @error('to_bank_id') is-invalid @enderror" required>
                                        <option value="">-- Select Destination Bank --</option>
                                        @foreach($banks as $bank)
                                            <option value="{{ $bank->id }}"
                                                data-name="{{ $bank->bank_name }}"
                                                data-account="{{ $bank->account_number }}"
                                                {{ old('to_bank_id') == $bank->id ? 'selected' : '' }}>
                                                {{ $bank->bank_name }} - {{ $bank->account_number }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('to_bank_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Transfer Date <span class="text-danger">*</span></label>
                                            <input type="date" name="transfer_date" class="form-control @error('transfer_date') is-invalid @enderror"
                                                value="{{ old('transfer_date', date('Y-m-d')) }}" required>
                                            @error('transfer_date')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Amount <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">₦</span>
                                                </div>
                                                <input type="number" name="amount" id="amount"
                                                    class="form-control @error('amount') is-invalid @enderror"
                                                    step="0.01" min="0.01" value="{{ old('amount') }}"
                                                    placeholder="0.00" required>
                                            </div>
                                            @error('amount')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Reference Number</label>
                                    <input type="text" name="reference" class="form-control @error('reference') is-invalid @enderror"
                                        value="{{ old('reference') }}" placeholder="e.g., Bank reference, cheque number">
                                    @error('reference')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label>Description <span class="text-danger">*</span></label>
                                    <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                                        rows="3" required placeholder="Purpose of the transfer">{{ old('description') }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - Method & Fees -->
                    <div class="col-lg-6">
                        <div class="card-modern">
                            <div class="card-header bg-info text-white">
                                <h3 class="card-title"><i class="mdi mdi-cogs mr-2"></i>Transfer Method</h3>
                            </div>
                            <div class="card-body">
                                <div class="method-options">
                                    <label class="method-card @if(old('transfer_method') == 'internal') selected @endif" data-method="internal">
                                        <input type="radio" name="transfer_method" value="internal" {{ old('transfer_method') == 'internal' ? 'checked' : '' }}>
                                        <div class="d-flex align-items-center">
                                            <span class="method-icon text-info"><i class="mdi mdi-bank"></i></span>
                                            <div>
                                                <div class="method-name">Internal Transfer</div>
                                                <div class="method-desc">Same bank branch or internal account</div>
                                            </div>
                                        </div>
                                    </label>

                                    <label class="method-card @if(old('transfer_method') == 'eft') selected @endif" data-method="eft">
                                        <input type="radio" name="transfer_method" value="eft" {{ old('transfer_method', 'eft') == 'eft' ? 'checked' : '' }}>
                                        <div class="d-flex align-items-center">
                                            <span class="method-icon text-success"><i class="mdi mdi-bank-transfer"></i></span>
                                            <div>
                                                <div class="method-name">EFT (Electronic Funds Transfer)</div>
                                                <div class="method-desc">Standard electronic transfer (1-3 business days)</div>
                                            </div>
                                        </div>
                                    </label>

                                    <label class="method-card @if(old('transfer_method') == 'wire') selected @endif" data-method="wire">
                                        <input type="radio" name="transfer_method" value="wire" {{ old('transfer_method') == 'wire' ? 'checked' : '' }}>
                                        <div class="d-flex align-items-center">
                                            <span class="method-icon text-primary"><i class="mdi mdi-flash"></i></span>
                                            <div>
                                                <div class="method-name">Wire Transfer</div>
                                                <div class="method-desc">Fast bank-to-bank transfer (same day)</div>
                                            </div>
                                        </div>
                                    </label>

                                    <label class="method-card @if(old('transfer_method') == 'rtgs') selected @endif" data-method="rtgs">
                                        <input type="radio" name="transfer_method" value="rtgs" {{ old('transfer_method') == 'rtgs' ? 'checked' : '' }}>
                                        <div class="d-flex align-items-center">
                                            <span class="method-icon text-dark"><i class="mdi mdi-speedometer"></i></span>
                                            <div>
                                                <div class="method-name">RTGS (Real-Time Gross Settlement)</div>
                                                <div class="method-desc">Real-time large value transfers</div>
                                            </div>
                                        </div>
                                    </label>

                                    <label class="method-card @if(old('transfer_method') == 'cheque') selected @endif" data-method="cheque">
                                        <input type="radio" name="transfer_method" value="cheque" {{ old('transfer_method') == 'cheque' ? 'checked' : '' }}>
                                        <div class="d-flex align-items-center">
                                            <span class="method-icon text-warning"><i class="mdi mdi-checkbook"></i></span>
                                            <div>
                                                <div class="method-name">Cheque</div>
                                                <div class="method-desc">Manager's cheque deposit (3-5 business days)</div>
                                            </div>
                                        </div>
                                    </label>

                                    <label class="method-card @if(old('transfer_method') == 'neft') selected @endif" data-method="neft">
                                        <input type="radio" name="transfer_method" value="neft" {{ old('transfer_method') == 'neft' ? 'checked' : '' }}>
                                        <div class="d-flex align-items-center">
                                            <span class="method-icon text-secondary"><i class="mdi mdi-clock-outline"></i></span>
                                            <div>
                                                <div class="method-name">NEFT (National EFT)</div>
                                                <div class="method-desc">Batch processed electronic transfer</div>
                                            </div>
                                        </div>
                                    </label>
                                </div>

                                @error('transfer_method')
                                    <div class="text-danger mt-2">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="card-modern">
                            <div class="card-header bg-warning">
                                <h3 class="card-title"><i class="mdi mdi-currency-usd mr-2"></i>Transfer Fees & Timing</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Transfer Fee</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">₦</span>
                                                </div>
                                                <input type="number" name="transfer_fee" id="transfer_fee"
                                                    class="form-control @error('transfer_fee') is-invalid @enderror"
                                                    step="0.01" min="0" value="{{ old('transfer_fee', 0) }}"
                                                    placeholder="0.00">
                                            </div>
                                            @error('transfer_fee')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Fee Expense Account</label>
                                            <select name="fee_account_id" id="fee_account_id" class="form-control select2 @error('fee_account_id') is-invalid @enderror">
                                                <option value="">-- Select Account --</option>
                                                @foreach($feeAccounts as $account)
                                                    <option value="{{ $account->id }}" {{ old('fee_account_id') == $account->id ? 'selected' : '' }}>
                                                        {{ $account->account_number }} - {{ $account->account_name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('fee_account_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Expected Clearance Date</label>
                                    <input type="date" name="expected_clearance_date" id="expected_clearance_date"
                                        class="form-control @error('expected_clearance_date') is-invalid @enderror"
                                        value="{{ old('expected_clearance_date') }}"
                                        min="{{ date('Y-m-d') }}">
                                    @error('expected_clearance_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">Auto-calculated based on transfer method</small>
                                </div>

                                <div class="form-group">
                                    <label>Additional Notes</label>
                                    <textarea name="notes" class="form-control @error('notes') is-invalid @enderror"
                                        rows="2" placeholder="Any additional notes or instructions">{{ old('notes') }}</textarea>
                                    @error('notes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="fee-section" id="total-summary">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Transfer Amount:</span>
                                        <span id="summary-amount">₦0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Transfer Fee:</span>
                                        <span id="summary-fee">₦0.00</span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between">
                                        <strong>Total Deducted from Source:</strong>
                                        <strong id="summary-total">₦0.00</strong>
                                    </div>
                                </div>

                                <!-- Journal Entry Preview -->
                                <div class="card bg-light mt-3">
                                    <div class="card-body py-2 px-3">
                                        <h6 class="mb-2"><i class="mdi mdi-book-open-variant mr-1"></i>Journal Entry Preview</h6>
                                        <small class="text-muted d-block mb-2">This entry will be created when transfer is cleared:</small>
                                        <table class="table table-sm mb-0" style="font-size: 0.85rem;">
                                            <thead style="background: #495057; color: white;">
                                                <tr>
                                                    <th style="width: 50%;">Account</th>
                                                    <th class="text-right" style="width: 25%;">Debit</th>
                                                    <th class="text-right" style="width: 25%;">Credit</th>
                                                </tr>
                                            </thead>
                                            <tbody id="jePreviewBody">
                                                <tr>
                                                    <td id="je-to-bank">Destination Bank</td>
                                                    <td class="text-right" id="je-to-debit">₦0.00</td>
                                                    <td class="text-right">-</td>
                                                </tr>
                                                <tr id="je-fee-row" style="display: none;">
                                                    <td id="je-fee-account">Bank Charges</td>
                                                    <td class="text-right" id="je-fee-debit">₦0.00</td>
                                                    <td class="text-right">-</td>
                                                </tr>
                                                <tr>
                                                    <td id="je-from-bank">Source Bank</td>
                                                    <td class="text-right">-</td>
                                                    <td class="text-right" id="je-from-credit">₦0.00</td>
                                                </tr>
                                            </tbody>
                                            <tfoot style="background: #f8f9fa; font-weight: 600;">
                                                <tr>
                                                    <td>TOTALS</td>
                                                    <td class="text-right" id="je-total-debit">₦0.00</td>
                                                    <td class="text-right" id="je-total-credit">₦0.00</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="row">
                    <div class="col-12">
                        <div class="card-modern">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="text-muted">
                                        <i class="mdi mdi-information-outline mr-1"></i>
                                        This transfer will be submitted for approval before processing.
                                    </div>
                                    <div>
                                        <a href="{{ route('accounting.transfers.index') }}" class="btn btn-secondary mr-2">
                                            <i class="mdi mdi-close mr-1"></i> Cancel
                                        </a>
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="mdi mdi-bank-transfer mr-1"></i> Submit Transfer Request
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap4',
        allowClear: true,
        width: '100%'
    });

    // Bank selection handlers
    $('#from_bank_id').on('change', function() {
        var selected = $(this).find(':selected');
        var name = selected.data('name') || 'Select Source Bank';
        var account = selected.data('account') || '';

        $('#from-bank-name').text(name);
        $('#from-bank-balance').text(account ? 'Account: ' + account : '');
        $('#from-bank-box').toggleClass('selected', !!$(this).val());
    });

    $('#to_bank_id').on('change', function() {
        var selected = $(this).find(':selected');
        var name = selected.data('name') || 'Select Destination Bank';
        var account = selected.data('account') || '';

        $('#to-bank-name').text(name);
        $('#to-bank-balance').text(account ? 'Account: ' + account : '');
        $('#to-bank-box').toggleClass('selected', !!$(this).val());
    });

    // Amount change handler
    $('#amount').on('input', function() {
        var amount = parseFloat($(this).val()) || 0;
        $('#amount-display').text('₦' + amount.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        updateSummary();
    });

    $('#transfer_fee').on('input', function() {
        updateSummary();
    });

    function updateSummary() {
        var amount = parseFloat($('#amount').val()) || 0;
        var fee = parseFloat($('#transfer_fee').val()) || 0;
        var total = amount + fee;

        $('#summary-amount').text('₦' + amount.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#summary-fee').text('₦' + fee.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#summary-total').text('₦' + total.toLocaleString('en-NG', {minimumFractionDigits: 2}));

        // Update JE Preview
        updateJePreview();
    }

    function updateJePreview() {
        var amount = parseFloat($('#amount').val()) || 0;
        var fee = parseFloat($('#transfer_fee').val()) || 0;
        var total = amount + fee;

        var fromBank = $('#from_bank_id option:selected').data('name') || 'Source Bank';
        var toBank = $('#to_bank_id option:selected').data('name') || 'Destination Bank';
        var feeAccount = $('#fee_account_id option:selected').text() || 'Bank Charges';

        $('#je-from-bank').text(fromBank);
        $('#je-to-bank').text(toBank);
        $('#je-to-debit').text('₦' + amount.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#je-from-credit').text('₦' + total.toLocaleString('en-NG', {minimumFractionDigits: 2}));

        if (fee > 0) {
            $('#je-fee-row').show();
            $('#je-fee-account').text(feeAccount.length > 30 ? feeAccount.substring(0, 30) + '...' : feeAccount);
            $('#je-fee-debit').text('₦' + fee.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        } else {
            $('#je-fee-row').hide();
        }

        $('#je-total-debit').text('₦' + total.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#je-total-credit').text('₦' + total.toLocaleString('en-NG', {minimumFractionDigits: 2}));
    }

    $('#from_bank_id, #to_bank_id, #fee_account_id').on('change', updateJePreview);

    // Transfer method selection
    $('.method-card').on('click', function() {
        $('.method-card').removeClass('selected');
        $(this).addClass('selected');
        $(this).find('input[type="radio"]').prop('checked', true);

        // Calculate expected clearance date
        var method = $(this).data('method');
        var clearanceDays = {
            'internal': 0,
            'eft': 2,
            'wire': 0,
            'rtgs': 0,
            'cheque': 4,
            'neft': 1
        };

        var days = clearanceDays[method] || 0;
        var clearanceDate = new Date();
        clearanceDate.setDate(clearanceDate.getDate() + days);

        // Skip weekends
        while (clearanceDate.getDay() === 0 || clearanceDate.getDay() === 6) {
            clearanceDate.setDate(clearanceDate.getDate() + 1);
        }

        $('#expected_clearance_date').val(clearanceDate.toISOString().split('T')[0]);
    });

    // Initialize if values already set (e.g., on form error)
    $('#from_bank_id, #to_bank_id').trigger('change');
    if ($('#amount').val()) {
        $('#amount').trigger('input');
    }
    if ($('input[name="transfer_method"]:checked').length) {
        $('input[name="transfer_method"]:checked').closest('.method-card').addClass('selected');
    } else {
        // Default to EFT
        $('input[name="transfer_method"][value="eft"]').closest('.method-card').click();
    }

    // Form validation
    $('#transfer-form').on('submit', function(e) {
        var fromBank = $('#from_bank_id').val();
        var toBank = $('#to_bank_id').val();

        if (fromBank && toBank && fromBank === toBank) {
            e.preventDefault();
            toastr.error('Source and destination banks cannot be the same');
            return false;
        }

        if (!$('input[name="transfer_method"]:checked').length) {
            e.preventDefault();
            toastr.error('Please select a transfer method');
            return false;
        }
    });
});
</script>
@endpush
