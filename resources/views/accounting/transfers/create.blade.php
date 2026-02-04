{{--
    Create Inter-Account Transfer
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 2
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'New Inter-Account Transfer')
@section('page_name', 'Accounting')
@section('subpage_name', 'New Transfer')

@push('styles')
<style>
    .transfer-flow {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 25px;
    }
    .bank-selector-card {
        background: white;
        border: 2px solid #dee2e6;
        border-radius: 12px;
        padding: 20px;
        transition: all 0.3s ease;
        height: 100%;
    }
    .bank-selector-card.from-bank {
        border-color: #dc354533;
    }
    .bank-selector-card.from-bank.has-selection {
        border-color: #dc3545;
        box-shadow: 0 4px 15px rgba(220, 53, 69, 0.15);
    }
    .bank-selector-card.to-bank {
        border-color: #28a74533;
    }
    .bank-selector-card.to-bank.has-selection {
        border-color: #28a745;
        box-shadow: 0 4px 15px rgba(40, 167, 69, 0.15);
    }
    .bank-selector-card .card-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
        margin-bottom: 12px;
    }
    .bank-selector-card .bank-icon {
        font-size: 2.5rem;
        margin-bottom: 10px;
    }
    .bank-selector-card .bank-info {
        min-height: 80px;
    }
    .bank-selector-card .bank-name {
        font-size: 1.1rem;
        font-weight: 600;
        color: #333;
    }
    .bank-selector-card .bank-details {
        font-size: 0.85rem;
        color: #666;
        margin-top: 5px;
    }
    .bank-selector-card .balance-display {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px dashed #dee2e6;
    }
    .amount-center {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 20px 10px;
    }
    .amount-center .transfer-arrows {
        font-size: 2rem;
        color: #007bff;
        margin: 10px 0;
    }
    .amount-center .amount-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: #007bff;
    }
    .method-card {
        border: 2px solid #dee2e6;
        border-radius: 8px;
        padding: 12px 15px;
        margin-bottom: 8px;
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
        font-size: 1.3rem;
        margin-right: 10px;
        width: 30px;
        text-align: center;
    }
    .method-card .method-name {
        font-weight: 600;
        font-size: 0.9rem;
    }
    .method-card .method-desc {
        font-size: 0.75rem;
        color: #666;
    }
    .fee-section {
        background: #fff3cd;
        border-radius: 8px;
        padding: 15px;
    }
    .card-modern {
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 20px;
    }
    /* Select2 styling */
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
    .select2-container {
        width: 100% !important;
    }
    .select2-container--bootstrap4 .select2-selection--single:focus,
    .select2-container--bootstrap4.select2-container--focus .select2-selection {
        border-color: #667eea !important;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25) !important;
    }
    .select2-container--bootstrap4 .select2-results__option--highlighted[aria-selected] {
        background-color: #667eea !important;
    }
</style>
@endpush

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Transfers', 'url' => route('accounting.transfers.index'), 'icon' => 'mdi-bank-transfer'],
        ['label' => 'New Transfer', 'url' => '#', 'icon' => 'mdi-plus']
    ]
])

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

        <!-- Transfer Flow - Bank Selection Cards -->
        <div class="transfer-flow">
            <div class="row align-items-stretch">
                <!-- FROM Bank Card -->
                <div class="col-lg-5">
                    <div class="bank-selector-card from-bank" id="from-bank-card">
                        <div class="card-label text-danger">
                            <i class="mdi mdi-arrow-up-bold mr-1"></i> From (Source)
                        </div>
                        <div class="text-center">
                            <div class="bank-icon text-danger"><i class="mdi mdi-bank-minus"></i></div>
                        </div>
                        <select name="from_bank_id" id="from_bank_id" class="form-control select2 @error('from_bank_id') is-invalid @enderror" required>
                            <option value="">-- Select Source Bank --</option>
                            @foreach($banks as $bank)
                                <option value="{{ $bank->id }}"
                                    data-name="{{ $bank->name }}"
                                    data-account="{{ $bank->account_number }}"
                                    data-balance="{{ $bank->current_balance }}"
                                    data-available="{{ $bank->available_balance }}"
                                    {{ old('from_bank_id') == $bank->id ? 'selected' : '' }}>
                                    {{ $bank->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('from_bank_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <div class="bank-info text-center mt-3" id="from-bank-info">
                            <div class="text-muted">Select a source bank</div>
                        </div>
                    </div>
                </div>

                <!-- Center - Amount & Arrows -->
                <div class="col-lg-2">
                    <div class="amount-center h-100">
                        <div class="transfer-arrows">
                            <i class="mdi mdi-arrow-right-bold d-none d-lg-inline"></i>
                            <i class="mdi mdi-arrow-down-bold d-lg-none"></i>
                        </div>
                        <div class="text-muted small">Transfer</div>
                        <div class="amount-value" id="amount-display">₦0.00</div>
                        <div class="transfer-arrows">
                            <i class="mdi mdi-arrow-right-bold d-none d-lg-inline"></i>
                            <i class="mdi mdi-arrow-down-bold d-lg-none"></i>
                        </div>
                    </div>
                </div>

                <!-- TO Bank Card -->
                <div class="col-lg-5">
                    <div class="bank-selector-card to-bank" id="to-bank-card">
                        <div class="card-label text-success">
                            <i class="mdi mdi-arrow-down-bold mr-1"></i> To (Destination)
                        </div>
                        <div class="text-center">
                            <div class="bank-icon text-success"><i class="mdi mdi-bank-plus"></i></div>
                        </div>
                        <select name="to_bank_id" id="to_bank_id" class="form-control select2 @error('to_bank_id') is-invalid @enderror" required>
                            <option value="">-- Select Destination Bank --</option>
                            @foreach($banks as $bank)
                                <option value="{{ $bank->id }}"
                                    data-name="{{ $bank->name }}"
                                    data-account="{{ $bank->account_number }}"
                                    data-balance="{{ $bank->current_balance }}"
                                    {{ old('to_bank_id') == $bank->id ? 'selected' : '' }}>
                                    {{ $bank->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('to_bank_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <div class="bank-info text-center mt-3" id="to-bank-info">
                            <div class="text-muted">Select a destination bank</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Form Row -->
        <div class="row">
            <!-- Left Column - Transfer Details -->
            <div class="col-lg-6">
                <div class="card-modern">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="mdi mdi-file-document-edit mr-2"></i>Transfer Details</h5>
                    </div>
                    <div class="card-body">
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
                                value="{{ old('reference') }}" placeholder="Bank reference, cheque number, etc.">
                            @error('reference')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label>Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                                rows="2" required placeholder="Purpose of the transfer">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <hr>

                        <h6 class="text-muted mb-3"><i class="mdi mdi-currency-usd mr-1"></i>Fees & Timing</h6>
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
                                    <label>Fee Account</label>
                                    <select name="fee_account_id" id="fee_account_id" class="form-control select2 @error('fee_account_id') is-invalid @enderror">
                                        <option value="">-- Select Account --</option>
                                        @foreach($feeAccounts as $account)
                                            <option value="{{ $account->id }}" {{ old('fee_account_id') == $account->id ? 'selected' : '' }}>
                                                {{ $account->code }} - {{ $account->name }}
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

                        <div class="form-group mb-0">
                            <label>Notes</label>
                            <textarea name="notes" class="form-control @error('notes') is-invalid @enderror"
                                rows="2" placeholder="Additional notes (optional)">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Method & Preview -->
            <div class="col-lg-6">
                <div class="card-modern">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0"><i class="mdi mdi-cogs mr-2"></i>Transfer Method</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="method-card @if(old('transfer_method') == 'internal') selected @endif" data-method="internal">
                                    <input type="radio" name="transfer_method" value="internal" {{ old('transfer_method') == 'internal' ? 'checked' : '' }}>
                                    <div class="d-flex align-items-center">
                                        <span class="method-icon text-info"><i class="mdi mdi-bank"></i></span>
                                        <div>
                                            <div class="method-name">Internal</div>
                                            <div class="method-desc">Same bank</div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="method-card @if(old('transfer_method', 'eft') == 'eft') selected @endif" data-method="eft">
                                    <input type="radio" name="transfer_method" value="eft" {{ old('transfer_method', 'eft') == 'eft' ? 'checked' : '' }}>
                                    <div class="d-flex align-items-center">
                                        <span class="method-icon text-success"><i class="mdi mdi-bank-transfer"></i></span>
                                        <div>
                                            <div class="method-name">EFT</div>
                                            <div class="method-desc">1-3 days</div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="method-card @if(old('transfer_method') == 'wire') selected @endif" data-method="wire">
                                    <input type="radio" name="transfer_method" value="wire" {{ old('transfer_method') == 'wire' ? 'checked' : '' }}>
                                    <div class="d-flex align-items-center">
                                        <span class="method-icon text-primary"><i class="mdi mdi-flash"></i></span>
                                        <div>
                                            <div class="method-name">Wire</div>
                                            <div class="method-desc">Same day</div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="method-card @if(old('transfer_method') == 'rtgs') selected @endif" data-method="rtgs">
                                    <input type="radio" name="transfer_method" value="rtgs" {{ old('transfer_method') == 'rtgs' ? 'checked' : '' }}>
                                    <div class="d-flex align-items-center">
                                        <span class="method-icon text-dark"><i class="mdi mdi-lightning-bolt"></i></span>
                                        <div>
                                            <div class="method-name">RTGS</div>
                                            <div class="method-desc">Real-time</div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="method-card @if(old('transfer_method') == 'neft') selected @endif" data-method="neft">
                                    <input type="radio" name="transfer_method" value="neft" {{ old('transfer_method') == 'neft' ? 'checked' : '' }}>
                                    <div class="d-flex align-items-center">
                                        <span class="method-icon text-secondary"><i class="mdi mdi-swap-horizontal"></i></span>
                                        <div>
                                            <div class="method-name">NEFT</div>
                                            <div class="method-desc">Next day</div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="method-card @if(old('transfer_method') == 'cheque') selected @endif" data-method="cheque">
                                    <input type="radio" name="transfer_method" value="cheque" {{ old('transfer_method') == 'cheque' ? 'checked' : '' }}>
                                    <div class="d-flex align-items-center">
                                        <span class="method-icon text-warning"><i class="mdi mdi-checkbook"></i></span>
                                        <div>
                                            <div class="method-name">Cheque</div>
                                            <div class="method-desc">3-5 days</div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        @error('transfer_method')
                            <div class="text-danger mt-2">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <!-- Summary & JE Preview -->
                <div class="card-modern">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="card-title mb-0"><i class="mdi mdi-calculator mr-2"></i>Transfer Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="fee-section mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Transfer Amount:</span>
                                <span id="summary-amount">₦0.00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Transfer Fee:</span>
                                <span id="summary-fee">₦0.00</span>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between">
                                <strong>Total from Source:</strong>
                                <strong id="summary-total">₦0.00</strong>
                            </div>
                        </div>

                        <!-- Journal Entry Preview -->
                        <h6 class="mb-2"><i class="mdi mdi-book-open-variant mr-1"></i>Journal Entry Preview</h6>
                        <small class="text-muted d-block mb-2">Created when transfer clears:</small>
                        <table class="table table-sm mb-0" style="font-size: 0.8rem;">
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

        <!-- Form Actions -->
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
                            <i class="mdi mdi-bank-transfer mr-1"></i> Submit Transfer
                        </button>
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

    // From bank selection handler
    $('#from_bank_id').on('change', function() {
        var selected = $(this).find(':selected');
        var name = selected.data('name');
        var account = selected.data('account');
        var balance = parseFloat(selected.data('balance')) || 0;
        var available = parseFloat(selected.data('available')) || 0;

        if (name) {
            $('#from-bank-info').html(
                '<div class="bank-name">' + name + '</div>' +
                '<div class="bank-details">Acct: ' + account + '</div>' +
                '<div class="balance-display">' +
                    '<div class="text-danger font-weight-bold">₦' + balance.toLocaleString('en-NG', {minimumFractionDigits: 2}) + '</div>' +
                    '<small class="text-muted">Available: ₦' + available.toLocaleString('en-NG', {minimumFractionDigits: 2}) + '</small>' +
                '</div>'
            );
            $('#from-bank-card').addClass('has-selection');
        } else {
            $('#from-bank-info').html('<div class="text-muted">Select a source bank</div>');
            $('#from-bank-card').removeClass('has-selection');
        }
        updateJePreview();
    });

    // To bank selection handler
    $('#to_bank_id').on('change', function() {
        var selected = $(this).find(':selected');
        var name = selected.data('name');
        var account = selected.data('account');
        var balance = parseFloat(selected.data('balance')) || 0;

        if (name) {
            $('#to-bank-info').html(
                '<div class="bank-name">' + name + '</div>' +
                '<div class="bank-details">Acct: ' + account + '</div>' +
                '<div class="balance-display">' +
                    '<div class="text-success font-weight-bold">₦' + balance.toLocaleString('en-NG', {minimumFractionDigits: 2}) + '</div>' +
                '</div>'
            );
            $('#to-bank-card').addClass('has-selection');
        } else {
            $('#to-bank-info').html('<div class="text-muted">Select a destination bank</div>');
            $('#to-bank-card').removeClass('has-selection');
        }
        updateJePreview();
    });

    // Amount change handler
    $('#amount').on('input', function() {
        var amount = parseFloat($(this).val()) || 0;
        $('#amount-display').text('₦' + amount.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        updateSummary();
    });

    $('#transfer_fee').on('input', updateSummary);

    function updateSummary() {
        var amount = parseFloat($('#amount').val()) || 0;
        var fee = parseFloat($('#transfer_fee').val()) || 0;
        var total = amount + fee;

        $('#summary-amount').text('₦' + amount.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#summary-fee').text('₦' + fee.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#summary-total').text('₦' + total.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        updateJePreview();
    }

    function updateJePreview() {
        var amount = parseFloat($('#amount').val()) || 0;
        var fee = parseFloat($('#transfer_fee').val()) || 0;
        var total = amount + fee;

        var fromBank = $('#from_bank_id option:selected').data('name') || 'Source Bank';
        var toBank = $('#to_bank_id option:selected').data('name') || 'Destination Bank';

        var feeAccountOption = $('#fee_account_id option:selected');
        var feeAccount = feeAccountOption.val() ? feeAccountOption.text().trim() : 'Bank Charges';
        if (feeAccount.startsWith('--')) feeAccount = 'Bank Charges';

        $('#je-from-bank').text(fromBank);
        $('#je-to-bank').text(toBank);
        $('#je-to-debit').text('₦' + amount.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#je-from-credit').text('₦' + total.toLocaleString('en-NG', {minimumFractionDigits: 2}));

        if (fee > 0) {
            $('#je-fee-row').show();
            $('#je-fee-account').text(feeAccount.length > 35 ? feeAccount.substring(0, 35) + '...' : feeAccount);
            $('#je-fee-debit').text('₦' + fee.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        } else {
            $('#je-fee-row').hide();
        }

        $('#je-total-debit').text('₦' + total.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#je-total-credit').text('₦' + total.toLocaleString('en-NG', {minimumFractionDigits: 2}));
    }

    $('#fee_account_id').on('change', updateJePreview);

    // Transfer method selection
    $('.method-card').on('click', function() {
        $('.method-card').removeClass('selected');
        $(this).addClass('selected');
        $(this).find('input[type="radio"]').prop('checked', true);

        var method = $(this).data('method');
        var clearanceDays = {
            'internal': 0, 'eft': 2, 'wire': 0, 'rtgs': 0, 'cheque': 4, 'neft': 1
        };

        var days = clearanceDays[method] || 0;
        var clearanceDate = new Date();
        clearanceDate.setDate(clearanceDate.getDate() + days);

        while (clearanceDate.getDay() === 0 || clearanceDate.getDay() === 6) {
            clearanceDate.setDate(clearanceDate.getDate() + 1);
        }

        $('#expected_clearance_date').val(clearanceDate.toISOString().split('T')[0]);
    });

    // Initialize on load
    $('#from_bank_id, #to_bank_id').trigger('change');
    if ($('#amount').val()) $('#amount').trigger('input');

    if ($('input[name="transfer_method"]:checked').length) {
        $('input[name="transfer_method"]:checked').closest('.method-card').addClass('selected');
    } else {
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
