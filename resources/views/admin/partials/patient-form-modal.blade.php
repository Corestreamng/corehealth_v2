{{-- Patient Form Modal (Shared Partial) --}}
{{-- Usage: @include('admin.partials.patient-form-modal') --}}
{{--
    Config: Set window.patientFormConfig before opening the modal:
    window.patientFormConfig = {
        nextFileNumberUrl: '/reception/patient/next-file-number',
        checkFileNumberUrl: '/reception/patient/check-file-number',
        updateUrl: '/reception/patient/__ID__/update',
        registerUrl: '/reception/patient/quick-register',
        hmos: [...],  // Array of HMO objects with id, name, scheme_name
        onSuccess: function(patientId, mode) { ... }
    };
--}}

@push('styles')
<link rel="stylesheet" href="{{ asset('css/patient-form-modal.css') }}?v={{ filemtime(public_path('css/patient-form-modal.css')) }}">
@endpush


@push('scripts')
<script src="{{ asset('js/patient-form-modal.js') }}?v={{ filemtime(public_path('js/patient-form-modal.js')) }}"></script>
@endpush

<div class="modal fade" id="patientFormModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header" id="patient-form-header">
                <h5 class="modal-title" id="patient-form-title"><i class="mdi mdi-account-plus"></i> New Patient Registration</h5>
                <span class="pf-emergency-timer badge text-white ms-2" id="pf-emergency-timer">00:00</span>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <form id="patient-form" novalidate>
                <input type="hidden" id="patient-form-mode" value="create">
                <input type="hidden" id="patient-form-id" value="">

                <div class="modal-body p-0">
                    <!-- Progress Stepper -->
                    <div class="form-stepper">
                        <div class="stepper-item active" data-step="1">
                            <div class="stepper-icon"><i class="mdi mdi-account"></i></div>
                            <div class="stepper-label">Basic Info</div>
                        </div>
                        <div class="stepper-line"></div>
                        <div class="stepper-item pf-hide-emergency" data-step="2">
                            <div class="stepper-icon"><i class="mdi mdi-clipboard-pulse"></i></div>
                            <div class="stepper-label">Medical</div>
                        </div>
                        <div class="stepper-line pf-hide-emergency"></div>
                        <div class="stepper-item pf-hide-emergency" data-step="3">
                            <div class="stepper-icon"><i class="mdi mdi-account-supervisor"></i></div>
                            <div class="stepper-label">Next of Kin</div>
                        </div>
                        <div class="stepper-line pf-hide-emergency"></div>
                        {{-- Emergency-only triage step (hidden until emergency mode) --}}
                        <div class="stepper-item pf-emergency-stepper" data-step="5" style="display:none;">
                            <div class="stepper-icon"><i class="mdi mdi-clipboard-pulse"></i></div>
                            <div class="stepper-label">Triage</div>
                        </div>
                        <div class="stepper-line pf-emergency-stepper" style="display:none;"></div>
                        {{-- Emergency-only disposition step (hidden until emergency mode) --}}
                        <div class="stepper-item pf-emergency-stepper" data-step="6" style="display:none;">
                            <div class="stepper-icon"><i class="mdi mdi-directions"></i></div>
                            <div class="stepper-label">Disposition</div>
                        </div>
                        <div class="stepper-line pf-emergency-stepper" style="display:none;"></div>
                        <div class="stepper-item" data-step="4">
                            <div class="stepper-icon"><i class="mdi mdi-shield-account"></i></div>
                            <div class="stepper-label">Insurance</div>
                        </div>
                    </div>

                    <div class="form-steps-container">
                        <!-- Step 1: Basic Information -->
                        <div class="form-step active" data-step="1">
                            <div class="step-header">
                                <h6><i class="mdi mdi-account"></i> Basic Information</h6>
                                <p class="text-muted mb-0">Personal details and contact information</p>
                            </div>
                            <div class="step-content">
                                <!-- Duplicate Patient Detection Panel -->
                                <div id="pf-duplicate-panel" class="pf-duplicate-panel" style="display: none;">
                                    <div class="pf-dup-header">
                                        <i class="mdi mdi-account-alert"></i>
                                        <span>Possible existing patient<span id="pf-dup-plural">s</span> found</span>
                                        <button type="button" class="pf-dup-dismiss" title="Dismiss">&times;</button>
                                    </div>
                                    <div id="pf-dup-list" class="pf-dup-list"></div>
                                    <div class="pf-dup-footer">
                                        <small class="text-muted"><i class="mdi mdi-information-outline"></i> If this is the same patient, close this form and search for them instead.</small>
                                    </div>
                                </div>

                                {{-- ===== EMERGENCY: Patient Chooser Tabs ===== --}}
                                <div class="pf-patient-chooser">
                                    <div class="pf-chooser-tabs">
                                        <div class="pf-chooser-tab tab-existing active" data-panel="existing">
                                            <i class="mdi mdi-account-search tab-icon"></i>
                                            <span>Find Existing</span>
                                        </div>
                                        <div class="pf-chooser-tab tab-new" data-panel="new">
                                            <i class="mdi mdi-account-plus tab-icon"></i>
                                            <span>New Patient</span>
                                        </div>
                                        <div class="pf-chooser-tab tab-unidentified" data-panel="unidentified">
                                            <i class="mdi mdi-account-question tab-icon"></i>
                                            <span>Unidentified</span>
                                        </div>
                                    </div>
                                    <div class="pf-chooser-body border-existing" id="pf-chooser-body">

                                        {{-- Panel: Find Existing Patient --}}
                                        <div class="pf-chooser-panel active" data-panel="existing">
                                            <div class="mb-2">
                                                <div class="input-group">
                                                    <span class="input-group-text bg-primary-subtle border-0"><i class="mdi mdi-magnify text-primary"></i></span>
                                                    <input type="text" class="form-control" id="pf-emergency-patient-search"
                                                           placeholder="Search by name, file number or phone..." autocomplete="off">
                                                </div>
                                                <small class="text-muted d-block mt-1" style="font-size:0.72rem;">Type at least 2 characters to search</small>
                                            </div>
                                            <div id="pf-emergency-patient-results" class="pf-patient-search-results list-group" style="display: none;"></div>

                                            {{-- Selected patient card --}}
                                            <div id="pf-emergency-selected-patient" class="pf-selected-card">
                                                <button type="button" class="btn-deselect" id="pf-emergency-clear-patient" title="Remove selection">
                                                    <i class="mdi mdi-close"></i>
                                                </button>
                                                <div class="d-flex align-items-center">
                                                    <div class="patient-avatar" id="pf-emergency-patient-avatar">?</div>
                                                    <div class="patient-details">
                                                        <h6 id="pf-emergency-patient-name"></h6>
                                                        <div class="patient-meta">
                                                            <span><i class="mdi mdi-file-document-outline"></i> <span id="pf-emergency-patient-fileno"></span></span>
                                                            <span><i class="mdi mdi-phone"></i> <span id="pf-emergency-patient-phone"></span></span>
                                                            <span><i class="mdi mdi-shield-check"></i> <span id="pf-emergency-patient-hmo"></span></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <input type="hidden" id="pf-emergency-patient-id">

                                            <div class="text-center mt-3" id="pf-existing-empty-state">
                                                <i class="mdi mdi-account-search-outline text-muted" style="font-size:2.5rem;"></i>
                                                <p class="text-muted mb-0" style="font-size:0.85rem;">Search for a patient to get started</p>
                                                <small class="text-muted">Select an existing patient to preserve medical history</small>
                                            </div>
                                        </div>

                                        {{-- Panel: Register New Patient --}}
                                        <div class="pf-chooser-panel" data-panel="new">
                                            <div class="alert alert-success py-2 mb-3">
                                                <i class="mdi mdi-information-outline"></i>
                                                <small>Fill in the patient details below. A file number will be auto-generated.</small>
                                            </div>
                                        </div>

                                        {{-- Panel: Unidentified Patient --}}
                                        <div class="pf-chooser-panel" data-panel="unidentified">
                                            <div class="pf-unidentified-panel">
                                                <div class="d-flex align-items-start gap-2 mb-3">
                                                    <i class="mdi mdi-alert-circle text-warning" style="font-size:1.5rem; margin-top:2px;"></i>
                                                    <div>
                                                        <strong>Unidentified Patient</strong>
                                                        <p class="mb-0" style="font-size:0.82rem; color:#666;">
                                                            Patient will be registered as <strong>"Unknown Patient"</strong> with an auto-generated identifier.
                                                            Identity can be updated later from the reception workbench.
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label fw-bold mb-1">Distinguishing Features</label>
                                                    <input type="text" class="form-control form-control-sm" id="pf-distinguishing-features"
                                                           placeholder="e.g. Scars, tattoos, clothing description, approximate age..." maxlength="500">
                                                    <small class="text-muted" style="font-size:0.72rem;">Helps identify the patient later</small>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                    <input type="hidden" id="pf-is-unidentified" value="0">
                                    <input type="hidden" id="pf-patient-chooser-mode" value="existing">
                                </div>

                                <div class="pf-new-patient-fields-wrapper" id="pf-new-patient-wrapper">

                                <div class="row align-items-start pf-row-fileno-family pf-hide-unidentified mb-2">
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <div class="file-no-label-row">
                                                <label class="form-label mb-0">File Number <span class="text-danger">*</span></label>
                                                <span class="file-no-next-badge" id="pf-file-no-hint" title="Next auto-generated number">
                                                    Next: <strong id="pf-next-file-no">--</strong>
                                                </span>
                                            </div>
                                            <div class="file-no-btn-group">
                                                <button type="button" class="file-no-mode-btn active" data-mode="auto">
                                                    <i class="mdi mdi-autorenew"></i> Auto
                                                </button>
                                                <button type="button" class="file-no-mode-btn" data-mode="manual">
                                                    <i class="mdi mdi-pencil"></i> Manual
                                                </button>
                                                <button type="button" class="file-no-mode-btn" id="pf-file-no-refresh" title="Regenerate (Ctrl+G)" style="margin-left: auto;">
                                                    <i class="mdi mdi-refresh"></i>
                                                </button>
                                            </div>
                                            <input type="text" class="form-control file-no-input" id="pf-file-no" readonly placeholder="Auto-generated">
                                            <!-- Info panel showing format and recent numbers -->
                                            <div class="file-no-info-panel" id="pf-file-no-info">
                                                <div class="format-display">
                                                    <span class="text-muted">Format:</span>
                                                    <span class="format-pattern" id="pf-format-pattern">--</span>
                                                </div>
                                                <div class="text-muted">Recent: <span id="pf-recent-label">click to copy</span></div>
                                                <div class="file-no-recent-list" id="pf-recent-file-nos"></div>
                                            </div>
                                            <!-- Duplicate warning (hidden by default) -->
                                            <div class="file-no-duplicate-warning" id="pf-duplicate-warning" style="display: none;">
                                                <div class="warning-title"><i class="mdi mdi-alert"></i> File number already in use</div>
                                                <div id="pf-duplicate-patients"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-8 pf-hide-emergency">
                                        <div class="card-modern bg-light border-0 shadow-sm rounded-3 h-100">
                                            <div class="card-body p-3">
                                                <h6 class="mb-3 text-primary d-flex align-items-center gap-2" style="font-size: 0.9rem; font-weight: 600;">
                                                    <i class="mdi mdi-account-group"></i> Family Folder Configuration
                                                </h6>
                                                <div class="row align-items-center">
                                                    <div class="col-md-5">
                                                        <div class="form-check form-switch d-flex align-items-center gap-2">
                                                            <input class="form-check-input" type="checkbox" id="pf-is-family-principal" style="transform: scale(1.3); margin-top: 0; cursor: pointer;">
                                                            <label class="form-check-label fw-bold mb-0" for="pf-is-family-principal" style="cursor: pointer;">
                                                                Is Family Principal?
                                                            </label>
                                                        </div>
                                                        <small class="text-muted d-block mt-1 ms-4" style="line-height: 1.2;">Check this if patient is the head of a family.</small>
                                                    </div>
                                                    <div class="col-md-7" id="pf-principal-select-container">
                                                        <label class="form-label mb-1 fw-bold text-secondary" style="font-size: 0.85rem;">Assign to Principal (Optional)</label>
                                                        <select class="form-control" id="pf-principal-id" style="width: 100%;">
                                                            <option value=""></option>
                                                        </select>
                                                        <small class="text-muted d-block mt-1" style="line-height: 1.2;">Leave empty if patient is independent.</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row pf-row-names pf-hide-unidentified">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Surname <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="pf-surname" required data-validate="required|min:2" placeholder="Enter surname">
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="pf-firstname" required data-validate="required|min:2" placeholder="Enter first name">
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row pf-row-other-names pf-hide-unidentified">
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Other Names</label>
                                            <input type="text" class="form-control" id="pf-othername" placeholder="Enter other names">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Gender <span class="text-danger">*</span></label>
                                            <select class="form-control" id="pf-gender" required data-validate="required">
                                                <option value="">Select gender</option>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                                <option value="Others">Others</option>
                                            </select>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Age / DOB <span class="text-danger">*</span>
                                                <span class="pf-age-dob-toggle">
                                                    <button type="button" class="pf-adt-btn active" data-mode="age">Age</button>
                                                    <button type="button" class="pf-adt-btn" data-mode="dob">DOB</button>
                                                </span>
                                            </label>
                                            <div class="pf-age-dob-wrapper mode-age">
                                                {{-- Age mode --}}
                                                <div class="pf-age-panel">
                                                    <div class="pf-age-input-group">
                                                        <input type="number" class="form-control" id="pf-age-val" min="0" max="130" placeholder="Age" inputmode="numeric">
                                                        <select class="form-control" id="pf-age-unit">
                                                            <option value="years">yrs</option>
                                                            <option value="months">mos</option>
                                                            <option value="days">days</option>
                                                        </select>
                                                    </div>
                                                    <div class="pf-age-hint" id="pf-age-dob-hint"></div>
                                                </div>
                                                {{-- DOB mode --}}
                                                <div class="pf-dob-panel">
                                                    <input type="date" class="form-control" id="pf-dob" data-validate="required">
                                                </div>
                                                <div class="invalid-feedback" id="pf-age-dob-error"></div>
                                                <small class="form-text" id="pf-age-display"></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row pf-row-contact pf-hide-unidentified">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Phone Number</label>
                                            <input type="tel" class="form-control" id="pf-phone" data-validate="phone" placeholder="Enter phone number">
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 pf-hide-emergency">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Email Address</label>
                                            <input type="email" class="form-control" id="pf-email" data-validate="email" placeholder="Enter email address">
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row pf-row-address pf-hide-unidentified pf-hide-emergency">
                                    <div class="col-12">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Residential Address</label>
                                            <textarea class="form-control" id="pf-address" rows="2" placeholder="Enter residential address"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="row pf-row-uploads pf-hide-unidentified pf-hide-emergency">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-2"><i class="mdi mdi-camera text-primary"></i> Passport Photo</label>

                                            <!-- Photo Capture Container -->
                                            <div class="photo-capture-container">
                                                <!-- Tabs -->
                                                <div class="photo-capture-tabs">
                                                    <button type="button" class="photo-capture-tab active" data-panel="upload">
                                                        <i class="mdi mdi-cloud-upload"></i>
                                                        <span>Upload</span>
                                                    </button>
                                                    <button type="button" class="photo-capture-tab" data-panel="webcam">
                                                        <i class="mdi mdi-camera"></i>
                                                        <span>Webcam</span>
                                                    </button>
                                                </div>

                                                <!-- Upload Panel -->
                                                <div class="photo-capture-content">
                                                    <div class="photo-capture-panel active" id="panel-upload">
                                                        <div class="upload-dropzone" id="photo-dropzone">
                                                            <i class="mdi mdi-cloud-upload-outline"></i>
                                                            <p>Drag & drop photo here</p>
                                                            <p>or <span class="browse-link">browse files</span></p>
                                                            <small class="text-muted">JPG, PNG (max 5MB)</small>
                                                        </div>
                                                        <input type="file" class="d-none" id="pf-passport" accept="image/*">
                                                    </div>

                                                    <!-- Webcam Panel -->
                                                    <div class="photo-capture-panel" id="panel-webcam">
                                                        <div class="webcam-container">
                                                            <div class="webcam-video-wrapper">
                                                                <video id="pf-webcam-video" autoplay playsinline></video>
                                                                <div class="webcam-overlay"></div>
                                                                <div class="webcam-placeholder" id="webcam-placeholder">
                                                                    <i class="mdi mdi-camera-off"></i>
                                                                    <small>Camera not started</small>
                                                                </div>
                                                            </div>
                                                            <div class="webcam-controls">
                                                                <button type="button" class="btn webcam-btn webcam-btn-start" id="btn-start-webcam">
                                                                    <i class="mdi mdi-video"></i> Start Camera
                                                                </button>
                                                                <button type="button" class="btn webcam-btn webcam-btn-capture d-none" id="btn-capture-photo">
                                                                    <i class="mdi mdi-camera-iris"></i> Capture
                                                                </button>
                                                                <button type="button" class="btn webcam-btn webcam-btn-stop d-none" id="btn-stop-webcam">
                                                                    <i class="mdi mdi-stop"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <canvas id="pf-photo-canvas"></canvas>
                                                    </div>

                                                    <!-- Photo Preview (shown after selection/capture) -->
                                                    <div class="photo-preview-wrapper" id="photo-preview-wrapper">
                                                        <img src="" alt="Photo Preview" class="photo-preview-image" id="photo-preview-img">
                                                        <div class="photo-preview-info">
                                                            <span class="badge bg-success" id="photo-source-badge">
                                                                <i class="mdi mdi-check-circle"></i> Photo Ready
                                                            </span>
                                                            <small class="d-block text-muted mt-1" id="photo-filename"></small>
                                                        </div>
                                                        <div class="photo-preview-actions">
                                                            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-change-photo">
                                                                <i class="mdi mdi-refresh"></i> Change
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" id="btn-remove-photo">
                                                                <i class="mdi mdi-delete"></i> Remove
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Hidden field for webcam captured data -->
                                            <input type="hidden" id="pf-passport-data" name="passport_data">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1"><i class="mdi mdi-file-document text-info"></i> Old Records</label>
                                            <input type="file" class="form-control" id="pf-old-records" accept=".pdf,.doc,.docx,.jpg,.png">
                                            <small class="form-text text-muted">Upload previous medical records (PDF, DOC, images)</small>
                                            <!-- Existing old records preview -->
                                            <div class="old-records-preview-container mt-2" style="display: none;">
                                                <div class="d-flex align-items-center gap-2 p-2 border rounded bg-light">
                                                    <div class="file-icon-preview" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; background: #e9ecef; border-radius: 6px;">
                                                        <img src="" alt="Record" id="old-records-preview-img" style="max-width: 100%; max-height: 100%; border-radius: 4px; display: none;">
                                                        <i class="mdi mdi-file-document text-info" id="old-records-preview-icon" style="font-size: 28px; display: none;"></i>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <small class="text-success d-block"><i class="mdi mdi-check-circle"></i> Current Record</small>
                                                        <small class="text-muted text-truncate d-block" id="old-records-preview-name" style="max-width: 150px;"></small>
                                                    </div>
                                                    <a href="#" class="btn btn-sm btn-outline-info" id="pf-view-old-records" title="View" target="_blank">
                                                        <i class="mdi mdi-eye"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" id="pf-clear-old-records" title="Remove">
                                                        <i class="mdi mdi-close"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <!-- New file preview -->
                                            <div class="old-records-new-preview mt-2" id="pf-old-records-new-preview" style="display: none;">
                                                <div class="d-flex align-items-center gap-2 p-2 border rounded bg-success-subtle">
                                                    <div class="file-icon-preview" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; background: #d4edda; border-radius: 6px;">
                                                        <i class="mdi mdi-file-upload text-success" style="font-size: 28px;"></i>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <small class="text-success d-block"><i class="mdi mdi-upload"></i> New File Selected</small>
                                                        <small class="text-muted text-truncate d-block" id="old-records-new-name" style="max-width: 150px;"></small>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="pf-cancel-old-records" title="Cancel">
                                                        <i class="mdi mdi-undo"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Unidentified-only: Gender + Approx Age (visible only when unidentified tab active) --}}
                                <div class="row g-2 pf-show-unidentified" style="display:none;">
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Gender <span class="text-danger">*</span></label>
                                            <select class="form-control" id="pf-gender-unid">
                                                <option value="">Select gender</option>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                                <option value="Others">Others</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Approx Age</label>
                                            <select class="form-control form-control-sm" id="pf-approx-age-unid">
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
                                            <small class="text-muted" style="font-size:0.72rem;">Auto-fills DOB estimate</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Phone <small class="text-muted">(if available)</small></label>
                                            <input type="tel" class="form-control form-control-sm" id="pf-phone-unid" placeholder="Phone number">
                                        </div>
                                    </div>
                                </div>

                                </div>{{-- end pf-new-patient-fields-wrapper --}}

                                {{-- ===== EMERGENCY: Approx Age + Arrival Info (shown only in emergency mode) ===== --}}
                                <div class="pf-emergency-fields">
                                    <div class="row">
                                        {{-- BID Toggle --}}
                                        <div class="col-12 mb-3">
                                            <div class="card-modern bg-light border-danger p-2">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="pf-is-bid" name="is_bid">
                                                    <label class="form-check-label fw-bold text-danger" for="pf-is-bid">
                                                        <i class="mdi mdi-emoticon-dead"></i> PATIENT IS BROUGHT IN DEAD (BID)
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- BID Info (Initially Hidden) --}}
                                        <div id="pf-bid-info" style="display:none;" class="col-12">
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <div class="floating-label">
                                                        <input type="date" class="form-control" name="bid_date" id="pf-bid-date" value="{{ date('Y-m-d') }}">
                                                        <label>Date of Death</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <div class="floating-label">
                                                        <input type="time" class="form-control" name="bid_time" id="pf-bid-time" value="{{ date('H:i') }}">
                                                        <label>Time of Death</label>
                                                    </div>
                                                </div>
                                                <div class="col-12 mb-3">
                                                    <div class="floating-label">
                                                        <input type="text" class="form-control" name="bid_cause" id="pf-bid-cause" placeholder="Suspected cause of death...">
                                                        <label>Suspected Cause of Death</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <div class="row g-2 mt-2">
                                        <div class="col-md-4 pf-hide-unidentified" id="pf-approx-age-col">
                                            <div class="form-group mb-3">
                                                <label class="form-label mb-1">Approx Age</label>
                                                <select class="form-control" id="pf-approx-age">
                                                    <option value="">Select range (if DOB unknown)</option>
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
                                                <small class="text-muted d-block mt-1" style="font-size:0.72rem;">Auto-fills DOB estimate</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group mb-3">
                                                <label class="form-label mb-1"><i class="mdi mdi-truck-fast"></i> Mode of Arrival</label>
                                                <select class="form-control" id="pf-arrival-mode">
                                                    <option value="walk_in">Walk-In</option>
                                                    <option value="ambulance">Ambulance</option>
                                                    <option value="police">Police / Security</option>
                                                    <option value="referral">Referral</option>
                                                    <option value="brought_in">Brought by Relative</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group mb-3">
                                                <label class="form-label mb-1">Brought By (Name)</label>
                                                <input type="text" class="form-control" id="pf-brought-by-name" placeholder="Name of escort/relative">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group mb-3">
                                                <label class="form-label mb-1">Brought By (Phone)</label>
                                                <input type="text" class="form-control" id="pf-brought-by-phone" placeholder="Phone number">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-step" data-step="2">
                            <div class="step-header">
                                <h6><i class="mdi mdi-clipboard-pulse"></i> Medical Information</h6>
                                <p class="text-muted mb-0">Health and demographic details</p>
                            </div>
                            <div class="step-content">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Blood Group</label>
                                            <select class="form-control" id="pf-blood-group">
                                                <option value="">Select blood group</option>
                                                <option value="A+">A+</option>
                                                <option value="A-">A-</option>
                                                <option value="B+">B+</option>
                                                <option value="B-">B-</option>
                                                <option value="AB+">AB+</option>
                                                <option value="AB-">AB-</option>
                                                <option value="O+">O+</option>
                                                <option value="O-">O-</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Genotype</label>
                                            <select class="form-control" id="pf-genotype">
                                                <option value="">Select genotype</option>
                                                <option value="AA">AA</option>
                                                <option value="AS">AS</option>
                                                <option value="AC">AC</option>
                                                <option value="SS">SS</option>
                                                <option value="SC">SC</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Disability Status</label>
                                            <select class="form-control" id="pf-disability">
                                                <option value="0">No Disability</option>
                                                <option value="1">Has Disability</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Nationality</label>
                                            <input type="text" class="form-control" id="pf-nationality" value="Nigerian" placeholder="Enter nationality">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Ethnicity</label>
                                            <input type="text" class="form-control" id="pf-ethnicity" placeholder="Enter ethnicity">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1"><i class="mdi mdi-alert-circle text-warning"></i> Known Allergies</label>
                                            <div class="allergies-input-container">
                                                <div class="allergies-tags" id="pf-allergies-tags"></div>
                                                <input type="text" class="form-control" id="pf-allergy-input" placeholder="Type allergy and press Enter">
                                            </div>
                                            <input type="hidden" id="pf-allergies" value="[]">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Medical History</label>
                                            <textarea class="form-control" id="pf-medical-history" rows="3" placeholder="Enter relevant medical history"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Additional Notes</label>
                                            <textarea class="form-control" id="pf-misc" rows="2" placeholder="Any additional notes"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Next of Kin -->
                        <div class="form-step" data-step="3">
                            <div class="step-header">
                                <h6><i class="mdi mdi-account-supervisor"></i> Next of Kin / Emergency Contact</h6>
                                <p class="text-muted mb-0">Emergency contact information</p>
                            </div>
                            <div class="step-content">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Next of Kin Name</label>
                                            <input type="text" class="form-control" id="pf-nok-name" placeholder="Enter next of kin name">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Next of Kin Phone</label>
                                            <input type="tel" class="form-control" id="pf-nok-phone" placeholder="Enter phone number">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Next of Kin Address</label>
                                            <textarea class="form-control" id="pf-nok-address" rows="2" placeholder="Enter address"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-info mt-3">
                                    <i class="mdi mdi-information"></i>
                                    <strong>Tip:</strong> Next of kin information is optional but recommended for emergency situations.
                                </div>
                            </div>
                        </div>

                        {{-- ===== Step 5: TRIAGE ASSESSMENT (Emergency mode only) ===== --}}
                        <div class="form-step pf-emergency-step" data-step="5">
                            <div class="step-header" style="border-left: 4px solid #dc3545;">
                                <h6><i class="mdi mdi-clipboard-pulse text-danger"></i> Rapid Triage Assessment</h6>
                                <p class="text-muted mb-0">ESI level, chief complaint, vitals and neurologic indicators</p>
                            </div>
                            <div class="step-content">
                                {{-- ESI Level --}}
                                <div class="mb-3">
                                    <label class="form-label fw-bold">ESI Triage Level <span class="text-danger">*</span></label>
                                    <div class="d-flex flex-wrap gap-2" id="pf-esi-buttons">
                                        <button type="button" class="btn btn-outline-danger pf-esi-btn" data-esi="1" data-hint="Immediate life-saving intervention? Intubation, surgical airway, IV push meds, emergency procedure?">
                                            <strong>1</strong><br><small>Resuscitation</small>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger pf-esi-btn" data-esi="2" data-hint="High risk situation? Confused, lethargic, disoriented? Severe pain/distress (Pain ≥ 8/10)?">
                                            <strong>2</strong><br><small>Emergent</small>
                                        </button>
                                        <button type="button" class="btn btn-outline-warning pf-esi-btn" data-esi="3" data-hint="Needs 2+ resources (labs, imaging, IV fluids, specialty consult)? Vitals may be outside normal range.">
                                            <strong>3</strong><br><small>Urgent</small>
                                        </button>
                                        <button type="button" class="btn btn-outline-info pf-esi-btn" data-esi="4" data-hint="Needs only 1 resource (e.g., one X-ray OR one lab test OR simple procedure). Vitals normal.">
                                            <strong>4</strong><br><small>Less Urgent</small>
                                        </button>
                                        <button type="button" class="btn btn-outline-success pf-esi-btn" data-esi="5" data-hint="No resources needed. Simple exam, prescription refill, minor complaint. Stable vitals.">
                                            <strong>5</strong><br><small>Non-Urgent</small>
                                        </button>
                                    </div>
                                    <div id="pf-esi-hint-box" class="alert alert-light border mt-2 py-2 px-3" style="display:none;">
                                        <small><i class="mdi mdi-lightbulb-on text-warning"></i> <span id="pf-esi-hint-text"></span></small>
                                    </div>
                                    <input type="hidden" id="pf-esi-level">
                                </div>

                                {{-- Chief Complaint --}}
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Chief Complaint <span class="text-danger">*</span></label>
                                    <textarea class="form-control form-control-sm" id="pf-chief-complaint" rows="2"
                                              placeholder="Describe the patient's primary complaint..." maxlength="500"></textarea>
                                    <small class="text-muted" style="font-size:0.72rem;">Use the patient's own words when possible, then add key qualifiers (onset, severity, associated symptoms).</small>
                                </div>

                                {{-- Quick Vitals --}}
                                <div class="pf-triage-card">
                                    <div class="d-flex justify-content-between align-items-center mb-2 pf-collapse-header" data-bs-toggle="collapse" data-bs-target="#pf-vitals-panel" role="button">
                                        <label class="form-label fw-bold mb-0"><i class="mdi mdi-heart-pulse text-danger"></i> Quick Vitals <small class="text-muted fw-normal">(recommended)</small></label>
                                        <i class="mdi mdi-chevron-down pf-collapse-icon"></i>
                                    </div>
                                    <div class="collapse" id="pf-vitals-panel">
                                        <div class="row g-2">
                                            <div class="col-md-4 col-6">
                                                <label class="form-label"><i class="mdi mdi-heart text-danger"></i> HR</label>
                                                <div class="input-group input-group-sm">
                                                    <input type="number" class="form-control" id="pf-vital-hr" placeholder="72" min="20" max="250">
                                                    <span class="input-group-text">bpm</span>
                                                </div>
                                            </div>
                                            <div class="col-md-4 col-6">
                                                <label class="form-label"><i class="mdi mdi-heart-pulse text-danger"></i> BP</label>
                                                <div class="d-flex gap-1">
                                                    <input type="number" class="form-control form-control-sm" id="pf-vital-bp-sys" placeholder="120" min="40" max="300" style="width:48%">
                                                    <span class="align-self-center">/</span>
                                                    <input type="number" class="form-control form-control-sm" id="pf-vital-bp-dia" placeholder="80" min="20" max="200" style="width:48%">
                                                </div>
                                            </div>
                                            <div class="col-md-4 col-6">
                                                <label class="form-label"><i class="mdi mdi-percent text-primary"></i> SpO2</label>
                                                <div class="input-group input-group-sm">
                                                    <input type="number" class="form-control" id="pf-vital-spo2" placeholder="98" min="0" max="100">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                            <div class="col-md-4 col-6">
                                                <label class="form-label"><i class="mdi mdi-thermometer text-warning"></i> Temp</label>
                                                <div class="input-group input-group-sm">
                                                    <input type="number" step="0.1" class="form-control" id="pf-vital-temp" placeholder="36.5" min="25" max="45">
                                                    <span class="input-group-text">°C</span>
                                                </div>
                                            </div>
                                            <div class="col-md-4 col-6">
                                                <label class="form-label"><i class="mdi mdi-lungs text-primary"></i> RR</label>
                                                <div class="input-group input-group-sm">
                                                    <input type="number" class="form-control" id="pf-vital-rr" placeholder="16" min="4" max="60">
                                                    <span class="input-group-text">/min</span>
                                                </div>
                                            </div>
                                            <div class="col-md-4 col-6">
                                                <label class="form-label"><i class="mdi mdi-water text-info"></i> Blood Sugar</label>
                                                <div class="input-group input-group-sm">
                                                    <input type="number" step="0.1" class="form-control" id="pf-vital-bs" placeholder="100">
                                                    <span class="input-group-text">mg/dL</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- GCS & Pain --}}
                                <div class="pf-triage-card">
                                    <div class="d-flex justify-content-between align-items-center mb-2 pf-collapse-header" data-bs-toggle="collapse" data-bs-target="#pf-gcs-panel" role="button">
                                        <label class="form-label fw-bold mb-0"><i class="mdi mdi-brain text-purple"></i> GCS & Pain <small class="text-muted fw-normal">(for ESI 1-2 assessment)</small></label>
                                        <i class="mdi mdi-chevron-down pf-collapse-icon"></i>
                                    </div>
                                    <div class="collapse" id="pf-gcs-panel">
                                        <div class="row g-2">
                                            <div class="col-md-3 col-6">
                                                <label class="form-label">Eye (E)</label>
                                                <select class="form-select form-select-sm pf-gcs-input" id="pf-gcs-eye">
                                                    <option value="">--</option>
                                                    <option value="4">4 – Spontaneous</option>
                                                    <option value="3">3 – To voice</option>
                                                    <option value="2">2 – To pain</option>
                                                    <option value="1">1 – None</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3 col-6">
                                                <label class="form-label">Verbal (V)</label>
                                                <select class="form-select form-select-sm pf-gcs-input" id="pf-gcs-verbal">
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
                                                <select class="form-select form-select-sm pf-gcs-input" id="pf-gcs-motor">
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
                                                <input type="text" class="form-control form-control-sm fw-bold text-center" id="pf-gcs-total" readonly value="--" style="font-size:1.1rem;">
                                                <input type="hidden" id="pf-gcs-total-val">
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <label class="form-label">Pain Scale: <strong id="pf-pain-display">0</strong>/10</label>
                                            <input type="range" class="form-range pf-pain-range" id="pf-pain-scale" min="0" max="10" value="0">
                                            <div class="d-flex justify-content-between text-muted" style="font-size:0.72rem;">
                                                <span>No pain</span><span>Moderate</span><span>Worst pain</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Allergies (radio-based for emergency speed) --}}
                                <div class="pf-triage-card">
                                    <label class="form-label fw-bold mb-2"><i class="mdi mdi-alert-circle text-warning"></i> Allergies</label>
                                    <div class="d-flex gap-3 mb-2">
                                        <div class="form-check">
                                            <input type="radio" class="form-check-input" name="pf_allergy_status" id="pf-allergy-nkda" value="nkda" checked>
                                            <label class="form-check-label" for="pf-allergy-nkda">NKDA <small class="text-muted">(No Known Drug Allergies)</small></label>
                                        </div>
                                        <div class="form-check">
                                            <input type="radio" class="form-check-input" name="pf_allergy_status" id="pf-allergy-has" value="has_allergies">
                                            <label class="form-check-label text-danger" for="pf-allergy-has">Has Allergies</label>
                                        </div>
                                        <div class="form-check">
                                            <input type="radio" class="form-check-input" name="pf_allergy_status" id="pf-allergy-unknown" value="unknown">
                                            <label class="form-check-label" for="pf-allergy-unknown">Unknown</label>
                                        </div>
                                    </div>
                                    <div id="pf-allergy-text-input" style="display:none;">
                                        <input type="text" class="form-control form-control-sm" id="pf-allergies-text"
                                               placeholder="e.g., Penicillin, Sulfa, Latex (comma-separated)">
                                    </div>
                                </div>

                                {{-- Triage Notes --}}
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Triage Notes</label>
                                    <textarea class="form-control form-control-sm" id="pf-triage-notes" rows="2"
                                              placeholder="Additional observations, mechanism of injury, clinical findings..." maxlength="1000"></textarea>
                                    <small class="text-muted" style="font-size:0.72rem;">Document objective findings, immediate interventions, and risk indicators.</small>
                                </div>
                            </div>
                        </div>

                        {{-- ===== Step 6: DISPOSITION PLANNING (Emergency mode only) ===== --}}
                        <div class="form-step pf-emergency-step" data-step="6">
                            <div class="step-header" style="border-left: 4px solid #dc3545;">
                                <h6><i class="mdi mdi-directions text-danger"></i> Disposition Planning</h6>
                                <p class="text-muted mb-0">Select one disposition pathway for the patient</p>
                            </div>
                            <div class="step-content">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Disposition <span class="text-danger">*</span></label>
                                    <div class="list-group">
                                        <label class="list-group-item list-group-item-action d-flex align-items-center pf-morgue-hide">
                                            <input type="radio" name="pf_disposition" value="admit_emergency" class="form-check-input me-2 pf-disposition-radio">
                                            <div>
                                                <strong><i class="mdi mdi-bed text-danger"></i> Admit to Emergency Ward</strong>
                                                <small class="d-block text-muted">Assign bed and admit immediately</small>
                                            </div>
                                        </label>
                                        <label class="list-group-item list-group-item-action d-flex align-items-center pf-morgue-hide">
                                            <input type="radio" name="pf_disposition" value="queue_consultation" class="form-check-input me-2 pf-disposition-radio">
                                            <div>
                                                <strong><i class="mdi mdi-account-clock text-warning"></i> Queue for Consultation</strong>
                                                <small class="d-block text-muted">Send to doctor queue for evaluation</small>
                                            </div>
                                        </label>
                                        <label class="list-group-item list-group-item-action d-flex align-items-center pf-morgue-hide">
                                            <input type="radio" name="pf_disposition" value="direct_service" class="form-check-input me-2 pf-disposition-radio">
                                            <div>
                                                <strong><i class="mdi mdi-flask text-info"></i> Direct to Lab/Imaging</strong>
                                                <small class="d-block text-muted">Order lab or imaging services directly</small>
                                            </div>
                                        </label>
                                        <label class="list-group-item list-group-item-action d-flex align-items-center pf-morgue-only">
                                            <input type="radio" name="pf_disposition" value="morgue_mortal" class="form-check-input me-2 pf-disposition-radio">
                                            <div>
                                                <strong><i class="mdi mdi-emoticon-dead text-dark"></i> Direct to Morgue</strong>
                                                <small class="d-block text-muted">Brought in Dead (BID) admission to morgue</small>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                {{-- Admit Emergency Options --}}
                                <div id="pf-admit-options" style="display: none;">
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Admission Service <span class="text-danger">*</span></label>
                                            <select id="pf-admit-service-select" style="width:100%">
                                                <option value="">-- Loading services... --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Emergency Clinic <span class="text-danger">*</span></label>
                                            <select class="form-select form-select-sm" id="pf-admit-clinic-select">
                                                <option value="">-- Loading clinics... --</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mt-2 mb-3">
                                        <label class="form-label fw-bold">Assign Bed <small class="text-muted">(optional)</small></label>
                                        <div class="row g-2">
                                            <div class="col-md-5">
                                                <select id="pf-ward-select" style="width:100%">
                                                    <option value="">-- Select Ward --</option>
                                                </select>
                                            </div>
                                            <div class="col-md-7">
                                                <select id="pf-bed-select" style="width:100%">
                                                    <option value="">-- No bed (assign later) --</option>
                                                </select>
                                            </div>
                                        </div>
                                        <small class="text-muted mt-1 d-block">Select a ward first, then pick an available bed. Can also be assigned later from nursing workbench.</small>
                                    </div>
                                </div>

                                {{-- Morgue Admission Options --}}
                                <div id="pf-morgue-options" style="display: none;">
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Daily Service Rate <span class="text-danger">*</span></label>
                                            <select class="form-select form-select-sm" id="pf-morgue-service-select">
                                                <option value="">-- Loading services... --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">Fridge No.</label>
                                            <input type="text" class="form-control form-control-sm" id="pf-morgue-fridge" placeholder="e.g. F-102">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">Tray No.</label>
                                            <input type="text" class="form-control form-control-sm" id="pf-morgue-tray" placeholder="e.g. T-05">
                                        </div>
                                    </div>
                                    <div class="mt-2 mb-3">
                                        <label class="form-label fw-bold">Admission Notes</label>
                                        <textarea class="form-control form-control-sm" id="pf-morgue-notes" rows="2" placeholder="Notes for mortuary staff..."></textarea>
                                    </div>
                                </div>

                                {{-- Queue Consultation Options --}}
                                <div id="pf-consult-options" style="display: none;">
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Clinic <span class="text-danger">*</span></label>
                                            <select class="form-select form-select-sm" id="pf-clinic-select">
                                                <option value="">-- Loading clinics... --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Service <span class="text-danger">*</span></label>
                                            <select id="pf-service-select" style="width:100%">
                                                <option value="">-- Loading services... --</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                {{-- Direct Service Options --}}
                                <div id="pf-direct-options" style="display: none;">
                                    <div class="mb-2">
                                        <label class="form-label fw-bold">Search & Add Services</label>
                                        <input type="text" class="form-control form-control-sm" id="pf-direct-service-search"
                                               placeholder="Search lab or imaging services...">
                                        <div id="pf-direct-service-results" class="list-group mt-1" style="max-height: 150px; overflow-y: auto; display: none;"></div>
                                    </div>
                                    <div id="pf-selected-direct-services" class="mb-2"><small class="text-muted">No services selected</small></div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 4: Insurance Information -->
                        <div class="form-step" data-step="4">
                            <div class="step-header">
                                <h6><i class="mdi mdi-shield-account"></i> Insurance / HMO Information</h6>
                                <p class="text-muted mb-0">Health insurance and payment details</p>
                            </div>
                            <div class="step-content">
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label class="form-label mb-1">Coverage Scheme</label>
                                        <div id="pf-scheme-grid">
                                            {{-- Scheme cards rendered by JS --}}
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6" id="pf-hmo-select-col">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">HMO Provider</label>
                                            <select class="form-control" id="pf-hmo">
                                                <!-- Options populated by JS -->
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6" id="pf-hmo-no-container" style="display: none;">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">HMO Enrollment Number</label>
                                            <input type="text" class="form-control" id="pf-hmo-no" placeholder="Enter enrollment number">
                                        </div>
                                    </div>
                                </div>
                                <div class="row" id="pf-hmo-no-container-alt" style="display: none;">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">HMO Plan</label>
                                            <input type="text" class="form-control" id="pf-hmo-plan" placeholder="Enter HMO plan">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Company/Organization</label>
                                            <input type="text" class="form-control" id="pf-company" placeholder="Enter company name">
                                        </div>
                                    </div>
                                </div>

                                {{-- Registration Fee (Optional) --}}
                                @if(isset($registrationServices) && $registrationServices->count()> 0)
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1"><i class="mdi mdi-cash-register text-success"></i> Registration Fee <small class="text-muted">(Optional)</small></label>
                                            <select class="form-control" id="pf-registration-service">
                                                <option value="">-- No Registration Fee --</option>
                                                @foreach($registrationServices as $regService)
                                                    <option value="{{ $regService->id }}" data-price="{{ $regService->price->sale_price ?? 0 }}">
                                                        {{ $regService->service_name }} - ₦{{ number_format($regService->price->sale_price ?? 0, 2) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="form-text text-info"><i class="mdi mdi-information-outline"></i> If selected, a billing entry will be created</small>
                                        </div>
                                    </div>
                                </div>
                                @endif

                                <!-- Comprehensive Summary Card -->
                                <div class="registration-summary mt-4" id="registration-summary">
                                    <h6><i class="mdi mdi-clipboard-check"></i> Registration Summary</h6>

                                    <!-- Basic Information -->
                                    <div class="summary-section">
                                        <h6 class="summary-section-title"><i class="mdi mdi-account"></i> Basic Information</h6>
                                        <div class="summary-grid">
                                            <div class="summary-item">
                                                <span class="summary-label">File No:</span>
                                                <span class="summary-value" id="summary-file-no">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Name:</span>
                                                <span class="summary-value" id="summary-name">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Gender:</span>
                                                <span class="summary-value" id="summary-gender">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Date of Birth:</span>
                                                <span class="summary-value" id="summary-dob">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Age:</span>
                                                <span class="summary-value" id="summary-age">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Phone:</span>
                                                <span class="summary-value" id="summary-phone">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Email:</span>
                                                <span class="summary-value" id="summary-email">-</span>
                                            </div>
                                            <div class="summary-item full-width">
                                                <span class="summary-label">Address:</span>
                                                <span class="summary-value" id="summary-address">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Passport:</span>
                                                <span class="summary-value" id="summary-passport">Not uploaded</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Old Records:</span>
                                                <span class="summary-value" id="summary-old-records">Not uploaded</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Medical Information -->
                                    <div class="summary-section">
                                        <h6 class="summary-section-title"><i class="mdi mdi-clipboard-pulse"></i> Medical Information</h6>
                                        <div class="summary-grid">
                                            <div class="summary-item">
                                                <span class="summary-label">Blood Group:</span>
                                                <span class="summary-value" id="summary-blood-group">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Genotype:</span>
                                                <span class="summary-value" id="summary-genotype">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Disability:</span>
                                                <span class="summary-value" id="summary-disability">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Nationality:</span>
                                                <span class="summary-value" id="summary-nationality">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Ethnicity:</span>
                                                <span class="summary-value" id="summary-ethnicity">-</span>
                                            </div>
                                            <div class="summary-item full-width">
                                                <span class="summary-label">Allergies:</span>
                                                <span class="summary-value" id="summary-allergies">None</span>
                                            </div>
                                            <div class="summary-item full-width">
                                                <span class="summary-label">Medical History:</span>
                                                <span class="summary-value" id="summary-medical-history">-</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Next of Kin -->
                                    <div class="summary-section">
                                        <h6 class="summary-section-title"><i class="mdi mdi-account-supervisor"></i> Next of Kin</h6>
                                        <div class="summary-grid">
                                            <div class="summary-item">
                                                <span class="summary-label">Name:</span>
                                                <span class="summary-value" id="summary-nok-name">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Phone:</span>
                                                <span class="summary-value" id="summary-nok-phone">-</span>
                                            </div>
                                            <div class="summary-item full-width">
                                                <span class="summary-label">Address:</span>
                                                <span class="summary-value" id="summary-nok-address">-</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Insurance Information -->
                                    <div class="summary-section">
                                        <h6 class="summary-section-title"><i class="mdi mdi-shield-account"></i> Insurance</h6>
                                        <div class="summary-grid">
                                            <div class="summary-item">
                                                <span class="summary-label">HMO:</span>
                                                <span class="summary-value" id="summary-hmo">Private</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">HMO No:</span>
                                                <span class="summary-value" id="summary-hmo-no">-</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Emergency Intake Summary (shown instead of Registration Summary in emergency mode) -->
                                <div class="registration-summary mt-4" id="emergency-intake-summary" style="display:none; border-left: 4px solid #dc3545;">
                                    <h6><i class="mdi mdi-ambulance text-danger"></i> Emergency Intake Summary</h6>

                                    <!-- Patient -->
                                    <div class="summary-section">
                                        <h6 class="summary-section-title"><i class="mdi mdi-account"></i> Patient</h6>
                                        <div class="summary-grid">
                                            <div class="summary-item">
                                                <span class="summary-label">Name:</span>
                                                <span class="summary-value" id="emg-summary-name">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">File No:</span>
                                                <span class="summary-value" id="emg-summary-fileno">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Gender:</span>
                                                <span class="summary-value" id="emg-summary-gender">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Type:</span>
                                                <span class="summary-value" id="emg-summary-patient-type">New Patient</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- BID Record (morgue mode only) -->
                                    <div class="summary-section pf-morgue-only" id="emg-summary-bid-section">
                                        <h6 class="summary-section-title"><i class="mdi mdi-emoticon-dead text-dark"></i> BID Record</h6>
                                        <div class="summary-grid">
                                            <div class="summary-item">
                                                <span class="summary-label">Date of Death:</span>
                                                <span class="summary-value" id="emg-summary-bid-date">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Time of Death:</span>
                                                <span class="summary-value" id="emg-summary-bid-time">-</span>
                                            </div>
                                            <div class="summary-item full-width">
                                                <span class="summary-label">Suspected Cause:</span>
                                                <span class="summary-value" id="emg-summary-bid-cause">-</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Triage -->
                                    <div class="summary-section pf-morgue-hide-summary">
                                        <h6 class="summary-section-title"><i class="mdi mdi-clipboard-pulse text-danger"></i> Triage</h6>
                                        <div class="summary-grid">
                                            <div class="summary-item">
                                                <span class="summary-label">ESI Level:</span>
                                                <span class="summary-value" id="emg-summary-esi">-</span>
                                            </div>
                                            <div class="summary-item full-width">
                                                <span class="summary-label">Chief Complaint:</span>
                                                <span class="summary-value" id="emg-summary-complaint">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">GCS Score:</span>
                                                <span class="summary-value" id="emg-summary-gcs">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Pain Scale:</span>
                                                <span class="summary-value" id="emg-summary-pain">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Allergy Status:</span>
                                                <span class="summary-value" id="emg-summary-allergy">-</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Vitals (if captured) -->
                                    <div class="summary-section pf-morgue-hide-summary" id="emg-summary-vitals-section" style="display:none;">
                                        <h6 class="summary-section-title"><i class="mdi mdi-heart-pulse"></i> Vitals</h6>
                                        <div class="summary-grid" id="emg-summary-vitals-grid"></div>
                                    </div>

                                    <!-- Disposition -->
                                    <div class="summary-section">
                                        <h6 class="summary-section-title"><i class="mdi mdi-directions text-danger"></i> Disposition</h6>
                                        <div class="summary-grid">
                                            <div class="summary-item full-width">
                                                <span class="summary-label">Pathway:</span>
                                                <span class="summary-value" id="emg-summary-disposition">-</span>
                                            </div>
                                            <div class="summary-item full-width" id="emg-summary-disposition-detail-row" style="display:none;">
                                                <span class="summary-label">Details:</span>
                                                <span class="summary-value" id="emg-summary-disposition-detail">-</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Elapsed Time -->
                                    <div class="summary-section">
                                        <div class="summary-grid">
                                            <div class="summary-item">
                                                <span class="summary-label">Elapsed Time:</span>
                                                <span class="summary-value" id="emg-summary-elapsed">-</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <div class="footer-left">
                        <button type="button" class="btn btn-outline-secondary" id="pf-btn-prev" style="display: none;">
                            <i class="mdi mdi-chevron-left"></i> Previous
                        </button>
                    </div>
                    <div class="footer-right">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="pf-btn-next">
                            Next <i class="mdi mdi-chevron-right"></i>
                        </button>
                        <button type="submit" class="btn btn-success" id="pf-btn-submit" style="display: none;">
                            <i class="mdi mdi-check"></i> <span id="pf-submit-text">Register Patient</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>


