@extends('admin.layouts.app')

@section('title', 'Journal Entry ' . $entry->entry_number)
@section('page_name', 'Accounting')
@section('subpage_name', 'View Journal Entry')

@push('styles')
<style>
    .text-pre-wrap {
        white-space: pre-wrap;
        word-wrap: break-word;
    }

    /* Fix modal z-index stacking issues */
    .modal {
        z-index: 1050;
    }
    .modal-backdrop {
        z-index: 1040;
    }

    /* Ensure modal backdrop doesn't interfere with multiple modals */
    body.modal-open {
        overflow: hidden;
    }
</style>
@endpush

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Journal Entries', 'url' => route('accounting.journal-entries.index'), 'icon' => 'mdi-book-open-page-variant'],
    ['label' => 'Entry #' . $entry->entry_number, 'url' => '#', 'icon' => 'mdi-eye']
]])

<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Journal Entry: {{ $entry->entry_number }}</h4>
            <p class="text-muted mb-0">View entry details and workflow status</p>
        </div>
        <div>
            @if($entry->status === 'draft')
                <a href="{{ route('accounting.journal-entries.edit', $entry->id) }}" class="btn btn-warning mr-1">
                    <i class="mdi mdi-pencil mr-1"></i> Edit
                </a>
                <form action="{{ route('accounting.journal-entries.submit', $entry->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary mr-1">
                        <i class="mdi mdi-send mr-1"></i> Submit for Approval
                    </button>
                </form>
            @endif
            @if($entry->status === 'pending_approval')
                <form action="{{ route('accounting.journal-entries.approve', $entry->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success mr-1">
                        <i class="mdi mdi-check mr-1"></i> Approve
                    </button>
                </form>
                <button type="button" class="btn btn-danger mr-1" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    <i class="mdi mdi-close mr-1"></i> Reject
                </button>
            @endif
            @if($entry->status === 'approved')
                <form action="{{ route('accounting.journal-entries.post', $entry->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary mr-1">
                        <i class="mdi mdi-book-open-page-variant mr-1"></i> Post to Ledger
                    </button>
                </form>
            @endif
            @if($entry->status === 'posted')
                <button type="button" class="btn btn-warning mr-1" data-bs-toggle="modal" data-bs-target="#reverseModal">
                    <i class="mdi mdi-undo mr-1"></i> Reverse Entry
                </button>
                <button type="button" class="btn btn-info mr-1" data-bs-toggle="modal" data-bs-target="#editRequestModal">
                    <i class="mdi mdi-pencil mr-1"></i> Request Edit
                </button>
            @endif
            <a href="{{ route('accounting.journal-entries.index') }}" class="btn btn-secondary">
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
        <!-- Entry Details -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Entry Details</h6>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td class="text-muted">Entry Number:</td>
                            <td><code>{{ $entry->entry_number }}</code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Entry Date:</td>
                            <td>{{ $entry->entry_date->format('M d, Y') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Type:</td>
                            <td>
                                <span class="badge bg-{{ $entry->entry_type === 'manual' ? 'info' : 'secondary' }}">
                                    {{ ucfirst($entry->entry_type) }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Status:</td>
                            <td>
                                @php
                                    $statusColors = [
                                        'draft' => 'secondary',
                                        'pending' => 'warning',
                                        'pending_approval' => 'warning',
                                        'approved' => 'info',
                                        'rejected' => 'danger',
                                        'posted' => 'success',
                                        'reversed' => 'dark',
                                    ];
                                @endphp
                                <span class="badge bg-{{ $statusColors[$entry->status] ?? 'secondary' }}">
                                    {{ ucfirst($entry->status) }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Description:</td>
                            <td>{{ $entry->description }}</td>
                        </tr>
                        @if($entry->memo)
                            <tr>
                                <td class="text-muted">Memo:</td>
                                <td>{{ $entry->memo }}</td>
                            </tr>
                        @endif
                        @if($entry->rejection_reason)
                            <tr>
                                <td class="text-muted">Rejection Reason:</td>
                                <td class="text-danger">{{ $entry->rejection_reason }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            </div>

            <!-- Workflow Info -->
            <div class="card shadow mt-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Workflow</h6>
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm">
                        <tr>
                            <td class="text-muted">Created By:</td>
                            <td>{{ $entry->createdBy->name ?? 'System' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Created At:</td>
                            <td>{{ $entry->created_at->format('M d, Y H:i') }}</td>
                        </tr>
                        @if($entry->submitted_at)
                            <tr>
                                <td class="text-muted">Submitted At:</td>
                                <td>{{ $entry->submitted_at->format('M d, Y H:i') }}</td>
                            </tr>
                        @endif
                        @if($entry->approvedBy)
                            <tr>
                                <td class="text-muted">Approved By:</td>
                                <td>{{ $entry->approvedBy->name }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Approved At:</td>
                                <td>{{ $entry->approved_at->format('M d, Y H:i') }}</td>
                            </tr>
                        @endif
                        @if($entry->postedBy)
                            <tr>
                                <td class="text-muted">Posted By:</td>
                                <td>{{ $entry->postedBy->name }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Posted At:</td>
                                <td>{{ $entry->posted_at->format('M d, Y H:i') }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            </div>

            @if($entry->reversalEntry || $entry->originalEntry)
                <div class="card shadow mt-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Related Entries</h6>
                    </div>
                    <div class="card-body">
                        @if($entry->reversalEntry)
                            <p class="mb-2">
                                <strong>Reversed by:</strong>
                                <a href="{{ route('accounting.journal-entries.show', $entry->reversalEntry->id) }}">
                                    {{ $entry->reversalEntry->entry_number }}
                                </a>
                            </p>
                        @endif
                        @if($entry->originalEntry)
                            <p class="mb-0">
                                <strong>Reversal of:</strong>
                                <a href="{{ route('accounting.journal-entries.show', $entry->originalEntry->id) }}">
                                    {{ $entry->originalEntry->entry_number }}
                                </a>
                            </p>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <!-- Entry Lines -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Entry Lines</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Account</th>
                                    <th>Description</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($entry->lines as $line)
                                    <tr>
                                        <td>
                                            <a href="{{ route('accounting.chart-of-accounts.show', $line->account_id) }}">
                                                {{ $line->account->account_code }} - {{ $line->account->name }}
                                            </a>
                                            <br>
                                            <small class="text-muted">
                                                {{ $line->account->accountGroup->accountClass->name ?? '' }}
                                            </small>
                                        </td>
                                        <td>{{ $line->description ?? '-' }}</td>
                                        <td class="text-end">
                                            @if($line->debit_amount > 0)
                                                ₦{{ number_format($line->debit_amount, 2) }}
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if($line->credit_amount > 0)
                                                ₦{{ number_format($line->credit_amount, 2) }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td colspan="2">Total</td>
                                    <td class="text-end">₦{{ number_format($entry->lines->sum('debit_amount'), 2) }}</td>
                                    <td class="text-end">₦{{ number_format($entry->lines->sum('credit_amount'), 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Edit Requests -->
            @if($entry->edits && $entry->edits->count() > 0)
                <div class="card shadow mt-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="mdi mdi-file-document-edit mr-2"></i>Edit Requests
                        </h6>
                        <span class="badge badge-{{ $entry->edits->where('status', 'pending')->count() > 0 ? 'warning' : 'secondary' }}">
                            {{ $entry->edits->where('status', 'pending')->count() }} Pending
                        </span>
                    </div>
                    <div class="card-body">
                        @foreach($entry->edits as $index => $edit)
                            <div class="border rounded mb-3 {{ $edit->status === 'approved' ? 'border-success' : ($edit->status === 'rejected' ? 'border-danger' : 'border-warning') }}" style="overflow: hidden;">
                                <!-- Header -->
                                <div class="px-3 py-2 {{ $edit->status === 'approved' ? 'bg-success' : ($edit->status === 'rejected' ? 'bg-danger' : 'bg-warning') }} text-white d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Edit Request #{{ $entry->edits->count() - $index }}</strong>
                                        <span class="ml-2 opacity-75">by {{ $edit->requester->name ?? 'Unknown' }}</span>
                                    </div>
                                    <span class="badge badge-light">
                                        {{ ucfirst($edit->status) }}
                                    </span>
                                </div>

                                <div class="p-3">
                                    <!-- Timeline/Status indicator for pending -->
                                    @if($edit->status === 'pending')
                                        <div class="alert alert-warning border-0 mb-3" style="background: #fff8e1;">
                                            <div class="d-flex align-items-center">
                                                <i class="mdi mdi-clock-outline mdi-24px mr-3 text-warning"></i>
                                                <div>
                                                    <strong>Awaiting Review</strong>
                                                    <p class="mb-0 small">This request is waiting for an authorized user to approve or reject it.</p>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    <!-- Request Details -->
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="text-muted small mb-1 d-block">SUBMITTED</label>
                                            <span>{{ $edit->requested_at ? $edit->requested_at->format('M d, Y \a\t H:i') : $edit->created_at->format('M d, Y \a\t H:i') }}</span>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="text-muted small mb-1 d-block">REQUESTED BY</label>
                                            <span>{{ $edit->requester->name ?? 'Unknown' }}</span>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="text-muted small mb-1 d-block">REASON FOR EDIT</label>
                                        <p class="mb-0 bg-light p-2 rounded">{{ $edit->edit_reason }}</p>
                                    </div>

                                    @if($edit->edited_data && isset($edit->edited_data['proposed_changes']))
                                        <div class="mb-3">
                                            <label class="text-muted small mb-1 d-block">PROPOSED CHANGES</label>
                                            <div class="bg-light p-2 rounded">
                                                <p class="mb-0 text-pre-wrap">{{ $edit->edited_data['proposed_changes'] }}</p>
                                            </div>
                                        </div>
                                    @endif

                                    <!-- Action Buttons for Pending -->
                                    @if($edit->status === 'pending')
                                        <div class="border-top pt-3 mt-3">
                                            <h6 class="text-muted mb-3"><i class="mdi mdi-gavel mr-1"></i> Review Actions</h6>

                                            <div class="row">
                                                <div class="col-md-6 mb-2">
                                                    <div class="card border-success h-100">
                                                        <div class="card-body p-3">
                                                            <h6 class="text-success"><i class="mdi mdi-check-circle mr-1"></i> Approve Request</h6>
                                                            <p class="small text-muted mb-2">
                                                                Approving will <strong>automatically reverse</strong> the original journal entry.
                                                                The requester can then create a new correcting entry.
                                                            </p>
                                                            <form action="{{ route('accounting.journal-entries.edit-requests.approve', $edit->id) }}" method="POST">
                                                                @csrf
                                                                <button type="submit" class="btn btn-success btn-block"
                                                                    onclick="return confirm('Are you sure you want to approve this edit request?\n\nThis will:\n1. Mark this request as approved\n2. REVERSE the original journal entry #{{ $entry->entry_number }}\n3. Redirect you to create a new correcting entry\n\nThis action cannot be undone.')">
                                                                    <i class="mdi mdi-check mr-1"></i> Approve & Reverse Entry
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <div class="card border-danger h-100">
                                                        <div class="card-body p-3">
                                                            <h6 class="text-danger"><i class="mdi mdi-close-circle mr-1"></i> Reject Request</h6>
                                                            <p class="small text-muted mb-2">
                                                                Rejecting will decline this request. The original entry remains unchanged.
                                                                You must provide a reason.
                                                            </p>
                                                            <button type="button" class="btn btn-outline-danger btn-block" data-bs-toggle="modal" data-bs-target="#rejectEditModal{{ $edit->id }}">
                                                                <i class="mdi mdi-close mr-1"></i> Reject Request
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    <!-- Approved Status -->
                                    @if($edit->status === 'approved' && $edit->approver)
                                        <div class="alert alert-success border-0 mb-0" style="background: #e8f5e9;">
                                            <div class="d-flex align-items-start">
                                                <i class="mdi mdi-check-circle mdi-24px mr-3 text-success"></i>
                                                <div>
                                                    <strong>Approved</strong>
                                                    <p class="mb-1 small">
                                                        Approved by <strong>{{ $edit->approver->name }}</strong> on {{ $edit->approved_at ? $edit->approved_at->format('M d, Y \a\t H:i') : 'N/A' }}
                                                    </p>
                                                    <p class="mb-0 small text-muted">
                                                        The original entry was reversed. A correcting entry should have been created.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    <!-- Rejected Status -->
                                    @if($edit->status === 'rejected')
                                        <div class="alert alert-danger border-0 mb-0" style="background: #ffebee;">
                                            <div class="d-flex align-items-start">
                                                <i class="mdi mdi-close-circle mdi-24px mr-3 text-danger"></i>
                                                <div>
                                                    <strong>Rejected</strong>
                                                    <p class="mb-1 small">
                                                        Rejected by <strong>{{ $edit->rejecter->name ?? 'Unknown' }}</strong> on {{ $edit->rejected_at ? $edit->rejected_at->format('M d, Y \a\t H:i') : 'N/A' }}
                                                    </p>
                                                    @if($edit->rejection_reason)
                                                        <p class="mb-0 small"><strong>Reason:</strong> {{ $edit->rejection_reason }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Reject Edit Request Modal -->
                            @if($edit->status === 'pending')
                            <div class="modal fade" id="rejectEditModal{{ $edit->id }}" tabindex="-1" role="dialog" aria-labelledby="rejectEditModalLabel{{ $edit->id }}" aria-hidden="true">
                                <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                        <form action="{{ route('accounting.journal-entries.edit-requests.reject', $edit->id) }}" method="POST">
                                            @csrf
                                            <div class="modal-header bg-danger text-white">
                                                <h5 class="modal-title" id="rejectEditModalLabel{{ $edit->id }}"><i class="mdi mdi-close-circle mr-2"></i>Reject Edit Request</h5>
                                                <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="alert alert-light border mb-3">
                                                    <strong>You are rejecting:</strong><br>
                                                    <span class="text-muted">{{ Str::limit($edit->edit_reason, 100) }}</span>
                                                </div>

                                                <p class="text-muted small">
                                                    The original journal entry will remain unchanged. The requester will be notified of this rejection.
                                                </p>

                                                <div class="mb-3">
                                                    <label class="form-label font-weight-bold">Why is this request being rejected? <span class="text-danger">*</span></label>
                                                    <textarea name="rejection_reason" class="form-control" rows="3" required
                                                              placeholder="e.g., The entry is correct as posted, insufficient justification provided, please provide more details..."></textarea>
                                                    <small class="text-muted">This reason will be visible to the requester</small>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="mdi mdi-close mr-1"></i> Reject Request
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('accounting.journal-entries.reject', $entry->id) }}" method="POST">
                @csrf
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="mdi mdi-close-circle mr-2"></i>Reject Journal Entry</h5>
                    <button type="button" class="close text-white" data-bs-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border mb-3">
                        <h6 class="mb-2"><i class="mdi mdi-file-document mr-1"></i> Entry Being Rejected</h6>
                        <div class="small">
                            <strong>Entry #:</strong> {{ $entry->entry_number }}<br>
                            <strong>Total:</strong> {{ number_format($entry->total_debit, 2) }}<br>
                            <strong>Description:</strong> {{ Str::limit($entry->description, 80) }}
                        </div>
                    </div>

                    <div class="alert alert-info border-0 small" style="background: #e8f4f8;">
                        <i class="mdi mdi-information mr-1"></i>
                        Rejecting will return this entry to <strong>draft status</strong>. The creator can then edit and resubmit it for approval.
                    </div>

                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="3" required
                                  placeholder="e.g., Incorrect account used, amount doesn't match documentation, needs additional supporting details..."></textarea>
                        <small class="text-muted">This reason will be visible to the entry creator</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="mdi mdi-close mr-1"></i> Reject Entry
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reverse Modal -->
<div class="modal fade" id="reverseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('accounting.journal-entries.reverse', $entry->id) }}" method="POST">
                @csrf
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="mdi mdi-undo-variant mr-2"></i>Reverse Journal Entry</h5>
                    <button type="button" class="close" data-bs-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning border-0" style="background: #fff8e1;">
                        <h6 class="alert-heading"><i class="mdi mdi-alert mr-2"></i>What happens when you reverse an entry?</h6>
                        <p class="mb-2">A <strong>new reversal entry</strong> will be created that:</p>
                        <ul class="mb-0 pl-3">
                            <li>Has all debits and credits swapped (debits become credits, vice versa)</li>
                            <li>Uses today's date as the entry date</li>
                            <li>References this original entry</li>
                            <li>Effectively cancels out this entry's effect on account balances</li>
                        </ul>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header bg-light py-2">
                            <strong>Entry Being Reversed</strong>
                        </div>
                        <div class="card-body py-2 small">
                            <strong>Entry #:</strong> {{ $entry->entry_number }}<br>
                            <strong>Date:</strong> {{ $entry->entry_date->format('M d, Y') }}<br>
                            <strong>Total:</strong> {{ number_format($entry->total_debit, 2) }}<br>
                            <strong>Description:</strong> {{ $entry->description }}
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Reason for Reversal <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required
                                  placeholder="e.g., Entry posted in error, duplicate entry, wrong period..."></textarea>
                        <small class="text-muted">This reason will be recorded in the audit trail</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="mdi mdi-undo-variant mr-1"></i> Create Reversal Entry
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Request Modal -->
<div class="modal fade" id="editRequestModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('accounting.journal-entries.request-edit', $entry->id) }}" method="POST">
                @csrf
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="mdi mdi-file-document-edit mr-2"></i>Request Edit for Posted Entry</h5>
                    <button type="button" class="close text-white" data-bs-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <!-- Step indicator -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between text-center">
                            <div class="flex-fill">
                                <div class="rounded-circle bg-info text-white d-inline-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">1</div>
                                <div class="small mt-1 font-weight-bold text-info">Submit Request</div>
                            </div>
                            <div class="flex-fill">
                                <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">2</div>
                                <div class="small mt-1 text-muted">Await Approval</div>
                            </div>
                            <div class="flex-fill">
                                <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">3</div>
                                <div class="small mt-1 text-muted">Entry Reversed</div>
                            </div>
                            <div class="flex-fill">
                                <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">4</div>
                                <div class="small mt-1 text-muted">Create Correction</div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info border-0" style="background: #e8f4f8;">
                        <h6 class="alert-heading"><i class="mdi mdi-information mr-2"></i>How Edit Requests Work</h6>
                        <p class="mb-2">Posted journal entries cannot be directly edited to maintain audit integrity. Instead:</p>
                        <ol class="mb-0 pl-3">
                            <li>You submit this request describing what needs to change</li>
                            <li>An authorized user reviews and approves/rejects the request</li>
                            <li>If approved, the <strong>original entry is automatically reversed</strong></li>
                            <li>You then create a new correcting entry with the correct values</li>
                        </ol>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header bg-light py-2">
                            <strong>Current Entry Summary</strong>
                        </div>
                        <div class="card-body py-2">
                            <div class="row small">
                                <div class="col-md-6">
                                    <strong>Entry #:</strong> {{ $entry->entry_number }}<br>
                                    <strong>Date:</strong> {{ $entry->entry_date->format('M d, Y') }}
                                </div>
                                <div class="col-md-6">
                                    <strong>Total:</strong> {{ number_format($entry->total_debit, 2) }}<br>
                                    <strong>Description:</strong> {{ Str::limit($entry->description, 50) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Why does this entry need to be edited? <span class="text-danger">*</span></label>
                        <textarea name="edit_reason" class="form-control" rows="2" required
                                  placeholder="e.g., Wrong account was used, incorrect amount, wrong date..."></textarea>
                        <small class="text-muted">Briefly explain the error or reason for the change</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Describe the Proposed Changes <span class="text-danger">*</span></label>
                        <textarea name="proposed_changes" class="form-control" rows="5" required
                                  placeholder="Please describe clearly:
• What should the correct account(s) be?
• What should the correct amount(s) be?
• Any other corrections needed?

Example:
- Change debit from 'Office Supplies' to 'Equipment'
- Amount should be 5,000.00 instead of 500.00"></textarea>
                        <small class="text-muted">Be specific so the reviewer understands exactly what changes are needed</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">
                        <i class="mdi mdi-send mr-1"></i> Submit Edit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
