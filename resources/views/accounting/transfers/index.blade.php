{{--
    Inter-Account Transfers Index
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 2
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'Inter-Account Transfers')
@section('page_name', 'Accounting')
@section('subpage_name', 'Bank Transfers')
<style>
    .stat-card {
        padding: 20px;
        background: #fff;
        border-radius: 6px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 20px;
        border-left: 4px solid;
    }
    .stat-card .label { font-size: 0.85rem; color: #666; margin-bottom: 5px; }
    .stat-card .value { font-size: 1.8rem; font-weight: 600; }
    .border-info { border-color: #17a2b8 !important; }
    .border-warning { border-color: #ffc107 !important; }
    .border-primary { border-color: #007bff !important; }
    .border-success { border-color: #28a745 !important; }
    .text-info { color: #17a2b8 !important; }
    .text-warning { color: #ffc107 !important; }
    .text-primary { color: #007bff !important; }
    .text-success { color: #28a745 !important; }
    .filter-panel {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .method-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 15px;
    }
    .method-legend .badge {
        font-size: 0.75rem;
        padding: 5px 10px;
    }
</style>
@endsection

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Inter-Account Transfers', 'url' => route('accounting.transfers.index'), 'icon' => 'mdi-bank-transfer']
    ]
])

<div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="mdi mdi-bank-transfer text-primary mr-2"></i>Inter-Account Transfers</h4>
                <a href="{{ route('accounting.transfers.create') }}" class="btn btn-primary">
                    <i class="mdi mdi-plus mr-1"></i> New Transfer
                </a>
            </div>

            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card border-info">
                        <div class="label">Total Transfers</div>
                        <div class="value text-info">{{ number_format($stats['total_transfers']) }}</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card border-warning">
                        <div class="label">Pending Approval</div>
                        <div class="value text-warning">{{ number_format($stats['pending_approval']) }}</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card border-primary">
                        <div class="label">In Transit</div>
                        <div class="value text-primary">{{ number_format($stats['in_transit']) }}</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card border-success">
                        <div class="label">Cleared Today</div>
                        <div class="value text-success">{{ number_format($stats['cleared_today']) }}</div>
                    </div>
                </div>
            </div>

            <!-- Amount Summary -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="info-box bg-info">
                        <span class="info-box-icon"><i class="mdi mdi-calendar-today"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Today's Volume</span>
                            <span class="info-box-number">₦{{ number_format($stats['today_amount'], 2) }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-box bg-primary">
                        <span class="info-box-icon"><i class="mdi mdi-calendar-month"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">This Month</span>
                            <span class="info-box-number">₦{{ number_format($stats['month_amount'], 2) }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-box bg-warning">
                        <span class="info-box-icon"><i class="mdi mdi-timer-sand"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Pending Clearance</span>
                            <span class="info-box-number">₦{{ number_format($stats['pending_clearance_amount'], 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Method Legend -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Transfer Methods</h3>
                </div>
                <div class="card-body py-2">
                    <div class="method-legend">
                        <span class="badge badge-info">INTERNAL - Same Bank</span>
                        <span class="badge badge-primary">WIRE - Wire Transfer</span>
                        <span class="badge badge-success">EFT - Electronic Funds</span>
                        <span class="badge badge-warning text-dark">CHEQUE - Cheque Payment</span>
                        <span class="badge badge-dark">RTGS - Real-Time Gross</span>
                        <span class="badge badge-secondary">NEFT - National EFT</span>
                    </div>
                </div>
            </div>

            <!-- Filter Panel -->
            <div class="filter-panel">
                <div class="row">
                    <div class="col-md-2">
                        <label>Status</label>
                        <select id="filter_status" class="form-control form-control-sm select2">
                            <option value="">All Statuses</option>
                            <option value="draft">Draft</option>
                            <option value="pending_approval">Pending Approval</option>
                            <option value="approved">Approved</option>
                            <option value="initiated">Initiated</option>
                            <option value="in_transit">In Transit</option>
                            <option value="cleared">Cleared</option>
                            <option value="failed">Failed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>Transfer Method</label>
                        <select id="filter_method" class="form-control form-control-sm select2">
                            <option value="">All Methods</option>
                            <option value="internal">Internal</option>
                            <option value="wire">Wire</option>
                            <option value="eft">EFT</option>
                            <option value="cheque">Cheque</option>
                            <option value="rtgs">RTGS</option>
                            <option value="neft">NEFT</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>From Bank</label>
                        <select id="filter_from_bank" class="form-control form-control-sm select2" data-placeholder="Select Bank">
                            <option value="">All Banks</option>
                            @foreach(\App\Models\Bank::where('is_active', true)->orderBy('name')->get() as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->bank_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>To Bank</label>
                        <select id="filter_to_bank" class="form-control form-control-sm select2" data-placeholder="Select Bank">
                            <option value="">All Banks</option>
                            @foreach(\App\Models\Bank::where('is_active', true)->orderBy('name')->get() as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->bank_name }}</option>
                            @endforeach
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
                </div>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <button id="btn_filter" class="btn btn-primary btn-sm">
                            <i class="mdi mdi-filter mr-1"></i> Apply Filters
                        </button>
                        <button id="btn_reset" class="btn btn-secondary btn-sm ml-2">
                            <i class="mdi mdi-refresh mr-1"></i> Reset
                        </button>
                        <div class="float-right">
                            <button id="btn_export_pdf" class="btn btn-danger btn-sm">
                                <i class="mdi mdi-file-pdf mr-1"></i> Export PDF
                            </button>
                            <button id="btn_export_excel" class="btn btn-success btn-sm ml-2">
                                <i class="mdi mdi-file-excel mr-1"></i> Export Excel
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transfers DataTable -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Transfer Records</h3>
                </div>
                <div class="card-body">
                    <table id="transfers-table" class="table table-bordered table-striped table-hover">
                        <thead class="thead-dark">
                            <tr>
                                <th>Transfer #</th>
                                <th>Date</th>
                                <th>From Bank</th>
                                <th>To Bank</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Initiated By</th>
                                <th width="120">Actions</th>
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
                <h5 class="modal-title"><i class="mdi mdi-check-circle text-success mr-2"></i>Approve Transfer</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to approve this transfer?</p>
                <p class="text-muted">This will create the journal entry and initiate the transfer process.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirm-approve">Approve Transfer</button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="mdi mdi-close-circle text-danger mr-2"></i>Reject Transfer</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Rejection Reason <span class="text-danger">*</span></label>
                    <textarea id="rejection_reason" class="form-control" rows="3" required placeholder="Enter reason for rejection..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-reject">Reject Transfer</button>
            </div>
        </div>
    </div>
</div>

<!-- Clearance Modal -->
<div class="modal fade" id="clearanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="mdi mdi-bank-check text-success mr-2"></i>Confirm Clearance</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Clearance Date</label>
                    <input type="date" id="clearance_date" class="form-control" value="{{ date('Y-m-d') }}">
                </div>
                <div class="form-group">
                    <label>Notes (Optional)</label>
                    <textarea id="clearance_notes" class="form-control" rows="2" placeholder="Any additional notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirm-clearance">Confirm Clearance</button>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="mdi mdi-cancel text-dark mr-2"></i>Cancel Transfer</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this transfer?</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">No, Keep It</button>
                <button type="button" class="btn btn-dark" id="confirm-cancel">Yes, Cancel Transfer</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('/plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
<script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap4',
        allowClear: true,
        width: '100%'
    });

    // Initialize DataTable
    var table = $('#transfers-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route('accounting.transfers.datatable') }}',
            data: function(d) {
                d.status = $('#filter_status').val();
                d.from_bank_id = $('#filter_from_bank').val();
                d.to_bank_id = $('#filter_to_bank').val();
                d.transfer_method = $('#filter_method').val();
                d.date_from = $('#filter_date_from').val();
                d.date_to = $('#filter_date_to').val();
            }
        },
        columns: [
            { data: 'transfer_number', name: 'transfer_number' },
            { data: 'transfer_date_formatted', name: 'transfer_date' },
            { data: 'from_bank_name', name: 'from_bank.name' },
            { data: 'to_bank_name', name: 'to_bank.name' },
            { data: 'amount_formatted', name: 'amount', className: 'text-right' },
            { data: 'method_badge', name: 'transfer_method' },
            { data: 'status_badge', name: 'status' },
            { data: 'initiator_name', name: 'initiator.name' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[1, 'desc']],
        pageLength: 25
    });

    // Filter events
    $('#btn_filter').click(function() {
        table.ajax.reload();
    });

    $('#btn_reset').click(function() {
        $('#filter_status, #filter_from_bank, #filter_to_bank, #filter_method').val('').trigger('change');
        $('#filter_date_from, #filter_date_to').val('');
        table.ajax.reload();
    });

    // Action buttons
    var currentId = null;

    $(document).on('click', '.approve-btn', function() {
        currentId = $(this).data('id');
        $('#approveModal').modal('show');
    });

    $(document).on('click', '.reject-btn', function() {
        currentId = $(this).data('id');
        $('#rejection_reason').val('');
        $('#rejectModal').modal('show');
    });

    $(document).on('click', '.clearance-btn', function() {
        currentId = $(this).data('id');
        $('#clearance_date').val('{{ date('Y-m-d') }}');
        $('#clearance_notes').val('');
        $('#clearanceModal').modal('show');
    });

    $(document).on('click', '.cancel-btn', function() {
        currentId = $(this).data('id');
        $('#cancelModal').modal('show');
    });

    // Confirm actions
    $('#confirm-approve').click(function() {
        $.ajax({
            url: '/accounting/transfers/' + currentId + '/approve',
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

    $('#confirm-reject').click(function() {
        var reason = $('#rejection_reason').val().trim();
        if (!reason) {
            toastr.error('Please provide a rejection reason');
            return;
        }
        $.ajax({
            url: '/accounting/transfers/' + currentId + '/reject',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}', rejection_reason: reason },
            success: function(res) {
                $('#rejectModal').modal('hide');
                toastr.success(res.message);
                table.ajax.reload();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
            }
        });
    });

    $('#confirm-clearance').click(function() {
        $.ajax({
            url: '/accounting/transfers/' + currentId + '/confirm-clearance',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                clearance_date: $('#clearance_date').val(),
                notes: $('#clearance_notes').val()
            },
            success: function(res) {
                $('#clearanceModal').modal('hide');
                toastr.success(res.message);
                table.ajax.reload();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
            }
        });
    });

    $('#confirm-cancel').click(function() {
        $.ajax({
            url: '/accounting/transfers/' + currentId + '/cancel',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(res) {
                $('#cancelModal').modal('hide');
                toastr.success(res.message);
                table.ajax.reload();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
            }
        });
    });

    // Export buttons
    function buildQueryString() {
        return $.param({
            status: $('#filter_status').val(),
            from_bank_id: $('#filter_from_bank').val(),
            to_bank_id: $('#filter_to_bank').val(),
            transfer_method: $('#filter_method').val(),
            date_from: $('#filter_date_from').val(),
            date_to: $('#filter_date_to').val()
        });
    }

    $('#btn_export_pdf').click(function() {
        window.location.href = '{{ route('accounting.transfers.export.pdf') }}?' + buildQueryString();
    });

    $('#btn_export_excel').click(function() {
        window.location.href = '{{ route('accounting.transfers.export.excel') }}?' + buildQueryString();
    });
});
</script>
@endpush
