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
        ['label' => $period->forecast->forecast_name, 'url' => route('accounting.cash-flow-forecast.show', $period->forecast_id), 'icon' => 'mdi-eye'],
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

@php
    $inflowCategories = [
        'operating_inflow' => 'Operating Inflow',
        'investing_inflow' => 'Investing Inflow',
        'financing_inflow' => 'Financing Inflow',
    ];

    $outflowCategories = [
        'operating_outflow' => 'Operating Outflow',
        'investing_outflow' => 'Investing Outflow',
        'financing_outflow' => 'Financing Outflow',
    ];

    $inflowItems = $period->items->filter(fn($item) => $item->isInflow());
    $outflowItems = $period->items->filter(fn($item) => $item->isOutflow());
@endphp

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

    <div class="alert alert-info alert-dismissible fade show">
        <i class="mdi mdi-lightbulb-outline"></i>
        <strong>Period Planning:</strong> Add all expected cash inflows (revenue, collections) and outflows (expenses, payments) for this period.
        The system automatically calculates net cash flow and closing balance. Use categories to organize items (Operating = day-to-day, Investing = assets, Financing = loans/equity).
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>

    <!-- Period Info Header -->
    <div class="period-info">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h4 class="mb-1">{{ $period->forecast->forecast_name }}</h4>
                <div>
                    <span class="badge badge-light">
                        {{ optional($period->period_start_date)->format('M d, Y') }} -
                        {{ optional($period->period_end_date)->format('M d, Y') }}
                    </span>
                    <span class="badge badge-{{ $period->forecast->status == 'active' ? 'success' : 'secondary' }} ml-1">
                        {{ ucfirst($period->forecast->forecast_type) }}
                    </span>
                </div>
            </div>
            <div class="col-md-4 text-md-right mt-3 mt-md-0">
                <a href="{{ route('accounting.cash-flow-forecast.show', $period->forecast_id) }}" class="btn btn-light btn-sm">
                    <i class="mdi mdi-arrow-left mr-1"></i> Back to Forecast
                </a>
                <a href="{{ route('accounting.cash-flow-forecast.show', [$period->forecast_id, 'tab' => 'details']) }}" class="btn btn-warning btn-sm ml-2">
                    <i class="mdi mdi-pencil mr-1"></i> Edit Forecast Details
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
                    <p class="text-muted small mb-3">
                        <i class="mdi mdi-information-outline"></i>
                        Add all expected cash <strong>receipts</strong> (e.g., patient revenue, HMO payments, collections).
                        <span class="text-success">Examples: Cash sales ₦500,000 | HMO receivables ₦300,000 | Investment income ₦50,000</span>
                    </p>

                    <div id="inflowItems">
                        @foreach($inflowItems as $index => $item)
                            <div class="item-row inflow">
                                <div class="row">
                                    <div class="col-md-5">
                                        <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                                        <input type="text" class="form-control" name="items[{{ $index }}][item_description]"
                                               value="{{ $item->item_description }}" placeholder="Description" required>
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-control" name="items[{{ $index }}][cash_flow_category]">
                                            @foreach($inflowCategories as $value => $label)
                                                <option value="{{ $value }}" {{ $item->cash_flow_category === $value ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="number" class="form-control item-amount" name="items[{{ $index }}][forecasted_amount]"
                                                   value="{{ $item->forecasted_amount }}" step="0.01" min="0" data-type="inflow" required>
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
                    <p class="text-muted small mb-3">
                        <i class="mdi mdi-information-outline"></i>
                        Add all expected cash <strong>payments</strong> (e.g., salaries, suppliers, rent).
                        <span class="text-danger">Examples: Staff salaries ₦400,000 | Drug suppliers ₦250,000 | Utilities ₦80,000 | Loan payment ₦100,000</span>
                    </p>

                    <div id="outflowItems">
                        @foreach($outflowItems as $outflowIndex => $item)
                            <div class="item-row outflow">
                                <div class="row">
                                    <div class="col-md-5">
                                        @php $index = $inflowItems->count() + $outflowIndex; @endphp
                                        <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                                        <input type="text" class="form-control" name="items[{{ $index }}][item_description]"
                                               value="{{ $item->item_description }}" placeholder="Description" required>
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-control" name="items[{{ $index }}][cash_flow_category]">
                                            @foreach($outflowCategories as $value => $label)
                                                <option value="{{ $value }}" {{ $item->cash_flow_category === $value ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="number" class="form-control item-amount" name="items[{{ $index }}][forecasted_amount]"
                                                   value="{{ $item->forecasted_amount }}" step="0.01" min="0" data-type="outflow" required>
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
                    <h6><i class="mdi mdi-help-circle mr-2"></i>Quick Guide</h6>
                    <small class="text-muted">
                        <strong>Category Guide:</strong>
                        <ul class="pl-3 mb-2">
                            <li><strong>Operating:</strong> Day-to-day business (revenue, salaries, supplies)</li>
                            <li><strong>Investing:</strong> Asset purchases/sales (equipment, property)</li>
                            <li><strong>Financing:</strong> Loans, equity, dividends</li>
                        </ul>
                        <strong>Best Practices:</strong>
                        <ul class="pl-3 mb-0">
                            <li>Be specific in descriptions (e.g., "January Staff Salaries" not just "Salaries")</li>
                            <li>Group similar items when appropriate</li>
                            <li>Review actuals vs forecast regularly to improve accuracy</li>
                            <li>Net flow = Inflows - Outflows; Closing = Opening + Net flow</li>
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
                <input type="text" class="form-control" name="items[INDEX][item_description]" placeholder="Description" required>
            </div>
            <div class="col-md-3">
                <select class="form-control" name="items[INDEX][cash_flow_category]">
                    @foreach($inflowCategories as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text">₦</span>
                    </div>
                    <input type="number" class="form-control item-amount" name="items[INDEX][forecasted_amount]"
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
                <input type="text" class="form-control" name="items[INDEX][item_description]" placeholder="Description" required>
            </div>
            <div class="col-md-3">
                <select class="form-control" name="items[INDEX][cash_flow_category]">
                    @foreach($outflowCategories as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text">₦</span>
                    </div>
                    <input type="number" class="form-control item-amount" name="items[INDEX][forecasted_amount]"
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
