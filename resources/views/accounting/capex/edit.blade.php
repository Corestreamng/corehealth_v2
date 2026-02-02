{{--
    Edit Capex Request
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.7
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'Edit Capex Request')
@section('page_name', 'Accounting')
@section('subpage_name', 'Edit Capex Request')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Capital Expenditure', 'url' => route('accounting.capex.index'), 'icon' => 'mdi-factory'],
        ['label' => $capex->reference_number, 'url' => route('accounting.capex.show', $capex->id), 'icon' => 'mdi-eye'],
        ['label' => 'Edit', 'url' => '#', 'icon' => 'mdi-pencil']
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
.priority-dot { width: 12px; height: 12px; border-radius: 50%; margin-right: 10px; }
.priority-low { background: #28a745; }
.priority-medium { background: #17a2b8; }
.priority-high { background: #ffc107; }
.priority-critical { background: #dc3545; }
.ref-badge {
    background: #e9ecef;
    padding: 5px 15px;
    border-radius: 20px;
    font-weight: 600;
    display: inline-block;
    margin-bottom: 20px;
}
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

    @if($capex->status == 'revision' && $capex->revision_notes)
        <div class="alert alert-warning">
            <strong><i class="mdi mdi-alert mr-1"></i> Revision Requested:</strong>
            {{ $capex->revision_notes }}
        </div>
    @endif

    <form action="{{ route('accounting.capex.update', $capex->id) }}" method="POST" id="capexForm">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-lg-8">
                <!-- Reference Info -->
                <div class="form-card">
                    <div class="ref-badge">
                        <i class="mdi mdi-tag mr-1"></i>{{ $capex->reference_number }}
                    </div>
                    <span class="badge badge-{{ $capex->status == 'draft' ? 'secondary' : 'warning' }} ml-2">
                        {{ ucfirst($capex->status) }}
                    </span>
                </div>

                <!-- Category Selection -->
                <div class="form-card">
                    <h6><i class="mdi mdi-shape mr-2"></i>Category</h6>
                    <input type="hidden" name="category" id="categoryInput" value="{{ old('category', $capex->category) }}">
                    <div class="row">
                        @foreach($categories as $key => $label)
                            <div class="col-md-3 mb-3">
                                <div class="category-card {{ old('category', $capex->category) == $key ? 'selected' : '' }}" data-category="{{ $key }}">
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
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="title"
                                       value="{{ old('title', $capex->title) }}" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Fiscal Year</label>
                                <input type="text" class="form-control bg-light" value="{{ $capex->fiscal_year }}" readonly>
                                <input type="hidden" name="fiscal_year" value="{{ $capex->fiscal_year }}">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="description" rows="3">{{ old('description', $capex->description) }}</textarea>
                    </div>
                    <div class="form-group">
                        <label>Business Justification <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="justification" rows="4" required>{{ old('justification', $capex->justification) }}</textarea>
                    </div>
                </div>

                <!-- Line Items -->
                <div class="form-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0"><i class="mdi mdi-format-list-bulleted mr-2"></i>Line Items</h6>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="addItem">
                            <i class="mdi mdi-plus mr-1"></i> Add Item
                        </button>
                    </div>

                    <div id="itemsContainer">
                        @forelse($items as $index => $item)
                            <div class="item-row">
                                <div class="row">
                                    <div class="col-md-5">
                                        <input type="text" class="form-control" name="items[{{ $index }}][description]"
                                               value="{{ $item->description }}" placeholder="Item description" required>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" class="form-control item-qty" name="items[{{ $index }}][quantity]"
                                               value="{{ $item->quantity }}" placeholder="Qty" min="1">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" class="form-control item-unit-cost" name="items[{{ $index }}][unit_cost]"
                                               value="{{ $item->unit_cost }}" placeholder="Unit Cost" step="0.01" min="0">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" class="form-control item-amount" name="items[{{ $index }}][amount]"
                                               value="{{ $item->amount }}" placeholder="Amount" step="0.01" min="0" required readonly>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-outline-danger btn-sm remove-item" {{ $items->count() <= 1 ? 'disabled' : '' }}>
                                            <i class="mdi mdi-delete"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="item-row">
                                <div class="row">
                                    <div class="col-md-5">
                                        <input type="text" class="form-control" name="items[0][description]" placeholder="Item description" required>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" class="form-control item-qty" name="items[0][quantity]" placeholder="Qty" value="1" min="1">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" class="form-control item-unit-cost" name="items[0][unit_cost]" placeholder="Unit Cost" step="0.01" min="0">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" class="form-control item-amount" name="items[0][amount]" placeholder="Amount" step="0.01" min="0" required readonly>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-outline-danger btn-sm remove-item" disabled>
                                            <i class="mdi mdi-delete"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforelse
                    </div>

                    <div class="mt-3 text-right">
                        <span class="text-muted">Total Requested Amount:</span>
                        <strong class="h5 ml-2" id="totalAmount">₦{{ number_format($capex->requested_amount, 2) }}</strong>
                        <input type="hidden" name="requested_amount" id="requestedAmount" value="{{ $capex->requested_amount }}">
                    </div>
                </div>

                <!-- Additional Details -->
                <div class="form-card">
                    <h6><i class="mdi mdi-cog mr-2"></i>Additional Details</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Cost Center</label>
                                <select class="form-control select2" name="cost_center_id">
                                    <option value="">-- Select Cost Center --</option>
                                    @foreach($costCenters as $cc)
                                        <option value="{{ $cc->id }}" {{ old('cost_center_id', $capex->cost_center_id) == $cc->id ? 'selected' : '' }}>
                                            {{ $cc->code }} - {{ $cc->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Preferred Vendor</label>
                                <select class="form-control select2" name="vendor_id">
                                    <option value="">-- Select Vendor (Optional) --</option>
                                    @foreach($vendors as $vendor)
                                        <option value="{{ $vendor->id }}" {{ old('vendor_id', $capex->vendor_id) == $vendor->id ? 'selected' : '' }}>
                                            {{ $vendor->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Expected Start Date</label>
                                <input type="date" class="form-control" name="expected_start_date"
                                       value="{{ old('expected_start_date', $capex->expected_start_date) }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Expected Completion Date</label>
                                <input type="date" class="form-control" name="expected_completion_date"
                                       value="{{ old('expected_completion_date', $capex->expected_completion_date) }}">
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
                    <div class="amount" id="totalDisplay">₦{{ number_format($capex->requested_amount, 2) }}</div>
                    <small>Capital Expenditure</small>
                </div>

                <!-- Priority -->
                <div class="form-card">
                    <h6><i class="mdi mdi-flag mr-2"></i>Priority Level</h6>

                    @foreach(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'] as $key => $label)
                        @php
                            $desc = ['low' => 'Can wait, no urgency', 'medium' => 'Normal priority', 'high' => 'Important, time-sensitive', 'critical' => 'Urgent, affects operations'];
                        @endphp
                        <label class="priority-option {{ old('priority', $capex->priority) == $key ? 'selected' : '' }}">
                            <input type="radio" name="priority" value="{{ $key }}" {{ old('priority', $capex->priority) == $key ? 'checked' : '' }}>
                            <span class="priority-dot priority-{{ $key }}"></span>
                            <div>
                                <strong>{{ $label }}</strong>
                                <small class="text-muted d-block">{{ $desc[$key] }}</small>
                            </div>
                        </label>
                    @endforeach
                </div>

                <!-- Actions -->
                <div class="form-card">
                    <h6><i class="mdi mdi-send mr-2"></i>Actions</h6>
                    <button type="submit" class="btn btn-primary btn-block mb-2">
                        <i class="mdi mdi-content-save mr-1"></i> Save Changes
                    </button>
                    @if($capex->status == 'revision')
                        <button type="button" class="btn btn-success btn-block mb-2" id="saveAndResubmit">
                            <i class="mdi mdi-send mr-1"></i> Save & Resubmit
                        </button>
                    @endif
                    <a href="{{ route('accounting.capex.show', $capex->id) }}" class="btn btn-outline-secondary btn-block">
                        <i class="mdi mdi-close mr-1"></i> Cancel
                    </a>
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

    var itemIndex = {{ $items->count() }};

    function formatCurrency(amount) {
        return '₦' + parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function calculateItemAmount(row) {
        var qty = parseFloat(row.find('.item-qty').val()) || 0;
        var unitCost = parseFloat(row.find('.item-unit-cost').val()) || 0;
        var amount = qty * unitCost;
        row.find('.item-amount').val(amount.toFixed(2));
        calculateTotal();
    }

    function calculateTotal() {
        var total = 0;
        $('.item-amount').each(function() {
            total += parseFloat($(this).val()) || 0;
        });

        $('#totalAmount, #totalDisplay').text(formatCurrency(total));
        $('#requestedAmount').val(total.toFixed(2));
    }

    // Initial calculation
    calculateTotal();

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

    // Save and resubmit
    $('#saveAndResubmit').on('click', function() {
        if ($('#capexForm')[0].checkValidity()) {
            $('<input>').attr({
                type: 'hidden',
                name: 'resubmit',
                value: '1'
            }).appendTo('#capexForm');

            $('#capexForm').submit();
        } else {
            $('#capexForm')[0].reportValidity();
        }
    });
});
</script>
@endpush
