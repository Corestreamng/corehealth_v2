<div class="tab-content" id="injection-sub-content">
                        <!-- Administer Sub-tab -->
                        <div class="tab-pane fade show active" id="injection-administer" role="tabpanel">
                            <div class="card-modern">
                                <div class="card-header bg-primary text-white py-2">
                                    <h6 class="mb-0"><i class="mdi mdi-needle"></i> Administer Injection</h6>
                                </div>
                                <div class="card-body">
                                    <input type="hidden" id="injection-drug-source" value="pharmacy_dispensed">

                                    <!-- Drug Source Selector -->
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Drug Source</label>
                                        <div class="btn-group w-100" role="group" aria-label="Injection drug source">
                                            <button type="button" class="btn btn-outline-primary active" data-inj-source="pharmacy_dispensed">Pharmacy Dispensed</button>
                                            <button type="button" class="btn btn-outline-secondary" data-inj-source="patient_own">Patient's Own</button>
                                            <button type="button" class="btn btn-outline-info" data-inj-source="ward_stock">Ward Stock</button>
                                        </div>
                                    </div>

                                    <!-- Pharmacy Dispensed Section -->
                                    <div class="mb-3 source-section" id="inj-source-pharmacy">
                                        <div class="alert alert-info py-2 mb-2"><i class="mdi mdi-pill"></i> Select dispensed prescriptions to chart.</div>
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-8">
                                                <label for="injection-rx-select" class="form-label">Dispensed Prescriptions</label>
                                                <select class="form-control" id="injection-rx-select">
                                                    <option value="">-- Loading prescriptions --</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 text-right">
                                                <button type="button" class="btn btn-success mt-4 w-100" id="injection-add-rx">
                                                    <i class="mdi mdi-plus"></i> Add to list
                                                </button>
                                            </div>
                                        </div>
                                        <div class="small text-muted mt-1" id="injection-rx-summary" style="display:none;"></div>
                                    </div>

                                    <!-- Patient Own Section -->
                                    <div class="mb-3 source-section" id="inj-source-patient" style="display:none;">
                                        <div class="alert alert-warning py-2 mb-3"><i class="mdi mdi-account-alert"></i> Record patient-supplied drug details. No stock will be deducted.</div>
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <label class="form-label">Drug Name</label>
                                                <input type="text" class="form-control" id="inj-external-name" placeholder="Patient supplied drug">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Quantity</label>
                                                <input type="number" step="0.01" class="form-control" id="inj-external-qty" placeholder="1">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Batch (optional)</label>
                                                <input type="text" class="form-control" id="inj-external-batch" placeholder="Batch #">
                                            </div>
                                        </div>
                                        <div class="row g-2 mt-2">
                                            <div class="col-md-4">
                                                <label class="form-label">Expiry (optional)</label>
                                                <input type="date" class="form-control" id="inj-external-expiry">
                                            </div>
                                            <div class="col-md-8">
                                                <label class="form-label">Source Note (optional)</label>
                                                <input type="text" class="form-control" id="inj-external-note" placeholder="Where obtained / remarks">
                                            </div>
                                        </div>
                                        {{-- §7.2: Add to List button for patient's own virtual row --}}
                                        <div class="row mt-3">
                                            <div class="col text-end">
                                                <button type="button" class="btn btn-warning btn-sm" id="btn-add-patient-own-injection" onclick="addPatientOwnInjectionRow()">
                                                    <i class="mdi mdi-plus"></i> Add Drug to List
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Ward Stock Section -->
                                    <div class="source-section" id="inj-source-ward" style="display:none;">
                                        <div class="store-selection-panel mb-4 p-3 rounded" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border: 2px solid #90caf9;">
                                            <div class="row align-items-center">
                                                <div class="col-md-6">
                                                    <label class="form-label fw-bold mb-2" style="font-size: 1rem;">
                                                        <i class="mdi mdi-store text-primary"></i> Select Ward Store
                                                    </label>
                                                    <select id="injection-store" class="form-control form-control-lg" style="border: 2px solid #1976d2; font-weight: 500;">
                                                        @if($resolvedStore ?? null)
                                                            <option value="{{ $resolvedStore->id }}" selected>{{ $resolvedStore->store_name }}</option>
                                                        @else
                                                            <option value="">-- No store assigned --</option>
                                                        @endif
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <div id="injection-store-info" class="p-3 bg-white rounded shadow-sm" style="display: none;">
                                                        <h6 class="text-primary mb-2"><i class="mdi mdi-package-variant"></i> Selected Store Stock</h6>
                                                        <div id="injection-store-stock-summary" class="small">
                                                            <!-- Stock will show here when items are selected -->
                                                        </div>
                                                    </div>
                                                    <div id="injection-store-placeholder" class="p-3 text-muted text-center">
                                                        <i class="mdi mdi-arrow-left-bold mdi-24px"></i>
                                                        <p class="mb-0 small">Select store first, then add drugs</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        {{-- §5.3: Bill Patient checkbox — unchecked = hospital absorbs cost, checked = creates POSR via tariff pipeline --}}
                                        <div class="form-check mt-2 ms-1">
                                            <input class="form-check-input" type="checkbox" id="injection-bill-patient" value="1">
                                            <label class="form-check-label" for="injection-bill-patient">
                                                <i class="mdi mdi-receipt text-info"></i> <strong>Bill Patient</strong>
                                                <small class="text-muted d-block">Creates a billing entry for this item (applies HMO tariff if applicable)</small>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Step 2: Drug Search (for ward stock / patient-own) -->
                                    <div class="form-group mb-3 inj-non-pharmacy">
                                        <label for="injection-drug-search"><i class="mdi mdi-magnify"></i> Step 2: Search Drug/Product</label>
                                        <input type="text" class="form-control" id="injection-drug-search"
                                               placeholder="Type to search for any drug or product..." autocomplete="off">
                                        <ul class="list-group" id="injection-drug-results"
                                            style="display: none; position: absolute; z-index: 1000; max-height: 250px; overflow-y: auto; width: calc(100% - 30px); box-shadow: 0 4px 6px rgba(0,0,0,0.1);"></ul>
                                        <small class="text-muted">Search hospital inventory. For dispensed prescriptions, use the list above.</small>
                                    </div>

                                    <!-- Selected Drugs Table with Stock & Batch Column -->
                                    <div class="table-responsive mb-3">
                                        <table class="table table-sm table-bordered table-striped" id="injection-selected-drugs">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th width="4%">#</th>
                                                    <th width="20%">Drug/Product</th>
                                                    <th width="8%">Qty</th>
                                                    <th width="18%">
                                                        <i class="mdi mdi-package-variant"></i> Batch
                                                        <span class="badge badge-info badge-sm ml-1" title="FIFO Recommended">FIFO</span>
                                                    </th>
                                                    <th width="10%">Stock</th>
                                                    <th width="12%">Price</th>
                                                    <th width="13%">HMO</th>
                                                    <th width="10%">Dose</th>
                                                    <th width="5%">*</th>
                                                </tr>
                                            </thead>
                                            <tbody id="injection-selected-body">
                                                <!-- Selected drugs will be added here with batch dropdown -->
                                            </tbody>
                                            <tfoot>
                                                <tr class="bg-light">
                                                    <td colspan="5" class="text-right"><strong>Total:</strong></td>
                                                    <td id="injection-total-price"><strong>₦0.00</strong></td>
                                                    <td id="injection-total-coverage">-</td>
                                                    <td colspan="2"></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>

                                    <!-- Step 3: Administration Details -->
                                    <form id="injection-form">
                                        <h6 class="text-muted mb-3"><i class="mdi mdi-clipboard-text"></i> Step 3: Administration Details</h6>
                                        <div class="form-row">
                                            <div class="form-group col-md-4">
                                                <label for="injection-route"><i class="mdi mdi-routes"></i> Route *</label>
                                                <select class="form-control" id="injection-route" required>
                                                    <option value="">Select Route</option>
                                                    <option value="IM">Intramuscular (IM)</option>
                                                    <option value="IV">Intravenous (IV)</option>
                                                    <option value="SC">Subcutaneous (SC)</option>
                                                    <option value="ID">Intradermal (ID)</option>
                                                </select>
                                            </div>
                                            <div class="form-group col-md-4">
                                                <label for="injection-site"><i class="mdi mdi-map-marker"></i> Site *</label>
                                                <select class="form-control" id="injection-site" required>
                                                    <option value="">Select Site</option>
                                                    <option value="Left Arm">Left Arm (Deltoid)</option>
                                                    <option value="Right Arm">Right Arm (Deltoid)</option>
                                                    <option value="Left Thigh">Left Thigh (Vastus Lateralis)</option>
                                                    <option value="Right Thigh">Right Thigh (Vastus Lateralis)</option>
                                                    <option value="Left Buttock">Left Buttock (Gluteus)</option>
                                                    <option value="Right Buttock">Right Buttock (Gluteus)</option>
                                                    <option value="Abdomen">Abdomen</option>
                                                </select>
                                            </div>
                                            <div class="form-group col-md-4">
                                                <label for="injection-time"><i class="mdi mdi-clock"></i> Time *</label>
                                                <input type="datetime-local" class="form-control" id="injection-time" required>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="injection-notes"><i class="mdi mdi-note-text"></i> Notes</label>
                                            <textarea class="form-control" id="injection-notes" rows="2" placeholder="Any additional notes..."></textarea>
                                        </div>
                                        <div class="form-actions text-right">
                                            <button type="submit" class="btn btn-primary btn-lg">
                                                <i class="mdi mdi-check"></i> Administer Injection
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- History Sub-tab -->
                        <div class="tab-pane fade" id="injection-history" role="tabpanel">
                            <div class="card-modern">
                                <div class="card-header py-2">
                                    <h6 class="mb-0"><i class="mdi mdi-history"></i> Injection History</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover" id="injection-history-table" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th>Date/Time</th>
                                                    <th>Drug</th>
                                                    <th>Dose</th>
                                                    <th>Route</th>
                                                    <th>Site</th>
                                                    <th>Nurse</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>