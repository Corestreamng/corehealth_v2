{{--
    Capex Request Details
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.7
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'Capex: ' . $capex->reference_number)
@section('page_name', 'Accounting')
@section('subpage_name', 'Capex Details')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Capital Expenditure', 'url' => route('accounting.capex.index'), 'icon' => 'mdi-factory'],
        ['label' => $capex->reference_number, 'url' => '#', 'icon' => 'mdi-eye']
    ]
])

<style>
.capex-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 20px;
}
.capex-header h3 { margin: 0; font-weight: 600; }
.info-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
    margin-bottom: 20px;
}
.info-card h6 {
    font-weight: 600;
    color: #333;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f1f1f1;
}
.stat-box {
    text-align: center;
    padding: 15px;
    border-radius: 8px;
}
.stat-box .amount { font-size: 1.5rem; font-weight: 700; }
.stat-box .label { color: #666; font-size: 0.8rem; }
.status-badge {
    font-size: 0.85rem;
    padding: 5px 15px;
    border-radius: 20px;
}
.priority-badge {
    font-size: 0.75rem;
    padding: 3px 8px;
    border-radius: 4px;
}
.progress-timeline {
    display: flex;
    justify-content: space-between;
    position: relative;
    margin: 30px 0;
}
.progress-timeline::before {
    content: '';
    position: absolute;
    top: 15px;
    left: 0;
    right: 0;
    height: 4px;
    background: #e9ecef;
}
.progress-step {
    position: relative;
    text-align: center;
    flex: 1;
}
.progress-step .circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #e9ecef;
    color: #666;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px;
    position: relative;
    z-index: 1;
}
.progress-step.completed .circle { background: #28a745; color: white; }
.progress-step.active .circle { background: #667eea; color: white; animation: pulse 1.5s infinite; }
.progress-step .label { font-size: 0.8rem; color: #666; }
.progress-step.completed .label { color: #28a745; }
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}
.item-row {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 12px 15px;
    margin-bottom: 10px;
}
.expense-row {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 12px 15px;
    margin-bottom: 10px;
    border-left: 3px solid #17a2b8;
}
.history-item {
    display: flex;
    margin-bottom: 15px;
}
.history-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 15px;
    margin-top: 4px;
    flex-shrink: 0;
}
.history-dot.submitted { background: #ffc107; }
.history-dot.approved { background: #28a745; }
.history-dot.rejected { background: #dc3545; }
.history-dot.started { background: #17a2b8; }
.history-dot.completed { background: #667eea; }
</style>

<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <!-- Header -->
    <div class="capex-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <small class="opacity-75">{{ $capex->reference_number }}</small>
                <h3>{{ $capex->title }}</h3>
                <div class="mt-2">
                    <span class="badge badge-light mr-1">{{ ucfirst($capex->category) }}</span>
                    <span class="badge badge-{{ $capex->status == 'completed' ? 'success' : ($capex->status == 'approved' ? 'primary' : ($capex->status == 'pending' ? 'warning' : ($capex->status == 'rejected' ? 'danger' : 'secondary'))) }} status-badge">
                        {{ ucfirst(str_replace('_', ' ', $capex->status)) }}
                    </span>
                    @php
                        $priorityColors = ['low' => 'success', 'medium' => 'info', 'high' => 'warning', 'critical' => 'danger'];
                    @endphp
                    <span class="badge badge-{{ $priorityColors[$capex->priority] ?? 'secondary' }} priority-badge ml-1">
                        {{ strtoupper($capex->priority) }}
                    </span>
                </div>
            </div>
            <div class="col-md-4 text-md-right mt-3 mt-md-0">
                @if($capex->status == 'draft')
                    <a href="{{ route('accounting.capex.edit', $capex->id) }}" class="btn btn-light btn-sm mr-1">
                        <i class="mdi mdi-pencil"></i> Edit
                    </a>
                    <button class="btn btn-success btn-sm" id="submitBtn">
                        <i class="mdi mdi-send"></i> Submit
                    </button>
                @elseif($capex->status == 'pending')
                    <button class="btn btn-success btn-sm mr-1" id="approveBtn">
                        <i class="mdi mdi-check"></i> Approve
                    </button>
                    <button class="btn btn-danger btn-sm" id="rejectBtn">
                        <i class="mdi mdi-close"></i> Reject
                    </button>
                @elseif($capex->status == 'approved')
                    <button class="btn btn-info btn-sm" id="startBtn">
                        <i class="mdi mdi-play"></i> Start Execution
                    </button>
                @elseif($capex->status == 'in_progress')
                    <button class="btn btn-primary btn-sm mr-1" data-toggle="modal" data-target="#expenseModal">
                        <i class="mdi mdi-cash-plus"></i> Record Expense
                    </button>
                    <button class="btn btn-success btn-sm" id="completeBtn">
                        <i class="mdi mdi-check-all"></i> Complete
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Progress Timeline -->
    @php
        $steps = ['draft', 'pending', 'approved', 'in_progress', 'completed'];
        $currentIndex = array_search($capex->status, $steps);
        if ($capex->status == 'rejected') $currentIndex = 1;
    @endphp
    <div class="info-card">
        <p class="text-muted small mb-3">
            <i class="mdi mdi-information-outline mr-1"></i>
            This timeline shows the current status and approval workflow for this capital expenditure request. Each stage must be completed before moving to the next.
        </p>
        <div class="progress-timeline">
            @foreach(['Draft', 'Pending Approval', 'Approved', 'In Progress', 'Completed'] as $index => $step)
                <div class="progress-step {{ $index < $currentIndex ? 'completed' : ($index == $currentIndex ? 'active' : '') }}">
                    <div class="circle">
                        @if($index < $currentIndex)
                            <i class="mdi mdi-check"></i>
                        @elseif($index == $currentIndex && $capex->status == 'rejected')
                            <i class="mdi mdi-close"></i>
                        @else
                            {{ $index + 1 }}
                        @endif
                    </div>
                    <div class="label">{{ $step }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Amount Summary -->
    <div class="row">
        <div class="col-12 mb-3">
            <p class="text-muted small">
                <i class="mdi mdi-information-outline mr-1"></i>
                <strong>Requested:</strong> Initial amount requested | <strong>Approved:</strong> Amount authorized for spending | <strong>Spent:</strong> Actual expenditure to date | <strong>Remaining:</strong> Approved budget still available
            </p>
        </div>
    </div>
    <div class="row">
        <div class="col-md-3 mb-3">
            <div class="stat-box" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                <div class="amount text-primary">₦{{ number_format($capex->requested_amount, 2) }}</div>
                <div class="label">Requested Amount</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-box" style="background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);">
                <div class="amount text-success">₦{{ number_format($capex->approved_amount ?? 0, 2) }}</div>
                <div class="label">Approved Amount</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-box" style="background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);">
                <div class="amount text-info">₦{{ number_format($expenses->sum('amount'), 2) }}</div>
                <div class="label">Spent to Date</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            @php
                $remaining = ($capex->approved_amount ?? 0) - $expenses->sum('amount');
            @endphp
            <div class="stat-box" style="background: linear-gradient(135deg, {{ $remaining >= 0 ? '#d4edda' : '#f8d7da' }} 0%, {{ $remaining >= 0 ? '#c3e6cb' : '#f5c6cb' }} 100%);">
                <div class="amount {{ $remaining >= 0 ? 'text-success' : 'text-danger' }}">₦{{ number_format($remaining, 2) }}</div>
                <div class="label">Remaining Budget</div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Details -->
            <div class="info-card">
                <h6><i class="mdi mdi-information-outline mr-2"></i>Request Details</h6>
                <p class="text-muted small mb-3">
                    <i class="mdi mdi-information-outline mr-1"></i>
                    Complete information about this capital expenditure request including category, budget allocation, requesting department, and project timeline.
                </p>
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-2"><strong>Category:</strong> {{ ucfirst($capex->category) }}</p>
                        <p class="mb-2"><strong>Fiscal Year:</strong> {{ $capex->fiscal_year }}</p>
                        <p class="mb-2"><strong>Cost Center:</strong> {{ $capex->cost_center_name ?? 'N/A' }}</p>
                        <p class="mb-2"><strong>Vendor:</strong> {{ $capex->vendor_name ?? 'Not specified' }}</p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-2"><strong>Requested By:</strong> {{ $capex->requestor_name ?? 'N/A' }}</p>
                        <p class="mb-2"><strong>Approved By:</strong> {{ $capex->approver_name ?? 'Pending' }}</p>
                        <p class="mb-2"><strong>Expected Start:</strong> {{ $capex->expected_start_date ? \Carbon\Carbon::parse($capex->expected_start_date)->format('M d, Y') : 'N/A' }}</p>
                        <p class="mb-2"><strong>Expected Completion:</strong> {{ $capex->expected_completion_date ? \Carbon\Carbon::parse($capex->expected_completion_date)->format('M d, Y') : 'N/A' }}</p>
                    </div>
                </div>
                @if($capex->description)
                    <hr>
                    <p class="mb-1"><strong>Description:</strong></p>
                    <p class="text-muted">{{ $capex->description }}</p>
                @endif
                @if($capex->justification)
                    <hr>
                    <p class="mb-1"><strong>Business Justification:</strong></p>
                    <p class="text-muted">{{ $capex->justification }}</p>
                @endif
            </div>

            <!-- Line Items -->
            <div class="info-card">
                <h6><i class="mdi mdi-format-list-bulleted mr-2"></i>Line Items ({{ $items->count() }})</h6>
                <p class="text-muted small mb-3">
                    <i class="mdi mdi-information-outline mr-1"></i>
                    Detailed breakdown of items included in this capital expenditure, showing quantity, unit cost, and total amount for each item.
                </p>
                @forelse($items as $item)
                    <div class="item-row">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>{{ $item->description }}</strong>
                                @if($item->notes)
                                    <small class="text-muted d-block">{{ $item->notes }}</small>
                                @endif
                            </div>
                            <div class="text-right">
                                <span class="text-muted">{{ $item->quantity }} × ₦{{ number_format($item->unit_cost, 2) }}</span>
                                <strong class="d-block">₦{{ number_format($item->amount, 2) }}</strong>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-3">
                        <small>No line items</small>
                    </div>
                @endforelse
                <div class="d-flex justify-content-between mt-3 pt-3 border-top">
                    <strong>Total:</strong>
                    <strong>₦{{ number_format($items->sum('amount'), 2) }}</strong>
                </div>
            </div>

            <!-- Expenses -->
            @if($expenses->count() > 0 || in_array($capex->status, ['approved', 'in_progress', 'completed']))
                <div class="info-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0"><i class="mdi mdi-cash-multiple mr-2"></i>Expenses Recorded ({{ $expenses->count() }})</h6>
                        @if($capex->status == 'in_progress')
                            <button class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#expenseModal">
                                <i class="mdi mdi-plus mr-1"></i> Add Expense
                            </button>
                        @endif
                    </div>
                    <p class="text-muted small mb-3">
                        <i class="mdi mdi-information-outline mr-1"></i>
                        Track actual expenditures against this capital project. Record each payment or expense as it occurs to monitor spending vs approved budget. Each expense entry includes date, amount, and payment reference.
                    </p>
                    @forelse($expenses as $expense)
                        <div class="expense-row">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>{{ $expense->description }}</strong>
                                    <small class="text-muted d-block">
                                        {{ \Carbon\Carbon::parse($expense->expense_date)->format('M d, Y') }}
                                        @if($expense->payment_reference)
                                            | Ref: {{ $expense->payment_reference }}
                                        @endif
                                    </small>
                                    <small class="d-block">
                                        <span class="badge badge-{{ $expense->payment_method == 'cash' ? 'warning' : 'info' }} badge-sm">
                                            {{ ucfirst(str_replace('_', ' ', $expense->payment_method ?? 'N/A')) }}
                                        </span>
                                        @if($expense->bank_id && $expense->payment_method != 'cash')
                                            @php $bank = $banks->firstWhere('id', $expense->bank_id); @endphp
                                            @if($bank)
                                                <span class="text-muted">{{ $bank->name }}</span>
                                            @endif
                                        @endif
                                        @if($expense->cheque_number)
                                            <span class="text-muted">| Cheque: {{ $expense->cheque_number }}</span>
                                        @endif
                                    </small>
                                </div>
                                <div class="text-right">
                                    <strong class="text-info">₦{{ number_format($expense->amount, 2) }}</strong>
                                    <small class="text-muted d-block">{{ $expense->vendor ?? 'N/A' }}</small>
                                    @if($expense->journal_entry_id)
                                        <a href="{{ route('accounting.journal-entries.show', $expense->journal_entry_id) }}"
                                           class="badge badge-success badge-sm" title="View Journal Entry">
                                            <i class="mdi mdi-book-open-variant"></i> JE #{{ $expense->journal_entry_id }}
                                        </a>
                                    @else
                                        <span class="badge badge-warning badge-sm" title="No journal entry">
                                            <i class="mdi mdi-alert-circle-outline"></i> No JE
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-3">
                            <small>No expenses recorded yet</small>
                        </div>
                    @endforelse
                    @if($expenses->count() > 0)
                        <div class="d-flex justify-content-between mt-3 pt-3 border-top">
                            <strong>Total Spent:</strong>
                            <strong class="text-info">₦{{ number_format($expenses->sum('amount'), 2) }}</strong>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Journal Entries Section -->
            @php
                $journalEntries = collect($expenses)->filter(fn($e) => $e->journal_entry_id)->pluck('journal_entry_id')->unique();
            @endphp
            @if($journalEntries->count() > 0)
                <div class="info-card">
                    <h6><i class="mdi mdi-book-open-variant mr-2"></i>Related Journal Entries</h6>
                    <p class="text-muted small mb-3">
                        <i class="mdi mdi-information-outline mr-1"></i>
                        Journal entries automatically created for expenses recorded against this CAPEX project.
                    </p>
                    @foreach($journalEntries as $jeId)
                        @php
                            $je = \DB::table('journal_entries')->where('id', $jeId)->first();
                        @endphp
                        @if($je)
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <div>
                                    <strong>{{ $je->entry_number }}</strong>
                                    <small class="text-muted d-block">{{ \Carbon\Carbon::parse($je->entry_date)->format('M d, Y') }}</small>
                                    <span class="badge badge-{{ $je->status == 'posted' ? 'success' : 'warning' }} badge-sm">
                                        {{ ucfirst($je->status) }}
                                    </span>
                                </div>
                                <a href="{{ route('accounting.journal-entries.show', $je->id) }}" class="btn btn-outline-info btn-sm">
                                    <i class="mdi mdi-eye mr-1"></i>View
                                </a>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif

            <!-- Linked Assets -->
            @if($assets->count() > 0)
                <div class="info-card">
                    <h6><i class="mdi mdi-tag-multiple mr-2"></i>Linked Fixed Assets</h6>
                    @foreach($assets as $asset)
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div>
                                <strong>{{ $asset->name }}</strong>
                                <small class="text-muted d-block">{{ $asset->asset_code }}</small>
                            </div>
                            <a href="{{ route('accounting.fixed-assets.show', $asset->id) }}" class="btn btn-outline-primary btn-sm">
                                View Asset
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Dates -->
            <div class="info-card">
                <h6><i class="mdi mdi-calendar mr-2"></i>Timeline</h6>
                <p class="text-muted small mb-3">
                    <i class="mdi mdi-information-outline mr-1"></i>
                    Important milestone dates tracked throughout the capex lifecycle.
                </p>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Created:</span>
                    <strong>{{ \Carbon\Carbon::parse($capex->created_at)->format('M d, Y') }}</strong>
                </div>
                @if($capex->submitted_at)
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Submitted:</span>
                        <strong>{{ \Carbon\Carbon::parse($capex->submitted_at)->format('M d, Y') }}</strong>
                    </div>
                @endif
                @if($capex->approved_at)
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Approved:</span>
                        <strong>{{ \Carbon\Carbon::parse($capex->approved_at)->format('M d, Y') }}</strong>
                    </div>
                @endif
                @if($capex->actual_start_date)
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Started:</span>
                        <strong>{{ \Carbon\Carbon::parse($capex->actual_start_date)->format('M d, Y') }}</strong>
                    </div>
                @endif
                @if($capex->completion_date)
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Completed:</span>
                        <strong>{{ \Carbon\Carbon::parse($capex->completion_date)->format('M d, Y') }}</strong>
                    </div>
                @endif
            </div>

            <!-- Approval History -->
            <div class="info-card">
                <h6><i class="mdi mdi-history mr-2"></i>Approval History</h6>
                <p class="text-muted small mb-3">
                    <i class="mdi mdi-information-outline mr-1"></i>
                    Complete audit trail of all status changes, approvals, and actions taken on this request.
                </p>
                @forelse($approvalHistory as $history)
                    <div class="history-item">
                        <div class="history-dot {{ $history->action }}"></div>
                        <div>
                            <strong>{{ ucfirst(str_replace('_', ' ', $history->action)) }}</strong>
                            <small class="text-muted d-block">{{ $history->user_name ?? 'System' }}</small>
                            <small class="text-muted">{{ \Carbon\Carbon::parse($history->created_at)->format('M d, Y H:i') }}</small>
                            @if($history->notes)
                                <small class="d-block mt-1">{{ $history->notes }}</small>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-3">
                        <small>No history yet</small>
                    </div>
                @endforelse
            </div>

            <!-- Actions -->
            <div class="info-card">
                <h6><i class="mdi mdi-cog mr-2"></i>Actions</h6>
                <button class="btn btn-outline-info btn-block btn-sm mb-2" onclick="window.print()">
                    <i class="mdi mdi-printer mr-1"></i> Print
                </button>
                <a href="{{ route('accounting.capex.index') }}" class="btn btn-outline-secondary btn-block btn-sm">
                    <i class="mdi mdi-arrow-left mr-1"></i> Back to List
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Record Expense Modal -->
<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="expenseForm">
                <div class="modal-header">
                    <h5 class="modal-title">Record Expense</h5>
                    <button type="button" class="close"  data-bs-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 px-3 mb-3">
                        <i class="mdi mdi-information-outline mr-1"></i>
                        <small>Record each payment or expense against this capital project. This helps track actual spending vs approved budget.</small>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Expense Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="expense_date" value="{{ date('Y-m-d') }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Amount <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₦</span>
                                    </div>
                                    <input type="number" class="form-control" name="amount" step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="description" required>
                        <small class="form-text text-muted">Brief description of what was purchased or paid for</small>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Payment Method <span class="text-danger">*</span></label>
                                <select class="form-control" name="payment_method" id="paymentMethod" required>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="cash">Cash</option>
                                    <option value="card">Card Payment</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group" id="bankSelectGroup">
                                <label>Bank Account <span class="text-danger">*</span></label>
                                <select class="form-control" name="bank_id" id="bankSelect">
                                    <option value="">-- Select Bank --</option>
                                    @foreach($banks as $bank)
                                        <option value="{{ $bank->id }}">{{ $bank->name }} - {{ $bank->account_number }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group" id="chequeNumberGroup" style="display: none;">
                        <label>Cheque Number</label>
                        <input type="text" class="form-control" name="cheque_number" placeholder="Enter cheque number">
                    </div>
                    <div class="form-group">
                        <label>Payment Reference / Invoice Number</label>
                        <input type="text" class="form-control" name="payment_reference" placeholder="e.g., Transfer Ref, Invoice #">
                        <small class="form-text text-muted">Optional: Bank transfer reference or invoice number</small>
                    </div>

                    <!-- Journal Entry Preview -->
                    <div class="card-modern bg-light mt-3" id="jePreviewCard">
                        <div class="card-body py-2 px-3">
                            <h6 class="mb-2"><i class="mdi mdi-book-open-variant mr-1"></i>Journal Entry Preview</h6>
                            <small class="text-muted d-block mb-2">This journal entry will be created automatically:</small>
                            <table class="table table-sm mb-0" style="font-size: 0.85rem;">
                                <thead style="background: #495057; color: white;">
                                    <tr>
                                        <th style="width: 50%;">Account</th>
                                        <th class="text-right" style="width: 25%;">Debit</th>
                                        <th class="text-right" style="width: 25%;">Credit</th>
                                    </tr>
                                </thead>
                                <tbody id="jePreviewBody">
                                    <tr>
                                        <td id="jeDebitAccount">Other Fixed Assets (1460)</td>
                                        <td class="text-right" id="jeDebitAmount">₦0.00</td>
                                        <td class="text-right">-</td>
                                    </tr>
                                    <tr>
                                        <td id="jeCreditAccount">Cash/Bank</td>
                                        <td class="text-right">-</td>
                                        <td class="text-right" id="jeCreditAmount">₦0.00</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="mdi mdi-content-save mr-1"></i> Save Expense
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="approveForm">
                <div class="modal-header">
                    <h5 class="modal-title">Approve Capex Request</h5>
                    <button type="button" class="close"  data-bs-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-success py-2 px-3 mb-3">
                        <i class="mdi mdi-information-outline mr-1"></i>
                        <small>Approve this capital expenditure request. You can adjust the approved amount if needed (e.g., partial approval or increase based on budget availability).</small>
                    </div>
                    <div class="form-group">
                        <label>Approved Amount</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">₦</span>
                            </div>
                            <input type="number" class="form-control" name="approved_amount"
                                   value="{{ $capex->requested_amount }}" step="0.01" min="0" required>
                        </div>
                        <small class="text-muted">Requested: ₦{{ number_format($capex->requested_amount, 2) }}</small>
                        <small class="form-text text-muted d-block">You can approve the full amount or adjust it based on budget availability</small>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                        <small class="form-text text-muted">Optional: Add any comments or conditions for this approval</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="mdi mdi-check mr-1"></i> Approve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Submit for approval
    $('#submitBtn').on('click', function() {
        if (confirm('Submit this request for approval?')) {
            $.ajax({
                url: '{{ route("accounting.capex.submit", $capex->id) }}',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        location.reload();
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function() {
                    toastr.error('Failed to submit request');
                }
            });
        }
    });

    // Show approve modal
    $('#approveBtn').on('click', function() {
        $('#approveModal').modal('show');
    });

    // Approve
    $('#approveForm').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: '{{ route("accounting.capex.approve", $capex->id) }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                approved_amount: $(this).find('[name="approved_amount"]').val(),
                notes: $(this).find('[name="notes"]').val()
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    $('#approveModal').modal('hide');
                    location.reload();
                } else {
                    toastr.error(response.message);
                }
            },
            error: function() {
                toastr.error('Failed to approve');
            }
        });
    });

    // Reject
    $('#rejectBtn').on('click', function() {
        var reason = prompt('Enter rejection reason:');
        if (reason) {
            $.ajax({
                url: '{{ route("accounting.capex.reject", $capex->id) }}',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}', reason: reason },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        location.reload();
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function() {
                    toastr.error('Failed to reject');
                }
            });
        }
    });

    // Start execution
    $('#startBtn').on('click', function() {
        if (confirm('Start execution of this Capex?')) {
            $.ajax({
                url: '{{ url("accounting/capex/{$capex->id}/start") }}',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        location.reload();
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function() {
                    toastr.error('Failed to start');
                }
            });
        }
    });

    // Payment method toggle
    $('#paymentMethod').on('change', function() {
        var method = $(this).val();
        if (method === 'cash') {
            $('#bankSelectGroup').hide();
            $('#bankSelect').prop('required', false);
            $('#chequeNumberGroup').hide();
        } else if (method === 'cheque') {
            $('#bankSelectGroup').show();
            $('#bankSelect').prop('required', true);
            $('#chequeNumberGroup').show();
        } else {
            $('#bankSelectGroup').show();
            $('#bankSelect').prop('required', true);
            $('#chequeNumberGroup').hide();
        }
        updateJePreview();
    });

    // Bank selection change
    $('#bankSelect').on('change', function() {
        updateJePreview();
    });

    // Amount change
    $('input[name="amount"]').on('input', function() {
        updateJePreview();
    });

    // Update JE Preview
    function updateJePreview() {
        var amount = parseFloat($('input[name="amount"]').val()) || 0;
        var method = $('#paymentMethod').val();
        var bankId = $('#bankSelect').val();
        var bankName = $('#bankSelect option:selected').text();

        // Format amount
        var formattedAmount = '₦' + amount.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        // Update amounts
        $('#jeDebitAmount').text(formattedAmount);
        $('#jeCreditAmount').text(formattedAmount);

        // Update debit account based on project type
        // Fixed Assets are in the 14xx range
        var projectType = '{{ strtolower($capex->category ?? $capex->project_type ?? "") }}';
        var debitAccount = 'Other Fixed Assets (1460)'; // Default for unclassified
        if (projectType.includes('equipment') || projectType.includes('machinery') || projectType.includes('medical')) {
            debitAccount = 'Medical Equipment (1400)';
        } else if (projectType.includes('furniture') || projectType.includes('fixture')) {
            debitAccount = 'Furniture & Fixtures (1410)';
        } else if (projectType.includes('technology') || projectType.includes('it') || projectType.includes('software') || projectType.includes('computer')) {
            debitAccount = 'Computer Equipment (1420)';
        } else if (projectType.includes('vehicle') || projectType.includes('transport')) {
            debitAccount = 'Vehicles (1430)';
        } else if (projectType.includes('building') || projectType.includes('renovation') || projectType.includes('improvement') || projectType.includes('construction')) {
            debitAccount = 'Building (1440)';
        } else if (projectType.includes('land') || projectType.includes('property')) {
            debitAccount = 'Land (1450)';
        }
        $('#jeDebitAccount').text(debitAccount);

        // Update credit account based on payment method
        var creditAccount = 'Cash in Hand (1010)';
        if (method === 'bank_transfer' || method === 'cheque' || method === 'card') {
            if (bankId && bankName) {
                creditAccount = bankName.split(' - ')[0] + ' (Bank)';
            } else {
                creditAccount = 'Bank Account (1020)';
            }
        }
        $('#jeCreditAccount').text(creditAccount);
    }

    // Initial preview update
    updateJePreview();

    // Record expense
    $('#expenseForm').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: '{{ route("accounting.capex.record-expense", $capex->id) }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                expense_date: $(this).find('[name="expense_date"]').val(),
                amount: $(this).find('[name="amount"]').val(),
                description: $(this).find('[name="description"]').val(),
                payment_method: $(this).find('[name="payment_method"]').val(),
                bank_id: $(this).find('[name="bank_id"]').val(),
                cheque_number: $(this).find('[name="cheque_number"]').val(),
                payment_reference: $(this).find('[name="payment_reference"]').val()
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    $('#expenseModal').modal('hide');
                    location.reload();
                } else {
                    toastr.error(response.message);
                }
            },
            error: function() {
                toastr.error('Failed to record expense');
            }
        });
    });

    // Complete
    $('#completeBtn').on('click', function() {
        if (confirm('Mark this Capex as completed?')) {
            $.ajax({
                url: '{{ url("accounting/capex/{$capex->id}/complete") }}',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        location.reload();
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function() {
                    toastr.error('Failed to complete');
                }
            });
        }
    });
});
</script>
@endpush
