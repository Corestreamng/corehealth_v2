                        </span>
                        <span class="filter-stat-divider">|</span>
                        <span class="filter-stat">
                            <input type="checkbox" id="select-visible-only" style="margin-right: 4px;">
                            <label for="select-visible-only" style="margin-bottom: 0; cursor: pointer;">Select visible only</label>
                        </span>
                        <span class="filter-stat-divider">|</span>
                        <span class="filter-stat total-visible-amount">
                            Visible Total: <strong id="billing-visible-total">₦0.00</strong>
                        </span>
                    </div>
                </div>

                <div class="billing-items-container">
                    <table class="table table-hover" id="billing-items-table">
                        <thead>
                            <tr>
                                <th width="40"><input type="checkbox" id="select-all-billing-items"></th>
                                <th>Date/Time</th>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th width="80">Qty</th>
                                <th width="80">Discount %</th>
                                <th>HMO Coverage</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="billing-items-tbody">
                            <tr>
                                <td colspan="9" class="text-center text-muted py-5">
                                    <i class="mdi mdi-information-outline" style="font-size: 3rem;"></i>
                                    <p>No unpaid items for this patient</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Floating Cart Button (appears when items selected) -->
                <div class="floating-cart" id="floating-cart">
                    <button class="floating-cart-btn" data-bs-toggle="modal" data-bs-target="#paymentModal">
                        <i class="mdi mdi-cart-outline"></i>
                        <span class="cart-badge" id="cart-item-count">0</span>
                        <span class="cart-total" id="cart-total-display">₦0.00</span>
                        <i class="mdi mdi-chevron-up"></i>
                    </button>
                </div>

                @include('admin.partials.payment_modal')
            </div>

            <div class="workspace-tab-content" id="receipts-tab">
                <div class="receipts-tab-header">
                    <h4><i class="mdi mdi-receipt"></i> Payment Receipts & Transactions</h4>
                    <div class="receipts-toolbar">
                        <button class="btn btn-sm btn-secondary" id="refresh-receipts">
                            <i class="mdi mdi-refresh"></i> Refresh
                        </button>
                        <button class="btn btn-sm btn-primary" id="print-selected-receipts" disabled>
                            <i class="mdi mdi-printer"></i> Print Selected
                        </button>
                        <button class="btn btn-sm btn-info" id="export-receipts">
                            <i class="mdi mdi-download"></i> Export
                        </button>
                    </div>
                </div>

                <!-- Filter Panel -->
                <div class="transactions-filter-panel">
                    <div class="row">
                        <div class="col-md-3">
                            <label>From Date</label>
                            <input type="date" class="form-control" id="receipts-from-date">
                        </div>
                        <div class="col-md-3">
                            <label>To Date</label>
                            <input type="date" class="form-control" id="receipts-to-date">
                        </div>
                        <div class="col-md-3">
                            <label>Payment Type</label>
                            <select class="form-control" id="receipts-payment-type">
                                <option value="">All Types</option>
                                <option value="CASH">Cash</option>
                                <option value="POS">POS/Card</option>
                                <option value="TRANSFER">Bank Transfer</option>
                                <option value="MOBILE">Mobile Money</option>
                                <option value="ACCOUNT">Account Balance</option>
                                <option value="ACC_DEPOSIT">Account Deposit</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>&nbsp;</label>
                            <button class="btn btn-primary btn-block" id="filter-receipts">
                                <i class="mdi mdi-filter"></i> Filter
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Summary Statistics -->
                <div class="transactions-summary" id="receipts-summary" style="display: none;">
                    <div class="stat-card">
                        <div class="stat-value" id="receipts-total-count">0</div>
                        <div class="stat-label">Total Transactions</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="receipts-total-amount">₦0.00</div>
                        <div class="stat-label">Total Amount</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="receipts-total-discounts">₦0.00</div>
                        <div class="stat-label">Total Discounts</div>
                    </div>
                </div>

                <div class="receipts-container">
                    <table class="table table-hover" id="receipts-table">
                        <thead>
                            <tr>
                                <th width="40"><input type="checkbox" id="select-all-receipts"></th>
                                <th>Receipt No</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Amount</th>
                                <th>Discount</th>
                                <th>Method</th>
                                <th>Cashier</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="receipts-tbody">
                            <tr>
                                <td colspan="9" class="text-center text-muted py-5">
                                    <i class="mdi mdi-receipt" style="font-size: 3rem;"></i>
                                    <p>No receipts found for this patient</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Admissions Tab (Reusable Module) -->
            <div class="workspace-tab-content" id="admissions-tab">
                @include('admin.partials.admissions-module')
            </div>

            <div class="workspace-tab-content" id="account-tab">
                <!-- Hero Balance Section -->
                <div class="account-hero-section" id="account-hero-section">
                    <div class="account-hero-balance" id="account-hero-balance">
                        <div class="hero-balance-icon">
                            <i class="mdi mdi-wallet"></i>
                        </div>
                        <div class="hero-balance-content">
                            <span class="hero-balance-label">Current Balance</span>
                            <span class="hero-balance-amount" id="hero-balance-amount">₦0.00</span>
                            <span class="hero-balance-status" id="hero-balance-status">Balanced</span>
                        </div>
                        <div class="hero-balance-actions">
                            <div class="action-btn-group">
                                <button class="btn btn-light btn-sm" id="quick-deposit-btn" title="Make Deposit">
                                    <i class="mdi mdi-plus-circle text-success"></i> Deposit
                                </button>
                                <button class="btn btn-outline-light btn-sm" id="quick-withdraw-btn" title="Withdraw">
                                    <i class="mdi mdi-minus-circle text-danger"></i> Withdraw
                                </button>
                                <button class="btn btn-outline-light btn-sm" id="quick-adjust-btn" title="Adjustment">
                                    <i class="mdi mdi-swap-horizontal text-info"></i> Adjust
                                </button>
                            </div>
                            <div class="action-btn-group mt-2">
                                <button class="btn btn-warning btn-sm" id="print-statement-btn" title="Print Account Statement">
                                    <i class="mdi mdi-file-document-outline"></i> Print Statement
                                </button>
                                <button class="btn btn-outline-light btn-sm" id="refresh-account-data" title="Refresh">
                                    <i class="mdi mdi-refresh"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Account Stats Dashboard -->
                    <div class="account-stats-grid">
                        <div class="account-stat-card deposits">
                            <div class="stat-icon"><i class="mdi mdi-arrow-down-bold-circle"></i></div>
                            <div class="stat-info">
                                <span class="stat-value" id="total-deposits-stat">₦0</span>
                                <span class="stat-label">Total Deposits</span>
                            </div>
                        </div>
                        <div class="account-stat-card withdrawals">
                            <div class="stat-icon"><i class="mdi mdi-arrow-up-bold-circle"></i></div>
                            <div class="stat-info">
                                <span class="stat-value" id="total-withdrawals-stat">₦0</span>
                                <span class="stat-label">Total Withdrawals</span>
                            </div>
                        </div>
                        <div class="account-stat-card pending">
                            <div class="stat-icon"><i class="mdi mdi-clock-outline"></i></div>
                            <div class="stat-info">
                                <span class="stat-value" id="pending-bills-stat">₦0</span>
                                <span class="stat-label">Pending Bills</span>
                            </div>
                        </div>
                        <div class="account-stat-card transactions">
                            <div class="stat-icon"><i class="mdi mdi-swap-horizontal"></i></div>
                            <div class="stat-info">
                                <span class="stat-value" id="tx-count-stat">0</span>
                                <span class="stat-label">Transactions</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- No Account State -->
                <div class="account-no-account-state" id="no-account-state" style="display: none;">
                    <div class="no-account-content">
                        <div class="no-account-icon">
                            <i class="mdi mdi-wallet-outline"></i>
                        </div>
                        <h4>No Account Found</h4>
                        <p>This patient doesn't have an account yet. Create one to start tracking deposits and payments.</p>
                        <button class="btn btn-primary btn-lg" id="create-account-btn">
                            <i class="mdi mdi-plus-circle"></i> Create Account
                        </button>
                    </div>
                </div>

                <!-- Account Transaction Panel (Deposit/Withdraw/Adjust) -->
                <div class="account-transaction-panel" id="account-transaction-panel" style="display: none;">
                    <div class="transaction-panel-header" id="transaction-panel-header">
                        <h5><i class="mdi mdi-cash-plus" id="transaction-panel-icon"></i> <span id="transaction-panel-title">Make Deposit</span></h5>
                        <button class="btn btn-sm btn-link" id="close-transaction-panel">
                            <i class="mdi mdi-close"></i>
                        </button>
                    </div>
                    <div class="transaction-panel-body">
                        <form id="account-transaction-form" class="transaction-form-inline">
                            <input type="hidden" id="transaction-type" value="deposit">
                            <div class="form-group">
                                <label>Amount</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₦</span>
                                    </div>
                                    <input type="number" step="0.01" class="form-control form-control-lg" id="transaction-amount" placeholder="0.00" required>
                                </div>
                                <small class="form-text text-muted" id="transaction-amount-help">Enter amount to deposit</small>
                            </div>
                            <div class="form-group" id="transaction-payment-method-group">
                                <label>Payment Method</label>
                                <select class="form-control" id="transaction-payment-method">
                                    <option value="CASH">Cash</option>
                                    <option value="POS">POS/Card</option>
                                    <option value="TRANSFER">Bank Transfer</option>
                                    <option value="MOBILE">Mobile Money</option>
                                </select>
                            </div>
                            <div class="form-group" id="transaction-bank-group" style="display: none;">
                                <label>Select Bank</label>
                                <select class="form-control" id="transaction-bank">
                                    <option value="">-- Select Bank --</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Description <small class="text-muted">(Required for adjustments)</small></label>
                                <input type="text" class="form-control" id="transaction-description" placeholder="e.g., Cash deposit, Refund, Correction, etc.">
                            </div>
                            <div class="transaction-actions">
                                <button type="submit" class="btn btn-block" id="transaction-submit-btn">
                                    <i class="mdi mdi-check"></i> <span id="transaction-submit-text">Confirm Deposit</span>
                                </button>
                            </div>
                        </form>

                        <!-- Balance Preview -->
                        <div class="balance-preview" id="balance-preview">
                            <div class="balance-preview-row">
                                <span>Current Balance:</span>
                                <span id="preview-current-balance">₦0.00</span>
                            </div>
                            <div class="balance-preview-row">
                                <span id="preview-change-label">After Deposit:</span>
                                <span id="preview-new-balance">₦0.00</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transaction History Section -->
                <div class="account-transactions-section" id="account-transactions-section">
                    <div class="transactions-section-header">
                        <h5><i class="mdi mdi-history"></i> Account Transactions</h5>
                        <div class="transactions-filters">
                            <div class="filter-group">
                                <input type="date" class="form-control form-control-sm" id="account-tx-from-date">
                            </div>
                            <div class="filter-group">
                                <input type="date" class="form-control form-control-sm" id="account-tx-to-date">
                            </div>
                            <div class="filter-group">
                                <select class="form-control form-control-sm" id="account-tx-type-filter">
                                    <option value="">All Types</option>
                                    <option value="ACC_DEPOSIT">Deposits</option>
                                    <option value="ACC_WITHDRAW">Withdrawals/Payments</option>
                                    <option value="ACC_ADJUSTMENT">Adjustments</option>
                                </select>
                            </div>
                            <button class="btn btn-sm btn-primary" id="filter-account-tx">
                                <i class="mdi mdi-filter"></i> Filter
                            </button>
                        </div>
                    </div>

                    <!-- Transaction Timeline -->
                    <div class="transaction-timeline" id="transaction-timeline">
                        <div class="timeline-empty-state">
                            <i class="mdi mdi-swap-horizontal"></i>
                            <p>No account transactions yet</p>
                            <small>Deposits and withdrawals will appear here</small>
                        </div>
                    </div>
                </div>
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
                                <option value="ACC_DEPOSIT">Account Deposit</option>
                                <option value="ACC_WITHDRAW">Account Withdrawal</option>
                                <option value="ACC_ADJUSTMENT">Adjustment</option>
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
                        <div class="col-md-4">
                            <div class="summary-stat-card">
                                <div class="stat-value" id="my-total-transactions">0</div>
                                <div class="stat-label">Total Transactions</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="summary-stat-card">
                                <div class="stat-value" id="my-total-amount">₦0.00</div>
                                <div class="stat-label">Total Amount</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="summary-stat-card">
                                <div class="stat-value" id="my-total-discounts">₦0.00</div>
                                <div class="stat-label">Total Discounts</div>
                            </div>
                        </div>
                    </div>
                    <!-- Breakdown by payment type -->
                    <div class="payment-type-breakdown" id="payment-type-breakdown"></div>
                </div>

                <!-- Transactions Table -->
                <div class="my-transactions-container">
                    <table class="table table-hover" id="my-transactions-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Patient</th>
                                <th>File No</th>
                                <th>Reference</th>
                                <th>Method</th>
                                <th>Bank</th>
                                <th>Amount</th>
                                <th>Discount</th>
                            </tr>
                        </thead>
                        <tbody id="my-transactions-tbody">
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="mdi mdi-information-outline" style="font-size: 3rem;"></i>
                                    <p>Click "Load" to fetch your transactions</p>
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

<style>
    /* Receipt Preview Modal */
    .receipt-modal-tabs {
        display: flex;
        background: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
    }

    .receipt-modal-tab {
        flex: 1;
        padding: 1rem 1.5rem;
        background: transparent;
        border: none;
        border-bottom: 3px solid transparent;
        cursor: pointer;
        font-weight: 600;
        color: #6c757d;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .receipt-modal-tab:hover {
        background: #e9ecef;
        color: var(--hospital-primary);
    }

    .receipt-modal-tab.active {
        background: white;
        color: var(--hospital-primary);
        border-bottom-color: var(--hospital-primary);
    }

    .receipt-modal-content {
        padding: 1.5rem;
        background: #f8f9fa;
        max-height: 60vh;
        overflow-y: auto;
    }

    .receipt-modal-pane {
        background: white;
        border-radius: 0.5rem;
        padding: 1.5rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    #receiptPreviewModal .modal-footer {
        justify-content: center;
        gap: 0.5rem;
    }

    /* Statement Modal Styles */
    .statement-modal-tabs {
        display: flex;
        border-bottom: 2px solid #e9ecef;
        background: #f8f9fa;
    }

    .statement-modal-tab {
        flex: 1;
        padding: 12px;
        border: none;
        background: transparent;
        color: #6c757d;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
    }

    .statement-modal-tab:hover {
        background: #e9ecef;
        color: #495057;
    }

    .statement-modal-tab.active {
        background: #fff;
        color: #007bff;
        border-bottom: 3px solid #007bff;
        margin-bottom: -2px;
    }

    .statement-modal-content {
        padding: 15px;
        max-height: 60vh;
        overflow-y: auto;
    }

    .statement-modal-pane {
        display: none;
    }

    .statement-modal-pane.active {
        display: block;
    }

    .statement-config-section {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
    }

    .statement-config-section h6 {
        margin-bottom: 15px;
        color: #495057;
        font-weight: 600;
    }

    .statement-type-checkbox {
        display: flex;
        align-items: center;
        padding: 8px 12px;
        margin: 5px 0;
        border-radius: 6px;
        transition: all 0.2s;
    }

    .statement-type-checkbox:hover {
        background: #e9ecef;
    }

    .statement-type-checkbox input[type="checkbox"] {
        width: 18px;
        height: 18px;
        margin-right: 10px;
    }

    .statement-type-checkbox label {
        margin: 0;
        cursor: pointer;
        font-weight: 500;
    }

    .statement-type-checkbox .type-icon {
        margin-left: auto;
        font-size: 18px;
    }

    .statement-type-checkbox.deposits .type-icon {
        color: #28a745;
    }

    .statement-type-checkbox.payments .type-icon {
        color: #dc3545;
    }

    .statement-type-checkbox.withdrawals .type-icon {
        color: #fd7e14;
    }

    .statement-type-checkbox.services .type-icon {
        color: #6610f2;
    }

    .statement-summary-preview {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 10px;
        padding: 15px;
        text-align: center;
        margin-bottom: 15px;
    }

    .statement-summary-preview h5 {
        margin-bottom: 10px;
    }

    .statement-summary-preview .stat-value {
        font-size: 1.5rem;
        font-weight: 700;
    }

    .statement-date-presets {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
        margin-top: 10px;
    }

    .statement-date-presets button {
        font-size: 11px;
        padding: 4px 10px;
    }
</style>

<!-- Account Statement Modal -->
<div class="modal fade" id="accountStatementModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="mdi mdi-file-document-outline"></i> Account Statement</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Config Panel -->
                <div id="statement-config-panel" style="display: block;">
                    <div class="p-3">
                        <div class="row">
                            <div class="col-md-6">
                                <!-- Date Range -->
                                <div class="statement-config-section">
                                    <h6><i class="mdi mdi-calendar-range"></i> Date Range</h6>
                                    <div class="row">
                                        <div class="col-6">
                                            <label class="small text-muted">From</label>
                                            <input type="date" id="statement-date-from" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-6">
                                            <label class="small text-muted">To</label>
                                            <input type="date" id="statement-date-to" class="form-control form-control-sm">
                                        </div>
                                    </div>
                                    <div class="statement-date-presets">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="7days">Last 7 Days</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="30days">Last 30 Days</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="thisMonth">This Month</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="lastMonth">Last Month</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="thisYear">This Year</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="all">All Time</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <!-- Transaction Types -->
                                <div class="statement-config-section">
                                    <h6><i class="mdi mdi-filter-outline"></i> Include Transaction Types</h6>
                                    <div class="statement-type-checkbox deposits">
                                        <input type="checkbox" id="include-deposits" checked>
                                        <label for="include-deposits">Deposits</label>
                                        <i class="mdi mdi-arrow-down-bold-circle type-icon"></i>
                                    </div>
                                    <div class="statement-type-checkbox payments">
                                        <input type="checkbox" id="include-payments" checked>
                                        <label for="include-payments">Direct Payments</label>
                                        <i class="mdi mdi-credit-card type-icon"></i>
                                    </div>
                                    <div class="statement-type-checkbox withdrawals">
                                        <input type="checkbox" id="include-withdrawals" checked>
                                        <label for="include-withdrawals">Withdrawals & Adjustments</label>
                                        <i class="mdi mdi-arrow-up-bold-circle type-icon"></i>
                                    </div>
                                    <div class="statement-type-checkbox services">
                                        <input type="checkbox" id="include-services" checked>
                                        <label for="include-services">Deposit Applications</label>
                                        <i class="mdi mdi-application type-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-center mt-3">
                            <button type="button" class="btn btn-warning btn-lg px-5" id="generate-statement-btn">
                                <i class="mdi mdi-file-document"></i> Generate Statement
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Statement Preview Panel -->
                <div id="statement-preview-panel" style="display: none;">
                    <div class="statement-modal-tabs">
                        <button class="statement-modal-tab active" data-format="a4">
                            <i class="mdi mdi-file-document"></i> A4 Statement
                        </button>
                        <button class="statement-modal-tab" data-format="thermal">
                            <i class="mdi mdi-receipt"></i> Thermal Statement
                        </button>
                        <button class="statement-modal-tab" data-format="config">
                            <i class="mdi mdi-cog"></i> Options
                        </button>
                    </div>
                    <div class="statement-modal-content">
                        <div class="statement-modal-pane active" id="statement-pane-a4"></div>
                        <div class="statement-modal-pane" id="statement-pane-thermal"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" id="statement-modal-footer" style="display: none;">
                <button type="button" class="btn btn-outline-secondary" id="statement-back-btn">
                    <i class="mdi mdi-arrow-left"></i> Back to Options
                </button>
                <button type="button" class="btn btn-primary" id="statement-print-a4">
                    <i class="mdi mdi-printer"></i> Print A4
                </button>
                <button type="button" class="btn btn-info" id="statement-print-thermal">
                    <i class="mdi mdi-printer"></i> Print Thermal
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fa fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

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

@include('admin.partials.patient-form-modal')

@endsection

@section('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('plugins/ckeditor/ckeditor5/ckeditor.js') }}"></script>
@include('admin.partials.patient_search_js', ['search_context' => 'billing'])
