{{-- Admission Module Partial - Reusable across workbenches --}}
{{-- Usage: @include('admin.partials.admissions-module') --}}

<style>
/* ========== ADMISSION MODULE STYLES (scoped with .adm-mod) ========== */
.adm-mod .admissions-tab-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
}

.adm-mod .admissions-tab-header h4 {
    margin: 0;
    color: #495057;
    font-weight: 600;
}

.adm-mod .admissions-split-view {
    display: flex;
    height: calc(100vh - 400px);
    min-height: 500px;
}

.adm-mod .admissions-list-panel {
    width: 55%;
    border-right: 1px solid #dee2e6;
    overflow: auto;
    transition: width 0.3s ease, min-width 0.3s ease, padding 0.3s ease;
}

.adm-mod .admissions-list-panel.collapsed {
    width: 0;
    min-width: 0;
    padding: 0;
    overflow: hidden;
    border-right: none;
}

.adm-mod .admissions-table-container {
    padding: 0;
}

.adm-mod .adm-mod-table {
    margin: 0;
    font-size: 0.9rem;
}

.adm-mod .adm-mod-table thead th {
    position: sticky;
    top: 0;
    background: #f8f9fa;
    z-index: 10;
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
    color: #6c757d;
    padding: 12px 10px;
    border-bottom: 2px solid #dee2e6;
}

.adm-mod .adm-mod-table tbody tr {
    cursor: pointer;
    transition: all 0.2s;
}

.adm-mod .adm-mod-table tbody tr:hover {
    background: rgba(13, 110, 253, 0.05);
}

.adm-mod .adm-mod-table tbody tr.selected {
    background: rgba(13, 110, 253, 0.1);
    border-left: 3px solid var(--hospital-primary, #0d6efd);
}

.adm-mod .adm-mod-table td {
    padding: 10px;
    vertical-align: middle;
}

.adm-mod .admission-status-pill {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.adm-mod .admission-status-pill.admitted {
    background: rgba(25, 135, 84, 0.15);
    color: #198754;
}

.adm-mod .admission-status-pill.discharged {
    background: rgba(108, 117, 125, 0.15);
    color: #6c757d;
}

.adm-mod .admission-detail-panel {
    width: 45%;
    overflow: auto;
    background: #fafbfc;
    transition: width 0.3s ease;
    position: relative;
}

.adm-mod .admissions-list-panel.collapsed + .admission-detail-panel {
    width: 100%;
}

.adm-mod .adm-mod-expand-list-btn {
    position: absolute;
    top: 8px;
    left: 8px;
    z-index: 15;
    border-radius: 16px;
    padding: 4px 10px 4px 6px;
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}

.adm-mod .admission-detail-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #adb5bd;
}

.adm-mod .admission-detail-placeholder i {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.adm-mod .admission-detail-content {
    padding: 1.5rem;
}

.adm-mod .admission-detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.adm-mod .admission-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
}

.adm-mod .admission-status-badge.admitted {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #047857;
}

.adm-mod .admission-status-badge.discharged {
    background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);
    color: #4b5563;
}

.adm-mod .admission-info-cards {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 1.5rem;
}

.adm-mod .admission-info-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: white;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.adm-mod .admission-info-card.full-width {
    grid-column: span 2;
}

.adm-mod .info-card-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(13, 110, 253, 0.1);
    border-radius: 8px;
    color: var(--hospital-primary, #0d6efd);
    font-size: 1.2rem;
}

.adm-mod .info-card-content {
    flex: 1;
}

.adm-mod .info-label {
    display: block;
    font-size: 0.75rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.adm-mod .info-value {
    display: block;
    font-size: 0.95rem;
    font-weight: 600;
    color: #212529;
}

.adm-mod .admission-bill-summary {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    border: 1px solid #e9ecef;
}

.adm-mod .admission-bill-summary h5 {
    font-size: 0.95rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e9ecef;
}

.adm-mod .bill-category-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 12px;
    margin-bottom: 8px;
    background: #f8f9fa;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.adm-mod .bill-category-item:hover {
    background: #e9ecef;
}

.adm-mod .bill-category-item.expanded {
    background: #e3f2fd;
}

.adm-mod .category-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.adm-mod .category-icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    border-radius: 6px;
    font-size: 1rem;
}

.adm-mod .category-name {
    font-weight: 500;
    color: #212529;
}

.adm-mod .category-count {
    font-size: 0.75rem;
    color: #6c757d;
}

.adm-mod .category-amount {
    font-weight: 600;
    color: #212529;
}

.adm-mod .category-items {
    display: none;
    margin-top: 8px;
    margin-left: 42px;
    padding: 10px;
    background: white;
    border-radius: 6px;
    font-size: 0.85rem;
}

.adm-mod .category-items.show {
    display: block;
}

.adm-mod .category-item-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    border-bottom: 1px dashed #e9ecef;
}

.adm-mod .category-item-row:last-child {
    border-bottom: none;
}

.adm-mod .admission-bill-totals {
    background: linear-gradient(135deg, #1e3a5f 0%, #2c5282 100%);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.adm-mod .bill-total-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
}

.adm-mod .bill-total-row.discount span:last-child,
.adm-mod .bill-total-row.hmo span:last-child,
.adm-mod .bill-total-row.paid span:last-child {
    color: #86efac;
}

.adm-mod .bill-total-row.grand-total {
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    margin-top: 8px;
    padding-top: 12px;
    font-size: 1.1rem;
    font-weight: 700;
    color: white;
}

.adm-mod .bill-total-row.grand-total span:last-child {
    color: #fbbf24;
}

/* HMO Claims Summary */
.adm-mod .adm-hmo-claims-summary {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    border: 1px solid #e9ecef;
    border-left: 4px solid #28a745;
}

.adm-mod .adm-hmo-claims-summary h5 {
    font-size: 0.95rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e9ecef;
}

.adm-mod .hmo-claims-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
}

.adm-mod .hmo-claim-stat {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 10px;
    background: #f8f9fa;
    border-radius: 6px;
    font-size: 0.85rem;
}

.adm-mod .hmo-claim-stat .claim-label {
    color: #6c757d;
}

.adm-mod .hmo-claim-stat .claim-value {
    font-weight: 600;
    color: #212529;
}

.adm-mod .hmo-claim-stat.approved .claim-value { color: #198754; }
.adm-mod .hmo-claim-stat.pending .claim-value { color: #fd7e14; }
.adm-mod .hmo-claim-stat.rejected .claim-value { color: #dc3545; }
.adm-mod .hmo-claim-stat.awaiting .claim-value { color: #6f42c1; }
.adm-mod .hmo-claim-stat.express .claim-value { color: #0dcaf0; }

.adm-mod .admission-timeline-section {
    margin-top: 1rem;
}

.adm-mod .adm-mod-toggle-timeline {
    width: 100%;
}

.adm-mod .admission-timeline {
    margin-top: 1rem;
    max-height: 300px;
    overflow-y: auto;
}

.adm-mod .timeline-day {
    margin-bottom: 1rem;
    padding: 12px;
    background: white;
    border-radius: 8px;
    border-left: 3px solid var(--hospital-primary, #0d6efd);
}

.adm-mod .timeline-day-header {
    font-weight: 600;
    color: var(--hospital-primary, #0d6efd);
    margin-bottom: 8px;
    font-size: 0.85rem;
}

.adm-mod .timeline-day-items {
    font-size: 0.8rem;
    color: #495057;
}

.adm-mod .timeline-day-items div {
    display: flex;
    justify-content: space-between;
    padding: 4px 0;
}

/* Full Detail Modal */
.adm-mod-full-detail-modal .modal-header {
    background: linear-gradient(135deg, #1e3a5f 0%, #2c5282 100%);
    color: white;
    border-radius: 0.3rem 0.3rem 0 0;
}

.adm-mod-full-detail-modal .modal-header .close {
    color: white;
    opacity: 0.8;
}

.adm-mod-full-detail-modal .modal-header .close:hover {
    opacity: 1;
}

.adm-mod-full-detail-modal .full-detail-table {
    font-size: 0.85rem;
}

.adm-mod-full-detail-modal .full-detail-table th {
    background: #f8f9fa;
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
    color: #6c757d;
}

.adm-mod-full-detail-modal .validation-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
}

.adm-mod-full-detail-modal .validation-badge.approved { background: rgba(25,135,84,0.15); color: #198754; }
.adm-mod-full-detail-modal .validation-badge.pending { background: rgba(253,126,20,0.15); color: #fd7e14; }
.adm-mod-full-detail-modal .validation-badge.rejected { background: rgba(220,53,69,0.15); color: #dc3545; }
.adm-mod-full-detail-modal .validation-badge.awaiting_code { background: rgba(111,66,193,0.15); color: #6f42c1; }

/* Print Bill Modal (standalone for non-billing workbenches) */
.adm-mod-print-modal .modal-header {
    background: linear-gradient(135deg, #1e3a5f 0%, #2c5282 100%);
    color: white;
}

.adm-mod-print-modal .modal-header .close {
    color: white;
    opacity: 0.8;
}

.adm-mod-print-modal .receipt-tab {
    padding: 0.75rem 1.5rem;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-weight: 600;
    color: #6c757d;
    transition: all 0.2s;
}

.adm-mod-print-modal .receipt-tab:hover {
    color: var(--hospital-primary, #0d6efd);
}

.adm-mod-print-modal .receipt-tab.active {
    color: var(--hospital-primary, #0d6efd);
    border-bottom-color: var(--hospital-primary, #0d6efd);
}

.adm-mod-print-modal .receipt-content {
    margin-bottom: 1rem;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 0.5rem;
    max-height: 600px;
    overflow-y: auto;
}
</style>

<div class="adm-mod">
    <div class="admissions-tab-header">
        <h4><i class="mdi mdi-hospital-building"></i> Admission History</h4>
        <div class="admissions-toolbar">
            <button class="btn btn-sm btn-outline-info adm-mod-history-btn" title="Full admission history">
                <i class="mdi mdi-history"></i> History
            </button>
            <button class="btn btn-sm btn-secondary adm-mod-refresh-btn">
                <i class="mdi mdi-refresh"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Admissions List / Detail Split View -->
    <div class="admissions-split-view">
        <!-- Left: Admissions List -->
        <div class="admissions-list-panel" id="adm-mod-list-panel">
            <div class="admissions-table-container">
                <table class="table table-hover table-sm adm-mod-table">
                    <thead>
                        <tr>
                            <th>Admitted</th>
                            <th>Discharged</th>
                            <th>Days</th>
                            <th>Ward/Bed</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody class="adm-mod-tbody">
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="mdi mdi-hospital-building" style="font-size: 3rem;"></i>
                                <p>No admission history found</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Right: Admission Detail -->
        <div class="admission-detail-panel" id="adm-mod-detail-panel">
            <button class="btn btn-sm btn-light adm-mod-expand-list-btn" style="display:none;" title="Show admission list">
                <i class="mdi mdi-chevron-right"></i> Admissions
            </button>
            <div class="admission-detail-placeholder adm-mod-placeholder">
                <i class="mdi mdi-gesture-tap"></i>
                <p>Select an admission to view details</p>
            </div>

            <!-- Admission Detail Content (hidden by default) -->
            <div class="admission-detail-content adm-mod-detail-content" style="display: none;">
                <!-- Admission Header -->
                <div class="admission-detail-header">
                    <div class="admission-status-badge adm-mod-status-badge">
                        <i class="mdi mdi-check-circle"></i> Discharged
                    </div>
                    <div class="admission-detail-actions">
                        <button class="btn btn-sm btn-outline-primary adm-mod-full-detail-btn" title="Full detail view">
                            <i class="mdi mdi-open-in-new"></i>
                        </button>
                        <button class="btn btn-sm btn-warning adm-mod-print-bill-btn">
                            <i class="mdi mdi-printer"></i> Print Bill
                        </button>
                    </div>
                </div>

                <!-- Admission Info Cards -->
                <div class="admission-info-cards">
                    <div class="admission-info-card">
                        <div class="info-card-icon"><i class="mdi mdi-calendar-check"></i></div>
                        <div class="info-card-content">
                            <span class="info-label">Admitted</span>
                            <span class="info-value adm-mod-admitted-date">-</span>
                        </div>
                    </div>
                    <div class="admission-info-card">
                        <div class="info-card-icon"><i class="mdi mdi-calendar-remove"></i></div>
                        <div class="info-card-content">
                            <span class="info-label">Discharged</span>
                            <span class="info-value adm-mod-discharge-date">-</span>
                        </div>
                    </div>
                    <div class="admission-info-card">
                        <div class="info-card-icon"><i class="mdi mdi-clock-outline"></i></div>
                        <div class="info-card-content">
                            <span class="info-label">Length of Stay</span>
                            <span class="info-value adm-mod-los">-</span>
                        </div>
                    </div>
                    <div class="admission-info-card">
                        <div class="info-card-icon"><i class="mdi mdi-bed"></i></div>
                        <div class="info-card-content">
                            <span class="info-label">Ward / Bed</span>
                            <span class="info-value adm-mod-ward-bed">-</span>
                        </div>
                    </div>
                    <div class="admission-info-card full-width">
                        <div class="info-card-icon"><i class="mdi mdi-doctor"></i></div>
                        <div class="info-card-content">
                            <span class="info-label">Attending Doctor</span>
                            <span class="info-value adm-mod-doctor">-</span>
                        </div>
                    </div>
                    <div class="admission-info-card full-width">
                        <div class="info-card-icon"><i class="mdi mdi-clipboard-text"></i></div>
                        <div class="info-card-content">
                            <span class="info-label">Admission Reason</span>
                            <span class="info-value adm-mod-reason">-</span>
                        </div>
                    </div>
                </div>

                <!-- HMO Claims Summary (shown only when HMO claims exist) -->
                <div class="adm-hmo-claims-summary adm-mod-hmo-section" style="display: none;">
                    <h5><i class="mdi mdi-shield-check"></i> HMO Claims Summary</h5>
                    <div class="mb-2" style="font-size: 0.85rem;">
                        <span class="text-muted">HMO:</span> <strong class="adm-mod-hmo-name">-</strong>
                        <span class="ml-3 text-muted">HMO No:</span> <strong class="adm-mod-hmo-no">-</strong>
                        <span class="ml-3 text-muted">Items:</span> <strong class="adm-mod-hmo-items">0</strong>
                    </div>
                    <div class="hmo-claims-grid">
                        <div class="hmo-claim-stat approved">
                            <span class="claim-label">Approved</span>
                            <span class="claim-value adm-mod-hmo-approved">₦0</span>
                        </div>
                        <div class="hmo-claim-stat pending">
                            <span class="claim-label">Pending</span>
                            <span class="claim-value adm-mod-hmo-pending">₦0</span>
                        </div>
                        <div class="hmo-claim-stat rejected">
                            <span class="claim-label">Rejected</span>
                            <span class="claim-value adm-mod-hmo-rejected">₦0</span>
                        </div>
                        <div class="hmo-claim-stat awaiting">
                            <span class="claim-label">Awaiting Code</span>
                            <span class="claim-value adm-mod-hmo-awaiting">₦0</span>
                        </div>
                        <div class="hmo-claim-stat express" style="grid-column: span 2;">
                            <span class="claim-label">Express</span>
                            <span class="claim-value adm-mod-hmo-express">₦0</span>
                        </div>
                    </div>
                </div>

                <!-- Categorized Bill Summary -->
                <div class="admission-bill-summary">
                    <h5><i class="mdi mdi-format-list-bulleted"></i> Bill Summary by Category</h5>
                    <div class="bill-categories adm-mod-categories">
                        <!-- Categories will be populated dynamically -->
                    </div>
                </div>

                <!-- Bill Totals -->
                <div class="admission-bill-totals">
                    <div class="bill-total-row">
                        <span>Gross Total:</span>
                        <span class="adm-mod-gross-total">₦0.00</span>
                    </div>
                    <div class="bill-total-row discount">
                        <span>Total Discount:</span>
                        <span class="adm-mod-total-discount">-₦0.00</span>
                    </div>
                    <div class="bill-total-row hmo">
                        <span>HMO Coverage:</span>
                        <span class="adm-mod-hmo-coverage">-₦0.00</span>
                    </div>
                    <div class="bill-total-row paid">
                        <span>Paid:</span>
                        <span class="adm-mod-paid-amount">-₦0.00</span>
                    </div>
                    <div class="bill-total-row grand-total">
                        <span>Balance Due:</span>
                        <span class="adm-mod-balance-due">₦0.00</span>
                    </div>
                </div>

                <!-- Day-by-Day Timeline Toggle -->
                <div class="admission-timeline-section">
                    <button class="btn btn-sm btn-outline-primary adm-mod-toggle-timeline">
                        <i class="mdi mdi-timeline"></i> Show Day-by-Day Breakdown
                    </button>
                    <div class="admission-timeline adm-mod-timeline" style="display: none;">
                        <!-- Timeline populated dynamically -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Full Detail Modal -->
<div class="modal fade adm-mod-full-detail-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="mdi mdi-hospital-building"></i> Admission Full Detail</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <div class="row mb-3">
                    <div class="col-md-3"><strong>Patient:</strong> <span class="adm-mod-fd-patient">-</span></div>
                    <div class="col-md-3"><strong>File No:</strong> <span class="adm-mod-fd-fileno">-</span></div>
                    <div class="col-md-3"><strong>Admitted:</strong> <span class="adm-mod-fd-admitted">-</span></div>
                    <div class="col-md-3"><strong>Discharged:</strong> <span class="adm-mod-fd-discharged">-</span></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3"><strong>LOS:</strong> <span class="adm-mod-fd-los">-</span></div>
                    <div class="col-md-3"><strong>Ward/Bed:</strong> <span class="adm-mod-fd-wardbed">-</span></div>
                    <div class="col-md-3"><strong>Doctor:</strong> <span class="adm-mod-fd-doctor">-</span></div>
                    <div class="col-md-3"><strong>Priority:</strong> <span class="adm-mod-fd-priority">-</span></div>
                </div>
                <div class="mb-3">
                    <strong>Reason:</strong> <span class="adm-mod-fd-reason">-</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped full-detail-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>Price</th>
                                <th>Amount</th>
                                <th>Discount</th>
                                <th>HMO Claims</th>
                                <th>Payable</th>
                                <th>Status</th>
                                <th>Auth Code</th>
                                <th>Validated By</th>
                                <th>Paid</th>
                            </tr>
                        </thead>
                        <tbody class="adm-mod-fd-tbody">
                        </tbody>
                    </table>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="adm-mod-fd-hmo-section" style="display: none;">
                            <h6><i class="mdi mdi-shield-check text-success"></i> HMO Summary</h6>
                            <div class="adm-mod-fd-hmo-content"></div>
                        </div>
                    </div>
                    <div class="col-md-6 text-right">
                        <table class="table table-sm" style="width: auto; margin-left: auto;">
                            <tr><td class="text-muted">Gross Total:</td><td class="font-weight-bold adm-mod-fd-gross">₦0</td></tr>
                            <tr><td class="text-muted">Discount:</td><td class="text-success adm-mod-fd-discount">-₦0</td></tr>
                            <tr><td class="text-muted">HMO Coverage:</td><td class="text-success adm-mod-fd-hmo">-₦0</td></tr>
                            <tr><td class="text-muted">Paid:</td><td class="text-success adm-mod-fd-paid">-₦0</td></tr>
                            <tr style="border-top: 2px solid #212529;"><td class="font-weight-bold">Balance Due:</td><td class="font-weight-bold text-warning adm-mod-fd-balance">₦0</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-warning adm-mod-fd-print-btn"><i class="mdi mdi-printer"></i> Print Bill</button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Print Bill Modal (for non-billing workbenches that don't have receiptPreviewModal) -->
<div class="modal fade adm-mod-print-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="mdi mdi-printer"></i> Admission Bill</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex gap-2 mb-3" style="border-bottom: 2px solid #dee2e6;">
                    <button class="receipt-tab active adm-mod-print-tab" data-format="a4">A4 Format</button>
                    <button class="receipt-tab adm-mod-print-tab" data-format="thermal">Thermal Format</button>
                </div>
                <div class="receipt-content adm-mod-print-a4"></div>
                <div class="receipt-content adm-mod-print-thermal" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary adm-mod-do-print-btn"><i class="mdi mdi-printer"></i> Print</button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
