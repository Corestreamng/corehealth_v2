@extends('admin.layouts.app')
@section('title', 'Encounter Intelligence Workbench')
@section('page_name', 'Encounters')
@section('subpage_name', 'Intelligence Workbench')

@section('style')
    <link rel="stylesheet" href="{{ asset('css/encounter-workbench.css') }}">
@endsection

@section('content')
<div class="container-fluid ewb-container" id="ewb-app">

    {{-- ══ Header ═══════════════════════════════════════════════════════════ --}}
    <div class="ewb-header d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h2 class="ewb-page-title mb-1">
                <i class="mdi mdi-stethoscope"></i> Encounter Intelligence Workbench
            </h2>
            <p class="text-muted mb-0 small">
                Comprehensive encounter analytics &amp; clinical activity overview
                @if($isDoctor)
                    <span class="badge ewb-badge-doctor ms-2"><i class="mdi mdi-account-tie-outline"></i> Viewing your encounters only</span>
                @endif
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-secondary btn-sm" id="ewb-btn-export" title="Export CSV">
                <i class="mdi mdi-download"></i> Export
            </button>
            <button class="btn btn-outline-dark btn-sm" id="ewb-btn-print" title="Print Report">
                <i class="mdi mdi-printer"></i> Print
            </button>
        </div>
    </div>

    {{-- ══ Filter Bar ════════════════════════════════════════════════════════ --}}
    @include('admin.encounters.partials._filter_bar')

    {{-- ══ KPI Strip ════════════════════════════════════════════════════════ --}}
    <div class="ewb-kpi-strip" id="ewb-kpi-strip">
        <div class="ewb-kpi-loading"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> Loading...</div>
    </div>

    {{-- ══ Tab Navigation ══════════════════════════════════════════════════ --}}
    @include('admin.encounters.partials._tab_nav')

    {{-- ══ Tab Content ═════════════════════════════════════════════════════ --}}
    <div class="tab-content ewb-tab-content" id="ewb-tab-content">

        {{-- Tab 1: Encounter List --}}
        <div class="tab-pane fade show active" id="ewb-pane-list" role="tabpanel">
            <div class="card-modern">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="ewb-table-list" class="table table-sm table-hover ewb-datatable w-100">
                            <thead class="ewb-thead">
                                <tr>
                                    <th>#</th>
                                    <th>Patient</th>
                                    <th>HMO / Payer</th>
                                    <th>Clinic</th>
                                    <th>Doctor</th>
                                    <th>Date / Time</th>
                                    <th>Tags</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tab 2: KPI Overview --}}
        <div class="tab-pane fade" id="ewb-pane-kpi" role="tabpanel">
            <div class="row" id="ewb-kpi-overview-cards"></div>
            <div class="row mt-3">
                <div class="col-md-7">
                    <div class="card-modern p-3">
                        <h6 class="ewb-chart-title"><i class="mdi mdi-chart-bar"></i> Daily Encounters Trend</h6>
                        <canvas id="ewb-chart-daily" height="200"></canvas>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="card-modern p-3">
                        <h6 class="ewb-chart-title"><i class="mdi mdi-chart-donut"></i> HMO vs Private</h6>
                        <canvas id="ewb-chart-payer" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12">
                    <div class="card-modern p-3">
                        <h6 class="ewb-chart-title"><i class="mdi mdi-hospital-building"></i> Top Clinics by Volume</h6>
                        <canvas id="ewb-chart-clinic-bar" height="150"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tab 3: Clinic Analytics (admin/accounts only) --}}
        @if($canViewAnalytics)
        <div class="tab-pane fade" id="ewb-pane-clinic" role="tabpanel">
            <div class="row mb-3" id="ewb-clinic-kpis"></div>
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card-modern p-3">
                        <h6 class="ewb-chart-title"><i class="mdi mdi-chart-bar-stacked"></i> Clinic Activity</h6>
                        <canvas id="ewb-chart-clinic-analytics" height="150"></canvas>
                    </div>
                </div>
            </div>
            <div class="card-modern">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="ewb-table-clinic" class="table table-sm table-hover ewb-datatable w-100">
                            <thead class="ewb-thead">
                                <tr>
                                    <th>Clinic</th>
                                    <th>Total Enc.</th>
                                    <th>Unique Pts</th>
                                    <th>Return Pts</th>
                                    <th>Completed</th>
                                    <th>Completion %</th>
                                    <th>Avg Duration</th>
                                    <th>Lab</th>
                                    <th>Imaging</th>
                                    <th>Rx</th>
                                    <th>Referrals</th>
                                    <th>Admissions</th>
                                    <th>Adm. Rate</th>
                                    <th></th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Tab 4: Doctor Productivity (admin/accounts/own-doctor) --}}
        @if($canViewAnalytics || $isDoctor)
        <div class="tab-pane fade" id="ewb-pane-doctor" role="tabpanel">
            <div class="row mb-3" id="ewb-doctor-kpis"></div>
            <div class="row mb-3">
                <div class="col-md-7">
                    <div class="card-modern p-3">
                        <h6 class="ewb-chart-title"><i class="mdi mdi-chart-bar"></i> Doctor Encounter Volume</h6>
                        <canvas id="ewb-chart-doctor-bar" height="200"></canvas>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="card-modern p-3">
                        <h6 class="ewb-chart-title"><i class="mdi mdi-chart-donut"></i> Completion Breakdown</h6>
                        <canvas id="ewb-chart-doctor-donut" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="card-modern">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="ewb-table-doctor" class="table table-sm table-hover ewb-datatable w-100">
                            <thead class="ewb-thead">
                                <tr>
                                    <th>Doctor</th>
                                    <th>Clinic</th>
                                    <th>Total</th>
                                    <th>Unique Pts</th>
                                    <th>Avg Duration</th>
                                    <th>Completion %</th>
                                    <th>Lab</th>
                                    <th>Imaging</th>
                                    <th>Rx</th>
                                    <th>Referrals</th>
                                    <th>Procedures</th>
                                    <th>Admissions</th>
                                    <th></th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Tab 5: Revenue (admin/accounts/own-doctor) --}}
        @if($canViewRevenue)
        <div class="tab-pane fade" id="ewb-pane-revenue" role="tabpanel">
            <div class="d-flex gap-2 mb-3 align-items-center">
                <span class="small text-muted font-weight-bold">Group by:</span>
                <div class="btn-group btn-group-sm" id="ewb-revenue-group-btns">
                    <button class="btn btn-outline-primary active" data-group="doctor">Doctor</button>
                    <button class="btn btn-outline-primary" data-group="clinic">Clinic</button>
                    @if($canViewAnalytics)
                    <button class="btn btn-outline-primary" data-group="hmo">HMO</button>
                    <button class="btn btn-outline-primary" data-group="method">Payment Method</button>
                    @endif
                </div>
            </div>
            <div class="row mb-3" id="ewb-revenue-kpis"></div>
            <div class="row mb-3">
                <div class="col-md-8">
                    <div class="card-modern p-3">
                        <h6 class="ewb-chart-title"><i class="mdi mdi-chart-bar"></i> Billing Breakdown</h6>
                        <canvas id="ewb-chart-revenue-bar" height="200"></canvas>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card-modern p-3">
                        <h6 class="ewb-chart-title"><i class="mdi mdi-chart-donut"></i> HMO vs Private Share</h6>
                        <canvas id="ewb-chart-revenue-donut" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="card-modern">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="ewb-table-revenue" class="table table-sm table-hover ewb-datatable w-100">
                            <thead class="ewb-thead" id="ewb-revenue-thead">
                                <tr>
                                    <th>Group</th>
                                    <th>Patients</th>
                                    <th>Encounters</th>
                                    <th>Line Items</th>
                                    <th>Total Billed</th>
                                    <th>Consult Billed</th>
                                    <th>Consult Payable</th>
                                    <th>Consult Claims</th>
                                    <th>Total Payable</th>
                                    <th>HMO Claims</th>
                                    <th>HMO Share</th>
                                    <th>Avg / Encounter</th>
                                    <th></th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Tab 6: Patient Insights (admin/accounts/own-doctor) --}}
        @if($canViewAnalytics || $isDoctor)
        <div class="tab-pane fade" id="ewb-pane-patients" role="tabpanel">
            <div class="row mb-3" id="ewb-patient-kpis"></div>

            <div class="row mb-3">
                <div class="col-md-7">
                    <div class="card-modern p-3">
                        <h6 class="ewb-chart-title"><i class="mdi mdi-chart-line"></i> New vs Returning (Weekly)</h6>
                        <canvas id="ewb-chart-new-returning" height="200"></canvas>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="card-modern p-3">
                        <h6 class="ewb-chart-title"><i class="mdi mdi-chart-donut"></i> Patient Mix</h6>
                        <canvas id="ewb-chart-patient-donut" height="200"></canvas>
                    </div>
                </div>
            </div>

            {{-- Return Rate windows --}}
            <div class="card-modern mb-3">
                <div class="card-header-modern">
                    <h6 class="mb-0"><i class="mdi mdi-refresh text-primary"></i> Return Rate Windows</h6>
                </div>
                <div class="card-body" id="ewb-return-rate-body">
                    <div class="text-center py-3"><div class="spinner-border spinner-border-sm" role="status"></div></div>
                </div>
            </div>

            {{-- High-frequency patients --}}
            <div class="card-modern mb-3">
                <div class="card-header-modern d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="mdi mdi-account-multiple text-warning"></i> High-Frequency Patients</h6>
                    <div class="d-flex gap-2 align-items-center">
                        <label class="small mb-0 text-muted">Min encounters:</label>
                        <input type="number" class="form-control form-control-sm" id="ewb-min-enc" value="3" min="2" max="50" style="width:70px;">
                        <button class="btn btn-sm btn-outline-primary" id="ewb-btn-hf-reload"><i class="mdi mdi-magnify"></i></button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="ewb-table-hf-patients" class="table table-sm w-100">
                            <thead class="ewb-thead"><tr><th>Patient</th><th>HMO</th><th>Encounters</th><th>First Visit</th><th>Last Visit</th></tr></thead>
                            <tbody id="ewb-hf-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Referral Outcomes --}}
            <div class="card-modern">
                <div class="card-header-modern">
                    <h6 class="mb-0"><i class="mdi mdi-share-variant text-info"></i> Referral Outcomes</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-5">
                            <canvas id="ewb-chart-referrals" height="220"></canvas>
                        </div>
                        <div class="col-md-7">
                            <table id="ewb-table-referrals" class="table table-sm w-100">
                                <thead class="ewb-thead"><tr><th>Status</th><th>Type</th><th>Total</th></tr></thead>
                                <tbody id="ewb-referral-tbody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

    </div>{{-- /tab-content --}}

</div>{{-- /ewb-container --}}

{{-- Modals --}}
@include('admin.encounters.partials._modals')
@endsection

@section('scripts')
@include('admin.encounters.partials._scripts')
@endsection
