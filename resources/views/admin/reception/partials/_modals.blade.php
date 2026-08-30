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
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

#receiptPreviewModal .modal-footer {
    justify-content: center;
    gap: 0.5rem;
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

<!-- Patient Form Modal (Shared Partial) -->
@include('admin.partials.patient-form-modal')


<!-- Quick Register Modal (Legacy - kept for compatibility) -->
<div class="modal fade" id="quickRegisterModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="mdi mdi-account-plus"></i> Quick Patient Registration</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <form id="quick-register-form">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <div class="file-no-label-row">
                                    <label class="form-label mb-0">File Number <span class="text-danger">*</span></label>
                                    <span class="file-no-next-badge" id="qr-file-no-hint" title="Next auto-generated number">
                                        Next: <strong id="qr-next-file-no">--</strong>
                                    </span>
                                </div>
                                <div class="file-no-btn-group">
                                    <button type="button" class="file-no-mode-btn active" data-mode="auto" id="qr-mode-auto">
                                        <i class="mdi mdi-autorenew"></i> Auto
                                    </button>
                                    <button type="button" class="file-no-mode-btn" data-mode="manual" id="qr-mode-manual">
                                        <i class="mdi mdi-pencil"></i> Manual
                                    </button>
                                    <button type="button" class="file-no-mode-btn" id="qr-file-no-refresh" title="Regenerate (Ctrl+G)" style="margin-left: auto;">
                                        <i class="mdi mdi-refresh"></i>
                                    </button>
                                </div>
                                <input type="text" class="form-control file-no-input" id="quick-register-file-no" readonly placeholder="Auto-generated">
                                <!-- Info panel showing format and recent numbers -->
                                <div class="file-no-info-panel" id="qr-file-no-info">
                                    <div class="format-display">
                                        <span class="text-muted">Format:</span>
                                        <span class="format-pattern" id="qr-format-pattern">--</span>
                                    </div>
                                    <div class="text-muted">Recent: <span id="qr-recent-label">click to copy</span></div>
                                    <div class="file-no-recent-list" id="qr-recent-file-nos"></div>
                                </div>
                                <!-- Duplicate warning (hidden by default) -->
                                <div class="file-no-duplicate-warning" id="qr-duplicate-warning" style="display: none;">
                                    <div class="warning-title"><i class="mdi mdi-alert"></i> File number already in use</div>
                                    <div id="qr-duplicate-patients"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" class="form-control" id="quick-register-phone" placeholder="08012345678">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="quick-register-firstname" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="quick-register-lastname" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Gender <span class="text-danger">*</span></label>
                                <select class="form-control" id="quick-register-gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Date of Birth</label>
                                <input type="date" class="form-control" id="quick-register-dob">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>HMO</label>
                                <select class="form-control" id="quick-register-hmo">
                                    <option value="">No HMO (Private)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row" id="hmo-no-row" style="display: none;">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>HMO Number</label>
                                <input type="text" class="form-control" id="quick-register-hmo-no" placeholder="Enter HMO enrollment number">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="mdi mdi-account-plus"></i> Register Patient
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('admin.shared.modals.request_details')
<!-- Discard Request Modal -->
<div class="modal fade" id="discardRequestModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="mdi mdi-delete-alert"></i> Discard Request</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <form id="discardRequestForm">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="mdi mdi-alert"></i>
                        <strong>Warning:</strong> This action will discard the request. This cannot be undone easily.
                    </div>
                    <div class="mb-3">
                        <p><strong>Service:</strong> <span id="discard_service_name"></span></p>
                        <p><strong>Request No:</strong> <span id="discard_request_no"></span></p>
                    </div>
                    <div class="form-group">
                        <label for="discard_reason">Reason for Discarding <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="discard_reason" name="reason" rows="3"
                                  placeholder="Please provide a reason for discarding this request (minimum 10 characters)"
                                  required minlength="10"></textarea>
                        <small class="form-text text-muted">This reason will be logged for audit purposes.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="mdi mdi-close"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-danger" id="confirmDiscardBtn">
                        <i class="mdi mdi-delete"></i> Discard Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hospital Patient Card Modal -->
<div class="modal fade" id="hospitalCardModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: {{ appsettings()->hos_color ?? '#0066cc' }}; color: white;">
                <h5 class="modal-title"><i class="mdi mdi-card-account-details"></i> Hospital Patient Card</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <!-- Card View Tabs -->
                <ul class="nav nav-pills nav-fill mb-3 justify-content-center" id="cardViewTabs" style="max-width: 340px; margin: 0 auto;">
                    <li class="nav-item">
                        <a class="nav-link active" data-card-tab="front" href="#">Front</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-card-tab="back" href="#">Back</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-card-tab="combined" href="#">Combined</a>
                    </li>
                </ul>

                <!-- Card Preview Container -->
                <div id="hospital-card-container" style="display: inline-block;">
                    <!-- FRONT SIDE -->
                    <div class="hospital-card hospital-card-front" id="hospital-card-preview">
                        <!-- Card Header with Hospital Info -->
                        <div class="card-header-section">
                            <div class="hospital-logo-section">
                                @if(appsettings()->logo)
                                    <img src="data:image/jpeg;base64,{{ appsettings()->logo }}" alt="Hospital Logo" class="hospital-logo">
                                @else
                                    <div class="hospital-logo-placeholder">
                                        <i class="mdi mdi-hospital-building"></i>
                                    </div>
                                @endif
                            </div>
                            <div class="hospital-info-section">
                                <div class="hospital-name-text">{{ appsettings()->site_name ?? 'Hospital Name' }}</div>
                                <div class="hospital-address-text">{{ appsettings()->contact_address ?? '' }}</div>
                                <div class="hospital-phone-text">{{ appsettings()->contact_phones ?? '' }}</div>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="card-body-section">
                            <div class="patient-photo-section">
                                <img src="" alt="Patient Photo" id="card-patient-photo" class="patient-photo">
                            </div>
                            <div class="patient-info-section">
                                <div class="patient-name-text" id="card-patient-name">Jane Doe</div>
                                <div class="patient-details-grid">
                                    <div class="detail-item">
                                        <span class="detail-label">Patient ID</span>
                                        <span class="detail-value" id="card-patient-id">JD123456</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">DOB</span>
                                        <span class="detail-value" id="card-dob">01/01/1970</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Blood</span>
                                        <span class="detail-value" id="card-blood-type">O+</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Gender</span>
                                        <span class="detail-value" id="card-gender">Female</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Barcode Section -->
                        <div class="card-barcode-section">
                            <svg id="card-barcode"></svg>
                            <div class="barcode-number" id="card-barcode-number"></div>
                        </div>

                        <!-- Card Footer -->
                        <div class="card-footer-section">
                            <span class="card-type-badge">PATIENT CARD</span>
                            <span class="powered-by">CoreHealth by corestream.ng</span>
                        </div>
                    </div>

                    <!-- BACK SIDE -->
                    <div class="hospital-card hospital-card-back" id="hospital-card-back" style="margin-top: 15px;">
                        <!-- Back Header -->
                        <div class="card-back-header">
                            <div class="back-title">PATIENT INFORMATION</div>
                        </div>

                        <!-- Back Body -->
                        <div class="card-back-body">
                            <div class="back-info-row">
                                <span class="back-label">Genotype:</span>
                                <span class="back-value" id="card-genotype">AA</span>
                            </div>
                            <div class="back-info-row">
                                <span class="back-label">Phone:</span>
                                <span class="back-value" id="card-phone">08012345678</span>
                            </div>
                            <div class="back-info-row full-width">
                                <span class="back-label">Address:</span>
                                <span class="back-value" id="card-address">123 Main Street, Lagos</span>
                            </div>
                            <div class="back-info-row full-width">
                                <span class="back-label">Allergies:</span>
                                <span class="back-value" id="card-allergies">None known</span>
                            </div>
                            <div class="back-divider"></div>
                            <div class="back-section-title">Emergency Contact</div>
                            <div class="back-info-row">
                                <span class="back-label">Name:</span>
                                <span class="back-value" id="card-nok-name">John Doe</span>
                            </div>
                            <div class="back-info-row">
                                <span class="back-label">Phone:</span>
                                <span class="back-value" id="card-nok-phone">08098765432</span>
                            </div>
                        </div>

                        <!-- Back Footer -->
                        <div class="card-back-footer">
                            <div class="emergency-note">In case of emergency, please contact the hospital</div>
                            <div class="powered-by-back">CoreHealth by corestream.ng</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="btn-print-card-now">
                    <i class="mdi mdi-printer"></i> Print Card
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Hospital Card Styles - Front */
.hospital-card {
    width: 340px;
    height: 215px;
    background: linear-gradient(135deg, #ffffff 0%, #f5f5f5 100%);
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    overflow: hidden;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    position: relative;
    display: flex;
    flex-direction: column;
}

.hospital-card .card-header-section {
    background: {{ appsettings()->hos_color ?? '#0066cc' }};
    color: white;
    padding: 8px 10px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.hospital-card .hospital-logo-section {
    width: 35px;
    height: 35px;
    flex-shrink: 0;
}

.hospital-card .hospital-logo {
    width: 35px;
    height: 35px;
    object-fit: contain;
    background: white;
    border-radius: 4px;
    padding: 2px;
}

.hospital-card .hospital-logo-placeholder {
    width: 35px;
    height: 35px;
    background: rgba(255,255,255,0.2);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hospital-card .hospital-logo-placeholder i {
    font-size: 20px;
}

.hospital-card .hospital-info-section {
    flex: 1;
    line-height: 1.2;
}

.hospital-card .hospital-name-text {
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
}

.hospital-card .hospital-address-text {
    font-size: 8px;
    font-weight: 600;
    opacity: 0.9;
}

.hospital-card .hospital-phone-text {
    font-size: 8px;
    font-weight: 600;
    opacity: 0.9;
}

.hospital-card .card-body-section {
    display: flex;
    padding: 8px 10px;
    gap: 10px;
    flex: 1;
    min-height: 0;
}

.hospital-card .patient-photo-section {
    flex-shrink: 0;
}

.hospital-card .patient-photo {
    width: 60px;
    height: 75px;
    object-fit: cover;
    border-radius: 6px;
    border: 2px solid {{ appsettings()->hos_color ?? '#0066cc' }};
    background: #f0f0f0;
}

.hospital-card .patient-info-section {
    flex: 1;
    text-align: left;
    overflow: visible;
}

.hospital-card .patient-name-text {
    font-size: 14px;
    font-weight: 800;
    color: #222;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    border-bottom: 1px solid #ddd;
    padding-bottom: 2px;
}

.hospital-card .patient-details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4px 8px;
}

.hospital-card .detail-item {
    display: flex;
    flex-direction: column;
}

.hospital-card .detail-label {
    font-size: 8px;
    font-weight: 700;
    color: #666;
    text-transform: uppercase;
}

.hospital-card .detail-value {
    font-size: 11px;
    font-weight: 700;
    color: #222;
    white-space: nowrap;
    overflow: visible;
}

.hospital-card .card-barcode-section {
    padding: 2px 10px;
    text-align: center;
    flex-shrink: 0;
    background: #fff;
}

.hospital-card .card-barcode-section svg {
    height: 20px;
    width: auto;
    max-width: 100%;
}

.hospital-card .barcode-number {
    font-size: 9px;
    font-family: 'Courier New', monospace;
    font-weight: 700;
    color: #222;
    letter-spacing: 1px;
}

.hospital-card .card-footer-section {
    background: {{ appsettings()->hos_color ?? '#0066cc' }};
    color: white;
    padding: 3px 10px;
    flex-shrink: 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.hospital-card .card-type-badge {
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 1px;
}

.hospital-card .powered-by {
    font-size: 7px;
    font-weight: 600;
    opacity: 0.8;
}

/* Hospital Card Styles - Back */
.hospital-card-back {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.hospital-card-back .card-back-header {
    background: {{ appsettings()->hos_color ?? '#0066cc' }};
    color: white;
    padding: 6px 10px;
    text-align: center;
}

.hospital-card-back .back-title {
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1px;
}

.hospital-card-back .card-back-body {
    padding: 8px 10px;
    flex: 1;
}

.hospital-card-back .back-info-row {
    display: flex;
    gap: 5px;
    margin-bottom: 4px;
    font-size: 10px;
}

.hospital-card-back .back-info-row.full-width {
    flex-direction: column;
    gap: 1px;
}

.hospital-card-back .back-label {
    font-weight: 700;
    color: #444;
    min-width: 50px;
}

.hospital-card-back .back-value {
    color: #333;
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hospital-card-back .back-info-row.full-width .back-value {
    white-space: normal;
    font-size: 9px;
    font-weight: 600;
    line-height: 1.3;
}

.hospital-card-back .back-divider {
    border-top: 1px dashed #ccc;
    margin: 6px 0;
}

.hospital-card-back .back-section-title {
    font-size: 10px;
    font-weight: 800;
    color: {{ appsettings()->hos_color ?? '#0066cc' }};
    margin-bottom: 4px;
    text-transform: uppercase;
}

.hospital-card-back .card-back-footer {
    background: {{ appsettings()->hos_color ?? '#0066cc' }};
    color: white;
    padding: 4px 10px;
    text-align: center;
}

.hospital-card-back .emergency-note {
    font-size: 8px;
    font-weight: 600;
    opacity: 0.9;
}

.hospital-card-back .powered-by-back {
    font-size: 7px;
    font-weight: 600;
    opacity: 0.7;
    margin-top: 2px;
}

/* Print Styles for Hospital Card */
@media print {
    body * {
        visibility: hidden;
    }

    #hospital-card-container, #hospital-card-container * {
        visibility: visible;
    }

    #hospital-card-container {
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
    }

    .hospital-card {
        box-shadow: none;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .hospital-card-back {
        page-break-before: always;
        margin-top: 20px;
    }
}
</style>


