@extends('admin.layouts.app')

@section('page_name', 'Accounting')
@section('subpage_name', 'Create Journal Entry')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Journal Entries', 'url' => route('accounting.journal-entries.index'), 'icon' => 'mdi-book-open-page-variant'],
    ['label' => 'Create Entry', 'url' => '#', 'icon' => 'mdi-plus-circle']
]])

<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Create Journal Entry</h4>
            <p class="text-muted mb-0">Record a new accounting transaction</p>
        </div>
        <div>
            <a href="{{ route('accounting.journal-entries.index') }}" class="btn btn-outline-secondary">
                <i class="mdi mdi-arrow-left mr-1"></i> Back to List
            </a>
        </div>
    </div>

    <form id="journalEntryForm">
        @csrf

        <div class="row">
            {{-- Entry Details --}}
            <div class="col-lg-4 mb-4">
                <div class="card card-modern">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="mdi mdi-file-document-edit-outline mr-2"></i>Entry Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Entry Date <span class="text-danger">*</span></label>
                            <input type="date" name="entry_date" id="entryDate" class="form-control"
                                   value="{{ now()->format('Y-m-d') }}" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Entry Type</label>
                            <select name="entry_type" id="entryType" class="form-control">
                                <option value="standard">Standard</option>
                                <option value="adjusting">Adjusting Entry</option>
                                <option value="closing">Closing Entry</option>
                                <option value="reversing">Reversing Entry</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Reference</label>
                            <input type="text" name="reference" id="reference" class="form-control"
                                   placeholder="Invoice #, Receipt #, etc.">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" id="description" class="form-control"
                                      rows="3" required placeholder="Enter transaction description..."></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Internal Memo</label>
                            <textarea name="memo" id="memo" class="form-control" rows="2"
                                      placeholder="Internal notes (not shown on reports)"></textarea>
                        </div>
                    </div>
                </div>

                {{-- Totals Card --}}
                <div class="card card-modern mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="mdi mdi-sigma mr-2"></i>Totals</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Debits:</span>
                            <strong class="text-primary" id="totalDebits">₦0.00</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Credits:</span>
                            <strong class="text-success" id="totalCredits">₦0.00</strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span>Difference:</span>
                            <strong id="difference">₦0.00</strong>
                        </div>
                        <div id="balanceStatus" class="mt-3 text-center"></div>
                    </div>
                </div>
            </div>

            {{-- Entry Lines --}}
            <div class="col-lg-8 mb-4">
                <div class="card card-modern">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="mdi mdi-format-list-bulleted mr-2"></i>Entry Lines</h5>
                        <button type="button" class="btn btn-sm btn-success" id="addLineBtn">
                            <i class="mdi mdi-plus mr-1"></i> Add Line
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="linesTable">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 40%;">Account <span class="text-danger">*</span></th>
                                        <th style="width: 18%;">Debit</th>
                                        <th style="width: 18%;">Credit</th>
                                        <th style="width: 19%;">Description</th>
                                        <th style="width: 5%;"></th>
                                    </tr>
                                </thead>
                                <tbody id="linesBody">
                                    {{-- Lines added dynamically --}}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="card card-modern mt-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary" onclick="window.history.back()">
                                <i class="mdi mdi-close mr-1"></i> Cancel
                            </button>
                            <div>
                                <button type="button" class="btn btn-outline-info mr-2" id="previewBtn">
                                    <i class="mdi mdi-eye mr-1"></i> Preview
                                </button>
                                <button type="submit" class="btn btn-primary" id="saveBtn" disabled>
                                    <i class="mdi mdi-content-save mr-1"></i> Save as Draft
                                </button>
                                <button type="button" class="btn btn-success ml-2" id="saveAndSubmitBtn" disabled>
                                    <i class="mdi mdi-send mr-1"></i> Save & Submit
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- Preview Modal --}}
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="mdi mdi-file-document-outline mr-2"></i>Journal Entry Preview
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Date:</strong> <span id="previewDate"></span></div>
                    <div class="col-md-4"><strong>Type:</strong> <span id="previewType"></span></div>
                    <div class="col-md-4"><strong>Reference:</strong> <span id="previewRef"></span></div>
                </div>
                <div class="mb-3"><strong>Description:</strong> <span id="previewDesc"></span></div>
                <div class="table-responsive">
                    <table class="table table-bordered" id="previewTable">
                        <thead class="thead-dark">
                            <tr>
                                <th>Account</th>
                                <th class="text-right">Debit</th>
                                <th class="text-right">Credit</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot class="font-weight-bold">
                            <tr>
                                <td>Total</td>
                                <td class="text-right" id="previewTotalDebit">0.00</td>
                                <td class="text-right" id="previewTotalCredit">0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
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

    .debit-input, .credit-input {
        text-align: right;
    }

    .debit-input:focus {
        border-color: #007bff;
    }

    .credit-input:focus {
        border-color: #28a745;
    }

    #linesTable tbody tr:hover {
        background-color: #f8f9fa;
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
<script>
$(document).ready(function() {
    const accounts = @json($accounts);
    let lineIndex = 0;

    // Initialize with 2 lines
    addLine();
    addLine();

    // Add line button
    $('#addLineBtn').on('click', addLine);

    function addLine() {
        const $tr = $(`
            <tr data-line="${lineIndex}">
                <td>
                    <select name="lines[${lineIndex}][account_id]" class="form-control account-select" required>
                        <option value="">Select Account...</option>
                        ${accounts.map(a => `<option value="${a.id}" data-code="${a.account_code}" data-name="${a.name}" data-normal="${a.normal_balance}">${a.full_name}</option>`).join('')}
                    </select>
                </td>
                <td>
                    <input type="number" name="lines[${lineIndex}][debit_amount]" class="form-control debit-input"
                           step="0.01" min="0" value="" placeholder="0.00">
                </td>
                <td>
                    <input type="number" name="lines[${lineIndex}][credit_amount]" class="form-control credit-input"
                           step="0.01" min="0" value="" placeholder="0.00">
                </td>
                <td>
                    <input type="text" name="lines[${lineIndex}][description]" class="form-control line-desc" placeholder="Line memo">
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-line-btn">
                        <i class="mdi mdi-close"></i>
                    </button>
                </td>
            </tr>
        `);

        $('#linesBody').append($tr);

        // Initialize Select2
        $tr.find('.account-select').select2({
            theme: 'bootstrap4',
            placeholder: 'Search account...',
            allowClear: true,
            width: '100%'
        });

        lineIndex++;

        // Event handlers
        $tr.find('.debit-input').on('input', function() {
            if (parseFloat($(this).val()) > 0) {
                $tr.find('.credit-input').val('');
            }
            calculateTotals();
        });

        $tr.find('.credit-input').on('input', function() {
            if (parseFloat($(this).val()) > 0) {
                $tr.find('.debit-input').val('');
            }
            calculateTotals();
        });

        $tr.find('.remove-line-btn').on('click', function() {
            if ($('#linesBody tr').length > 2) {
                $tr.remove();
                calculateTotals();
            } else {
                toastr.warning('Minimum 2 lines required for a journal entry');
            }
        });
    }

    function calculateTotals() {
        let totalDebits = 0;
        let totalCredits = 0;

        $('.debit-input').each(function() {
            totalDebits += parseFloat($(this).val()) || 0;
        });

        $('.credit-input').each(function() {
            totalCredits += parseFloat($(this).val()) || 0;
        });

        $('#totalDebits').text('₦' + numberFormat(totalDebits));
        $('#totalCredits').text('₦' + numberFormat(totalCredits));

        const difference = Math.abs(totalDebits - totalCredits);
        $('#difference').text('₦' + numberFormat(difference));

        const $status = $('#balanceStatus');
        const $saveBtn = $('#saveBtn');
        const $saveSubmitBtn = $('#saveAndSubmitBtn');

        if (totalDebits === 0 && totalCredits === 0) {
            $status.html('<span class="text-muted">Enter amounts to continue</span>');
            $saveBtn.prop('disabled', true);
            $saveSubmitBtn.prop('disabled', true);
        } else if (difference < 0.01) {
            $status.html('<span class="badge badge-success p-2"><i class="mdi mdi-check-circle mr-1"></i> Entry is Balanced</span>');
            $saveBtn.prop('disabled', false);
            $saveSubmitBtn.prop('disabled', false);
            $('#difference').removeClass('text-danger').addClass('text-success');
        } else {
            $status.html('<span class="badge badge-danger p-2"><i class="mdi mdi-alert mr-1"></i> Entry is NOT Balanced</span>');
            $saveBtn.prop('disabled', true);
            $saveSubmitBtn.prop('disabled', true);
            $('#difference').removeClass('text-success').addClass('text-danger');
        }
    }

    function numberFormat(num) {
        return num.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }

    // Preview button
    $('#previewBtn').on('click', function() {
        $('#previewDate').text($('#entryDate').val());
        $('#previewType').text($('#entryType option:selected').text());
        $('#previewRef').text($('#reference').val() || '-');
        $('#previewDesc').text($('#description').val() || '-');

        const $tbody = $('#previewTable tbody');
        $tbody.empty();

        let totalDebit = 0;
        let totalCredit = 0;

        $('#linesBody tr').each(function() {
            const $select = $(this).find('.account-select');
            const accountText = $select.find('option:selected').text();
            const debit = parseFloat($(this).find('.debit-input').val()) || 0;
            const credit = parseFloat($(this).find('.credit-input').val()) || 0;

            if (debit > 0 || credit > 0) {
                $tbody.append(`
                    <tr>
                        <td>${accountText || '-'}</td>
                        <td class="text-right">${debit > 0 ? numberFormat(debit) : ''}</td>
                        <td class="text-right">${credit > 0 ? numberFormat(credit) : ''}</td>
                    </tr>
                `);
                totalDebit += debit;
                totalCredit += credit;
            }
        });

        $('#previewTotalDebit').text(numberFormat(totalDebit));
        $('#previewTotalCredit').text(numberFormat(totalCredit));

        $('#previewModal').modal('show');
    });

    // Form submission (AJAX)
    $('#journalEntryForm').on('submit', function(e) {
        e.preventDefault();
        saveEntry(false);
    });

    $('#saveAndSubmitBtn').on('click', function() {
        saveEntry(true);
    });

    function saveEntry(submitAfterSave) {
        // Validate
        let hasDebit = false;
        let hasCredit = false;
        let hasInvalidLine = false;

        $('#linesBody tr').each(function() {
            const accountId = $(this).find('.account-select').val();
            const debit = parseFloat($(this).find('.debit-input').val()) || 0;
            const credit = parseFloat($(this).find('.credit-input').val()) || 0;

            if (debit > 0 || credit > 0) {
                if (!accountId) {
                    hasInvalidLine = true;
                }
                if (debit > 0) hasDebit = true;
                if (credit > 0) hasCredit = true;
            }
        });

        if (!$('#description').val().trim()) {
            toastr.error('Description is required');
            return;
        }

        if (!hasDebit || !hasCredit) {
            toastr.error('Entry must have at least one debit and one credit');
            return;
        }

        if (hasInvalidLine) {
            toastr.error('All lines with amounts must have an account selected');
            return;
        }

        // Build lines data
        const lines = [];
        $('#linesBody tr').each(function() {
            const accountId = $(this).find('.account-select').val();
            const debit = parseFloat($(this).find('.debit-input').val()) || 0;
            const credit = parseFloat($(this).find('.credit-input').val()) || 0;
            const desc = $(this).find('.line-desc').val();

            if (accountId && (debit > 0 || credit > 0)) {
                lines.push({
                    account_id: accountId,
                    debit_amount: debit,
                    credit_amount: credit,
                    description: desc
                });
            }
        });

        const $btn = submitAfterSave ? $('#saveAndSubmitBtn') : $('#saveBtn');
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Saving...');

        $.ajax({
            url: '{{ route("accounting.journal-entries.store") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                entry_date: $('#entryDate').val(),
                entry_type: $('#entryType').val(),
                reference: $('#reference').val(),
                description: $('#description').val(),
                memo: $('#memo').val(),
                lines: lines,
                submit_after_save: submitAfterSave ? 1 : 0
            }
        })
        .done(function(response) {
            if (response.success) {
                toastr.success(response.message || 'Journal entry created successfully');
                setTimeout(function() {
                    window.location.href = response.redirect || '{{ route("accounting.journal-entries.index") }}';
                }, 1000);
            } else {
                toastr.error(response.message || 'Error creating journal entry');
                $btn.prop('disabled', false).html(originalText);
            }
        })
        .fail(function(xhr) {
            let message = 'Error creating journal entry';
            if (xhr.responseJSON) {
                if (xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                } else if (xhr.responseJSON.errors) {
                    const errors = Object.values(xhr.responseJSON.errors).flat();
                    message = errors.join('<br>');
                }
            }
            toastr.error(message);
            $btn.prop('disabled', false).html(originalText);
        });
    }
});
</script>
@endpush
