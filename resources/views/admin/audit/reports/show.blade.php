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

        {{-- Interactive Filter & Stats Bar --}}
        <div class="row mb-4">
            {{-- Search & Date Filters --}}
            <div class="col-lg-4 mb-3">
                <div class="card h-100 bg-white shadow-sm border-0 p-3">
                    <h6 class="text-dark font-weight-bold mb-3"><i class="mdi mdi-filter-variant"></i> Advanced Filters</h6>
                    <form method="GET" action="{{ route('audit.reports.show', $responsibility_key) }}" class="d-flex flex-column gap-2">
                        <div>
                            <label class="form-label small text-muted">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="{{ $startDate->format('Y-m-d') }}">
                        </div>
                        <div>
                            <label class="form-label small text-muted">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="{{ $endDate->format('Y-m-d') }}">
                        </div>
                        <button type="submit" class="btn btn-primary mt-2 w-100">
                            <i class="mdi mdi-refresh"></i> Update Worksheet
                        </button>
                    </form>
                </div>
            </div>

            {{-- Dynamic KPIs --}}
            <div class="col-lg-8 mb-3">
                <div class="row h-100">
                    @foreach($kpis as $kpi)
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 bg-white shadow-sm border-0 p-3 d-flex flex-column justify-content-between">
                                <span class="text-muted small text-uppercase font-weight-bold">{{ $kpi['label'] }}</span>
                                <h3 class="font-weight-bold my-2 {{ $kpi['class'] ?? 'text-dark' }}">{{ $kpi['value'] }}</h3>
                                <span class="text-muted small">Validated period metric</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Graphical Analytics Panel --}}
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-white shadow-sm border-0">
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
            </div>
        </div>

        {{-- Real DataTable Section --}}
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-white shadow-sm border-0">
                    <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-dark font-weight-bold"><i class="mdi mdi-database-outline"></i> Detailed Transaction Ledger</h5>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-xs btn-outline-success" onclick="window.print()">
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
                                            <table class="table table-striped table-bordered table-sm audit-datatable" id="auditDataTable_{{ $tabId }}">
                                                <thead>
                                                    <tr>
                                                        @foreach($tabInfo['headers'] as $header)
                                                            <th>{{ $header }}</th>
                                                        @endforeach
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse($tabInfo['rows'] as $row)
                                                        <tr>
                                                            @foreach($row as $cell)
                                                                <td>{!! $cell !!}</td>
                                                            @endforeach
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="{{ count($tabInfo['headers']) }}" class="text-center py-4 text-muted">
                                                                No records found for the active dates range in this tab.
                                                            </td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered table-sm" id="auditDataTable">
                                    <thead>
                                        <tr>
                                            @foreach($headers as $header)
                                                <th>{{ $header }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($rows as $row)
                                            <tr>
                                                @foreach($row as $cell)
                                                    <td>{!! $cell !!}</td>
                                                @endforeach
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="{{ count($headers) }}" class="text-center py-4 text-muted">
                                                    No logs captured for the active dates range.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

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

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

    // 2. Stamping Period Action
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
});
</script>
@endpush
@endsection
