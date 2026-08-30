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
            <div class="card-modern bg-gradient-danger card-img-holder text-white">
                <div class="card-body">
                    <h4 class="font-weight-normal mb-3">Pending Admissions <i class="mdi mdi-alert-circle-outline mdi-24px float-right"></i></h4>
                    <h2 class="mb-5" id="stat-pending">0</h2>
                    <p class="card-text">Candidates awaiting morgue intake</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 stretch-card grid-margin">
            <div class="card-modern bg-gradient-info card-img-holder text-white">
                <div class="card-body">
                    <h4 class="font-weight-normal mb-3">Currently in Morgue <i class="mdi mdi-emoticon-dead mdi-24px float-right"></i></h4>
                    <h2 class="mb-5" id="stat-active">0</h2>
                    <p class="card-text">Bodies currently under care</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 stretch-card grid-margin">
            <div class="card-modern bg-gradient-success card-img-holder text-white">
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
            <div class="card-modern">
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
            <div class="card-modern border-top border-info border-3">
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

@include("admin.morgue.partials._modals")
@endsection

@include("admin.morgue.partials._scripts")
