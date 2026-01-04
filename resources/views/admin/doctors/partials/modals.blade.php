{{-- Delete Confirmation Modal --}}
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: {{ $hos_color ?? '#007bff' }}; color: white;">
                <h5 class="modal-title" id="deleteConfirmModalLabel">
                    <i class="fa fa-trash-alt"></i> Confirm Deletion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">You are about to delete the following request:</p>
                <div class="alert alert-info" id="deleteItemInfo"></div>

                <div class="form-group mb-3">
                    <label for="deletionReasonSelect" class="form-label">
                        <strong>Reason for Deletion <span class="text-danger">*</span></strong>
                    </label>
                    <select class="form-control" id="deletionReasonSelect" required>
                        <option value="">-- Select a reason --</option>
                        <option value="Ordered by mistake">Ordered by mistake</option>
                        <option value="Patient declined">Patient declined</option>
                        <option value="Already done elsewhere">Already done elsewhere</option>
                        <option value="Duplicate request">Duplicate request</option>
                        <option value="Changed treatment plan">Changed treatment plan</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group mb-3" id="otherReasonGroup" style="display: none;">
                    <label for="deletionReasonOther" class="form-label">
                        <strong>Please specify:</strong>
                    </label>
                    <textarea class="form-control" id="deletionReasonOther" rows="2"
                        placeholder="Enter specific reason..."></textarea>
                </div>

                <div class="form-group mb-3">
                    <label for="deletionNotes" class="form-label">Additional Notes (Optional)</label>
                    <textarea class="form-control" id="deletionNotes" rows="3"
                        placeholder="Any additional details..."></textarea>
                </div>

                <div class="alert alert-warning">
                    <i class="fa fa-exclamation-triangle"></i>
                    <strong>Warning:</strong> This action cannot be undone. The request will be marked as deleted.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fa fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="fa fa-trash-alt"></i> Delete Request
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Delete Denied Modal --}}
<div class="modal fade" id="deleteDeniedModal" tabindex="-1" aria-labelledby="deleteDeniedModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteDeniedModalLabel">
                    <i class="fa fa-ban"></i> Deletion Not Allowed
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger mb-3">
                    <i class="fa fa-exclamation-circle fa-2x float-start me-3"></i>
                    <div id="deleteDeniedReason"></div>
                </div>

                <h6 class="mb-2"><strong>Possible reasons:</strong></h6>
                <ul class="mb-0">
                    <li>Request has already been processed (results entered or dispensed)</li>
                    <li>Request has been billed to the patient</li>
                    <li>You are not the doctor who created this request</li>
                    <li>Request is from a completed encounter</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                    <i class="fa fa-check"></i> Understood
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Edit Encounter Note Modal --}}
<div class="modal fade" id="editEncounterModal" tabindex="-1" aria-labelledby="editEncounterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background-color: {{ appsettings('hos_color', '#007bff') }}; color: white;">
                <h5 class="modal-title" id="editEncounterModalLabel">
                    <i class="mdi mdi-pencil"></i> Edit Encounter Note
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editEncounterId">

                @if (appsettings('requirediagnosis', 0))
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="editEncounterNotApplicable">
                    <label class="form-check-label" for="editEncounterNotApplicable">
                        <strong>Diagnosis Not Applicable</strong>
                    </label>
                </div>

                <div class="form-group mb-3" id="editEncounterReasonsGroup">
                    <label for="editEncounterReasons" class="form-label">
                        <strong>Select ICPC-2 Reason(s) for Encounter/Diagnosis <span class="text-danger">*</span></strong>
                    </label>
                    <select name="editEncounterReasons[]" id="editEncounterReasons" class="form-control text-lg"
                        multiple="multiple" required style="width: 100%; display:block;">
                        @foreach ($reasons_for_encounter_cat_list as $reason_cat)
                            <optgroup label="{{ $reason_cat->category }}">
                                @foreach ($reasons_for_encounter_sub_cat_list as $reason_sub_cat)
                                    @if ($reason_sub_cat->category == $reason_cat->category)
                                        <option disabled style="font-weight: bold;">
                                            {{ $reason_sub_cat->sub_category }}</option>
                                        @foreach ($reasons_for_encounter_list as $reason_item)
                                            @if ($reason_item->category == $reason_cat->category && $reason_item->sub_category == $reason_sub_cat->sub_category)
                                                <option value="{{ $reason_item->code }}-{{ $reason_item->name }}">
                                                    &emsp;{{ $reason_item->code }} {{ $reason_item->name }}
                                                </option>
                                            @endif
                                        @endforeach
                                    @endif
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>

                <div class="row mb-3" id="editEncounterCommentsGroup">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="editEncounterComment1" class="form-label">
                                <strong>Select Diagnosis Comment 1 <span class="text-danger">*</span></strong>
                            </label>
                            <select class="form-control" id="editEncounterComment1" required>
                                <option value="NA">Not Applicable</option>
                                <option value="QUERY">Query</option>
                                <option value="DIFFRENTIAL">Diffrential</option>
                                <option value="CONFIRMED">Confirmed</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="editEncounterComment2" class="form-label">
                                <strong>Select Diagnosis Comment 2 <span class="text-danger">*</span></strong>
                            </label>
                            <select class="form-control" id="editEncounterComment2" required>
                                <option value="NA">Not Applicable</option>
                                <option value="ACUTE">Acute</option>
                                <option value="CHRONIC">Chronic</option>
                                <option value="RECURRENT">Recurrent</option>
                            </select>
                        </div>
                    </div>
                </div>
                @endif

                <div class="form-group mb-3">
                    <label for="editEncounterNotes" class="form-label">
                        <strong>Clinical Notes / Diagnosis <span class="text-danger">*</span></strong>
                    </label>
                    <textarea class="form-control" id="editEncounterNotes" rows="10" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fa fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="saveEncounterEditBtn">
                    <i class="fa fa-save"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Show/hide "Other" reason text field
$('#deletionReasonSelect').on('change', function() {
    if ($(this).val() === 'Other') {
        $('#otherReasonGroup').show();
        $('#deletionReasonOther').attr('required', true);
    } else {
        $('#otherReasonGroup').hide();
        $('#deletionReasonOther').attr('required', false);
        $('#deletionReasonOther').val('');
    }
});
</script>
