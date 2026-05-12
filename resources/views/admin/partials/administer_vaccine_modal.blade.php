{{-- Shared Administer Vaccine Modal (reused from nursing and maternity workflow) --}}
<div class="modal fade" id="administerVaccineModalShared" tabindex="-1" role="dialog" aria-labelledby="administerVaccineModalSharedLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="administerVaccineModalSharedLabel">
                    <i class="mdi mdi-needle"></i> Administer Vaccine
                </h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3" id="imm-modal-schedule-info">
                    <div class="row">
                        <div class="col-md-6"><strong><i class="mdi mdi-needle"></i> Vaccine:</strong> <span id="imm-modal-vaccine-name">-</span></div>
                        <div class="col-md-3"><strong><i class="mdi mdi-numeric"></i> Dose:</strong> <span id="imm-modal-dose-label">-</span></div>
                        <div class="col-md-3"><strong><i class="mdi mdi-calendar"></i> Due:</strong> <span id="imm-modal-due-date">-</span></div>
                    </div>
                </div>

                <div class="form-check mb-3 p-2 bg-light rounded border">
                    <input type="checkbox" class="form-check-input ms-1" id="imm-modal-vaccine-is-external">
                    <label class="form-check-label font-weight-bold text-dark ms-4" for="imm-modal-vaccine-is-external">
                        <i class="mdi mdi-earth text-primary"></i> External / Patient's Own Vaccine (Skip hospital stock deduction)
                    </label>
                </div>

                <div id="imm-modal-store-selection-container" class="store-selection-panel mb-4 p-3 rounded" style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border: 2px solid #81c784;">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <label class="form-label fw-bold mb-2" style="font-size: 1rem;"><i class="mdi mdi-store text-success"></i> Step 1: Select Dispensing Store</label>
                            <select id="imm-modal-vaccine-store" class="form-control form-control-lg" style="border: 2px solid #388e3c; font-weight: 500;" required>
                                @if($resolvedStore ?? null)
                                <option value="{{ $resolvedStore->id }}" selected>{{ $resolvedStore->store_name }}</option>
                                @else
                                <option value="">-- No store assigned --</option>
                                @endif
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div id="imm-modal-vaccine-store-info" class="p-3 bg-white rounded shadow-sm" style="display: none;">
                                <h6 class="text-success mb-2"><i class="mdi mdi-package-variant"></i> Store Stock</h6>
                                <div id="imm-modal-vaccine-store-stock" class="small"></div>
                            </div>
                            <div id="imm-modal-vaccine-store-placeholder" class="p-3 text-muted text-center">
                                <i class="mdi mdi-arrow-left-bold mdi-24px"></i>
                                <p class="mb-0 small">Select store first</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- External Service Billing (Hidden by default) -->
                <div id="imm-modal-external-billing-container" class="mb-4 p-3 rounded d-none" style="background: #e3f2fd; border: 2px solid #90caf9;">
                    <label class="form-label fw-bold mb-2"><i class="mdi mdi-cash-register text-info"></i> Administration Service Fee (Optional)</label>
                    <div class="input-group mb-2">
                        <input type="text" class="form-control" id="imm-modal-external-service-search" placeholder="Search nursing services to bill..." autocomplete="off">
                        <input type="hidden" id="imm-modal-external-service-id">
                    </div>
                    <ul class="list-group" id="imm-modal-external-service-results" style="display: none; position: absolute; z-index: 1050; max-height: 200px; overflow-y: auto; width: calc(100% - 30px); box-shadow: 0 4px 6px rgba(0,0,0,0.1);"></ul>
                    
                    <div id="imm-modal-selected-service-card" class="card-modern d-none mb-2" style="background: #ffffff; border-left: 4px solid #2196f3;">
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong id="imm-modal-selected-service-name">-</strong>
                                    <br><small class="text-muted" id="imm-modal-selected-service-category">-</small>
                                </div>
                                <div class="text-right">
                                    <div id="imm-modal-selected-service-hmo-info"></div>
                                    <span class="badge badge-info text-white" id="imm-modal-selected-service-price">₦0.00</span>
                                    <button type="button" class="btn btn-sm btn-outline-danger ml-2" id="imm-modal-remove-service"><i class="mdi mdi-close"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <small class="text-muted">If selected, the patient will be billed for this service instead of the vaccine product.</small>
                </div>

                <div class="form-group mb-3" id="imm-modal-product-selection-container">
                    <label for="imm-modal-vaccine-search"><i class="mdi mdi-magnify"></i> Step 2: Search Vaccine Product *</label>
                    <input type="text" class="form-control" id="imm-modal-vaccine-search" placeholder="Type to search for vaccine product from inventory..." autocomplete="off">
                    <ul class="list-group" id="imm-modal-vaccine-results" style="display:none; position:absolute; z-index:1050; max-height:200px; overflow-y:auto; width:calc(100% - 30px); box-shadow:0 4px 6px rgba(0,0,0,0.1);"></ul>
                </div>

                <div class="card-modern mb-3 d-none" id="imm-modal-selected-product-card">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong id="imm-modal-selected-product-name">-</strong>
                                <br><small class="text-muted" id="imm-modal-selected-product-details">-</small>
                                <br><small id="imm-modal-selected-product-stock" class="text-success"></small>
                            </div>
                            <div class="text-right">
                                <span class="badge badge-primary" id="imm-modal-selected-product-price">₦0.00</span>
                                <button type="button" class="btn btn-sm btn-outline-danger ml-2" id="imm-modal-remove-product"><i class="mdi mdi-close"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="imm-modal-schedule-id">
                <input type="hidden" id="imm-modal-product-id">

                <h6 class="text-muted mb-3"><i class="mdi mdi-clipboard-text"></i> Step 3: Administration Details</h6>
                <form id="imm-modal-immunization-form">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="imm-modal-vaccine-site"><i class="mdi mdi-map-marker"></i> Administration Site *</label>
                            <select class="form-control" id="imm-modal-vaccine-site" required>
                                <option value="">Select Site</option>
                                <option value="Left Deltoid">Left Deltoid (Arm)</option>
                                <option value="Right Deltoid">Right Deltoid (Arm)</option>
                                <option value="Left Vastus Lateralis">Left Vastus Lateralis (Thigh)</option>
                                <option value="Right Vastus Lateralis">Right Vastus Lateralis (Thigh)</option>
                                <option value="Left Gluteal">Left Gluteal</option>
                                <option value="Right Gluteal">Right Gluteal</option>
                                <option value="Oral">Oral</option>
                                <option value="Intranasal">Intranasal</option>
                                <option value="Intradermal">Intradermal</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="imm-modal-vaccine-route"><i class="mdi mdi-routes"></i> Route *</label>
                            <select class="form-control" id="imm-modal-vaccine-route" required>
                                <option value="">Select Route</option>
                                <option value="Intramuscular">Intramuscular (IM)</option>
                                <option value="Subcutaneous">Subcutaneous (SC)</option>
                                <option value="Intradermal">Intradermal (ID)</option>
                                <option value="Oral">Oral (PO)</option>
                                <option value="Intranasal">Intranasal</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4" id="imm-modal-vaccine-batch-container">
                            <label for="imm-modal-vaccine-batch-select"><i class="mdi mdi-package-variant"></i> Select Batch <span class="badge badge-info badge-sm ml-1" title="Auto-selects FIFO">FIFO</span></label>
                            <select class="form-control" id="imm-modal-vaccine-batch-select">
                                <option value="">-- Select store first --</option>
                            </select>
                            <small class="text-muted" id="imm-modal-vaccine-batch-help">Select product to see available batches</small>
                        </div>
                        <div class="form-group col-md-4 d-none" id="imm-modal-vaccine-batch-manual-container">
                            <label for="imm-modal-vaccine-batch-manual"><i class="mdi mdi-package-variant"></i> Batch Number</label>
                            <input type="text" class="form-control" id="imm-modal-vaccine-batch-manual" placeholder="Enter batch number">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="imm-modal-vaccine-expiry"><i class="mdi mdi-calendar-alert"></i> Expiry Date</label>
                            <input type="date" class="form-control" id="imm-modal-vaccine-expiry" readonly>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="imm-modal-vaccine-time"><i class="mdi mdi-clock"></i> Administration Time *</label>
                            <input type="datetime-local" class="form-control" id="imm-modal-vaccine-time" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="imm-modal-vaccine-manufacturer"><i class="mdi mdi-factory"></i> Manufacturer</label>
                            <input type="text" class="form-control" id="imm-modal-vaccine-manufacturer" placeholder="Vaccine manufacturer">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="imm-modal-vaccine-vis"><i class="mdi mdi-file-document"></i> VIS Date Given</label>
                            <input type="date" class="form-control" id="imm-modal-vaccine-vis" title="Vaccine Information Statement date">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="imm-modal-vaccine-notes"><i class="mdi mdi-note-text"></i> Notes / Reactions</label>
                        <textarea class="form-control" id="imm-modal-vaccine-notes" rows="2" placeholder="Any observations, reactions, or notes..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="mdi mdi-close"></i> Cancel</button>
                <button type="button" class="btn btn-success btn-lg" id="imm-modal-submit-immunization"><i class="mdi mdi-check"></i> Record Immunization</button>
            </div>
        </div>
    </div>
</div>
