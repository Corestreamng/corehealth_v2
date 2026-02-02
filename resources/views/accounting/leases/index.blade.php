@extends('admin.layouts.app')
@section('title', 'Lease Management')
@section('page_name', 'Accounting')
@section('subpage_name', 'Leases (IFRS 16)')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Leases', 'url' => '#', 'icon' => 'mdi-file-document-edit']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Stats Row -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card card-modern h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon-box bg-primary rounded-circle p-3 mr-3">
                            <i class="mdi mdi-file-document-multiple text-white mdi-24px"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Active Leases</h6>
                            <h4 class="mb-0">{{ $stats['active_count'] }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card card-modern h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon-box bg-info rounded-circle p-3 mr-3">
                            <i class="mdi mdi-office-building text-white mdi-24px"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total ROU Assets</h6>
                            <h4 class="mb-0">₦{{ number_format($stats['total_rou_asset'], 0) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card card-modern h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon-box bg-warning rounded-circle p-3 mr-3">
                            <i class="mdi mdi-scale-balance text-white mdi-24px"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Lease Liability</h6>
                            <h4 class="mb-0">₦{{ number_format($stats['total_liability'], 0) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card card-modern h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon-box bg-secondary rounded-circle p-3 mr-3">
                            <i class="mdi mdi-chart-line-variant text-white mdi-24px"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Monthly Depreciation</h6>
                            <h4 class="mb-0">₦{{ number_format($stats['monthly_depreciation'], 0) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secondary Stats Row -->
        <div class="row mb-4">
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="card card-modern h-100 border-left-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Due This Month</h6>
                                <h5 class="mb-0">₦{{ number_format($stats['payments_due_this_month'], 2) }}</h5>
                            </div>
                            <i class="mdi mdi-calendar-clock mdi-36px text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="card card-modern h-100 border-left-danger">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Overdue Payments</h6>
                                <h5 class="mb-0 {{ $stats['overdue_payments'] > 0 ? 'text-danger' : '' }}">₦{{ number_format($stats['overdue_payments'], 2) }}</h5>
                            </div>
                            <i class="mdi mdi-alert-circle mdi-36px {{ $stats['overdue_payments'] > 0 ? 'text-danger' : 'text-muted' }}"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="card card-modern h-100 border-left-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Expiring Soon (90 days)</h6>
                                <h5 class="mb-0 {{ $stats['expiring_soon'] > 0 ? 'text-warning' : '' }}">{{ $stats['expiring_soon'] }} leases</h5>
                            </div>
                            <i class="mdi mdi-clock-alert mdi-36px {{ $stats['expiring_soon'] > 0 ? 'text-warning' : 'text-muted' }}"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leases Table -->
        <div class="card card-modern">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="mdi mdi-file-document-edit mr-2"></i>Lease Agreements</h5>
                <div>
                    <div class="btn-group mr-2">
                        <a href="{{ route('accounting.leases.export.pdf', request()->query()) }}" class="btn btn-danger btn-sm">
                            <i class="mdi mdi-file-pdf"></i> PDF
                        </a>
                        <a href="{{ route('accounting.leases.export.excel', request()->query()) }}" class="btn btn-success btn-sm">
                            <i class="mdi mdi-file-excel"></i> Excel
                        </a>
                    </div>
                    <button type="button" class="btn btn-outline-info btn-sm" data-toggle="modal" data-target="#runDepreciationModal">
                        <i class="mdi mdi-play-circle"></i> Run Depreciation
                    </button>
                    <a href="{{ route('accounting.leases.reports.ifrs16') }}" class="btn btn-outline-primary btn-sm">
                        <i class="mdi mdi-file-chart"></i> IFRS 16 Report
                    </a>
                    <a href="{{ route('accounting.leases.create') }}" class="btn btn-primary btn-sm">
                        <i class="mdi mdi-plus"></i> New Lease
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="small text-muted">Status</label>
                        <select id="filter-status" class="form-control form-control-sm">
                            <option value="">All Status</option>
                            <option value="draft">Draft</option>
                            <option value="active">Active</option>
                            <option value="expired">Expired</option>
                            <option value="terminated">Terminated</option>
                            <option value="purchased">Purchased</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="small text-muted">Lease Type</label>
                        <select id="filter-type" class="form-control form-control-sm">
                            <option value="">All Types</option>
                            <option value="operating">Operating</option>
                            <option value="finance">Finance</option>
                            <option value="short_term">Short Term</option>
                            <option value="low_value">Low Value</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="small text-muted">Lessor</label>
                        <input type="text" id="filter-lessor" class="form-control form-control-sm" placeholder="Search lessor...">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-secondary mr-2" id="btn-filter">
                            <i class="mdi mdi-filter"></i> Apply
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-reset">
                            <i class="mdi mdi-refresh"></i> Reset
                        </button>
                    </div>
                </div>

                <!-- DataTable -->
                <div class="table-responsive">
                    <table id="leases-table" class="table table-striped table-bordered" style="width:100%">
                        <thead class="thead-dark">
                            <tr>
                                <th>Lease #</th>
                                <th>Leased Item</th>
                                <th>Lessor</th>
                                <th>Type</th>
                                <th>Monthly Payment</th>
                                <th>ROU Asset</th>
                                <th>Liability</th>
                                <th>Remaining</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Run Depreciation Modal -->
<div class="modal fade" id="runDepreciationModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('accounting.leases.depreciation.run') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="mdi mdi-play-circle mr-2"></i>Run ROU Depreciation</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="mdi mdi-information"></i> This will run monthly depreciation for all active leases.
                    </div>
                    <div class="form-group">
                        <label for="depreciation_date">Depreciation Date <span class="text-danger">*</span></label>
                        <input type="date" name="depreciation_date" id="depreciation_date" class="form-control"
                               value="{{ date('Y-m-d') }}" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="mdi mdi-play"></i> Run Depreciation
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
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 25
    });

    $('#btn-filter').on('click', function() {
        table.draw();
    });

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
});
</script>
@endpush
