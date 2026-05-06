@extends('admin.layouts.app')

@section('content')
@php
    $sett     = appsettings();
    $hosColor = $sett->hos_color ?? '#0066cc';
@endphp
<div class="content-wrapper">
    <div class="row">
        <div class="col-md-12 grid-margin">
            <div class="d-flex justify-content-between flex-wrap">
                <div class="d-flex align-items-end flex-wrap">
                    <div class="mr-md-3 mr-xl-5">
                        <h2>Morgue Workbench</h2>
                        <p class="mb-md-0">Manage deceased patients and mortuary services.</p>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-end flex-wrap gap-2">
                    <button class="btn btn-dark mt-2 mt-xl-0" onclick="showMorgueAdmissionModal()">
                        <i class="mdi mdi-emoticon-dead"></i> Direct Morgue Admission (BID)
                    </button>
                    <button class="btn btn-primary mt-2 mt-xl-0" id="refresh-btn">
                        <i class="mdi mdi-refresh"></i> Refresh Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Tabs -->
    <ul class="nav nav-tabs mb-3" id="morgue-main-tabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="tab-workbench" data-toggle="tab" href="#pane-workbench" role="tab">
                <i class="mdi mdi-clipboard-list"></i> Workbench
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-reports" data-toggle="tab" href="#pane-reports" role="tab">
                <i class="mdi mdi-chart-box"></i> Reports &amp; Analytics
            </a>
        </li>
    </ul>

    <div class="tab-content" id="morgue-main-tab-content">

    {{-- ════════════ WORKBENCH TAB ════════════ --}}
    <div class="tab-pane fade show active" id="pane-workbench" role="tabpanel">

    <!-- Stats Section -->
    <div class="row">
        <div class="col-md-4 stretch-card grid-margin">
            <div class="card bg-gradient-danger card-img-holder text-white">
                <div class="card-body">
                    <h4 class="font-weight-normal mb-3">Pending Admissions <i class="mdi mdi-alert-circle-outline mdi-24px float-right"></i></h4>
                    <h2 class="mb-5" id="stat-pending">0</h2>
                    <p class="card-text">Candidates awaiting morgue intake</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 stretch-card grid-margin">
            <div class="card bg-gradient-info card-img-holder text-white">
                <div class="card-body">
                    <h4 class="font-weight-normal mb-3">Currently in Morgue <i class="mdi mdi-emoticon-dead mdi-24px float-right"></i></h4>
                    <h2 class="mb-5" id="stat-active">0</h2>
                    <p class="card-text">Bodies currently under care</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 stretch-card grid-margin">
            <div class="card bg-gradient-success card-img-holder text-white">
                <div class="card-body">
                    <h4 class="font-weight-normal mb-3">Released Today <i class="mdi mdi-check-circle-outline mdi-24px float-right"></i></h4>
                    <h2 class="mb-5" id="stat-released">0</h2>
                    <p class="card-text">Final discharges completed today</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Pending Admissions -->
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title text-danger"><i class="mdi mdi-clock-alert"></i> Pending Admissions</h4>
                    <div class="table-responsive">
                        <table class="table table-hover" id="pending-table">
                            <thead>
                                <tr>
                                    <th>Patient Name</th>
                                    <th>File No.</th>
                                    <th>Death Type</th>
                                    <th>Date/Time of Death</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="pending-body">
                                <!-- Loaded via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Admissions -->
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card border-top border-info border-3">
                <div class="card-body">
                    <h4 class="card-title text-info"><i class="mdi mdi-account-multiple"></i> Active Morgue Residents</h4>
                    <div class="table-responsive">
                        <table class="table table-hover" id="active-table">
                            <thead>
                                <tr>
                                    <th>Patient Name</th>
                                    <th>File No.</th>
                                    <th>Fridge/Tray</th>
                                    <th>Admitted At</th>
                                    <th>Days Spent</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="active-body">
                                <!-- Loaded via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>{{-- end #pane-workbench --}}

    {{-- ════════════ REPORTS TAB ════════════ --}}
    <div class="tab-pane fade" id="pane-reports" role="tabpanel">

        <!-- Filter Panel -->
        <div class="reports-filter-panel card-modern mb-4">
            <div class="card-header py-2">
                <h6 class="mb-0"><i class="mdi mdi-filter"></i> Filters</h6>
            </div>
            <div class="card-body py-3">
                <form id="reports-filter-form">
                    <div class="row">
                        <div class="form-group col-md-3">
                            <label for="rpt-date-from" class="small mb-1">Date From</label>
                            <input type="date" class="form-control form-control-sm" id="rpt-date-from">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="rpt-date-to" class="small mb-1">Date To</label>
                            <input type="date" class="form-control form-control-sm" id="rpt-date-to">
                        </div>
                        <div class="form-group col-md-3 d-flex align-items-end">
                            <button type="button" class="btn btn-sm btn-secondary mr-2" id="btn-clear-report-filters">
                                <i class="mdi mdi-refresh"></i> Reset
                            </button>
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="mdi mdi-filter"></i> Apply
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- KPI Cards Row -->
        <div class="row mb-4">
            <div class="col-6 col-md-2">
                <div class="rpt-stat-card">
                    <div class="rpt-stat-icon" style="background:linear-gradient(135deg,#ff5252,#f44336);">
                        <i class="mdi mdi-login"></i>
                    </div>
                    <div class="rpt-stat-content">
                        <h3 id="rpt-total-admissions">0</h3>
                        <p>Total Admissions</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="rpt-stat-card">
                    <div class="rpt-stat-icon" style="background:linear-gradient(135deg,#43a047,#66bb6a);">
                        <i class="mdi mdi-logout"></i>
                    </div>
                    <div class="rpt-stat-content">
                        <h3 id="rpt-total-released">0</h3>
                        <p>Released</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="rpt-stat-card">
                    <div class="rpt-stat-icon" style="background:linear-gradient(135deg,#40c4ff,#2196f3);">
                        <i class="mdi mdi-emoticon-dead"></i>
                    </div>
                    <div class="rpt-stat-content">
                        <h3 id="rpt-currently-stored">0</h3>
                        <p>Currently Stored</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="rpt-stat-card">
                    <div class="rpt-stat-icon" style="background:linear-gradient(135deg,#f093fb,#f5576c);">
                        <i class="mdi mdi-clock-outline"></i>
                    </div>
                    <div class="rpt-stat-content">
                        <h3 id="rpt-avg-stay">0</h3>
                        <p>Avg Stay (Days)</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="rpt-stat-card">
                    <div class="rpt-stat-icon" style="background:linear-gradient(135deg,#11998e,#38ef7d);">
                        <i class="mdi mdi-cash-multiple"></i>
                    </div>
                    <div class="rpt-stat-content">
                        <h3 id="rpt-revenue">&#8358;0</h3>
                        <p>Total Revenue</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="rpt-stat-card">
                    <div class="rpt-stat-icon" style="background:linear-gradient(135deg,#ffecd2,#fcb69f);">
                        <i class="mdi mdi-alert-circle-outline"></i>
                    </div>
                    <div class="rpt-stat-content">
                        <h3 id="rpt-pending-rev">&#8358;0</h3>
                        <p>Pending Revenue</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-md-7 grid-margin stretch-card">
                <div class="card card-modern">
                    <div class="card-body">
                        <h5 class="card-title text-muted small text-uppercase mb-3">
                            <i class="mdi mdi-chart-bar"></i> Monthly Admission Trend (Last 12 Months)
                        </h5>
                        <canvas id="rpt-chart-trend" height="120"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-5 grid-margin stretch-card">
                <div class="card card-modern">
                    <div class="card-body">
                        <h5 class="card-title text-muted small text-uppercase mb-3">
                            <i class="mdi mdi-chart-donut"></i> Death Type Breakdown
                        </h5>
                        <canvas id="rpt-chart-types" height="160"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reports Sub-tabs -->
        <ul class="nav nav-tabs mb-3" id="rpt-subtabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#rpt-pane-all" role="tab">
                    <i class="mdi mdi-table"></i> All Admissions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#rpt-pane-active" role="tab">
                    <i class="mdi mdi-emoticon-dead"></i> Currently Stored
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#rpt-pane-released" role="tab">
                    <i class="mdi mdi-check-circle"></i> Released
                </a>
            </li>
        </ul>

        <div class="tab-content" id="rpt-subtab-content">
            <div class="tab-pane fade show active" id="rpt-pane-all" role="tabpanel">
                <div class="card card-modern">
                    <div class="card-body p-0">
                        <div class="d-flex justify-content-end p-2 border-bottom">
                            <button class="btn btn-sm btn-outline-secondary" onclick="printReportTable('rpt-all-table')">
                                <i class="mdi mdi-printer"></i> Print
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0" id="rpt-all-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Body Code</th>
                                        <th>Patient</th>
                                        <th>File No.</th>
                                        <th>Death Type</th>
                                        <th>Admitted</th>
                                        <th>Released</th>
                                        <th>Days</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="rpt-all-body"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="rpt-pane-active" role="tabpanel">
                <div class="card card-modern">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0" id="rpt-active-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Body Code</th>
                                        <th>Patient</th>
                                        <th>File No.</th>
                                        <th>Death Type</th>
                                        <th>Admitted</th>
                                        <th>Days</th>
                                    </tr>
                                </thead>
                                <tbody id="rpt-active-body"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="rpt-pane-released" role="tabpanel">
                <div class="card card-modern">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0" id="rpt-released-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Body Code</th>
                                        <th>Patient</th>
                                        <th>File No.</th>
                                        <th>Death Type</th>
                                        <th>Admitted</th>
                                        <th>Released</th>
                                        <th>Days Stayed</th>
                                    </tr>
                                </thead>
                                <tbody id="rpt-released-body"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>{{-- end #pane-reports --}}

    </div>{{-- end .tab-content (morgue-main-tab-content) --}}
</div>{{-- end .content-wrapper --}}
<div class="modal fade" id="admitModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Admit to Morgue</h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="admit-form">
                    <input type="hidden" id="admit-death-record-id">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Patient Name</label>
                        <div id="admit-patient-name" class="form-control-plaintext text-primary fw-bold"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fridge No.</label>
                            <input type="text" class="form-control" id="admit-fridge" placeholder="e.g. F-102">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tray No.</label>
                            <input type="text" class="form-control" id="admit-tray" placeholder="e.g. T-05">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Daily Service Fee (Billing) <span class="text-danger">*</span></label>
                        <select class="form-select select2-morgue" id="admit-daily-service" required>
                            <option value="">-- Select Daily Rate --</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Admissions Notes</label>
                        <textarea class="form-control" id="admit-notes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="btn-save-admission">Admit Body</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Service Modal -->
<div class="modal fade" id="serviceModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Add Morgue Service</h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="service-form">
                    <input type="hidden" id="service-admission-id">
                    <div class="mb-3">
                        <label class="form-label">Select Service</label>
                        <select class="form-select select2-morgue" id="morgue-service-id" required>
                            <!-- Loaded via JS -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="morgue-service-qty" value="1" min="1">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" id="btn-save-service">Add to Bill</button>
            </div>
        </div>
    </div>
</div>

<!-- Release Modal -->
<div class="modal fade" id="releaseModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Release Body</h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="release-form">
                    <input type="hidden" id="release-admission-id">
                    <div class="mb-3">
                        <label class="form-label">Released To (Name) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="release-name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" class="form-control" id="release-phone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Release Notes</label>
                        <textarea class="form-control" id="release-notes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="btn-confirm-release">Confirm Release</button>
            </div>
        </div>
    </div>
</div>

@include('admin.partials.patient-form-modal')

{{-- ═══ PATIENT BILL MODAL ═══ --}}
<div class="modal fade" id="billModal" tabindex="-1" role="dialog" aria-labelledby="billModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="billModalLabel">
                    <i class="mdi mdi-receipt"></i> Patient Bill
                </h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 border-bottom bg-light">
                    <div id="bill-patient-info"><span class="text-muted small">Loading...</span></div>
                </div>
                <div class="p-3 border-bottom" id="bill-totals" style="display:none;">
                    <div class="row text-center">
                        <div class="col-4">
                            <small class="text-muted d-block">Total Billed</small>
                            <strong class="text-dark" id="bill-total-amount">&#8358;0</strong>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block">Paid</small>
                            <strong class="text-success" id="bill-paid-amount">&#8358;0</strong>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block">Outstanding</small>
                            <strong class="text-danger" id="bill-pending-amount">&#8358;0</strong>
                        </div>
                    </div>
                </div>
                <div id="bill-items-container" class="p-3">
                    <div class="text-center text-muted py-4" id="bill-loading">
                        <i class="mdi mdi-loading mdi-spin mdi-36px"></i>
                        <p class="mt-2">Loading bill...</p>
                    </div>
                    <div id="bill-items-list" style="display:none;">
                        <table class="table table-sm table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>Service</th>
                                    <th>Date</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-right">Unit</th>
                                    <th class="text-right">Total</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody id="bill-items-tbody"></tbody>
                        </table>
                    </div>
                    <div id="bill-empty" class="text-center text-muted py-4" style="display:none;">
                        <i class="mdi mdi-receipt-text-outline mdi-36px"></i>
                        <p class="mt-2">No morgue charges found for this patient.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btn-print-bill" class="btn btn-primary" onclick="printBillModal()" style="display:none;">
                    <i class="mdi mdi-printer"></i> Print Bill
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .card-modern {
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        border: none;
        transition: transform 0.2s;
    }
    .bg-gradient-danger  { background: linear-gradient(45deg,#ff5252,#f44336) !important; }
    .bg-gradient-info    { background: linear-gradient(45deg,#40c4ff,#2196f3) !important; }
    .bg-gradient-success { background: linear-gradient(45deg,#66bb6a,#43a047) !important; }
    .table th { font-weight: 700; color: #333; }

    /* Main tabs */
    #morgue-main-tabs .nav-link {
        color: #555;
        font-weight: 600;
        border-radius: 8px 8px 0 0;
        padding: 0.6rem 1.4rem;
    }
    #morgue-main-tabs .nav-link.active {
        background: #fff;
        border-bottom-color: #fff;
        color: #3f51b5;
    }

    /* Reports KPI cards */
    .rpt-stat-card {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: #fff;
        border-radius: 10px;
        padding: 0.9rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        margin-bottom: 1rem;
    }
    .rpt-stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .rpt-stat-icon i      { font-size: 1.3rem; color: #fff; }
    .rpt-stat-content h3  { margin: 0; font-size: 1.4rem; font-weight: 700; line-height: 1.1; }
    .rpt-stat-content p   { margin: 0; font-size: 0.72rem; color: #888; }

    /* Reports filter panel */
    .reports-filter-panel { background:#fff; border:1px solid #e8e8e8; border-radius:10px; }
    .reports-filter-panel .card-header { background:#f8f9fa; border-radius:10px 10px 0 0; border-bottom:1px solid #e8e8e8; }

    /* Report sub-tabs */
    #rpt-subtabs .nav-link         { font-size:0.85rem; color:#666; }
    #rpt-subtabs .nav-link.active  { color:#3f51b5; font-weight:600; }

    /* Bill offcanvas */
    #bill-items-tbody tr td   { font-size:0.82rem; vertical-align:middle; }
    .badge-paid    { background-color:#43a047; color:#fff; font-size:0.72rem; padding:0.25em 0.5em; border-radius:4px; }
    .badge-pending { background-color:#e53935; color:#fff; font-size:0.72rem; padding:0.25em 0.5em; border-radius:4px; }
</style>
@endpush

@push('scripts')
<script>
    $(document).ready(function() {
        // Merge (do not replace) — modal partial already set registerUrl, emergencyIntakeUrl, etc.
        $.extend(window.patientFormConfig, {
            submitUrl: '{{ route("morgue.admit") }}',
            onSuccess: function(patientId, mode) {
                toastr.success("Patient record created/admitted successfully");
                $("#patientFormModal").modal("hide");
                loadData();
            }
        });
        // Handle queue filter deep-linking from dashboard
        const urlParams = new URLSearchParams(window.location.search);
        const queueFilter = urlParams.get('queue_filter');
        if (queueFilter === 'pending') {
            // Pending is already the first thing on the page, but we can highlight it
            $('#tab-workbench').tab('show');
        } else if (queueFilter === 'admitted') {
            $('#tab-workbench').tab('show');
            // Scroll to active residents table
            $('html, body').animate({
                scrollTop: $("#active-table").offset().top - 100
            }, 500);
        }

        loadData();

        $('#refresh-btn').click(function() {
            loadData();
        });

        function loadData() {
            $.get('{{ route("morgue.queue") }}', function(response) {
                renderPending(response.pending);
                renderActive(response.active);
                $('#stat-pending').text(response.pending.length);
                $('#stat-active').text(response.active.length);
            });
        }

        function loadServices(patientId, targetSelect) {
            $(targetSelect).html('<option value="">Loading services...</option>');
            $.get('{{ route("morgue.services") }}', { patient_id: patientId }, function(services) {
                let html = '<option value="">-- Select Service --</option>';
                services.forEach(s => {
                    const basePrice = s.price ? parseFloat(s.price.sale_price) : 0;
                    const payable = parseFloat(s.payable_amount);
                    const claims = parseFloat(s.claims_amount);

                    let priceText = `Base: ₦${basePrice.toLocaleString()}`;
                    if (s.coverage_mode !== 'cash') {
                        priceText = `Payable: ₦${payable.toLocaleString()} | HMO: ₦${claims.toLocaleString()}`;
                    }

                    html += `<option value="${s.id}">${s.service_name} (${priceText})</option>`;
                });
                $(targetSelect).html(html);
            });
        }

        function renderPending(data) {
            let html = '';
            if (data.length === 0) {
                html = '<tr><td colspan="5" class="text-center py-4 text-muted">No pending admissions</td></tr>';
            } else {
                data.forEach(r => {
                    html += `
                        <tr>
                            <td class="fw-bold">${r.name}</td>
                            <td>${r.file_no}</td>
                            <td><span class="badge bg-danger">${r.death_type}</span></td>
                            <td>${r.date_of_death} ${r.time_of_death}</td>
                            <td>
                                <button class="btn btn-sm btn-danger" onclick="openAdmitModal(${r.id}, '${r.name.replace(/'/g, "\\'")}', ${r.patient_id})">
                                    <i class="mdi mdi-login"></i> Admit
                                </button>
                            </td>
                        </tr>
                    `;
                });
            }
            $('#pending-body').html(html);
        }

        function renderActive(data) {
            let html = '';
            if (data.length === 0) {
                html = '<tr><td colspan="6" class="text-center py-4 text-muted">Morgue is currently empty</td></tr>';
            } else {
                data.forEach(a => {
                    html += `
                        <tr>
                            <td class="fw-bold">${a.name}</td>
                            <td>${a.file_no}</td>
                            <td>F: ${a.fridge_no || '-'} / T: ${a.tray_no || '-'}</td>
                            <td>${a.admitted_at}</td>
                            <td><span class="badge bg-info">${a.days_spent} days</span></td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-secondary" onclick="openBillPanel(${a.id}, '${a.name}')">
                                        <i class="mdi mdi-receipt"></i> Bill
                                    </button>
                                    <button class="btn btn-sm btn-outline-info" onclick="openServiceModal(${a.id}, ${a.patient_id})">
                                        <i class="mdi mdi-plus-circle"></i> Service
                                    </button>
                                    <button class="btn btn-sm btn-outline-success" onclick="openReleaseModal(${a.id})">
                                        <i class="mdi mdi-logout"></i> Release
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
            }
            $('#active-body').html(html);
        }

        window.openAdmitModal = function(id, name, patientId) {
            $('#admit-death-record-id').val(id);
            $('#admit-patient-name').text(name);
            loadServices(patientId, '#admit-daily-service');
            $('#admitModal').modal('show');
        };

        window.openServiceModal = function(id, patientId) {
            $('#service-admission-id').val(id);
            loadServices(patientId, '#morgue-service-id');
            $('#serviceModal').modal('show');
        };

        window.openReleaseModal = function(id) {
            $('#release-admission-id').val(id);
            $('#releaseModal').modal('show');
        };

        $('#btn-save-admission').click(function() {
            const data = {
                _token: '{{ csrf_token() }}',
                death_record_id: $('#admit-death-record-id').val(),
                fridge_no: $('#admit-fridge').val(),
                tray_no: $('#admit-tray').val(),
                daily_service_id: $('#admit-daily-service').val(),
                notes: $('#admit-notes').val()
            };

            if (!data.daily_service_id) {
                toastr.warning('Please select a daily rate.');
                return;
            }

            $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

            $.post('{{ route("morgue.admit") }}', data, function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    $('#admitModal').modal('hide');
                    loadData();
                } else {
                    toastr.error(res.message);
                }
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Error processing admission.');
            }).always(() => {
                $(this).prop('disabled', false).text('Admit Body');
            });
        });

        $('#btn-save-service').click(function() {
            const data = {
                _token: '{{ csrf_token() }}',
                morgue_admission_id: $('#service-admission-id').val(),
                service_id: $('#morgue-service-id').val(),
                qty: $('#morgue-service-qty').val()
            };

            if (!data.service_id) {
                toastr.warning('Please select a service.');
                return;
            }

            $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Adding...');

            $.post('{{ route("morgue.add-service") }}', data, function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    $('#serviceModal').modal('hide');
                } else {
                    toastr.error(res.message);
                }
            }).always(() => {
                $(this).prop('disabled', false).text('Add to Bill');
            });
        });

        $('#btn-confirm-release').click(function() {
            const data = {
                _token: '{{ csrf_token() }}',
                morgue_admission_id: $('#release-admission-id').val(),
                released_to_name: $('#release-name').val(),
                released_to_phone: $('#release-phone').val(),
                release_notes: $('#release-notes').val()
            };

            if (!data.released_to_name) {
                toastr.warning('Please enter releasee name.');
                return;
            }

            $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Releasing...');

            $.post('{{ route("morgue.release") }}', data, function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    $('#releaseModal').modal('hide');
                    loadData();
                } else {
                    toastr.error(res.message);
                }
            }).always(() => {
                $(this).prop('disabled', false).text('Confirm Release');
            });
        });
    });
</script>

{{-- ═══ REPORTS & BILL SCRIPTS ═══ --}}
<script>
(function() {
    'use strict';

    /* ── Hospital branding (from appsettings) ── */
    const HOS = {!! json_encode([
        'name'    => $sett->site_name ?? config('app.name'),
        'color'   => $hosColor,
        'logo'    => $sett->logo ? 'data:image/jpeg;base64,'.$sett->logo : '',
        'address' => $sett->contact_address ?? '',
        'phone'   => $sett->contact_phones ?? '',
        'email'   => $sett->contact_emails ?? '',
        'tagline' => $sett->hos_tagline ?? '',
    ]) !!};

    let trendChart = null;
    let typesChart = null;
    let currentBillData = null;   // last loaded bill, for printing

    /* ── Utility ── */
    function fmt(n) { return '₦' + parseFloat(n||0).toLocaleString('en-NG', {minimumFractionDigits:2}); }
    function fmtDate(s) { return s ? s.substr(0,10) : '—'; }

    /* ── Shared branded header HTML (used in all print windows) ── */
    function brandedHeader(docTitle) {
        return `
        <div style="display:flex;justify-content:space-between;align-items:flex-start;
                    padding:16px 20px;border-bottom:3px solid ${HOS.color};background:#f8f9fa;">
            <div style="display:flex;align-items:center;gap:14px;">
                ${HOS.logo ? `<img src="${HOS.logo}" style="width:70px;height:70px;object-fit:contain;border-radius:6px;">` : ''}
                <div>
                    <div style="font-size:1.4rem;font-weight:700;color:${HOS.color};">${HOS.name}</div>
                    ${HOS.tagline ? `<div style="font-size:0.8rem;color:#666;">${HOS.tagline}</div>` : ''}
                </div>
            </div>
            <div style="text-align:right;font-size:0.82rem;color:#495057;line-height:1.7;">
                ${HOS.address ? HOS.address + '<br>' : ''}
                ${HOS.phone ? 'Tel: ' + HOS.phone + '<br>' : ''}
                ${HOS.email ? HOS.email : ''}
            </div>
        </div>
        <div style="background:${HOS.color};color:#fff;text-align:center;padding:8px 20px;
                    font-size:0.95rem;font-weight:600;letter-spacing:1px;">
            ${docTitle}
        </div>`;
    }

    /* ── Reports load ── */
    function loadReports() {
        const from = $('#rpt-date-from').val();
        const to   = $('#rpt-date-to').val();
        $.get('{{ route("morgue.reports") }}', { date_from: from, date_to: to })
            .done(function(res) {
                /* KPI cards */
                $('#rpt-total-admissions').text(res.stats.total_admissions);
                $('#rpt-total-released').text(res.stats.total_released);
                $('#rpt-currently-stored').text(res.stats.currently_stored);
                $('#rpt-avg-stay').text(parseFloat(res.stats.avg_stay_days||0).toFixed(1));
                $('#rpt-revenue').html('₦' + parseFloat(res.stats.total_revenue||0).toLocaleString('en-NG',{minimumFractionDigits:2}));
                $('#rpt-pending-rev').html('₦' + parseFloat(res.stats.pending_revenue||0).toLocaleString('en-NG',{minimumFractionDigits:2}));

                /* Trend chart */
                if (trendChart) trendChart.destroy();
                const trendCtx = document.getElementById('rpt-chart-trend').getContext('2d');
                trendChart = new Chart(trendCtx, {
                    type: 'bar',
                    data: {
                        labels: res.trend.labels,
                        datasets: [{
                            label: 'Admissions',
                            data: res.trend.data,
                            backgroundColor: 'rgba(63,81,181,0.7)',
                            borderColor: '#3f51b5',
                            borderWidth: 1
                        }]
                    },
                    options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
                });

                /* Death types chart */
                if (typesChart) typesChart.destroy();
                const typeLabels = Object.keys(res.death_types);
                const typeData   = Object.values(res.death_types);
                const typesCtx = document.getElementById('rpt-chart-types').getContext('2d');
                typesChart = new Chart(typesCtx, {
                    type: 'doughnut',
                    data: {
                        labels: typeLabels,
                        datasets: [{
                            data: typeData,
                            backgroundColor: ['#f44336','#2196f3','#ff9800','#4caf50','#9c27b0','#00bcd4']
                        }]
                    },
                    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
                });

                /* Tables */
                renderReportTables(res.admissions);
            })
            .fail(function() { toastr.error('Failed to load reports.'); });
    }

    function renderReportTables(admissions) {
        const allRows  = admissions;
        const actRows  = admissions.filter(a => a.status === 'stored');
        const relRows  = admissions.filter(a => a.status === 'released');

        $('#rpt-all-body').html(allRows.map(rowAll).join(''));
        $('#rpt-active-body').html(actRows.map(rowActive).join(''));
        $('#rpt-released-body').html(relRows.map(rowReleased).join(''));
    }

    function statusBadge(s) {
        const map = { admitted: 'bg-info', released: 'bg-success' };
        return `<span class="badge ${map[s]||'bg-secondary'}">${s}</span>`;
    }

    function rowAll(a) {
        return `<tr>
            <td>${a.body_code||'—'}</td>
            <td>${a.name}</td>
            <td>${a.file_no||'—'}</td>
            <td>${a.death_type||'—'}</td>
            <td>${fmtDate(a.admitted_at)}</td>
            <td>${fmtDate(a.released_at)}</td>
            <td>${a.days||0}</td>
            <td>${statusBadge(a.status)}</td>
        </tr>`;
    }
    function rowActive(a) {
        return `<tr>
            <td>${a.body_code||'—'}</td>
            <td>${a.name}</td>
            <td>${a.file_no||'—'}</td>
            <td>${a.death_type||'—'}</td>
            <td>${fmtDate(a.admitted_at)}</td>
            <td>${a.days||0}</td>
        </tr>`;
    }
    function rowReleased(a) {
        return `<tr>
            <td>${a.body_code||'—'}</td>
            <td>${a.name}</td>
            <td>${a.file_no||'—'}</td>
            <td>${a.death_type||'—'}</td>
            <td>${fmtDate(a.admitted_at)}</td>
            <td>${fmtDate(a.released_at)}</td>
            <td>${a.days||0}</td>
        </tr>`;
    }

    /* ── Patient Bill offcanvas ── */
    window.openBillPanel = function(admissionId, patientName) {
        currentBillData = null;
        $('#btn-print-bill').hide();
        $('#bill-patient-info').html(`<strong>${patientName}</strong>`);
        $('#bill-totals').hide();
        $('#bill-items-list').hide();
        $('#bill-empty').hide();
        $('#bill-loading').show();

        $('#billModal').modal('show');

        $.get(`{{ url('morgue/patient') }}/${admissionId}/bill`)
            .done(function(res) {
                $('#bill-loading').hide();
                $('#bill-patient-info').html(`
                    <strong>${res.patient_name}</strong>
                    <span class="text-muted small ml-2">File: ${res.file_no||'—'}</span>
                    ${res.body_code ? `<span class="badge bg-secondary ml-1">${res.body_code}</span>` : ''}
                `);

                currentBillData = res;   // store for print

                if (res.items && res.items.length) {
                    $('#bill-total-amount').text(fmt(res.total_amount));
                    $('#bill-paid-amount').text(fmt(res.paid_amount));
                    $('#bill-pending-amount').text(fmt(res.pending_amount));
                    $('#bill-totals').show();
                    $('#btn-print-bill').show();

                    const rows = res.items.map(i => `
                        <tr>
                            <td>${i.service_name}</td>
                            <td>${fmtDate(i.date)}</td>
                            <td class="text-center">${i.qty}</td>
                            <td class="text-right">${fmt(i.unit_price)}</td>
                            <td class="text-right">${fmt(i.total)}</td>
                            <td class="text-center">
                                ${i.paid
                                    ? `<span class="badge-paid">Paid</span>`
                                    : `<span class="badge-pending">Pending</span>`}
                            </td>
                        </tr>
                    `).join('');
                    $('#bill-items-tbody').html(rows);
                    $('#bill-items-list').show();
                } else {
                    $('#bill-empty').show();
                }
            })
            .fail(function() {
                $('#bill-loading').hide();
                toastr.error('Failed to load patient bill.');
            });
    };

    /* ── Print patient bill ── */
    window.printBillModal = function() {
        const d = currentBillData;
        if (!d) return;
        const rows = (d.items||[]).map(i => `
            <tr>
                <td>${i.service_name}</td>
                <td>${fmtDate(i.date)}</td>
                <td style="text-align:center">${i.qty}</td>
                <td style="text-align:right">${fmt(i.unit_price)}</td>
                <td style="text-align:right">${fmt(i.total)}</td>
                <td style="text-align:center">
                    <span style="background:${i.paid?'#43a047':'#e53935'};color:#fff;padding:2px 8px;
                                border-radius:4px;font-size:0.75rem;">
                        ${i.paid?'Paid':'Pending'}
                    </span>
                </td>
            </tr>`).join('');

        const win = window.open('', '_blank');
        win.document.write(`<!DOCTYPE html><html><head>
            <meta charset="UTF-8">
            <title>Morgue Bill – ${d.patient_name}</title>
            <style>
                *{box-sizing:border-box;margin:0;padding:0}
                body{font-family:'Segoe UI',Arial,sans-serif;font-size:13px;color:#212529;background:#fff;print-color-adjust:exact;-webkit-print-color-adjust:exact}
                .container{max-width:800px;margin:0 auto;padding:20px}
                table{width:100%;border-collapse:collapse;margin-top:16px}
                th,td{padding:8px 10px;border:1px solid #dee2e6;font-size:0.82rem}
                thead th{background:${HOS.color};color:#fff;font-weight:600}
                .info-row{display:flex;gap:16px;padding:14px 20px;background:#f8f9fa;border-bottom:1px solid #dee2e6}
                .info-cell small{color:#6c757d;display:block;font-size:0.72rem}
                .totals-row{display:flex;gap:0;border:1px solid #dee2e6;border-radius:6px;overflow:hidden;margin:16px 0}
                .totals-cell{flex:1;text-align:center;padding:12px;border-right:1px solid #dee2e6}
                .totals-cell:last-child{border-right:none}
                .totals-cell .lbl{font-size:0.72rem;color:#6c757d;display:block}
                .totals-cell .val{font-size:1.1rem;font-weight:700}
                .footer{margin-top:24px;font-size:0.75rem;color:#999;text-align:center;border-top:1px solid #dee2e6;padding-top:12px}
                @media print{@page{size:A4;margin:10mm} .no-print{display:none}}
            </style>
        </head><body>
        <div class="container">
            ${brandedHeader('MORGUE BILL / STATEMENT')}
            <div class="info-row">
                <div class="info-cell"><small>Patient Name</small><strong>${d.patient_name}</strong></div>
                <div class="info-cell"><small>File No.</small><strong>${d.file_no||'—'}</strong></div>
                ${ d.body_code ? `<div class="info-cell"><small>Body Code</small><strong>${d.body_code}</strong></div>` : '' }
                <div class="info-cell" style="margin-left:auto"><small>Print Date</small><strong>${new Date().toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'})}</strong></div>
            </div>
            <div class="totals-row">
                <div class="totals-cell"><span class="lbl">Total Billed</span><span class="val">${fmt(d.total_amount)}</span></div>
                <div class="totals-cell"><span class="lbl">Paid</span><span class="val" style="color:#43a047">${fmt(d.paid_amount)}</span></div>
                <div class="totals-cell"><span class="lbl">Outstanding</span><span class="val" style="color:#e53935">${fmt(d.pending_amount)}</span></div>
            </div>
            <table>
                <thead><tr><th>Service</th><th>Date</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Status</th></tr></thead>
                <tbody>${rows || '<tr><td colspan="6" style="text-align:center;color:#999">No items</td></tr>'}</tbody>
            </table>
            <div class="footer">This is a computer-generated document &mdash; ${HOS.name}</div>
        </div>
        <script>window.onload=function(){window.print();}<\/script>
        </body></html>`);
        win.document.close();
    };

    /* ── Print report table ── */
    window.printReportTable = function(tableId, subtitle) {
        const tbl = document.getElementById(tableId);
        if (!tbl) return;
        const title = (subtitle || 'MORGUE ADMISSIONS REPORT').toUpperCase();
        const dateRange = $('#rpt-date-from').val() && $('#rpt-date-to').val()
            ? `Period: ${$('#rpt-date-from').val()} to ${$('#rpt-date-to').val()}`
            : '';
        const win = window.open('', '_blank');
        win.document.write(`<!DOCTYPE html><html><head>
            <meta charset="UTF-8">
            <title>${title}</title>
            <style>
                *{box-sizing:border-box;margin:0;padding:0}
                body{font-family:'Segoe UI',Arial,sans-serif;font-size:12px;color:#212529;background:#fff;print-color-adjust:exact;-webkit-print-color-adjust:exact}
                .container{max-width:900px;margin:0 auto;padding:16px}
                table{width:100%;border-collapse:collapse;margin-top:14px}
                th,td{padding:7px 9px;border:1px solid #dee2e6;font-size:0.8rem}
                thead th{background:${HOS.color};color:#fff;font-weight:600}
                tbody tr:nth-child(even){background:#f8f9fa}
                .meta{padding:8px 20px;font-size:0.8rem;color:#666;background:#f8f9fa;border-bottom:1px solid #dee2e6}
                .footer{margin-top:20px;font-size:0.72rem;color:#999;text-align:center;border-top:1px solid #dee2e6;padding-top:10px}
                @media print{@page{size:A4 landscape;margin:8mm}}
            </style>
        </head><body>
        <div class="container">
            ${brandedHeader(title)}
            ${ dateRange ? `<div class="meta">${dateRange} &nbsp;&bull;&nbsp; Printed: ${new Date().toLocaleDateString('en-GB')}</div>` : '' }
            ${tbl.outerHTML}
            <div class="footer">This is a computer-generated document &mdash; ${HOS.name}</div>
        </div>
        <script>window.onload=function(){window.print();}<\/script>
        </body></html>`);
        win.document.close();
    };

    /* ── Init ── */
    $(document).ready(function() {
        // Default dates: last 3 months
        const today = new Date();
        const past  = new Date(today); past.setMonth(past.getMonth() - 3);
        $('#rpt-date-to').val(today.toISOString().substr(0,10));
        $('#rpt-date-from').val(past.toISOString().substr(0,10));

        // Load reports when tab shown
        $('a[href="#pane-reports"]').on('shown.bs.tab', function() {
            loadReports();
        });

        // Filter form submit
        $('#reports-filter-form').on('submit', function(e) {
            e.preventDefault();
            loadReports();
        });

        // Reset filters
        $('#btn-clear-report-filters').on('click', function() {
            const t = new Date();
            const p = new Date(t); p.setMonth(p.getMonth() - 3);
            $('#rpt-date-to').val(t.toISOString().substr(0,10));
            $('#rpt-date-from').val(p.toISOString().substr(0,10));
            loadReports();
        });
    });
}());
</script>
@endpush
