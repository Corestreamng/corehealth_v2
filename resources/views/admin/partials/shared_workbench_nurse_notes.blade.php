{{-- Reusable Shared Workbench Nurse Notes Partial --}}
@php
    $prefix = $prefix ?? 'nursing';
    $formId = $formId ?? ($prefix . '-note-form');
    $editorId = $editorId ?? ($prefix . '-note-editor');
    $statusId = $statusId ?? ($prefix . '-note-autosave-status');
    $tableId = $tableId ?? ($prefix . '-notes-table');
    $refreshCallback = $refreshCallback ?? 'loadNotesHistory(currentPatient)';
@endphp

<div class="notes-container p-3">
    <!-- Sub-tabs for Notes -->
    <ul class="nav nav-tabs mb-3" id="notes-sub-tabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="notes-add-tab" data-toggle="tab" href="#notes-add" role="tab">
                <i class="mdi mdi-plus-circle"></i> Add Note
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="notes-history-tab-link" data-toggle="tab" href="#notes-history" role="tab">
                <i class="mdi mdi-history"></i> History
            </a>
        </li>
    </ul>

    <div class="tab-content" id="notes-sub-content">
        <!-- Add Note Sub-tab -->
        <div class="tab-pane fade show active" id="notes-add" role="tabpanel">
            <div class="card-modern shadow-sm border rounded-3">
                <div class="card-header bg-primary text-white py-2 px-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 text-white"><i class="mdi mdi-note-text"></i> Add Note</h6>
                </div>
                <div class="card-body">
                    <form id="{{ $formId }}">
                        <div class="form-group mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                                <label for="{{ $editorId }}" class="form-label mb-0 fw-medium"><i class="mdi mdi-text"></i> Note Content *</label>
                                @include('admin.partials.speech_dictation', [
                                    'targetId' => $editorId,
                                    'editorType' => 'ckeditor',
                                    'showLangSelect' => true
                                ])
                            </div>
                            <style>
                                /* Make notes CKEditor taller */
                                #{{ $formId }} .ck-editor__editable,
                                #editNoteModal .ck-editor__editable {
                                    min-height: 55vh !important;
                                    max-height: calc(100vh - 250px) !important;
                                    overflow-y: auto !important;
                                }
                            </style>
                            <div id="{{ $editorId }}"></div>
                        </div>
                        <div class="form-actions text-right mt-3 d-flex justify-content-end align-items-center gap-3">
                            <div id="{{ $statusId }}" class="small text-muted" style="min-height: 1.3em;"></div>
                            <button type="submit" class="btn btn-primary rounded-pill px-4">
                                <i class="mdi mdi-content-save"></i> Save Note
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Notes History Sub-tab -->
        <div class="tab-pane fade has-timeline" id="notes-history" role="tabpanel">
            <div class="card-modern shadow-sm border rounded-3">
                <div class="card-header py-2 px-3 d-flex justify-content-between align-items-center bg-light">
                    <h6 class="mb-0 text-primary fw-semibold"><i class="mdi mdi-history"></i> Notes History</h6>
                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="{{ $refreshCallback }}">
                        <i class="mdi mdi-refresh"></i> Refresh
                    </button>
                </div>
                <div class="card-body p-3 bg-light-alt" id="{{ $prefix }}-notes-history-container">
                    @if($prefix === 'maternity')
                        <div class="notes-timeline" id="maternity-notes-timeline">
                            {{-- Dynamically loaded in maternity workbench --}}
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-borderless w-100" id="{{ $tableId }}">
                                <thead class="d-none">
                                    <tr>
                                        <th>Info</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
