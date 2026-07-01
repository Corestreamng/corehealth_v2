<div class="modal fade" id="procedureOutcomeModal" tabindex="-1" role="dialog" aria-labelledby="procedureOutcomeModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="procedureOutcomeModalLabel">
                    <i class="fa fa-notes-medical mr-2 text-primary"></i>Document Procedure Outcome
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="procedureOutcomeAlert" class="alert alert-warning" style="display:none;">
                    <i class="fa fa-info-circle mr-1"></i>
                    <strong>Standard Procedure Detected:</strong> This quick documentation form is ideal for simple, unbilled, or externally performed procedures.
                    For full tracking (including team members, patient consent, and detailed billing), please use the
                    <a href="#" id="procedureOutcomeShowLink" target="_blank" class="alert-link text-decoration-underline">Procedure Show Page</a>.
                </div>

                <form id="procedureOutcomeForm">
                    <input type="hidden" id="outcome_procedure_id" name="procedure_id">
                    
                    <div class="form-group">
                        <label for="procedure_outcome">Outcome <span class="text-danger">*</span></label>
                        <select class="form-control" id="procedure_outcome" name="outcome" required>
                            <option value="">-- Select Outcome --</option>
                            <option value="successful">Successful</option>
                            <option value="complications">Complications</option>
                            <option value="aborted">Aborted</option>
                            <option value="converted">Converted</option>
                        </select>
                    </div>

                    <div class="form-group mt-3">
                        <label for="procedure_outcome_notes">Post-Op / Outcome Notes</label>
                        <textarea class="form-control" id="procedure_outcome_notes" name="outcome_notes" rows="4" placeholder="Enter any post-operative notes or details about the outcome..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-save-procedure-outcome">
                    <i class="fa fa-save mr-1"></i>Save Outcome
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function openProcedureOutcomeModal(procedureId, serviceName, isFreeForm) {
        $('#outcome_procedure_id').val(procedureId);
        $('#procedure_outcome').val('');
        $('#procedure_outcome_notes').val('');
        
        if (isFreeForm) {
            $('#procedureOutcomeAlert').hide();
        } else {
            $('#procedureOutcomeAlert').show();
            // Provide a link to the full procedure show page
            var showUrl = '{{ url("patient-procedures") }}/' + procedureId;
            $('#procedureOutcomeShowLink').attr('href', showUrl);
        }
        
        $('#procedureOutcomeModal').modal('show');
    }

    $(document).ready(function() {
        $('#btn-save-procedure-outcome').on('click', function() {
            var procedureId = $('#outcome_procedure_id').val();
            var outcome = $('#procedure_outcome').val();
            var notes = $('#procedure_outcome_notes').val();

            if (!outcome) {
                Swal.fire('Error', 'Please select an outcome.', 'error');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i>Saving...');

            $.ajax({
                url: '/patient-procedures/' + procedureId + '/outcome',
                type: 'PUT',
                data: {
                    _token: '{{ csrf_token() }}',
                    outcome: outcome,
                    outcome_notes: notes
                },
                success: function(response) {
                    $btn.prop('disabled', false).html('<i class="fa fa-save mr-1"></i>Save Outcome');
                    if (response.success) {
                        $('#procedureOutcomeModal').modal('hide');
                        Swal.fire({
                            toast: true, position: 'top-end', icon: 'success',
                            title: 'Outcome documented successfully.',
                            showConfirmButton: false, timer: 3000
                        });
                        
                        // Reload data tables if they exist
                        if ($.fn.DataTable.isDataTable('#procedures_history_list')) {
                            $('#procedures_history_list').DataTable().ajax.reload(null, false);
                        }
                        if ($.fn.DataTable.isDataTable('#procedure_history_list')) {
                            $('#procedure_history_list').DataTable().ajax.reload(null, false);
                        }
                    } else {
                        Swal.fire('Error', response.message || 'Failed to save outcome.', 'error');
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).html('<i class="fa fa-save mr-1"></i>Save Outcome');
                    Swal.fire('Error', 'An error occurred while saving. Please try again.', 'error');
                }
            });
        });
    });
</script>
@endpush
