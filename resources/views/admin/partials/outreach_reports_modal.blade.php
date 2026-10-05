{{-- Shared Community Outreach Reports & Analytics Dashboard Modal (Nursing & Maternity) --}}
<div class="modal fade modal-outreach-xl" id="outreachReportsModal" tabindex="-1" role="dialog" aria-labelledby="outreachReportsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content border-0 shadow-lg">
            <!-- Modal Header -->
            <div class="modal-header outreach-modal-header">
                <div>
                    <h5 class="modal-title" id="outreachReportsModalLabel">
                        <i class="mdi mdi-chart-box-outline"></i> Community Outreach Sessions &amp; EPI Field Reports Dashboard
                    </h5>
                    <small class="text-white-50">Multi-Source Inventory Audit &bull; NHMIS 2019 Routine Outreach &bull; WHO EPI Form 001</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-light fw-bold" id="btn-print-outreach-report-summary">
                        <i class="mdi mdi-printer"></i> Print Summary
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-light" id="btn-export-outreach-csv">
                        <i class="mdi mdi-file-delimited-outline"></i> Export CSV
                    </button>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            <div class="modal-body p-3">
                <!-- 1. Hero KPI Cards Grid (7 Key Metrics) -->
                <div class="outreach-kpi-grid-7">
                    <div class="outreach-kpi-card">
                        <span class="outreach-kpi-title"><i class="mdi mdi-map-marker-distance"></i> Campaigns</span>
                        <span class="outreach-kpi-val" id="report-kpi-sessions">0</span>
                    </div>
                    <div class="outreach-kpi-card kpi-green">
                        <span class="outreach-kpi-title"><i class="mdi mdi-needle"></i> Total Doses</span>
                        <span class="outreach-kpi-val" id="report-kpi-doses">0</span>
                    </div>
                    <div class="outreach-kpi-card kpi-red">
                        <span class="outreach-kpi-title"><i class="mdi mdi-delete-variant"></i> Wasted / Rate</span>
                        <span class="outreach-kpi-val" id="report-kpi-wasted">0 (0%)</span>
                    </div>
                    <div class="outreach-kpi-card">
                        <span class="outreach-kpi-title"><i class="mdi mdi-baby"></i> Infants &lt;1y</span>
                        <span class="outreach-kpi-val" id="report-kpi-infants">0</span>
                    </div>
                    <div class="outreach-kpi-card kpi-amber">
                        <span class="outreach-kpi-title"><i class="mdi mdi-human-child"></i> Children &ge;1y</span>
                        <span class="outreach-kpi-val" id="report-kpi-children">0</span>
                    </div>
                    <div class="outreach-kpi-card kpi-pink">
                        <span class="outreach-kpi-title"><i class="mdi mdi-gender-female"></i> HPV Girls (9–14y)</span>
                        <span class="outreach-kpi-val" id="report-kpi-hpv">0</span>
                    </div>
                    <div class="outreach-kpi-card kpi-purple">
                        <span class="outreach-kpi-title"><i class="mdi mdi-mother-nurse"></i> Maternal Td (PW)</span>
                        <span class="outreach-kpi-val" id="report-kpi-pregnant">0</span>
                    </div>
                </div>

                <!-- 2. Multi-Column Filter Bar -->
                <div class="outreach-session-card mb-3 p-2">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label for="report-filter-from" class="form-label small fw-bold text-muted mb-1">From Date</label>
                            <input type="date" class="form-control form-control-sm" id="report-filter-from">
                        </div>
                        <div class="col-md-2">
                            <label for="report-filter-to" class="form-label small fw-bold text-muted mb-1">To Date</label>
                            <input type="date" class="form-control form-control-sm" id="report-filter-to">
                        </div>
                        <div class="col-md-3">
                            <label for="report-filter-location" class="form-label small fw-bold text-muted mb-1">Settlement / Location</label>
                            <input type="text" class="form-control form-control-sm" id="report-filter-location" placeholder="Search settlement...">
                        </div>
                        <div class="col-md-3">
                            <label for="report-filter-stock-source" class="form-label small fw-bold text-muted mb-1">Stock Source</label>
                            <select class="form-select form-select-sm" id="report-filter-stock-source">
                                <option value="all" selected>All Stock Sources</option>
                                <option value="govt_epi">Government Free Routine EPI Buffer</option>
                                <option value="hospital_store">Hospital Cold Store Inventory</option>
                                <option value="donor_partner">Partner / Donor Campaign (UNICEF/WHO)</option>
                                <option value="outbreak_reserve">Outbreak Emergency Stockpile</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-primary w-100 fw-bold" id="btn-apply-outreach-report-filter">
                                <i class="mdi mdi-filter"></i> Apply
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-reset-outreach-report-filter" title="Reset Filters">
                                <i class="mdi mdi-refresh"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 3. Multi-Tab Navigation -->
                <div class="outreach-report-tabs">
                    <button type="button" class="report-tab-btn active" data-tab="sessions">
                        <i class="mdi mdi-format-list-bulleted"></i> Sessions Ledger
                    </button>
                    <button type="button" class="report-tab-btn" data-tab="matrix">
                        <i class="mdi mdi-grid"></i> Antigen Utilization Matrix
                    </button>
                    <button type="button" class="report-tab-btn" data-tab="reconciliation">
                        <i class="mdi mdi-package-variant-closed"></i> Stock Source &amp; Cold Chain
                    </button>
                    <button type="button" class="report-tab-btn" data-tab="nhmis">
                        <i class="mdi mdi-file-document-check-outline"></i> NHMIS 2019 Crosswalk
                    </button>
                </div>

                <!-- 4. Tab Panes -->
                <!-- Tab Pane 1: Sessions Ledger -->
                <div class="report-tab-pane" id="pane-report-sessions">
                    <div class="table-responsive border rounded bg-white" style="max-height: 440px; overflow-y: auto;">
                        <table class="table table-hover table-striped mb-0" id="outreach-sessions-report-table">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="width: 10%;">Date</th>
                                    <th style="width: 16%;">Settlement / Site</th>
                                    <th style="width: 13%;">Session ID</th>
                                    <th style="width: 15%;">Stock Source</th>
                                    <th style="width: 11%;">Cold Chain</th>
                                    <th style="width: 12%;">Vaccinator</th>
                                    <th style="width: 8%; text-align: center;">Doses</th>
                                    <th style="width: 7%; text-align: center;">Wasted</th>
                                    <th style="width: 8%; text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="outreach-sessions-report-tbody">
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">
                                        <i class="mdi mdi-loading mdi-spin mdi-24px"></i>
                                        <div class="mt-1">Loading outreach sessions...</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab Pane 2: Antigen Utilization Matrix -->
                <div class="report-tab-pane d-none" id="pane-report-matrix">
                    <div class="table-responsive border rounded bg-white" style="max-height: 440px; overflow-y: auto;">
                        <table class="table table-bordered table-sm outreach-pivot-table mb-0" id="outreach-matrix-table">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="width: 22%;">Antigen / Vaccine</th>
                                    <th style="width: 11%;">Infants &lt;1y</th>
                                    <th style="width: 11%;">Children &ge;1y</th>
                                    <th style="width: 11%;">Girls 9–14y (HPV)</th>
                                    <th style="width: 11%;">Pregnant Women (Td)</th>
                                    <th style="width: 11%;">Non-Pregnant WRA</th>
                                    <th style="width: 11%;">Adults / Other</th>
                                    <th style="width: 12%; background: #e2e8f0;">Total Doses</th>
                                </tr>
                            </thead>
                            <tbody id="outreach-matrix-tbody">
                                <!-- Populated dynamically via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab Pane 3: Stock Source & Cold Chain Reconciliation -->
                <div class="report-tab-pane d-none" id="pane-report-reconciliation">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card border h-100">
                                <div class="card-header bg-light py-2 fw-bold">
                                    <i class="mdi mdi-source-branch text-primary"></i> Doses by Stock Source
                                </div>
                                <div class="card-body p-2">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>Stock Source</th>
                                                <th style="text-align: right;">Doses</th>
                                                <th style="text-align: right;">Share %</th>
                                            </tr>
                                        </thead>
                                        <tbody id="stock-sources-recon-tbody">
                                            <!-- Dynamic via JS -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border h-100">
                                <div class="card-header bg-light py-2 fw-bold">
                                    <i class="mdi mdi-snowflake text-info"></i> Cold Chain Quality &amp; VVM Distribution
                                </div>
                                <div class="card-body p-2">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>VVM Stage</th>
                                                <th>Usability</th>
                                                <th style="text-align: right;">Doses Tracked</th>
                                            </tr>
                                        </thead>
                                        <tbody id="vvm-recon-tbody">
                                            <!-- Dynamic via JS -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Pane 4: NHMIS 2019 Crosswalk -->
                <div class="report-tab-pane d-none" id="pane-report-nhmis">
                    <div class="alert alert-light border py-2 px-3 mb-2 small">
                        <i class="mdi mdi-check-decagram text-success"></i>
                        <strong>NHMIS 2019 Integration:</strong> Community outreach doses automatically integrate into the monthly aggregate summary register alongside fixed clinic doses. Below is the direct crosswalk mapping for the active period.
                    </div>
                    <div class="table-responsive border rounded bg-white" style="max-height: 380px; overflow-y: auto;">
                        <table class="table table-sm table-striped mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="width: 12%;">NHMIS Item #</th>
                                    <th style="width: 40%;">Official Indicator Title</th>
                                    <th style="width: 25%;">Target Demographic Category</th>
                                    <th style="width: 13%; text-align: center;">Outreach Contribution</th>
                                </tr>
                            </thead>
                            <tbody id="nhmis-crosswalk-tbody">
                                <!-- Populated dynamically via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 5. Single Session Line-by-Line Details Container -->
                <div id="outreach-session-detail-container" class="mt-3 d-none">
                    <div class="card border">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                            <strong><i class="mdi mdi-file-tree text-primary"></i> Line-by-Line Breakdown: <span id="detail-session-id">-</span></strong>
                            <button type="button" class="btn btn-sm btn-link text-muted p-0" id="btn-close-session-detail">
                                <i class="mdi mdi-close"></i> Close Breakdown
                            </button>
                        </div>
                        <div class="card-body p-2">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Antigen</th>
                                            <th>Dose</th>
                                            <th>Target Cohort</th>
                                            <th>Sex</th>
                                            <th style="text-align: center;">Headcount</th>
                                            <th style="text-align: center;">Wasted</th>
                                            <th>Stock Source</th>
                                            <th>Batch Number</th>
                                            <th>Expiry</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detail-session-tbody">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
