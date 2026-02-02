@extends('admin.layouts.app')
@section('title', 'Create KPI')
@section('page_name', 'Accounting')
@section('subpage_name', 'Create KPI')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'KPI Dashboard', 'url' => route('accounting.kpi.dashboard'), 'icon' => 'mdi-chart-box'],
    ['label' => 'KPI Definitions', 'url' => route('accounting.kpi.index'), 'icon' => 'mdi-format-list-bulleted'],
    ['label' => 'Create', 'url' => '#', 'icon' => 'mdi-plus']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        <form action="{{ route('accounting.kpi.store') }}" method="POST" id="kpiForm">
            @csrf
            <div class="row">
                <!-- Main Form -->
                <div class="col-lg-8">
                    <!-- Basic Information -->
                    <div class="card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-information mr-2"></i>Basic Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="kpi_code">KPI Code <span class="text-danger">*</span></label>
                                        <input type="text" name="kpi_code" id="kpi_code"
                                               class="form-control @error('kpi_code') is-invalid @enderror"
                                               value="{{ old('kpi_code') }}" required
                                               placeholder="e.g., CR, QR, ROA">
                                        @error('kpi_code')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="kpi_name">KPI Name <span class="text-danger">*</span></label>
                                        <input type="text" name="kpi_name" id="kpi_name"
                                               class="form-control @error('kpi_name') is-invalid @enderror"
                                               value="{{ old('kpi_name') }}" required
                                               placeholder="e.g., Current Ratio, Quick Ratio">
                                        @error('kpi_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="category">Category <span class="text-danger">*</span></label>
                                        <select name="category" id="category" class="form-control @error('category') is-invalid @enderror" required>
                                            <option value="">Select Category</option>
                                            <option value="liquidity" {{ old('category') == 'liquidity' ? 'selected' : '' }}>Liquidity</option>
                                            <option value="profitability" {{ old('category') == 'profitability' ? 'selected' : '' }}>Profitability</option>
                                            <option value="efficiency" {{ old('category') == 'efficiency' ? 'selected' : '' }}>Efficiency</option>
                                            <option value="solvency" {{ old('category') == 'solvency' ? 'selected' : '' }}>Solvency</option>
                                            <option value="leverage" {{ old('category') == 'leverage' ? 'selected' : '' }}>Leverage</option>
                                        </select>
                                        @error('category')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="frequency">Calculation Frequency <span class="text-danger">*</span></label>
                                        <select name="frequency" id="frequency"
                                                class="form-control @error('frequency') is-invalid @enderror" required>
                                            <option value="">Select Frequency</option>
                                            <option value="daily" {{ old('frequency') == 'daily' ? 'selected' : '' }}>Daily</option>
                                            <option value="weekly" {{ old('frequency') == 'weekly' ? 'selected' : '' }}>Weekly</option>
                                            <option value="monthly" {{ old('frequency', 'monthly') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                            <option value="quarterly" {{ old('frequency') == 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                                            <option value="annually" {{ old('frequency') == 'annually' ? 'selected' : '' }}>Annually</option>
                                        </select>
                                        @error('frequency')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea name="description" id="description" rows="2"
                                          class="form-control @error('description') is-invalid @enderror"
                                          placeholder="What does this KPI measure?">{{ old('description') }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Formula Configuration -->
                    <div class="card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-function mr-2"></i>Formula Configuration</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="mdi mdi-information"></i>
                                Define the formula to calculate this KPI. Use account codes or predefined variables.
                            </div>
                            <div class="form-group">
                                <label for="calculation_formula">Formula <span class="text-danger">*</span></label>
                                <input type="text" name="calculation_formula" id="calculation_formula"
                                       class="form-control font-monospace @error('calculation_formula') is-invalid @enderror"
                                       value="{{ old('calculation_formula') }}" required
                                       placeholder="e.g., {current_assets} / {current_liabilities}">
                                @error('calculation_formula')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">
                                    Available variables: {revenue}, {expenses}, {net_income}, {total_assets}, {total_liabilities},
                                    {current_assets}, {current_liabilities}, {equity}, {cash}, {inventory}, {receivables}, {payables}
                                </small>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="data_source">Data Source</label>
                                        <select name="data_source" id="data_source" class="form-control">
                                            <option value="trial_balance" {{ old('data_source', 'trial_balance') == 'trial_balance' ? 'selected' : '' }}>Trial Balance</option>
                                            <option value="financial_statements" {{ old('data_source') == 'financial_statements' ? 'selected' : '' }}>Financial Statements</option>
                                            <option value="custom" {{ old('data_source') == 'custom' ? 'selected' : '' }}>Custom Query</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="unit">Unit <span class="text-danger">*</span></label>
                                        <select name="unit" id="unit" class="form-control @error('unit') is-invalid @enderror" required>
                                            <option value="">Select Unit</option>
                                            <option value="ratio" {{ old('unit', 'ratio') == 'ratio' ? 'selected' : '' }}>Ratio (x)</option>
                                            <option value="percentage" {{ old('unit') == 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
                                            <option value="currency" {{ old('unit') == 'currency' ? 'selected' : '' }}>Currency (₦)</option>
                                            <option value="days" {{ old('unit') == 'days' ? 'selected' : '' }}>Days</option>
                                            <option value="number" {{ old('unit') == 'number' ? 'selected' : '' }}>Number</option>
                                        </select>
                                        @error('unit')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Threshold Configuration -->
                    <div class="card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-alert-circle mr-2"></i>Threshold Configuration</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="target_value">Target Value</label>
                                        <input type="number" name="target_value" id="target_value" step="0.01"
                                               class="form-control" value="{{ old('target_value') }}"
                                               placeholder="Ideal value for this KPI">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="comparison_operator">Comparison <span class="text-danger">*</span></label>
                                        <select name="comparison_operator" id="comparison_operator" class="form-control" required>
                                            <option value="higher_better" {{ old('comparison_operator', 'higher_better') == 'higher_better' ? 'selected' : '' }}>Higher is Better</option>
                                            <option value="lower_better" {{ old('comparison_operator') == 'lower_better' ? 'selected' : '' }}>Lower is Better</option>
                                            <option value="target_range" {{ old('comparison_operator') == 'target_range' ? 'selected' : '' }}>Target Range</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <h6 class="text-muted mb-3">Alert Thresholds</h6>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="warning_threshold_low">Warning Low</label>
                                        <input type="number" name="warning_threshold_low" id="warning_threshold_low" step="0.01"
                                               class="form-control" value="{{ old('warning_threshold_low') }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="warning_threshold_high">Warning High</label>
                                        <input type="number" name="warning_threshold_high" id="warning_threshold_high" step="0.01"
                                               class="form-control" value="{{ old('warning_threshold_high') }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="critical_threshold_low">Critical Low</label>
                                        <input type="number" name="critical_threshold_low" id="critical_threshold_low" step="0.01"
                                               class="form-control" value="{{ old('critical_threshold_low') }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="critical_threshold_high">Critical High</label>
                                        <input type="number" name="critical_threshold_high" id="critical_threshold_high" step="0.01"
                                               class="form-control" value="{{ old('critical_threshold_high') }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Display Settings -->
                    <div class="card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-palette mr-2"></i>Display Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="chart_type">Chart Type</label>
                                <select name="chart_type" id="chart_type" class="form-control">
                                    <option value="gauge" {{ old('chart_type', 'gauge') == 'gauge' ? 'selected' : '' }}>Gauge</option>
                                    <option value="line" {{ old('chart_type') == 'line' ? 'selected' : '' }}>Line Chart</option>
                                    <option value="bar" {{ old('chart_type') == 'bar' ? 'selected' : '' }}>Bar Chart</option>
                                    <option value="number" {{ old('chart_type') == 'number' ? 'selected' : '' }}>Number Only</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="display_order">Display Order</label>
                                <input type="number" name="display_order" id="display_order"
                                       class="form-control" value="{{ old('display_order', 0) }}" min="0">
                                <small class="text-muted">Lower numbers appear first</small>
                            </div>
                            <hr>
                            <div class="form-group mb-0">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="show_on_dashboard"
                                           name="show_on_dashboard" value="1" {{ old('show_on_dashboard', true) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="show_on_dashboard">Show on Dashboard</label>
                                </div>
                            </div>
                            <div class="form-group mb-0 mt-2">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="is_active"
                                           name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="is_active">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Common KPIs Reference -->
                    <div class="card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-lightbulb mr-2"></i>Quick Templates</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <a href="#" class="list-group-item list-group-item-action kpi-template"
                                   data-code="CR" data-name="Current Ratio" data-category="liquidity"
                                   data-formula="{current_assets} / {current_liabilities}" data-unit="ratio"
                                   data-comparison="higher_better" data-target="2"
                                   data-warning-low="1.5" data-critical-low="1">
                                    <strong>Current Ratio</strong>
                                    <small class="d-block text-muted">Current Assets / Current Liabilities</small>
                                </a>
                                <a href="#" class="list-group-item list-group-item-action kpi-template"
                                   data-code="QR" data-name="Quick Ratio" data-category="liquidity"
                                   data-formula="({current_assets} - {inventory}) / {current_liabilities}" data-unit="ratio"
                                   data-comparison="higher_better" data-target="1"
                                   data-warning-low="0.8" data-critical-low="0.5">
                                    <strong>Quick Ratio</strong>
                                    <small class="d-block text-muted">(Current Assets - Inventory) / Current Liabilities</small>
                                </a>
                                <a href="#" class="list-group-item list-group-item-action kpi-template"
                                   data-code="ROA" data-name="Return on Assets" data-category="profitability"
                                   data-formula="({net_income} / {total_assets}) * 100" data-unit="percentage"
                                   data-comparison="higher_better" data-target="10"
                                   data-warning-low="5" data-critical-low="2">
                                    <strong>Return on Assets</strong>
                                    <small class="d-block text-muted">Net Income / Total Assets × 100</small>
                                </a>
                                <a href="#" class="list-group-item list-group-item-action kpi-template"
                                   data-code="ROE" data-name="Return on Equity" data-category="profitability"
                                   data-formula="({net_income} / {equity}) * 100" data-unit="percentage"
                                   data-comparison="higher_better" data-target="15"
                                   data-warning-low="10" data-critical-low="5">
                                    <strong>Return on Equity</strong>
                                    <small class="d-block text-muted">Net Income / Equity × 100</small>
                                </a>
                                <a href="#" class="list-group-item list-group-item-action kpi-template"
                                   data-code="DE" data-name="Debt to Equity Ratio" data-category="leverage"
                                   data-formula="{total_liabilities} / {equity}" data-unit="ratio"
                                   data-comparison="lower_better" data-target="1"
                                   data-warning-high="2" data-critical-high="3">
                                    <strong>Debt to Equity</strong>
                                    <small class="d-block text-muted">Total Liabilities / Equity</small>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="card-modern card-modern">
                        <div class="card-body">
                            <button type="submit" class="btn btn-success btn-block mb-2">
                                <i class="mdi mdi-check"></i> Create KPI
                            </button>
                            <a href="{{ route('accounting.kpi.index') }}" class="btn btn-secondary btn-block">
                                <i class="mdi mdi-arrow-left"></i> Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // KPI Template Click
    $('.kpi-template').on('click', function(e) {
        e.preventDefault();
        var template = $(this);

        $('#kpi_code').val(template.data('code'));
        $('#kpi_name').val(template.data('name'));
        $('#category').val(template.data('category'));
        $('#formula').val(template.data('formula'));
        $('#unit').val(template.data('unit'));
        $('#comparison_operator').val(template.data('comparison'));
        $('#target_value').val(template.data('target'));
        $('#warning_threshold_low').val(template.data('warning-low') || '');
        $('#warning_threshold_high').val(template.data('warning-high') || '');
        $('#critical_threshold_low').val(template.data('critical-low') || '');
        $('#critical_threshold_high').val(template.data('critical-high') || '');

        toastr.info('Template applied: ' + template.data('name'));
    });

    // Auto-generate code from name
    $('#kpi_name').on('blur', function() {
        if (!$('#kpi_code').val()) {
            var name = $(this).val();
            var code = name.split(' ').map(function(word) {
                return word.charAt(0).toUpperCase();
            }).join('');
            $('#kpi_code').val(code);
        }
    });
});
</script>
@endpush
