@extends('admin.layouts.app')

@section('page_name', 'Accounting')
@section('subpage_name', 'Financial Reports')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Reports', 'url' => route('accounting.reports.index'), 'icon' => 'mdi-file-chart']
]])

<div class="container-fluid">
    {{-- Header with Title --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Financial Reports</h4>
            <p class="text-muted mb-0">Generate and view financial statements</p>
        </div>
    </div>

    {{-- Quick Report Generator Card --}}
    <div class="card-modern mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="mdi mdi-chart-bar mr-2"></i>Quick Report Generator</h5>
        </div>
        <div class="card-body">
            <form id="quickReportForm">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Report Type <span class="text-danger">*</span></label>
                        <select id="reportType" class="form-control" required>
                            <option value="">Select Report...</option>
                            <option value="trial-balance">Trial Balance</option>
                            <option value="profit-loss">Profit & Loss</option>
                            <option value="balance-sheet">Balance Sheet</option>
                            <option value="cash-flow">Cash Flow Statement</option>
                            <option value="general-ledger">General Ledger</option>
                            <option value="bank-statement">Bank Statement</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Period</label>
                        <select id="periodSelect" class="form-control">
                            <option value="">Custom Dates</option>
                            @foreach($periods as $period)
                                <option value="{{ $period->id }}"
                                        data-start="{{ $period->start_date->format('Y-m-d') }}"
                                        data-end="{{ $period->end_date->format('Y-m-d') }}">
                                    {{ $period->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" id="startDate" class="form-control" value="{{ now()->startOfMonth()->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">End Date</label>
                        <input type="date" id="endDate" class="form-control" value="{{ now()->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-2 mb-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="mdi mdi-play mr-1"></i> Generate
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        {{-- Core Financial Statements --}}
        <div class="col-lg-6 mb-4">
            <div class="card-modern h-100">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="mdi mdi-file-document-outline mr-2"></i>Core Financial Statements
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @can('reports.trial-balance')
                        <a href="{{ route('accounting.reports.trial-balance') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <div class="d-flex align-items-center">
                                <div class="report-icon bg-primary-light mr-3">
                                    <i class="mdi mdi-scale-balance text-primary"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Trial Balance</h6>
                                    <small class="text-muted">All accounts with debit and credit balances</small>
                                </div>
                            </div>
                            <i class="mdi mdi-chevron-right text-muted"></i>
                        </a>
                        @endcan
                        @can('reports.profit-loss')
                        <a href="{{ route('accounting.reports.profit-loss') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <div class="d-flex align-items-center">
                                <div class="report-icon bg-success-light mr-3">
                                    <i class="mdi mdi-chart-line text-success"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Profit & Loss</h6>
                                    <small class="text-muted">Revenue and expenses for a period</small>
                                </div>
                            </div>
                            <i class="mdi mdi-chevron-right text-muted"></i>
                        </a>
                        @endcan
                        @can('reports.balance-sheet')
                        <a href="{{ route('accounting.reports.balance-sheet') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <div class="d-flex align-items-center">
                                <div class="report-icon bg-info-light mr-3">
                                    <i class="mdi mdi-file-document text-info"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Balance Sheet</h6>
                                    <small class="text-muted">Assets, liabilities, and equity snapshot</small>
                                </div>
                            </div>
                            <i class="mdi mdi-chevron-right text-muted"></i>
                        </a>
                        @endcan
                        @can('reports.cash-flow')
                        <a href="{{ route('accounting.reports.cash-flow') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <div class="d-flex align-items-center">
                                <div class="report-icon bg-warning-light mr-3">
                                    <i class="mdi mdi-cash-multiple text-warning"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Cash Flow Statement</h6>
                                    <small class="text-muted">Cash inflows and outflows by activity</small>
                                </div>
                            </div>
                            <i class="mdi mdi-chevron-right text-muted"></i>
                        </a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        {{-- Detailed Reports --}}
        <div class="col-lg-6 mb-4">
            <div class="card-modern h-100">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="mdi mdi-book-open-outline mr-2"></i>Detailed Reports
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @can('reports.general-ledger')
                        <a href="{{ route('accounting.reports.general-ledger') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <div class="d-flex align-items-center">
                                <div class="report-icon bg-secondary-light mr-3">
                                    <i class="mdi mdi-book-multiple text-secondary"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">General Ledger</h6>
                                    <small class="text-muted">Detailed transactions for each account</small>
                                </div>
                            </div>
                            <i class="mdi mdi-chevron-right text-muted"></i>
                        </a>
                        @endcan
                        @can('reports.bank-statement')
                        <a href="{{ route('accounting.reports.bank-statement') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <div class="d-flex align-items-center">
                                <div class="report-icon bg-cyan-light mr-3">
                                    <i class="mdi mdi-bank text-cyan"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Bank Statement</h6>
                                    <small class="text-muted">Bank account transactions with advanced filters</small>
                                </div>
                            </div>
                            <i class="mdi mdi-chevron-right text-muted"></i>
                        </a>
                        @endcan
                        @can('reports.account-activity')
                        <a href="{{ route('accounting.reports.account-activity') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <div class="d-flex align-items-center">
                                <div class="report-icon bg-purple-light mr-3">
                                    <i class="mdi mdi-history text-purple"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Account Activity</h6>
                                    <small class="text-muted">Transaction history for specific account</small>
                                </div>
                            </div>
                            <i class="mdi mdi-chevron-right text-muted"></i>
                        </a>
                        @endcan
                        <a href="{{ route('accounting.reports.aged-receivables') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <div class="d-flex align-items-center">
                                <div class="report-icon bg-danger-light mr-3">
                                    <i class="mdi mdi-clock-alert text-danger"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Aged Receivables</h6>
                                    <small class="text-muted">Outstanding amounts owed by patients/HMOs</small>
                                </div>
                            </div>
                            <i class="mdi mdi-chevron-right text-muted"></i>
                        </a>
                        <a href="{{ route('accounting.reports.aged-payables') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <div class="d-flex align-items-center">
                                <div class="report-icon bg-orange-light mr-3">
                                    <i class="mdi mdi-account-clock text-orange"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Aged Payables</h6>
                                    <small class="text-muted">Outstanding amounts owed to suppliers</small>
                                </div>
                            </div>
                            <i class="mdi mdi-chevron-right text-muted"></i>
                        </a>
                        @can('reports.daily-audit')
                        <a href="{{ route('accounting.reports.daily-audit') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <div class="d-flex align-items-center">
                                <div class="report-icon bg-teal-light mr-3">
                                    <i class="mdi mdi-clipboard-check text-teal"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Daily Audit Trail</h6>
                                    <small class="text-muted">All transactions for a specific day</small>
                                </div>
                            </div>
                            <i class="mdi mdi-chevron-right text-muted"></i>
                        </a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Saved Filters --}}
    @if($savedFilters->count() > 0)
    <div class="card-modern card-modern">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="mdi mdi-bookmark-outline mr-2"></i>Saved Report Filters</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th>Name</th>
                            <th>Report Type</th>
                            <th>Shared</th>
                            <th>Last Used</th>
                            <th width="150">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($savedFilters as $filter)
                            <tr>
                                <td><strong>{{ $filter->name }}</strong></td>
                                <td>
                                    <span class="badge badge-info">{{ ucwords(str_replace('-', ' ', $filter->report_type)) }}</span>
                                </td>
                                <td>
                                    @if($filter->is_shared)
                                        <span class="badge badge-success">Shared</span>
                                    @else
                                        <span class="badge badge-secondary">Private</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $filter->last_used_at ? $filter->last_used_at->format('M d, Y') : 'Never' }}
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary load-filter"
                                            data-filter-id="{{ $filter->id }}">
                                        <i class="mdi mdi-play"></i> Load
                                    </button>
                                    @if($filter->user_id === auth()->id())
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-filter"
                                                data-filter-id="{{ $filter->id }}">
                                            <i class="mdi mdi-delete"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

@push('styles')
<style>
    .card-modern {
        border: none;
        box-shadow: 0 0 10px rgba(0,0,0,0.05);
        border-radius: 10px;
    }
    .card-modern .card-header {
        background-color: transparent;
        border-bottom: 1px solid #eee;
    }

    .report-icon {
        width: 45px;
        height: 45px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .report-icon i {
        font-size: 22px;
    }

    .bg-primary-light { background-color: rgba(0, 123, 255, 0.1); }
    .bg-success-light { background-color: rgba(40, 167, 69, 0.1); }
    .bg-warning-light { background-color: rgba(255, 193, 7, 0.1); }
    .bg-info-light { background-color: rgba(23, 162, 184, 0.1); }
    .bg-danger-light { background-color: rgba(220, 53, 69, 0.1); }
    .bg-secondary-light { background-color: rgba(108, 117, 125, 0.1); }
    .bg-purple-light { background-color: rgba(111, 66, 193, 0.1); }
    .bg-orange-light { background-color: rgba(253, 126, 20, 0.1); }
    .bg-teal-light { background-color: rgba(32, 201, 151, 0.1); }

    .text-purple { color: #6f42c1; }
    .text-orange { color: #fd7e14; }
    .text-teal { color: #20c997; }

    .list-group-item:hover {
        background-color: #f8f9fa;
    }
    .list-group-item:hover .mdi-chevron-right {
        transform: translateX(3px);
        transition: transform 0.2s;
    }
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    // Period selection auto-fills dates
    $('#periodSelect').on('change', function() {
        var option = $(this).find(':selected');
        if (option.val()) {
            $('#startDate').val(option.data('start'));
            $('#endDate').val(option.data('end'));
        }
    });

    // Quick report form
    $('#quickReportForm').on('submit', function(e) {
        e.preventDefault();
        var reportType = $('#reportType').val();
        var periodId = $('#periodSelect').val();
        var startDate = $('#startDate').val();
        var endDate = $('#endDate').val();

        if (!reportType) {
            toastr.warning('Please select a report type');
            return;
        }

        var url = '/accounting/reports/' + reportType + '?';
        if (periodId) {
            url += 'period_id=' + periodId;
        } else {
            url += 'start_date=' + startDate + '&end_date=' + endDate;
        }

        window.location.href = url;
    });

    // Load saved filter
    $('.load-filter').on('click', function() {
        var filterId = $(this).data('filter-id');
        $.get('/accounting/reports/filters/' + filterId)
        .done(function(data) {
            if (data.success) {
                var filter = data.filter;
                var url = '/accounting/reports/' + filter.report_type + '?';
                var params = new URLSearchParams(filter.filters);
                window.location.href = url + params.toString();
            }
        })
        .fail(function() {
            toastr.error('Error loading filter');
        });
    });

    // Delete saved filter
    $('.delete-filter').on('click', function() {
        if (!confirm('Delete this saved filter?')) {
            return;
        }

        var filterId = $(this).data('filter-id');
        $.ajax({
            url: '/accounting/reports/filters/' + filterId,
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .done(function(data) {
            if (data.success) {
                toastr.success('Filter deleted');
                location.reload();
            }
        })
        .fail(function() {
            toastr.error('Error deleting filter');
        });
    });
});
</script>
@endpush
