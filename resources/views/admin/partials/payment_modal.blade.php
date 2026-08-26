<!-- Payment Modal -->
                <div class="modal fade" id="paymentModal" tabindex="-1" role="dialog" aria-labelledby="paymentModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="paymentModalLabel">
                                    <i class="mdi mdi-cart-check"></i> Checkout
                                    <span class="item-count-badge"><span id="modal-item-count">0</span> items</span>
                                </h5>
                                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="payment-summary-card" id="payment-summary-card">
                                    <h5><i class="mdi mdi-calculator"></i> Payment Summary</h5>

                                    <!-- Account Balance Info -->
                                    <div class="account-balance-info" id="billing-account-balance" style="display: none;">
                                        <div class="balance-row">
                                            <span><i class="mdi mdi-wallet"></i> Account Balance:</span>
                                            <span id="billing-balance-amount" class="balance-amount">₦0.00</span>
                                        </div>
                                    </div>

                                    <div class="summary-details">
                                        <div class="summary-row">
                                            <span>Subtotal:</span>
                                            <span id="summary-subtotal">₦0.00</span>
                                        </div>
                                        <div class="summary-row">
                                            <span>Total Discount:</span>
                                            <span id="summary-discount">₦0.00</span>
                                        </div>
                                        <div class="summary-row total">
                                            <span>Total Payable:</span>
                                            <span id="summary-total">₦0.00</span>
                                        </div>
                                    </div>
                                    <div class="payment-method-section">
                                        <label><i class="mdi mdi-cash-multiple"></i> Payment Method</label>
                                        <select class="form-control" id="payment-method">
                                            <option value="CASH">Cash</option>
                                            <option value="POS">POS/Card</option>
                                            <option value="TRANSFER">Bank Transfer</option>
                                            <option value="MOBILE">Mobile Money</option>
                                            <option value="BILL_TO_STAFF">Bill to Staff</option>
                                            <option value="BILL_TO_ORGANIZATION">Bill to Organization</option>
                                            <option value="ACCOUNT" id="account-payment-option" style="display: none;">Pay from Account Balance</option>
                                        </select>
                                        <small class="text-muted" id="account-payment-note" style="display: none;">
                                            <i class="mdi mdi-information"></i> Payment will be deducted from account balance
                                        </small>
                                    </div>
                                    <div class="staff-selection-section" id="staff-selection-section" style="display: none;">
                                        <label><i class="mdi mdi-account-card-outline"></i> Select Staff to Bill</label>
                                        <select class="form-control select2" id="payment-staff-id" style="width: 100%;">
                                            <option value="">-- Select Staff --</option>
                                        </select>
                                    </div>
                                    <div class="org-selection-section" id="org-selection-section" style="display: none;">
                                        <label><i class="mdi mdi-domain"></i> Select Organization to Bill</label>
                                        <select class="form-control select2" id="payment-organization-id" style="width: 100%;">
                                            <option value="">-- Select Organization --</option>
                                        </select>
                                    </div>
                                    <div class="bank-selection-section" id="bank-selection-section" style="display: none;">
                                        <label><i class="mdi mdi-bank"></i> Select Bank</label>
                                        <select class="form-control" id="payment-bank">
                                            <option value="">-- Select Bank --</option>
                                        </select>
                                    </div>
                                    <div class="payment-reference-section">
                                        <label>Reference Number (Optional)</label>
                                        <input type="text" class="form-control" id="payment-reference" placeholder="Enter transaction reference">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                    <i class="mdi mdi-close"></i> Cancel
                                </button>
                                <button type="button" class="btn btn-success btn-confirm-payment" id="confirm-payment-btn">
                                    <i class="mdi mdi-check-circle"></i> Confirm Payment
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Receipt Display (after payment) -->
                <div class="receipt-display" id="receipt-display" style="display: none;">
                    <div class="receipt-tabs">
                        <button class="receipt-tab active" data-format="a4">A4 Receipt</button>
                        <button class="receipt-tab" data-format="thermal">Thermal Receipt</button>
                    </div>
                    <div class="receipt-content" id="receipt-content-a4"></div>
                    <div class="receipt-content" id="receipt-content-thermal" style="display: none;"></div>
                    <div class="receipt-actions">
                        <button class="btn btn-primary" id="print-a4-receipt">
                            <i class="mdi mdi-printer"></i> Print A4
                        </button>
                        <button class="btn btn-primary" id="print-thermal-receipt">
                            <i class="mdi mdi-printer"></i> Print Thermal
                        </button>
                        <button class="btn btn-secondary" id="close-receipt">
                            <i class="mdi mdi-close"></i> Close
                        </button>
                    </div>
                </div>
            </div>

            