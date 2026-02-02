@extends('admin.layouts.app')

@section('page_name', 'Accounting')
@section('subpage_name', 'Edit Credit Note')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Credit Notes', 'url' => route('accounting.credit-notes.index'), 'icon' => 'mdi-note-text'],
    ['label' => $creditNote->credit_note_number, 'url' => route('accounting.credit-notes.show', $creditNote->id), 'icon' => 'mdi-eye'],
    ['label' => 'Edit', 'url' => '#', 'icon' => 'mdi-pencil']
]])

<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Edit Credit Note</h4>
            <p class="text-muted mb-0">Modify credit note details</p>
        </div>
        <div>
            <a href="{{ route('accounting.credit-notes.show', $creditNote->id) }}" class="btn btn-outline-secondary">
                <i class="mdi mdi-arrow-left mr-1"></i> Back to Details
            </a>
        </div>
    </div>

    @if($creditNote->status === 'rejected')
    <div class="alert alert-warning">
        <i class="mdi mdi-alert-circle mr-2"></i>
        <strong>Rejected Credit Note:</strong> This credit note was rejected. You can make changes and resubmit for approval.
    </div>
    @endif

    <form id="creditNoteEditForm">
        @csrf
        @method('PUT')

        <div class="row">
            {{-- Main Form --}}
            <div class="col-lg-8">
                <div class="card-modern mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="mdi mdi-file-document-edit-outline mr-2"></i>Credit Note Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Credit Note Number <span class="text-danger">*</span></label>
                                <input type="text" name="credit_note_number" id="creditNoteNumber" class="form-control"
                                       value="{{ $creditNote->credit_note_number }}" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date" name="date" id="creditDate" class="form-control"
                                       value="{{ $creditNote->date ? \Carbon\Carbon::parse($creditNote->date)->format('Y-m-d') : date('Y-m-d') }}" required>
                            </div>
                        </div>

                        <hr class="my-4">

                        {{-- Patient Information (Read-only) --}}
                        <div class="form-group">
                            <label class="form-label">Patient</label>
                            <div class="alert alert-light mb-0">
                                <div class="d-flex align-items-center">
                                    <div class="avatar avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 40px; height: 40px;">
                                        {{ strtoupper(substr($creditNote->patient->user->name ?? 'P', 0, 1)) }}
                                    </div>
                                    <div>
                                        <strong>{{ $creditNote->patient->user->name ?? 'N/A' }}</strong>
                                        <br>
                                        <small class="text-muted">MRN: {{ $creditNote->patient->mrn ?? 'N/A' }}</small>
                                    </div>
                                </div>
                            </div>
                            <small class="text-muted">Patient cannot be changed after creation.</small>
                        </div>

                        {{-- Original Payment (Read-only if set) --}}
                        @if($creditNote->original_payment_id)
                        <div class="form-group">
                            <label class="form-label">Original Payment</label>
                            <div class="alert alert-light mb-0">
                                <strong>{{ $creditNote->originalPayment->receipt_number ?? 'Payment #' . $creditNote->original_payment_id }}</strong>
                                <span class="badge badge-info ml-2">₦{{ number_format($creditNote->originalPayment->amount ?? 0, 2) }}</span>
                            </div>
                        </div>
                        @endif

                        <hr class="my-4">

                        {{-- Reason and Amount --}}
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Reason Type <span class="text-danger">*</span></label>
                                <select name="reason_type" id="reasonType" class="form-control" required>
                                    <option value="">Select Reason</option>
                                    <option value="billing_error" {{ $creditNote->reason_type === 'billing_error' ? 'selected' : '' }}>Billing Error</option>
                                    <option value="service_not_rendered" {{ $creditNote->reason_type === 'service_not_rendered' ? 'selected' : '' }}>Service Not Rendered</option>
                                    <option value="duplicate_charge" {{ $creditNote->reason_type === 'duplicate_charge' ? 'selected' : '' }}>Duplicate Charge</option>
                                    <option value="overcharge" {{ $creditNote->reason_type === 'overcharge' ? 'selected' : '' }}>Overcharge</option>
                                    <option value="discount_adjustment" {{ $creditNote->reason_type === 'discount_adjustment' ? 'selected' : '' }}>Discount Adjustment</option>
                                    <option value="insurance_adjustment" {{ $creditNote->reason_type === 'insurance_adjustment' ? 'selected' : '' }}>Insurance Adjustment</option>
                                    <option value="patient_request" {{ $creditNote->reason_type === 'patient_request' ? 'selected' : '' }}>Patient Request</option>
                                    <option value="other" {{ $creditNote->reason_type === 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Amount <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₦</span>
                                    </div>
                                    <input type="number" name="amount" id="amount" class="form-control"
                                           value="{{ $creditNote->amount }}" step="0.01" min="0.01" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Reason Description <span class="text-danger">*</span></label>
                            <textarea name="reason" id="reason" class="form-control" rows="3" required
                                      placeholder="Provide detailed explanation for this credit note...">{{ $creditNote->reason }}</textarea>
                        </div>

                        {{-- Supporting Documents --}}
                        <div class="form-group">
                            <label class="form-label">Reference Documents</label>
                            <textarea name="supporting_documents" id="supportingDocs" class="form-control" rows="2"
                                      placeholder="Reference any supporting documents or authorization numbers...">{{ $creditNote->supporting_documents }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Side Panel --}}
            <div class="col-lg-4">
                {{-- Status Card --}}
                <div class="card-modern mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="mdi mdi-information mr-2"></i>Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="text-muted small">Current Status</label>
                            @php
                                $statusColors = [
                                    'draft' => 'secondary',
                                    'pending_approval' => 'warning',
                                    'approved' => 'success',
                                    'rejected' => 'danger',
                                    'processed' => 'info',
                                    'void' => 'dark',
                                ];
                            @endphp
                            <h4>
                                <span class="badge badge-{{ $statusColors[$creditNote->status] ?? 'secondary' }}">
                                    {{ ucfirst(str_replace('_', ' ', $creditNote->status)) }}
                                </span>
                            </h4>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small">Created By</label>
                            <p class="mb-0">{{ $creditNote->createdBy->name ?? 'N/A' }}</p>
                            <small class="text-muted">{{ $creditNote->created_at->format('M d, Y H:i') }}</small>
                        </div>
                    </div>
                </div>

                {{-- Summary Card --}}
                <div class="card-modern mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="mdi mdi-cash-refund mr-2"></i>Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="text-muted small">Credit Amount</label>
                            <h3 class="mb-0 text-primary" id="summaryAmount">₦ {{ number_format($creditNote->amount, 2) }}</h3>
                        </div>

                        <div class="mb-3">
                            <label class="text-muted small">Patient</label>
                            <h6 class="mb-0">{{ $creditNote->patient->user->name ?? 'N/A' }}</h6>
                        </div>

                        <hr>

                        <div class="d-grid gap-2">
                            <button type="button" id="saveBtn" class="btn btn-primary">
                                <i class="mdi mdi-content-save mr-1"></i> Save Changes
                            </button>
                            @if($creditNote->status === 'draft' || $creditNote->status === 'rejected')
                            <button type="button" id="saveSubmitBtn" class="btn btn-success">
                                <i class="mdi mdi-send mr-1"></i> Save & Submit for Approval
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('styles')
<style>
    .card-modern {
        border: none;
        box-shadow: 0 0 10px rgba(0,0,0,0.05);
        border-radius: 10px;
    }
    .card-modern .card-header {
        background-color: transparent;
        border-bottom: 1px solid #eee;
    }
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    // Update summary amount on change
    $('#amount').on('input', function() {
        var amount = parseFloat($(this).val()) || 0;
        $('#summaryAmount').text('₦ ' + amount.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
    });

    // Save button click
    $('#saveBtn').on('click', function() {
        submitForm(false);
    });

    // Save & Submit button click
    $('#saveSubmitBtn').on('click', function() {
        submitForm(true);
    });

    function submitForm(submitForApproval) {
        var form = $('#creditNoteEditForm');
        var formData = form.serialize();

        // Disable buttons during submission
        $('#saveBtn, #saveSubmitBtn').prop('disabled', true);

        $.ajax({
            url: '{{ route("accounting.credit-notes.update", $creditNote->id) }}',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    if (submitForApproval) {
                        // Submit for approval after save
                        $.ajax({
                            url: '{{ route("accounting.credit-notes.submit", $creditNote->id) }}',
                            type: 'POST',
                            data: { _token: '{{ csrf_token() }}' },
                            success: function(submitResponse) {
                                if (submitResponse.success) {
                                    toastr.success('Credit note saved and submitted for approval');
                                    window.location.href = submitResponse.redirect || '{{ route("accounting.credit-notes.show", $creditNote->id) }}';
                                } else {
                                    toastr.warning(response.message + ' - But failed to submit for approval.');
                                    window.location.href = '{{ route("accounting.credit-notes.show", $creditNote->id) }}';
                                }
                            },
                            error: function() {
                                toastr.warning('Saved but failed to submit for approval');
                                window.location.href = '{{ route("accounting.credit-notes.show", $creditNote->id) }}';
                            }
                        });
                    } else {
                        toastr.success(response.message);
                        window.location.href = response.redirect || '{{ route("accounting.credit-notes.show", $creditNote->id) }}';
                    }
                } else {
                    toastr.error(response.message || 'Failed to save credit note');
                    $('#saveBtn, #saveSubmitBtn').prop('disabled', false);
                }
            },
            error: function(xhr) {
                var message = 'An error occurred';
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    } else if (xhr.responseJSON.errors) {
                        message = Object.values(xhr.responseJSON.errors).flat().join(', ');
                    }
                }
                toastr.error(message);
                $('#saveBtn, #saveSubmitBtn').prop('disabled', false);
            }
        });
    }
});
</script>
@endpush
