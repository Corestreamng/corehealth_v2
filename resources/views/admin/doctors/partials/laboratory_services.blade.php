{{-- Laboratory Services - Tabbed History and Request --}}
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
        {{-- Sub-tabs for History and New Request --}}
        <ul class="nav nav-tabs service-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="lab-history-tab" data-bs-toggle="tab"
                    data-bs-target="#lab-history" type="button" role="tab">
                    <i class="fa fa-history"></i> Lab History
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="lab-new-tab" data-bs-toggle="tab"
                    data-bs-target="#lab-new" type="button" role="tab">
                    <i class="fa fa-plus-circle"></i> New Lab Request
                </button>
            </li>
        </ul>

        <div class="tab-content">
            {{-- History Tab --}}
            <div class="tab-pane fade show active tab-content-fade" id="lab-history" role="tabpanel">
                <h5 class="mb-3"><i class="fa fa-flask"></i> Laboratory History</h5>
                <div class="table-responsive">
                    <table class="table table-hover" style="width: 100%" id="investigation_history_list">
                        <thead class="table-light">
                            <th style="width: 100%;"><i class="fa fa-flask"></i> Laboratory Requests</th>
                        </thead>
                    </table>
                </div>
            </div>

            {{-- New Request Tab --}}
            <div class="tab-pane fade tab-content-fade" id="lab-new" role="tabpanel">
                <h5 class="mb-3"><i class="fa fa-plus-circle"></i> New Lab Request</h5>
                <div class="form-group">
                    <label for="consult_invest_search">Search services</label>
                    <input type="text" class="form-control" id="consult_invest_search"
                        onkeyup="searchServices(this.value)" placeholder="search services..." autocomplete="off">
                    <ul class="list-group" id="consult_invest_res" style="display: none;"></ul>
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
                        <tbody id="selected-services"></tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Navigation Buttons --}}
        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
            <button type="button" onclick="switch_tab(event,'clinical_notes_tab')" class="btn btn-secondary">
                <i class="fa fa-arrow-left"></i> Previous
            </button>
            <div>
                <button type="button" onclick="saveLabsAndNext()" id="save_labs_btn" class="btn btn-success me-2">
                    <i class="fa fa-save"></i> Save & Next
                </button>
                <button type="button" onclick="saveLabs()" class="btn btn-outline-success">
                    <i class="fa fa-save"></i> Save
                </button>
            </div>
        </div>
        <div id="labs_save_message" class="mt-2"></div>
    </div>
</div>
