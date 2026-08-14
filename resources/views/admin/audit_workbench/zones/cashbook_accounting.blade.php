@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-cash-register"></i> Cash Book & Accounting Ledgers Audit</h4>
</div>

@include('admin.audit_workbench.partials.datetime_filter', ['zoneKey' => 'cashbook-accounting', 'zoneLabel' => 'Cash Book & Accounting Ledgers Zone'])

{{-- ============================== PARENT-LEVEL NAV ============================== --}}
<ul class="nav nav-pills mb-3" id="cb-parent-tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active font-weight-bold px-4" id="cb-audit-register-tab" data-bs-toggle="pill" data-bs-target="#cb-audit-register" type="button" role="tab">
            <i class="mdi mdi-clipboard-check-outline"></i> Audit Register
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link font-weight-bold px-4" id="cb-stories-tab" data-bs-toggle="pill" data-bs-target="#cb-stories" type="button" role="tab">
            <i class="mdi mdi-chart-timeline-variant"></i> Financial Stories
        </button>
    </li>
</ul>

<div class="tab-content" id="cb-parent-content">

{{-- ============================== TAB 1: AUDIT REGISTER (existing content, untouched) ============================== --}}
<div class="tab-pane fade show active" id="cb-audit-register" role="tabpanel">
<div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden" style="width: 100%;">
    <div class="card-header bg-white border-bottom p-0">
        <ul class="nav nav-tabs nav-fill audit-tabs" id="cashbookTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-3 font-weight-bold" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab">
                    <i class="mdi mdi-receipt text-success"></i> Cash Book & Daily Receipts
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 font-weight-bold" id="ledger-tab" data-bs-toggle="tab" data-bs-target="#ledger" type="button" role="tab">
                    <i class="mdi mdi-book-open-page-variant text-info"></i> General Ledger Lines
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 font-weight-bold" id="bank-recon-tab" data-bs-toggle="tab" data-bs-target="#bank-recon" type="button" role="tab">
                    <i class="mdi mdi-bank text-primary"></i> Bank Reconciliation
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 font-weight-bold" id="shift-audits-tab" data-bs-toggle="tab" data-bs-target="#shift-audits" type="button" role="tab">
                    <i class="mdi mdi-account-clock text-warning"></i> Shift Audits
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 font-weight-bold" id="expenses-payroll-tab" data-bs-toggle="tab" data-bs-target="#expenses-payroll" type="button" role="tab">
                    <i class="mdi mdi-cash-multiple text-danger"></i> Expenses & Payroll
                </button>
            </li>
        </ul>
    </div>
    
    <div class="card-body p-4 bg-light">
        <div class="tab-content" id="cashbookTabsContent">
            
            {{-- Payments Tab --}}
            <div class="tab-pane fade show active" id="payments" role="tabpanel">
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card bg-success text-white h-100 shadow-sm border-0"><div class="card-body"><h6>Cash Receipts</h6><h3 class="mb-0">₦{{ number_format($kpis['total_cash_receipts'] ?? 0, 2) }}</h3></div></div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-primary text-white h-100 shadow-sm border-0"><div class="card-body"><h6>POS / Bank Transfer</h6><h3 class="mb-0">₦{{ number_format($kpis['total_pos_receipts'] ?? 0, 2) }}</h3></div></div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white h-100 shadow-sm border-0"><div class="card-body"><h6>Wallet Deposits</h6><h3 class="mb-0">₦{{ number_format($kpis['total_account_deposits'] ?? 0, 2) }}</h3></div></div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white h-100 shadow-sm border-0"><div class="card-body"><h6>Refunds / Withdrawals</h6><h3 class="mb-0">₦{{ number_format($kpis['total_withdrawals'] ?? 0, 2) }}</h3></div></div>
                    </div>
                </div>
                <div class="card shadow-sm border-0"><div class="card-body p-3"><div class="table-responsive">
                    <table id="table-payments" class="table table-hover align-middle w-100">
                        <thead class="bg-light"><tr><th>Date & Time</th><th>Receipt & Patient</th><th>Payment Method</th><th>Amount</th><th>Receiving Cashier</th><th>Audit Action</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div></div></div>
            </div>

            {{-- General Ledger Tab --}}
            <div class="tab-pane fade" id="ledger" role="tabpanel">
                <div class="row g-3 mb-4">
                    <div class="col-md-3"><div class="card bg-primary text-white h-100 shadow-sm border-0"><div class="card-body"><h6>Total Debits</h6><h3 class="mb-0">₦{{ number_format($kpis['total_debits'] ?? 0, 2) }}</h3></div></div></div>
                    <div class="col-md-3"><div class="card bg-success text-white h-100 shadow-sm border-0"><div class="card-body"><h6>Total Credits</h6><h3 class="mb-0">₦{{ number_format($kpis['total_credits'] ?? 0, 2) }}</h3></div></div></div>
                    <div class="col-md-3"><div class="card bg-info text-white h-100 shadow-sm border-0"><div class="card-body"><h6>Monthly Revenue</h6><h3 class="mb-0">₦{{ number_format($kpis['monthly_revenue'] ?? 0, 2) }}</h3></div></div></div>
                    <div class="col-md-3"><div class="card bg-warning text-dark h-100 shadow-sm border-0"><div class="card-body"><h6>Monthly Expenses</h6><h3 class="mb-0">₦{{ number_format($kpis['monthly_expenses'] ?? 0, 2) }}</h3></div></div></div>
                </div>
                <div class="card shadow-sm border-0"><div class="card-body p-3"><div class="table-responsive">
                    <table id="table-ledger" class="table table-hover align-middle w-100">
                        <thead class="bg-light"><tr><th>Date</th><th>Account Code & Name</th><th>Debit</th><th>Credit</th><th>Narration / Reference</th><th>Audit Action</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div></div></div>
            </div>

            {{-- Bank Reconciliation Tab --}}
            <div class="tab-pane fade" id="bank-recon" role="tabpanel">
                <div class="card shadow-sm border-0"><div class="card-body p-3"><div class="table-responsive">
                    <table id="table-bank-recon" class="table table-hover align-middle w-100">
                        <thead class="bg-light"><tr><th>Statement Date</th><th>Bank Account Details</th><th>Balances (GL vs Bank)</th><th>Unreconciled Variance</th><th>Audit Action</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div></div></div>
            </div>

            {{-- Shift Audits Tab --}}
            <div class="tab-pane fade" id="shift-audits" role="tabpanel">
                <div class="table-responsive bg-white shadow-sm rounded">
                    <table class="table table-hover table-bordered align-middle mb-0" id="shiftAuditsTable" style="width: 100%;">
                        <thead class="table-light"><tr><th>Date & Time</th><th>Cashier / User</th><th>Status</th><th>Expected Cash</th><th>Remitted Cash</th><th>Variance</th><th width="15%">Action</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            {{-- Expenses & Payroll Tab --}}
            <div class="tab-pane fade" id="expenses-payroll" role="tabpanel">
                <div class="table-responsive bg-white shadow-sm rounded">
                    <table class="table table-hover table-bordered align-middle mb-0" id="expensesTable" style="width: 100%;">
                        <thead class="table-light"><tr><th>Date & Ref</th><th>Category</th><th>Title & Desc</th><th>Amount</th><th>Status</th><th width="15%">Action</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </div>
</div>
</div>{{-- /cb-audit-register --}}

{{-- ============================== TAB 2: FINANCIAL STORIES ============================== --}}
<div class="tab-pane fade" id="cb-stories" role="tabpanel">
<div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden" style="width: 100%;">
    <div class="card-header bg-white border-bottom p-0">
        <ul class="nav nav-tabs nav-fill" id="cbStoryTabs" role="tablist" style="font-size: 0.85rem;">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-2 font-weight-bold" data-bs-toggle="tab" data-bs-target="#cb-story-channel" data-story="channel-breakdown" type="button" role="tab">
                    <i class="mdi mdi-credit-card-multiple"></i> Channel Breakdown
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 font-weight-bold" data-bs-toggle="tab" data-bs-target="#cb-story-paytype" data-story="payment-type" type="button" role="tab">
                    <i class="mdi mdi-tag-multiple"></i> Payment Types
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 font-weight-bold" data-bs-toggle="tab" data-bs-target="#cb-story-revenue" data-story="revenue-attribution" type="button" role="tab">
                    <i class="mdi mdi-chart-pie"></i> Revenue Attribution
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 font-weight-bold" data-bs-toggle="tab" data-bs-target="#cb-story-cashier" data-story="cashier-performance" type="button" role="tab">
                    <i class="mdi mdi-account-cash"></i> Cashier Performance
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 font-weight-bold" data-bs-toggle="tab" data-bs-target="#cb-story-daily" data-story="daily-cashflow" type="button" role="tab">
                    <i class="mdi mdi-chart-line"></i> Daily Cash Flow
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 font-weight-bold" data-bs-toggle="tab" data-bs-target="#cb-story-gl" data-story="gl-summary" type="button" role="tab">
                    <i class="mdi mdi-book-open-page-variant"></i> GL Summary
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 font-weight-bold" data-bs-toggle="tab" data-bs-target="#cb-story-hourly" data-story="hourly-heatmap" type="button" role="tab">
                    <i class="mdi mdi-clock-outline"></i> Hourly Heatmap
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 font-weight-bold" data-bs-toggle="tab" data-bs-target="#cb-story-bankrecon" data-story="bank-recon" type="button" role="tab">
                    <i class="mdi mdi-bank-check"></i> Bank Recon
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body p-4 bg-light">
        <div class="tab-content" id="cbStoryContent">
            @foreach(['cb-story-channel' => 'channel-breakdown', 'cb-story-paytype' => 'payment-type', 'cb-story-revenue' => 'revenue-attribution', 'cb-story-cashier' => 'cashier-performance', 'cb-story-daily' => 'daily-cashflow', 'cb-story-gl' => 'gl-summary', 'cb-story-hourly' => 'hourly-heatmap', 'cb-story-bankrecon' => 'bank-recon'] as $paneId => $storySlug)
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
</div>{{-- /cb-stories --}}

</div>{{-- /cb-parent-content --}}
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
    $('#table-payments').DataTable($.extend({}, commonDtConfig, {
        ajax: { url: "{{ route('audit.cashbook-accounting.data', 'payments') }}", data: appendMultidimData },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'receipt_patient', name: 'receipt_no' },
            { data: 'method_badge', name: 'payment_method' },
            { data: 'amount_formatted', name: 'total' },
            { data: 'cashier_staff', name: 'staff_user.surname' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));
    $('#table-ledger').DataTable($.extend({}, commonDtConfig, {
        ajax: { url: "{{ route('audit.cashbook-accounting.data', 'ledger') }}", data: appendMultidimData },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'account_details', name: 'account.name' },
            { data: 'debit_formatted', name: 'debit' },
            { data: 'credit_formatted', name: 'credit' },
            { data: 'narration', name: 'narration' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));
    $('#table-bank-recon').DataTable($.extend({}, commonDtConfig, {
        ajax: { url: "{{ route('audit.cashbook-accounting.data', 'bank-recon') }}", data: appendMultidimData },
        columns: [
            { data: 'statement_date', name: 'statement_date' },
            { data: 'bank_account', name: 'bank.bank_name' },
            { data: 'balances', name: 'ending_balance_gl' },
            { data: 'variance', name: 'ending_balance_bank' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));
    $('#shiftAuditsTable').DataTable($.extend({}, commonDtConfig, {
        ajax: { url: "{{ route('audit.cashbook-accounting.data', 'shift-audits') }}", data: appendMultidimData },
        columns: [
            { data: 'created_at', name: 'created_at' }, { data: 'user_details', name: 'user.name' },
            { data: 'status_badge', name: 'status' }, { data: 'expected_cash', name: 'expected_cash' },
            { data: 'remitted_cash', name: 'remitted_cash' }, { data: 'variance', name: 'variance' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));
    $('#expensesTable').DataTable($.extend({}, commonDtConfig, {
        ajax: { url: "{{ route('audit.cashbook-accounting.data', 'expenses') }}", data: appendMultidimData },
        columns: [
            { data: 'date_ref', name: 'expense_date' }, { data: 'category', name: 'category' },
            { data: 'title_desc', name: 'title' }, { data: 'amount', name: 'amount' },
            { data: 'status_badge', name: 'status' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    // ============================================================
    // STORY SUB-TAB AJAX LOADER (uses shared top datetime filter component)
    // ============================================================
    var storyDataUrl = "{{ route('audit.cashbook-stories.data', '__STORY__') }}";
    var storyDatatables = {};

    function formatNaira(val) {
        if (typeof val !== 'number') return val;
        return '₦' + val.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function loadCbStory(paneEl) {
        var $pane = $(paneEl);
        var story = $pane.data('story');
        if ($pane.data('loaded') == 1) return;

        var url = storyDataUrl.replace('__STORY__', story);
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
    $('#cb-stories-tab').on('shown.bs.tab', function() {
        window.location.hash = 'cb-stories';
        var $activePane = $('#cbStoryContent .tab-pane.active');
        if ($activePane.length) loadCbStory($activePane[0]);
    });

    // Load story when sub-tab is clicked
    $('#cbStoryTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        var target = $(e.target).data('bs-target');
        window.location.hash = 'cb-stories:' + target.replace('#', '');
        loadCbStory($(target)[0]);
    });

    // Hash tab restoration on load
    if (window.location.hash) {
        var parts = window.location.hash.replace('#', '').split(':');
        if (parts[0] === 'cb-stories') {
            $('#cb-stories-tab').tab('show');
            if (parts[1]) {
                var $subBtn = $('#cbStoryTabs button[data-bs-target="#' + parts[1] + '"]');
                if ($subBtn.length) {
                    $subBtn.tab('show');
                    loadCbStory($('#' + parts[1])[0]);
                }
            }
        }
    }

    // Re-load stories when filters change
    $('#apply_filters_btn').on('click', function() {
        $('#cbStoryContent .tab-pane').data('loaded', 0);
        var $activePane = $('#cbStoryContent .tab-pane.active');
        if ($activePane.length && $('#cb-stories').hasClass('active')) loadCbStory($activePane[0]);
    });

});
</script>
@endpush
