@extends('admin.layouts.app')

@section('title', 'Internal Audit Workbench')

@push('styles')
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<style>
    .audit-layout {
        display: flex;
        min-height: calc(100vh - 120px); /* Adjust based on navbar height */
        background-color: #f8f9fa;
        margin: -1.5rem -1.5rem 0 -1.5rem; /* Remove default padding from main layout if necessary */
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
        padding: 1.5rem 1rem;
        border-bottom: 1px solid #e9ecef;
        background: #f8f9fa;
        text-align: center;
    }

    .audit-sidebar-header h5 {
        margin: 0;
        font-weight: 600;
        color: #2c3e50;
    }

    .audit-sidebar-menu {
        flex-grow: 1;
        overflow-y: auto;
        padding: 1rem 0;
    }

    .audit-sidebar-menu .nav-item {
        padding: 0 1rem;
        margin-bottom: 0.25rem;
    }

    .audit-sidebar-menu .nav-link {
        color: #495057;
        padding: 0.75rem 1rem;
        border-radius: 6px;
        display: flex;
        align-items: center;
        transition: all 0.2s ease-in-out;
        font-weight: 500;
    }

    .audit-sidebar-menu .nav-link i {
        margin-right: 10px;
        font-size: 1.1rem;
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
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #adb5bd;
        font-weight: 700;
        margin: 1.5rem 1rem 0.5rem;
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
    
    /* 100% Width & Dense Table Styling */
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
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #dee2e6 !important;
        padding: 0.75rem 0.75rem !important;
    }
    table.dataTable tbody td {
        padding: 0.75rem 0.75rem !important;
        vertical-align: middle !important;
        font-size: 0.875rem;
        border-bottom: 1px solid #edf2f7 !important;
    }
    table.dataTable tbody tr:hover {
        background-color: #f8fafc !important;
    }
    .dt-buttons .btn {
        font-size: 0.8rem;
        padding: 0.375rem 0.75rem;
        border-radius: 4px;
    }

    /* Responsive adjustments */
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
</style>
@stack('audit_styles')
@endpush

@push('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
@endpush

@section('content')
<div class="audit-layout">
    <div class="audit-sidebar shadow-sm">
        <div class="audit-sidebar-header">
            <h5><i class="mdi mdi-shield-check text-primary"></i> Audit Workbench</h5>
        </div>
        
        <div class="audit-sidebar-menu">
            <div class="nav-item">
                <a href="{{ route('audit.workbench') }}" class="nav-link {{ request()->routeIs('audit.workbench') ? 'active' : '' }}">
                    <i class="mdi mdi-view-dashboard"></i> Dashboard
                </a>
            </div>

            <div class="menu-section">Financial Audits</div>
            <div class="nav-item">
                <a href="{{ route('audit.receivables-debtors') }}" class="nav-link {{ request()->routeIs('audit.receivables-debtors') ? 'active' : '' }}">
                    <i class="mdi mdi-account-cash"></i> 1. Receivables & Debtors
                </a>
            </div>
            <div class="nav-item">
                <a href="{{ route('audit.cashbook-accounting') }}" class="nav-link {{ request()->routeIs('audit.cashbook-accounting') ? 'active' : '' }}">
                    <i class="mdi mdi-cash-multiple"></i> 2. Cash Book & Ledgers
                </a>
            </div>

            <div class="menu-section">Clinical & Patient Flow</div>
            <div class="nav-item">
                <a href="{{ route('audit.consultations-clinics') }}" class="nav-link {{ request()->routeIs('audit.consultations-clinics') ? 'active' : '' }}">
                    <i class="mdi mdi-human-male-female"></i> 3. Consults & Clinics
                </a>
            </div>
            <div class="nav-item">
                <a href="{{ route('audit.admissions-discharges') }}" class="nav-link {{ request()->routeIs('audit.admissions-discharges') ? 'active' : '' }}">
                    <i class="mdi mdi-bed"></i> 4. Admissions & Discharges
                </a>
            </div>

            <div class="menu-section">Inventory & Stores</div>
            <div class="nav-item">
                <a href="{{ route('audit.main-store-stock') }}" class="nav-link {{ request()->routeIs('audit.main-store-stock') ? 'active' : '' }}">
                    <i class="mdi mdi-store"></i> 5. Main Store Stock
                </a>
            </div>
            <div class="nav-item">
                <a href="{{ route('audit.ward-dept-stores') }}" class="nav-link {{ request()->routeIs('audit.ward-dept-stores') ? 'active' : '' }}">
                    <i class="mdi mdi-store-24-hour"></i> 6. Sub-Store & Ward Stores
                </a>
            </div>
            <div class="nav-item">
                <a href="{{ route('audit.store-utilization-revenue') }}" class="nav-link {{ request()->routeIs('audit.store-utilization-revenue') ? 'active' : '' }}">
                    <i class="mdi mdi-clipboard-pulse"></i> 7. Store Utilization vs Income
                </a>
            </div>

            <div class="menu-section">Specialized Audits</div>
            <div class="nav-item">
                <a href="{{ route('audit.hmo-nhis-audit') }}" class="nav-link {{ request()->routeIs('audit.hmo-nhis-audit') ? 'active' : '' }}">
                    <i class="mdi mdi-hospital-building"></i> 8. HMO & NHIS Audit
                </a>
            </div>
            <div class="nav-item">
                <a href="{{ route('audit.service-registers-billing') }}" class="nav-link {{ request()->routeIs('audit.service-registers-billing') ? 'active' : '' }}">
                    <i class="mdi mdi-format-list-checks"></i> 9. Service Registers vs Billing
                </a>
            </div>
            <div class="nav-item">
                <a href="{{ route('audit.pharmacy-mortuary') }}" class="nav-link {{ request()->routeIs('audit.pharmacy-mortuary') ? 'active' : '' }}">
                    <i class="mdi mdi-pill"></i> 10. Pharmacy & Mortuary
                </a>
            </div>

            <div class="menu-section mt-4 border-top pt-3">Hospital-wide Analytics</div>
            <div class="nav-item">
                <a href="{{ route('audit.queries-dashboard') }}" class="nav-link {{ request()->routeIs('audit.queries-dashboard') ? 'active text-danger font-weight-bold' : 'text-danger' }}">
                    <i class="mdi mdi-alert-circle"></i> Unified Query Dashboard
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

        @yield('audit_content')
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Global function for the Green Audit Tick
    function markAudited(arg1, arg2, arg3) {
        let btnElement, modelType, modelId;
        // Handle both signatures: (btnElement, modelType, modelId) and (modelType, modelId, btnElement)
        if (typeof arg1 === 'object') {
            btnElement = arg1;
            modelType = arg2;
            modelId = arg3;
        } else {
            modelType = arg1;
            modelId = arg2;
            btnElement = arg3;
        }
        openUniversalStampModal('single', modelType, modelId, btnElement);
    }
    
    function openUniversalStampModal(mode, modelType = null, modelId = null, btnElement = null) {
        document.getElementById('stamp_mode').value = mode;
        document.getElementById('stamp_model_type').value = modelType || '';
        document.getElementById('stamp_model_id').value = modelId || '';
        
        let titleEl = document.getElementById('stamp_modal_title');
        let textEl = document.getElementById('stamp_modal_text');
        
        // Reset checkbox and button
        let checkbox = document.getElementById('stamp_confirm_check');
        checkbox.checked = false;
        checkbox.disabled = false;
        document.getElementById('btnSubmitUniversalStamp').disabled = true;
        
        var myModal = new bootstrap.Modal(document.getElementById('universalStampModal'));
        
        if (mode === 'bulk') {
            titleEl.innerHTML = '<i class="mdi mdi-check-all"></i> Bulk Stamp View';
            
            // Get proper active tab name, scoped to main content area to avoid sidebar clashes
            let activeTab = document.querySelector('.audit-content .audit-tabs .nav-link.active');
            if (!activeTab) activeTab = document.querySelector('.audit-content .nav-tabs .nav-link.active');
            if (!activeTab) activeTab = document.querySelector('.audit-content .nav-link.active');
            let tabName = activeTab ? activeTab.innerText.trim() : 'the current';
            
            textEl.innerHTML = `Fetching preview... <i class="mdi mdi-loading mdi-spin"></i>`;
            myModal.show();
            
            let filterData = $('#audit_period_form').serializeArray();
            filterData.push({name: 'action', value: 'bulk_stamp_preview'});
            filterData.push({name: '_token', value: '{{ csrf_token() }}'});
            
            let activeTable = $('.audit-content .tab-pane.active table.dataTable');
            if (activeTable.length === 0) activeTable = $('.audit-content table.dataTable:visible');
            
            let targetUrl = activeTable.length > 0 ? activeTable.DataTable().ajax.url() : (window.currentDataTablesUrl || window.location.href);
            
            $.ajax({
                url: targetUrl,
                type: 'POST',
                data: $.param(filterData),
                success: function(res) {
                    if(res.success) {
                        let msg = `You are about to stamp <strong>${res.valid}</strong> records in the <strong>${tabName}</strong> view.`;
                        if (res.queried > 0) {
                            msg += `<br><br><span class="text-danger"><i class="mdi mdi-alert"></i> ${res.queried} records with active queries will be automatically skipped.</span>`;
                        } else {
                            msg += `<br><br><span class="text-success"><i class="mdi mdi-check-circle"></i> No active queries found in this selection.</span>`;
                        }
                        
                        if (res.valid === 0) {
                            msg = `<span class="text-danger"><i class="mdi mdi-alert-circle"></i> No valid records to stamp in this view.</span>`;
                            checkbox.disabled = true;
                        }
                        
                        textEl.innerHTML = msg;
                    } else {
                        textEl.innerHTML = `<span class="text-danger">Failed to load preview data.</span>`;
                    }
                },
                error: function() {
                    textEl.innerHTML = `<span class="text-danger">Error fetching preview data.</span>`;
                }
            });
            
        } else {
            titleEl.innerHTML = '<i class="mdi mdi-check-decagram"></i> Confirm Stamp';
            textEl.innerHTML = 'You are about to mark the selected record as audited.';
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
            let filterData = $('#audit_period_form').serializeArray();
            filterData.push({name: 'action', value: 'bulk_stamp'});
            filterData.push({name: '_token', value: '{{ csrf_token() }}'});
            
            let activeTable = $('.audit-content .tab-pane.active table.dataTable');
            if (activeTable.length === 0) activeTable = $('.audit-content table.dataTable:visible');
            
            let targetUrl = activeTable.length > 0 ? activeTable.DataTable().ajax.url() : (window.currentDataTablesUrl || window.location.href);
            
            $.ajax({
                url: targetUrl,
                type: 'POST',
                data: $.param(filterData),
                success: function(res) {
                    if(res.success) {
                        toastr.success(res.message);
                        if (res.skipped_count > 0) toastr.warning(res.skipped_count + ' items were skipped due to active queries.');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        toastr.error(res.message || 'Failed to bulk stamp.');
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'An error occurred during bulk stamp.');
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
            });
        } else {
            let modelType = document.getElementById('stamp_model_type').value;
            let modelId = document.getElementById('stamp_model_id').value;
            
            $.ajax({
                url: '{{ route("audit.mark.stamp") }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    model_type: modelType,
                    model_id: modelId,
                    zone_key: '{{ $zoneKey ?? "zone_dynamic" }}'
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        toastr.error(response.message);
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'An error occurred while marking as audited.');
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
            });
        }
    }

    function openRaiseQueryModal(modelType, modelId) {
        document.getElementById('query_model_type').value = modelType;
        document.getElementById('query_model_id').value = modelId;
        document.getElementById('query_notes').value = '';
        var myModal = new bootstrap.Modal(document.getElementById('raiseQueryModal'));
        myModal.show();
    }

    function submitRaiseQuery() {
        let btn = document.getElementById('btnSubmitQuery');
        let form = document.getElementById('formRaiseQuery');
        btn.disabled = true;
        btn.innerHTML = 'Submitting...';

        let data = $(form).serializeArray();
        data.push({name: 'zone_key', value: '{{ $zoneKey ?? "" }}'});

        $.ajax({
            url: '{{ route("audit.mark.raise-query") }}',
            type: 'POST',
            data: $.param(data),
            success: function(res) {
                if(res.success) {
                    toastr.success(res.message);
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    toastr.error(res.message);
                    btn.disabled = false;
                    btn.innerHTML = 'Raise Query';
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred.');
                btn.disabled = false;
                btn.innerHTML = 'Raise Query';
            }
        });
    }

    function openResolveQueryModal(modelType, modelId) {
        document.getElementById('resolve_model_type').value = modelType;
        document.getElementById('resolve_model_id').value = modelId;
        document.getElementById('resolve_notes').value = '';
        var myModal = new bootstrap.Modal(document.getElementById('resolveQueryModal'));
        myModal.show();
    }

    function submitResolveQuery() {
        let btn = document.getElementById('btnSubmitResolve');
        let form = document.getElementById('formResolveQuery');
        btn.disabled = true;
        btn.innerHTML = 'Resolving...';

        $.ajax({
            url: '{{ route("audit.mark.resolve-query") }}',
            type: 'POST',
            data: $(form).serialize(),
            success: function(res) {
                if(res.success) {
                    toastr.success(res.message);
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    toastr.error(res.message);
                    btn.disabled = false;
                    btn.innerHTML = 'Resolve Query';
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred.');
                btn.disabled = false;
                btn.innerHTML = 'Resolve Query';
            }
        });
    }
    

</script>
@endpush

{{-- Universal Stamp Modal --}}
<div class="modal fade" id="universalStampModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
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
                    <i class="mdi mdi-shield-check-outline text-success" style="font-size: 4rem;"></i>
                    <p class="mt-3 mb-0 fs-5" id="stamp_modal_text">You are about to mark the selected record as audited.</p>
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
                        Raising a query blocks this record from being audited (individually or in bulk) until the query is resolved.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small font-weight-bold">Query Reason / Details <span class="text-danger">*</span></label>
                        <textarea name="query_notes" id="query_notes" class="form-control border-secondary" rows="4" required placeholder="Why is this record being flagged? E.g., Amount mismatch, missing receipt..."></textarea>
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
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="mdi mdi-check-circle"></i> Resolve Audit Query</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formResolveQuery">
                    @csrf
                    <input type="hidden" name="model_type" id="resolve_model_type">
                    <input type="hidden" name="model_id" id="resolve_model_id">
                    
                    <div class="mb-3">
                        <label class="form-label small font-weight-bold">Resolution Notes <span class="text-danger">*</span></label>
                        <textarea name="resolution_notes" id="resolve_notes" class="form-control border-secondary" rows="4" required placeholder="How was this query resolved?"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="btnSubmitResolve" onclick="submitResolveQuery()">Mark as Resolved</button>
            </div>
        </div>
    </div>
</div>

{{-- Rich Executive Story Detail Modal --}}
<div class="modal fade" id="storyDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width: 92%;">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            {{-- Header with Dark Gradient --}}
            <div class="modal-header text-white py-3 px-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-bottom: 3px solid #3b82f6;">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-primary bg-opacity-25 p-2 me-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 48px; height: 48px; border: 1px solid rgba(255,255,255,0.2);">
                        <i class="mdi mdi-chart-timeline-variant text-info" style="font-size: 1.6rem;"></i>
                    </div>
                    <div>
                        <h4 class="modal-title font-weight-bold mb-1 text-white d-flex align-items-center gap-2" id="storyDetailModalTitle">
                            <span>Story Transaction Audit</span>
                        </h4>
                        <div class="d-flex align-items-center gap-2 flex-wrap" id="storyDetailModalSubtitleContainer">
                            <span class="badge bg-dark bg-opacity-75 text-info border border-info border-opacity-25 px-2.5 py-1" id="storyDetailModalSubtitle">
                                <i class="mdi mdi-calendar-range me-1"></i> Loading period...
                            </span>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            {{-- Modal Body --}}
            <div class="modal-body p-4" style="background-color: #f8fafc;">
                {{-- KPI Cards Row --}}
                <div class="row g-3 mb-4" id="modalStoryCards">
                    <div class="col-12 text-center py-4">
                        <div class="spinner-border text-primary" role="status"><span class="sr-only">Loading transactions...</span></div>
                    </div>
                </div>

                {{-- Transactions Table Card Container --}}
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3 px-3 d-flex justify-content-between align-items-center border-bottom">
                        <h6 class="mb-0 font-weight-bold text-dark d-flex align-items-center gap-2">
                            <i class="mdi mdi-table-large text-primary" style="font-size:1.1rem;"></i>
                            <span>Detailed Transaction Ledger</span>
                        </h6>
                        <span class="badge bg-light text-dark border font-weight-bold px-3 py-1.5" id="modalRecordCountBadge">
                            <i class="mdi mdi-database me-1 text-info"></i> Transactions Log
                        </span>

                    </div>
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle w-100 table-dense-story" id="modalStoryTable">
                                <thead class="bg-light"><tr></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="modal-footer bg-white border-top py-2.5 px-4 justify-content-between">
                <div class="text-muted small d-flex align-items-center gap-1">
                    <i class="mdi mdi-shield-check text-success" style="font-size: 1.1rem;"></i>
                    <span>Internal Audit Workbench &bull; Row Drill-Down View</span>
                </div>
                <button type="button" class="btn btn-secondary px-4 font-weight-bold rounded-pill" data-bs-dismiss="modal">
                    <i class="mdi mdi-close me-1"></i> Close Audit
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
var storyDetailDtUrl = "{{ route('audit.story-details.data', ['zone' => '__ZONE__', 'story' => '__STORY__']) }}";

function openStoryDetailModal(zone, story, key) {
    var $modal = $('#storyDetailModal');
    $('#storyDetailModalTitle').html('<i class="mdi mdi-spin mdi-loading me-2 text-info"></i> Fetching Audit Details...');
    $('#storyDetailModalSubtitle').html('<i class="mdi mdi-calendar-range me-1"></i> Filter: ' + ($('#filter_start_date').val() || 'Last 30 Days') + ' to ' + ($('#filter_end_date').val() || 'Now'));
    $('#modalStoryCards').html('<div class="col-12 text-center py-4"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading transactions...</span></div></div>');

    $modal.modal('show');

    var url = storyDetailDtUrl.replace('__ZONE__', zone).replace('__STORY__', story);
    var params = {
        key: key,
        start_date: $('#filter_start_date').val(),
        end_date: $('#filter_end_date').val(),
        hmo_scheme_id: $('#filter_hmo_scheme_id').val(),
        hmo_id: $('#filter_hmo_id').val(),
        gender: $('#filter_gender').val(),
        age_range: $('#filter_age_range').val(),
        audit_status: $('#filter_audit_status').val(),
    };

    $.get(url, params, function(data) {
        $('#storyDetailModalTitle').html('<i class="mdi mdi-chart-timeline-variant text-info me-2"></i> ' + (data.title || 'Story Transaction Audit'));
        $('#storyDetailModalSubtitle').html('<i class="mdi mdi-calendar-clock me-1"></i> ' + (data.subtitle || 'Period: Filtered Range'));
        $('#modalRecordCountBadge').html('<i class="mdi mdi-database me-1 text-info"></i> ' + (data.rows ? data.rows.length.toLocaleString() : 0) + ' Records');


        // Render Rich Executive KPI cards
        var cardsHtml = '';
        if (data.cards && data.cards.length > 0) {
            var colSize = data.cards.length <= 3 ? 4 : (data.cards.length <= 4 ? 3 : 2);
            data.cards.forEach(function(card) {
                cardsHtml += '<div class="col-md-' + colSize + ' col-6 mb-2">' +
                    '<div class="card shadow-sm border-0 h-100 rounded-3 overflow-hidden">' +
                    '<div class="card-body py-3 px-3 ' + card.class + ' d-flex justify-content-between align-items-center">' +
                    '<div>' +
                    '<h6 class="mb-1 text-white-50 font-weight-bold" style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.5px;">' + card.label + '</h6>' +
                    '<h3 class="mb-0 font-weight-bold text-white" style="font-size:1.35rem;">' + card.value + '</h3>' +
                    '</div>' +
                    '<div class="bg-white bg-opacity-25 rounded-circle p-2 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">' +
                    '<i class="mdi mdi-numeric-1-box-multiple text-white fs-4"></i>' +
                    '</div>' +
                    '</div>' +
                    '</div>' +
                    '</div>';
            });
        }
        $('#modalStoryCards').html(cardsHtml);

        // Datatable Lifecycle Fix for Modal
        var $table = $('#modalStoryTable');
        if ($.fn.DataTable.isDataTable('#modalStoryTable')) {
            $table.DataTable().clear().destroy();
        }

        $table.empty().append('<thead class="bg-light"><tr></tr></thead><tbody></tbody>');

        // Render headers
        var $tr = $table.find('thead tr');
        if (data.headers && data.headers.length > 0) {
            data.headers.forEach(function(h) { $tr.append('<th class="text-uppercase font-weight-bold" style="font-size:0.75rem;">' + h + '</th>'); });
        }

        // Render rows
        var $tbody = $table.find('tbody');
        if (data.rows && data.rows.length > 0) {
            data.rows.forEach(function(row) {
                var trHtml = '<tr>';
                Object.values(row).forEach(function(val) {
                    if (typeof val === 'number') val = '<span class="font-weight-bold text-dark">₦' + val.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</span>';
                    trHtml += '<td>' + val + '</td>';
                });
                trHtml += '</tr>';
                $tbody.append(trHtml);
            });
        }

        // Re-initialize DataTable safely
        $table.DataTable({
            dom: '<"d-flex justify-content-between align-items-center mb-3"<"d-flex gap-2"B><"d-flex align-items-center"f>>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
            buttons: [
                { extend: 'copy', className: 'btn btn-xs btn-outline-secondary font-weight-bold' },
                { extend: 'excel', className: 'btn btn-xs btn-outline-success font-weight-bold' },
                { extend: 'pdf', className: 'btn btn-xs btn-outline-danger font-weight-bold' },
                { extend: 'print', className: 'btn btn-xs btn-outline-info font-weight-bold' }
            ],
            paging: true, pageLength: 15, order: [], responsive: true, destroy: true,
            language: {
                zeroRecords: "No transactions found for this entry in the selected period.",
                emptyTable: "No transactions found for this entry in the selected period."
            }
        });

    }).fail(function() {
        $('#modalStoryCards').html('<div class="col-12"><div class="alert alert-danger font-weight-bold"><i class="mdi mdi-alert-circle me-1"></i> Failed to load transaction details for this record.</div></div>');
    });
}

$(document).on('click', '.story-detail-btn', function(e) {
    e.preventDefault();
    var zone = $(this).data('zone');
    var story = $(this).data('story');
    var key = $(this).data('key');
    openStoryDetailModal(zone, story, key);
});
</script>

@stack('audit_scripts')
@endpush



