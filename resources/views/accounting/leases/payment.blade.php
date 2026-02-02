@extends('admin.layouts.app')
@section('title', 'Record Lease Payment')
@section('page_name', 'Accounting')
@section('subpage_name', 'Record Payment')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Leases', 'url' => route('accounting.leases.index'), 'icon' => 'mdi-file-document-edit'],
    ['label' => $lease->lease_number, 'url' => route('accounting.leases.show', $lease->id), 'icon' => 'mdi-eye'],
    ['label' => 'Record Payment', 'url' => '#', 'icon' => 'mdi-cash']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Lease Summary Header -->
                <div class="card-modern mb-4">
                    <div class="card-body bg-primary text-white">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="mb-1">{{ $lease->lease_number }}</h4>
                                <p class="mb-0">{{ $lease->leased_item }} | {{ $lease->lessor_name ?: $lease->supplier_name }}</p>
                            </div>
                            <div class="col-md-4 text-right">
                                <small>Current Lease Liability</small>
                                <h3 class="mb-0">₦{{ number_format($lease->current_lease_liability, 2) }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                @if($nextPayment)
                <!-- Next Payment Alert -->
                <div class="alert alert-{{ $nextPayment->due_date < now()->toDateString() ? 'danger' : 'info' }} mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong><i class="mdi mdi-calendar-clock mr-2"></i>
                                {{ $nextPayment->due_date < now()->toDateString() ? 'OVERDUE PAYMENT' : 'Next Scheduled Payment' }}
                            </strong>
                            <p class="mb-0">
                                Payment #{{ $nextPayment->payment_number }} - ₦{{ number_format($nextPayment->payment_amount, 2) }}
                                due {{ \Carbon\Carbon::parse($nextPayment->due_date)->format('M d, Y') }}
                                @if($nextPayment->due_date < now()->toDateString())
                                    <span class="badge badge-danger ml-2">{{ \Carbon\Carbon::parse($nextPayment->due_date)->diffForHumans() }}</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Payment Form -->
                <form action="{{ route('accounting.leases.payment.store', $lease->id) }}" method="POST" id="payment-form">
                    @csrf
                    <input type="hidden" name="schedule_id" value="{{ $nextPayment->id }}">

                    <div class="card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-cash-multiple mr-2"></i>Payment Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="payment_date">Payment Date <span class="text-danger">*</span></label>
                                        <input type="date" name="payment_date" id="payment_date" class="form-control"
                                               value="{{ date('Y-m-d') }}" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="bank_account_id">Bank Account <span class="text-danger">*</span></label>
                                        <select name="bank_account_id" id="bank_account_id" class="form-control select2" required>
                                            <option value="">Select Bank Account</option>
                                            @foreach($bankAccounts as $bank)
                                                <option value="{{ $bank->id }}">{{ $bank->code }} - {{ $bank->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Breakdown (Read-only from schedule) -->
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Scheduled Payment</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="text" class="form-control bg-light"
                                                   value="{{ number_format($nextPayment->payment_amount, 2) }}" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Principal Portion</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="text" class="form-control bg-light"
                                                   value="{{ number_format($nextPayment->principal_portion, 2) }}" readonly>
                                        </div>
                                        <small class="text-muted">Reduces lease liability</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Interest Portion</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="text" class="form-control bg-light"
                                                   value="{{ number_format($nextPayment->interest_portion, 2) }}" readonly>
                                        </div>
                                        <small class="text-muted">Interest expense</small>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="actual_payment">Actual Amount Paid <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="number" name="actual_payment" id="actual_payment" class="form-control"
                                                   step="0.01" min="0" value="{{ $nextPayment->payment_amount }}" required>
                                        </div>
                                        <small class="text-muted">Enter actual amount paid</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="payment_reference">Payment Reference</label>
                                        <input type="text" name="payment_reference" id="payment_reference" class="form-control"
                                               placeholder="Check #, Transfer Ref, etc.">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <textarea name="notes" id="notes" class="form-control" rows="2"
                                          placeholder="Optional payment notes"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Balance Preview -->
                    <div class="card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-eye mr-2"></i>Balance Preview</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-4">
                                    <small class="text-muted">Liability Before Payment</small>
                                    <h5>₦{{ number_format($nextPayment->opening_liability, 2) }}</h5>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Principal Reduction</small>
                                    <h5 class="text-success">- ₦{{ number_format($nextPayment->principal_portion, 2) }}</h5>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Liability After Payment</small>
                                    <h5 class="text-primary">₦{{ number_format($nextPayment->closing_liability, 2) }}</h5>
                                </div>
                            </div>
                            <hr>
                            <div class="row text-center">
                                <div class="col-md-4">
                                    <small class="text-muted">ROU Asset Before</small>
                                    <h5>₦{{ number_format($nextPayment->opening_rou_value, 2) }}</h5>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Monthly Depreciation</small>
                                    <h5 class="text-danger">- ₦{{ number_format($nextPayment->rou_depreciation, 2) }}</h5>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">ROU Asset After</small>
                                    <h5 class="text-info">₦{{ number_format($nextPayment->closing_rou_value, 2) }}</h5>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('accounting.leases.show', $lease->id) }}" class="btn btn-outline-secondary">
                            <i class="mdi mdi-arrow-left"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="mdi mdi-check-circle"></i> Record Payment
                        </button>
                    </div>
                </form>
                @else
                <div class="alert alert-success">
                    <i class="mdi mdi-check-circle mr-2"></i>
                    <strong>All payments have been made!</strong> This lease has no pending payments.
                </div>
                <a href="{{ route('accounting.leases.show', $lease->id) }}" class="btn btn-primary">
                    <i class="mdi mdi-arrow-left"></i> Back to Lease Details
                </a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $('.select2').select2({
        theme: 'bootstrap4',
        placeholder: 'Select...',
        width: '100%'
    });
});
</script>
@endpush
