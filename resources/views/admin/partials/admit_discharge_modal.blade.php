<!-- Unified Admission/Discharge Modal -->
<div class="modal fade" id="admitDischargeModal" tabindex="-1" aria-labelledby="admitDischargeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: {{ appsettings('hos_color', '#007bff') }}; color: white;">
                <h5 class="modal-title" id="admitDischargeModalLabel">
                    <i class="fa fa-bed" id="modal_icon"></i> <span id="modal_title_text">Admit Patient</span>
                </h5>
                <button type="button" data-bs-dismiss="modal" class="btn- close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i> <strong>Patient:</strong> <span id="admit_modal_patient_name">{{ isset($patient) ? userfullname($patient->user_id) : 'Selected Patient' }}</span>
                </div>

                <form id="admitDischargeForm">
                    @csrf
                    <input type="hidden" name="action" id="modal_action" value="admit">
                    <input type="hidden" name="patient_id" id="admit_modal_patient_id" value="{{ isset($patient) ? $patient->id : '' }}">
                    <input type="hidden" name="encounter_id" id="admit_modal_encounter_id" value="{{ isset($encounter) ? $encounter->id : '' }}">
                    <input type="hidden" name="doctor_id" id="admit_modal_doctor_id" value="{{ auth()->user()->id }}">
                    <input type="hidden" name="admission_request_id" id="admit_modal_admission_request_id" value="{{ isset($admission_request) ? $admission_request->id : '' }}">

                    <!-- Admission Section -->
                    <div id="admission_section">
                        <div class="form-group mb-3">
                            <label for="admission_reason_category"><strong>Admission Reason Category</strong> <span class="text-danger">*</span></label>
                            <select class="form-control" name="admission_reason" id="admission_reason_category">
                                <option value="">-- Select Reason --</option>
                                <option value="Acute illness or injury">Acute Illness or Injury</option>
                                <option value="Chronic condition management">Chronic Condition Management</option>
                                <option value="Post-surgical care">Post-Surgical Care</option>
                                <option value="Diagnostic workup">Diagnostic Workup</option>
                                <option value="Maternal care">Maternal Care (Obstetrics)</option>
                                <option value="Neonatal care">Neonatal Care</option>
                                <option value="Mental health crisis">Mental Health Crisis</option>
                                <option value="Palliative or end-of-life care">Palliative or End-of-Life Care</option>
                                <option value="Rehabilitation">Rehabilitation</option>
                                <option value="Observation">Observation</option>
                                <option value="Social or safeguarding reasons">Social or Safeguarding Reasons</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label for="admit_note"><strong>Detailed Admission Notes</strong> <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="note" id="admit_note" rows="4"
                                      placeholder="Enter detailed clinical notes, diagnosis, and special care instructions..."></textarea>
                        </div>

                        <!-- Ward Availability Section -->
                        <div class="mb-3">
                            <label class="mb-2"><strong><i class="fa fa-hospital"></i> Ward Availability</strong></label>
                            <div id="ward_availability_container" class="border rounded p-2" style="max-height: 280px; overflow-y: auto;">
                                <div class="text-center text-muted py-3" id="ward_loading">
                                    <i class="fa fa-spinner fa-spin"></i> Loading ward availability...
                                </div>
                            </div>
                            <input type="hidden" name="preferred_ward_id" id="preferred_ward_id" value="">
                            <small class="text-muted">
                                <i class="fa fa-info-circle"></i> Ward preference is optional. Bed will be assigned by nursing staff.
                            </small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="admit_priority"><strong>Priority</strong></label>
                                    <select class="form-control" name="priority" id="admit_priority">
                                        <option value="routine">Routine</option>
                                        <option value="urgent">Urgent</option>
                                        <option value="emergency">Emergency</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Discharge Section -->
                    <div id="discharge_section" style="display: none;">
                        <div class="form-group mb-3">
                            <label for="discharge_reason_category"><strong>Discharge Reason</strong> <span class="text-danger">*</span></label>
                            <select class="form-control" name="discharge_reason" id="discharge_reason_category">
                                <option value="">-- Select Reason --</option>
                                <option value="Discharged to home">Discharged to Home (Recovered)</option>
                                <option value="Discharged improved">Discharged Improved (Ongoing Care at Home)</option>
                                <option value="Discharged against medical advice">Discharged Against Medical Advice (AMA)</option>
                                <option value="Transfer to another facility">Transfer to Another Facility</option>
                                <option value="Transfer to higher level of care">Transfer to Higher Level of Care</option>
                                <option value="Absconded">Absconded (Left Without Notice)</option>
                                <option value="Deceased">Deceased</option>
                                <option value="Discharged for financial reasons">Discharged for Financial Reasons</option>
                                <option value="Discharged for end-of-life care">Discharged for End-of-Life Care (Hospice)</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        {{-- Death Notification for Discharge (Initially Hidden) --}}
                        <div id="discharge-death-fields" style="display: none; background: #fff5f5; border: 1px solid #feb2b2; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                            <h6 class="text-danger mb-3"><i class="mdi mdi-alert-circle"></i> Death Notification Info</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold small">Date of Death <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="death_date" id="discharge-death-date" value="{{ date('Y-m-d') }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold small">Time of Death <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" name="death_time" id="discharge-death-time" value="{{ date('H:i') }}">
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label fw-bold small">Primary Cause of Death <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="death_cause" id="discharge-death-cause" placeholder="Enter primary cause...">
                                </div>
                            </div>
                            <div class="alert alert-info py-2 mb-0" style="font-size: 0.75rem;">
                                <i class="mdi mdi-information-outline"></i> Recording this death will notify nursing staff for Last Office and morgue admission.
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label for="discharge_note"><strong>Discharge Summary / Death Note</strong> <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="discharge_note" id="discharge_note" rows="5"
                                      placeholder="Enter discharge summary, condition at discharge, medications, follow-up instructions..."></textarea>
                        </div>

                        <div class="form-group mb-3">
                            <label for="discharge_followup"><strong>Follow-up Instructions</strong></label>
                            <textarea class="form-control" name="followup_instructions" id="discharge_followup" rows="2"
                                      placeholder="Next appointment, medication refills, warning signs to watch for..."></textarea>
                        </div>
                    </div>

                    <div class="alert alert-warning" id="modal_warning">
                        <i class="fa fa-exclamation-triangle"></i> <strong>Note:</strong> <span id="warning_text">This will create an admission request.</span>
                    </div>
                </form>

                <!-- Checklist & Bed Assignment Section (Initially Hidden) -->
                <div id="modal_checklist_section" style="display: none;">
                    <h6 id="modal-checklist-title"><i class="mdi mdi-checkbox-marked-outline"></i> Checklist</h6>
                    <div class="d-flex align-items-center mb-3">
                        <span class="me-2 fw-bold"><i class="mdi mdi-checkbox-multiple-marked"></i> Progress:</span>
                        <div class="progress flex-grow-1" style="height: 15px;">
                            <div class="progress-bar bg-success" id="modal-checklist-progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                        <span class="fw-bold ms-2" id="modal-checklist-progress-text">0%</span>
                    </div>
                    <div id="modal-checklist-items" class="mb-3 p-2 border rounded" style="max-height: 250px; overflow-y: auto; background-color: #f8f9fa;">
                        <!-- Checklist items load here -->
                    </div>

                    <!-- Bed Assignment Section (For Admission) -->
                    <div id="modal_bed_assignment_section" style="display: none;">
                        <hr>
                        <h6><i class="fa fa-bed"></i> Assign Bed</h6>
                        <div class="mb-2">
                            <select id="modal-ward-filter" class="form-control form-control-sm" onchange="modalLoadAvailableBeds()">
                                <option value="">-- All Wards --</option>
                            </select>
                        </div>
                        <div id="modal-available-beds-grid" class="d-flex flex-wrap gap-2 mb-3" style="max-height: 200px; overflow-y: auto;">
                            <!-- Beds load here -->
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer" id="modal_footer_actions">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fa fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary btn-lg" onclick="submitAdmitDischarge()" id="modal_submit_btn">
                    <i class="fa fa-bed" id="btn_icon"></i> <span id="btn_text">Submit Admission</span>
                </button>
            </div>
            
            <!-- Checklist Action Buttons (Initially Hidden) -->
            <div class="modal-footer" id="modal_checklist_actions" style="display: none;">
                <button type="button" class="btn btn-secondary" onclick="location.reload()">
                    <i class="fa fa-handshake"></i> Hand off to Nurse
                </button>
                <button type="button" class="btn btn-primary" id="modal_assign_bed_btn" onclick="modalConfirmBedAssignment()" style="display:none;" disabled>
                    <i class="fa fa-check"></i> Complete & Assign Bed
                </button>
                <button type="button" class="btn btn-warning" id="modal_complete_discharge_btn" onclick="modalConfirmDischarge()" style="display:none;" disabled>
                    <i class="fa fa-check"></i> Complete Discharge
                </button>
            </div>
            <div id="modal_message" class="px-3 pb-3"></div>
        </div>
    </div>
</div>

<script>
// Helper function to show messages within the modal
function admitDischargeShowMessage(elementId, message, type = 'success') {
    const element = document.getElementById(elementId);
    if (!element) return;
    const typeMap = { success: 'alert-success', error: 'alert-danger', warning: 'alert-warning', info: 'alert-info' };
    const alertClass = typeMap[type] || 'alert-info';
    element.innerHTML = `<div class="alert ${alertClass} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    element.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    setTimeout(() => { element.innerHTML = ''; }, 5000);
}

// ─── Ward Availability for Admission Modal ───
function loadWardAvailability() {
    const container = document.getElementById('ward_availability_container');
    container.innerHTML = '<div class="text-center text-muted py-3"><i class="fa fa-spinner fa-spin"></i> Loading ward availability...</div>';

    $.ajax({
        url: '{{ route("ward-availability") }}',
        method: 'GET',
        success: function(wards) {
            if (!wards || wards.length === 0) {
                container.innerHTML = '<div class="text-center text-muted py-3"><i class="fa fa-info-circle"></i> No active wards configured.</div>';
                return;
            }

            let html = '<div class="list-group list-group-flush">';

            // "No preference" option (default selected)
            html += `
                <label class="list-group-item list-group-item-action d-flex align-items-center p-2 ward-option" style="cursor:pointer;">
                    <input type="radio" name="ward_radio" value="" checked class="me-2 ward-radio" onchange="selectWardPreference('')">
                    <div class="flex-grow-1">
                        <span class="fw-bold text-muted"><i class="fa fa-globe"></i> No ward preference</span>
                        <small class="d-block text-muted">Bed will be assigned by nursing staff</small>
                    </div>
                </label>`;

            wards.forEach(function(ward) {
                const pct = ward.occupancy_pct;
                let barColor = pct >= 90 ? '#dc3545' : (pct >= 70 ? '#ffc107' : '#28a745');
                let textColor = pct >= 90 ? 'text-danger' : (pct >= 70 ? 'text-warning' : 'text-success');
                let typeIcons = {
                    'general': 'fa-bed', 'icu': 'fa-heartbeat', 'pediatric': 'fa-child',
                    'maternity': 'fa-baby', 'emergency': 'fa-ambulance', 'psychiatric': 'fa-brain',
                    'isolation': 'fa-shield-alt', 'recovery': 'fa-procedures', 'private': 'fa-star',
                    'other': 'fa-hospital'
                };
                let icon = typeIcons[ward.type] || 'fa-hospital';
                let isDisabled = ward.available_beds === 0;

                html += `
                    <label class="list-group-item list-group-item-action d-flex align-items-center p-2 ward-option ${isDisabled ? 'opacity-50' : ''}" style="cursor:${isDisabled ? 'not-allowed' : 'pointer'};">
                        <input type="radio" name="ward_radio" value="${ward.id}" class="me-2 ward-radio"
                               ${isDisabled ? 'disabled' : ''}
                               onchange="selectWardPreference('${ward.id}')">
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">
                                    <i class="fa ${icon} me-1"></i> ${ward.name}
                                    <span class="badge bg-secondary ms-1" style="font-size:0.7em;">${ward.type_label}</span>
                                </span>
                                <span class="${textColor} fw-bold" style="font-size: 0.85em;">
                                    ${ward.available_beds}/${ward.total_beds} available
                                </span>
                            </div>
                            <div class="progress mt-1" style="height: 6px;">
                                <div class="progress-bar" role="progressbar"
                                     style="width: ${pct}%; background-color: ${barColor};"
                                     aria-valuenow="${pct}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            ${ward.floor || ward.building ? `<small class="text-muted">${[ward.building, ward.floor].filter(Boolean).join(', ')}</small>` : ''}
                        </div>
                    </label>`;
            });

            html += '</div>';
            container.innerHTML = html;
        },
        error: function() {
            container.innerHTML = '<div class="text-center text-danger py-3"><i class="fa fa-exclamation-triangle"></i> Failed to load ward availability.</div>';
        }
    });
}

function selectWardPreference(wardId) {
    document.getElementById('preferred_ward_id').value = wardId;
}

// Open modal for admission request
function openAdmitModal(patientId = null, patientName = null, encounterId = null) {
    // Reset form
    document.getElementById('admitDischargeForm').reset();
    document.getElementById('modal_action').value = 'admit';
    document.getElementById('preferred_ward_id').value = '';
    
    if (patientId) document.getElementById('admit_modal_patient_id').value = patientId;
    if (patientName) document.getElementById('admit_modal_patient_name').textContent = patientName;
    if (encounterId) document.getElementById('admit_modal_encounter_id').value = encounterId;

    // Update UI for admission request
    document.getElementById('modal_title_text').textContent = 'Request Patient Admission';
    document.getElementById('modal_icon').className = 'fa fa-bed';
    document.getElementById('btn_text').textContent = 'Submit Admission Request';
    document.getElementById('btn_icon').className = 'fa fa-bed';
    document.getElementById('modal_submit_btn').className = 'btn btn-info btn-lg text-white';
    document.getElementById('warning_text').textContent = 'This will create an admission request. Nursing staff will complete the admission checklist and assign a bed.';

    // Show/hide sections
    document.getElementById('admission_section').style.display = 'block';
    document.getElementById('discharge_section').style.display = 'none';
    document.getElementById('modal_checklist_section').style.display = 'none';
    document.getElementById('modal_footer_actions').style.display = 'flex';
    document.getElementById('modal_checklist_actions').style.display = 'none';

    // Load live ward availability
    loadWardAvailability();

    $('#admitDischargeModal').modal('show');
}

// Open modal for discharge request
function openDischargeModal(patientId = null, patientName = null, admissionRequestId = null) {
    // Reset form
    document.getElementById('admitDischargeForm').reset();
    document.getElementById('modal_action').value = 'discharge';
    
    if (patientId) document.getElementById('admit_modal_patient_id').value = patientId;
    if (patientName) document.getElementById('admit_modal_patient_name').textContent = patientName;
    if (admissionRequestId) document.getElementById('admit_modal_admission_request_id').value = admissionRequestId;

    // Update UI for discharge request
    document.getElementById('modal_title_text').textContent = 'Request Patient Discharge';
    document.getElementById('modal_icon').className = 'fa fa-sign-out-alt';
    document.getElementById('btn_text').textContent = 'Submit Discharge Request';
    document.getElementById('btn_icon').className = 'fa fa-sign-out-alt';
    document.getElementById('modal_submit_btn').className = 'btn btn-warning btn-lg';
    document.getElementById('warning_text').textContent = 'This will create a discharge request. Nursing staff will complete the discharge checklist before releasing the bed.';

    // Show/hide sections
    document.getElementById('admission_section').style.display = 'none';
    document.getElementById('discharge_section').style.display = 'block';
    document.getElementById('modal_checklist_section').style.display = 'none';
    document.getElementById('modal_footer_actions').style.display = 'flex';
    document.getElementById('modal_checklist_actions').style.display = 'none';
    $('#discharge-death-fields').hide(); // Reset death fields

    $('#admitDischargeModal').modal('show');
}

// Toggle discharge death fields
document.addEventListener('DOMContentLoaded', function() {
    $('#discharge_reason_category').on('change', function() {
        if ($(this).val() === 'Deceased') {
            $('#discharge-death-fields').slideDown();
            $('#discharge_note').attr('placeholder', 'Enter death summary, clinical observations, and notification details...');
        } else {
            $('#discharge-death-fields').slideUp();
            $('#discharge_note').attr('placeholder', 'Enter discharge summary, condition at discharge, medications, follow-up instructions...');
        }
    });
});

// Submit admission or discharge
function submitAdmitDischarge() {
    const form = document.getElementById('admitDischargeForm');
    const btn = document.getElementById('modal_submit_btn');
    const action = document.getElementById('modal_action').value;

    // Validate based on action
    if (action === 'admit') {
        if (!document.getElementById('admission_reason_category').value) {
            admitDischargeShowMessage('modal_message', 'Please select an admission reason category', 'error');
            return;
        }
        if (!document.getElementById('admit_note').value.trim()) {
            admitDischargeShowMessage('modal_message', 'Please enter detailed admission notes', 'error');
            return;
        }
    } else if (action === 'discharge') {
        if (!document.getElementById('discharge_reason_category').value) {
            admitDischargeShowMessage('modal_message', 'Please select a discharge reason', 'error');
            return;
        }
        if (document.getElementById('discharge_reason_category').value === 'Deceased') {
            if (!document.getElementById('discharge-death-date').value || !document.getElementById('discharge-death-time').value || !document.getElementById('discharge-death-cause').value.trim()) {
                admitDischargeShowMessage('modal_message', 'Please complete all death notification fields', 'error');
                return;
            }
        }
        if (!document.getElementById('discharge_note').value.trim()) {
            admitDischargeShowMessage('modal_message', 'Please enter discharge summary', 'error');
            return;
        }
    }

    btn.disabled = true;
    btn.innerHTML = '<i class=\"fa fa-spinner fa-spin\"></i> Processing...';

    const formData = new FormData(form);

    // Determine endpoint
    const url = action === 'admit'
        ? '{{ route('admission-requests.store') }}'
        : '/discharge-patient-api/' + formData.get('admission_request_id');

    $.ajax({
        url: url,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            const isDoctorFullAdmission = {{ appsettings('doctor_full_admission') ? 'true' : 'false' }};
            const isDoctorFullDischarge = {{ appsettings('doctor_full_discharge') ? 'true' : 'false' }};

            if (action === 'admit' && isDoctorFullAdmission) {
                const admissionReqId = response.data && response.data.id ? response.data.id : null;
                if (admissionReqId) {
                    admitDischargeShowMessage('modal_message', 'Admission request created! Proceeding to checklist...', 'success');
                    modalTransitionToChecklist(admissionReqId, 'admission');
                    return;
                }
            } else if (action === 'discharge' && isDoctorFullDischarge) {
                const admissionReqId = formData.get('admission_request_id');
                if (admissionReqId) {
                    admitDischargeShowMessage('modal_message', 'Discharge request created! Proceeding to checklist...', 'success');
                    modalTransitionToChecklist(admissionReqId, 'discharge');
                    return;
                }
            }

            const successMsg = action === 'admit'
                ? 'Admission request submitted! Nursing staff will process the admission checklist.'
                : 'Discharge request submitted! Nursing staff will complete the discharge checklist before releasing the bed.';
            admitDischargeShowMessage('modal_message', response.message || successMsg, 'success');
            setTimeout(() => {
                location.reload();
            }, 1500);
        },
        error: function(xhr) {
            const message = xhr.responseJSON?.message || 'Error processing request';
            admitDischargeShowMessage('modal_message', message, 'error');
            btn.disabled = false;
            const originalText = action === 'admit' ? 'Submit Admission Request' : 'Submit Discharge Request';
            const originalIcon = action === 'admit' ? 'fa-bed' : 'fa-sign-out-alt';
            btn.innerHTML = `<i class=\"fa ${originalIcon}\"></i> ${originalText}`;
        }
    });
}

// ─── Checklist & Bed Assignment Logic ───
let modalCurrentAdmissionId = null;
let modalCurrentChecklistType = null;
let modalSelectedBedId = null;

function modalTransitionToChecklist(admissionRequestId, type) {
    modalCurrentAdmissionId = admissionRequestId;
    modalCurrentChecklistType = type;
    
    // Hide forms, show checklist
    document.getElementById('admitDischargeForm').style.display = 'none';
    document.getElementById('modal_footer_actions').style.display = 'none';
    
    document.getElementById('modal_checklist_section').style.display = 'block';
    document.getElementById('modal_checklist_actions').style.display = 'flex';
    
    document.getElementById('modal-checklist-title').innerHTML = type === 'admission' 
        ? '<i class="mdi mdi-checkbox-marked-outline"></i> Admission Checklist'
        : '<i class="mdi mdi-checkbox-marked-outline"></i> Discharge Checklist';
        
    if (type === 'admission') {
        document.getElementById('modal_assign_bed_btn').style.display = 'block';
        document.getElementById('modal_complete_discharge_btn').style.display = 'none';
    } else {
        document.getElementById('modal_assign_bed_btn').style.display = 'none';
        document.getElementById('modal_complete_discharge_btn').style.display = 'block';
    }

    modalLoadChecklist(admissionRequestId, type);
}

function modalLoadChecklist(id, type) {
    const url = type === 'admission' 
        ? '/nursing-workbench/admission/' + id + '/checklist'
        : '/nursing-workbench/admission/' + id + '/discharge-checklist';

    document.getElementById('modal-checklist-items').innerHTML = '<div class="text-center py-3"><i class="fa fa-spinner fa-spin"></i> Loading checklist...</div>';

    $.get(url, function(checklist) {
        if (!checklist || !checklist.items || checklist.items.length === 0) {
            document.getElementById('modal-checklist-items').innerHTML = '<div class="text-center py-3 text-muted">No checklist items required.</div>';
            modalUpdateProgress(100);
        } else {
            modalRenderChecklist(checklist.items);
            modalUpdateProgress(checklist.progress || 0);
        }
    }).fail(function() {
        document.getElementById('modal-checklist-items').innerHTML = '<div class="text-danger p-2">Failed to load checklist.</div>';
    });
}

function modalRenderChecklist(items) {
    let html = '';
    items.forEach(function(item) {
        const isDone = item.completed || item.waived;
        const statusClass = item.completed ? 'bg-success-light border-success' : (item.waived ? 'bg-warning-light border-warning' : 'bg-white');
        
        html += `<div class="d-flex align-items-center justify-content-between p-2 mb-2 border rounded ${statusClass}">`;
        html += `<div class="d-flex align-items-center">`;
        html += `<input type="checkbox" class="me-3 mt-0" style="width: 1.5em; height: 1.5em; cursor: pointer;" 
                  onchange="modalToggleChecklistItem(${item.id}, this.checked)" ${isDone ? 'checked disabled' : ''}>`;
        html += `<div><strong>${item.name}</strong>`;
        if (item.description) html += `<br><small class="text-muted">${item.description}</small>`;
        if (item.waived) html += `<br><small class="text-warning"><i class="mdi mdi-alert"></i> Waived</small>`;
        html += `</div></div>`;
        
        if (!isDone && item.is_waivable) {
            html += `<button type="button" class="btn btn-sm btn-outline-warning" onclick="modalWaiveChecklistItem(${item.id})">Waive</button>`;
        }
        html += `</div>`;
    });
    document.getElementById('modal-checklist-items').innerHTML = html;
}

function modalToggleChecklistItem(itemId, checked) {
    const url = modalCurrentChecklistType === 'admission' 
        ? '/nursing-workbench/admission-checklist/item/' + itemId + '/complete'
        : '/nursing-workbench/discharge-checklist/item/' + itemId + '/complete';

    $.post(url, { _token: '{{ csrf_token() }}', completed: checked }, function(response) {
        if (response.success) {
            modalUpdateProgress(response.progress);
            modalLoadChecklist(modalCurrentAdmissionId, modalCurrentChecklistType);
        }
    });
}

function modalWaiveChecklistItem(itemId) {
    const reason = prompt("Enter reason for waiving:");
    if (reason === null) return;
    
    const url = modalCurrentChecklistType === 'admission'
        ? '/nursing-workbench/admission-checklist/item/' + itemId + '/waive'
        : '/nursing-workbench/discharge-checklist/item/' + itemId + '/waive';

    $.post(url, { _token: '{{ csrf_token() }}', reason: reason }, function(response) {
        if (response.success) {
            modalUpdateProgress(response.progress);
            modalLoadChecklist(modalCurrentAdmissionId, modalCurrentChecklistType);
        }
    });
}

function modalUpdateProgress(progress) {
    $('#modal-checklist-progress-bar').css('width', progress + '%');
    $('#modal-checklist-progress-text').text(progress + '%');
    
    if (progress >= 100) {
        if (modalCurrentChecklistType === 'admission') {
            document.getElementById('modal_bed_assignment_section').style.display = 'block';
            modalPopulateWardFilter();
            modalLoadAvailableBeds();
        } else {
            document.getElementById('modal_complete_discharge_btn').disabled = false;
        }
    }
}

function modalPopulateWardFilter() {
    $.get('{{ route("ward-availability") }}', function(wards) {
        const select = document.getElementById('modal-ward-filter');
        select.innerHTML = '<option value="">-- All Wards --</option>';
        if (wards && wards.length) {
            wards.forEach(w => {
                select.innerHTML += `<option value="${w.id}">${w.name} (${w.type_label})</option>`;
            });
        }
        const preferred = document.getElementById('preferred_ward_id').value;
        if (preferred) {
            select.value = preferred;
        }
    });
}

function modalLoadAvailableBeds() {
    const wardId = document.getElementById('modal-ward-filter').value;
    let url = '/nursing-workbench/ward-dashboard/available-beds';
    if (wardId) url += '?ward_id=' + wardId;

    document.getElementById('modal-available-beds-grid').innerHTML = '<div class="text-center py-2"><i class="fa fa-spinner fa-spin"></i> Loading beds...</div>';

    $.get(url, function(beds) {
        let html = '';
        if (beds.length === 0) {
            html = '<div class="w-100 text-center py-2 text-muted">No available beds found.</div>';
        } else {
            beds.forEach(bed => {
                html += `
                    <div class="p-2 border rounded modal-bed-item" style="cursor:pointer; width: 120px; text-align: center;" 
                         data-id="${bed.id}" onclick="modalSelectBed(this, ${bed.id})">
                        <i class="mdi mdi-bed-empty mdi-24px text-success"></i><br>
                        <strong>${bed.name}</strong><br>
                        <small class="text-muted" style="font-size:0.7em;">${bed.ward_name}</small>
                    </div>`;
            });
        }
        document.getElementById('modal-available-beds-grid').innerHTML = html;
        modalSelectedBedId = null;
        document.getElementById('modal_assign_bed_btn').disabled = true;
    });
}

function modalSelectBed(el, bedId) {
    $('.modal-bed-item').removeClass('border-primary bg-primary text-white').addClass('border');
    $('.modal-bed-item i').removeClass('text-white').addClass('text-success');
    
    $(el).removeClass('border').addClass('border-primary bg-primary text-white');
    $(el).find('i').removeClass('text-success').addClass('text-white');
    
    modalSelectedBedId = bedId;
    document.getElementById('modal_assign_bed_btn').disabled = false;
}

function modalConfirmBedAssignment() {
    if (!modalSelectedBedId) return;
    
    const btn = document.getElementById('modal_assign_bed_btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Assigning...';

    $.post('/nursing-workbench/admission/' + modalCurrentAdmissionId + '/assign-bed', {
        _token: '{{ csrf_token() }}',
        bed_id: modalSelectedBedId
    }, function(response) {
        if (response.success) {
            admitDischargeShowMessage('modal_message', 'Patient fully admitted and bed assigned!', 'success');
            setTimeout(() => { location.reload(); }, 1500);
        } else {
            admitDischargeShowMessage('modal_message', 'Failed to assign bed.', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-check"></i> Complete & Assign Bed';
        }
    });
}

function modalConfirmDischarge() {
    const btn = document.getElementById('modal_complete_discharge_btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Discharging...';

    $.post('/nursing-workbench/admission/' + modalCurrentAdmissionId + '/complete-discharge', {
        _token: '{{ csrf_token() }}'
    }, function(response) {
        if (response.success) {
            admitDischargeShowMessage('modal_message', 'Patient fully discharged and bed released!', 'success');
            setTimeout(() => { location.reload(); }, 1500);
        } else {
            admitDischargeShowMessage('modal_message', 'Failed to complete discharge.', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-check"></i> Complete Discharge';
        }
    });
}
</script>
