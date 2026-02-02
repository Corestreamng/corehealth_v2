{{--
    Create Cash Flow Forecast
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.6
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

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
                    <div class="form-group">
                        <label>Forecast Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}" required placeholder="e.g., Q1 2024 Cash Flow Forecast">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="2"
                                  placeholder="Optional description or notes about this forecast">{{ old('description') }}</textarea>
                    </div>
                </div>

                <!-- Forecast Period -->
                <div class="form-section">
                    <h6><i class="mdi mdi-calendar mr-2"></i>Forecast Period</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Start Date <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror"
                                       value="{{ old('start_date', date('Y-m-01')) }}" required>
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
                                @error('end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Frequency -->
                <div class="form-section">
                    <h6><i class="mdi mdi-repeat mr-2"></i>Forecast Frequency</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="frequency-card {{ old('frequency') == 'daily' ? 'selected' : '' }}" data-frequency="daily">
                                <div class="icon"><i class="mdi mdi-calendar-today"></i></div>
                                <div class="font-weight-bold">Daily</div>
                                <small class="text-muted">Day-by-day tracking</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="frequency-card {{ old('frequency') == 'weekly' || !old('frequency') ? 'selected' : '' }}" data-frequency="weekly">
                                <div class="icon"><i class="mdi mdi-calendar-week"></i></div>
                                <div class="font-weight-bold">Weekly</div>
                                <small class="text-muted">Week-by-week tracking</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="frequency-card {{ old('frequency') == 'monthly' ? 'selected' : '' }}" data-frequency="monthly">
                                <div class="icon"><i class="mdi mdi-calendar-month"></i></div>
                                <div class="font-weight-bold">Monthly</div>
                                <small class="text-muted">Month-by-month tracking</small>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="frequency" id="frequency" value="{{ old('frequency', 'weekly') }}" required>
                    @error('frequency')
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

                <!-- Patterns -->
                @if($patterns->count() > 0)
                <div class="form-section">
                    <h6><i class="mdi mdi-repeat mr-2"></i>Available Patterns</h6>
                    <p class="text-muted small mb-3">These recurring patterns can be applied after creating the forecast.</p>
                    @foreach($patterns as $pattern)
                        <div class="pattern-item">
                            <div>
                                <strong>{{ $pattern->name }}</strong><br>
                                <small class="text-muted">
                                    {{ ucfirst($pattern->type) }} - ₦{{ number_format($pattern->amount, 0) }} / {{ ucfirst($pattern->frequency) }}
                                </small>
                            </div>
                            <span class="badge badge-{{ $pattern->type == 'inflow' ? 'success' : 'danger' }}">
                                {{ ucfirst($pattern->type) }}
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
    // Frequency selection
    $('.frequency-card').on('click', function() {
        $('.frequency-card').removeClass('selected');
        $(this).addClass('selected');
        $('#frequency').val($(this).data('frequency'));
    });
});
</script>
@endpush
