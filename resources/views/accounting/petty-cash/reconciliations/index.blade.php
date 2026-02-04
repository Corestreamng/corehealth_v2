@extends('admin.layouts.app')
@section('title', 'Reconciliations')
@section('page_name', 'Accounting')
@section('subpage_name', 'Petty Cash Reconciliations')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Petty Cash', 'url' => route('accounting.petty-cash.index'), 'icon' => 'mdi-cash-register'],
    ['label' => 'Reconciliations', 'url' => '#', 'icon' => 'mdi-scale-balance']
]])

<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="mdi mdi-scale-balance mr-2"></i>Petty Cash Reconciliations
                @if($pendingCount > 0)
                    <span class="badge badge-warning">{{ $pendingCount }} Pending Approval</span>
                @endif
            </h4>
            <p class="text-muted mb-0">Review and approve reconciliations with variances</p>
        </div>
        <a href="{{ route('accounting.petty-cash.index') }}" class="btn btn-outline-secondary">
            <i class="mdi mdi-arrow-left"></i> Back to Petty Cash
        </a>
    </div>

    <!-- Filters -->
    <div class="card-modern mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <label>Fund</label>
                    <select class="form-control filter-control" id="filter_fund">
                        <option value="">All Funds</option>
                        @foreach(\App\Models\Accounting\PettyCashFund::where('status', 'active')->get() as $fund)
                            <option value="{{ $fund->id }}">{{ $fund->fund_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label>Approval Status</label>
                    <select class="form-control filter-control" id="filter_approval">
                        <option value="">All</option>
                        <option value="pending_approval">Pending Approval</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label>Variance Status</label>
                    <select class="form-control filter-control" id="filter_status">
                        <option value="">All</option>
                        <option value="balanced">Balanced</option>
                        <option value="shortage">Shortage</option>
                        <option value="overage">Overage</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-secondary" id="clearFilters">
                        <i class="mdi mdi-filter-remove"></i> Clear Filters
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reconciliations Table -->
    <div class="card-modern">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="reconciliationsTable">
                    <thead class="thead-light">
                        <tr>
                            <th width="100">Date</th>
                            <th width="120">Recon #</th>
                            <th>Fund</th>
                            <th class="text-right" width="120">Book Balance</th>
                            <th class="text-right" width="120">Physical Count</th>
                            <th class="text-right" width="100">Variance</th>
                            <th width="80">Status</th>
                            <th width="100">Approval</th>
                            <th>Reconciled By</th>
                            <th width="130">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
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
                <h5 class="modal-title">Reject Reconciliation</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="rejectForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="rejection_reason">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" required
                                  placeholder="Please explain why this reconciliation is being rejected..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Initialize DataTable
    var table = $('#reconciliationsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('accounting.petty-cash.reconciliations.datatable') }}",
            data: function(d) {
                d.fund_id = $('#filter_fund').val();
                d.approval_status = $('#filter_approval').val();
                d.status = $('#filter_status').val();
            }
        },
        columns: [
            { data: 'reconciliation_date_formatted', name: 'reconciliation_date' },
            { data: 'reconciliation_number', name: 'reconciliation_number' },
            { data: 'fund_name', name: 'fund.fund_name' },
            { data: 'expected_formatted', name: 'expected_balance', className: 'text-right' },
            { data: 'actual_formatted', name: 'actual_cash_count', className: 'text-right' },
            { data: 'variance_formatted', name: 'variance', className: 'text-right' },
            { data: 'status_badge', name: 'status', orderable: false },
            { data: 'approval_badge', name: 'approval_status', orderable: false },
            { data: 'reconciled_by_name', name: 'reconciledBy.name' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 25
    });

    // Filter handlers
    $('.filter-control').on('change', function() {
        table.ajax.reload();
    });

    $('#clearFilters').on('click', function() {
        $('.filter-control').val('');
        table.ajax.reload();
    });

    // Approve reconciliation
    $(document).on('click', '.approve-btn', function() {
        var id = $(this).data('id');
        if (confirm('Are you sure you want to approve this reconciliation? This will create an adjustment journal entry.')) {
            $.ajax({
                url: "{{ route('accounting.petty-cash.reconciliations.approve', ':id') }}".replace(':id', id),
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

    // Reject reconciliation
    var rejectId = null;
    $(document).on('click', '.reject-btn', function() {
        rejectId = $(this).data('id');
        $('#rejection_reason').val('');
        $('#rejectModal').modal('show');
    });

    $('#rejectForm').submit(function(e) {
        e.preventDefault();
        $.ajax({
            url: "{{ route('accounting.petty-cash.reconciliations.reject', ':id') }}".replace(':id', rejectId),
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                rejection_reason: $('#rejection_reason').val()
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
@endsection
