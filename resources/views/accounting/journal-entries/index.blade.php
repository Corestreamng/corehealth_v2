@extends('admin.layouts.app')
@section('title', 'Journal Entries')
@section('page_name', 'Accounting')
@section('subpage_name', 'Journal Entries')

@section('content')
<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Summary Cards Row 1 -->
        <div class="row mb-3">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-primary" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-book-open-page-variant mr-1"></i> Total Entries</h5>
                    <div class="value text-primary">{{ number_format($stats['total'] ?? 0) }}</div>
                    <small class="text-muted">This period</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-warning" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-clock-outline mr-1"></i> Pending Approval</h5>
                    <div class="value text-warning">{{ number_format($stats['pending'] ?? 0) }}</div>
                    <small class="text-muted">Awaiting review</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-info" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-check-circle mr-1"></i> Approved</h5>
                    <div class="value text-info">{{ number_format($stats['approved'] ?? 0) }}</div>
                    <small class="text-muted">Ready to post</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-success" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-book-check mr-1"></i> Posted</h5>
                    <div class="value text-success">{{ number_format($stats['posted'] ?? 0) }}</div>
                    <small class="text-muted">Finalized</small>
                </div>
            </div>
        </div>

        <!-- Summary Cards Row 2 -->
        <div class="row mb-3">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-secondary" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-file-document-edit mr-1"></i> Draft</h5>
                    <div class="value text-secondary">{{ number_format($stats['draft'] ?? 0) }}</div>
                    <small class="text-muted">In progress</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-primary" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-currency-ngn mr-1"></i> Total Debits (Posted)</h5>
                    <div class="value text-primary">₦{{ number_format($stats['total_debits'] ?? 0, 2) }}</div>
                    <small class="text-muted">This period</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-dark" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-undo mr-1"></i> Reversed</h5>
                    <div class="value text-dark">{{ number_format($stats['reversed'] ?? 0) }}</div>
                    <small class="text-muted">Cancelled entries</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-info" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-robot mr-1"></i> Auto-Generated</h5>
                    <div class="value text-info">{{ number_format($stats['automated'] ?? 0) }}</div>
                    <small class="text-muted">From transactions</small>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="card-modern">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Journal Entries</h3>
                    <div>
                        @can('accounting.journal.create')
                        <a href="{{ route('accounting.journal-entries.create') }}" class="btn btn-primary btn-sm">
                            <i class="mdi mdi-plus"></i> New Entry
                        </a>
                        @endcan
                        <a href="{{ route('accounting.reports.index') }}" class="btn btn-outline-info btn-sm">
                            <i class="mdi mdi-chart-box"></i> Reports
                        </a>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <!-- Bulk Actions Bar (hidden by default) -->
                <div class="bulk-actions-bar d-none" id="bulk-actions-bar">
                    <div class="d-flex align-items-center">
                        <span class="mr-3"><strong><span id="selected-count">0</span></strong> entries selected</span>
                        @can('accounting.journal.approve')
                        <button type="button" class="btn btn-success btn-sm mr-2" onclick="bulkApprove()">
                            <i class="mdi mdi-check-all"></i> Approve Selected
                        </button>
                        @endcan
                        @can('accounting.journal.post')
                        <button type="button" class="btn btn-primary btn-sm mr-2" onclick="bulkPost()">
                            <i class="mdi mdi-book-check"></i> Post Selected
                        </button>
                        @endcan
                        <button type="button" class="btn btn-secondary btn-sm" onclick="clearSelection()">
                            <i class="mdi mdi-close"></i> Clear
                        </button>
                    </div>
                </div>

                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-2">
                        <select id="status-filter" class="form-control form-control-sm">
                            <option value="">All Statuses</option>
                            <option value="draft">Draft</option>
                            <option value="pending">Pending Approval</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="posted">Posted</option>
                            <option value="reversed">Reversed</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="entry-type-filter" class="form-control form-control-sm">
                            <option value="">All Types</option>
                            <option value="manual">Manual</option>
                            <option value="automated">Automated</option>
                            <option value="adjustment">Adjustment</option>
                            <option value="reversal">Reversal</option>
                            <option value="opening">Opening Balance</option>
                            <option value="closing">Closing</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="period-filter" class="form-control form-control-sm">
                            <option value="">All Periods</option>
                            @foreach($periods ?? [] as $period)
                            <option value="{{ $period->id }}">{{ $period->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" id="date-from" class="form-control form-control-sm" placeholder="From Date">
                    </div>
                    <div class="col-md-2">
                        <input type="date" id="date-to" class="form-control form-control-sm" placeholder="To Date">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearFilters()">
                            <i class="mdi mdi-filter-remove"></i> Clear
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="journal-entries-table" class="table table-sm table-bordered table-striped">
                        <thead>
                            <tr>
                                <th width="30"><input type="checkbox" id="select-all" class="select-all"></th>
                                <th>Entry #</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th class="text-right">Debit</th>
                                <th class="text-right">Credit</th>
                                <th>Status</th>
                                <th>Created By</th>
                                <th width="120">Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="mdi mdi-close-circle"></i> Reject Journal Entry
                </h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <strong>Entry:</strong> <span id="reject-entry-number"></span>
                </div>
                <div class="form-group">
                    <label for="rejection_reason">Rejection Reason <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="rejection_reason" rows="3"
                              placeholder="Please explain why this entry is being rejected..."
                              required></textarea>
                    <small class="text-muted">This reason will be sent to the entry creator.</small>
                </div>
                <input type="hidden" id="reject_entry_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="mdi mdi-close"></i> Cancel
                </button>
                <button type="button" class="btn btn-danger" onclick="confirmReject()">
                    <i class="mdi mdi-check"></i> Reject Entry
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reverse Modal -->
<div class="modal fade" id="reverseModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">
                    <i class="mdi mdi-undo"></i> Reverse Journal Entry
                </h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <strong>Warning!</strong> Reversing this entry will:
                    <ul class="mb-0 mt-2">
                        <li>Create a new reversing entry with opposite debits/credits</li>
                        <li>Mark the original entry as reversed</li>
                        <li>Both entries will remain in the ledger for audit purposes</li>
                    </ul>
                </div>
                <div class="form-group">
                    <label for="reversal_reason">Reversal Reason <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="reversal_reason" rows="3"
                              placeholder="Please explain why this entry is being reversed..."
                              required></textarea>
                </div>
                <input type="hidden" id="reverse_entry_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="mdi mdi-close"></i> Cancel
                </button>
                <button type="button" class="btn btn-dark" onclick="confirmReverse()">
                    <i class="mdi mdi-undo"></i> Reverse Entry
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<style>
    .stat-card {
        background: #fff;
        border-radius: 8px;
        padding: 1.25rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 1rem;
    }
    .stat-card h5 {
        font-size: 0.85rem;
        color: #6c757d;
        margin-bottom: 0.5rem;
    }
    .stat-card .value {
        font-size: 1.5rem;
        font-weight: 600;
    }
    .bulk-actions-bar {
        background: #f8f9fa;
        padding: 10px 15px;
        border-radius: 4px;
        margin-bottom: 15px;
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
<script>
var selectedEntries = [];

$(function() {
    var table = $('#journal-entries-table').DataTable({
        dom: 'Bfrtip',
        iDisplayLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        buttons: ['pageLength', 'copy', 'excel', 'pdf', 'print'],
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('accounting.journal-entries.datatable') }}",
            type: "GET",
            data: function(d) {
                d.status = $('#status-filter').val();
                d.entry_type = $('#entry-type-filter').val();
                d.period_id = $('#period-filter').val();
                d.date_from = $('#date-from').val();
                d.date_to = $('#date-to').val();
            }
        },
        columns: [
            {
                data: 'checkbox',
                name: 'checkbox',
                orderable: false,
                searchable: false,
                className: 'text-center'
            },
            { data: "entry_number", name: "entry_number" },
            { data: "entry_date_formatted", name: "entry_date" },
            { data: "entry_type_badge", name: "entry_type" },
            { data: "description", name: "description" },
            { data: "total_debit_formatted", name: "total_debit", className: "text-right" },
            { data: "total_credit_formatted", name: "total_credit", className: "text-right" },
            { data: "status_badge", name: "status" },
            { data: "created_by_name", name: "createdBy.name" },
            { data: "actions", name: "actions", orderable: false, searchable: false }
        ],
        order: [[2, 'desc']]
    });

    // Filters
    $('#status-filter, #entry-type-filter, #period-filter, #date-from, #date-to').on('change', function() {
        table.ajax.reload();
    });

    // Select all checkbox
    $('#journal-entries-table').on('click', '.select-all', function() {
        var checked = $(this).is(':checked');
        $('.row-checkbox').prop('checked', checked);
        updateSelectedEntries();
    });

    // Individual row checkbox
    $('#journal-entries-table').on('change', '.row-checkbox', function() {
        updateSelectedEntries();
    });
});

function updateSelectedEntries() {
    selectedEntries = [];
    $('.row-checkbox:checked').each(function() {
        selectedEntries.push($(this).val());
    });

    if (selectedEntries.length > 0) {
        $('#bulk-actions-bar').removeClass('d-none');
        $('#selected-count').text(selectedEntries.length);
    } else {
        $('#bulk-actions-bar').addClass('d-none');
    }
}

function clearSelection() {
    $('.row-checkbox, .select-all').prop('checked', false);
    selectedEntries = [];
    $('#bulk-actions-bar').addClass('d-none');
}

function clearFilters() {
    $('#status-filter, #entry-type-filter, #period-filter').val('');
    $('#date-from, #date-to').val('');
    $('#journal-entries-table').DataTable().ajax.reload();
}

// Single Entry Actions
function submitEntry(id) {
    if (confirm('Submit this entry for approval?')) {
        $.post(`{{ url('/accounting/journal-entries') }}/${id}/submit`, { _token: '{{ csrf_token() }}' })
            .done(function(response) {
                toastr.success(response.message || 'Entry submitted for approval');
                $('#journal-entries-table').DataTable().ajax.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to submit entry');
            });
    }
}

function approveEntry(id) {
    if (confirm('Approve this journal entry?')) {
        $.post(`{{ url('/accounting/journal-entries') }}/${id}/approve`, { _token: '{{ csrf_token() }}' })
            .done(function(response) {
                toastr.success(response.message || 'Entry approved');
                $('#journal-entries-table').DataTable().ajax.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to approve');
            });
    }
}

function rejectEntry(id, entryNumber) {
    $('#reject_entry_id').val(id);
    $('#reject-entry-number').text(entryNumber);
    $('#rejection_reason').val('');
    $('#rejectModal').modal('show');
}

function confirmReject() {
    const reason = $('#rejection_reason').val().trim();
    const entryId = $('#reject_entry_id').val();

    if (!reason) {
        toastr.error('Please provide a rejection reason');
        return;
    }

    if (reason.length < 10) {
        toastr.error('Rejection reason must be at least 10 characters');
        return;
    }

    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<i class="mdi mdi-loading mdi-spin"></i> Rejecting...';

    $.post(`{{ url('/accounting/journal-entries') }}/${entryId}/reject`, {
        _token: '{{ csrf_token() }}',
        rejection_reason: reason
    })
        .done(function(response) {
            toastr.success(response.message || 'Entry rejected');
            $('#rejectModal').modal('hide');
            $('#journal-entries-table').DataTable().ajax.reload();
        })
        .fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to reject entry');
        })
        .always(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="mdi mdi-check"></i> Reject Entry';
        });
}

function postEntry(id) {
    if (confirm('Post this journal entry to the ledger? This action cannot be undone.')) {
        $.post(`{{ url('/accounting/journal-entries') }}/${id}/post`, { _token: '{{ csrf_token() }}' })
            .done(function(response) {
                toastr.success(response.message || 'Entry posted to ledger');
                $('#journal-entries-table').DataTable().ajax.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to post entry');
            });
    }
}

function reverseEntry(id) {
    $('#reverse_entry_id').val(id);
    $('#reversal_reason').val('');
    $('#reverseModal').modal('show');
}

function confirmReverse() {
    const reason = $('#reversal_reason').val().trim();
    const entryId = $('#reverse_entry_id').val();

    if (!reason) {
        toastr.error('Please provide a reversal reason');
        return;
    }

    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<i class="mdi mdi-loading mdi-spin"></i> Reversing...';

    $.post(`{{ url('/accounting/journal-entries') }}/${entryId}/reverse`, {
        _token: '{{ csrf_token() }}',
        reversal_reason: reason
    })
        .done(function(response) {
            toastr.success(response.message || 'Entry reversed successfully');
            $('#reverseModal').modal('hide');
            $('#journal-entries-table').DataTable().ajax.reload();
        })
        .fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to reverse entry');
        })
        .always(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="mdi mdi-undo"></i> Reverse Entry';
        });
}

// Bulk Actions
function bulkApprove() {
    if (selectedEntries.length === 0) {
        toastr.warning('No entries selected');
        return;
    }

    if (confirm(`Approve ${selectedEntries.length} selected entries?`)) {
        $.post('{{ route("accounting.journal-entries.bulk-approve") }}', {
            _token: '{{ csrf_token() }}',
            entry_ids: selectedEntries
        })
            .done(function(response) {
                toastr.success(response.message || `${response.approved_count} entries approved`);
                clearSelection();
                $('#journal-entries-table').DataTable().ajax.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to approve entries');
            });
    }
}

function bulkPost() {
    if (selectedEntries.length === 0) {
        toastr.warning('No entries selected');
        return;
    }

    if (confirm(`Post ${selectedEntries.length} selected entries to the ledger?`)) {
        $.post('{{ route("accounting.journal-entries.bulk-post") }}', {
            _token: '{{ csrf_token() }}',
            entry_ids: selectedEntries
        })
            .done(function(response) {
                toastr.success(response.message || `${response.posted_count} entries posted`);
                clearSelection();
                $('#journal-entries-table').DataTable().ajax.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to post entries');
            });
    }
}
</script>
@endpush
