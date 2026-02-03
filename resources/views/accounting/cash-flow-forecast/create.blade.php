{{--
    Create Cash Flow Forecast
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.6
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')
@php use Illuminate\Support\Str; @endphp

@section('title', 'Create Cash Flow Forecast')
@section('page_name', 'Accounting')
@section('subpage_name', 'Create Forecast')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Cash Flow Forecast', 'url' => route('accounting.cash-flow-forecast.index'), 'icon' => 'mdi-chart-timeline-variant'],
        ['label' => 'Create', 'url' => '#', 'icon' => 'mdi-plus']
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
.frequency-card {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 15px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}
.frequency-card:hover { border-color: #667eea; }
.frequency-card.selected {
    border-color: #667eea;
    background: linear-gradient(135deg, #667eea22 0%, #764ba222 100%);
}
.frequency-card .icon { font-size: 2rem; color: #667eea; margin-bottom: 10px; }
.pattern-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    border-radius: 8px;
    background: #f8f9fa;
    margin-bottom: 8px;
}
.pattern-item:hover { background: #e9ecef; }
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

    <form action="{{ route('accounting.cash-flow-forecast.store') }}" method="POST">
        @csrf

        <div class="row">
            <div class="col-lg-8">
                <!-- Basic Information -->
                <div class="form-section">
                    <h6><i class="mdi mdi-information-outline mr-2"></i>Forecast Information</h6>
                    <div class="alert alert-info" style="padding: 10px 15px; font-size: 0.9rem;">
                        <i class="mdi mdi-lightbulb-outline"></i>
                        <strong>Getting Started:</strong> Name your forecast descriptively (e.g., "Q1 2026 13-Week Forecast").
                        Choose dates and frequency - the system will automatically create periods. After creation, you'll add line items for each period.
                    </div>
                    <div class="form-group">
                        <label>Forecast Name <span class="text-danger">*</span></label>
                        <input type="text" name="forecast_name" class="form-control @error('forecast_name') is-invalid @enderror"
                               value="{{ old('forecast_name') }}" required placeholder="e.g., Q1 2026 Cash Flow Forecast">
                        <small class="form-text text-muted">Choose a clear name that indicates time period and purpose</small>
                        @error('forecast_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label>Scenario</label>
                        <select name="scenario" class="form-control">
                            <option value="base" {{ old('scenario', 'base') == 'base' ? 'selected' : '' }}>Base case (Most likely outcome)</option>
                            <option value="optimistic" {{ old('scenario') == 'optimistic' ? 'selected' : '' }}>Optimistic (Best case)</option>
                            <option value="pessimistic" {{ old('scenario') == 'pessimistic' ? 'selected' : '' }}>Pessimistic (Worst case)</option>
                        </select>
                        <small class="form-text text-muted">Create multiple forecasts with different scenarios to plan for various outcomes</small>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2"
                                  placeholder="Optional notes about this forecast">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <!-- Forecast Period -->
                <div class="form-section">
                    <h6><i class="mdi mdi-calendar mr-2"></i>Forecast Period</h6>
                    <p class="text-muted small mb-3">
                        <i class="mdi mdi-information-outline"></i>
                        Select the date range for your forecast. The system will divide this into periods based on your chosen frequency below.
                    </p>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Start Date <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror"
                                       value="{{ old('start_date', date('Y-m-01')) }}" required>
                                <small class="form-text text-muted">First day of forecast period</small>
                                @error('start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>End Date <span class="text-danger">*</span></label>
                                <input type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror"
                                       value="{{ old('end_date', date('Y-m-t', strtotime('+3 months'))) }}" required>
                                <small class="form-text text-muted">Last day of forecast period</small>
                                @error('end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Frequency -->
                <div class="form-section">
                    <h6><i class="mdi mdi-repeat mr-2"></i>Forecast Type</h6>
                    <p class="text-muted small mb-3">
                        <i class="mdi mdi-lightbulb-outline"></i>
                        <strong>Weekly</strong> gives detailed short-term visibility (13 weeks recommended).
                        <strong>Monthly</strong> is ideal for quarterly/annual planning.
                        <strong>Quarterly/Annual</strong> for long-term strategic forecasts.
                    </p>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="frequency-card {{ old('forecast_type', 'weekly') == 'weekly' ? 'selected' : '' }}" data-frequency="weekly">
                                <div class="icon"><i class="mdi mdi-calendar-week"></i></div>
                                <div class="font-weight-bold">Weekly</div>
                                <small class="text-muted">13-week rolling</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="frequency-card {{ old('forecast_type') == 'monthly' ? 'selected' : '' }}" data-frequency="monthly">
                                <div class="icon"><i class="mdi mdi-calendar-month"></i></div>
                                <div class="font-weight-bold">Monthly</div>
                                <small class="text-muted">Month-by-month</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="frequency-card {{ old('forecast_type') == 'quarterly' ? 'selected' : '' }}" data-frequency="quarterly">
                                <div class="icon"><i class="mdi mdi-calendar-range"></i></div>
                                <div class="font-weight-bold">Quarterly</div>
                                <small class="text-muted">3-month periods</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="frequency-card {{ old('forecast_type') == 'annual' ? 'selected' : '' }}" data-frequency="annual">
                                <div class="icon"><i class="mdi mdi-calendar"></i></div>
                                <div class="font-weight-bold">Annual</div>
                                <small class="text-muted">Full year</small>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="forecast_type" id="forecast_type" value="{{ old('forecast_type', 'weekly') }}" required>
                    @error('forecast_type')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="form-section">
                    <h6><i class="mdi mdi-cog mr-2"></i>Actions</h6>
                    <button type="submit" class="btn btn-primary btn-block mb-2">
                        <i class="mdi mdi-content-save mr-1"></i> Create Forecast
                    </button>
                    <a href="{{ route('accounting.cash-flow-forecast.index') }}" class="btn btn-outline-secondary btn-block">
                        <i class="mdi mdi-close mr-1"></i> Cancel
                    </a>
                </div>

                <!-- Help -->
                <div class="form-section bg-light">
                    <h6><i class="mdi mdi-help-circle mr-2"></i>How It Works</h6>
                    <ol class="pl-3 mb-0 small">
                        <li class="mb-2"><strong>Create:</strong> Set up your forecast with dates and frequency</li>
                        <li class="mb-2"><strong>Plan:</strong> Add line items for each period (inflows like revenue, outflows like expenses)</li>
                        <li class="mb-2"><strong>Track:</strong> As periods pass, record actual results</li>
                        <li class="mb-2"><strong>Analyze:</strong> Compare actual vs forecast to improve accuracy</li>
                    </ol>
                    <div class="alert alert-info mt-3 mb-0" style="padding: 8px 12px; font-size: 0.85rem;">
                        <i class="mdi mdi-lightbulb-outline"></i>
                        <strong>Tip:</strong> Start with a 13-week (weekly) forecast for immediate cash visibility.
                    </div>
                </div>

                <!-- Patterns -->
                @if($patterns->count() > 0)
                <div class="form-section">
                    <h6><i class="mdi mdi-repeat mr-2"></i>Available Patterns ({{ $patterns->count() }})</h6>
                    <p class="text-muted small mb-3">
                        <i class="mdi mdi-information-outline"></i>
                        These recurring patterns can be applied to periods after creating the forecast to speed up data entry.
                    </p>
                    @foreach($patterns as $pattern)
                        <div class="pattern-item">
                            <div>
                                <strong>{{ $pattern->pattern_name }}</strong><br>
                                <small class="text-muted">
                                    {{ str_replace('_', ' ', ucfirst($pattern->cash_flow_category)) }} - ₦{{ number_format($pattern->expected_amount, 0) }} / {{ str_replace('_', ' ', ucfirst($pattern->frequency)) }}
                                </small>
                            </div>
                            <span class="badge badge-{{ Str::contains($pattern->cash_flow_category, 'inflow') ? 'success' : 'danger' }}">
                                {{ Str::contains($pattern->cash_flow_category, 'inflow') ? 'Inflow' : 'Outflow' }}
                            </span>
                        </div>
                    @endforeach
                </div>
                @endif

                <!-- Help -->
                <div class="form-section">
                    <h6><i class="mdi mdi-help-circle mr-2"></i>Help</h6>
                    <p class="text-muted small mb-2">
                        <strong>Daily:</strong> Best for short-term forecasts (1-4 weeks) when cash flow is tight.
                    </p>
                    <p class="text-muted small mb-2">
                        <strong>Weekly:</strong> Ideal for 1-3 month forecasts. Balances detail with manageability.
                    </p>
                    <p class="text-muted small mb-0">
                        <strong>Monthly:</strong> Best for long-term planning (3-12 months).
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
    // Forecast type selection
    $('.frequency-card').on('click', function() {
        $('.frequency-card').removeClass('selected');
        $(this).addClass('selected');
        $('#forecast_type').val($(this).data('frequency'));
    });
});
</script>
@endpush
