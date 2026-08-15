@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-account-cash"></i> Receivables & Debtors Audit</h4>
</div>

@include('admin.audit_workbench.partials.datetime_filter', ['zoneKey' => 'receivables', 'zoneLabel' => 'Receivables & Debtors Zone'])

{{-- ============================== PARENT-LEVEL NAV ============================== --}}
<ul class="nav nav-pills mb-3" id="rd-parent-tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active font-weight-bold px-4" id="rd-audit-register-tab" data-bs-toggle="pill" data-bs-target="#rd-audit-register" type="button" role="tab">
            <i class="mdi mdi-clipboard-check-outline"></i> Audit Register
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link font-weight-bold px-4" id="rd-stories-tab" data-bs-toggle="pill" data-bs-target="#rd-stories" type="button" role="tab">
            <i class="mdi mdi-chart-timeline-variant"></i> Debtor Stories
        </button>
    </li>
</ul>

<div class="tab-content" id="rd-parent-content">

{{-- ============================== TAB 1: AUDIT REGISTER (existing content, untouched) ============================== --}}
<div class="tab-pane fade show active" id="rd-audit-register" role="tabpanel">
<div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden" style="width: 100%;">
    <div class="card-header bg-white border-bottom p-0">
        <ul class="nav nav-tabs nav-fill audit-tabs" id="receivablesTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-3 font-weight-bold" id="staff-tab" data-bs-toggle="tab" data-bs-target="#staff" type="button" role="tab">
                    <i class="mdi mdi-account-tie text-info"></i> Staff Receivables
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 font-weight-bold" id="patients-tab" data-bs-toggle="tab" data-bs-target="#patients" type="button" role="tab">
                    <i class="mdi mdi-account-injury text-warning"></i> Patient Debtors
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 font-weight-bold" id="corporate-tab" data-bs-toggle="tab" data-bs-target="#corporate" type="button" role="tab">
                    <i class="mdi mdi-domain text-primary"></i> Corporate / Retainership
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 font-weight-bold" id="unremitted-hmo-tab" data-bs-toggle="tab" data-bs-target="#unremitted-hmo" type="button" role="tab">
                    <i class="mdi mdi-clock-alert text-warning"></i> Unremitted HMO Claims
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 font-weight-bold" id="payroll-deductions-tab" data-bs-toggle="tab" data-bs-target="#payroll-deductions" type="button" role="tab">
                    <i class="mdi mdi-cash-minus text-secondary"></i> Payroll Deductions
                </button>
            </li>
        </ul>
    </div>
    
    <div class="card-body p-4 bg-light">
        <div class="tab-content" id="receivablesTabsContent">
            
            {{-- Staff Receivables Tab --}}
            <div class="tab-pane fade show active" id="staff" role="tabpanel">
                <div class="row g-3 mb-4">
                    <div class="col-md-4"><div class="card bg-info text-white h-100 shadow-sm border-0"><div class="card-body"><h6>Total Pending Staff Debt</h6><h3 class="mb-0">₦{{ number_format($kpis['total_staff_debt'] ?? 0, 2) }}</h3></div></div></div>
                    <div class="col-md-4"><div class="card bg-white text-dark h-100 shadow-sm border-0"><div class="card-body"><h6 class="text-muted">Active Deductions</h6><h3 class="mb-0">{{ $kpis['active_staff_deductions_count'] ?? 0 }}</h3></div></div></div>
                    <div class="col-md-4"><div class="card bg-success text-white h-100 shadow-sm border-0"><div class="card-body"><h6>Cleared This Period</h6><h3 class="mb-0">₦{{ number_format($kpis['cleared_staff_debt'] ?? 0, 2) }}</h3></div></div></div>
                </div>
                <div class="card shadow-sm border-0"><div class="card-body p-3"><div class="table-responsive">
                    <table id="table-staff-receivables" class="table table-hover align-middle w-100">
                        <thead class="bg-light"><tr><th>Date</th><th>Staff Details</th><th>Item Description</th><th>Billed Amount</th><th>Audit Action</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div></div></div>
            </div>

            {{-- Patient Debtors Tab --}}
            <div class="tab-pane fade" id="patients" role="tabpanel">
                <div class="row g-3 mb-4">
                    <div class="col-md-3"><div class="card bg-danger text-white h-100 shadow-sm border-0"><div class="card-body"><h6>Total Overdue Debt</h6><h3 class="mb-0">₦{{ number_format($kpis['total_patient_debt'] ?? 0, 2) }}</h3></div></div></div>
                    <div class="col-md-3"><div class="card bg-primary text-white h-100 shadow-sm border-0"><div class="card-body"><h6>Total Patient Deposits</h6><h3 class="mb-0">₦{{ number_format($kpis['total_patient_deposits'] ?? 0, 2) }}</h3></div></div></div>
                    <div class="col-md-3"><div class="card bg-white text-dark h-100 shadow-sm border-0"><div class="card-body"><h6 class="text-muted">Deficit Accounts</h6><h3 class="mb-0 text-danger">{{ $kpis['deficit_accounts_count'] ?? 0 }}</h3></div></div></div>
                    <div class="col-md-3"><div class="card bg-white text-dark h-100 shadow-sm border-0"><div class="card-body"><h6 class="text-muted">Average Debt</h6><h3 class="mb-0 text-warning">₦{{ number_format($kpis['avg_patient_debt'] ?? 0, 2) }}</h3></div></div></div>
                </div>
                <div class="card shadow-sm border-0"><div class="card-body p-3"><div class="table-responsive">
                    <table id="table-patient-debtors" class="table table-hover align-middle w-100">
                        <thead class="bg-light"><tr><th>Patient Details</th><th>Account Balance</th><th>Coverage Mode</th><th>Audit Action</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div></div></div>
            </div>

            {{-- Corporate / Retainership Tab --}}
            <div class="tab-pane fade" id="corporate" role="tabpanel">
                <div class="row g-3 mb-4">
                    <div class="col-md-3"><div class="card bg-secondary text-white h-100 shadow-sm border-0"><div class="card-body"><h6>Total Corporate Debt</h6><h3 class="mb-0">₦{{ number_format($kpis['total_corporate_debt'] ?? 0, 2) }}</h3></div></div></div>
                    <div class="col-md-3"><div class="card bg-white text-dark h-100 shadow-sm border-0"><div class="card-body"><h6 class="text-muted">Unpaid Invoices</h6><h3 class="mb-0">{{ $kpis['unpaid_corporate_invoices'] ?? 0 }}</h3></div></div></div>
                    <div class="col-md-3"><div class="card bg-warning text-dark h-100 shadow-sm border-0"><div class="card-body"><h6>Aging 31-60 Days</h6><h3 class="mb-0">₦{{ number_format($kpis['corp_aging_30_60'] ?? 0, 2) }}</h3></div></div></div>
                    <div class="col-md-3"><div class="card bg-danger text-white h-100 shadow-sm border-0"><div class="card-body"><h6>Aging 60+ Days</h6><h3 class="mb-0">₦{{ number_format($kpis['corp_aging_60_plus'] ?? 0, 2) }}</h3></div></div></div>
                </div>
                <div class="card shadow-sm border-0"><div class="card-body p-3"><div class="table-responsive">
                    <table id="table-corporate-retainership" class="table table-hover align-middle w-100">
                        <thead class="bg-light"><tr><th>Date</th><th>Organization Details</th><th>Financial Summary</th><th>Status</th><th>Audit Action</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div></div></div>
            </div>

            {{-- Unremitted HMO Claims Tab --}}
            <div class="tab-pane fade" id="unremitted-hmo" role="tabpanel">
                <div class="row g-3 mb-4">
                    <div class="col-md-3"><div class="card bg-success text-white h-100 shadow-sm border-0"><div class="card-body"><h6>Total Unremitted Claims</h6><h3 class="mb-0">₦{{ number_format($kpis['total_unremitted_hmo'] ?? 0, 2) }}</h3></div></div></div>
                    <div class="col-md-3"><div class="card bg-white text-dark h-100 shadow-sm border-0"><div class="card-body"><h6 class="text-muted">Outstanding Claims Count</h6><h3 class="mb-0">{{ $kpis['unremitted_hmo_count'] ?? 0 }}</h3></div></div></div>
                    <div class="col-md-3"><div class="card bg-warning text-dark h-100 shadow-sm border-0"><div class="card-body"><h6>Aging 31-60 Days</h6><h3 class="mb-0">₦{{ number_format($kpis['hmo_aging_30_60'] ?? 0, 2) }}</h3></div></div></div>
                    <div class="col-md-3"><div class="card bg-danger text-white h-100 shadow-sm border-0"><div class="card-body"><h6>Aging 90+ Days</h6><h3 class="mb-0">₦{{ number_format($kpis['hmo_aging_90_plus'] ?? 0, 2) }}</h3></div></div></div>
                </div>
                <div class="card shadow-sm border-0"><div class="card-body p-3"><div class="table-responsive">
                    <table id="table-unremitted-hmo" class="table table-hover align-middle w-100">
                        <thead class="bg-light"><tr><th>Date</th><th>Patient & HMO</th><th>Claim Details</th><th>Aging</th><th>Audit Action</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div></div></div>
            </div>
            
            {{-- Payroll Deductions Tab --}}
            <div class="tab-pane fade" id="payroll-deductions" role="tabpanel">
                <div class="table-responsive bg-white shadow-sm rounded">
                    <table class="table table-hover table-bordered align-middle mb-0" id="payrollDeductionsTable" style="width: 100%;">
                        <thead class="table-light"><tr><th>Staff Member</th><th>Total Accrued Bills</th><th>Total Unpaid (To Deduct)</th><th>Action</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </div>
</div>
</div>{{-- /rd-audit-register --}}

{{-- ============================== TAB 2: DEBTOR STORIES ============================== --}}
<div class="tab-pane fade" id="rd-stories" role="tabpanel">
<div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden" style="width: 100%;">
    <div class="card-header bg-white border-bottom p-0">
        <ul class="nav nav-tabs nav-fill" id="rdStoryTabs" role="tablist" style="font-size: 0.85rem;">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-2 font-weight-bold" data-bs-toggle="tab" data-bs-target="#rd-story-corp" data-story="corporate-exposure" type="button" role="tab">
                    <i class="mdi mdi-domain"></i> Corp Exposure
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 font-weight-bold" data-bs-toggle="tab" data-bs-target="#rd-story-hmo" data-story="hmo-claims-aging" type="button" role="tab">
                    <i class="mdi mdi-clock-alert"></i> HMO Claims Aging
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 font-weight-bold" data-bs-toggle="tab" data-bs-target="#rd-story-staff" data-story="staff-debt-ledger" type="button" role="tab">
                    <i class="mdi mdi-account-tie"></i> Staff Debt Ledger
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 font-weight-bold" data-bs-toggle="tab" data-bs-target="#rd-story-wallet" data-story="patient-wallet" type="button" role="tab">
                    <i class="mdi mdi-wallet"></i> Patient Wallets
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 font-weight-bold" data-bs-toggle="tab" data-bs-target="#rd-story-settle" data-story="settlement-activity" type="button" role="tab">
                    <i class="mdi mdi-cash-check"></i> Settlement Activity
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body p-4 bg-light">
        <div class="tab-content" id="rdStoryContent">
            @foreach(['rd-story-corp' => 'corporate-exposure', 'rd-story-hmo' => 'hmo-claims-aging', 'rd-story-staff' => 'staff-debt-ledger', 'rd-story-wallet' => 'patient-wallet', 'rd-story-settle' => 'settlement-activity'] as $paneId => $storySlug)
            <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="{{ $paneId }}" role="tabpanel" data-story="{{ $storySlug }}" data-loaded="0">
                <div class="row g-3 mb-4 story-cards">
                    <div class="col-12 text-center py-4"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div></div>
                </div>
                <div class="card shadow-sm border-0"><div class="card-body p-3"><div class="table-responsive">
                    <table class="table table-hover align-middle w-100 story-table" id="table-{{ $paneId }}">
                        <thead class="bg-light"><tr></tr></thead>
                        <tbody></tbody>
                    </table>
                </div></div></div>
            </div>
            @endforeach
        </div>
    </div>
</div>
</div>{{-- /rd-stories --}}

</div>{{-- /rd-parent-content --}}
@endsection

@push('audit_scripts')
<script>

$(document).ready(function() {
    let commonDtConfig = {
        dom: '<"d-flex justify-content-between align-items-center mb-3"<"d-flex gap-2"B><"d-flex align-items-center"f>>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
        iDisplayLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        buttons: ['pageLength', 'copy', 'excel', 'pdf', 'print', 'colvis'],
        processing: true, serverSide: true, responsive: true, order: [[0, 'desc']]
    };

    function appendMultidimData(d) {
        d.start_date = $('#filter_start_date').val();
        d.end_date = $('#filter_end_date').val();
        d.hmo_scheme_id = $('#filter_hmo_scheme_id').val();
        d.hmo_id = $('#filter_hmo_id').val();
        d.gender = $('#filter_gender').val();
        d.age_range = $('#filter_age_range').val();
        d.audit_status = $('#filter_audit_status').val();
    }

    // Existing server-side DataTables (Audit Register)
    $('#table-staff-receivables').DataTable($.extend({}, commonDtConfig, {
        ajax: { url: "{{ route('audit.receivables-debtors.data', 'staff-receivables') }}", data: appendMultidimData },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'staff_details', name: 'staff.name' },
            { data: 'item_details', name: 'product.product_name' },
            { data: 'amount_formatted', name: 'payable_amount' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));
    $('#table-patient-debtors').DataTable($.extend({}, commonDtConfig, {
        ajax: { url: "{{ route('audit.receivables-debtors.data', 'patient-debtors') }}", data: appendMultidimData },
        columns: [
            { data: 'patient_details', name: 'patient.user.surname' },
            { data: 'balance_formatted', name: 'balance' },
            { data: 'coverage', name: 'patient.hmo.name' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));
    $('#table-corporate-retainership').DataTable($.extend({}, commonDtConfig, {
        ajax: { url: "{{ route('audit.receivables-debtors.data', 'corporate-retainership') }}", data: appendMultidimData },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'org_details', name: 'organization.name' },
            { data: 'financials', name: 'total_amount' },
            { data: 'status_badge', name: 'status' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));
    $('#table-unremitted-hmo').DataTable($.extend({}, commonDtConfig, {
        ajax: { url: "{{ route('audit.receivables-debtors.data', 'unremitted-hmo') }}", data: appendMultidimData },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'patient_hmo', name: 'patient.user.surname' },
            { data: 'claim_details', name: 'claims_amount' },
            { data: 'aging_badge', name: 'created_at' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));
    $('#payrollDeductionsTable').DataTable($.extend({}, commonDtConfig, {
        ajax: { url: "{{ route('audit.receivables-debtors.data', 'payroll-deductions') }}", data: appendMultidimData },
        columns: [
            { data: 'staff_details', name: 'surname' },
            { data: 'total_accrued', name: 'total_amount' },
            { data: 'total_outstanding', name: 'total_outstanding' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    // ============================================================
    // STORY SUB-TAB AJAX LOADER (uses shared top datetime filter component)
    // ============================================================
    var rdStoryDataUrl = "{{ route('audit.receivables-stories.data', '__STORY__') }}";
    var rdStoryDatatables = {};

    function formatNaira(val) {
        if (typeof val !== 'number') return val;
        return '₦' + val.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function loadRdStory(paneEl) {
        var $pane = $(paneEl);
        var story = $pane.data('story');
        if ($pane.data('loaded') == 1) return;

        var url = rdStoryDataUrl.replace('__STORY__', story);
        var params = {
            start_date: $('#filter_start_date').val(),
            end_date: $('#filter_end_date').val(),
            hmo_scheme_id: $('#filter_hmo_scheme_id').val(),
            hmo_id: $('#filter_hmo_id').val(),
            gender: $('#filter_gender').val(),
            age_range: $('#filter_age_range').val(),
            audit_status: $('#filter_audit_status').val(),
        };

        $pane.find('.story-cards').html('<div class="col-12 text-center py-4"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div></div>');

        $.get(url, params, function(data) {
            // Render cards
            var cardsHtml = '';
            if (data.cards) {
                var colSize = data.cards.length <= 3 ? 4 : (data.cards.length <= 4 ? 3 : 2);
                data.cards.forEach(function(card) {
                    cardsHtml += '<div class="col-md-' + colSize + ' col-6 mb-2"><div class="card shadow-sm border-0 h-100 ' + card.class + '"><div class="card-body py-3 px-3"><h6 class="mb-1" style="font-size:0.8rem;">' + card.label + '</h6><h4 class="mb-0 font-weight-bold">' + card.value + '</h4></div></div></div>';
                });
            }
            $pane.find('.story-cards').html(cardsHtml);

            // Datatable Lifecycle Fix: Destroy existing DataTable BEFORE touching DOM
            var $table = $pane.find('.story-table');
            var tableId = $table.attr('id');
            if (tableId && $.fn.DataTable.isDataTable('#' + tableId)) {
                $('#' + tableId).DataTable().clear().destroy();
            }

            // Rebuild DOM structure
            $table.empty().append('<thead class="bg-light"><tr></tr></thead><tbody></tbody>');

            // Render headers
            var $tr = $table.find('thead tr');
            if (data.headers && data.headers.length > 0) {
                data.headers.forEach(function(h) { $tr.append('<th>' + h + '</th>'); });
            }

            // Render rows
            var $tbody = $table.find('tbody');
            if (data.rows && data.rows.length > 0) {
                data.rows.forEach(function(row) {
                    var trHtml = '<tr>';
                    Object.values(row).forEach(function(val) {
                        if (typeof val === 'number') val = formatNaira(val);
                        trHtml += '<td>' + val + '</td>';
                    });
                    trHtml += '</tr>';
                    $tbody.append(trHtml);
                });
            }

            // Re-initialize DataTable safely
            $table.DataTable({
                dom: '<"d-flex justify-content-between align-items-center mb-3"<"d-flex gap-2"B><"d-flex align-items-center"f>>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
                buttons: ['copy', 'excel', 'pdf', 'print'],
                paging: true, pageLength: 25, order: [], responsive: true, destroy: true,
                language: {
                    zeroRecords: "No data available for this period.",
                    emptyTable: "No data available for this period."
                }
            });
            $pane.data('loaded', 1);


        }).fail(function() {
            $pane.find('.story-cards').html('<div class="col-12"><div class="alert alert-danger">Failed to load story data.</div></div>');
        });
    }

    // Load first story when Stories parent tab is activated
    $('#rd-stories-tab').on('shown.bs.tab', function() {
        window.location.hash = 'rd-stories';
        var $activePane = $('#rdStoryContent .tab-pane.active');
        if ($activePane.length) loadRdStory($activePane[0]);
    });

    // Load story when sub-tab is clicked
    $('#rdStoryTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        var target = $(e.target).data('bs-target');
        window.location.hash = 'rd-stories:' + target.replace('#', '');
        loadRdStory($(target)[0]);
    });

    // Hash tab restoration on load
    if (window.location.hash) {
        var parts = window.location.hash.replace('#', '').split(':');
        if (parts[0] === 'rd-stories') {
            $('#rd-stories-tab').tab('show');
            if (parts[1]) {
                var $subBtn = $('#rdStoryTabs button[data-bs-target="#' + parts[1] + '"]');
                if ($subBtn.length) {
                    $subBtn.tab('show');
                    loadRdStory($('#' + parts[1])[0]);
                }
            }
        }
    }

    // Re-load stories when filters change
    $('#apply_filters_btn').on('click', function() {
        $('#rdStoryContent .tab-pane').data('loaded', 0);
        var $activePane = $('#rdStoryContent .tab-pane.active');
        if ($activePane.length && $('#rd-stories').hasClass('active')) loadRdStory($activePane[0]);
    });

});
</script>
@endpush
