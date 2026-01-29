@extends('admin.layouts.app')

@section('title', 'Credit Note - ' . $creditNote->credit_note_number)
@section('page_name', 'Accounting')
@section('subpage_name', 'View Credit Note')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Credit Note: {{ $creditNote->credit_note_number }}</h4>
            <p class="text-muted mb-0">View credit note details and status</p>
        </div>
        <div>
            @if($creditNote->status == 'draft')
                <a href="{{ route('accounting.credit-notes.edit', $creditNote->id) }}" class="btn btn-outline-primary mr-1">
                    <i class="mdi mdi-pencil mr-1"></i> Edit
                </a>
                <form action="{{ route('accounting.credit-notes.submit', $creditNote->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary mr-1">
                        <i class="mdi mdi-send mr-1"></i> Submit for Approval
                    </button>
                </form>
            @endif
            @if($creditNote->status == 'submitted')
                <form action="{{ route('accounting.credit-notes.approve', $creditNote->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success mr-1">
                        <i class="mdi mdi-check mr-1"></i> Approve
                    </button>
                </form>
                <button type="button" class="btn btn-danger mr-1" data-toggle="modal" data-target="#rejectModal">
                    <i class="mdi mdi-close mr-1"></i> Reject
                </button>
            @endif
            @if($creditNote->status == 'approved')
                <button type="button" class="btn btn-primary mr-1" data-toggle="modal" data-target="#applyRefundModal">
                    <i class="mdi mdi-cash-refund mr-1"></i> Apply Refund
                </button>
            @endif
            <a href="{{ route('accounting.credit-notes.print', $creditNote->id) }}" class="btn btn-outline-secondary mr-1" target="_blank">
                <i class="mdi mdi-printer mr-1"></i> Print
            </a>
            <a href="{{ route('accounting.credit-notes.index') }}" class="btn btn-secondary">
                <i class="mdi mdi-arrow-left mr-1"></i> Back
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <!-- Main Details -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Credit Note Details</h6>
                    @switch($creditNote->status)
                        @case('draft')
                            <span class="badge bg-secondary fs-6">Draft</span>
                            @break
                        @case('submitted')
                            <span class="badge bg-warning fs-6">Pending Approval</span>
                            @break
                        @case('approved')
                            <span class="badge bg-success fs-6">Approved</span>
                            @break
                        @case('applied')
                            <span class="badge bg-info fs-6">Applied</span>
                            @break
                        @case('rejected')
                            <span class="badge bg-danger fs-6">Rejected</span>
                            @break
                    @endswitch
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5 class="text-primary">Patient Information</h5>
                            @if($creditNote->patient)
                                <p class="mb-1"><strong>{{ $creditNote->patient->fullname }}</strong></p>
                                <p class="mb-1 text-muted">MRN: {{ $creditNote->patient->mrn }}</p>
                                @if($creditNote->patient->phone)
                                    <p class="mb-0 text-muted">Phone: {{ $creditNote->patient->phone }}</p>
                                @endif
                            @else
                                <p class="text-muted">No patient linked</p>
                            @endif
                        </div>
                        <div class="col-md-6 text-md-end">
                            <h5 class="text-primary">Credit Note Info</h5>
                            <p class="mb-1"><strong>Number:</strong> {{ $creditNote->credit_note_number }}</p>
                            <p class="mb-1"><strong>Date:</strong> {{ $creditNote->date->format('F d, Y') }}</p>
                            @if($creditNote->invoice)
                                <p class="mb-0"><strong>Original Invoice:</strong> {{ $creditNote->invoice->invoice_number }}</p>
                            @endif
                        </div>
                    </div>

                    <hr>

                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="text-primary">Reason for Credit</h5>
                            <p class="mb-1">
                                <span class="badge bg-info">{{ ucwords(str_replace('_', ' ', $creditNote->reason_type)) }}</span>
                            </p>
                            <p class="mb-0">{{ $creditNote->reason }}</p>
                        </div>
                    </div>

                    @if($creditNote->supporting_documents)
                        <div class="row mb-4">
                            <div class="col-12">
                                <h5 class="text-primary">Supporting Documents/References</h5>
                                <p class="mb-0">{{ $creditNote->supporting_documents }}</p>
                            </div>
                        </div>
                    @endif

                    <hr>

                    <div class="row">
                        <div class="col-md-6 offset-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th class="text-end">Credit Amount:</th>
                                    <td class="text-end fs-4 fw-bold text-danger">
                                        ₦ {{ number_format($creditNote->amount, 2) }}
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    @if($creditNote->status == 'rejected' && $creditNote->rejection_reason)
                        <div class="alert alert-danger mt-4">
                            <h6 class="alert-heading"><i class="fas fa-times-circle me-2"></i>Rejection Reason</h6>
                            <p class="mb-0">{{ $creditNote->rejection_reason }}</p>
                            <hr>
                            <small>Rejected by: {{ $creditNote->rejectedBy->name ?? 'Unknown' }} on {{ $creditNote->rejected_at?->format('M d, Y H:i') }}</small>
                        </div>
                    @endif

                    @if($creditNote->status == 'applied')
                        <div class="alert alert-success mt-4">
                            <h6 class="alert-heading"><i class="fas fa-check-circle me-2"></i>Refund Applied</h6>
                            <p class="mb-1"><strong>Method:</strong> {{ ucwords(str_replace('_', ' ', $creditNote->refund_method)) }}</p>
                            @if($creditNote->refund_reference)
                                <p class="mb-1"><strong>Reference:</strong> {{ $creditNote->refund_reference }}</p>
                            @endif
                            <hr>
                            <small>Applied by: {{ $creditNote->appliedBy->name ?? 'Unknown' }} on {{ $creditNote->applied_at?->format('M d, Y H:i') }}</small>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Journal Entry -->
            @if($creditNote->journalEntry)
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Related Journal Entry</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Account</th>
                                        <th class="text-end">Debit</th>
                                        <th class="text-end">Credit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($creditNote->journalEntry->lines as $line)
                                        <tr>
                                            <td>
                                                {{ $line->account->code }} - {{ $line->account->name }}
                                            </td>
                                            <td class="text-end">
                                                {{ $line->debit > 0 ? number_format($line->debit, 2) : '-' }}
                                            </td>
                                            <td class="text-end">
                                                {{ $line->credit > 0 ? number_format($line->credit, 2) : '-' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <a href="{{ route('accounting.journal-entries.show', $creditNote->journalEntry->id) }}" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-external-link-alt me-1"></i> View Full Entry
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Workflow Timeline -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Workflow History</h6>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <!-- Created -->
                        <div class="timeline-item">
                            <div class="timeline-marker bg-secondary"></div>
                            <div class="timeline-content">
                                <h6 class="mb-0">Created</h6>
                                <small class="text-muted">
                                    {{ $creditNote->created_at->format('M d, Y H:i') }}<br>
                                    by {{ $creditNote->createdBy->name ?? 'System' }}
                                </small>
                            </div>
                        </div>

                        @if($creditNote->submitted_at)
                            <div class="timeline-item">
                                <div class="timeline-marker bg-warning"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-0">Submitted</h6>
                                    <small class="text-muted">
                                        {{ $creditNote->submitted_at->format('M d, Y H:i') }}<br>
                                        by {{ $creditNote->submittedBy->name ?? 'Unknown' }}
                                    </small>
                                </div>
                            </div>
                        @endif

                        @if($creditNote->approved_at)
                            <div class="timeline-item">
                                <div class="timeline-marker bg-success"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-0">Approved</h6>
                                    <small class="text-muted">
                                        {{ $creditNote->approved_at->format('M d, Y H:i') }}<br>
                                        by {{ $creditNote->approvedBy->name ?? 'Unknown' }}
                                    </small>
                                </div>
                            </div>
                        @endif

                        @if($creditNote->rejected_at)
                            <div class="timeline-item">
                                <div class="timeline-marker bg-danger"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-0">Rejected</h6>
                                    <small class="text-muted">
                                        {{ $creditNote->rejected_at->format('M d, Y H:i') }}<br>
                                        by {{ $creditNote->rejectedBy->name ?? 'Unknown' }}
                                    </small>
                                </div>
                            </div>
                        @endif

                        @if($creditNote->applied_at)
                            <div class="timeline-item">
                                <div class="timeline-marker bg-info"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-0">Refund Applied</h6>
                                    <small class="text-muted">
                                        {{ $creditNote->applied_at->format('M d, Y H:i') }}<br>
                                        by {{ $creditNote->appliedBy->name ?? 'Unknown' }}
                                    </small>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Quick Info -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Info</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th>Status:</th>
                            <td>{{ ucfirst($creditNote->status) }}</td>
                        </tr>
                        <tr>
                            <th>Reason Type:</th>
                            <td>{{ ucwords(str_replace('_', ' ', $creditNote->reason_type)) }}</td>
                        </tr>
                        <tr>
                            <th>Amount:</th>
                            <td class="fw-bold text-danger">₦ {{ number_format($creditNote->amount, 2) }}</td>
                        </tr>
                        @if($creditNote->refund_method)
                            <tr>
                                <th>Refund Method:</th>
                                <td>{{ ucwords(str_replace('_', ' ', $creditNote->refund_method)) }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
@if($creditNote->status == 'submitted')
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('accounting.credit-notes.reject', $creditNote->id) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Reject Credit Note</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                            <textarea name="rejection_reason" class="form-control" rows="3" required
                                      placeholder="Explain why this credit note is being rejected..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject Credit Note</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

<!-- Apply Refund Modal -->
@if($creditNote->status == 'approved')
    <div class="modal fade" id="applyRefundModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('accounting.credit-notes.apply', $creditNote->id) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Apply Refund</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <strong>Credit Amount:</strong> ₦ {{ number_format($creditNote->amount, 2) }}
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Refund Method <span class="text-danger">*</span></label>
                            <select name="refund_method" class="form-control" required>
                                <option value="">Select Method</option>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="credit_to_account">Credit to Patient Account</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Reference Number</label>
                            <input type="text" name="refund_reference" class="form-control"
                                   placeholder="Transaction reference or receipt number...">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"
                                      placeholder="Additional notes about the refund..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Apply Refund</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
@endsection

@push('styles')
<style>
.timeline {
    position: relative;
    padding-left: 30px;
}
.timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}
.timeline-item {
    position: relative;
    padding-bottom: 20px;
}
.timeline-marker {
    position: absolute;
    left: -25px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
}
.timeline-content {
    padding-left: 5px;
}
</style>
@endpush
