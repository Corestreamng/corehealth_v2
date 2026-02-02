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
                <div class="count text-success">{{ $patterns->where('type', 'inflow')->count() }}</div>
                <div class="label">Inflow Patterns</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box" style="background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);">
                <div class="count text-danger">{{ $patterns->where('type', 'outflow')->count() }}</div>
                <div class="label">Outflow Patterns</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box" style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);">
                @php
                    $monthlyRecurring = $patterns->where('is_active', true)->sum(function($p) {
                        $multiplier = $p->frequency == 'weekly' ? 4 : ($p->frequency == 'daily' ? 30 : 1);
                        return $p->type == 'inflow' ? ($p->amount * $multiplier) : -($p->amount * $multiplier);
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
                        <div class="pattern-card {{ $pattern->type }} {{ $pattern->is_active ? '' : 'inactive' }}"
                             data-type="{{ $pattern->type }}">
                            <div class="pattern-header">
                                <div>
                                    <h6>
                                        {{ $pattern->name }}
                                        @if(!$pattern->is_active)
                                            <span class="badge badge-secondary badge-sm ml-1">Inactive</span>
                                        @endif
                                    </h6>
                                    <div class="pattern-meta">
                                        <span class="category-badge badge badge-{{ $pattern->type == 'inflow' ? 'success' : 'danger' }}">
                                            {{ ucfirst($pattern->type) }}
                                        </span>
                                        <span class="frequency-badge ml-1">
                                            <i class="mdi mdi-repeat"></i> {{ ucfirst($pattern->frequency) }}
                                        </span>
                                        @if($pattern->category)
                                            <span class="frequency-badge ml-1">{{ ucfirst($pattern->category) }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="pattern-amount {{ $pattern->type == 'inflow' ? 'text-success' : 'text-danger' }}">
                                        {{ $pattern->type == 'inflow' ? '+' : '-' }}₦{{ number_format($pattern->amount, 2) }}
                                    </div>
                                    @php
                                        $monthly = $pattern->frequency == 'weekly' ? $pattern->amount * 4 :
                                                   ($pattern->frequency == 'daily' ? $pattern->amount * 30 : $pattern->amount);
                                    @endphp
                                    <small class="text-muted">≈ ₦{{ number_format($monthly, 0) }}/mo</small>
                                </div>
                            </div>
                            @if($pattern->description)
                                <div class="pattern-meta mb-2">{{ $pattern->description }}</div>
                            @endif
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    Created {{ $pattern->created_at->format('M d, Y') }}
                                </small>
                                <div>
                                    <button class="btn btn-outline-primary btn-sm edit-pattern"
                                            data-id="{{ $pattern->id }}"
                                            data-name="{{ $pattern->name }}"
                                            data-type="{{ $pattern->type }}"
                                            data-frequency="{{ $pattern->frequency }}"
                                            data-category="{{ $pattern->category }}"
                                            data-amount="{{ $pattern->amount }}"
                                            data-description="{{ $pattern->description }}"
                                            data-active="{{ $pattern->is_active ? 1 : 0 }}">
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
                                data-type="inflow" data-category="sales" data-name="Daily Sales">
                            <i class="mdi mdi-cash-register"></i> Daily Sales
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-success btn-block btn-sm mb-2 quick-add"
                                data-type="inflow" data-category="receivables" data-name="Monthly Collections">
                            <i class="mdi mdi-account-cash"></i> Collections
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-danger btn-block btn-sm mb-2 quick-add"
                                data-type="outflow" data-category="payroll" data-name="Monthly Payroll">
                            <i class="mdi mdi-account-group"></i> Payroll
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-danger btn-block btn-sm mb-2 quick-add"
                                data-type="outflow" data-category="operating" data-name="Rent">
                            <i class="mdi mdi-home"></i> Rent
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-danger btn-block btn-sm quick-add"
                                data-type="outflow" data-category="operating" data-name="Utilities">
                            <i class="mdi mdi-flash"></i> Utilities
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-danger btn-block btn-sm quick-add"
                                data-type="outflow" data-category="debt" data-name="Loan Payment">
                            <i class="mdi mdi-bank"></i> Loan Payment
                        </button>
                    </div>
                </div>
            </div>

            <!-- Summary by Category -->
            <div class="info-card">
                <h6><i class="mdi mdi-chart-pie mr-2"></i>By Category</h6>
                @php
                    $byCategory = $patterns->where('is_active', true)->groupBy('category');
                @endphp
                @forelse($byCategory as $category => $catPatterns)
                    <div class="d-flex justify-content-between mb-2">
                        <span>{{ ucfirst($category ?: 'Other') }}</span>
                        <div>
                            <span class="text-success">+₦{{ number_format($catPatterns->where('type', 'inflow')->sum('amount'), 0) }}</span>
                            <span class="mx-1">/</span>
                            <span class="text-danger">-₦{{ number_format($catPatterns->where('type', 'outflow')->sum('amount'), 0) }}</span>
                        </div>
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
                        <li>Choose frequency: daily, weekly, or monthly</li>
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
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span id="modalTitle">Add New Pattern</span>
                    </h5>
                    <button type="button" class="close"  data-bs-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Pattern Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="patternName" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Type <span class="text-danger">*</span></label>
                                <select class="form-control" name="type" id="patternType" required>
                                    <option value="inflow">Inflow (Cash In)</option>
                                    <option value="outflow">Outflow (Cash Out)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Frequency <span class="text-danger">*</span></label>
                                <select class="form-control" name="frequency" id="patternFrequency" required>
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Category</label>
                        <select class="form-control" name="category" id="patternCategory">
                            <optgroup label="Inflow Categories" id="inflowCategories">
                                <option value="sales">Sales</option>
                                <option value="receivables">Receivables</option>
                                <option value="investment">Investment</option>
                                <option value="financing">Financing</option>
                            </optgroup>
                            <optgroup label="Outflow Categories" id="outflowCategories">
                                <option value="operating">Operating</option>
                                <option value="payroll">Payroll</option>
                                <option value="payables">Payables</option>
                                <option value="capex">Capital Expense</option>
                                <option value="taxes">Taxes</option>
                                <option value="debt">Debt Service</option>
                            </optgroup>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">₦</span>
                            </div>
                            <input type="number" class="form-control" name="amount" id="patternAmount"
                                   step="0.01" min="0" required>
                        </div>
                        <small class="text-muted">Amount per occurrence (per day/week/month)</small>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="description" id="patternDescription" rows="2"></textarea>
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
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
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

    // Update category options based on type
    $('#patternType').on('change', function() {
        var type = $(this).val();
        if (type === 'inflow') {
            $('#inflowCategories').show();
            $('#outflowCategories').hide();
            $('#patternCategory').val('sales');
        } else {
            $('#inflowCategories').hide();
            $('#outflowCategories').show();
            $('#patternCategory').val('operating');
        }
    });

    // Quick add
    $('.quick-add').on('click', function() {
        var type = $(this).data('type');
        var category = $(this).data('category');
        var name = $(this).data('name');

        resetPatternForm();
        $('#patternName').val(name);
        $('#patternType').val(type).trigger('change');
        $('#patternCategory').val(category);
        $('#patternFrequency').val('monthly');

        $('#patternModal').modal('show');
    });

    // Edit pattern
    $('.edit-pattern').on('click', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var type = $(this).data('type');
        var frequency = $(this).data('frequency');
        var category = $(this).data('category');
        var amount = $(this).data('amount');
        var description = $(this).data('description');
        var active = $(this).data('active');

        $('#modalTitle').text('Edit Pattern');
        $('#patternId').val(id);
        $('#patternName').val(name);
        $('#patternType').val(type).trigger('change');
        $('#patternFrequency').val(frequency);
        $('#patternCategory').val(category);
        $('#patternAmount').val(amount);
        $('#patternDescription').val(description);
        $('#patternActive').prop('checked', active == 1);

        $('#patternForm').attr('action', '{{ url("accounting/cash-flow-forecast/patterns") }}/' + id);
        $('#patternForm').append('<input type="hidden" name="_method" value="PUT">');

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
        $('#patternForm').attr('action', '{{ route("accounting.cash-flow-forecast.patterns.store") }}');
        $('#patternForm input[name="_method"]').remove();
        $('#patternType').val('inflow').trigger('change');
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
            error: function() {
                toastr.error('Failed to update pattern');
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
                error: function() {
                    toastr.error('Failed to delete pattern');
                }
            });
        }
    });
});
</script>
@endpush
