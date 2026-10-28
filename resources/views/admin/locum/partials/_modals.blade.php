{{-- Modal: Register Locum Practitioner & Issue Shift Pass --}}
<div class="modal fade" id="modal-create-locum" tabindex="-1" role="dialog" aria-labelledby="modalCreateLocumLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold" id="modalCreateLocumLabel">
                    <i class="mdi mdi-badge-account-outline me-2"></i> Register Locum & Issue Shift Pass
                </h5>
                <button type="button" class="close btn-close text-white btn-close-white" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close" style="opacity: 0.9; font-size: 1.5rem; line-height: 1;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="form-create-locum">
                <div class="modal-body p-4 bg-light">
                    {{-- Section 1: Cadre & Clinical Roles --}}
                    <div class="card mb-3 border">
                        <div class="card-header bg-white py-2">
                            <strong class="text-primary"><i class="mdi mdi-shield-account me-1"></i> 1. Professional Cadre & Role Assignment</strong>
                            <small class="text-muted d-block">Roles are assigned immediately at creation and enforce full EHR & billing compatibility.</small>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label font-weight-bold">Cadre Template <span class="text-danger">*</span></label>
                                    <select class="form-control" name="cadre_template" id="locum_cadre_template" required>
                                        @foreach($cadres as $key => $cadre)
                                            <option value="{{ $key }}" {{ $key === 'DOCTOR' ? 'selected' : '' }}>
                                                {{ $cadre['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label font-weight-bold">Assigned Spatie Roles <span class="text-danger">*</span></label>
                                    <select class="form-control select2" name="roles[]" id="locum_roles" multiple="multiple" style="width: 100%;">
                                        @foreach($roles as $roleName)
                                            <option value="{{ $roleName }}" {{ $roleName === 'DOCTOR' ? 'selected' : '' }}>{{ $roleName }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Auto-populated based on cadre, or customize as needed.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Section 2: Personal & Contact Information --}}
                    <div class="card mb-3 border">
                        <div class="card-header bg-white py-2">
                            <strong class="text-primary"><i class="mdi mdi-account-details me-1"></i> 2. Clinician Identity & Contact</strong>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label font-weight-bold">Surname <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="surname" placeholder="e.g. Ibrahim" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label font-weight-bold">Firstname <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="firstname" placeholder="e.g. Fatima" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label font-weight-bold">Othername</label>
                                    <input type="text" class="form-control" name="othername" placeholder="Middle name">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label font-weight-bold">Phone Number (For PIN Dispatch) <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="phone_number" placeholder="+234..." required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label font-weight-bold">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email" placeholder="clinician@example.com" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Section 3: Licensing & Departmental Scoping --}}
                    <div class="card mb-3 border">
                        <div class="card-header bg-white py-2">
                            <strong class="text-primary"><i class="mdi mdi-certificate me-1"></i> 3. Regulatory Licensing & Clinic Scoping</strong>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label font-weight-bold">Regulatory Council <span class="text-danger">*</span></label>
                                    <select class="form-control" name="regulatory_body" id="locum_regulatory_body" required>
                                        <option value="MDCN">MDCN (Medical Doctors)</option>
                                        <option value="RRBN">RRBN (Radiographers & Radiologists)</option>
                                        <option value="MLSCN">MLSCN (Medical Lab Scientists)</option>
                                        <option value="NMCN">NMCN (Nurses & Midwives)</option>
                                        <option value="PCN">PCN (Pharmacists)</option>
                                        <option value="OTHER">Other Professional Board</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label font-weight-bold">License Folio Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="license_number" placeholder="e.g. MDCN/R/12345" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label font-weight-bold">Primary Specialty</label>
                                    <input type="text" class="form-control" name="primary_specialty" placeholder="e.g. General Medicine, Ultrasound">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label font-weight-bold">Primary Clinic</label>
                                    <select class="form-control" name="clinic_id">
                                        <option value="">Select Primary Clinic</option>
                                        @foreach($clinics as $cId => $cName)
                                            <option value="{{ $cId }}">{{ $cName }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label font-weight-bold">Also See Queues From (Optional)</label>
                                    <select class="form-control select2" name="can_see_clinic_queues[]" id="locum_can_see_clinics" multiple="multiple" style="width: 100%;">
                                        @foreach($clinics as $cId => $cName)
                                            <option value="{{ $cId }}">{{ $cName }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Section 4: Initial Shift Pass Configuration --}}
                    <div class="card border">
                        <div class="card-header bg-white py-2">
                            <strong class="text-primary"><i class="mdi mdi-clock-outline me-1"></i> 4. Shift Window & Ephemeral Pass</strong>
                            <small class="text-muted d-block">A 6-digit cryptographic PIN will be issued automatically with auto-revocation at shift end.</small>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label font-weight-bold">Shift Duty Window <span class="text-danger">*</span></label>
                                    <select class="form-control" name="shift_type" id="locum_shift_type" required>
                                        <option value="NIGHT" selected>Night Duty (18:00 - 08:00 next day · 14h)</option>
                                        <option value="MORNING">Morning Duty (08:00 - 14:00 · 6h)</option>
                                        <option value="AFTERNOON">Afternoon Duty (13:00 - 19:00 · 6h)</option>
                                        <option value="CUSTOM">Custom Shift Range</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3" id="locum-custom-dates" style="display: none;">
                                    <div class="row">
                                        <div class="col-6">
                                            <label class="form-label font-weight-bold">Starts At</label>
                                            <input type="datetime-local" class="form-control" name="starts_at">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label font-weight-bold">Expires At</label>
                                            <input type="datetime-local" class="form-control" name="expires_at">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold">
                        <i class="mdi mdi-check-circle me-1"></i> Register & Issue Shift Pass
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal: Issue Shift Pass to Existing Locum --}}
<div class="modal fade" id="modal-issue-pass" tabindex="-1" role="dialog" aria-labelledby="modalIssuePassLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold" id="modalIssuePassLabel">
                    <i class="mdi mdi-key-plus me-2"></i> Issue New Shift Pass
                </h5>
                <button type="button" class="close btn-close text-white btn-close-white" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close" style="opacity: 0.9; font-size: 1.5rem; line-height: 1;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="form-issue-pass">
                <input type="hidden" name="user_id" id="issue_pass_user_id">
                <div class="modal-body p-4 bg-light">
                    <p class="mb-3">Issuing a new shift-bounded pass for <strong id="issue_pass_practitioner_name" class="text-primary">Practitioner</strong>. Any previous active pass will be automatically superseded.</p>

                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Shift Duty Window <span class="text-danger">*</span></label>
                        <select class="form-control" name="shift_type" id="issue_pass_shift_type" required>
                            <option value="NIGHT" selected>Night Duty (18:00 - 08:00 next day · 14h)</option>
                            <option value="MORNING">Morning Duty (08:00 - 14:00 · 6h)</option>
                            <option value="AFTERNOON">Afternoon Duty (13:00 - 19:00 · 6h)</option>
                            <option value="CUSTOM">Custom Shift Range</option>
                        </select>
                    </div>

                    <div id="issue-pass-custom-dates" style="display: none;" class="mb-3">
                        <div class="row">
                            <div class="col-6">
                                <label class="form-label font-weight-bold">Starts At</label>
                                <input type="datetime-local" class="form-control" name="starts_at">
                            </div>
                            <div class="col-6">
                                <label class="form-label font-weight-bold">Expires At</label>
                                <input type="datetime-local" class="form-control" name="expires_at">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold">
                        <i class="mdi mdi-key me-1"></i> Generate Shift Pass
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal: Generated Pass Slip & Credentials Preview --}}
<div class="modal fade" id="modal-slip-preview" tabindex="-1" role="dialog" aria-labelledby="modalSlipPreviewLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title font-weight-bold" id="modalSlipPreviewLabel">
                    <i class="mdi mdi-check-decagram me-2"></i> Pass Issued Successfully
                </h5>
                <button type="button" class="close btn-close text-white btn-close-white" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close" style="opacity: 0.9; font-size: 1.5rem; line-height: 1;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="locum-slip-box text-start">
                    <div class="slip-header">
                        <h4 class="mb-0 font-weight-bold">{{ appsettings()->site_name ?? 'COREHEALTH HOSPITAL' }}</h4>
                        <small>LOCUM CLINICAL SMART PASS</small>
                    </div>

                    <div class="mb-2"><strong>PRACTITIONER:</strong> <span id="slip_modal_name">Dr. Jane Doe</span></div>
                    <div class="mb-2"><strong>EMAIL:</strong> <span id="slip_modal_email">jane@hospital.com</span></div>
                    <div class="mb-2"><strong>ASSIGNED ROLES:</strong> <span id="slip_modal_roles">DOCTOR</span></div>
                    <div class="mb-2"><strong>SHIFT:</strong> <span id="slip_modal_shift">Night Duty</span></div>

                    <div class="slip-pin-hero">
                        <small class="d-block text-muted">6-DIGIT SHIFT PIN</small>
                        <h2 id="slip_modal_pin">123456</h2>
                    </div>

                    <div class="mb-2 text-muted small"><strong>TEMPORARY PASSWORD:</strong> <span id="slip_modal_temp_pass">abc12345</span></div>
                    <hr>
                    <div class="small text-muted text-center">
                        <i class="mdi mdi-shield-check text-success"></i> HIPAA & Caldicott Non-Repudiation Verified.
                        Pass expires automatically when shift terminates.
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light justify-content-between">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="window.print();">
                    <i class="mdi mdi-printer me-1"></i> Print Thermal Slip
                </button>
            </div>
        </div>
    </div>
</div>
