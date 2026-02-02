@extends('admin.layouts.app')
@section('title', 'Edit KPI')
@section('page_name', 'Accounting')
@section('subpage_name', 'Edit KPI')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'KPI Dashboard', 'url' => route('accounting.kpi.dashboard'), 'icon' => 'mdi-chart-box'],
    ['label' => 'KPI Definitions', 'url' => route('accounting.kpi.index'), 'icon' => 'mdi-format-list-bulleted'],
    ['label' => 'Edit', 'url' => '#', 'icon' => 'mdi-pencil']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        <form action="{{ route('accounting.kpi.update', $kpi->id) }}" method="POST" id="kpiForm">
            @csrf
            @method('PUT')
            <div class="row">
                <!-- Main Form -->
                <div class="col-lg-8">
                    <!-- Basic Information -->
                    <div class="card card-modern mb-4">
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
                                               value="{{ old('kpi_code', $kpi->kpi_code) }}" required>
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
                                               value="{{ old('kpi_name', $kpi->kpi_name) }}" required>
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
                                            <option value="liquidity" {{ old('category', $kpi->category) == 'liquidity' ? 'selected' : '' }}>Liquidity</option>
                                            <option value="profitability" {{ old('category', $kpi->category) == 'profitability' ? 'selected' : '' }}>Profitability</option>
                                            <option value="efficiency" {{ old('category', $kpi->category) == 'efficiency' ? 'selected' : '' }}>Efficiency</option>
                                            <option value="solvency" {{ old('category', $kpi->category) == 'solvency' ? 'selected' : '' }}>Solvency</option>
                                            <option value="leverage" {{ old('category', $kpi->category) == 'leverage' ? 'selected' : '' }}>Leverage</option>
                                        </select>
                                        @error('category')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="calculation_frequency">Calculation Frequency <span class="text-danger">*</span></label>
                                        <select name="calculation_frequency" id="calculation_frequency"
                                                class="form-control @error('calculation_frequency') is-invalid @enderror" required>
                                            <option value="">Select Frequency</option>
                                            <option value="daily" {{ old('calculation_frequency', $kpi->calculation_frequency) == 'daily' ? 'selected' : '' }}>Daily</option>
                                            <option value="weekly" {{ old('calculation_frequency', $kpi->calculation_frequency) == 'weekly' ? 'selected' : '' }}>Weekly</option>
                                            <option value="monthly" {{ old('calculation_frequency', $kpi->calculation_frequency) == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                            <option value="quarterly" {{ old('calculation_frequency', $kpi->calculation_frequency) == 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                                            <option value="yearly" {{ old('calculation_frequency', $kpi->calculation_frequency) == 'yearly' ? 'selected' : '' }}>Yearly</option>
                                        </select>
                                        @error('calculation_frequency')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea name="description" id="description" rows="2"
                                          class="form-control @error('description') is-invalid @enderror">{{ old('description', $kpi->description) }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Formula Configuration -->
                    <div class="card card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-function mr-2"></i>Formula Configuration</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="formula">Formula <span class="text-danger">*</span></label>
                                <input type="text" name="formula" id="formula"
                                       class="form-control font-monospace @error('formula') is-invalid @enderror"
                                       value="{{ old('formula', $kpi->formula) }}" required>
                                @error('formula')
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
                                            <option value="trial_balance" {{ old('data_source', $kpi->data_source) == 'trial_balance' ? 'selected' : '' }}>Trial Balance</option>
                                            <option value="financial_statements" {{ old('data_source', $kpi->data_source) == 'financial_statements' ? 'selected' : '' }}>Financial Statements</option>
                                            <option value="custom" {{ old('data_source', $kpi->data_source) == 'custom' ? 'selected' : '' }}>Custom Query</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="unit">Unit <span class="text-danger">*</span></label>
                                        <select name="unit" id="unit" class="form-control @error('unit') is-invalid @enderror" required>
                                            <option value="">Select Unit</option>
                                            <option value="ratio" {{ old('unit', $kpi->unit) == 'ratio' ? 'selected' : '' }}>Ratio (x)</option>
                                            <option value="percentage" {{ old('unit', $kpi->unit) == 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
                                            <option value="currency" {{ old('unit', $kpi->unit) == 'currency' ? 'selected' : '' }}>Currency (₦)</option>
                                            <option value="days" {{ old('unit', $kpi->unit) == 'days' ? 'selected' : '' }}>Days</option>
                                            <option value="number" {{ old('unit', $kpi->unit) == 'number' ? 'selected' : '' }}>Number</option>
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
                    <div class="card card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-alert-circle mr-2"></i>Threshold Configuration</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="target_value">Target Value</label>
                                        <input type="number" name="target_value" id="target_value" step="0.01"
                                               class="form-control" value="{{ old('target_value', $kpi->target_value) }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="comparison_operator">Comparison <span class="text-danger">*</span></label>
                                        <select name="comparison_operator" id="comparison_operator" class="form-control" required>
                                            <option value="higher_better" {{ old('comparison_operator', $kpi->comparison_operator) == 'higher_better' ? 'selected' : '' }}>Higher is Better</option>
                                            <option value="lower_better" {{ old('comparison_operator', $kpi->comparison_operator) == 'lower_better' ? 'selected' : '' }}>Lower is Better</option>
                                            <option value="target_range" {{ old('comparison_operator', $kpi->comparison_operator) == 'target_range' ? 'selected' : '' }}>Target Range</option>
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
                                               class="form-control" value="{{ old('warning_threshold_low', $kpi->warning_threshold_low) }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="warning_threshold_high">Warning High</label>
                                        <input type="number" name="warning_threshold_high" id="warning_threshold_high" step="0.01"
                                               class="form-control" value="{{ old('warning_threshold_high', $kpi->warning_threshold_high) }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="critical_threshold_low">Critical Low</label>
                                        <input type="number" name="critical_threshold_low" id="critical_threshold_low" step="0.01"
                                               class="form-control" value="{{ old('critical_threshold_low', $kpi->critical_threshold_low) }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="critical_threshold_high">Critical High</label>
                                        <input type="number" name="critical_threshold_high" id="critical_threshold_high" step="0.01"
                                               class="form-control" value="{{ old('critical_threshold_high', $kpi->critical_threshold_high) }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Display Settings -->
                    <div class="card card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-palette mr-2"></i>Display Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="chart_type">Chart Type</label>
                                <select name="chart_type" id="chart_type" class="form-control">
                                    <option value="gauge" {{ old('chart_type', $kpi->chart_type) == 'gauge' ? 'selected' : '' }}>Gauge</option>
                                    <option value="line" {{ old('chart_type', $kpi->chart_type) == 'line' ? 'selected' : '' }}>Line Chart</option>
                                    <option value="bar" {{ old('chart_type', $kpi->chart_type) == 'bar' ? 'selected' : '' }}>Bar Chart</option>
                                    <option value="number" {{ old('chart_type', $kpi->chart_type) == 'number' ? 'selected' : '' }}>Number Only</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="display_order">Display Order</label>
                                <input type="number" name="display_order" id="display_order"
                                       class="form-control" value="{{ old('display_order', $kpi->display_order) }}" min="0">
                            </div>
                            <hr>
                            <div class="form-group mb-0">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="show_on_dashboard"
                                           name="show_on_dashboard" value="1" {{ old('show_on_dashboard', $kpi->show_on_dashboard) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="show_on_dashboard">Show on Dashboard</label>
                                </div>
                            </div>
                            <div class="form-group mb-0 mt-2">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="is_active"
                                           name="is_active" value="1" {{ old('is_active', $kpi->is_active) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="is_active">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- KPI Summary -->
                    <div class="card card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-chart-box mr-2"></i>KPI Summary</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted">Created:</td>
                                    <td>{{ $kpi->created_at->format('M d, Y') }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Last Updated:</td>
                                    <td>{{ $kpi->updated_at->format('M d, Y H:i') }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Total Records:</td>
                                    <td>{{ $kpi->values_count ?? 0 }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="card card-modern">
                        <div class="card-body">
                            <button type="submit" class="btn btn-primary btn-block mb-2">
                                <i class="mdi mdi-check"></i> Update KPI
                            </button>
                            <a href="{{ route('accounting.kpi.history', $kpi->id) }}" class="btn btn-outline-info btn-block mb-2">
                                <i class="mdi mdi-chart-line"></i> View History
                            </a>
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
