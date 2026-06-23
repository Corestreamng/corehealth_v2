<style>
    /* =============================================
       REQUEST DETAILS MODAL STYLES
       ============================================= */
    #requestDetailsModal .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-bottom: none;
    }

    #requestDetailsModal .modal-header.lab-header {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    }

    #requestDetailsModal .modal-header.imaging-header {
        background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
        color: #333;
    }

    #requestDetailsModal .modal-header.imaging-header .close,
    #requestDetailsModal .modal-header.imaging-header .btn-close {
        color: #333;
    }

    #requestDetailsModal .modal-header.product-header {
        background: linear-gradient(135deg, #28a745 0%, #218838 100%);
    }

    #requestDetailsModal .modal-header.service-header {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    }

    .request-header-section h4 {
        font-weight: 700;
        color: #333;
    }

    .badge-lg {
        font-size: 0.85rem;
        padding: 0.4rem 0.8rem;
    }

    /* Timeline Styles */
    .timeline-vertical {
        position: relative;
        padding-left: 30px;
    }

    .timeline-vertical::before {
        content: '';
        position: absolute;
        left: 10px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e9ecef;
    }

    .timeline-item {
        position: relative;
        padding-bottom: 1.25rem;
        padding-left: 20px;
    }

    .timeline-item:last-child {
        padding-bottom: 0;
    }

    .timeline-item::before {
        content: '';
        position: absolute;
        left: -20px;
        top: 4px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #e9ecef;
        border: 2px solid white;
        z-index: 1;
    }

    .timeline-item.completed::before {
        background: #28a745;
    }

    .timeline-item.in-progress::before {
        background: #17a2b8;
        animation: pulse 1.5s infinite;
    }

    .timeline-item.pending::before {
        background: #6c757d;
    }

    .timeline-item .timeline-title {
        font-weight: 600;
        color: #333;
        margin-bottom: 0.25rem;
    }

    .timeline-item .timeline-subtitle {
        font-size: 0.8rem;
        color: #6c757d;
    }

    .timeline-item .timeline-meta {
        font-size: 0.75rem;
        color: #adb5bd;
    }

    .bg-warning-light {
        background-color: #fff9e6 !important;
    }

    /* Billing Badge Styles for Details Modal */
    .billing-badge.billing-pending {
        background: #fff3cd;
        color: #856404;
    }

    .billing-badge.billing-billed {
        background: #cce5ff;
        color: #004085;
    }

    .billing-badge.billing-paid {
        background: #d4edda;
        color: #155724;
    }

    /* Delivery Badge Styles for Details Modal */
    .delivery-badge.delivery-pending {
        background: #f8d7da;
        color: #721c24;
    }

    .delivery-badge.delivery-progress {
        background: #cce5ff;
        color: #004085;
    }

    .delivery-badge.delivery-completed {
        background: #d4edda;
        color: #155724;
    }
</style>

<!-- Request Details Modal -->
<div class="modal fade" id="requestDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" id="request-details-header">
                <h5 class="modal-title" id="request-details-title">
                    <i class="mdi mdi-clipboard-text"></i> Request Details
                </h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="request-details-body">
                <div class="text-center py-5" id="request-details-loading">
                    <i class="mdi mdi-loading mdi-spin mdi-36px text-primary"></i>
                    <p class="mt-2 mb-0">Loading request details...</p>
                </div>
                <div id="request-details-content" style="display: none;">
                    <!-- Header Section -->
                    <div class="request-header-section mb-4">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h4 class="mb-1" id="detail-request-no"></h4>
                                <span class="badge badge-lg" id="detail-type-badge"></span>
                            </div>
                            <div class="text-right">
                                <div class="mb-2">
                                    <span class="mr-2" id="detail-billing-badge"></span>
                                    <span id="detail-delivery-badge"></span>
                                </div>
                                <small class="text-muted" id="detail-requested-at"></small>
                            </div>
                        </div>
                    </div>

                    <!-- Service/Product Info -->
                    <div class="card-modern mb-3">
                        <div class="card-header py-2 bg-light">
                            <h6 class="mb-0"><i class="mdi mdi-information"></i> <span id="detail-info-title">Service Information</span></h6>
                        </div>
                        <div class="card-body py-3">
                            <div class="row">
                                <div class="col-md-8">
                                    <h5 class="mb-1" id="detail-item-name"></h5>
                                    <p class="text-muted mb-0" id="detail-item-category"></p>
                                    <div id="detail-dose-section" style="display: none;" class="mt-2">
                                        <small class="text-muted">Dosage:</small>
                                        <strong id="detail-dose"></strong>
                                    </div>
                                    <div id="detail-quantity-section" style="display: none;" class="mt-2">
                                        <small class="text-muted">Quantity:</small>
                                        <strong id="detail-quantity"></strong>
                                        <span class="text-muted"> × ₦<span id="detail-unit-price"></span></span>
                                    </div>
                                </div>
                                <div class="col-md-4 text-right">
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <td class="text-muted">Price:</td>
                                            <td class="text-right"><strong id="detail-price"></strong></td>
                                        </tr>
                                        <tr id="detail-hmo-row">
                                            <td class="text-success">HMO Covers:</td>
                                            <td class="text-right text-success"><strong id="detail-hmo-covers"></strong></td>
                                        </tr>
                                        <tr class="border-top">
                                            <td class="text-primary">Patient Pays:</td>
                                            <td class="text-right text-primary"><strong id="detail-payable"></strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Clinical Note (if any) -->
                    <div class="card-modern mb-3" id="detail-note-card" style="display: none;">
                        <div class="card-header py-2 bg-warning-light">
                            <h6 class="mb-0"><i class="mdi mdi-note-text"></i> Clinical Note</h6>
                        </div>
                        <div class="card-body py-3">
                            <p class="mb-0" id="detail-clinical-note"></p>
                        </div>
                    </div>

                    <!-- Timeline / Status History -->
                    <div class="card-modern mb-3">
                        <div class="card-header py-2 bg-light">
                            <h6 class="mb-0"><i class="mdi mdi-timeline"></i> Status Timeline</h6>
                        </div>
                        <div class="card-body py-3">
                            <div class="timeline-vertical" id="detail-timeline">
                                <!-- Timeline items will be populated by JS -->
                            </div>
                        </div>
                    </div>

                    <!-- Result Summary (Lab/Imaging only) -->
                    <div class="card-modern mb-3" id="detail-result-card" style="display: none;">
                        <div class="card-header py-2 bg-success text-white">
                            <h6 class="mb-0"><i class="mdi mdi-file-document"></i> Result Summary</h6>
                        </div>
                        <div class="card-body py-3">
                            <div id="detail-result-content">
                                <p class="text-muted mb-0" id="detail-result-summary"></p>
                            </div>
                            <div id="detail-no-result" style="display: none;">
                                <p class="text-muted mb-0"><i class="mdi mdi-clock-outline"></i> Result not yet available</p>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Info (if paid) -->
                    <div class="card-modern mb-0" id="detail-payment-card" style="display: none;">
                        <div class="card-header py-2 bg-success text-white">
                            <h6 class="mb-0"><i class="mdi mdi-cash-check"></i> Payment Information</h6>
                        </div>
                        <div class="card-body py-3">
                            <div class="row">
                                <div class="col-md-6">
                                    <small class="text-muted">Payment Reference:</small>
                                    <p class="mb-2"><strong id="detail-payment-ref"></strong></p>
                                </div>
                                <div class="col-md-6 text-right">
                                    <small class="text-muted">Payment Date:</small>
                                    <p class="mb-0"><strong id="detail-payment-date"></strong></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
