{{--
    Billing Workbench Modals
    Extracted from main workbench view for modular architecture.
--}}

@include('admin.shared._my_transactions_modal')

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
