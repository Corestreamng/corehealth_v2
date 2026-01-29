@extends('admin.layouts.app')

@section('page_name', 'Accounting')
@section('subpage_name', 'Create Credit Note')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Create Credit Note</h4>
            <p class="text-muted mb-0">Issue a credit note for patient refund or adjustment</p>
        </div>
        <div>
            <a href="{{ route('accounting.credit-notes.index') }}" class="btn btn-outline-secondary">
                <i class="mdi mdi-arrow-left mr-1"></i> Back to List
            </a>
        </div>
    </div>

    <form id="creditNoteForm">
        @csrf

        <div class="row">
            {{-- Main Form --}}
            <div class="col-lg-8">
                <div class="card card-modern mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="mdi mdi-file-document-edit-outline mr-2"></i>Credit Note Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Credit Note Number <span class="text-danger">*</span></label>
                                <input type="text" name="credit_note_number" id="creditNoteNumber" class="form-control"
                                       value="{{ $nextNumber ?? 'CN-' . date('Ymd') . '-001' }}" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date" name="date" id="creditDate" class="form-control"
                                       value="{{ date('Y-m-d') }}" required>
                            </div>
                        </div>

                        <hr class="my-4">

                        {{-- Patient Selection --}}
                        <div class="form-group">
                            <label class="form-label">Patient <span class="text-danger">*</span></label>
                            <select name="patient_id" id="patientSelect" class="form-control" required></select>
                        </div>

                        {{-- Invoice Selection --}}
                        <div class="form-group" id="invoiceSection" style="display: none;">
                            <label class="form-label">Original Invoice</label>
                            <select name="invoice_id" id="invoiceSelect" class="form-control">
                                <option value="">-- No specific invoice --</option>
                            </select>
                            <small class="text-muted">Optional: Link this credit note to a specific invoice</small>
                        </div>

                        <hr class="my-4">

                        {{-- Reason and Amount --}}
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Reason Type <span class="text-danger">*</span></label>
                                <select name="reason_type" id="reasonType" class="form-control" required>
                                    <option value="">Select Reason</option>
                                    <option value="billing_error">Billing Error</option>
                                    <option value="service_not_rendered">Service Not Rendered</option>
                                    <option value="duplicate_charge">Duplicate Charge</option>
                                    <option value="overcharge">Overcharge</option>
                                    <option value="discount_adjustment">Discount Adjustment</option>
                                    <option value="insurance_adjustment">Insurance Adjustment</option>
                                    <option value="patient_request">Patient Request</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Amount <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₦</span>
                                    </div>
                                    <input type="number" name="amount" id="amount" class="form-control"
                                           step="0.01" min="0.01" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Reason Description <span class="text-danger">*</span></label>
                            <textarea name="reason" id="reason" class="form-control" rows="3" required
                                      placeholder="Provide detailed explanation for this credit note..."></textarea>
                        </div>

                        {{-- Supporting Documents --}}
                        <div class="form-group">
                            <label class="form-label">Reference Documents</label>
                            <textarea name="supporting_documents" id="supportingDocs" class="form-control" rows="2"
                                      placeholder="Reference any supporting documents or authorization numbers..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Side Panel --}}
            <div class="col-lg-4">
                {{-- Summary Card --}}
                <div class="card card-modern mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="mdi mdi-cash-refund mr-2"></i>Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="text-muted small">Credit Amount</label>
                            <h3 class="mb-0 text-primary" id="summaryAmount">₦ 0.00</h3>
                        </div>

                        <div class="mb-3" id="patientSummary" style="display: none;">
                            <label class="text-muted small">Patient</label>
                            <h6 class="mb-0" id="patientSummaryName">-</h6>
                        </div>

                        <hr>

                        <div class="d-grid gap-2">
                            <button type="button" id="saveDraftBtn" class="btn btn-outline-secondary">
                                <i class="mdi mdi-content-save mr-1"></i> Save as Draft
                            </button>
                            <button type="button" id="saveSubmitBtn" class="btn btn-primary">
                                <i class="mdi mdi-send mr-1"></i> Save & Submit for Approval
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Guidelines Card --}}
                <div class="card card-modern">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="mdi mdi-information-outline mr-2"></i>Guidelines</h5>
                    </div>
                    <div class="card-body">
                        <ul class="small mb-0 pl-3">
                            <li class="mb-2">Credit notes require approval before refunds can be processed.</li>
                            <li class="mb-2">Link to the original invoice whenever possible for audit trail.</li>
                            <li class="mb-2">Provide detailed reasons to expedite approval.</li>
                            <li>Approved credit notes will automatically create journal entries.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
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

    .select2-container--bootstrap4 .select2-selection {
        height: calc(1.5em + 0.75rem + 2px);
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
<script>
$(document).ready(function() {
    // Initialize patient Select2 with AJAX
    $('#patientSelect').select2({
        theme: 'bootstrap4',
        placeholder: 'Search patient by name or MRN...',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: '/api/patients/search',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { q: params.term };
            },
            processResults: function(data) {
                return {
                    results: data.map(function(patient) {
                        return {
                            id: patient.id,
                            text: patient.fullname + ' (' + patient.mrn + ')',
                            patient: patient
                        };
                    })
                };
            },
            cache: true
        }
    });

    // On patient select, load invoices
    $('#patientSelect').on('select2:select', function(e) {
        var patient = e.params.data.patient;
        $('#patientSummary').show();
        $('#patientSummaryName').text(patient.fullname);
        loadPatientInvoices(patient.id);
    });

    $('#patientSelect').on('select2:clear', function() {
        $('#patientSummary').hide();
        $('#invoiceSection').hide();
        $('#invoiceSelect').html('<option value="">-- No specific invoice --</option>');
    });

    function loadPatientInvoices(patientId) {
        $.get('/accounting/credit-notes/api/patient/' + patientId + '/invoices')
        .done(function(data) {
            var $select = $('#invoiceSelect');
            $select.html('<option value="">-- No specific invoice --</option>');

            if (data && data.length > 0) {
                data.forEach(function(invoice) {
                    $select.append('<option value="' + invoice.id + '">' +
                        invoice.invoice_number + ' - ₦' + parseFloat(invoice.total).toLocaleString() +
                        ' (' + invoice.date + ')</option>');
                });
            }

            $('#invoiceSection').show();
        })
        .fail(function() {
            $('#invoiceSection').show();
        });
    }

    // Amount summary
    $('#amount').on('input', function() {
        var amount = parseFloat($(this).val()) || 0;
        $('#summaryAmount').text('₦ ' + amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
    });

    // Save handlers
    $('#saveDraftBtn').on('click', function() {
        saveCreditNote('draft');
    });

    $('#saveSubmitBtn').on('click', function() {
        saveCreditNote('submit');
    });

    function saveCreditNote(action) {
        // Validate
        if (!$('#patientSelect').val()) {
            toastr.error('Please select a patient');
            return;
        }
        if (!$('#reasonType').val()) {
            toastr.error('Please select a reason type');
            return;
        }
        if (!$('#amount').val() || parseFloat($('#amount').val()) <= 0) {
            toastr.error('Please enter a valid amount');
            return;
        }
        if (!$('#reason').val().trim()) {
            toastr.error('Please provide a reason description');
            return;
        }

        var $btn = action === 'draft' ? $('#saveDraftBtn') : $('#saveSubmitBtn');
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Saving...');

        $.ajax({
            url: '{{ route("accounting.credit-notes.store") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                credit_note_number: $('#creditNoteNumber').val(),
                date: $('#creditDate').val(),
                patient_id: $('#patientSelect').val(),
                invoice_id: $('#invoiceSelect').val(),
                reason_type: $('#reasonType').val(),
                amount: $('#amount').val(),
                reason: $('#reason').val(),
                supporting_documents: $('#supportingDocs').val(),
                action: action
            }
        })
        .done(function(response) {
            if (response.success) {
                toastr.success(response.message || 'Credit note created successfully');
                setTimeout(function() {
                    window.location.href = response.redirect || '{{ route("accounting.credit-notes.index") }}';
                }, 1000);
            } else {
                toastr.error(response.message || 'Error creating credit note');
                $btn.prop('disabled', false).html(originalText);
            }
        })
        .fail(function(xhr) {
            var message = 'Error creating credit note';
            if (xhr.responseJSON) {
                if (xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                } else if (xhr.responseJSON.errors) {
                    message = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                }
            }
            toastr.error(message);
            $btn.prop('disabled', false).html(originalText);
        });
    }
});
</script>
@endpush
