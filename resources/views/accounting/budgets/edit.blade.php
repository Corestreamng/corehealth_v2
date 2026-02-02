{{--
    Edit Budget
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.10
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'Edit Budget: ' . $budget->name)
@section('page_name', 'Accounting')
@section('subpage_name', 'Edit Budget')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Budgets', 'url' => route('accounting.budgets.index'), 'icon' => 'mdi-calculator'],
        ['label' => $budget->name, 'url' => route('accounting.budgets.show', $budget->id), 'icon' => 'mdi-eye'],
        ['label' => 'Edit', 'url' => '#', 'icon' => 'mdi-pencil']
    ]
])

<style>
.form-section {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 25px;
    margin-bottom: 20px;
}
.form-section h6 {
    font-weight: 600;
    color: #333;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f1f1f1;
}
.line-item-row {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
}
.total-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
}
.total-card .amount { font-size: 1.8rem; font-weight: 700; }
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

    <form action="{{ route('accounting.budgets.update', $budget->id) }}" method="POST" id="budgetForm">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-lg-8">
                <!-- Basic Information -->
                <div class="form-section">
                    <h6><i class="mdi mdi-information-outline mr-2"></i>Basic Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Budget Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name', $budget->name) }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Fiscal Year <span class="text-danger">*</span></label>
                                <select name="fiscal_year_id" class="form-control" required>
                                    @foreach($fiscalYears as $fy)
                                        <option value="{{ $fy->id }}" {{ old('fiscal_year_id', $budget->fiscal_year_id) == $fy->id ? 'selected' : '' }}>
                                            {{ $fy->year }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Department</label>
                                <select name="department_id" class="form-control select2">
                                    <option value="">Organization-wide</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" {{ old('department_id', $budget->department_id) == $dept->id ? 'selected' : '' }}>
                                            {{ $dept->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Description</label>
                                <input type="text" name="description" class="form-control"
                                       value="{{ old('description', $budget->description) }}">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Budget Line Items -->
                <div class="form-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0"><i class="mdi mdi-format-list-numbered mr-2"></i>Budget Line Items</h6>
                        <button type="button" class="btn btn-success btn-sm" id="addLineItem">
                            <i class="mdi mdi-plus"></i> Add Line
                        </button>
                    </div>

                    <div id="lineItemsContainer">
                        @foreach($budget->items as $index => $item)
                            <div class="line-item-row" data-index="{{ $index }}">
                                <div class="row">
                                    <div class="col-md-5">
                                        <div class="form-group mb-2">
                                            <label>Expense Account <span class="text-danger">*</span></label>
                                            <select name="items[{{ $index }}][account_id]" class="form-control account-select" required>
                                                <option value="">Select Account</option>
                                                @foreach($expenseAccounts as $account)
                                                    <option value="{{ $account->id }}" {{ $item->account_id == $account->id ? 'selected' : '' }}>
                                                        {{ $account->code }} - {{ $account->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group mb-2">
                                            <label>Amount <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">₦</span>
                                                </div>
                                                <input type="number" name="items[{{ $index }}][amount]" class="form-control amount-input"
                                                       step="0.01" min="0" value="{{ $item->budgeted_amount }}" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group mb-2">
                                            <label>Notes</label>
                                            <input type="text" name="items[{{ $index }}][notes]" class="form-control"
                                                   value="{{ $item->notes }}">
                                        </div>
                                    </div>
                                    <div class="col-md-1 d-flex align-items-end">
                                        <button type="button" class="btn btn-danger btn-sm mb-2 remove-line" {{ $budget->items->count() <= 1 ? 'disabled' : '' }}>
                                            <i class="mdi mdi-delete"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Total -->
                <div class="total-card mb-3">
                    <div class="label">Total Budget</div>
                    <div class="amount" id="totalAmount">₦{{ number_format($budget->total_amount, 2) }}</div>
                    <div class="label"><span id="lineCount">{{ $budget->items->count() }}</span> line item(s)</div>
                </div>

                <!-- Actions -->
                <div class="form-section">
                    <h6><i class="mdi mdi-cog mr-2"></i>Actions</h6>
                    <button type="submit" class="btn btn-primary btn-block mb-2">
                        <i class="mdi mdi-content-save mr-1"></i> Save Changes
                    </button>
                    <a href="{{ route('accounting.budgets.show', $budget->id) }}" class="btn btn-outline-secondary btn-block">
                        <i class="mdi mdi-close mr-1"></i> Cancel
                    </a>
                </div>

                <!-- Info -->
                <div class="form-section">
                    <h6><i class="mdi mdi-information-outline mr-2"></i>Info</h6>
                    <p class="text-muted small mb-2">
                        <strong>Status:</strong> {{ ucfirst($budget->status) }}
                    </p>
                    <p class="text-muted small mb-2">
                        <strong>Created:</strong> {{ $budget->created_at->format('M d, Y') }}
                    </p>
                    <p class="text-muted small mb-0">
                        <strong>Last Updated:</strong> {{ $budget->updated_at->format('M d, Y') }}
                    </p>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    var lineIndex = {{ $budget->items->count() }};

    function initSelect2() {
        $('.account-select').select2({
            placeholder: 'Select Account',
            allowClear: true,
            width: '100%'
        });
    }
    initSelect2();

    // Add line item
    $('#addLineItem').on('click', function() {
        var html = `
            <div class="line-item-row" data-index="${lineIndex}">
                <div class="row">
                    <div class="col-md-5">
                        <div class="form-group mb-2">
                            <label>Expense Account <span class="text-danger">*</span></label>
                            <select name="items[${lineIndex}][account_id]" class="form-control account-select" required>
                                <option value="">Select Account</option>
                                @foreach($expenseAccounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group mb-2">
                            <label>Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">₦</span>
                                </div>
                                <input type="number" name="items[${lineIndex}][amount]" class="form-control amount-input"
                                       step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group mb-2">
                            <label>Notes</label>
                            <input type="text" name="items[${lineIndex}][notes]" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="button" class="btn btn-danger btn-sm mb-2 remove-line">
                            <i class="mdi mdi-delete"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;

        $('#lineItemsContainer').append(html);
        initSelect2();
        lineIndex++;
        updateTotals();
        updateRemoveButtons();
    });

    $(document).on('click', '.remove-line', function() {
        if ($('.line-item-row').length > 1) {
            $(this).closest('.line-item-row').remove();
            updateTotals();
            updateRemoveButtons();
        }
    });

    $(document).on('input', '.amount-input', function() {
        updateTotals();
    });

    function updateTotals() {
        var total = 0;
        var count = 0;

        $('.amount-input').each(function() {
            var val = parseFloat($(this).val()) || 0;
            total += val;
            count++;
        });

        $('#totalAmount').text('₦' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#lineCount').text(count);
    }

    function updateRemoveButtons() {
        if ($('.line-item-row').length > 1) {
            $('.remove-line').prop('disabled', false);
        } else {
            $('.remove-line').prop('disabled', true);
        }
    }

    updateTotals();
});
</script>
@endpush
