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

<div class="card mt-2">
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
                <h5 class="mb-3"><i class="fa fa-plus-circle"></i> New Prescription</h5>
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
            <button type="button" onclick="$('#imaging_services_tab').click()" class="btn btn-secondary">
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
        <div id="prescriptions_save_message" class="mt-2"></div>
    </div>
</div>
