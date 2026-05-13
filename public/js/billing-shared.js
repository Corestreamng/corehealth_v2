/**
 * BillingKit — Shared Billing Tab Module
 * Used in: Nursing Workbench, Maternity Workbench, Patient Procedures
 *
 * Each workbench must emit window.BILLING_KIT_CONFIG before loading this script:
 *
 *   window.BILLING_KIT_CONFIG = {
 *     csrf:                  '...',
 *     addServiceRoute:       '/nursing-workbench/billing/add-service',
 *     addLabRoute:           '/nursing-workbench/billing/add-lab-bill',
 *     addImagingRoute:       '/nursing-workbench/billing/add-imaging-bill',
 *     addConsumableRoute:    '/nursing-workbench/billing/add-consumable-bill',
 *     removeBillBase:        '/nursing-workbench/remove-bill',
 *     pendingBillsBase:      '/nursing-workbench/patient',
 *     serviceRequestsBase:   '/nursing-workbench/patient',
 *     searchServicesRoute:   '/nursing-workbench/search-services',
 *     searchProductsRoute:   '/nursing-workbench/search-products',
 *     productBatchesRoute:   '/nursing-workbench/product-batches',
 *     investigationCategoryId: '',
 *     imagingCategoryId:     6,
 *     resolvedStoreId:       '',
 *     resolvedStoreName:     '',
 *     showMedicationOption:  true,
 *     canBundle:             false, // true for procedure context
 *     procedureId:           null,  // procedure ID if canBundle is true
 *   };
 */
window.BillingKit = (function ($) {
    'use strict';

    let _cfg = {};
    let _patientId = null;
    let _billingHistoryTable = null;
    let _billingHistoryLoaded = false;

    // search timers / XHRs
    let _svcTimer = null, _svcXhr = null;
    let _labTimer = null, _labXhr = null;
    let _imgTimer = null, _imgXhr = null;
    let _conTimer = null, _conXhr = null;

    /* ═══════════════════════════════════════════════════════════
       HTML BUILDER
       ═══════════════════════════════════════════════════════════ */

    function _buildHtml() {
        let storeOpts = _cfg.resolvedStoreId
            ? '<option value="' + _cfg.resolvedStoreId + '" selected>' + _escHtml(_cfg.resolvedStoreName) + '</option>'
            : '<option value="">-- No store assigned --</option>';

        if (_cfg.accessibleStores && Array.isArray(_cfg.accessibleStores)) {
            let opts = "";
            _cfg.accessibleStores.forEach(function(s) {
                const sel = s.id == _cfg.resolvedStoreId ? "selected" : "";
                opts += '<option value="' + s.id + '" ' + sel + '>' + _escHtml(s.store_name || s.name) + '</option>';
            });
            if (opts) storeOpts = opts;
        }

        const bundleHtml = (id) => _cfg.canBundle ? `
            <div class="form-group mb-3">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="${id}" checked>
                    <label class="custom-control-label" for="${id}">
                        <i class="mdi mdi-package-variant"></i> Included in Procedure Bundle
                    </label>
                </div>
            </div>` : '';

        const medicationSection = _cfg.showMedicationOption !== false ? `
            <div class="form-group mb-3">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="consumable-is-medication">
                    <label class="custom-control-label" for="consumable-is-medication">
                        <i class="mdi mdi-pill"></i> This is a medication (add dose/frequency)
                    </label>
                </div>
            </div>
            <div id="consumable-dose-section" style="display:none;">
                <div class="form-row">
                    <div class="form-group col-md-12">
                        <label for="consumable-dose"><i class="mdi mdi-pencil-outline"></i> Dose / Frequency</label>
                        <input type="text" class="form-control" id="consumable-dose" placeholder="e.g. 500mg BD x 5 days">
                        <small class="form-text text-muted">This item will also appear in the patient's prescription history.</small>
                    </div>
                </div>
            </div>` : '';

        return `<div class="billing-container p-3">
    <ul class="nav nav-tabs mb-3" id="billing-sub-tabs" role="tablist">
        <li class="nav-item"><a class="nav-link active" id="billing-services-tab" data-toggle="tab" href="#billing-services" role="tab"><i class="mdi mdi-medical-bag"></i> Services</a></li>
        <li class="nav-item"><a class="nav-link" id="billing-labs-tab" data-toggle="tab" href="#billing-labs" role="tab"><i class="mdi mdi-flask"></i> Labs</a></li>
        <li class="nav-item"><a class="nav-link" id="billing-imaging-tab" data-toggle="tab" href="#billing-imaging" role="tab"><i class="mdi mdi-radioactive"></i> Imaging</a></li>
        <li class="nav-item"><a class="nav-link" id="billing-consumables-tab" data-toggle="tab" href="#billing-consumables" role="tab"><i class="mdi mdi-package-variant"></i> Consumables</a></li>
        <li class="nav-item"><a class="nav-link" id="billing-pending-tab" data-toggle="tab" href="#billing-pending" role="tab"><i class="mdi mdi-clock-outline"></i> Pending Bills</a></li>
        <li class="nav-item"><a class="nav-link" id="billing-history-tab" data-toggle="tab" href="#billing-history" role="tab"><i class="mdi mdi-clipboard-list"></i> Billing History</a></li>
    </ul>

    <div class="tab-content" id="billing-sub-content">

        <!-- SERVICES -->
        <div class="tab-pane fade show active" id="billing-services" role="tabpanel">
            <div class="card-modern">
                <div class="card-header bg-warning py-2"><h6 class="mb-0"><i class="mdi mdi-medical-bag"></i> Add Service</h6></div>
                <div class="card-body">
                    <form id="service-billing-form">
                        <div class="form-row">
                            <div class="form-group col-md-6" style="position:relative;">
                                <label for="service-search"><i class="mdi mdi-magnify"></i> Search Service *</label>
                                <input type="text" class="form-control" id="service-search" placeholder="Type to search..." autocomplete="off">
                                <input type="hidden" id="service-id">
                                <input type="hidden" id="service-unit-price" value="0">
                                <ul class="list-group" id="service-search-results" style="display:none;position:absolute;z-index:1050;max-height:280px;overflow-y:auto;width:100%;left:0;"></ul>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="service-qty"><i class="mdi mdi-numeric"></i> Qty *</label>
                                <input type="number" class="form-control" id="service-qty" min="1" value="1" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="service-price"><i class="mdi mdi-currency-ngn"></i> Total</label>
                                <input type="text" class="form-control" id="service-price" readonly placeholder="Auto-calculated">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="service-notes"><i class="mdi mdi-note-text"></i> Notes</label>
                            <textarea class="form-control" id="service-notes" rows="2" placeholder="Any additional notes..."></textarea>
                        </div>
                        ${bundleHtml('service-is-bundled')}
                        <div class="form-actions text-right">
                            <button type="submit" class="btn btn-warning"><i class="mdi mdi-plus"></i> Add Service</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- LABS -->
        <div class="tab-pane fade" id="billing-labs" role="tabpanel">
            <div class="card-modern">
                <div class="card-header bg-success text-white py-2"><h6 class="mb-0"><i class="mdi mdi-flask"></i> Direct Lab Billing</h6></div>
                <div class="card-body">
                    <div class="alert alert-light py-2 px-3 mb-3 small" style="border-left:4px solid #28a745;">
                        <i class="mdi mdi-information-outline text-success"></i> Creates a lab request <strong>and bills it directly</strong>.
                    </div>
                    <form id="lab-billing-form">
                        <div class="form-row">
                            <div class="form-group col-md-8" style="position:relative;">
                                <label for="lab-billing-search"><i class="mdi mdi-magnify"></i> Search Lab Service *</label>
                                <input type="text" class="form-control" id="lab-billing-search" placeholder="Type to search lab services..." autocomplete="off">
                                <input type="hidden" id="lab-billing-id">
                                <ul class="list-group" id="lab-billing-search-results" style="display:none;position:absolute;z-index:1050;max-height:280px;overflow-y:auto;width:100%;left:0;"></ul>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="lab-billing-price"><i class="mdi mdi-currency-ngn"></i> Price</label>
                                <input type="text" class="form-control" id="lab-billing-price" readonly placeholder="Auto-calculated">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="lab-billing-notes"><i class="mdi mdi-note-text"></i> Clinical Notes</label>
                            <textarea class="form-control" id="lab-billing-notes" rows="2" placeholder="Any clinical notes..."></textarea>
                        </div>
                        ${bundleHtml('lab-is-bundled')}
                        <div class="form-actions text-right">
                            <button type="submit" class="btn btn-success"><i class="mdi mdi-flask-plus"></i> Add Lab Bill</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- IMAGING -->
        <div class="tab-pane fade" id="billing-imaging" role="tabpanel">
            <div class="card-modern">
                <div class="card-header py-2" style="background:#6f42c1;color:white;"><h6 class="mb-0"><i class="mdi mdi-radioactive"></i> Direct Imaging Billing</h6></div>
                <div class="card-body">
                    <div class="alert alert-light py-2 px-3 mb-3 small" style="border-left:4px solid #6f42c1;">
                        <i class="mdi mdi-information-outline" style="color:#6f42c1;"></i> Creates an imaging request <strong>and bills it directly</strong>.
                    </div>
                    <form id="imaging-billing-form">
                        <div class="form-row">
                            <div class="form-group col-md-8" style="position:relative;">
                                <label for="imaging-billing-search"><i class="mdi mdi-magnify"></i> Search Imaging Service *</label>
                                <input type="text" class="form-control" id="imaging-billing-search" placeholder="Type to search imaging services..." autocomplete="off">
                                <input type="hidden" id="imaging-billing-id">
                                <ul class="list-group" id="imaging-billing-search-results" style="display:none;position:absolute;z-index:1050;max-height:280px;overflow-y:auto;width:100%;left:0;"></ul>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="imaging-billing-price"><i class="mdi mdi-currency-ngn"></i> Price</label>
                                <input type="text" class="form-control" id="imaging-billing-price" readonly placeholder="Auto-calculated">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="imaging-billing-notes"><i class="mdi mdi-note-text"></i> Clinical Notes</label>
                            <textarea class="form-control" id="imaging-billing-notes" rows="2" placeholder="Any clinical notes..."></textarea>
                        </div>
                        ${bundleHtml('imaging-is-bundled')}
                        <div class="form-actions text-right">
                            <button type="submit" class="btn" style="background:#6f42c1;color:white;"><i class="mdi mdi-radioactive"></i> Add Imaging Bill</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- CONSUMABLES -->
        <div class="tab-pane fade" id="billing-consumables" role="tabpanel">
            <div class="card-modern">
                <div class="card-header bg-info text-white py-2"><h6 class="mb-0"><i class="mdi mdi-package-variant"></i> Add Consumable</h6></div>
                <div class="card-body">
                    <div class="store-selection-panel mb-4 p-3 rounded" style="background:linear-gradient(135deg,#e1f5fe 0%,#b3e5fc 100%);border:2px solid #4fc3f7;">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <label class="form-label fw-bold mb-2" style="font-size:1rem;"><i class="mdi mdi-store text-info"></i> Step 1: Select Dispensing Store</label>
                                <select id="consumable-store" class="form-control form-control-lg" style="border:2px solid #0288d1;font-weight:500;" required>
                                    ${storeOpts}
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div id="consumable-store-info" class="p-3 bg-white rounded shadow-sm" style="display:none;">
                                    <h6 class="text-info mb-2"><i class="mdi mdi-package-variant"></i> Store Stock</h6>
                                    <div id="consumable-store-stock-summary" class="small"></div>
                                </div>
                                <div id="consumable-store-placeholder" class="p-3 text-muted text-center">
                                    <i class="mdi mdi-arrow-left-bold mdi-24px"></i>
                                    <p class="mb-0 small">Select store first, then search product</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <form id="consumable-billing-form">
                        <div class="form-row">
                            <div class="form-group col-md-5" style="position:relative;">
                                <label for="consumable-search"><i class="mdi mdi-magnify"></i> Step 2: Search Consumable *</label>
                                <input type="text" class="form-control" id="consumable-search" placeholder="Type to search for products..." autocomplete="off">
                                <input type="hidden" id="consumable-id">
                                <ul class="list-group" id="consumable-search-results" style="display:none;position:absolute;z-index:1050;max-height:280px;overflow-y:auto;width:100%;left:0;"></ul>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="consumable-quantity"><i class="mdi mdi-numeric"></i> Qty *</label>
                                <input type="number" class="form-control" id="consumable-quantity" min="1" value="1" required>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="consumable-packaging"><i class="mdi mdi-package-variant"></i> Unit</label>
                                <select class="form-control" id="consumable-packaging">
                                    <option value="" data-base="1">Base Unit</option>
                                </select>
                                <small class="text-muted" id="consumable-base-equiv" style="display:none;">= <strong id="consumable-base-qty">0</strong> <span id="consumable-base-unit-name">units</span></small>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="consumable-price"><i class="mdi mdi-currency-ngn"></i> Total</label>
                                <input type="text" class="form-control" id="consumable-price" readonly placeholder="Auto">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-5">
                                <label for="consumable-batch-select">
                                    <i class="mdi mdi-package-variant"></i> Batch <span class="badge badge-info badge-sm" title="FIFO">FIFO</span>
                                </label>
                                <select class="form-control" id="consumable-batch-select">
                                    <option value="">-- Select product first --</option>
                                </select>
                                <input type="hidden" id="consumable-batch-id">
                            </div>
                        </div>
                        <div id="consumable-selected-stock" class="alert alert-light mb-3" style="display:none;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong id="consumable-selected-name">-</strong>
                                    <br><small class="text-muted" id="consumable-selected-code">-</small>
                                </div>
                                <div id="consumable-stock-info" class="text-right"></div>
                            </div>
                            <div id="consumable-batch-info" class="mt-2 pt-2 border-top small" style="display:none;">
                                <i class="mdi mdi-information-outline text-info"></i>
                                <span id="consumable-batch-detail">FIFO batch will be auto-selected</span>
                            </div>
                        </div>
                        ${medicationSection}
                        ${bundleHtml('consumable-is-bundled')}
                        <div class="form-actions text-right">
                            <button type="submit" class="btn btn-info btn-lg"><i class="mdi mdi-plus"></i> Add Consumable</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- PENDING BILLS -->
        <div class="tab-pane fade" id="billing-pending" role="tabpanel">
            <div class="card-modern">
                <div class="card-header py-2"><h6 class="mb-0"><i class="mdi mdi-clock-outline"></i> Pending Bills</h6></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover" id="pending-bills-table" style="width:100%">
                            <thead><tr>
                                <th>Item</th><th>Type</th><th>Qty</th>
                                <th>Amount</th><th>Added By</th><th>Date</th><th>Action</th>
                            </tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- BILLING HISTORY -->
        <div class="tab-pane fade" id="billing-history" role="tabpanel">
            <div class="row mb-3">
                <div class="col-md-3"><div class="bh-stat-card bh-stat-purple"><div class="bh-stat-icon"><i class="mdi mdi-clipboard-list mdi-24px"></i></div><div><div class="bh-stat-value" id="bh-total-requests">0</div><div class="bh-stat-label">Total Requests</div></div></div></div>
                <div class="col-md-3"><div class="bh-stat-card bh-stat-green"><div class="bh-stat-icon"><i class="mdi mdi-shield-check mdi-24px"></i></div><div><div class="bh-stat-value" id="bh-hmo-covered">₦0.00</div><div class="bh-stat-label">HMO Covered</div></div></div></div>
                <div class="col-md-3"><div class="bh-stat-card bh-stat-pink"><div class="bh-stat-icon"><i class="mdi mdi-cash mdi-24px"></i></div><div><div class="bh-stat-value" id="bh-patient-payable">₦0.00</div><div class="bh-stat-label">Patient Payable</div></div></div></div>
                <div class="col-md-3"><div class="bh-stat-card bh-stat-blue"><div class="bh-stat-icon"><i class="mdi mdi-check-circle mdi-24px"></i></div><div><div class="bh-stat-value" id="bh-completed-count">0</div><div class="bh-stat-label">Completed</div></div></div></div>
            </div>
            <div class="card-modern mb-3">
                <div class="card-body py-2">
                    <form id="bh-filter-form" class="form-inline flex-wrap">
                        <div class="form-group mr-2 mb-2"><label class="mr-1 small font-weight-bold">From</label><input type="date" class="form-control form-control-sm" id="bh-date-from"></div>
                        <div class="form-group mr-2 mb-2"><label class="mr-1 small font-weight-bold">To</label><input type="date" class="form-control form-control-sm" id="bh-date-to"></div>
                        <div class="form-group mr-2 mb-2">
                            <select class="form-control form-control-sm" id="bh-type-filter">
                                <option value="">All Types</option>
                                <option value="lab">Lab Test</option>
                                <option value="imaging">Imaging</option>
                                <option value="product">Product/Drug</option>
                                <option value="service">Service</option>
                            </select>
                        </div>
                        <div class="form-group mr-2 mb-2">
                            <select class="form-control form-control-sm" id="bh-billing-filter">
                                <option value="">All Billing</option>
                                <option value="pending">Pending</option>
                                <option value="billed">Billed</option>
                                <option value="paid">Paid</option>
                            </select>
                        </div>
                        <div class="form-group mr-2 mb-2">
                            <select class="form-control form-control-sm" id="bh-delivery-filter">
                                <option value="">All Delivery</option>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div class="form-group mb-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary mr-1" id="bh-clear-filters" title="Clear Filters"><i class="mdi mdi-refresh"></i></button>
                            <button type="submit" class="btn btn-sm btn-primary"><i class="mdi mdi-filter"></i> Apply</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card-modern">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover" id="billing-history-table" style="width:100%">
                            <thead><tr>
                                <th>Date</th><th>Request #</th><th>Type</th><th>Service/Item</th>
                                <th>Price</th><th>HMO Covers</th><th>Payable</th>
                                <th>Billing</th><th>Delivery</th><th>Actions</th>
                            </tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /tab-content -->
</div><!-- /billing-container -->`;
    }

    /* ═══════════════════════════════════════════════════════════
       UTILITIES
       ═══════════════════════════════════════════════════════════ */

    function _escHtml(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function _notify(type, msg) {
        if (typeof showNotification === 'function') { showNotification(type, msg); return; }
        if (typeof toastr !== 'undefined') { toastr[type === 'error' ? 'error' : 'success'](msg); return; }
        alert(msg);
    }

    function _csrf() { return _cfg.csrf || $('meta[name="csrf-token"]').attr('content') || ''; }

    function _toNum(v, fallback) {
        const n = parseFloat(v);
        return Number.isFinite(n) ? n : (fallback || 0);
    }

    function _extractPrice(raw) {
        if (raw && typeof raw === 'object') {
            return _toNum(raw.sale_price ?? raw.initial_sale_price ?? raw.unit_price, 0);
        }
        return _toNum(raw, 0);
    }

    function _renderSearchResults(results, $container, onSelectFn) {
        if (!results || !Array.isArray(results) || !results.length) {
            $container.html('<li class="list-group-item billing-search-no-results text-muted">No services found</li>').show();
            return;
        }
        let html = '';
        results.forEach(function(svc) {
            const price = _extractPrice(svc.price);
            const hmo = svc.hmo || null;
            const payable = _toNum(svc.payable_amount ?? (hmo ? hmo.payable : null), price);
            const claims = _toNum(svc.claims_amount ?? (hmo ? hmo.claims : null), 0);
            const mode = String(svc.coverage_mode ?? (hmo ? hmo.mode : '') ?? '').toLowerCase();
            const category = svc.category?.category_name || svc.category || '';
            const code = svc.service_code || svc.product_code || svc.code || '';
            const stock = svc.stock?.current_quantity ?? svc.current_quantity ?? null;

            const rawName = svc.name || svc.service_name || svc.product_name || '';
            const name = _escHtml(rawName);
            const escapedName = rawName.replace(/'/g, "\\'").replace(/\n/g, ' ').replace(/\r/g, ' ');
            const modeClass = mode ? (' mode-' + mode.replace(/[^a-z0-9_-]/g, '')) : '';
            const showCoverage = !!mode || claims > 0 || payable !== price;

            html += '<li class="list-group-item list-group-item-action" onclick="' + onSelectFn + '(' + svc.id + ', \'' + escapedName + '\', ' + payable + ')" style="cursor:pointer;">' +
                '<div class="billing-search-item-name"><b>' + name + '</b></div>' +
                '<div class="billing-search-item-meta">' +
                    '<span class="billing-search-item-price">' + (showCoverage ? '<s style="color:#999;font-weight:400">₦' + price.toLocaleString() + '</s>' : '₦' + price.toLocaleString()) + '</span>' +
                    (category ? '<span class="billing-search-item-badge">' + _escHtml(category) + '</span>' : '') +
                    (code ? '<span class="billing-search-item-badge">' + _escHtml(code) + '</span>' : '') +
                    (stock !== null ? '<span class="billing-search-item-stock text-muted">' + _escHtml(String(stock)) + ' avail.</span>' : '') +
                '</div>' +
                (showCoverage ? '<div class="billing-search-hmo-row">' +
                    '<span class="billing-search-hmo-label">Coverage:</span>' +
                    '<span class="billing-search-hmo-payable">Pay ₦' + payable.toLocaleString() + '</span>' +
                    '<span class="billing-search-hmo-claims">Claim ₦' + claims.toLocaleString() + '</span>' +
                    (mode ? '<span class="billing-search-hmo-mode' + modeClass + '">' + _escHtml(mode.toUpperCase()) + '</span>' : '') +
                '</div>' : '') +
            '</li>';
        });
        $container.html(html).show();
    }

    /* ═══════════════════════════════════════════════════════════
       SERVICE TAB
       ═══════════════════════════════════════════════════════════ */

    function _selectService(id, name, price) {
        $('#service-id').val(id);
        $('#service-unit-price').val(price);
        $('#service-search').val(name);
        _updateServiceTotal();
        $('#service-search-results').hide();
    }
    window._bkSelectService = _selectService; // exposed for inline onclick

    function _updateServiceTotal() {
        const unitPrice = parseFloat($('#service-unit-price').val()) || 0;
        const qty = parseInt($('#service-qty').val()) || 1;
        const total = unitPrice * qty;
        $('#service-price').val(total > 0 ? '₦' + total.toLocaleString() : '');
    }

    /* ═══════════════════════════════════════════════════════════
       LAB TAB
       ═══════════════════════════════════════════════════════════ */

    function _selectLabBilling(id, name, price) {
        $('#lab-billing-id').val(id);
        $('#lab-billing-search').val(name);
        $('#lab-billing-price').val('₦' + parseFloat(price).toLocaleString());
        $('#lab-billing-search-results').hide();
    }
    window._bkSelectLabBilling = _selectLabBilling;

    /* ═══════════════════════════════════════════════════════════
       IMAGING TAB
       ═══════════════════════════════════════════════════════════ */

    function _selectImagingBilling(id, name, price) {
        $('#imaging-billing-id').val(id);
        $('#imaging-billing-search').val(name);
        $('#imaging-billing-price').val('₦' + parseFloat(price).toLocaleString());
        $('#imaging-billing-search-results').hide();
    }
    window._bkSelectImagingBilling = _selectImagingBilling;

    /* ═══════════════════════════════════════════════════════════
       CONSUMABLE TAB
       ═══════════════════════════════════════════════════════════ */

    function _selectConsumable(id, name, unitPrice, code) {
        const storeId = $('#consumable-store').val();
        if (!storeId) { _notify('warning', 'Please select a store first'); $('#consumable-store').focus(); return; }

        $('#consumable-id').val(id);
        $('#consumable-search').val(name);
        _updateConsumablePrice(unitPrice);
        $('#consumable-search-results').hide();

        $('#consumable-selected-name').text(name);
        $('#consumable-selected-code').text('[' + (code || 'N/A') + ']');
        $('#consumable-selected-stock').show();
        _updateConsumableStockDisplay();
        _loadConsumablePackagings(id);

        _fetchBatchesForSelect(id, storeId, '#consumable-batch-select', function(resp) {
            if (resp.success && resp.total_available > 0) {
                $('#consumable-batch-info').show();
                const batch = resp.fifo_recommended || resp.batches[0];
                if (batch) {
                    $('#consumable-batch-detail').html(
                        '<strong>FIFO Recommended:</strong> ' + _escHtml(batch.batch_number) +
                        ' (' + batch.current_qty + ' avail)' +
                        (batch.expiry_formatted ? ' | Exp: ' + _escHtml(batch.expiry_formatted) : '')
                    );
                }
            } else {
                $('#consumable-batch-info').hide();
            }
        });
    }
    window._bkSelectConsumable = _selectConsumable;

    function _updateConsumablePrice(unitPrice) {
        const quantity = $('#consumable-quantity').val() || 1;
        const total = unitPrice * quantity;
        $('#consumable-price').val('₦' + total.toFixed(2));
        $('#consumable-quantity').data('unit-price', unitPrice);
    }

    function _updateConsumableStockDisplay() {
        const productId = $('#consumable-id').val();
        const storeId = $('#consumable-store').val();
        if (!productId || !storeId) return;
        $.get('/products/' + productId + '/stock-in-store/' + storeId, function(data) {
            if (data && data.available !== undefined) {
                const qty = data.available;
                const cls = qty > 10 ? 'text-success' : qty > 0 ? 'text-warning' : 'text-danger';
                const icon = qty > 0 ? 'mdi-package-variant' : 'mdi-package-variant-closed';
                $('#consumable-stock-info').html('<span class="' + cls + '"><i class="mdi ' + icon + '"></i> Stock: ' + qty + '</span>');
            }
        });
    }

    function _loadConsumablePackagings(productId) {
        const $select = $('#consumable-packaging');
        $select.html('<option value="" data-base="1">Base Unit</option>');
        $('#consumable-base-equiv').hide();

        $.get('/products/' + productId + '/packagings', function(resp) {
            const baseUnitName = resp.base_unit_name || 'units';
            $select.html('<option value="" data-base="1">' + _escHtml(baseUnitName) + ' (base)</option>');
            $('#consumable-base-unit-name').text(baseUnitName);

            if (resp.packagings && resp.packagings.length > 0) {
                resp.packagings.forEach(function(pkg) {
                    const sel = pkg.is_default_dispense ? ' selected' : '';
                    $select.append('<option value="' + pkg.id + '" data-base="' + pkg.base_unit_qty + '"' + sel + '>' +
                        _escHtml(pkg.name) + ' (' + parseFloat(pkg.base_unit_qty) + ' ' + baseUnitName + ')</option>');
                });
                _updateConsumableBaseEquiv();
            }

            if (resp.allow_decimal_qty) {
                $('#consumable-quantity').attr('step', 'any').attr('min', '0.01');
            } else {
                $('#consumable-quantity').attr('step', '1').attr('min', '1');
            }
        });
    }

    function _updateConsumableBaseEquiv() {
        const $sel = $('#consumable-packaging');
        const base = parseFloat($sel.find(':selected').data('base')) || 1;
        const qty = parseFloat($('#consumable-quantity').val()) || 0;
        const total = qty * base;
        if (base > 1) {
            $('#consumable-base-qty').text(parseFloat(total.toFixed(4)));
            $('#consumable-base-equiv').show();
        } else {
            $('#consumable-base-equiv').hide();
        }
    }

    function _fetchBatchesForSelect(productId, storeId, selectId, callback) {
        const $select = $(selectId);
        $select.html('<option value="">Loading batches...</option>').prop('disabled', true);

        $.ajax({
            url: _cfg.productBatchesRoute || '/nursing-workbench/product-batches',
            method: 'GET',
            data: { product_id: productId, store_id: storeId },
            success: function(resp) {
                $select.prop('disabled', false);
                if (resp.success && resp.batches && resp.batches.length > 0) {
                    let opts = '<option value="">Auto (FIFO) - Recommended</option>';
                    resp.batches.forEach(function(b, i) {
                        const expiryText = b.expiry_formatted ? ' | Exp: ' + b.expiry_formatted : '';
                        opts += '<option value="' + b.id + '" data-qty="' + b.current_qty + '">' +
                            _escHtml(b.batch_number) + ' (' + b.current_qty + ' avail)' + expiryText + (i === 0 ? ' ★' : '') + '</option>';
                    });
                    $select.html(opts);
                    if (callback) callback(resp);
                } else {
                    $select.html('<option value="">No batches in this store</option>');
                    if (callback) callback({ success: false, batches: [], total_available: 0 });
                }
            },
            error: function() {
                $select.prop('disabled', false);
                $select.html('<option value="">Error loading batches</option>');
                if (callback) callback({ success: false, error: true });
            }
        });
    }

    /* ═══════════════════════════════════════════════════════════
       PENDING BILLS
       ═══════════════════════════════════════════════════════════ */

    function loadPendingBills(patientId) {
        if (!patientId) return;
        if ($.fn.DataTable.isDataTable('#pending-bills-table')) {
            $('#pending-bills-table').DataTable().destroy();
        }
        $('#pending-bills-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: { url: (_cfg.pendingBillsBase || '/nursing-workbench/patient') + '/' + patientId + '/pending-bills', dataSrc: '' },
            columns: [
                { data: 'item_name' },
                { data: 'type', render: function(d) {
                    var cls = d === 'service' ? 'badge-primary' : 'badge-info';
                    return '<span class="badge ' + cls + '">' + d + '</span>';
                }},
                { data: 'qty' },
                { data: 'payable_amount', render: function(d, t, row) {
                    var html = '₦' + parseFloat(d || 0).toFixed(2);
                    if (row.claims_amount > 0) html += '<br><small class="text-success">Claims: ₦' + parseFloat(row.claims_amount).toFixed(2) + '</small>';
                    return html;
                }},
                { data: 'added_by' },
                { data: 'created_at' },
                { data: null, render: function(d) {
                    if (d.can_delete) return '<button class="btn btn-sm btn-danger bk-remove-bill-btn" data-id="' + d.id + '"><i class="fa fa-trash"></i></button>';
                    return '<span class="text-muted">-</span>';
                }}
            ],
            order: [[5, 'desc']],
            pageLength: 10,
            language: { emptyTable: 'No pending bills' }
        });
    }

    function _removeBillItem(id) {
        if (!confirm('Remove this bill item?')) return;
        $.ajax({
            url: (_cfg.removeBillBase || '/nursing-workbench/remove-bill') + '/' + id,
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': _csrf() },
            success: function(resp) {
                _notify('success', resp.message || 'Item removed');
                loadPendingBills(_patientId);
                if (_billingHistoryLoaded && _billingHistoryTable) _billingHistoryTable.ajax.reload();
            },
            error: function(xhr) {
                _notify('error', (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to remove item');
            }
        });
    }

    /* ═══════════════════════════════════════════════════════════
       BILLING HISTORY
       ═══════════════════════════════════════════════════════════ */

    function _initBillingHistory(patientId) {
        if (!patientId) return;
        const now = new Date();
        const first = new Date(now.getFullYear(), now.getMonth(), 1);
        const last  = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        $('#bh-date-from').val(first.toISOString().split('T')[0]);
        $('#bh-date-to').val(last.toISOString().split('T')[0]);
        _loadBillingHistoryTable(patientId);
        _loadBillingHistoryStats(patientId);
        _billingHistoryLoaded = true;
    }

    function _loadBillingHistoryTable(patientId) {
        if (_billingHistoryTable) { _billingHistoryTable.destroy(); _billingHistoryTable = null; }
        _billingHistoryTable = $('#billing-history-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: (_cfg.serviceRequestsBase || '/nursing-workbench/patient') + '/' + patientId + '/service-requests',
                data: function(d) {
                    d.date_from = $('#bh-date-from').val();
                    d.date_to   = $('#bh-date-to').val();
                    d.type_filter     = $('#bh-type-filter').val();
                    d.billing_filter  = $('#bh-billing-filter').val();
                    d.delivery_filter = $('#bh-delivery-filter').val();
                }
            },
            columns: [
                { data: 'date_formatted' }, { data: 'request_no' }, { data: 'type_badge' },
                { data: 'name' }, { data: 'price_formatted', className: 'text-right' },
                { data: 'hmo_covers_formatted', className: 'text-right text-success' },
                { data: 'payable_formatted', className: 'text-right text-primary font-weight-bold' },
                { data: 'billing_badge' }, { data: 'delivery_badge' },
                { data: 'actions', orderable: false, searchable: false }
            ],
            order: [[0, 'desc']], pageLength: 25,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
            language: { emptyTable: 'No service requests found', processing: '<i class="mdi mdi-loading mdi-spin mdi-24px"></i>' },
            drawCallback: function() { _loadBillingHistoryStats(_patientId); }
        });
    }

    function _loadBillingHistoryStats(patientId) {
        if (!patientId) return;
        $.ajax({
            url: (_cfg.serviceRequestsBase || '/nursing-workbench/patient') + '/' + patientId + '/service-requests-stats',
            data: { date_from: $('#bh-date-from').val(), date_to: $('#bh-date-to').val() },
            success: function(res) {
                if (res.success) {
                    $('#bh-total-requests').text(res.stats.total_requests);
                    $('#bh-hmo-covered').text(res.stats.hmo_covered);
                    $('#bh-patient-payable').text(res.stats.patient_payable);
                    $('#bh-completed-count').text(res.stats.completed);
                }
            }
        });
    }

    /* ═══════════════════════════════════════════════════════════
       EVENT BINDING (delegated to #billing-kit-root)
       ═══════════════════════════════════════════════════════════ */

    function _bindEvents() {
        const $root = $('#billing-kit-root');

        // ── Close dropdowns on outside click ──────────────
        $(document).on('click.billingKit', function(e) {
            if (!$(e.target).closest('#service-search, #service-search-results').length)    $('#service-search-results').hide();
            if (!$(e.target).closest('#lab-billing-search, #lab-billing-search-results').length) $('#lab-billing-search-results').hide();
            if (!$(e.target).closest('#imaging-billing-search, #imaging-billing-search-results').length) $('#imaging-billing-search-results').hide();
            if (!$(e.target).closest('#consumable-search, #consumable-search-results').length)  $('#consumable-search-results').hide();
        });

        // ── Service search input ───────────────────────────
        $root.on('input', '#service-search', function() {
            const q = $(this).val().trim();
            clearTimeout(_svcTimer);
            if (_svcXhr) { _svcXhr.abort(); _svcXhr = null; }
            if (q.length < 2) { $('#service-search-results').hide(); return; }
            $('#service-search-results').html('<li class="billing-search-no-results"><i class="mdi mdi-loading mdi-spin"></i> Searching...</li>').show();
            _svcTimer = setTimeout(function() {
                _svcXhr = $.ajax({
                    url: _cfg.searchServicesRoute,
                    data: { term: q, patient_id: _patientId || null },
                    dataType: 'json',
                    success: function(res) {
                        _svcXhr = null;
                        _renderSearchResults(res, $('#service-search-results'), '_bkSelectService');
                    }
                });
            }, 300);
        });

        // ── Service qty change ─────────────────────────────
        $root.on('input', '#service-qty', function() {
            if ($('#service-id').val()) _updateServiceTotal();
        });

        // ── Service form submit ────────────────────────────
        $root.on('submit', '#service-billing-form', function(e) {
            e.preventDefault();
            const svcId = $('#service-id').val();
            if (!svcId) { _notify('error', 'Please select a service'); return; }
            const $btn = $(this).find('button[type="submit"]');
            const orig = $btn.html();
            $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Adding...');
            $.ajax({
                url: _cfg.addServiceRoute, method: 'POST',
                data: { 
                    patient_id: _patientId, 
                    procedure_id: _cfg.procedureId || null,
                    service_id: svcId, 
                    qty: parseInt($('#service-qty').val()) || 1, 
                    notes: $('#service-notes').val(),
                    is_bundled: $('#service-is-bundled').is(':checked') ? 1 : 0
                },
                headers: { 'X-CSRF-TOKEN': _csrf() },
                success: function(r) {
                    $btn.prop('disabled', false).html(orig);
                    _notify('success', r.message || 'Service added');
                    $('#service-billing-form')[0].reset();
                    $('#service-id, #service-unit-price').val('0');
                    loadPendingBills(_patientId);
                    if (_billingHistoryLoaded && _billingHistoryTable) _billingHistoryTable.ajax.reload();
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).html(orig);
                    _notify('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to add service');
                }
            });
        });

        // ── Lab search input ───────────────────────────────
        $root.on('input', '#lab-billing-search', function() {
            const q = $(this).val().trim();
            clearTimeout(_labTimer);
            if (_labXhr) { _labXhr.abort(); _labXhr = null; }
            if (q.length < 2) { $('#lab-billing-search-results').hide(); return; }
            $('#lab-billing-search-results').html('<li class="list-group-item billing-search-no-results"><i class="mdi mdi-loading mdi-spin"></i> Searching...</li>').show();
            _labTimer = setTimeout(function() {
                _labXhr = $.ajax({
                    url: _cfg.searchServicesRoute,
                    data: { term: q, patient_id: _patientId || null, category_id: _cfg.investigationCategoryId || '' },
                    dataType: 'json',
                    success: function(res) { _labXhr = null; _renderSearchResults(res, $('#lab-billing-search-results'), '_bkSelectLabBilling'); }
                });
            }, 300);
        });

        // ── Lab form submit ────────────────────────────────
        $root.on('submit', '#lab-billing-form', function(e) {
            e.preventDefault();
            const svcId = $('#lab-billing-id').val();
            if (!svcId) { _notify('error', 'Please select a lab service'); return; }
            const $btn = $(this).find('button[type="submit"]');
            const orig = $btn.html();
            $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing...');
            $.ajax({
                url: _cfg.addLabRoute, method: 'POST',
                data: { 
                    patient_id: _patientId, 
                    procedure_id: _cfg.procedureId || null,
                    service_id: svcId, 
                    note: $('#lab-billing-notes').val(),
                    is_bundled: $('#lab-is-bundled').is(':checked') ? 1 : 0
                },
                headers: { 'X-CSRF-TOKEN': _csrf() },
                success: function(r) {
                    $btn.prop('disabled', false).html(orig);
                    _notify('success', r.message || 'Lab request created');
                    $('#lab-billing-form')[0].reset();
                    $('#lab-billing-id').val('');
                    loadPendingBills(_patientId);
                    if (_billingHistoryLoaded && _billingHistoryTable) _billingHistoryTable.ajax.reload();
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).html(orig);
                    _notify('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to create lab request');
                }
            });
        });

        // ── Imaging search input ───────────────────────────
        $root.on('input', '#imaging-billing-search', function() {
            const q = $(this).val().trim();
            clearTimeout(_imgTimer);
            if (_imgXhr) { _imgXhr.abort(); _imgXhr = null; }
            if (q.length < 2) { $('#imaging-billing-search-results').hide(); return; }
            $('#imaging-billing-search-results').html('<li class="list-group-item billing-search-no-results"><i class="mdi mdi-loading mdi-spin"></i> Searching...</li>').show();
            _imgTimer = setTimeout(function() {
                _imgXhr = $.ajax({
                    url: _cfg.searchServicesRoute,
                    data: { term: q, patient_id: _patientId || null, category_id: _cfg.imagingCategoryId || '' },
                    dataType: 'json',
                    success: function(res) { _imgXhr = null; _renderSearchResults(res, $('#imaging-billing-search-results'), '_bkSelectImagingBilling'); }
                });
            }, 300);
        });

        // ── Imaging form submit ────────────────────────────
        $root.on('submit', '#imaging-billing-form', function(e) {
            e.preventDefault();
            const svcId = $('#imaging-billing-id').val();
            if (!svcId) { _notify('error', 'Please select an imaging service'); return; }
            const $btn = $(this).find('button[type="submit"]');
            const orig = $btn.html();
            $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing...');
            $.ajax({
                url: _cfg.addImagingRoute, method: 'POST',
                data: { 
                    patient_id: _patientId, 
                    procedure_id: _cfg.procedureId || null,
                    service_id: svcId, 
                    note: $('#imaging-billing-notes').val(),
                    is_bundled: $('#imaging-is-bundled').is(':checked') ? 1 : 0
                },
                headers: { 'X-CSRF-TOKEN': _csrf() },
                success: function(r) {
                    $btn.prop('disabled', false).html(orig);
                    _notify('success', r.message || 'Imaging request created');
                    $('#imaging-billing-form')[0].reset();
                    $('#imaging-billing-id').val('');
                    loadPendingBills(_patientId);
                    if (_billingHistoryLoaded && _billingHistoryTable) _billingHistoryTable.ajax.reload();
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).html(orig);
                    _notify('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to create imaging request');
                }
            });
        });

        // ── Consumable store change ────────────────────────
        $root.on('change', '#consumable-store', function() {
            const storeId = $(this).val();
            if (storeId) {
                $('#consumable-store-placeholder').hide();
                $('#consumable-store-info').show();
                const productId = $('#consumable-id').val();
                if (productId) {
                    _updateConsumableStockDisplay();
                    _fetchBatchesForSelect(productId, storeId, '#consumable-batch-select');
                }
            } else {
                $('#consumable-store-placeholder').show();
                $('#consumable-store-info').hide();
                $('#consumable-batch-select').html('<option value="">-- Select store first --</option>');
            }
        });

        // ── Consumable search input ────────────────────────
        $root.on('input', '#consumable-search', function() {
            const q = $(this).val().trim();
            const storeId = $('#consumable-store').val();
            clearTimeout(_conTimer);
            if (_conXhr) { _conXhr.abort(); _conXhr = null; }
            if (q.length < 2) { $('#consumable-search-results').hide(); return; }
            $('#consumable-search-results').html('<li class="list-group-item billing-search-no-results"><i class="mdi mdi-loading mdi-spin"></i> Searching...</li>').show();
            _conTimer = setTimeout(function() {
                _conXhr = $.ajax({
                    url: _cfg.searchProductsRoute,
                    data: { term: q, store_id: storeId, patient_id: _patientId || null },
                    dataType: "json",
                    success: function(res) { _conXhr = null; _renderSearchResults(res, $('#consumable-search-results'), '_bkSelectConsumable'); }
                });
            }, 300);
        });

        // ── Consumable unit change ─────────────────────────
        $root.on('change', '#consumable-packaging', function() {
            _updateConsumableBaseEquiv();
        });

        // ── Consumable quantity change ─────────────────────
        $root.on('input', '#consumable-quantity', function() {
            const price = $(this).data('unit-price');
            if (price) _updateConsumablePrice(price);
            _updateConsumableBaseEquiv();
        });

        // ── Medication checkbox toggle ─────────────────────
        $root.on('change', '#consumable-is-medication', function() {
            if ($(this).is(':checked')) $('#consumable-dose-section').slideDown();
            else $('#consumable-dose-section').slideUp();
        });

        // ── Consumable form submit ─────────────────────────
        $root.on('submit', '#consumable-billing-form', function(e) {
            e.preventDefault();
            const productId = $('#consumable-id').val();
            const storeId   = $('#consumable-store').val();
            if (!productId || !storeId) { _notify('error', 'Select product and store'); return; }
            const qty = $('#consumable-quantity').val();
            const batchId = $('#consumable-batch-select').val();
            const packagingId = $('#consumable-packaging').val();
            const isMed = $('#consumable-is-medication').is(':checked');

            const $btn = $(this).find('button[type="submit"]');
            const orig = $btn.html();
            $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Adding...');

            $.ajax({
                url: _cfg.addConsumableRoute, method: 'POST',
                data: {
                    patient_id: _patientId,
                    procedure_id: _cfg.procedureId || null,
                    store_id: storeId,
                    product_id: productId,
                    batch_id: batchId,
                    qty: qty,
                    packaging_id: packagingId,
                    dose: isMed ? $('#consumable-dose').val() : null,
                    is_bundled: $('#consumable-is-bundled').is(':checked') ? 1 : 0
                },
                headers: { 'X-CSRF-TOKEN': _csrf() },
                success: function(r) {
                    $btn.prop('disabled', false).html(orig);
                    _notify('success', r.message || 'Consumable added');
                    $('#consumable-billing-form')[0].reset();
                    $('#consumable-dose-section').hide();
                    $('#consumable-id').val('');
                    $('#consumable-selected-stock').hide();
                    loadPendingBills(_patientId);
                    if (_billingHistoryLoaded && _billingHistoryTable) _billingHistoryTable.ajax.reload();
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).html(orig);
                    _notify('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to add consumable');
                }
            });
        });

        // ── Remove bill button ─────────────────────────────
        $root.on('click', '.bk-remove-bill-btn', function() {
            _removeBillItem($(this).data('id'));
        });

        // ── History Filter change ──────────────────────────
        $root.on('change', '#bh-date-from, #bh-date-to, #bh-type-filter, #bh-billing-filter, #bh-delivery-filter', function() {
            if (_billingHistoryTable) _billingHistoryTable.ajax.reload();
        });

        // ── History Filter Clear ───────────────────────────
        $root.on('click', '#bh-clear-filters', function() {
            const now = new Date();
            const first = new Date(now.getFullYear(), now.getMonth(), 1);
            const last  = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            $('#bh-date-from').val(first.toISOString().split('T')[0]);
            $('#bh-date-to').val(last.toISOString().split('T')[0]);
            $('#bh-type-filter, #bh-billing-filter, #bh-delivery-filter').val('');
            if (_billingHistoryTable) _billingHistoryTable.ajax.reload();
        });
    }

    /* ═══════════════════════════════════════════════════════════
       PUBLIC API
       ═══════════════════════════════════════════════════════════ */

    return {
        init: function (config) {
            _cfg = config || {};
            const $root = $('#billing-kit-root');
            if (!$root.length) { console.error('BillingKit: #billing-kit-root not found.'); return; }

            $root.html(_buildHtml());
            _bindEvents();

            // Auto-load history tab when clicked for the first time
            $('a[href="#billing-history"]').on('shown.bs.tab', function (e) {
                if (!_billingHistoryLoaded && _patientId) {
                    _initBillingHistory(_patientId);
                }
            });

            // Auto-load pending tab
            $('a[href="#billing-pending"]').on('shown.bs.tab', function (e) {
                if (_patientId) loadPendingBills(_patientId);
            });
        },

        setPatient: function (patientId) {
            _patientId = patientId;
            _billingHistoryLoaded = false;
            _billingHistoryTable = null;

            // If pending tab is active, load it
            if ($('#billing-pending').hasClass('active')) {
                loadPendingBills(patientId);
            }
            // If history tab is active, load it
            if ($('#billing-history').hasClass('active')) {
                _initBillingHistory(patientId);
            }
        },

        refresh: function() {
            if (_patientId) {
                loadPendingBills(_patientId);
                if (_billingHistoryLoaded && _billingHistoryTable) _billingHistoryTable.ajax.reload();
            }
        }
    };

})(jQuery);
