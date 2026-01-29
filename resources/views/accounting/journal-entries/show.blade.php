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
</style>
@endpush

@section('content')
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
                <button type="button" class="btn btn-danger mr-1" data-toggle="modal" data-target="#rejectModal">
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
                <button type="button" class="btn btn-warning mr-1" data-toggle="modal" data-target="#reverseModal">
                    <i class="mdi mdi-undo mr-1"></i> Reverse Entry
                </button>
                <button type="button" class="btn btn-info mr-1" data-toggle="modal" data-target="#editRequestModal">
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
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Edit Requests</h6>
                    </div>
                    <div class="card-body">
                        @foreach($entry->edits as $edit)
                            <div class="border rounded p-3 mb-3 {{ $edit->status === 'approved' ? 'border-success' : ($edit->status === 'rejected' ? 'border-danger' : 'border-warning') }}">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <strong>{{ $edit->requester->name ?? 'Unknown' }}</strong>
                                        <span class="text-muted ml-2">{{ $edit->requested_at ? $edit->requested_at->format('M d, Y H:i') : $edit->created_at->format('M d, Y H:i') }}</span>
                                    </div>
                                    <span class="badge badge-{{ $edit->status === 'pending' ? 'warning' : ($edit->status === 'approved' ? 'success' : 'danger') }}">
                                        {{ ucfirst($edit->status) }}
                                    </span>
                                </div>

                                <div class="mb-2">
                                    <strong class="text-muted">Reason for Edit:</strong>
                                    <p class="mb-0">{{ $edit->edit_reason }}</p>
                                </div>

                                @if($edit->edited_data && isset($edit->edited_data['proposed_changes']))
                                    <div class="mb-2">
                                        <strong class="text-muted">Proposed Changes:</strong>
                                        <p class="mb-0 text-pre-wrap">{{ $edit->edited_data['proposed_changes'] }}</p>
                                    </div>
                                @endif

                                @if($edit->status === 'pending')
                                    <div class="mt-3 pt-2 border-top">
                                        <div class="d-flex gap-2">
                                            <form action="{{ route('accounting.journal-entries.edit-requests.approve', $edit->id) }}" method="POST" class="d-inline mr-2">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Approve this edit request? This will REVERSE the original entry and you will need to create a new correcting entry.')">
                                                    <i class="mdi mdi-check mr-1"></i> Approve & Reverse Entry
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#rejectEditModal{{ $edit->id }}">
                                                <i class="mdi mdi-close mr-1"></i> Reject
                                            </button>
                                        </div>
                                        <small class="text-muted mt-2 d-block">
                                            <i class="mdi mdi-information-outline mr-1"></i>
                                            Approving will reverse the original entry. A new correcting entry must then be created manually.
                                        </small>
                                    </div>
                                @endif

                                @if($edit->status === 'approved' && $edit->approver)
                                    <div class="mt-2 pt-2 border-top">
                                        <small class="text-success">
                                            <i class="mdi mdi-check-circle mr-1"></i>
                                            Approved by {{ $edit->approver->name }} on {{ $edit->approved_at ? $edit->approved_at->format('M d, Y H:i') : 'N/A' }}
                                        </small>
                                    </div>
                                @endif

                                @if($edit->status === 'rejected')
                                    <div class="mt-2 pt-2 border-top">
                                        <small class="text-danger">
                                            <i class="mdi mdi-close-circle mr-1"></i>
                                            Rejected by {{ $edit->rejecter->name ?? 'Unknown' }} on {{ $edit->rejected_at ? $edit->rejected_at->format('M d, Y H:i') : 'N/A' }}
                                        </small>
                                        @if($edit->rejection_reason)
                                            <p class="mb-0 mt-1 text-danger"><strong>Reason:</strong> {{ $edit->rejection_reason }}</p>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <!-- Reject Edit Request Modal -->
                            @if($edit->status === 'pending')
                            <div class="modal fade" id="rejectEditModal{{ $edit->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form action="{{ route('accounting.journal-entries.edit-requests.reject', $edit->id) }}" method="POST">
                                            @csrf
                                            <div class="modal-header">
                                                <h5 class="modal-title">Reject Edit Request</h5>
                                                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                                                    <textarea name="rejection_reason" class="form-control" rows="3" required
                                                              placeholder="Explain why this edit request is being rejected..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Reject Request</button>
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
                <div class="modal-header">
                    <h5 class="modal-title">Reject Entry</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="3" required
                                  placeholder="Explain why this entry is being rejected..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Entry</button>
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
                <div class="modal-header">
                    <h5 class="modal-title">Reverse Entry</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="mdi mdi-alert mr-2"></i>
                        This will create a new reversal entry that negates this posted entry.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason for Reversal <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required
                                  placeholder="Explain why this entry needs to be reversed..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reverse Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Request Modal -->
<div class="modal fade" id="editRequestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('accounting.journal-entries.request-edit', $entry->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Request Edit</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="mdi mdi-information mr-2"></i>
                        Posted entries cannot be edited directly. Submit an edit request for approval.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason for Edit Request <span class="text-danger">*</span></label>
                        <textarea name="edit_reason" class="form-control" rows="2" required
                                  placeholder="Why does this entry need to be edited?"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Proposed Changes <span class="text-danger">*</span></label>
                        <textarea name="proposed_changes" class="form-control" rows="4" required
                                  placeholder="Describe the changes that need to be made..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
