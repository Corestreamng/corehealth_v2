{{--
    Bank Reconciliation Index
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 3
    Access: SUPERADMIN|ADMIN|ACCOUNTS|AUDIT
--}}

@extends('admin.layouts.app')

@section('title', 'Bank Reconciliation')
@section('page_name', 'Accounting')
@section('subpage_name', 'Bank Reconciliation')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Bank Reconciliation', 'url' => route('accounting.bank-reconciliation.index'), 'icon' => 'mdi-bank-check']
    ]
])

<style>
.stat-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    border-left: 4px solid;
}
.stat-card .label { font-size: 0.85rem; color: #666; margin-bottom: 5px; }
.stat-card .value { font-size: 1.8rem; font-weight: 600; }
.filter-panel {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}
.reconciliation-status {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}
.status-item {
    text-align: center;
    padding: 15px 20px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    min-width: 120px;
}
.status-item .count {
    font-size: 1.5rem;
    font-weight: 700;
}
.status-item .label {
    font-size: 0.8rem;
    color: #666;
}
</style>

<div class="container-fluid">
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="mdi mdi-alert-circle mr-2"></i>{{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="mdi mdi-check-circle mr-2"></i>{{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="mdi mdi-bank-check mr-2"></i>Bank Reconciliation</h4>
            <p class="text-muted mb-0">Match bank statements with general ledger entries</p>
        </div>
        {{-- <a href="{{ route('accounting.bank-reconciliation.create') }}" class="btn btn-primary">
            <i class="mdi mdi-plus mr-1"></i> New Reconciliation
        </a> --}}
    </div>

    <!-- Status Overview -->
    <div class="reconciliation-status">
        <div class="status-item">
            <div class="count text-secondary">{{ $stats['draft'] }}</div>
            <div class="label">Draft</div>
        </div>
        <div class="status-item">
            <div class="count text-info">{{ $stats['in_progress'] }}</div>
            <div class="label">In Progress</div>
        </div>
        <div class="status-item">
            <div class="count text-warning">{{ $stats['pending_review'] }}</div>
            <div class="label">Pending Review</div>
        </div>
        <div class="status-item">
            <div class="count text-success">{{ $stats['finalized_this_month'] }}</div>
            <div class="label">Finalized (Month)</div>
        </div>
        <div class="status-item">
            <div class="count text-danger">{{ $stats['unmatched_items'] }}</div>
            <div class="label">Unmatched Items</div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card border-primary">
                <div class="label">Total Reconciliations</div>
                <div class="value text-primary">{{ number_format($stats['total_reconciliations']) }}</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card border-info">
                <div class="label">Banks Tracked</div>
                <div class="value text-info">{{ $banks->count() }}</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card border-warning">
                <div class="label">Awaiting Action</div>
                <div class="value text-warning">{{ $stats['draft'] + $stats['in_progress'] }}</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card border-success">
                <div class="label">Completed This Month</div>
                <div class="value text-success">{{ $stats['finalized_this_month'] }}</div>
            </div>
        </div>
    </div>

    <!-- Filter Panel -->
    <div class="filter-panel">
        <div class="row">
            <div class="col-md-3">
                <label>Bank Account</label>
                <select id="filter_bank" class="form-control form-control-sm">
                    <option value="">All Banks</option>
                    @foreach($banks as $bank)
                        <option value="{{ $bank->id }}">{{ $bank->bank_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label>Status</label>
                <select id="filter_status" class="form-control form-control-sm">
                    <option value="">All Statuses</option>
                    <option value="draft">Draft</option>
                    <option value="in_progress">In Progress</option>
                    <option value="pending_review">Pending Review</option>
                    <option value="approved">Approved</option>
                    <option value="finalized">Finalized</option>
                </select>
            </div>
            <div class="col-md-2">
                <label>Date From</label>
                <input type="date" id="filter_date_from" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label>Date To</label>
                <input type="date" id="filter_date_to" class="form-control form-control-sm">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button id="btn_filter" class="btn btn-primary btn-sm mr-2">
                    <i class="mdi mdi-filter mr-1"></i> Filter
                </button>
                <button id="btn_reset" class="btn btn-secondary btn-sm mr-2">
                    <i class="mdi mdi-refresh mr-1"></i> Reset
                </button>
                <div class="input-group">
                    <select id="create_recon_bank" class="form-control form-control-sm mr-2">
                        <option value="">New Reconciliation for...</option>
                        @foreach($banks as $bank)
                            <option value="{{ $bank->id }}">{{ $bank->bank_name }}</option>
                        @endforeach
                    </select>
                    <button id="btn_create_recon" class="btn btn-success btn-sm" disabled>
                        <i class="mdi mdi-plus"></i> New Reconciliation
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reconciliations Table -->
    <div class="card-modern">
        <div class="card-header">
            <h5 class="mb-0"><i class="mdi mdi-table mr-2"></i>Reconciliation Records</h5>
        </div>
        <div class="card-body">
            <table id="reconciliations-table" class="table table-bordered table-striped table-hover">
                <thead class="thead-dark">
                    <tr>
                        <th>Recon #</th>
                        <th>Bank</th>
                        <th>Statement Date</th>
                        <th>Period</th>
                        <th>Opening Bal.</th>
                        <th>Closing Bal.</th>
                        <th>GL Balance</th>
                        <th>Variance</th>
                        <th>Status</th>
                        <th>Prepared By</th>
                        <th width="100">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="mdi mdi-check-circle text-success mr-2"></i>Approve Reconciliation</h5>
                <button type="button" class="close"  data-bs-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to approve this reconciliation?</p>
                <p class="text-muted">Please ensure all items have been properly matched and verified.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirm-approve">Approve</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('/plugins/dataT/datatables.min.js') }}"></script>
<script>
$(document).ready(function() {
    var table = $('#reconciliations-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route('accounting.bank-reconciliation.datatable') }}',
            data: function(d) {
                d.bank_id = $('#filter_bank').val();
                d.status = $('#filter_status').val();
                d.date_from = $('#filter_date_from').val();
                d.date_to = $('#filter_date_to').val();
            }
        },
        columns: [
            { data: 'reconciliation_number', name: 'reconciliation_number' },
            { data: 'bank_name', name: 'bank.name' },
            { data: 'statement_date_formatted', name: 'statement_date' },
            { data: 'period', name: 'period', orderable: false },
            { data: 'opening_balance_formatted', name: 'statement_opening_balance', className: 'text-right' },
            { data: 'statement_balance_formatted', name: 'statement_closing_balance', className: 'text-right' },
            { data: 'gl_balance_formatted', name: 'gl_closing_balance', className: 'text-right' },
            { data: 'variance_formatted', name: 'variance', className: 'text-right' },
            { data: 'status_badge', name: 'status' },
            { data: 'prepared_by_name', name: 'preparedBy.name' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[2, 'desc']],
        pageLength: 25
    });

    $('#btn_filter').click(function() {
        table.ajax.reload();
    });

    $('#btn_reset').click(function() {
        $('#filter_bank, #filter_status').val('');
        $('#filter_date_from, #filter_date_to').val('');
        table.ajax.reload();
    });

    // Approve
    var currentId = null;
    $(document).on('click', '.approve-btn', function() {
        currentId = $(this).data('id');
        $('#approveModal').modal('show');
    });

    $('#confirm-approve').click(function() {
        $.ajax({
            url: '/accounting/bank-reconciliation/' + currentId + '/approve',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(res) {
                $('#approveModal').modal('hide');
                toastr.success(res.message);
                table.ajax.reload();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
            }
        });
    });

    // Enable/disable New Reconciliation button based on selection
    $('#create_recon_bank').on('change', function() {
        if ($(this).val()) {
            $('#btn_create_recon').prop('disabled', false);
        } else {
            $('#btn_create_recon').prop('disabled', true);
        }
    });
    // Redirect to create route with selected bank
    $('#btn_create_recon').on('click', function(e) {
        e.preventDefault();
        var bankId = $('#create_recon_bank').val();
        console.log('Bank ID selected:', bankId);
        if (bankId) {
            var url = '{{ url('accounting/bank-reconciliation/create') }}/' + bankId;
            console.log('Redirecting to:', url);
            window.location.href = url;
        } else {
            console.log('No bank selected');
        }
    });
});
</script>
@endpush
