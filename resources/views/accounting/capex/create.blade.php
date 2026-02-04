{{--
    Create Capex Request
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.7
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'New Capex Request')
@section('page_name', 'Accounting')
@section('subpage_name', 'New Capex Request')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Capital Expenditure', 'url' => route('accounting.capex.index'), 'icon' => 'mdi-factory'],
        ['label' => 'New Request', 'url' => '#', 'icon' => 'mdi-plus']
    ]
])

<style>
.form-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 25px;
    margin-bottom: 20px;
}
.form-card h6 {
    font-weight: 600;
    color: #333;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f1f1f1;
}
.category-card {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 15px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}
.category-card:hover { border-color: #667eea; background: #f8f9fa; }
.category-card.selected { border-color: #667eea; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
.category-card .icon { font-size: 2rem; margin-bottom: 10px; }
.category-card.selected .icon { color: white; }
.item-row {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
    border-left: 3px solid #667eea;
}
.total-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    margin-bottom: 20px;
}
.total-card .amount { font-size: 1.8rem; font-weight: 700; }
.priority-option {
    display: flex;
    align-items: center;
    padding: 10px 15px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    margin-bottom: 10px;
}
.priority-option:hover { border-color: #667eea; }
.priority-option.selected { border-color: #667eea; background: #f8f9fa; }
.priority-option input { display: none; }
.priority-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 10px;
}
.priority-low { background: #28a745; }
.priority-medium { background: #17a2b8; }
.priority-high { background: #ffc107; }
.priority-critical { background: #dc3545; }
</style>

<div class="container-fluid">
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <form action="{{ route('accounting.capex.store') }}" method="POST" id="capexForm">
        @csrf

        <div class="row">
            <div class="col-lg-8">
                <!-- Category Selection -->
                <div class="form-card">
                    <h6><i class="mdi mdi-shape mr-2"></i>Category</h6>
                    <p class="text-muted small mb-3">
                        <i class="mdi mdi-information-outline mr-1"></i>
                        Select the category that best describes this capital expenditure. Categories help organize and track spending patterns across different asset types.
                    </p>
                    <input type="hidden" name="category" id="categoryInput" value="{{ old('category') }}">
                    <div class="row">
                        @foreach($categories as $key => $label)
                            <div class="col-md-3 mb-3">
                                <div class="category-card {{ old('category') == $key ? 'selected' : '' }}" data-category="{{ $key }}">
                                    <div class="icon">
                                        @switch($key)
                                            @case('equipment')
                                                <i class="mdi mdi-desktop-classic"></i>
                                                @break
                                            @case('technology')
                                                <i class="mdi mdi-laptop"></i>
                                                @break
                                            @case('facilities')
                                                <i class="mdi mdi-office-building"></i>
                                                @break
                                            @case('vehicles')
                                                <i class="mdi mdi-car"></i>
                                                @break
                                            @case('furniture')
                                                <i class="mdi mdi-chair-rolling"></i>
                                                @break
                                            @case('software')
                                                <i class="mdi mdi-application"></i>
                                                @break
                                            @case('construction')
                                                <i class="mdi mdi-home-city"></i>
                                                @break
                                            @case('renovation')
                                                <i class="mdi mdi-hammer"></i>
                                                @break
                                            @default
                                                <i class="mdi mdi-package"></i>
                                        @endswitch
                                    </div>
                                    <div class="font-weight-bold">{{ $label }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Basic Info -->
                <div class="form-card">
                    <h6><i class="mdi mdi-information-outline mr-2"></i>Request Details</h6>
                    <p class="text-muted small mb-3">
                        <i class="mdi mdi-information-outline mr-1"></i>
                        Provide clear and detailed information about this capital expenditure request. A well-documented request speeds up the approval process.
                    </p>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="title" value="{{ old('title') }}"
                                       placeholder="e.g., Purchase of Laboratory Equipment" required>
                                <small class="form-text text-muted">Provide a concise, descriptive title that clearly identifies this capital expenditure</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Fiscal Year <span class="text-danger">*</span></label>
                                <select class="form-control" name="fiscal_year" required>
                                    @foreach($fiscalYears as $year)
                                        <option value="{{ $year }}" {{ old('fiscal_year', date('Y')) == $year ? 'selected' : '' }}>{{ $year }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">The budget year for this expenditure</small>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Brief description of the capital expenditure...">{{ old('description') }}</textarea>
                        <small class="form-text text-muted">Provide additional details about what will be purchased/constructed and its intended use</small>
                    </div>
                    <div class="form-group">
                        <label>Business Justification <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="justification" rows="4"
                                  placeholder="Explain why this expenditure is necessary, expected benefits, ROI, etc." required>{{ old('justification') }}</textarea>
                        <small class="form-text text-muted">
                            <strong>Required:</strong> Explain the business case including: Why is this needed? What problems does it solve? Expected benefits and ROI? Impact if not approved?
                        </small>
                    </div>
                </div>

                <!-- Line Items -->
                <div class="form-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0"><i class="mdi mdi-format-list-bulleted mr-2"></i>Line Items</h6>
                    </div>
                    <p class="text-muted small mb-3">
                        <i class="mdi mdi-information-outline mr-1"></i>
                        Break down the total expenditure into individual items. Specify quantity and unit cost for each item. The system will automatically calculate the total amount.
                    </p>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div></div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="addItem">
                            <i class="mdi mdi-plus mr-1"></i> Add Item
                        </button>
                    </div>

                    <div id="itemsContainer">
                        <div class="row mb-2">
                            <div class="col-md-5"><small class="text-muted"><strong>Item Description</strong></small></div>
                            <div class="col-md-2"><small class="text-muted"><strong>Quantity</strong></small></div>
                            <div class="col-md-2"><small class="text-muted"><strong>Unit Cost</strong></small></div>
                            <div class="col-md-2"><small class="text-muted"><strong>Total Amount</strong></small></div>
                            <div class="col-md-1"></div>
                        </div>
                        <div class="item-row">
                            <div class="row">
                                <div class="col-md-5">
                                    <input type="text" class="form-control" name="items[0][description]"
                                           placeholder="Item description" required>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control item-qty" name="items[0][quantity]"
                                           placeholder="Qty" value="1" min="1">
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control item-unit-cost" name="items[0][unit_cost]"
                                           placeholder="Unit Cost" step="0.01" min="0">
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control item-amount" name="items[0][amount]"
                                           placeholder="Amount" step="0.01" min="0" required readonly>
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-outline-danger btn-sm remove-item" disabled>
                                        <i class="mdi mdi-delete"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 text-right">
                        <span class="text-muted">Total Requested Amount:</span>
                        <strong class="h5 ml-2" id="totalAmount">₦0.00</strong>
                        <input type="hidden" name="requested_amount" id="requestedAmount" value="0">
                    </div>
                </div>

                <!-- Additional Details -->
                <div class="form-card">
                    <h6><i class="mdi mdi-cog mr-2"></i>Additional Details</h6>
                    <p class="text-muted small mb-3">
                        <i class="mdi mdi-information-outline mr-1"></i>
                        Provide optional information to help with budget tracking, vendor management, and project timeline planning.
                    </p>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Cost Center</label>
                                <select class="form-control select2" name="cost_center_id">
                                    <option value="">-- Select Cost Center --</option>
                                    @foreach($costCenters as $cc)
                                        <option value="{{ $cc->id }}" {{ old('cost_center_id') == $cc->id ? 'selected' : '' }}>
                                            {{ $cc->code }} - {{ $cc->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Assign to a department/unit for budget tracking purposes</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Preferred Vendor</label>
                                <select class="form-control select2" name="vendor_id">
                                    <option value="">-- Select Vendor (Optional) --</option>
                                    @foreach($vendors as $vendor)
                                        <option value="{{ $vendor->id }}" {{ old('vendor_id') == $vendor->id ? 'selected' : '' }}>
                                            {{ $vendor->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Suggest a vendor if you have a preferred supplier for this purchase</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Expected Start Date</label>
                                <input type="date" class="form-control" name="expected_start_date" value="{{ old('expected_start_date') }}">
                                <small class="form-text text-muted">When do you expect to begin this project/purchase?</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Expected Completion Date</label>
                                <input type="date" class="form-control" name="expected_completion_date" value="{{ old('expected_completion_date') }}">
                                <small class="form-text text-muted">When should this project/purchase be completed?</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Total Amount -->
                <div class="total-card">
                    <div class="label">Total Requested</div>
                    <div class="amount" id="totalDisplay">₦0.00</div>
                    <small>Capital Expenditure</small>
                </div>

                <!-- Priority -->
                <div class="form-card">
                    <h6><i class="mdi mdi-flag mr-2"></i>Priority Level</h6>
                    <p class="text-muted small mb-3">
                        <i class="mdi mdi-information-outline mr-1"></i>
                        Indicate the urgency and importance of this request. Higher priority items are reviewed first and may receive expedited approval.
                    </p>

                    <label class="priority-option {{ old('priority') == 'low' ? 'selected' : '' }}">
                        <input type="radio" name="priority" value="low" {{ old('priority') == 'low' ? 'checked' : '' }}>
                        <span class="priority-dot priority-low"></span>
                        <div>
                            <strong>Low</strong>
                            <small class="text-muted d-block">Can be deferred if needed, no immediate operational impact</small>
                        </div>
                    </label>

                    <label class="priority-option {{ old('priority', 'medium') == 'medium' ? 'selected' : '' }}">
                        <input type="radio" name="priority" value="medium" {{ old('priority', 'medium') == 'medium' ? 'checked' : '' }}>
                        <span class="priority-dot priority-medium"></span>
                        <div>
                            <strong>Medium</strong>
                            <small class="text-muted d-block">Standard processing timeline, should be completed within budget cycle</small>
                        </div>
                    </label>

                    <label class="priority-option {{ old('priority') == 'high' ? 'selected' : '' }}">
                        <input type="radio" name="priority" value="high" {{ old('priority') == 'high' ? 'checked' : '' }}>
                        <span class="priority-dot priority-high"></span>
                        <div>
                            <strong>High</strong>
                            <small class="text-muted d-block">Time-sensitive with significant operational benefit, needs prompt attention</small>
                        </div>
                    </label>

                    <label class="priority-option {{ old('priority') == 'critical' ? 'selected' : '' }}">
                        <input type="radio" name="priority" value="critical" {{ old('priority') == 'critical' ? 'checked' : '' }}>
                        <span class="priority-dot priority-critical"></span>
                        <div>
                            <strong>Critical</strong>
                            <small class="text-muted d-block">Urgent - operational necessity, safety concern, or regulatory requirement</small>
                        </div>
                    </label>
                </div>

                <!-- Actions -->
                <div class="form-card">
                    <h6><i class="mdi mdi-send mr-2"></i>Actions</h6>
                    <p class="text-muted small mb-3">
                        <strong>Save as Draft:</strong> Save your progress without submitting for approval<br>
                        <strong>Save & Submit:</strong> Submit request for management review and approval
                    </p>
                    <button type="submit" class="btn btn-primary btn-block mb-2">
                        <i class="mdi mdi-content-save mr-1"></i> Save as Draft
                    </button>
                    <button type="button" class="btn btn-success btn-block mb-2" id="saveAndSubmit">
                        <i class="mdi mdi-send mr-1"></i> Save & Submit for Approval
                    </button>
                    <a href="{{ route('accounting.capex.index') }}" class="btn btn-outline-secondary btn-block">
                        <i class="mdi mdi-close mr-1"></i> Cancel
                    </a>
                </div>

                <!-- Help -->
                <div class="form-card bg-light">
                    <h6><i class="mdi mdi-help-circle mr-2"></i>Capex Guidelines</h6>
                    <small class="text-muted">
                        <ul class="pl-3 mb-2">
                            <li><strong>Definition:</strong> Capital expenditures are for assets with useful life > 1 year and value > ₦100,000</li>
                            <li><strong>Documentation:</strong> Include detailed business justification explaining ROI and operational benefits</li>
                            <li><strong>Approval Levels:</strong> Requests above ₦500,000 require senior management approval</li>
                            <li><strong>Budget Tracking:</strong> All capex is tracked against annual fiscal year budgets by category</li>
                            <li><strong>Supporting Documents:</strong> Attach vendor quotes, technical specifications, or feasibility studies when available</li>
                        </ul>
                        <div class="alert alert-info py-2 px-3 mb-0">
                            <i class="mdi mdi-lightbulb-outline mr-1"></i>
                            <strong>Tip:</strong> Thoroughly complete all sections to expedite the approval process.
                        </div>
                    </small>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Item Template -->
<template id="itemTemplate">
    <div class="item-row">
        <div class="row">
            <div class="col-md-5">
                <input type="text" class="form-control" name="items[INDEX][description]" placeholder="Item description" required>
            </div>
            <div class="col-md-2">
                <input type="number" class="form-control item-qty" name="items[INDEX][quantity]" placeholder="Qty" value="1" min="1">
            </div>
            <div class="col-md-2">
                <input type="number" class="form-control item-unit-cost" name="items[INDEX][unit_cost]" placeholder="Unit Cost" step="0.01" min="0">
            </div>
            <div class="col-md-2">
                <input type="number" class="form-control item-amount" name="items[INDEX][amount]" placeholder="Amount" step="0.01" min="0" required readonly>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-outline-danger btn-sm remove-item">
                    <i class="mdi mdi-delete"></i>
                </button>
            </div>
        </div>
    </div>
</template>
@endsection

@push('scripts')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2').select2({ width: '100%' });

    var itemIndex = 1;

    // Format currency
    function formatCurrency(amount) {
        return '₦' + parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Calculate item amount
    function calculateItemAmount(row) {
        var qty = parseFloat(row.find('.item-qty').val()) || 0;
        var unitCost = parseFloat(row.find('.item-unit-cost').val()) || 0;
        var amount = qty * unitCost;
        row.find('.item-amount').val(amount.toFixed(2));
        calculateTotal();
    }

    // Calculate total
    function calculateTotal() {
        var total = 0;
        $('.item-amount').each(function() {
            total += parseFloat($(this).val()) || 0;
        });

        $('#totalAmount, #totalDisplay').text(formatCurrency(total));
        $('#requestedAmount').val(total.toFixed(2));
    }

    // Category selection
    $('.category-card').on('click', function() {
        $('.category-card').removeClass('selected');
        $(this).addClass('selected');
        $('#categoryInput').val($(this).data('category'));
    });

    // Priority selection
    $('.priority-option').on('click', function() {
        $('.priority-option').removeClass('selected');
        $(this).addClass('selected');
        $(this).find('input').prop('checked', true);
    });

    // Item amount calculation
    $(document).on('input', '.item-qty, .item-unit-cost', function() {
        calculateItemAmount($(this).closest('.item-row'));
    });

    // Add item
    $('#addItem').on('click', function() {
        var template = $('#itemTemplate').html();
        template = template.replace(/INDEX/g, itemIndex);
        $('#itemsContainer').append(template);
        itemIndex++;

        // Enable remove on first item if more than one
        if ($('.item-row').length > 1) {
            $('.remove-item').prop('disabled', false);
        }
    });

    // Remove item
    $(document).on('click', '.remove-item', function() {
        if ($('.item-row').length > 1) {
            $(this).closest('.item-row').remove();
            calculateTotal();

            if ($('.item-row').length === 1) {
                $('.remove-item').prop('disabled', true);
            }
        }
    });

    // Save and submit
    $('#saveAndSubmit').on('click', function() {
        if ($('#capexForm')[0].checkValidity()) {
            if (!$('#categoryInput').val()) {
                toastr.error('Please select a category');
                return;
            }

            // Add hidden input to trigger submit after save
            $('<input>').attr({
                type: 'hidden',
                name: 'submit_after_save',
                value: '1'
            }).appendTo('#capexForm');

            $('#capexForm').submit();
        } else {
            $('#capexForm')[0].reportValidity();
        }
    });

    // Form validation before submit
    $('#capexForm').on('submit', function(e) {
        if (!$('#categoryInput').val()) {
            e.preventDefault();
            toastr.error('Please select a category');
            return false;
        }

        if (parseFloat($('#requestedAmount').val()) <= 0) {
            e.preventDefault();
            toastr.error('Please add at least one line item with amount');
            return false;
        }
    });
});
</script>
@endpush
