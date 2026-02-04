@extends('admin.layouts.app')
@section('title', 'New Disbursement')
@section('page_name', 'Accounting')
@section('subpage_name', 'Disbursement')

@push('styles')
<style>
    /* Select2 styling for Bootstrap 4 consistency */
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
    .select2-container--bootstrap4 .select2-selection--single:focus {
        border-color: #667eea !important;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25) !important;
    }
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
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Petty Cash', 'url' => route('accounting.petty-cash.index'), 'icon' => 'mdi-cash-register'],
    ['label' => $fund->fund_name, 'url' => route('accounting.petty-cash.funds.show', $fund), 'icon' => 'mdi-wallet'],
    ['label' => 'Disbursement', 'url' => '#', 'icon' => 'mdi-cash-minus']
]])

<div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Fund Info Card -->
                <div class="card-modern mb-4">
                    <div class="card-body py-3">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <h6 class="mb-0 text-muted">Fund</h6>
                                <strong>{{ $fund->fund_name }}</strong>
                            </div>
                            <div class="col-md-4 text-center">
                                <h6 class="mb-0 text-muted">Available Balance</h6>
                                <strong class="text-success">₦{{ number_format($fund->current_balance, 2) }}</strong>
                            </div>
                            <div class="col-md-4 text-right">
                                <h6 class="mb-0 text-muted">Transaction Limit</h6>
                                <strong class="text-warning">₦{{ number_format($fund->transaction_limit, 2) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-modern card-modern">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="mdi mdi-cash-minus mr-2 text-danger"></i>New Disbursement</h5>
                    </div>
                    <div class="card-body">
                        @if(session('error'))
                            <div class="alert alert-danger">{{ session('error') }}</div>
                        @endif

                        <form action="{{ route('accounting.petty-cash.disbursement.store', $fund) }}" method="POST">
                            @csrf

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Amount (₦) <span class="text-danger">*</span></label>
                                        <input type="number" name="amount" id="amount"
                                               class="form-control form-control-lg @error('amount') is-invalid @enderror"
                                               value="{{ old('amount') }}"
                                               step="0.01" min="0.01"
                                               max="{{ $fund->transaction_limit }}"
                                               required
                                               placeholder="0.00">
                                        @error('amount')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="form-text text-muted">
                                            Max: ₦{{ number_format($fund->transaction_limit, 2) }}
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Transaction Date <span class="text-danger">*</span></label>
                                        <input type="date" name="transaction_date"
                                               class="form-control form-control-lg @error('transaction_date') is-invalid @enderror"
                                               value="{{ old('transaction_date', date('Y-m-d')) }}" required>
                                        @error('transaction_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Expense Account <span class="text-danger">*</span></label>
                                <select name="expense_account_id" class="form-control select2 @error('expense_account_id') is-invalid @enderror" required>
                                    <option value="">Select Expense Account</option>
                                    @foreach($expenseAccounts as $account)
                                        <option value="{{ $account->id }}" {{ old('expense_account_id') == $account->id ? 'selected' : '' }}>
                                            {{ $account->code }} - {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('expense_account_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Description <span class="text-danger">*</span></label>
                                <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                                          rows="3" required placeholder="Describe the purpose of this disbursement...">{{ old('description') }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Payee Name</label>
                                        <input type="text" name="payee_name"
                                               class="form-control @error('payee_name') is-invalid @enderror"
                                               value="{{ old('payee_name') }}"
                                               placeholder="Who received the funds?">
                                        @error('payee_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Receipt Number</label>
                                        <input type="text" name="receipt_number"
                                               class="form-control @error('receipt_number') is-invalid @enderror"
                                               value="{{ old('receipt_number') }}"
                                               placeholder="Receipt/Invoice number if available">
                                        @error('receipt_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Expense Category</label>
                                <select name="expense_category" class="form-control @error('expense_category') is-invalid @enderror">
                                    <option value="">Select Category (Optional)</option>
                                    <option value="office_supplies" {{ old('expense_category') == 'office_supplies' ? 'selected' : '' }}>Office Supplies</option>
                                    <option value="travel" {{ old('expense_category') == 'travel' ? 'selected' : '' }}>Travel & Transport</option>
                                    <option value="meals" {{ old('expense_category') == 'meals' ? 'selected' : '' }}>Meals & Entertainment</option>
                                    <option value="maintenance" {{ old('expense_category') == 'maintenance' ? 'selected' : '' }}>Repairs & Maintenance</option>
                                    <option value="utilities" {{ old('expense_category') == 'utilities' ? 'selected' : '' }}>Utilities</option>
                                    <option value="miscellaneous" {{ old('expense_category') == 'miscellaneous' ? 'selected' : '' }}>Miscellaneous</option>
                                </select>
                            </div>

                            @if($fund->requires_approval && $fund->approval_threshold > 0)
                                <div class="alert alert-info">
                                    <i class="mdi mdi-information mr-2"></i>
                                    Disbursements below ₦{{ number_format($fund->approval_threshold, 2) }} will be auto-approved.
                                </div>
                            @elseif($fund->requires_approval)
                                <div class="alert alert-warning">
                                    <i class="mdi mdi-alert mr-2"></i>
                                    This disbursement will require approval before processing.
                                </div>
                            @endif

                            <!-- Journal Entry Preview -->
                            <div class="card bg-light mt-3">
                                <div class="card-body py-2 px-3">
                                    <h6 class="mb-2"><i class="mdi mdi-book-open-variant mr-1"></i>Journal Entry Preview</h6>
                                    <small class="text-muted d-block mb-2">This entry will be created when disbursement is approved:</small>
                                    <table class="table table-sm mb-0" style="font-size: 0.85rem;">
                                        <thead style="background: #495057; color: white;">
                                            <tr>
                                                <th style="width: 50%;">Account</th>
                                                <th class="text-right" style="width: 25%;">Debit</th>
                                                <th class="text-right" style="width: 25%;">Credit</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td id="je-expense-account">Expense Account</td>
                                                <td class="text-right" id="je-debit">₦0.00</td>
                                                <td class="text-right">-</td>
                                            </tr>
                                            <tr>
                                                <td>Petty Cash - {{ $fund->name }}</td>
                                                <td class="text-right">-</td>
                                                <td class="text-right" id="je-credit">₦0.00</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <hr class="my-4">

                            <div class="d-flex justify-content-between">
                                <a href="{{ route('accounting.petty-cash.funds.show', $fund) }}" class="btn btn-secondary">
                                    <i class="mdi mdi-arrow-left"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-danger">
                                    <i class="mdi mdi-cash-minus"></i> Create Disbursement
                                </button>
                            </div>
                        </form>
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

    // Validate amount against transaction limit
    $('#amount').on('change', function() {
        var amount = parseFloat($(this).val()) || 0;
        var limit = {{ $fund->transaction_limit }};
        var balance = {{ $fund->current_balance }};

        if (amount > limit) {
            toastr.warning('Amount exceeds transaction limit of ₦' + limit.toLocaleString());
            $(this).val(limit);
        }

        if (amount > balance) {
            toastr.warning('Amount exceeds available balance of ₦' + balance.toLocaleString());
        }

        updateJePreview();
    });

    // Update JE preview
    function updateJePreview() {
        var amount = parseFloat($('#amount').val()) || 0;
        var expenseAccount = $('select[name="expense_account_id"] option:selected').text() || 'Expense Account';

        $('#je-expense-account').text(expenseAccount);
        $('#je-debit').text('₦' + amount.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#je-credit').text('₦' + amount.toLocaleString('en-NG', {minimumFractionDigits: 2}));
    }

    $('select[name="expense_account_id"]').on('change', updateJePreview);
    $('#amount').on('input', updateJePreview);
    updateJePreview();
});
</script>
@endpush
