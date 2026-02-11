{{-- Pharmacy Damages Management Panel --}}
{{-- Integrates into pharmacy workbench as a queue-view panel --}}

<div class="queue-view" id="pharmacy-damages-view">
    <div class="queue-view-header" style="background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);">
        <h4><i class="mdi mdi-alert-octagon"></i> Damage Reports</h4>
        <div class="reports-header-actions">
            @hasanyrole('SUPERADMIN|ADMIN|PHARMACIST|STORE_MANAGER')
            <button class="btn btn-sm btn-outline-light" id="btn-create-damage">
                <i class="mdi mdi-plus"></i> Report Damage
            </button>
            @endhasanyrole
            <button class="btn btn-secondary btn-close-queue" id="btn-close-damages">
                <i class="mdi mdi-close"></i> Close
            </button>
        </div>
    </div>
    <div class="queue-view-content" style="padding: 1.5rem; overflow-y: auto; max-height: calc(100vh - 180px);">

        {{-- Summary Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-card-mini" style="border-left: 4px solid #ffc107;">
                    <div class="stat-icon-mini" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                        <i class="mdi mdi-clock-outline"></i>
                    </div>
                    <div class="stat-content-mini">
                        <h4 id="damages-stat-pending" class="stat-skeleton">—</h4>
                        <small>Pending</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card-mini" style="border-left: 4px solid #28a745;">
                    <div class="stat-icon-mini" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                        <i class="mdi mdi-check-circle"></i>
                    </div>
                    <div class="stat-content-mini">
                        <h4 id="damages-stat-approved" class="stat-skeleton">—</h4>
                        <small>Approved</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card-mini" style="border-left: 4px solid #dc3545;">
                    <div class="stat-icon-mini" style="background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);">
                        <i class="mdi mdi-currency-usd-off"></i>
                    </div>
                    <div class="stat-content-mini">
                        <h4 id="damages-stat-value" class="stat-skeleton">₦—</h4>
                        <small>Total Loss</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card-mini" style="border-left: 4px solid #6f42c1;">
                    <div class="stat-icon-mini" style="background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);">
                        <i class="mdi mdi-package-variant-closed"></i>
                    </div>
                    <div class="stat-content-mini">
                        <h4 id="damages-stat-deducted" class="stat-skeleton">—</h4>
                        <small>Stock Deducted</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="date-presets-bar mb-3">
            <span class="text-muted me-2"><i class="mdi mdi-filter-variant"></i> Filters:</span>
            <select class="form-control form-control-sm" id="damages-status-filter" style="width: auto; display: inline-block;">
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
            <select class="form-control form-control-sm" id="damages-type-filter" style="width: auto; display: inline-block;">
                <option value="">All Types</option>
                <option value="expired">Expired</option>
                <option value="broken">Broken</option>
                <option value="contaminated">Contaminated</option>
                <option value="spoiled">Spoiled</option>
                <option value="theft">Theft</option>
                <option value="other">Other</option>
            </select>
            <input type="date" class="form-control form-control-sm" id="damages-date-from"
                   value="{{ date('Y-m-d', strtotime('-30 days')) }}" style="width: auto; display: inline-block;">
            <span class="text-muted">to</span>
            <input type="date" class="form-control form-control-sm" id="damages-date-to"
                   value="{{ date('Y-m-d') }}" style="width: auto; display: inline-block;">
            <button class="btn btn-sm btn-primary" id="apply-damages-filters">
                <i class="mdi mdi-filter"></i> Apply
            </button>
        </div>

        {{-- Damages DataTable --}}
        <div class="card-modern">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped mb-0" id="damagesTable" style="width: 100%">
                        <thead>
                            <tr>
                                <th width="30">#</th>
                                <th>Item</th>
                                <th>Details</th>
                                <th>Value</th>
                                <th>Status</th>
                                <th width="90">Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Create Damage Slide Panel --}}
<div class="queue-view" id="pharmacy-damage-create-view">
    <div class="queue-view-header" style="background: linear-gradient(135deg, #dc3545 0%, #c0392b 100%);">
        <h4><i class="mdi mdi-alert-plus"></i> Report New Damage</h4>
        <button class="btn btn-secondary btn-close-queue" id="btn-cancel-create-damage">
            <i class="mdi mdi-arrow-left"></i> Back to Damages
        </button>
    </div>
    <div class="queue-view-content" style="padding: 1.5rem; overflow-y: auto; max-height: calc(100vh - 180px);">
        <form id="createDamageForm">
            {{-- Store & Product Selection --}}
            <div class="card-modern mb-3">
                <div class="card-header py-2">
                    <h6 class="mb-0"><span class="badge badge-danger mr-1">1</span> Select Product</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Store <span class="text-danger">*</span></label>
                            <select class="form-control select2-damage-store" id="damage_store_id" name="store_id" required
                                    style="width: 100%;">
                                <option value="">Select Store</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Product <span class="text-danger">*</span></label>
                            <select class="form-control select2-damage-product" id="damage_product_id" name="product_id" required
                                    disabled style="width: 100%;">
                                <option value="">Select store first</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Batch <small class="text-muted">(optional — for batch-level tracking)</small></label>
                            <select class="form-control select2-damage-batch" id="damage_batch_id" name="batch_id" disabled
                                    style="width: 100%;">
                                <option value="">Select product first</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Available Stock</label>
                            <input type="text" class="form-control" id="damage_available_stock" readonly value="-" style="background: #f8f9fa;">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Damage Details --}}
            <div class="card-modern mb-3">
                <div class="card-header py-2">
                    <h6 class="mb-0"><span class="badge badge-danger mr-1">2</span> Damage Details</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Damage Type <span class="text-danger">*</span></label>
                            <select class="form-control" id="damage_type" name="damage_type" required>
                                <option value="">Select Type</option>
                                <option value="expired">⏰ Expired</option>
                                <option value="broken">💔 Broken</option>
                                <option value="contaminated">☣️ Contaminated</option>
                                <option value="spoiled">🤢 Spoiled</option>
                                <option value="theft">🔒 Theft/Shrinkage</option>
                                <option value="other">📝 Other</option>
                            </select>
                            <small class="form-text text-muted" id="damage-type-hint"></small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Qty Damaged <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="damage_qty" name="qty_damaged"
                                   step="0.01" min="0.01" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Unit Cost</label>
                            <input type="number" class="form-control" id="damage_unit_cost" name="unit_cost"
                                   step="0.01" min="0" readonly style="background: #f8f9fa;">
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Total Value</label>
                            <input type="text" class="form-control fw-bold text-danger" id="damage_total_display" readonly
                                   value="₦0.00" style="background: #fff5f5; font-size: 1.1rem;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Discovered Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="damage_discovered_date" name="discovered_date"
                                   value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label small fw-bold">Damage Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="damage_reason" name="damage_reason"
                                  rows="3" required minlength="10" placeholder="Describe the damage in detail (min 10 characters)..."></textarea>
                    </div>
                    <div class="mt-3">
                        <label class="form-label small fw-bold">Additional Notes <small class="text-muted">(optional)</small></label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="Any additional observations..."></textarea>
                    </div>

                    {{-- JE Preview --}}
                    <div class="mt-3 p-2 rounded" id="damage-je-preview" style="background: #f8f9fa; display: none;">
                        <h6 class="mb-2"><i class="mdi mdi-book-open-page-variant"></i> Journal Entry Preview</h6>
                        <small class="text-muted">This entry will be created when the report is approved:</small>
                        <table class="table table-sm table-bordered mt-1 mb-0" style="font-size: 0.8rem;">
                            <thead><tr><th>Account</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr></thead>
                            <tbody id="damage-je-preview-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-danger flex-fill" id="submitDamageBtn">
                    <i class="mdi mdi-alert-octagon"></i> Submit Damage Report
                </button>
                <button type="button" class="btn btn-outline-secondary" id="resetDamageForm">
                    <i class="mdi mdi-refresh"></i> Reset
                </button>
            </div>
        </form>
    </div>
</div>

{{-- View Damage Detail Modal --}}
<div class="modal fade" id="viewDamageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%); color: white;">
                <h5 class="modal-title"><i class="mdi mdi-eye"></i> Damage Report Details</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="viewDamageModalBody">
                <div class="text-center p-4"><i class="mdi mdi-loading mdi-spin mdi-36px"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Approve Damage Modal --}}
<div class="modal fade" id="approveDamageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
                <h5 class="modal-title"><i class="mdi mdi-check-circle"></i> Approve Damage Report</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="approveDamageForm">
                <input type="hidden" id="approve_damage_id">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong><i class="mdi mdi-alert"></i> This action cannot be undone.</strong>
                        <ul class="mb-0 mt-1">
                            <li>A write-off journal entry will be created</li>
                            <li>Damaged quantity will be deducted from inventory</li>
                            <li>Both global stock and store-level stock will be reduced</li>
                        </ul>
                    </div>
                    <div class="form-group">
                        <label class="form-label small fw-bold">Approval Notes <small class="text-muted">(optional)</small></label>
                        <textarea class="form-control" id="approve_damage_notes" name="approval_notes" rows="2" placeholder="Optional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="mdi mdi-check"></i> Confirm Approval</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Reject Damage Modal --}}
<div class="modal fade" id="rejectDamageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%); color: white;">
                <h5 class="modal-title"><i class="mdi mdi-close-circle"></i> Reject Damage Report</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="rejectDamageForm">
                <input type="hidden" id="reject_damage_id">
                <div class="modal-body">
                    <p class="text-muted">No stock will be deducted and no journal entry will be created.</p>
                    <div class="form-group">
                        <label class="form-label small fw-bold">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reject_damage_reason" name="rejection_reason" rows="3" required minlength="10"
                                  placeholder="Explain why this damage report is being rejected (min 10 characters)..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="mdi mdi-close"></i> Reject Report</button>
                </div>
            </form>
        </div>
    </div>
</div>
