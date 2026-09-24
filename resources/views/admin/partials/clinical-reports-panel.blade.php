{{-- Clinical Reports Panel Partial --}}
{{-- Included inside #reports-tab-content in the reception workbench --}}
<div class="tab-pane fade" id="clinical-reports-content" role="tabpanel" aria-labelledby="clinical-reports-tab">
    <div class="card-modern border-0 mt-1">
        <div class="card-body p-2">

            {{-- ================================================================
                 CR FILTER BAR  (all IDs prefixed with cr- to avoid conflicts)
                 ================================================================ --}}
            <div class="row align-items-end mb-2" id="cr-filter-bar">
                <div class="form-group col-md-2 mb-1">
                    <label for="cr-date-from" class="small mb-0">Date From</label>
                    <input type="date" class="form-control form-control-sm" id="cr-date-from">
                </div>
                <div class="form-group col-md-2 mb-1">
                    <label for="cr-date-to" class="small mb-0">Date To</label>
                    <input type="date" class="form-control form-control-sm" id="cr-date-to">
                </div>
                <div class="form-group col-md-2 mb-1">
                    <label class="small mb-0">Quick Range</label>
                    <select class="form-control form-control-sm" id="cr-quick-range">
                        <option value="">Custom</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month" selected>This Month</option>
                        <option value="last_month">Last Month</option>
                        <option value="quarter">This Quarter</option>
                        <option value="year">This Year</option>
                    </select>
                </div>
                <div class="form-group col-md-2 mb-1">
                    <label for="cr-clinic-filter" class="small mb-0">Clinic</label>
                    <select class="form-control form-control-sm" id="cr-clinic-filter">
                        <option value="">All Clinics</option>
                        @foreach(\App\Models\Clinic::where('status', 1)->orderBy('name')->get() as $clinic)
                            <option value="{{ $clinic->id }}">{{ $clinic->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2 mb-1">
                    <label for="cr-hmo-filter" class="small mb-0">HMO</label>
                    <select class="form-control form-control-sm" id="cr-hmo-filter">
                        <option value="">All HMOs</option>
                        @foreach(\App\Models\Hmo::where('status', 1)->orderBy('name')->get() as $hmo)
                            <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2 mb-1">
                    <label for="cr-ward-filter" class="small mb-0">Ward</label>
                    <select class="form-control form-control-sm" id="cr-ward-filter">
                        <option value="">All Wards</option>
                        @foreach(\App\Models\Ward::where('is_active', 1)->orderBy('name')->get() as $ward)
                            <option value="{{ $ward->id }}">{{ $ward->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-12 mb-1 text-right">
                    <button class="btn btn-sm btn-secondary" id="cr-clear-filters"><i class="mdi mdi-refresh"></i> Clear</button>
                    <button class="btn btn-sm btn-primary" id="cr-apply-filters"><i class="mdi mdi-filter"></i> Apply</button>
                    <button class="btn btn-sm btn-success" id="cr-export-btn"><i class="mdi mdi-download"></i> Export</button>
                </div>
            </div>

            {{-- ================================================================
                 CR SUB-TABS NAV
                 ================================================================ --}}
            <ul class="nav nav-tabs nav-tabs-sm border-bottom" id="cr-sub-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="cr-tab-overview" data-bs-toggle="tab" data-toggle="tab" data-bs-target="#cr-overview" href="#cr-overview" role="tab">
                        <i class="mdi mdi-view-dashboard-outline"></i> Overview
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="cr-tab-unit-visits" data-bs-toggle="tab" data-toggle="tab" data-bs-target="#cr-unit-visits" href="#cr-unit-visits" role="tab">
                        <i class="mdi mdi-hospital-building"></i> Unit Visits
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="cr-tab-hmo-trends" data-bs-toggle="tab" data-toggle="tab" data-bs-target="#cr-hmo-trends" href="#cr-hmo-trends" role="tab">
                        <i class="mdi mdi-trending-up"></i> HMO Trends
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="cr-tab-diagnosis" data-bs-toggle="tab" data-toggle="tab" data-bs-target="#cr-diagnosis" href="#cr-diagnosis" role="tab">
                        <i class="mdi mdi-magnify"></i> Diagnosis Search
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="cr-tab-maternity" data-bs-toggle="tab" data-toggle="tab" data-bs-target="#cr-maternity" href="#cr-maternity" role="tab">
                        <i class="mdi mdi-baby-carriage"></i> Maternity
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="cr-tab-mortality" data-bs-toggle="tab" data-toggle="tab" data-bs-target="#cr-mortality" href="#cr-mortality" role="tab">
                        <i class="mdi mdi-pulse"></i> Mortality
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="cr-tab-surgeries" data-bs-toggle="tab" data-toggle="tab" data-bs-target="#cr-surgeries" href="#cr-surgeries" role="tab">
                        <i class="mdi mdi-medical-bag"></i> Surgeries
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="cr-tab-vaccinations" data-bs-toggle="tab" data-toggle="tab" data-bs-target="#cr-vaccinations" href="#cr-vaccinations" role="tab">
                        <i class="mdi mdi-needle"></i> Vaccinations
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="cr-tab-referrals" data-bs-toggle="tab" data-toggle="tab" data-bs-target="#cr-referrals" href="#cr-referrals" role="tab">
                        <i class="mdi mdi-share-variant"></i> Referrals
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="cr-tab-occupancy" data-bs-toggle="tab" data-toggle="tab" data-bs-target="#cr-occupancy" href="#cr-occupancy" role="tab">
                        <i class="mdi mdi-bed"></i> Ward Occupancy
                    </a>
                </li>
            </ul>

            {{-- ================================================================
                 CR SUB-TAB PANES
                 ================================================================ --}}
            <div class="tab-content mt-2" id="cr-sub-tab-content">

                {{-- ---- OVERVIEW ---- --}}
                <div class="tab-pane fade show active" id="cr-overview" role="tabpanel">
                    <div class="row" id="cr-overview-kpis">
                        {{-- KPI cards injected by JS --}}
                        <div class="col-12 text-center py-4">
                            <div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-6">
                            <canvas id="cr-overview-bar-chart" height="200"></canvas>
                        </div>
                        <div class="col-md-6">
                            <canvas id="cr-overview-line-chart" height="200"></canvas>
                        </div>
                    </div>
                </div>

                {{-- ---- UNIT VISITS ---- --}}
                <div class="tab-pane fade" id="cr-unit-visits" role="tabpanel">
                    <div class="row" id="cr-unit-visits-summary">
                        <div class="col-md-5">
                            <canvas id="cr-unit-visits-chart" height="250"></canvas>
                        </div>
                        <div class="col-md-7">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-hover" id="cr-unit-visits-table">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Clinic / Unit</th>
                                            <th class="text-center">Total Visits</th>
                                            <th class="text-center">% Share</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    {{-- Drill-down table (shown when clinic selected) --}}
                    <div id="cr-unit-visits-drilldown" class="mt-3" style="display:none;">
                        <h6 class="small font-weight-bold text-muted border-bottom pb-1">
                            <i class="mdi mdi-arrow-right-circle"></i> Encounter Detail — <span id="cr-unit-visits-drill-label"></span>
                            <button class="btn btn-xs btn-link float-right" id="cr-unit-visits-drill-back"><i class="mdi mdi-arrow-left"></i> Back</button>
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered" id="cr-unit-visits-drill-table">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Patient</th><th>File No</th><th>Date</th><th>Doctor</th><th>HMO</th><th>Status</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- ---- HMO TRENDS ---- --}}
                <div class="tab-pane fade" id="cr-hmo-trends" role="tabpanel">
                    <div class="row">
                        <div class="col-md-8">
                            <canvas id="cr-hmo-trends-chart" height="220"></canvas>
                        </div>
                        <div class="col-md-4">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered" id="cr-hmo-totals-table">
                                    <thead class="thead-light">
                                        <tr><th>HMO</th><th>Encounters</th><th>Patients</th></tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ---- DIAGNOSIS SEARCH ---- --}}
                <div class="tab-pane fade" id="cr-diagnosis" role="tabpanel">
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" id="cr-diagnosis-keyword" placeholder="Search ICD10 code or diagnosis name...">
                                <div class="input-group-append">
                                    <button class="btn btn-primary" id="cr-diagnosis-search-btn"><i class="mdi mdi-magnify"></i> Search</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 small text-muted pt-2" id="cr-diagnosis-result-count"></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover" id="cr-diagnosis-table">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width:28px;"></th>
                                    <th>ICD10 Code</th>
                                    <th>Diagnosis</th>
                                    <th class="text-center">Unique Patients</th>
                                    <th class="text-center">Encounters</th>
                                    <th>Statuses</th>
                                    <th>Query Types</th>
                                </tr>
                            </thead>
                            <tbody id="cr-diagnosis-tbody"></tbody>
                        </table>
                    </div>
                </div>

                {{-- ---- MATERNITY ---- --}}
                <div class="tab-pane fade" id="cr-maternity" role="tabpanel">
                    {{-- Summary KPIs --}}
                    <div class="row mb-2" id="cr-maternity-kpis">
                        <div class="col text-center py-3">
                            <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
                        </div>
                    </div>
                    {{-- Maternity Sub-Sub-Tabs --}}
                    <ul class="nav nav-pills nav-pills-sm mb-2" id="cr-mat-sub-tabs" role="tablist">
                        <li class="nav-item"><a class="nav-link active small" data-bs-toggle="tab" data-toggle="tab" data-bs-target="#cr-mat-enrollments" href="#cr-mat-enrollments">Enrollments</a></li>
                        <li class="nav-item"><a class="nav-link small" data-bs-toggle="tab" data-toggle="tab" data-bs-target="#cr-mat-anc" href="#cr-mat-anc">ANC Visits</a></li>
                        <li class="nav-item"><a class="nav-link small" data-bs-toggle="tab" data-toggle="tab" data-bs-target="#cr-mat-deliveries" href="#cr-mat-deliveries">Deliveries</a></li>
                        <li class="nav-item"><a class="nav-link small" data-bs-toggle="tab" data-toggle="tab" data-bs-target="#cr-mat-babies" href="#cr-mat-babies">Babies</a></li>
                        <li class="nav-item"><a class="nav-link small" data-bs-toggle="tab" data-toggle="tab" data-bs-target="#cr-mat-postnatal" href="#cr-mat-postnatal">Postnatal</a></li>
                    </ul>
                    <div class="tab-content" id="cr-mat-sub-content">
                        <div class="tab-pane fade show active" id="cr-mat-enrollments" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered" id="cr-mat-enrollments-table">
                                    <thead class="thead-light"><tr><th>Patient</th><th>File No</th><th>Enrolled</th><th>EDD</th><th>Risk</th><th>Status</th></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="cr-mat-anc" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered" id="cr-mat-anc-table">
                                    <thead class="thead-light"><tr><th>Patient</th><th>File No</th><th>Visit Date</th><th>Weight</th><th>BP</th><th>Fundal Ht</th><th>Gest. Age</th></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="cr-mat-deliveries" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered" id="cr-mat-deliveries-table">
                                    <thead class="thead-light"><tr><th>Mother</th><th>File No</th><th>Date</th><th>Type</th><th>Babies</th><th>Blood Loss</th><th>Complications</th></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="cr-mat-babies" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered" id="cr-mat-babies-table">
                                    <thead class="thead-light"><tr><th>Mother</th><th>Baby</th><th>Sex</th><th>Weight (kg)</th><th>Stillbirth</th><th>Status</th><th>Cause of Death</th></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="cr-mat-postnatal" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered" id="cr-mat-postnatal-table">
                                    <thead class="thead-light"><tr><th>Patient</th><th>Visit Date</th><th>Mother Condition</th><th>Baby Condition</th></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ---- MORTALITY ---- --}}
                <div class="tab-pane fade" id="cr-mortality" role="tabpanel">
                    <div class="row mb-2" id="cr-mortality-kpis"></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered" id="cr-mortality-table">
                            <thead class="thead-light">
                                <tr><th>Patient</th><th>File No</th><th>Age</th><th>Sex</th><th>Date</th><th>Type</th><th>Primary Cause</th><th>Contributing Factors</th></tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                {{-- ---- SURGERIES / PROCEDURES ---- --}}
                <div class="tab-pane fade" id="cr-surgeries" role="tabpanel">
                    <div class="row mb-2">
                        <div class="col-md-4">
                            <canvas id="cr-surgeries-donut" height="220"></canvas>
                        </div>
                        <div class="col-md-8">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered" id="cr-surgeries-table">
                                    <thead class="thead-light">
                                        <tr><th>Patient</th><th>File No</th><th>Date</th><th>Procedure</th><th>Category</th><th>Doctor</th><th>Outcome</th></tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ---- VACCINATIONS ---- --}}
                <div class="tab-pane fade" id="cr-vaccinations" role="tabpanel">
                    <div class="row mb-2" id="cr-vacc-schedule-stats">
                        {{-- Schedule status badges injected by JS --}}
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-4">
                            <h6 class="small font-weight-bold text-muted">Doses by Vaccine</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered" id="cr-vacc-summary-table">
                                    <thead class="thead-light"><tr><th>Vaccine</th><th class="text-center">Doses</th><th class="text-center">Patients</th></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <h6 class="small font-weight-bold text-muted">Administered Records</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered" id="cr-vacc-records-table">
                                    <thead class="thead-light"><tr><th>Patient</th><th>File No</th><th>Vaccine</th><th>Dose</th><th>Route</th><th>Date</th><th>Nurse</th><th>Next Due</th></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ---- REFERRALS ---- --}}
                <div class="tab-pane fade" id="cr-referrals" role="tabpanel">
                    <div class="row mb-2" id="cr-referrals-kpis"></div>
                    <div class="row mb-2">
                        <div class="col-md-4">
                            <canvas id="cr-referrals-donut" height="200"></canvas>
                        </div>
                        <div class="col-md-8">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered" id="cr-referrals-table">
                                    <thead class="thead-light">
                                        <tr><th>Patient</th><th>File No</th><th>Type</th><th>From Doctor</th><th>To</th><th>Reason</th><th>Urgency</th><th>Status</th><th>Booked</th><th>Date</th></tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ---- WARD OCCUPANCY ---- --}}
                <div class="tab-pane fade" id="cr-occupancy" role="tabpanel">
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <canvas id="cr-occupancy-bar" height="230"></canvas>
                        </div>
                        <div class="col-md-6">
                            <div id="cr-occupancy-avg-los" class="alert alert-info p-2 small mb-2"></div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered" id="cr-occupancy-table">
                                    <thead class="thead-light">
                                        <tr><th>Ward</th><th>Type</th><th>Capacity</th><th>Occupied</th><th>Available</th><th class="text-center">Occupancy %</th></tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    {{-- Current patients list --}}
                    <div id="cr-occupancy-patients-section" class="mt-2" style="display:none;">
                        <h6 class="small font-weight-bold text-muted border-bottom pb-1">
                            Current Inpatients — <span id="cr-occupancy-ward-label"></span>
                            <button class="btn btn-xs btn-link float-right" id="cr-occupancy-patients-close"><i class="mdi mdi-close"></i></button>
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered" id="cr-occupancy-patients-table">
                                <thead class="thead-light"><tr><th>Patient</th><th>File No</th><th>Ward</th><th>Bed</th><th>Admitted</th><th class="text-center">Days</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>{{-- /#cr-sub-tab-content --}}
        </div>{{-- /.card-body --}}
    </div>{{-- /.card --}}
</div>{{-- /#clinical-reports-content --}}

{{-- Encounter Details Modal --}}
{{-- Encounter Details Modal --}}
<div class="modal fade" id="crEncounterDetailModal" tabindex="-1" role="dialog" aria-labelledby="crEncounterDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light border-bottom">
                <div>
                    <h5 class="modal-title font-weight-bold mb-0 text-primary" id="crEncounterDetailModalLabel">
                        <i class="mdi mdi-clipboard-text-outline me-1"></i> <span id="cr-enc-title">Encounter Details</span>
                    </h5>
                    <small class="text-muted" id="cr-enc-subtitle"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                {{-- Patient & Encounter Info Banner --}}
                <div class="card mb-3 border-0 bg-light shadow-sm" id="cr-enc-banner">
                    <div class="card-body p-3">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-3">
                                <small class="text-muted text-uppercase d-block font-weight-bold" style="font-size: 11px;">Patient</small>
                                <span class="font-weight-bold text-dark" id="cr-enc-patient-name">—</span>
                                <div class="small text-muted">File: <span class="badge bg-secondary text-white" id="cr-enc-file-no">—</span></div>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted text-uppercase d-block font-weight-bold" style="font-size: 11px;">Doctor</small>
                                <span class="text-dark" id="cr-enc-doctor-name">—</span>
                                <div class="small text-muted"><span id="cr-enc-clinic-name">—</span></div>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted text-uppercase d-block font-weight-bold" style="font-size: 11px;">Date & Coverage</small>
                                <span class="text-dark" id="cr-enc-date">—</span>
                                <div class="small text-muted"><span class="badge bg-info text-white" id="cr-enc-hmo-name">—</span></div>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted text-uppercase d-block font-weight-bold" style="font-size: 11px;">Diagnosis / Reason</small>
                                <div class="text-dark small font-weight-bold text-truncate" id="cr-enc-reasons" title="">—</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tab navigation for detailed breakdown --}}
                <ul class="nav nav-tabs nav-tabs-sm border-bottom mb-3" id="cr-enc-modal-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active font-weight-bold" id="cr-enc-tab-notes-btn" data-bs-toggle="tab" data-toggle="tab" data-bs-target="#cr-enc-tab-notes" data-target="#cr-enc-tab-notes" type="button" role="tab" aria-selected="true">
                            <i class="mdi mdi-text-box-outline me-1"></i> Clinical Notes
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link font-weight-bold" id="cr-enc-tab-rx-btn" data-bs-toggle="tab" data-toggle="tab" data-bs-target="#cr-enc-tab-rx" data-target="#cr-enc-tab-rx" type="button" role="tab" aria-selected="false">
                            <i class="mdi mdi-pill me-1"></i> Prescriptions <span class="badge rounded-pill bg-primary text-white ms-1" id="cr-enc-rx-count">0</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link font-weight-bold" id="cr-enc-tab-labs-btn" data-bs-toggle="tab" data-toggle="tab" data-bs-target="#cr-enc-tab-labs" data-target="#cr-enc-tab-labs" type="button" role="tab" aria-selected="false">
                            <i class="mdi mdi-flask-outline me-1"></i> Laboratory <span class="badge rounded-pill bg-info text-white ms-1" id="cr-enc-labs-count">0</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link font-weight-bold" id="cr-enc-tab-img-btn" data-bs-toggle="tab" data-toggle="tab" data-bs-target="#cr-enc-tab-img" data-target="#cr-enc-tab-img" type="button" role="tab" aria-selected="false">
                            <i class="mdi mdi-radiology-box-outline me-1"></i> Imaging <span class="badge rounded-pill text-white ms-1" id="cr-enc-img-count" style="background-color: #6f42c1;">0</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link font-weight-bold" id="cr-enc-tab-proc-btn" data-bs-toggle="tab" data-toggle="tab" data-bs-target="#cr-enc-tab-proc" data-target="#cr-enc-tab-proc" type="button" role="tab" aria-selected="false">
                            <i class="mdi mdi-needle me-1"></i> Procedures <span class="badge rounded-pill bg-success text-white ms-1" id="cr-enc-proc-count">0</span>
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="cr-enc-modal-tab-content">
                    {{-- Tab 1: Clinical Notes --}}
                    <div class="tab-pane fade show active" id="cr-enc-tab-notes" role="tabpanel">
                        <div id="cr-enc-notes" class="p-3 bg-white rounded border" style="min-height: 180px; max-height: 400px; overflow-y: auto; line-height: 1.6;">
                            <div class="text-center p-3"><div class="spinner-border spinner-border-sm text-primary"></div></div>
                        </div>
                    </div>

                    {{-- Tab 2: Prescriptions --}}
                    <div class="tab-pane fade" id="cr-enc-tab-rx" role="tabpanel">
                        <div class="table-responsive border rounded">
                            <table class="table table-sm table-hover table-striped mb-0" id="cr-enc-prescriptions">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40px;" class="text-center">#</th>
                                        <th>Medication</th>
                                        <th>Dose / Instruction</th>
                                        <th style="width: 80px;" class="text-center">Qty</th>
                                        <th style="width: 140px;" class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Tab 3: Laboratory Orders --}}
                    <div class="tab-pane fade" id="cr-enc-tab-labs" role="tabpanel">
                        <div class="table-responsive border rounded">
                            <table class="table table-sm table-hover table-striped mb-0" id="cr-enc-labs">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40px;" class="text-center">#</th>
                                        <th>Investigation / Test</th>
                                        <th style="width: 150px;" class="text-center">Status</th>
                                        <th>Result / Findings</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Tab 4: Imaging Orders --}}
                    <div class="tab-pane fade" id="cr-enc-tab-img" role="tabpanel">
                        <div class="table-responsive border rounded">
                            <table class="table table-sm table-hover table-striped mb-0" id="cr-enc-imaging">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40px;" class="text-center">#</th>
                                        <th>Investigation / Scan</th>
                                        <th style="width: 150px;" class="text-center">Status</th>
                                        <th>Result / Findings</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Tab 5: Procedures --}}
                    <div class="tab-pane fade" id="cr-enc-tab-proc" role="tabpanel">
                        <div class="table-responsive border rounded">
                            <table class="table table-sm table-hover table-striped mb-0" id="cr-enc-procedures">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40px;" class="text-center">#</th>
                                        <th>Procedure</th>
                                        <th style="width: 140px;" class="text-center">Status</th>
                                        <th>Outcome / Clinical Notes</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
