@extends('admin.layouts.app')

@section('title', 'Salary Profiles')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                        <i class="mdi mdi-account-cash mr-2"></i>Salary Profiles
                    </h3>
                    <p class="text-muted mb-0">Configure salary profiles for staff members</p>
                </div>
                @can('salary-profile.create')
                <button type="button" class="btn btn-primary" id="addProfileBtn" style="border-radius: 8px; padding: 0.75rem 1.5rem;">
                    <i class="mdi mdi-plus mr-1"></i> New Salary Profile
                </button>
                @endcan
            </div>

            <!-- Salary Profiles Table Card -->
            <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                    <h5 class="mb-0" style="font-weight: 600; color: #1a1a1a;">
                        <i class="mdi mdi-format-list-bulleted mr-2" style="color: var(--primary-color);"></i>
                        Staff Salary Profiles
                    </h5>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="table-responsive">
                        <table id="salaryProfilesTable" class="table table-hover" style="width: 100%;">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th style="font-weight: 600; color: #495057;">SN</th>
                                    <th style="font-weight: 600; color: #495057;">Staff</th>
                                    <th style="font-weight: 600; color: #495057;">Employee ID</th>
                                    <th style="font-weight: 600; color: #495057;">Basic Salary</th>
                                    <th style="font-weight: 600; color: #495057;">Gross Salary</th>
                                    <th style="font-weight: 600; color: #495057;">Total Deductions</th>
                                    <th style="font-weight: 600; color: #495057;">Net Salary</th>
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

<!-- Create/Edit Salary Profile Modal -->
<div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" style="font-weight: 600; color: #1a1a1a;">
                    <i class="mdi mdi-account-cash mr-2" style="color: var(--primary-color);"></i>
                    <span id="modalTitleText">New Salary Profile</span>
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="profileForm">
                @csrf
                <input type="hidden" name="profile_id" id="profile_id">
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Staff Member *</label>
                            <select class="form-control select2" name="staff_id" id="staff_id" required style="width: 100%;">
                                <option value="">Select Staff</option>
                                @foreach($staffList ?? [] as $staff)
                                <option value="{{ $staff->id }}">{{ $staff->user->firstname ?? '' }} {{ $staff->user->surname ?? '' }} ({{ $staff->employee_id ?? 'N/A' }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Basic Salary *</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text" style="border-radius: 8px 0 0 8px;">₦</span>
                                </div>
                                <input type="number" class="form-control" name="basic_salary" id="basic_salary" required
                                       step="0.01" min="0" style="border-radius: 0 8px 8px 0; padding: 0.75rem;">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Effective Date *</label>
                            <input type="date" class="form-control" name="effective_date" id="effective_date" required
                                   style="border-radius: 8px; padding: 0.75rem;" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Currency</label>
                            <input type="text" class="form-control" name="currency" id="currency" value="NGN"
                                   style="border-radius: 8px; padding: 0.75rem;" readonly>
                        </div>
                    </div>

                    <hr class="my-3">
                    <h6 class="mb-3" style="font-weight: 600; color: #1a1a1a;">
                        <i class="mdi mdi-plus-circle mr-1 text-success"></i> Additions
                    </h6>

                    <div id="additionsContainer">
                        <!-- Dynamic additions will be added here -->
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-success" id="addAdditionBtn">
                        <i class="mdi mdi-plus"></i> Add Earning
                    </button>

                    <hr class="my-3">
                    <h6 class="mb-3" style="font-weight: 600; color: #1a1a1a;">
                        <i class="mdi mdi-minus-circle mr-1 text-danger"></i> Deductions
                    </h6>

                    <div id="deductionsContainer">
                        <!-- Dynamic deductions will be added here -->
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="addDeductionBtn">
                        <i class="mdi mdi-plus"></i> Add Deduction
                    </button>

                    <hr class="my-3">
                    <!-- Salary Summary -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-light rounded">
                                <small class="text-muted d-block">Basic Salary</small>
                                <h5 class="mb-0" id="summaryBasic">₦0.00</h5>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-success text-white rounded">
                                <small class="d-block">Gross Salary</small>
                                <h5 class="mb-0" id="summaryGross">₦0.00</h5>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-danger text-white rounded">
                                <small class="d-block">Deductions</small>
                                <h5 class="mb-0" id="summaryDeductions">₦0.00</h5>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-primary text-white rounded">
                                <small class="d-block">Net Salary</small>
                                <h5 class="mb-0" id="summaryNet">₦0.00</h5>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e9ecef; padding: 1rem 1.5rem;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveProfileBtn" style="border-radius: 8px;">
                        <i class="mdi mdi-check mr-1"></i> Save Profile
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Salary Profile Modal -->
<div class="modal fade" id="viewProfileModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" style="font-weight: 600; color: #1a1a1a;">
                    <i class="mdi mdi-eye mr-2" style="color: var(--primary-color);"></i>
                    Salary Profile Details
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 1.5rem;">
                <div id="profileDetails"></div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #e9ecef;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
<script>
$(function() {
    // Pay heads data
    const payHeads = @json($payHeads ?? []);
    const additions = payHeads.filter(h => h.type === 'addition');
    const deductions = payHeads.filter(h => h.type === 'deduction');

    // Initialize Select2
    $('.select2').select2({
        dropdownParent: $('#profileModal'),
        placeholder: 'Select Staff',
        allowClear: true
    });

    // Initialize DataTable
    const table = $('#salaryProfilesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('hr.salary-profiles.index') }}",
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'staff_name', name: 'staff.user.firstname' },
            { data: 'employee_id', name: 'staff.employee_id' },
            { data: 'basic_salary_formatted', name: 'basic_salary' },
            { data: 'gross_salary_formatted', name: 'gross_salary', orderable: false },
            { data: 'total_deductions_formatted', name: 'total_deductions', orderable: false },
            { data: 'net_salary_formatted', name: 'net_salary', orderable: false },
            { data: 'is_current', name: 'is_current',
              render: function(data) {
                  return data ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>';
              }
            },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[1, 'asc']],
        language: {
            emptyTable: "No salary profiles configured",
            processing: '<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>'
        }
    });

    // Generate addition row
    function additionRow(payHead = null, amount = '') {
        const options = additions.map(h =>
            `<option value="${h.id}" ${payHead && h.id == payHead ? 'selected' : ''}>${h.name} (${h.code})</option>`
        ).join('');

        return `
            <div class="row mb-2 addition-row">
                <div class="col-md-5">
                    <select class="form-control pay-head-select" name="addition_pay_head_id[]" style="border-radius: 8px;">
                        <option value="">Select Pay Head</option>
                        ${options}
                    </select>
                </div>
                <div class="col-md-5">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text" style="border-radius: 8px 0 0 8px;">₦</span>
                        </div>
                        <input type="number" class="form-control item-amount" name="addition_amount[]"
                               step="0.01" min="0" value="${amount}" style="border-radius: 0 8px 8px 0;">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-danger remove-row-btn" style="border-radius: 8px;">
                        <i class="mdi mdi-close"></i>
                    </button>
                </div>
            </div>
        `;
    }

    // Generate deduction row
    function deductionRow(payHead = null, amount = '') {
        const options = deductions.map(h =>
            `<option value="${h.id}" ${payHead && h.id == payHead ? 'selected' : ''}>${h.name} (${h.code})</option>`
        ).join('');

        return `
            <div class="row mb-2 deduction-row">
                <div class="col-md-5">
                    <select class="form-control pay-head-select" name="deduction_pay_head_id[]" style="border-radius: 8px;">
                        <option value="">Select Pay Head</option>
                        ${options}
                    </select>
                </div>
                <div class="col-md-5">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text" style="border-radius: 8px 0 0 8px;">₦</span>
                        </div>
                        <input type="number" class="form-control item-amount" name="deduction_amount[]"
                               step="0.01" min="0" value="${amount}" style="border-radius: 0 8px 8px 0;">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-danger remove-row-btn" style="border-radius: 8px;">
                        <i class="mdi mdi-close"></i>
                    </button>
                </div>
            </div>
        `;
    }

    // Calculate summary
    function calculateSummary() {
        const basic = parseFloat($('#basic_salary').val()) || 0;
        let grossAdditions = basic;
        let totalDeductions = 0;

        $('.addition-row').each(function() {
            const amount = parseFloat($(this).find('.item-amount').val()) || 0;
            grossAdditions += amount;
        });

        $('.deduction-row').each(function() {
            const amount = parseFloat($(this).find('.item-amount').val()) || 0;
            totalDeductions += amount;
        });

        const net = grossAdditions - totalDeductions;

        $('#summaryBasic').text('₦' + basic.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#summaryGross').text('₦' + grossAdditions.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#summaryDeductions').text('₦' + totalDeductions.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#summaryNet').text('₦' + net.toLocaleString('en-NG', {minimumFractionDigits: 2}));
    }

    // Add addition row
    $('#addAdditionBtn').click(function() {
        $('#additionsContainer').append(additionRow());
    });

    // Add deduction row
    $('#addDeductionBtn').click(function() {
        $('#deductionsContainer').append(deductionRow());
    });

    // Remove row
    $(document).on('click', '.remove-row-btn', function() {
        $(this).closest('.row').remove();
        calculateSummary();
    });

    // Calculate on amount change
    $(document).on('change keyup', '#basic_salary, .item-amount', function() {
        calculateSummary();
    });

    // Add profile button
    $('#addProfileBtn').click(function() {
        $('#profileForm')[0].reset();
        $('#profile_id').val('');
        $('#staff_id').val('').trigger('change');
        $('#additionsContainer').html('');
        $('#deductionsContainer').html('');
        calculateSummary();
        $('#modalTitleText').text('New Salary Profile');
        $('#profileModal').modal('show');
    });

    // Edit profile
    $(document).on('click', '.edit-btn', function() {
        const id = $(this).data('id');

        $.get("{{ route('hr.salary-profiles.index') }}/" + id + "/edit", function(data) {
            $('#profile_id').val(data.id);
            $('#staff_id').val(data.staff_id).trigger('change');
            $('#basic_salary').val(data.basic_salary);
            $('#effective_date').val(data.effective_date);

            // Load additions
            $('#additionsContainer').html('');
            if (data.items) {
                data.items.filter(i => i.pay_head && i.pay_head.type === 'addition').forEach(item => {
                    $('#additionsContainer').append(additionRow(item.pay_head_id, item.amount));
                });
            }

            // Load deductions
            $('#deductionsContainer').html('');
            if (data.items) {
                data.items.filter(i => i.pay_head && i.pay_head.type === 'deduction').forEach(item => {
                    $('#deductionsContainer').append(deductionRow(item.pay_head_id, item.amount));
                });
            }

            calculateSummary();
            $('#modalTitleText').text('Edit Salary Profile');
            $('#profileModal').modal('show');
        });
    });

    // View profile
    $(document).on('click', '.view-btn', function() {
        const id = $(this).data('id');

        $.get("{{ route('hr.salary-profiles.index') }}/" + id, function(data) {
            let html = `
                <div class="row mb-4">
                    <div class="col-md-6">
                        <p><strong>Staff:</strong> ${data.staff_name}</p>
                        <p><strong>Employee ID:</strong> ${data.employee_id || '-'}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Effective Date:</strong> ${data.effective_date}</p>
                        <p><strong>Status:</strong> ${data.is_current ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>'}</p>
                    </div>
                </div>

                <h6 class="font-weight-bold text-success"><i class="mdi mdi-plus-circle mr-1"></i> Earnings</h6>
                <table class="table table-sm table-bordered mb-4">
                    <thead class="bg-light">
                        <tr>
                            <th>Pay Head</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Basic Salary</td>
                            <td class="text-right">₦${parseFloat(data.basic_salary).toLocaleString('en-NG', {minimumFractionDigits: 2})}</td>
                        </tr>
            `;

            if (data.items) {
                data.items.filter(i => i.pay_head && i.pay_head.type === 'addition').forEach(item => {
                    html += `
                        <tr>
                            <td>${item.pay_head.name}</td>
                            <td class="text-right">₦${parseFloat(item.amount).toLocaleString('en-NG', {minimumFractionDigits: 2})}</td>
                        </tr>
                    `;
                });
            }

            html += `
                        <tr class="table-success font-weight-bold">
                            <td>Gross Salary</td>
                            <td class="text-right">₦${parseFloat(data.gross_salary || 0).toLocaleString('en-NG', {minimumFractionDigits: 2})}</td>
                        </tr>
                    </tbody>
                </table>

                <h6 class="font-weight-bold text-danger"><i class="mdi mdi-minus-circle mr-1"></i> Deductions</h6>
                <table class="table table-sm table-bordered mb-4">
                    <thead class="bg-light">
                        <tr>
                            <th>Pay Head</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            if (data.items) {
                data.items.filter(i => i.pay_head && i.pay_head.type === 'deduction').forEach(item => {
                    html += `
                        <tr>
                            <td>${item.pay_head.name}</td>
                            <td class="text-right">₦${parseFloat(item.amount).toLocaleString('en-NG', {minimumFractionDigits: 2})}</td>
                        </tr>
                    `;
                });
            }

            html += `
                        <tr class="table-danger font-weight-bold">
                            <td>Total Deductions</td>
                            <td class="text-right">₦${parseFloat(data.total_deductions || 0).toLocaleString('en-NG', {minimumFractionDigits: 2})}</td>
                        </tr>
                    </tbody>
                </table>

                <div class="alert alert-primary text-center" style="border-radius: 8px;">
                    <h5 class="mb-0">Net Salary: <strong>₦${parseFloat(data.net_salary || 0).toLocaleString('en-NG', {minimumFractionDigits: 2})}</strong></h5>
                </div>
            `;

            $('#profileDetails').html(html);
            $('#viewProfileModal').modal('show');
        });
    });

    // Submit profile
    $('#profileForm').submit(function(e) {
        e.preventDefault();

        const id = $('#profile_id').val();
        const url = id ? "{{ route('hr.salary-profiles.index') }}/" + id : "{{ route('hr.salary-profiles.store') }}";

        // Collect items
        const items = [];

        $('.addition-row').each(function() {
            const payHeadId = $(this).find('select[name="addition_pay_head_id[]"]').val();
            const amount = $(this).find('input[name="addition_amount[]"]').val();
            if (payHeadId && amount) {
                items.push({ pay_head_id: payHeadId, amount: amount });
            }
        });

        $('.deduction-row').each(function() {
            const payHeadId = $(this).find('select[name="deduction_pay_head_id[]"]').val();
            const amount = $(this).find('input[name="deduction_amount[]"]').val();
            if (payHeadId && amount) {
                items.push({ pay_head_id: payHeadId, amount: amount });
            }
        });

        $('#saveProfileBtn').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Saving...');

        $.ajax({
            url: url,
            method: id ? 'PUT' : 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                staff_id: $('#staff_id').val(),
                basic_salary: $('#basic_salary').val(),
                effective_date: $('#effective_date').val(),
                items: items
            },
            success: function(response) {
                $('#profileModal').modal('hide');
                table.ajax.reload();
                toastr.success(response.message || 'Salary profile saved successfully');
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
                $('#saveProfileBtn').prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i> Save Profile');
            }
        });
    });
});
</script>
@endsection
