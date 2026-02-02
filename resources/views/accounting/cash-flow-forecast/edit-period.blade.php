{{--
    Edit Cash Flow Forecast Period
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.6
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'Edit Forecast Period')
@section('page_name', 'Accounting')
@section('subpage_name', 'Edit Period')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Cash Flow Forecast', 'url' => route('accounting.cash-flow-forecast.index'), 'icon' => 'mdi-chart-timeline-variant'],
        ['label' => $period->forecast->name, 'url' => route('accounting.cash-flow-forecast.show', $period->forecast_id), 'icon' => 'mdi-eye'],
        ['label' => 'Edit Period', 'url' => '#', 'icon' => 'mdi-pencil']
    ]
])

<style>
.period-info {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}
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
.item-row {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
    border: 1px solid #e9ecef;
}
.item-row.inflow { border-left: 3px solid #28a745; }
.item-row.outflow { border-left: 3px solid #dc3545; }
.total-row {
    background: #e9ecef;
    border-radius: 8px;
    padding: 15px;
    margin-top: 10px;
}
.total-row .amount { font-size: 1.2rem; font-weight: 700; }
.section-title {
    font-weight: 600;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
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

    <!-- Period Info Header -->
    <div class="period-info">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h4 class="mb-1">{{ $period->forecast->name }}</h4>
                <div>
                    <span class="badge badge-light">
                        {{ \Carbon\Carbon::parse($period->period_start)->format('M d, Y') }} -
                        {{ \Carbon\Carbon::parse($period->period_end)->format('M d, Y') }}
                    </span>
                    <span class="badge badge-{{ $period->forecast->status == 'active' ? 'success' : 'secondary' }} ml-1">
                        {{ ucfirst($period->forecast->frequency) }}
                    </span>
                </div>
            </div>
            <div class="col-md-4 text-md-right mt-3 mt-md-0">
                <a href="{{ route('accounting.cash-flow-forecast.show', $period->forecast_id) }}" class="btn btn-light btn-sm">
                    <i class="mdi mdi-arrow-left mr-1"></i> Back to Forecast
                </a>
            </div>
        </div>
    </div>

    <form action="{{ route('accounting.cash-flow-forecast.periods.update', $period->id) }}" method="POST" id="periodForm">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-lg-8">
                <!-- Inflows -->
                <div class="form-card">
                    <div class="section-title text-success">
                        <span><i class="mdi mdi-arrow-down-bold-circle mr-2"></i>Cash Inflows</span>
                        <button type="button" class="btn btn-outline-success btn-sm add-item" data-type="inflow">
                            <i class="mdi mdi-plus"></i> Add Inflow
                        </button>
                    </div>

                    <div id="inflowItems">
                        @foreach($period->items->where('type', 'inflow') as $item)
                            <div class="item-row inflow">
                                <div class="row">
                                    <div class="col-md-5">
                                        <input type="hidden" name="items[{{ $loop->index }}][id]" value="{{ $item->id }}">
                                        <input type="hidden" name="items[{{ $loop->index }}][type]" value="inflow">
                                        <input type="text" class="form-control" name="items[{{ $loop->index }}][description]"
                                               value="{{ $item->description }}" placeholder="Description" required>
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-control" name="items[{{ $loop->index }}][category]">
                                            <option value="sales" {{ $item->category == 'sales' ? 'selected' : '' }}>Sales</option>
                                            <option value="receivables" {{ $item->category == 'receivables' ? 'selected' : '' }}>Receivables</option>
                                            <option value="investment" {{ $item->category == 'investment' ? 'selected' : '' }}>Investment</option>
                                            <option value="financing" {{ $item->category == 'financing' ? 'selected' : '' }}>Financing</option>
                                            <option value="other" {{ $item->category == 'other' ? 'selected' : '' }}>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="number" class="form-control item-amount" name="items[{{ $loop->index }}][amount]"
                                                   value="{{ $item->amount }}" step="0.01" min="0" data-type="inflow" required>
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-outline-danger btn-sm remove-item">
                                            <i class="mdi mdi-delete"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="total-row">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">Total Inflows:</span>
                            <span class="amount text-success" id="totalInflows">₦0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Outflows -->
                <div class="form-card">
                    <div class="section-title text-danger">
                        <span><i class="mdi mdi-arrow-up-bold-circle mr-2"></i>Cash Outflows</span>
                        <button type="button" class="btn btn-outline-danger btn-sm add-item" data-type="outflow">
                            <i class="mdi mdi-plus"></i> Add Outflow
                        </button>
                    </div>

                    <div id="outflowItems">
                        @foreach($period->items->where('type', 'outflow') as $item)
                            <div class="item-row outflow">
                                <div class="row">
                                    <div class="col-md-5">
                                        <input type="hidden" name="items[{{ $period->items->where('type', 'inflow')->count() + $loop->index }}][id]" value="{{ $item->id }}">
                                        <input type="hidden" name="items[{{ $period->items->where('type', 'inflow')->count() + $loop->index }}][type]" value="outflow">
                                        <input type="text" class="form-control" name="items[{{ $period->items->where('type', 'inflow')->count() + $loop->index }}][description]"
                                               value="{{ $item->description }}" placeholder="Description" required>
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-control" name="items[{{ $period->items->where('type', 'inflow')->count() + $loop->index }}][category]">
                                            <option value="operating" {{ $item->category == 'operating' ? 'selected' : '' }}>Operating</option>
                                            <option value="payroll" {{ $item->category == 'payroll' ? 'selected' : '' }}>Payroll</option>
                                            <option value="payables" {{ $item->category == 'payables' ? 'selected' : '' }}>Payables</option>
                                            <option value="capex" {{ $item->category == 'capex' ? 'selected' : '' }}>Capital Expense</option>
                                            <option value="taxes" {{ $item->category == 'taxes' ? 'selected' : '' }}>Taxes</option>
                                            <option value="debt" {{ $item->category == 'debt' ? 'selected' : '' }}>Debt Service</option>
                                            <option value="other" {{ $item->category == 'other' ? 'selected' : '' }}>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="number" class="form-control item-amount" name="items[{{ $period->items->where('type', 'inflow')->count() + $loop->index }}][amount]"
                                                   value="{{ $item->amount }}" step="0.01" min="0" data-type="outflow" required>
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-outline-danger btn-sm remove-item">
                                            <i class="mdi mdi-delete"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="total-row">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">Total Outflows:</span>
                            <span class="amount text-danger" id="totalOutflows">₦0.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Net Cash Flow Summary -->
                <div class="form-card">
                    <h6><i class="mdi mdi-calculator mr-2"></i>Period Summary</h6>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Opening Balance:</span>
                        <strong>₦{{ number_format($openingBalance, 2) }}</strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total Inflows:</span>
                        <strong class="text-success" id="summaryInflows">₦0.00</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total Outflows:</span>
                        <strong class="text-danger" id="summaryOutflows">₦0.00</strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Net Cash Flow:</span>
                        <strong id="netCashFlow">₦0.00</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Closing Balance:</span>
                        <strong id="closingBalance">₦0.00</strong>
                    </div>
                </div>

                <!-- Actuals -->
                <div class="form-card">
                    <h6><i class="mdi mdi-checkbox-marked-circle-outline mr-2"></i>Record Actuals</h6>
                    <div class="form-group">
                        <label>Actual Inflows</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">₦</span>
                            </div>
                            <input type="number" class="form-control" name="actual_inflows"
                                   value="{{ $period->actual_inflows ?? 0 }}" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label>Actual Outflows</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">₦</span>
                            </div>
                            <input type="number" class="form-control" name="actual_outflows"
                                   value="{{ $period->actual_outflows ?? 0 }}" step="0.01" min="0">
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="form-card">
                    <h6><i class="mdi mdi-cog mr-2"></i>Actions</h6>
                    <button type="submit" class="btn btn-primary btn-block mb-2">
                        <i class="mdi mdi-content-save mr-1"></i> Save Period
                    </button>
                    <a href="{{ route('accounting.cash-flow-forecast.show', $period->forecast_id) }}" class="btn btn-outline-secondary btn-block">
                        <i class="mdi mdi-close mr-1"></i> Cancel
                    </a>
                </div>

                <!-- Help -->
                <div class="form-card bg-light">
                    <h6><i class="mdi mdi-help-circle mr-2"></i>Help</h6>
                    <small class="text-muted">
                        <strong>Tips:</strong>
                        <ul class="pl-3 mb-0">
                            <li>Add all expected cash inflows and outflows for this period</li>
                            <li>Use categories to organize items</li>
                            <li>Record actuals once the period has passed to track variance</li>
                            <li>The closing balance automatically calculates from opening + net flow</li>
                        </ul>
                    </small>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Inflow Item Template -->
<template id="inflowTemplate">
    <div class="item-row inflow">
        <div class="row">
            <div class="col-md-5">
                <input type="hidden" name="items[INDEX][type]" value="inflow">
                <input type="text" class="form-control" name="items[INDEX][description]" placeholder="Description" required>
            </div>
            <div class="col-md-3">
                <select class="form-control" name="items[INDEX][category]">
                    <option value="sales">Sales</option>
                    <option value="receivables">Receivables</option>
                    <option value="investment">Investment</option>
                    <option value="financing">Financing</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="col-md-3">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text">₦</span>
                    </div>
                    <input type="number" class="form-control item-amount" name="items[INDEX][amount]"
                           step="0.01" min="0" data-type="inflow" required>
                </div>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-outline-danger btn-sm remove-item">
                    <i class="mdi mdi-delete"></i>
                </button>
            </div>
        </div>
    </div>
</template>

<!-- Outflow Item Template -->
<template id="outflowTemplate">
    <div class="item-row outflow">
        <div class="row">
            <div class="col-md-5">
                <input type="hidden" name="items[INDEX][type]" value="outflow">
                <input type="text" class="form-control" name="items[INDEX][description]" placeholder="Description" required>
            </div>
            <div class="col-md-3">
                <select class="form-control" name="items[INDEX][category]">
                    <option value="operating">Operating</option>
                    <option value="payroll">Payroll</option>
                    <option value="payables">Payables</option>
                    <option value="capex">Capital Expense</option>
                    <option value="taxes">Taxes</option>
                    <option value="debt">Debt Service</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="col-md-3">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text">₦</span>
                    </div>
                    <input type="number" class="form-control item-amount" name="items[INDEX][amount]"
                           step="0.01" min="0" data-type="outflow" required>
                </div>
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
<script>
$(document).ready(function() {
    var itemIndex = {{ $period->items->count() }};
    var openingBalance = {{ $openingBalance }};

    function formatCurrency(amount) {
        return '₦' + parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function calculateTotals() {
        var totalInflows = 0;
        var totalOutflows = 0;

        $('.item-amount[data-type="inflow"]').each(function() {
            totalInflows += parseFloat($(this).val()) || 0;
        });

        $('.item-amount[data-type="outflow"]').each(function() {
            totalOutflows += parseFloat($(this).val()) || 0;
        });

        var netFlow = totalInflows - totalOutflows;
        var closing = openingBalance + netFlow;

        $('#totalInflows, #summaryInflows').text(formatCurrency(totalInflows));
        $('#totalOutflows, #summaryOutflows').text(formatCurrency(totalOutflows));

        $('#netCashFlow').text(formatCurrency(netFlow)).removeClass('text-success text-danger')
            .addClass(netFlow >= 0 ? 'text-success' : 'text-danger');

        $('#closingBalance').text(formatCurrency(closing)).removeClass('text-success text-danger')
            .addClass(closing >= 0 ? 'text-success' : 'text-danger');
    }

    // Initial calculation
    calculateTotals();

    // On amount change
    $(document).on('input', '.item-amount', function() {
        calculateTotals();
    });

    // Add item
    $('.add-item').on('click', function() {
        var type = $(this).data('type');
        var template = $('#' + type + 'Template').html();
        template = template.replace(/INDEX/g, itemIndex);

        $('#' + type + 'Items').append(template);
        itemIndex++;
    });

    // Remove item
    $(document).on('click', '.remove-item', function() {
        $(this).closest('.item-row').remove();
        calculateTotals();
    });
});
</script>
@endpush
