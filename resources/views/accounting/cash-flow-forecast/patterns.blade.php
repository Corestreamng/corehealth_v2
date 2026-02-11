{{--
    Cash Flow Patterns Management
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.6
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'Cash Flow Patterns')
@section('page_name', 'Accounting')
@section('subpage_name', 'Cash Flow Patterns')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Cash Flow Forecast', 'url' => route('accounting.cash-flow-forecast.index'), 'icon' => 'mdi-chart-timeline-variant'],
        ['label' => 'Patterns', 'url' => '#', 'icon' => 'mdi-repeat']
    ]
])

<style>
.pattern-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
    margin-bottom: 15px;
    border-left: 4px solid #667eea;
    transition: transform 0.2s, box-shadow 0.2s;
}
.pattern-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.12);
}
.pattern-card.inflow { border-left-color: #28a745; }
.pattern-card.outflow { border-left-color: #dc3545; }
.pattern-card.inactive { opacity: 0.6; border-left-color: #6c757d; }
.pattern-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}
.pattern-header h6 { margin: 0; font-weight: 600; }
.pattern-amount { font-size: 1.2rem; font-weight: 700; }
.pattern-meta { font-size: 0.85rem; color: #666; }
.info-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
    margin-bottom: 20px;
}
.info-card h6 {
    font-weight: 600;
    color: #333;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f1f1f1;
}
.stat-box {
    text-align: center;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.stat-box .count { font-size: 2rem; font-weight: 700; }
.stat-box .label { color: #666; font-size: 0.85rem; }
.category-badge {
    font-size: 0.75rem;
    padding: 3px 8px;
    border-radius: 4px;
}
.frequency-badge {
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 3px;
    background: #e9ecef;
    color: #495057;
}
</style>

<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    @php
        $inflowCategories = ['operating_inflow', 'investing_inflow', 'financing_inflow'];
        $outflowCategories = ['operating_outflow', 'investing_outflow', 'financing_outflow'];
        $inflowPatterns = $patterns->filter(fn($p) => in_array($p->cash_flow_category, $inflowCategories));
        $outflowPatterns = $patterns->filter(fn($p) => in_array($p->cash_flow_category, $outflowCategories));
    @endphp

    <!-- Stats Row -->
    <div class="row">
        <div class="col-md-3">
            <div class="stat-box" style="background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);">
                <div class="count text-info">{{ $patterns->count() }}</div>
                <div class="label">Total Patterns</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box" style="background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);">
                <div class="count text-success">{{ $inflowPatterns->count() }}</div>
                <div class="label">Inflow Patterns</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box" style="background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);">
                <div class="count text-danger">{{ $outflowPatterns->count() }}</div>
                <div class="label">Outflow Patterns</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box" style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);">
                @php
                    $monthlyRecurring = $patterns->where('is_active', true)->sum(function($p) use ($inflowCategories) {
                        $multiplier = match($p->frequency) {
                            'weekly' => 4,
                            'bi_weekly' => 2,
                            'quarterly' => 1/3,
                            'annually' => 1/12,
                            default => 1
                        };
                        $isInflow = in_array($p->cash_flow_category, $inflowCategories);
                        return $isInflow ? ($p->expected_amount * $multiplier) : -($p->expected_amount * $multiplier);
                    });
                @endphp
                <div class="count {{ $monthlyRecurring >= 0 ? 'text-success' : 'text-danger' }}">
                    ₦{{ number_format(abs($monthlyRecurring), 0) }}
                </div>
                <div class="label">Monthly Net Impact</div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Patterns List -->
            <div class="info-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0"><i class="mdi mdi-repeat mr-2"></i>Recurring Patterns</h6>
                    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#patternModal">
                        <i class="mdi mdi-plus mr-1"></i> Add Pattern
                    </button>
                </div>

                <!-- Filter Tabs -->
                <ul class="nav nav-pills mb-3">
                    <li class="nav-item">
                        <a class="nav-link active" href="#" data-filter="all">All</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-filter="inflow">
                            <i class="mdi mdi-arrow-down-bold text-success"></i> Inflows
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-filter="outflow">
                            <i class="mdi mdi-arrow-up-bold text-danger"></i> Outflows
                        </a>
                    </li>
                </ul>

                <div id="patternsList">
                    @forelse($patterns as $pattern)
                        @php
                            $isInflow = in_array($pattern->cash_flow_category, $inflowCategories);
                            $typeClass = $isInflow ? 'inflow' : 'outflow';
                            $monthly = match($pattern->frequency) {
                                'weekly' => $pattern->expected_amount * 4,
                                'bi_weekly' => $pattern->expected_amount * 2,
                                'quarterly' => $pattern->expected_amount / 3,
                                'annually' => $pattern->expected_amount / 12,
                                default => $pattern->expected_amount
                            };
                        @endphp
                        <div class="pattern-card {{ $typeClass }} {{ $pattern->is_active ? '' : 'inactive' }}"
                             data-type="{{ $typeClass }}">
                            <div class="pattern-header">
                                <div>
                                    <h6>
                                        {{ $pattern->pattern_name }}
                                        @if(!$pattern->is_active)
                                            <span class="badge badge-secondary badge-sm ml-1">Inactive</span>
                                        @endif
                                    </h6>
                                    <div class="pattern-meta">
                                        <span class="category-badge badge badge-{{ $isInflow ? 'success' : 'danger' }}">
                                            {{ ucwords(str_replace('_', ' ', $pattern->cash_flow_category)) }}
                                        </span>
                                        <span class="frequency-badge ml-1">
                                            <i class="mdi mdi-repeat"></i> {{ ucwords(str_replace('_', ' ', $pattern->frequency)) }}
                                        </span>
                                        @if($pattern->day_of_period)
                                            <span class="frequency-badge ml-1">Day {{ $pattern->day_of_period }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="pattern-amount {{ $isInflow ? 'text-success' : 'text-danger' }}">
                                        {{ $isInflow ? '+' : '-' }}₦{{ number_format($pattern->expected_amount, 2) }}
                                    </div>
                                    <small class="text-muted">≈ ₦{{ number_format($monthly, 0) }}/mo</small>
                                </div>
                            </div>
                            @if($pattern->notes)
                                <div class="pattern-meta mb-2">{{ $pattern->notes }}</div>
                            @endif
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    Created {{ $pattern->created_at->format('M d, Y') }}
                                </small>
                                <div>
                                    <button class="btn btn-outline-primary btn-sm edit-pattern"
                                            data-id="{{ $pattern->id }}"
                                            data-pattern_name="{{ $pattern->pattern_name }}"
                                            data-cash_flow_category="{{ $pattern->cash_flow_category }}"
                                            data-frequency="{{ $pattern->frequency }}"
                                            data-day_of_period="{{ $pattern->day_of_period }}"
                                            data-expected_amount="{{ $pattern->expected_amount }}"
                                            data-variance_percentage="{{ $pattern->variance_percentage }}"
                                            data-notes="{{ $pattern->notes }}"
                                            data-is_active="{{ $pattern->is_active ? 1 : 0 }}">
                                        <i class="mdi mdi-pencil"></i>
                                    </button>
                                    @if($pattern->is_active)
                                        <button class="btn btn-outline-warning btn-sm toggle-pattern" data-id="{{ $pattern->id }}" data-action="deactivate">
                                            <i class="mdi mdi-pause"></i>
                                        </button>
                                    @else
                                        <button class="btn btn-outline-success btn-sm toggle-pattern" data-id="{{ $pattern->id }}" data-action="activate">
                                            <i class="mdi mdi-play"></i>
                                        </button>
                                    @endif
                                    <button class="btn btn-outline-danger btn-sm delete-pattern" data-id="{{ $pattern->id }}">
                                        <i class="mdi mdi-delete"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-5 text-muted">
                            <i class="mdi mdi-repeat mdi-48px"></i>
                            <p class="mt-2 mb-0">No patterns created yet</p>
                            <small>Create recurring patterns to automatically populate forecast periods</small>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Quick Add -->
            <div class="info-card">
                <h6><i class="mdi mdi-flash mr-2"></i>Quick Add</h6>
                <div class="row">
                    <div class="col-6">
                        <button class="btn btn-outline-success btn-block btn-sm mb-2 quick-add"
                                data-category="operating_inflow" data-name="Cash Sales">
                            <i class="mdi mdi-cash-register"></i> Cash Sales
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-success btn-block btn-sm mb-2 quick-add"
                                data-category="operating_inflow" data-name="Collections">
                            <i class="mdi mdi-account-cash"></i> Collections
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-danger btn-block btn-sm mb-2 quick-add"
                                data-category="operating_outflow" data-name="Payroll">
                            <i class="mdi mdi-account-group"></i> Payroll
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-danger btn-block btn-sm mb-2 quick-add"
                                data-category="operating_outflow" data-name="Rent">
                            <i class="mdi mdi-home"></i> Rent
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-danger btn-block btn-sm quick-add"
                                data-category="operating_outflow" data-name="Utilities">
                            <i class="mdi mdi-flash"></i> Utilities
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-danger btn-block btn-sm quick-add"
                                data-category="financing_outflow" data-name="Loan Payment">
                            <i class="mdi mdi-bank"></i> Loan Payment
                        </button>
                    </div>
                </div>
            </div>

            <!-- Summary by Category -->
            <div class="info-card">
                <h6><i class="mdi mdi-chart-pie mr-2"></i>By Category</h6>
                @php
                    $byCategory = $patterns->where('is_active', true)->groupBy('cash_flow_category');
                @endphp
                @forelse($byCategory as $category => $catPatterns)
                    <div class="d-flex justify-content-between mb-2">
                        <span>{{ ucwords(str_replace('_', ' ', $category)) }}</span>
                        <span class="{{ str_contains($category, 'inflow') ? 'text-success' : 'text-danger' }}">
                            {{ str_contains($category, 'inflow') ? '+' : '-' }}₦{{ number_format($catPatterns->sum('expected_amount'), 0) }}
                        </span>
                    </div>
                @empty
                    <div class="text-center text-muted py-3">
                        <small>No active patterns</small>
                    </div>
                @endforelse
            </div>

            <!-- Help -->
            <div class="info-card bg-light">
                <h6><i class="mdi mdi-help-circle mr-2"></i>About Patterns</h6>
                <small class="text-muted">
                    <strong>Patterns</strong> are recurring cash flow items that can be automatically applied to forecast periods.
                    <ul class="pl-3 mt-2 mb-0">
                        <li>Create patterns for regular income like sales collections</li>
                        <li>Set up outflow patterns for rent, payroll, utilities</li>
                        <li>Choose frequency: weekly, bi-weekly, monthly, quarterly, annually</li>
                        <li>Deactivate patterns temporarily without deleting</li>
                    </ul>
                </small>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Pattern Modal -->
<div class="modal fade" id="patternModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="patternForm" method="POST" action="{{ route('accounting.cash-flow-forecast.patterns.store') }}">
                @csrf
                <input type="hidden" id="patternId" name="id">
                <input type="hidden" id="methodField" name="_method" value="POST">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span id="modalTitle">Add New Pattern</span>
                    </h5>
                    <button type="button" class="close" data-bs-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Pattern Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="pattern_name" id="patternName" required
                               placeholder="e.g., Monthly Rent, Weekly Payroll">
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Category <span class="text-danger">*</span></label>
                                <select class="form-control" name="cash_flow_category" id="patternCategory" required>
                                    <optgroup label="Inflows (Cash In)">
                                        <option value="operating_inflow">Operating Inflow</option>
                                        <option value="investing_inflow">Investing Inflow</option>
                                        <option value="financing_inflow">Financing Inflow</option>
                                    </optgroup>
                                    <optgroup label="Outflows (Cash Out)">
                                        <option value="operating_outflow">Operating Outflow</option>
                                        <option value="investing_outflow">Investing Outflow</option>
                                        <option value="financing_outflow">Financing Outflow</option>
                                    </optgroup>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Frequency <span class="text-danger">*</span></label>
                                <select class="form-control" name="frequency" id="patternFrequency" required>
                                    <option value="weekly">Weekly</option>
                                    <option value="bi_weekly">Bi-Weekly</option>
                                    <option value="monthly" selected>Monthly</option>
                                    <option value="quarterly">Quarterly</option>
                                    <option value="annually">Annually</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Expected Amount <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₦</span>
                                    </div>
                                    <input type="number" class="form-control" name="expected_amount" id="patternAmount"
                                           step="0.01" min="0" required>
                                </div>
                                <small class="text-muted">Amount per occurrence</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Day of Period</label>
                                <input type="number" class="form-control" name="day_of_period" id="patternDayOfPeriod"
                                       min="1" max="31" placeholder="e.g., 1 for 1st">
                                <small class="text-muted">Day of month/week when this occurs</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Variance Percentage</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="variance_percentage" id="patternVariance"
                                   step="0.01" min="0" max="100" value="10">
                            <div class="input-group-append">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <small class="text-muted">Expected variance from the amount</small>
                    </div>

                    <div class="form-group">
                        <label>Notes</label>
                        <textarea class="form-control" name="notes" id="patternNotes" rows="2"
                                  placeholder="Additional details about this pattern"></textarea>
                    </div>

                    <div class="form-group mb-0">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" name="is_active"
                                   id="patternActive" value="1" checked>
                            <label class="custom-control-label" for="patternActive">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="mdi mdi-content-save mr-1"></i> Save Pattern
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Filter tabs
    $('[data-filter]').on('click', function(e) {
        e.preventDefault();
        var filter = $(this).data('filter');

        $('[data-filter]').removeClass('active');
        $(this).addClass('active');

        if (filter === 'all') {
            $('.pattern-card').show();
        } else {
            $('.pattern-card').hide();
            $('.pattern-card[data-type="' + filter + '"]').show();
        }
    });

    // Quick add
    $('.quick-add').on('click', function() {
        var category = $(this).data('category');
        var name = $(this).data('name');

        resetPatternForm();
        $('#patternName').val(name);
        $('#patternCategory').val(category);
        $('#patternFrequency').val('monthly');

        $('#patternModal').modal('show');
    });

    // Edit pattern
    $('.edit-pattern').on('click', function() {
        var id = $(this).data('id');

        $('#modalTitle').text('Edit Pattern');
        $('#patternId').val(id);
        $('#methodField').val('PUT');
        $('#patternName').val($(this).data('pattern_name'));
        $('#patternCategory').val($(this).data('cash_flow_category'));
        $('#patternFrequency').val($(this).data('frequency'));
        $('#patternDayOfPeriod').val($(this).data('day_of_period'));
        $('#patternAmount').val($(this).data('expected_amount'));
        $('#patternVariance').val($(this).data('variance_percentage') || 10);
        $('#patternNotes').val($(this).data('notes'));
        $('#patternActive').prop('checked', $(this).data('is_active') == 1);

        $('#patternForm').attr('action', '{{ url("accounting/cash-flow-forecast/patterns") }}/' + id);

        $('#patternModal').modal('show');
    });

    // Reset form on modal close
    $('#patternModal').on('hidden.bs.modal', function() {
        resetPatternForm();
    });

    function resetPatternForm() {
        $('#modalTitle').text('Add New Pattern');
        $('#patternForm')[0].reset();
        $('#patternId').val('');
        $('#methodField').val('POST');
        $('#patternForm').attr('action', '{{ route("accounting.cash-flow-forecast.patterns.store") }}');
        $('#patternCategory').val('operating_inflow');
        $('#patternFrequency').val('monthly');
        $('#patternVariance').val('10');
        $('#patternActive').prop('checked', true);
    }

    // Toggle pattern
    $('.toggle-pattern').on('click', function() {
        var id = $(this).data('id');
        var action = $(this).data('action');

        $.ajax({
            url: '{{ url("accounting/cash-flow-forecast/patterns") }}/' + id + '/toggle',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}', action: action },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    location.reload();
                } else {
                    toastr.error(response.message);
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON?.message || 'Failed to update pattern';
                toastr.error(msg);
            }
        });
    });

    // Delete pattern
    $('.delete-pattern').on('click', function() {
        var id = $(this).data('id');

        if (confirm('Are you sure you want to delete this pattern?')) {
            $.ajax({
                url: '{{ url("accounting/cash-flow-forecast/patterns") }}/' + id,
                type: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        location.reload();
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function(xhr) {
                    var msg = xhr.responseJSON?.message || 'Failed to delete pattern';
                    toastr.error(msg);
                }
            });
        }
    });
});
</script>
@endpush
