{{-- Unified Prescription Management Partial --}}
{{-- Used by: patient show1.blade.php, pharmacy workbench.blade.php --}}
{{-- Requires: $patient variable with id, user_id --}}

<style>
/* Prescription Card Styles */
.presc-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 12px 15px;
    margin-bottom: 8px;
    transition: all 0.2s ease;
}

.presc-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-color: #0d6efd;
}

.presc-card.selected {
    background: #e7f1ff;
    border-color: #0d6efd;
}

.presc-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}

.presc-card-title {
    font-weight: 600;
    color: #212529;
    font-size: 0.95rem;
}

.presc-card-code {
    font-size: 0.75rem;
    color: #6c757d;
}

.presc-card-price {
    font-weight: 700;
    color: #198754;
    font-size: 1rem;
}

.presc-card-body {
    font-size: 0.875rem;
    color: #495057;
}

.presc-card-hmo-info {
    background: #f8f9fa;
    border-radius: 4px;
    padding: 4px 8px;
    margin-top: 8px;
}

.presc-card-meta {
    border-top: 1px solid #f1f3f5;
    padding-top: 8px;
    margin-top: 8px;
    font-size: 0.8rem;
    color: #6c757d;
}

.presc-card-meta-item {
    display: inline-flex;
    align-items: center;
    margin-right: 12px;
}

.presc-card-meta-item i {
    margin-right: 4px;
}

.presc-card-status {
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 3px;
    margin-top: 4px;
}

.presc-card-status.paid { background: #d4edda; color: #155724; }
.presc-card-status.unpaid { background: #f8d7da; color: #721c24; }
.presc-card-status.validated { background: #cce5ff; color: #004085; }
.presc-card-status.pending { background: #fff3cd; color: #856404; }

/* DataTable Adjustments for Card View */
#presc_billing_table td,
#presc_dispense_table td,
#presc_history_table td {
    vertical-align: top;
    padding: 8px;
}

#presc_billing_table td:first-child,
#presc_dispense_table td:first-child {
    width: 40px;
    text-align: center;
}

.presc-card-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
}
</style>

@php
    $patientId = $patient->id ?? (isset($patientId) ? $patientId : null);
    $patientUserId = $patient->user->id ?? (isset($patientUserId) ? $patientUserId : null);
@endphp

<div class="presc-management-container" data-patient-id="{{ $patientId }}" data-patient-user-id="{{ $patientUserId }}">
    <!-- Sub-tabs Navigation -->
    <ul class="nav nav-tabs nav-tabs-modern mb-3" id="prescSubTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="presc-billing-tab" data-bs-toggle="tab"
                    data-bs-target="#presc-billing-pane" type="button" role="tab">
                <i class="mdi mdi-cash-register me-1"></i> Billing
                <span class="badge bg-warning ms-1" id="presc-billing-count">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="presc-pending-tab" data-bs-toggle="tab"
                    data-bs-target="#presc-pending-pane" type="button" role="tab">
                <i class="mdi mdi-clock-outline me-1"></i> Pending
                <span class="badge bg-danger ms-1" id="presc-pending-count">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="presc-dispense-tab" data-bs-toggle="tab"
                    data-bs-target="#presc-dispense-pane" type="button" role="tab">
                <i class="mdi mdi-pill me-1"></i> Ready to Dispense
                <span class="badge bg-success ms-1" id="presc-dispense-count">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="presc-history-tab" data-bs-toggle="tab"
                    data-bs-target="#presc-history-pane" type="button" role="tab">
                <i class="mdi mdi-history me-1"></i> History
                <span class="badge bg-secondary ms-1" id="presc-history-count">0</span>
            </button>
        </li>
    </ul>

    <!-- Sub-tabs Content -->
    <div class="tab-content" id="prescSubTabsContent">
        <!-- Billing Tab -->
        <div class="tab-pane fade show active" id="presc-billing-pane" role="tabpanel">
            <div class="card-modern card-modern">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="mdi mdi-cash-register"></i> Requested Prescriptions (Awaiting Billing)</h6>
                </div>
                <div class="card-body">
                    <input type="hidden" id="presc_patient_user_id" value="{{ $patientUserId }}">
                    <input type="hidden" id="presc_patient_id" value="{{ $patientId }}">

                    <!-- Billing DataTable with Card Layout -->
                    <div class="table-responsive">
                        <table class="table table-hover" style="width: 100%" id="presc_billing_table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;"><input type="checkbox" id="select-all-billing" onclick="toggleAllPrescBilling(this)"></th>
                                    <th><i class="mdi mdi-pill"></i> Medication</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                    <hr>

                    <!-- Add More Items Section -->
                    <div class="card-modern mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="mdi mdi-plus-circle"></i> Add More Items</h6>
                        </div>
                        <div class="card-body">
                            <div class="position-relative">
                                <label>Search products</label>
                                <input type="text" class="form-control" id="presc_product_search"
                                       onkeyup="searchProductsForPresc(this.value)"
                                       placeholder="Search products by name or code..." autocomplete="off">
                                <ul class="list-group position-absolute w-100" id="presc_product_results"
                                    style="display: none; max-height: 300px; overflow-y: auto; z-index: 1000;"></ul>
                            </div>
                            <br>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <th style="width: 40px;">*</th>
                                        <th>Product</th>
                                        <th style="width: 100px;">Price</th>
                                        <th style="width: 200px;">Dose/Freq.</th>
                                        <th style="width: 60px;">*</th>
                                    </thead>
                                    <tbody id="presc_added_products"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Total and Actions -->
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <label class="fw-bold">Total: </label>
                            <span class="fs-5 text-primary" id="presc_billing_total">₦0.00</span>
                            <input type="hidden" id="presc_billing_total_val" value="0">
                        </div>
                        <div>
                            <button type="button" class="btn btn-danger me-2" onclick="dismissPrescItems('billing')">
                                <i class="mdi mdi-close"></i> Dismiss Selected
                            </button>
                            <button type="button" class="btn btn-primary" onclick="billPrescItems()" id="btn-bill-presc">
                                <i class="mdi mdi-check"></i> Bill Selected
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Tab (Awaiting Payment/Validation) -->
        <div class="tab-pane fade" id="presc-pending-pane" role="tabpanel">
            <div class="card-modern card-modern">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="mdi mdi-clock-outline"></i> Pending Items (Awaiting Payment / HMO Validation)</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning mb-3">
                        <i class="mdi mdi-alert-circle-outline"></i>
                        <strong>Important:</strong> These items have been billed but are waiting for payment or HMO validation before they can be dispensed.
                        <ul class="mb-0 mt-2">
                            <li><span class="badge bg-danger">Awaiting Payment</span> - Patient needs to pay the billable amount</li>
                            <li><span class="badge bg-info">Awaiting HMO Validation</span> - HMO claims need to be validated</li>
                        </ul>
                    </div>

                    <!-- Pending DataTable with Card Layout -->
                    <div class="table-responsive">
                        <table class="table table-hover" style="width: 100%" id="presc_pending_table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;"><input type="checkbox" id="select-all-pending" onclick="toggleAllPrescPending(this)"></th>
                                    <th><i class="mdi mdi-pill"></i> Medication</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                    <!-- Pending Actions -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted small">
                            <i class="mdi mdi-information-outline"></i> Items must be paid/validated before they can be dispensed
                        </div>
                        <div>
                            <button type="button" class="btn btn-danger" onclick="dismissPrescItems('pending')">
                                <i class="mdi mdi-close"></i> Dismiss Selected
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dispense Tab (Ready to Dispense) -->
        <div class="tab-pane fade" id="presc-dispense-pane" role="tabpanel">
            <div class="card-modern card-modern">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="mdi mdi-pill"></i> Ready to Dispense</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-success mb-3">
                        <i class="mdi mdi-check-circle-outline"></i>
                        <strong>Ready!</strong> All items below have been paid (if applicable) and validated (if HMO). They are ready to be dispensed.
                    </div>

                    <!-- Dispense DataTable with Card Layout -->
                    <div class="table-responsive">
                        <table class="table table-hover" style="width: 100%" id="presc_dispense_table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;"><input type="checkbox" id="select-all-dispense" onclick="toggleAllPrescDispense(this)"></th>
                                    <th><i class="mdi mdi-pill"></i> Medication</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex justify-content-end mt-3">
                        <button type="button" class="btn btn-danger me-2" onclick="dismissPrescItems('dispense')">
                            <i class="mdi mdi-close"></i> Dismiss Selected
                        </button>
                        <button type="button" class="btn btn-success" onclick="dispensePrescItems()" id="btn-dispense-presc">
                            <i class="mdi mdi-pill"></i> Dispense Selected
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- History Tab -->
        <div class="tab-pane fade" id="presc-history-pane" role="tabpanel">
            <div class="card-modern card-modern">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="mdi mdi-history"></i> Prescription History</h6>
                </div>
                <div class="card-body">
                    <!-- History DataTable with Card Layout -->
                    <div class="table-responsive">
                        <table class="table table-hover" style="width: 100%" id="presc_history_table">
                            <thead class="table-light">
                                <th><i class="mdi mdi-pill"></i> Dispensed Medications</th>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Prescription Card Styles */
.presc-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 12px 15px;
    margin-bottom: 8px;
    transition: all 0.2s ease;
}

.presc-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-color: #0d6efd;
}

.presc-card.selected {
    background: #e7f1ff;
    border-color: #0d6efd;
}

.presc-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}

.presc-card-title {
    font-weight: 600;
    color: #212529;
    font-size: 14px;
}

.presc-card-code {
    font-size: 12px;
    color: #6c757d;
}

.presc-card-body {
    font-size: 13px;
    color: #495057;
}

.presc-card-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 8px;
    font-size: 12px;
    color: #6c757d;
}

.presc-card-meta-item {
    display: flex;
    align-items: center;
    gap: 4px;
}

.presc-card-price {
    font-weight: 600;
    color: #198754;
}

.presc-card-status {
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 500;
}

.presc-card-status.paid {
    background: #d1e7dd;
    color: #0f5132;
}

.presc-card-status.unpaid {
    background: #f8d7da;
    color: #842029;
}

.presc-card-status.validated {
    background: #cff4fc;
    color: #055160;
}

.presc-card-status.pending {
    background: #fff3cd;
    color: #664d03;
}

.presc-card-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.presc-card-hmo-info {
    background: #f8f9fa;
    border-radius: 4px;
    padding: 6px 10px;
    margin-top: 8px;
    font-size: 12px;
}

/* Nav tabs modern style */
.nav-tabs-modern .nav-link {
    border: none;
    border-bottom: 2px solid transparent;
    color: #6c757d;
    padding: 10px 20px;
    font-weight: 500;
}

.nav-tabs-modern .nav-link:hover {
    border-color: transparent;
    color: #0d6efd;
}

.nav-tabs-modern .nav-link.active {
    color: #0d6efd;
    border-bottom-color: #0d6efd;
    background: transparent;
}

.nav-tabs-modern .badge {
    font-size: 10px;
    padding: 3px 6px;
}
</style>
