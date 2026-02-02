@extends('admin.layouts.app')
@section('title', 'Statutory Remittance Details')
@section('page_name', 'Accounting')
@section('subpage_name', 'Statutory Remittances')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="mdi mdi-bank-transfer text-primary mr-2"></i>
                Remittance: {{ $remittance->reference_number }}
            </h4>
            <p class="text-muted mb-0">{{ $remittance->payHead?->name ?? 'Unknown Type' }}</p>
        </div>
        <div class="btn-group">
            <a href="{{ route('accounting.statutory-remittances.index') }}" class="btn btn-outline-secondary">
                <i class="mdi mdi-arrow-left"></i> Back
            </a>
            @if($remittance->canEdit())
            <a href="{{ route('accounting.statutory-remittances.edit', $remittance) }}" class="btn btn-outline-primary">
                <i class="mdi mdi-pencil"></i> Edit
            </a>
            @endif
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Main Details Card -->
            <div class="card-modern mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="mdi mdi-file-document mr-2"></i>Remittance Details</h5>
                    <span class="badge badge-{{ $remittance->status_badge }} badge-lg px-3 py-2">
                        {{ strtoupper($remittance->status) }}
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="text-muted" width="40%">Reference Number:</td>
                                    <td><strong>{{ $remittance->reference_number }}</strong></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Deduction Type:</td>
                                    <td>
                                        <span class="badge badge-info">{{ $remittance->payHead?->name ?? 'N/A' }}</span>
                                        <br><small class="text-muted">Code: {{ $remittance->payHead?->code ?? 'N/A' }}</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Period:</td>
                                    <td>{{ $remittance->period_string }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Due Date:</td>
                                    <td>
                                        @if($remittance->due_date)
                                            @if($remittance->due_date < now() && !in_array($remittance->status, ['paid', 'voided']))
                                                <span class="text-danger font-weight-bold">
                                                    <i class="mdi mdi-alert-circle"></i>
                                                    {{ $remittance->due_date->format('M d, Y') }} (OVERDUE)
                                                </span>
                                            @else
                                                {{ $remittance->due_date->format('M d, Y') }}
                                            @endif
                                        @else
                                            <span class="text-muted">Not set</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="text-muted" width="40%">Amount:</td>
                                    <td><h4 class="text-primary mb-0">₦{{ number_format($remittance->amount, 2) }}</h4></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">GL Account:</td>
                                    <td>
                                        @if($remittance->liability_account)
                                            {{ $remittance->liability_account->code }} - {{ $remittance->liability_account->name }}
                                        @else
                                            <span class="text-warning">Not configured</span>
                                        @endif
                                    </td>
                                </tr>
                                @if($remittance->remittance_date)
                                <tr>
                                    <td class="text-muted">Payment Date:</td>
                                    <td>{{ $remittance->remittance_date->format('M d, Y') }}</td>
                                </tr>
                                @endif
                                @if($remittance->journal_entry_id)
                                <tr>
                                    <td class="text-muted">Journal Entry:</td>
                                    <td>
                                        <a href="{{ route('accounting.journal-entries.show', $remittance->journal_entry_id) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="mdi mdi-file-document"></i> View JE #{{ $remittance->journal_entry_id }}
                                        </a>
                                    </td>
                                </tr>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payee Details -->
            <div class="card-modern mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="mdi mdi-office-building mr-2"></i>Payee Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="text-muted" width="40%">Payee Name:</td>
                                    <td><strong>{{ $remittance->payee_name }}</strong></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Bank Name:</td>
                                    <td>{{ $remittance->payee_bank_name ?: '-' }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="text-muted" width="40%">Account Number:</td>
                                    <td>{{ $remittance->payee_account_number ?: '-' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Details (if paid) -->
            @if($remittance->status === 'paid')
            <div class="card-modern mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="mdi mdi-cash-check mr-2"></i>Payment Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="text-muted" width="40%">Payment Method:</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $remittance->payment_method ?? '-')) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Our Bank:</td>
                                    <td>{{ $remittance->bank?->bank_name ?? '-' }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="text-muted" width="40%">Transaction Ref:</td>
                                    <td>{{ $remittance->transaction_reference ?: '-' }}</td>
                                </tr>
                                @if($remittance->cheque_number)
                                <tr>
                                    <td class="text-muted">Cheque Number:</td>
                                    <td>{{ $remittance->cheque_number }}</td>
                                </tr>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Notes -->
            @if($remittance->notes)
            <div class="card-modern mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="mdi mdi-note-text mr-2"></i>Notes</h5>
                </div>
                <div class="card-body">
                    {{ $remittance->notes }}
                </div>
            </div>
            @endif

            <!-- Void Info -->
            @if($remittance->status === 'voided')
            <div class="card-modern mb-4 border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="mdi mdi-cancel mr-2"></i>Void Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td class="text-muted" width="20%">Voided By:</td>
                            <td>{{ $remittance->voidedBy?->name ?? 'Unknown' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Voided At:</td>
                            <td>{{ $remittance->voided_at?->format('M d, Y H:i') ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Reason:</td>
                            <td>{{ $remittance->void_reason ?? 'No reason provided' }}</td>
                        </tr>
                    </table>
                </div>
            </div>
            @endif
        </div>

        <div class="col-lg-4">
            <!-- Actions Card -->
            <div class="card-modern mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="mdi mdi-cog mr-2"></i>Actions</h6>
                </div>
                <div class="card-body">
                    @if($remittance->status === 'draft')
                    <button type="button" class="btn btn-success btn-block mb-2" id="submitBtn">
                        <i class="mdi mdi-send mr-1"></i> Submit for Approval
                    </button>
                    @endif

                    @if($remittance->canApprove())
                    <button type="button" class="btn btn-success btn-block mb-2" id="approveBtn">
                        <i class="mdi mdi-check mr-1"></i> Approve Remittance
                    </button>
                    @endif

                    @if($remittance->canPay())
                    <button type="button" class="btn btn-primary btn-block mb-2" data-toggle="modal" data-target="#payModal">
                        <i class="mdi mdi-cash-check mr-1"></i> Record Payment
                    </button>
                    @endif

                    @if($remittance->canVoid())
                    <button type="button" class="btn btn-outline-danger btn-block" data-toggle="modal" data-target="#voidModal">
                        <i class="mdi mdi-cancel mr-1"></i> Void Remittance
                    </button>
                    @endif

                    @if(!$remittance->canEdit() && !$remittance->canApprove() && !$remittance->canPay() && !$remittance->canVoid())
                    <p class="text-muted text-center mb-0">
                        <i class="mdi mdi-information-outline"></i> No actions available
                    </p>
                    @endif
                </div>
            </div>

            <!-- Audit Trail -->
            <div class="card-modern">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="mdi mdi-history mr-2"></i>Audit Trail</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <small class="text-muted">Created</small><br>
                            <strong>{{ $remittance->preparedBy?->name ?? 'System' }}</strong>
                            <br><small class="text-muted">{{ $remittance->created_at->format('M d, Y H:i') }}</small>
                        </li>
                        @if($remittance->approved_at)
                        <li class="list-group-item">
                            <small class="text-muted">Approved</small><br>
                            <strong>{{ $remittance->approvedBy?->name ?? 'Unknown' }}</strong>
                            <br><small class="text-muted">{{ $remittance->approved_at->format('M d, Y H:i') }}</small>
                        </li>
                        @endif
                        @if($remittance->paid_at)
                        <li class="list-group-item">
                            <small class="text-muted">Paid</small><br>
                            <strong>{{ $remittance->paidBy?->name ?? 'Unknown' }}</strong>
                            <br><small class="text-muted">{{ $remittance->paid_at->format('M d, Y H:i') }}</small>
                        </li>
                        @endif
                        @if($remittance->voided_at)
                        <li class="list-group-item text-danger">
                            <small class="text-muted">Voided</small><br>
                            <strong>{{ $remittance->voidedBy?->name ?? 'Unknown' }}</strong>
                            <br><small class="text-muted">{{ $remittance->voided_at->format('M d, Y H:i') }}</small>
                        </li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Pay Modal -->
@if($remittance->canPay())
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="mdi mdi-cash-check mr-2"></i>Record Payment</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">&times;</button>
            </div>
            <form action="{{ route('accounting.statutory-remittances.pay', $remittance) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Amount:</strong> ₦{{ number_format($remittance->amount, 2) }}<br>
                        <strong>Payee:</strong> {{ $remittance->payee_name }}
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
                        <label class="form-label">Our Bank Account *</label>
                        <select name="bank_id" class="form-control" required>
                            <option value="">Select Bank</option>
                            @foreach($banks ?? [] as $bank)
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
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="mdi mdi-check mr-1"></i> Confirm Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<!-- Void Modal -->
@if($remittance->canVoid())
<div class="modal fade" id="voidModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="mdi mdi-cancel mr-2"></i>Void Remittance</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">&times;</button>
            </div>
            <form action="{{ route('accounting.statutory-remittances.void', $remittance) }}" method="POST">
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
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="mdi mdi-cancel mr-1"></i> Void Remittance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@section('scripts')
<script>
$(function() {
    @if($remittance->status === 'draft')
    $('#submitBtn').click(function() {
        if (confirm('Submit this remittance for approval?')) {
            $.post("{{ route('accounting.statutory-remittances.submit', $remittance) }}", {
                _token: '{{ csrf_token() }}'
            }).done(function(response) {
                toastr.success(response.message || 'Remittance submitted for approval');
                location.reload();
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to submit remittance');
            });
        }
    });
    @endif

    @if($remittance->canApprove())
    $('#approveBtn').click(function() {
        if (confirm('Approve this remittance?')) {
            $.post("{{ route('accounting.statutory-remittances.approve', $remittance) }}", {
                _token: '{{ csrf_token() }}'
            }).done(function(response) {
                toastr.success(response.message || 'Remittance approved');
                location.reload();
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to approve remittance');
            });
        }
    });
    @endif
});
</script>
@endsection
