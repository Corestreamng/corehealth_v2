{{-- Clinical Alerts Modal - Shared across all Clinical Workbenches --}}
<script>
    window.currentUserStaffId = @json(Auth::user()->staff->id ?? null);
    window.currentUserIsAdmin = @json(Auth::user()->hasRole(['SUPERADMIN', 'ADMIN']) ?? false);
</script>
<div class="modal fade" id="clinical-alerts-modal" tabindex="-1" role="dialog" aria-labelledby="clinicalAlertsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="clinicalAlertsModalLabel">
                    <i class="mdi mdi-alert-octagon"></i> Patient Clinical Alerts
                </h5>
                <button type="button" class="btn-close text-white btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="row m-0">
                    <!-- Left column: Add/Edit Alert Form -->
                    <div class="col-md-5 p-3 bg-light border-end">
                        <h6 class="mb-3" id="alert-form-title"><i class="mdi mdi-plus-circle"></i> Add New Alert</h6>
                        <form id="clinical-alert-form">
                            <input type="hidden" id="alert_id" name="alert_id" value="">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Alert Text <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="alert_text" name="alert_text" rows="3" required placeholder="Enter critical clinical warning..."></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Severity <span class="text-danger">*</span></label>
                                <select class="form-select" id="alert_severity" name="severity" required>
                                    <option value="high">High (Critical)</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="low">Low</option>
                                 </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Show To (Visibility)</label>
                                <div class="form-text text-muted mb-2 small">Select which departments should see this alert. Leave all unchecked to show everywhere.</div>
                                <div class="d-flex flex-wrap gap-2">
                                    <div class="form-check">
                                        <input class="form-check-input visibility-check" type="checkbox" value="nurses_maternity" id="vis_nurses">
                                        <label class="form-check-label" for="vis_nurses">Nurses/Maternity</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input visibility-check" type="checkbox" value="doctors" id="vis_doctors">
                                        <label class="form-check-label" for="vis_doctors">Doctors</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input visibility-check" type="checkbox" value="pharmacy" id="vis_pharmacy">
                                        <label class="form-check-label" for="vis_pharmacy">Pharmacy</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input visibility-check" type="checkbox" value="lab_imaging" id="vis_lab">
                                        <label class="form-check-label" for="vis_lab">Lab & Imaging</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input visibility-check" type="checkbox" value="records_reception" id="vis_records">
                                        <label class="form-check-label" for="vis_records">Health Records</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input visibility-check" type="checkbox" value="billing" id="vis_billing">
                                        <label class="form-check-label" for="vis_billing">Billing</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input visibility-check" type="checkbox" value="hmo" id="vis_hmo">
                                        <label class="form-check-label" for="vis_hmo">HMO</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-cancel-alert-edit" style="display:none;">Cancel Edit</button>
                                <button type="submit" class="btn btn-primary w-100" id="btn-save-alert">
                                    <i class="mdi mdi-content-save"></i> Save Alert
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Right column: Paginated list of active alerts -->
                    <div class="col-md-7 p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0"><i class="mdi mdi-format-list-bulleted"></i> Active Alerts <span class="badge bg-danger ms-1" id="alerts-total-count">0</span></h6>
                            <button class="btn btn-sm btn-outline-info" id="btn-refresh-alerts">
                                <i class="mdi mdi-refresh"></i> Refresh
                            </button>
                        </div>
                        
                        <div id="alerts-list-container" style="min-height: 200px;">
                            <div class="text-center py-4">
                                <i class="mdi mdi-loading mdi-spin mdi-36px text-muted"></i>
                                <p class="text-muted mt-2">Loading alerts...</p>
                            </div>
                        </div>

                        <!-- Pagination Controls -->
                        <div id="alerts-pagination" class="d-flex justify-content-between align-items-center border-top pt-2 mt-2" style="display:none !important;">
                            <small class="text-muted" id="alerts-page-info">Page 1 of 1</small>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary" id="alerts-prev-page" disabled>
                                    <i class="mdi mdi-chevron-left"></i> Prev
                                </button>
                                <button class="btn btn-outline-secondary" id="alerts-next-page" disabled>
                                    Next <i class="mdi mdi-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Clinical Alert Card Styles */
    .alert-card {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 0.5rem;
        padding: 0.75rem 1rem;
        margin-bottom: 0.75rem;
        transition: all 0.2s;
        border-left-width: 4px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    
    .alert-card.severity-high { border-left-color: #dc3545; background-color: #fff5f5; }
    .alert-card.severity-medium { border-left-color: #fd7e14; background-color: #fffcf5; }
    .alert-card.severity-low { border-left-color: #ffc107; background-color: #fffdf5; }
    
    .alert-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0.25rem;
    }
    
    .alert-text-content {
        font-weight: 600;
        font-size: 0.95rem;
        color: #212529;
        margin-bottom: 0.25rem;
    }

    .alert-meta {
        font-size: 0.7rem;
        color: #6c757d;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .alert-visibility-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 3px;
        align-items: center;
    }
    
    .alert-actions {
        opacity: 0.4;
        transition: opacity 0.2s;
        white-space: nowrap;
    }
    
    .alert-card:hover .alert-actions {
        opacity: 1;
    }

    /* ─── Sticky Header Alert Banner ─── */
    .sticky-header-alerts {
        /* Reset old column layout */
        display: block;
        max-height: none;
        overflow: visible;
    }

    .clinical-alerts-banner {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 5px 10px;
        border-radius: 6px;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        animation: alertPulse 3s ease-in-out infinite;
    }
    .clinical-alerts-banner:hover {
        filter: brightness(0.95);
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.12);
    }
    .clinical-alerts-banner.severity-high {
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: white;
    }
    .clinical-alerts-banner.severity-medium {
        background: linear-gradient(135deg, #fd7e14, #e8590c);
        color: white;
    }
    .clinical-alerts-banner.severity-low {
        background: linear-gradient(135deg, #ffc107, #e0a800);
        color: #212529;
    }
    .clinical-alerts-banner .banner-count {
        background: rgba(255,255,255,0.3);
        border-radius: 50%;
        width: 22px;
        height: 22px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 700;
    }
    .clinical-alerts-banner .banner-text {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 350px;
    }
    .clinical-alerts-banner .banner-cta {
        margin-left: auto;
        font-size: 0.7rem;
        opacity: 0.8;
        white-space: nowrap;
    }

    @keyframes alertPulse {
        0%, 100% { box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
        50% { box-shadow: 0 2px 12px rgba(220,53,69,0.25); }
    }

    .clinical-alerts-banner.severity-low { animation-name: alertPulseLow; }
    @keyframes alertPulseLow {
        0%, 100% { box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
        50% { box-shadow: 0 2px 12px rgba(255,193,7,0.25); }
    }
    .clinical-alerts-banner.severity-medium { animation-name: alertPulseMedium; }
    @keyframes alertPulseMedium {
        0%, 100% { box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
        50% { box-shadow: 0 2px 12px rgba(253,126,20,0.25); }
    }

    /* No alerts state */
    .clinical-alerts-banner.no-alerts {
        background: #f8f9fa;
        color: #6c757d;
        font-weight: 400;
        animation: none;
        cursor: default;
    }
</style>
