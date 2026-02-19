{{-- Emergency Intake Modal v2 - WHO/ESI compliant triage --}}
{{-- Usage: @include('admin.partials.emergency-intake-modal') --}}

<!-- Emergency Intake Modal -->
<div class="modal fade" id="emergencyIntakeModal" tabindex="-1" aria-labelledby="emergencyIntakeModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white py-2">
                <h5 class="modal-title d-flex align-items-center gap-2" id="emergencyIntakeModalLabel">
                    <i class="mdi mdi-ambulance mdi-24px"></i>
                    <span>Emergency / Walk-In Intake</span>
                    <span class="badge bg-dark ms-2" id="emergency-timer" style="font-size:0.75rem; font-family:monospace;">00:00</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Step Progress Bar -->
                <div class="emergency-steps-bar d-flex border-bottom">
                    <div class="emergency-step active flex-fill text-center py-2" data-step="1">
                        <small class="fw-bold"><i class="mdi mdi-account"></i> 1. Patient</small>
                    </div>
                    <div class="emergency-step flex-fill text-center py-2" data-step="2">
                        <small class="fw-bold"><i class="mdi mdi-clipboard-pulse"></i> 2. Triage</small>
                    </div>
                    <div class="emergency-step flex-fill text-center py-2" data-step="3">
                        <small class="fw-bold"><i class="mdi mdi-directions"></i> 3. Disposition</small>
                    </div>
                </div>

                <form id="emergencyIntakeForm">
                    @csrf

                    {{-- ========== STEP 1: Patient Identification (Enhanced) ========== --}}
                    <div class="emergency-panel" id="emergency-step-1">
                        <div class="p-3">
                            {{-- Search existing --}}
                            <div class="mb-3">
                                <label class="form-label fw-bold"><i class="mdi mdi-magnify"></i> Find Existing Patient</label>
                                <input type="text" class="form-control" id="emergency-patient-search"
                                       placeholder="Search by name, file number, or phone..." autocomplete="off">
                                <div id="emergency-patient-results" class="list-group mt-1" style="max-height: 200px; overflow-y: auto; display: none;"></div>
                            </div>

                            {{-- Selected patient display --}}
                            <div id="emergency-selected-patient" class="alert alert-success d-flex align-items-center mb-3" style="display: none !important;">
                                <div class="flex-grow-1">
                                    <strong id="emergency-patient-name"></strong>
                                    <small class="d-block text-muted">
                                        File: <span id="emergency-patient-fileno"></span> |
                                        Phone: <span id="emergency-patient-phone"></span> |
                                        <span id="emergency-patient-hmo"></span>
                                    </small>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="emergency-clear-patient">
                                    <i class="mdi mdi-close"></i>
                                </button>
                            </div>
                            <input type="hidden" name="patient_id" id="emergency-patient-id">

                            <hr class="my-2">
                            <div class="text-center mb-2"><small class="text-muted">— OR —</small></div>

                            {{-- Quick Register Toggle --}}
                            <div class="form-check mb-2">
                                <input type="checkbox" class="form-check-input" id="emergency-new-patient-toggle" name="is_new_patient" value="1">
                                <label class="form-check-label fw-bold" for="emergency-new-patient-toggle">
                                    <i class="mdi mdi-account-plus"></i> Register New Patient (Quick)
                                </label>
                            </div>

                            {{-- New Patient Fields --}}
                            <div id="emergency-new-patient-fields" style="display: none;">
                                {{-- Identity Mode --}}
                                <div class="emi-identity-mode mb-3">
                                    <div class="btn-group btn-group-sm w-100" role="group">
                                        <input type="radio" class="btn-check" name="identity_mode" id="emi-mode-known" value="known" checked>
                                        <label class="btn btn-outline-secondary" for="emi-mode-known"><i class="mdi mdi-account-check"></i> Known Patient</label>
                                        <input type="radio" class="btn-check" name="identity_mode" id="emi-mode-unknown" value="unknown">
                                        <label class="btn btn-outline-warning" for="emi-mode-unknown"><i class="mdi mdi-account-question"></i> Unidentified / Unknown</label>
                                    </div>
                                </div>

                                {{-- Known patient name fields --}}
                                <div id="emi-known-fields">
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <label class="form-label">Surname <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" name="surname" id="emergency-surname">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" name="firstname" id="emergency-firstname">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Other Name</label>
                                            <input type="text" class="form-control form-control-sm" name="othername" id="emergency-othername">
                                        </div>
                                    </div>
                                </div>
                                {{-- Unidentified patient --}}
                                <div id="emi-unknown-fields" style="display: none;">
                                    <div class="alert alert-warning py-2 mb-2">
                                        <small><i class="mdi mdi-information"></i> Patient will be registered as <strong>"Unknown Patient [auto-ID]"</strong>. Identity can be updated later.</small>
                                    </div>
                                    <input type="hidden" name="is_unidentified" id="emergency-is-unidentified" value="0">
                                    <div class="mb-2">
                                        <label class="form-label">Distinguishing Features</label>
                                        <input type="text" class="form-control form-control-sm" name="distinguishing_features" id="emergency-features"
                                               placeholder="Scars, tattoos, clothing description, etc.">
                                    </div>
                                </div>

                                {{-- Shared fields --}}
                                <div class="row g-2 mt-1">
                                    <div class="col-md-3">
                                        <label class="form-label">Gender <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm" name="gender" id="emergency-gender">
                                            <option value="">Select</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                            <option value="Others">Others</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3" id="emi-dob-group">
                                        <label class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control form-control-sm" name="dob" id="emergency-dob">
                                    </div>
                                    <div class="col-md-3" id="emi-age-group">
                                        <label class="form-label">Approx Age</label>
                                        <select class="form-select form-select-sm" name="approx_age" id="emergency-approx-age">
                                            <option value="">Select range</option>
                                            <option value="neonate">Neonate (0-28 days)</option>
                                            <option value="infant">Infant (1-12 months)</option>
                                            <option value="child_1_5">Child (1-5 yrs)</option>
                                            <option value="child_6_12">Child (6-12 yrs)</option>
                                            <option value="adolescent">Adolescent (13-17 yrs)</option>
                                            <option value="adult_18_30">Adult (18-30 yrs)</option>
                                            <option value="adult_31_50">Adult (31-50 yrs)</option>
                                            <option value="adult_51_65">Adult (51-65 yrs)</option>
                                            <option value="elderly">Elderly (65+ yrs)</option>
                                        </select>
                                        <small class="text-muted">Used when DOB unknown</small>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Phone</label>
                                        <input type="text" class="form-control form-control-sm" name="phone_no" id="emergency-phone">
                                    </div>
                                </div>

                                {{-- Arrival Info --}}
                                <div class="emi-section-card mt-3">
                                    <label class="form-label fw-bold mb-2"><i class="mdi mdi-truck-fast"></i> Arrival Information</label>
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <label class="form-label">Mode of Arrival</label>
                                            <select class="form-select form-select-sm" name="arrival_mode" id="emergency-arrival-mode">
                                                <option value="walk_in">Walk-In</option>
                                                <option value="ambulance">Ambulance</option>
                                                <option value="police">Police / Security</option>
                                                <option value="referral">Referral</option>
                                                <option value="brought_in">Brought by Relative</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Brought By (Name)</label>
                                            <input type="text" class="form-control form-control-sm" name="brought_by_name" id="emergency-brought-by-name"
                                                   placeholder="Name of escort/relative">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Brought By (Phone)</label>
                                            <input type="text" class="form-control form-control-sm" name="brought_by_phone" id="emergency-brought-by-phone"
                                                   placeholder="Phone number">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer border-top py-2">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-danger btn-sm" id="emergency-step1-next">
                                Next: Triage <i class="mdi mdi-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    {{-- ========== STEP 2: Triage Assessment (Enhanced with vitals + GCS) ========== --}}
                    <div class="emergency-panel" id="emergency-step-2" style="display: none;">
                        <div class="p-3">
                            {{-- ESI Level with decision-support hints --}}
                            <div class="mb-3">
                                <label class="form-label fw-bold">ESI Triage Level <span class="text-danger">*</span></label>
                                <div class="d-flex flex-wrap gap-2" id="emergency-esi-buttons">
                                    <button type="button" class="btn btn-outline-danger esi-btn flex-fill" data-esi="1"
                                            data-hint="Immediate life-saving intervention? Intubation, surgical airway, IV push meds, emergency procedure?">
                                        <strong>1</strong><br><small>Resuscitation</small>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger esi-btn flex-fill" data-esi="2"
                                            data-hint="High risk situation? Confused, lethargic, disoriented? Severe pain/distress (Pain ≥ 8/10)?">
                                        <strong>2</strong><br><small>Emergent</small>
                                    </button>
                                    <button type="button" class="btn btn-outline-warning esi-btn flex-fill" data-esi="3"
                                            data-hint="Needs 2+ resources (labs, imaging, IV fluids, specialty consult)? Vitals may be outside normal range.">
                                        <strong>3</strong><br><small>Urgent</small>
                                    </button>
                                    <button type="button" class="btn btn-outline-info esi-btn flex-fill" data-esi="4"
                                            data-hint="Needs only 1 resource (e.g., one X-ray OR one lab test OR simple procedure). Vitals normal.">
                                        <strong>4</strong><br><small>Less Urgent</small>
                                    </button>
                                    <button type="button" class="btn btn-outline-success esi-btn flex-fill" data-esi="5"
                                            data-hint="No resources needed. Simple exam, prescription refill, minor complaint. Stable vitals.">
                                        <strong>5</strong><br><small>Non-Urgent</small>
                                    </button>
                                </div>
                                <div id="esi-hint-box" class="alert alert-light border mt-2 py-2 px-3" style="display:none;">
                                    <small><i class="mdi mdi-lightbulb-on text-warning"></i> <span id="esi-hint-text"></span></small>
                                </div>
                                <input type="hidden" name="esi_level" id="emergency-esi-level">
                            </div>

                            {{-- Chief Complaint --}}
                            <div class="mb-3">
                                <label class="form-label fw-bold">Chief Complaint <span class="text-danger">*</span></label>
                                <textarea class="form-control form-control-sm" name="chief_complaint" id="emergency-chief-complaint"
                                          rows="2" placeholder="Describe the patient's primary complaint..." maxlength="500"></textarea>
                            </div>

                            {{-- Quick Vitals (collapsible) --}}
                            <div class="emi-section-card mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2 emi-collapse-header" data-bs-toggle="collapse" data-bs-target="#emi-vitals-panel" role="button">
                                    <label class="form-label fw-bold mb-0"><i class="mdi mdi-heart-pulse text-danger"></i> Quick Vitals <small class="text-muted fw-normal">(recommended)</small></label>
                                    <i class="mdi mdi-chevron-down emi-collapse-icon"></i>
                                </div>
                                <div class="collapse" id="emi-vitals-panel">
                                    <div class="row g-2">
                                        <div class="col-md-4 col-6">
                                            <label class="form-label"><i class="mdi mdi-heart text-danger"></i> HR</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" class="form-control" name="vital_hr" id="emi-hr" placeholder="72" min="20" max="250">
                                                <span class="input-group-text">bpm</span>
                                            </div>
                                        </div>
                                        <div class="col-md-4 col-6">
                                            <label class="form-label"><i class="mdi mdi-heart-pulse text-danger"></i> BP</label>
                                            <div class="d-flex gap-1">
                                                <input type="number" class="form-control form-control-sm" name="vital_bp_sys" id="emi-bp-sys" placeholder="120" min="40" max="300" style="width:48%">
                                                <span class="align-self-center">/</span>
                                                <input type="number" class="form-control form-control-sm" name="vital_bp_dia" id="emi-bp-dia" placeholder="80" min="20" max="200" style="width:48%">
                                            </div>
                                        </div>
                                        <div class="col-md-4 col-6">
                                            <label class="form-label"><i class="mdi mdi-percent text-primary"></i> SpO2</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" class="form-control" name="vital_spo2" id="emi-spo2" placeholder="98" min="0" max="100">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                        <div class="col-md-4 col-6">
                                            <label class="form-label"><i class="mdi mdi-thermometer text-warning"></i> Temp</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" step="0.1" class="form-control" name="vital_temp" id="emi-temp" placeholder="36.5" min="25" max="45">
                                                <span class="input-group-text">°C</span>
                                            </div>
                                        </div>
                                        <div class="col-md-4 col-6">
                                            <label class="form-label"><i class="mdi mdi-lungs text-primary"></i> RR</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" class="form-control" name="vital_rr" id="emi-rr" placeholder="16" min="4" max="60">
                                                <span class="input-group-text">/min</span>
                                            </div>
                                        </div>
                                        <div class="col-md-4 col-6">
                                            <label class="form-label"><i class="mdi mdi-water text-info"></i> Blood Sugar</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" step="0.1" class="form-control" name="vital_bs" id="emi-bs" placeholder="100">
                                                <span class="input-group-text">mg/dL</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- GCS (Glasgow Coma Scale) --}}
                            <div class="emi-section-card mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2 emi-collapse-header" data-bs-toggle="collapse" data-bs-target="#emi-gcs-panel" role="button">
                                    <label class="form-label fw-bold mb-0"><i class="mdi mdi-brain text-purple"></i> GCS & Pain <small class="text-muted fw-normal">(for ESI 1-2 assessment)</small></label>
                                    <i class="mdi mdi-chevron-down emi-collapse-icon"></i>
                                </div>
                                <div class="collapse" id="emi-gcs-panel">
                                    <div class="row g-2">
                                        <div class="col-md-3 col-6">
                                            <label class="form-label">Eye (E)</label>
                                            <select class="form-select form-select-sm emi-gcs-input" name="gcs_eye" id="emi-gcs-eye">
                                                <option value="">--</option>
                                                <option value="4">4 – Spontaneous</option>
                                                <option value="3">3 – To voice</option>
                                                <option value="2">2 – To pain</option>
                                                <option value="1">1 – None</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <label class="form-label">Verbal (V)</label>
                                            <select class="form-select form-select-sm emi-gcs-input" name="gcs_verbal" id="emi-gcs-verbal">
                                                <option value="">--</option>
                                                <option value="5">5 – Oriented</option>
                                                <option value="4">4 – Confused</option>
                                                <option value="3">3 – Inappropriate</option>
                                                <option value="2">2 – Incomprehensible</option>
                                                <option value="1">1 – None</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <label class="form-label">Motor (M)</label>
                                            <select class="form-select form-select-sm emi-gcs-input" name="gcs_motor" id="emi-gcs-motor">
                                                <option value="">--</option>
                                                <option value="6">6 – Obeys commands</option>
                                                <option value="5">5 – Localises pain</option>
                                                <option value="4">4 – Withdraws</option>
                                                <option value="3">3 – Abnormal flexion</option>
                                                <option value="2">2 – Extension</option>
                                                <option value="1">1 – None</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <label class="form-label">GCS Total</label>
                                            <input type="text" class="form-control form-control-sm fw-bold text-center" id="emi-gcs-total" readonly value="--" style="font-size:1.1rem;">
                                            <input type="hidden" name="gcs_total" id="emi-gcs-total-val">
                                        </div>
                                    </div>
                                    {{-- Pain Scale --}}
                                    <div class="mt-2">
                                        <label class="form-label">Pain Scale: <strong id="emi-pain-display">0</strong>/10</label>
                                        <input type="range" class="form-range emi-pain-range" name="pain_scale" id="emi-pain-scale" min="0" max="10" value="0">
                                        <div class="d-flex justify-content-between" style="font-size:0.7rem; color:#999;">
                                            <span>No pain</span><span>Moderate</span><span>Worst pain</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Allergies --}}
                            <div class="emi-section-card mb-3">
                                <label class="form-label fw-bold mb-2"><i class="mdi mdi-alert-circle text-warning"></i> Allergies</label>
                                <div class="d-flex gap-3 mb-2">
                                    <div class="form-check">
                                        <input type="radio" class="form-check-input" name="allergy_status" id="emi-nkda" value="nkda" checked>
                                        <label class="form-check-label" for="emi-nkda">NKDA <small class="text-muted">(No Known Drug Allergies)</small></label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" class="form-check-input" name="allergy_status" id="emi-has-allergies" value="has_allergies">
                                        <label class="form-check-label text-danger" for="emi-has-allergies">Has Allergies</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" class="form-check-input" name="allergy_status" id="emi-allergy-unknown" value="unknown">
                                        <label class="form-check-label" for="emi-allergy-unknown">Unknown</label>
                                    </div>
                                </div>
                                <div id="emi-allergy-input" style="display:none;">
                                    <input type="text" class="form-control form-control-sm" name="allergies_text" id="emi-allergies-text"
                                           placeholder="e.g., Penicillin, Sulfa, Latex (comma-separated)">
                                </div>
                            </div>

                            {{-- Triage Notes --}}
                            <div class="mb-3">
                                <label class="form-label fw-bold">Triage Notes</label>
                                <textarea class="form-control form-control-sm" name="triage_notes" id="emergency-triage-notes"
                                          rows="2" placeholder="Additional observations, mechanism of injury, clinical findings..." maxlength="1000"></textarea>
                            </div>
                        </div>

                        <div class="modal-footer border-top py-2">
                            <button type="button" class="btn btn-secondary btn-sm" id="emergency-step2-back">
                                <i class="mdi mdi-arrow-left"></i> Back
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" id="emergency-step2-next">
                                Next: Disposition <i class="mdi mdi-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 3: Disposition -->
                    <div class="emergency-panel" id="emergency-step-3" style="display: none;">
                        <div class="p-3">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Disposition <span class="text-danger">*</span></label>
                                <div class="list-group">
                                    <label class="list-group-item list-group-item-action d-flex align-items-center">
                                        <input type="radio" name="disposition" value="admit_emergency" class="form-check-input me-2 emergency-disposition-radio">
                                        <div>
                                            <strong><i class="mdi mdi-bed text-danger"></i> Admit to Emergency Ward</strong>
                                            <small class="d-block text-muted">Assign bed and admit immediately</small>
                                        </div>
                                    </label>
                                    <label class="list-group-item list-group-item-action d-flex align-items-center">
                                        <input type="radio" name="disposition" value="queue_consultation" class="form-check-input me-2 emergency-disposition-radio">
                                        <div>
                                            <strong><i class="mdi mdi-account-clock text-warning"></i> Queue for Consultation</strong>
                                            <small class="d-block text-muted">Send to doctor queue for evaluation</small>
                                        </div>
                                    </label>
                                    <label class="list-group-item list-group-item-action d-flex align-items-center">
                                        <input type="radio" name="disposition" value="direct_service" class="form-check-input me-2 emergency-disposition-radio">
                                        <div>
                                            <strong><i class="mdi mdi-flask text-info"></i> Direct to Lab/Imaging</strong>
                                            <small class="d-block text-muted">Order lab or imaging services directly</small>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Admit Emergency Options -->
                            <div id="emergency-admit-options" style="display: none;">
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Admission Service <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm" name="admit_service_id" id="emergency-admit-service-select">
                                            <option value="">-- Loading services... --</option>
                                        </select>
                                        <small class="text-muted">Service to bill for this emergency admission.</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Emergency Clinic <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm" name="admit_clinic_id" id="emergency-admit-clinic-select">
                                            <option value="">-- Loading clinics... --</option>
                                        </select>
                                        <small class="text-muted">Clinic for the doctor queue entry.</small>
                                    </div>
                                </div>
                                <div class="mt-2 mb-3">
                                    <label class="form-label fw-bold">Assign Bed</label>
                                    <select class="form-select form-select-sm" name="bed_id" id="emergency-bed-select">
                                        <option value="">-- Loading beds... --</option>
                                    </select>
                                    <small class="text-muted">Bed can also be assigned later from nursing workbench.</small>
                                </div>
                            </div>

                            <!-- Queue Consultation Options -->
                            <div id="emergency-consult-options" style="display: none;">
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Clinic <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm" name="clinic_id" id="emergency-clinic-select">
                                            <option value="">-- Loading clinics... --</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Service <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm" name="service_id" id="emergency-service-select">
                                            <option value="">-- Loading services... --</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Direct Service Options -->
                            <div id="emergency-direct-options" style="display: none;">
                                <div class="mb-2">
                                    <label class="form-label fw-bold">Search & Add Services</label>
                                    <input type="text" class="form-control form-control-sm" id="emergency-service-search"
                                           placeholder="Search lab or imaging services...">
                                    <div id="emergency-service-results" class="list-group mt-1" style="max-height: 150px; overflow-y: auto; display: none;"></div>
                                </div>
                                <div id="emergency-selected-services" class="mb-2"></div>
                            </div>
                        </div>

                        <div class="modal-footer border-top py-2">
                            <button type="button" class="btn btn-secondary btn-sm" id="emergency-step3-back">
                                <i class="mdi mdi-arrow-left"></i> Back
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" id="emergency-submit-btn">
                                <i class="mdi mdi-check-bold"></i> Submit Emergency Intake
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Emergency Intake Modal Styles -->
<style>
    .emergency-steps-bar .emergency-step {
        background: #f8f9fa;
        color: #999;
        cursor: default;
        transition: all 0.2s;
        border-right: 1px solid #dee2e6;
    }
    .emergency-steps-bar .emergency-step.active {
        background: #dc3545;
        color: #fff;
    }
    .emergency-steps-bar .emergency-step.completed {
        background: #28a745;
        color: #fff;
    }
    /* ESI buttons */
    .esi-btn {
        min-height: 60px;
        transition: all 0.2s;
    }
    .esi-btn.selected {
        color: #fff !important;
        transform: scale(1.05);
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    .esi-btn[data-esi="1"].selected, .esi-btn[data-esi="2"].selected { background: #dc3545 !important; border-color: #dc3545 !important; }
    .esi-btn[data-esi="3"].selected { background: #ffc107 !important; border-color: #ffc107 !important; color: #000 !important; }
    .esi-btn[data-esi="4"].selected { background: #0dcaf0 !important; border-color: #0dcaf0 !important; }
    .esi-btn[data-esi="5"].selected { background: #198754 !important; border-color: #198754 !important; }
    /* Disposition */
    .emergency-disposition-radio:checked + div strong {
        color: #dc3545;
    }
    #emergency-selected-services .service-chip {
        display: inline-flex;
        align-items: center;
        background: #e9ecef;
        border-radius: 4px;
        padding: 4px 8px;
        margin: 2px;
        font-size: 0.8rem;
    }
    #emergency-selected-services .service-chip .remove-service {
        cursor: pointer;
        margin-left: 6px;
        color: #dc3545;
    }
    /* v2 Section cards */
    .emi-section-card {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 6px;
        padding: 12px;
    }
    .emi-collapse-header {
        cursor: pointer;
        user-select: none;
    }
    .emi-collapse-header .emi-collapse-icon { transition: transform 0.2s; }
    .emi-collapse-header[aria-expanded="true"] .emi-collapse-icon,
    .emi-collapse-header:not(.collapsed) .emi-collapse-icon { transform: rotate(180deg); }
    /* GCS highlight */
    #emi-gcs-total { background: #fff; }
    .gcs-severe { background: #dc3545 !important; color: #fff !important; }
    .gcs-moderate { background: #ffc107 !important; color: #000 !important; }
    .gcs-mild { background: #28a745 !important; color: #fff !important; }
    /* Pain slider */
    .emi-pain-range { height: 8px; }
    .emi-pain-range::-webkit-slider-thumb { width: 20px; height: 20px; }
    /* Timer */
    #emergency-timer { letter-spacing: 1px; }
    /* Identity mode buttons */
    .emi-identity-mode .btn-check:checked + .btn-outline-warning {
        background: #ffc107;
        color: #000;
        border-color: #ffc107;
    }
    /* Unidentified patient alert flash */
    @keyframes emi-pulse { 0%,100%{ opacity:1; } 50%{ opacity:.7; } }
    #emi-unknown-fields .alert { animation: emi-pulse 2s ease-in-out 3; }
</style>

<!-- Emergency Intake Modal Scripts -->
<script>
(function() {
    'use strict';

    let emergencySearchTimeout = null;
    let emergencyServiceSearchTimeout = null;
    let emergencyDirectServices = []; // [{type, id, name}]
    let emiTimerInterval = null;
    let emiTimerSeconds = 0;

    // ===== ELAPSED TIMER =====
    function startEmiTimer() {
        emiTimerSeconds = 0;
        clearInterval(emiTimerInterval);
        $('#emergency-timer').text('00:00');
        emiTimerInterval = setInterval(function() {
            emiTimerSeconds++;
            const m = String(Math.floor(emiTimerSeconds / 60)).padStart(2, '0');
            const s = String(emiTimerSeconds % 60).padStart(2, '0');
            $('#emergency-timer').text(m + ':' + s);
        }, 1000);
    }

    function stopEmiTimer() {
        clearInterval(emiTimerInterval);
        emiTimerInterval = null;
    }

    // Start timer when modal opens
    $('#emergencyIntakeModal').on('shown.bs.modal', function() {
        startEmiTimer();
    });

    // ===== STEP NAVIGATION =====
    function goToStep(step) {
        $('.emergency-panel').hide();
        $('#emergency-step-' + step).show();
        $('.emergency-step').removeClass('active completed');
        for (let i = 1; i < step; i++) {
            $('.emergency-step[data-step="' + i + '"]').addClass('completed');
        }
        $('.emergency-step[data-step="' + step + '"]').addClass('active');
    }

    // ===== IDENTITY MODE TOGGLE (Known / Unidentified) =====
    $('input[name="identity_mode"]').on('change', function() {
        if ($(this).val() === 'unknown') {
            $('#emi-known-fields').hide();
            $('#emi-unknown-fields').show();
            $('#emergency-is-unidentified').val('1');
            // Pre-fill name fields for unknown patient
            $('#emergency-surname').val('Unknown');
            $('#emergency-firstname').val('Patient');
            $('#emergency-gender').val('');
        } else {
            $('#emi-known-fields').show();
            $('#emi-unknown-fields').hide();
            $('#emergency-is-unidentified').val('0');
            $('#emergency-surname').val('');
            $('#emergency-firstname').val('');
        }
    });

    // ===== APPROX AGE ↔ DOB LOGIC =====
    var emiApproxAgeMap = {
        'neonate':      14,        // ~14 days
        'infant':       183,       // ~6 months
        'child_1_5':    1095,      // ~3 years
        'child_6_12':   3285,      // ~9 years
        'adolescent':   5475,      // ~15 years
        'adult_18_30':  8760,      // ~24 years
        'adult_31_50':  14600,     // ~40 years
        'adult_51_65':  21170,     // ~58 years
        'elderly':      27375      // ~75 years
    };

    $('#emergency-approx-age').on('change', function() {
        var key = $(this).val();
        if (key && emiApproxAgeMap[key]) {
            var estDate = new Date();
            estDate.setDate(estDate.getDate() - emiApproxAgeMap[key]);
            var y = estDate.getFullYear();
            var m = String(estDate.getMonth() + 1).padStart(2, '0');
            var d = String(estDate.getDate()).padStart(2, '0');
            $('#emergency-dob').val(y + '-' + m + '-' + d);
        }
    });

    // Clear approx age if user manually types DOB
    $('#emergency-dob').on('change', function() {
        if ($(this).val()) {
            $('#emergency-approx-age').val('');
        }
    });

    // Step 1 → 2
    $('#emergency-step1-next').on('click', function() {
        var patientId = $('#emergency-patient-id').val();
        var isNew = $('#emergency-new-patient-toggle').is(':checked');

        if (!patientId && !isNew) {
            toastr.warning('Please select an existing patient or register a new one.');
            return;
        }

        if (isNew) {
            var isUnidentified = $('#emergency-is-unidentified').val() === '1';
            if (!isUnidentified) {
                if (!$('#emergency-surname').val().trim() || !$('#emergency-firstname').val().trim()) {
                    toastr.warning('Surname and First Name are required for known patients.');
                    return;
                }
            }
            if (!$('#emergency-gender').val()) {
                toastr.warning('Gender is required.');
                return;
            }
            // DOB is optional — allow approx age or no age for emergencies
        }

        goToStep(2);
    });

    // Step 2 → 3
    $('#emergency-step2-next').on('click', function() {
        if (!$('#emergency-esi-level').val()) {
            toastr.warning('Please select an ESI triage level.');
            return;
        }
        if (!$('#emergency-chief-complaint').val().trim()) {
            toastr.warning('Chief complaint is required.');
            return;
        }

        goToStep(3);
        loadDispositionData();
    });

    // Back buttons
    $('#emergency-step2-back').on('click', function() { goToStep(1); });
    $('#emergency-step3-back').on('click', function() { goToStep(2); });

    // ===== PATIENT SEARCH =====
    $('#emergency-patient-search').on('input', function() {
        clearTimeout(emergencySearchTimeout);
        var query = $(this).val().trim();

        if (query.length < 2) {
            $('#emergency-patient-results').hide();
            return;
        }

        emergencySearchTimeout = setTimeout(function() {
            $.get('{{ route("emergency.search-patient") }}', { q: query }, function(patients) {
                var $results = $('#emergency-patient-results').empty();
                if (patients.length === 0) {
                    $results.html('<div class="list-group-item text-muted text-center">No patients found</div>');
                } else {
                    patients.forEach(function(p) {
                        $results.append(
                            '<a href="#" class="list-group-item list-group-item-action emergency-patient-item py-1"' +
                            ' data-id="' + p.id + '" data-name="' + p.name + '" data-fileno="' + p.file_no + '"' +
                            ' data-phone="' + p.phone + '" data-hmo="' + p.hmo + '" data-allergies="' + (p.allergies || '') + '">' +
                                '<div class="d-flex justify-content-between align-items-center">' +
                                    '<div><strong>' + p.name + '</strong>' +
                                    '<small class="d-block text-muted">' + p.file_no + ' | ' + (p.gender || '') + ' | ' + p.phone + '</small></div>' +
                                    '<span class="badge bg-secondary">' + p.hmo + '</span>' +
                                '</div>' +
                            '</a>'
                        );
                    });
                }
                $results.show();
            });
        }, 300);
    });

    // Select patient from search
    $(document).on('click', '.emergency-patient-item', function(e) {
        e.preventDefault();
        var $el = $(this);
        $('#emergency-patient-id').val($el.data('id'));
        $('#emergency-patient-name').text($el.data('name'));
        $('#emergency-patient-fileno').text($el.data('fileno'));
        $('#emergency-patient-phone').text($el.data('phone'));
        $('#emergency-patient-hmo').text($el.data('hmo'));
        $('#emergency-selected-patient').css('display', 'flex').show();
        $('#emergency-patient-results').hide();
        $('#emergency-patient-search').val('');

        // Pre-fill allergies if existing patient has them
        var existingAllergies = $el.data('allergies');
        if (existingAllergies && existingAllergies !== 'null' && existingAllergies.length) {
            $('#emi-has-allergies').prop('checked', true).trigger('change');
            if (typeof existingAllergies === 'object') {
                $('#emi-allergies-text').val(existingAllergies.join(', '));
            } else {
                $('#emi-allergies-text').val(existingAllergies);
            }
            $('#emi-allergy-input').show();
        }

        // Disable new patient fields
        $('#emergency-new-patient-toggle').prop('checked', false);
        $('#emergency-new-patient-fields').hide();
    });

    // Clear selected patient
    $('#emergency-clear-patient').on('click', function() {
        $('#emergency-patient-id').val('');
        $('#emergency-selected-patient').hide();
    });

    // Toggle new patient fields
    $('#emergency-new-patient-toggle').on('change', function() {
        if ($(this).is(':checked')) {
            $('#emergency-new-patient-fields').slideDown(200);
            $('#emergency-patient-id').val('');
            $('#emergency-selected-patient').hide();
            $('#emergency-patient-search').prop('disabled', true);
        } else {
            $('#emergency-new-patient-fields').slideUp(200);
            $('#emergency-patient-search').prop('disabled', false);
        }
    });

    // ===== ESI LEVEL SELECTION WITH HINTS =====
    $(document).on('click', '.esi-btn', function() {
        $('.esi-btn').removeClass('selected');
        $(this).addClass('selected');
        $('#emergency-esi-level').val($(this).data('esi'));

        // Show decision-support hint
        var hint = $(this).data('hint');
        if (hint) {
            $('#esi-hint-text').text(hint);
            $('#esi-hint-box').slideDown(150);
        }

        // Auto-expand GCS panel for ESI 1-2
        var esi = parseInt($(this).data('esi'));
        if (esi <= 2) {
            var $gcsPanel = $('#emi-gcs-panel');
            if (!$gcsPanel.hasClass('show')) {
                $gcsPanel.collapse('show');
            }
        }
    });

    // ===== GCS AUTO-CALCULATION =====
    $(document).on('change', '.emi-gcs-input', function() {
        var eye = parseInt($('#emi-gcs-eye').val()) || 0;
        var verbal = parseInt($('#emi-gcs-verbal').val()) || 0;
        var motor = parseInt($('#emi-gcs-motor').val()) || 0;

        if (eye && verbal && motor) {
            var total = eye + verbal + motor;
            $('#emi-gcs-total').val(total);
            $('#emi-gcs-total-val').val(total);

            // Color-code severity
            var $el = $('#emi-gcs-total');
            $el.removeClass('gcs-severe gcs-moderate gcs-mild');
            if (total <= 8) $el.addClass('gcs-severe');
            else if (total <= 12) $el.addClass('gcs-moderate');
            else $el.addClass('gcs-mild');
        } else {
            $('#emi-gcs-total').val('--');
            $('#emi-gcs-total-val').val('');
            $('#emi-gcs-total').removeClass('gcs-severe gcs-moderate gcs-mild');
        }
    });

    // ===== PAIN SCALE DISPLAY =====
    $('#emi-pain-scale').on('input', function() {
        $('#emi-pain-display').text($(this).val());
    });

    // ===== ALLERGY TOGGLE =====
    $('input[name="allergy_status"]').on('change', function() {
        if ($(this).val() === 'has_allergies') {
            $('#emi-allergy-input').slideDown(150);
        } else {
            $('#emi-allergy-input').slideUp(150);
        }
    });

    // ===== DISPOSITION TOGGLE =====
    $(document).on('change', '.emergency-disposition-radio', function() {
        var val = $(this).val();
        $('#emergency-admit-options, #emergency-consult-options, #emergency-direct-options').hide();

        if (val === 'admit_emergency') {
            $('#emergency-admit-options').slideDown(200);
        } else if (val === 'queue_consultation') {
            $('#emergency-consult-options').slideDown(200);
        } else if (val === 'direct_service') {
            $('#emergency-direct-options').slideDown(200);
        }
    });

    // Load disposition reference data
    function loadDispositionData() {
        // Load emergency beds
        $.get('{{ route("emergency.available-beds") }}', function(beds) {
            var $sel = $('#emergency-bed-select').empty().append('<option value="">-- No bed (assign later) --</option>');
            beds.forEach(function(b) {
                $sel.append('<option value="' + b.id + '">' + b.name + ' — ' + b.ward + ' (' + b.bed_type + ')</option>');
            });
        });

        // Load clinics for both admission and consultation
        $.get('{{ route("emergency.clinics") }}', function(clinics) {
            var $consultClinic = $('#emergency-clinic-select').empty().append('<option value="">-- Select Clinic --</option>');
            var $admitClinic = $('#emergency-admit-clinic-select').empty().append('<option value="">-- Select Clinic --</option>');
            clinics.forEach(function(c) {
                $consultClinic.append('<option value="' + c.id + '">' + c.name + '</option>');
                $admitClinic.append('<option value="' + c.id + '">' + c.name + '</option>');
            });
        });

        // Load services (admission + consultation grouped)
        $.get('{{ route("emergency.services") }}', function(data) {
            var $admitSvc = $('#emergency-admit-service-select').empty().append('<option value="">-- Select Service --</option>');
            if (data.admission && data.admission.length) {
                data.admission.forEach(function(s) {
                    $admitSvc.append('<option value="' + s.id + '">' + s.name + ' — ₦' + Number(s.price).toLocaleString() + '</option>');
                });
            }
            var $consultSvc = $('#emergency-service-select').empty().append('<option value="">-- Select Service --</option>');
            if (data.consultation && data.consultation.length) {
                data.consultation.forEach(function(s) {
                    $consultSvc.append('<option value="' + s.id + '">' + s.name + ' — ₦' + Number(s.price).toLocaleString() + '</option>');
                });
            }
        });
    }

    // ===== DIRECT SERVICE SEARCH =====
    $('#emergency-service-search').on('input', function() {
        clearTimeout(emergencyServiceSearchTimeout);
        var query = $(this).val().trim();

        if (query.length < 2) {
            $('#emergency-service-results').hide();
            return;
        }

        emergencyServiceSearchTimeout = setTimeout(function() {
            var labUrl = '{{ route("reception.services.lab") }}';
            var imgUrl = '{{ route("reception.services.imaging") }}';

            Promise.all([
                $.get(labUrl, { q: query }),
                $.get(imgUrl, { q: query })
            ]).then(function(results) {
                var labResults = results[0];
                var imgResults = results[1];
                var $results = $('#emergency-service-results').empty();

                labResults.forEach(function(s) {
                    if (!emergencyDirectServices.find(function(x) { return x.type === 'lab' && x.id === s.id; })) {
                        $results.append(
                            '<a href="#" class="list-group-item list-group-item-action emergency-add-service py-1"' +
                            ' data-type="lab" data-id="' + s.id + '" data-name="' + s.name + '">' +
                            '<span class="badge bg-primary me-1">LAB</span> ' + s.name +
                            '<small class="text-muted ms-2">' + s.category + '</small></a>'
                        );
                    }
                });

                imgResults.forEach(function(s) {
                    if (!emergencyDirectServices.find(function(x) { return x.type === 'imaging' && x.id === s.id; })) {
                        $results.append(
                            '<a href="#" class="list-group-item list-group-item-action emergency-add-service py-1"' +
                            ' data-type="imaging" data-id="' + s.id + '" data-name="' + s.name + '">' +
                            '<span class="badge bg-info me-1">IMG</span> ' + s.name +
                            '<small class="text-muted ms-2">' + s.category + '</small></a>'
                        );
                    }
                });

                if ($results.children().length === 0) {
                    $results.html('<div class="list-group-item text-muted text-center">No services found</div>');
                }
                $results.show();
            });
        }, 300);
    });

    // Add direct service
    $(document).on('click', '.emergency-add-service', function(e) {
        e.preventDefault();
        emergencyDirectServices.push({
            type: $(this).data('type'),
            id: $(this).data('id'),
            name: $(this).data('name')
        });
        renderSelectedServices();
        $('#emergency-service-results').hide();
        $('#emergency-service-search').val('');
    });

    // Remove direct service
    $(document).on('click', '.remove-service', function() {
        emergencyDirectServices.splice($(this).data('index'), 1);
        renderSelectedServices();
    });

    function renderSelectedServices() {
        var $container = $('#emergency-selected-services').empty();
        if (emergencyDirectServices.length === 0) {
            $container.html('<small class="text-muted">No services selected</small>');
            return;
        }
        emergencyDirectServices.forEach(function(s, i) {
            var badge = s.type === 'lab' ? 'bg-primary' : 'bg-info';
            $container.append(
                '<span class="service-chip">' +
                '<span class="badge ' + badge + ' me-1">' + s.type.toUpperCase() + '</span>' +
                s.name +
                '<span class="remove-service" data-index="' + i + '"><i class="mdi mdi-close-circle"></i></span>' +
                '</span>'
            );
        });
    }

    // ===== FORM SUBMISSION =====
    $('#emergency-submit-btn').on('click', function() {
        var disposition = $('input[name="disposition"]:checked').val();
        if (!disposition) {
            toastr.warning('Please select a disposition.');
            return;
        }

        if (disposition === 'admit_emergency') {
            if (!$('#emergency-admit-service-select').val()) {
                toastr.warning('Please select an admission service.');
                return;
            }
            if (!$('#emergency-admit-clinic-select').val()) {
                toastr.warning('Please select a clinic for the doctor queue.');
                return;
            }
        }

        if (disposition === 'queue_consultation') {
            if (!$('#emergency-clinic-select').val()) {
                toastr.warning('Please select a clinic for consultation.');
                return;
            }
            if (!$('#emergency-service-select').val()) {
                toastr.warning('Please select a consultation service.');
                return;
            }
        }

        if (disposition === 'direct_service' && emergencyDirectServices.length === 0) {
            toastr.warning('Please add at least one lab or imaging service.');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

        // Build form data with all v2 fields
        var formData = {
            _token: '{{ csrf_token() }}',
            patient_id: $('#emergency-patient-id').val() || null,
            is_new_patient: $('#emergency-new-patient-toggle').is(':checked') ? 1 : 0,
            is_unidentified: $('#emergency-is-unidentified').val() == '1' ? 1 : 0,
            surname: $('#emergency-surname').val(),
            firstname: $('#emergency-firstname').val(),
            othername: $('#emergency-othername').val(),
            gender: $('#emergency-gender').val(),
            dob: $('#emergency-dob').val(),
            approx_age: $('#emergency-approx-age').val(),
            phone_no: $('#emergency-phone').val(),
            distinguishing_features: $('#emergency-features').val(),
            arrival_mode: $('#emergency-arrival-mode').val(),
            brought_by_name: $('#emergency-brought-by-name').val(),
            brought_by_phone: $('#emergency-brought-by-phone').val(),
            // Triage
            esi_level: $('#emergency-esi-level').val(),
            chief_complaint: $('#emergency-chief-complaint').val(),
            triage_notes: $('#emergency-triage-notes').val(),
            // Vitals
            vital_hr: $('#emi-hr').val() || null,
            vital_bp_sys: $('#emi-bp-sys').val() || null,
            vital_bp_dia: $('#emi-bp-dia').val() || null,
            vital_spo2: $('#emi-spo2').val() || null,
            vital_temp: $('#emi-temp').val() || null,
            vital_rr: $('#emi-rr').val() || null,
            vital_bs: $('#emi-bs').val() || null,
            // GCS & Pain
            gcs_eye: $('#emi-gcs-eye').val() || null,
            gcs_verbal: $('#emi-gcs-verbal').val() || null,
            gcs_motor: $('#emi-gcs-motor').val() || null,
            gcs_total: $('#emi-gcs-total-val').val() || null,
            pain_scale: $('#emi-pain-scale').val(),
            // Allergies
            allergy_status: $('input[name="allergy_status"]:checked').val(),
            allergies_text: $('#emi-allergies-text').val(),
            // Disposition
            disposition: disposition,
            clinic_id: $('#emergency-clinic-select').val() || null,
            service_id: $('#emergency-service-select').val() || null,
            admit_service_id: $('#emergency-admit-service-select').val() || null,
            admit_clinic_id: $('#emergency-admit-clinic-select').val() || null,
            bed_id: $('#emergency-bed-select').val() || null,
            // Elapsed time
            elapsed_seconds: emiTimerSeconds
        };

        if (disposition === 'direct_service') {
            formData.direct_services = emergencyDirectServices.map(function(s) {
                return { type: s.type, id: s.id };
            });
        }

        $.ajax({
            url: '{{ route("emergency.intake") }}',
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);

                    if (typeof loadPatient === 'function' && response.patient_id) {
                        loadPatient(response.patient_id);
                    } else if (typeof selectPatient === 'function' && response.patient_id) {
                        selectPatient(response.patient_id);
                    }

                    if (typeof loadQueueCounts === 'function') {
                        loadQueueCounts();
                    }

                    $('#emergencyIntakeModal').modal('hide');
                    resetEmergencyForm();
                } else {
                    toastr.error(response.message || 'Intake failed.');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON?.message || 'Server error during emergency intake.';
                if (xhr.responseJSON?.errors) {
                    var errors = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                    toastr.error(errors, 'Validation Error');
                } else {
                    toastr.error(msg);
                }
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="mdi mdi-check-bold"></i> Submit Emergency Intake');
            }
        });
    });

    // ===== RESET FORM =====
    function resetEmergencyForm() {
        $('#emergencyIntakeForm')[0].reset();
        $('#emergency-patient-id').val('');
        $('#emergency-is-unidentified').val('0');
        $('#emergency-selected-patient').hide();
        $('#emergency-new-patient-fields').hide();
        $('#emi-known-fields').show();
        $('#emi-unknown-fields').hide();
        $('#emi-mode-known').prop('checked', true);
        $('#emergency-patient-search').prop('disabled', false);
        $('#emergency-patient-results').hide();
        $('#emergency-esi-level').val('');
        $('#emi-gcs-total-val').val('');
        $('#emi-gcs-total').val('--').removeClass('gcs-severe gcs-moderate gcs-mild');
        $('#emi-pain-display').text('0');
        $('#esi-hint-box').hide();
        $('.esi-btn').removeClass('selected');
        $('input[name="allergy_status"][value="nkda"]').prop('checked', true);
        $('#emi-allergy-input').hide();
        $('input[name="disposition"]').prop('checked', false);
        $('#emergency-admit-options, #emergency-consult-options, #emergency-direct-options').hide();
        // Collapse vitals/GCS panels
        $('#emi-vitals-panel, #emi-gcs-panel').collapse('hide');
        emergencyDirectServices = [];
        renderSelectedServices();
        stopEmiTimer();
        goToStep(1);
    }

    // Reset when modal is closed
    $('#emergencyIntakeModal').on('hidden.bs.modal', function() {
        resetEmergencyForm();
    });

})();
</script>
