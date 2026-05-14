@extends('admin.layouts.app')
@section('title', 'Result Entry Templates (V1 WYSIWYG)')
@section('page_name', 'Hospital Setup')
@section('subpage_name', 'Result Entry Templates')

@push('styles')
<style>
    /* Custom height for the CKEditor in the Result Templates modal */
    #rtModal .ck-editor__editable {
        min-height: 500px !important;
        max-height: 70vh !important;
        overflow-y: auto !important;
    }
</style>
@endpush

@section('content')
<section class="content">
    <div class="col-12">
        <div class="card-modern">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title">
                    <i class="mdi mdi-flask-outline"></i>
                    Result Entry Templates <small class="text-muted ms-2">(V1 WYSIWYG)</small>
                </h3>
                <button class="btn btn-primary" onclick="openCreateModal()">
                    <i class="fa fa-plus"></i> New Template
                </button>
            </div>
            <div class="card-body">

                <div class="alert alert-info border-0 mb-3">
                    <div class="d-flex align-items-start gap-2">
                        <i class="mdi mdi-information-outline fs-5 mt-1"></i>
                        <div>
                            <strong>About Result Entry Templates:</strong>
                            These are reusable WYSIWYG HTML templates used in the result entry modal when entering lab or imaging investigation results.
                            Templates are organised by <em>type</em> (Lab / Imaging / Both) and <em>category</em>, and appear in the template selector inside the workbench result entry dialog.
                            Build rich table-based report layouts using the built-in editor.
                        </div>
                    </div>
                </div>

                {{-- Filters --}}
                <div class="row g-2 mb-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold mb-1 small">Filter by Type</label>
                        <select id="filterType" class="form-select form-select-sm">
                            <option value="">All Types</option>
                            <option value="lab">Lab</option>
                            <option value="imaging">Imaging</option>
                            <option value="both">Both</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold mb-1 small">Filter by Category</label>
                        <select id="filterCategory" class="form-select form-select-sm">
                            <option value="">All Categories</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold mb-1 small">Filter by Status</label>
                        <select id="filterStatus" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-sm btn-outline-secondary" onclick="resetFilters()">
                            <i class="mdi mdi-refresh"></i> Reset
                        </button>
                    </div>
                </div>

                {{-- DataTable --}}
                <div class="table-responsive">
                    <table id="rt_table" class="table table-bordered table-hover" style="width:100%">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Sort</th>
                                <th>Created By</th>
                                <th style="width:120px;">Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════ Create / Edit Modal ═══════════ --}}
<div class="modal fade" id="rtModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="rtModalTitle">
                    <i class="mdi mdi-file-document-edit-outline"></i> New Result Template
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="rtId">

                {{-- Row 1: name + type --}}
                <div class="row g-3 mb-3">
                    <div class="col-md-7">
                        <label class="form-label fw-semibold">Template Name <span class="text-danger">*</span></label>
                        <input type="text" id="rtName" class="form-control" placeholder="e.g. Full Blood Count Report" maxlength="255">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Template Type <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3 mt-1">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="rtType" id="rtTypeLab" value="lab" checked>
                                <label class="form-check-label" for="rtTypeLab">
                                    <span class="badge bg-primary">Lab</span>
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="rtType" id="rtTypeImaging" value="imaging">
                                <label class="form-check-label" for="rtTypeImaging">
                                    <span class="badge bg-warning text-dark">Imaging</span>
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="rtType" id="rtTypeBoth" value="both">
                                <label class="form-check-label" for="rtTypeBoth">
                                    <span class="badge bg-success">Both</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Row 2: category + sort_order + active --}}
                <div class="row g-3 mb-3">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Category</label>
                        <input type="text" id="rtCategory" class="form-control" placeholder="e.g. Haematology, Biochemistry, X-Ray" value="General" maxlength="100">
                        <div class="form-text">Groups templates in the result-entry selector.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Sort Order</label>
                        <input type="number" id="rtSortOrder" class="form-control" value="0" min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Status</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" id="rtActive" checked>
                            <label class="form-check-label" for="rtActive">Active (visible in result entry)</label>
                        </div>
                    </div>
                </div>

                {{-- Description --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold">Description</label>
                    <input type="text" id="rtDescription" class="form-control" placeholder="Brief note about this template's purpose (optional)" maxlength="500">
                </div>

                {{-- Content editor --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        Template Content <span class="text-danger">*</span>
                        <span class="badge bg-secondary ms-2 fw-normal" style="font-size:11px;">
                            <i class="mdi mdi-table"></i> Tables recommended
                        </span>
                    </label>
                    <div id="rtContentEditor" style="min-height:500px;"></div>
                    <textarea id="rtContent" class="form-control d-none" rows="12"></textarea>
                    <div class="form-text mt-1">
                        <i class="mdi mdi-lightbulb-outline text-warning"></i>
                        Tip: Use the <strong>Insert Table</strong> toolbar button to build structured result tables. The HTML is saved as-is and rendered in the result entry modal.
                    </div>
                </div>

            </div><!-- /.modal-body -->

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="mdi mdi-close"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="rtSaveBtn" onclick="saveTemplate()">
                    <i class="fa fa-save"></i> Save Template
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset("plugins/ckeditor/ckeditor5/ckeditor.js") }}"></script>
<script>
let rtTable;
let rtEditor = null;

$(document).ready(function () {
    rtTable = $('#rt_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('v1-result-templates.data') }}",
            data: function (d) {
                d.filter_type     = $('#filterType').val();
                d.filter_category = $('#filterCategory').val();
                d.filter_status   = $('#filterStatus').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex',   name: 'DT_RowIndex',  orderable: false, searchable: false, width: '40px' },
            { data: 'name',          name: 'name' },
            { data: 'type_badge',    name: 'template_type', searchable: false },
            { data: 'category',      name: 'category' },
            { data: 'status_badge',  name: 'is_active',    searchable: false },
            { data: 'sort_order',    name: 'sort_order',   width: '60px' },
            { data: 'creator_name',  name: 'creator_name', orderable: false, searchable: false },
            { data: 'actions',       name: 'actions',      orderable: false, searchable: false, className: 'text-center' }
        ],
        order: [[5, 'asc'], [1, 'asc']],
        pageLength: 25,
        responsive: true,
        drawCallback: function () {
            // Sync category filter options with table data
            refreshCategoryOptions();
        }
    });

    $('#filterType, #filterCategory, #filterStatus').on('change', function () {
        rtTable.ajax.reload();
    });

    // Clean up editor when modal is hidden
    $('#rtModal').on('hidden.bs.modal', function () {
        destroyEditor();
    });
});

// ─── Filters ──────────────────────────────────────────────────────────────────

function resetFilters() {
    $('#filterType, #filterCategory, #filterStatus').val('');
    rtTable.ajax.reload();
}

function refreshCategoryOptions() {
    // Pull distinct categories from rendered rows
    const seen = new Set();
    $('#rt_table tbody tr').each(function () {
        const cat = $(this).find('td:eq(3)').text().trim();
        if (cat && cat !== '—') seen.add(cat);
    });
    const current = $('#filterCategory').val();
    $('#filterCategory').find('option:gt(0)').remove();
    [...seen].sort().forEach(c => {
        $('#filterCategory').append(`<option value="${c}">${c}</option>`);
    });
    if (seen.has(current)) $('#filterCategory').val(current);
}

// ─── Modal: Open / Load ───────────────────────────────────────────────────────

function openCreateModal() {
    $('#rtId').val('');
    $('#rtName').val('');
    $('input[name="rtType"][value="lab"]').prop('checked', true);
    $('#rtCategory').val('General');
    $('#rtSortOrder').val(0);
    $('#rtActive').prop('checked', true);
    $('#rtDescription').val('');
    $('#rtModalTitle').html('<i class="mdi mdi-file-plus-outline"></i> New Result Template');

    initEditor('');
    $('#rtModal').modal('show');
}

function editTemplate(id) {
    $.get(`{{ url('v1-result-templates') }}/${id}`)
        .done(function (res) {
            if (!res.success) { toastr.error('Failed to load template.'); return; }
            const t = res.template;
            $('#rtId').val(t.id);
            $('#rtName').val(t.name);
            $(`input[name="rtType"][value="${t.template_type}"]`).prop('checked', true);
            $('#rtCategory').val(t.category || 'General');
            $('#rtSortOrder').val(t.sort_order || 0);
            $('#rtActive').prop('checked', !!t.is_active);
            $('#rtDescription').val(t.description || '');
            $('#rtModalTitle').html('<i class="mdi mdi-pencil-outline"></i> Edit Template');

            initEditor(t.content || '');
            $('#rtModal').modal('show');
        })
        .fail(function () { toastr.error('Failed to load template.'); });
}

// ─── CKEditor lifecycle ───────────────────────────────────────────────────────

function initEditor(content) {
    if (rtEditor) {
        rtEditor.destroy()
            .then(() => { rtEditor = null; createEditor(content); })
            .catch(() => { rtEditor = null; createEditor(content); });
    } else {
        // Give the modal a tick to become visible before init
        setTimeout(() => createEditor(content), 150);
    }
}

function createEditor(content) {
    const el = document.getElementById('rtContentEditor');
    if (!el) return;

    el.innerHTML = '';
    $('#rtContentEditor').removeClass('d-none');
    $('#rtContent').addClass('d-none');

    if (typeof ClassicEditor === 'undefined') {
        // Fallback to plain textarea
        $('#rtContentEditor').addClass('d-none');
        $('#rtContent').removeClass('d-none').val(content);
        return;
    }

    ClassicEditor.create(el, {
        toolbar: {
            items: [
                'undo', 'redo',
                '|', 'heading',
                '|', 'bold', 'italic', 'underline', 'strikethrough',
                '|', 'insertTable',
                '|', 'bulletedList', 'numberedList', 'outdent', 'indent',
                '|', 'blockQuote', 'horizontalLine',
                '|', 'link', 'removeFormat'
            ]
        },
        table: {
            contentToolbar: [
                'tableColumn', 'tableRow', 'mergeTableCells',
                'tableProperties', 'tableCellProperties'
            ]
        }
    })
    .then(editor => {
        rtEditor = editor;
        editor.setData(content);
    })
    .catch(err => {
        console.error('CKEditor init failed:', err);
        $('#rtContentEditor').addClass('d-none');
        $('#rtContent').removeClass('d-none').val(content);
    });
}

function destroyEditor() {
    if (rtEditor) {
        rtEditor.destroy().catch(() => {});
        rtEditor = null;
    }
}

function getEditorContent() {
    if (rtEditor) return rtEditor.getData();
    return $('#rtContent').val();
}

// ─── Save ─────────────────────────────────────────────────────────────────────

function saveTemplate() {
    const id      = $('#rtId').val();
    const name    = $('#rtName').val().trim();
    const type    = $('input[name="rtType"]:checked').val();
    const content = getEditorContent();

    if (!name) {
        toastr.warning('Template name is required.');
        $('#rtName').focus();
        return;
    }
    if (!content || content.trim() === '' || content.trim() === '<p>&nbsp;</p>') {
        toastr.warning('Template content cannot be empty.');
        return;
    }

    const data = {
        name:          name,
        template_type: type,
        description:   $('#rtDescription').val().trim(),
        content:       content,
        category:      $('#rtCategory').val().trim() || 'General',
        sort_order:    parseInt($('#rtSortOrder').val()) || 0,
        is_active:     $('#rtActive').is(':checked') ? 1 : 0
    };

    const url    = id ? `{{ url('v1-result-templates') }}/${id}` : "{{ route('v1-result-templates.store') }}";
    const method = id ? 'PUT' : 'POST';

    const $btn = $('#rtSaveBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving…');

    $.ajax({
        url:     url,
        type:    method,
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        data:    data,
        success: function (res) {
            if (res.success) {
                toastr.success(res.message || 'Template saved.');
                $('#rtModal').modal('hide');
                rtTable.ajax.reload();
            } else {
                toastr.error(res.message || 'Failed to save template.');
            }
        },
        error: function (xhr) {
            const msg = xhr.responseJSON?.message || 'An error occurred.';
            toastr.error(msg);
        },
        complete: function () {
            $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Template');
        }
    });
}

// ─── Toggle ───────────────────────────────────────────────────────────────────

function toggleTemplate(id) {
    if (!confirm('Toggle this template\'s active status?')) return;

    $.ajax({
        url:  `{{ url('v1-result-templates') }}/${id}/toggle`,
        type: 'POST',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: function (res) {
            if (res.success) {
                toastr.success(res.message);
                rtTable.ajax.reload(null, false);
            } else {
                toastr.error(res.message || 'Failed to toggle template.');
            }
        },
        error: function () { toastr.error('Failed to toggle template.'); }
    });
}

// ─── Delete ───────────────────────────────────────────────────────────────────

function deleteTemplate(id) {
    if (!confirm('Are you sure you want to permanently delete this template?\nThis action cannot be undone.')) return;

    $.ajax({
        url:  `{{ url('v1-result-templates') }}/${id}`,
        type: 'DELETE',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: function (res) {
            if (res.success) {
                toastr.success(res.message);
                rtTable.ajax.reload();
            } else {
                toastr.error(res.message || 'Failed to delete template.');
            }
        },
        error: function () { toastr.error('Failed to delete template.'); }
    });
}
</script>
@endpush
