@extends('admin.layouts.app')

@section('title', 'Internal Audit Workbench')

@push('styles')
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
        width: calc(100% - 250px);
    }
    
    .audit-tick-btn {
        transition: all 0.2s ease-in-out;
    }
    .audit-tick-btn:hover {
        transform: scale(1.1);
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
            width: 100%;
        }
    }
</style>
@stack('audit_styles')
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
                <a href="{{ route('audit.cash-and-billing') }}" class="nav-link {{ request()->routeIs('audit.cash-and-billing') ? 'active' : '' }}">
                    <i class="mdi mdi-cash-multiple"></i> Cash Book & POS
                </a>
            </div>
            <div class="nav-item">
                <a href="{{ route('audit.receivables') }}" class="nav-link {{ request()->routeIs('audit.receivables') ? 'active' : '' }}">
                    <i class="mdi mdi-account-cash"></i> Receivables & Waivers
                </a>
            </div>
            <div class="nav-item">
                <a href="{{ route('audit.hmo-claims') }}" class="nav-link {{ request()->routeIs('audit.hmo-claims') ? 'active' : '' }}">
                    <i class="mdi mdi-hospital-building"></i> HMO Claims & Capitation
                </a>
            </div>
            <div class="nav-item">
                <a href="{{ route('audit.expenses-payroll') }}" class="nav-link {{ request()->routeIs('audit.expenses-payroll') ? 'active' : '' }}">
                    <i class="mdi mdi-cash-register"></i> Payroll & OpEx
                </a>
            </div>

            <div class="menu-section">Clinical & Ward Flow</div>
            <div class="nav-item">
                <a href="{{ route('audit.clinics-flow') }}" class="nav-link {{ request()->routeIs('audit.clinics-flow') ? 'active' : '' }}">
                    <i class="mdi mdi-human-male-female"></i> Clinics & Patient Flow
                </a>
            </div>
            <div class="nav-item">
                <a href="{{ route('audit.ward-discharge') }}" class="nav-link {{ request()->routeIs('audit.ward-discharge') ? 'active' : '' }}">
                    <i class="mdi mdi-bed"></i> Ward Discharges
                </a>
            </div>
            <div class="nav-item">
                <a href="{{ route('audit.maternity-morgue') }}" class="nav-link {{ request()->routeIs('audit.maternity-morgue') ? 'active' : '' }}">
                    <i class="mdi mdi-baby-carriage"></i> Maternity & Morgue
                </a>
            </div>

            <div class="menu-section">Diagnostics & Services</div>
            <div class="nav-item">
                <a href="{{ route('audit.prescriptions') }}" class="nav-link {{ request()->routeIs('audit.prescriptions') ? 'active' : '' }}">
                    <i class="mdi mdi-pill"></i> Prescriptions
                </a>
            </div>
            <div class="nav-item">
                <a href="{{ route('audit.lab-verification') }}" class="nav-link {{ request()->routeIs('audit.lab-verification') ? 'active' : '' }}">
                    <i class="mdi mdi-microscope"></i> Lab Price Verif.
                </a>
            </div>
            <div class="nav-item">
                <a href="{{ route('audit.imaging-verification') }}" class="nav-link {{ request()->routeIs('audit.imaging-verification') ? 'active' : '' }}">
                    <i class="mdi mdi-radiology"></i> Imaging Price Verif.
                </a>
            </div>
            <div class="nav-item">
                <a href="{{ route('audit.procedures') }}" class="nav-link {{ request()->routeIs('audit.procedures') ? 'active' : '' }}">
                    <i class="mdi mdi-medical-bag"></i> Procedures & Theatre
                </a>
            </div>
            
            <div class="menu-section">Inventory</div>
            <div class="nav-item">
                <a href="{{ route('audit.stock-utilization') }}" class="nav-link {{ request()->routeIs('audit.stock-utilization') ? 'active' : '' }}">
                    <i class="mdi mdi-clipboard-pulse"></i> Stock & Utilization
                </a>
            </div>

            <div class="menu-section">Reports & Shift Deductions</div>
            <div class="nav-item">
                <a href="{{ route('audit.shift-audit') }}" class="nav-link {{ request()->routeIs('audit.shift-audit') ? 'active' : '' }}">
                    <i class="mdi mdi-clock-outline"></i> Shift Audit
                </a>
            </div>
            <div class="nav-item">
                <a href="{{ route('audit.custom-report') }}" class="nav-link {{ request()->routeIs('audit.custom-report') ? 'active' : '' }}">
                    <i class="mdi mdi-file-chart"></i> Custom Report
                </a>
            </div>
            <div class="nav-item">
                <a href="{{ route('audit.overall-report') }}" class="nav-link {{ request()->routeIs('audit.overall-report') ? 'active' : '' }}">
                    <i class="mdi mdi-chart-bar"></i> Overall Report
                </a>
            </div>
            <div class="nav-item">
                <a href="{{ route('audit.staff-deductions') }}" class="nav-link {{ request()->routeIs('audit.staff-deductions') ? 'active' : '' }}">
                    <i class="mdi mdi-cash-minus"></i> Staff Deductions
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
    function markAudited(modelType, modelId, btnElement) {
        if (!confirm('Are you sure you want to mark this record as audited?')) {
            return;
        }
        
        let originalHtml = btnElement.innerHTML;
        btnElement.innerHTML = '<i class="mdi mdi-loading mdi-spin"></i>';
        btnElement.disabled = true;
        
        $.ajax({
            url: '{{ route("audit.mark-audited") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                model_type: modelType,
                model_id: modelId
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    // Change button to indicate it's audited
                    btnElement.classList.remove('btn-outline-success');
                    btnElement.classList.add('btn-success');
                    btnElement.innerHTML = '<i class="mdi mdi-check-decagram"></i> Audited';
                } else {
                    toastr.error(response.message);
                    btnElement.innerHTML = originalHtml;
                    btnElement.disabled = false;
                }
            },
            error: function(xhr) {
                toastr.error('An error occurred while marking as audited.');
                btnElement.innerHTML = originalHtml;
                btnElement.disabled = false;
            }
        });
    }
</script>
<script>
    // Global function for the Green Audit Tick
    function markAudited(modelType, modelId, btnElement) {
        if (!confirm('Are you sure you want to mark this record as audited?')) {
            return;
        }

        let originalHtml = btnElement.innerHTML;
        btnElement.innerHTML = '<i class="mdi mdi-loading mdi-spin"></i>';
        btnElement.disabled = true;

        $.ajax({
            url: '{{ route("audit.mark-audited") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                model_type: modelType,
                model_id: modelId
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    // Change button to indicate it's audited
                    btnElement.classList.remove('btn-outline-success');
                    btnElement.classList.add('btn-success');
                    btnElement.innerHTML = '<i class="mdi mdi-check-decagram"></i> Audited';
                    btnElement.disabled = true; // Disable after success
                } else {
                    toastr.error(response.message);
                    btnElement.innerHTML = originalHtml;
                    btnElement.disabled = false;
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred while marking as audited.');
                btnElement.innerHTML = originalHtml;
                btnElement.disabled = false;
            }
        });
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

        $.ajax({
            url: '{{ route("audit.raise-query") }}',
            type: 'POST',
            data: $(form).serialize(),
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
            url: '{{ route("audit.resolve-query") }}',
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

    function submitBulkStamp() {
        let btn = document.getElementById('btnSubmitBulkStamp');
        let form = document.getElementById('formBulkStamp');
        btn.disabled = true;
        btn.innerHTML = 'Applying Stamp...';

        $.ajax({
            url: '{{ route("audit.bulk-stamp") }}',
            type: 'POST',
            data: $(form).serialize(),
            success: function(res) {
                if(res.success) {
                    toastr.success(res.message);
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    toastr.error(res.message);
                    btn.disabled = false;
                    btn.innerHTML = 'Apply Bulk Stamp';
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred.');
                btn.disabled = false;
                btn.innerHTML = 'Apply Bulk Stamp';
            }
        });
    }
</script>
@stack('audit_scripts')
@endpush

{{-- Bulk Stamp Modal --}}
<div class="modal fade" id="bulkStampModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-indigo text-white" style="background-color: var(--audit-accent);">
                <h5 class="modal-title"><i class="mdi mdi-stamper"></i> Bulk Approve Period</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formBulkStamp">
                    @csrf
                    <input type="hidden" name="zone_key" id="stamp_zone_key">
                    <input type="hidden" name="start_date" id="stamp_start_date">
                    <input type="hidden" name="end_date" id="stamp_end_date">
                    
                    <div class="alert alert-info py-2 small">
                        You are applying a global approval stamp to <strong><span id="stamp_zone_label"></span></strong>.
                    </div>
                    
                    <div class="d-flex justify-content-between mb-3 bg-light p-2 rounded border">
                        <div>
                            <div class="text-muted small">From</div>
                            <div class="font-weight-bold" id="stamp_display_start"></div>
                        </div>
                        <div class="text-right">
                            <div class="text-muted small">To</div>
                            <div class="font-weight-bold" id="stamp_display_end"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small font-weight-bold">Stamp Notes / Comments</label>
                        <textarea name="notes" class="form-control border-secondary" rows="3" placeholder="Checked against physical copies..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="btnSubmitBulkStamp" onclick="submitBulkStamp()">Apply Bulk Stamp</button>
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
