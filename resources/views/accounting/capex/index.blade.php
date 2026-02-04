{{--
    Capital Expenditure (Capex) Dashboard & Listing
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.7
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'Capital Expenditure')
@section('page_name', 'Accounting')
@section('subpage_name', 'Capital Expenditure')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Capital Expenditure', 'url' => '#', 'icon' => 'mdi-factory']
    ]
])

<style>
.stat-card {
    border-radius: 10px;
    padding: 20px;
    color: white;
    margin-bottom: 20px;
    position: relative;
    overflow: hidden;
}
.stat-card::after {
    content: '';
    position: absolute;
    right: -20px;
    bottom: -20px;
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: rgba(255,255,255,0.1);
}
.stat-card .amount { font-size: 1.8rem; font-weight: 700; margin: 10px 0; }
.stat-card .label { font-size: 0.85rem; opacity: 0.9; }
.stat-card .icon { font-size: 2.5rem; opacity: 0.5; position: absolute; right: 20px; top: 20px; }
.stat-card.purple { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.stat-card.green { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
.stat-card.blue { background: linear-gradient(135deg, #17a2b8 0%, #0d6efd 100%); }
.stat-card.orange { background: linear-gradient(135deg, #fd7e14 0%, #f59f00 100%); }
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
.utilization-bar {
    height: 30px;
    border-radius: 15px;
    background: #e9ecef;
    overflow: hidden;
    margin-bottom: 15px;
}
.utilization-fill {
    height: 100%;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    transition: width 0.5s ease;
}
.status-badge {
    font-size: 0.75rem;
    padding: 4px 10px;
    border-radius: 20px;
}
.priority-badge {
    font-size: 0.65rem;
    padding: 2px 6px;
    border-radius: 3px;
}
.category-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f1f1f1;
}
.category-item:last-child { border-bottom: none; }
</style>

<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <!-- Info Banner -->
    <div class="alert alert-info alert-dismissible fade show mb-3">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <h6 class="mb-2"><i class="mdi mdi-information-outline mr-2"></i>Capital Expenditure Management</h6>
        <small>
            <strong>What is CAPEX?</strong> Capital expenditures are investments in long-term assets (life > 1 year, value > ₦100,000) such as equipment, facilities, technology, and vehicles.
            <br>
            <strong>Workflow:</strong>
            <span class="badge badge-secondary badge-sm">Draft</span> →
            <span class="badge badge-warning badge-sm">Pending</span> →
            <span class="badge badge-primary badge-sm">Approved</span> →
            <span class="badge badge-info badge-sm">In Progress</span> →
            <span class="badge badge-success badge-sm">Completed</span>
            <br>
            <strong>Budget Tracking:</strong> All capex requests are tracked against annual fiscal year budgets by category to ensure spending stays within allocated limits.
        </small>
    </div>

    <!-- Stats Cards -->
    <div class="row">
        <div class="col-md-3">
            <div class="stat-card purple">
                <i class="mdi mdi-wallet icon"></i>
                <div class="label">Total Budget ({{ date('Y') }})</div>
                <div class="amount">₦{{ number_format($stats['total_budget'], 0) }}</div>
                <small>Annual Capex Budget</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card green">
                <i class="mdi mdi-check-circle icon"></i>
                <div class="label">Committed</div>
                <div class="amount">₦{{ number_format($stats['total_committed'], 0) }}</div>
                <small>Approved Requests</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card blue">
                <i class="mdi mdi-cash-multiple icon"></i>
                <div class="label">Spent</div>
                <div class="amount">₦{{ number_format($stats['total_spent'], 0) }}</div>
                <small>Completed Capex</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card orange">
                <i class="mdi mdi-clock-outline icon"></i>
                <div class="label">Pending Approvals</div>
                <div class="amount">{{ $stats['pending_approvals'] }}</div>
                <small>Awaiting Review</small>
            </div>
        </div>
    </div>

    <!-- Budget Utilization -->
    <div class="info-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0"><i class="mdi mdi-chart-bar mr-2"></i>Budget Utilization</h6>
            <span class="text-muted">{{ $stats['utilization'] }}% of Budget Committed</span>
        </div>
        <p class="text-muted small mb-3">
            <i class="mdi mdi-information-outline mr-1"></i>
            Tracks your capital expenditure budget utilization for the current fiscal year. <strong>Committed</strong> = approved requests, <strong>Spent</strong> = actual expenditures, <strong>Remaining</strong> = available budget.
        </p>
        <div class="utilization-bar">
            @php
                $util = min($stats['utilization'], 100);
                $bgColor = $util < 75 ? '#28a745' : ($util < 90 ? '#ffc107' : '#dc3545');
            @endphp
            <div class="utilization-fill" style="width: {{ $util }}%; background: {{ $bgColor }};">
                {{ $stats['utilization'] }}%
            </div>
        </div>
        <div class="row text-center">
            <div class="col-3">
                <small class="text-muted">Budget</small><br>
                <strong>₦{{ number_format($stats['total_budget'], 0) }}</strong>
            </div>
            <div class="col-3">
                <small class="text-muted">Committed</small><br>
                <strong class="text-success">₦{{ number_format($stats['total_committed'], 0) }}</strong>
            </div>
            <div class="col-3">
                <small class="text-muted">Spent</small><br>
                <strong class="text-info">₦{{ number_format($stats['total_spent'], 0) }}</strong>
            </div>
            <div class="col-3">
                <small class="text-muted">Remaining</small><br>
                <strong class="{{ $stats['remaining_budget'] >= 0 ? 'text-success' : 'text-danger' }}">
                    ₦{{ number_format($stats['remaining_budget'], 0) }}
                </strong>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Capex Requests Table -->
            <div class="info-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0"><i class="mdi mdi-format-list-bulleted mr-2"></i>Capex Requests</h6>
                    <a href="{{ route('accounting.capex.create') }}" class="btn btn-primary btn-sm">
                        <i class="mdi mdi-plus mr-1"></i> New Request
                    </a>
                </div>
                <p class="text-muted small mb-3">
                    <i class="mdi mdi-information-outline mr-1"></i>
                    View and manage all capital expenditure requests. Use filters to find specific requests by year, status, category, or priority. Click on any request to view full details.
                </p>

                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <select class="form-control form-control-sm" id="filterYear">
                            <option value="">All Years</option>
                            @foreach($fiscalYears as $year)
                                <option value="{{ $year }}" {{ $year == date('Y') ? 'selected' : '' }}>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-control form-control-sm" id="filterStatus">
                            <option value="">All Status</option>
                            <option value="draft">Draft</option>
                            <option value="pending">Pending Approval</option>
                            <option value="approved">Approved</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-control form-control-sm" id="filterCategory">
                            <option value="">All Categories</option>
                            @foreach($categories as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-control form-control-sm" id="filterPriority">
                            <option value="">All Priorities</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover" id="capexTable">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Requested</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- By Category -->
            <div class="info-card">
                <h6><i class="mdi mdi-chart-pie mr-2"></i>By Category</h6>
                @forelse($stats['by_category'] as $cat)
                    <div class="category-item">
                        <div>
                            <strong>{{ ucfirst($cat->category ?? 'Other') }}</strong>
                            <small class="text-muted d-block">{{ $cat->count }} requests</small>
                        </div>
                        <div class="text-right">
                            <strong>₦{{ number_format($cat->committed, 0) }}</strong>
                            <small class="text-muted d-block">₦{{ number_format($cat->spent, 0) }} spent</small>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-3">
                        <small>No data available</small>
                    </div>
                @endforelse
            </div>

            <!-- Quick Stats -->
            <div class="info-card">
                <h6><i class="mdi mdi-speedometer mr-2"></i>Quick Stats</h6>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Total Requests:</span>
                    <span class="badge badge-secondary">{{ $stats['total_requests'] }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Pending Approvals:</span>
                    <span class="badge badge-warning">{{ $stats['pending_approvals'] }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">In Progress:</span>
                    <span class="badge badge-info">{{ $stats['in_progress'] }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Completed:</span>
                    <span class="badge badge-success">{{ $stats['completed'] }}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Fiscal Year:</span>
                    <strong>{{ date('Y') }}</strong>
                </div>
            </div>

            <!-- Actions -->
            <div class="info-card">
                <h6><i class="mdi mdi-cog mr-2"></i>Actions</h6>
                <a href="{{ route('accounting.capex.create') }}" class="btn btn-primary btn-block btn-sm mb-2">
                    <i class="mdi mdi-plus mr-1"></i> New Capex Request
                </a>
                <a href="{{ route('accounting.capex.budget-overview') }}" class="btn btn-outline-info btn-block btn-sm mb-2">
                    <i class="mdi mdi-wallet mr-1"></i> Budget Overview
                </a>
                <div class="btn-group btn-block mb-2">
                    <a href="{{ route('accounting.capex.export', ['fiscal_year' => date('Y'), 'format' => 'pdf']) }}" class="btn btn-danger btn-sm">
                        <i class="mdi mdi-file-pdf"></i> PDF
                    </a>
                    <a href="{{ route('accounting.capex.export', ['fiscal_year' => date('Y'), 'format' => 'excel']) }}" class="btn btn-success btn-sm">
                        <i class="mdi mdi-file-excel"></i> Excel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<!-- DataTables CSS -->
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
@endpush

@push('scripts')
<!-- DataTables JS -->
<script src="{{ asset('/plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
<script>
$(document).ready(function() {
    var table = $('#capexTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("accounting.capex.datatable") }}',
            data: function(d) {
                d.fiscal_year = $('#filterYear').val();
                d.status = $('#filterStatus').val();
                d.category = $('#filterCategory').val();
                d.priority = $('#filterPriority').val();
            }
        },
        columns: [
            { data: 'reference_number', name: 'reference_number' },
            {
                data: 'title',
                name: 'title',
                render: function(data, type, row) {
                    return '<a href="/accounting/capex/' + row.id + '">' + data + '</a>';
                }
            },
            {
                data: 'category',
                name: 'category',
                render: function(data) {
                    return data ? data.charAt(0).toUpperCase() + data.slice(1) : '-';
                }
            },
            {
                data: 'requested_amount',
                name: 'requested_amount',
                render: function(data) {
                    return '₦' + parseFloat(data).toLocaleString('en-US', { minimumFractionDigits: 0 });
                }
            },
            {
                data: 'status',
                name: 'status',
                render: function(data) {
                    var badges = {
                        'draft': 'secondary',
                        'pending': 'warning',
                        'approved': 'success',
                        'in_progress': 'info',
                        'completed': 'primary',
                        'rejected': 'danger',
                        'revision': 'secondary'
                    };
                    var labels = {
                        'draft': 'Draft',
                        'pending': 'Pending',
                        'approved': 'Approved',
                        'in_progress': 'In Progress',
                        'completed': 'Completed',
                        'rejected': 'Rejected',
                        'revision': 'Revision'
                    };
                    return '<span class="badge badge-' + (badges[data] || 'secondary') + ' status-badge">' + (labels[data] || data) + '</span>';
                }
            },
            {
                data: 'priority',
                name: 'priority',
                render: function(data) {
                    var colors = {
                        'low': 'success',
                        'medium': 'info',
                        'high': 'warning',
                        'critical': 'danger'
                    };
                    return '<span class="badge badge-' + (colors[data] || 'secondary') + ' priority-badge">' + (data ? data.toUpperCase() : '-') + '</span>';
                }
            },
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    var btns = '<div class="btn-group btn-group-sm">';
                    btns += '<a href="/accounting/capex/' + row.id + '" class="btn btn-outline-primary btn-sm"><i class="mdi mdi-eye"></i></a>';

                    if (row.status === 'draft') {
                        btns += '<a href="/accounting/capex/' + row.id + '/edit" class="btn btn-outline-secondary btn-sm"><i class="mdi mdi-pencil"></i></a>';
                        btns += '<button class="btn btn-outline-success btn-sm submit-btn" data-id="' + row.id + '"><i class="mdi mdi-send"></i></button>';
                    }

                    if (row.status === 'pending') {
                        btns += '<button class="btn btn-outline-success btn-sm approve-btn" data-id="' + row.id + '" data-amount="' + row.requested_amount + '"><i class="mdi mdi-check"></i></button>';
                        btns += '<button class="btn btn-outline-danger btn-sm reject-btn" data-id="' + row.id + '"><i class="mdi mdi-close"></i></button>';
                    }

                    btns += '</div>';
                    return btns;
                }
            }
        ],
        order: [[5, 'desc']],
        pageLength: 10,
        language: {
            emptyTable: "No capex requests found"
        }
    });

    // Filter handlers
    $('#filterYear, #filterStatus, #filterCategory, #filterPriority').on('change', function() {
        table.ajax.reload();
    });

    // Submit for approval
    $(document).on('click', '.submit-btn', function() {
        var id = $(this).data('id');
        if (confirm('Submit this request for approval?')) {
            $.ajax({
                url: '/accounting/capex/' + id + '/submit',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        table.ajax.reload();
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function() {
                    toastr.error('Failed to submit request');
                }
            });
        }
    });

    // Approve
    $(document).on('click', '.approve-btn', function() {
        var id = $(this).data('id');
        var amount = $(this).data('amount');

        if (confirm('Approve this Capex request for ₦' + parseFloat(amount).toLocaleString() + '?')) {
            $.ajax({
                url: '/accounting/capex/' + id + '/approve',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}', approved_amount: amount },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        table.ajax.reload();
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function() {
                    toastr.error('Failed to approve request');
                }
            });
        }
    });

    // Reject
    $(document).on('click', '.reject-btn', function() {
        var id = $(this).data('id');
        var reason = prompt('Enter rejection reason:');

        if (reason) {
            $.ajax({
                url: '/accounting/capex/' + id + '/reject',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}', reason: reason },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        table.ajax.reload();
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function() {
                    toastr.error('Failed to reject request');
                }
            });
        }
    });
});
</script>
@endpush
