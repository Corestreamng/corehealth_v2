{{-- Imaging Services - Tabbed History and Request --}}
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
        {{-- Treatment Plans + Save as Template (Plan §6.4: buttons at top of all 4 tab areas) --}}
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
        </div>

        {{-- Sub-tabs for History and New Request --}}
        <ul class="nav nav-tabs service-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="imaging-history-tab" data-bs-toggle="tab"
                    data-bs-target="#imaging-history" type="button" role="tab">
                    <i class="fa fa-history"></i> Imaging History
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="imaging-new-tab" data-bs-toggle="tab"
                    data-bs-target="#imaging-new" type="button" role="tab">
                    <i class="fa fa-plus-circle"></i> New Imaging Request
                </button>
            </li>
        </ul>

        <div class="tab-content">
            {{-- History Tab --}}
            <div class="tab-pane fade show active tab-content-fade" id="imaging-history" role="tabpanel">
                <h5 class="mb-3"><i class="fa fa-x-ray"></i> Imaging History</h5>
                <div class="table-responsive">
                    <table class="table table-hover" style="width: 100%" id="imaging_history_list">
                        <thead class="table-light">
                            <th style="width: 100%;"><i class="fa fa-x-ray"></i> Imaging Requests</th>
                        </thead>
                    </table>
                </div>
            </div>

            {{-- New Request Tab --}}
            <div class="tab-pane fade tab-content-fade" id="imaging-new" role="tabpanel">
                <div id="imaging_save_message" class="mb-2"></div>
                <h5 class="mb-3"><i class="fa fa-plus-circle"></i> New Imaging Request</h5>
                <div class="form-group">
                    <label for="consult_imaging_search">Search imaging services</label>
                    <input type="text" class="form-control" id="consult_imaging_search"
                        onkeyup="searchImagingServices(this.value)" placeholder="search imaging services..." autocomplete="off">
                    <ul class="list-group" id="consult_imaging_res" style="display: none;"></ul>
                </div>
                <br>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped">
                        <thead>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Notes</th>
                            <th>*</th>
                        </thead>
                        <tbody id="selected-imaging-services"></tbody>
                    </table>
                </div>
                {{-- Phase 2d (Plan §4.5): Auto-save status line — items save on add --}}
                <div class="auto-save-status text-muted small mt-2" id="imaging-auto-save-status"></div>
            </div>
        </div>

        {{-- Navigation Buttons (Save removed — imaging auto-saves on add) --}}
        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
            <button type="button" onclick="switch_tab(event,'laboratory_services_tab')" class="btn btn-secondary">
                <i class="fa fa-arrow-left"></i> Previous
            </button>
            <div>
                <button type="button" onclick="$('#medications_tab').click();" class="btn btn-success">
                    <i class="fa fa-arrow-right"></i> Next
                </button>
            </div>
        </div>
    </div>
</div>
