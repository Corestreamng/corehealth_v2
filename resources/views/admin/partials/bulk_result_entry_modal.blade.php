{{-- Bulk Result Entry Modal --}}
<style>
    /* Make bulk result entry CKEditor taller */
    #bulkResultEntryModal .bulk-editor-container .ck-editor__editable {
        min-height: 55vh !important;
        max-height: calc(100vh - 280px) !important;
        overflow-y: auto !important;
    }
</style>
<div class="modal fade" id="bulkResultEntryModal" tabindex="-1" role="dialog" aria-labelledby="bulkResultEntryModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkResultEntryModalLabel">
                    <i class="mdi mdi-file-multiple"></i> Bulk Result Entry
                    <span class="badge bg-info ms-2" id="bulkResultProgress"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeBulkResultModal()"></button>
            </div>
            <div class="modal-body p-0">
                {{-- Tabs for each request --}}
                <ul class="nav nav-tabs px-3 pt-2" id="bulkResultTabs" role="tablist" style="flex-wrap: nowrap; overflow-x: auto;"></ul>
                {{-- Tab content --}}
                <div class="tab-content p-3" id="bulkResultTabContent"></div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <div>
                    <span class="text-muted" id="bulkResultStatusText"></span>
                </div>
                <div>
                    <button type="button" class="btn btn-dark" onclick="closeBulkResultModal()">
                        <i class="mdi mdi-close"></i> Close
                    </button>
                    <button type="button" class="btn btn-success" id="bulkSaveAndNextBtn" onclick="bulkSaveAndNext()">
                        <i class="mdi mdi-content-save"></i> Save &amp; Next
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="bulkNextBtn" onclick="bulkGoNext()">
                        Next <i class="mdi mdi-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
