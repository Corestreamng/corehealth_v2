@extends('admin.layouts.app')
@section('title', 'Petty Cash Management')
@section('page_name', 'Accounting')
@section('subpage_name', 'Petty Cash')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Petty Cash', 'url' => route('accounting.petty-cash.index'), 'icon' => 'mdi-cash-register']
]])

<div class="container-fluid">
        <!-- Header with Actions -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="mdi mdi-cash-register mr-2"></i>Petty Cash Management</h4>
                <p class="text-muted mb-0">Manage petty cash funds, disbursements, and reconciliations</p>
            </div>
            <div class="btn-group">
                <a href="{{ route('accounting.petty-cash.funds.index') }}" class="btn btn-outline-primary">
                    <i class="mdi mdi-wallet"></i> View All Funds
                </a>
                <a href="{{ route('accounting.petty-cash.funds.create') }}" class="btn btn-primary">
                    <i class="mdi mdi-plus"></i> New Fund
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card border-left border-primary" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-wallet mr-1"></i> Total Funds</h5>
                    <div class="value text-primary">{{ number_format($stats['total_funds']) }}</div>
                    <small class="text-muted">{{ $stats['active_funds'] }} active</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card border-left border-success" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-cash mr-1"></i> Total Balance</h5>
                    <div class="value text-success">₦{{ number_format($stats['total_balance'], 2) }}</div>
                    <small class="text-muted">Across all funds</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card border-left border-warning" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-clock-outline mr-1"></i> Pending</h5>
                    <div class="value text-warning">{{ number_format($stats['pending_transactions']) }}</div>
                    <small class="text-muted">Awaiting approval</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card border-left border-danger" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-cash-minus mr-1"></i> Today's Disbursements</h5>
                    <div class="value text-danger">₦{{ number_format($stats['today_disbursements'], 2) }}</div>
                    <small class="text-muted">{{ now()->format('M d, Y') }}</small>
                </div>
            </div>
        </div>

        <!-- Secondary Stats -->
        <div class="row mb-4">
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="stat-card border-left border-info" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-calendar-month mr-1"></i> Monthly Disbursements</h5>
                    <div class="value text-info">₦{{ number_format($stats['month_disbursements'], 2) }}</div>
                    <small class="text-muted">{{ now()->format('F Y') }}</small>
                </div>
            </div>
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="stat-card border-left border-secondary" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-percent mr-1"></i> Total Limit</h5>
                    <div class="value text-secondary">₦{{ number_format($stats['total_limit'], 2) }}</div>
                    <small class="text-muted">Combined fund limits</small>
                </div>
            </div>
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="stat-card border-left {{ $stats['low_balance_funds'] > 0 ? 'border-danger' : 'border-success' }}" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-alert-circle mr-1"></i> Low Balance Funds</h5>
                    <div class="value {{ $stats['low_balance_funds'] > 0 ? 'text-danger' : 'text-success' }}">
                        {{ $stats['low_balance_funds'] }}
                    </div>
                    <small class="text-muted">&lt; 20% of limit</small>
                </div>
            </div>
        </div>

        <!-- Quick Navigation Cards -->
        <div class="row mb-4">
            <div class="col-12">
                <h5 class="mb-3"><i class="mdi mdi-view-grid mr-2"></i>Quick Access</h5>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <a href="{{ route('accounting.petty-cash.funds.index') }}" class="nav-card nav-card-primary">
                    <div class="nav-card-icon"><i class="mdi mdi-wallet"></i></div>
                    <div class="nav-card-content">
                        <h6>All Funds</h6>
                        <p>Manage petty cash funds</p>
                    </div>
                    <i class="mdi mdi-chevron-right nav-card-arrow"></i>
                </a>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <a href="{{ route('accounting.petty-cash.funds.create') }}" class="nav-card nav-card-success">
                    <div class="nav-card-icon"><i class="mdi mdi-plus-circle"></i></div>
                    <div class="nav-card-content">
                        <h6>New Fund</h6>
                        <p>Create a new petty cash fund</p>
                    </div>
                    <i class="mdi mdi-chevron-right nav-card-arrow"></i>
                </a>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <a href="#" class="nav-card nav-card-warning" id="pending-btn">
                    <div class="nav-card-icon"><i class="mdi mdi-clock-alert"></i></div>
                    <div class="nav-card-content">
                        <h6>Pending Approvals</h6>
                        <p>{{ $stats['pending_transactions'] }} transactions waiting</p>
                    </div>
                    <i class="mdi mdi-chevron-right nav-card-arrow"></i>
                </a>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <a href="{{ route('accounting.reports.index') }}" class="nav-card nav-card-info">
                    <div class="nav-card-icon"><i class="mdi mdi-file-chart"></i></div>
                    <div class="nav-card-content">
                        <h6>Reports</h6>
                        <p>Export petty cash reports</p>
                    </div>
                    <i class="mdi mdi-chevron-right nav-card-arrow"></i>
                </a>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="card card-modern">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="mdi mdi-history mr-2"></i>Recent Transactions</h5>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="refresh-btn">
                        <i class="mdi mdi-refresh"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Fund</label>
                        <select class="form-control form-control-sm filter-control" id="fund-filter">
                            <option value="">All Funds</option>
                            @foreach(\App\Models\Accounting\PettyCashFund::where('status', 'active')->get() as $fund)
                                <option value="{{ $fund->id }}">{{ $fund->fund_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select class="form-control form-control-sm filter-control" id="status-filter">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Type</label>
                        <select class="form-control form-control-sm filter-control" id="type-filter">
                            <option value="">All Types</option>
                            <option value="disbursement">Disbursement</option>
                            <option value="replenishment">Replenishment</option>
                            <option value="adjustment">Adjustment</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Date From</label>
                        <input type="date" class="form-control form-control-sm filter-control" id="date-from">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Date To</label>
                        <input type="date" class="form-control form-control-sm filter-control" id="date-to">
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button class="btn btn-outline-secondary btn-sm w-100" id="clear-filters">
                            <i class="mdi mdi-filter-remove"></i>
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover" id="transactions-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Fund</th>
                                <th>Voucher #</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th class="text-right">Amount</th>
                                <th>Status</th>
                                <th>Requested By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Transaction</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="rejectForm">
                <div class="modal-body">
                    <input type="hidden" id="reject-transaction-id">
                    <div class="form-group">
                        <label>Rejection Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="rejection-reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Transaction</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.stat-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.stat-card h5 {
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 8px;
}
.stat-card .value {
    font-size: 1.75rem;
    font-weight: 600;
    margin-bottom: 4px;
}
.nav-card {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    background: #fff;
    border-radius: 8px;
    border-left: 4px solid;
    text-decoration: none !important;
    color: inherit;
    transition: all 0.2s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.nav-card:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.nav-card-icon {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: #fff;
    margin-right: 15px;
}
.nav-card-primary { border-color: #007bff; }
.nav-card-primary .nav-card-icon { background: #007bff; }
.nav-card-success { border-color: #28a745; }
.nav-card-success .nav-card-icon { background: #28a745; }
.nav-card-warning { border-color: #ffc107; }
.nav-card-warning .nav-card-icon { background: #ffc107; }
.nav-card-info { border-color: #17a2b8; }
.nav-card-info .nav-card-icon { background: #17a2b8; }
.nav-card-content h6 { margin: 0 0 2px; font-weight: 600; }
.nav-card-content p { margin: 0; font-size: 0.8rem; color: #6c757d; }
.nav-card-arrow { margin-left: auto; color: #adb5bd; }
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    var table = $('#transactions-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('accounting.petty-cash.datatable') }}",
            data: function(d) {
                d.fund_id = $('#fund-filter').val();
                d.status = $('#status-filter').val();
                d.transaction_type = $('#type-filter').val();
                d.date_from = $('#date-from').val();
                d.date_to = $('#date-to').val();
            }
        },
        columns: [
            { data: 'transaction_date_formatted', name: 'transaction_date' },
            { data: 'fund_name', name: 'fund.fund_name' },
            { data: 'voucher_number', name: 'voucher_number' },
            { data: 'type_badge', name: 'transaction_type' },
            { data: 'description', name: 'description' },
            { data: 'amount_formatted', name: 'amount', className: 'text-right' },
            { data: 'status_badge', name: 'status' },
            { data: 'requested_by_name', name: 'requestedBy.name' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 15,
        lengthMenu: [[10, 15, 25, 50, -1], [10, 15, 25, 50, "All"]]
    });

    // Filter controls
    $('.filter-control').on('change', function() {
        table.ajax.reload();
    });

    $('#clear-filters').click(function() {
        $('.filter-control').val('');
        table.ajax.reload();
    });

    $('#refresh-btn').click(function() {
        table.ajax.reload();
    });

    // Show pending only
    $('#pending-btn').click(function(e) {
        e.preventDefault();
        $('#status-filter').val('pending').trigger('change');
    });

    // Approve transaction
    $(document).on('click', '.approve-btn', function() {
        var id = $(this).data('id');
        if (confirm('Are you sure you want to approve this transaction?')) {
            $.ajax({
                url: "{{ route('accounting.petty-cash.transactions.approve', '') }}/" + id,
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(res) {
                    if (res.success) {
                        toastr.success(res.message);
                        table.ajax.reload();
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'An error occurred');
                }
            });
        }
    });

    // Reject transaction
    var rejectId = null;
    $(document).on('click', '.reject-btn', function() {
        rejectId = $(this).data('id');
        $('#rejection-reason').val('');
        $('#rejectModal').modal('show');
    });

    $('#rejectForm').submit(function(e) {
        e.preventDefault();
        $.ajax({
            url: "{{ route('accounting.petty-cash.transactions.reject', '') }}/" + rejectId,
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                rejection_reason: $('#rejection-reason').val()
            },
            success: function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    $('#rejectModal').modal('hide');
                    table.ajax.reload();
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
            }
        });
    });
});
</script>
@endpush
