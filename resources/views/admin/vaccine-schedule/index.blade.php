@extends('admin.layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                        <i class="mdi mdi-needle mr-2"></i>Vaccine Schedule Configuration
                    </h3>
                    <p class="text-muted mb-0">Manage immunization schedule templates and product mappings</p>
                </div>
            </div>

            <!-- Nav Tabs -->
            <ul class="nav nav-tabs mb-4" id="vaccineConfigTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="templates-tab" data-toggle="tab" href="#templates" role="tab">
                        <i class="mdi mdi-calendar-clock"></i> Schedule Templates
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="mappings-tab" data-toggle="tab" href="#mappings" role="tab">
                        <i class="mdi mdi-link-variant"></i> Product Mappings
                    </a>
                </li>
            </ul>

            <div class="tab-content" id="vaccineConfigTabContent">
                <!-- Schedule Templates Tab -->
                <div class="tab-pane fade show active" id="templates" role="tabpanel">
                    <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                            <h5 class="mb-0" style="font-weight: 600; color: #1a1a1a;">
                                <i class="mdi mdi-clipboard-list-outline mr-2" style="color: var(--primary-color);"></i>
                                Schedule Templates
                            </h5>
                            <div>
                                <button type="button" class="btn btn-outline-secondary btn-sm mr-2" id="btnImportTemplate">
                                    <i class="mdi mdi-upload"></i> Import
                                </button>
                                <button type="button" class="btn btn-primary btn-sm" id="btnAddTemplate">
                                    <i class="mdi mdi-plus"></i> Add Template
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <table class="table table-hover" id="templatesTable" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Country</th>
                                        <th>Vaccines</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Product Mappings Tab -->
                <div class="tab-pane fade" id="mappings" role="tabpanel">
                    <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                            <h5 class="mb-0" style="font-weight: 600; color: #1a1a1a;">
                                <i class="mdi mdi-link-variant mr-2" style="color: var(--primary-color);"></i>
                                Vaccine to Product Mappings
                            </h5>
                            <button type="button" class="btn btn-primary btn-sm" id="btnAddMapping">
                                <i class="mdi mdi-plus"></i> Add Mapping
                            </button>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">
                                <i class="mdi mdi-information-outline"></i>
                                Map generic vaccine names from schedules to actual products in your inventory.
                                This allows the system to suggest the correct product when administering vaccines.
                            </p>
                            <table class="table table-hover" id="mappingsTable" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Vaccine Name</th>
                                        <th>Product</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Template Modal -->
<div class="modal fade" id="templateModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px;">
            <div class="modal-header" style="border-bottom: 1px solid #e9ecef;">
                <h5 class="modal-title" id="templateModalTitle">Add Schedule Template</h5>
                <button type="button" class="close"  data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="templateForm">
                <div class="modal-body">
                    <input type="hidden" id="templateId" name="template_id">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label" style="font-weight: 600;">Template Name *</label>
                            <input type="text" class="form-control" id="templateName" name="name" required
                                   placeholder="e.g., Nigeria NPI Schedule" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="font-weight: 600;">Country</label>
                            <input type="text" class="form-control" id="templateCountry" name="country"
                                   placeholder="e.g., Nigeria" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600;">Description</label>
                            <textarea class="form-control" id="templateDescription" name="description" rows="2"
                                      placeholder="Brief description of this schedule template" style="border-radius: 8px;"></textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="templateIsActive" name="is_active" checked>
                                <label class="custom-control-label" for="templateIsActive">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e9ecef;">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveTemplate">
                        <i class="mdi mdi-content-save"></i> Save Template
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Template Modal (with schedule items) -->
<div class="modal fade" id="viewTemplateModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="border-radius: 12px;">
            <div class="modal-header" style="border-bottom: 1px solid #e9ecef;">
                <h5 class="modal-title">
                    <i class="mdi mdi-calendar-clock mr-2"></i>
                    <span id="viewTemplateTitle">Schedule Template</span>
                </h5>
                <button type="button" class="close"  data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <p class="text-muted mb-0" id="viewTemplateDescription"></p>
                    <div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnExportTemplate">
                            <i class="mdi mdi-download"></i> Export
                        </button>
                        <button type="button" class="btn btn-outline-info btn-sm" id="btnDuplicateTemplate">
                            <i class="mdi mdi-content-copy"></i> Duplicate
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" id="btnAddScheduleItem">
                            <i class="mdi mdi-plus"></i> Add Vaccine
                        </button>
                    </div>
                </div>

                <!-- Schedule Items by Age -->
                <div id="scheduleItemsContainer">
                    <div class="text-center py-5">
                        <i class="mdi mdi-loading mdi-spin mdi-48px text-muted"></i>
                        <p class="text-muted mt-2">Loading schedule...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #e9ecef;">
                <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Item Modal -->
<div class="modal fade" id="scheduleItemModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px;">
            <div class="modal-header" style="border-bottom: 1px solid #e9ecef;">
                <h5 class="modal-title" id="scheduleItemModalTitle">Add Schedule Item</h5>
                <button type="button" class="close"  data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="scheduleItemForm">
                <div class="modal-body">
                    <input type="hidden" id="scheduleItemId" name="item_id">
                    <input type="hidden" id="scheduleItemTemplateId" name="template_id">

                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label" style="font-weight: 600;">Vaccine Name *</label>
                            <input type="text" class="form-control" id="vaccineName" name="vaccine_name" required
                                   placeholder="e.g., BCG, OPV, Pentavalent" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="font-weight: 600;">Code</label>
                            <input type="text" class="form-control" id="vaccineCode" name="vaccine_code"
                                   placeholder="e.g., OPV-1" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="font-weight: 600;">Dose Number *</label>
                            <input type="number" class="form-control" id="doseNumber" name="dose_number" required
                                   min="0" value="1" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="font-weight: 600;">Dose Label</label>
                            <input type="text" class="form-control" id="doseLabel" name="dose_label"
                                   placeholder="e.g., Penta-1" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="font-weight: 600;">Age (Days) *</label>
                            <input type="number" class="form-control" id="ageDays" name="age_days" required
                                   min="0" value="0" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;">Age Display *</label>
                            <input type="text" class="form-control" id="ageDisplay" name="age_display" required
                                   placeholder="e.g., At Birth, 6 Weeks" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;">Sort Order</label>
                            <input type="number" class="form-control" id="sortOrder" name="sort_order"
                                   min="0" value="0" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;">Route</label>
                            <select class="form-control" id="itemRoute" name="route" style="border-radius: 8px;">
                                <option value="">Select Route</option>
                                <option value="IM">IM (Intramuscular)</option>
                                <option value="SC">SC (Subcutaneous)</option>
                                <option value="ID">ID (Intradermal)</option>
                                <option value="Oral">Oral</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;">Site</label>
                            <input type="text" class="form-control" id="itemSite" name="site"
                                   placeholder="e.g., Left Thigh, Right Arm" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600;">Notes</label>
                            <textarea class="form-control" id="itemNotes" name="notes" rows="2"
                                      placeholder="Any additional notes about this vaccine" style="border-radius: 8px;"></textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="itemIsRequired" name="is_required" checked>
                                <label class="custom-control-label" for="itemIsRequired">Required vaccine</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e9ecef;">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveScheduleItem">
                        <i class="mdi mdi-content-save"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Product Mapping Modal -->
<div class="modal fade" id="mappingModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px;">
            <div class="modal-header" style="border-bottom: 1px solid #e9ecef;">
                <h5 class="modal-title" id="mappingModalTitle">Add Product Mapping</h5>
                <button type="button" class="close"  data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="mappingForm">
                <div class="modal-body">
                    <input type="hidden" id="mappingId" name="mapping_id">

                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600;">Vaccine Name *</label>
                        <select class="form-control" id="mappingVaccineName" name="vaccine_name" required style="border-radius: 8px;">
                            <option value="">Select or type vaccine name</option>
                        </select>
                        <small class="text-muted">Select from schedule vaccines or type a custom name</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600;">Product *</label>
                        <select class="form-control" id="mappingProductId" name="product_id" required style="border-radius: 8px; width: 100%;">
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="mappingIsPrimary" name="is_primary">
                            <label class="custom-control-label" for="mappingIsPrimary">
                                Primary product for this vaccine
                            </label>
                        </div>
                        <small class="text-muted">Primary products are auto-selected when administering vaccines</small>
                    </div>
                    <div class="mb-3" id="mappingIsActiveContainer" style="display: none;">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="mappingIsActive" name="is_active" checked>
                            <label class="custom-control-label" for="mappingIsActive">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e9ecef;">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveMapping">
                        <i class="mdi mdi-content-save"></i> Save Mapping
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Template Modal -->
<div class="modal fade" id="importTemplateModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px;">
            <div class="modal-header">
                <h5 class="modal-title">Import Schedule Template</h5>
                <button type="button" class="close"  data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="importTemplateForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600;">JSON File *</label>
                        <input type="file" class="form-control" id="importFile" name="file" accept=".json" required>
                        <small class="text-muted">Upload a previously exported template JSON file</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="mdi mdi-upload"></i> Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Current template ID for viewing
    let currentTemplateId = null;

    // Initialize Templates DataTable
    const templatesTable = $('#templatesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("vaccine-schedule.templates.list") }}',
        columns: [
            { data: 'name', name: 'name' },
            { data: 'description', name: 'description', render: function(data) {
                return data ? (data.length > 50 ? data.substring(0, 50) + '...' : data) : '-';
            }},
            { data: 'country', name: 'country', render: function(data) { return data || '-'; }},
            { data: 'items_count', name: 'items_count', className: 'text-center' },
            { data: 'status_badge', name: 'status_badge', orderable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'asc']],
        language: {
            emptyTable: "No schedule templates found. Click 'Add Template' to create one."
        }
    });

    // Initialize Product Mappings DataTable
    const mappingsTable = $('#mappingsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("vaccine-schedule.mappings.list") }}',
        columns: [
            { data: 'vaccine_name', name: 'vaccine_name' },
            { data: 'product_name', name: 'product_name' },
            { data: 'status_badge', name: 'status_badge', orderable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'asc']],
        language: {
            emptyTable: "No product mappings found. Click 'Add Mapping' to create one."
        }
    });

    // Initialize Select2 for product selection
    $('#mappingProductId').select2({
        dropdownParent: $('#mappingModal'),
        placeholder: 'Search for a product...',
        minimumInputLength: 2,
        ajax: {
            url: '{{ route("vaccine-schedule.products.search") }}',
            dataType: 'json',
            delay: 300,
            data: function(params) {
                return { q: params.term };
            },
            processResults: function(data) {
                return data;
            }
        }
    });

    // Initialize Select2 for vaccine name (with tags for custom entry)
    $('#mappingVaccineName').select2({
        dropdownParent: $('#mappingModal'),
        tags: true,
        placeholder: 'Select or type vaccine name',
        ajax: {
            url: '{{ route("vaccine-schedule.vaccines.list") }}',
            dataType: 'json',
            processResults: function(data) {
                return {
                    results: data.vaccine_names.map(name => ({ id: name, text: name }))
                };
            }
        }
    });

    // Add Template
    $('#btnAddTemplate').click(function() {
        $('#templateForm')[0].reset();
        $('#templateId').val('');
        $('#templateModalTitle').text('Add Schedule Template');
        $('#templateIsActive').prop('checked', true);
        $('#templateModal').modal('show');
    });

    // Save Template
    $('#templateForm').submit(function(e) {
        e.preventDefault();
        const id = $('#templateId').val();
        const url = id
            ? '{{ url("admin/vaccine-schedule/templates") }}/' + id
            : '{{ route("vaccine-schedule.templates.store") }}';

        $.ajax({
            url: url,
            method: id ? 'PUT' : 'POST',
            data: $(this).serialize(),
            success: function(response) {
                $('#templateModal').modal('hide');
                templatesTable.ajax.reload();
                toastr.success(response.message);
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
            }
        });
    });

    // View Template
    $(document).on('click', '.btn-view-template', function() {
        currentTemplateId = $(this).data('id');
        loadTemplateDetails(currentTemplateId);
        $('#viewTemplateModal').modal('show');
    });

    function loadTemplateDetails(id) {
        $('#scheduleItemsContainer').html(`
            <div class="text-center py-5">
                <i class="mdi mdi-loading mdi-spin mdi-48px text-muted"></i>
                <p class="text-muted mt-2">Loading schedule...</p>
            </div>
        `);

        $.get('{{ url("admin/vaccine-schedule/templates") }}/' + id, function(response) {
            const template = response.template;
            $('#viewTemplateTitle').text(template.name);
            $('#viewTemplateDescription').text(template.description || '');

            // Group items by age_display
            const grouped = {};
            template.items.forEach(item => {
                if (!grouped[item.age_display]) {
                    grouped[item.age_display] = [];
                }
                grouped[item.age_display].push(item);
            });

            let html = '';
            Object.keys(grouped).forEach(ageDisplay => {
                html += `
                    <div class="card-modern mb-3" style="border-radius: 8px;">
                        <div class="card-header bg-light py-2">
                            <strong><i class="mdi mdi-clock-outline mr-1"></i>${ageDisplay}</strong>
                            <span class="badge badge-info float-right">${grouped[ageDisplay].length} vaccines</span>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Vaccine</th>
                                        <th>Dose</th>
                                        <th>Route</th>
                                        <th>Site</th>
                                        <th class="text-center">Required</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;

                grouped[ageDisplay].forEach(item => {
                    html += `
                        <tr>
                            <td><strong>${item.vaccine_name}</strong> ${item.vaccine_code ? `<small class="text-muted">(${item.vaccine_code})</small>` : ''}</td>
                            <td>${item.dose_label || ('Dose ' + item.dose_number)}</td>
                            <td>${item.route || '-'}</td>
                            <td>${item.site || '-'}</td>
                            <td class="text-center">
                                ${item.is_required ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>'}
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary btn-edit-item" data-item='${JSON.stringify(item)}'>
                                    <i class="mdi mdi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger btn-delete-item" data-id="${item.id}">
                                    <i class="mdi mdi-delete"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });

                html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
            });

            if (Object.keys(grouped).length === 0) {
                html = `
                    <div class="text-center py-5">
                        <i class="mdi mdi-needle mdi-48px text-muted"></i>
                        <p class="text-muted mt-2">No vaccines in this schedule yet.</p>
                        <button class="btn btn-primary btn-sm" id="btnAddScheduleItemEmpty">
                            <i class="mdi mdi-plus"></i> Add First Vaccine
                        </button>
                    </div>
                `;
            }

            $('#scheduleItemsContainer').html(html);
        });
    }

    // Edit Template
    $(document).on('click', '.btn-edit-template', function() {
        const id = $(this).data('id');
        $.get('{{ url("admin/vaccine-schedule/templates") }}/' + id, function(response) {
            const template = response.template;
            $('#templateId').val(template.id);
            $('#templateName').val(template.name);
            $('#templateDescription').val(template.description);
            $('#templateCountry').val(template.country);
            $('#templateIsActive').prop('checked', template.is_active);
            $('#templateModalTitle').text('Edit Schedule Template');
            $('#templateModal').modal('show');
        });
    });

    // Set Default Template
    $(document).on('click', '.btn-set-default', function() {
        const id = $(this).data('id');
        if (confirm('Set this template as the default schedule?')) {
            $.post('{{ url("admin/vaccine-schedule/templates") }}/' + id + '/set-default', {
                _token: '{{ csrf_token() }}'
            }, function(response) {
                templatesTable.ajax.reload();
                toastr.success(response.message);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
            });
        }
    });

    // Delete Template
    $(document).on('click', '.btn-delete-template', function() {
        const id = $(this).data('id');
        if (confirm('Are you sure you want to delete this template? This cannot be undone.')) {
            $.ajax({
                url: '{{ url("admin/vaccine-schedule/templates") }}/' + id,
                method: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    templatesTable.ajax.reload();
                    toastr.success(response.message);
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'An error occurred');
                }
            });
        }
    });

    // Add Schedule Item
    $('#btnAddScheduleItem, #viewTemplateModal').on('click', '#btnAddScheduleItemEmpty', function() {
        $('#scheduleItemForm')[0].reset();
        $('#scheduleItemId').val('');
        $('#scheduleItemTemplateId').val(currentTemplateId);
        $('#scheduleItemModalTitle').text('Add Vaccine to Schedule');
        $('#itemIsRequired').prop('checked', true);
        $('#scheduleItemModal').modal('show');
    });

    // Edit Schedule Item
    $(document).on('click', '.btn-edit-item', function() {
        const item = $(this).data('item');
        $('#scheduleItemId').val(item.id);
        $('#scheduleItemTemplateId').val(item.template_id);
        $('#vaccineName').val(item.vaccine_name);
        $('#vaccineCode').val(item.vaccine_code);
        $('#doseNumber').val(item.dose_number);
        $('#doseLabel').val(item.dose_label);
        $('#ageDays').val(item.age_days);
        $('#ageDisplay').val(item.age_display);
        $('#itemRoute').val(item.route);
        $('#itemSite').val(item.site);
        $('#itemNotes').val(item.notes);
        $('#sortOrder').val(item.sort_order);
        $('#itemIsRequired').prop('checked', item.is_required);
        $('#scheduleItemModalTitle').text('Edit Vaccine');
        $('#scheduleItemModal').modal('show');
    });

    // Save Schedule Item
    $('#scheduleItemForm').submit(function(e) {
        e.preventDefault();
        const id = $('#scheduleItemId').val();
        const url = id
            ? '{{ url("admin/vaccine-schedule/items") }}/' + id
            : '{{ route("vaccine-schedule.items.store") }}';

        $.ajax({
            url: url,
            method: id ? 'PUT' : 'POST',
            data: $(this).serialize() + '&_token={{ csrf_token() }}',
            success: function(response) {
                $('#scheduleItemModal').modal('hide');
                loadTemplateDetails(currentTemplateId);
                templatesTable.ajax.reload();
                toastr.success(response.message);
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
            }
        });
    });

    // Delete Schedule Item
    $(document).on('click', '.btn-delete-item', function() {
        const id = $(this).data('id');
        if (confirm('Delete this vaccine from the schedule?')) {
            $.ajax({
                url: '{{ url("admin/vaccine-schedule/items") }}/' + id,
                method: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    loadTemplateDetails(currentTemplateId);
                    templatesTable.ajax.reload();
                    toastr.success(response.message);
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'An error occurred');
                }
            });
        }
    });

    // Export Template
    $('#btnExportTemplate').click(function() {
        window.location.href = '{{ url("admin/vaccine-schedule/templates") }}/' + currentTemplateId + '/export';
    });

    // Duplicate Template
    $('#btnDuplicateTemplate').click(function() {
        if (confirm('Create a copy of this template?')) {
            $.post('{{ url("admin/vaccine-schedule/templates") }}/' + currentTemplateId + '/duplicate', {
                _token: '{{ csrf_token() }}'
            }, function(response) {
                $('#viewTemplateModal').modal('hide');
                templatesTable.ajax.reload();
                toastr.success(response.message);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
            });
        }
    });

    // Import Template
    $('#btnImportTemplate').click(function() {
        $('#importTemplateForm')[0].reset();
        $('#importTemplateModal').modal('show');
    });

    $('#importTemplateForm').submit(function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('_token', '{{ csrf_token() }}');

        $.ajax({
            url: '{{ route("vaccine-schedule.templates.import") }}',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#importTemplateModal').modal('hide');
                templatesTable.ajax.reload();
                toastr.success(response.message);
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
            }
        });
    });

    // Add Product Mapping
    $('#btnAddMapping').click(function() {
        $('#mappingForm')[0].reset();
        $('#mappingId').val('');
        $('#mappingVaccineName').val(null).trigger('change');
        $('#mappingProductId').val(null).trigger('change');
        $('#mappingIsPrimary').prop('checked', false);
        $('#mappingIsActiveContainer').hide();
        $('#mappingModalTitle').text('Add Product Mapping');
        $('#mappingModal').modal('show');
    });

    // Save Product Mapping
    $('#mappingForm').submit(function(e) {
        e.preventDefault();
        const id = $('#mappingId').val();
        const url = id
            ? '{{ url("admin/vaccine-schedule/mappings") }}/' + id
            : '{{ route("vaccine-schedule.mappings.store") }}';

        $.ajax({
            url: url,
            method: id ? 'PUT' : 'POST',
            data: $(this).serialize() + '&_token={{ csrf_token() }}',
            success: function(response) {
                $('#mappingModal').modal('hide');
                mappingsTable.ajax.reload();
                toastr.success(response.message);
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
            }
        });
    });

    // Edit Product Mapping
    $(document).on('click', '.btn-edit-mapping', function() {
        const id = $(this).data('id');
        // Would need an endpoint to get mapping details, for now we'll handle inline
        toastr.info('Edit functionality coming soon');
    });

    // Set Primary Mapping
    $(document).on('click', '.btn-set-primary-mapping', function() {
        const id = $(this).data('id');
        $.post('{{ url("admin/vaccine-schedule/mappings") }}/' + id + '/set-primary', {
            _token: '{{ csrf_token() }}'
        }, function(response) {
            mappingsTable.ajax.reload();
            toastr.success(response.message);
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'An error occurred');
        });
    });

    // Delete Product Mapping
    $(document).on('click', '.btn-delete-mapping', function() {
        const id = $(this).data('id');
        if (confirm('Delete this product mapping?')) {
            $.ajax({
                url: '{{ url("admin/vaccine-schedule/mappings") }}/' + id,
                method: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    mappingsTable.ajax.reload();
                    toastr.success(response.message);
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'An error occurred');
                }
            });
        }
    });

    // Auto-generate dose label based on vaccine name and dose number
    $('#vaccineName, #doseNumber').on('change keyup', function() {
        const name = $('#vaccineName').val();
        const dose = $('#doseNumber').val();
        if (name && dose && dose > 0) {
            const abbrev = name.substring(0, 4).toUpperCase();
            $('#doseLabel').val(abbrev + '-' + dose);
        }
    });

    // Auto-suggest age display based on age days
    $('#ageDays').on('change', function() {
        const days = parseInt($(this).val()) || 0;
        let display = '';
        if (days === 0) {
            display = 'At Birth';
        } else if (days < 7) {
            display = days + ' Days';
        } else if (days < 30) {
            display = Math.round(days / 7) + ' Week' + (Math.round(days / 7) > 1 ? 's' : '');
        } else if (days < 365) {
            display = Math.round(days / 30) + ' Month' + (Math.round(days / 30) > 1 ? 's' : '');
        } else {
            display = Math.round(days / 365) + ' Year' + (Math.round(days / 365) > 1 ? 's' : '');
        }
        $('#ageDisplay').val(display);
    });
});
</script>
@endsection
