@extends('admin.layouts.app')

@section('title', 'Bank Configuration')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">Bank Configuration</h3>
                    <p class="text-muted mb-0">Manage bank accounts for payment tracking</p>
                </div>
                <button type="button" class="btn btn-primary" id="addBankBtn" style="border-radius: 8px; padding: 0.75rem 1.5rem;">
                    <i class="mdi mdi-plus mr-1"></i> Add Bank
                </button>
            </div>

            <!-- Banks Table Card -->
            <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                    <h5 class="mb-0" style="font-weight: 600; color: #1a1a1a;">
                        <i class="mdi mdi-bank mr-2" style="color: var(--primary-color);"></i>
                        Banks List
                    </h5>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="table-responsive">
                        <table id="banksTable" class="table table-hover" style="width: 100%;">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th style="font-weight: 600; color: #495057;">SN</th>
                                    <th style="font-weight: 600; color: #495057;">Bank Name</th>
                                    <th style="font-weight: 600; color: #495057;">Account Number</th>
                                    <th style="font-weight: 600; color: #495057;">Account Name</th>
                                    <th style="font-weight: 600; color: #495057;">Bank Code</th>
                                    <th style="font-weight: 600; color: #495057;">Status</th>
                                    <th style="font-weight: 600; color: #495057;">Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Bank Modal -->
<div class="modal fade" id="bankModal" tabindex="-1" role="dialog" aria-labelledby="bankModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" id="bankModalLabel" style="font-weight: 600; color: #1a1a1a;">
                    <i class="mdi mdi-bank mr-2" style="color: var(--primary-color);"></i>
                    <span id="modalTitleText">Add New Bank</span>
                </h5>
                <button type="button" class="close"  data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="bankForm">
                @csrf
                <input type="hidden" name="bank_id" id="bank_id">
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Bank Name *</label>
                            <input type="text" class="form-control" name="name" id="bank_name" required
                                   style="border-radius: 8px; padding: 0.75rem;" placeholder="e.g., First Bank, GTBank">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Account Number</label>
                            <input type="text" class="form-control" name="account_number" id="account_number"
                                   style="border-radius: 8px; padding: 0.75rem;" placeholder="0123456789">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Bank Code</label>
                            <input type="text" class="form-control" name="bank_code" id="bank_code"
                                   style="border-radius: 8px; padding: 0.75rem;" placeholder="011">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Account Name</label>
                            <input type="text" class="form-control" name="account_name" id="account_name"
                                   style="border-radius: 8px; padding: 0.75rem;" placeholder="Company Name Ltd">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">GL Account</label>
                            <select class="form-control" name="account_id" id="account_id" style="border-radius: 8px; padding: 0.75rem;">
                                <option value="">-- Select GL Account --</option>
                                @foreach(\App\Models\Accounting\Account::whereHas('accountGroup.accountClass', function($q) {
                                    $q->where('name', 'LIKE', '%Cash%')->orWhere('name', 'LIKE', '%Bank%');
                                })->orderBy('name')->get() as $account)
                                    <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Link this bank to a GL account for reconciliation</small>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Description</label>
                            <textarea class="form-control" name="description" id="description" rows="2"
                                      style="border-radius: 8px; padding: 0.75rem;" placeholder="Optional notes about this bank account"></textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" checked>
                                <label class="custom-control-label" for="is_active" style="font-weight: 600; color: #495057;">
                                    Active
                                </label>
                            </div>
                            <small class="text-muted">Inactive banks won't appear in payment options</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e9ecef; padding: 1rem 1.5rem;">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveBankBtn" style="border-radius: 8px;">
                        <i class="mdi mdi-check mr-1"></i> Save Bank
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toggle Status Confirmation Modal -->
<div class="modal fade" id="deleteBankModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-warning text-dark" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title">
                    <i class="mdi mdi-toggle-switch mr-2"></i>
                    Toggle Bank Status
                </h5>
                <button type="button" class="close"  data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center" style="padding: 1.5rem;">
                <p class="mb-0">Toggle active status for <strong id="deleteBankName"></strong>?</p>
                <small class="text-muted">Inactive banks won't appear in payment options</small>
                <input type="hidden" id="deleteBankId">
            </div>
            <div class="modal-footer justify-content-center" style="border-top: 1px solid #e9ecef;">
                <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirmDeleteBtn" style="border-radius: 8px;">
                    <i class="mdi mdi-toggle-switch mr-1"></i> Toggle Status
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
<script>
$(function() {
    // Initialize DataTable
    const table = $('#banksTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('banks.list') }}",
            type: 'GET'
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'name', name: 'name' },
            { data: 'account_number', name: 'account_number' },
            { data: 'account_name', name: 'account_name' },
            { data: 'bank_code', name: 'bank_code' },
            { data: 'status', name: 'status', orderable: false, searchable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[1, 'asc']],
        dom: 'Bfrtip',
        buttons: ['copy', 'excel', 'pdf', 'print'],
        language: {
            emptyTable: "No banks configured yet. Click 'Add Bank' to create one.",
            zeroRecords: "No matching banks found"
        }
    });

    // Add Bank Button
    $('#addBankBtn').on('click', function() {
        resetForm();
        $('#modalTitleText').text('Add New Bank');
        $('#bankModal').modal('show');
    });

    // Edit Bank Button
    $(document).on('click', '.edit-bank', function() {
        resetForm();
        const btn = $(this);
        $('#bank_id').val(btn.data('id'));
        $('#bank_name').val(btn.data('name'));
        $('#account_number').val(btn.data('account_number'));
        $('#account_name').val(btn.data('account_name'));
        $('#bank_code').val(btn.data('bank_code'));
        $('#description').val(btn.data('description'));
        $('#account_id').val(btn.data('account_id'));
        $('#is_active').prop('checked', btn.data('is_active') == 1);
        $('#modalTitleText').text('Edit Bank');
        $('#bankModal').modal('show');
    });

    // Toggle Bank Status Button
    $(document).on('click', '.delete-bank', function() {
        $('#deleteBankId').val($(this).data('id'));
        $('#deleteBankName').text($(this).data('name'));
        $('#deleteBankModal').modal('show');
    });

    // Confirm Toggle Status
    $('#confirmDeleteBtn').on('click', function() {
        const bankId = $('#deleteBankId').val();
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Processing...');

        $.ajax({
            url: `/banks/${bankId}`,
            type: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                $('#deleteBankModal').modal('hide');
                table.ajax.reload();
                showToast('success', response.message);
            },
            error: function(xhr) {
                const msg = xhr.responseJSON?.message || 'Failed to toggle bank status';
                showToast('error', msg);
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="mdi mdi-toggle-switch mr-1"></i> Toggle Status');
            }
        });
    });

    // Save Bank (Add/Edit)
    $('#bankForm').on('submit', function(e) {
        e.preventDefault();
        const bankId = $('#bank_id').val();
        const isEdit = bankId ? true : false;
        const url = isEdit ? `/banks/${bankId}` : '/banks';
        const method = isEdit ? 'PUT' : 'POST';
        const btn = $('#saveBankBtn');

        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Saving...');

        $.ajax({
            url: url,
            type: method,
            data: $(this).serialize(),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                $('#bankModal').modal('hide');
                table.ajax.reload();
                showToast('success', response.message);
            },
            error: function(xhr) {
                const errors = xhr.responseJSON?.errors;
                if (errors) {
                    let errorMsg = Object.values(errors).flat().join('<br>');
                    showToast('error', errorMsg);
                } else {
                    showToast('error', xhr.responseJSON?.message || 'Failed to save bank');
                }
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i> Save Bank');
            }
        });
    });

    // Reset Form
    function resetForm() {
        $('#bankForm')[0].reset();
        $('#bank_id').val('');
        $('#is_active').prop('checked', true);
    }

    // Show Toast Notification
    function showToast(type, message) {
        if (typeof toastr !== 'undefined') {
            toastr[type](message);
        } else if (typeof Swal !== 'undefined') {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: type === 'success' ? 'success' : 'error',
                title: message,
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        } else {
            alert(message);
        }
    }
});
</script>
@endsection

<style>
    #banksTable th, #banksTable td {
        vertical-align: middle;
        padding: 1rem 0.75rem;
    }

    #banksTable tbody tr {
        transition: background-color 0.15s ease;
    }

    #banksTable tbody tr:hover {
        background-color: #f8f9fa;
    }

    .badge {
        font-weight: 500;
        padding: 0.5rem 0.75rem;
        border-radius: 6px;
    }

    .badge-success {
        background-color: #28a745;
    }

    .badge-secondary {
        background-color: #6c757d;
    }

    .btn-sm {
        padding: 0.375rem 0.75rem;
        border-radius: 6px;
        margin-right: 0.25rem;
    }

    .modal-content {
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    }

    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(var(--primary-rgb), 0.15);
    }

    .custom-switch .custom-control-input:checked ~ .custom-control-label::before {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }
</style>
