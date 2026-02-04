{{--
    Transfer Details View
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 2
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'Transfer Details')
@section('page_name', 'Accounting')
@section('subpage_name', 'Transfer Details')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Transfers', 'url' => route('accounting.transfers.index'), 'icon' => 'mdi-bank-transfer'],
        ['label' => $transfer->transfer_number, 'url' => '#', 'icon' => 'mdi-information']
    ]
])

<style>
.transfer-timeline {
    position: relative;
    padding-left: 30px;
}
.transfer-timeline::before {
    content: '';
    position: absolute;
    left: 8px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}
.timeline-item {
    position: relative;
    padding-bottom: 20px;
}
.timeline-item::before {
    content: '';
    position: absolute;
    left: -26px;
    top: 4px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #dee2e6;
    border: 2px solid #fff;
}
.timeline-item.active::before {
    background: #007bff;
}
.timeline-item.completed::before {
    background: #28a745;
}
.timeline-item.failed::before {
    background: #dc3545;
}
.status-badge-lg {
    padding: 8px 16px;
    font-size: 0.9rem;
    border-radius: 20px;
}
.detail-card {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
    margin-bottom: 20px;
}
.detail-card .label {
    font-size: 0.8rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
}
.detail-card .value {
    font-size: 1.1rem;
    font-weight: 500;
}
.amount-highlight {
    font-size: 2.5rem;
    font-weight: 700;
    color: #007bff;
}
.bank-info {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}
.bank-info .bank-icon {
    font-size: 3rem;
    margin-bottom: 10px;
}
.bank-info .bank-name {
    font-size: 1.2rem;
    font-weight: 600;
}
.bank-info .bank-account {
    font-size: 0.9rem;
    color: #6c757d;
}
</style>

<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="mdi mdi-bank-transfer mr-2"></i>{{ $transfer->transfer_number }}
            </h4>
            <p class="text-muted mb-0">
                Initiated on {{ $transfer->transfer_date->format('F d, Y') }} by {{ $transfer->initiator?->name ?? 'System' }}
            </p>
        </div>
        <div>
            @php
                $statusColors = [
                    'draft' => 'secondary',
                    'pending_approval' => 'warning',
                    'approved' => 'info',
                    'initiated' => 'primary',
                    'in_transit' => 'info',
                    'cleared' => 'success',
                    'failed' => 'danger',
                    'cancelled' => 'dark',
                ];
                $statusLabels = [
                    'draft' => 'Draft',
                    'pending_approval' => 'Pending Approval',
                    'approved' => 'Approved',
                    'initiated' => 'Initiated',
                    'in_transit' => 'In Transit',
                    'cleared' => 'Cleared',
                    'failed' => 'Failed',
                    'cancelled' => 'Cancelled',
                ];
            @endphp
            <span class="status-badge-lg badge badge-{{ $statusColors[$transfer->status] ?? 'secondary' }}">
                <i class="mdi mdi-{{ $transfer->status === 'cleared' ? 'check-circle' : ($transfer->status === 'failed' ? 'alert-circle' : 'clock-outline') }} mr-1"></i>
                {{ $statusLabels[$transfer->status] ?? ucfirst($transfer->status) }}
            </span>
        </div>
    </div>

    <div class="row">
        <!-- Left Column - Transfer Details -->
        <div class="col-lg-8">
            <!-- Transfer Flow -->
            <div class="card-modern">
                <div class="card-header">
                    <h5 class="mb-0"><i class="mdi mdi-swap-horizontal mr-2"></i>Transfer Flow</h5>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <div class="bank-info">
                                <div class="bank-icon text-danger"><i class="mdi mdi-bank-minus"></i></div>
                                <div class="bank-name">{{ $transfer->fromBank?->bank_name ?? 'N/A' }}</div>
                                <div class="bank-account">{{ $transfer->fromBank?->account_number ?? '' }}</div>
                                <small class="text-muted">Source Account</small>
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="mb-2">
                                <i class="mdi mdi-arrow-right-bold-outline text-primary" style="font-size: 3rem;"></i>
                            </div>
                            <div class="amount-highlight">₦{{ number_format($transfer->amount, 2) }}</div>
                            <span class="badge badge-{{ ['internal' => 'info', 'wire' => 'primary', 'eft' => 'success', 'cheque' => 'warning', 'rtgs' => 'dark', 'neft' => 'secondary'][$transfer->transfer_method] ?? 'secondary' }}">
                                {{ strtoupper($transfer->transfer_method) }}
                            </span>
                        </div>
                        <div class="col-md-4">
                            <div class="bank-info">
                                <div class="bank-icon text-success"><i class="mdi mdi-bank-plus"></i></div>
                                <div class="bank-name">{{ $transfer->toBank?->bank_name ?? 'N/A' }}</div>
                                <div class="bank-account">{{ $transfer->toBank?->account_number ?? '' }}</div>
                                <small class="text-muted">Destination Account</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transfer Details -->
            <div class="card-modern">
                <div class="card-header">
                    <h5 class="mb-0"><i class="mdi mdi-information-outline mr-2"></i>Transfer Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="label">Reference Number</div>
                                <div class="value">{{ $transfer->reference ?? 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="label">Transfer Date</div>
                                <div class="value">{{ $transfer->transfer_date->format('M d, Y') }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="label">Expected Clearance</div>
                                <div class="value">
                                    @if($transfer->expected_clearance_date)
                                        {{ $transfer->expected_clearance_date->format('M d, Y') }}
                                    @else
                                        N/A
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="label">Actual Clearance</div>
                                <div class="value">
                                    @if($transfer->actual_clearance_date)
                                        {{ $transfer->actual_clearance_date->format('M d, Y') }}
                                    @else
                                        <span class="text-muted">Pending</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="detail-card">
                                <div class="label">Description</div>
                                <div class="value">{{ $transfer->description }}</div>
                            </div>
                        </div>
                        @if($transfer->notes)
                        <div class="col-12">
                            <div class="detail-card">
                                <div class="label">Notes</div>
                                <div class="value">{{ $transfer->notes }}</div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Fee Information -->
            @if($transfer->transfer_fee > 0)
            <div class="card-modern">
                <div class="card-header">
                    <h5 class="mb-0"><i class="mdi mdi-cash mr-2"></i>Fee Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="detail-card">
                                <div class="label">Transfer Fee</div>
                                <div class="value text-danger">₦{{ number_format($transfer->transfer_fee, 2) }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="detail-card">
                                <div class="label">Fee Account</div>
                                <div class="value">{{ $transfer->feeAccount?->account_name ?? 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="detail-card">
                                <div class="label">Total Deduction</div>
                                <div class="value text-primary">₦{{ number_format($transfer->amount + $transfer->transfer_fee, 2) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Journal Entry -->
            @if($transfer->journalEntry)
            <div class="card-modern">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="mdi mdi-book-open-page-variant mr-2"></i>Journal Entry</h5>
                    <a href="{{ route('accounting.journal-entries.show', $transfer->journalEntry) }}" class="btn btn-sm btn-outline-primary">
                        View Full Entry
                    </a>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Entry #:</strong> {{ $transfer->journalEntry->entry_number }}
                        <span class="badge badge-{{ $transfer->journalEntry->status === 'posted' ? 'success' : 'warning' }} ml-2">
                            {{ ucfirst($transfer->journalEntry->status) }}
                        </span>
                    </div>
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Account</th>
                                <th>Description</th>
                                <th class="text-right">Debit</th>
                                <th class="text-right">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($transfer->journalEntry->lines as $line)
                            <tr>
                                <td>{{ $line->account?->account_number }} - {{ $line->account?->account_name }}</td>
                                <td>{{ $line->description }}</td>
                                <td class="text-right">{{ $line->debit > 0 ? '₦'.number_format($line->debit, 2) : '-' }}</td>
                                <td class="text-right">{{ $line->credit > 0 ? '₦'.number_format($line->credit, 2) : '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="font-weight-bold">
                            <tr>
                                <td colspan="2">Totals</td>
                                <td class="text-right">₦{{ number_format($transfer->journalEntry->lines->sum('debit'), 2) }}</td>
                                <td class="text-right">₦{{ number_format($transfer->journalEntry->lines->sum('credit'), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            @endif
        </div>

        <!-- Right Column - Status & Actions -->
        <div class="col-lg-4">
            <!-- Actions Card -->
            <div class="card-modern">
                <div class="card-header">
                    <h5 class="mb-0"><i class="mdi mdi-cog mr-2"></i>Actions</h5>
                </div>
                <div class="card-body">
                    <!-- Export Buttons -->
                    <a href="{{ route('accounting.transfers.show.export.pdf', $transfer) }}" class="btn btn-outline-primary btn-block mb-2" target="_blank">
                        <i class="mdi mdi-file-pdf-box mr-1"></i> Download PDF Voucher
                    </a>
                    <a href="{{ route('accounting.transfers.show.export.excel', $transfer) }}" class="btn btn-outline-success btn-block mb-2">
                        <i class="mdi mdi-file-excel mr-1"></i> Download Excel
                    </a>

                    <hr>

                    @if($transfer->status === 'pending_approval')
                        <button class="btn btn-success btn-block mb-2" id="approve-btn">
                            <i class="mdi mdi-check mr-1"></i> Approve Transfer
                        </button>
                        <button class="btn btn-danger btn-block mb-2" data-toggle="modal" data-target="#rejectModal">
                            <i class="mdi mdi-close mr-1"></i> Reject Transfer
                        </button>
                    @endif

                    @if(in_array($transfer->status, ['approved', 'initiated', 'in_transit']))
                        <button class="btn btn-success btn-block mb-2" data-toggle="modal" data-target="#clearanceModal">
                            <i class="mdi mdi-bank-check mr-1"></i> Confirm Clearance
                        </button>
                        <button class="btn btn-warning btn-block mb-2" data-toggle="modal" data-target="#failModal">
                            <i class="mdi mdi-alert mr-1"></i> Mark as Failed
                        </button>
                    @endif

                    @if(in_array($transfer->status, ['draft', 'pending_approval']))
                        <button class="btn btn-dark btn-block mb-2" id="cancel-btn">
                            <i class="mdi mdi-cancel mr-1"></i> Cancel Transfer
                        </button>
                    @endif

                    <hr>

                    <a href="{{ route('accounting.transfers.index') }}" class="btn btn-outline-secondary btn-block">
                        <i class="mdi mdi-arrow-left mr-1"></i> Back to List
                    </a>
                </div>
            </div>

            <!-- Status Timeline -->
            <div class="card-modern">
                <div class="card-header">
                    <h5 class="mb-0"><i class="mdi mdi-timeline mr-2"></i>Status Timeline</h5>
                </div>
                <div class="card-body">
                    <div class="transfer-timeline">
                        @php
                            $statusOrder = ['pending_approval', 'approved', 'initiated', 'in_transit', 'cleared'];
                            $currentIndex = array_search($transfer->status, $statusOrder);
                            $isFailed = in_array($transfer->status, ['failed', 'cancelled']);
                        @endphp

                        <div class="timeline-item completed">
                            <strong>Created</strong>
                            <div class="text-muted small">{{ $transfer->created_at->format('M d, Y h:i A') }}</div>
                            <div class="small">By {{ $transfer->initiator?->name ?? 'System' }}</div>
                        </div>

                        @if($transfer->approved_at)
                        <div class="timeline-item completed">
                            <strong>Approved</strong>
                            <div class="text-muted small">{{ $transfer->approved_at->format('M d, Y h:i A') }}</div>
                            <div class="small">By {{ $transfer->approver?->name ?? 'System' }}</div>
                        </div>
                        @elseif(!$isFailed && $transfer->status === 'pending_approval')
                        <div class="timeline-item active">
                            <strong>Pending Approval</strong>
                            <div class="text-muted small">Awaiting approval</div>
                        </div>
                        @endif

                        @if($transfer->status === 'cleared')
                        <div class="timeline-item completed">
                            <strong>Cleared</strong>
                            <div class="text-muted small">{{ $transfer->cleared_at?->format('M d, Y h:i A') ?? '' }}</div>
                        </div>
                        @endif

                        @if($transfer->status === 'failed')
                        <div class="timeline-item failed">
                            <strong>Failed</strong>
                            <div class="text-danger small">{{ $transfer->failure_reason ?? 'Transfer failed' }}</div>
                        </div>
                        @endif

                        @if($transfer->status === 'cancelled')
                        <div class="timeline-item failed">
                            <strong>Cancelled</strong>
                            <div class="text-muted small">{{ $transfer->cancelled_at?->format('M d, Y h:i A') ?? '' }}</div>
                            @if($transfer->failure_reason)
                            <div class="text-danger small">{{ $transfer->failure_reason }}</div>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Additional Info -->
            <div class="card-modern">
                <div class="card-header">
                    <h5 class="mb-0"><i class="mdi mdi-information mr-2"></i>Additional Info</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td class="text-muted">Same Bank:</td>
                            <td>{{ $transfer->is_same_bank ? 'Yes' : 'No' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Created:</td>
                            <td>{{ $transfer->created_at->format('M d, Y h:i A') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Last Updated:</td>
                            <td>{{ $transfer->updated_at->format('M d, Y h:i A') }}</td>
                        </tr>
                    </table>
                </div>
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
                <button type="button" class="close"  data-bs-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Rejection Reason <span class="text-danger">*</span></label>
                    <textarea id="rejection_reason" class="form-control" rows="3" required placeholder="Enter reason for rejection..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
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
                <button type="button" class="close"  data-bs-dismiss="modal">&times;</button>
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
                <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirm-clearance">Confirm Clearance</button>
            </div>
        </div>
    </div>
</div>

<!-- Fail Modal -->
<div class="modal fade" id="failModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="mdi mdi-alert text-warning mr-2"></i>Mark as Failed</h5>
                <button type="button" class="close"  data-bs-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Failure Reason <span class="text-danger">*</span></label>
                    <textarea id="failure_reason" class="form-control" rows="3" required placeholder="Enter reason for failure..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirm-fail">Mark as Failed</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    var transferId = {{ $transfer->id }};

    // Approve
    $('#approve-btn').click(function() {
        if (confirm('Are you sure you want to approve this transfer?')) {
            $.ajax({
                url: '/accounting/transfers/' + transferId + '/approve',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(res) {
                    toastr.success(res.message);
                    location.reload();
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'An error occurred');
                }
            });
        }
    });

    // Reject
    $('#confirm-reject').click(function() {
        var reason = $('#rejection_reason').val().trim();
        if (!reason) {
            toastr.error('Please provide a rejection reason');
            return;
        }
        $.ajax({
            url: '/accounting/transfers/' + transferId + '/reject',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}', rejection_reason: reason },
            success: function(res) {
                toastr.success(res.message);
                location.reload();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
            }
        });
    });

    // Confirm Clearance
    $('#confirm-clearance').click(function() {
        $.ajax({
            url: '/accounting/transfers/' + transferId + '/confirm-clearance',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                clearance_date: $('#clearance_date').val(),
                notes: $('#clearance_notes').val()
            },
            success: function(res) {
                toastr.success(res.message);
                location.reload();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
            }
        });
    });

    // Mark as Failed
    $('#confirm-fail').click(function() {
        var reason = $('#failure_reason').val().trim();
        if (!reason) {
            toastr.error('Please provide a failure reason');
            return;
        }
        $.ajax({
            url: '/accounting/transfers/' + transferId + '/mark-failed',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}', failure_reason: reason },
            success: function(res) {
                toastr.success(res.message);
                location.reload();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
            }
        });
    });

    // Cancel
    $('#cancel-btn').click(function() {
        if (confirm('Are you sure you want to cancel this transfer?')) {
            $.ajax({
                url: '/accounting/transfers/' + transferId + '/cancel',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(res) {
                    toastr.success(res.message);
                    location.reload();
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'An error occurred');
                }
            });
        }
    });
});
</script>
@endpush
