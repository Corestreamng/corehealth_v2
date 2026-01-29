@extends('admin.layouts.app')

@section('page_name', 'Accounting')
@section('subpage_name', 'Opening Balances')

@section('content')
<div class="container-fluid">
    {{-- Header with Title --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Opening Balances</h4>
            <p class="text-muted mb-0">Set account balances at the start of a fiscal year</p>
        </div>
        <div>
            @can('accounting.opening-balances.create')
            <a href="{{ route('accounting.opening-balances.create', ['fiscal_year_id' => $selectedYear]) }}" class="btn btn-primary">
                <i class="mdi mdi-plus mr-1"></i> Bulk Entry
            </a>
            @endcan
        </div>
    </div>

    {{-- Stat Cards --}}
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-modern h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary-light mr-3">
                            <i class="mdi mdi-calculator text-primary"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Debits</h6>
                            <h4 class="mb-0">₦{{ number_format($stats['total_debit'], 2) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-modern h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success-light mr-3">
                            <i class="mdi mdi-calculator-variant text-success"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Credits</h6>
                            <h4 class="mb-0">₦{{ number_format($stats['total_credit'], 2) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-modern h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon {{ $stats['balance_difference'] > 0.01 ? 'bg-danger-light' : 'bg-info-light' }} mr-3">
                            <i class="mdi mdi-scale-balance {{ $stats['balance_difference'] > 0.01 ? 'text-danger' : 'text-info' }}"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Difference</h6>
                            <h4 class="mb-0 {{ $stats['balance_difference'] > 0.01 ? 'text-danger' : '' }}">
                                ₦{{ number_format($stats['balance_difference'], 2) }}
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-modern h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning-light mr-3">
                            <i class="mdi mdi-folder-account text-warning"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Accounts with Balance</h6>
                            <h4 class="mb-0">{{ $stats['accounts_with_opening'] }} / {{ $stats['total_accounts'] }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters Card --}}
    <div class="card card-modern mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="mdi mdi-filter-outline mr-2"></i>Filters</h5>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="resetFilters">
                <i class="mdi mdi-refresh"></i> Reset
            </button>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Fiscal Year</label>
                    <select id="fiscalYearFilter" class="form-control">
                        @foreach($fiscalYears as $year)
                            <option value="{{ $year->id }}" {{ $selectedYear == $year->id ? 'selected' : '' }}>
                                {{ $year->year_name }} {{ $year->is_active ? '(Active)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Account Class</label>
                    <select id="classFilter" class="form-control">
                        <option value="">All Classes</option>
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}">{{ $class->class_code }} - {{ $class->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Account Group</label>
                    <select id="groupFilter" class="form-control">
                        <option value="">All Groups</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Has Balance</label>
                    <select id="hasBalanceFilter" class="form-control">
                        <option value="">All Accounts</option>
                        <option value="1">With Opening Balance</option>
                        <option value="0">Without Opening Balance</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Table Card --}}
    <div class="card card-modern">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="mdi mdi-table mr-2"></i>Account Opening Balances</h5>
            @if($fiscalYear)
            <span class="badge badge-info">{{ $fiscalYear->year_name }} - Starting {{ $fiscalYear->start_date->format('M d, Y') }}</span>
            @endif
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="openingBalancesTable" class="table table-striped table-hover w-100">
                    <thead class="thead-light">
                        <tr>
                            <th>Account Code</th>
                            <th>Account Name</th>
                            <th>Class</th>
                            <th>Group</th>
                            <th>Normal Balance</th>
                            <th class="text-right">Opening Balance</th>
                            <th width="100">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Edit Balance Modal --}}
<div class="modal fade" id="editBalanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="mdi mdi-pencil mr-2"></i>Edit Opening Balance
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="editBalanceForm">
                @csrf
                <input type="hidden" id="editAccountId" name="account_id">
                <input type="hidden" name="fiscal_year_id" value="{{ $selectedYear }}">
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="mdi mdi-information mr-1"></i>
                        <strong id="editAccountCode"></strong> - <span id="editAccountName"></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Normal Balance Side</label>
                        <input type="text" id="editNormalBalance" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Opening Balance Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">₦</span>
                            </div>
                            <input type="number" step="0.01" id="editAmount" name="amount" class="form-control" required>
                        </div>
                        <small class="text-muted">Enter positive for normal balance, negative for contra balance</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="mdi mdi-check mr-1"></i> Save Balance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') }}">
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

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .stat-icon i {
        font-size: 24px;
    }

    .bg-primary-light { background-color: rgba(0, 123, 255, 0.1); }
    .bg-success-light { background-color: rgba(40, 167, 69, 0.1); }
    .bg-warning-light { background-color: rgba(255, 193, 7, 0.1); }
    .bg-info-light { background-color: rgba(23, 162, 184, 0.1); }
    .bg-danger-light { background-color: rgba(220, 53, 69, 0.1); }

    #openingBalancesTable tbody tr:hover {
        background-color: #f8f9fa;
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
<script>
$(document).ready(function() {
    // Account groups by class (for cascading filter)
    var groupsByClass = @json($classes->mapWithKeys(function($class) {
        return [$class->id => $class->groups->map(function($g) {
            return ['id' => $g->id, 'name' => $g->group_code . ' - ' . $g->name];
        })];
    }));

    // Initialize DataTable
    var table = $('#openingBalancesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("accounting.opening-balances.datatable") }}',
            data: function(d) {
                d.fiscal_year_id = $('#fiscalYearFilter').val();
                d.class_id = $('#classFilter').val();
                d.group_id = $('#groupFilter').val();
                d.has_balance = $('#hasBalanceFilter').val();
            }
        },
        columns: [
            { data: 'account_code', name: 'account_code' },
            { data: 'name', name: 'name' },
            { data: 'class_name', name: 'class_name', orderable: false },
            { data: 'group_name', name: 'group_name', orderable: false },
            { data: 'normal_balance', name: 'normal_balance', orderable: false },
            { data: 'opening_balance_formatted', name: 'opening_balance', className: 'text-right' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'asc']],
        pageLength: 25,
        language: {
            emptyTable: 'No accounts found',
            processing: '<i class="mdi mdi-loading mdi-spin mr-2"></i> Loading...'
        }
    });

    // Filter handlers
    $('#fiscalYearFilter').on('change', function() {
        // Reload page with new fiscal year
        window.location.href = '{{ route("accounting.opening-balances.index") }}?fiscal_year_id=' + $(this).val();
    });

    $('#classFilter').on('change', function() {
        var classId = $(this).val();
        var $groupFilter = $('#groupFilter');

        $groupFilter.html('<option value="">All Groups</option>');

        if (classId && groupsByClass[classId]) {
            groupsByClass[classId].forEach(function(group) {
                $groupFilter.append('<option value="' + group.id + '">' + group.name + '</option>');
            });
        }

        table.ajax.reload();
    });

    $('#groupFilter, #hasBalanceFilter').on('change', function() {
        table.ajax.reload();
    });

    $('#resetFilters').on('click', function() {
        $('#classFilter').val('').trigger('change');
        $('#groupFilter').val('');
        $('#hasBalanceFilter').val('');
        table.ajax.reload();
    });

    // Edit balance modal
    $(document).on('click', '.edit-balance', function() {
        var $btn = $(this);
        $('#editAccountId').val($btn.data('account-id'));
        $('#editAccountCode').text($btn.data('account-code'));
        $('#editAccountName').text($btn.data('account-name'));
        $('#editNormalBalance').val($btn.data('normal-balance') === 'debit' ? 'Debit (Dr)' : 'Credit (Cr)');
        $('#editAmount').val($btn.data('balance'));
        $('#editBalanceModal').modal('show');
    });

    // Save balance
    $('#editBalanceForm').on('submit', function(e) {
        e.preventDefault();

        var accountId = $('#editAccountId').val();
        var $btn = $(this).find('button[type="submit"]');

        $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Saving...');

        $.ajax({
            url: '/accounting/opening-balances/' + accountId,
            method: 'POST',
            data: $(this).serialize() + '&_method=PUT',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .done(function(response) {
            if (response.success) {
                toastr.success(response.message);
                $('#editBalanceModal').modal('hide');
                table.ajax.reload();
            } else {
                toastr.error(response.message || 'Error saving balance');
            }
        })
        .fail(function(xhr) {
            var message = xhr.responseJSON?.message || 'Error saving balance';
            toastr.error(message);
        })
        .always(function() {
            $btn.prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i> Save Balance');
        });
    });
});
</script>
@endpush
