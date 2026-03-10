@extends('admin.layouts.app')
@section('title', 'HMO Tariff Management')
@section('page_name', 'HMO Tariffs')
@section('subpage_name', 'Tariff Management')
@section('content')

<section class="content">
    <div class="container-fluid">
        <!-- Filters Card -->
        <div class="card-modern">
            <div class="card-header bg-primary text-white">
                <h3 class="card-title"><i class="fa fa-filter"></i> Filters</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>HMO</label>
                            <select class="form-control form-control-sm" id="filter_hmo_id">
                                <option value="">All HMOs</option>
                                @foreach($hmos as $hmo)
                                    <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Type</label>
                            <select class="form-control form-control-sm" id="filter_type">
                                <option value="">All Types</option>
                                <option value="product">Products</option>
                                <option value="service">Services</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Coverage Mode</label>
                            <select class="form-control form-control-sm" id="filter_coverage_mode">
                                <option value="">All Modes</option>
                                <option value="express">Express</option>
                                <option value="primary">Primary</option>
                                <option value="secondary">Secondary</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="button" class="btn btn-primary btn-block" id="applyFilters">
                                <i class="fa fa-search"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions Card -->
        <div class="card-modern">
            <div class="card-header d-flex flex-wrap gap-2">
                <button class="btn btn-success" id="addTariffBtn">
                    <i class="fa fa-plus"></i> Add New Tariff
                </button>
                <button class="btn btn-info" id="exportBtn">
                    <i class="fa fa-download"></i> Export Tariffs
                </button>
                <button class="btn btn-warning" id="importBtn">
                    <i class="fa fa-upload"></i> Import Tariffs
                </button>
                <button class="btn btn-purple" id="normalizeBtn" style="background: #7B1FA2; border-color: #7B1FA2; color: #fff;">
                    <i class="fa fa-magic"></i> Quick Drug Split
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tariffsTable" class="table table-sm table-bordered table-striped display">
                        <thead>
                            <tr>
                                <th>S/N</th>
                                <th>HMO</th>
                                <th>Type</th>
                                <th>Item</th>
                                <th>Original Price</th>
                                <th>Claims Amount</th>
                                <th>Payable Amount</th>
                                <th>Coverage Mode</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Add/Edit Tariff Modal -->
<div class="modal fade" id="tariffModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="tariffModalTitle">Add New Tariff</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="tariffForm">
                @csrf
                <input type="hidden" id="tariff_id" name="tariff_id">
                <input type="hidden" id="form_method" value="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>HMO <span class="text-danger">*</span></label>
                                <select class="form-control" id="hmo_id" name="hmo_id" required>
                                    <option value="">Select HMO</option>
                                    @foreach($hmos as $hmo)
                                        <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-danger" id="error_hmo_id"></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Item Type <span class="text-danger">*</span></label>
                                <select class="form-control" id="item_type" name="item_type" required>
                                    <option value="">Select Type</option>
                                    <option value="product">Product</option>
                                    <option value="service">Service</option>
                                </select>
                                <small class="form-text text-danger" id="error_item_type"></small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6" id="product_select_div" style="display:none;">
                            <div class="form-group">
                                <label>Product <span class="text-danger">*</span></label>
                                <select class="form-control" id="product_id" name="product_id">
                                    <option value="">Select Product</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}" data-price="{{ $product->price ? $product->price->current_sale_price : 0 }}">
                                            {{ $product->product_name }} - ₦{{ $product->price ? number_format($product->price->current_sale_price, 2) : 0 }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-danger" id="error_product_id"></small>
                            </div>
                        </div>
                        <div class="col-md-6" id="service_select_div" style="display:none;">
                            <div class="form-group">
                                <label>Service <span class="text-danger">*</span></label>
                                <select class="form-control" id="service_id" name="service_id">
                                    <option value="">Select Service</option>
                                    @foreach($services as $service)
                                        <option value="{{ $service->id }}" data-price="{{ $service->price ? $service->price->sale_price : 0 }}">
                                            {{ $service->service_name }} - ₦{{ $service->price ? number_format($service->price->sale_price, 2) : 0 }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-danger" id="error_service_id"></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Coverage Mode <span class="text-danger">*</span></label>
                                <select class="form-control" id="coverage_mode" name="coverage_mode" required>
                                    <option value="">Select Mode</option>
                                    <option value="express">Express (Auto-Approved)</option>
                                    <option value="primary">Primary (Requires Validation)</option>
                                    <option value="secondary">Secondary (Requires Validation + Auth Code)</option>
                                </select>
                                <small class="form-text text-danger" id="error_coverage_mode"></small>
                                <small class="form-text text-muted">
                                    <strong>Express:</strong> Auto-approved<br>
                                    <strong>Primary:</strong> Requires HMO executive approval<br>
                                    <strong>Secondary:</strong> Requires approval + authorization code
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Claims Amount (HMO Covers) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₦</span>
                                    </div>
                                    <input type="number" class="form-control" id="claims_amount" name="claims_amount"
                                           min="0" step="0.01" placeholder="0.00" required>
                                </div>
                                <small class="form-text text-danger" id="error_claims_amount"></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Payable Amount (Patient Pays) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₦</span>
                                    </div>
                                    <input type="number" class="form-control" id="payable_amount" name="payable_amount"
                                           min="0" step="0.01" placeholder="0.00" required>
                                </div>
                                <small class="form-text text-danger" id="error_payable_amount"></small>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <strong>Note:</strong> Claims Amount + Payable Amount should typically equal the original price,
                        but you can adjust them as needed for your HMO agreements.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveTariffBtn">
                        <i class="fa fa-save"></i> Save Tariff
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Export Tariffs Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fa fa-download"></i> Export Tariffs</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><strong>Export Scope</strong></label>
                            <select class="form-control" id="export_scope">
                                <option value="hmo">Single HMO</option>
                                <option value="scheme">By Scheme (all HMOs)</option>
                            </select>
                            <small class="form-text text-muted">Scheme export uses the first HMO as reference template</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group" id="export_hmo_div">
                            <label>Select HMO</label>
                            <select class="form-control" id="export_hmo_id">
                                <option value="">-- All HMOs --</option>
                                @foreach($hmos as $hmo)
                                    <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" id="export_scheme_div" style="display:none;">
                            <label>Select Scheme</label>
                            <select class="form-control" id="export_scheme_id">
                                <option value="">-- Select Scheme --</option>
                                @foreach($schemes as $scheme)
                                    <option value="{{ $scheme->id }}">{{ $scheme->name }} ({{ $scheme->code }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Item Type</label>
                            <select class="form-control" id="export_type">
                                <option value="">Both (Products & Services)</option>
                                <option value="product">Products Only</option>
                                <option value="service">Services Only</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Coverage Mode</label>
                            <select class="form-control" id="export_coverage_mode">
                                <option value="">All Modes</option>
                                <option value="express">Express</option>
                                <option value="primary">Primary</option>
                                <option value="secondary">Secondary</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group" id="export_prod_cat_div">
                            <label>Product Category</label>
                            <select class="form-control" id="export_product_category_id">
                                <option value="">All Categories</option>
                                @foreach($productCategories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->category_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" id="export_svc_cat_div" style="display:none;">
                            <label>Service Category</label>
                            <select class="form-control" id="export_service_category_id">
                                <option value="">All Categories</option>
                                @foreach($serviceCategories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->category_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" id="doExportBtn">
                    <i class="fa fa-download"></i> Download Excel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Import Tariffs Modal -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fa fa-upload"></i> Import Tariffs</h5>
                <button type="button" class="close" data-bs-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <!-- Tabs -->
                <ul class="nav nav-tabs" id="importTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#tab_import_hmo" role="tab">
                            <i class="fa fa-building"></i> By Single HMO
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#tab_import_scheme" role="tab">
                            <i class="fa fa-sitemap"></i> By Scheme
                        </a>
                    </li>
                </ul>
                <div class="tab-content pt-3" id="importTabContent">
                    <!-- Tab 1: Import by HMO -->
                    <div class="tab-pane fade show active" id="tab_import_hmo" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Target HMO <span class="text-danger">*</span></label>
                                    <select class="form-control" id="import_hmo_id" required>
                                        <option value="">-- Select HMO --</option>
                                        @foreach($hmos as $hmo)
                                            <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Excel/CSV File <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control-file" id="import_hmo_file" accept=".xlsx,.xls,.csv" required>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <strong>Format:</strong> Item Code | Item Name | Item Type | Category | Current Price | Claims Amount | Payable Amount | Coverage Mode<br>
                            <small>Matches items by <strong>Item Code</strong> first, then falls back to <strong>Item Name</strong>. First row should be headers.</small>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="previewHmoImportBtn">
                            <i class="fa fa-eye"></i> Preview Changes
                        </button>
                        <button type="button" class="btn btn-warning btn-sm ml-2" id="executeHmoImportBtn" style="display:none;">
                            <i class="fa fa-upload"></i> Apply Import
                        </button>
                    </div>

                    <!-- Tab 2: Import by Scheme -->
                    <div class="tab-pane fade" id="tab_import_scheme" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Target Scheme <span class="text-danger">*</span></label>
                                    <select class="form-control" id="import_scheme_id" required>
                                        <option value="">-- Select Scheme --</option>
                                        @foreach($schemes as $scheme)
                                            <option value="{{ $scheme->id }}">{{ $scheme->name }} ({{ $scheme->code }})</option>
                                        @endforeach
                                    </select>
                                    <small class="form-text text-muted" id="import_scheme_hmo_list"></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Excel/CSV File <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control-file" id="import_scheme_file" accept=".xlsx,.xls,.csv" required>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-warning">
                            <strong><i class="fa fa-exclamation-triangle"></i> Scheme Import:</strong> This will apply the <strong>exact same prices</strong> to ALL active HMOs in the selected scheme.
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="previewSchemeImportBtn">
                            <i class="fa fa-eye"></i> Preview Changes
                        </button>
                        <button type="button" class="btn btn-warning btn-sm ml-2" id="executeSchemeImportBtn" style="display:none;">
                            <i class="fa fa-upload"></i> Apply Import to All HMOs
                        </button>
                    </div>
                </div>

                <!-- Preview Table (shared) -->
                <div id="importPreviewArea" style="display:none;" class="mt-3">
                    <h6><i class="fa fa-list"></i> Import Preview <span id="previewSummary" class="text-muted"></span></h6>
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-bordered" id="previewTable">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Item</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Old Claims</th>
                                    <th>New Claims</th>
                                    <th>Old Payable</th>
                                    <th>New Payable</th>
                                    <th>Old Mode</th>
                                    <th>New Mode</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Drug Split / Normalize Modal -->
<div class="modal fade" id="normalizeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header text-white" style="background: #7B1FA2;">
                <h5 class="modal-title"><i class="fa fa-magic"></i> Quick Drug Split / Normalize Scheme</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <strong>What this does:</strong> Automatically sets tariff prices for ALL products and services under a scheme.
                    Drugs get a patient/HMO percentage split. Services can be set to 100% HMO coverage.
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><strong>Scheme</strong> <span class="text-danger">*</span></label>
                            <select class="form-control" id="normalize_scheme_id" required>
                                <option value="">-- Select Scheme --</option>
                                @foreach($schemes as $scheme)
                                    <option value="{{ $scheme->id }}">{{ $scheme->name }} ({{ $scheme->code }})</option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted" id="normalize_hmo_list"></small>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><i class="fa fa-pills"></i> Drug Split — Patient Pays (%)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="drug_patient_pct" value="10" min="0" max="100" step="1">
                                <div class="input-group-append">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <small class="form-text text-muted">HMO covers the rest. E.g. 10% patient = 90% HMO.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><i class="fa fa-stethoscope"></i> Services — HMO Covers (%)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="service_claims_pct" value="100" min="0" max="100" step="1">
                                <div class="input-group-append">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <small class="form-text text-muted">100% = full HMO coverage for services.</small>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="general_consult_express" checked>
                            <label class="custom-control-label" for="general_consult_express">
                                Set "General Consultation" to <span class="badge badge-success">EXPRESS</span> (auto-approved)
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="other_consult_secondary" checked>
                            <label class="custom-control-label" for="other_consult_secondary">
                                Set other consultations to <span class="badge badge-danger">SECONDARY</span> (needs auth code)
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn text-white" id="executeNormalizeBtn" style="background: #7B1FA2;">
                    <i class="fa fa-magic"></i> Apply Normalization
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this tariff?</p>
                <input type="hidden" id="delete_tariff_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="fa fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Feedback Alert Modal (replaces SweetAlert) -->
<div class="modal fade" id="feedbackModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" id="feedbackModalHeader">
                <h5 class="modal-title" id="feedbackModalTitle"></h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p id="feedbackModalBody"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Action Modal -->
<div class="modal fade" id="confirmActionModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="confirmActionTitle">Confirm</h5>
                <button type="button" class="close" data-bs-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p id="confirmActionBody"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirmActionBtn">Yes, proceed</button>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
    <div class="text-center text-white">
        <div class="spinner-border text-light" style="width:3rem; height:3rem;" role="status"></div>
        <p class="mt-2" id="loadingText">Please wait...</p>
    </div>
</div>

@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}"></script>
<script>
$(function() {
    const csrfToken = '{{ csrf_token() }}';

    // ═══════════════════════════════════
    // Helpers: feedback modal, confirm modal, loading overlay
    // ═══════════════════════════════════
    function showFeedback(title, message, type) {
        let headerClass = type === 'success' ? 'bg-success text-white' :
                          type === 'error' ? 'bg-danger text-white' : 'bg-info text-white';
        $('#feedbackModalHeader').attr('class', 'modal-header ' + headerClass);
        $('#feedbackModalTitle').text(title);
        $('#feedbackModalBody').html(message);
        $('#feedbackModal').modal('show');
    }

    let _confirmCallback = null;
    function showConfirm(title, message, btnText, callback) {
        $('#confirmActionTitle').text(title);
        $('#confirmActionBody').html(message);
        $('#confirmActionBtn').text(btnText || 'Yes, proceed');
        _confirmCallback = callback;
        $('#confirmActionModal').modal('show');
    }
    $('#confirmActionBtn').click(function() {
        $('#confirmActionModal').modal('hide');
        if (_confirmCallback) _confirmCallback();
    });

    function showLoading(text) {
        $('#loadingText').text(text || 'Please wait...');
        $('#loadingOverlay').css('display', 'flex');
    }
    function hideLoading() {
        $('#loadingOverlay').hide();
    }

    // ═══════════════════════════════════
    // DataTable
    // ═══════════════════════════════════
    let table = $('#tariffsTable').DataTable({
        "dom": 'Bfrtip',
        "iDisplayLength": 50,
        "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        "buttons": ['pageLength', 'copy', 'excel', 'pdf', 'print', 'colvis'],
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "{{ route('hmo-tariffs.data') }}",
            "type": "GET",
            "data": function(d) {
                d.hmo_id = $('#filter_hmo_id').val();
                d.type = $('#filter_type').val();
                d.coverage_mode = $('#filter_coverage_mode').val();
            }
        },
        "columns": [
            { "data": "DT_RowIndex", "orderable": false, "searchable": false },
            { "data": "hmo_name" },
            { "data": "item_type" },
            { "data": "item_name" },
            { "data": "original_price" },
            { "data": "claims_amount_formatted" },
            { "data": "payable_amount_formatted" },
            { "data": "coverage_badge" },
            { "data": "actions", "orderable": false, "searchable": false }
        ],
        "order": [[1, 'asc']]
    });

    $('#applyFilters').click(function() { table.ajax.reload(); });

    // ═══════════════════════════════════
    // Add / Edit Tariff
    // ═══════════════════════════════════
    $('#addTariffBtn').click(function() {
        $('#tariffModalTitle').text('Add New Tariff');
        $('#tariffForm')[0].reset();
        $('#tariff_id').val('');
        $('#form_method').val('POST');
        $('#hmo_id, #item_type, #product_id, #service_id').prop('disabled', false);
        $('.text-danger').text('');
        $('#tariffModal').modal('show');
    });

    $('#item_type').change(function() {
        let type = $(this).val();
        if (type === 'product') {
            $('#product_select_div').show();
            $('#service_select_div').hide();
            $('#service_id').val('').prop('required', false);
            $('#product_id').prop('required', true);
        } else if (type === 'service') {
            $('#service_select_div').show();
            $('#product_select_div').hide();
            $('#product_id').val('').prop('required', false);
            $('#service_id').prop('required', true);
        } else {
            $('#product_select_div, #service_select_div').hide();
            $('#product_id, #service_id').val('').prop('required', false);
        }
    });

    $('#product_id, #service_id').change(function() {
        let price = $(this).find('option:selected').data('price');
        if (price) {
            $('#payable_amount').val(price);
            $('#claims_amount').val(0);
        }
    });

    $('#tariffForm').submit(function(e) {
        e.preventDefault();
        $('.text-danger').text('');

        let tariffId = $('#tariff_id').val();
        let url = tariffId ? "{{ url('admin/hmo-tariffs') }}/" + tariffId : "{{ route('hmo-tariffs.store') }}";
        let method = tariffId ? 'PUT' : 'POST';

        $.ajax({
            url: url,
            type: method,
            data: $(this).serialize(),
            success: function(response) {
                $('#tariffModal').modal('hide');
                table.ajax.reload();
                showFeedback('Success', response.message, 'success');
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    let errors = xhr.responseJSON.errors;
                    $.each(errors, function(key, value) {
                        $('#error_' + key).text(value[0]);
                    });
                } else {
                    showFeedback('Error', xhr.responseJSON?.message || 'An error occurred', 'error');
                }
            }
        });
    });

    // Edit
    $(document).on('click', '.edit-tariff-btn', function() {
        let id = $(this).data('id');
        $.get("{{ url('admin/hmo-tariffs') }}/" + id, function(response) {
            let tariff = response.data;
            $('#tariffModalTitle').text('Edit Tariff');
            $('#tariff_id').val(tariff.id);
            $('#form_method').val('PUT');
            $('#hmo_id').val(tariff.hmo_id).prop('disabled', true);
            $('#item_type').prop('disabled', true);
            $('#product_id, #service_id').prop('disabled', true);

            if (tariff.product_id) {
                $('#item_type').val('product');
                $('#product_select_div').show();
                $('#product_id').val(tariff.product_id);
            } else {
                $('#item_type').val('service');
                $('#service_select_div').show();
                $('#service_id').val(tariff.service_id);
            }

            $('#claims_amount').val(tariff.claims_amount);
            $('#payable_amount').val(tariff.payable_amount);
            $('#coverage_mode').val(tariff.coverage_mode);
            $('.text-danger').text('');
            $('#tariffModal').modal('show');
        });
    });

    // Delete
    $(document).on('click', '.delete-tariff-btn', function() {
        $('#delete_tariff_id').val($(this).data('id'));
        $('#deleteModal').modal('show');
    });

    $('#confirmDeleteBtn').click(function() {
        let id = $('#delete_tariff_id').val();
        $.ajax({
            url: "{{ url('admin/hmo-tariffs') }}/" + id,
            type: 'DELETE',
            data: { _token: csrfToken },
            success: function(response) {
                $('#deleteModal').modal('hide');
                table.ajax.reload();
                showFeedback('Deleted', response.message, 'success');
            },
            error: function() { showFeedback('Error', 'Failed to delete tariff', 'error'); }
        });
    });

    // ═══════════════════════════════════
    // EXPORT MODAL
    // ═══════════════════════════════════
    $('#exportBtn').click(function() { $('#exportModal').modal('show'); });

    // Toggle scope
    $('#export_scope').change(function() {
        if ($(this).val() === 'scheme') {
            $('#export_hmo_div').hide();
            $('#export_scheme_div').show();
        } else {
            $('#export_hmo_div').show();
            $('#export_scheme_div').hide();
        }
    });

    // Toggle category filters based on type
    $('#export_type').change(function() {
        let type = $(this).val();
        if (type === 'service') {
            $('#export_prod_cat_div').hide();
            $('#export_svc_cat_div').show();
        } else if (type === 'product') {
            $('#export_prod_cat_div').show();
            $('#export_svc_cat_div').hide();
        } else {
            $('#export_prod_cat_div').show();
            $('#export_svc_cat_div').hide();
        }
    });

    // Download
    $('#doExportBtn').click(function() {
        let params = new URLSearchParams({
            scope: $('#export_scope').val(),
            hmo_id: $('#export_hmo_id').val() || '',
            scheme_id: $('#export_scheme_id').val() || '',
            type: $('#export_type').val() || '',
            coverage_mode: $('#export_coverage_mode').val() || '',
            product_category_id: $('#export_product_category_id').val() || '',
            service_category_id: $('#export_service_category_id').val() || '',
        });
        window.location.href = "{{ route('hmo-tariffs.export-excel') }}?" + params.toString();
        $('#exportModal').modal('hide');
    });

    // ═══════════════════════════════════
    // IMPORT MODAL
    // ═══════════════════════════════════
    $('#importBtn').click(function() {
        resetImportPreview();
        $('#importModal').modal('show');
    });

    // Load scheme HMOs when selecting scheme for import
    $('#import_scheme_id').change(function() {
        let schemeId = $(this).val();
        if (!schemeId) { $('#import_scheme_hmo_list').text(''); return; }
        $.get("{{ url('admin/hmo-tariffs/scheme-hmos') }}/" + schemeId, function(resp) {
            if (resp.data && resp.data.length) {
                $('#import_scheme_hmo_list').html('<strong>HMOs:</strong> ' + resp.data.map(h => h.name).join(', '));
            } else {
                $('#import_scheme_hmo_list').text('No active HMOs in this scheme.');
            }
        });
    });

    function resetImportPreview() {
        $('#importPreviewArea').hide();
        $('#previewTable tbody').empty();
        $('#executeHmoImportBtn, #executeSchemeImportBtn').hide();
    }

    // Preview — By HMO
    $('#previewHmoImportBtn').click(function() {
        doImportPreview('hmo', '#import_hmo_id', '#import_hmo_file', '#executeHmoImportBtn');
    });

    // Preview — By Scheme
    $('#previewSchemeImportBtn').click(function() {
        doImportPreview('scheme', '#import_scheme_id', '#import_scheme_file', '#executeSchemeImportBtn');
    });

    function doImportPreview(scope, selectId, fileId, execBtn) {
        let targetId = $(selectId).val();
        let fileInput = $(fileId)[0];

        if (!targetId) { showFeedback('Error', 'Please select a target ' + scope, 'error'); return; }
        if (!fileInput.files.length) { showFeedback('Error', 'Please select a file', 'error'); return; }

        let formData = new FormData();
        formData.append('file', fileInput.files[0]);
        formData.append('scope', scope);
        formData.append('_token', csrfToken);
        if (scope === 'hmo') formData.append('hmo_id', targetId);
        else formData.append('scheme_id', targetId);

        $(execBtn).hide();
        $('#importPreviewArea').hide();
        showLoading('Analyzing file...');

        $.ajax({
            url: "{{ route('hmo-tariffs.import-preview') }}",
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(resp) {
                hideLoading();
                renderPreview(resp);
                $(execBtn).show();
            },
            error: function(xhr) {
                hideLoading();
                showFeedback('Error', xhr.responseJSON?.message || 'Preview failed', 'error');
            }
        });
    }

    function renderPreview(resp) {
        let tbody = $('#previewTable tbody');
        tbody.empty();

        let s = resp.summary;
        $('#previewSummary').html(
            `— <strong>${resp.target}</strong> (${resp.hmo_count} HMO${resp.hmo_count > 1 ? 's' : ''}) ` +
            `| <span class="text-warning">${s.updates} updates</span> ` +
            `| <span class="text-success">${s.new} new</span> ` +
            `| <span class="text-muted">${s.skipped} skipped</span> ` +
            (resp.total_rows > 100 ? `| Showing first 100 of ${resp.total_rows}` : '')
        );

        resp.preview.forEach(function(row) {
            let rowClass = row.status === 'new' ? 'table-success' : (row.status === 'skipped' ? 'table-secondary' : '');
            let statusBadge = row.status === 'new' ? '<span class="badge badge-success">NEW</span>' :
                              row.status === 'skipped' ? '<span class="badge badge-secondary">SKIP</span>' :
                              '<span class="badge badge-warning">UPDATE</span>';

            let claimsChanged = row.old_claims !== null && row.old_claims !== row.new_claims;
            let payableChanged = row.old_payable !== null && row.old_payable !== row.new_payable;
            let modeChanged = row.old_mode !== null && row.old_mode !== row.new_mode;

            tbody.append(`<tr class="${rowClass}">
                <td>${row.name} <small class="text-muted">${row.code || ''}</small></td>
                <td>${row.type}</td>
                <td>${statusBadge}${row.reason ? ' <small class="text-danger">' + row.reason + '</small>' : ''}</td>
                <td>${row.old_claims !== null ? '₦' + Number(row.old_claims).toFixed(2) : '-'}</td>
                <td class="${claimsChanged ? 'font-weight-bold text-primary' : ''}">₦${Number(row.new_claims).toFixed(2)}</td>
                <td>${row.old_payable !== null ? '₦' + Number(row.old_payable).toFixed(2) : '-'}</td>
                <td class="${payableChanged ? 'font-weight-bold text-primary' : ''}">₦${Number(row.new_payable).toFixed(2)}</td>
                <td>${row.old_mode || '-'}</td>
                <td class="${modeChanged ? 'font-weight-bold text-primary' : ''}">${row.new_mode}</td>
            </tr>`);
        });

        $('#importPreviewArea').show();
    }

    // Execute — By HMO
    $('#executeHmoImportBtn').click(function() {
        doImportExecute('hmo', '#import_hmo_id', '#import_hmo_file');
    });

    // Execute — By Scheme
    $('#executeSchemeImportBtn').click(function() {
        doImportExecute('scheme', '#import_scheme_id', '#import_scheme_file');
    });

    function doImportExecute(scope, selectId, fileId) {
        let targetId = $(selectId).val();
        let fileInput = $(fileId)[0];

        showConfirm('Confirm Import', 'This will apply the tariff changes. Continue?', 'Yes, apply!', function() {
            let formData = new FormData();
            formData.append('file', fileInput.files[0]);
            formData.append('scope', scope);
            formData.append('_token', csrfToken);
            if (scope === 'hmo') formData.append('hmo_id', targetId);
            else formData.append('scheme_id', targetId);

            showLoading('Importing tariffs...');

            $.ajax({
                url: "{{ route('hmo-tariffs.import-excel') }}",
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(resp) {
                    hideLoading();
                    $('#importModal').modal('hide');
                    table.ajax.reload();
                    let errMsg = resp.errors && resp.errors.length ? '<br><br><strong>Warnings:</strong> ' + resp.errors.join(', ') : '';
                    showFeedback('Import Complete', resp.message + errMsg, 'success');
                },
                error: function(xhr) {
                    hideLoading();
                    showFeedback('Error', xhr.responseJSON?.message || 'Import failed', 'error');
                }
            });
        });
    }

    // ═══════════════════════════════════
    // QUICK DRUG SPLIT / NORMALIZE
    // ═══════════════════════════════════
    $('#normalizeBtn').click(function() { $('#normalizeModal').modal('show'); });

    // Load scheme HMOs for normalize modal
    $('#normalize_scheme_id').change(function() {
        let schemeId = $(this).val();
        if (!schemeId) { $('#normalize_hmo_list').text(''); return; }
        $.get("{{ url('admin/hmo-tariffs/scheme-hmos') }}/" + schemeId, function(resp) {
            if (resp.data && resp.data.length) {
                $('#normalize_hmo_list').html('<strong>Will apply to:</strong> ' + resp.data.map(h => h.name).join(', '));
            } else {
                $('#normalize_hmo_list').text('No active HMOs in this scheme.');
            }
        });
    });

    $('#executeNormalizeBtn').click(function() {
        let schemeId = $('#normalize_scheme_id').val();
        if (!schemeId) { showFeedback('Error', 'Please select a scheme', 'error'); return; }

        let drugPct = $('#drug_patient_pct').val();
        let svcPct = $('#service_claims_pct').val();

        showConfirm('Confirm Normalization',
            'This will update ALL tariffs for ALL HMOs in this scheme:<br><br>' +
            '<strong>Drugs:</strong> ' + drugPct + '% patient / ' + (100 - drugPct) + '% HMO<br>' +
            '<strong>Services:</strong> ' + svcPct + '% HMO coverage<br><br>' +
            'This cannot be easily undone. Continue?',
            'Yes, normalize!',
            function() {
                showLoading('Normalizing tariffs...');

                $.ajax({
                    url: "{{ route('hmo-tariffs.normalize') }}",
                    type: 'POST',
                    data: {
                        _token: csrfToken,
                        scheme_id: schemeId,
                        drug_patient_pct: drugPct,
                        service_claims_pct: svcPct,
                        general_consult_express: $('#general_consult_express').is(':checked') ? 1 : 0,
                        other_consult_secondary: $('#other_consult_secondary').is(':checked') ? 1 : 0,
                    },
                    success: function(resp) {
                        hideLoading();
                        $('#normalizeModal').modal('hide');
                        table.ajax.reload();
                        showFeedback('Success', resp.message, 'success');
                    },
                    error: function(xhr) {
                        hideLoading();
                        showFeedback('Error', xhr.responseJSON?.message || 'Normalization failed', 'error');
                    }
                });
            }
        );
    });
});
</script>
@endsection
