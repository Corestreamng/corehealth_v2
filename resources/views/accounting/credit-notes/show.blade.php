@extends('admin.layouts.app')

@section('title', 'Credit Note - ' . $creditNote->credit_note_number)
@section('page_name', 'Accounting')
@section('subpage_name', 'View Credit Note')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Credit Notes', 'url' => route('accounting.credit-notes.index'), 'icon' => 'mdi-note-text'],
    ['label' => 'View Credit Note', 'url' => '#', 'icon' => 'mdi-eye']
]])

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
            @if($creditNote->status == 'pending_approval')
                <form action="{{ route('accounting.credit-notes.approve', $creditNote->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success mr-1">
                        <i class="mdi mdi-check mr-1"></i> Approve
                    </button>
                </form>
                <button type="button" class="btn btn-dark mr-1" data-bs-toggle="modal" data-bs-target="#voidModal">
                    <i class="mdi mdi-close mr-1"></i> Void
                </button>
            @endif
            @if($creditNote->status == 'approved')
                <button type="button" class="btn btn-primary mr-1" data-bs-toggle="modal" data-bs-target="#processRefundModal">
                    <i class="mdi mdi-cash-refund mr-1"></i> Process Refund
                </button>
                <button type="button" class="btn btn-dark mr-1" data-bs-toggle="modal" data-bs-target="#voidModal">
                    <i class="mdi mdi-close mr-1"></i> Void
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
            <div modern shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Credit Note Details</h6>
                    @switch($creditNote->status)
                        @case('draft')
                            <span class="badge bg-secondary fs-6">Draft</span>
                            @break
                        @case('pending_approval')
                            <span class="badge bg-warning fs-6">Pending Approval</span>
                            @break
                        @case('approved')
                            <span class="badge bg-success fs-6">Approved</span>
                            @break
                        @case('processed')
                            <span class="badge bg-info fs-6">Processed</span>
                            @break
                        @case('void')
                            <span class="badge bg-dark fs-6">Voided</span>
                            @break
                    @endswitch
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5 class="text-primary">Patient Information</h5>
                            @if($creditNote->patient)
                                <p class="mb-1"><strong>{{ $creditNote->patient->fullname ?? $creditNote->patient->user->name ?? 'N/A' }}</strong></p>
                                <p class="mb-1 text-muted">MRN: {{ $creditNote->patient->mrn ?? 'N/A' }}</p>
                                @if($creditNote->patient->phone ?? $creditNote->patient->user?->phone)
                                    <p class="mb-0 text-muted">Phone: {{ $creditNote->patient->phone ?? $creditNote->patient->user?->phone }}</p>
                                @endif
                            @else
                                <p class="text-muted">No patient linked</p>
                            @endif
                        </div>
                        <div class="col-md-6 text-md-end">
                            <h5 class="text-primary">Credit Note Info</h5>
                            <p class="mb-1"><strong>Number:</strong> {{ $creditNote->credit_note_number }}</p>
                            <p class="mb-1"><strong>Date:</strong> {{ $creditNote->created_at->format('F d, Y') }}</p>
                            @if($creditNote->originalPayment)
                                <p class="mb-0"><strong>Original Payment:</strong> {{ $creditNote->originalPayment->reference ?? 'PAY-' . $creditNote->originalPayment->id }}</p>
                            @endif
                        </div>
                    </div>

                    <hr>

                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="text-primary">Reason for Credit</h5>
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

                    @if($creditNote->status == 'void' && $creditNote->void_reason)
                        <div class="alert alert-dark mt-4">
                            <h6 class="alert-heading"><i class="fas fa-ban me-2"></i>Voided</h6>
                            <p class="mb-0">{{ $creditNote->void_reason }}</p>
                            <hr>
                            <small>Voided by: {{ $creditNote->voidedBy->name ?? 'Unknown' }} on {{ $creditNote->voided_at?->format('M d, Y H:i') }}</small>
                        </div>
                    @endif

                    @if($creditNote->status == 'processed')
                        <div class="alert alert-success mt-4">
                            <h6 class="alert-heading"><i class="fas fa-check-circle me-2"></i>Refund Processed</h6>
                            <p class="mb-1"><strong>Method:</strong> {{ ucwords(str_replace('_', ' ', $creditNote->refund_method)) }}</p>
                            @if($creditNote->bank)
                                <p class="mb-1"><strong>Bank:</strong> {{ $creditNote->bank->name }}</p>
                            @endif
                            <hr>
                            <small>Processed by: {{ $creditNote->processedBy->name ?? 'Unknown' }} on {{ $creditNote->processed_at?->format('M d, Y H:i') }}</small>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Journal Entry -->
            @if($creditNote->journalEntry)
                <div modern shadow mb-4">
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
            <div modern shadow mb-4">
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

                        @if($creditNote->voided_at)
                            <div class="timeline-item">
                                <div class="timeline-marker bg-dark"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-0">Voided</h6>
                                    <small class="text-muted">
                                        {{ $creditNote->voided_at->format('M d, Y H:i') }}<br>
                                        by {{ $creditNote->voidedBy->name ?? 'Unknown' }}
                                    </small>
                                </div>
                            </div>
                        @endif

                        @if($creditNote->processed_at)
                            <div class="timeline-item">
                                <div class="timeline-marker bg-info"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-0">Refund Processed</h6>
                                    <small class="text-muted">
                                        {{ $creditNote->processed_at->format('M d, Y H:i') }}<br>
                                        by {{ $creditNote->processedBy->name ?? 'Unknown' }}
                                    </small>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Quick Info -->
            <div modern shadow">
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
@if($creditNote->canVoid())
    <!-- Void Modal -->
    <div class="modal fade" id="voidModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('accounting.credit-notes.void', $creditNote->id) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Void Credit Note</h5>
                        <button type="button" class="close" data-bs-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="mdi mdi-alert-circle mr-1"></i>
                            This action cannot be undone. The credit note will be permanently voided.
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Void Reason <span class="text-danger">*</span></label>
                            <textarea name="void_reason" class="form-control" rows="3" required
                                      placeholder="Explain why this credit note is being voided..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark">Void Credit Note</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

<!-- Process Refund Modal -->
@if($creditNote->canProcess())
    <div class="modal fade" id="processRefundModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('accounting.credit-notes.process', $creditNote->id) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Process Refund</h5>
                        <button type="button" class="close" data-bs-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <strong>Credit Amount:</strong> ₦ {{ number_format($creditNote->amount, 2) }}
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Refund Method <span class="text-danger">*</span></label>
                            <select name="refund_method" class="form-control" id="refundMethodSelect" required>
                                <option value="">Select Method</option>
                                <option value="cash">Cash</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="account_credit">Credit to Patient Account</option>
                            </select>
                        </div>

                        <div class="mb-3" id="bankSelectDiv" style="display: none;">
                            <label class="form-label">Bank Account <span class="text-danger">*</span></label>
                            <select name="bank_id" class="form-control" id="bankSelect">
                                <option value="">Select Bank Account</option>
                                @if(isset($banks))
                                    @foreach($banks as $bank)
                                        <option value="{{ $bank->id }}">{{ $bank->name }} - {{ $bank->account_number }}</option>
                                    @endforeach
                                @endif
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
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Process Refund</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.getElementById('refundMethodSelect').addEventListener('change', function() {
            var bankDiv = document.getElementById('bankSelectDiv');
            var bankSelect = document.getElementById('bankSelect');
            if (this.value === 'bank') {
                bankDiv.style.display = 'block';
                bankSelect.setAttribute('required', 'required');
            } else {
                bankDiv.style.display = 'none';
                bankSelect.removeAttribute('required');
            }
        });
    </script>
    @endpush
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
