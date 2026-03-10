<style>
    /* Make lab/imaging result entry CKEditor taller */
    #investResModal .ck-editor__editable {
        min-height: 55vh !important;
        max-height: calc(100vh - 280px) !important;
        overflow-y: auto !important;
    }
</style>
<div class="modal fade" id="investResModal" tabindex="-1" role="dialog" aria-labelledby="investResModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="{{ route(!empty($save_route) ? $save_route : 'service-save-result') }}" method="post" enctype="multipart/form-data" id="investResForm" onsubmit="copyResTemplateToField()">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="investResModalLabel">Enter Result (<span
                            id="invest_res_service_name"></span>)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="invest_res_entry_id" id="invest_res_entry_id">
                    <input type="hidden" name="invest_res_is_edit" id="invest_res_is_edit" value="0">
                    <input type="hidden" name="invest_res_template_version" id="invest_res_template_version" value="1">
                    <textarea name="invest_res_template_submited" style="display:none;" id="invest_res_template_submited"></textarea>

                    <!-- V2 Hidden Input for structured data -->
                    <input type="hidden" name="invest_res_template_data" id="invest_res_template_data">
                    <input type="hidden" name="deleted_attachments" id="deleted_attachments">

                    <!-- V1/V2 Template Version Toggle (only shown when V2 template exists for the service) -->
                    <div id="template_version_toggle_container" class="mb-3" style="display:none;">
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small me-1">Entry Mode:</span>
                            <div class="btn-group btn-group-sm" role="group">
                                <input type="radio" class="btn-check" name="template_version_toggle" id="toggle_v1" value="1" autocomplete="off">
                                <label class="btn btn-outline-secondary" for="toggle_v1"><i class="mdi mdi-file-document-edit-outline"></i> Free Text (V1)</label>
                                <input type="radio" class="btn-check" name="template_version_toggle" id="toggle_v2" value="2" autocomplete="off">
                                <label class="btn btn-outline-secondary" for="toggle_v2"><i class="mdi mdi-form-select"></i> Structured (V2)</label>
                            </div>
                        </div>
                    </div>

                    <!-- V1 Template Selector (only shown for V1 templates) -->
                    <div id="v1_template_selector_container" class="mb-3" style="display:none;">
                        <div class="d-flex align-items-center gap-2">
                            <select class="form-select form-select-sm" id="v1_result_template_select" style="max-width: 350px;">
                                <option value="">-- Insert Template --</option>
                            </select>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="v1_insert_template_btn" disabled onclick="insertV1ResultTemplate()">
                                <i class="mdi mdi-file-import"></i> Insert
                            </button>
                        </div>
                    </div>

                    <!-- V1 Template: WYSIWYG Editor -->
                    <div id="v1_template_container">
                        <div id="invest_res_template_editor" class="ckeditor-content" style="min-height: 300px; border: 1px solid #ddd; padding: 10px;"></div>
                    </div>

                    <!-- V2 Template: Structured Form -->
                    <div id="v2_template_container" style="display: none;">
                        <div id="v2_form_fields"></div>
                    </div>

                    <!-- Attachments Section -->
                    <div class="mt-4">
                        <h6><i class="fa fa-paperclip"></i> Attachments</h6>

                        <!-- Existing Attachments -->
                        <div id="existing_attachments_container" style="display: none;" class="mb-3">
                            <label class="form-label text-muted">Existing Files:</label>
                            <div id="existing_attachments_list" class="attachment-list"></div>
                        </div>

                        <!-- New Attachments -->
                        <div class="mb-3">
                            <label class="form-label">Add New Files:</label>
                            <input type="file" class="form-control" name="result_attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            <small class="text-muted">Allowed types: PDF, Images, Word Docs (Max 10MB)</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Result</button>
                </div>
            </form>
        </div>
    </div>
</div>
