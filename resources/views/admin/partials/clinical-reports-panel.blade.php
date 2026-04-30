{{-- Clinical Reports Panel Partial --}}
{{-- Included inside #reports-tab-content in the reception workbench --}}
<div class="tab-pane fade" id="clinical-reports-content" role="tabpanel" aria-labelledby="clinical-reports-tab">
    <div class="card border-0 mt-1">
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
                    <a class="nav-link active" id="cr-tab-overview" data-toggle="tab" href="#cr-overview" role="tab">
                        <i class="mdi mdi-view-dashboard-outline"></i> Overview
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="cr-tab-unit-visits" data-toggle="tab" href="#cr-unit-visits" role="tab">
                        <i class="mdi mdi-hospital-building"></i> Unit Visits
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="cr-tab-hmo-trends" data-toggle="tab" href="#cr-hmo-trends" role="tab">
                        <i class="mdi mdi-trending-up"></i> HMO Trends
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="cr-tab-diagnosis" data-toggle="tab" href="#cr-diagnosis" role="tab">
                        <i class="mdi mdi-magnify"></i> Diagnosis Search
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="cr-tab-maternity" data-toggle="tab" href="#cr-maternity" role="tab">
                        <i class="mdi mdi-baby-carriage"></i> Maternity
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="cr-tab-mortality" data-toggle="tab" href="#cr-mortality" role="tab">
                        <i class="mdi mdi-pulse"></i> Mortality
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="cr-tab-surgeries" data-toggle="tab" href="#cr-surgeries" role="tab">
                        <i class="mdi mdi-medical-bag"></i> Surgeries
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="cr-tab-vaccinations" data-toggle="tab" href="#cr-vaccinations" role="tab">
                        <i class="mdi mdi-needle"></i> Vaccinations
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="cr-tab-referrals" data-toggle="tab" href="#cr-referrals" role="tab">
                        <i class="mdi mdi-share-variant"></i> Referrals
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="cr-tab-occupancy" data-toggle="tab" href="#cr-occupancy" role="tab">
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
                        <li class="nav-item"><a class="nav-link active small" data-toggle="tab" href="#cr-mat-enrollments">Enrollments</a></li>
                        <li class="nav-item"><a class="nav-link small" data-toggle="tab" href="#cr-mat-anc">ANC Visits</a></li>
                        <li class="nav-item"><a class="nav-link small" data-toggle="tab" href="#cr-mat-deliveries">Deliveries</a></li>
                        <li class="nav-item"><a class="nav-link small" data-toggle="tab" href="#cr-mat-babies">Babies</a></li>
                        <li class="nav-item"><a class="nav-link small" data-toggle="tab" href="#cr-mat-postnatal">Postnatal</a></li>
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
