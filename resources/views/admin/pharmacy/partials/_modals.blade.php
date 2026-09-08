<div class="modal fade" id="cartReviewModal" tabindex="-1" role="dialog" aria-labelledby="cartReviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cartReviewModalLabel">
                    <i class="mdi mdi-cart-check"></i> Selected Items
                    <span class="badge bg-light text-primary ms-2" id="modal-cart-count">0</span>
                </h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" id="cart-review-body">
                <!-- Populated by JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearAllSelections()">
                    <i class="mdi mdi-close-circle-outline"></i> Clear All
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="mdi mdi-close"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Dismiss Confirmation Modal -->
<div class="modal fade" id="dismissConfirmModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="mdi mdi-alert-circle"></i> Confirm Dismissal</h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="mdi mdi-trash-can-outline text-danger" style="font-size: 3rem;"></i>
                </div>
                <p class="text-center mb-3">Are you sure you want to dismiss the following <strong id="dismiss-count">0</strong> item(s)?</p>
                <div class="dismiss-items-preview p-3 bg-light rounded" id="dismiss-items-preview" style="max-height: 200px; overflow-y: auto;">
                    <!-- Items list will be populated here -->
                </div>
                <div class="alert alert-warning mt-3 mb-0">
                    <i class="mdi mdi-alert"></i> <strong>Warning:</strong> This action cannot be undone. Dismissed prescriptions will be removed from the queue.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="mdi mdi-close"></i> Cancel
                </button>
                <button type="button" class="btn btn-danger" id="confirm-dismiss-btn" onclick="confirmDismiss()">
                    <i class="mdi mdi-trash-can"></i> Yes, Dismiss Items
                </button>
            </div>
        </div>
    </div>
</div>

<!-- My Transactions Modal (Global Access) -->
<div class="modal fade" id="myTransactionsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--hospital-primary); color: white;">
                <h5 class="modal-title"><i class="mdi mdi-receipt"></i> My Transactions</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="my-transactions-modal-body">
                <!-- Filter Panel -->
                <div class="my-transactions-filter">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="font-weight-bold">Quick Date Filters</label>
                            <div class="btn-group btn-group-sm w-100" role="group">
                                <button type="button" class="btn btn-outline-primary my-trans-date-preset" data-preset="today">Today</button>
                                <button type="button" class="btn btn-outline-primary my-trans-date-preset" data-preset="yesterday">Yesterday</button>
                                <button type="button" class="btn btn-outline-primary my-trans-date-preset" data-preset="this_week">This Week</button>
                                <button type="button" class="btn btn-outline-primary my-trans-date-preset" data-preset="last_7_days">Last 7 Days</button>
                                <button type="button" class="btn btn-outline-primary my-trans-date-preset" data-preset="this_month">This Month</button>
                                <button type="button" class="btn btn-outline-primary my-trans-date-preset" data-preset="last_month">Last Month</button>
                                <button type="button" class="btn btn-outline-secondary my-trans-date-preset" data-preset="custom">Custom</button>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-2">
                            <label>From Date</label>
                            <input type="date" class="form-control" id="my-trans-from-date">
                        </div>
                        <div class="col-md-2">
                            <label>To Date</label>
                            <input type="date" class="form-control" id="my-trans-to-date">
                        </div>
                        <div class="col-md-2">
                            <label>Payment Type</label>
                            <select class="form-control" id="my-trans-payment-type">
                                <option value="">All Types</option>
                                <option value="CASH">Cash</option>
                                <option value="POS">POS/Card</option>
                                <option value="TRANSFER">Bank Transfer</option>
                                <option value="MOBILE">Mobile Money</option>
                                <option value="HMO">HMO</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>Bank</label>
                            <select class="form-control" id="my-trans-bank">
                                <option value="">All Banks</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>&nbsp;</label>
                            <div class="btn-group btn-block">
                                <button class="btn btn-primary" id="load-my-transactions">
                                    <i class="mdi mdi-filter"></i> Load
                                </button>
                                <button class="btn btn-success" id="export-my-transactions-excel">
                                    <i class="mdi mdi-file-excel"></i> Excel
                                </button>
                                <button class="btn btn-info" id="print-my-transactions">
                                    <i class="mdi mdi-printer"></i> Print
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Statistics -->
                <div class="my-transactions-summary" id="my-transactions-summary" style="display: none;">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="summary-stat-card">
                                <div class="stat-value" id="my-total-transactions">0</div>
                                <div class="stat-label">Total Transactions</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-stat-card">
                                <div class="stat-value" id="my-total-amount">₦0.00</div>
                                <div class="stat-label">Gross Amount</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-stat-card">
                                <div class="stat-value" id="my-total-discounts">₦0.00</div>
                                <div class="stat-label">Total Discounts</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-stat-card">
                                <div class="stat-value" id="my-net-amount">₦0.00</div>
                                <div class="stat-label">Net Amount</div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Section -->
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="chart-card">
                                <h6>Payment Method Distribution</h6>
                                <div style="position: relative; height: 200px;">
                                    <canvas id="my-trans-payment-chart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="chart-card">
                                <h6>Top 5 Products Dispensed</h6>
                                <div style="position: relative; height: 200px;">
                                    <canvas id="my-trans-products-chart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Breakdown by payment type -->
                    <div class="payment-type-breakdown" id="payment-type-breakdown"></div>
                </div>

                <!-- Transactions Table -->
                <div class="my-transactions-container">
                    <table class="table table-hover table-sm" id="my-transactions-table">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Patient</th>
                                <th>File No</th>
                                <th>Reference</th>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Method</th>
                                <th>Bank</th>
                                <th>Amount</th>
                                <th>Discount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="my-transactions-tbody">
                            <tr>
                                <td colspan="12" class="text-center text-muted py-5">
                                    <i class="mdi mdi-information-outline" style="font-size: 3rem;"></i>
                                    <p>Select a date range and click "Load" to fetch your transactions</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Transaction Details Modal -->
<div class="modal fade" id="transactionDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--hospital-primary); color: white;">
                <h5 class="modal-title"><i class="mdi mdi-file-document-outline"></i> Transaction Details</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="transaction-details-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="detail-group">
                            <label class="detail-label">Transaction ID</label>
                            <div class="detail-value" id="detail-id"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-group">
                            <label class="detail-label">Date & Time</label>
                            <div class="detail-value" id="detail-datetime"></div>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <div class="detail-group">
                            <label class="detail-label">Patient Name</label>
                            <div class="detail-value" id="detail-patient"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-group">
                            <label class="detail-label">File Number</label>
                            <div class="detail-value" id="detail-file-no"></div>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-12">
                        <div class="detail-group">
                            <label class="detail-label">Product</label>
                            <div class="detail-value" id="detail-product"></div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="detail-group">
                            <label class="detail-label">Quantity</label>
                            <div class="detail-value" id="detail-quantity"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="detail-group">
                            <label class="detail-label">Unit Price</label>
                            <div class="detail-value" id="detail-unit-price"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="detail-group">
                            <label class="detail-label">Subtotal</label>
                            <div class="detail-value" id="detail-subtotal"></div>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-4">
                        <div class="detail-group">
                            <label class="detail-label">Payment Method</label>
                            <div class="detail-value" id="detail-payment-method"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="detail-group">
                            <label class="detail-label">Bank</label>
                            <div class="detail-value" id="detail-bank"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="detail-group">
                            <label class="detail-label">Reference No</label>
                            <div class="detail-value" id="detail-reference"></div>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-4">
                        <div class="detail-group">
                            <label class="detail-label">Total Amount</label>
                            <div class="detail-value text-primary" style="font-size: 1.2rem; font-weight: bold;" id="detail-total"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="detail-group">
                            <label class="detail-label">Discount</label>
                            <div class="detail-value text-danger" style="font-size: 1.2rem; font-weight: bold;" id="detail-discount"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="detail-group">
                            <label class="detail-label">Net Amount</label>
                            <div class="detail-value text-success" style="font-size: 1.2rem; font-weight: bold;" id="detail-net"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="print-transaction-detail">
                    <i class="mdi mdi-printer"></i> Print Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========== PRODUCT ADAPTATION MODAL ========== -->
<!-- For adapting/changing prescribed products - shows billing impact for billed items -->
<div class="modal fade" id="productAdaptationModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #17a2b8, #138496); color: white;">
                <h5 class="modal-title"><i class="mdi mdi-swap-horizontal"></i> Adapt Prescription</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Step indicator -->
                <div class="adapt-steps mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="step active" id="adapt-step-1">
                            <span class="step-number">1</span>
                            <span class="step-label">Select Product</span>
                        </div>
                        <div class="step-line"></div>
                        <div class="step" id="adapt-step-2">
                            <span class="step-number">2</span>
                            <span class="step-label">Review Changes</span>
                        </div>
                        <div class="step-line"></div>
                        <div class="step" id="adapt-step-3">
                            <span class="step-number">3</span>
                            <span class="step-label">Confirm</span>
                        </div>
                    </div>
                </div>

                <!-- Status-specific guidance -->
                <div id="adapt-unbilled-notice" class="alert alert-info mb-3" style="display: none;">
                    <i class="mdi mdi-information-outline"></i>
                    <strong>Unbilled Item:</strong> This prescription has not been billed yet. You can freely change the product without affecting any billing records.
                </div>

                <div id="adapt-billed-notice" class="alert alert-warning mb-3" style="display: none;">
                    <i class="mdi mdi-alert-outline"></i>
                    <strong>Billed Item:</strong> This prescription has already been billed. Changing the product will automatically update the billing record with the new product's price.
                </div>

                <div class="row">
                    <!-- Left Column: Original & New Product Selection -->
                    <div class="col-md-7">
                        <div class="row mb-3">
                            <!-- Original Product Card -->
                            <div class="col-12 mb-3">
                                <div class="card-modern border-secondary h-100">
                                    <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                        <small class="text-muted"><i class="mdi mdi-pill"></i> ORIGINAL PRESCRIPTION</small>
                                        <span class="badge bg-secondary" id="adapt-original-status-badge">Unbilled</span>
                                    </div>
                                    <div class="card-body py-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 id="adapt-original-product" class="card-title text-primary mb-1">-</h5>
                                                <small class="text-muted" id="adapt-original-code">-</small>
                                            </div>
                                            <div class="text-end">
                                                <div class="fs-5 fw-bold text-success" id="adapt-original-total">₦0.00</div>
                                                <small class="text-muted">Total</small>
                                            </div>
                                        </div>
                                        <hr class="my-2">
                                        <div class="row small">
                                            <div class="col-4">
                                                <span class="text-muted">Unit Price:</span><br>
                                                <strong id="adapt-original-price">₦0.00</strong>
                                            </div>
                                            <div class="col-4">
                                                <span class="text-muted">Quantity:</span><br>
                                                <strong id="adapt-original-qty">-</strong>
                                            </div>
                                            <div class="col-4">
                                                <span class="text-muted">Dose/Freq:</span><br>
                                                <strong id="adapt-original-dose">-</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- New Product Selection Card -->
                            <div class="col-12">
                                <div class="card-modern border-success h-100">
                                    <div class="card-header bg-success text-white py-2">
                                        <i class="mdi mdi-arrow-right-bold"></i> SELECT NEW PRODUCT
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group mb-3">
                                            <label class="small text-muted mb-1">Search & Select Replacement Product</label>
                                            <select class="form-control" id="adapt-new-product" style="width: 100%;">
                                                <option value="">Type to search products...</option>
                                            </select>
                                        </div>

                                        <!-- New Product Details (shown after selection) -->
                                        <div id="adapt-new-product-details" style="display: none;">
                                            <div class="row mb-3">
                                                <div class="col-6">
                                                    <label class="small text-muted mb-1">Quantity</label>
                                                    <div class="input-group">
                                                        <button type="button" class="btn btn-outline-secondary" id="adapt-qty-minus">
                                                            <i class="mdi mdi-minus"></i>
                                                        </button>
                                                        <input type="number" class="form-control text-center" id="adapt-new-qty" min="1" value="1">
                                                        <button type="button" class="btn btn-outline-secondary" id="adapt-qty-plus">
                                                            <i class="mdi mdi-plus"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <label class="small text-muted mb-1">Unit Price</label>
                                                    <div class="form-control-plaintext fs-5 fw-bold text-success" id="adapt-new-price">₦0.00</div>
                                                </div>
                                            </div>

                                            <!-- Stock Availability -->
                                            <div class="card-modern bg-light mb-3">
                                                <div class="card-body py-2">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <strong class="small"><i class="mdi mdi-warehouse"></i> Stock Availability</strong>
                                                        <span class="badge" id="adapt-stock-badge">-</span>
                                                    </div>
                                                    <div id="adapt-store-stocks" class="small">
                                                        <!-- Store stocks will be populated here -->
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- HMO Coverage Info -->
                                            <div id="adapt-hmo-info" class="card-modern border-info mb-0" style="display: none;">
                                                <div class="card-body py-2">
                                                    <div class="d-flex justify-content-between align-items-center small">
                                                        <span><i class="mdi mdi-hospital-building text-info"></i> HMO Coverage</span>
                                                        <span class="badge bg-info" id="adapt-coverage-badge">-</span>
                                                    </div>
                                                    <div class="row mt-2 small">
                                                        <div class="col-6">
                                                            <span class="text-muted">Patient Pays:</span>
                                                            <strong class="text-danger ms-1" id="adapt-new-payable">₦0</strong>
                                                        </div>
                                                        <div class="col-6">
                                                            <span class="text-muted">HMO Covers:</span>
                                                            <strong class="text-success ms-1" id="adapt-new-claims">₦0</strong>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Placeholder when no product selected -->
                                        <div id="adapt-no-product-selected" class="text-center py-4 text-muted">
                                            <i class="mdi mdi-magnify" style="font-size: 2rem;"></i>
                                            <p class="mb-0 mt-2">Search and select a product above</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Calculations & Summary -->
                    <div class="col-md-5">
                        <!-- Price Calculation Summary -->
                        <div class="card-modern border-primary mb-3">
                            <div class="card-header bg-primary text-white py-2">
                                <i class="mdi mdi-calculator"></i> <strong>Price Calculation</strong>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted">Original Total:</td>
                                            <td class="text-end fw-bold" id="adapt-calc-original">₦0.00</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">New Total:</td>
                                            <td class="text-end fw-bold text-primary" id="adapt-calc-new">₦0.00</td>
                                        </tr>
                                        <tr class="border-top">
                                            <td><strong>Difference:</strong></td>
                                            <td class="text-end fs-5" id="adapt-calc-diff">
                                                <span class="text-muted">₦0.00</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div id="adapt-calc-note" class="small text-muted mt-2 text-center" style="display: none;">
                                    <!-- Note about price difference -->
                                </div>
                            </div>
                        </div>

                        <!-- Billing Impact Preview (only for billed items) -->
                        <div id="adapt-billing-impact" class="card-modern border-warning mb-3" style="display: none;">
                            <div class="card-header bg-warning text-dark py-2">
                                <strong><i class="mdi mdi-receipt"></i> Billing Impact</strong>
                            </div>
                            <div class="card-body py-2">
                                <table class="table table-sm table-bordered mb-0 small">
                                    <thead class="bg-light">
                                        <tr>
                                            <th></th>
                                            <th class="text-center">Current</th>
                                            <th class="text-center">New</th>
                                            <th class="text-center">Change</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Patient Pays</td>
                                            <td class="text-center" id="adapt-impact-payable-old">₦0</td>
                                            <td class="text-center" id="adapt-impact-payable-new">₦0</td>
                                            <td class="text-center" id="adapt-impact-payable-diff">-</td>
                                        </tr>
                                        <tr>
                                            <td>HMO Claims</td>
                                            <td class="text-center" id="adapt-impact-claims-old">₦0</td>
                                            <td class="text-center" id="adapt-impact-claims-new">₦0</td>
                                            <td class="text-center" id="adapt-impact-claims-diff">-</td>
                                        </tr>
                                        <tr class="table-secondary">
                                            <td><strong>Total</strong></td>
                                            <td class="text-center" id="adapt-impact-total-old">₦0</td>
                                            <td class="text-center" id="adapt-impact-total-new">₦0</td>
                                            <td class="text-center" id="adapt-impact-total-diff">-</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div id="adapt-impact-note" class="mt-2 small"></div>
                            </div>
                        </div>

                        <!-- Current Billing Info (for billed items) -->
                        <div id="adapt-current-billing" class="card-modern border-secondary mb-3" style="display: none;">
                            <div class="card-header bg-light py-2">
                                <strong><i class="mdi mdi-information-outline"></i> Current Billing</strong>
                            </div>
                            <div class="card-body py-2 small">
                                <div class="row">
                                    <div class="col-6">
                                        <span class="text-muted">Patient Pays:</span><br>
                                        <strong class="text-danger" id="adapt-current-payable">₦0.00</strong>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted">HMO Claims:</span><br>
                                        <strong class="text-success" id="adapt-current-claims">₦0.00</strong>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <span class="text-muted">Coverage Mode:</span>
                                    <strong id="adapt-current-coverage">-</strong>
                                </div>
                            </div>
                        </div>

                        <!-- Reason for Adaptation -->
                        <div class="card-modern border-secondary">
                            <div class="card-header bg-light py-2">
                                <strong><i class="mdi mdi-note-text"></i> Reason for Change</strong>
                                <span class="text-danger">*</span>
                            </div>
                            <div class="card-body py-2">
                                <textarea class="form-control" id="adapt-reason" rows="3" placeholder="Why are you changing this product? (e.g., out of stock, patient preference, generic substitution)..." required></textarea>
                                <div class="mt-2">
                                    <small class="text-muted">Quick reasons:</small>
                                    <div class="mt-1">
                                        <button type="button" class="btn btn-xs btn-outline-secondary adapt-quick-reason me-1 mb-1" data-reason="Out of stock">Out of stock</button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary adapt-quick-reason me-1 mb-1" data-reason="Generic substitution">Generic substitution</button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary adapt-quick-reason me-1 mb-1" data-reason="Patient request">Patient request</button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary adapt-quick-reason me-1 mb-1" data-reason="Doctor recommendation">Doctor advised</button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary adapt-quick-reason me-1 mb-1" data-reason="Cost consideration">Cost saving</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="adapt-product-request-id">
                <input type="hidden" id="adapt-billing-status">
                <input type="hidden" id="adapt-coverage-mode">
                <input type="hidden" id="adapt-original-price-value">
                <input type="hidden" id="adapt-original-qty-value">
            </div>
            <div class="modal-footer bg-light">
                <div class="d-flex justify-content-between w-100 align-items-center">
                    <div class="small text-muted" id="adapt-summary-text">
                        Select a new product to see the changes
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="mdi mdi-close"></i> Cancel
                        </button>
                        <button type="button" class="btn btn-info" id="confirm-adaptation" disabled>
                            <i class="mdi mdi-check"></i> Confirm Adaptation
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========== QUANTITY ADJUSTMENT MODAL ========== -->
<!-- For adjusting quantity - shows billing impact for billed items -->
<div class="modal fade" id="qtyAdjustmentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #ffc107, #e0a800); color: #212529;">
                <h5 class="modal-title"><i class="mdi mdi-counter"></i> Adjust Quantity</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Status-specific guidance -->
                <div id="qty-unbilled-notice" class="alert alert-info mb-3" style="display: none;">
                    <i class="mdi mdi-information-outline"></i>
                    <strong>Unbilled Item:</strong> This prescription has not been billed yet. You can freely adjust the quantity without affecting any billing records.
                </div>

                <div id="qty-billed-notice" class="alert alert-warning mb-3" style="display: none;">
                    <i class="mdi mdi-alert-outline"></i>
                    <strong>Billed Item:</strong> This prescription has already been billed. Changing the quantity will automatically update the billing amount.
                </div>

                <!-- Product Info Card -->
                <div class="card-modern border-primary mb-3">
                    <div class="card-body py-2">
                        <h6 class="card-title mb-1" id="qty-adjust-product-name">Product Name</h6>
                        <div class="row small">
                            <div class="col-6">
                                <span class="text-muted">Unit Price:</span>
                                <strong id="qty-adjust-unit-price">₦0.00</strong>
                            </div>
                            <div class="col-6">
                                <span class="text-muted">Current Qty:</span>
                                <strong id="qty-adjust-current">0</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Current billing info for billed items -->
                <div id="qty-current-billing" class="card-modern border-secondary mb-3" style="display: none;">
                    <div class="card-header bg-light py-2">
                        <small><strong><i class="mdi mdi-receipt"></i> Current Billing</strong></small>
                    </div>
                    <div class="card-body py-2">
                        <div class="row small">
                            <div class="col-6">
                                <span class="text-muted">Patient Pays:</span><br>
                                <strong class="text-danger" id="qty-current-payable">₦0.00</strong>
                            </div>
                            <div class="col-6">
                                <span class="text-muted">HMO Claims:</span><br>
                                <strong class="text-success" id="qty-current-claims">₦0.00</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quantity Input -->
                <div class="form-group mb-3">
                    <label><strong>New Quantity</strong> <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <button class="btn btn-outline-secondary" type="button" onclick="adjustQtyDecrement()">
                            <i class="mdi mdi-minus"></i>
                        </button>
                        <input type="number" class="form-control text-center" id="qty-adjust-new" min="1" value="1" style="font-size: 1.25rem; font-weight: bold;">
                        <button class="btn btn-outline-secondary" type="button" onclick="adjustQtyIncrement()">
                            <i class="mdi mdi-plus"></i>
                        </button>
                    </div>
                </div>

                <!-- Billing Impact Preview (only for billed items) -->
                <div id="qty-billing-impact" class="card-modern border-warning mb-3" style="display: none;">
                    <div class="card-header bg-warning text-dark py-2">
                        <strong><i class="mdi mdi-calculator"></i> Billing Impact</strong>
                    </div>
                    <div class="card-body py-2">
                        <table class="table table-sm mb-0">
                            <tr>
                                <td>Patient Payable:</td>
                                <td class="text-end">
                                    <span class="text-muted text-decoration-line-through" id="qty-impact-payable-old">₦0.00</span>
                                    <i class="mdi mdi-arrow-right mx-1"></i>
                                    <strong class="text-danger" id="qty-impact-payable-new">₦0.00</strong>
                                    <span id="qty-impact-payable-diff" class="badge ms-1">₦0.00</span>
                                </td>
                            </tr>
                            <tr>
                                <td>HMO Claims:</td>
                                <td class="text-end">
                                    <span class="text-muted text-decoration-line-through" id="qty-impact-claims-old">₦0.00</span>
                                    <i class="mdi mdi-arrow-right mx-1"></i>
                                    <strong class="text-success" id="qty-impact-claims-new">₦0.00</strong>
                                    <span id="qty-impact-claims-diff" class="badge ms-1">₦0.00</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Reason -->
                <div class="form-group">
                    <label><strong><i class="mdi mdi-note-text"></i> Reason for Adjustment</strong> <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="qty-adjust-reason" rows="2" placeholder="Why are you changing the quantity? (e.g., patient request, stock availability, clinical decision)..." required></textarea>
                    <small class="text-muted">This will be recorded for audit purposes</small>
                </div>

                <input type="hidden" id="qty-adjust-request-id">
                <input type="hidden" id="qty-adjust-billing-status">
                <input type="hidden" id="qty-adjust-price">
                <input type="hidden" id="qty-adjust-coverage-mode">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="mdi mdi-close"></i> Cancel
                </button>
                <button type="button" class="btn btn-warning" id="confirm-qty-adjustment">
                    <i class="mdi mdi-check"></i> Confirm Adjustment
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ========================================= --}}
{{-- PRE-BILLING PRICE ADJUSTMENT MODAL        --}}
{{-- ========================================= --}}
<div class="modal fade" id="priceAdjustmentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #3949ab, #1a237e); color: #fff;">
                <h5 class="modal-title"><i class="mdi mdi-cash-edit"></i> Adjust Price (Pre-Billing)</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Guidance -->
                <div class="alert alert-info mb-3">
                    <i class="mdi mdi-information-outline"></i>
                    <strong>Pre-Billing Override:</strong> This changes the unit price <em>before</em> billing. When this item is billed, the adjusted price will be used instead of the standard tariff/sale price.
                </div>

                <!-- Product Info Card -->
                <div class="card-modern border-primary mb-3">
                    <div class="card-body py-2">
                        <h6 class="card-title mb-1" id="price-adjust-product-name">Product Name</h6>
                        <small class="text-muted" id="price-adjust-product-code"></small>
                        <div class="row small mt-2">
                            <div class="col-4">
                                <span class="text-muted">Unit Price:</span><br>
                                <strong id="price-adjust-unit-price">₦0.00</strong>
                            </div>
                            <div class="col-4">
                                <span class="text-muted">Qty:</span><br>
                                <strong id="price-adjust-qty">0</strong>
                            </div>
                            <div class="col-4">
                                <span class="text-muted">Line Total:</span><br>
                                <strong id="price-adjust-line-total">₦0.00</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- HMO Tariff Info (shown only for HMO patients) -->
                <div id="price-adjust-tariff-info" class="card-modern border-info mb-3" style="display: none;">
                    <div class="card-header bg-light py-2">
                        <small><strong><i class="mdi mdi-shield-check"></i> HMO Tariff Reference</strong></small>
                    </div>
                    <div class="card-body py-2">
                        <div class="row small">
                            <div class="col-6">
                                <span class="text-muted">Tariff Patient Pays:</span><br>
                                <strong class="text-danger" id="price-adjust-tariff-payable">₦0.00</strong>
                                <small class="text-muted">/unit</small>
                            </div>
                            <div class="col-6">
                                <span class="text-muted">Tariff HMO Covers:</span><br>
                                <strong class="text-success" id="price-adjust-tariff-claims">₦0.00</strong>
                                <small class="text-muted">/unit</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Previous Override Notice -->
                <div id="price-adjust-existing-override" class="alert alert-warning mb-3" style="display: none;">
                    <i class="mdi mdi-alert-outline"></i>
                    <strong>Previously Adjusted:</strong> This item already has a price override of
                    <strong id="price-adjust-existing-value">₦0.00</strong>/unit
                    <span id="price-adjust-existing-meta" class="small text-muted"></span>
                </div>

                <!-- New Price Input -->
                <div class="form-group mb-3">
                    <label><strong>New Unit Price</strong> <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">₦</span>
                        <input type="number" class="form-control" id="price-adjust-new" min="0" step="0.01" value="0" style="font-size: 1.15rem; font-weight: bold;">
                    </div>
                    <small class="text-muted">Enter the new unit price (per single item)</small>
                </div>

                <!-- Billing Impact Preview -->
                <div id="price-billing-impact" class="card-modern border-warning mb-3">
                    <div class="card-header bg-warning text-dark py-2">
                        <strong><i class="mdi mdi-calculator"></i> Billing Preview (at time of billing)</strong>
                    </div>
                    <div class="card-body py-2">
                        <table class="table table-sm mb-0">
                            <tr>
                                <td>Patient Payable:</td>
                                <td class="text-end">
                                    <span class="text-muted text-decoration-line-through" id="price-impact-payable-old">₦0.00</span>
                                    <i class="mdi mdi-arrow-right mx-1"></i>
                                    <strong class="text-danger" id="price-impact-payable-new">₦0.00</strong>
                                    <span id="price-impact-payable-diff" class="badge ms-1">₦0.00</span>
                                </td>
                            </tr>
                            <tr id="price-impact-claims-row" style="display: none;">
                                <td>HMO Claims:</td>
                                <td class="text-end">
                                    <span class="text-muted text-decoration-line-through" id="price-impact-claims-old">₦0.00</span>
                                    <i class="mdi mdi-arrow-right mx-1"></i>
                                    <strong class="text-success" id="price-impact-claims-new">₦0.00</strong>
                                    <span id="price-impact-claims-diff" class="badge ms-1">₦0.00</span>
                                </td>
                            </tr>
                            <tr class="border-top">
                                <td><strong>Total Line Amount:</strong></td>
                                <td class="text-end">
                                    <strong id="price-impact-total-new">₦0.00</strong>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Warning for extreme price changes -->
                <div id="price-adjust-warning" class="alert alert-danger mb-3" style="display: none;">
                    <i class="mdi mdi-alert-circle"></i>
                    <span id="price-adjust-warning-text"></span>
                </div>

                <!-- Reason -->
                <div class="form-group">
                    <label><strong><i class="mdi mdi-note-text"></i> Reason for Price Adjustment</strong> <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="price-adjust-reason" rows="2" placeholder="Why are you adjusting the price? (e.g., HMO negotiated rate, management discount, price correction)..." required></textarea>
                    <small class="text-muted">This will be recorded for audit purposes</small>
                </div>

                <input type="hidden" id="price-adjust-request-id">
                <input type="hidden" id="price-adjust-original-price">
                <input type="hidden" id="price-adjust-coverage-mode">
                <input type="hidden" id="price-adjust-tariff-payable-unit">
                <input type="hidden" id="price-adjust-tariff-claims-unit">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="mdi mdi-close"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="confirm-price-adjustment">
                    <i class="mdi mdi-check"></i> Confirm Price Adjustment
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Adaptation Modal Steps */
.adapt-steps .step {
    display: flex;
    flex-direction: column;
    align-items: center;
    opacity: 0.5;
}
.adapt-steps .step.active {
    opacity: 1;
}
.adapt-steps .step-number {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #dee2e6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-bottom: 4px;
}
.adapt-steps .step.active .step-number {
    background: #17a2b8;
    color: white;
}
.adapt-steps .step-label {
    font-size: 0.75rem;
    color: #6c757d;
}
.adapt-steps .step-line {
    flex: 1;
    height: 2px;
    background: #dee2e6;
    margin: 0 10px;
    margin-bottom: 20px;
}

.batch-fifo-display {
    text-align: left;
}

.batch-fifo-display .badge {
    font-size: 0.7rem;
}

.cart-batch-cell .batch-select {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
    min-width: 140px;
}

.cart-batch-cell .form-select option.text-warning {
    background-color: #fff3cd;
}

.cart-batch-cell .form-select option.text-danger {
    background-color: #f8d7da;
}

/* Adaptation Modal Select2 Style */
#productAdaptationModal .select2-container {
    width: 100% !important;
}

/* Ensure Select2 dropdown appears above modals */
.select2-container--open {
    z-index: 9999 !important;
}

.select2-dropdown {
    z-index: 9999 !important;
}

/* Enhanced Adaptation Modal Styling */
#productAdaptationModal .modal-body {
    max-height: 75vh;
    overflow-y: auto;
}

#productAdaptationModal .card {
    transition: box-shadow 0.2s;
}

#productAdaptationModal .card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

#adapt-store-stocks .border-bottom:last-child {
    border-bottom: none !important;
}

.adapt-quick-reason {
    font-size: 0.75rem;
    padding: 0.2rem 0.5rem;
}

.adapt-quick-reason.btn-secondary {
    background-color: #17a2b8;
    border-color: #17a2b8;
    color: white;
}

/* Step indicator styling */
.adapt-steps .step {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    opacity: 0.5;
    transition: opacity 0.3s;
}

.adapt-steps .step.active {
    opacity: 1;
}

.adapt-steps .step-number {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.875rem;
}

.adapt-steps .step.active .step-number {
    background: #17a2b8;
    color: white;
}

.adapt-steps .step-line {
    flex: 1;
    height: 2px;
    background: #e9ecef;
}

.adapt-steps .step-label {
    font-size: 0.8rem;
    color: #6c757d;
}

.adapt-steps .step.active .step-label {
    color: #17a2b8;
    font-weight: 500;
}
</style>

<!-- Receipt Preview Modal -->
<div class="modal fade" id="receiptPreviewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="mdi mdi-receipt"></i> Receipt Preview</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="receipt-modal-tabs">
                    <button class="receipt-modal-tab active" data-format="a4">
                        <i class="mdi mdi-file-document"></i> A4 Receipt
                    </button>
                    <button class="receipt-modal-tab" data-format="thermal">
                        <i class="mdi mdi-receipt"></i> Thermal Receipt
                    </button>
                </div>
                <div class="receipt-modal-content">
                    <div class="receipt-modal-pane active" id="modal-receipt-a4"></div>
                    <div class="receipt-modal-pane" id="modal-receipt-thermal" style="display: none;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="modal-print-a4">
                    <i class="mdi mdi-printer"></i> Print A4
                </button>
                <button type="button" class="btn btn-info" id="modal-print-thermal">
                    <i class="mdi mdi-printer"></i> Print Thermal
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fa fa-times"></i> Close
                    </button>
            </div>
        </div>
    </div>
</div>

<!-- Dispense Cart Modal -->
<div class="modal fade" id="dispenseCartModal" tabindex="-1" role="dialog" aria-labelledby="dispenseCartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="dispenseCartModalLabel">
                    <i class="mdi mdi-cart-check"></i> Dispense Cart
                    <span id="modal-cart-count" class="badge bg-light text-success ms-2">0</span>
                </h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Store Selection in Modal -->
                <div class="p-3 bg-light border-bottom">
                    <div class="row align-items-center">
                        <div class="col-md-5">
                            <label class="form-label fw-bold mb-1 small">
                                <i class="mdi mdi-store text-success"></i> Dispensing Store
                            </label>
                            <select id="modal-store-select" class="form-select">
                                @if($resolvedStore)
                                    <option value="{{ $resolvedStore->id }}" selected>{{ $resolvedStore->store_name }}</option>
                                @else
                                    <option value="">-- No store assigned --</option>
                                @endif
                            </select>
                        </div>
                        <div class="col-md-7">
                            <div id="modal-store-status" class="small">
                                <span class="text-muted"><i class="mdi mdi-information-outline"></i> Select a store to check stock availability</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cart Empty State -->
                <div id="modal-cart-empty" class="text-center py-5">
                    <i class="mdi mdi-cart-outline mdi-48px text-muted"></i>
                    <p class="text-muted mt-2 mb-0">Your cart is empty</p>
                    <p class="text-muted small">Select items from the list and click "Add to Cart & Review"</p>
                </div>

                <!-- Cart Items Table -->
                <div id="modal-cart-content" style="display: none;">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-hover mb-0" id="modal-cart-table">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="width: 35%;">Medication</th>
                                    <th style="width: 10%;" class="text-center">Qty</th>
                                    <th style="width: 25%;" class="text-center">Batch Selection</th>
                                    <th style="width: 12%;" class="text-end">Amount</th>
                                    <th style="width: 10%;">Status</th>
                                    <th style="width: 8%;" class="text-center"></th>
                                </tr>
                            </thead>
                            <tbody id="modal-cart-body">
                                <!-- Cart items rendered here -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Batch Selection Mode Toggle -->
                    <div class="px-3 py-2 bg-white border-bottom">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="use-fifo-auto" checked>
                            <label class="form-check-label small" for="use-fifo-auto">
                                <i class="mdi mdi-sort-clock-ascending text-info"></i>
                                <strong>FIFO Mode:</strong> Automatically dispense from oldest batches first
                            </label>
                        </div>
                    </div>

                    <!-- Cart Summary -->
                    <div class="p-3 bg-light border-top">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div id="modal-stock-warning" class="text-danger small" style="display: none;">
                                    <i class="mdi mdi-alert-circle"></i>
                                    <span id="modal-stock-warning-text">Some items have insufficient stock</span>
                                </div>
                                <div id="modal-stock-status">
                                    <span class="badge bg-secondary">Select items</span>
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <span class="text-muted">Total:</span>
                                <span id="modal-cart-total" class="fs-5 fw-bold text-success ms-2">₦0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="clearDispenseCart()">
                    <i class="mdi mdi-cart-remove"></i> Clear Cart
                </button>
                <button type="button" class="btn btn-outline-primary" onclick="printCartPrescriptions()">
                    <i class="mdi mdi-printer"></i> Print
                </button>
                <button type="button" class="btn btn-success btn-lg px-4" id="btn-dispense-cart" onclick="dispenseFromCart()" disabled>
                    <i class="mdi mdi-pill"></i> Dispense All
                </button>
            </div>
        </div>
    </div>
</div>


{{-- Free-form dispense: no billing, mark as dispensed only --}}
<div class="modal fade" id="dispenseFreeFormModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="mdi mdi-pill"></i> Dispense Free-form Medication</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-2">Free-form items are not billed. Confirm quantity and mark as dispensed.</p>
                <input type="hidden" id="ff-dispense-id">
                <div class="mb-2">
                    <label class="form-label">Medication</label>
                    <input type="text" class="form-control" id="ff-dispense-name" readonly>
                </div>
                <div class="mb-2">
                    <label class="form-label">Quantity dispensed</label>
                    <input type="number" class="form-control" id="ff-dispense-qty" min="1" value="1">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" id="btn-confirm-ff-dispense">Mark Dispensed</button>
            </div>
        </div>
    </div>
</div>

@include('admin.partials.patient-form-modal')
@include('admin.partials.combo_confirm_modal')
@include('admin.partials.clinical_context_modal')

