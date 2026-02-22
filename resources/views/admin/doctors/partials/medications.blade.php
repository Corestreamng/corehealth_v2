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
        {{-- Treatment Plans + Re-prescribe buttons (Plan §6.4, §5.3) --}}
        <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
            <div class="btn-group">
                <button class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="modal" data-bs-target="#treatmentPlanModal">
                    <i class="fa fa-clipboard-list"></i> Treatment Plans
                </button>
                <button class="btn btn-sm btn-outline-success"
                        onclick="ClinicalOrdersKit.openSaveTemplateModal()">
                    <i class="fa fa-save"></i> Save as Template
                </button>
            </div>
            {{-- Re-prescribe from previous encounter dropdown (Plan §5.3) --}}
            <div class="dropdown" id="rp-encounter-dropdown">
                <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button"
                        data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <i class="fa fa-redo"></i> Re-prescribe from Encounter
                </button>
                <ul class="dropdown-menu rp-encounter-menu" style="min-width: 320px; max-height: 300px; overflow-y: auto;">
                    <li class="dropdown-item text-muted"><i class="fa fa-spinner fa-spin"></i> Loading...</li>
                </ul>
            </div>
        </div>

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

                {{-- Dose Mode Toggle — Segmented button group (Plan §2.2, structured default) --}}
                @include('admin.partials.dose-mode-toggle', ['prefix' => ''])

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

        {{-- Navigation Buttons (Save removed — prescriptions auto-save on add; Plan §4.5) --}}
        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
            <button type="button" onclick="switch_tab(event,'imaging_services_tab')" class="btn btn-secondary">
                <i class="fa fa-arrow-left"></i> Previous
            </button>
            <button type="button" onclick="switch_tab(event,'procedures_tab')" class="btn btn-primary">
                Next <i class="fa fa-arrow-right"></i>
            </button>
        </div>
    </div>
</div>
