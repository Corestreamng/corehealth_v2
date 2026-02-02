{{--
    Bank Reconciliation Show/Details View
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 3
    Access: SUPERADMIN|ADMIN|ACCOUNTS|AUDIT
--}}

@extends('admin.layouts.app')

@section('title', $reconciliation->reconciliation_number)
@section('page_name', 'Accounting')
@section('subpage_name', 'Reconciliation Details')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Bank Reconciliation', 'url' => route('accounting.bank-reconciliation.index'), 'icon' => 'mdi-bank-check'],
        ['label' => $reconciliation->reconciliation_number, 'url' => '#', 'icon' => 'mdi-information']
    ]
])

<style>
.status-timeline {
    display: flex;
    justify-content: space-between;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 25px;
}
.timeline-step {
    flex: 1;
    text-align: center;
    position: relative;
}
.timeline-step::after {
    content: '';
    position: absolute;
    top: 15px;
    right: -50%;
    width: 100%;
    height: 2px;
    background: #dee2e6;
    z-index: 0;
}
.timeline-step:last-child::after { display: none; }
.timeline-step.completed::after { background: #28a745; }
.timeline-dot {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #dee2e6;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: white;
    position: relative;
    z-index: 1;
}
.timeline-step.completed .timeline-dot { background: #28a745; }
.timeline-step.current .timeline-dot { background: #007bff; animation: pulse 1.5s infinite; }
.timeline-label { margin-top: 8px; font-size: 0.8rem; color: #666; }
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}
.summary-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
    margin-bottom: 20px;
}
.summary-card h6 {
    font-weight: 600;
    color: #333;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}
.summary-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px dashed #eee;
}
.summary-item:last-child { border-bottom: none; }
.summary-item .label { color: #666; }
.summary-item .value { font-weight: 600; }
.variance-box {
    text-align: center;
    padding: 20px;
    border-radius: 8px;
}
.variance-box.balanced {
    background: #d4edda;
    border: 2px solid #28a745;
}
.variance-box.unbalanced {
    background: #f8d7da;
    border: 2px solid #dc3545;
}
.variance-box .amount {
    font-size: 2rem;
    font-weight: 700;
}
</style>

<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <!-- Status Timeline -->
    <div class="status-timeline">
        @php
            $statuses = ['draft' => 'Draft', 'in_progress' => 'In Progress', 'pending_review' => 'Review', 'approved' => 'Approved', 'finalized' => 'Finalized'];
            $currentIndex = array_search($reconciliation->status, array_keys($statuses));
        @endphp
        @foreach($statuses as $key => $label)
            @php
                $index = array_search($key, array_keys($statuses));
                $stepClass = $index < $currentIndex ? 'completed' : ($index === $currentIndex ? 'current' : '');
            @endphp
            <div class="timeline-step {{ $stepClass }}">
                <div class="timeline-dot">
                    @if($index < $currentIndex)
                        <i class="mdi mdi-check"></i>
                    @else
                        {{ $index + 1 }}
                    @endif
                </div>
                <div class="timeline-label">{{ $label }}</div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <!-- Main Details -->
        <div class="col-lg-8">
            <!-- Basic Info -->
            <div class="summary-card">
                <h6><i class="mdi mdi-information-outline mr-2"></i>Reconciliation Details</h6>
                <div class="row">
                    <div class="col-md-6">
                        <div class="summary-item">
                            <span class="label">Reconciliation #</span>
                            <span class="value">{{ $reconciliation->reconciliation_number }}</span>
                        </div>
                        <div class="summary-item">
                            <span class="label">Bank</span>
                            <span class="value">{{ $reconciliation->bank->bank_name }}</span>
                        </div>
                        <div class="summary-item">
                            <span class="label">Account</span>
                            <span class="value">{{ $reconciliation->account->account_name ?? 'N/A' }}</span>
                        </div>
                        <div class="summary-item">
                            <span class="label">Fiscal Period</span>
                            <span class="value">{{ $reconciliation->fiscalPeriod->name ?? 'N/A' }}</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="summary-item">
                            <span class="label">Statement Date</span>
                            <span class="value">{{ $reconciliation->statement_date->format('M d, Y') }}</span>
                        </div>
                        <div class="summary-item">
                            <span class="label">Period From</span>
                            <span class="value">{{ $reconciliation->statement_period_from->format('M d, Y') }}</span>
                        </div>
                        <div class="summary-item">
                            <span class="label">Period To</span>
                            <span class="value">{{ $reconciliation->statement_period_to->format('M d, Y') }}</span>
                        </div>
                        <div class="summary-item">
                            <span class="label">Status</span>
                            <span class="value">
                                @if($reconciliation->status === 'draft')
                                    <span class="badge badge-secondary">Draft</span>
                                @elseif($reconciliation->status === 'in_progress')
                                    <span class="badge badge-info">In Progress</span>
                                @elseif($reconciliation->status === 'pending_review')
                                    <span class="badge badge-warning">Pending Review</span>
                                @elseif($reconciliation->status === 'approved')
                                    <span class="badge badge-primary">Approved</span>
                                @else
                                    <span class="badge badge-success">Finalized</span>
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Balance Summary -->
            <div class="summary-card">
                <h6><i class="mdi mdi-calculator mr-2"></i>Balance Summary</h6>
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Bank Statement</h6>
                        <div class="summary-item">
                            <span class="label">Opening Balance</span>
                            <span class="value">₦{{ number_format($reconciliation->statement_opening_balance, 2) }}</span>
                        </div>
                        <div class="summary-item">
                            <span class="label">Closing Balance</span>
                            <span class="value text-primary">₦{{ number_format($reconciliation->statement_closing_balance, 2) }}</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">General Ledger</h6>
                        <div class="summary-item">
                            <span class="label">Opening Balance</span>
                            <span class="value">₦{{ number_format($reconciliation->gl_opening_balance, 2) }}</span>
                        </div>
                        <div class="summary-item">
                            <span class="label">Closing Balance</span>
                            <span class="value text-primary">₦{{ number_format($reconciliation->gl_closing_balance, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Outstanding Items -->
            <div class="summary-card">
                <h6><i class="mdi mdi-clock-outline mr-2"></i>Outstanding Items</h6>
                <div class="row">
                    <div class="col-md-6">
                        <div class="summary-item">
                            <span class="label">Outstanding Deposits</span>
                            <span class="value text-success">₦{{ number_format($reconciliation->outstanding_deposits, 2) }}</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="summary-item">
                            <span class="label">Outstanding Checks</span>
                            <span class="value text-danger">₦{{ number_format($reconciliation->outstanding_checks, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reconciliation Items Table -->
            <div class="summary-card">
                <h6><i class="mdi mdi-format-list-bulleted mr-2"></i>Reconciliation Items</h6>
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Source</th>
                                <th>Reference</th>
                                <th>Description</th>
                                <th class="text-right">Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reconciliation->items as $item)
                                <tr>
                                    <td>{{ $item->transaction_date->format('M d, Y') }}</td>
                                    <td>
                                        <span class="badge badge-{{ $item->source === 'gl' ? 'info' : ($item->source === 'statement' ? 'secondary' : 'warning') }}">
                                            {{ strtoupper($item->source) }}
                                        </span>
                                    </td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $item->item_type)) }}</td>
                                    <td>{{ $item->reference }}</td>
                                    <td>{{ Str::limit($item->description, 30) }}</td>
                                    <td class="text-right {{ $item->amount_type === 'debit' ? 'text-danger' : 'text-success' }}">
                                        {{ $item->amount_type === 'debit' ? '-' : '+' }}₦{{ number_format($item->amount, 2) }}
                                    </td>
                                    <td>
                                        @if($item->is_reconciled)
                                            <span class="badge badge-success">Reconciled</span>
                                        @elseif($item->is_matched)
                                            <span class="badge badge-info">Matched</span>
                                        @elseif($item->is_outstanding)
                                            <span class="badge badge-warning">Outstanding</span>
                                        @else
                                            <span class="badge badge-secondary">Pending</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No items found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Variance -->
            <div class="variance-box {{ abs($reconciliation->variance) < 0.01 ? 'balanced' : 'unbalanced' }} mb-4">
                <div class="label">Variance</div>
                <div class="amount {{ $reconciliation->variance >= 0 ? 'text-success' : 'text-danger' }}">
                    ₦{{ number_format($reconciliation->variance, 2) }}
                </div>
                @if(abs($reconciliation->variance) < 0.01)
                    <small class="text-success"><i class="mdi mdi-check-circle mr-1"></i>Balanced</small>
                @else
                    <small class="text-danger"><i class="mdi mdi-alert-circle mr-1"></i>Unbalanced</small>
                @endif
            </div>

            <!-- Matching Stats -->
            <div class="summary-card">
                <h6><i class="mdi mdi-chart-pie mr-2"></i>Matching Statistics</h6>
                @php
                    $totalItems = $reconciliation->items->count();
                    $matchedItems = $reconciliation->items->where('is_matched', true)->count();
                    $outstandingItems = $reconciliation->items->where('is_outstanding', true)->count();
                    $unmatchedItems = $totalItems - $matchedItems - $outstandingItems;
                @endphp
                <div class="summary-item">
                    <span class="label">Total Items</span>
                    <span class="value">{{ $totalItems }}</span>
                </div>
                <div class="summary-item">
                    <span class="label">Matched</span>
                    <span class="value text-success">{{ $matchedItems }}</span>
                </div>
                <div class="summary-item">
                    <span class="label">Outstanding</span>
                    <span class="value text-warning">{{ $outstandingItems }}</span>
                </div>
                <div class="summary-item">
                    <span class="label">Unmatched</span>
                    <span class="value text-danger">{{ $unmatchedItems }}</span>
                </div>
            </div>

            <!-- Workflow Info -->
            <div class="summary-card">
                <h6><i class="mdi mdi-account-clock mr-2"></i>Workflow</h6>
                <div class="summary-item">
                    <span class="label">Prepared By</span>
                    <span class="value">{{ $reconciliation->preparedBy->full_name ?? 'N/A' }}</span>
                </div>
                <div class="summary-item">
                    <span class="label">Prepared At</span>
                    <span class="value">{{ $reconciliation->created_at->format('M d, Y H:i') }}</span>
                </div>
                @if($reconciliation->reviewed_by)
                    <div class="summary-item">
                        <span class="label">Reviewed By</span>
                        <span class="value">{{ $reconciliation->reviewedBy->full_name ?? 'N/A' }}</span>
                    </div>
                @endif
                @if($reconciliation->approved_by)
                    <div class="summary-item">
                        <span class="label">Approved By</span>
                        <span class="value">{{ $reconciliation->approvedBy->full_name ?? 'N/A' }}</span>
                    </div>
                    <div class="summary-item">
                        <span class="label">Approved At</span>
                        <span class="value">{{ $reconciliation->approved_at?->format('M d, Y H:i') ?? 'N/A' }}</span>
                    </div>
                @endif
            </div>

            <!-- Actions -->
            <div class="summary-card">
                <h6><i class="mdi mdi-cog mr-2"></i>Actions</h6>
                <div class="btn-group-vertical w-100">
                    <a href="{{ route('accounting.bank-reconciliation.index') }}" class="btn btn-outline-secondary">
                        <i class="mdi mdi-arrow-left mr-1"></i> Back to List
                    </a>

                    @if(in_array($reconciliation->status, ['draft', 'in_progress']))
                        <a href="{{ route('accounting.bank-reconciliation.edit', $reconciliation) }}" class="btn btn-primary">
                            <i class="mdi mdi-link-variant mr-1"></i> Continue Matching
                        </a>
                    @endif

                    @if($reconciliation->status === 'pending_review' && auth()->user()->hasAnyRole(['SUPERADMIN', 'ADMIN', 'AUDIT']))
                        <form action="{{ route('accounting.bank-reconciliation.approve', $reconciliation) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-success w-100">
                                <i class="mdi mdi-check mr-1"></i> Approve Reconciliation
                            </button>
                        </form>
                    @endif

                    @if($reconciliation->status === 'approved')
                        <form action="{{ route('accounting.bank-reconciliation.finalize', $reconciliation) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-success w-100">
                                <i class="mdi mdi-lock mr-1"></i> Finalize
                            </button>
                        </form>
                    @endif

                    <a href="{{ route('accounting.bank-reconciliation.export', $reconciliation) }}" class="btn btn-outline-info">
                        <i class="mdi mdi-download mr-1"></i> Export PDF
                    </a>
                </div>
            </div>

            <!-- Notes -->
            @if($reconciliation->notes)
                <div class="summary-card">
                    <h6><i class="mdi mdi-note-text mr-2"></i>Notes</h6>
                    <p class="mb-0">{{ $reconciliation->notes }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Any additional JS can go here
});
</script>
@endpush
