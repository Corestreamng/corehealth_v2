<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header py-2 text-white" style="border-radius: 12px 12px 0 0; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <h6 class="modal-title mb-0"><i class="mdi mdi-file-document-outline mr-1"></i>Request <span id="detail_request_id" class="font-weight-bold"></span></h6>
                <div class="ml-auto d-flex align-items-center">
                    <span id="detail_validation_status" class="mr-3"></span>
                    <span id="detail_coverage_mode" class="mr-3"></span>
                    <button type="button" data-bs-dismiss="modal" class="btn-close text-white ml-2 btn-close-white" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-3" style="font-size: 0.88rem;">
                {{-- Row 1: Patient + HMO + Request context --}}
                <div class="row mb-2">
                    {{-- Patient card --}}
                    <div class="col-md-4">
                        <div class="card-modern mb-0 h-100">
                            <div class="card-header py-1 px-2">
                                <strong class="text-primary"><i class="mdi mdi-account mr-1"></i>Patient</strong>
                            </div>
                            <div class="card-body p-2">
                                <p class="mb-1 font-weight-bold" id="detail_patient_name" style="font-size:1rem;"></p>
                                <div class="d-flex flex-wrap" style="gap:4px 12px; font-size:0.82rem;">
                                    <span><i class="mdi mdi-folder-account text-muted"></i> <span id="detail_file_no"></span></span>
                                    <span><i class="mdi mdi-gender-male-female text-muted"></i> <span id="detail_gender"></span></span>
                                    <span><i class="mdi mdi-cake-variant text-muted"></i> <span id="detail_age"></span></span>
                                    <span><i class="mdi mdi-phone text-muted"></i> <span id="detail_phone"></span></span>
                                </div>
                                <div id="detail_allergies_row" class="mt-1" style="display:none;">
                                    <small class="text-danger"><i class="mdi mdi-alert-circle"></i> Allergies: <span id="detail_allergies"></span></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- HMO card --}}
                    <div class="col-md-4">
                        <div class="card-modern mb-0 h-100">
                            <div class="card-header py-1 px-2 d-flex align-items-center justify-content-between">
                                <strong class="text-primary"><i class="mdi mdi-hospital-building mr-1"></i>HMO / Scheme</strong>
                                <button type="button" class="btn btn-link btn-sm p-0 text-muted" id="detail_edit_hmo_btn" title="Correct patient HMO" style="font-size: 0.85rem;">
                                    <i class="mdi mdi-pencil"></i>
                                </button>
                            </div>
                            <div class="card-body p-2">
                                <div id="detail_hmo_display">
                                    <p class="mb-1 font-weight-bold" id="detail_hmo_name"></p>
                                    <div style="font-size:0.82rem;">
                                        <div><i class="mdi mdi-card-account-details text-muted"></i> HMO#: <span id="detail_hmo_no" class="font-weight-bold"></span></div>
                                        <span id="detail_scheme_row"><i class="mdi mdi-tag-outline text-muted"></i> Scheme: <span id="detail_hmo_scheme" class="font-weight-bold"></span> <small class="text-muted" id="detail_hmo_scheme_code"></small></span>
                                    </div>
                                </div>
                                <div id="detail_hmo_edit_section" style="display:none;" class="mt-1">
                                    <div class="form-group mb-2">
                                        <label class="small font-weight-bold mb-1">HMO Provider</label>
                                        <select class="form-control form-control-sm" id="detail_edit_hmo_id" style="border-radius:6px;">
                                            @foreach($hmos as $hmo)
                                                <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group mb-2">
                                        <label class="small font-weight-bold mb-1">HMO Number</label>
                                        <input type="text" class="form-control form-control-sm" id="detail_edit_hmo_no" placeholder="Enter HMO number" style="border-radius:6px;">
                                    </div>
                                    <div class="d-flex justify-content-end" style="gap:6px;">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="detail_cancel_hmo_edit">Cancel</button>
                                        <button type="button" class="btn btn-sm btn-primary" id="detail_save_hmo_edit">
                                            <i class="mdi mdi-content-save mr-1"></i>Save
                                        </button>
                                    </div>
                                    <small class="text-muted d-block mt-1">
                                        <i class="mdi mdi-information-outline"></i> Changing HMO will recalculate tariffs for pending requests.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Context card --}}
                    <div class="col-md-4">
                        <div class="card-modern mb-0 h-100">
                            <div class="card-header py-1 px-2">
                                <strong class="text-primary"><i class="mdi mdi-clipboard-text mr-1"></i>Request Context</strong>
                            </div>
                            <div class="card-body p-2" style="font-size:0.82rem;">
                                <div><i class="mdi mdi-calendar text-muted"></i> <span id="detail_created_at"></span></div>
                                <div><i class="mdi mdi-account-tie text-muted"></i> Requested by: <strong id="detail_requested_by"></strong></div>
                                <div id="detail_doctor_row"><i class="mdi mdi-doctor text-muted"></i> Doctor: <strong id="detail_encounter_doctor"></strong></div>
                                <div id="detail_admission_row" style="display:none;"><i class="mdi mdi-bed text-muted"></i> Admitted — <span id="detail_admission_status" class="badge badge-info"></span></div>
                                <div id="detail_procedure_row" style="display:none;"><i class="mdi mdi-medical-bag text-muted"></i> Procedure: <span id="detail_procedure_name"></span></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Row 2: Item + Pricing --}}
                <div class="card-modern mb-2">
                    <div class="card-header py-1 px-2 d-flex align-items-center">
                        <strong class="text-primary"><i class="mdi mdi-cash-multiple mr-1"></i>Item & Pricing</strong>
                    </div>
                    <div class="card-body p-2">
                        <div class="row mb-2" style="font-size:0.85rem;">
                            <div class="col-md-6">
                                <span class="badge badge-secondary" id="detail_item_type"></span>
                                <span id="detail_category" class="text-muted ml-1" style="font-size:0.8rem;"></span>
                                <div class="font-weight-bold mt-1" id="detail_item_name" style="font-size:0.95rem;"></div>
                                <small class="text-muted" id="detail_item_code"></small>
                            </div>
                            <div class="col-md-6 text-right">
                                <span class="text-muted">Qty:</span> <strong id="detail_qty"></strong>
                                <span class="ml-2 text-muted">Unit Price:</span> <strong>₦<span id="detail_unit_price"></span></strong>
                                <span class="ml-2 text-muted">Total:</span> <strong>₦<span id="detail_total_price"></span></strong>
                            </div>
                        </div>
                        <table class="table table-sm table-bordered mb-0" style="font-size:0.85rem;">
                            <thead class="bg-light">
                                <tr>
                                    <th></th>
                                    <th class="text-center">Per Unit</th>
                                    <th class="text-center">Total (×<span id="detail_qty2"></span>)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><i class="mdi mdi-hospital text-info"></i> <strong>HMO Covers (Claims)</strong></td>
                                    <td class="text-center">₦<span id="detail_unit_claims"></span></td>
                                    <td class="text-center font-weight-bold text-info">₦<span id="detail_claims_amount"></span></td>
                                </tr>
                                <tr>
                                    <td><i class="mdi mdi-account-cash text-warning"></i> <strong>Patient Pays</strong></td>
                                    <td class="text-center">₦<span id="detail_unit_payable"></span></td>
                                    <td class="text-center font-weight-bold text-warning">₦<span id="detail_payable_amount"></span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Row 3: Validation + Submission --}}
                <div class="row">
                    <div class="col-md-6">
                        <div class="card-modern mb-0 h-100">
                            <div class="card-header py-1 px-2">
                                <strong class="text-primary"><i class="mdi mdi-check-decagram mr-1"></i>Validation</strong>
                            </div>
                            <div class="card-body p-2" style="font-size:0.85rem;">
                                <div class="mb-1"><strong>Auth Code:</strong> <span id="detail_auth_code">-</span></div>
                                <div class="mb-1"><strong>Validated By:</strong> <span id="detail_validated_by">-</span></div>
                                <div class="mb-1"><strong>Validated At:</strong> <span id="detail_validated_at">-</span></div>
                                <div><strong>Notes:</strong> <span id="detail_validation_notes" class="text-muted">-</span></div>
                                {{-- Reception validation sub-section --}}
                                <div id="detail_reception_validation" class="mt-2 pt-2" style="border-top:1px dashed #dee2e6; display:none;">
                                    <span class="badge badge-outline-info" style="border:1px solid #17a2b8;color:#17a2b8;font-size:0.75rem;"><i class="mdi mdi-check-decagram"></i> Reception Validated</span>
                                    <div class="mt-1"><strong>By:</strong> <span id="detail_reception_validated_by">-</span></div>
                                    <div><strong>At:</strong> <span id="detail_reception_validated_at">-</span></div>
                                    <div><strong>Notes:</strong> <span id="detail_reception_validation_notes" class="text-muted">-</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card-modern mb-0 h-100">
                            <div class="card-header py-1 px-2">
                                <strong class="text-primary"><i class="mdi mdi-send mr-1"></i>Submission / Billing</strong>
                            </div>
                            <div class="card-body p-2" style="font-size:0.85rem;">
                                <div class="mb-1"><strong>Payment ID:</strong> <span id="detail_payment_id">-</span></div>
                                <div class="mb-1"><strong>Submitted to HMO:</strong> <span id="detail_submitted_at">-</span></div>
                                <div><strong>Batch:</strong> <span id="detail_batch">-</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Audit trail (collapsible) --}}
                <div class="mt-2">
                    <a data-toggle="collapse" href="#auditTrailCollapse" class="text-muted" style="font-size:0.82rem;">
                        <i class="mdi mdi-history"></i> Audit Trail <i class="mdi mdi-chevron-down"></i>
                    </a>
                    <div class="collapse" id="auditTrailCollapse">
                        <table class="table table-sm table-striped mt-1 mb-0" style="font-size:0.8rem;">
                            <thead><tr><th>Event</th><th>By</th><th>Date</th><th>Changes</th></tr></thead>
                            <tbody id="detail_audit_tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-1" style="border-radius: 0 0 12px 12px;">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header text-white" style="border-radius: 12px 12px 0 0; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <h5 class="modal-title"><i class="mdi mdi-check-circle mr-2"></i>Approve Request</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <form id="approveForm">
                @csrf
                <input type="hidden" id="approve_request_id">
                <input type="hidden" id="approve_coverage_mode">
                <div class="modal-body">
                    <div class="alert alert-success" style="border-radius: 8px; border-left: 4px solid #28a745;">
                        <i class="mdi mdi-information mr-1"></i>
                        <strong>Confirm Approval:</strong> You are about to approve this HMO request.
                    </div>
                    <!-- Tariff Edit Section -->
                    <div class="tariff-edit-section mb-3">
                        <div class="card-modern mb-0" style="border-radius: 8px; border: 1px dashed #adb5bd;">
                            <div class="card-header px-3 py-2 tariff-toggle cursor-pointer" style="background: #f8f9fa; border-radius: 8px;">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span style="font-size: 0.85rem;">
                                        <i class="mdi mdi-tune-vertical mr-1 text-info"></i>
                                        <strong>Tariff Settings</strong>
                                        <span class="tariff-summary-text text-muted small ml-1"></span>
                                    </span>
                                    <i class="mdi mdi-chevron-down tariff-chevron" style="transition: transform 0.3s;"></i>
                                </div>
                            </div>
                            <div class="tariff-panel" style="display: none;">
                                <div class="card-body px-3 pt-2 pb-3" style="border-top: 1px solid #dee2e6;">
                                    <div class="tariff-loading text-center py-3">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                        <span class="ml-2 small text-muted">Loading tariff details...</span>
                                    </div>
                                    <div class="tariff-fields" style="display:none;">
                                        <div class="form-group mb-2">
                                            <label class="small font-weight-bold mb-1">Display Name</label>
                                            <input type="text" class="form-control form-control-sm tariff-display-name" style="border-radius: 6px;">
                                            <small class="form-text text-muted" style="font-size: 0.7rem;">Overrides item name in claims/reports. Leave blank for original.</small>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4 mb-2">
                                                <label class="small font-weight-bold mb-1">Coverage Mode</label>
                                                <select class="form-control form-control-sm tariff-coverage-mode" style="border-radius: 6px;">
                                                    <option value="express">Express</option>
                                                    <option value="primary">Primary</option>
                                                    <option value="secondary">Secondary</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="small font-weight-bold mb-1">Claims Amount</label>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm tariff-claims-amount" style="border-radius: 6px;">
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="small font-weight-bold mb-1">Payable Amount</label>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm tariff-payable-amount" style="border-radius: 6px;">
                                            </div>
                                        </div>
                                        <div class="tariff-scheme-option mt-1" style="display:none;">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input tariff-apply-scheme" id="approve_apply_scheme">
                                                <label class="custom-control-label small" for="approve_apply_scheme">
                                                    Apply to all HMOs under <strong class="tariff-scheme-name"></strong>
                                                    (<span class="tariff-scheme-count">0</span> HMOs)
                                                </label>
                                            </div>
                                        </div>
                                        <div class="tariff-current-info mt-2 p-2 small" style="border-radius: 6px; font-size: 0.75rem; background: #eef2ff;">
                                            <i class="mdi mdi-information-outline mr-1 text-info"></i>
                                            <strong>This request:</strong>
                                            Qty: <span class="tariff-current-qty">-</span> |
                                            Claims: ₦<span class="tariff-current-claims">-</span> |
                                            Payable: ₦<span class="tariff-current-payable">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group" id="auth_code_div" style="display:none;">
                        <label class="font-weight-bold">Authorization Code <small class="text-muted font-weight-normal">(optional — enter later if not available)</small></label>
                        <input type="text" class="form-control" id="auth_code" name="auth_code" placeholder="Enter HMO auth code" style="border-radius: 6px;">
                        <small class="form-text text-danger" id="error_auth_code"></small>
                        <small class="form-text text-muted">If left blank for secondary coverage, request moves to "Awaiting Code" queue</small>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Validation Notes</label>
                        <textarea class="form-control" id="approve_notes" name="validation_notes" rows="3" placeholder="Optional notes..." style="border-radius: 6px;"></textarea>
                        <small class="form-text text-danger" id="error_validation_notes"></small>
                    </div>
                </div>
                <div class="modal-footer" style="border-radius: 0 0 12px 12px;">
                    <button type="button" class="btn btn-secondary" style="border-radius: 6px;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" style="border-radius: 6px;">
                        <i class="mdi mdi-check mr-1"></i>Approve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header text-white" style="border-radius: 12px 12px 0 0; background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);">
                <h5 class="modal-title"><i class="mdi mdi-close-circle mr-2"></i>Reject Request</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <form id="rejectForm">
                @csrf
                <input type="hidden" id="reject_request_id">
                <div class="modal-body">
                    <div class="alert alert-warning" style="border-radius: 8px; border-left: 4px solid #ffc107;">
                        <i class="mdi mdi-alert mr-1"></i>
                        <strong>Confirm Rejection:</strong> You are about to reject this HMO request.
                    </div>
                    <!-- Tariff Edit Section -->
                    <div class="tariff-edit-section mb-3">
                        <div class="card-modern mb-0" style="border-radius: 8px; border: 1px dashed #adb5bd;">
                            <div class="card-header px-3 py-2 tariff-toggle cursor-pointer" style="background: #f8f9fa; border-radius: 8px;">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span style="font-size: 0.85rem;">
                                        <i class="mdi mdi-tune-vertical mr-1 text-info"></i>
                                        <strong>Tariff Settings</strong>
                                        <span class="tariff-summary-text text-muted small ml-1"></span>
                                    </span>
                                    <i class="mdi mdi-chevron-down tariff-chevron" style="transition: transform 0.3s;"></i>
                                </div>
                            </div>
                            <div class="tariff-panel" style="display: none;">
                                <div class="card-body px-3 pt-2 pb-3" style="border-top: 1px solid #dee2e6;">
                                    <div class="tariff-loading text-center py-3">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                        <span class="ml-2 small text-muted">Loading tariff details...</span>
                                    </div>
                                    <div class="tariff-fields" style="display:none;">
                                        <div class="form-group mb-2">
                                            <label class="small font-weight-bold mb-1">Display Name</label>
                                            <input type="text" class="form-control form-control-sm tariff-display-name" style="border-radius: 6px;">
                                            <small class="form-text text-muted" style="font-size: 0.7rem;">Overrides item name in claims/reports. Leave blank for original.</small>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4 mb-2">
                                                <label class="small font-weight-bold mb-1">Coverage Mode</label>
                                                <select class="form-control form-control-sm tariff-coverage-mode" style="border-radius: 6px;">
                                                    <option value="express">Express</option>
                                                    <option value="primary">Primary</option>
                                                    <option value="secondary">Secondary</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="small font-weight-bold mb-1">Claims Amount</label>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm tariff-claims-amount" style="border-radius: 6px;">
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="small font-weight-bold mb-1">Payable Amount</label>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm tariff-payable-amount" style="border-radius: 6px;">
                                            </div>
                                        </div>
                                        <div class="tariff-scheme-option mt-1" style="display:none;">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input tariff-apply-scheme" id="reject_apply_scheme">
                                                <label class="custom-control-label small" for="reject_apply_scheme">
                                                    Apply to all HMOs under <strong class="tariff-scheme-name"></strong>
                                                    (<span class="tariff-scheme-count">0</span> HMOs)
                                                </label>
                                            </div>
                                        </div>
                                        <div class="tariff-current-info mt-2 p-2 small" style="border-radius: 6px; font-size: 0.75rem; background: #eef2ff;">
                                            <i class="mdi mdi-information-outline mr-1 text-info"></i>
                                            <strong>This request:</strong>
                                            Qty: <span class="tariff-current-qty">-</span> |
                                            Claims: ₦<span class="tariff-current-claims">-</span> |
                                            Payable: ₦<span class="tariff-current-payable">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Rejection Reason <span class="text-danger">*</span></label>
                        <select class="form-control" id="rejection_reason" name="rejection_reason" required style="border-radius: 6px;">
                            <option value="">-- Select Reason --</option>
                            @foreach($rejectionReasons as $key => $reason)
                                <option value="{{ $key }}">{{ $reason }}</option>
                            @endforeach
                        </select>
                        <small class="form-text text-danger" id="error_rejection_reason"></small>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Additional Notes</label>
                        <textarea class="form-control" id="reject_notes" name="validation_notes" rows="3" placeholder="Optional additional notes..." style="border-radius: 6px;"></textarea>
                        <small class="form-text text-danger" id="error_reject_notes"></small>
                    </div>
                </div>
                <div class="modal-footer" style="border-radius: 0 0 12px 12px;">
                    <button type="button" class="btn btn-secondary" style="border-radius: 6px;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" style="border-radius: 6px;">
                        <i class="mdi mdi-close mr-1"></i>Reject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reverse Approval Modal -->
<div class="modal fade" id="reverseModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header text-dark" style="border-radius: 12px 12px 0 0; background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
                <h5 class="modal-title"><i class="mdi mdi-undo mr-2"></i>Reverse Approval</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close" aria-label="Close"></button>
            </div>
            <form id="reverseForm">
                @csrf
                <input type="hidden" id="reverse_request_id">
                <div class="modal-body">
                    <div class="alert alert-warning" style="border-radius: 8px; border-left: 4px solid #ffc107;">
                        <i class="mdi mdi-alert-circle mr-1"></i>
                        <strong>⚠️ Warning:</strong> You are about to reverse this approval and set the request back to pending.
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Reason for Reversal <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reverse_reason" name="reason" rows="3" placeholder="Please provide reason for reversing this approval..." required style="border-radius: 6px;"></textarea>
                        <small class="form-text text-danger" id="error_reverse_reason"></small>
                    </div>
                </div>
                <div class="modal-footer" style="border-radius: 0 0 12px 12px;">
                    <button type="button" class="btn btn-secondary" style="border-radius: 6px;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" style="border-radius: 6px;">
                        <i class="mdi mdi-undo mr-1"></i>Reverse to Pending
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Re-approve Modal -->
<div class="modal fade" id="reapproveModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header text-white" style="border-radius: 12px 12px 0 0; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <h5 class="modal-title"><i class="mdi mdi-check-decagram mr-2"></i>Re-approve Request</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <form id="reapproveForm">
                @csrf
                <input type="hidden" id="reapprove_request_id">
                <input type="hidden" id="reapprove_coverage_mode">
                <div class="modal-body">
                    <div class="alert alert-success" style="border-radius: 8px; border-left: 4px solid #28a745;">
                        <i class="mdi mdi-information mr-1"></i>
                        <strong>Re-approve:</strong> You are about to re-approve a previously rejected request.
                    </div>
                    <!-- Tariff Edit Section -->
                    <div class="tariff-edit-section mb-3">
                        <div class="card-modern mb-0" style="border-radius: 8px; border: 1px dashed #adb5bd;">
                            <div class="card-header px-3 py-2 tariff-toggle cursor-pointer" style="background: #f8f9fa; border-radius: 8px;">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span style="font-size: 0.85rem;">
                                        <i class="mdi mdi-tune-vertical mr-1 text-info"></i>
                                        <strong>Tariff Settings</strong>
                                        <span class="tariff-summary-text text-muted small ml-1"></span>
                                    </span>
                                    <i class="mdi mdi-chevron-down tariff-chevron" style="transition: transform 0.3s;"></i>
                                </div>
                            </div>
                            <div class="tariff-panel" style="display: none;">
                                <div class="card-body px-3 pt-2 pb-3" style="border-top: 1px solid #dee2e6;">
                                    <div class="tariff-loading text-center py-3">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                        <span class="ml-2 small text-muted">Loading tariff details...</span>
                                    </div>
                                    <div class="tariff-fields" style="display:none;">
                                        <div class="form-group mb-2">
                                            <label class="small font-weight-bold mb-1">Display Name</label>
                                            <input type="text" class="form-control form-control-sm tariff-display-name" style="border-radius: 6px;">
                                            <small class="form-text text-muted" style="font-size: 0.7rem;">Overrides item name in claims/reports. Leave blank for original.</small>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4 mb-2">
                                                <label class="small font-weight-bold mb-1">Coverage Mode</label>
                                                <select class="form-control form-control-sm tariff-coverage-mode" style="border-radius: 6px;">
                                                    <option value="express">Express</option>
                                                    <option value="primary">Primary</option>
                                                    <option value="secondary">Secondary</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="small font-weight-bold mb-1">Claims Amount</label>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm tariff-claims-amount" style="border-radius: 6px;">
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="small font-weight-bold mb-1">Payable Amount</label>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm tariff-payable-amount" style="border-radius: 6px;">
                                            </div>
                                        </div>
                                        <div class="tariff-scheme-option mt-1" style="display:none;">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input tariff-apply-scheme" id="reapprove_apply_scheme">
                                                <label class="custom-control-label small" for="reapprove_apply_scheme">
                                                    Apply to all HMOs under <strong class="tariff-scheme-name"></strong>
                                                    (<span class="tariff-scheme-count">0</span> HMOs)
                                                </label>
                                            </div>
                                        </div>
                                        <div class="tariff-current-info mt-2 p-2 small" style="border-radius: 6px; font-size: 0.75rem; background: #eef2ff;">
                                            <i class="mdi mdi-information-outline mr-1 text-info"></i>
                                            <strong>This request:</strong>
                                            Qty: <span class="tariff-current-qty">-</span> |
                                            Claims: ₦<span class="tariff-current-claims">-</span> |
                                            Payable: ₦<span class="tariff-current-payable">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group" id="reapprove_auth_code_div" style="display:none;">
                        <label class="font-weight-bold">Authorization Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="reapprove_auth_code" name="auth_code" placeholder="Enter HMO auth code" style="border-radius: 6px;">
                        <small class="form-text text-danger" id="error_reapprove_auth_code"></small>
                        <small class="form-text text-muted">Required for secondary coverage</small>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Validation Notes</label>
                        <textarea class="form-control" id="reapprove_notes" name="validation_notes" rows="3" placeholder="Optional notes for re-approval..." style="border-radius: 6px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-radius: 0 0 12px 12px;">
                    <button type="button" class="btn btn-secondary" style="border-radius: 6px;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" style="border-radius: 6px;">
                        <i class="mdi mdi-check mr-1"></i>Re-approve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Batch Approve Modal -->
<div class="modal fade" id="batchApproveModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header text-white" style="border-radius: 12px 12px 0 0; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <h5 class="modal-title"><i class="mdi mdi-check-all mr-2"></i>Batch Approve Requests</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <form id="batchApproveForm">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-success" style="border-radius: 8px; border-left: 4px solid #28a745;">
                        <i class="mdi mdi-information mr-1"></i>
                        <strong>Batch Approve:</strong> You are about to approve <strong><span id="batchApproveCount">0</span></strong> requests.
                        <br><small class="text-warning"><i class="mdi mdi-alert mr-1"></i>Secondary coverage requests will be skipped (require individual auth codes).</small>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Validation Notes (applied to all)</label>
                        <textarea class="form-control" name="validation_notes" rows="3" placeholder="Optional notes..." style="border-radius: 6px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-radius: 0 0 12px 12px;">
                    <button type="button" class="btn btn-secondary" style="border-radius: 6px;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" style="border-radius: 6px;">
                        <i class="mdi mdi-check-all mr-1"></i>Approve All
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Batch Reject Modal -->
<div class="modal fade" id="batchRejectModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header text-white" style="border-radius: 12px 12px 0 0; background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);">
                <h5 class="modal-title"><i class="mdi mdi-close-circle-multiple mr-2"></i>Batch Reject Requests</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <form id="batchRejectForm">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-warning" style="border-radius: 8px; border-left: 4px solid #ffc107;">
                        <i class="mdi mdi-alert mr-1"></i>
                        <strong>Batch Reject:</strong> You are about to reject <strong><span id="batchRejectCount">0</span></strong> requests.
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Rejection Reason <span class="text-danger">*</span></label>
                        <select class="form-control" name="rejection_reason" required style="border-radius: 6px;">
                            <option value="">-- Select Reason --</option>
                            @foreach($rejectionReasons as $key => $reason)
                                <option value="{{ $key }}">{{ $reason }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Additional Notes</label>
                        <textarea class="form-control" name="validation_notes" rows="3" placeholder="Optional additional notes..." style="border-radius: 6px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-radius: 0 0 12px 12px;">
                    <button type="button" class="btn btn-secondary" style="border-radius: 6px;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" style="border-radius: 6px;">
                        <i class="mdi mdi-close mr-1"></i>Reject All
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Batch Auth Code Modal -->
<div class="modal fade" id="batchAuthCodeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header text-white" style="border-radius: 12px 12px 0 0; background: linear-gradient(135deg, #7c4dff 0%, #b388ff 100%);">
                <h5 class="modal-title"><i class="mdi mdi-key-plus mr-2"></i>Batch Enter Auth Codes</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <form id="batchAuthCodeForm">
                @csrf
                <div class="modal-body">
                    <div class="alert" style="border-radius: 8px; border-left: 4px solid #7c4dff; background: #f3f0ff;">
                        <i class="mdi mdi-information mr-1" style="color: #7c4dff;"></i>
                        <strong>Auth Code Entry:</strong> You are submitting auth codes for <strong><span id="batchAuthCodeCount">0</span></strong> requests.
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Auth Code Mode</label>
                        <div class="mt-2">
                            <label class="mr-3">
                                <input type="radio" name="batch_ac_mode" value="shared" checked> <strong>Shared</strong> — Same auth code for all
                            </label>
                            <label>
                                <input type="radio" name="batch_ac_mode" value="individual"> <strong>Individual</strong> — Different code per request
                            </label>
                        </div>
                    </div>
                    <div class="form-group" id="batch_ac_shared_group">
                        <label class="font-weight-bold">Auth Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="batch_ac_shared_code" placeholder="Enter auth code" style="border-radius: 6px;" required>
                    </div>
                    <div id="batch_ac_individual_group" style="display:none;">
                        {{-- Populated by JS --}}
                    </div>
                </div>
                <div class="modal-footer" style="border-radius: 0 0 12px 12px;">
                    <button type="button" class="btn btn-secondary" style="border-radius: 6px;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" style="border-radius: 6px; background: #7c4dff; color: #fff; border: none;">
                        <i class="mdi mdi-key-plus mr-1"></i>Submit Auth Codes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Single Auth Code Modal -->
<div class="modal fade" id="singleAuthCodeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header text-white" style="border-radius: 12px 12px 0 0; background: linear-gradient(135deg, #7c4dff 0%, #b388ff 100%);">
                <h5 class="modal-title"><i class="mdi mdi-key-plus mr-2"></i>Enter Auth Code</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <form id="singleAuthCodeForm">
                @csrf
                <input type="hidden" id="single_ac_request_id">
                <div class="modal-body">
                    <div class="alert" style="border-radius: 8px; border-left: 4px solid #7c4dff; background: #f3f0ff;">
                        <i class="mdi mdi-information mr-1" style="color: #7c4dff;"></i>
                        Enter the authorization code for request <strong>#<span id="single_ac_request_label"></span></strong>.
                    </div>
                    <div id="single_ac_request_info" class="mb-3" style="display:none;">
                        <div class="d-flex align-items-center p-2" style="background:#f8f9fa; border-radius:8px;">
                            <div>
                                <div class="font-weight-bold" id="single_ac_patient_name"></div>
                                <small class="text-muted" id="single_ac_item_name"></small>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold">Auth Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="single_ac_code" placeholder="Enter authorization code" style="border-radius: 6px;" required>
                        <small class="form-text text-muted">The HMO authorization code for this service request.</small>
                    </div>
                </div>
                <div class="modal-footer" style="border-radius: 0 0 12px 12px;">
                    <button type="button" class="btn btn-secondary" style="border-radius: 6px;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" id="single_ac_submit_btn" style="border-radius: 6px; background: #7c4dff; color: #fff; border: none;">
                        <i class="mdi mdi-key-plus mr-1"></i>Submit Auth Code
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Validate by Group Modal -->
<div class="modal fade" id="validateGroupModal" tabindex="-1" role="dialog" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header text-white" style="border-radius: 12px 12px 0 0; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <div>
                    <h5 class="modal-title mb-0"><i class="mdi mdi-account-check-outline mr-2"></i>Validate Patient Requests — <span id="vg_patient_name"></span></h5>
                    <small id="vg_patient_subtitle" class="text-white-50"></small>
                </div>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                {{-- Summary bar --}}
                <div class="card card-modern mb-3">
                    <div class="card-body py-2 px-3">
                        <div class="d-flex flex-wrap align-items-center justify-content-between">
                            <div>
                                <span class="badge badge-warning mr-1" id="vg_primary_badge">0 Primary</span>
                                <span class="badge badge-danger mr-1" id="vg_secondary_badge">0 Secondary</span>
                                <span class="text-muted small ml-2">Total: <strong id="vg_total_count">0</strong> pending</span>
                            </div>
                            <div class="text-right">
                                <span class="small text-muted">Claims: </span><strong class="text-success" id="vg_total_claims">₦0</strong>
                                <span class="small text-muted ml-2">Payable: </span><strong class="text-info" id="vg_total_payable">₦0</strong>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Grouping toolbar --}}
                <div class="d-flex flex-wrap align-items-center mb-3 gap-2">
                    <span class="small font-weight-bold mr-2">Group By:</span>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-primary active" id="vg_group_encounter">
                            <i class="mdi mdi-stethoscope mr-1"></i>Encounter
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="vg_group_date">
                            <i class="mdi mdi-calendar-range mr-1"></i>Date Range
                        </button>
                    </div>
                    <div id="vg_date_range_controls" class="ml-2" style="display:none;">
                        <select class="form-control form-control-sm" id="vg_date_filter" style="width:160px; border-radius:6px;">
                            <option value="all">All Dates</option>
                            <option value="today">Today</option>
                            <option value="3days">Last 3 Days</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                        </select>
                    </div>
                    <div class="ml-auto">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="vg_select_all_btn">
                            <i class="mdi mdi-checkbox-multiple-marked-outline mr-1"></i>Select All
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="vg_deselect_all_btn">
                            <i class="mdi mdi-checkbox-multiple-blank-outline mr-1"></i>Deselect All
                        </button>
                    </div>
                </div>

                {{-- Loading spinner --}}
                <div id="vg_loading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2 small">Loading patient requests...</p>
                </div>

                {{-- Empty state --}}
                <div id="vg_empty" class="text-center py-5" style="display:none;">
                    <i class="mdi mdi-check-circle-outline text-success" style="font-size:48px;"></i>
                    <p class="text-muted mt-2">No pending requests for this patient.</p>
                </div>

                {{-- Request groups container --}}
                <div id="vg_groups_container"></div>

                {{-- Auth code section --}}
                <div id="vg_auth_section" class="card card-modern mt-3" style="display:none;">
                    <div class="card-header py-2 px-3">
                        <strong class="small"><i class="mdi mdi-key-variant mr-1"></i>Authorization Codes</strong>
                        <span class="badge badge-danger ml-1" id="vg_secondary_selected_count">0</span>
                        <span class="small text-muted"> secondary items selected — auth code required</span>
                    </div>
                    <div class="card-body py-2 px-3">
                        <div class="d-flex flex-wrap align-items-center mb-2" style="gap: 4px 0;">
                            <div class="custom-control custom-radio mr-3">
                                <input type="radio" class="custom-control-input" id="vg_auth_shared" name="vg_auth_mode" value="shared" checked>
                                <label class="custom-control-label small" for="vg_auth_shared">One code for all secondary items</label>
                            </div>
                            <div class="custom-control custom-radio mr-3">
                                <input type="radio" class="custom-control-input" id="vg_auth_individual" name="vg_auth_mode" value="individual">
                                <label class="custom-control-label small" for="vg_auth_individual">Individual codes per item</label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input type="radio" class="custom-control-input" id="vg_auth_skip" name="vg_auth_mode" value="skip">
                                <label class="custom-control-label small" for="vg_auth_skip">Approve without code — enter later <span class="badge ml-1" style="background:#7c4dff;color:#fff;font-size:0.7rem;">Awaiting Code</span></label>
                            </div>
                        </div>
                        <div id="vg_shared_auth_input">
                            <input type="text" class="form-control form-control-sm" id="vg_shared_auth_code" placeholder="Enter shared authorization code" style="border-radius:6px; max-width:400px;">
                        </div>
                    </div>
                </div>

                {{-- Validation notes --}}
                <div class="form-group mt-3 mb-0">
                    <label class="small font-weight-bold">Validation Notes (Optional)</label>
                    <textarea class="form-control form-control-sm" id="vg_validation_notes" rows="2" placeholder="Applied to all approved/rejected items..." style="border-radius:6px;"></textarea>
                </div>

                {{-- Reject inline section (hidden) --}}
                <div id="vg_reject_section" class="card-modern border-danger mt-3" style="display:none;">
                    <div class="card-header py-2 px-3 bg-danger text-white">
                        <strong class="small"><i class="mdi mdi-close-circle mr-1"></i>Reject Selected Items</strong>
                    </div>
                    <div class="card-body py-2 px-3">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold">Rejection Reason <span class="text-danger">*</span></label>
                            <select class="form-control form-control-sm" id="vg_rejection_reason" style="border-radius:6px;">
                                <option value="">Select reason...</option>
                                @foreach($rejectionReasons as $key => $reason)
                                    <option value="{{ $key }}">{{ $reason }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold">Additional Notes</label>
                            <textarea class="form-control form-control-sm" id="vg_reject_notes" rows="2" placeholder="Optional..." style="border-radius:6px;"></textarea>
                        </div>
                        <div class="text-right">
                            <button type="button" class="btn btn-sm btn-outline-secondary mr-1" id="vg_cancel_reject_btn">Cancel</button>
                            <button type="button" class="btn btn-sm btn-danger" id="vg_confirm_reject_btn">
                                <i class="mdi mdi-close-circle mr-1"></i>Confirm Reject (<span class="vg-selected-count">0</span>)
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-radius: 0 0 12px 12px;">
                <div class="d-flex align-items-center mr-auto">
                    <span class="small text-muted">Selected: <strong class="vg-selected-count">0</strong> of <strong id="vg_footer_total">0</strong></span>
                    <span class="small text-muted ml-3">Claims: <strong class="text-success vg-selected-claims">₦0</strong></span>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" style="border-radius:6px;">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="vg_reject_btn" disabled style="border-radius:6px;">
                    <i class="mdi mdi-close-circle mr-1"></i>Reject (<span class="vg-selected-count">0</span>)
                </button>
                <button type="button" class="btn btn-success btn-sm" id="vg_approve_btn" disabled style="border-radius:6px;">
                    <i class="mdi mdi-check-all mr-1"></i>Approve (<span class="vg-selected-count">0</span>)
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Patient History Modal -->
<div class="modal fade" id="patientHistoryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header text-white" style="border-radius: 12px 12px 0 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <h5 class="modal-title"><i class="mdi mdi-history mr-2"></i>Patient HMO History</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stat-card-modern text-white" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <div class="card-body text-center py-3">
                                <h6 class="text-white-50 mb-1">Total HMO Claims</h6>
                                <h3 class="mb-0" id="history_total_claims" style="font-weight: 700;">₦0</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card-modern text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <div class="card-body text-center py-3">
                                <h6 class="text-white-50 mb-1">This Month Claims</h6>
                                <h3 class="mb-0" id="history_month_claims" style="font-weight: 700;">₦0</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card-modern text-white" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                            <div class="card-body text-center py-3">
                                <h6 class="text-white-50 mb-1">Total HMO Visits</h6>
                                <h3 class="mb-0" id="history_total_visits" style="font-weight: 700;">0</h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" id="historyTable" style="border-radius: 8px;">
                        <thead class="bg-light">
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Item</th>
                                <th>Coverage</th>
                                <th>Claims</th>
                                <th>Payable</th>
                                <th>Status</th>
                                <th>Validated By</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody">
                            <tr><td colspan="8" class="text-center text-muted py-4"><i class="mdi mdi-loading mdi-spin mr-2"></i>Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer" style="border-radius: 0 0 12px 12px;">
                <button type="button" class="btn btn-secondary" style="border-radius: 6px;" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
