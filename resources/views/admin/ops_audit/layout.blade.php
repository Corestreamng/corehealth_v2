@extends('admin.layouts.app')

@section('title', 'Ops Audit Workbench')

@push('styles')
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/select2/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/ops-audit.css') }}">
@stack('ops_audit_styles')
@endpush

@push('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('plugins/select2/select2.min.js') }}"></script>
<script src="{{ asset('js/ops-audit.js') }}"></script>
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
                    <i class="mdi mdi-heart-pulse"></i> Nursing / Ward
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
                    <i class="mdi mdi-mother-nurse"></i> Maternity
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
            
            <div class="menu-section mt-4">Monitoring</div>
            <div class="nav-item">
                <a href="{{ route('ops-audit.queries') }}" class="nav-link {{ request()->routeIs('ops-audit.queries*') ? 'active' : '' }}">
                    <i class="mdi mdi-alert-circle text-danger"></i> Unified Queries
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



{{-- Universal Stamp Modal --}}
{{-- Generic Details Modal --}}
<div class="modal fade" id="opsAuditGenericModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title font-weight-bold" id="opsAuditGenericModalTitle">
                    <i class="mdi mdi-information-outline me-2 text-primary"></i> Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" id="opsAuditGenericModalBody" style="min-height: 400px;">
                <div class="d-flex justify-content-center align-items-center h-100 p-5">
                    <i class="mdi mdi-loading mdi-spin text-primary" style="font-size: 3rem;"></i>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="universalStampModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            <div class="modal-header text-white py-3 px-4" style="background: linear-gradient(135deg, #065f46 0%, #047857 100%); border-bottom: 3px solid #10b981;">
                <h5 class="modal-title font-weight-bold text-white d-flex align-items-center" id="stamp_modal_title">
                    <i class="mdi mdi-check-decagram text-white me-2" style="font-size:1.4rem;"></i> Confirm Audit Stamp
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="background-color: #f8fafc;">
                <div class="row g-0">
                    <div class="col-md-7 p-4">
                        <input type="hidden" id="stamp_mode">
                        <input type="hidden" id="stamp_model_type">
                        <input type="hidden" id="stamp_model_id">
                        
                        <div class="card-modern border-0 shadow-sm mb-4 rounded-3 p-3 bg-white border-start border-success border-4">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-success bg-opacity-10 p-2 me-3 text-success">
                                    <i class="mdi mdi-shield-check" style="font-size:1.8rem;"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 font-weight-bold text-dark">Confirm Audit Stamp</h6>
                                    <p class="mb-0 text-muted small" id="stamp_modal_text">You are about to mark the selected record as audited and verified.</p>
                                </div>
                            </div>
                        </div>

                        <div class="form-check bg-white p-3 rounded-3 border shadow-sm mb-2 d-flex align-items-center">
                            <input class="form-check-input ms-1 me-3 mt-0" type="checkbox" id="stamp_confirm_check" onchange="toggleUniversalStampButton(this)" style="transform: scale(1.35);">
                            <label class="form-check-label user-select-none font-weight-bold text-dark mb-0" for="stamp_confirm_check" style="font-size:0.9rem;">
                                <i class="mdi mdi-check-decagram text-success me-1"></i> I confirm I have reviewed this record
                                <small class="text-muted d-block font-weight-normal mt-0.5" style="font-size:0.8rem;">This will permanently mark the record as audited and lock it from further queries.</small>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-5 bg-white border-start p-4" id="stamp_timeline_container" style="max-height: 400px; overflow-y: auto;">
                        <!-- Timeline JS injected here -->
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-2.5 px-4 justify-content-between">
                <button type="button" class="btn btn-secondary px-4 font-weight-bold rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success px-4 font-weight-bold rounded-pill shadow-sm" id="btnSubmitUniversalStamp" onclick="submitUniversalStamp()" disabled>
                    <i class="mdi mdi-check-circle me-1"></i> Stamp Records
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Raise Query Modal --}}
<div class="modal fade" id="raiseQueryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            <div class="modal-header text-dark py-3 px-4" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-bottom: 3px solid #b45309;">
                <h5 class="modal-title font-weight-bold text-white d-flex align-items-center">
                    <i class="mdi mdi-alert-circle text-white me-2" style="font-size:1.4rem;"></i> Raise Audit Query
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="background-color: #f8fafc;">
                <div class="row g-0">
                    <div class="col-md-7 p-4">
                        <form id="formRaiseQuery">
                            @csrf
                            <input type="hidden" name="model_type" id="query_model_type">
                            <input type="hidden" name="model_id" id="query_model_id">
                            
                            <div class="card-modern border-0 shadow-sm mb-4 rounded-3 p-3 bg-white border-start border-warning border-4">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-warning bg-opacity-10 p-2 me-3 text-warning">
                                        <i class="mdi mdi-alert-circle" style="font-size:1.8rem;"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1 font-weight-bold text-dark">Flag Record for Investigation</h6>
                                        <p class="mb-0 text-muted small">Raising a query blocks this record from being audited until resolved.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label font-weight-bold text-dark">Query Reason & Notes <span class="text-danger">*</span></label>
                                <textarea name="query_notes" id="query_notes" class="form-control border-secondary shadow-sm rounded-3 p-3" rows="4" required placeholder="Describe in detail why this record is being flagged (e.g. Missing signature, mismatched amounts, awaiting supervisor approval)..."></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-5 bg-white border-start p-4" id="raise_timeline_container" style="max-height: 400px; overflow-y: auto;">
                        <!-- Timeline JS injected here -->
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-2.5 px-4 justify-content-between">
                <button type="button" class="btn btn-secondary px-4 font-weight-bold rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning text-dark font-weight-bold px-4 rounded-pill shadow-sm" id="btnSubmitQuery" onclick="submitRaiseQuery()">
                    <i class="mdi mdi-flag me-1"></i> Raise Query
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Resolve Query Modal --}}
<div class="modal fade" id="resolveQueryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            <div class="modal-header text-white py-3 px-4" style="background: linear-gradient(135deg, #065f46 0%, #047857 100%); border-bottom: 3px solid #10b981;">
                <h5 class="modal-title font-weight-bold text-white d-flex align-items-center">
                    <i class="mdi mdi-check-decagram text-white me-2" style="font-size:1.4rem;"></i> Resolve Audit Query
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="background-color: #f8fafc;">
                <div class="row g-0">
                    <div class="col-md-7 p-4">
                        <form id="formResolveQuery">
                            @csrf
                            <input type="hidden" name="model_type" id="resolve_model_type">
                            <input type="hidden" name="model_id" id="resolve_model_id">
                            <div class="card-modern border-0 shadow-sm mb-4 rounded-3 p-3 bg-white border-start border-success border-4">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-success bg-opacity-10 p-2 me-3 text-success">
                                        <i class="mdi mdi-shield-check" style="font-size:1.8rem;"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1 font-weight-bold text-dark">Resolving Flagged Query</h6>
                                        <p class="mb-0 text-muted small">Enter the resolution outcome or corrective action taken to close this discrepancy.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label font-weight-bold text-dark">Resolution Notes & Findings <span class="text-danger">*</span></label>
                                <textarea name="resolution_notes" id="resolve_notes" class="form-control border-secondary shadow-sm rounded-3 p-3" rows="4" required placeholder="Describe how this query was verified and resolved (e.g. Receipt verified, missing claim document attached, supervisor approved mismatch)..."></textarea>
                            </div>

                            <div class="form-check bg-white p-3 rounded-3 border shadow-sm mb-2 d-flex align-items-center">
                                <input class="form-check-input ms-1 me-3 mt-0" type="checkbox" name="auto_stamp" id="resolve_auto_stamp" value="1" checked style="transform: scale(1.35);">
                                <label class="form-check-label user-select-none font-weight-bold text-dark mb-0" for="resolve_auto_stamp" style="font-size:0.9rem;">
                                    <i class="mdi mdi-check-decagram text-success me-1"></i> Automatically stamp record as audited upon resolution
                                    <small class="text-muted d-block font-weight-normal mt-0.5" style="font-size:0.8rem;">Marks the underlying entry as verified/audited (`is_audited = 1`) immediately after closing the query.</small>
                                </label>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-5 bg-white border-start p-4" id="resolve_timeline_container" style="max-height: 400px; overflow-y: auto;">
                        <!-- Timeline JS injected here -->
                    </div>
                </div>
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

{{-- Standalone View Timeline Modal --}}
<div class="modal fade" id="viewTimelineModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            <div class="modal-header text-white py-3 px-4" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border-bottom: 3px solid #64748b;">
                <h5 class="modal-title font-weight-bold text-white d-flex align-items-center">
                    <i class="mdi mdi-history text-white me-2"></i> Audit Ledger Timeline
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="view_timeline_container" style="max-height: 550px; overflow-y: auto; background-color: #f8fafc;">
                <!-- Timeline JS injected here -->
            </div>
            <div class="modal-footer bg-white border-top py-2.5 px-4 justify-content-end">
                <button type="button" class="btn btn-secondary px-4 font-weight-bold rounded-pill" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- View Query Modal --}}
<div class="modal fade" id="viewQueryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            <div class="modal-header text-white py-3 px-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-bottom: 3px solid #3b82f6;">
                <h5 class="modal-title font-weight-bold text-white d-flex align-items-center" id="viewQueryModalTitle">
                    <i class="mdi mdi-shield-search text-info me-2"></i> Audit Query Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="viewQueryModalBody" style="background-color: #f8fafc;">
                <div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>
            </div>
            <div class="modal-footer bg-white border-top py-2.5 px-4 justify-content-between">
                <button type="button" class="btn btn-secondary px-4 font-weight-bold rounded-pill" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-warning text-dark font-weight-bold px-4 rounded-pill" id="viewQueryModalResolveBtn">
                    <i class="mdi mdi-check-circle me-1"></i> Resolve Query
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Transactions Console Modal --}}
@include('admin.shared._my_transactions_modal')

@push('scripts')
<script src="{{ asset('js/billing-my-transactions.js') }}"></script>
@stack('ops_audit_scripts')
@endpush
