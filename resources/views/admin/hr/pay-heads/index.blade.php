@extends('admin.layouts.app')

@section('title', 'Pay Heads')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                        <i class="mdi mdi-cash-multiple mr-2"></i>Pay Heads
                    </h3>
                    <p class="text-muted mb-0">Configure salary additions and deductions</p>
                </div>
                @can('pay-head.create')
                <button type="button" class="btn btn-primary" id="addPayHeadBtn" style="border-radius: 8px; padding: 0.75rem 1.5rem;">
                    <i class="mdi mdi-plus mr-1"></i> Add Pay Head
                </button>
                @endcan
            </div>

            <div class="row">
                <!-- Additions Card -->
                <div class="col-md-6">
                    <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                        <div class="card-header bg-success text-white" style="border-radius: 12px 12px 0 0;">
                            <h5 class="mb-0" style="font-weight: 600;">
                                <i class="mdi mdi-plus-circle mr-2"></i>
                                Additions (Earnings)
                            </h5>
                        </div>
                        <div class="card-body" style="padding: 1.5rem;">
                            <div class="table-responsive">
                                <table class="table table-hover" id="additionsTable" style="width: 100%;">
                                    <thead>
                                        <tr style="background: #f8f9fa;">
                                            <th style="font-weight: 600; color: #495057;">Name</th>
                                            <th style="font-weight: 600; color: #495057;">Code</th>
                                            <th style="font-weight: 600; color: #495057;">Calculation</th>
                                            <th style="font-weight: 600; color: #495057;">Status</th>
                                            <th style="font-weight: 600; color: #495057;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($additions ?? [] as $head)
                                        <tr>
                                            <td>{{ $head->name }}</td>
                                            <td><code>{{ $head->code }}</code></td>
                                            <td>
                                                @if($head->calculation_type == 'fixed')
                                                <span class="badge badge-info">Fixed</span>
                                                @elseif($head->calculation_type == 'percentage')
                                                <span class="badge badge-warning">% of {{ $head->percentage_of ?? 'Basic' }}</span>
                                                @else
                                                <span class="badge badge-secondary">Formula</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($head->is_active)
                                                <span class="badge badge-success">Active</span>
                                                @else
                                                <span class="badge badge-danger">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                @can('pay-head.edit')
                                                <button class="btn btn-sm btn-outline-primary edit-btn"
                                                        data-id="{{ $head->id }}"
                                                        data-name="{{ $head->name }}"
                                                        data-code="{{ $head->code }}"
                                                        data-type="{{ $head->type }}"
                                                        data-calculation-type="{{ $head->calculation_type }}"
                                                        data-percentage-of="{{ $head->percentage_of }}"
                                                        data-is-taxable="{{ $head->is_taxable }}"
                                                        data-is-active="{{ $head->is_active }}"
                                                        data-description="{{ $head->description }}"
                                                        title="Edit">
                                                    <i class="mdi mdi-pencil"></i>
                                                </button>
                                                @endcan
                                                @can('pay-head.delete')
                                                <button class="btn btn-sm btn-outline-danger delete-btn"
                                                        data-id="{{ $head->id }}"
                                                        data-name="{{ $head->name }}"
                                                        title="Delete">
                                                    <i class="mdi mdi-delete"></i>
                                                </button>
                                                @endcan
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">No addition pay heads configured</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Deductions Card -->
                <div class="col-md-6">
                    <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                        <div class="card-header bg-danger text-white" style="border-radius: 12px 12px 0 0;">
                            <h5 class="mb-0" style="font-weight: 600;">
                                <i class="mdi mdi-minus-circle mr-2"></i>
                                Deductions
                            </h5>
                        </div>
                        <div class="card-body" style="padding: 1.5rem;">
                            <div class="table-responsive">
                                <table class="table table-hover" id="deductionsTable" style="width: 100%;">
                                    <thead>
                                        <tr style="background: #f8f9fa;">
                                            <th style="font-weight: 600; color: #495057;">Name</th>
                                            <th style="font-weight: 600; color: #495057;">Code</th>
                                            <th style="font-weight: 600; color: #495057;">Calculation</th>
                                            <th style="font-weight: 600; color: #495057;">GL Account</th>
                                            <th style="font-weight: 600; color: #495057;">Status</th>
                                            <th style="font-weight: 600; color: #495057;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($deductions ?? [] as $head)
                                        <tr>
                                            <td>{{ $head->name }}</td>
                                            <td><code>{{ $head->code }}</code></td>
                                            <td>
                                                @if($head->calculation_type == 'fixed')
                                                <span class="badge badge-info">Fixed</span>
                                                @elseif($head->calculation_type == 'percentage')
                                                <span class="badge badge-warning">% of {{ $head->percentage_of ?? 'Basic' }}</span>
                                                @else
                                                <span class="badge badge-secondary">Formula</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($head->liabilityAccount)
                                                <small class="text-success">
                                                    <i class="mdi mdi-check-circle mr-1"></i>
                                                    {{ $head->liabilityAccount->code }} - {{ Str::limit($head->liabilityAccount->name, 20) }}
                                                </small>
                                                @else
                                                <small class="text-muted">
                                                    <i class="mdi mdi-alert-circle-outline mr-1"></i>Not linked
                                                </small>
                                                @endif
                                            </td>
                                            <td>
                                                @if($head->is_active)
                                                <span class="badge badge-success">Active</span>
                                                @else
                                                <span class="badge badge-danger">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                @can('pay-head.edit')
                                                <button class="btn btn-sm btn-outline-primary edit-btn"
                                                        data-id="{{ $head->id }}"
                                                        data-name="{{ $head->name }}"
                                                        data-code="{{ $head->code }}"
                                                        data-type="{{ $head->type }}"
                                                        data-calculation-type="{{ $head->calculation_type }}"
                                                        data-percentage-of="{{ $head->percentage_of }}"
                                                        data-liability-account-id="{{ $head->liability_account_id }}"
                                                        data-is-taxable="{{ $head->is_taxable }}"
                                                        data-is-active="{{ $head->is_active }}"
                                                        data-description="{{ $head->description }}"
                                                        title="Edit">
                                                    <i class="mdi mdi-pencil"></i>
                                                </button>
                                                @endcan
                                                @can('pay-head.delete')
                                                <button class="btn btn-sm btn-outline-danger delete-btn"
                                                        data-id="{{ $head->id }}"
                                                        data-name="{{ $head->name }}"
                                                        title="Delete">
                                                    <i class="mdi mdi-delete"></i>
                                                </button>
                                                @endcan
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">No deduction pay heads configured</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Pay Head Modal -->
<div class="modal fade" id="payHeadModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" style="font-weight: 600; color: #1a1a1a;">
                    <i class="mdi mdi-cash mr-2" style="color: var(--primary-color);"></i>
                    <span id="modalTitleText">Add Pay Head</span>
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="payHeadForm">
                @csrf
                <input type="hidden" name="pay_head_id" id="pay_head_id">
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Name *</label>
                        <input type="text" class="form-control" name="name" id="name" required
                               style="border-radius: 8px; padding: 0.75rem;" placeholder="e.g., Basic Salary">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Code *</label>
                        <input type="text" class="form-control" name="code" id="code" required
                               style="border-radius: 8px; padding: 0.75rem;" placeholder="e.g., BASIC" maxlength="20">
                        <small class="text-muted">Unique identifier for calculations</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Type *</label>
                        <select class="form-control" name="type" id="type" required style="border-radius: 8px;">
                            <option value="addition">Addition (Earning)</option>
                            <option value="deduction">Deduction</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Calculation Type *</label>
                        <select class="form-control" name="calculation_type" id="calculation_type" required style="border-radius: 8px;">
                            <option value="fixed">Fixed Amount</option>
                            <option value="percentage">Percentage of Base</option>
                            <option value="formula">Custom Formula</option>
                        </select>
                    </div>
                    <div class="form-group" id="percentageOfGroup" style="display: none;">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Percentage Of</label>
                        <select class="form-control" name="percentage_of" id="percentage_of" style="border-radius: 8px;">
                            <option value="basic">Basic Salary</option>
                            <option value="gross">Gross Salary</option>
                        </select>
                    </div>
                    <div class="form-group" id="liabilityAccountGroup" style="display: none;">
                        <label class="form-label" style="font-weight: 600; color: #495057;">
                            <i class="mdi mdi-bank-transfer mr-1 text-primary"></i>
                            GL Liability Account
                        </label>
                        <select class="form-control" name="liability_account_id" id="liability_account_id" style="border-radius: 8px;">
                            <option value="">-- Select Liability Account --</option>
                            @foreach($liabilityAccounts ?? [] as $account)
                            <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">
                            <i class="mdi mdi-information-outline mr-1"></i>
                            Link this deduction to a GL account for accurate payroll journal entries.
                            When payroll is processed, deductions with linked accounts will be credited to their respective liability accounts.
                        </small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Description</label>
                        <textarea class="form-control" name="description" id="description" rows="2"
                                  style="border-radius: 8px; padding: 0.75rem;" placeholder="Brief description"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="custom-control custom-switch mb-3">
                                <input type="checkbox" class="custom-control-input" id="is_taxable" name="is_taxable">
                                <label class="custom-control-label" for="is_taxable" style="font-weight: 600; color: #495057;">
                                    Taxable
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="custom-control custom-switch mb-3">
                                <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" checked>
                                <label class="custom-control-label" for="is_active" style="font-weight: 600; color: #495057;">
                                    Active
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e9ecef; padding: 1rem 1.5rem;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="savePayHeadBtn" style="border-radius: 8px;">
                        <i class="mdi mdi-check mr-1"></i> Save Pay Head
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-danger text-white" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title">
                    <i class="mdi mdi-delete mr-2"></i>
                    Delete Pay Head
                </h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center" style="padding: 1.5rem;">
                <p class="mb-0">Are you sure you want to delete <strong id="deleteItemName"></strong>?</p>
                <small class="text-muted">This may affect salary profiles using this pay head</small>
                <input type="hidden" id="deleteItemId">
            </div>
            <div class="modal-footer justify-content-center" style="border-top: 1px solid #e9ecef;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn" style="border-radius: 8px;">
                    <i class="mdi mdi-delete mr-1"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function() {
    // Function to toggle liability account field based on type
    function toggleLiabilityAccountField() {
        if ($('#type').val() === 'deduction') {
            $('#liabilityAccountGroup').show();
        } else {
            $('#liabilityAccountGroup').hide();
            $('#liability_account_id').val(''); // Clear selection for additions
        }
    }

    // Calculation type change
    $('#calculation_type').change(function() {
        if ($(this).val() === 'percentage') {
            $('#percentageOfGroup').show();
        } else {
            $('#percentageOfGroup').hide();
        }
    });

    // Type change - toggle liability account field
    $('#type').change(function() {
        toggleLiabilityAccountField();
    });

    // Add button click
    $('#addPayHeadBtn').click(function() {
        $('#payHeadForm')[0].reset();
        $('#pay_head_id').val('');
        $('#percentageOfGroup').hide();
        $('#liabilityAccountGroup').hide();
        $('#is_active').prop('checked', true);
        $('#modalTitleText').text('Add Pay Head');
        $('#payHeadModal').modal('show');
    });

    // Edit button click
    $(document).on('click', '.edit-btn', function() {
        const data = $(this).data();
        $('#pay_head_id').val(data.id);
        $('#name').val(data.name);
        $('#code').val(data.code);
        $('#type').val(data.type);
        $('#calculation_type').val(data.calculationType).trigger('change');
        $('#percentage_of').val(data.percentageOf);
        $('#liability_account_id').val(data.liabilityAccountId || '');
        $('#description').val(data.description);
        $('#is_taxable').prop('checked', data.isTaxable == 1);
        $('#is_active').prop('checked', data.isActive == 1);
        $('#modalTitleText').text('Edit Pay Head');
        toggleLiabilityAccountField();
        $('#payHeadModal').modal('show');
    });

    // Form submission
    $('#payHeadForm').submit(function(e) {
        e.preventDefault();
        const id = $('#pay_head_id').val();
        const url = id ? "{{ route('hr.pay-heads.index') }}/" + id : "{{ route('hr.pay-heads.store') }}";
        const method = id ? 'PUT' : 'POST';

        const formData = {
            _token: '{{ csrf_token() }}',
            name: $('#name').val(),
            code: $('#code').val(),
            type: $('#type').val(),
            calculation_type: $('#calculation_type').val(),
            percentage_of: $('#percentage_of').val(),
            liability_account_id: $('#type').val() === 'deduction' ? $('#liability_account_id').val() : null,
            description: $('#description').val(),
            is_taxable: $('#is_taxable').is(':checked') ? 1 : 0,
            is_active: $('#is_active').is(':checked') ? 1 : 0
        };

        $('#savePayHeadBtn').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Saving...');

        $.ajax({
            url: url,
            method: method,
            data: formData,
            success: function(response) {
                $('#payHeadModal').modal('hide');
                toastr.success(response.message || 'Pay head saved successfully');
                location.reload();
            },
            error: function(xhr) {
                let message = 'An error occurred';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    message = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                }
                toastr.error(message);
            },
            complete: function() {
                $('#savePayHeadBtn').prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i> Save Pay Head');
            }
        });
    });

    // Delete button click
    $(document).on('click', '.delete-btn', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        $('#deleteItemId').val(id);
        $('#deleteItemName').text(name);
        $('#deleteModal').modal('show');
    });

    // Confirm delete
    $('#confirmDeleteBtn').click(function() {
        const id = $('#deleteItemId').val();

        $.ajax({
            url: "{{ route('hr.pay-heads.index') }}/" + id,
            method: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                $('#deleteModal').modal('hide');
                toastr.success(response.message || 'Pay head deleted successfully');
                location.reload();
            },
            error: function(xhr) {
                let message = 'Failed to delete pay head';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                toastr.error(message);
            }
        });
    });
});
</script>
@endsection
