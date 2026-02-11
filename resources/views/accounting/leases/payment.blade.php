@extends('admin.layouts.app')
@section('title', 'Record Lease Payment')
@section('page_name', 'Accounting')
@section('subpage_name', 'Record Payment')

@push('styles')
<style>
    /* Select2 Fixes for consistency */
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
    .select2-container--bootstrap4 .select2-selection--single .select2-selection__placeholder {
        color: #6c757d !important;
    }
    .select2-container {
        width: 100% !important;
    }
    .select2-dropdown {
        border-color: #ced4da !important;
    }
    .select2-container--bootstrap4 .select2-results__option--highlighted[aria-selected] {
        background-color: #667eea !important;
    }

    /* Page-specific styles */
    .payment-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 0.5rem;
        padding: 1.5rem;
    }
    .preview-sidebar {
        position: sticky;
        top: 20px;
    }
    .balance-card {
        border-left: 4px solid #6c757d;
        transition: all 0.3s ease;
    }
    .balance-card.liability {
        border-left-color: #dc3545;
    }
    .balance-card.asset {
        border-left-color: #28a745;
    }
    .je-preview-table tbody tr {
        transition: background-color 0.2s ease;
    }
    .variance-warning {
        background-color: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 0.375rem;
        padding: 0.75rem;
        margin-bottom: 1rem;
    }
    .variance-warning.over {
        background-color: #f8d7da;
        border-color: #dc3545;
    }
    .variance-warning.under {
        background-color: #d1ecf1;
        border-color: #17a2b8;
    }
    .scheduled-amount-hint {
        font-size: 0.85rem;
        color: #6c757d;
    }
    .dynamic-value {
        font-weight: 600;
        transition: color 0.3s ease;
    }
    .dynamic-value.changed {
        color: #007bff;
    }
    .breakdown-item {
        display: flex;
        justify-content: space-between;
        padding: 0.5rem 0;
        border-bottom: 1px dashed #dee2e6;
    }
    .breakdown-item:last-child {
        border-bottom: none;
    }
    .value-card {
        border-left: 4px solid;
        transition: transform 0.2s;
    }
    .value-card:hover {
        transform: translateX(3px);
    }
    .value-card.asset { border-left-color: #0dcaf0; }
    .value-card.liability { border-left-color: #ffc107; }
</style>
@endpush

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Leases', 'url' => route('accounting.leases.index'), 'icon' => 'mdi-file-document-edit'],
    ['label' => $lease->lease_number, 'url' => route('accounting.leases.show', $lease->id), 'icon' => 'mdi-eye'],
    ['label' => 'Record Payment', 'url' => '#', 'icon' => 'mdi-cash-multiple']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        {{-- Alert Boxes --}}
        <div id="alert-container">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-check-circle mr-2"></i>{{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-alert-circle mr-2"></i>{{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-alert mr-2"></i>{{ session('warning') }}
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-alert-circle mr-2"></i>
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            @endif
        </div>

        @php
            $typeBadges = [
                'operating' => 'badge-secondary',
                'finance' => 'badge-primary',
                'short_term' => 'badge-info',
                'low_value' => 'badge-light',
            ];
            $isExempt = in_array($lease->lease_type, ['short_term', 'low_value']);
        @endphp

        {{-- Lease Header Card --}}
        <div class="card-modern mb-4">
            <div class="payment-header">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h4 class="mb-2">
                            <i class="mdi mdi-cash-multiple mr-2"></i>Record Payment
                            <span class="badge {{ $typeBadges[$lease->lease_type] ?? 'badge-secondary' }} ml-2">
                                {{ ucfirst(str_replace('_', ' ', $lease->lease_type)) }}
                            </span>
                        </h4>
                        <h5 class="mb-1 font-weight-normal">{{ $lease->leased_item }}</h5>
                        <p class="mb-0 small opacity-75">{{ $lease->lease_number }} | {{ $lease->lessor_name ?: 'N/A' }}</p>
                    </div>
                    <div class="text-right">
                        <a href="{{ route('accounting.leases.show', $lease->id) }}" class="btn btn-light btn-sm">
                            <i class="mdi mdi-arrow-left mr-1"></i>Back to Lease
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Current Payment Status Alert --}}
        @if($nextPayment)
            <div class="alert alert-info mb-4">
                <div class="d-flex align-items-center">
                    <i class="mdi mdi-information mdi-24px mr-3"></i>
                    <div>
                        <strong>Scheduled Payment #{{ $nextPayment->payment_number ?? 'N/A' }}</strong>
                        <p class="mb-0 mt-1">
                            Due: <strong>{{ $nextPayment->due_date ? \Carbon\Carbon::parse($nextPayment->due_date)->format('M d, Y') : 'N/A' }}</strong> |
                            Amount: <strong>₦{{ number_format($nextPayment->payment_amount ?? 0, 2) }}</strong>
                            @if(!$isExempt)
                                (Principal: ₦{{ number_format($nextPayment->principal_portion ?? 0, 2) }} + Interest: ₦{{ number_format($nextPayment->interest_portion ?? 0, 2) }})
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        @else
            <div class="alert alert-warning mb-4">
                <i class="mdi mdi-alert mr-2"></i>
                No pending payments found. All scheduled payments may have been completed.
            </div>
        @endif

        {{-- Main Two-Column Layout --}}
        <form action="{{ route('accounting.leases.payment.store', $lease->id) }}" method="POST" id="paymentForm">
            @csrf
            @if($nextPayment)
                <input type="hidden" name="schedule_id" value="{{ $nextPayment->id }}">
            @endif
            <div class="row">
                {{-- LEFT COLUMN: Payment Form --}}
                <div class="col-lg-6">
                    <div class="card-modern mb-4">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="mdi mdi-pencil mr-2"></i>Payment Details</h6>
                        </div>
                        <div class="card-body">
                            {{-- Payment Date --}}
                            <div class="form-group">
                                <label for="payment_date">Payment Date <span class="text-danger">*</span></label>
                                <input type="date"
                                       class="form-control @error('payment_date') is-invalid @enderror"
                                       id="payment_date"
                                       name="payment_date"
                                       value="{{ old('payment_date', $nextPayment->due_date ?? now()->format('Y-m-d')) }}"
                                       required>
                                @error('payment_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Bank Account --}}
                            <div class="form-group">
                                <label for="bank_account_id">Bank Account <span class="text-danger">*</span></label>
                                <select class="form-control select2 @error('bank_account_id') is-invalid @enderror"
                                        id="bank_account_id"
                                        name="bank_account_id"
                                        required>
                                    <option value="">Select Bank Account</option>
                                    @foreach($bankAccounts as $account)
                                        <option value="{{ $account->id }}"
                                                data-code="{{ $account->code }}"
                                                data-name="{{ $account->name }}"
                                                {{ old('bank_account_id') == $account->id ? 'selected' : '' }}>
                                            {{ $account->code }} - {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('bank_account_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small id="selectedBankDisplay" class="form-text text-muted"></small>
                            </div>

                            {{-- Payment Amount --}}
                            <div class="form-group">
                                <label for="actual_payment">Payment Amount <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₦</span>
                                    </div>
                                    <input type="number"
                                           class="form-control @error('actual_payment') is-invalid @enderror"
                                           id="actual_payment"
                                           name="actual_payment"
                                           step="0.01"
                                           min="0"
                                           value="{{ old('actual_payment', $nextPayment->payment_amount ?? 0) }}"
                                           required>
                                </div>
                                @error('actual_payment')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @if($nextPayment)
                                    <small class="scheduled-amount-hint mt-1 d-block">
                                        <i class="mdi mdi-information-outline mr-1"></i>
                                        Scheduled: ₦{{ number_format($nextPayment->payment_amount ?? 0, 2) }}
                                        <a href="#" id="useScheduledAmount" class="ml-2 text-primary">
                                            <i class="mdi mdi-refresh mr-1"></i>Use scheduled amount
                                        </a>
                                    </small>
                                @endif
                            </div>

                            {{-- Variance Warning (shown dynamically) --}}
                            <div id="varianceWarning" class="variance-warning d-none">
                                <i class="mdi mdi-alert mr-2"></i>
                                <span id="varianceMessage"></span>
                            </div>

                            {{-- Payment Reference --}}
                            <div class="form-group">
                                <label for="payment_reference">Payment Reference</label>
                                <input type="text"
                                       class="form-control @error('payment_reference') is-invalid @enderror"
                                       id="payment_reference"
                                       name="payment_reference"
                                       value="{{ old('payment_reference') }}"
                                       placeholder="e.g., Bank Transfer Ref, Check No.">
                                @error('payment_reference')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Notes --}}
                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <textarea class="form-control @error('notes') is-invalid @enderror"
                                          id="notes"
                                          name="notes"
                                          rows="2"
                                          placeholder="Optional payment notes">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Payment Breakdown Card --}}
                    <div class="card-modern mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="mdi mdi-calculator mr-2"></i>Payment Breakdown</h6>
                        </div>
                        <div class="card-body">
                            @if(!$isExempt)
                                {{-- IFRS 16 Lease: Principal + Interest --}}
                                <div class="breakdown-item">
                                    <span>Total Payment:</span>
                                    <span class="dynamic-value" id="breakdownTotal">₦{{ number_format($nextPayment->payment_amount ?? 0, 2) }}</span>
                                </div>
                                <div class="breakdown-item">
                                    <span><i class="mdi mdi-minus-circle text-danger mr-1"></i>Principal (Liability Reduction):</span>
                                    <span class="dynamic-value" id="breakdownPrincipal">₦{{ number_format($nextPayment->principal_portion ?? 0, 2) }}</span>
                                </div>
                                <div class="breakdown-item">
                                    <span><i class="mdi mdi-percent text-warning mr-1"></i>Interest Expense:</span>
                                    <span class="dynamic-value" id="breakdownInterest">₦{{ number_format($nextPayment->interest_portion ?? 0, 2) }}</span>
                                </div>
                                <input type="hidden" id="scheduledPrincipal" value="{{ $nextPayment->principal_portion ?? 0 }}">
                                <input type="hidden" id="scheduledInterest" value="{{ $nextPayment->interest_portion ?? 0 }}">
                            @else
                                {{-- Exempt Lease: Full Rent Expense --}}
                                <div class="breakdown-item">
                                    <span><i class="mdi mdi-office-building text-info mr-1"></i>Rent Expense:</span>
                                    <span class="dynamic-value" id="breakdownRentExpense">₦{{ number_format($nextPayment->payment_amount ?? 0, 2) }}</span>
                                </div>
                                <p class="text-muted small mb-0 mt-2">
                                    <i class="mdi mdi-information-outline mr-1"></i>
                                    This lease is exempt from IFRS 16 recognition. Full payment recorded as rent expense.
                                </p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- RIGHT COLUMN: Live Preview --}}
                <div class="col-lg-6">
                    <div class="preview-sidebar">
                        {{-- Balance Preview --}}
                        <div class="card-modern mb-4">
                            <div class="card-header bg-secondary text-white">
                                <h6 class="mb-0"><i class="mdi mdi-scale-balance mr-2"></i>Balance Movement Preview</h6>
                            </div>
                            <div class="card-body">
                                @if(!$isExempt)
                                    {{-- Lease Liability Preview --}}
                                    <div class="card-modern value-card liability mb-3">
                                        <div class="card-body py-3">
                                            <h6 class="text-danger mb-3"><i class="mdi mdi-file-document-outline mr-2"></i>Lease Liability</h6>
                                            <div class="row text-center">
                                                <div class="col-4">
                                                    <small class="text-muted d-block">Opening</small>
                                                    <strong id="liabilityOpening">₦{{ number_format($lease->current_lease_liability ?? $lease->initial_lease_liability, 2) }}</strong>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted d-block">Principal Paid</small>
                                                    <strong class="text-danger dynamic-value" id="liabilityReduction">- ₦{{ number_format($nextPayment->principal_portion ?? 0, 2) }}</strong>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted d-block">Closing</small>
                                                    @php
                                                        $openingLiability = $lease->current_lease_liability ?? $lease->initial_lease_liability;
                                                        $closingLiability = $openingLiability - ($nextPayment->principal_portion ?? 0);
                                                    @endphp
                                                    <strong class="text-success dynamic-value" id="liabilityClosing">₦{{ number_format($closingLiability, 2) }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- ROU Asset Preview --}}
                                    <div class="card-modern value-card asset">
                                        <div class="card-body py-3">
                                            <h6 class="text-success mb-3"><i class="mdi mdi-office-building mr-2"></i>ROU Asset</h6>
                                            <div class="row text-center">
                                                <div class="col-4">
                                                    <small class="text-muted d-block">Cost</small>
                                                    <strong>₦{{ number_format($lease->initial_rou_asset_value ?? 0, 2) }}</strong>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted d-block">Accum. Depr.</small>
                                                    <strong class="text-warning">₦{{ number_format($lease->accumulated_rou_depreciation ?? 0, 2) }}</strong>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted d-block">NBV</small>
                                                    <strong class="text-primary">₦{{ number_format($lease->current_rou_asset_value ?? $lease->initial_rou_asset_value ?? 0, 2) }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    {{-- Exempt Lease - Simple Cash Impact --}}
                                    <div class="card-modern value-card">
                                        <div class="card-body py-3">
                                            <h6 class="text-info mb-3"><i class="mdi mdi-cash mr-2"></i>Cash Impact</h6>
                                            <div class="row text-center">
                                                <div class="col-6">
                                                    <small class="text-muted d-block">Cash Payment</small>
                                                    <strong class="text-danger dynamic-value" id="cashPayment">₦{{ number_format($nextPayment->payment_amount ?? 0, 2) }}</strong>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted d-block">Rent Expense</small>
                                                    <strong class="text-warning dynamic-value" id="rentExpensePreview">₦{{ number_format($nextPayment->payment_amount ?? 0, 2) }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Journal Entry Preview --}}
                        <div class="card-modern mb-4">
                            <div class="card-header bg-dark text-white">
                                <h6 class="mb-0"><i class="mdi mdi-book-open-variant mr-2"></i>Journal Entry Preview</h6>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm table-hover je-preview-table mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Account</th>
                                            <th class="text-right">Debit</th>
                                            <th class="text-right">Credit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if(!$isExempt)
                                            {{-- IFRS 16 Lease JE --}}
                                            <tr>
                                                <td>
                                                    <small class="text-muted">2301</small><br>
                                                    Lease Liability
                                                </td>
                                                <td class="text-right">
                                                    <span class="dynamic-value" id="jeLiabilityDebit">{{ number_format($nextPayment->principal_portion ?? 0, 2) }}</span>
                                                </td>
                                                <td class="text-right">-</td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <small class="text-muted">7200</small><br>
                                                    Interest Expense
                                                </td>
                                                <td class="text-right">
                                                    <span class="dynamic-value" id="jeInterestDebit">{{ number_format($nextPayment->interest_portion ?? 0, 2) }}</span>
                                                </td>
                                                <td class="text-right">-</td>
                                            </tr>
                                            <tr class="table-secondary">
                                                <td>
                                                    <small class="text-muted" id="jeBankCode">1100</small><br>
                                                    <span id="jeBankName">Bank Account</span>
                                                </td>
                                                <td class="text-right">-</td>
                                                <td class="text-right">
                                                    <span class="dynamic-value" id="jeBankCredit">{{ number_format($nextPayment->payment_amount ?? 0, 2) }}</span>
                                                </td>
                                            </tr>
                                        @else
                                            {{-- Exempt Lease JE --}}
                                            <tr>
                                                <td>
                                                    <small class="text-muted">6100</small><br>
                                                    Rent Expense
                                                </td>
                                                <td class="text-right">
                                                    <span class="dynamic-value" id="jeRentDebit">{{ number_format($nextPayment->payment_amount ?? 0, 2) }}</span>
                                                </td>
                                                <td class="text-right">-</td>
                                            </tr>
                                            <tr class="table-secondary">
                                                <td>
                                                    <small class="text-muted" id="jeBankCode">1100</small><br>
                                                    <span id="jeBankName">Bank Account</span>
                                                </td>
                                                <td class="text-right">-</td>
                                                <td class="text-right">
                                                    <span class="dynamic-value" id="jeBankCredit">{{ number_format($nextPayment->payment_amount ?? 0, 2) }}</span>
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                    <tfoot class="bg-dark text-white">
                                        <tr>
                                            <th>Totals</th>
                                            <th class="text-right"><span id="jeTotalDebit">{{ number_format($nextPayment->payment_amount ?? 0, 2) }}</span></th>
                                            <th class="text-right"><span id="jeTotalCredit">{{ number_format($nextPayment->payment_amount ?? 0, 2) }}</span></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="d-flex justify-content-end">
                            <a href="{{ route('accounting.leases.show', $lease->id) }}" class="btn btn-secondary mr-2">
                                <i class="mdi mdi-close mr-1"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="mdi mdi-check mr-1"></i>Record Payment
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuration
    const isIfrs16 = {{ $isExempt ? 'false' : 'true' }};
    const scheduledAmount = {{ $nextPayment->payment_amount ?? 0 }};
    const scheduledPrincipal = {{ $nextPayment->principal_portion ?? 0 }};
    const scheduledInterest = {{ $nextPayment->interest_portion ?? 0 }};
    const openingLiability = {{ $lease->current_lease_liability ?? $lease->initial_lease_liability ?? 0 }};

    // Elements
    const amountInput = document.getElementById('actual_payment');
    const bankSelect = document.getElementById('bank_account_id');
    const useScheduledLink = document.getElementById('useScheduledAmount');
    const varianceWarning = document.getElementById('varianceWarning');
    const varianceMessage = document.getElementById('varianceMessage');

    // Format currency helper
    function formatCurrency(value) {
        return new Intl.NumberFormat('en-NG', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(value);
    }

    // Update all dynamic values based on payment amount
    function updateDynamicValues() {
        const paymentAmount = parseFloat(amountInput.value) || 0;
        const variance = paymentAmount - scheduledAmount;

        // Show/hide variance warning
        if (Math.abs(variance) > 0.01 && scheduledAmount > 0) {
            varianceWarning.classList.remove('d-none');
            varianceWarning.classList.remove('over', 'under');

            if (variance > 0) {
                varianceWarning.classList.add('over');
                varianceMessage.textContent = `Payment is ₦${formatCurrency(variance)} OVER the scheduled amount of ₦${formatCurrency(scheduledAmount)}`;
            } else {
                varianceWarning.classList.add('under');
                varianceMessage.textContent = `Payment is ₦${formatCurrency(Math.abs(variance))} UNDER the scheduled amount of ₦${formatCurrency(scheduledAmount)}`;
            }
        } else {
            varianceWarning.classList.add('d-none');
        }

        if (isIfrs16) {
            // IFRS 16: Calculate proportional split
            // Interest stays fixed as scheduled, principal adjusts
            let principal = paymentAmount - scheduledInterest;
            let interest = scheduledInterest;

            // Handle edge cases
            if (principal < 0) {
                // Payment less than interest - all goes to interest
                interest = paymentAmount;
                principal = 0;
            }

            // Cap principal at opening liability
            if (principal > openingLiability) {
                principal = openingLiability;
            }

            const closingLiability = openingLiability - principal;

            // Update breakdown
            updateElement('breakdownTotal', `₦${formatCurrency(paymentAmount)}`);
            updateElement('breakdownPrincipal', `₦${formatCurrency(principal)}`);
            updateElement('breakdownInterest', `₦${formatCurrency(interest)}`);

            // Update balance preview
            updateElement('liabilityReduction', `- ₦${formatCurrency(principal)}`);
            updateElement('liabilityClosing', `₦${formatCurrency(closingLiability)}`);

            // Update JE preview
            updateElement('jeLiabilityDebit', formatCurrency(principal));
            updateElement('jeInterestDebit', formatCurrency(interest));
            updateElement('jeBankCredit', formatCurrency(paymentAmount));
            updateElement('jeTotalDebit', formatCurrency(paymentAmount));
            updateElement('jeTotalCredit', formatCurrency(paymentAmount));
        } else {
            // Exempt lease: Full amount to rent expense
            updateElement('breakdownRentExpense', `₦${formatCurrency(paymentAmount)}`);
            updateElement('cashPayment', `₦${formatCurrency(paymentAmount)}`);
            updateElement('rentExpensePreview', `₦${formatCurrency(paymentAmount)}`);
            updateElement('jeRentDebit', formatCurrency(paymentAmount));
            updateElement('jeBankCredit', formatCurrency(paymentAmount));
            updateElement('jeTotalDebit', formatCurrency(paymentAmount));
            updateElement('jeTotalCredit', formatCurrency(paymentAmount));
        }
    }

    // Helper to update element and add animation
    function updateElement(id, value) {
        const el = document.getElementById(id);
        if (el) {
            const oldValue = el.textContent;
            el.textContent = value;

            // Add visual feedback for changed values
            if (oldValue !== value) {
                el.classList.add('changed');
                setTimeout(() => el.classList.remove('changed'), 500);
            }
        }
    }

    // Update bank account display in JE preview
    function updateBankDisplay() {
        const selected = bankSelect.options[bankSelect.selectedIndex];
        if (selected && selected.value) {
            const code = selected.dataset.code || '1100';
            const name = selected.dataset.name || 'Bank Account';

            document.getElementById('jeBankCode').textContent = code;
            document.getElementById('jeBankName').textContent = name;
            document.getElementById('selectedBankDisplay').textContent = `Selected: ${code} - ${name}`;
        }
    }

    // Event listeners
    amountInput.addEventListener('input', updateDynamicValues);
    amountInput.addEventListener('change', updateDynamicValues);
    bankSelect.addEventListener('change', updateBankDisplay);

    if (useScheduledLink) {
        useScheduledLink.addEventListener('click', function(e) {
            e.preventDefault();
            amountInput.value = scheduledAmount.toFixed(2);
            updateDynamicValues();
        });
    }

    // Initialize Select2 if available
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('#bank_account_id').select2({
            theme: 'bootstrap4',
            placeholder: '-- Select Bank Account --',
            allowClear: true,
            width: '100%'
        }).on('change', updateBankDisplay);
    }

    // Initial update
    updateBankDisplay();
    updateDynamicValues();
});
</script>
@endpush
