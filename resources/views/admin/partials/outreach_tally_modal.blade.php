{{-- Shared Community Outreach Immunization Rapid Tally Modal (Nursing & Maternity Workbenches) --}}
<div class="modal fade modal-outreach-xl" id="outreachTallyModal" tabindex="-1" role="dialog" aria-labelledby="outreachTallyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content border-0 shadow-lg">
            <!-- Header -->
            <div class="modal-header outreach-modal-header">
                <div>
                    <h5 class="modal-title" id="outreachTallyModalLabel">
                        <i class="mdi mdi-account-group-outline"></i> Community Outreach Immunization Tally
                    </h5>
                    <small class="text-white-50">Stepped Field Entry &bull; WHO EPI Form 001 &bull; DHIS2 &bull; NHMIS 2019 Standard</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-dark px-2 py-1" id="outreach-session-uuid-display">
                        <i class="mdi mdi-barcode-scan"></i> <span id="display-outreach-uuid">NEW SESSION</span>
                    </span>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            <!-- Stepper Progress Bar -->
            <div class="outreach-stepper-wrapper">
                <div class="outreach-stepper">
                    <div class="stepper-progress-line" id="stepper-progress-line"></div>
                    <div class="stepper-step active" data-step="1" id="step-node-1">
                        <div class="stepper-circle">1</div>
                        <span class="stepper-label">Logistics &amp; Stock Source</span>
                    </div>
                    <div class="stepper-step" data-step="2" id="step-node-2">
                        <div class="stepper-circle">2</div>
                        <span class="stepper-label">Rapid Tallies Matrix</span>
                    </div>
                    <div class="stepper-step" data-step="3" id="step-node-3">
                        <div class="stepper-circle">3</div>
                        <span class="stepper-label">Reconcile &amp; Save</span>
                    </div>
                </div>
            </div>

            <!-- Modal Body (Stepped Panes) -->
            <div class="modal-body p-3">
                <!-- ============================================================== -->
                <!-- STEP 1: CAMPAIGN LOGISTICS & VACCINE STOCK SOURCE              -->
                <!-- ============================================================== -->
                <div class="outreach-step-pane" id="outreach-step-1">
                    <div class="outreach-setup-grid">
                        <!-- Column 1: Campaign Logistics -->
                        <div class="setup-card">
                            <div class="setup-card-header">
                                <i class="mdi mdi-map-marker-radius"></i> 1. Campaign Logistics
                            </div>
                            <div class="mb-2">
                                <label for="outreach-session-date" class="form-label fw-bold small text-muted mb-1">
                                    Outreach Date *
                                </label>
                                <input type="date" class="form-control form-control-sm" id="outreach-session-date" required>
                            </div>
                            <div class="mb-2">
                                <label for="outreach-session-location" class="form-label fw-bold small text-muted mb-1">
                                    Settlement / Ward / Site *
                                </label>
                                <input type="text" class="form-control form-control-sm" id="outreach-session-location" placeholder="e.g. Sabon Gari Market, Ungwan Rogo Primary School" required>
                            </div>
                            <div class="mb-2">
                                <label for="outreach-session-strategy" class="form-label fw-bold small text-muted mb-1">
                                    Delivery Strategy
                                </label>
                                <select class="form-select form-select-sm" id="outreach-session-strategy">
                                    <option value="Mobile Outreach Team" selected>Mobile Outreach Team</option>
                                    <option value="Community Fixed Post">Community Fixed Post</option>
                                    <option value="School-Based Drive">School-Based Drive</option>
                                    <option value="Market / Public Gathering">Market / Public Gathering</option>
                                    <option value="Supplementary Immunization (SIA)">Supplementary Immunization (SIA)</option>
                                    <option value="Outbreak Response (OVD)">Outbreak Response (OVD)</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label for="outreach-session-lead" class="form-label fw-bold small text-muted mb-1">
                                    Team Lead / Supervisor
                                </label>
                                <input type="text" class="form-control form-control-sm" id="outreach-session-lead" placeholder="Lead nurse / vaccinator name" value="{{ auth()->user()?->name }}">
                            </div>
                        </div>

                        <!-- Column 2: Vaccine Stock Source -->
                        <div class="setup-card">
                            <div class="setup-card-header">
                                <i class="mdi mdi-package-variant-closed"></i> 2. Vaccine Stock Source
                            </div>
                            <div class="stock-source-options">
                                <!-- Option A: Government Free EPI -->
                                <label class="stock-source-choice selected" id="choice-source-govt">
                                    <input type="radio" name="outreach_stock_source" value="govt_epi" checked>
                                    <div class="stock-source-info">
                                        <div class="stock-source-title">
                                            <span><i class="mdi mdi-shield-check text-success"></i> Government Free Routine EPI</span>
                                            <span class="badge bg-success-subtle text-success">Standard</span>
                                        </div>
                                        <div class="stock-source-desc">
                                            Allocated from LGA / State Cold Store in vaccine carriers. No hospital retail stock deduction.
                                        </div>
                                    </div>
                                </label>

                                <!-- Option B: Hospital Internal Store -->
                                <label class="stock-source-choice" id="choice-source-hospital">
                                    <input type="radio" name="outreach_stock_source" value="hospital_store">
                                    <div class="stock-source-info">
                                        <div class="stock-source-title">
                                            <span><i class="mdi mdi-hospital-building text-primary"></i> Hospital Cold Store (Internal Inventory)</span>
                                            <span class="badge bg-primary-subtle text-primary">Store Tracked</span>
                                        </div>
                                        <div class="stock-source-desc">
                                            Requisitioned directly from hospital cold chain store / dispensary.
                                        </div>
                                    </div>
                                </label>

                                <!-- Option C: Partner / NGO / Donor -->
                                <label class="stock-source-choice" id="choice-source-donor">
                                    <input type="radio" name="outreach_stock_source" value="donor_partner">
                                    <div class="stock-source-info">
                                        <div class="stock-source-title">
                                            <span><i class="mdi mdi-charity text-info"></i> Partner / Donor Campaign (UNICEF/WHO)</span>
                                            <span class="badge bg-info-subtle text-info">Partner</span>
                                        </div>
                                        <div class="stock-source-desc">
                                            Supplied by UNICEF, WHO, GAVI, Rotary Club for special campaigns.
                                        </div>
                                    </div>
                                </label>

                                <!-- Option D: Outbreak Emergency Stockpile -->
                                <label class="stock-source-choice" id="choice-source-outbreak">
                                    <input type="radio" name="outreach_stock_source" value="outbreak_reserve">
                                    <div class="stock-source-info">
                                        <div class="stock-source-title">
                                            <span><i class="mdi mdi-alert-decagram text-danger"></i> Outbreak / Emergency Stockpile</span>
                                            <span class="badge bg-danger-subtle text-danger">Emergency</span>
                                        </div>
                                        <div class="stock-source-desc">
                                            Emergency disease response buffer (Cholera, Yellow Fever, Meningitis).
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <!-- Hospital Store Configuration (Revealed when Hospital Store is selected) -->
                            <div id="hospital-store-details-panel" class="p-2 border rounded bg-white mt-1 d-none">
                                <label for="outreach-session-store" class="form-label fw-bold small text-muted mb-1">
                                    <i class="mdi mdi-store"></i> Select Dispensing Store *
                                </label>
                                <select class="form-select form-select-sm" id="outreach-session-store">
                                    @if(isset($stores) && count($stores) > 0)
                                        @foreach($stores as $st)
                                            <option value="{{ $st->id }}" {{ ($resolvedStore && $resolvedStore->id == $st->id) ? 'selected' : '' }}>
                                                {{ $st->store_name }}
                                            </option>
                                        @endforeach
                                    @elseif($resolvedStore ?? null)
                                        <option value="{{ $resolvedStore->id }}" selected>{{ $resolvedStore->store_name }}</option>
                                    @else
                                        <option value="">-- No store assigned --</option>
                                    @endif
                                </select>

                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="outreach-auto-deduct-stock" checked>
                                    <label class="form-check-label small fw-bold text-dark" for="outreach-auto-deduct-stock">
                                        <i class="mdi mdi-database-minus text-warning"></i> Auto-deduct dispensed quantities from hospital inventory on session save
                                    </label>
                                </div>

                                <!-- Store Inventory Live Summary -->
                                <div id="outreach-store-inventory-preview" class="store-stock-preview mt-2 p-2 rounded border bg-light d-none">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="small fw-bold text-dark"><i class="mdi mdi-package-variant-closed text-success"></i> Store Vaccine Stock</span>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle" id="store-stock-status-badge">Loaded</span>
                                    </div>
                                    <div class="small text-muted" id="store-stock-summary-text">
                                        Checking store inventory...
                                    </div>
                                </div>
                            </div>

                            <!-- Partner Details (Revealed when Donor/Partner is selected) -->
                            <div id="partner-details-panel" class="p-2 border rounded bg-white mt-1 d-none">
                                <label for="outreach-partner-name" class="form-label fw-bold small text-muted mb-1">
                                    Partner / Donor Organization
                                </label>
                                <input type="text" class="form-control form-control-sm" id="outreach-partner-name" placeholder="e.g. UNICEF, WHO, Rotary International, GAVI">
                            </div>
                        </div>

                        <!-- Column 3: Cold Chain & Quality Assurance -->
                        <div class="setup-card">
                            <div class="setup-card-header">
                                <i class="mdi mdi-snowflake"></i> 3. Cold Chain &amp; Quality
                            </div>
                            <div class="mb-2">
                                <label for="outreach-carrier-id" class="form-label fw-bold small text-muted mb-1">
                                    Vaccine Carrier / Cold Box ID
                                </label>
                                <input type="text" class="form-control form-control-sm" id="outreach-carrier-id" placeholder="e.g. Cold Box #2, Carrier A (Giostyle)" value="Cold Box #1">
                            </div>

                            <div class="mb-2">
                                <label class="form-label fw-bold small text-muted mb-1">
                                    Vaccine Vial Monitor (VVM) Status
                                </label>
                                <div class="vvm-selector">
                                    <div class="vvm-pill stage-usable active" data-vvm="Stage 1">
                                        Stage 1<br><small>Normal</small>
                                    </div>
                                    <div class="vvm-pill stage-usable" data-vvm="Stage 2">
                                        Stage 2<br><small>Usable</small>
                                    </div>
                                    <div class="vvm-pill stage-discard" data-vvm="Stage 3">
                                        Stage 3<br><small>Discard</small>
                                    </div>
                                    <div class="vvm-pill stage-discard" data-vvm="Stage 4">
                                        Stage 4<br><small>Discard</small>
                                    </div>
                                </div>
                                <input type="hidden" id="outreach-vvm-stage" value="Stage 1">
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label for="outreach-cold-temp" class="form-label fw-bold small text-muted mb-1">
                                        Pack Temp (°C)
                                    </label>
                                    <input type="number" step="0.5" class="form-control form-control-sm" id="outreach-cold-temp" placeholder="e.g. 4.0" value="4.0">
                                </div>
                                <div class="col-6">
                                    <label for="outreach-wasted-doses" class="form-label fw-bold small text-muted mb-1">
                                        Doses Wasted
                                    </label>
                                    <input type="number" min="0" class="form-control form-control-sm" id="outreach-wasted-doses" placeholder="0" value="0">
                                </div>
                            </div>

                            <div class="mb-2">
                                <label for="outreach-session-notes" class="form-label fw-bold small text-muted mb-1">
                                    Session Notes / Cold Chain Observations
                                </label>
                                <textarea class="form-control form-control-sm" id="outreach-session-notes" rows="2" placeholder="Field conditions, weather, community mobilization..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================== -->
                <!-- STEP 2: RAPID TALLIES & MULTI-COLUMN MATRIX                     -->
                <!-- ============================================================== -->
                <!-- ============================================================== -->
                <!-- STEP 2: RAPID TALLIES & MULTI-COLUMN MATRIX                     -->
                <!-- ============================================================== -->
                <div class="outreach-step-pane d-none" id="outreach-step-2">
                    <div class="outreach-split-layout">
                        <!-- Primary Column: Antigens & Quick Bundles -->
                        <div class="outreach-main-column">

                            <!-- Flow Control Deck: Step 2A (Cohort Filter) & Step 2B (Action Bundles) -->
                            <div class="outreach-control-deck mb-2">
                                <!-- 1. Cohort Navigator Segmented Control -->
                                <div class="outreach-deck-section">
                                    <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-2">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="step-subbadge">2A</span>
                                            <span class="control-deck-title"><i class="mdi mdi-filter-variant text-primary"></i> Target Cohort Filter:</span>
                                        </div>
                                        <div class="input-group input-group-sm search-antigen-wrap">
                                            <span class="input-group-text bg-white border-end-0 py-0"><i class="mdi mdi-magnify text-muted"></i></span>
                                            <input type="text" class="form-control form-control-sm border-start-0 py-0" id="search-antigen-input" placeholder="Search antigen or dose...">
                                            <button class="btn btn-outline-secondary btn-sm py-0 border-start-0 d-none" type="button" id="btn-clear-search"><i class="mdi mdi-close"></i></button>
                                        </div>
                                    </div>
                                    <div class="outreach-demographic-tabs">
                                        <button type="button" class="outreach-tab-btn active" data-filter="all">
                                            <i class="mdi mdi-earth"></i> All Antigens <span class="tab-count-pill" id="pill-count-all">0</span>
                                        </button>
                                        <button type="button" class="outreach-tab-btn" data-filter="infants">
                                            <i class="mdi mdi-baby"></i> Infants &lt; 1y <span class="tab-count-pill" id="pill-count-infants">0</span>
                                        </button>
                                        <button type="button" class="outreach-tab-btn" data-filter="children">
                                            <i class="mdi mdi-human-child"></i> Children 1–4y <span class="tab-count-pill" id="pill-count-children">0</span>
                                        </button>
                                        <button type="button" class="outreach-tab-btn" data-filter="hpv">
                                            <i class="mdi mdi-gender-female text-pink"></i> Girls 9–14y (HPV) <span class="tab-count-pill" id="pill-count-hpv">0</span>
                                        </button>
                                        <button type="button" class="outreach-tab-btn" data-filter="pregnant">
                                            <i class="mdi mdi-mother-nurse text-primary"></i> Pregnant (Td) <span class="tab-count-pill" id="pill-count-pregnant">0</span>
                                        </button>
                                        <button type="button" class="outreach-tab-btn" data-filter="wra">
                                            <i class="mdi mdi-gender-female text-purple"></i> WRA 15–49y <span class="tab-count-pill" id="pill-count-wra">0</span>
                                        </button>
                                        <button type="button" class="outreach-tab-btn" data-filter="general">
                                            <i class="mdi mdi-account-group"></i> Adults / Other <span class="tab-count-pill" id="pill-count-general">0</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- 2. One-Touch Rapid Visit Bundles (+1 Action Triggers) -->
                                <div class="outreach-deck-section mt-2 pt-2 border-top">
                                    <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="step-subbadge subbadge-amber">2B</span>
                                            <span class="control-deck-title text-amber-dark"><i class="mdi mdi-flash text-warning"></i> Rapid Visit Bundles (+1 Action):</span>
                                        </div>
                                        <small class="text-muted"><i class="mdi mdi-cursor-default-click"></i> Click once to increment (+1) all schedule antigens in that visit</small>
                                    </div>
                                    <div class="outreach-bundles-bar">
                                        <button type="button" class="outreach-bundle-chip" data-bundle="birth" title="Adds +1 to BCG, OPV-0, HepB-0">
                                            <span class="bundle-plus-tag">+1</span> <i class="mdi mdi-baby"></i> <strong>Birth</strong> <span class="bundle-subtext">(BCG, OPV-0, HepB-0)</span>
                                        </button>
                                        <button type="button" class="outreach-bundle-chip" data-bundle="week6" title="Adds +1 to OPV-1, Penta-1, PCV-1, Rota-1">
                                            <span class="bundle-plus-tag">+1</span> <i class="mdi mdi-calendar-week"></i> <strong>6-Week</strong> <span class="bundle-subtext">(OPV-1, Penta-1, PCV-1, Rota-1)</span>
                                        </button>
                                        <button type="button" class="outreach-bundle-chip" data-bundle="week10" title="Adds +1 to OPV-2, Penta-2, PCV-2, Rota-2">
                                            <span class="bundle-plus-tag">+1</span> <i class="mdi mdi-calendar-week"></i> <strong>10-Week</strong> <span class="bundle-subtext">(OPV-2, Penta-2, PCV-2, Rota-2)</span>
                                        </button>
                                        <button type="button" class="outreach-bundle-chip" data-bundle="week14" title="Adds +1 to OPV-3, Penta-3, PCV-3, Rota-3, IPV">
                                            <span class="bundle-plus-tag">+1</span> <i class="mdi mdi-calendar-week"></i> <strong>14-Week</strong> <span class="bundle-subtext">(OPV-3, Penta-3, PCV-3, Rota-3, IPV)</span>
                                        </button>
                                        <button type="button" class="outreach-bundle-chip" data-bundle="month9" title="Adds +1 to Vit A, Measles-1, YF, Men-A">
                                            <span class="bundle-plus-tag">+1</span> <i class="mdi mdi-shield-check"></i> <strong>9-Month</strong> <span class="bundle-subtext">(Vit A, Measles-1, YF, Men-A)</span>
                                        </button>
                                        <button type="button" class="outreach-bundle-chip" data-bundle="year2" title="Adds +1 to Measles-2, Men-A, Vit A">
                                            <span class="bundle-plus-tag">+1</span> <i class="mdi mdi-human-child"></i> <strong>2YL Booster</strong> <span class="bundle-subtext">(Measles-2, Men-A, Vit A)</span>
                                        </button>
                                        <button type="button" class="outreach-bundle-chip" data-bundle="hpv" title="Adds +1 to HPV Dose 1">
                                            <span class="bundle-plus-tag">+1</span> <i class="mdi mdi-gender-female text-pink"></i> <strong>HPV Drive</strong> <span class="bundle-subtext">(Girls 9–14y)</span>
                                        </button>
                                        <button type="button" class="outreach-bundle-chip" data-bundle="maternal_td" title="Adds +1 to Maternal Td">
                                            <span class="bundle-plus-tag">+1</span> <i class="mdi mdi-mother-nurse text-primary"></i> <strong>Maternal Td</strong> <span class="bundle-subtext">(Pregnant)</span>
                                        </button>
                                        <button type="button" class="outreach-bundle-chip" data-bundle="wra_td" title="Adds +1 to Non-Pregnant WRA Td">
                                            <span class="bundle-plus-tag">+1</span> <i class="mdi mdi-gender-female text-purple"></i> <strong>WRA Td</strong> <span class="bundle-subtext">(15–49y)</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. Interactive Tally Matrix Table -->
                            <div class="outreach-tally-table-wrapper">
                                <table class="table outreach-tally-table" id="outreach-tally-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 4%; text-align: center;">#</th>
                                            <th style="width: 28%;">Antigen &amp; Formulation</th>
                                            <th style="width: 11%; text-align: center;">Dose</th>
                                            <th style="width: 18%;">Target Cohort</th>
                                            <th style="width: 9%; text-align: center;">Sex</th>
                                            <th style="width: 22%; text-align: center;">Headcount (Live Tally)</th>
                                            <th style="width: 8%; text-align: center;">Wasted</th>
                                        </tr>
                                    </thead>
                                    <tbody id="outreach-tally-rows">
                                        <!-- Populated dynamically via JS -->
                                    </tbody>
                                </table>
                            </div>

                            <!-- Quick Row Actions -->
                            <div class="d-flex justify-content-between align-items-center mt-2 px-1 flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-outreach-row">
                                    <i class="mdi mdi-plus-circle-outline"></i> Add Custom Antigen Row
                                </button>
                                <span class="small text-muted" id="outreach-table-row-count">Showing 32 WHO standard antigens across 9 schedule milestones</span>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="btn-clear-outreach-tallies">
                                    <i class="mdi mdi-refresh"></i> Clear All Tallies
                                </button>
                            </div>
                        </div>

                        <!-- Sticky Sidebar: Live Session Breakdown & Audit (30%) -->
                        <div class="outreach-sticky-sidebar">
                            <div class="sidebar-live-title">
                                <span><i class="mdi mdi-chart-donut text-primary"></i> Live Session Audit</span>
                                <span class="badge bg-success" id="sidebar-live-status">Active</span>
                            </div>

                            <!-- Big Main Number -->
                            <div class="sidebar-kpi-main">
                                <div class="sidebar-kpi-num" id="outreach-stat-total">0</div>
                                <div class="sidebar-kpi-lbl">Total Doses Administered</div>
                            </div>

                            <!-- Cohort Breakdown List -->
                            <ul class="sidebar-breakdown-list">
                                <li class="sidebar-breakdown-item">
                                    <span class="label"><i class="mdi mdi-baby text-info"></i> Infants &lt; 1y (0–11m)</span>
                                    <span class="val" id="outreach-stat-infants">0</span>
                                </li>
                                <li class="sidebar-breakdown-item">
                                    <span class="label"><i class="mdi mdi-human-child text-warning"></i> Children &ge; 1y (12–59m)</span>
                                    <span class="val" id="outreach-stat-children">0</span>
                                </li>
                                <li class="sidebar-breakdown-item">
                                    <span class="label"><i class="mdi mdi-gender-female text-pink"></i> Girls 9–14y (HPV)</span>
                                    <span class="val" id="outreach-stat-hpv">0</span>
                                </li>
                                <li class="sidebar-breakdown-item">
                                    <span class="label"><i class="mdi mdi-mother-nurse text-primary"></i> Pregnant Women (Td)</span>
                                    <span class="val" id="outreach-stat-pregnant">0</span>
                                </li>
                                <li class="sidebar-breakdown-item">
                                    <span class="label"><i class="mdi mdi-account-group text-purple"></i> Non-Pregnant WRA</span>
                                    <span class="val" id="outreach-stat-wra">0</span>
                                </li>
                                <li class="sidebar-breakdown-item">
                                    <span class="label"><i class="mdi mdi-account text-secondary"></i> Adults &amp; General</span>
                                    <span class="val" id="outreach-stat-adults">0</span>
                                </li>
                                <li class="sidebar-breakdown-item">
                                    <span class="label text-danger"><i class="mdi mdi-delete-variant"></i> Total Doses Wasted</span>
                                    <span class="val text-danger" id="outreach-stat-wasted">0</span>
                                </li>
                            </ul>

                            <!-- Stock Source & Cold Chain Status Box -->
                            <div class="p-2 bg-light border rounded mb-2 small">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Stock Source:</span>
                                    <strong id="sidebar-stock-source-label" class="text-dark">Govt Free EPI</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Carrier Box:</span>
                                    <strong id="sidebar-carrier-label" class="text-dark">Cold Box #1</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">VVM Quality:</span>
                                    <strong id="sidebar-vvm-label" class="text-success">Stage 1 (Usable)</strong>
                                </div>
                            </div>

                            <!-- Hospital Stock Live Audit (Visible when Hospital Store is selected) -->
                            <div id="sidebar-hospital-stock-box" class="p-2 border rounded mb-2 small d-none" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-color: #86efac !important;">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold text-success"><i class="mdi mdi-store"></i> Store Inventory</span>
                                    <span class="badge bg-success" id="sidebar-stock-status-pill">In Stock</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Store:</span>
                                    <strong id="sidebar-stock-store-name" class="text-dark text-truncate" style="max-width: 140px;">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Doses to Deduct:</span>
                                    <strong id="sidebar-stock-total-deduct" class="text-dark">0</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Antigens Linked:</span>
                                    <strong id="sidebar-stock-linked-count" class="text-dark">0 / 0</strong>
                                </div>
                                <div id="sidebar-stock-warning" class="alert alert-warning py-1 px-2 mt-1 mb-0 small d-none">
                                    <i class="mdi mdi-alert"></i> <span id="sidebar-stock-warning-text">Stock shortage detected!</span>
                                </div>
                            </div>

                            <!-- Live Validation Feedback -->
                            <div id="sidebar-validation-alert" class="alert alert-warning py-1 px-2 mb-0 small d-none">
                                <i class="mdi mdi-alert"></i> <span id="sidebar-validation-text">Please enter tallies before proceeding.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================== -->
                <!-- STEP 3: COLD CHAIN RECONCILIATION, REVIEW & SAVE                -->
                <!-- ============================================================== -->
                <div class="outreach-step-pane d-none" id="outreach-step-3">
                    <div class="outreach-review-container">
                        <!-- Dual-Column Master-Detail Layout -->
                        <div class="row g-3">
                            <!-- Left Column: Session Summary, Cold Chain & Cohort Mini-Cards (5 of 12 cols / ~42%) -->
                            <div class="col-lg-5">
                                <!-- Campaign Header Card -->
                                <div class="outreach-receipt-card mb-3 p-3">
                                    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                                        <h6 class="fw-bold mb-0 text-dark">
                                            <i class="mdi mdi-map-marker-radius text-danger"></i> Session &amp; Logistics Overview
                                        </h6>
                                        <span class="badge bg-primary px-2 py-1" id="review-total-doses-badge">0 Doses Total</span>
                                    </div>
                                    <div class="receipt-grid-compact">
                                        <div class="receipt-meta-item">
                                            <span class="text-muted small">Settlement / Ward:</span>
                                            <strong id="review-location-text" class="d-block text-dark text-truncate">-</strong>
                                        </div>
                                        <div class="receipt-meta-item">
                                            <span class="text-muted small">Outreach Date:</span>
                                            <strong id="review-date-text" class="d-block text-dark">-</strong>
                                        </div>
                                        <div class="receipt-meta-item">
                                            <span class="text-muted small">Stock Source:</span>
                                            <strong id="review-stock-source-text" class="d-block text-dark text-truncate">-</strong>
                                        </div>
                                        <div class="receipt-meta-item">
                                            <span class="text-muted small">Cold Chain &amp; VVM:</span>
                                            <strong id="review-coldchain-text" class="d-block text-dark text-truncate">-</strong>
                                        </div>
                                        <div class="receipt-meta-item">
                                            <span class="text-muted small">Team Lead:</span>
                                            <strong id="review-lead-text" class="d-block text-dark text-truncate">-</strong>
                                        </div>
                                        <div class="receipt-meta-item">
                                            <span class="text-muted small">Strategy:</span>
                                            <strong id="review-strategy-text" class="d-block text-dark text-truncate">-</strong>
                                        </div>
                                    </div>
                                </div>

                                <!-- Cohort Breakdown Grid (6 KPI Mini-Tiles) -->
                                <div class="outreach-receipt-card mb-3 p-3">
                                    <h6 class="fw-bold mb-2 text-dark">
                                        <i class="mdi mdi-account-group text-primary"></i> Demographic Cohort Reach
                                    </h6>
                                    <div class="receipt-cohort-tiles">
                                        <div class="cohort-tile tile-blue">
                                            <span class="tile-label"><i class="mdi mdi-baby"></i> Infants &lt; 1y</span>
                                            <span class="tile-val" id="review-stat-infants">0</span>
                                        </div>
                                        <div class="cohort-tile tile-amber">
                                            <span class="tile-label"><i class="mdi mdi-human-child"></i> Children 1–4y</span>
                                            <span class="tile-val" id="review-stat-children">0</span>
                                        </div>
                                        <div class="cohort-tile tile-pink">
                                            <span class="tile-label"><i class="mdi mdi-gender-female"></i> Girls 9–14y</span>
                                            <span class="tile-val" id="review-stat-hpv">0</span>
                                        </div>
                                        <div class="cohort-tile tile-purple">
                                            <span class="tile-label"><i class="mdi mdi-mother-nurse"></i> Pregnant Td</span>
                                            <span class="tile-val" id="review-stat-pregnant">0</span>
                                        </div>
                                        <div class="cohort-tile tile-indigo">
                                            <span class="tile-label"><i class="mdi mdi-gender-female"></i> WRA 15–49y</span>
                                            <span class="tile-val" id="review-stat-wra">0</span>
                                        </div>
                                        <div class="cohort-tile tile-red">
                                            <span class="tile-label"><i class="mdi mdi-delete-variant"></i> Wasted Doses</span>
                                            <span class="tile-val" id="review-stat-wasted">0</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Epidemiological Compliance Notice -->
                                <div class="alert alert-info py-2 px-3 mb-0 small">
                                    <i class="mdi mdi-information-outline"></i>
                                    <strong>NHMIS 2019 / DHIS2 Compliance:</strong> Committing this session commits tallies directly into the authoritative register and aggregates to Routine Outreach Items 63–87 without requiring individual patient registrations.
                                </div>
                            </div>

                            <!-- Right Column: Administered Antigens Breakdown & Hospital Stock Deduction (7 of 12 cols / ~58%) -->
                            <div class="col-lg-7">
                                <!-- Antigens Breakdown Card -->
                                <div class="outreach-receipt-card mb-3 p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="fw-bold text-dark mb-0">
                                            <i class="mdi mdi-format-list-numbered text-primary"></i> Administered Antigens Tally Breakdown
                                        </h6>
                                        <span class="small text-muted" id="review-active-antigen-count">0 recorded items</span>
                                    </div>
                                    <div class="table-responsive border rounded" style="max-height: 280px; overflow-y: auto;">
                                        <table class="table table-sm table-striped mb-0">
                                            <thead class="table-light sticky-top">
                                                <tr>
                                                    <th>Antigen</th>
                                                    <th>Dose</th>
                                                    <th>Target Cohort</th>
                                                    <th>Sex</th>
                                                    <th style="text-align: center;">Headcount</th>
                                                    <th style="text-align: center;">Wasted</th>
                                                </tr>
                                            </thead>
                                            <tbody id="review-tallies-tbody">
                                                <!-- Populated dynamically via JS -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Hospital Stock Deduction Ledger (Shown when hospital stock is selected) -->
                                <div id="review-stock-ledger-card" class="outreach-receipt-card p-3 rounded border d-none" style="background: #f8fafc; border-color: #cbd5e1 !important;">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="fw-bold text-dark mb-0">
                                            <i class="mdi mdi-database-export text-success"></i> Hospital Store Inventory Deduction Ledger
                                        </h6>
                                        <span class="badge bg-success" id="review-stock-deduction-badge">Auto-Deduction Active</span>
                                    </div>
                                    <p class="text-muted small mb-2">
                                        The following stock will be deducted from <strong id="review-ledger-store-name">-</strong> using FIFO batch allocation:
                                    </p>
                                    <div id="review-ledger-deficit-alert" class="alert alert-danger py-2 px-3 mb-2 small d-none">
                                        <!-- Populated dynamically via JS -->
                                    </div>
                                    <div class="table-responsive border rounded bg-white" style="max-height: 200px; overflow-y: auto;">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead class="table-light small sticky-top">
                                                <tr>
                                                    <th>Antigen</th>
                                                    <th>Mapped Store Product</th>
                                                    <th>Batch Allocation</th>
                                                    <th style="text-align: right;">Store Stock</th>
                                                    <th style="text-align: right;">Deduct Qty</th>
                                                    <th style="text-align: right;">Post Balance</th>
                                                    <th style="text-align: center;">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody id="review-stock-ledger-tbody" class="small">
                                                <!-- Dynamically populated via JS -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer Controls -->
            <div class="modal-footer bg-light d-flex justify-content-between align-items-center py-2 px-3">
                <div>
                    <button type="button" class="btn btn-outline-secondary" id="btn-outreach-prev-step" style="display: none;">
                        <i class="mdi mdi-arrow-left"></i> Back
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" data-dismiss="modal" id="btn-outreach-cancel">
                        <i class="mdi mdi-close"></i> Cancel
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary fw-bold px-4" id="btn-outreach-next-step">
                        Next: Tallies Matrix <i class="mdi mdi-arrow-right"></i>
                    </button>
                    <button type="button" class="btn btn-success fw-bold px-4" id="btn-save-outreach-tally" style="display: none;">
                        <i class="mdi mdi-check-circle"></i> Commit Outreach Session
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Product Linker / Picker Modal for Outreach Antigens -->
<div class="modal fade" id="outreachProductPickerModal" tabindex="-1" aria-hidden="true" style="z-index: 1065;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, var(--hospital-primary, #011b33) 0%, #0284c7 100%);">
                <h5 class="modal-title fs-6 fw-bold">
                    <i class="mdi mdi-link-variant"></i> Link Hospital Product &bull; <span id="picker-antigen-label" class="text-warning">Antigen</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="input-group mb-2">
                    <span class="input-group-text bg-white border-end-0"><i class="mdi mdi-magnify text-muted"></i></span>
                    <input type="text" class="form-control border-start-0 border-end-0" id="picker-product-search" placeholder="Type name, code, or category to search any hospital product..." autocomplete="off">
                    <span class="input-group-text bg-white border-start-0 d-none" id="picker-search-spinner">
                        <div class="spinner-border spinner-border-sm text-primary" role="status" style="width: 1rem; height: 1rem;"></div>
                    </span>
                    <button class="btn btn-outline-secondary" type="button" id="picker-search-clear-btn" title="Clear search">
                        <i class="mdi mdi-close"></i>
                    </button>
                </div>
                <!-- Quick Filter Chips -->
                <div class="d-flex align-items-center gap-1 mb-2 flex-wrap" id="picker-filter-chips">
                    <span class="small text-muted me-1 fw-bold">Filter:</span>
                    <button type="button" class="btn btn-xs btn-outline-secondary active picker-filter-chip" data-filter="all">All Items</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary picker-filter-chip" data-filter="in_stock">In-Stock Only</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary picker-filter-chip" data-filter="vaccines">Vaccines Only</button>
                </div>
                <div class="picker-store-info mb-2 small text-muted d-flex justify-content-between align-items-center">
                    <span><i class="mdi mdi-store text-primary"></i> Target Store: <strong id="picker-store-name" class="text-dark">-</strong></span>
                    <span class="badge bg-secondary-subtle text-secondary" id="picker-products-count">0 products</span>
                </div>
                <div class="table-responsive border rounded" style="max-height: 320px; overflow-y: auto;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Product Code</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th style="text-align: right;">Store Stock</th>
                                <th style="text-align: center;">Batches</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="picker-products-tbody" class="small">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2 bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-sm btn-outline-danger" id="picker-unlink-btn">
                    <i class="mdi mdi-link-off"></i> Unlink (No Product)
                </button>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
