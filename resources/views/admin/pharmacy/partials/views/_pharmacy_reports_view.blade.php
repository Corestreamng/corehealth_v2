<div class="queue-view" id="pharmacy-reports-view">
            <div class="queue-view-header">
                <h4><i class="mdi mdi-chart-box"></i> Pharmacy Reports & Analytics</h4>
                <div class="reports-header-actions">
                    <button class="btn btn-sm btn-outline-success" id="export-reports-excel" title="Export to Excel">
                        <i class="mdi mdi-file-excel"></i> Excel
                    </button>
                    <button class="btn btn-sm btn-outline-danger" id="export-reports-pdf" title="Export to PDF">
                        <i class="mdi mdi-file-pdf-box"></i> PDF
                    </button>
                    <button class="btn btn-sm btn-outline-info" id="print-reports" title="Print">
                        <i class="mdi mdi-printer"></i> Print
                    </button>
                    <button class="btn btn-secondary btn-close-queue" id="btn-close-pharmacy-reports">
                        <i class="mdi mdi-close"></i> Close
                    </button>
                </div>
            </div>
            <div class="queue-view-content" style="padding: 1.5rem; overflow-y: auto; max-height: calc(100vh - 180px);">

                <!-- Quick Date Presets -->
                <div class="date-presets-bar mb-3">
                    <span class="text-muted me-2">Quick Filters:</span>
                    <button class="btn btn-sm btn-outline-primary date-preset-btn active" data-preset="today">Today</button>
                    <button class="btn btn-sm btn-outline-primary date-preset-btn" data-preset="yesterday">Yesterday</button>
                    <button class="btn btn-sm btn-outline-primary date-preset-btn" data-preset="week">This Week</button>
                    <button class="btn btn-sm btn-outline-primary date-preset-btn" data-preset="month">This Month</button>
                    <button class="btn btn-sm btn-outline-primary date-preset-btn" data-preset="quarter">This Quarter</button>
                    <button class="btn btn-sm btn-outline-primary date-preset-btn" data-preset="year">This Year</button>
                    <button class="btn btn-sm btn-outline-secondary date-preset-btn" data-preset="all">All Time</button>
                </div>

                <!-- Advanced Filters Panel (Collapsible) -->
                <div class="card-modern mb-4" id="pharmacy-reports-filter-card">
                    <div class="card-header d-flex justify-content-between align-items-center py-2" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#pharmacy-reports-filters">
                        <h6 class="mb-0"><i class="mdi mdi-filter-variant"></i> Advanced Filters</h6>
                        <i class="mdi mdi-chevron-down filter-collapse-icon"></i>
                    </div>
                    <div class="collapse" id="pharmacy-reports-filters">
                        <div class="card-body">
                            <form id="pharmacy-reports-filter-form">
                                <div class="row g-3">
                                    <div class="col-md-2">
                                        <label class="form-label small"><i class="mdi mdi-calendar"></i> Date From</label>
                                        <input type="date" class="form-control form-control-sm" id="pharm-report-date-from" name="date_from">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small"><i class="mdi mdi-calendar"></i> Date To</label>
                                        <input type="date" class="form-control form-control-sm" id="pharm-report-date-to" name="date_to">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small"><i class="mdi mdi-filter-variant"></i> Status</label>
                                        <select class="form-control form-control-sm" id="pharm-report-status" name="status">
                                            <option value="">All Statuses</option>
                                            <option value="1">Unbilled</option>
                                            <option value="2">Billed/Pending</option>
                                            <option value="3">Dispensed</option>
                                            <option value="0">Dismissed</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small"><i class="mdi mdi-store"></i> Store</label>
                                        <select class="form-control form-control-sm" id="pharm-report-store" name="store_id">
                                            <option value="">All Stores</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small"><i class="mdi mdi-cash"></i> Payment Type</label>
                                        <select class="form-control form-control-sm" id="pharm-report-payment-type" name="payment_type">
                                            <option value="">All Types</option>
                                            <option value="CASH">Cash</option>
                                            <option value="CARD">Card</option>
                                            <option value="TRANSFER">Transfer</option>
                                            <option value="HMO">HMO</option>
                                            <option value="ACCOUNT">Account Balance</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small"><i class="mdi mdi-hospital-building"></i> HMO</label>
                                        <select class="form-control form-control-sm" id="pharm-report-hmo" name="hmo_id">
                                            <option value="">All HMOs</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row g-3 mt-2">
                                    <div class="col-md-2">
                                        <label class="form-label small"><i class="mdi mdi-doctor"></i> Doctor</label>
                                        <select class="form-control form-control-sm" id="pharm-report-doctor" name="doctor_id">
                                            <option value="">All Doctors</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small"><i class="mdi mdi-account-tie"></i> Pharmacist</label>
                                        <select class="form-control form-control-sm" id="pharm-report-pharmacist" name="pharmacist_id">
                                            <option value="">All Pharmacists</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small"><i class="mdi mdi-shape"></i> Category</label>
                                        <select class="form-control form-control-sm" id="pharm-report-category" name="category_id">
                                            <option value="">All Categories</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small"><i class="mdi mdi-account-search"></i> Patient</label>
                                        <input type="text" class="form-control form-control-sm" id="pharm-report-patient" name="patient_search" placeholder="Name or File No...">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small"><i class="mdi mdi-cash-minus"></i> Min Amount</label>
                                        <input type="number" class="form-control form-control-sm" id="pharm-report-min-amount" name="min_amount" placeholder="0">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small"><i class="mdi mdi-cash-plus"></i> Max Amount</label>
                                        <input type="number" class="form-control form-control-sm" id="pharm-report-max-amount" name="max_amount" placeholder="999999">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small"><i class="mdi mdi-account-group"></i> Age Brackets</label>
                                        <input type="text" class="form-control form-control-sm" id="pharm-report-age-brackets" name="age_brackets" placeholder="e.g. 0-12,13-19,20-35" title="Comma separated ranges: 0-12,13-19...">
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12 text-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="clear-pharmacy-report-filters">
                                            <i class="mdi mdi-refresh"></i> Clear All
                                        </button>
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <i class="mdi mdi-filter"></i> Apply Filters
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Summary Statistics Cards -->
                <div class="row g-3 mb-4" id="pharmacy-stats-row">
                    <div class="col-6 col-md-3 col-lg-2">
                        <div class="stat-card-mini" style="border-left: 4px solid #667eea;">
                            <div class="stat-icon-mini" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="mdi mdi-pill"></i>
                            </div>
                            <div class="stat-content-mini">
                                <h4 id="pharm-stat-dispensed">0</h4>
                                <small>Dispensed</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 col-lg-2">
                        <div class="stat-card-mini" style="border-left: 4px solid #28a745;">
                            <div class="stat-icon-mini" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                                <i class="mdi mdi-cash-multiple"></i>
                            </div>
                            <div class="stat-content-mini">
                                <h4 id="pharm-stat-revenue">₦0</h4>
                                <small>Total Revenue</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 col-lg-2">
                        <div class="stat-card-mini" style="border-left: 4px solid #17a2b8;">
                            <div class="stat-icon-mini" style="background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);">
                                <i class="mdi mdi-cash"></i>
                            </div>
                            <div class="stat-content-mini">
                                <h4 id="pharm-stat-cash">₦0</h4>
                                <small>Cash Sales</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 col-lg-2">
                        <div class="stat-card-mini" style="border-left: 4px solid #fd7e14;">
                            <div class="stat-icon-mini" style="background: linear-gradient(135deg, #fd7e14 0%, #e83e8c 100%);">
                                <i class="mdi mdi-hospital-building"></i>
                            </div>
                            <div class="stat-content-mini">
                                <h4 id="pharm-stat-hmo">₦0</h4>
                                <small>HMO Claims</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 col-lg-2">
                        <div class="stat-card-mini" style="border-left: 4px solid #6f42c1;">
                            <div class="stat-icon-mini" style="background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);">
                                <i class="mdi mdi-account-group"></i>
                            </div>
                            <div class="stat-content-mini">
                                <h4 id="pharm-stat-patients">0</h4>
                                <small>Patients</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 col-lg-2">
                        <div class="stat-card-mini" style="border-left: 4px solid #dc3545;">
                            <div class="stat-icon-mini" style="background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);">
                                <i class="mdi mdi-clock-alert"></i>
                            </div>
                            <div class="stat-content-mini">
                                <h4 id="pharm-stat-pending">0</h4>
                                <small>Pending</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Report Tabs -->
                <ul class="nav nav-tabs nav-fill mb-3" id="pharmacy-report-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pharm-executive-tab" data-bs-toggle="tab" data-bs-target="#pharm-executive-content" type="button" role="tab">
                            <i class="mdi mdi-chart-box"></i> Executive Summary
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pharm-overview-tab" data-bs-toggle="tab" data-bs-target="#pharm-overview-content" type="button" role="tab">
                            <i class="mdi mdi-view-dashboard"></i> Overview
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pharm-aggregate-tab" data-bs-toggle="tab" data-bs-target="#pharm-aggregate-content" type="button" role="tab">
                            <i class="mdi mdi-chart-donut"></i> Aggregate Summaries
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pharm-dispensing-tab" data-bs-toggle="tab" data-bs-target="#pharm-dispensing-content" type="button" role="tab">
                            <i class="mdi mdi-pill"></i> Dispensing
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pharm-revenue-tab" data-bs-toggle="tab" data-bs-target="#pharm-revenue-content" type="button" role="tab">
                            <i class="mdi mdi-cash-register"></i> Revenue
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pharm-stock-tab" data-bs-toggle="tab" data-bs-target="#pharm-stock-content" type="button" role="tab">
                            <i class="mdi mdi-package-variant"></i> Stock
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pharm-performance-tab" data-bs-toggle="tab" data-bs-target="#pharm-performance-content" type="button" role="tab">
                            <i class="mdi mdi-account-tie"></i> Performance
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pharm-hmo-tab" data-bs-toggle="tab" data-bs-target="#pharm-hmo-content" type="button" role="tab">
                            <i class="mdi mdi-hospital-building"></i> HMO Claims
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="pharmacy-report-tab-content">
                    
                    <!-- Executive Summary Tab -->
                    <div class="tab-pane fade" id="pharm-executive-content" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0 text-primary"><i class="mdi mdi-chart-box"></i> Executive Summary</h5>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-secondary me-2 d-none" id="btn-print-executive-summary"><i class="mdi mdi-printer"></i> Print Detailed Report</button>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-refresh-executive-summary"><i class="mdi mdi-refresh"></i> Refresh Data</button>
                            </div>
                        </div>

                        <!-- Sub Navigation for Executive Summary -->
                        <ul class="nav nav-pills mb-3 bg-light p-1 rounded" id="exec-sub-tabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="exec-summary-sub-tab" data-bs-toggle="pill" data-bs-target="#exec-summary-sub-content" type="button" role="tab">Summary</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="exec-detailed-sub-tab" data-bs-toggle="pill" data-bs-target="#exec-detailed-sub-content" type="button" role="tab">Detailed Drill-Down</button>
                            </li>
                        </ul>

                        <div id="executive-summary-loader" class="text-center py-5 d-none">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 text-muted">Aggregating executive summary data...</p>
                        </div>
                        
                        <div id="executive-summary-container" class="tab-content">
                            <!-- Summary Sub-Tab Content (Original implementation) -->
                            <div class="tab-pane fade show active" id="exec-summary-sub-content" role="tabpanel">
                            <!-- Stock Valuation & Overall Numbers -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <div class="card-modern h-100 bg-light border-primary">
                                        <div class="card-body text-center">
                                            <h6 class="text-muted text-uppercase mb-2">Total Stock Valuation</h6>
                                            <h3 class="text-primary fw-bold mb-0" id="exec-stock-value">₦0.00</h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card-modern h-100 bg-light border-success">
                                        <div class="card-body text-center">
                                            <h6 class="text-muted text-uppercase mb-2">Total Collections</h6>
                                            <h3 class="text-success fw-bold mb-0" id="exec-collections-value">₦0.00</h3>
                                            <small class="text-muted" id="exec-collections-count">0 items</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card-modern h-100 bg-light border-info">
                                        <div class="card-body text-center">
                                            <h6 class="text-muted text-uppercase mb-2">Total Patients Attended</h6>
                                            <h3 class="text-info fw-bold mb-0" id="exec-patients-count">0</h3>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-4">
                                <!-- Demographics (Gender & Age) -->
                                <div class="col-md-6">
                                    <div class="card-modern h-100">
                                        <div class="card-header py-2 bg-light">
                                            <h6 class="mb-0"><i class="mdi mdi-account-group"></i> Demographics</h6>
                                        </div>
                                        <div class="card-body">
                                            <ul class="nav nav-pills nav-sm mb-3" id="exec-demographics-tabs" role="tablist">
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#exec-gender-content" type="button" role="tab">Gender</button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#exec-age-content" type="button" role="tab">Age Brackets</button>
                                                </li>
                                            </ul>
                                            <div class="tab-content">
                                                <div class="tab-pane fade show active" id="exec-gender-content" role="tabpanel">
                                                    <div class="accordion accordion-flush" id="accordion-exec-gender"></div>
                                                </div>
                                                <div class="tab-pane fade" id="exec-age-content" role="tabpanel">
                                                    <div class="accordion accordion-flush" id="accordion-exec-age"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Classifications & Clinics -->
                                <div class="col-md-6">
                                    <div class="card-modern h-100">
                                        <div class="card-header py-2 bg-light">
                                            <h6 class="mb-0"><i class="mdi mdi-hospital-building"></i> Classifications</h6>
                                        </div>
                                        <div class="card-body">
                                            <ul class="nav nav-pills nav-sm mb-3" id="exec-class-tabs" role="tablist">
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#exec-patient-class-content" type="button" role="tab">Visit Type</button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#exec-clinics-content" type="button" role="tab">Clinics</button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#exec-collections-store-content" type="button" role="tab">Collections by Store</button>
                                                </li>
                                            </ul>
                                            <div class="tab-content">
                                                <div class="tab-pane fade show active" id="exec-patient-class-content" role="tabpanel">
                                                    <div class="accordion accordion-flush" id="accordion-exec-class"></div>
                                                </div>
                                                <div class="tab-pane fade" id="exec-clinics-content" role="tabpanel">
                                                    <ul class="list-group list-group-flush" id="exec-clinics-list"></ul>
                                                </div>
                                                <div class="tab-pane fade" id="exec-collections-store-content" role="tabpanel">
                                                    <ul class="list-group list-group-flush" id="exec-collections-list"></ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Detailed Drill-Down Sub-Tab Content -->
                            <div class="tab-pane fade" id="exec-detailed-sub-content" role="tabpanel">
                                <div class="row g-4 mb-4">
                                    <div class="col-12">
                                        <div class="card-modern border-success">
                                            <div class="card-header py-3 bg-light">
                                                <h6 class="mb-0 text-success"><i class="mdi mdi-cash-register"></i> Financial Performance Summary</h6>
                                            </div>
                                            <div class="card-body p-0">
                                                <table class="table table-bordered mb-0">
                                                    <tbody>
                                                        <tr>
                                                            <td class="bg-light fw-bold w-50">Opening Stock</td>
                                                            <td class="text-end fw-bold" id="exec-det-opening-stock">₦0.00</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="bg-light fw-bold">Purchases (Expenditure)</td>
                                                            <td class="text-end text-danger fw-bold" id="exec-det-purchases">₦0.00</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="bg-light fw-bold">Goods Available</td>
                                                            <td class="text-end text-primary fw-bold" id="exec-det-goods-available">₦0.00</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="bg-light fw-bold">Goods Used (Income/Sales)</td>
                                                            <td class="text-end text-success fw-bold" id="exec-det-goods-used">₦0.00</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="bg-light fw-bold">Closing Stock</td>
                                                            <td class="text-end fw-bold" id="exec-det-closing-stock">₦0.00</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card-modern border-warning">
                                            <div class="card-header py-3 bg-light">
                                                <h6 class="mb-0 text-warning"><i class="mdi mdi-wallet"></i> Income by Scheme</h6>
                                            </div>
                                            <div class="card-body p-0">
                                                <ul class="list-group list-group-flush" id="exec-det-income-scheme-list"></ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card-modern border-secondary">
                                            <div class="card-header py-3 bg-light">
                                                <h6 class="mb-0 text-secondary"><i class="mdi mdi-account-group"></i> Total Patients Attended by Scheme</h6>
                                            </div>
                                            <div class="card-body p-0">
                                                <ul class="list-group list-group-flush" id="exec-det-patients-scheme-list"></ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row g-4">
                                    <div class="col-12">
                                        <div class="card-modern border-primary">
                                            <div class="card-header py-3 bg-light d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0 text-primary"><i class="mdi mdi-cash-multiple"></i> Financial Breakdowns (Store &rarr; Scheme &rarr; HMO)</h6>
                                            </div>
                                            <div class="card-body p-0">
                                                <div id="exec-detailed-financials" class="p-3"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="card-modern border-info">
                                            <div class="card-header py-3 bg-light">
                                                <h6 class="mb-0 text-info"><i class="mdi mdi-account-group"></i> Demographic Breakdowns (Category &rarr; Scheme &rarr; HMO)</h6>
                                            </div>
                                            <div class="card-body p-0">
                                                <ul class="nav nav-tabs nav-fill bg-light m-0 border-bottom" id="exec-detailed-demo-tabs" role="tablist">
                                                    <li class="nav-item" role="presentation">
                                                        <button class="nav-link active py-3 border-0 fw-bold" data-bs-toggle="tab" data-bs-target="#exec-det-gender-content" type="button" role="tab">Gender</button>
                                                    </li>
                                                    <li class="nav-item" role="presentation">
                                                        <button class="nav-link py-3 border-0 fw-bold" data-bs-toggle="tab" data-bs-target="#exec-det-age-content" type="button" role="tab">Age Brackets</button>
                                                    </li>
                                                    <li class="nav-item" role="presentation">
                                                        <button class="nav-link py-3 border-0 fw-bold" data-bs-toggle="tab" data-bs-target="#exec-det-class-content" type="button" role="tab">Visit Type</button>
                                                    </li>
                                                </ul>
                                                <div class="tab-content p-3">
                                                    <div class="tab-pane fade show active" id="exec-det-gender-content" role="tabpanel">
                                                        <div id="detailed-gender-container"></div>
                                                    </div>
                                                    <div class="tab-pane fade" id="exec-det-age-content" role="tabpanel">
                                                        <div id="detailed-age-container"></div>
                                                    </div>
                                                    <div class="tab-pane fade" id="exec-det-class-content" role="tabpanel">
                                                        <div id="detailed-class-container"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="pharm-overview-content" role="tabpanel">
                        <div class="row g-4">
                            <!-- Dispensing Trend Chart -->
                            <div class="col-md-8">
                                <div class="card-modern h-100">
                                    <div class="card-header py-2">
                                        <h6 class="mb-0"><i class="mdi mdi-chart-line"></i> Dispensing Trends</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="pharm-trend-chart" height="250"></canvas>
                                    </div>
                                </div>
                            </div>
                            <!-- Revenue Breakdown -->
                            <div class="col-md-4">
                                <div class="card-modern h-100">
                                    <div class="card-header py-2">
                                        <h6 class="mb-0"><i class="mdi mdi-chart-pie"></i> Revenue Breakdown</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="pharm-revenue-pie" height="250"></canvas>
                                    </div>
                                </div>
                            </div>
                            <!-- Top Products -->
                            <div class="col-md-6">
                                <div class="card-modern">
                                    <div class="card-header py-2">
                                        <h6 class="mb-0"><i class="mdi mdi-star"></i> Top 10 Products</h6>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive" style="max-height: 300px;">
                                            <table class="table table-sm table-hover mb-0">
                                                <thead class="table-light sticky-top">
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Product</th>
                                                        <th class="text-center">Qty</th>
                                                        <th class="text-end">Revenue</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="pharm-top-products-tbody">
                                                    <tr><td colspan="4" class="text-center text-muted py-3">Loading...</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Payment Methods -->
                            <div class="col-md-6">
                                <div class="card-modern">
                                    <div class="card-header py-2">
                                        <h6 class="mb-0"><i class="mdi mdi-credit-card-multiple"></i> Payment Methods</h6>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive" style="max-height: 300px;">
                                            <table class="table table-sm table-hover mb-0">
                                                <thead class="table-light sticky-top">
                                                    <tr>
                                                        <th>Method</th>
                                                        <th class="text-center">Transactions</th>
                                                        <th class="text-end">Amount</th>
                                                        <th class="text-end">%</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="pharm-payment-methods-tbody">
                                                    <tr><td colspan="4" class="text-center text-muted py-3">Loading...</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Aggregate Summaries Tab -->
                    <div class="tab-pane fade" id="pharm-aggregate-content" role="tabpanel">
                        <div class="card mb-4 border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h6 class="mb-0"><i class="mdi mdi-chart-donut"></i> Dispense & Requisition Summary</h6>
                            </div>
                            <div class="card-body">
                                <ul class="nav nav-pills nav-justified mb-3">
                                    <li class="nav-item"><a class="nav-link active" data-toggle="pill" href="#pharm-sr-given">Stock Given Out (Dispensed/Transferred)</a></li>
                                    <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#pharm-sr-received">Stock Received</a></li>
                                </ul>
                                <div class="tab-content border rounded p-3 bg-light">
                                    <div class="tab-pane fade show active" id="pharm-sr-given">
                                        @include('admin.inventory.components.summary-report-ui', ['storeIds' => implode(',', $managedStoreIds ?? []), 'storeName' => $resolvedStore->store_name ?? 'Multiple Pharmacy Units', 'mode' => 'given'])
                                    </div>
                                    <div class="tab-pane fade" id="pharm-sr-received">
                                        @include('admin.inventory.components.summary-report-ui', ['storeIds' => implode(',', $managedStoreIds ?? []), 'storeName' => $resolvedStore->store_name ?? 'Multiple Pharmacy Units', 'mode' => 'received'])
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dispensing Report Tab -->
                    <div class="tab-pane fade" id="pharm-dispensing-content" role="tabpanel">
                        <div class="card-modern">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped" id="pharm-dispensing-table" style="width: 100%">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Date/Time</th>
                                                <th>Ref #</th>
                                                <th>Patient</th>
                                                <th>Product</th>
                                                <th>Qty</th>
                                                <th>Amount</th>
                                                <th>Payment</th>
                                                <th>Store</th>
                                                <th>Pharmacist</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Revenue Report Tab -->
                    <div class="tab-pane fade" id="pharm-revenue-content" role="tabpanel">
                        <div class="card-modern">
                            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="mdi mdi-cash-register"></i> Revenue Summary</h6>
                                <div class="btn-group btn-group-sm" role="group">
                                    <input type="radio" class="btn-check" name="revenue-group" id="revenue-daily" value="daily" checked>
                                    <label class="btn btn-outline-primary" for="revenue-daily">Daily</label>
                                    <input type="radio" class="btn-check" name="revenue-group" id="revenue-weekly" value="weekly">
                                    <label class="btn btn-outline-primary" for="revenue-weekly">Weekly</label>
                                    <input type="radio" class="btn-check" name="revenue-group" id="revenue-monthly" value="monthly">
                                    <label class="btn btn-outline-primary" for="revenue-monthly">Monthly</label>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped" id="pharm-revenue-table" style="width: 100%">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Period</th>
                                                <th class="text-center">Transactions</th>
                                                <th class="text-end">Cash</th>
                                                <th class="text-end">Card</th>
                                                <th class="text-end">Transfer</th>
                                                <th class="text-end">HMO</th>
                                                <th class="text-end">Total</th>
                                                <th class="text-end">Avg/Txn</th>
                                            </tr>
                                        </thead>
                                        <tfoot class="table-secondary fw-bold">
                                            <tr>
                                                <td>TOTAL</td>
                                                <td class="text-center" id="revenue-total-txn">0</td>
                                                <td class="text-end" id="revenue-total-cash">₦0</td>
                                                <td class="text-end" id="revenue-total-card">₦0</td>
                                                <td class="text-end" id="revenue-total-transfer">₦0</td>
                                                <td class="text-end" id="revenue-total-hmo">₦0</td>
                                                <td class="text-end" id="revenue-total-all">₦0</td>
                                                <td class="text-end" id="revenue-total-avg">₦0</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stock Report Tab -->
                    <div class="tab-pane fade" id="pharm-stock-content" role="tabpanel">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <select class="form-select" id="stock-report-store-filter">
                                    <option value="">All Stores (Combined)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" id="stock-report-category-filter">
                                    <option value="">All Categories</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="stock-show-low-only">
                                    <label class="form-check-label" for="stock-show-low-only">Show Low Stock Only</label>
                                </div>
                            </div>
                        </div>
                        <div class="card-modern">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped" id="pharm-stock-table" style="width: 100%">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Product</th>
                                                <th>Code</th>
                                                <th>Category</th>
                                                <th class="text-center">Reorder Level</th>
                                                <th class="text-center">Global Stock</th>
                                                <th>Store Breakdown</th>
                                                <th class="text-center">Dispensed (Period)</th>
                                                <th class="text-end">Unit Price</th>
                                                <th class="text-end">Stock Value</th>
                                                <th class="text-center">Status</th>
                                            </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Performance Report Tab -->
                    <div class="tab-pane fade" id="pharm-performance-content" role="tabpanel">
                        <div class="card-modern">
                            <div class="card-header py-2">
                                <h6 class="mb-0"><i class="mdi mdi-account-tie"></i> Pharmacist Performance</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped" id="pharm-performance-table" style="width: 100%">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Pharmacist</th>
                                                <th class="text-center">Total Dispensed</th>
                                                <th class="text-end">Total Revenue</th>
                                                <th class="text-center">Cash Txns</th>
                                                <th class="text-center">HMO Txns</th>
                                                <th class="text-end">Cash Amount</th>
                                                <th class="text-end">HMO Amount</th>
                                                <th class="text-center">Avg TAT (mins)</th>
                                                <th class="text-center">Unique Patients</th>
                                            </tr>
                                        </thead>
                                        <tfoot class="table-secondary fw-bold">
                                            <tr>
                                                <td>TOTAL</td>
                                                <td class="text-center" id="perf-total-dispensed">0</td>
                                                <td class="text-end" id="perf-total-revenue">₦0</td>
                                                <td class="text-center" id="perf-total-cash-txn">0</td>
                                                <td class="text-center" id="perf-total-hmo-txn">0</td>
                                                <td class="text-end" id="perf-total-cash-amt">₦0</td>
                                                <td class="text-end" id="perf-total-hmo-amt">₦0</td>
                                                <td class="text-center" id="perf-avg-tat">-</td>
                                                <td class="text-center" id="perf-total-patients">0</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- HMO Claims Tab -->
                    <div class="tab-pane fade" id="pharm-hmo-content" role="tabpanel">
                        <div class="card-modern">
                            <div class="card-header py-2">
                                <h6 class="mb-0"><i class="mdi mdi-hospital-building"></i> HMO Claims Summary</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped" id="pharm-hmo-table" style="width: 100%">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>HMO Provider</th>
                                                <th class="text-center">Total Claims</th>
                                                <th class="text-end">Total Amount</th>
                                                <th class="text-center">Validated</th>
                                                <th class="text-end">Validated Amt</th>
                                                <th class="text-center">Pending</th>
                                                <th class="text-end">Pending Amt</th>
                                                <th class="text-center">Rejected</th>
                                                <th class="text-end">Rejected Amt</th>
                                            </tr>
                                        </thead>
                                        <tfoot class="table-secondary fw-bold">
                                            <tr>
                                                <td>TOTAL</td>
                                                <td class="text-center" id="hmo-total-claims">0</td>
                                                <td class="text-end" id="hmo-total-amount">₦0</td>
                                                <td class="text-center" id="hmo-total-validated">0</td>
                                                <td class="text-end" id="hmo-total-validated-amt">₦0</td>
                                                <td class="text-center" id="hmo-total-pending">0</td>
                                                <td class="text-end" id="hmo-total-pending-amt">₦0</td>
                                                <td class="text-center" id="hmo-total-rejected">0</td>
                                                <td class="text-end" id="hmo-total-rejected-amt">₦0</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>