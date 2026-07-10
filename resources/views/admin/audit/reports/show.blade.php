@extends('admin.layouts.app')

@section('title', $reportLabel)
@section('page_name', 'Internal Audit')
@section('subpage_name', $reportLabel)

@section('content')
{{-- Include premium custom breadcrumb partial --}}
@include('admin.audit.partials.breadcrumb', ['items' => [
    ['label' => $categoryLabel . ' Audits', 'url' => '#'],
    ['label' => $reportLabel]
]])

<div id="content-wrapper">
    <div class="container-fluid">
        
        {{-- Stamping Period Header Card --}}
        <div class="card mb-4 bg-white shadow-sm border-0">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h4 class="mb-1 text-dark">{{ $reportLabel }}</h4>
                    <p class="text-muted mb-0 small">
                        Active Audit Worksheet Period: <code>{{ $startDate->format('Y-m-d') }}</code> to <code>{{ $endDate->format('Y-m-d') }}</code>
                    </p>
                </div>
                
                <div class="d-flex align-items-center gap-2">
                    @if($stamp)
                        <div class="bg-success text-white px-3 py-2 rounded d-flex align-items-center gap-2 shadow-sm">
                            <i class="mdi mdi-shield-check mdi-24px"></i>
                            <div>
                                <div class="font-weight-bold small">Audit Stamp Applied</div>
                                <div class="small opacity-75">Approved by: {{ $stamp->auditor->surname ?? 'Auditor' }}</div>
                            </div>
                        </div>
                    @else
                        <button type="button" class="btn btn-warning" id="stampThisPeriodBtn">
                            <i class="mdi mdi-stamp"></i> Stamp Period as Correct
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <div class="row">
            {{-- Left Sidebar: Advanced Filters --}}
            <div class="col-lg-3 mb-4">
                <div class="card bg-white shadow-sm border-0 p-3 sticky-top" style="top: 20px; z-index: 100;">
                    <h6 class="text-dark font-weight-bold mb-3"><i class="mdi mdi-filter-variant"></i> Advanced Filters</h6>
                    <form method="GET" id="filterForm" action="{{ route('audit.reports.show', $responsibility_key) }}" class="d-flex flex-column gap-2">
                        <div>
                            <label class="form-label small text-muted font-weight-bold">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="{{ $startDate->format('Y-m-d') }}">
                        </div>
                        <div>
                            <label class="form-label small text-muted font-weight-bold">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="{{ $endDate->format('Y-m-d') }}">
                        </div>
                        
                        {{-- Context-Aware Filters --}}
                        @if(isset($filters) && count($filters) > 0)
                            @foreach($filters as $filter)
                                <div>
                                    <label class="form-label small text-muted font-weight-bold mb-1">{{ $filter['label'] }}</label>
                                    @if($filter['type'] === 'select')
                                        <select name="{{ $filter['name'] }}" class="form-control form-select-sm">
                                            <option value="">All {{ $filter['label'] }}s</option>
                                            @foreach($filter['options'] as $val => $lbl)
                                                <option value="{{ $val }}" {{ (string)$filter['value'] === (string)$val ? 'selected' : '' }}>{{ $lbl }}</option>
                                            @endforeach
                                        </select>
                                    @elseif($filter['type'] === 'number')
                                        <input type="number" step="any" name="{{ $filter['name'] }}" class="form-control form-control-sm" value="{{ $filter['value'] }}" placeholder="Min/Max/Value">
                                    @else
                                        <input type="text" name="{{ $filter['name'] }}" class="form-control form-control-sm" value="{{ $filter['value'] }}">
                                    @endif
                                </div>
                            @endforeach
                        @endif
                        
                        <button type="submit" class="btn btn-primary mt-2 w-100">
                            <i class="mdi mdi-refresh"></i> Update Worksheet
                        </button>
                    </form>
                </div>
            </div>


            {{-- Right Main Content Area --}}
            <div class="col-lg-9">
                {{-- Dynamic KPIs --}}
                <div class="row mb-4">
                    @foreach($kpis as $kpi)
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card h-100 bg-white shadow-sm border-0 p-3 d-flex flex-column justify-content-between">
                                <span class="text-muted small text-uppercase font-weight-bold">{{ $kpi['label'] }}</span>
                                <h3 class="font-weight-bold my-2 {{ $kpi['class'] ?? 'text-dark' }}">{{ $kpi['value'] }}</h3>
                                <span class="text-muted small">Validated period metric</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Graphical Analytics Panel --}}
                <div class="card bg-white shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-dark font-weight-bold"><i class="mdi mdi-chart-line text-indigo"></i> Graphical Analysis & Daily Trends</h5>
                        <span class="badge bg-light text-muted">Chart.js Visualization</span>
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 260px; width: 100%;">
                            <canvas id="auditReportChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Real DataTable Section --}}
                <div class="card bg-white shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-dark font-weight-bold"><i class="mdi mdi-database-outline"></i> Detailed Transaction Ledger</h5>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-xs btn-outline-success" data-toggle="modal" data-bs-toggle="modal" data-target="#printReportModal" data-bs-target="#printReportModal">
                                <i class="mdi mdi-printer"></i> Print Report
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        @if(isset($tabbedData) && count($tabbedData) > 0)
                            <ul class="nav nav-tabs mb-3" id="auditReportTabs" role="tablist">
                                @foreach($tabbedData as $tabId => $tabInfo)
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link {{ $loop->first ? 'active' : '' }}" id="{{ $tabId }}-tab" data-toggle="tab" href="#{{ $tabId }}" role="tab" aria-controls="{{ $tabId }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                            {{ $tabInfo['label'] }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="tab-content" id="auditReportTabContent">
                                @foreach($tabbedData as $tabId => $tabInfo)
                                    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="{{ $tabId }}" role="tabpanel" aria-labelledby="{{ $tabId }}-tab">
                                        <div class="table-responsive mt-2">
                                            <table class="table table-striped table-bordered table-sm audit-datatable w-100" id="auditDataTable_{{ $tabId }}" style="width: 100%;">
                                                <thead>
                                                    <tr>
                                                        @foreach($tabInfo['headers'] as $header)
                                                            <th>{{ $header }}</th>
                                                        @endforeach
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {{-- Dynamically loaded via server-side DataTable AJAX --}}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered table-sm audit-datatable w-100" id="auditDataTable_default" style="width: 100%;">
                                    <thead>
                                        <tr>
                                            @foreach($headers as $header)
                                                <th>{{ $header }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {{-- Dynamically loaded via server-side DataTable AJAX --}}
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div> {{-- End Right Main Content --}}
        </div> {{-- End Outer Layout Row --}}

    </div>
</div>

{{-- Stamp Modal --}}
<div class="modal fade" id="stampModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-white">
                <h5 class="modal-title text-dark">Apply Digital Approval Stamp</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="stampForm">
                @csrf
                <input type="hidden" name="responsibility_key" value="{{ $responsibility_key }}">
                <input type="hidden" name="start_date" value="{{ $startDate->format('Y-m-d') }}">
                <input type="hidden" name="end_date" value="{{ $endDate->format('Y-m-d') }}">
                <div class="modal-body d-flex flex-column gap-3">
                    <div class="bg-light p-2 rounded border">
                        <div class="text-muted small">Worksheet Responsibility:</div>
                        <div class="font-weight-bold text-dark">{{ $reportLabel }}</div>
                        <div class="text-muted small mt-1">Audit Period: <code>{{ $startDate->format('Y-m-d') }}</code> to <code>{{ $endDate->format('Y-m-d') }}</code></div>
                    </div>
                    <div>
                        <label class="form-label small text-muted font-weight-bold">Auditing Notes / Review Comments</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Verify reconciliations are complete and correct..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-white border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-indigo text-white" style="background: #4f46e5;">Apply Approval Stamp</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Print Modal --}}
<div class="modal fade" id="printReportModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-white">
                <h5 class="modal-title text-dark">Print Audit Report</h5>
                <button type="button" class="btn-close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('audit.reports.print', $responsibility_key) }}" method="GET" target="_blank">
                <input type="hidden" name="start_date" value="{{ request('start_date', $startDate->format('Y-m-d')) }}">
                <input type="hidden" name="end_date" value="{{ request('end_date', $endDate->format('Y-m-d')) }}">
                @if(isset($filters) && count($filters) > 0)
                    @foreach($filters as $filter)
                        <input type="hidden" name="{{ $filter['name'] }}" value="{{ $filter['value'] }}">
                    @endforeach
                @endif

                <div class="modal-body d-flex flex-column gap-3">
                    <div class="bg-light p-2 rounded border mb-3">
                        <div class="text-muted small">Select the sections you want to include in the printed report.</div>
                    </div>
                    
                    @if(isset($tabbedData) && count($tabbedData) > 0)
                        <div class="form-group">
                            <label class="font-weight-bold d-block mb-2">Report Sections to Print</label>
                            @foreach($tabbedData as $tabId => $tabInfo)
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="tabs[]" value="{{ $tabId }}" id="print_tab_{{ $tabId }}" checked>
                                    <label class="form-check-label" for="print_tab_{{ $tabId }}">
                                        {{ $tabInfo['label'] }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="alert alert-info py-2 small">
                            This report consists of a single combined dataset.
                        </div>
                    @endif

                    <div class="form-group mb-0">
                        <label class="font-weight-bold d-block mb-1">Max Rows per Section</label>
                        <select name="max_rows" class="form-control form-select-sm">
                            <option value="-1">All Rows (Default)</option>
                            <option value="50">50 Rows</option>
                            <option value="100">100 Rows</option>
                            <option value="500">500 Rows</option>
                            <option value="1000">1,000 Rows</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-white border-0">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="mdi mdi-printer"></i> Generate Print View</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
{{-- Explicitly load dynamic server-side DataTables JS --}}
<script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
<script>
$(document).ready(function() {
    // 1. Initializing Chart.js
    var ctx = document.getElementById('auditReportChart').getContext('2d');
    var chartLabels = {!! json_encode($chart['labels'] ?? []) !!};
    var chartData = {!! json_encode($chart['datasets'] ?? []) !!};

    if (chartLabels.length === 0) {
        chartLabels = ['Dummy Label'];
        chartData = [0];
    }

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Period Audited Sum / Value',
                data: chartData,
                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                borderColor: '#4f46e5',
                borderWidth: 2,
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#f1f5f9'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });

    // 2. Initialize Rich Server-Side DataTables Dynamically
    $('.audit-datatable').each(function() {
        var $table = $(this);
        var tableId = $table.attr('id');
        var tabId = tableId.replace('auditDataTable_', '');

        var colCount = $table.find('thead th').length;
        $table.DataTable({
            processing: true,
            serverSide: true,
            pageLength: 25,
            order: [[ colCount - 1, 'desc' ]], // Sort by last column (date) descending — latest first
            ajax: {
                url: window.location.href,
                data: function(d) {
                    d.datatable_tab = tabId;
                    // Attach all filter inputs from the card form
                    $('#filterForm').serializeArray().forEach(function(item) {
                        d[item.name] = item.value;
                    });
                }
            },
            // Maps arrays directly to the respective column index dynamically
            columns: $table.find('thead th').map(function(idx) {
                return { data: idx, orderable: true, searchable: true };
            }).get(),
            language: {
                processing: '<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>'
            }
        });
    });

    // Recalculate columns width when a tab is shown to fix width issue in hidden tabs
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
    });

    // 3. Stamping Period Action
    $('#stampThisPeriodBtn').on('click', function() {
        $('#stampModal').modal('show');
    });

    $('#stampForm').on('submit', function(e) {
        e.preventDefault();
        var btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).text('Applying Stamp...');

        $.ajax({
            url: "{{ route('audit.stamps.approve') }}",
            method: "POST",
            data: $(this).serialize(),
            success: function(res) {
                $('#stampModal').modal('hide');
                alert(res.message);
                window.location.reload();
            },
            error: function(err) {
                btn.prop('disabled', false).text('Apply Approval Stamp');
                alert('An error occurred while applying the stamp.');
            }
        });
    });
    // Handle Physical Stock Verification Save
    $(document).on('click', '.save-physical-count-btn', function() {
        var btn = $(this);
        var stockId = btn.data('stock-id');
        var physVal = $('#phys_count_' + stockId).val();
        
        if (!physVal) {
            alert('Please enter a physical count');
            return;
        }

        btn.prop('disabled', true).text('Saving...');
        $.ajax({
            url: "{{ route('audit.physical-stock.save') }}",
            method: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                store_id: btn.data('store'),
                product_id: btn.data('product'),
                system_value: btn.data('system'),
                physical_value: physVal
            },
            success: function(res) {
                alert(res.message);
                window.location.reload();
            },
            error: function(err) {
                btn.prop('disabled', false).text('Save');
                alert(err.responseJSON?.message || 'Error saving count');
            }
        });
    });
});
</script>
@endpush
@endsection
