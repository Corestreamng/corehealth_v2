{{--
    Billing Workbench Modals
    Extracted from main workbench view for modular architecture.
--}}

<!-- My Transactions Modal (Global Access) -->
<div class="modal fade" id="myTransactionsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--hospital-primary, var(--primary-color, #011b33)); color: white;">
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
