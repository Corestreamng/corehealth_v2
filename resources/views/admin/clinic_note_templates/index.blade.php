@extends('admin.layouts.app')
@section('title', 'Clinical Note Templates')
@section('page_name', 'Hospital Setup')
@section('subpage_name', 'Clinical Note Templates')

@section('content')
<section class="content">
    <div class="col-12">
        <div class="card-modern">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title"><i class="mdi mdi-file-document-edit"></i> Clinical Note Templates</h3>
                <button class="btn btn-primary" onclick="openCreateModal()">
                    <i class="fa fa-plus"></i> New Template
                </button>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-3">
                    <i class="mdi mdi-information"></i>
                    <strong>About Templates:</strong>
                    Create reusable clinical note templates that doctors can use to quickly populate their encounter notes.
                    Templates can be assigned to a specific clinic or made global (available to all clinics).
                </div>

                {{-- Filters --}}
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Filter by Clinic</label>
                        <select id="filterClinic" class="form-select form-select-sm">
                            <option value="">All Clinics</option>
                            <option value="global">Global Templates Only</option>
                            @foreach($clinics as $clinic)
                                <option value="{{ $clinic->id }}">{{ $clinic->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Filter by Category</label>
                        <select id="filterCategory" class="form-select form-select-sm">
                            <option value="">All Categories</option>
                        </select>
                    </div>
                </div>

                {{-- DataTable --}}
                <div class="table-responsive">
                    <table id="templates_table" class="table table-bordered table-striped" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Clinic</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Sort</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Create/Edit Modal --}}
<div class="modal fade" id="templateModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="templateModalTitle">
                    <i class="mdi mdi-file-document-edit"></i> New Template
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="templateId" value="">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Template Name <span class="text-danger">*</span></label>
                        <input type="text" id="templateName" class="form-control" placeholder="e.g. General Consultation Note" maxlength="255">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Clinic</label>
                        <select id="templateClinic" class="form-select">
                            <option value="">Global (All Clinics)</option>
                            @foreach($clinics as $clinic)
                                <option value="{{ $clinic->id }}">{{ $clinic->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Category</label>
                        <input type="text" id="templateCategory" class="form-control" placeholder="e.g. General, Cardiology, Pediatric" value="General" maxlength="100">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Sort Order</label>
                        <input type="number" id="templateSortOrder" class="form-control" value="0" min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Status</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" id="templateActive" checked>
                            <label class="form-check-label" for="templateActive">Active</label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Description</label>
                    <input type="text" id="templateDescription" class="form-control" placeholder="Brief description of when to use this template" maxlength="500">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Template Content <span class="text-danger">*</span></label>
                    <div id="templateContentEditor" style="min-height: 300px;"></div>
                    <textarea id="templateContent" class="form-control d-none" rows="10"></textarea>
                </div>
                <div class="mb-3">
                    <div class="card border-info">
                        <div class="card-header bg-info bg-opacity-10 py-2 cursor-pointer" data-bs-toggle="collapse" data-bs-target="#placeholderHelperCollapse" style="cursor:pointer;">
                            <i class="mdi mdi-code-braces"></i> <strong>Available Placeholders</strong>
                            <small class="text-muted ms-2">(click to expand)</small>
                        </div>
                        <div class="collapse" id="placeholderHelperCollapse">
                            <div class="card-body py-2">
                                <p class="text-muted small mb-2">Use these placeholders in your template content. They will be auto-replaced with actual values when a doctor inserts the template.</p>
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-sm table-borderless mb-0" style="font-size:12px;">
                                            <tr><td><code>@{{patient_name}}</code></td><td>Full patient name</td></tr>
                                            <tr><td><code>@{{patient_file_no}}</code></td><td>Patient file number</td></tr>
                                            <tr><td><code>@{{patient_dob}}</code></td><td>Date of birth</td></tr>
                                            <tr><td><code>@{{patient_age}}</code></td><td>Age in years</td></tr>
                                            <tr><td><code>@{{patient_sex}}</code></td><td>Sex / Gender</td></tr>
                                            <tr><td><code>@{{patient_phone}}</code></td><td>Phone number</td></tr>
                                            <tr><td><code>@{{patient_address}}</code></td><td>Address</td></tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-sm table-borderless mb-0" style="font-size:12px;">
                                            <tr><td><code>@{{patient_blood_group}}</code></td><td>Blood group</td></tr>
                                            <tr><td><code>@{{doctor_name}}</code></td><td>Doctor's full name</td></tr>
                                            <tr><td><code>@{{clinic_name}}</code></td><td>Clinic name</td></tr>
                                            <tr><td><code>@{{date}}</code></td><td>Current date (long format)</td></tr>
                                            <tr><td><code>@{{date_short}}</code></td><td>Current date (short format)</td></tr>
                                            <tr><td><code>@{{encounter_id}}</code></td><td>Encounter ID</td></tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveTemplateBtn" onclick="saveTemplate()">
                    <i class="fa fa-save"></i> Save Template
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let templatesTable;
let templateEditorInstance = null;

$(document).ready(function() {
    // Initialize DataTable
    templatesTable = $('#templates_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('clinic-note-templates.data') }}",
            data: function(d) {
                d.clinic_id = $('#filterClinic').val();
                d.category = $('#filterCategory').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'name', name: 'name' },
            { data: 'clinic_name', name: 'clinic_name' },
            { data: 'category', name: 'category' },
            { data: 'status', name: 'status' },
            { data: 'sort_order', name: 'sort_order' },
            { data: 'creator_name', name: 'creator_name' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[5, 'asc'], [1, 'asc']],
        pageLength: 25,
        responsive: true
    });

    // Filter change handlers
    $('#filterClinic, #filterCategory').on('change', function() {
        templatesTable.ajax.reload();
    });

    // Load categories for filter
    loadCategories();
});

function loadCategories() {
    // Extract unique categories from current data
    $.get("{{ route('clinic-note-templates.data') }}", { length: -1 }, function(response) {
        if (response.data) {
            let categories = [...new Set(response.data.map(r => r.category).filter(Boolean))];
            let sel = $('#filterCategory');
            sel.find('option:gt(0)').remove();
            categories.sort().forEach(cat => {
                sel.append(`<option value="${cat}">${cat}</option>`);
            });
        }
    });
}

function openCreateModal() {
    $('#templateId').val('');
    $('#templateName').val('');
    $('#templateClinic').val('');
    $('#templateCategory').val('General');
    $('#templateSortOrder').val('0');
    $('#templateActive').prop('checked', true);
    $('#templateDescription').val('');
    $('#templateModalTitle').html('<i class="mdi mdi-file-document-edit"></i> New Template');

    // Initialize/reset editor
    initTemplateEditor('');

    $('#templateModal').modal('show');
}

function editTemplate(id) {
    $.get(`{{ url('clinic-note-templates') }}/${id}`, function(response) {
        if (response.success) {
            let t = response.template;
            $('#templateId').val(t.id);
            $('#templateName').val(t.name);
            $('#templateClinic').val(t.clinic_id || '');
            $('#templateCategory').val(t.category || 'General');
            $('#templateSortOrder').val(t.sort_order || 0);
            $('#templateActive').prop('checked', t.is_active);
            $('#templateDescription').val(t.description || '');
            $('#templateModalTitle').html('<i class="mdi mdi-pencil"></i> Edit Template');

            initTemplateEditor(t.content || '');

            $('#templateModal').modal('show');
        } else {
            alert('Failed to load template');
        }
    }).fail(function() {
        alert('Failed to load template');
    });
}

function initTemplateEditor(content) {
    // Destroy existing instance
    if (templateEditorInstance) {
        templateEditorInstance.destroy()
            .then(() => createEditor(content))
            .catch(() => createEditor(content));
    } else {
        // Wait for modal to be fully shown
        setTimeout(() => createEditor(content), 200);
    }
}

function createEditor(content) {
    const el = document.querySelector('#templateContentEditor');
    if (!el) return;

    // Clear the element
    el.innerHTML = '';

    if (typeof ClassicEditor !== 'undefined') {
        ClassicEditor
            .create(el, {
                toolbar: {
                    items: [
                        'undo', 'redo',
                        '|', 'heading',
                        '|', 'bold', 'italic', 'underline', 'strikethrough',
                        '|', 'link', 'insertTable',
                        '|', 'bulletedList', 'numberedList', 'outdent', 'indent',
                        '|', 'blockQuote', 'horizontalLine'
                    ]
                }
            })
            .then(editor => {
                templateEditorInstance = editor;
                editor.setData(content);
            })
            .catch(error => {
                console.error('Editor init error:', error);
                // Fallback to textarea
                $('#templateContentEditor').addClass('d-none');
                $('#templateContent').removeClass('d-none').val(content);
            });
    } else {
        // No CKEditor, use textarea
        $('#templateContentEditor').addClass('d-none');
        $('#templateContent').removeClass('d-none').val(content);
    }
}

function saveTemplate() {
    let id = $('#templateId').val();
    let content = '';

    if (templateEditorInstance) {
        content = templateEditorInstance.getData();
    } else {
        content = $('#templateContent').val();
    }

    let data = {
        clinic_id: $('#templateClinic').val() || null,
        name: $('#templateName').val(),
        description: $('#templateDescription').val(),
        content: content,
        category: $('#templateCategory').val() || 'General',
        sort_order: parseInt($('#templateSortOrder').val()) || 0,
        is_active: $('#templateActive').is(':checked') ? 1 : 0
    };

    if (!data.name) {
        alert('Template name is required.');
        return;
    }
    if (!data.content || data.content.trim() === '' || data.content === '<p>&nbsp;</p>') {
        alert('Template content is required.');
        return;
    }

    let url = id ? `{{ url('clinic-note-templates') }}/${id}` : "{{ route('clinic-note-templates.store') }}";
    let method = id ? 'PUT' : 'POST';

    $('#saveTemplateBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

    $.ajax({
        url: url,
        type: method,
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        data: data,
        success: function(response) {
            if (response.success) {
                $('#templateModal').modal('hide');
                templatesTable.ajax.reload();
                loadCategories();
                alert(response.message);
            } else {
                alert(response.message || 'Failed to save template');
            }
        },
        error: function(xhr) {
            alert(xhr.responseJSON?.message || 'Failed to save template');
        },
        complete: function() {
            $('#saveTemplateBtn').prop('disabled', false).html('<i class="fa fa-save"></i> Save Template');
        }
    });
}

function toggleTemplate(id, activate) {
    if (!confirm(`Are you sure you want to ${activate ? 'activate' : 'deactivate'} this template?`)) return;

    $.ajax({
        url: `{{ url('clinic-note-templates') }}/${id}/toggle`,
        type: 'POST',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: function(response) {
            if (response.success) {
                templatesTable.ajax.reload();
            } else {
                alert(response.message || 'Failed to toggle template');
            }
        },
        error: function(xhr) {
            alert(xhr.responseJSON?.message || 'Failed to toggle template');
        }
    });
}

function deleteTemplate(id) {
    if (!confirm('Are you sure you want to delete this template? This action cannot be undone.')) return;

    $.ajax({
        url: `{{ url('clinic-note-templates') }}/${id}`,
        type: 'DELETE',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: function(response) {
            if (response.success) {
                templatesTable.ajax.reload();
                loadCategories();
                alert(response.message);
            } else {
                alert(response.message || 'Failed to delete template');
            }
        },
        error: function(xhr) {
            alert(xhr.responseJSON?.message || 'Failed to delete template');
        }
    });
}
</script>
@endpush
