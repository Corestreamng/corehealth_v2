@extends('admin.layouts.app')
@section('title', 'Statutory Remittances')
@section('page_name', 'Accounting')
@section('subpage_name', 'Statutory Remittances')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="mdi mdi-bank-transfer text-primary mr-2"></i>Statutory Remittances
            </h4>
            <p class="text-muted mb-0">Manage remittances for PAYE, Pension, NHF, and other statutory deductions</p>
        </div>
        <div class="btn-group">
            <a href="{{ route('accounting.statutory-remittances.balances') }}" class="btn btn-outline-info">
                <i class="mdi mdi-scale-balance"></i> View Balances
            </a>
            <a href="{{ route('accounting.statutory-remittances.create') }}" class="btn btn-primary">
                <i class="mdi mdi-plus"></i> New Remittance
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-4 col-sm-6 mb-3">
            <div class="card-modern border-left border-warning" style="border-left-width: 4px !important;">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-1"><i class="mdi mdi-clock-outline mr-1"></i> Pending Remittance</h6>
                    <h4 class="mb-0 text-warning">₦{{ number_format($stats['total_pending'] ?? 0, 2) }}</h4>
                    <small class="text-muted">Draft, pending, and approved</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6 mb-3">
            <div class="card-modern border-left border-success" style="border-left-width: 4px !important;">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-1"><i class="mdi mdi-check-circle mr-1"></i> Paid This Month</h6>
                    <h4 class="mb-0 text-success">₦{{ number_format($stats['total_paid_this_month'] ?? 0, 2) }}</h4>
                    <small class="text-muted">{{ now()->format('F Y') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6 mb-3">
            <div class="card-modern border-left border-danger" style="border-left-width: 4px !important;">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-1"><i class="mdi mdi-alert-circle mr-1"></i> Overdue</h6>
                    <h4 class="mb-0 text-danger">{{ $stats['overdue_count'] ?? 0 }}</h4>
                    <small class="text-muted">Past due date</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card-modern mb-4">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('accounting.statutory-remittances.index') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Status</label>
                    <select name="status" class="form-control form-control-sm">
                        <option value="">All Status</option>
                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending Approval</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                        <option value="voided" {{ request('status') == 'voided' ? 'selected' : '' }}>Voided</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Deduction Type</label>
                    <select name="pay_head_id" class="form-control form-control-sm">
                        <option value="">All Types</option>
                        @foreach($payHeads as $payHead)
                        <option value="{{ $payHead->id }}" {{ request('pay_head_id') == $payHead->id ? 'selected' : '' }}>
                            {{ $payHead->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Period From</label>
                    <input type="date" name="period_from" class="form-control form-control-sm" value="{{ request('period_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Period To</label>
                    <input type="date" name="period_to" class="form-control form-control-sm" value="{{ request('period_to') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="mdi mdi-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Remittances Table -->
    <div class="card-modern">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="mdi mdi-format-list-bulleted mr-2"></i>Remittances</h5>
            <span class="badge badge-secondary">{{ $remittances->total() }} total</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Reference</th>
                            <th>Type</th>
                            <th>Period</th>
                            <th>Payee</th>
                            <th class="text-right">Amount</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($remittances as $remittance)
                        <tr>
                            <td>
                                <a href="{{ route('accounting.statutory-remittances.show', $remittance) }}" class="font-weight-bold">
                                    {{ $remittance->reference_number }}
                                </a>
                            </td>
                            <td>
                                <span class="badge badge-info">{{ $remittance->payHead?->name ?? 'N/A' }}</span>
                            </td>
                            <td>{{ $remittance->period_string }}</td>
                            <td>{{ $remittance->payee_name }}</td>
                            <td class="text-right font-weight-bold">₦{{ number_format($remittance->amount, 2) }}</td>
                            <td>
                                @if($remittance->due_date)
                                    @if($remittance->due_date < now() && !in_array($remittance->status, ['paid', 'voided']))
                                        <span class="text-danger">
                                            <i class="mdi mdi-alert-circle"></i>
                                            {{ $remittance->due_date->format('M d, Y') }}
                                        </span>
                                    @else
                                        {{ $remittance->due_date->format('M d, Y') }}
                                    @endif
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-{{ $remittance->status_badge }}">
                                    {{ ucfirst($remittance->status) }}
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('accounting.statutory-remittances.show', $remittance) }}"
                                       class="btn btn-outline-info" title="View">
                                        <i class="mdi mdi-eye"></i>
                                    </a>
                                    @if($remittance->canEdit())
                                    <a href="{{ route('accounting.statutory-remittances.edit', $remittance) }}"
                                       class="btn btn-outline-primary" title="Edit">
                                        <i class="mdi mdi-pencil"></i>
                                    </a>
                                    @endif
                                    @if($remittance->status === 'draft')
                                    <button type="button" class="btn btn-outline-success submit-btn"
                                            data-id="{{ $remittance->id }}" title="Submit for Approval">
                                        <i class="mdi mdi-send"></i>
                                    </button>
                                    @endif
                                    @if($remittance->canApprove())
                                    <button type="button" class="btn btn-outline-success approve-btn"
                                            data-id="{{ $remittance->id }}" title="Approve">
                                        <i class="mdi mdi-check"></i>
                                    </button>
                                    @endif
                                    @if($remittance->canPay())
                                    <button type="button" class="btn btn-outline-primary pay-btn"
                                            data-id="{{ $remittance->id }}"
                                            data-amount="{{ $remittance->amount }}"
                                            data-payee="{{ $remittance->payee_name }}"
                                            title="Mark as Paid">
                                        <i class="mdi mdi-cash-check"></i>
                                    </button>
                                    @endif
                                    @if($remittance->canVoid())
                                    <button type="button" class="btn btn-outline-danger void-btn"
                                            data-id="{{ $remittance->id }}" title="Void">
                                        <i class="mdi mdi-cancel"></i>
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="mdi mdi-information-outline mdi-24px"></i>
                                <p class="mb-0">No remittances found</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($remittances->hasPages())
        <div class="card-footer">
            {{ $remittances->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Pay Modal -->
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="mdi mdi-cash-check mr-2"></i>Record Payment</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal">&times;</button>
            </div>
            <form id="payForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Amount:</strong> ₦<span id="payAmount"></span><br>
                        <strong>Payee:</strong> <span id="payPayee"></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Date *</label>
                        <input type="date" name="remittance_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Method *</label>
                        <select name="payment_method" class="form-control" required>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                            <option value="cash">Cash</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Bank Account *</label>
                        <select name="bank_id" class="form-control" required>
                            <option value="">Select Bank</option>
                            @foreach($banks as $bank)
                            <option value="{{ $bank->id }}">{{ $bank->bank_name }} - {{ $bank->account_number }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Transaction Reference</label>
                        <input type="text" name="transaction_reference" class="form-control" placeholder="Bank reference or cheque number">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="mdi mdi-check mr-1"></i> Confirm Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Void Modal -->
<div class="modal fade" id="voidModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="mdi mdi-cancel mr-2"></i>Void Remittance</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal">&times;</button>
            </div>
            <form id="voidForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="mdi mdi-alert mr-1"></i>
                        This action cannot be undone. If this remittance was already paid, the journal entry will be reversed.
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reason for Voiding *</label>
                        <textarea name="void_reason" class="form-control" rows="3" required placeholder="Enter reason..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="mdi mdi-cancel mr-1"></i> Void Remittance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function() {
    // Submit for approval
    $('.submit-btn').click(function() {
        const id = $(this).data('id');
        if (confirm('Submit this remittance for approval?')) {
            $.post("{{ url('accounting/statutory-remittances') }}/" + id + "/submit", {
                _token: '{{ csrf_token() }}'
            }).done(function(response) {
                toastr.success(response.message || 'Remittance submitted for approval');
                location.reload();
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to submit remittance');
            });
        }
    });

    // Approve
    $('.approve-btn').click(function() {
        const id = $(this).data('id');
        if (confirm('Approve this remittance?')) {
            $.post("{{ url('accounting/statutory-remittances') }}/" + id + "/approve", {
                _token: '{{ csrf_token() }}'
            }).done(function(response) {
                toastr.success(response.message || 'Remittance approved');
                location.reload();
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to approve remittance');
            });
        }
    });

    // Pay modal
    $('.pay-btn').click(function() {
        const id = $(this).data('id');
        const amount = $(this).data('amount');
        const payee = $(this).data('payee');

        $('#payAmount').text(parseFloat(amount).toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#payPayee').text(payee);
        $('#payForm').attr('action', "{{ url('accounting/statutory-remittances') }}/" + id + "/pay");
        $('#payModal').modal('show');
    });

    // Void modal
    $('.void-btn').click(function() {
        const id = $(this).data('id');
        $('#voidForm').attr('action', "{{ url('accounting/statutory-remittances') }}/" + id + "/void");
        $('#voidModal').modal('show');
    });
});
</script>
@endsection
