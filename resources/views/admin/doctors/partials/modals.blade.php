{{-- Delete Confirmation Modal --}}
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: {{ $hos_color ?? '#007bff' }}; color: white;">
                <h5 class="modal-title" id="deleteConfirmModalLabel">
                    <i class="fa fa-trash-alt"></i> Confirm Deletion
                </h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
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
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
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
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
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


    <div class="modal fade" id="concludeEncounterModal" tabindex="-1" aria-labelledby="concludeEncounterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header text-white" style="background-color: {{ appsettings('hos_color', '#007bff') }};">
                    <h5 class="modal-title" id="concludeEncounterModalLabel">
                        <i class="fa fa-check-circle"></i> Conclude Encounter
                    </h5>
                    <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Encounter Summary -->
                    <div class="mb-4">
                        <h6 style="color: {{ appsettings('hos_color', '#007bff') }};"><i class="fa fa-info-circle"></i> Encounter Summary</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card-modern summary-card bg-light mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title" style="color: {{ appsettings('hos_color', '#007bff') }};"><i class="fa fa-stethoscope"></i> Diagnosis & Notes</h6>
                                        <p class="card-text" id="modal_summary_diagnosis">
                                            <span class="text-muted">None selected</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card-modern summary-card bg-light mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title" style="color: {{ appsettings('hos_color', '#007bff') }};"><i class="fa fa-flask"></i> Lab Requests</h6>
                                        <p class="card-text" id="modal_summary_labs">
                                            <span class="text-muted">None selected</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card-modern summary-card bg-light mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title" style="color: {{ appsettings('hos_color', '#007bff') }};"><i class="fa fa-x-ray"></i> Imaging Requests</h6>
                                        <p class="card-text" id="modal_summary_imaging">
                                            <span class="text-muted">None selected</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card-modern summary-card bg-light mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title" style="color: {{ appsettings('hos_color', '#007bff') }};"><i class="fa fa-pills"></i> Prescriptions</h6>
                                        <p class="card-text" id="modal_summary_prescriptions">
                                            <span class="text-muted">None selected</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card-modern summary-card bg-light mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title" style="color: {{ appsettings('hos_color', '#007bff') }};"><i class="fa fa-user-md"></i> Procedures</h6>
                                        <p class="card-text" id="modal_summary_procedures">
                                            <span class="text-muted">None selected</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card-modern summary-card bg-light mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title" style="color: {{ appsettings('hos_color', '#007bff') }};"><i class="mdi mdi-account-switch"></i> Specialist Referrals</h6>
                                        <p class="card-text" id="modal_summary_referrals">
                                            <span class="text-muted">None selected</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card-modern summary-card bg-light mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title" style="color: {{ appsettings('hos_color', '#007bff') }};"><i class="fa fa-heartbeat"></i> Care Plan / Non-Pharm</h6>
                                        <p class="card-text" id="modal_summary_care_plans">
                                            <span class="text-muted">None selected</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    {{-- Clinical Outcome Section --}}
                    <div class="mb-4">
                        <label class="form-label fw-bold" style="color: {{ appsettings('hos_color', '#007bff') }};">
                            <i class="mdi mdi-trending-up"></i> Clinical Outcome <span class="text-danger">*</span>
                        </label>
                        <select class="form-select form-select-lg border-primary-subtle" id="encounter-outcome" required style="border-width: 2px;">
                            <option value="concluded" selected>Consultation Finalized / Continuing Care</option>
                            <option value="discharged">Discharged / Stable</option>
                            <option value="improved">Improved</option>
                            <option value="unimproved">Unimproved</option>
                            <option value="referred">Referred Out</option>
                            <option value="absconded">Absconded / DAMA</option>
                            <option value="death_rip">Deceased (RIP)</option>
                            <option value="death_bid">Brought In Dead (BID)</option>
                        </select>
                        <div class="form-text text-muted">
                            <i class="mdi mdi-information-outline"></i> Select the primary outcome for this clinical session.
                        </div>
                    </div>

                    {{-- Death Notification Group (Initially Hidden) --}}
                    <div id="death-notification-fields" style="display: none; background: #fff5f5; border: 1px solid #feb2b2; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                        <h6 class="text-danger mb-3"><i class="mdi mdi-alert-circle"></i> Death Notification Info</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small">Date of Death <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="death-date" value="{{ date('Y-m-d') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small">Time of Death <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="death-time" value="{{ date('H:i') }}">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-bold small">Primary Cause of Death <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="death-cause" placeholder="Enter primary cause or find diagnosis...">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-bold small">Certified By</label>
                                <input type="text" class="form-control" id="death-certified-by" value="Dr. {{ Auth::user()->surname }} {{ Auth::user()->firstname }}" readonly>
                            </div>
                        </div>
                    </div>

                    <hr>

                    {{-- Follow-Up Scheduling Section --}}
                    <div class="mb-3">
                        <h6 style="color: {{ appsettings('hos_color', '#007bff') }};"><i class="mdi mdi-calendar-clock"></i> Schedule Follow-Up</h6>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="schedule-followup-check">
                            <label class="form-check-label" for="schedule-followup-check">Schedule a follow-up appointment for this patient</label>
                        </div>
                        <div id="followup-fields" style="display: none;">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label class="form-label fw-bold small">Follow-Up Date</label>
                                    <input type="date" class="form-control form-control-sm" id="followup-date" min="{{ date('Y-m-d', strtotime('+1 day')) }}">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label fw-bold small">Time Slot</label>
                                    <select class="form-select form-select-sm" id="followup-time-slot">
                                        <option value="">-- Select date first --</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label fw-bold small">Priority</label>
                                    <select class="form-select form-select-sm" id="followup-priority">
                                        <option value="routine">Routine</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                                <div class="col-md-8 mb-2">
                                    <label class="form-label fw-bold small">Follow-Up Notes</label>
                                    <input type="text" class="form-control form-control-sm" id="followup-notes" placeholder="e.g., Review blood test results">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="followup-prepaid">
                                        <label class="form-check-label small" for="followup-prepaid">Pre-paid (skip billing at check-in)</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i>
                        <strong>Note:</strong> Clicking "Complete Encounter" will finalize this consultation.
                        Make sure all information is correct before proceeding.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fa fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-lg text-white" style="background-color: {{ appsettings('hos_color', '#007bff') }};" onclick="finalizeEncounterFromModal()" id="modal_finalize_encounter_btn">
                        <i class="fa fa-check-circle"></i> Complete Encounter
                    </button>
                </div>
                <div id="modal_finalize_message" class="px-3 pb-3"></div>
            </div>
        </div>
    </div>

<script>
    // Update modal summary when modal is opened
    $('#concludeEncounterModal').on('show.bs.modal', function() {
        updateModalSummary();
    });

    // Finalize encounter from modal
    function finalizeEncounterFromModal() {
        if (!confirm('Are you sure you want to complete this encounter?')) {
            return;
        }

        const btn = document.getElementById('modal_finalize_encounter_btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Completing...';

        // First: schedule follow-up if opted in
        var followUpPromise = Promise.resolve();
        if ($('#schedule-followup-check').is(':checked')) {
            var fuDate = $('#followup-date').val();
            var fuSlot = $('#followup-time-slot').val();
            if (!fuDate) {
                showMessage('modal_finalize_message', 'Please select a follow-up date', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-check-circle"></i> Complete Encounter';
                return;
            }
            followUpPromise = new Promise(function(resolve, reject) {
                $.ajax({
                    url: "{{ route('encounters.schedule-followup', ['encounter' => '__EID__']) }}".replace('__EID__', encounterId),
                    method: 'POST',
                    data: {
                        appointment_date: fuDate,
                        start_time: fuSlot || null,
                        priority: $('#followup-priority').val(),
                        reason: $('#followup-notes').val(),
                        is_prepaid: $('#followup-prepaid').is(':checked') ? 1 : 0,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(res) {
                        resolve(res);
                    },
                    error: function(xhr) {
                        // Non-critical — log but continue with finalization
                        console.warn('Follow-up scheduling failed:', xhr.responseJSON?.message);
                        resolve(null);
                    }
                });
            });
        }

        followUpPromise.then(function(followUpResult) {
            $.ajax({
                url: `/encounters/${encounterId}/finalize`,
                method: 'POST',
                data: {
                    end_consultation: 0,
                    consult_admit: 0,
                    admit_note: '',
                    queue_id: typeof queueId !== 'undefined' ? queueId : null,
                    outcome: $('#encounter-outcome').val(),
                    death_record: ($('#encounter-outcome').val() && $('#encounter-outcome').val().startsWith('death')) ? {
                        date: $('#death-date').val(),
                        time: $('#death-time').val(),
                        cause: $('#death-cause').val(),
                        certified_by: {{ Auth::id() }}
                    } : null,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    var msg = response.message;
                    if (followUpResult && followUpResult.appointment) {
                        msg += ' Follow-up scheduled for ' + followUpResult.appointment.appointment_date + '.';
                    }
                    showMessage('modal_finalize_message', msg, 'success');
                    setTimeout(() => {
                        window.location.href = response.redirect;
                    }, 1500);
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || 'Error completing encounter';
                    showMessage('modal_finalize_message', message, 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa fa-check-circle"></i> Complete Encounter';
                }
            });
        });
    }

    // Toggle encounter outcome death fields
    $('#encounter-outcome').on('change', function() {
        if ($(this).val() && $(this).val().startsWith('death')) {
            $('#death-notification-fields').slideDown();
        } else {
            $('#death-notification-fields').slideUp();
        }
    });

    // Update modal summary (similar to updateSummary but targets modal elements)
    function updateModalSummary() {
        $.ajax({
            url: `/encounters/${encounterId}/summary`,
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    const data = response.data;

                    // Update diagnosis summary
                    if (data.diagnosis.saved) {
                        const notesPreview = data.diagnosis.notes ?
                            (data.diagnosis.notes.substring(0, 100) + (data.diagnosis.notes.length> 100 ? '...' : '')) :
                            'Saved';
                        $('#modal_summary_diagnosis').html(`
                            <span class="text-success"><i class="fa fa-check-circle"></i> <strong>Saved</strong></span>
                            <br><small>${notesPreview}</small>
                        `);
                    } else {
                        $('#modal_summary_diagnosis').html(`<span class="text-muted">Not saved yet</span>`);
                    }

                    // Update labs summary
                    if (data.labs && data.labs.length > 0) {
                        let html = `<span class="badge bg-success mb-2">${data.labs.length} service(s)</span><br>`;
                        html += '<ul class="small mb-0 ps-3 text-start">';
                        data.labs.forEach(lab => {
                            html += `<li><strong>${lab.name}</strong> ${lab.code ? '[' + lab.code + ']' : ''}</li>`;
                        });
                        html += '</ul>';
                        $('#modal_summary_labs').html(html);
                    } else {
                        $('#modal_summary_labs').html(`<span class="text-muted">None selected</span>`);
                    }

                    // Update imaging summary
                    if (data.imaging && data.imaging.length > 0) {
                        let html = `<span class="badge bg-success mb-2">${data.imaging.length} service(s)</span><br>`;
                        html += '<ul class="small mb-0 ps-3 text-start">';
                        data.imaging.forEach(img => {
                            html += `<li><strong>${img.name}</strong> ${img.code ? '[' + img.code + ']' : ''}</li>`;
                        });
                        html += '</ul>';
                        $('#modal_summary_imaging').html(html);
                    } else {
                        $('#modal_summary_imaging').html(`<span class="text-muted">None selected</span>`);
                    }

                    // Update prescriptions summary
                    if (data.prescriptions && data.prescriptions.length > 0) {
                        let html = `<span class="badge bg-success mb-2">${data.prescriptions.length} medication(s)</span><br>`;
                        html += '<ul class="small mb-0 ps-3 text-start">';
                        data.prescriptions.forEach(presc => {
                            html += `<li><strong>${presc.name}</strong>${presc.dose ? ' - ' + presc.dose : ''}</li>`;
                        });
                        html += '</ul>';
                        $('#modal_summary_prescriptions').html(html);
                    } else {
                        $('#modal_summary_prescriptions').html(`<span class="text-muted">None selected</span>`);
                    }

                    // Update procedures summary
                    if (data.procedures && data.procedures.length > 0) {
                        let html = `<span class="badge bg-success mb-2">${data.procedures.length} procedure(s)</span><br>`;
                        html += '<ul class="small mb-0 ps-3 text-start">';
                        data.procedures.forEach(proc => {
                            html += `<li><strong>${proc.name}</strong> ${proc.code ? '[' + proc.code + ']' : ''} <span class="badge bg-light text-dark small">${proc.priority}</span></li>`;
                        });
                        html += '</ul>';
                        $('#modal_summary_procedures').html(html);
                    } else {
                        $('#modal_summary_procedures').html(`<span class="text-muted">None selected</span>`);
                    }

                    // Update referrals summary
                    if (data.referrals && data.referrals.length > 0) {
                        let html = `<span class="badge bg-success mb-2">${data.referrals.length} referral(s)</span><br>`;
                        html += '<ul class="small mb-0 ps-3 text-start">';
                        data.referrals.forEach(ref => {
                            html += `<li><strong>${ref.target}</strong> <span class="badge bg-light text-dark small">${ref.urgency}</span></li>`;
                        });
                        html += '</ul>';
                        $('#modal_summary_referrals').html(html);
                    } else {
                        $('#modal_summary_referrals').html(`<span class="text-muted">None selected</span>`);
                    }

                    // Update care plans summary
                    if (data.care_plans && data.care_plans.length > 0) {
                        let html = `<span class="badge bg-success mb-2">${data.care_plans.length} care plan(s)</span><br>`;
                        html += '<ul class="small mb-0 ps-3 text-start">';
                        data.care_plans.forEach(cp => {
                            html += `<li><strong>${cp.category}</strong> (to ${cp.target_executor})<br><small class="text-muted">${cp.frequency} for ${cp.duration}</small></li>`;
                        });
                        html += '</ul>';
                        $('#modal_summary_care_plans').html(html);
                    } else {
                        $('#modal_summary_care_plans').html(`<span class="text-muted">None selected</span>`);
                    }
                }
            },
            error: function(xhr) {
                console.error('Error loading modal summary:', xhr);
            }
        });
    }
    // ─── Follow-Up Scheduling Toggle & Slot Loading ────────────────
    $('#schedule-followup-check').on('change', function() {
        $('#followup-fields').toggle(this.checked);
    });

    $('#followup-date').on('change', function() {
        var date = $(this).val();
        var $slot = $('#followup-time-slot');
        if (!date) {
            $slot.html('<option value="">-- Select date first --</option>');
            return;
        }
        $slot.html('<option value="">Loading...</option>');
        $.ajax({
            url: "{{ route('appointments.available-slots') }}",
            type: 'GET',
            data: { date: date, clinic_id: '{{ isset($clinic) ? $clinic->id : '' }}' },
            success: function(data) {
                var html = '<option value="">-- Select time (optional) --</option>';
                if (data.slots && data.slots.length> 0) {
                    data.slots.forEach(function(slot) {
                        if (slot.available) {
                            html += '<option value="' + slot.time + '">' + slot.time + '</option>';
                        }
                    });
                } else {
                    html += '<option value="" disabled>No slots available</option>';
                }
                $slot.html(html);
            },
            error: function() {
                $slot.html('<option value="">-- Error loading slots --</option>');
            }
        });
    });
</script>

    <style>
    .summary-card {
        border-left: 4px solid {{ appsettings('hos_color', '#007bff') }};
    }
</style>
