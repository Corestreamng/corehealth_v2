                                </thead>
                                <tbody id="rpt-all-body"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="rpt-pane-active" role="tabpanel">
                <div class="card card-modern">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0" id="rpt-active-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Body Code</th>
                                        <th>Patient</th>
                                        <th>File No.</th>
                                        <th>Death Type</th>
                                        <th>Admitted</th>
                                        <th>Days</th>
                                    </tr>
                                </thead>
                                <tbody id="rpt-active-body"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="rpt-pane-released" role="tabpanel">
                <div class="card card-modern">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0" id="rpt-released-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Body Code</th>
                                        <th>Patient</th>
                                        <th>File No.</th>
                                        <th>Death Type</th>
                                        <th>Admitted</th>
                                        <th>Released</th>
                                        <th>Days Stayed</th>
                                    </tr>
                                </thead>
                                <tbody id="rpt-released-body"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>{{-- end #pane-reports --}}

    </div>{{-- end .tab-content (morgue-main-tab-content) --}}
</div>{{-- end .content-wrapper --}}
<div class="modal fade" id="admitModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Admit to Morgue</h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="admit-form">
                    <input type="hidden" id="admit-death-record-id">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Patient Name</label>
                        <div id="admit-patient-name" class="form-control-plaintext text-primary fw-bold"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fridge No.</label>
                            <input type="text" class="form-control" id="admit-fridge" placeholder="e.g. F-102">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tray No.</label>
                            <input type="text" class="form-control" id="admit-tray" placeholder="e.g. T-05">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Daily Service Fee (Billing) <span class="text-danger">*</span></label>
                        <select class="form-select select2-morgue" id="admit-daily-service" required>
                            <option value="">-- Select Daily Rate --</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Admissions Notes</label>
                        <textarea class="form-control" id="admit-notes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="btn-save-admission">Admit Body</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Service Modal -->
<div class="modal fade" id="serviceModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Add Morgue Service</h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="service-form">
                    <input type="hidden" id="service-admission-id">
                    <div class="mb-3">
                        <label class="form-label">Select Service</label>
                        <select class="form-select select2-morgue" id="morgue-service-id" required>
                            <!-- Loaded via JS -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="morgue-service-qty" value="1" min="1">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" id="btn-save-service">Add to Bill</button>
            </div>
        </div>
    </div>
</div>

<!-- Release Modal -->
<div class="modal fade" id="releaseModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Release Body</h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="release-form">
                    <input type="hidden" id="release-admission-id">
                    <div class="mb-3">
                        <label class="form-label">Released To (Name) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="release-name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" class="form-control" id="release-phone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Release Notes</label>
                        <textarea class="form-control" id="release-notes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="btn-confirm-release">Confirm Release</button>
            </div>
        </div>
    </div>
</div>

@include('admin.partials.patient-form-modal')

{{-- ═══ PATIENT BILL MODAL ═══ --}}
<div class="modal fade" id="billModal" tabindex="-1" role="dialog" aria-labelledby="billModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="billModalLabel">
                    <i class="mdi mdi-receipt"></i> Patient Bill
                </h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 border-bottom bg-light">
                    <div id="bill-patient-info"><span class="text-muted small">Loading...</span></div>
                </div>
                <div class="p-3 border-bottom" id="bill-totals" style="display:none;">
                    <div class="row text-center">
                        <div class="col-4">
                            <small class="text-muted d-block">Total Billed</small>
                            <strong class="text-dark" id="bill-total-amount">&#8358;0</strong>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block">Paid</small>
                            <strong class="text-success" id="bill-paid-amount">&#8358;0</strong>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block">Outstanding</small>
                            <strong class="text-danger" id="bill-pending-amount">&#8358;0</strong>
                        </div>
                    </div>
                </div>
                <div id="bill-items-container" class="p-3">
                    <div class="text-center text-muted py-4" id="bill-loading">
                        <i class="mdi mdi-loading mdi-spin mdi-36px"></i>
                        <p class="mt-2">Loading bill...</p>
                    </div>
                    <div id="bill-items-list" style="display:none;">
                        <table class="table table-sm table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>Service</th>
                                    <th>Date</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-right">Unit</th>
                                    <th class="text-right">Total</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody id="bill-items-tbody"></tbody>
                        </table>
                    </div>
                    <div id="bill-empty" class="text-center text-muted py-4" style="display:none;">
                        <i class="mdi mdi-receipt-text-outline mdi-36px"></i>
                        <p class="mt-2">No morgue charges found for this patient.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btn-print-bill" class="btn btn-primary" onclick="printBillModal()" style="display:none;">
                    <i class="mdi mdi-printer"></i> Print Bill
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .card-modern {
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        border: none;
        transition: transform 0.2s;
    }
    .bg-gradient-danger  { background: linear-gradient(45deg,#ff5252,#f44336) !important; }
    .bg-gradient-info    { background: linear-gradient(45deg,#40c4ff,#2196f3) !important; }
    .bg-gradient-success { background: linear-gradient(45deg,#66bb6a,#43a047) !important; }
    .table th { font-weight: 700; color: #333; }

    /* Main tabs */
    #morgue-main-tabs .nav-link {
        color: #555;
        font-weight: 600;
        border-radius: 8px 8px 0 0;
        padding: 0.6rem 1.4rem;
    }
    #morgue-main-tabs .nav-link.active {
        background: #fff;
        border-bottom-color: #fff;
        color: #3f51b5;
    }

    /* Reports KPI cards */
    .rpt-stat-card {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: #fff;
        border-radius: 10px;
        padding: 0.9rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        margin-bottom: 1rem;
    }
    .rpt-stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .rpt-stat-icon i      { font-size: 1.3rem; color: #fff; }
    .rpt-stat-content h3  { margin: 0; font-size: 1.4rem; font-weight: 700; line-height: 1.1; }
    .rpt-stat-content p   { margin: 0; font-size: 0.72rem; color: #888; }

    /* Reports filter panel */
    .reports-filter-panel { background:#fff; border:1px solid #e8e8e8; border-radius:10px; }
    .reports-filter-panel .card-header { background:#f8f9fa; border-radius:10px 10px 0 0; border-bottom:1px solid #e8e8e8; }

    /* Report sub-tabs */
    #rpt-subtabs .nav-link         { font-size:0.85rem; color:#666; }
    #rpt-subtabs .nav-link.active  { color:#3f51b5; font-weight:600; }

    /* Bill offcanvas */
    #bill-items-tbody tr td   { font-size:0.82rem; vertical-align:middle; }
    .badge-paid    { background-color:#43a047; color:#fff; font-size:0.72rem; padding:0.25em 0.5em; border-radius:4px; }
    .badge-pending { background-color:#e53935; color:#fff; font-size:0.72rem; padding:0.25em 0.5em; border-radius:4px; }
</style>
@endpush

@push('scripts')
