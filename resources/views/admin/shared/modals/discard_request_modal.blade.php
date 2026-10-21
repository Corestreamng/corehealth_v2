<!-- Shared Discard Request Modal -->
<div class="modal fade" id="discardRequestModal" tabindex="-1" role="dialog" aria-labelledby="discardRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white py-2 px-3">
                <h5 class="modal-title font-14" id="discardRequestModalLabel">
                    <i class="mdi mdi-delete-alert me-1"></i> Discard Request
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="discardRequestForm">
                <div class="modal-body p-3">
                    <div class="alert alert-warning py-2 px-3 mb-3 font-12">
                        <i class="mdi mdi-alert me-1"></i>
                        <strong>Warning:</strong> This action will discard the request. This cannot be undone easily.
                    </div>
                    <div class="mb-3 font-12">
                        <p class="mb-1"><strong>Service / Item:</strong> <span id="discard_service_name" class="text-primary font-weight-bold"></span></p>
                        <p class="mb-0"><strong>Request No:</strong> <span id="discard_request_no" class="badge bg-secondary font-11"></span></p>
                    </div>
                    <div class="form-group mb-2">
                        <label for="discard_reason" class="font-12 font-weight-bold">Reason for Discarding <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm font-12" id="discard_reason" name="reason" rows="3"
                                  placeholder="Please provide a reason for discarding this request (minimum 10 characters)"
                                  required minlength="10"></textarea>
                        <small class="form-text text-muted font-11">This reason will be logged for audit purposes.</small>
                    </div>
                </div>
                <div class="modal-footer py-2 px-3 bg-light">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal" data-dismiss="modal">
                        <i class="mdi mdi-close"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger" id="confirmDiscardBtn">
                        <i class="mdi mdi-delete"></i> Discard Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
