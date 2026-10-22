<!-- Shared My Transactions & Manage Transactions Modal -->
<div class="modal fade" id="myTransactionsModal" role="dialog" aria-labelledby="myTransactionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header py-2" style="background: var(--hospital-primary, #011b33); color: white;">
                <h5 class="modal-title mb-0" id="myTransactionsModalLabel">
                    <i class="mdi mdi-receipt"></i> Transactions Console
                </h5>
                <button type="button" class="close btn-close text-white btn-close-white" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close" style="opacity: 0.9; font-size: 1.5rem; line-height: 1;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body p-3">
                <!-- Navigation Tabs Bar inside Body -->
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                    <ul class="nav nav-pills" id="trans-console-tabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active py-1 px-3 font-13" id="tab-my-transactions" data-toggle="pill" data-bs-toggle="pill" data-target="#pane-my-transactions" data-bs-target="#pane-my-transactions" type="button" role="tab">
                                <i class="mdi mdi-account-cash"></i> My Transactions
                            </button>
                        </li>
                        <li class="nav-item ms-2 ml-2">
                            <button class="nav-link py-1 px-3 font-13" id="tab-manage-transactions" data-toggle="pill" data-bs-toggle="pill" data-target="#pane-manage-transactions" data-bs-target="#pane-manage-transactions" type="button" role="tab">
                                <i class="mdi mdi-tune-vertical"></i> Manage Transactions
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="tab-content" id="trans-console-tab-content">
                    <!-- ========================================== -->
                    <!-- TAB 1: MY TRANSACTIONS & ITEM BREAKDOWN   -->
                    <!-- ========================================== -->
                    <div class="tab-pane fade show active" id="pane-my-transactions" role="tabpanel">
                        <!-- Filter Bar -->
                        <div class="card bg-light border mb-3">
                            <div class="card-body p-2">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-2 col-sm-6">
                                        <label class="small text-muted mb-0">From</label>
                                        <input type="date" class="form-control form-control-sm" id="my-trans-from-date">
                                    </div>
                                    <div class="col-md-2 col-sm-6">
                                        <label class="small text-muted mb-0">To</label>
                                        <input type="date" class="form-control form-control-sm" id="my-trans-to-date">
                                    </div>
                                    <div class="col-md-2 col-sm-6">
                                        <label class="small text-muted mb-0">Method</label>
                                        <select class="form-select form-select-sm" id="my-trans-payment-type">
                                            <option value="">All Methods</option>
                                            <option value="CASH">Cash</option>
                                            <option value="POS">POS / Card</option>
                                            <option value="TRANSFER">Bank Transfer</option>
                                            <option value="MOBILE">Mobile Money</option>
                                            <option value="SPLIT">Split Payment</option>
                                            <option value="ACC_DEPOSIT">Deposit</option>
                                            <option value="ACC_WITHDRAW">Withdrawal</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 col-sm-6">
                                        <label class="small text-muted mb-0">Item Scope</label>
                                        <select class="form-select form-select-sm" id="my-trans-item-type">
                                            <option value="">All Items</option>
                                            <option value="product">Products Only</option>
                                            <option value="service">Services Only</option>
                                            <option value="custom">Custom / Free-form</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 col-sm-6">
                                        <label class="small text-muted mb-0">Category</label>
                                        <select class="form-select form-select-sm" id="my-trans-category-filter">
                                            <option value="">All Categories</option>
                                            <optgroup label="Clinical &amp; Services" id="optgroup-services">
                                                @foreach(\App\Models\ServiceCategory::where('status', 1)->orderBy('category_name')->get() as $scat)
                                                    <option value="{{ $scat->category_name }}" data-type="service">{{ $scat->category_name }}</option>
                                                @endforeach
                                                <option value="Custom Items" data-type="custom">Custom Items</option>
                                            </optgroup>
                                            <optgroup label="Pharmacy &amp; Products" id="optgroup-products">
                                                @foreach(\App\Models\ProductCategory::where('status', 1)->orderBy('category_name')->get() as $pcat)
                                                    <option value="{{ $pcat->category_name }}" data-type="product">{{ $pcat->category_name }}</option>
                                                @endforeach
                                            </optgroup>
                                        </select>
                                    </div>
                                    <div class="col-md-2 col-sm-6 text-end">
                                        <div class="btn-group w-100">
                                            <button class="btn btn-sm btn-primary" id="btn-load-my-transactions">
                                                <i class="mdi mdi-filter"></i> Load
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary" id="btn-print-my-transactions" title="Print Summary">
                                                <i class="mdi mdi-printer"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Method & Financial Volume KPIs with Bank Breakdowns -->
                        <div class="row g-2 mb-2" id="my-trans-kpi-row">
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="p-2 border rounded bg-white shadow-sm text-center h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="text-muted small">Total Volume</div>
                                        <div class="fw-bold font-15 text-primary" id="kpi-my-total">₦0.00</div>
                                        <div class="text-muted font-10"><span id="kpi-my-count">0</span> transactions</div>
                                    </div>
                                    <div class="border-top pt-1 mt-1 font-10 text-muted fst-italic">Net Inflow</div>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="p-2 border rounded bg-white shadow-sm text-center h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="text-muted small"><i class="mdi mdi-cash text-success"></i> Cash</div>
                                        <div class="fw-bold font-15 text-success" id="kpi-my-cash">₦0.00</div>
                                        <div class="text-muted font-10"><span id="kpi-my-cash-count">0</span> tenders</div>
                                    </div>
                                    <div class="border-top pt-1 mt-1 font-10 text-muted fst-italic">Cash in Drawer</div>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="p-2 border rounded bg-white shadow-sm text-center h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="text-muted small"><i class="mdi mdi-credit-card-outline text-info"></i> POS</div>
                                        <div class="fw-bold font-15 text-info" id="kpi-my-pos">₦0.00</div>
                                        <div class="text-muted font-10"><span id="kpi-my-pos-count">0</span> tenders</div>
                                    </div>
                                    <div class="border-top pt-1 mt-1 font-10 text-muted text-start" id="kpi-my-pos-banks" style="max-height: 48px; overflow-y: auto;">
                                        <div class="text-muted text-center font-9">No POS tenders</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="p-2 border rounded bg-white shadow-sm text-center h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="text-muted small"><i class="mdi mdi-bank-transfer text-purple"></i> Transfer</div>
                                        <div class="fw-bold font-15 text-purple" id="kpi-my-transfer">₦0.00</div>
                                        <div class="text-muted font-10"><span id="kpi-my-transfer-count">0</span> tenders</div>
                                    </div>
                                    <div class="border-top pt-1 mt-1 font-10 text-muted text-start" id="kpi-my-transfer-banks" style="max-height: 48px; overflow-y: auto;">
                                        <div class="text-muted text-center font-9">No Transfer tenders</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="p-2 border rounded bg-white shadow-sm text-center h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="text-muted small"><i class="mdi mdi-cellphone-wireless text-secondary"></i> Mobile / Deposit</div>
                                        <div class="fw-bold font-15 text-secondary" id="kpi-my-deposit">₦0.00</div>
                                        <div class="text-muted font-10"><span id="kpi-my-deposit-count">0</span> tenders</div>
                                    </div>
                                    <div class="border-top pt-1 mt-1 font-10 text-muted text-start" id="kpi-my-deposit-banks" style="max-height: 48px; overflow-y: auto;">
                                        <div class="text-muted text-center font-9">No Deposit tenders</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="p-2 border rounded bg-white shadow-sm text-center h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="text-muted small"><i class="mdi mdi-tag-outline text-warning"></i> Discounts / Splits</div>
                                        <div class="fw-bold font-15 text-warning" id="kpi-my-discount">₦0.00</div>
                                        <div class="text-muted font-10"><span id="kpi-my-splits-count">0</span> split txs</div>
                                    </div>
                                    <div class="border-top pt-1 mt-1 font-10 text-muted fst-italic">Split Tenders</div>
                                </div>
                            </div>
                        </div>

                        <!-- Product & Service Categories KPI Strip -->
                        <div class="row g-2 mb-2" id="my-trans-categories-row">
                            <!-- Clinical & Services Categories -->
                            <div class="col-md-6 col-12">
                                <div class="border rounded p-2 bg-light h-100">
                                    <div class="d-flex justify-content-between align-items-center mb-1 pb-1 border-bottom">
                                        <span class="fw-bold font-12 text-info">
                                            <i class="mdi mdi-stethoscope me-1"></i> Clinical & Service Categories
                                        </span>
                                        <span class="badge bg-info text-white font-11" id="kpi-my-services-total">₦0.00 (0 items)</span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-1 align-items-center" id="my-trans-service-categories-list" style="max-height: 80px; overflow-y: auto;">
                                        <span class="text-muted font-11">No services recorded</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Pharmacy & Products Categories -->
                            <div class="col-md-6 col-12">
                                <div class="border rounded p-2 bg-light h-100">
                                    <div class="d-flex justify-content-between align-items-center mb-1 pb-1 border-bottom">
                                        <span class="fw-bold font-12 text-success">
                                            <i class="mdi mdi-pill me-1"></i> Pharmacy & Product Categories
                                        </span>
                                        <span class="badge bg-success text-white font-11" id="kpi-my-products-total">₦0.00 (0 items)</span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-1 align-items-center" id="my-trans-product-categories-list" style="max-height: 80px; overflow-y: auto;">
                                        <span class="text-muted font-11">No products recorded</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Transactions Grid with Item Outlines -->
                        <div class="table-responsive border rounded bg-white" style="max-height: 440px; overflow-y: auto;">
                            <table class="table table-sm table-hover align-middle mb-0 font-12" id="my-transactions-table">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th width="30"></th>
                                        <th>Date/Time</th>
                                        <th>Receipt / Ref</th>
                                        <th>Patient</th>
                                        <th>Payment Method & Breakdown</th>
                                        <th>Items Paid For</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-center" width="70">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="my-transactions-tbody">
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-5">
                                            <i class="mdi mdi-information-outline font-24"></i>
                                            <p class="mb-0">Click "Load" to fetch your transactions</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- TAB 2: MANAGE TRANSACTIONS (ADJUST / VOID) -->
                    <!-- ========================================== -->
                    <div class="tab-pane fade" id="pane-manage-transactions" role="tabpanel">
                        <!-- Manage Filters -->
                        <div class="card bg-light border mb-3">
                            <div class="card-body p-2">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-2 col-sm-6">
                                        <label class="small text-muted mb-0">From</label>
                                        <input type="date" class="form-control form-control-sm" id="manage-trans-from">
                                    </div>
                                    <div class="col-md-2 col-sm-6">
                                        <label class="small text-muted mb-0">To</label>
                                        <input type="date" class="form-control form-control-sm" id="manage-trans-to">
                                    </div>
                                    <div class="col-md-2 col-sm-6">
                                        <label class="small text-muted mb-0">Method</label>
                                        <select class="form-select form-select-sm" id="manage-trans-method">
                                            <option value="">All Methods</option>
                                            <option value="CASH">Cash</option>
                                            <option value="POS">POS</option>
                                            <option value="TRANSFER">Transfer</option>
                                            <option value="MOBILE">Mobile</option>
                                            <option value="SPLIT">Split Payment</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 col-sm-6">
                                        <label class="small text-muted mb-0">Status</label>
                                        <select class="form-select form-select-sm" id="manage-trans-status">
                                            <option value="">All</option>
                                            <option value="active" selected>Active Only</option>
                                            <option value="voided">Voided Only</option>
                                        </select>
                                    </div>
                                    @php
                                        $isPrivileged = Auth::user() && Auth::user()->hasAnyRole(['Accountant', 'Admin', 'Super-Admin', 'super-admin']);
                                    @endphp
                                    @if($isPrivileged)
                                    <div class="col-md-2 col-sm-6">
                                        <label class="small text-muted mb-0">Scope</label>
                                        <select class="form-select form-select-sm" id="manage-trans-scope">
                                            <option value="all" selected>All Cashiers</option>
                                            <option value="own">My Transactions Only</option>
                                        </select>
                                    </div>
                                    @endif
                                    <div class="{{ $isPrivileged ? 'col-md-2' : 'col-md-4' }} col-sm-6 text-end">
                                        <button class="btn btn-sm btn-primary w-100" id="btn-refresh-manage-transactions">
                                            <i class="mdi mdi-refresh"></i> Filter
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- DataTables Manage Grid -->
                        <div class="table-responsive border rounded bg-white p-2">
                            <table class="table table-sm table-striped table-hover align-middle w-100 font-12" id="manage-transactions-datatable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Ref No</th>
                                        <th>Patient</th>
                                        <th>File No</th>
                                        <th>Payment Method</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center" width="100">Actions</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================= -->
<!-- PAYMENT METHOD ADJUSTMENT MODAL                          -->
<!-- ========================================================= -->
<div class="modal fade" id="adjustPaymentModal" role="dialog" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-warning">
            <div class="modal-header bg-warning text-dark py-2">
                <h5 class="modal-title font-15 mb-0">
                    <i class="mdi mdi-tune"></i> Adjust Transaction Payment Method
                </h5>
                <button type="button" class="close btn-close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="form-adjust-payment">
                <input type="hidden" id="adjust-payment-id" name="payment_id">
                <div class="modal-body p-3 font-13">
                    <div class="alert alert-info py-2 px-3 mb-3 font-11">
                        <i class="mdi mdi-information-outline"></i>
                        Adjusting the payment method generates reversing journal entries in the General Ledger and creates new entries reflecting the new tender/splits.
                    </div>

                    <!-- Read-Only Transaction Context -->
                    <div class="p-2 border rounded bg-light mb-3">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Reference:</span>
                            <span class="fw-bold" id="adjust-tx-ref">-</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Patient:</span>
                            <span class="fw-bold" id="adjust-tx-patient">-</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Total Amount:</span>
                            <span class="fw-bold text-primary" id="adjust-tx-amount">₦0.00</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Current Tender:</span>
                            <span class="badge bg-secondary" id="adjust-tx-current-method">-</span>
                        </div>
                    </div>

                    <!-- New Method Selector -->
                    <div class="mb-3">
                        <label class="form-label fw-bold mb-1">New Payment Method <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="adjust-new-method" required>
                            <option value="CASH">Cash</option>
                            <option value="POS">POS / Card</option>
                            <option value="TRANSFER">Bank Transfer</option>
                            <option value="MOBILE">Mobile Money</option>
                            <option value="SPLIT">Split Payment (Multiple Methods)</option>
                        </select>
                    </div>

                    <!-- Single Bank Selection (for non-cash, non-split) -->
                    <div class="mb-3" id="adjust-bank-col" style="display: none;">
                        <label class="form-label fw-bold mb-1">Select Bank <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="adjust-new-bank">
                            <option value="">-- Select Bank --</option>
                        </select>
                    </div>

                    <!-- Split Legs Container for Adjustments -->
                    <div id="adjust-split-section" class="mb-3 border rounded p-2 bg-light" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-primary font-12"><i class="mdi mdi-call-split"></i> New Split Allocation</span>
                            <button type="button" class="btn btn-xs btn-outline-primary" id="btn-add-adjust-split-leg">
                                <i class="mdi mdi-plus"></i> Add
                            </button>
                        </div>
                        <div id="adjust-split-legs-container">
                            <!-- Injected rows -->
                        </div>
                        <div class="p-2 border rounded bg-white mt-2 font-11">
                            <div class="d-flex justify-content-between">
                                <span>Allocated:</span>
                                <span class="fw-bold" id="adjust-split-allocated">₦0.00</span>
                            </div>
                            <div class="d-flex justify-content-between pt-1 border-top mt-1">
                                <span>Remaining:</span>
                                <span class="fw-bold text-success" id="adjust-split-remaining">₦0.00</span>
                            </div>
                        </div>
                    </div>

                    <!-- Reason Input -->
                    <div class="mb-2">
                        <label class="form-label fw-bold mb-1">Reason for Adjustment <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" id="adjust-reason" rows="2" placeholder="e.g., Patient switched from Cash to POS card due to network delay" required minlength="5"></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm" id="btn-submit-adjustment">
                        <i class="mdi mdi-check"></i> Apply Adjustment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
