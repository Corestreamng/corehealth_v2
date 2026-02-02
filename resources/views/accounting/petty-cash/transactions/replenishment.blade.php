@extends('admin.layouts.app')
@section('title', 'Replenish Fund')
@section('page_name', 'Accounting')
@section('subpage_name', 'Replenishment')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Petty Cash', 'url' => route('accounting.petty-cash.index'), 'icon' => 'mdi-cash-register'],
    ['label' => $fund->fund_name, 'url' => route('accounting.petty-cash.funds.show', $fund), 'icon' => 'mdi-wallet'],
    ['label' => 'Replenishment', 'url' => '#', 'icon' => 'mdi-cash-plus']
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
                                <h6 class="mb-0 text-muted">Current Balance</h6>
                                <strong class="{{ $fund->current_balance < ($fund->fund_limit * 0.2) ? 'text-danger' : 'text-success' }}">
                                    ₦{{ number_format($fund->current_balance, 2) }}
                                </strong>
                            </div>
                            <div class="col-md-4 text-right">
                                <h6 class="mb-0 text-muted">Fund Limit</h6>
                                <strong>₦{{ number_format($fund->fund_limit, 2) }}</strong>
                            </div>
                        </div>

                        @php
                            $utilizationPct = $fund->fund_limit > 0 ? (($fund->fund_limit - $fund->current_balance) / $fund->fund_limit) * 100 : 0;
                        @endphp
                        <div class="progress mt-3" style="height: 10px;">
                            <div class="progress-bar {{ $utilizationPct > 80 ? 'bg-danger' : ($utilizationPct > 50 ? 'bg-warning' : 'bg-success') }}"
                                 style="width: {{ $utilizationPct }}%"></div>
                        </div>
                        <small class="text-muted">{{ number_format($utilizationPct, 1) }}% utilized</small>
                    </div>
                </div>

                <div class="card-modern card-modern">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="mdi mdi-cash-plus mr-2 text-success"></i>Replenish Fund</h5>
                    </div>
                    <div class="card-body">
                        @if(session('error'))
                            <div class="alert alert-danger">{{ session('error') }}</div>
                        @endif

                        <form action="{{ route('accounting.petty-cash.replenishment.store', $fund) }}" method="POST">
                            @csrf

                            <div class="form-group">
                                <label>Replenishment Amount (₦) <span class="text-danger">*</span></label>
                                <input type="number" name="amount" id="amount"
                                       class="form-control form-control-lg @error('amount') is-invalid @enderror"
                                       value="{{ old('amount', $suggestedAmount) }}"
                                       step="0.01" min="0.01"
                                       required
                                       placeholder="0.00">
                                @error('amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">
                                    <i class="mdi mdi-lightbulb-on text-warning"></i>
                                    Suggested amount to bring balance back to limit:
                                    <strong class="text-success">₦{{ number_format($suggestedAmount, 2) }}</strong>
                                    <button type="button" class="btn btn-link btn-sm p-0 ml-2" id="use-suggested">Use this amount</button>
                                </small>
                            </div>

                            <!-- Payment Source Selection -->
                            <div class="card-modern bg-light mb-4">
                                <div class="card-body">
                                    <h6 class="mb-3"><i class="mdi mdi-cash-multiple mr-2"></i>Replenishment Source <span class="text-danger">*</span></h6>

                                    <div class="form-group mb-3">
                                        <label>Payment Method</label>
                                        <select name="payment_method" id="payment_method" class="form-control @error('payment_method') is-invalid @enderror" required>
                                            <option value="">-- Select Source --</option>
                                            <option value="cash" {{ old('payment_method') == 'cash' ? 'selected' : '' }}>
                                                <i class="mdi mdi-cash"></i> Cash (from Cash in Hand)
                                            </option>
                                            <option value="bank_transfer" {{ old('payment_method', 'bank_transfer') == 'bank_transfer' ? 'selected' : '' }}>
                                                <i class="mdi mdi-bank"></i> Bank Transfer
                                            </option>
                                        </select>
                                        @error('payment_method')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="form-text text-muted">
                                            Select where the replenishment funds will be drawn from.
                                        </small>
                                    </div>

                                    <div class="form-group mb-0" id="bank_selection_group" style="{{ old('payment_method', 'bank_transfer') == 'bank_transfer' ? '' : 'display:none;' }}">
                                        <label>Select Bank <span class="text-danger">*</span></label>
                                        <select name="bank_id" id="bank_id" class="form-control @error('bank_id') is-invalid @enderror">
                                            <option value="">-- Select Bank --</option>
                                            @foreach($banks as $bank)
                                                <option value="{{ $bank->id }}" {{ old('bank_id') == $bank->id ? 'selected' : '' }}>
                                                    {{ $bank->name }} ({{ $bank->account_number }})
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('bank_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="form-text text-muted">
                                            <i class="mdi mdi-information-outline"></i>
                                            The selected bank's GL account will be credited.
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Description <span class="text-danger">*</span></label>
                                <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                                          rows="3" required placeholder="Reason for replenishment...">{{ old('description', 'Replenishment of petty cash fund') }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Summary -->
                            <div class="card-modern bg-light mb-4">
                                <div class="card-body">
                                    <h6 class="mb-3"><i class="mdi mdi-calculator mr-2"></i>Summary</h6>
                                    <div class="row">
                                        <div class="col-6">
                                            <p class="mb-1">Current Balance:</p>
                                            <p class="mb-1">Replenishment:</p>
                                            <p class="mb-1">Source:</p>
                                            <p class="mb-0"><strong>New Balance:</strong></p>
                                        </div>
                                        <div class="col-6 text-right">
                                            <p class="mb-1">₦{{ number_format($fund->current_balance, 2) }}</p>
                                            <p class="mb-1 text-success">+ ₦<span id="replenish-amount">{{ number_format($suggestedAmount, 2) }}</span></p>
                                            <p class="mb-1" id="source-display"><span class="badge badge-info">Bank Transfer</span></p>
                                            <p class="mb-0"><strong>₦<span id="new-balance">{{ number_format($fund->current_balance + $suggestedAmount, 2) }}</span></strong></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <i class="mdi mdi-information mr-2"></i>
                                This replenishment will require approval before the funds are credited to the petty cash account.
                            </div>

                            <hr class="my-4">

                            <div class="d-flex justify-content-between">
                                <a href="{{ route('accounting.petty-cash.funds.show', $fund) }}" class="btn btn-secondary">
                                    <i class="mdi mdi-arrow-left"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="mdi mdi-cash-plus"></i> Create Replenishment Request
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
    var currentBalance = {{ $fund->current_balance }};
    var fundLimit = {{ $fund->fund_limit }};

    // Use suggested amount
    $('#use-suggested').click(function() {
        $('#amount').val({{ $suggestedAmount }}).trigger('change');
    });

    // Update summary on amount change
    $('#amount').on('change keyup', function() {
        var amount = parseFloat($(this).val()) || 0;
        var newBalance = currentBalance + amount;

        $('#replenish-amount').text(amount.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#new-balance').text(newBalance.toLocaleString('en-NG', {minimumFractionDigits: 2}));

        if (newBalance > fundLimit) {
            toastr.warning('New balance will exceed fund limit of ₦' + fundLimit.toLocaleString());
        }
    });

    // Toggle bank selection based on payment method
    $('#payment_method').on('change', function() {
        var method = $(this).val();
        if (method === 'bank_transfer') {
            $('#bank_selection_group').slideDown();
            $('#bank_id').prop('required', true);
            $('#source-display').html('<span class="badge badge-info">Bank Transfer</span>');
        } else if (method === 'cash') {
            $('#bank_selection_group').slideUp();
            $('#bank_id').prop('required', false).val('');
            $('#source-display').html('<span class="badge badge-success">Cash in Hand</span>');
        } else {
            $('#bank_selection_group').slideUp();
            $('#bank_id').prop('required', false).val('');
            $('#source-display').html('<span class="badge badge-secondary">Not selected</span>');
        }
    });

    // Update source display when bank is selected
    $('#bank_id').on('change', function() {
        var bankName = $(this).find('option:selected').text();
        if (bankName && bankName !== '-- Select Bank --') {
            $('#source-display').html('<span class="badge badge-info">' + bankName + '</span>');
        }
    });

    // Trigger initial state
    $('#payment_method').trigger('change');
});
</script>
@endpush
