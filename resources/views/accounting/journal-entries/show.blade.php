@extends('admin.layouts.app')

@section('title', 'Journal Entry ' . $entry->entry_number)
@section('page_name', 'Accounting')
@section('subpage_name', 'View Journal Entry')

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
            @if($entry->status === 'pending')
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
                            <div class="border-bottom pb-3 mb-3">
                                <div class="d-flex justify-content-between">
                                    <strong>{{ $edit->requestedBy->name ?? 'Unknown' }}</strong>
                                    <span class="badge bg-{{ $edit->status === 'pending' ? 'warning' : ($edit->status === 'approved' ? 'success' : 'danger') }}">
                                        {{ ucfirst($edit->status) }}
                                    </span>
                                </div>
                                <p class="mb-1 text-muted">{{ $edit->created_at->format('M d, Y H:i') }}</p>
                                <p class="mb-1"><strong>Reason:</strong> {{ $edit->reason }}</p>
                                <p class="mb-0"><strong>Proposed Changes:</strong> {{ $edit->proposed_changes }}</p>
                            </div>
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
                        <textarea name="reason" class="form-control" rows="2" required
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
