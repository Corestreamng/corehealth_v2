@extends('admin.layouts.app')

@section('title', 'Bank Statement')
@section('page_name', 'Accounting')
@section('subpage_name', 'Bank Statement')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Reports', 'url' => route('accounting.reports.index'), 'icon' => 'mdi-file-chart'],
    ['label' => 'Bank Statement', 'url' => '#', 'icon' => 'mdi-bank']
]])

<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Bank Statement</h4>
            <p class="text-muted mb-0">Detailed bank account transactions and reconciliation</p>
        </div>
        <div>
            <a href="{{ route('accounting.reports.index') }}" class="btn btn-outline-secondary mr-2">
                <i class="mdi mdi-arrow-left mr-1"></i> Back to Reports
            </a>
            @if($selectedAccount)
            <a href="{{ route('accounting.reports.bank-statement', array_merge(request()->all(), ['export' => 'pdf'])) }}"
               class="btn btn-danger mr-1" target="_blank">
                <i class="mdi mdi-file-pdf-box mr-1"></i> PDF
            </a>
            <a href="{{ route('accounting.reports.bank-statement', array_merge(request()->all(), ['export' => 'excel'])) }}"
               class="btn btn-success">
                <i class="mdi mdi-file-excel mr-1"></i> Excel
            </a>
            @endif
        </div>
    </div>

    {{-- Advanced Filters --}}
    <div class="card-modern shadow mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="mdi mdi-filter-variant mr-2"></i>Filters
                </h6>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnResetFilters">
                        <i class="mdi mdi-refresh mr-1"></i> Reset
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="collapse" data-target="#advancedFilters">
                        <i class="mdi mdi-tune mr-1"></i> Advanced
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('accounting.reports.bank-statement') }}" id="filterForm">
                <div class="row g-3">
                    {{-- Bank Selection --}}
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Bank</label>
                        <select name="bank_id" class="form-select" id="bankSelect">
                            <option value="">All Banks</option>
                            @foreach($banks as $bank)
                                <option value="{{ $bank->id }}"
                                    {{ request('bank_id') == $bank->id ? 'selected' : '' }}>
                                    {{ $bank->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Bank Account Selection --}}
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Bank Account <span class="text-danger">*</span></label>
                        <select name="bank_account_id" class="form-select" id="bankAccountSelect" required>
                            <option value="">Select Bank Account</option>
                            @foreach($bankAccounts as $account)
                                <option value="{{ $account->id }}"
                                    {{ request('bank_account_id') == $account->id ? 'selected' : '' }}
                                    data-bank-id="{{ $account->bank_id }}"
                                    data-balance="{{ $account->getBalance() }}">
                                    {{ $account->code }} - {{ $account->name }}
                                    @if($account->bank)
                                        ({{ $account->bank->name }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Date Range --}}
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Start Date</label>
                        <input type="date" name="start_date" class="form-control"
                               value="{{ request('start_date', $startDate->format('Y-m-d')) }}">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-bold">End Date</label>
                        <input type="date" name="end_date" class="form-control"
                               value="{{ request('end_date', $endDate->format('Y-m-d')) }}">
                    </div>

                    {{-- Generate Button --}}
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="mdi mdi-chart-line mr-1"></i> Generate
                        </button>
                    </div>
                </div>

                {{-- Quick Date Ranges Row --}}
                <div class="row g-3 mt-2">
                    <div class="col-md-3">
                        <label class="form-label">Quick Select</label>
                        <select class="form-select" id="quickDateRange">
                            <option value="">Custom Dates</option>
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="this_week">This Week</option>
                            <option value="last_week">Last Week</option>
                            <option value="this_month">This Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="this_quarter">This Quarter</option>
                            <option value="last_quarter">Last Quarter</option>
                            <option value="this_year">This Year</option>
                        </select>
                    </div>
                </div>

                {{-- Advanced Filters (Collapsible) --}}
                <div class="collapse mt-4" id="advancedFilters">
                    <hr class="my-3">
                    <div class="row g-3">
                        {{-- Transaction Type Filter --}}
                        <div class="col-md-3">
                            <label class="form-label">Transaction Type</label>
                            <select name="transaction_type" class="form-select">
                                <option value="all" {{ request('transaction_type', 'all') == 'all' ? 'selected' : '' }}>All Transactions</option>
                                <option value="deposits" {{ request('transaction_type') == 'deposits' ? 'selected' : '' }}>Deposits Only</option>
                                <option value="withdrawals" {{ request('transaction_type') == 'withdrawals' ? 'selected' : '' }}>Withdrawals Only</option>
                            </select>
                        </div>

                        {{-- Amount Range --}}
                        <div class="col-md-2">
                            <label class="form-label">Min Amount (₦)</label>
                            <input type="number" name="min_amount" class="form-control"
                                   step="0.01" min="0" value="{{ request('min_amount') }}"
                                   placeholder="0.00">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Max Amount (₦)</label>
                            <input type="number" name="max_amount" class="form-control"
                                   step="0.01" min="0" value="{{ request('max_amount') }}"
                                   placeholder="999999.99">
                        </div>

                        {{-- Reconciliation Status --}}
                        <div class="col-md-3">
                            <label class="form-label">Reconciliation Status</label>
                            <select name="reconciliation_status" class="form-select">
                                <option value="all" {{ request('reconciliation_status', 'all') == 'all' ? 'selected' : '' }}>All Status</option>
                                <option value="reconciled" {{ request('reconciliation_status') == 'reconciled' ? 'selected' : '' }}>Reconciled</option>
                                <option value="unreconciled" {{ request('reconciliation_status') == 'unreconciled' ? 'selected' : '' }}>Unreconciled</option>
                            </select>
                        </div>

                        {{-- Search Description --}}
                        <div class="col-md-2">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control"
                                   value="{{ request('search') }}"
                                   placeholder="Description...">
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Statement Summary Cards --}}
    @if($selectedAccount && $statement)
    {{-- Bank Information Header --}}
    @if($selectedBank)
    <div class="alert alert-info mb-3">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h5 class="mb-1">
                    <i class="mdi mdi-bank mr-2"></i>{{ $selectedBank->name }}
                </h5>
                <p class="mb-0">
                    <strong>Account:</strong> {{ $selectedAccount->code }} - {{ $selectedAccount->name }}
                    @if($selectedBank->account_number)
                        | <strong>Bank Account #:</strong> {{ $selectedBank->account_number }}
                    @endif
                    @if($selectedBank->account_name)
                        | <strong>Account Name:</strong> {{ $selectedBank->account_name }}
                    @endif
                </p>
            </div>
            <div class="col-md-4 text-right">
                @if($selectedBank->bank_code)
                    <span class="badge badge-secondary">Code: {{ $selectedBank->bank_code }}</span>
                @endif
            </div>
        </div>
    </div>
    @endif

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card-modern border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Opening Balance</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                ₦ {{ number_format($statement['opening_balance'], 2) }}
                            </div>
                            <small class="text-muted">{{ $startDate->format('M d, Y') }}</small>
                        </div>
                        <div class="col-auto">
                            <i class="mdi mdi-calendar-start mdi-36px text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card-modern border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Deposits</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                ₦ {{ number_format($statement['total_debit'], 2) }}
                            </div>
                            <small class="text-muted">{{ count(array_filter($statement['transactions'], fn($t) => $t['debit'] > 0)) }} transactions</small>
                        </div>
                        <div class="col-auto">
                            <i class="mdi mdi-arrow-down-bold-circle mdi-36px text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card-modern border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Withdrawals</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                ₦ {{ number_format($statement['total_credit'], 2) }}
                            </div>
                            <small class="text-muted">{{ count(array_filter($statement['transactions'], fn($t) => $t['credit'] > 0)) }} transactions</small>
                        </div>
                        <div class="col-auto">
                            <i class="mdi mdi-arrow-up-bold-circle mdi-36px text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card-modern border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Closing Balance</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                ₦ {{ number_format($statement['closing_balance'], 2) }}
                            </div>
                            <small class="text-muted">{{ $endDate->format('M d, Y') }}</small>
                        </div>
                        <div class="col-auto">
                            <i class="mdi mdi-calendar-end mdi-36px text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Bank Statement Table --}}
    <div class="card-modern shadow">
        <div class="card-header py-3 bg-white">
            <div class="text-center">
                <h4 class="mb-1">{{ config('app.name', 'CoreHealth') }}</h4>
                <h5 class="mb-1">Bank Statement</h5>
                <p class="mb-1"><strong>{{ $selectedAccount->code }} - {{ $selectedAccount->name }}</strong></p>
                <p class="text-muted mb-0">
                    {{ $startDate->format('F d, Y') }} to {{ $endDate->format('F d, Y') }}
                </p>
            </div>
        </div>
        <div class="card-body">
            @if(count($statement['transactions']) > 0)
            <div class="table-responsive">
                <table class="table table-sm table-hover" id="statementTable">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th width="10%">Date</th>
                            <th width="10%">Entry #</th>
                            <th width="30%">Description</th>
                            <th width="12%">Reference</th>
                            <th width="12%" class="text-end">Deposits</th>
                            <th width="12%" class="text-end">Withdrawals</th>
                            <th width="14%" class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="table-active fw-bold">
                            <td colspan="6" class="text-end">Opening Balance</td>
                            <td class="text-end">₦ {{ number_format($statement['opening_balance'], 2) }}</td>
                        </tr>
                        @foreach($statement['transactions'] as $transaction)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($transaction['date'])->format('M d, Y') }}</td>
                            <td>
                                <a href="{{ route('accounting.journal-entries.show', $transaction['entry_id']) }}"
                                   target="_blank" class="text-primary">
                                    {{ $transaction['entry_number'] }}
                                </a>
                            </td>
                            <td>
                                <span data-toggle="tooltip" title="{{ $transaction['description'] }}">
                                    {{ Str::limit($transaction['description'], 50) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-secondary">{{ $transaction['source_type'] ?? '-' }}</span>
                            </td>
                            <td class="text-end text-success fw-bold">
                                {{ $transaction['debit'] > 0 ? '₦ ' . number_format($transaction['debit'], 2) : '-' }}
                            </td>
                            <td class="text-end text-danger fw-bold">
                                {{ $transaction['credit'] > 0 ? '₦ ' . number_format($transaction['credit'], 2) : '-' }}
                            </td>
                            <td class="text-end fw-bold">
                                ₦ {{ number_format($transaction['running_balance'], 2) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="4" class="text-end">Period Totals:</td>
                            <td class="text-end text-success">₦ {{ number_format($statement['total_debit'], 2) }}</td>
                            <td class="text-end text-danger">₦ {{ number_format($statement['total_credit'], 2) }}</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="6" class="text-end">Closing Balance:</td>
                            <td class="text-end">₦ {{ number_format($statement['closing_balance'], 2) }}</td>
                        </tr>
                        <tr class="table-info">
                            <td colspan="6" class="text-end">Net Movement:</td>
                            <td class="text-end">
                                @php $netMovement = $statement['closing_balance'] - $statement['opening_balance']; @endphp
                                <span class="{{ $netMovement >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $netMovement >= 0 ? '+' : '' }}₦ {{ number_format($netMovement, 2) }}
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @else
            <div class="text-center py-5">
                <i class="mdi mdi-file-document-outline mdi-48px text-muted mb-3"></i>
                <h5>No Transactions Found</h5>
                <p class="text-muted">No transactions found for this period with the selected filters.</p>
            </div>
            @endif
        </div>
        <div class="card-footer text-muted small">
            <div class="row">
                <div class="col-md-6">
                    Generated on: {{ now()->format('F d, Y H:i:s') }}
                </div>
                <div class="col-md-6 text-end">
                    Generated by: {{ auth()->user()->name ?? 'System' }}
                </div>
            </div>
        </div>
    </div>
    @else
    <div class="card-modern shadow">
        <div class="card-body text-center py-5">
            <i class="mdi mdi-bank mdi-48px text-muted mb-3"></i>
            <h5>Select Bank Account</h5>
            <p class="text-muted">Select a bank account and date range above to generate the statement.</p>
        </div>
    </div>
    @endif
</div>

@push('styles')
<style>
    .border-left-primary { border-left: 0.25rem solid #4e73df !important; }
    .border-left-success { border-left: 0.25rem solid #1cc88a !important; }
    .border-left-danger { border-left: 0.25rem solid #e74a3b !important; }
    .border-left-info { border-left: 0.25rem solid #36b9cc !important; }

    .sticky-top {
        position: sticky;
        top: 0;
        z-index: 10;
        background-color: #f8f9fc;
    }

    #statementTable tbody tr:hover {
        background-color: #f8f9fa;
        cursor: pointer;
    }

    .form-select, .form-control {
        border-radius: 0.35rem;
    }
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    // Bank filter for accounts
    const allAccounts = $('#bankAccountSelect option').clone();

    $('#bankSelect').on('change', function() {
        const bankId = $(this).val();
        const $accountSelect = $('#bankAccountSelect');

        // Clear and restore default option
        $accountSelect.html('<option value="">Select Bank Account</option>');

        if (!bankId) {
            // Show all accounts if no bank selected
            allAccounts.each(function() {
                if ($(this).val()) {
                    $accountSelect.append($(this).clone());
                }
            });
        } else {
            // Filter accounts by bank
            allAccounts.each(function() {
                if ($(this).val() && $(this).data('bank-id') == bankId) {
                    $accountSelect.append($(this).clone());
                }
            });
        }

        // Restore selected value if it matches filter
        const selectedAccountId = '{{ request("bank_account_id") }}';
        if (selectedAccountId) {
            $accountSelect.val(selectedAccountId);
        }
    });

    // Quick date range selector
    $('#quickDateRange').on('change', function() {
        let range = $(this).val();
        let startDate, endDate;
        let today = new Date();

        switch(range) {
            case 'today':
                startDate = endDate = today;
                break;
            case 'yesterday':
                startDate = endDate = new Date(today.setDate(today.getDate() - 1));
                break;
            case 'this_week':
                let firstDay = today.getDate() - today.getDay();
                startDate = new Date(today.setDate(firstDay));
                endDate = new Date();
                break;
            case 'last_week':
                let lastWeekEnd = new Date(today.setDate(today.getDate() - today.getDay()));
                endDate = lastWeekEnd;
                startDate = new Date(lastWeekEnd.setDate(lastWeekEnd.getDate() - 6));
                break;
            case 'this_month':
                startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                endDate = new Date();
                break;
            case 'last_month':
                startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                endDate = new Date(today.getFullYear(), today.getMonth(), 0);
                break;
            case 'this_quarter':
                let quarter = Math.floor(today.getMonth() / 3);
                startDate = new Date(today.getFullYear(), quarter * 3, 1);
                endDate = new Date();
                break;
            case 'last_quarter':
                let lastQuarter = Math.floor(today.getMonth() / 3) - 1;
                if (lastQuarter < 0) { lastQuarter = 3; }
                startDate = new Date(today.getFullYear(), lastQuarter * 3, 1);
                endDate = new Date(today.getFullYear(), lastQuarter * 3 + 3, 0);
                break;
            case 'this_year':
                startDate = new Date(today.getFullYear(), 0, 1);
                endDate = new Date();
                break;
            default:
                return;
        }

        $('input[name="start_date"]').val(formatDate(startDate));
        $('input[name="end_date"]').val(formatDate(endDate));
    });

    function formatDate(date) {
        let d = new Date(date),
            month = '' + (d.getMonth() + 1),
            day = '' + d.getDate(),
            year = d.getFullYear();

        if (month.length < 2) month = '0' + month;
        if (day.length < 2) day = '0' + day;

        return [year, month, day].join('-');
    }

    // Reset filters
    $('#btnResetFilters').on('click', function() {
        window.location.href = '{{ route('accounting.reports.bank-statement') }}';
    });

    // Show current balance on account selection
    $('select[name="bank_account_id"]').on('change', function() {
        let selectedOption = $(this).find(':selected');
        let balance = selectedOption.data('balance');
        if (balance !== undefined) {
            console.log('Current Balance: ₦' + parseFloat(balance).toLocaleString('en-US', {minimumFractionDigits: 2}));
        }
    });

    // Enable tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Auto-submit form when account changes (optional - can be removed if unwanted)
    // $('select[name="bank_account_id"]').on('change', function() {
    //     if ($(this).val()) {
    //         $('#filterForm').submit();
    //     }
    // });
});
</script>
@endpush
@endsection
