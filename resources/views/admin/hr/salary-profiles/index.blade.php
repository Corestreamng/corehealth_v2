@extends('admin.layouts.app')

@section('title', 'Salary Profiles')

@section('style')
<style>
    /* Fix Select2 z-index issue in modals */
    .select2-container--open {
        z-index: 9999 !important;
    }
    .select2-dropdown {
        z-index: 9999 !important;
    }
</style>
@endsection

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

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card border-0" style="border-radius: 10px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <div class="card-body text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Active Profiles</h6>
                                    <h3 class="mb-0" id="activeProfilesCount">{{ $stats['active_profiles'] ?? 0 }}</h3>
                                    <small class="text-white-50">of {{ $stats['total_active_staff'] ?? 0 }} staff</small>
                                </div>
                                <i class="mdi mdi-account-check" style="font-size: 2.5rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0" style="border-radius: 10px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <div class="card-body text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Without Profiles</h6>
                                    <h3 class="mb-0" id="staffWithoutProfilesCount">{{ $stats['staff_without_profiles'] ?? 0 }}</h3>
                                    <small class="text-white-50">staff need setup</small>
                                </div>
                                <i class="mdi mdi-account-alert" style="font-size: 2.5rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0" style="border-radius: 10px; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                        <div class="card-body text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Total Net Payroll</h6>
                                    <h4 class="mb-0" id="totalNetPayroll">{{ $stats['total_net_formatted'] ?? '₦0.00' }}</h4>
                                    <small class="text-white-50">monthly commitment</small>
                                </div>
                                <i class="mdi mdi-cash-multiple" style="font-size: 2.5rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0" style="border-radius: 10px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <div class="card-body text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Average Net Salary</h6>
                                    <h4 class="mb-0" id="avgNetSalary">{{ $stats['avg_net_formatted'] ?? '₦0.00' }}</h4>
                                    <small class="text-white-50">per employee</small>
                                </div>
                                <i class="mdi mdi-chart-line" style="font-size: 2.5rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Bar -->
            <div class="card border-0 mb-4" style="border-radius: 10px; background: #fff;">
                <div class="card-body py-3">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <small class="text-muted d-block">Total Basic</small>
                            <strong class="text-dark" id="totalBasic">{{ $stats['total_basic_formatted'] ?? '₦0.00' }}</strong>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Total Gross</small>
                            <strong class="text-success" id="totalGross">{{ $stats['total_gross_formatted'] ?? '₦0.00' }}</strong>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Total Deductions</small>
                            <strong class="text-danger" id="totalDeductions">{{ $stats['total_deductions_formatted'] ?? '₦0.00' }}</strong>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Total Net</small>
                            <strong class="text-primary" id="totalNet">{{ $stats['total_net_formatted'] ?? '₦0.00' }}</strong>
                        </div>
                    </div>
                </div>
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
                            <div class="text-center p-3 bg-success text-white rounded" style="cursor: help;" data-toggle="tooltip" data-placement="top" title="">
                                <small class="d-block">Gross Salary</small>
                                <h5 class="mb-0" id="summaryGross">₦0.00</h5>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-danger text-white rounded" style="cursor: help;" data-toggle="tooltip" data-placement="top" title="">
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
                    
                    <!-- Calculation Breakdown (collapsible) -->
                    <div class="mt-3">
                        <a class="text-muted small" data-toggle="collapse" href="#calcBreakdown" role="button" aria-expanded="false" aria-controls="calcBreakdown">
                            <i class="mdi mdi-information-outline mr-1"></i> View Calculation Breakdown
                        </a>
                        <div class="collapse mt-2" id="calcBreakdown">
                            <div class="alert alert-light border small mb-0" style="font-size: 12px;">
                                <strong>How Calculations Work:</strong>
                                <ul class="mb-0 mt-2 pl-3">
                                    <li><strong>Fixed/Basic additions</strong> are calculated first</li>
                                    <li><strong>Intermediate Gross</strong> = Basic + Fixed/Basic Additions = <span id="breakdownIntermediateGross">₦0.00</span></li>
                                    <li><strong>Gross-based additions</strong> use Intermediate Gross as base</li>
                                    <li><strong>Final Gross</strong> = Intermediate + Gross-based Additions = <span id="breakdownFinalGross">₦0.00</span></li>
                                    <li><strong>Deductions based on Gross</strong> use Final Gross as base</li>
                                </ul>
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
    // Pay heads data with full configuration
    const payHeads = @json($payHeads ?? []);
    const additions = payHeads.filter(h => h.type === 'addition');
    const deductions = payHeads.filter(h => h.type === 'deduction');

    // Initialize Select2 only when modal is shown (to avoid "not a function" error)
    let select2Initialized = false;
    $('#profileModal').on('shown.bs.modal', function() {
        if (!select2Initialized && typeof $.fn.select2 !== 'undefined') {
            $('#staff_id').select2({
                dropdownParent: $('#profileModal'),
                placeholder: 'Select Staff',
                allowClear: true,
                width: '100%'
            });
            select2Initialized = true;
        }
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

    // Load and refresh stats
    function loadStats() {
        $.get("{{ route('hr.salary-profiles.index') }}", { stats: true }, function(data) {
            $('#activeProfilesCount').text(data.active_profiles || 0);
            $('#staffWithoutProfilesCount').text(data.staff_without_profiles || 0);
            $('#totalNetPayroll').text(data.total_net_formatted || '₦0.00');
            $('#avgNetSalary').text(data.avg_net_formatted || '₦0.00');
            $('#totalBasic').text(data.total_basic_formatted || '₦0.00');
            $('#totalGross').text(data.total_gross_formatted || '₦0.00');
            $('#totalDeductions').text(data.total_deductions_formatted || '₦0.00');
            $('#totalNet').text(data.total_net_formatted || '₦0.00');
        });
    }

    // Get pay head by ID
    function getPayHead(id) {
        return payHeads.find(h => h.id == id);
    }

    // Generate addition row with full calculation support
    function additionRow(payHead = null, calcType = 'fixed', calcBase = 'basic', value = '') {
        const options = additions.map(h => {
            const calcInfo = h.calculation_type === 'percentage' ? ` [${h.calculation_type} of ${h.percentage_of || 'basic'}]` : ` [${h.calculation_type}]`;
            return `<option value="${h.id}" 
                data-calc-type="${h.calculation_type}" 
                data-calc-base="${h.percentage_of || 'basic'}"
                ${payHead && h.id == payHead ? 'selected' : ''}>${h.name} (${h.code})${calcInfo}</option>`;
        }).join('');

        const isPercentage = calcType === 'percentage';
        const showBaseSelect = isPercentage ? 'display:block' : 'display:none';

        return `
            <div class="row mb-2 addition-row align-items-center">
                <div class="col-md-4">
                    <select class="form-control pay-head-select" name="addition_pay_head_id[]" style="border-radius: 8px;">
                        <option value="">Select Pay Head</option>
                        ${options}
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-control calc-type-select" name="addition_calc_type[]" style="border-radius: 8px;">
                        <option value="fixed" ${calcType === 'fixed' ? 'selected' : ''}>Fixed</option>
                        <option value="percentage" ${calcType === 'percentage' ? 'selected' : ''}>Percentage</option>
                    </select>
                </div>
                <div class="col-md-2 calc-base-container" style="${showBaseSelect}">
                    <select class="form-control calc-base-select" name="addition_calc_base[]" style="border-radius: 8px;">
                        <option value="basic" ${calcBase === 'basic' || calcBase === 'basic_salary' ? 'selected' : ''}>Basic</option>
                        <option value="gross" ${calcBase === 'gross' || calcBase === 'gross_salary' ? 'selected' : ''}>Gross</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text value-prefix" style="border-radius: 8px 0 0 8px;">${isPercentage ? '%' : '₦'}</span>
                        </div>
                        <input type="number" class="form-control item-value" name="addition_value[]"
                               step="${isPercentage ? '0.01' : '0.01'}" min="0" value="${value}" 
                               placeholder="${isPercentage ? 'e.g. 10' : 'Amount'}"
                               style="border-radius: 0 8px 8px 0;">
                    </div>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-outline-danger btn-sm remove-row-btn" style="border-radius: 8px;">
                        <i class="mdi mdi-close"></i>
                    </button>
                </div>
            </div>
        `;
    }

    // Generate deduction row with full calculation support
    function deductionRow(payHead = null, calcType = 'fixed', calcBase = 'basic', value = '') {
        const options = deductions.map(h => {
            const calcInfo = h.calculation_type === 'percentage' ? ` [${h.calculation_type} of ${h.percentage_of || 'basic'}]` : ` [${h.calculation_type}]`;
            return `<option value="${h.id}" 
                data-calc-type="${h.calculation_type}" 
                data-calc-base="${h.percentage_of || 'basic'}"
                ${payHead && h.id == payHead ? 'selected' : ''}>${h.name} (${h.code})${calcInfo}</option>`;
        }).join('');

        const isPercentage = calcType === 'percentage';
        const showBaseSelect = isPercentage ? 'display:block' : 'display:none';

        return `
            <div class="row mb-2 deduction-row align-items-center">
                <div class="col-md-4">
                    <select class="form-control pay-head-select" name="deduction_pay_head_id[]" style="border-radius: 8px;">
                        <option value="">Select Pay Head</option>
                        ${options}
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-control calc-type-select" name="deduction_calc_type[]" style="border-radius: 8px;">
                        <option value="fixed" ${calcType === 'fixed' ? 'selected' : ''}>Fixed</option>
                        <option value="percentage" ${calcType === 'percentage' ? 'selected' : ''}>Percentage</option>
                    </select>
                </div>
                <div class="col-md-2 calc-base-container" style="${showBaseSelect}">
                    <select class="form-control calc-base-select" name="deduction_calc_base[]" style="border-radius: 8px;">
                        <option value="basic" ${calcBase === 'basic' || calcBase === 'basic_salary' ? 'selected' : ''}>Basic</option>
                        <option value="gross" ${calcBase === 'gross' || calcBase === 'gross_salary' ? 'selected' : ''}>Gross</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text value-prefix" style="border-radius: 8px 0 0 8px;">${isPercentage ? '%' : '₦'}</span>
                        </div>
                        <input type="number" class="form-control item-value" name="deduction_value[]"
                               step="${isPercentage ? '0.01' : '0.01'}" min="0" value="${value}" 
                               placeholder="${isPercentage ? 'e.g. 7.5' : 'Amount'}"
                               style="border-radius: 0 8px 8px 0;">
                    </div>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-outline-danger btn-sm remove-row-btn" style="border-radius: 8px;">
                        <i class="mdi mdi-close"></i>
                    </button>
                </div>
            </div>
        `;
    }

    // Handle pay head selection - auto-set calculation type from pay head config
    $(document).on('change', '.pay-head-select', function() {
        const row = $(this).closest('.row');
        const selectedOption = $(this).find('option:selected');
        const calcType = selectedOption.data('calc-type') || 'fixed';
        const calcBase = selectedOption.data('calc-base') || 'basic';
        const selectedId = $(this).val();

        // Check for duplicate pay head selection
        if (selectedId) {
            const isDuplicate = checkDuplicatePayHead(selectedId, this);
            if (isDuplicate) {
                const payHeadName = selectedOption.text().split(' (')[0];
                toastr.warning(`"${payHeadName}" is already added to this profile. Each pay head can only be used once.`, 'Duplicate Pay Head');
                $(this).val('').trigger('change');
                return;
            }
        }

        row.find('.calc-type-select').val(calcType).trigger('change');
        row.find('.calc-base-select').val(calcBase);
    });

    // Check for duplicate pay head across all rows
    function checkDuplicatePayHead(payHeadId, currentSelect) {
        let count = 0;
        $('.pay-head-select').each(function() {
            if ($(this).val() == payHeadId) {
                count++;
            }
        });
        // If count > 1, there's a duplicate (current selection + another)
        return count > 1;
    }

    // Validate no duplicates before form submission
    function validateNoDuplicatePayHeads() {
        const payHeadIds = [];
        const duplicates = [];
        
        $('.pay-head-select').each(function() {
            const id = $(this).val();
            if (id) {
                if (payHeadIds.includes(id)) {
                    const name = $(this).find('option:selected').text().split(' (')[0];
                    if (!duplicates.includes(name)) {
                        duplicates.push(name);
                    }
                } else {
                    payHeadIds.push(id);
                }
            }
        });

        if (duplicates.length > 0) {
            toastr.error(`Duplicate pay heads detected: ${duplicates.join(', ')}. Please remove duplicates before saving.`, 'Validation Error');
            return false;
        }
        return true;
    }

    // Handle calculation type change - show/hide base selector and change prefix
    $(document).on('change', '.calc-type-select', function() {
        const row = $(this).closest('.row');
        const isPercentage = $(this).val() === 'percentage';

        if (isPercentage) {
            row.find('.calc-base-container').show();
            row.find('.value-prefix').text('%');
            row.find('.item-value').attr('placeholder', 'e.g. 10');
        } else {
            row.find('.calc-base-container').hide();
            row.find('.value-prefix').text('₦');
            row.find('.item-value').attr('placeholder', 'Amount');
        }
        calculateSummary();
    });

    // Calculate summary with proper two-pass calculation for gross-based items
    function calculateSummary() {
        const basic = parseFloat($('#basic_salary').val()) || 0;
        
        // ===== ADDITIONS CALCULATION =====
        // Pass 1: Calculate FIXED and BASIC-based additions first
        let fixedAndBasicAdditions = 0;
        let grossBasedAdditions = [];

        $('.addition-row').each(function() {
            const calcType = $(this).find('.calc-type-select').val();
            const calcBase = $(this).find('.calc-base-select').val();
            const value = parseFloat($(this).find('.item-value').val()) || 0;

            if (calcType === 'percentage') {
                if (calcBase === 'basic' || calcBase === 'basic_salary') {
                    // Percentage of basic - calculate now
                    fixedAndBasicAdditions += (basic * value / 100);
                } else {
                    // Percentage of gross - save for later
                    grossBasedAdditions.push(value);
                }
            } else {
                // Fixed amount
                fixedAndBasicAdditions += value;
            }
        });

        // Pass 2: Calculate GROSS-based additions using intermediate gross
        let intermediateGross = basic + fixedAndBasicAdditions;
        let grossAdditionsTotal = 0;
        grossBasedAdditions.forEach(percentage => {
            grossAdditionsTotal += (intermediateGross * percentage / 100);
        });

        // Final gross salary
        const grossSalary = intermediateGross + grossAdditionsTotal;

        // ===== DEDUCTIONS CALCULATION =====
        // Same two-pass approach for deductions
        let fixedAndBasicDeductions = 0;
        let grossBasedDeductions = [];

        $('.deduction-row').each(function() {
            const calcType = $(this).find('.calc-type-select').val();
            const calcBase = $(this).find('.calc-base-select').val();
            const value = parseFloat($(this).find('.item-value').val()) || 0;

            if (calcType === 'percentage') {
                if (calcBase === 'basic' || calcBase === 'basic_salary') {
                    // Percentage of basic
                    fixedAndBasicDeductions += (basic * value / 100);
                } else {
                    // Percentage of gross - use final gross
                    grossBasedDeductions.push(value);
                }
            } else {
                // Fixed amount
                fixedAndBasicDeductions += value;
            }
        });

        // Calculate gross-based deductions using final gross
        let grossDeductionsTotal = 0;
        grossBasedDeductions.forEach(percentage => {
            grossDeductionsTotal += (grossSalary * percentage / 100);
        });

        const totalDeductions = fixedAndBasicDeductions + grossDeductionsTotal;
        const netSalary = grossSalary - totalDeductions;

        // Update display
        $('#summaryBasic').text('₦' + basic.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#summaryGross').text('₦' + grossSalary.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#summaryDeductions').text('₦' + totalDeductions.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#summaryNet').text('₦' + netSalary.toLocaleString('en-NG', {minimumFractionDigits: 2}));

        // Show calculation breakdown tooltip/info
        updateCalculationBreakdown(basic, fixedAndBasicAdditions, grossAdditionsTotal, grossSalary, fixedAndBasicDeductions, grossDeductionsTotal, totalDeductions, netSalary);
    }

    // Show breakdown details
    function updateCalculationBreakdown(basic, fixedBasicAdd, grossAdd, gross, fixedBasicDed, grossDed, totalDed, net) {
        // Update breakdown section
        const intermediateGross = basic + fixedBasicAdd;
        $('#breakdownIntermediateGross').text('₦' + intermediateGross.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#breakdownFinalGross').text('₦' + gross.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        
        // Update tooltips
        $('#summaryGross').closest('div').attr('title', 
            `Basic: ₦${basic.toLocaleString('en-NG')}\n` +
            `+ Fixed/Basic Add: ₦${fixedBasicAdd.toLocaleString('en-NG')}\n` +
            `+ Gross-based Add: ₦${grossAdd.toLocaleString('en-NG')}`
        );
        $('#summaryDeductions').closest('div').attr('title',
            `Fixed/Basic Ded: ₦${fixedBasicDed.toLocaleString('en-NG')}\n` +
            `+ Gross-based Ded: ₦${grossDed.toLocaleString('en-NG')}`
        );
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

    // Calculate on amount/value change
    $(document).on('change keyup', '#basic_salary, .item-value, .calc-type-select, .calc-base-select', function() {
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

            // Load additions with full calculation info
            $('#additionsContainer').html('');
            if (data.items) {
                data.items.filter(i => i.pay_head && i.pay_head.type === 'addition').forEach(item => {
                    $('#additionsContainer').append(additionRow(
                        item.pay_head_id, 
                        item.calculation_type || 'fixed',
                        item.calculation_base || 'basic',
                        item.value
                    ));
                });
            }

            // Load deductions with full calculation info
            $('#deductionsContainer').html('');
            if (data.items) {
                data.items.filter(i => i.pay_head && i.pay_head.type === 'deduction').forEach(item => {
                    $('#deductionsContainer').append(deductionRow(
                        item.pay_head_id,
                        item.calculation_type || 'fixed',
                        item.calculation_base || 'basic',
                        item.value
                    ));
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
            const basic = parseFloat(data.basic_salary) || 0;
            const gross = parseFloat(data.gross_salary) || basic;

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
                            <th>Calculation</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Basic Salary</td>
                            <td><span class="badge badge-info">Fixed</span></td>
                            <td class="text-right">₦${basic.toLocaleString('en-NG', {minimumFractionDigits: 2})}</td>
                        </tr>
            `;

            if (data.items) {
                data.items.filter(i => i.pay_head && i.pay_head.type === 'addition').forEach(item => {
                    const calcType = item.calculation_type || 'fixed';
                    const calcBase = item.calculation_base || 'basic';
                    const value = parseFloat(item.value) || 0;
                    let calcDesc = '';
                    let calculatedAmount = 0;

                    if (calcType === 'percentage') {
                        const baseAmount = (calcBase === 'basic' || calcBase === 'basic_salary') ? basic : gross;
                        calculatedAmount = baseAmount * value / 100;
                        calcDesc = `<span class="badge badge-warning">${value}% of ${calcBase === 'basic' || calcBase === 'basic_salary' ? 'Basic' : 'Gross'}</span>`;
                    } else {
                        calculatedAmount = value;
                        calcDesc = `<span class="badge badge-info">Fixed</span>`;
                    }

                    html += `
                        <tr>
                            <td>${item.pay_head.name}</td>
                            <td>${calcDesc}</td>
                            <td class="text-right">₦${calculatedAmount.toLocaleString('en-NG', {minimumFractionDigits: 2})}</td>
                        </tr>
                    `;
                });
            }

            html += `
                        <tr class="table-success font-weight-bold">
                            <td colspan="2">Gross Salary</td>
                            <td class="text-right">₦${gross.toLocaleString('en-NG', {minimumFractionDigits: 2})}</td>
                        </tr>
                    </tbody>
                </table>

                <h6 class="font-weight-bold text-danger"><i class="mdi mdi-minus-circle mr-1"></i> Deductions</h6>
                <table class="table table-sm table-bordered mb-4">
                    <thead class="bg-light">
                        <tr>
                            <th>Pay Head</th>
                            <th>Calculation</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            let totalDeductions = 0;
            if (data.items) {
                data.items.filter(i => i.pay_head && i.pay_head.type === 'deduction').forEach(item => {
                    const calcType = item.calculation_type || 'fixed';
                    const calcBase = item.calculation_base || 'basic';
                    const value = parseFloat(item.value) || 0;
                    let calcDesc = '';
                    let calculatedAmount = 0;

                    if (calcType === 'percentage') {
                        const baseAmount = (calcBase === 'basic' || calcBase === 'basic_salary') ? basic : gross;
                        calculatedAmount = baseAmount * value / 100;
                        calcDesc = `<span class="badge badge-warning">${value}% of ${calcBase === 'basic' || calcBase === 'basic_salary' ? 'Basic' : 'Gross'}</span>`;
                    } else {
                        calculatedAmount = value;
                        calcDesc = `<span class="badge badge-info">Fixed</span>`;
                    }
                    totalDeductions += calculatedAmount;

                    html += `
                        <tr>
                            <td>${item.pay_head.name}</td>
                            <td>${calcDesc}</td>
                            <td class="text-right">₦${calculatedAmount.toLocaleString('en-NG', {minimumFractionDigits: 2})}</td>
                        </tr>
                    `;
                });
            }

            const netSalary = gross - totalDeductions;

            html += `
                        <tr class="table-danger font-weight-bold">
                            <td colspan="2">Total Deductions</td>
                            <td class="text-right">₦${totalDeductions.toLocaleString('en-NG', {minimumFractionDigits: 2})}</td>
                        </tr>
                    </tbody>
                </table>

                <div class="alert alert-primary text-center" style="border-radius: 8px;">
                    <h5 class="mb-0">Net Salary: <strong>₦${netSalary.toLocaleString('en-NG', {minimumFractionDigits: 2})}</strong></h5>
                </div>
            `;

            $('#profileDetails').html(html);
            $('#viewProfileModal').modal('show');
        });
    });

    // Submit profile
    $('#profileForm').submit(function(e) {
        e.preventDefault();

        // Validate no duplicate pay heads before submission
        if (!validateNoDuplicatePayHeads()) {
            return false;
        }

        const id = $('#profile_id').val();
        const url = id ? "{{ route('hr.salary-profiles.index') }}/" + id : "{{ route('hr.salary-profiles.store') }}";

        // Collect items with full calculation config
        const items = [];

        $('.addition-row').each(function() {
            const payHeadId = $(this).find('select[name="addition_pay_head_id[]"]').val();
            const calcType = $(this).find('select[name="addition_calc_type[]"]').val();
            const calcBase = $(this).find('select[name="addition_calc_base[]"]').val();
            const value = $(this).find('input[name="addition_value[]"]').val();
            if (payHeadId && value) {
                items.push({ 
                    pay_head_id: payHeadId, 
                    calculation_type: calcType,
                    calculation_base: calcBase,
                    value: value 
                });
            }
        });

        $('.deduction-row').each(function() {
            const payHeadId = $(this).find('select[name="deduction_pay_head_id[]"]').val();
            const calcType = $(this).find('select[name="deduction_calc_type[]"]').val();
            const calcBase = $(this).find('select[name="deduction_calc_base[]"]').val();
            const value = $(this).find('input[name="deduction_value[]"]').val();
            if (payHeadId && value) {
                items.push({ 
                    pay_head_id: payHeadId,
                    calculation_type: calcType,
                    calculation_base: calcBase,
                    value: value 
                });
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
                loadStats(); // Refresh stats
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

    // Delete profile
    $(document).on('click', '.delete-btn', function() {
        const id = $(this).data('id');
        
        if (!confirm('Are you sure you want to delete this salary profile? This action cannot be undone.')) {
            return;
        }

        $.ajax({
            url: "{{ route('hr.salary-profiles.index') }}/" + id,
            method: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                table.ajax.reload();
                loadStats(); // Refresh stats
                toastr.success(response.message || 'Salary profile deleted successfully');
            },
            error: function(xhr) {
                let message = 'Failed to delete salary profile';
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
