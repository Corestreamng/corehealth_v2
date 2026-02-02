{{--
    Patient Deposit Details
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 4
    Access: SUPERADMIN|ADMIN|ACCOUNTS|BILLER
--}}

@extends('admin.layouts.app')

@section('title', $patientDeposit->deposit_number)
@section('page_name', 'Accounting')
@section('subpage_name', 'Deposit Details')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Patient Deposits', 'url' => route('accounting.patient-deposits.index'), 'icon' => 'mdi-account-cash'],
        ['label' => $patientDeposit->deposit_number, 'url' => '#', 'icon' => 'mdi-information']
    ]
])

<style>
.deposit-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    border-radius: 10px;
    margin-bottom: 25px;
}
.deposit-header .number {
    font-size: 1.5rem;
    font-weight: 600;
}
.deposit-header .status-badge {
    padding: 6px 15px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}
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
.info-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px dashed #eee;
}
.info-row:last-child { border-bottom: none; }
.info-row .label { color: #666; }
.info-row .value { font-weight: 500; }
.balance-card {
    text-align: center;
    padding: 25px;
    border-radius: 10px;
}
.balance-card.positive {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
}
.balance-card.zero {
    background: #f8f9fa;
    color: #666;
}
.balance-card.negative {
    background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
    color: white;
}
.balance-card .amount {
    font-size: 2.5rem;
    font-weight: 700;
}
.balance-card .label {
    opacity: 0.9;
}
.progress-bar-custom {
    height: 25px;
    border-radius: 15px;
    background: #e9ecef;
    overflow: hidden;
    margin-bottom: 10px;
}
.progress-bar-custom .fill {
    height: 100%;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    transition: width 0.5s;
}
.je-preview {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
}
.je-line {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}
.je-line:last-child { border-bottom: none; }
.timeline-item {
    position: relative;
    padding-left: 30px;
    padding-bottom: 20px;
}
.timeline-item::before {
    content: '';
    position: absolute;
    left: 8px;
    top: 25px;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}
.timeline-item:last-child::before { display: none; }
.timeline-dot {
    position: absolute;
    left: 0;
    top: 5px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #667eea;
    border: 3px solid white;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
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
    <div class="deposit-header">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="number">{{ $patientDeposit->deposit_number }}</div>
                <div class="opacity-75">
                    <i class="mdi mdi-calendar mr-1"></i> {{ $patientDeposit->deposit_date->format('F d, Y') }}
                </div>
            </div>
            <div class="col-md-6 text-md-right">
                @php
                    $statusColors = [
                        'active' => 'bg-success',
                        'fully_applied' => 'bg-info',
                        'refunded' => 'bg-warning text-dark',
                        'cancelled' => 'bg-danger',
                    ];
                @endphp
                <span class="status-badge {{ $statusColors[$patientDeposit->status] ?? 'bg-secondary' }}">
                    {{ $patientDeposit->status_label }}
                </span>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Patient Info -->
            <div class="info-card">
                <h6><i class="mdi mdi-account mr-2"></i>Patient Information</h6>
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-row">
                            <span class="label">Name</span>
                            <span class="value">{{ $patientDeposit->patient->user?->name ?? $patientDeposit->patient->full_name ?? 'Unknown' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">File Number</span>
                            <span class="value">{{ $patientDeposit->patient->file_no ?? 'N/A' }}</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <span class="label">Phone</span>
                            <span class="value">{{ $patientDeposit->patient->phone_no ?? 'N/A' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">HMO</span>
                            <span class="value">{{ $patientDeposit->patient->hmo?->name ?? 'Self-Pay' }}</span>
                        </div>
                    </div>
                </div>
                @if($patientAccount)
                    <div class="mt-3 p-3 rounded {{ $patientAccount->balance >= 0 ? 'bg-success-light' : 'bg-danger-light' }}" style="background: {{ $patientAccount->balance >= 0 ? '#e8f5e9' : '#ffebee' }};">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Current Account Balance:</span>
                            <span class="h5 mb-0 {{ $patientAccount->balance >= 0 ? 'text-success' : 'text-danger' }}">
                                ₦{{ number_format(abs($patientAccount->balance), 2) }}
                                @if($patientAccount->balance > 0)
                                    <small>(Credit)</small>
                                @elseif($patientAccount->balance < 0)
                                    <small>(Debt)</small>
                                @endif
                            </span>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Deposit Details -->
            <div class="info-card">
                <h6><i class="mdi mdi-cash-multiple mr-2"></i>Deposit Details</h6>
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-row">
                            <span class="label">Deposit Type</span>
                            <span class="value">{{ $patientDeposit->deposit_type_label }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Payment Method</span>
                            <span class="value">{{ ucfirst($patientDeposit->payment_method) }}</span>
                        </div>
                        @if($patientDeposit->bank)
                            <div class="info-row">
                                <span class="label">Bank</span>
                                <span class="value">{{ $patientDeposit->bank->bank_name }}</span>
                            </div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        @if($patientDeposit->payment_reference)
                            <div class="info-row">
                                <span class="label">Payment Reference</span>
                                <span class="value">{{ $patientDeposit->payment_reference }}</span>
                            </div>
                        @endif
                        <div class="info-row">
                            <span class="label">Received By</span>
                            <span class="value">{{ $patientDeposit->receiver?->name ?? 'System' }}</span>
                        </div>
                        @if($patientDeposit->admission)
                            <div class="info-row">
                                <span class="label">Linked Admission</span>
                                <span class="value">{{ $patientDeposit->admission->admission_no ?? 'N/A' }}</span>
                            </div>
                        @endif
                    </div>
                </div>
                @if($patientDeposit->notes)
                    <div class="mt-3 p-3 bg-light rounded">
                        <strong>Notes:</strong> {{ $patientDeposit->notes }}
                    </div>
                @endif
            </div>

            <!-- Amount Breakdown -->
            <div class="info-card">
                <h6><i class="mdi mdi-calculator mr-2"></i>Amount Breakdown</h6>

                <div class="progress-bar-custom mb-3">
                    @php
                        $utilizedPercent = $patientDeposit->amount > 0
                            ? min(100, ($patientDeposit->utilized_amount / $patientDeposit->amount) * 100)
                            : 0;
                        $refundedPercent = $patientDeposit->amount > 0
                            ? min(100 - $utilizedPercent, ($patientDeposit->refunded_amount / $patientDeposit->amount) * 100)
                            : 0;
                    @endphp
                    <div class="fill bg-success" style="width: {{ $utilizedPercent }}%">
                        @if($utilizedPercent > 10)
                            {{ number_format($utilizedPercent, 0) }}% Used
                        @endif
                    </div>
                </div>

                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="h4 text-primary mb-0">₦{{ number_format($patientDeposit->amount, 2) }}</div>
                        <small class="text-muted">Original Amount</small>
                    </div>
                    <div class="col-md-3">
                        <div class="h4 text-success mb-0">₦{{ number_format($patientDeposit->utilized_amount, 2) }}</div>
                        <small class="text-muted">Utilized</small>
                    </div>
                    <div class="col-md-3">
                        <div class="h4 text-warning mb-0">₦{{ number_format($patientDeposit->refunded_amount, 2) }}</div>
                        <small class="text-muted">Refunded</small>
                    </div>
                    <div class="col-md-3">
                        <div class="h4 text-info mb-0">₦{{ number_format($patientDeposit->balance, 2) }}</div>
                        <small class="text-muted">Balance</small>
                    </div>
                </div>
            </div>

            <!-- Journal Entry -->
            @if($patientDeposit->journalEntry)
                <div class="info-card">
                    <h6><i class="mdi mdi-book-open mr-2"></i>Journal Entry</h6>
                    <div class="je-preview">
                        <div class="d-flex justify-content-between mb-2">
                            <span><strong>Entry #:</strong> {{ $patientDeposit->journalEntry->entry_number }}</span>
                            <span><strong>Date:</strong> {{ $patientDeposit->journalEntry->entry_date->format('M d, Y') }}</span>
                        </div>
                        <hr>
                        @foreach($patientDeposit->journalEntry->lines as $line)
                            <div class="je-line">
                                <div>
                                    <span class="badge badge-{{ $line->debit > 0 ? 'primary' : 'success' }}">
                                        {{ $line->debit > 0 ? 'DR' : 'CR' }}
                                    </span>
                                    {{ $line->account->display_name ?? $line->account->account_name }}
                                </div>
                                <div class="font-weight-bold">
                                    ₦{{ number_format($line->debit ?: $line->credit, 2) }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Applications History -->
            @if($patientDeposit->applications->count() > 0)
                <div class="info-card">
                    <h6><i class="mdi mdi-history mr-2"></i>Application History</h6>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Applied By</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($patientDeposit->applications as $app)
                                <tr>
                                    <td>{{ $app->application_date->format('M d, Y') }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $app->application_type)) }}</td>
                                    <td>₦{{ number_format($app->amount, 2) }}</td>
                                    <td>{{ $app->appliedByUser?->name ?? 'System' }}</td>
                                    <td>
                                        <span class="badge badge-{{ $app->status === 'applied' ? 'success' : 'danger' }}">
                                            {{ ucfirst($app->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Balance Card -->
            <div class="balance-card {{ $patientDeposit->balance > 0 ? 'positive' : ($patientDeposit->balance < 0 ? 'negative' : 'zero') }} mb-4">
                <div class="label">Available Balance</div>
                <div class="amount">₦{{ number_format($patientDeposit->balance, 2) }}</div>
                <div class="label">{{ $patientDeposit->utilization_percentage }}% utilized</div>
            </div>

            <!-- Actions -->
            <div class="info-card">
                <h6><i class="mdi mdi-cog mr-2"></i>Actions</h6>
                <div class="btn-group-vertical w-100">
                    <a href="{{ route('accounting.patient-deposits.index') }}" class="btn btn-outline-secondary">
                        <i class="mdi mdi-arrow-left mr-1"></i> Back to List
                    </a>

                    @if($patientDeposit->isActive() && $patientDeposit->balance > 0)
                        <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#applyModal">
                            <i class="mdi mdi-credit-card mr-1"></i> Apply to Bill
                        </button>
                        <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#refundModal">
                            <i class="mdi mdi-cash-refund mr-1"></i> Process Refund
                        </button>
                    @endif

                    <a href="javascript:window.print()" class="btn btn-outline-info">
                        <i class="mdi mdi-printer mr-1"></i> Print Receipt
                    </a>
                </div>
            </div>

            <!-- Refund Info -->
            @if($patientDeposit->refunded_amount > 0)
                <div class="info-card">
                    <h6><i class="mdi mdi-cash-refund mr-2"></i>Refund Information</h6>
                    <div class="info-row">
                        <span class="label">Refunded Amount</span>
                        <span class="value text-warning">₦{{ number_format($patientDeposit->refunded_amount, 2) }}</span>
                    </div>
                    @if($patientDeposit->refund_reason)
                        <div class="info-row">
                            <span class="label">Reason</span>
                            <span class="value">{{ $patientDeposit->refund_reason }}</span>
                        </div>
                    @endif
                    <div class="info-row">
                        <span class="label">Refunded By</span>
                        <span class="value">{{ $patientDeposit->refunder?->name ?? 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Refunded At</span>
                        <span class="value">{{ $patientDeposit->refunded_at?->format('M d, Y H:i') ?? 'N/A' }}</span>
                    </div>
                </div>
            @endif

            <!-- Other Deposits -->
            @if($otherDeposits->count() > 0)
                <div class="info-card">
                    <h6><i class="mdi mdi-format-list-bulleted mr-2"></i>Other Deposits</h6>
                    @foreach($otherDeposits as $deposit)
                        <a href="{{ route('accounting.patient-deposits.show', $deposit) }}" class="d-block p-2 mb-2 bg-light rounded text-decoration-none">
                            <div class="d-flex justify-content-between">
                                <span>{{ $deposit->deposit_number }}</span>
                                <span class="badge badge-{{ $deposit->status === 'active' ? 'success' : 'secondary' }}">
                                    {{ ucfirst($deposit->status) }}
                                </span>
                            </div>
                            <small class="text-muted">
                                {{ $deposit->deposit_date->format('M d, Y') }} - ₦{{ number_format($deposit->balance, 2) }} balance
                            </small>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Apply Modal -->
<div class="modal fade" id="applyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="mdi mdi-credit-card mr-2"></i>Apply Deposit</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="apply-form">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Amount to Apply <span class="text-danger">*</span></label>
                        <input type="number" id="apply-amount" class="form-control"
                               step="0.01" min="0.01" max="{{ $patientDeposit->balance }}" required>
                        <small class="text-muted">Available: ₦{{ number_format($patientDeposit->balance, 2) }}</small>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea id="apply-notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Apply</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Refund Modal -->
<div class="modal fade" id="refundModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="mdi mdi-cash-refund mr-2"></i>Process Refund</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="refund-form">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="mdi mdi-alert-circle mr-2"></i>
                        This will reduce the patient's account balance and cannot be undone.
                    </div>
                    <div class="form-group">
                        <label>Refund Amount <span class="text-danger">*</span></label>
                        <input type="number" id="refund-amount" class="form-control"
                               step="0.01" min="0.01" max="{{ $patientDeposit->balance }}" required>
                        <small class="text-muted">Maximum: ₦{{ number_format($patientDeposit->balance, 2) }}</small>
                    </div>
                    <div class="form-group">
                        <label>Reason <span class="text-danger">*</span></label>
                        <textarea id="refund-reason" class="form-control" rows="2" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Process Refund</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $('#apply-form').on('submit', function(e) {
        e.preventDefault();

        $.ajax({
            url: '{{ route('accounting.patient-deposits.apply', $patientDeposit) }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                amount: $('#apply-amount').val(),
                notes: $('#apply-notes').val()
            },
            success: function(res) {
                toastr.success(res.message);
                location.reload();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to apply deposit');
            }
        });
    });

    $('#refund-form').on('submit', function(e) {
        e.preventDefault();

        $.ajax({
            url: '{{ route('accounting.patient-deposits.refund', $patientDeposit) }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                amount: $('#refund-amount').val(),
                reason: $('#refund-reason').val()
            },
            success: function(res) {
                toastr.success(res.message);
                location.reload();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to process refund');
            }
        });
    });
});
</script>
@endpush
