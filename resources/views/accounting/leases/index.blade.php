@extends('admin.layouts.app')
@section('title', 'Lease Management')
@section('page_name', 'Accounting')
@section('subpage_name', 'Leases (IFRS 16)')

@push('styles')
<style>
    .stat-card {
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1);
    }
    .stat-icon {
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
    }
    .quick-ref-card {
        border-left: 4px solid;
        transition: transform 0.2s;
    }
    .quick-ref-card:hover {
        transform: translateX(5px);
    }
    .quick-ref-card.finance { border-left-color: #0d6efd; }
    .quick-ref-card.short-term { border-left-color: #198754; }
    .quick-ref-card.low-value { border-left-color: #0dcaf0; }
    .quick-ref-card.calculations { border-left-color: #ffc107; }
    .filter-section {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1rem;
    }
</style>
@endpush

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Leases', 'url' => '#', 'icon' => 'mdi-file-document-edit']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        {{-- Alert Boxes --}}
        <div id="alert-container">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-check-circle mr-2"></i>{{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-alert-circle mr-2"></i>{{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-alert mr-2"></i>{{ session('warning') }}
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            @endif
            @if(session('info'))
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-information mr-2"></i>{{ session('info') }}
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            @endif
        </div>

        {{-- Stats Row --}}
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card-modern stat-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-primary mr-3">
                            <i class="mdi mdi-file-document-multiple text-white mdi-24px"></i>
                        </div>
                        <div>
                            <small class="text-muted text-uppercase">Active Leases</small>
                            <h3 class="mb-0 font-weight-bold">{{ $stats['active_count'] }}</h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card-modern stat-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-info mr-3">
                            <i class="mdi mdi-office-building text-white mdi-24px"></i>
                        </div>
                        <div>
                            <small class="text-muted text-uppercase">Total ROU Assets</small>
                            <h4 class="mb-0 font-weight-bold">₦{{ number_format($stats['total_rou_asset'], 0) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card-modern stat-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-warning mr-3">
                            <i class="mdi mdi-scale-balance text-white mdi-24px"></i>
                        </div>
                        <div>
                            <small class="text-muted text-uppercase">Total Lease Liability</small>
                            <h4 class="mb-0 font-weight-bold">₦{{ number_format($stats['total_liability'], 0) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card-modern stat-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-secondary mr-3">
                            <i class="mdi mdi-chart-line-variant text-white mdi-24px"></i>
                        </div>
                        <div>
                            <small class="text-muted text-uppercase">Monthly Depreciation</small>
                            <h4 class="mb-0 font-weight-bold">₦{{ number_format($stats['monthly_depreciation'], 0) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Secondary Stats Row --}}
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card-modern h-100 border-left border-primary" style="border-left-width: 4px !important;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted text-uppercase">Due This Month</small>
                                <h4 class="mb-0 font-weight-bold">₦{{ number_format($stats['payments_due_this_month'], 2) }}</h4>
                            </div>
                            <i class="mdi mdi-calendar-clock mdi-36px text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card-modern h-100 border-left border-danger" style="border-left-width: 4px !important;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted text-uppercase">Overdue Payments</small>
                                <h4 class="mb-0 font-weight-bold {{ $stats['overdue_payments'] > 0 ? 'text-danger' : '' }}">
                                    ₦{{ number_format($stats['overdue_payments'], 2) }}
                                </h4>
                            </div>
                            <i class="mdi mdi-alert-circle mdi-36px {{ $stats['overdue_payments'] > 0 ? 'text-danger' : 'text-muted' }}"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card-modern h-100 border-left border-warning" style="border-left-width: 4px !important;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted text-uppercase">Expiring (90 days)</small>
                                <h4 class="mb-0 font-weight-bold {{ $stats['expiring_soon'] > 0 ? 'text-warning' : '' }}">
                                    {{ $stats['expiring_soon'] }} leases
                                </h4>
                            </div>
                            <i class="mdi mdi-clock-alert mdi-36px {{ $stats['expiring_soon'] > 0 ? 'text-warning' : 'text-muted' }}"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- IFRS 16 Quick Reference (Collapsible) --}}
        <div class="card-modern mb-4">
            <div class="card-header bg-light" data-toggle="collapse" data-target="#ifrs16Help" style="cursor: pointer;">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="mdi mdi-help-circle-outline mr-2 text-primary"></i>
                        <span class="text-primary">IFRS 16 Quick Reference</span>
                        <small class="text-muted ml-2">(click to expand)</small>
                    </h6>
                    <i class="mdi mdi-chevron-down text-muted"></i>
                </div>
            </div>
            <div class="collapse" id="ifrs16Help">
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                            <div class="quick-ref-card finance bg-white p-3 h-100 rounded shadow-sm">
                                <h6 class="text-primary mb-2">
                                    <i class="mdi mdi-bank mr-1"></i>Finance/Operating Lease
                                </h6>
                                <p class="small text-muted mb-2">Full IFRS 16 Recognition:</p>
                                <ul class="small mb-0 pl-3">
                                    <li>Recognize ROU Asset</li>
                                    <li>Recognize Lease Liability</li>
                                    <li>Monthly Depreciation</li>
                                    <li>Interest on Liability</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                            <div class="quick-ref-card short-term bg-white p-3 h-100 rounded shadow-sm">
                                <h6 class="text-success mb-2">
                                    <i class="mdi mdi-clock-fast mr-1"></i>Short-Term Lease
                                </h6>
                                <p class="small text-muted mb-2">Exemption (≤12 months):</p>
                                <ul class="small mb-0 pl-3">
                                    <li>No asset/liability recognition</li>
                                    <li>Expense payments as rent</li>
                                    <li>Simpler accounting</li>
                                    <li>Elect by asset class</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                            <div class="quick-ref-card low-value bg-white p-3 h-100 rounded shadow-sm">
                                <h6 class="text-info mb-2">
                                    <i class="mdi mdi-currency-usd-off mr-1"></i>Low-Value Lease
                                </h6>
                                <p class="small text-muted mb-2">Exemption (≤$5,000 new):</p>
                                <ul class="small mb-0 pl-3">
                                    <li>No asset/liability recognition</li>
                                    <li>Expense payments as rent</li>
                                    <li>Elect lease-by-lease</li>
                                    <li>e.g., laptops, furniture</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="quick-ref-card calculations bg-white p-3 h-100 rounded shadow-sm">
                                <h6 class="text-warning mb-2">
                                    <i class="mdi mdi-calculator mr-1"></i>Key Terms
                                </h6>
                                <p class="small text-muted mb-2">Important Values:</p>
                                <ul class="small mb-0 pl-3">
                                    <li><strong>IBR:</strong> Incremental Borrowing Rate</li>
                                    <li><strong>PV:</strong> Present Value of payments</li>
                                    <li><strong>ROU:</strong> Right-of-Use Asset</li>
                                    <li><strong>NBV:</strong> Net Book Value</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Leases Table --}}
        <div class="card-modern card-modern">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="mdi mdi-file-document-edit mr-2"></i>Lease Agreements
                </h5>
                <div class="btn-toolbar mt-2 mt-md-0">
                    <div class="btn-group mr-2 mb-1 mb-md-0">
                        <a href="{{ route('accounting.leases.export.pdf', request()->query()) }}" class="btn btn-outline-danger btn-sm" title="Export to PDF">
                            <i class="mdi mdi-file-pdf-box"></i> <span class="d-none d-md-inline">PDF</span>
                        </a>
                        <a href="{{ route('accounting.leases.export.excel', request()->query()) }}" class="btn btn-outline-success btn-sm" title="Export to Excel">
                            <i class="mdi mdi-file-excel"></i> <span class="d-none d-md-inline">Excel</span>
                        </a>
                    </div>
                    <a href="{{ route('accounting.leases.reports.ifrs16') }}" class="btn btn-outline-primary btn-sm mr-2 mb-1 mb-md-0" title="IFRS 16 Report">
                        <i class="mdi mdi-file-chart"></i> <span class="d-none d-lg-inline">IFRS 16 Report</span>
                    </a>
                    <a href="{{ route('accounting.leases.create') }}" class="btn btn-primary btn-sm mb-1 mb-md-0">
                        <i class="mdi mdi-plus"></i> New Lease
                    </a>
                </div>
            </div>
            <div class="card-body">
                {{-- Filters --}}
                <div class="filter-section">
                    <div class="row align-items-end">
                        <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                            <label class="small text-muted mb-1">Status</label>
                            <select id="filter-status" class="form-control form-control-sm">
                                <option value="">All Status</option>
                                <option value="draft">Draft</option>
                                <option value="active">Active</option>
                                <option value="expired">Expired</option>
                                <option value="terminated">Terminated</option>
                                <option value="purchased">Purchased</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                            <label class="small text-muted mb-1">Lease Type</label>
                            <select id="filter-type" class="form-control form-control-sm">
                                <option value="">All Types</option>
                                <option value="operating">Operating</option>
                                <option value="finance">Finance</option>
                                <option value="short_term">Short Term</option>
                                <option value="low_value">Low Value</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                            <label class="small text-muted mb-1">Lessor</label>
                            <input type="text" id="filter-lessor" class="form-control form-control-sm" placeholder="Search lessor name...">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <button type="button" class="btn btn-sm btn-primary mr-1" id="btn-filter">
                                <i class="mdi mdi-filter"></i> Filter
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-reset">
                                <i class="mdi mdi-refresh"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>

                {{-- DataTable --}}
                <div class="table-responsive">
                    <table id="leases-table" class="table table-striped table-hover" style="width:100%">
                        <thead class="thead-dark">
                            <tr>
                                <th>Lease #</th>
                                <th>Leased Item</th>
                                <th>Lessor</th>
                                <th>Type</th>
                                <th title="Monthly lease payment amount">Payment/Mo</th>
                                <th title="Right-of-Use Asset NBV">ROU Asset</th>
                                <th title="Current Lease Liability">Liability</th>
                                <th title="Months remaining">Remaining</th>
                                <th>Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    var table = $('#leases-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("accounting.leases.datatable") }}',
            data: function(d) {
                d.status = $('#filter-status').val();
                d.lease_type = $('#filter-type').val();
                d.lessor = $('#filter-lessor').val();
            }
        },
        columns: [
            { data: 'lease_number', name: 'lease_number' },
            { data: 'leased_item', name: 'leased_item' },
            { data: 'lessor_name', name: 'lessor_name' },
            { data: 'type_badge', name: 'lease_type' },
            { data: 'monthly_payment_formatted', name: 'monthly_payment' },
            { data: 'rou_asset_formatted', name: 'current_rou_asset_value' },
            { data: 'liability_formatted', name: 'current_lease_liability' },
            { data: 'remaining_term', name: 'end_date', orderable: false },
            { data: 'status_badge', name: 'status' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-center' }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        language: {
            processing: '<div class="spinner-border spinner-border-sm text-primary" role="status"><span class="sr-only">Loading...</span></div> Loading...',
            emptyTable: 'No leases found. <a href="{{ route("accounting.leases.create") }}">Create your first lease</a>.'
        }
    });

    // Filter button click
    $('#btn-filter').on('click', function() {
        table.draw();
    });

    // Reset button click
    $('#btn-reset').on('click', function() {
        $('#filter-status').val('');
        $('#filter-type').val('');
        $('#filter-lessor').val('');
        table.draw();
    });

    // Filter on enter key
    $('#filter-lessor').on('keypress', function(e) {
        if (e.which === 13) {
            table.draw();
        }
    });

    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.alert-dismissible').fadeOut('slow');
    }, 5000);
});
</script>
@endpush
