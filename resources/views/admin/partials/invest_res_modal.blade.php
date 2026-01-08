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
