@extends('admin.layouts.app')

@section('page_name', 'Accounting')
@section('subpage_name', 'Bulk Opening Balance Entry')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Opening Balances', 'url' => route('accounting.opening-balances.index'), 'icon' => 'mdi-bank-transfer'],
    ['label' => 'Set Balances', 'url' => '#', 'icon' => 'mdi-pencil']
]])

<div class="container-fluid">
    {{-- Header with Title --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Bulk Opening Balance Entry</h4>
            <p class="text-muted mb-0">Enter opening balances for multiple accounts at once</p>
        </div>
        <div>
            <a href="{{ route('accounting.opening-balances.index', ['fiscal_year_id' => $selectedYear]) }}" class="btn btn-outline-secondary">
                <i class="mdi mdi-arrow-left mr-1"></i> Back to List
            </a>
        </div>
    </div>

    {{-- Info Alert --}}
    <div class="alert alert-info mb-4">
        <i class="mdi mdi-information mr-2"></i>
        <strong>Instructions:</strong> Enter opening balances for accounts below. The system will automatically create
        a balanced journal entry. Accounts with their normal balance (Debit for Assets/Expenses, Credit for Liabilities/Equity/Revenue)
        should have positive amounts.
    </div>

    <form id="bulkOpeningBalanceForm">
        @csrf
        <input type="hidden" name="fiscal_year_id" value="{{ $selectedYear }}">

        {{-- Fiscal Year Selection --}}
        <div class="card card-modern mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="mdi mdi-calendar mr-2"></i>Fiscal Year</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Select Fiscal Year <span class="text-danger">*</span></label>
                        <select id="fiscalYearSelect" name="fiscal_year_id" class="form-control" required>
                            @foreach($fiscalYears as $year)
                                <option value="{{ $year->id }}"
                                        data-start="{{ $year->start_date->format('M d, Y') }}"
                                        {{ $selectedYear == $year->id ? 'selected' : '' }}>
                                    {{ $year->year_name }} {{ $year->is_active ? '(Active)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Starting Date</label>
                        <input type="text" id="startDateDisplay" class="form-control" readonly
                               value="{{ $fiscalYears->where('id', $selectedYear)->first()?->start_date->format('M d, Y') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Filter by Class</label>
                        <select id="classFilter" class="form-control">
                            <option value="">All Classes</option>
                            @foreach($classes as $class)
                                <option value="{{ $class->id }}">{{ $class->class_code }} - {{ $class->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- Account Balances Entry --}}
        @foreach($accounts as $className => $classAccounts)
        <div class="card card-modern mb-4 account-class-card" data-class-name="{{ $className }}">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="mdi mdi-folder-outline mr-2"></i>{{ $className }}
                </h5>
                <div>
                    <span class="badge badge-secondary account-count">{{ $classAccounts->count() }} accounts</span>
                    <button type="button" class="btn btn-sm btn-outline-primary ml-2 toggle-card">
                        <i class="mdi mdi-chevron-down"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead class="thead-light">
                            <tr>
                                <th width="120">Code</th>
                                <th>Account Name</th>
                                <th width="100">Normal</th>
                                <th width="180" class="text-right">Opening Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($classAccounts as $account)
                            <tr class="account-row" data-class-id="{{ $account->accountGroup?->account_class_id }}">
                                <td><code>{{ $account->account_code }}</code></td>
                                <td>
                                    {{ $account->name }}
                                    @if($account->opening_balance && $account->opening_balance != 0)
                                        <small class="text-success ml-2">
                                            <i class="mdi mdi-check-circle"></i> Has balance
                                        </small>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $normalBalance = $account->accountGroup?->accountClass?->normal_balance ?? 'debit';
                                    @endphp
                                    <span class="badge badge-{{ $normalBalance === 'debit' ? 'info' : 'warning' }}">
                                        {{ ucfirst($normalBalance) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">₦</span>
                                        </div>
                                        <input type="number"
                                               step="0.01"
                                               name="balances[{{ $account->id }}][amount]"
                                               class="form-control text-right balance-input"
                                               data-account-id="{{ $account->id }}"
                                               data-normal-balance="{{ $normalBalance }}"
                                               value="{{ $account->opening_balance ?? '' }}"
                                               placeholder="0.00">
                                        <input type="hidden"
                                               name="balances[{{ $account->id }}][account_id]"
                                               value="{{ $account->id }}">
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endforeach

        {{-- Summary Card --}}
        <div class="card card-modern mb-4" id="summaryCard">
            <div class="card-header">
                <h5 class="mb-0"><i class="mdi mdi-sigma mr-2"></i>Summary</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <h6 class="text-muted">Total Debits</h6>
                        <h3 class="text-primary" id="totalDebits">₦0.00</h3>
                    </div>
                    <div class="col-md-3">
                        <h6 class="text-muted">Total Credits</h6>
                        <h3 class="text-success" id="totalCredits">₦0.00</h3>
                    </div>
                    <div class="col-md-3">
                        <h6 class="text-muted">Difference</h6>
                        <h3 id="totalDifference" class="text-info">₦0.00</h3>
                    </div>
                    <div class="col-md-3">
                        <h6 class="text-muted">Status</h6>
                        <h3 id="balanceStatus">
                            <span class="badge badge-secondary">No entries</span>
                        </h3>
                    </div>
                </div>
            </div>
        </div>

        {{-- Submit Buttons --}}
        <div class="card card-modern">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" onclick="window.history.back()">
                        <i class="mdi mdi-arrow-left mr-1"></i> Cancel
                    </button>
                    <div>
                        <button type="button" class="btn btn-outline-info mr-2" id="previewBtn">
                            <i class="mdi mdi-eye mr-1"></i> Preview Entry
                        </button>
                        <button type="submit" class="btn btn-primary" id="saveBtn">
                            <i class="mdi mdi-content-save mr-1"></i> Save Opening Balances
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- Preview Modal --}}
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="mdi mdi-file-document-outline mr-2"></i>Opening Balance Journal Entry Preview
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="previewTable">
                        <thead class="thead-dark">
                            <tr>
                                <th>Account</th>
                                <th class="text-right">Debit</th>
                                <th class="text-right">Credit</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot class="font-weight-bold">
                            <tr>
                                <td>Total</td>
                                <td class="text-right" id="previewTotalDebit">0.00</td>
                                <td class="text-right" id="previewTotalCredit">0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .card-modern {
        border: none;
        box-shadow: 0 0 10px rgba(0,0,0,0.05);
        border-radius: 10px;
    }
    .card-modern .card-header {
        background-color: transparent;
        border-bottom: 1px solid #eee;
    }

    .balance-input:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
    }

    .balance-input.has-value {
        background-color: #f0fff0;
    }

    .table-sm td, .table-sm th {
        padding: 0.5rem;
    }

    #summaryCard {
        position: sticky;
        bottom: 0;
        z-index: 100;
        background: white;
    }

    .toggle-card .mdi {
        transition: transform 0.2s;
    }

    .card-body.collapsed + .toggle-card .mdi,
    .collapsed .toggle-card .mdi {
        transform: rotate(-90deg);
    }
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    // Update start date display when fiscal year changes
    $('#fiscalYearSelect').on('change', function() {
        var $option = $(this).find(':selected');
        $('#startDateDisplay').val($option.data('start'));
    });

    // Filter by class
    $('#classFilter').on('change', function() {
        var classId = $(this).val();
        if (classId) {
            $('.account-row').each(function() {
                var rowClassId = $(this).data('class-id');
                $(this).toggle(rowClassId == classId);
            });
        } else {
            $('.account-row').show();
        }
    });

    // Toggle card collapse
    $('.toggle-card').on('click', function() {
        var $card = $(this).closest('.card');
        var $body = $card.find('.card-body');
        $body.slideToggle();
        $(this).find('.mdi').toggleClass('mdi-chevron-down mdi-chevron-right');
    });

    // Calculate totals on input change
    $('.balance-input').on('input', function() {
        var value = parseFloat($(this).val()) || 0;
        $(this).toggleClass('has-value', value !== 0);
        calculateTotals();
    });

    // Initial calculation
    calculateTotals();

    function calculateTotals() {
        var totalDebits = 0;
        var totalCredits = 0;
        var hasEntries = false;

        $('.balance-input').each(function() {
            var amount = parseFloat($(this).val()) || 0;
            if (amount === 0) return;

            hasEntries = true;
            var normalBalance = $(this).data('normal-balance');

            if (amount > 0) {
                if (normalBalance === 'debit') {
                    totalDebits += amount;
                } else {
                    totalCredits += amount;
                }
            } else {
                // Negative = contra side
                if (normalBalance === 'debit') {
                    totalCredits += Math.abs(amount);
                } else {
                    totalDebits += Math.abs(amount);
                }
            }
        });

        var difference = Math.abs(totalDebits - totalCredits);

        $('#totalDebits').text('₦' + numberFormat(totalDebits));
        $('#totalCredits').text('₦' + numberFormat(totalCredits));
        $('#totalDifference').text('₦' + numberFormat(difference));

        if (!hasEntries) {
            $('#balanceStatus').html('<span class="badge badge-secondary">No entries</span>');
            $('#totalDifference').removeClass('text-danger text-success').addClass('text-info');
        } else if (difference < 0.01) {
            $('#balanceStatus').html('<span class="badge badge-success"><i class="mdi mdi-check"></i> Balanced</span>');
            $('#totalDifference').removeClass('text-danger text-info').addClass('text-success');
        } else {
            $('#balanceStatus').html('<span class="badge badge-warning"><i class="mdi mdi-alert"></i> Out of Balance</span>');
            $('#totalDifference').removeClass('text-success text-info').addClass('text-danger');
        }
    }

    function numberFormat(num) {
        return num.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }

    // Preview
    $('#previewBtn').on('click', function() {
        var $tbody = $('#previewTable tbody');
        $tbody.empty();

        var totalDebits = 0;
        var totalCredits = 0;

        $('.balance-input').each(function() {
            var amount = parseFloat($(this).val()) || 0;
            if (amount === 0) return;

            var accountId = $(this).data('account-id');
            var normalBalance = $(this).data('normal-balance');
            var $row = $(this).closest('tr');
            var accountCode = $row.find('code').text();
            var accountName = $row.find('td:eq(1)').text().trim().split('\n')[0];

            var debit = 0;
            var credit = 0;

            if (amount > 0) {
                if (normalBalance === 'debit') {
                    debit = amount;
                } else {
                    credit = amount;
                }
            } else {
                if (normalBalance === 'debit') {
                    credit = Math.abs(amount);
                } else {
                    debit = Math.abs(amount);
                }
            }

            totalDebits += debit;
            totalCredits += credit;

            $tbody.append(`
                <tr>
                    <td>${accountCode} - ${accountName}</td>
                    <td class="text-right">${debit > 0 ? numberFormat(debit) : ''}</td>
                    <td class="text-right">${credit > 0 ? numberFormat(credit) : ''}</td>
                </tr>
            `);
        });

        $('#previewTotalDebit').text(numberFormat(totalDebits));
        $('#previewTotalCredit').text(numberFormat(totalCredits));

        $('#previewModal').modal('show');
    });

    // Form submission
    $('#bulkOpeningBalanceForm').on('submit', function(e) {
        e.preventDefault();

        var $btn = $('#saveBtn');
        $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Saving...');

        // Build balances array with only non-zero values
        var balances = [];
        $('.balance-input').each(function() {
            var amount = parseFloat($(this).val()) || 0;
            if (amount !== 0) {
                balances.push({
                    account_id: $(this).data('account-id'),
                    amount: amount
                });
            }
        });

        if (balances.length === 0) {
            toastr.warning('Please enter at least one opening balance');
            $btn.prop('disabled', false).html('<i class="mdi mdi-content-save mr-1"></i> Save Opening Balances');
            return;
        }

        $.ajax({
            url: '{{ route("accounting.opening-balances.store") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                fiscal_year_id: $('#fiscalYearSelect').val(),
                balances: balances
            }
        })
        .done(function(response) {
            if (response.success) {
                toastr.success(response.message);
                setTimeout(function() {
                    window.location.href = '{{ route("accounting.opening-balances.index") }}?fiscal_year_id=' + $('#fiscalYearSelect').val();
                }, 1000);
            } else {
                toastr.error(response.message || 'Error saving opening balances');
                $btn.prop('disabled', false).html('<i class="mdi mdi-content-save mr-1"></i> Save Opening Balances');
            }
        })
        .fail(function(xhr) {
            var message = xhr.responseJSON?.message || 'Error saving opening balances';
            toastr.error(message);
            $btn.prop('disabled', false).html('<i class="mdi mdi-content-save mr-1"></i> Save Opening Balances');
        });
    });
});
</script>
@endpush
