@extends('admin.layouts.app')

@section('page_name', 'Accounting')
@section('subpage_name', 'Credit Notes')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Credit Notes', 'url' => route('accounting.credit-notes.index'), 'icon' => 'mdi-note-text']
]])

<div class="container-fluid">
    {{-- Header with Title and Action Button --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Credit Notes</h4>
            <p class="text-muted mb-0">Manage patient refunds and credit notes</p>
        </div>
        @can('credit-notes.create')
        <div>
            <a href="{{ route('accounting.credit-notes.create') }}" class="btn btn-primary">
                <i class="mdi mdi-plus mr-1"></i> New Credit Note
            </a>
        </div>
        @endcan
    </div>

    {{-- Stat Cards Row --}}
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card card-modern h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Credit Notes</h6>
                            <h3 class="mb-0">{{ number_format($stats['total']) }}</h3>
                        </div>
                        <div class="stat-icon bg-primary-light">
                            <i class="mdi mdi-file-document text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card card-modern h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Pending Approval</h6>
                            <h3 class="mb-0 text-warning">{{ number_format($stats['pending']) }}</h3>
                            <small class="text-muted">₦{{ number_format($stats['pending_amount'], 2) }}</small>
                        </div>
                        <div class="stat-icon bg-warning-light">
                            <i class="mdi mdi-clock-outline text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card card-modern h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Approved</h6>
                            <h3 class="mb-0 text-success">{{ number_format($stats['approved']) }}</h3>
                            <small class="text-muted">Ready for refund</small>
                        </div>
                        <div class="stat-icon bg-success-light">
                            <i class="mdi mdi-check-circle text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card card-modern h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Processed/Refunded</h6>
                            <h3 class="mb-0 text-info">{{ number_format($stats['processed']) }}</h3>
                            <small class="text-muted">₦{{ number_format($stats['processed_amount'], 2) }}</small>
                        </div>
                        <div class="stat-icon bg-info-light">
                            <i class="mdi mdi-cash-refund text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters Card --}}
    <div class="card card-modern mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="mdi mdi-filter-variant mr-2"></i>Filters</h5>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearFilters">
                <i class="mdi mdi-close mr-1"></i>Clear All
            </button>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Status</label>
                    <select id="filterStatus" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="draft">Draft</option>
                        <option value="pending_approval">Pending Approval</option>
                        <option value="approved">Approved</option>
                        <option value="processed">Processed</option>
                        <option value="void">Voided</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Patient</label>
                    <select id="filterPatient" class="form-control select2" style="width: 100%;">
                        <option value="">All Patients</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">From Date</label>
                    <input type="date" id="filterFromDate" class="form-control">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">To Date</label>
                    <input type="date" id="filterToDate" class="form-control">
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <button type="button" class="btn btn-primary" id="btnApplyFilters">
                        <i class="mdi mdi-magnify mr-1"></i> Apply Filters
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Bulk Actions Bar (hidden by default) --}}
    <div class="card card-modern mb-4" id="bulkActionsBar" style="display: none;">
        <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span id="selectedCount">0</span> credit note(s) selected
                </div>
                <div class="btn-group">
                    @can('credit-notes.approve')
                    <button type="button" class="btn btn-success btn-sm" id="btnBulkApprove">
                        <i class="mdi mdi-check-all mr-1"></i> Approve Selected
                    </button>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    {{-- Credit Notes DataTable --}}
    <div class="card card-modern">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="mdi mdi-file-document-outline mr-2"></i>Credit Notes List</h5>
            <div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnExport">
                    <i class="mdi mdi-download mr-1"></i>Export
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="creditNotesTable" class="table table-hover table-striped w-100">
                    <thead class="thead-light">
                        <tr>
                            <th width="30">
                                <input type="checkbox" id="selectAll" class="form-check-input">
                            </th>
                            <th>Credit Note #</th>
                            <th>Date</th>
                            <th>Patient</th>
                            <th>Invoice</th>
                            <th>Reason</th>
                            <th class="text-right">Amount</th>
                            <th class="text-center">Status</th>
                            <th width="100">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Reject Modal --}}
<div class="modal fade" id="rejectModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="mdi mdi-close-circle mr-2"></i>Reject Credit Note</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="rejectForm">
                <div class="modal-body">
                    <p>You are about to reject credit note <strong id="rejectCreditNoteNumber"></strong>.</p>
                    <div class="form-group">
                        <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="3"
                                  required placeholder="Enter reason for rejection..."></textarea>
                    </div>
                    <input type="hidden" id="rejectCreditNoteId" name="id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="mdi mdi-close mr-1"></i>Reject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Apply Refund Modal --}}
<div class="modal fade" id="applyModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="mdi mdi-cash-refund mr-2"></i>Apply Refund</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="applyForm">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Credit Note:</strong> <span id="applyCreditNoteNumber"></span><br>
                        <strong>Amount:</strong> ₦<span id="applyCreditNoteAmount"></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Refund Method <span class="text-danger">*</span></label>
                        <select name="refund_method" id="refund_method" class="form-control" required>
                            <option value="">Select Method...</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                            <option value="wallet_credit">Wallet Credit</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reference Number</label>
                        <input type="text" name="refund_reference" id="refund_reference" class="form-control"
                               placeholder="Transaction reference, cheque number, etc.">
                    </div>
                    <input type="hidden" id="applyCreditNoteId" name="id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="mdi mdi-cash-refund mr-1"></i>Process Refund
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
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

    .card-modern {
        border: none;
        box-shadow: 0 0 10px rgba(0,0,0,0.05);
        border-radius: 10px;
    }
    .card-modern .card-header {
        background-color: transparent;
        border-bottom: 1px solid #eee;
    }

    #creditNotesTable tbody tr {
        cursor: pointer;
    }
    #creditNotesTable tbody tr:hover {
        background-color: #f8f9fa;
    }
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    // Initialize DataTable
    var table = $('#creditNotesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("accounting.credit-notes.datatable") }}',
            data: function(d) {
                d.status = $('#filterStatus').val();
                d.patient_id = $('#filterPatient').val();
                d.from_date = $('#filterFromDate').val();
                d.to_date = $('#filterToDate').val();
            }
        },
        columns: [
            { data: 'checkbox', name: 'checkbox', orderable: false, searchable: false },
            { data: 'credit_note_link', name: 'credit_note_number' },
            { data: 'formatted_date', name: 'credit_note_date' },
            { data: 'patient_name', name: 'patient.user.name' },
            { data: 'invoice_number', name: 'invoice.invoice_number', orderable: false },
            { data: 'reason', name: 'reason' },
            { data: 'formatted_amount', name: 'total_amount', className: 'text-right' },
            { data: 'status_badge', name: 'status', className: 'text-center' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[2, 'desc']],
        pageLength: 25,
        language: {
            processing: '<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>'
        },
        drawCallback: function() {
            updateSelectedCount();
        }
    });

    // Initialize Select2 for patient filter
    $('#filterPatient').select2({
        placeholder: 'Search patient...',
        allowClear: true,
        ajax: {
            url: '/api/patients/search',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { q: params.term };
            },
            processResults: function(data) {
                return {
                    results: data.map(function(item) {
                        return { id: item.id, text: item.fullname + ' (' + item.mrn + ')' };
                    })
                };
            }
        }
    });

    // Apply Filters
    $('#btnApplyFilters').on('click', function() {
        table.ajax.reload();
    });

    // Clear Filters
    $('#btnClearFilters').on('click', function() {
        $('#filterStatus').val('').trigger('change');
        $('#filterPatient').val('').trigger('change');
        $('#filterFromDate').val('');
        $('#filterToDate').val('');
        table.ajax.reload();
    });

    // Select All Checkbox
    $('#selectAll').on('change', function() {
        $('.row-checkbox').prop('checked', $(this).is(':checked'));
        updateSelectedCount();
    });

    // Individual checkbox change
    $(document).on('change', '.row-checkbox', function() {
        updateSelectedCount();
    });

    // Update selected count
    function updateSelectedCount() {
        var count = $('.row-checkbox:checked').length;
        $('#selectedCount').text(count);
        if (count > 0) {
            $('#bulkActionsBar').slideDown();
        } else {
            $('#bulkActionsBar').slideUp();
        }
    }

    // Approve single credit note
    $(document).on('click', '.btn-approve', function(e) {
        e.preventDefault();
        var id = $(this).data('id');

        if (!confirm('Are you sure you want to approve this credit note?')) {
            return;
        }

        $.post('{{ url("accounting/credit-notes") }}/' + id + '/approve', {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            if (response.success) {
                toastr.success(response.message);
                table.ajax.reload(null, false);
            } else {
                toastr.error(response.message || 'Error approving credit note');
            }
        })
        .fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error approving credit note');
        });
    });

    // Reject modal
    $(document).on('click', '.btn-reject', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var number = $(this).data('number');

        $('#rejectCreditNoteId').val(id);
        $('#rejectCreditNoteNumber').text(number);
        $('#rejection_reason').val('');
        $('#rejectModal').modal('show');
    });

    // Reject form submit
    $('#rejectForm').on('submit', function(e) {
        e.preventDefault();
        var id = $('#rejectCreditNoteId').val();

        $.post('{{ url("accounting/credit-notes") }}/' + id + '/reject', {
            _token: '{{ csrf_token() }}',
            rejection_reason: $('#rejection_reason').val()
        })
        .done(function(response) {
            if (response.success) {
                toastr.success(response.message);
                $('#rejectModal').modal('hide');
                table.ajax.reload(null, false);
            } else {
                toastr.error(response.message || 'Error rejecting credit note');
            }
        })
        .fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error rejecting credit note');
        });
    });

    // Apply refund modal
    $(document).on('click', '.btn-apply', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var number = $(this).data('number');
        var amount = $(this).data('amount');

        $('#applyCreditNoteId').val(id);
        $('#applyCreditNoteNumber').text(number);
        $('#applyCreditNoteAmount').text(parseFloat(amount).toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('#refund_method').val('');
        $('#refund_reference').val('');
        $('#applyModal').modal('show');
    });

    // Apply form submit
    $('#applyForm').on('submit', function(e) {
        e.preventDefault();
        var id = $('#applyCreditNoteId').val();

        $.post('{{ url("accounting/credit-notes") }}/' + id + '/apply', {
            _token: '{{ csrf_token() }}',
            refund_method: $('#refund_method').val(),
            refund_reference: $('#refund_reference').val()
        })
        .done(function(response) {
            if (response.success) {
                toastr.success(response.message);
                $('#applyModal').modal('hide');
                table.ajax.reload(null, false);
            } else {
                toastr.error(response.message || 'Error processing refund');
            }
        })
        .fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error processing refund');
        });
    });

    // Bulk approve
    $('#btnBulkApprove').on('click', function() {
        var ids = [];
        $('.row-checkbox:checked').each(function() {
            ids.push($(this).val());
        });

        if (ids.length === 0) {
            toastr.warning('Please select at least one credit note');
            return;
        }

        if (!confirm('Are you sure you want to approve ' + ids.length + ' credit note(s)?')) {
            return;
        }

        $.post('{{ route("accounting.credit-notes.bulk-approve") }}', {
            _token: '{{ csrf_token() }}',
            ids: ids
        })
        .done(function(response) {
            if (response.success) {
                toastr.success(response.message);
                table.ajax.reload(null, false);
                $('#selectAll').prop('checked', false);
            } else {
                toastr.error(response.message || 'Error processing bulk approve');
            }
        })
        .fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error processing bulk approve');
        });
    });

    // Export
    $('#btnExport').on('click', function() {
        var params = $.param({
            status: $('#filterStatus').val(),
            patient_id: $('#filterPatient').val(),
            from_date: $('#filterFromDate').val(),
            to_date: $('#filterToDate').val(),
            export: 'excel'
        });
        window.location.href = '{{ route("accounting.credit-notes.index") }}?' + params;
    });
});
</script>
@endpush
