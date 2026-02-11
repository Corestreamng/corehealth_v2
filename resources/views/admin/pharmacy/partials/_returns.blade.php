{{-- Pharmacy Returns Management Panel --}}
{{-- Integrates into pharmacy workbench as a queue-view panel --}}

<div class="queue-view" id="pharmacy-returns-view">
    <div class="queue-view-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <h4><i class="mdi mdi-undo-variant"></i> Process Returns</h4>
        <div class="reports-header-actions">
            @hasanyrole('SUPERADMIN|ADMIN|PHARMACIST|STORE_MANAGER')
            <button class="btn btn-sm btn-outline-light" id="btn-create-return">
                <i class="mdi mdi-plus"></i> New Return
            </button>
            @endhasanyrole
            <button class="btn btn-secondary btn-close-queue" id="btn-close-returns">
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
                        <h4 id="returns-stat-pending" class="stat-skeleton">—</h4>
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
                        <h4 id="returns-stat-approved" class="stat-skeleton">—</h4>
                        <small>Approved</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card-mini" style="border-left: 4px solid #dc3545;">
                    <div class="stat-icon-mini" style="background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);">
                        <i class="mdi mdi-close-circle"></i>
                    </div>
                    <div class="stat-content-mini">
                        <h4 id="returns-stat-rejected" class="stat-skeleton">—</h4>
                        <small>Rejected</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card-mini" style="border-left: 4px solid #17a2b8;">
                    <div class="stat-icon-mini" style="background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);">
                        <i class="mdi mdi-cash-refund"></i>
                    </div>
                    <div class="stat-content-mini">
                        <h4 id="returns-stat-refunded" class="stat-skeleton">₦—</h4>
                        <small>Total Refunded</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="date-presets-bar mb-3">
            <span class="text-muted me-2"><i class="mdi mdi-filter-variant"></i> Filters:</span>
            <select class="form-control form-control-sm" id="returns-status-filter" style="width: auto; display: inline-block;">
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="completed">Completed</option>
            </select>
            <input type="date" class="form-control form-control-sm" id="returns-date-from"
                   value="{{ date('Y-m-d', strtotime('-30 days')) }}" style="width: auto; display: inline-block;">
            <span class="text-muted">to</span>
            <input type="date" class="form-control form-control-sm" id="returns-date-to"
                   value="{{ date('Y-m-d') }}" style="width: auto; display: inline-block;">
            <button class="btn btn-sm btn-primary" id="apply-returns-filters">
                <i class="mdi mdi-filter"></i> Apply
            </button>
        </div>

        {{-- Returns DataTable --}}
        <div class="card-modern">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped mb-0" id="returnsTable" style="width: 100%">
                        <thead>
                            <tr>
                                <th width="30">#</th>
                                <th>Item</th>
                                <th>Details</th>
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

{{-- Create Return Slide Panel --}}
<div class="queue-view" id="pharmacy-return-create-view">
    <div class="queue-view-header" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
        <h4><i class="mdi mdi-plus-circle"></i> New Return</h4>
        <button class="btn btn-secondary btn-close-queue" id="btn-cancel-create-return">
            <i class="mdi mdi-arrow-left"></i> Back to Returns
        </button>
    </div>
    <div class="queue-view-content" style="padding: 1.5rem; overflow-y: auto; max-height: calc(100vh - 180px);">
        <form id="createReturnForm">
            {{-- Step 1: Search Dispensed Item --}}
            <div class="card-modern mb-3">
                <div class="card-header py-2">
                    <h6 class="mb-0"><span class="badge badge-primary mr-1">1</span> Search Dispensed Item</h6>
                </div>
                <div class="card-body">
                    <input type="text" class="form-control" id="dispensedItemSearch"
                           placeholder="🔍 Search by patient name, file no, or product name...">
                    <small class="form-text text-muted">Type at least 2 characters to search</small>
                    <div id="dispensedItemResults" class="mt-2" style="max-height: 300px; overflow-y: auto;"></div>
                </div>
            </div>

            {{-- Step 2: Return Details (hidden until item selected) --}}
            <div id="returnDetailsSection" style="display: none;">
                <div class="card-modern mb-3">
                    <div class="card-header py-2" style="background: linear-gradient(135deg, #e7f1ff 0%, #f0f4ff 100%);">
                        <h6 class="mb-0"><i class="mdi mdi-information"></i> Selected Item</h6>
                    </div>
                    <div class="card-body">
                        <div id="selectedReturnItemInfo"></div>
                    </div>
                </div>

                <input type="hidden" id="return_product_request_id" name="product_request_id">

                <div class="card-modern mb-3">
                    <div class="card-header py-2">
                        <h6 class="mb-0"><span class="badge badge-primary mr-1">2</span> Return Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Quantity to Return <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="return_qty_returned" name="qty_returned"
                                       step="0.01" min="0.01" required>
                                <small class="form-text text-muted">Max: <span id="return_max_qty" class="fw-bold text-primary">-</span></small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Return Condition <span class="text-danger">*</span></label>
                                <select class="form-control" id="return_condition" name="return_condition" required>
                                    <option value="">Select Condition</option>
                                    <option value="good">✅ Good — can be restocked</option>
                                    <option value="expired">⏰ Expired — cannot restock</option>
                                    <option value="damaged">💔 Damaged — cannot restock</option>
                                    <option value="wrong_item">❌ Wrong Item — can be restocked</option>
                                </select>
                                <small class="form-text text-muted" id="return_condition_hint"></small>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label small fw-bold">Return Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="return_reason" name="return_reason"
                                      rows="3" required minlength="10" placeholder="Describe the reason for this return (min 10 characters)..."></textarea>
                        </div>

                        {{-- JE Preview --}}
                        <div class="mt-3 p-2 rounded" id="return-je-preview" style="background: #f8f9fa; display: none;">
                            <h6 class="mb-2"><i class="mdi mdi-book-open-page-variant"></i> Journal Entry Preview</h6>
                            <small class="text-muted">This entry will be created upon approval:</small>
                            <table class="table table-sm table-bordered mt-1 mb-0" style="font-size: 0.8rem;">
                                <thead><tr><th>Account</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr></thead>
                                <tbody id="return-je-preview-body"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill" id="submitReturnBtn">
                        <i class="mdi mdi-check"></i> Submit Return for Approval
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="resetReturnForm">
                        <i class="mdi mdi-refresh"></i> Reset
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- View Return Detail Modal --}}
<div class="modal fade" id="viewReturnModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title"><i class="mdi mdi-eye"></i> Return Details</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="viewReturnModalBody">
                <div class="text-center p-4"><i class="mdi mdi-loading mdi-spin mdi-36px"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Approve Return Modal --}}
<div class="modal fade" id="approveReturnModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
                <h5 class="modal-title"><i class="mdi mdi-check-circle"></i> Approve Return</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="approveReturnForm">
                <input type="hidden" id="approve_return_id">
                <div class="modal-body">
                    <div id="approve-return-summary" class="mb-3"></div>
                    <div class="alert alert-info mb-3">
                        <i class="mdi mdi-information"></i> Approving this return will:
                        <ul class="mb-0 mt-1">
                            <li>Create a reversal journal entry</li>
                            <li id="approve-return-restock-note">Restock inventory if in good condition</li>
                            <li>Mark the return as approved</li>
                        </ul>
                    </div>
                    <div class="form-group">
                        <label class="form-label small fw-bold">Approval Notes <small class="text-muted">(optional)</small></label>
                        <textarea class="form-control" id="approve_return_notes" name="approval_notes" rows="2" placeholder="Optional notes..."></textarea>
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

{{-- Reject Return Modal --}}
<div class="modal fade" id="rejectReturnModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%); color: white;">
                <h5 class="modal-title"><i class="mdi mdi-close-circle"></i> Reject Return</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="rejectReturnForm">
                <input type="hidden" id="reject_return_id">
                <div class="modal-body">
                    <p class="text-muted">The dispensed item status will be reverted and the patient can re-collect.</p>
                    <div class="form-group">
                        <label class="form-label small fw-bold">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reject_return_reason" name="rejection_reason" rows="3" required minlength="10"
                                  placeholder="Explain why this return is being rejected (min 10 characters)..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="mdi mdi-close"></i> Reject Return</button>
                </div>
            </form>
        </div>
    </div>
</div>
