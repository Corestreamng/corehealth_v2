{{-- Medications - Tabbed History and Prescriptions --}}
<style>
    .service-tabs .nav-link {
        border-radius: 0;
        transition: all 0.3s ease;
        font-weight: 500;
    }
    .service-tabs .nav-link:hover {
        background-color: #f8f9fa;
        transform: translateY(-2px);
    }
    .service-tabs .nav-link.active {
        background-color: {{ appsettings('hos_color', '#007bff') }};
        color: white !important;
        border-color: {{ appsettings('hos_color', '#007bff') }};
    }
    .tab-content-fade {
        animation: fadeIn 0.4s ease-in;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="card-modern mt-2">
    <div class="card-body">
        {{-- Sub-tabs for History and New Prescription --}}
        <ul class="nav nav-tabs service-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="presc-history-tab" data-bs-toggle="tab"
                    data-bs-target="#presc-history" type="button" role="tab">
                    <i class="fa fa-history"></i> Drug History
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="presc-new-tab" data-bs-toggle="tab"
                    data-bs-target="#presc-new" type="button" role="tab">
                    <i class="fa fa-plus-circle"></i> Add Prescription
                </button>
            </li>
        </ul>

        <div class="tab-content">
            {{-- History Tab --}}
            <div class="tab-pane fade show active tab-content-fade" id="presc-history" role="tabpanel">
                <h5 class="mb-3"><i class="fa fa-pills"></i> Prescription History</h5>
                <div class="table-responsive">
                    <table class="table table-hover" style="width: 100%" id="presc_history_list">
                        <thead class="table-light">
                            <th style="width: 100%;"><i class="fa fa-pills"></i> Prescriptions</th>
                        </thead>
                    </table>
                </div>
            </div>

            {{-- New Prescription Tab --}}
            <div class="tab-pane fade tab-content-fade" id="presc-new" role="tabpanel">
                <div id="prescriptions_save_message" class="mb-2"></div>
                <h5 class="mb-3"><i class="fa fa-plus-circle"></i> New Prescription</h5>

                {{-- Dose Mode Toggle --}}
                <div class="d-flex align-items-center mb-3 gap-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="dose_mode_toggle" onchange="toggleDoseMode(this.checked)">
                        <label class="form-check-label" for="dose_mode_toggle">
                            <i class="fa fa-sliders-h"></i> Structured Dose Entry
                        </label>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleDoseCalculator()">
                        <i class="fa fa-calculator"></i> Dose Calculator
                    </button>
                </div>

                {{-- Mini Dose Calculator Panel --}}
                <div id="dose_calculator_panel" class="card border-secondary mb-3" style="display:none;">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                        <span><i class="fa fa-calculator"></i> <strong>Dose Calculator</strong></span>
                        <button type="button" class="btn-close btn-sm" onclick="toggleDoseCalculator()"></button>
                    </div>
                    <div class="card-body py-2">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="small fw-bold">Patient Weight (kg)</label>
                                <input type="number" class="form-control form-control-sm" id="calc_weight" step="0.1" min="0" oninput="calculateDose()">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold">Dose per kg (mg/kg)</label>
                                <input type="number" class="form-control form-control-sm" id="calc_dose_per_kg" step="0.01" min="0" oninput="calculateDose()">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold">Frequency</label>
                                <select class="form-select form-select-sm" id="calc_frequency" onchange="calculateDose()">
                                    <option value="1">OD (once daily)</option>
                                    <option value="2">BD (twice daily)</option>
                                    <option value="3">TDS (three times)</option>
                                    <option value="4">QID (four times)</option>
                                    <option value="6">Q4H (every 4 hrs)</option>
                                    <option value="4">Q6H (every 6 hrs)</option>
                                    <option value="3">Q8H (every 8 hrs)</option>
                                    <option value="2">Q12H (every 12 hrs)</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold">Duration (days)</label>
                                <input type="number" class="form-control form-control-sm" id="calc_duration" min="1" value="5" oninput="calculateDose()">
                            </div>
                        </div>
                        <div class="row g-2 mt-1">
                            <div class="col-md-3">
                                <label class="small fw-bold">Tablet/Unit Strength</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control" id="calc_tab_strength" step="0.01" min="0" value="500" oninput="calculateDose()">
                                    <span class="input-group-text">mg</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div id="calc_results" class="mt-2 small">
                                    <span class="text-muted">Enter values to calculate...</span>
                                </div>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="button" class="btn btn-sm btn-primary w-100" onclick="applyCalculatorToSelected()">
                                    <i class="fa fa-arrow-down"></i> Apply to Selected
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="consult_presc_search">Search products</label>
                    <input type="text" class="form-control" id="consult_presc_search"
                        onkeyup="searchProducts(this.value)" placeholder="search products..." autocomplete="off">
                    <ul class="list-group" id="consult_presc_res" style="display: none;"></ul>
                </div>
                <br>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped">
                        <thead>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Dose/Freq.</th>
                            <th>*</th>
                        </thead>
                        <tbody id="selected-products"></tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Navigation Buttons --}}
        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
            <button type="button" onclick="switch_tab(event,'imaging_services_tab')" class="btn btn-secondary">
                <i class="fa fa-arrow-left"></i> Previous
            </button>
            <div>
                <button type="button" onclick="savePrescriptionsAndNext()" id="save_prescriptions_btn" class="btn btn-success me-2">
                    <i class="fa fa-save"></i> Save & Next
                </button>
                <button type="button" onclick="savePrescriptions()" class="btn btn-outline-success">
                    <i class="fa fa-save"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>
