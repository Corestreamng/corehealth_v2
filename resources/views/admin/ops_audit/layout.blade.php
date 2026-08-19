@extends('admin.layouts.app')

@section('title', 'Ops Audit Workbench')

@push('styles')
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/select2/select2.min.css') }}">
<style>
    .select2-container--default .select2-selection--single {
        border-radius: 0.25rem;
        border: 1px solid #ced4da;
        height: 38px;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 36px;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
    }
</style>
<style>
    .audit-layout {
        display: flex;
        min-height: calc(100vh - 120px);
        background-color: #f8f9fa;
        margin: -1.5rem -1.5rem 0 -1.5rem;
    }

    .audit-sidebar {
        width: 250px;
        background: #ffffff;
        border-right: 1px solid #e9ecef;
        display: flex;
        flex-direction: column;
        flex-shrink: 0;
    }

    .audit-sidebar-header {
        padding: 1.25rem 1rem;
        border-bottom: 1px solid #e9ecef;
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        text-align: center;
    }

    .audit-sidebar-header h5 {
        margin: 0;
        font-weight: 700;
        color: #ffffff;
        font-size: 0.95rem;
    }
    .audit-sidebar-header small {
        color: #94a3b8;
        font-size: 0.7rem;
    }

    .audit-sidebar-menu {
        flex-grow: 1;
        overflow-y: auto;
        padding: 0.75rem 0;
    }

    .audit-sidebar-menu .nav-item {
        padding: 0 0.75rem;
        margin-bottom: 0.15rem;
    }

    .audit-sidebar-menu .nav-link {
        color: #495057;
        padding: 0.55rem 0.85rem;
        border-radius: 6px;
        display: flex;
        align-items: center;
        transition: all 0.2s ease-in-out;
        font-weight: 500;
        font-size: 0.85rem;
    }

    .audit-sidebar-menu .nav-link i {
        margin-right: 8px;
        font-size: 1rem;
        width: 20px;
        text-align: center;
        color: #6c757d;
    }

    .audit-sidebar-menu .nav-link:hover,
    .audit-sidebar-menu .nav-link.active {
        background-color: #e8f4fd;
        color: #0d6efd;
    }

    .audit-sidebar-menu .nav-link:hover i,
    .audit-sidebar-menu .nav-link.active i {
        color: #0d6efd;
    }

    .audit-sidebar-menu .menu-section {
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #adb5bd;
        font-weight: 700;
        margin: 1rem 1rem 0.35rem;
    }

    .audit-content {
        flex-grow: 1;
        padding: 1.5rem;
        overflow-y: auto;
        background-color: #f4f6f9;
        width: calc(100% - 250px) !important;
        max-width: calc(100% - 250px) !important;
    }

    .audit-tick-btn {
        transition: all 0.2s ease-in-out;
    }
    .audit-tick-btn:hover {
        transform: scale(1.1);
    }

    /* Dense Table Styling */
    .table-responsive {
        width: 100% !important;
    }
    table.dataTable {
        width: 100% !important;
        margin-top: 0 !important;
        margin-bottom: 0 !important;
        border-collapse: collapse !important;
    }
    table.dataTable thead th {
        background-color: #f8f9fa;
        color: #495057;
        font-weight: 700;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #dee2e6 !important;
        padding: 0.6rem 0.5rem !important;
        white-space: nowrap;
    }
    table.dataTable tbody td {
        padding: 0.5rem 0.5rem !important;
        vertical-align: middle !important;
        font-size: 0.82rem;
        border-bottom: 1px solid #edf2f7 !important;
    }
    table.dataTable tbody tr:hover {
        background-color: #f8fafc !important;
    }
    .dt-buttons .btn {
        font-size: 0.75rem;
        padding: 0.3rem 0.6rem;
        border-radius: 4px;
    }

    /* KPI Cards */
    .ops-kpi-row .kpi-card {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 0.75rem 1rem;
        text-align: center;
        transition: box-shadow 0.2s;
    }
    .ops-kpi-row .kpi-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .ops-kpi-row .kpi-value {
        font-size: 1.35rem;
        font-weight: 800;
        color: #1e293b;
    }
    .ops-kpi-row .kpi-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #6b7280;
        font-weight: 600;
    }

    /* Filter bar */
    .ops-filter-bar {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.85rem 1rem;
        margin-bottom: 1rem;
    }
    .ops-filter-bar label {
        font-size: 0.72rem;
        text-transform: uppercase;
        font-weight: 700;
        color: #64748b;
        letter-spacing: 0.3px;
        margin-bottom: 0.2rem;
    }
    .ops-filter-bar .form-control,
    .ops-filter-bar .form-select {
        font-size: 0.82rem;
        padding: 0.35rem 0.5rem;
        border-radius: 6px;
    }

    /* Tabs */
    .ops-tabs .nav-link {
        font-size: 0.82rem;
        font-weight: 600;
        color: #64748b;
        padding: 0.5rem 1rem;
        border-radius: 6px 6px 0 0;
    }
    .ops-tabs .nav-link.active {
        color: #0d6efd;
        border-color: #dee2e6 #dee2e6 #fff;
        font-weight: 700;
    }

    /* Shift buttons */
    .shift-btn { font-size: 0.72rem; padding: 0.25rem 0.6rem; border-radius: 20px; }
    .shift-btn.active { background-color: #0d6efd !important; color: #fff !important; border-color: #0d6efd !important; }

    /* Responsive */
    @media (max-width: 768px) {
        .audit-layout {
            flex-direction: column;
        }
        .audit-sidebar {
            width: 100%;
            border-right: none;
            border-bottom: 1px solid #e9ecef;
        }
        .audit-content {
            width: 100% !important;
            max-width: 100% !important;
        }
    }

    /* Print Styles */
    @media print {
        body { background: #fff !important; }
        .audit-sidebar, .ops-filter-bar, .ops-tabs, .btn, footer, header, .navbar { display: none !important; }
        .audit-content { width: 100% !important; padding: 0 !important; margin: 0 !important; max-width: 100% !important; background: #fff !important; }
        .ops-kpi-row { display: flex !important; flex-wrap: wrap !important; margin-bottom: 15px !important; }
        .ops-kpi-row .col-md-3 { width: 25% !important; float: left; }
        .ops-kpi-row .col-md-2 { width: 16.66% !important; float: left; }
        .kpi-card { border: 1px solid #ccc !important; break-inside: avoid; }
        table.dataTable { width: 100% !important; border-collapse: collapse !important; }
        table.dataTable th, table.dataTable td { border: 1px solid #ddd !important; padding: 4px !important; font-size: 10pt !important; }
        @page { size: landscape; margin: 1cm; }
    }
</style>
@stack('ops_audit_styles')
@endpush

@push('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('plugins/select2/select2.min.js') }}"></script>
<script>
    $(document).ready(function() {
        if (typeof $.fn.select2 !== 'undefined') {
            $('.ops-hmo-select2').select2({
                placeholder: "All HMOs",
                allowClear: true
            });
        }
    });
</script>
@endpush

@section('content')
<div class="audit-layout">
    <div class="audit-sidebar shadow-sm">
        <div class="audit-sidebar-header">
            <h5><i class="mdi mdi-clipboard-check-multiple"></i> Ops Audit</h5>
            <small>Operations Audit Workbench</small>
        </div>

        <div class="audit-sidebar-menu">
            <div class="menu-section">Clinical Workbenches</div>
            <div class="nav-item">
                <a href="{{ route('ops-audit.reception') }}" class="nav-link {{ request()->routeIs('ops-audit.reception*') ? 'active' : '' }}">
                    <i class="mdi mdi-desktop-mac"></i> Reception
                </a>
            </div>
            <div class="nav-item">
                <a href="{{ route('ops-audit.doctor') }}" class="nav-link {{ request()->routeIs('ops-audit.doctor*') ? 'active' : '' }}">
                    <i class="mdi mdi-stethoscope"></i> Doctor
                </a>
            </div>
            <div class="nav-item">
                <a href="{{ route('ops-audit.nursing') }}" class="nav-link {{ request()->routeIs('ops-audit.nursing*') ? 'active' : '' }}">
                    <i class="mdi mdi-bed"></i> Nursing / Ward
                </a>
            </div>
            <div class="nav-item">
                <a href="{{ route('ops-audit.pharmacy') }}" class="nav-link {{ request()->routeIs('ops-audit.pharmacy*') ? 'active' : '' }}">
                    <i class="mdi mdi-pill"></i> Pharmacy
                </a>
            </div>
            <div class="nav-item">
                <a href="{{ route('ops-audit.lab') }}" class="nav-link {{ request()->routeIs('ops-audit.lab*') ? 'active' : '' }}">
                    <i class="mdi mdi-flask"></i> Laboratory
                </a>
            </div>
            <div class="nav-item">
                <a href="{{ route('ops-audit.imaging') }}" class="nav-link {{ request()->routeIs('ops-audit.imaging*') ? 'active' : '' }}">
                    <i class="mdi mdi-radioactive"></i> Imaging
                </a>
            </div>

            <div class="menu-section">Specialized</div>
            <div class="nav-item">
                <a href="{{ route('ops-audit.maternity') }}" class="nav-link {{ request()->routeIs('ops-audit.maternity*') ? 'active' : '' }}">
                    <i class="mdi mdi-baby-carriage"></i> Maternity
                </a>
            </div>
            <div class="nav-item">
                <a href="{{ route('ops-audit.surgery') }}" class="nav-link {{ request()->routeIs('ops-audit.surgery*') ? 'active' : '' }}">
                    <i class="mdi mdi-hospital"></i> Surgery
                </a>
            </div>
            <div class="nav-item">
                <a href="{{ route('ops-audit.morgue') }}" class="nav-link {{ request()->routeIs('ops-audit.morgue*') ? 'active' : '' }}">
                    <i class="mdi mdi-coffin"></i> Morgue
                </a>
            </div>

            <div class="menu-section">Financial & Insurance</div>
            <div class="nav-item">
                <a href="{{ route('ops-audit.hmo') }}" class="nav-link {{ request()->routeIs('ops-audit.hmo*') ? 'active' : '' }}">
                    <i class="mdi mdi-hospital-building"></i> HMO / Insurance
                </a>
            </div>
            <div class="nav-item">
                <a href="{{ route('ops-audit.billing') }}" class="nav-link {{ request()->routeIs('ops-audit.billing*') ? 'active' : '' }}">
                    <i class="mdi mdi-cash-register"></i> Billing / Cashier
                </a>
            </div>

            <div class="menu-section">Store</div>
            <div class="nav-item">
                <a href="{{ route('ops-audit.store') }}" class="nav-link {{ request()->routeIs('ops-audit.store*') ? 'active' : '' }}">
                    <i class="mdi mdi-store"></i> Store / Inventory
                </a>
            </div>
        </div>
    </div>

    <div class="audit-content">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="d-none d-print-block mb-4 border-bottom pb-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-1">Ops Audit Report</h3>
                    <p class="text-muted mb-0">Printed on {{ now()->format('d M Y H:i') }}</p>
                </div>
                <div>
                    <!-- Optional: Add hospital logo here if available in app config -->
                    <h5 class="text-end">{{ config('app.name', 'CoreHealth') }}</h5>
                </div>
            </div>
        </div>

        @yield('ops_audit_content')
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Global DataTables configuration: auto-highlight queried rows
    if (window.jQuery && $.fn && $.fn.dataTable) {
        $.extend(true, $.fn.dataTable.defaults, {
            createdRow: function(row, data, dataIndex) {
                var str = typeof data === 'object' ? JSON.stringify(data) : String(data);
                if (str.includes('Resolve Query') || str.includes('Active Query Flagged') || str.includes('btn-warning')) {
                    $(row).addClass('table-warning bg-warning bg-opacity-10');
                }
            }
        });
    }

    // Currency formatter
    function opsFormatCurrency(amount) {
        if (!amount && amount !== 0) return '₦0.00';
        return '₦' + parseFloat(amount).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // KPI renderer
    function renderOpsKpis(kpis, containerId) {
        if (!kpis || !kpis.length) return;
        var html = '';
        var colSize = kpis.length <= 4 ? 3 : (kpis.length <= 6 ? 2 : 'auto');
        kpis.forEach(function(k) {
            var borderColor = k.color || '#0d6efd';
            html += '<div class="col-md-' + colSize + ' col-6 col-xl-' + colSize + '">' +
                '<div class="kpi-card" style="border-left: 3px solid ' + borderColor + ';">' +
                '<div class="kpi-value" style="color: ' + borderColor + ';">' + k.value + '</div>' +
                '<div class="kpi-label">' + k.label + '</div>' +
                '</div></div>';
        });
        $('#' + containerId).html(html);
    }

    // Override DataTables print button to use our new generic backend print view
    if (window.jQuery && $.fn && $.fn.dataTable && $.fn.dataTable.ext && $.fn.dataTable.ext.buttons && $.fn.dataTable.ext.buttons.print) {
        $.fn.dataTable.ext.buttons.print.action = function (e, dt, button, config) {
            var ajaxUrl = dt.ajax.url();
            if (!ajaxUrl) return;

            var url = new URL(ajaxUrl, window.location.origin);
            url.searchParams.set('action', 'print');
            
            // Add top-level global filters
            $('.ops-filter-bar input, .ops-filter-bar select').each(function() {
                if ($(this).val()) {
                    url.searchParams.set($(this).attr('name'), $(this).val());
                }
            });
            
            // Add active tab specific filters
            $('.ops-tabs .tab-pane.active').find('.ops-tab-filter').each(function() {
                if ($(this).val()) {
                    url.searchParams.set($(this).attr('name'), $(this).val());
                }
            });

            window.open(url.toString(), '_blank', 'width=1100,height=800');
        };
    }

    // Shift filter buttons
    $(document).on('click', '.shift-btn', function() {
        var shift = $(this).data('shift');
        var form = $(this).closest('.ops-filter-bar');
        var today = form.find('[name="start_date"]').val() || new Date().toISOString().split('T')[0];

        // Remove active from siblings
        $(this).siblings('.shift-btn').removeClass('active btn-primary').addClass('btn-outline-secondary');
        $(this).addClass('active btn-primary').removeClass('btn-outline-secondary');

        if (shift === 'morning') {
            form.find('[name="shift_start"]').val('00:00');
            form.find('[name="shift_end"]').val('12:59');
        } else if (shift === 'afternoon') {
            form.find('[name="shift_start"]').val('13:00');
            form.find('[name="shift_end"]').val('17:59');
        } else if (shift === 'night') {
            form.find('[name="shift_start"]').val('18:00');
            form.find('[name="shift_end"]').val('23:59');
        } else {
            form.find('[name="shift_start"]').val('');
            form.find('[name="shift_end"]').val('');
        }

        // Trigger table reload
        $('.ops-datatable:visible').each(function() {
            if ($.fn.DataTable.isDataTable(this)) {
                $(this).DataTable().ajax.reload();
            }
        });
    });

    // Green Audit Tick — identical to audit_workbench
    function markAudited(arg1, arg2, arg3) {
        let btnElement, modelType, modelId;
        if (typeof arg1 === 'object') {
            btnElement = arg1; modelType = arg2; modelId = arg3;
        } else {
            modelType = arg1; modelId = arg2; btnElement = arg3;
        }
        openUniversalStampModal('single', modelType, modelId, btnElement);
    }

    function openUniversalStampModal(mode, modelType, modelId, btnElement) {
        document.getElementById('stamp_mode').value = mode;
        document.getElementById('stamp_model_type').value = modelType || '';
        document.getElementById('stamp_model_id').value = modelId || '';

        let titleEl = document.getElementById('stamp_modal_title');
        let textEl = document.getElementById('stamp_modal_text');
        let checkbox = document.getElementById('stamp_confirm_check');
        checkbox.checked = false;
        checkbox.disabled = false;
        document.getElementById('btnSubmitUniversalStamp').disabled = true;

        var myModal = new bootstrap.Modal(document.getElementById('universalStampModal'));

        if (mode === 'bulk') {
            titleEl.innerHTML = '<i class="mdi mdi-check-all"></i> Bulk Stamp View';
            let activeTab = document.querySelector('.audit-content .ops-tabs .nav-link.active');
            if (!activeTab) activeTab = document.querySelector('.audit-content .nav-tabs .nav-link.active');
            let tabName = activeTab ? activeTab.innerText.trim() : 'the current';

            textEl.innerHTML = 'Fetching preview... <i class="mdi mdi-loading mdi-spin"></i>';
            myModal.show();

            let filterData = $('#ops_audit_filter_form').serializeArray();
            filterData.push({name: 'action', value: 'bulk_stamp_preview'});
            filterData.push({name: '_token', value: $('meta[name="csrf-token"]').attr('content')});

            let activeTable = $('.audit-content .tab-pane.active table.ops-datatable');
            if (activeTable.length === 0) activeTable = $('.audit-content table.ops-datatable:visible');
            let targetUrl = activeTable.length > 0 ? activeTable.DataTable().ajax.url() : window.location.href;

            $.ajax({
                url: targetUrl,
                type: 'POST',
                data: $.param(filterData),
                success: function(res) {
                    if (res.success) {
                        let msg = `You are about to stamp <strong>${res.valid}</strong> records in the <strong>${tabName}</strong> view.`;
                        if (res.monetary) {
                            msg += `<div class="row g-2 mt-2">
                                <div class="col-4"><div class="bg-light p-2 rounded text-center"><small class="text-muted d-block">Total Amount</small><strong>${opsFormatCurrency(res.monetary.total_amount)}</strong></div></div>
                                <div class="col-4"><div class="bg-light p-2 rounded text-center"><small class="text-muted d-block">Payable</small><strong class="text-success">${opsFormatCurrency(res.monetary.total_payable)}</strong></div></div>
                                <div class="col-4"><div class="bg-light p-2 rounded text-center"><small class="text-muted d-block">Claims</small><strong class="text-info">${opsFormatCurrency(res.monetary.total_claims)}</strong></div></div>
                            </div>`;
                            if (res.monetary.unique_patients) msg += `<div class="mt-2"><small class="text-muted"><i class="mdi mdi-account-group me-1"></i> ${res.monetary.unique_patients} unique patients</small></div>`;
                            if (res.monetary.staff_summary) msg += `<div><small class="text-muted"><i class="mdi mdi-account-tie me-1"></i> Staff: ${res.monetary.staff_summary}</small></div>`;
                        }
                        if (res.queried > 0) {
                            msg += `<br><span class="text-danger"><i class="mdi mdi-alert"></i> ${res.queried} records with active queries will be skipped.</span>`;
                        } else {
                            msg += `<br><span class="text-success"><i class="mdi mdi-check-circle"></i> No active queries in this selection.</span>`;
                        }
                        if (res.valid === 0) {
                            msg = '<span class="text-danger"><i class="mdi mdi-alert-circle"></i> No valid records to stamp.</span>';
                            checkbox.disabled = true;
                        }
                        textEl.innerHTML = msg;
                    } else {
                        textEl.innerHTML = '<span class="text-danger">Failed to load preview data.</span>';
                    }
                },
                error: function() {
                    textEl.innerHTML = '<span class="text-danger">Error fetching preview.</span>';
                }
            });
        } else {
            titleEl.innerHTML = '<i class="mdi mdi-check-decagram"></i> Confirm Stamp';
            textEl.innerHTML = 'You are about to mark this record as audited.';
            myModal.show();
        }
    }

    function toggleUniversalStampButton(checkbox) {
        document.getElementById('btnSubmitUniversalStamp').disabled = !checkbox.checked;
    }

    function submitUniversalStamp() {
        let mode = document.getElementById('stamp_mode').value;
        let btn = document.getElementById('btnSubmitUniversalStamp');
        let originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="mdi mdi-loading mdi-spin"></i> Processing...';

        if (mode === 'bulk') {
            let filterData = $('#ops_audit_filter_form').serializeArray();
            filterData.push({name: 'action', value: 'bulk_stamp'});
            filterData.push({name: '_token', value: $('meta[name="csrf-token"]').attr('content')});

            let activeTable = $('.audit-content .tab-pane.active table.ops-datatable');
            if (activeTable.length === 0) activeTable = $('.audit-content table.ops-datatable:visible');
            let targetUrl = activeTable.length > 0 ? activeTable.DataTable().ajax.url() : window.location.href;

            $.ajax({
                url: targetUrl, type: 'POST', data: $.param(filterData),
                success: function(res) {
                    if (res.success) {
                        toastr.success(res.message);
                        if (res.skipped_count > 0) toastr.warning(res.skipped_count + ' items were skipped due to active queries.');
                        setTimeout(() => { $('.ops-datatable:visible').each(function() { if ($.fn.DataTable.isDataTable(this)) $(this).DataTable().ajax.reload(); }); }, 500);
                    } else {
                        toastr.error(res.message || 'Failed to bulk stamp.');
                        btn.disabled = false; btn.innerHTML = originalHtml;
                    }
                },
                error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Error during bulk stamp.'); btn.disabled = false; btn.innerHTML = originalHtml; }
            });
        } else {
            let modelType = document.getElementById('stamp_model_type').value;
            let modelId = document.getElementById('stamp_model_id').value;
            $.ajax({
                url: '{{ route("audit.mark.stamp") }}', type: 'POST',
                data: { _token: $('meta[name="csrf-token"]').attr('content'), model_type: modelType, model_id: modelId, zone_key: 'ops_audit' },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        setTimeout(() => { $('.ops-datatable:visible').each(function() { if ($.fn.DataTable.isDataTable(this)) $(this).DataTable().ajax.reload(); }); }, 500);
                    } else { toastr.error(response.message); btn.disabled = false; btn.innerHTML = originalHtml; }
                    bootstrap.Modal.getInstance(document.getElementById('universalStampModal'))?.hide();
                },
                error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Error.'); btn.disabled = false; btn.innerHTML = originalHtml; }
            });
        }
    }

    function openRaiseQueryModal(modelType, modelId) {
        document.getElementById('query_model_type').value = modelType;
        document.getElementById('query_model_id').value = modelId;
        document.getElementById('query_notes').value = '';
        new bootstrap.Modal(document.getElementById('raiseQueryModal')).show();
    }

    function submitRaiseQuery() {
        let btn = document.getElementById('btnSubmitQuery');
        btn.disabled = true; btn.innerHTML = 'Submitting...';
        let data = $('#formRaiseQuery').serializeArray();
        data.push({name: 'zone_key', value: 'ops_audit'});
        $.ajax({
            url: '{{ route("audit.mark.raise-query") }}', type: 'POST', data: $.param(data),
            success: function(res) {
                if (res.success) { toastr.success(res.message); setTimeout(() => { $('.ops-datatable:visible').each(function() { if ($.fn.DataTable.isDataTable(this)) $(this).DataTable().ajax.reload(); }); }, 500); bootstrap.Modal.getInstance(document.getElementById('raiseQueryModal'))?.hide(); }
                else { toastr.error(res.message); btn.disabled = false; btn.innerHTML = 'Raise Query'; }
            },
            error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Error.'); btn.disabled = false; btn.innerHTML = 'Raise Query'; }
        });
    }

    function openResolveQueryModal(modelType, modelId) {
        document.getElementById('resolve_model_type').value = modelType;
        document.getElementById('resolve_model_id').value = modelId;
        document.getElementById('resolve_notes').value = '';
        new bootstrap.Modal(document.getElementById('resolveQueryModal')).show();
    }

    function submitResolveQuery() {
        let btn = document.getElementById('btnSubmitResolve');
        btn.disabled = true; btn.innerHTML = 'Resolving...';
        $.ajax({
            url: '{{ route("audit.mark.resolve-query") }}', type: 'POST', data: $('#formResolveQuery').serialize(),
            success: function(res) {
                if (res.success) { toastr.success(res.message); setTimeout(() => { $('.ops-datatable:visible').each(function() { if ($.fn.DataTable.isDataTable(this)) $(this).DataTable().ajax.reload(); }); }, 500); bootstrap.Modal.getInstance(document.getElementById('resolveQueryModal'))?.hide(); }
                else { toastr.error(res.message); btn.disabled = false; btn.innerHTML = 'Resolve Query'; }
            },
            error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Error.'); btn.disabled = false; btn.innerHTML = 'Resolve Query'; }
        });
    }
</script>
@endpush

{{-- Universal Stamp Modal --}}
<div class="modal fade" id="universalStampModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="stamp_modal_title"><i class="mdi mdi-check-decagram"></i> Confirm Stamp</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="stamp_mode">
                <input type="hidden" id="stamp_model_type">
                <input type="hidden" id="stamp_model_id">
                <div class="text-center mb-4">
                    <i class="mdi mdi-shield-check-outline text-success" style="font-size: 3.5rem;"></i>
                    <p class="mt-3 mb-0 fs-6" id="stamp_modal_text">You are about to mark the selected record as audited.</p>
                </div>
                <div class="form-check bg-light p-3 rounded border d-flex align-items-center">
                    <input class="form-check-input ms-1 me-3 mt-0" type="checkbox" id="stamp_confirm_check" onchange="toggleUniversalStampButton(this)" style="transform: scale(1.5);">
                    <label class="form-check-label user-select-none" for="stamp_confirm_check">
                        <strong>I confirm</strong> I have reviewed the data and want to stamp it.
                    </label>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 justify-content-between">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success px-4" id="btnSubmitUniversalStamp" onclick="submitUniversalStamp()" disabled>
                    <i class="mdi mdi-check"></i> Stamp Records
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Raise Query Modal --}}
<div class="modal fade" id="raiseQueryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-dark"><i class="mdi mdi-alert-circle"></i> Raise Audit Query</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formRaiseQuery">
                    @csrf
                    <input type="hidden" name="model_type" id="query_model_type">
                    <input type="hidden" name="model_id" id="query_model_id">
                    <div class="alert alert-warning py-2 small">
                        Raising a query blocks this record from being audited until resolved.
                    </div>
                    <div class="mb-3">
                        <label class="form-label small font-weight-bold">Query Reason <span class="text-danger">*</span></label>
                        <textarea name="query_notes" id="query_notes" class="form-control border-secondary" rows="4" required placeholder="Why is this record being flagged?"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning text-dark font-weight-bold" id="btnSubmitQuery" onclick="submitRaiseQuery()">Raise Query</button>
            </div>
        </div>
    </div>
</div>

{{-- Resolve Query Modal --}}
<div class="modal fade" id="resolveQueryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            <div class="modal-header text-white py-3 px-4" style="background: linear-gradient(135deg, #065f46 0%, #047857 100%); border-bottom: 3px solid #10b981;">
                <h5 class="modal-title font-weight-bold text-white d-flex align-items-center">
                    <i class="mdi mdi-check-decagram text-white me-2" style="font-size:1.4rem;"></i> Resolve Audit Query
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" style="background-color: #f8fafc;">
                <form id="formResolveQuery">
                    @csrf
                    <input type="hidden" name="model_type" id="resolve_model_type">
                    <input type="hidden" name="model_id" id="resolve_model_id">
                    <div class="mb-3">
                        <label class="form-label font-weight-bold text-dark">Resolution Notes <span class="text-danger">*</span></label>
                        <textarea name="resolution_notes" id="resolve_notes" class="form-control border-secondary shadow-sm rounded-3 p-3" rows="4" required placeholder="Describe how this query was resolved..."></textarea>
                    </div>
                    <div class="form-check bg-white p-3 rounded-3 border shadow-sm mb-2 d-flex align-items-center">
                        <input class="form-check-input ms-1 me-3 mt-0" type="checkbox" name="auto_stamp" id="resolve_auto_stamp" value="1" checked style="transform: scale(1.35);">
                        <label class="form-check-label user-select-none font-weight-bold text-dark mb-0" for="resolve_auto_stamp">
                            <i class="mdi mdi-check-decagram text-success me-1"></i> Auto-stamp on resolution
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-white border-top py-2 px-4 justify-content-between">
                <button type="button" class="btn btn-secondary px-4 font-weight-bold rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success px-4 font-weight-bold rounded-pill shadow-sm" id="btnSubmitResolve" onclick="submitResolveQuery()">
                    <i class="mdi mdi-check-circle me-1"></i> Mark as Resolved
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
@stack('ops_audit_scripts')
@endpush
