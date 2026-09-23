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
        {{-- Pending Returns Bulk Action Bar --}}
        <div class="alert alert-warning justify-content-between align-items-center mb-3 py-2" id="pending-returns-bulk-bar" style="display: none;">
            <div class="d-flex align-items-center">
                <i class="mdi mdi-checkbox-multiple-marked-circle text-warning fs-4 me-2"></i>
                <div>
                    <strong id="pending-returns-selected-count">0 pending returns selected</strong>
                    <div class="small text-muted">Perform bulk action on selected pending returns</div>
                </div>
            </div>
            <div class="d-flex gap-2">
                @hasanyrole('SUPERADMIN|ADMIN|PHARMACIST|STORE_MANAGER')
                <button type="button" class="btn btn-sm btn-success" id="btn-bulk-approve-returns">
                    <i class="mdi mdi-check-all"></i> Approve Selected
                </button>
                <button type="button" class="btn btn-sm btn-danger" id="btn-bulk-reject-returns">
                    <i class="mdi mdi-close-octagon"></i> Reject Selected
                </button>
                @endhasanyrole
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-clear-pending-selection">
                    <i class="mdi mdi-close"></i> Clear
                </button>
            </div>
        </div>

        <div class="card-modern">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped mb-0" id="returnsTable" style="width: 100%">
                        <thead>
                            <tr>
                                <th width="38" class="text-center"><input type="checkbox" class="form-check-input position-static m-0" id="select-all-pending-returns" title="Select All Pending"></th>
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
            {{-- Stepper Progress Bar --}}
            <div class="return-stepper mb-4">
                <div class="return-step-item active" id="step-nav-1" data-step="1">
                    <div class="step-badge">
                        <span class="step-index">1</span>
                        <i class="mdi mdi-check step-check"></i>
                    </div>
                    <div class="step-info">
                        <span class="step-title">1. Select Dispensed Items</span>
                        <small class="step-desc">Filter by date or patient & mark items</small>
                    </div>
                    <span class="badge rounded-pill bg-primary ms-auto d-none" id="step1-selected-pill">0 selected</span>
                </div>
                <div class="step-divider"></div>
                <div class="return-step-item disabled" id="step-nav-2" data-step="2">
                    <div class="step-badge">
                        <span class="step-index">2</span>
                        <i class="mdi mdi-check step-check"></i>
                    </div>
                    <div class="step-info">
                        <span class="step-title">2. Configure & Review</span>
                        <small class="step-desc">Customize quantities, conditions & refund splits</small>
                    </div>
                </div>
            </div>

            {{-- STEP 1 PANE: Search & Select Dispensed Items --}}
            <div id="return-step-1-pane" class="return-step-pane">
                <div class="card-modern mb-3">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><span class="badge badge-primary mr-1">1</span> Search Dispensed Items</h6>
                        <div class="btn-group btn-group-sm" role="group" id="dispensed-date-mode-group">
                            <button type="button" class="btn btn-outline-primary active" data-mode="range" id="btn-mode-date-range">Date Range</button>
                            <button type="button" class="btn btn-outline-primary" data-mode="single" id="btn-mode-single-date">Specific Date</button>
                        </div>
                    </div>
                    <div class="card-body">
                        {{-- Filter Controls Row --}}
                        <div class="row g-2 mb-3 align-items-end">
                            {{-- Specific Single Date Filter (hidden in date range mode) --}}
                            <div class="col-md-3" id="col-single-date" style="display: none;">
                                <label class="form-label small fw-bold mb-1">Dispense Date</label>
                                <input type="date" class="form-control form-control-sm" id="dispensed-search-date" value="{{ date('Y-m-d') }}">
                            </div>

                            {{-- Date Range Filters (default active mode) --}}
                            <div class="col-md-3 col-date-range">
                                <label class="form-label small fw-bold mb-1">From Date</label>
                                <input type="date" class="form-control form-control-sm" id="dispensed-search-start" value="{{ date('Y-m-d', strtotime('-30 days')) }}">
                            </div>
                            <div class="col-md-3 col-date-range">
                                <label class="form-label small fw-bold mb-1">To Date</label>
                                <input type="date" class="form-control form-control-sm" id="dispensed-search-end" value="{{ date('Y-m-d') }}">
                            </div>

                            {{-- Patient Filter (Surname or File No) --}}
                            <div class="col-md-4">
                                <label class="form-label small fw-bold mb-1">Patient (Surname or File No)</label>
                                <input type="text" class="form-control form-control-sm" id="dispensed-search-patient" placeholder="Surname or File No...">
                            </div>

                            {{-- Quick Presets & Search Buttons --}}
                            <div class="col-md-5 d-flex gap-2">
                                <div class="btn-group btn-group-sm me-auto">
                                    <button type="button" class="btn btn-outline-secondary btn-date-preset" data-preset="today">Today</button>
                                    <button type="button" class="btn btn-outline-secondary btn-date-preset" data-preset="yesterday">Yesterday</button>
                                    <button type="button" class="btn btn-outline-secondary btn-date-preset" data-preset="7days">7 Days</button>
                                </div>
                                <button type="button" class="btn btn-sm btn-primary" id="btn-search-dispensed">
                                    <i class="mdi mdi-magnify"></i> Search
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-reset-dispensed-filter" title="Reset Filters">
                                    <i class="mdi mdi-refresh"></i>
                                </button>
                            </div>
                        </div>

                        {{-- Dispensed Items Table --}}
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-striped w-100" id="dt-dispensed-items">
                                <thead>
                                    <tr>
                                        <th width="38" class="text-center"><input type="checkbox" class="form-check-input position-static m-0" id="select-all-dispensed-items" title="Select all on this page"></th>
                                        <th>Patient</th>
                                        <th>Product & Batch</th>
                                        <th>Audit Trail (Billed & Dispensed)</th>
                                        <th width="150" class="text-end">Qty, Amount & Action</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>

                        {{-- Sticky/Floating Selection Basket Bar --}}
                        <div class="alert alert-primary justify-content-between align-items-center mt-3 mb-0" id="dispensed-selection-bar" style="display: none;">
                            <div class="d-flex align-items-center">
                                <i class="mdi mdi-cart-arrow-down fs-4 me-2 text-primary"></i>
                                <div>
                                    <strong id="dispensed-selected-count">0 items selected</strong>
                                    <span class="text-muted ms-2">(Total Value: <span id="dispensed-selected-total" class="fw-bold text-success">₦0.00</span>)</span>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-clear-dispensed-selection">
                                    <i class="mdi mdi-close"></i> Clear Selection
                                </button>
                                <button type="button" class="btn btn-sm btn-primary" id="btn-proceed-multi-return">
                                    Next: Configure Returns (<span id="dispensed-btn-count">0</span>) <i class="mdi mdi-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- STEP 2 PANE: Multi-Item Return Configuration & Review --}}
            <div id="return-step-2-pane" class="return-step-pane" style="display: none;">
                <div class="card-modern mb-3">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #e7f1ff 0%, #f0f4ff 100%);">
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary btn-back-to-step1" title="Back to Item Selection">
                                <i class="mdi mdi-arrow-left"></i> Back to Items
                            </button>
                            <h6 class="mb-0 text-primary fw-bold">
                                <span class="badge badge-primary mr-1">2</span> Configure Return Items 
                                (<span id="multi-return-item-count">0</span> selected)
                            </h6>
                        </div>
                        <small class="text-muted">Customize quantity, condition, and reason for each item below</small>
                    </div>
                    <div class="card-body">
                        {{-- Batch Shortcuts Toolbar --}}
                        <div class="p-3 mb-3 rounded" style="background: #f8fafc; border: 1px dashed #cbd5e1;">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold mb-1"><i class="mdi mdi-auto-fix"></i> Batch Condition</label>
                                    <div class="input-group input-group-sm">
                                        <select class="form-control" id="batch_return_condition">
                                            <option value="good">Good (Restockable)</option>
                                            <option value="wrong_item">Wrong Item (Restockable)</option>
                                            <option value="damaged">Damaged (Loss)</option>
                                            <option value="expired">Expired (Loss)</option>
                                        </select>
                                        <button type="button" class="btn btn-outline-primary" id="btn-apply-batch-condition">
                                            Apply All
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label small fw-bold mb-0"><i class="mdi mdi-comment-text-multiple-outline"></i> Batch Common Reason</label>
                                        <span class="small text-muted" style="font-size: 0.72rem;">Quick select to fill:</span>
                                    </div>
                                    <div class="input-group input-group-sm mb-1">
                                        <input type="text" class="form-control" id="batch_return_reason" placeholder="Common reason for this batch return (e.g. mm6, patient discharged)...">
                                        <button type="button" class="btn btn-outline-primary" id="btn-apply-batch-reason">
                                            Apply All
                                        </button>
                                    </div>
                                    <div class="quick-reasons-bar d-flex flex-wrap gap-1 align-items-center mt-1">
                                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 btn-quick-reason" data-reason="Patient Discharged">Patient Discharged</button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 btn-quick-reason" data-reason="Medication Changed by Doctor">Medication Changed</button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 btn-quick-reason" data-reason="Patient Refused Medication">Patient Refused</button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 btn-quick-reason" data-reason="Adverse Drug Reaction">Adverse Reaction</button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 btn-quick-reason" data-reason="Wrong Medication Dispensed">Wrong Medication</button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 btn-quick-reason" data-reason="Damaged / Seal Broken">Damaged</button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 btn-quick-reason" data-reason="Expired Medication">Expired</button>
                                    </div>
                                </div>
                                <div class="col-md-2 text-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger w-100" id="btn-remove-all-return-items">
                                        <i class="mdi mdi-delete-sweep"></i> Remove All
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Multi-Item Items Table --}}
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered align-middle mb-0" id="multi-return-items-table">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width: 220px;">Item / Patient</th>
                                        <th style="width: 130px;">Return Qty</th>
                                        <th style="width: 180px;">Condition</th>
                                        <th style="min-width: 200px;">Reason <span class="text-danger">*</span></th>
                                        <th style="width: 140px;" class="text-end">Refund Amount</th>
                                        <th style="width: 40px;" class="text-center"><i class="mdi mdi-cog"></i></th>
                                    </tr>
                                </thead>
                                <tbody id="multi-return-items-tbody">
                                    {{-- Dynamically populated by JS --}}
                                </tbody>
                            </table>
                        </div>

                        {{-- Summary Cards Row --}}
                        <div class="row g-3 mb-3">
                            <div class="col-md-3 col-6">
                                <div class="p-2 rounded text-center" style="background: #eef2ff; border: 1px solid #c7d2fe;">
                                    <small class="text-muted d-block">Items to Return</small>
                                    <h5 class="mb-0 text-primary" id="multi-summary-items-count">0</h5>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="p-2 rounded text-center" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                                    <small class="text-muted d-block">Total Refund</small>
                                    <h5 class="mb-0 text-success" id="multi-summary-total-refund">₦0.00</h5>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="p-2 rounded text-center" style="background: #fefce8; border: 1px solid #fef08a;">
                                    <small class="text-muted d-block">Patient Wallet Credit</small>
                                    <h5 class="mb-0 text-warning" id="multi-summary-patient-refund">₦0.00</h5>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="p-2 rounded text-center" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                                    <small class="text-muted d-block">HMO Claims Reversal</small>
                                    <h5 class="mb-0 text-secondary" id="multi-summary-hmo-refund">₦0.00</h5>
                                </div>
                            </div>
                        </div>

                        {{-- Multi-Return Journal Entry Preview --}}
                        <div class="p-3 rounded mb-3" id="return-je-preview" style="background: #f8f9fa; border: 1px solid #e9ecef; display: none;">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <h6 class="mb-0"><i class="mdi mdi-book-open-page-variant text-primary"></i> Aggregated Journal Entry Preview</h6>
                                <small class="text-muted">Estimated entries created upon approval</small>
                            </div>
                            <table class="table table-sm table-bordered mt-2 mb-0" style="font-size: 0.85rem;">
                                <thead>
                                    <tr class="table-light">
                                        <th>Account</th>
                                        <th class="text-end" style="width: 140px;">Debit (DR)</th>
                                        <th class="text-end" style="width: 140px;">Credit (CR)</th>
                                    </tr>
                                </thead>
                                <tbody id="return-je-preview-body"></tbody>
                            </table>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-back-to-step1">
                                <i class="mdi mdi-arrow-left"></i> Back to Items
                            </button>
                            <button type="submit" class="btn btn-primary flex-fill" id="submitReturnBtn">
                                <i class="mdi mdi-check-circle"></i> Submit All Returns for Approval (<span id="submit-btn-count">0</span>)
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="resetReturnForm">
                                <i class="mdi mdi-refresh"></i> Clear All
                            </button>
                        </div>
                    </div>
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
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
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
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
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
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
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

{{-- Bulk Approve Return Modal --}}
<div class="modal fade" id="bulkApproveReturnModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
                <h5 class="modal-title"><i class="mdi mdi-check-all"></i> Bulk Approve Returns</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <form id="bulkApproveReturnForm">
                <div class="modal-body">
                    <div class="alert alert-success mb-3">
                        <i class="mdi mdi-information"></i> You are approving <strong id="bulk-approve-count-label">0</strong> returns.
                        <ul class="mb-0 mt-2">
                            <li>Reversal journal entries will be posted for all returns</li>
                            <li>Good condition items will be restocked to inventory batches</li>
                            <li>Patient wallets will be credited with their respective refund portions</li>
                        </ul>
                    </div>
                    <div class="form-group mb-2">
                        <label class="form-label small fw-bold">Approval Notes <small class="text-muted">(optional)</small></label>
                        <textarea class="form-control" id="bulk_approve_return_notes" name="approval_notes" rows="2" placeholder="Optional notes for this batch..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="btn-confirm-bulk-approve">
                        <i class="mdi mdi-check"></i> Confirm Bulk Approval
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Bulk Reject Return Modal --}}
<div class="modal fade" id="bulkRejectReturnModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%); color: white;">
                <h5 class="modal-title"><i class="mdi mdi-close-octagon"></i> Bulk Reject Returns</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <form id="bulkRejectReturnForm">
                <div class="modal-body">
                    <div class="alert alert-danger mb-3">
                        <i class="mdi mdi-alert-circle"></i> You are rejecting <strong id="bulk-reject-count-label">0</strong> returns.
                        <div class="small mt-1">Dispensed item statuses will be reverted back to 'Dispensed'.</div>
                    </div>
                    <div class="form-group mb-2">
                        <label class="form-label small fw-bold">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="bulk_reject_return_reason" name="rejection_reason" rows="3" required minlength="10" placeholder="Reason for rejecting these returns (min 10 characters)..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="btn-confirm-bulk-reject">
                        <i class="mdi mdi-close"></i> Confirm Bulk Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
