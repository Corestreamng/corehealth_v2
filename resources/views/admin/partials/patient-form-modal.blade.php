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

<style>
/* Select2 z-index fix for modals */
.select2-container--open { z-index: 9999 !important; }
#patientFormModal .select2-container { width: 100% !important; }
#patientFormModal .select2-container .select2-selection--single {
    height: calc(1.5em + 0.75rem + 2px);
    padding: 0.375rem 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
}
#patientFormModal .select2-container .select2-selection--single .select2-selection__rendered {
    line-height: 1.5;
    padding-left: 0;
}
#patientFormModal .select2-container .select2-selection--single .select2-selection__arrow {
    height: calc(1.5em + 0.75rem);
}

/* Duplicate Patient Detection Panel */
.pf-duplicate-panel {
    background: #fff8e1;
    border: 1px solid #ffe082;
    border-left: 4px solid #ffa000;
    border-radius: 6px;
    margin-bottom: 1rem;
    overflow: hidden;
    animation: pfDupSlideIn 0.3s ease;
}
@keyframes pfDupSlideIn {
    from { opacity: 0; max-height: 0; margin-bottom: 0; }
    to { opacity: 1; max-height: 500px; margin-bottom: 1rem; }
}
.pf-dup-header {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    background: #fff3cd;
    font-weight: 600;
    font-size: 13px;
    color: #856404;
}
.pf-dup-header .mdi { font-size: 18px; }
.pf-dup-dismiss {
    margin-left: auto;
    background: none;
    border: none;
    font-size: 18px;
    color: #856404;
    cursor: pointer;
    padding: 0 4px;
    line-height: 1;
}
.pf-dup-dismiss:hover { color: #533f03; }
.pf-dup-list { padding: 4px 8px; }
.pf-dup-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 8px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    transition: background 0.15s;
}
.pf-dup-item:hover { background: rgba(255,160,0,0.12); }
.pf-dup-item + .pf-dup-item { border-top: 1px solid #ffe08260; }
.pf-dup-select {
    flex-shrink: 0;
    padding: 2px 10px;
    border: 1px solid #ffa000;
    border-radius: 4px;
    background: #fff;
    color: #e65100;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
}
.pf-dup-select:hover { background: #ffa000; color: #fff; }
.pf-dup-item:hover .pf-dup-select { background: #ffa000; color: #fff; }
.pf-dup-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #ffa000;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 12px;
    flex-shrink: 0;
}
.pf-dup-info { flex: 1; min-width: 0; }
.pf-dup-name { font-weight: 600; color: #333; }
.pf-dup-meta { font-size: 11px; color: #888; display: flex; gap: 8px; flex-wrap: wrap; }
.pf-dup-reasons { flex-shrink: 0; display: flex; gap: 3px; flex-wrap: wrap; }
.pf-dup-reason {
    display: inline-block;
    padding: 1px 6px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: 600;
    background: #ffa000;
    color: #fff;
}
.pf-dup-reason.high { background: #e53935; }
.pf-dup-reason.medium { background: #fb8c00; }
.pf-dup-reason.low { background: #78909c; }
.pf-dup-footer { padding: 6px 12px; border-top: 1px solid #ffe08260; }
}

/* ============================================
       PATIENT FORM MODAL STYLES
       ============================================ */

    #patientFormModal .modal-dialog {
        max-width: 900px;
    }

    #patientFormModal .modal-content {
        border: none;
        border-radius: 1rem;
        overflow: hidden;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }

    #patient-form-header {
        background: linear-gradient(135deg, var(--hospital-primary) 0%, #0052a3 100%);
        color: white;
        padding: 1.25rem 1.5rem;
        border: none;
    }

    #patient-form-header.edit-mode {
        background: linear-gradient(135deg, #28a745 0%, #1e7b34 100%);
    }

    #patient-form-title {
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Form Stepper */
    .form-stepper {
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 1.5rem 2rem;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        gap: 0;
    }

    .stepper-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        z-index: 1;
    }

    .stepper-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: #e9ecef;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        color: #6c757d;
        transition: all 0.3s ease;
        border: 3px solid transparent;
    }

    .stepper-item.active .stepper-icon {
        background: var(--hospital-primary);
        color: white;
        border-color: rgba(0, 123, 255, 0.3);
        box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.15);
    }

    .stepper-item.completed .stepper-icon {
        background: #28a745;
        color: white;
    }

    .stepper-label {
        margin-top: 0.5rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stepper-item.active .stepper-label {
        color: var(--hospital-primary);
    }

    .stepper-item.completed .stepper-label {
        color: #28a745;
    }

    .stepper-line {
        width: 60px;
        height: 3px;
        background: #e9ecef;
        margin: 0 0.5rem;
        margin-bottom: 1.5rem;
        transition: all 0.3s ease;
    }

    .stepper-line.completed {
        background: #28a745;
    }

    /* Form Steps Container */
    .form-steps-container {
        padding: 0;
        max-height: 55vh;
        overflow-y: auto;
    }

    .form-step {
        display: none;
        animation: fadeIn 0.3s ease;
    }

    .form-step.active {
        display: block;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .step-header {
        padding: 1.25rem 1.5rem;
        background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
        border-bottom: 1px solid #e9ecef;
    }

    .step-header h6 {
        margin: 0 0 0.25rem;
        color: #212529;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .step-header h6 i {
        color: var(--hospital-primary);
    }

    .step-content {
        padding: 1.5rem;
    }

    /* Floating Labels */
    .floating-label {
        position: relative;
        margin-bottom: 1.25rem;
    }

    .floating-label label {
        position: absolute;
        top: 0.75rem;
        left: 0.75rem;
        font-size: 0.875rem;
        color: #6c757d;
        transition: all 0.2s ease;
        pointer-events: none;
        background: transparent;
        padding: 0 0.25rem;
        z-index: 1;
    }

    .floating-label .form-control {
        padding: 0.75rem;
        padding-top: 1.25rem;
        border: 2px solid #e9ecef;
        border-radius: 0.5rem;
        transition: all 0.2s ease;
        background: #fff;
    }

    .floating-label .form-control:focus {
        border-color: var(--hospital-primary);
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    }

    .floating-label .form-control:focus + label,
    .floating-label .form-control:not(:placeholder-shown) + label,
    .floating-label .form-control.has-value + label,
    .floating-label select.form-control + label {
        top: -0.5rem;
        left: 0.5rem;
        font-size: 0.75rem;
        background: white;
        color: var(--hospital-primary);
        font-weight: 600;
    }

    .floating-label select.form-control {
        padding-top: 1.25rem;
    }

    .floating-label .form-control.is-valid {
        border-color: #28a745;
    }

    .floating-label .form-control.is-invalid {
        border-color: #dc3545;
    }

    .floating-label .invalid-feedback {
        font-size: 0.8rem;
        margin-top: 0.25rem;
    }

    .floating-label .input-addon {
        position: absolute;
        right: 0.5rem;
        top: 50%;
        transform: translateY(-50%);
    }

    .floating-label .toggle-edit {
        cursor: pointer;
        padding: 0.25rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .floating-label .toggle-edit input {
        cursor: pointer;
    }

    .floating-label .toggle-edit i {
        color: #6c757d;
        font-size: 1rem;
    }

    /* File Number Label Row */
    .file-no-label-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.25rem;
    }

    .file-no-next-badge {
        font-size: 0.7rem;
        color: #6c757d;
        background: #e9ecef;
        padding: 0.15rem 0.5rem;
        border-radius: 0.25rem;
    }

    .file-no-next-badge strong {
        color: #28a745;
    }

    .file-no-next-badge.manual-mode {
        background: #fff3cd;
        color: #856404;
    }

    .file-no-next-badge.manual-mode strong {
        color: #fd7e14;
    }

    /* File Number Button Group */
    .file-no-btn-group {
        display: flex;
        margin-bottom: 0.5rem;
        border-radius: 0.5rem;
        overflow: hidden;
        border: 2px solid #e9ecef;
    }

    .file-no-mode-btn {
        flex: 1;
        padding: 0.5rem 1rem;
        border: none;
        background: #f8f9fa;
        color: #6c757d;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
    }

    .file-no-mode-btn:first-child {
        border-right: 1px solid #e9ecef;
    }

    .file-no-mode-btn:hover:not(.active) {
        background: #e9ecef;
    }

    .file-no-mode-btn.active[data-mode="auto"] {
        background: #28a745;
        color: white;
    }

    .file-no-mode-btn.active[data-mode="manual"] {
        background: #fd7e14;
        color: white;
    }

    .file-no-mode-btn i {
        font-size: 1rem;
    }

    /* File Number Input */
    .file-no-input {
        border: 2px solid #e9ecef;
        border-radius: 0.5rem;
        padding: 0.75rem;
        font-size: 1rem;
        font-weight: 600;
        transition: all 0.2s ease;
    }

    .file-no-input[readonly] {
        background: #f8f9fa;
        border-color: #28a745;
    }

    .file-no-input:not([readonly]) {
        background: #fff;
        border-color: #fd7e14;
    }

    .file-no-input:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(253, 126, 20, 0.15);
    }

    #pf-file-no-hint {
        margin-top: 0.35rem;
    }

    #pf-file-no-hint.manual-mode {
        color: #fd7e14;
    }

    #pf-file-no-hint.manual-mode i {
        animation: pulse 1s infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    /* File Number Status Indicators */
    .file-no-input.status-valid {
        border-color: #28a745 !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='m2.3 6.73.6.57 4.8-4.82-.6-.57-4.2 4.25-1.8-1.81-.6.57 2.4 2.38z'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 1rem;
        padding-right: 2.5rem;
    }

    .file-no-input.status-checking {
        border-color: #ffc107 !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%23ffc107' stroke-width='2'%3e%3ccircle cx='12' cy='12' r='10'/%3e%3cpath d='M12 6v6l4 2'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 1rem;
        padding-right: 2.5rem;
    }

    .file-no-input.status-duplicate {
        border-color: #fd7e14 !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23fd7e14'%3e%3cpath d='M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z'/%3e%3cpath d='M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 1rem;
        padding-right: 2.5rem;
    }

    /* File Number Info Panel */
    .file-no-info-panel {
        background: #f8f9fa;
        border-radius: 0.5rem;
        padding: 0.5rem 0.75rem;
        margin-top: 0.5rem;
        font-size: 0.8rem;
        border: 1px solid #e9ecef;
    }

    .file-no-info-panel .format-display {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.25rem;
    }

    .file-no-info-panel .format-pattern {
        font-family: monospace;
        background: #e9ecef;
        padding: 0.15rem 0.5rem;
        border-radius: 0.25rem;
        font-weight: 600;
    }

    .file-no-recent-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        margin-top: 0.35rem;
    }

    .file-no-recent-item {
        background: #e9ecef;
        padding: 0.15rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        cursor: pointer;
        transition: all 0.15s ease;
        font-family: monospace;
    }

    .file-no-recent-item:hover {
        background: #dee2e6;
    }

    .file-no-duplicate-warning {
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 0.5rem;
        padding: 0.5rem 0.75rem;
        margin-top: 0.5rem;
        font-size: 0.8rem;
    }

    .file-no-duplicate-warning .warning-title {
        font-weight: 600;
        color: #856404;
        margin-bottom: 0.25rem;
    }

    .file-no-duplicate-warning .duplicate-patient {
        font-size: 0.75rem;
        color: #856404;
    }

    /* Allergies Input */
    .allergies-input-container {
        border: 2px solid #e9ecef;
        border-radius: 0.5rem;
        padding: 0.5rem;
        background: white;
        min-height: 80px;
    }

    .allergies-input-container:focus-within {
        border-color: var(--hospital-primary);
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    }

    .allergies-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }

    .allergy-tag-item {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.375rem 0.75rem;
        background: linear-gradient(135deg, #ffe5e5 0%, #ffcccc 100%);
        border: 1px solid #ff9999;
        border-radius: 2rem;
        font-size: 0.85rem;
        color: #cc0000;
        font-weight: 500;
    }

    .allergy-tag-item .remove-allergy {
        cursor: pointer;
        margin-left: 0.25rem;
        color: #cc0000;
        font-size: 1rem;
        line-height: 1;
    }

    .allergy-tag-item .remove-allergy:hover {
        color: #990000;
    }

    #pf-allergy-input {
        border: none;
        outline: none;
        width: 100%;
        padding: 0.25rem;
        font-size: 0.9rem;
    }

    /* Registration Summary */
    .registration-summary {
        background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
        border: 2px solid #81c784;
        border-radius: 0.75rem;
        padding: 1.25rem;
    }

    .registration-summary > h6 {
        margin: 0 0 1rem;
        color: #2e7d32;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 1rem;
    }

    .summary-section {
        background: rgba(255, 255, 255, 0.5);
        border-radius: 0.5rem;
        padding: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .summary-section:last-child {
        margin-bottom: 0;
    }

    .summary-section-title {
        font-size: 0.8rem;
        font-weight: 600;
        color: #495057;
        margin: 0 0 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.35rem;
        border-bottom: 1px solid rgba(0,0,0,0.1);
        padding-bottom: 0.35rem;
    }

    .summary-section-title i {
        color: var(--hospital-primary);
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 0.5rem;
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        padding: 0.35rem 0.5rem;
        background: rgba(255, 255, 255, 0.8);
        border-radius: 0.25rem;
        font-size: 0.8rem;
    }

    .summary-item.full-width {
        grid-column: 1 / -1;
    }

    .summary-label {
        color: #6c757d;
        font-size: 0.75rem;
        flex-shrink: 0;
        margin-right: 0.5rem;
    }

    .summary-value {
        font-weight: 600;
        color: #212529;
        font-size: 0.8rem;
        text-align: right;
        word-break: break-word;
    }

    .summary-value.text-success {
        color: #28a745 !important;
    }

    /* File Upload Preview */
    .passport-preview, .old-records-info {
        display: flex;
        align-items: center;
        padding: 0.5rem;
        background: #f8f9fa;
        border-radius: 0.5rem;
        border: 1px dashed #dee2e6;
    }

    .passport-preview img {
        border: 2px solid #28a745;
    }

    /* Modal Footer */
    #patientFormModal .modal-footer {
        display: flex;
        justify-content: space-between;
        padding: 1rem 1.5rem;
        background: #f8f9fa;
        border-top: 1px solid #e9ecef;
    }

    .footer-left, .footer-right {
        display: flex;
        gap: 0.5rem;
    }

    #pf-btn-prev, #pf-btn-next, #pf-btn-submit {
        padding: 0.625rem 1.25rem;
        font-weight: 600;
        border-radius: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    #pf-btn-submit {
        background: linear-gradient(135deg, #28a745 0%, #1e7b34 100%);
        border: none;
    }

    #pf-btn-submit:hover {
        background: linear-gradient(135deg, #218838 0%, #196d2e 100%);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .form-stepper {
            padding: 1rem;
            flex-wrap: wrap;
        }

        .stepper-line {
            display: none;
        }

        .stepper-item {
            margin-bottom: 0.5rem;
        }

        .stepper-icon {
            width: 40px;
            height: 40px;
            font-size: 1rem;
        }

        .stepper-label {
            font-size: 0.65rem;
        }

        .summary-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Photo Capture Styles */
    .photo-capture-container {
        border: 2px dashed #dee2e6;
        border-radius: 12px;
        background: #f8f9fa;
        overflow: hidden;
    }

    .photo-capture-tabs {
        display: flex;
        border-bottom: 1px solid #dee2e6;
        background: #fff;
    }

    .photo-capture-tab {
        flex: 1;
        padding: 10px 15px;
        border: none;
        background: transparent;
        cursor: pointer;
        font-weight: 500;
        color: #6c757d;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .photo-capture-tab:hover {
        background: #f0f4f8;
        color: #0d6efd;
    }

    .photo-capture-tab.active {
        background: linear-gradient(135deg, #0d6efd 0%, #0099ff 100%);
        color: #fff;
    }

    .photo-capture-tab i {
        font-size: 1.1rem;
    }

    .photo-capture-content {
        padding: 15px;
        min-height: 180px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .photo-capture-panel {
        display: none;
        width: 100%;
        text-align: center;
    }

    .photo-capture-panel.active {
        display: block;
    }

    /* Upload Panel */
    .upload-dropzone {
        border: 2px dashed #0d6efd;
        border-radius: 8px;
        padding: 25px 15px;
        background: rgba(13, 110, 253, 0.03);
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .upload-dropzone:hover, .upload-dropzone.dragover {
        background: rgba(13, 110, 253, 0.08);
        border-color: #0099ff;
    }

    .upload-dropzone i {
        font-size: 2.5rem;
        color: #0d6efd;
        margin-bottom: 10px;
    }

    .upload-dropzone p {
        margin: 0;
        color: #6c757d;
        font-size: 0.9rem;
    }

    .upload-dropzone .browse-link {
        color: #0d6efd;
        font-weight: 600;
        text-decoration: underline;
        cursor: pointer;
    }

    /* Webcam Panel */
    .webcam-container {
        position: relative;
        width: 100%;
        max-width: 280px;
        margin: 0 auto;
    }

    .webcam-video-wrapper {
        position: relative;
        width: 100%;
        aspect-ratio: 4/3;
        background: #1a1a2e;
        border-radius: 8px;
        overflow: hidden;
    }

    #pf-webcam-video {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transform: scaleX(-1);
    }

    .webcam-overlay {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 120px;
        height: 150px;
        border: 3px dashed rgba(255, 255, 255, 0.5);
        border-radius: 50% 50% 50% 50% / 60% 60% 40% 40%;
        pointer-events: none;
    }

    .webcam-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #6c757d;
    }

    .webcam-placeholder i {
        font-size: 3rem;
        opacity: 0.5;
        margin-bottom: 10px;
    }

    .webcam-controls {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-top: 12px;
    }

    .webcam-btn {
        padding: 8px 20px;
        border-radius: 20px;
        font-weight: 500;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
    }

    .webcam-btn-start {
        background: linear-gradient(135deg, #28a745 0%, #1e7b34 100%);
        border: none;
        color: #fff;
    }

    .webcam-btn-start:hover {
        background: linear-gradient(135deg, #218838 0%, #196d2e 100%);
        color: #fff;
    }

    .webcam-btn-capture {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        border: none;
        color: #fff;
        padding: 12px 30px;
        border-radius: 30px;
        font-size: 1rem;
    }

    .webcam-btn-capture:hover {
        background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
        color: #fff;
        transform: scale(1.05);
    }

    .webcam-btn-stop {
        background: #6c757d;
        border: none;
        color: #fff;
    }

    .webcam-btn-stop:hover {
        background: #5a6268;
        color: #fff;
    }

    /* Photo Preview */
    .photo-preview-wrapper {
        display: none;
        padding: 15px;
        text-align: center;
    }

    .photo-preview-wrapper.show {
        display: block;
    }

    .photo-preview-image {
        width: 120px;
        height: 120px;
        object-fit: cover;
        border-radius: 8px;
        border: 3px solid #28a745;
        box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
    }

    .photo-preview-info {
        margin-top: 10px;
    }

    .photo-preview-info .badge {
        font-size: 0.75rem;
        padding: 5px 12px;
    }

    .photo-preview-actions {
        margin-top: 10px;
        display: flex;
        justify-content: center;
        gap: 8px;
    }

    /* Canvas for capture (hidden) */
    #pf-photo-canvas {
        display: none;
    }

    /* ========================================
       EMERGENCY MODE STYLES
       ======================================== */
    #patientFormModal.pf-emergency-mode .modal-header {
        background: linear-gradient(135deg, #dc3545 0%, #a71d2a 100%) !important;
    }
    #patientFormModal.pf-emergency-mode .modal-header.edit-mode {
        background: linear-gradient(135deg, #dc3545 0%, #a71d2a 100%) !important;
    }
    #patientFormModal.pf-emergency-mode .stepper-item.active .stepper-icon {
        background: #dc3545;
        box-shadow: 0 0 0 4px rgba(220,53,69,0.2);
    }
    #patientFormModal.pf-emergency-mode #pf-btn-next {
        background: #dc3545; border-color: #dc3545;
    }
    #patientFormModal.pf-emergency-mode #pf-btn-next:hover {
        background: #a71d2a; border-color: #a71d2a;
    }
    .pf-emergency-timer {
        font-family: monospace;
        font-size: 0.78rem;
        background: rgba(0,0,0,0.25);
        padding: 2px 10px;
        border-radius: 4px;
        letter-spacing: 1px;
        display: none;
    }
    #patientFormModal.pf-emergency-mode .pf-emergency-timer { display: inline-block; }

    /* Patient search section (emergency only) */
    .pf-patient-search-section { display: none; }
    #patientFormModal.pf-emergency-mode .pf-patient-search-section { display: block; }
    .pf-patient-search-results {
        max-height: 200px; overflow-y: auto;
        border: 1px solid #dee2e6; border-radius: 6px;
    }
    .pf-patient-search-results .list-group-item { cursor: pointer; padding: 6px 12px; font-size: 0.85rem; }
    .pf-selected-patient-banner {
        display: none; background: #d4edda; border: 1px solid #c3e6cb;
        border-radius: 6px; padding: 10px 14px;
    }
    .pf-selected-patient-banner.show { display: flex; align-items: center; }

    /* Emergency-specific fields in Step 1 */
    .pf-emergency-fields { display: none; }
    #patientFormModal.pf-emergency-mode .pf-emergency-fields { display: block; }
    .pf-new-patient-fields-wrapper { display: block; }
    #patientFormModal.pf-emergency-mode .pf-new-patient-fields-wrapper.collapsed { display: none; }

    /* === Emergency Patient Chooser Tabs === */
    .pf-patient-chooser { display: none; margin-bottom: 1rem; }
    #patientFormModal.pf-emergency-mode .pf-patient-chooser { display: block; }

    .pf-chooser-tabs {
        display: flex; gap: 8px; margin-bottom: 0;
    }
    .pf-chooser-tab {
        flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px;
        padding: 12px 16px; border: 2px solid #dee2e6; border-bottom: none;
        border-radius: 10px 10px 0 0; background: #f8f9fa;
        cursor: pointer; font-weight: 600; font-size: 0.88rem;
        color: #6c757d; transition: all 0.2s ease;
        position: relative; z-index: 1;
    }
    .pf-chooser-tab:hover { background: #e9ecef; color: #495057; }
    .pf-chooser-tab.active {
        background: #fff; color: #0d6efd; border-color: #0d6efd;
        box-shadow: 0 -2px 8px rgba(13,110,253,0.1);
    }
    .pf-chooser-tab.active.tab-existing { color: #0d6efd; border-color: #0d6efd; }
    .pf-chooser-tab.active.tab-new { color: #198754; border-color: #198754; }
    .pf-chooser-tab.active.tab-unidentified { color: #e67e22; border-color: #e67e22; }
    .pf-chooser-tab .tab-icon { font-size: 1.2rem; }
    .pf-chooser-tab .tab-badge {
        font-size: 0.65rem; padding: 2px 6px; border-radius: 10px;
        font-weight: 500; margin-left: 4px;
    }

    .pf-chooser-body {
        border: 2px solid #dee2e6; border-top: none;
        border-radius: 0 0 10px 10px; background: #fff;
        padding: 16px; min-height: 100px;
    }
    .pf-chooser-body.border-existing { border-color: #0d6efd; }
    .pf-chooser-body.border-new { border-color: #198754; }
    .pf-chooser-body.border-unidentified { border-color: #e67e22; }

    .pf-chooser-panel { display: none; }
    .pf-chooser-panel.active { display: block; animation: fadeIn 0.2s ease; }

    /* Selected patient card (existing patient) */
    .pf-selected-card {
        display: none; background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        border: 1px solid #a3d9a5; border-radius: 10px; padding: 16px;
        position: relative; margin-top: 12px;
    }
    .pf-selected-card.show { display: block; animation: fadeIn 0.2s ease; }
    .pf-selected-card .patient-avatar {
        width: 48px; height: 48px; border-radius: 50%;
        background: #198754; color: #fff; display: flex;
        align-items: center; justify-content: center;
        font-size: 1.2rem; font-weight: 700; flex-shrink: 0;
    }
    .pf-selected-card .patient-details { flex: 1; margin-left: 14px; }
    .pf-selected-card .patient-details h6 { margin: 0 0 2px; font-size: 1rem; }
    .pf-selected-card .patient-meta { font-size: 0.78rem; color: #555; }
    .pf-selected-card .patient-meta span { margin-right: 12px; }
    .pf-selected-card .btn-deselect {
        position: absolute; top: 8px; right: 8px;
        background: rgba(220,53,69,0.1); border: none;
        border-radius: 50%; width: 28px; height: 28px;
        display: flex; align-items: center; justify-content: center;
        color: #dc3545; cursor: pointer; transition: all 0.2s;
    }
    .pf-selected-card .btn-deselect:hover { background: #dc3545; color: #fff; }

    /* ---- Age / DOB Toggle Widget ---- */
    .pf-age-dob-toggle {
        display: inline-flex;
        background: #e9ecef;
        border-radius: 6px;
        padding: 2px;
        gap: 2px;
        margin-left: 6px;
        vertical-align: middle;
    }
    .pf-age-dob-toggle .pf-adt-btn {
        font-size: 0.72rem;
        font-weight: 600;
        padding: 1px 10px;
        border: none;
        border-radius: 5px;
        background: transparent;
        color: #6c757d;
        cursor: pointer;
        transition: all 0.15s;
        line-height: 1.6;
    }
    .pf-age-dob-toggle .pf-adt-btn.active {
        background: #fff;
        color: #0d6efd;
        box-shadow: 0 1px 3px rgba(0,0,0,.12);
    }
    .pf-age-input-group {
        display: flex;
        gap: 6px;
        align-items: center;
    }
    .pf-age-input-group input {
        flex: 1;
        min-width: 0;
    }
    .pf-age-input-group select {
        width: 80px;
        flex-shrink: 0;
    }
    .pf-age-hint {
        font-size: 0.75rem;
        color: #6c757d;
        margin-top: 2px;
    }
    .pf-age-hint .badge {
        font-weight: 500;
        font-size: 0.72rem;
    }
    .pf-dob-panel { display: none; }
    .pf-age-panel { display: block; }
    .pf-age-dob-wrapper.mode-dob .pf-dob-panel { display: block; }
    .pf-age-dob-wrapper.mode-dob .pf-age-panel { display: none; }
    .pf-age-dob-wrapper.mode-age .pf-dob-panel { display: none; }
    .pf-age-dob-wrapper.mode-age .pf-age-panel { display: block; }

    /* Unidentified patient fields */
    .pf-unidentified-panel {
        background: #fff8e1; border: 1px solid #ffe082; border-radius: 8px;
        padding: 14px;
    }

    /* Unidentified mode: hide irrelevant rows, show compact fields */
    #pf-new-patient-wrapper.pf-unidentified-active .pf-hide-unidentified { display: none !important; }
    .pf-show-unidentified { display: none; }
    #pf-new-patient-wrapper.pf-unidentified-active .pf-show-unidentified { display: flex !important; }

    /* Unidentified patient toggle */
    .emi-identity-mode .btn-check:checked + .btn-outline-warning {
        background: #ffc107; color: #000; border-color: #ffc107;
    }

    /* ESI buttons */
    .pf-esi-btn {
        min-height: 56px; transition: all 0.2s; flex: 1;
    }
    .pf-esi-btn.selected {
        color: #fff !important; transform: scale(1.05);
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    .pf-esi-btn[data-esi="1"].selected, .pf-esi-btn[data-esi="2"].selected { background: #dc3545 !important; border-color: #dc3545 !important; }
    .pf-esi-btn[data-esi="3"].selected { background: #ffc107 !important; border-color: #ffc107 !important; color: #000 !important; }
    .pf-esi-btn[data-esi="4"].selected { background: #0dcaf0 !important; border-color: #0dcaf0 !important; }
    .pf-esi-btn[data-esi="5"].selected { background: #198754 !important; border-color: #198754 !important; }

    /* GCS severity colors */
    .pf-gcs-severe { background: #dc3545 !important; color: #fff !important; }
    .pf-gcs-moderate { background: #ffc107 !important; color: #000 !important; }
    .pf-gcs-mild { background: #28a745 !important; color: #fff !important; }

    /* Triage & Disposition section cards */
    .pf-triage-card {
        background: #f8f9fa; border: 1px solid #e9ecef;
        border-radius: 6px; padding: 12px; margin-bottom: 12px;
    }
    .pf-triage-card .pf-collapse-header {
        cursor: pointer; user-select: none;
    }
    .pf-triage-card .pf-collapse-icon { transition: transform 0.2s; }
    .pf-triage-card .pf-collapse-header:not(.collapsed) .pf-collapse-icon { transform: rotate(180deg); }

    /* Disposition radio cards */
    .pf-disposition-option {
        border: 1px solid #dee2e6; border-radius: 6px; padding: 12px; margin-bottom: 8px;
        cursor: pointer; transition: all 0.15s;
    }
    .pf-disposition-option:hover { border-color: #adb5bd; background: #f8f9fa; }
    .pf-disposition-option.selected { border-color: #dc3545; background: #fff5f5; }

    /* Service chips (disposition direct services) */
    .pf-service-chip {
        display: inline-flex; align-items: center; gap: 4px;
        background: #e9ecef; border-radius: 4px; padding: 4px 8px;
        margin: 2px; font-size: 0.8rem;
    }
    .pf-service-chip .pf-remove-service { cursor: pointer; color: #dc3545; }

    /* Pain slider */
    .pf-pain-range { height: 8px; }
    .pf-pain-range::-webkit-slider-thumb { width: 20px; height: 20px; }

    /* ESI hint box */
    #pf-esi-hint-box { font-size: 0.82rem; }

</style>


@push('scripts')
<script>
// Patient Form Config - must be set by the including page
if (typeof window.patientFormConfig === 'undefined') {
    window.patientFormConfig = {
        nextFileNumberUrl: '/reception/patient/next-file-number',
        checkFileNumberUrl: '/reception/patient/check-file-number',
        updateUrl: '/reception/patient/__ID__/update',
        registerUrl: '/reception/patient/quick-register',
        hmos: [],
        onSuccess: function(patientId, mode) {}
    };
}

// =============================================
// PATIENT FORM MODAL (REGISTER/EDIT)
// =============================================
let patientFormCurrentStep = 1;
const patientFormTotalSteps = 4;
let patientFormAllergies = [];

function showPatientFormModal(mode = 'create', patientData = null) {
    // Reset form
    resetPatientForm();

    // Set mode
    $('#patient-form-mode').val(mode);

    if (mode === 'edit' && patientData) {
        $('#patient-form-id').val(patientData.id);
        $('#patient-form-title').html('<i class="mdi mdi-account-edit"></i> Edit Patient');
        $('#patient-form-header').addClass('edit-mode');
        $('#pf-submit-text').text('Update Patient');

        // Populate form with patient data
        populatePatientForm(patientData);
    } else {
        $('#patient-form-title').html('<i class="mdi mdi-account-plus"></i> New Patient Registration');
        $('#patient-form-header').removeClass('edit-mode');
        $('#pf-submit-text').text('Register Patient');

        // Generate new file number
        generatePatientFormFileNumber();
    }

    // Populate HMO dropdown
    populatePatientFormHMO();

    // Show modal
    $('#patientFormModal').modal('show');
}

function resetPatientForm() {
    // Reset form fields
    $('#patient-form')[0].reset();
    $('#patient-form-id').val('');
    $('#patient-form-mode').val('create');

    // Reset age/DOB toggle to Age mode
    $('.pf-adt-btn').removeClass('active');
    $('.pf-adt-btn[data-mode="age"]').addClass('active');
    $('.pf-age-dob-wrapper').removeClass('mode-dob').addClass('mode-age');
    $('#pf-age-val').val('');
    $('#pf-age-unit').val('years');
    $('#pf-age-dob-hint').html('');
    $('#pf-age-dob-error').hide().text('');

    // Reset duplicate detection
    _dupDismissed = false;
    $('#pf-duplicate-panel').hide();
    $('#pf-dup-list').empty();

    // Reset file number toggle to Auto mode
    $('#pf-file-no').prop('readonly', true);
    $('#pf-file-no-toggle').prop('checked', false);
    $('#mode-auto-label').addClass('active');
    $('#mode-manual-label').removeClass('active');
    $('#pf-file-no-hint').html('<i class="mdi mdi-information-outline"></i> Next number: <strong id="pf-last-file-no">--</strong> + 1 = <strong id="pf-next-file-no">--</strong>').removeClass('manual-mode');

    // Reset stepper
    patientFormCurrentStep = 1;
    updatePatientFormStepper();

    // Reset allergies
    patientFormAllergies = [];
    updateAllergiesTags();

    // Reset validation states
    $('#patient-form .form-control').removeClass('is-valid is-invalid');

    // Clear file uploads
    $('#pf-passport').val('').removeData('existing');
    $('#pf-old-records').val('').removeData('existing');
    $('#pf-passport-data').val(''); // Clear webcam capture data

    // Reset photo capture UI
    $('#photo-preview-wrapper').removeClass('show');
    $('#photo-preview-img').attr('src', '');
    $('.photo-capture-tab').removeClass('active').first().addClass('active');
    $('.photo-capture-panel').removeClass('active');
    $('#panel-upload').addClass('active');

    // Legacy passport preview elements (keep for compatibility)
    $('.passport-preview-container').hide();
    $('#passport-preview-img').attr('src', '');
    $('#pf-passport-new-preview').hide();
    $('#passport-new-img').attr('src', '');

    // Old records
    $('.old-records-preview-container').hide();
    $('#old-records-preview-img').attr('src', '').hide();
    $('#old-records-preview-icon').hide();
    $('#old-records-preview-name').text('');
    $('#pf-old-records-new-preview').hide();
    $('#old-records-new-name').text('');
    $('#pf-view-old-records').attr('href', '#');

    // Show step 1
    $('.form-step').removeClass('active');
    $('.form-step[data-step="1"]').addClass('active');

    // Show/hide navigation buttons
    updatePatientFormNavigation();

    // Clear summary
    clearPatientFormSummary();
}

function generatePatientFormFileNumber() {
    const $input = $('#pf-file-no');
    $input.removeClass('status-valid status-checking status-duplicate');

    $.ajax({
        url: patientFormConfig.nextFileNumberUrl,
        method: 'GET',
        success: function(response) {
            $('#pf-file-no').val(response.file_no);
            $('#pf-next-file-no').text(response.file_no);

            // Update format pattern display
            if (response.format_pattern) {
                $('#pf-format-pattern').text(response.format_pattern);
            } else {
                $('#pf-format-pattern').text('Sequential');
            }

            // Populate recent file numbers
            const $recentList = $('#pf-recent-file-nos');
            $recentList.empty();
            if (response.recent_file_nos && response.recent_file_nos.length > 0) {
                response.recent_file_nos.forEach(fileNo => {
                    $recentList.append(`<span class="file-no-recent-item" data-file-no="${fileNo}">${fileNo}</span>`);
                });
            }

            // Store data for later use
            $input.data('lastFileNo', response.last_file_no);
            $input.data('formatPattern', response.format_pattern);

            // Mark as valid (auto-generated)
            $input.addClass('status-valid');
            $('#pf-duplicate-warning').hide();
        },
        error: function() {
            const now = new Date();
            const fallbackNo = `${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}${String(Math.floor(Math.random() * 10000)).padStart(4, '0')}`;
            $('#pf-file-no').val(fallbackNo);
            $('#pf-next-file-no').text(fallbackNo);
            $('#pf-format-pattern').text('Auto-generated');
            $('#pf-recent-file-nos').empty();
        }
    });
}

// Debounced file number duplicate check
let fileNoCheckTimeout = null;
function checkFileNumberDuplicate(fileNo, excludePatientId = null) {
    const $input = $('#pf-file-no');

    // Clear previous timeout
    if (fileNoCheckTimeout) {
        clearTimeout(fileNoCheckTimeout);
    }

    if (!fileNo || fileNo.trim() === '') {
        $input.removeClass('status-valid status-checking status-duplicate');
        $('#pf-duplicate-warning').hide();
        return;
    }

    // Show checking state
    $input.removeClass('status-valid status-duplicate').addClass('status-checking');

    // Debounce the AJAX call
    fileNoCheckTimeout = setTimeout(function() {
        $.ajax({
            url: patientFormConfig.checkFileNumberUrl,
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                file_no: fileNo,
                exclude_patient_id: excludePatientId
            },
            success: function(response) {
                $input.removeClass('status-checking');

                if (response.exists) {
                    // Show warning (not blocking, just informative)
                    $input.addClass('status-duplicate');
                    const $warning = $('#pf-duplicate-warning');
                    const $patients = $('#pf-duplicate-patients');

                    let html = '';
                    response.patients.forEach(p => {
                        html += `<div class="duplicate-patient"><i class="mdi mdi-account"></i> ${p.name} (${p.file_no})</div>`;
                    });
                    if (response.count > 3) {
                        html += `<div class="duplicate-patient text-muted">...and ${response.count - 3} more</div>`;
                    }
                    $patients.html(html);
                    $warning.show();
                } else {
                    // File number is unique
                    $input.addClass('status-valid');
                    $('#pf-duplicate-warning').hide();
                }
            },
            error: function() {
                $input.removeClass('status-checking');
            }
        });
    }, 400); // 400ms debounce
}

// Click handler for recent file numbers (copy to input)
$(document).on('click', '.file-no-recent-item', function() {
    const fileNo = $(this).data('file-no');
    const $input = $('#pf-file-no');

    // Switch to manual mode
    toggleFileNumberEdit('manual');

    // Set the value
    $input.val(fileNo);

    // Check for duplicates
    checkFileNumberDuplicate(fileNo);

    toastr.info(`Copied "${fileNo}" - you can edit it now`);
});

// Keyboard shortcut: Ctrl+G to regenerate file number
$(document).on('keydown', function(e) {
    if (e.ctrlKey && e.key === 'g' && $('#patientFormModal').is(':visible')) {
        e.preventDefault();
        toggleFileNumberEdit('auto');
        toastr.info('File number regenerated');
    }
});

// Refresh button click handler
$('#pf-file-no-refresh').on('click', function() {
    toggleFileNumberEdit('auto');
    toastr.info('File number regenerated');
});

// Input change handler for duplicate check (in manual mode)
$('#pf-file-no').on('input', function() {
    const $input = $(this);
    if (!$input.prop('readonly')) {
        const fileNo = $input.val();
        const excludeId = $input.data('editPatientId'); // Set when editing existing patient
        checkFileNumberDuplicate(fileNo, excludeId);
    }
});

// ============================================
// DUPLICATE PATIENT DETECTION
// ============================================
let _dupCheckTimeout = null;
let _dupDismissed = false;

function checkDuplicatePatient() {
    if (_dupDismissed) return;
    if ($('#patient-form-mode').val() === 'edit') return; // Skip in edit mode

    var surname = $('#pf-surname').val().trim();
    var firstname = $('#pf-firstname').val().trim();
    var phone = $('#pf-phone').val().trim();
    var dob = $('#pf-dob').val();

    // Need at least surname+firstname (2+ chars each) or phone (7+ chars)
    var hasName = surname.length >= 2 && firstname.length >= 2;
    var hasPhone = phone.length >= 7;
    if (!hasName && !hasPhone) {
        $('#pf-duplicate-panel').slideUp(200);
        return;
    }

    if (_dupCheckTimeout) clearTimeout(_dupCheckTimeout);
    _dupCheckTimeout = setTimeout(function() {
        $.ajax({
            url: '/reception/patient/check-duplicate',
            method: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                surname: surname,
                firstname: firstname,
                phone: phone,
                dob: dob,
                exclude_patient_id: $('#patient-form-id').val() || null
            },
            success: function(resp) {
                if (_dupDismissed) return;
                if (resp.count > 0) {
                    renderDuplicateHints(resp.matches);
                } else {
                    $('#pf-duplicate-panel').slideUp(200);
                }
            }
        });
    }, 600);
}

function renderDuplicateHints(matches) {
    var $list = $('#pf-dup-list').empty();
    $('#pf-dup-plural').text(matches.length > 1 ? 's' : '');

    matches.forEach(function(m) {
        var initials = (m.name || '??').split(' ').map(function(w) { return w[0]; }).join('').substring(0, 2).toUpperCase();
        var scoreClass = m.score >= 50 ? 'high' : (m.score >= 30 ? 'medium' : 'low');

        var metaParts = [];
        if (m.file_no) metaParts.push('<i class="mdi mdi-file-document-outline"></i> ' + m.file_no);
        if (m.phone) metaParts.push('<i class="mdi mdi-phone"></i> ' + m.phone);
        if (m.dob) metaParts.push('<i class="mdi mdi-calendar"></i> ' + m.dob);
        if (m.gender) metaParts.push(m.gender);

        var reasons = m.reasons.map(function(r) {
            return '<span class="pf-dup-reason ' + scoreClass + '">' + r + '</span>';
        }).join('');

        $list.append(
            '<div class="pf-dup-item" data-patient-id="' + m.id + '">' +
                '<div class="pf-dup-avatar">' + initials + '</div>' +
                '<div class="pf-dup-info">' +
                    '<div class="pf-dup-name">' + (m.name || 'Unknown') + '</div>' +
                    '<div class="pf-dup-meta">' + metaParts.join(' &middot; ') + '</div>' +
                '</div>' +
                '<div class="pf-dup-reasons">' + reasons + '</div>' +
                '<button type="button" class="pf-dup-select" title="Select this patient"><i class="mdi mdi-account-check"></i> Select</button>' +
            '</div>'
        );
    });

    $('#pf-duplicate-panel').slideDown(300);
}

function toggleFileNumberEdit(mode) {
    const $input = $('#pf-file-no');
    const $hint = $('#pf-file-no-hint');
    const $buttons = $('.file-no-mode-btn');

    // Update button states
    $buttons.removeClass('active');
    $buttons.filter('[data-mode="' + mode + '"]').addClass('active');

    if (mode === 'manual') {
        // Manual mode - allow editing
        $input.prop('readonly', false).attr('placeholder', 'Enter file number');
        $hint.addClass('manual-mode');
        $input.focus().select();

        // Check current value for duplicates
        if ($input.val()) {
            checkFileNumberDuplicate($input.val(), $input.data('editPatientId'));
        }
    } else {
        // Auto mode - readonly with generated number
        $input.prop('readonly', true).attr('placeholder', 'Auto-generated');
        $hint.removeClass('manual-mode');
        generatePatientFormFileNumber();
    }
}

function populatePatientFormHMO() {
    const $select = $('#pf-hmo');
    $select.empty();

    // Group HMOs by scheme
    if (patientFormConfig.hmos && patientFormConfig.hmos.length) {
        const grouped = {};
        patientFormConfig.hmos.forEach(hmo => {
            const schemeName = hmo.scheme_name || hmo.scheme || 'Other';
            if (!grouped[schemeName]) {
                grouped[schemeName] = [];
            }
            grouped[schemeName].push(hmo);
        });

        Object.keys(grouped).sort().forEach(scheme => {
            const $optgroup = $('<optgroup>').attr('label', scheme);
            grouped[scheme].forEach(hmo => {
                // HMO ID 1 is Private and is the default
                const selected = hmo.id === 1 ? ' selected' : '';
                $optgroup.append(`<option value="${hmo.id}"${selected}>${hmo.name}</option>`);
            });
            $select.append($optgroup);
        });
    }

    // Default select HMO ID 1 (Private)
    $select.val(1);

    // Initialize or refresh Select2
    if ($.fn.select2) {
        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }
        $select.select2({
            dropdownParent: $('#patientFormModal'),
            placeholder: 'Select HMO',
            allowClear: false,
            width: '100%'
        });
    }
}

function populatePatientForm(data) {
    // Basic info
    $('#pf-file-no').val(data.file_no || '');
    $('#pf-surname').val(data.surname || '');
    $('#pf-firstname').val(data.firstname || '');
    $('#pf-othername').val(data.othername || '');
    $('#pf-gender').val(data.gender || '');

    // Parse DOB (may be in d/m/Y format)
    if (data.dob) {
        let dob = data.dob;
        // Check if it's in d/m/Y format
        if (dob.includes('/')) {
            const parts = dob.split('/');
            if (parts.length === 3) {
                dob = `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`;
            }
        }
        $('#pf-dob').val(dob).trigger('change');
        // Switch to DOB mode for edit since we have an exact date
        $('.pf-adt-btn').removeClass('active');
        $('.pf-adt-btn[data-mode="dob"]').addClass('active');
        $('.pf-age-dob-wrapper').removeClass('mode-age').addClass('mode-dob');
        updatePatientFormAge();
    }

    $('#pf-phone').val(data.phone_no || '');
    $('#pf-email').val(data.email || '');
    $('#pf-address').val(data.address || '');

    // Medical info
    $('#pf-blood-group').val(data.blood_group || '');
    $('#pf-genotype').val(data.genotype || '');
    $('#pf-disability').val(data.disability ? '1' : '0');
    $('#pf-nationality').val(data.nationality || 'Nigerian');
    $('#pf-ethnicity').val(data.ethnicity || '');
    $('#pf-medical-history').val(data.medical_history || '');
    $('#pf-misc').val(data.misc || '');

    // Allergies
    if (data.allergies) {
        try {
            patientFormAllergies = typeof data.allergies === 'string' ? JSON.parse(data.allergies) : data.allergies;
            updateAllergiesTags();
        } catch (e) {
            patientFormAllergies = [];
        }
    }

    // Next of Kin
    $('#pf-nok-name').val(data.next_of_kin_name || '');
    $('#pf-nok-phone').val(data.next_of_kin_phone || '');
    $('#pf-nok-address').val(data.next_of_kin_address || '');

    // Insurance
    if (data.hmo_id) {
        setTimeout(() => {
            $('#pf-hmo').val(data.hmo_id).trigger('change');
            $('#pf-hmo-no').val(data.hmo_no || '');
            $('#pf-hmo-no-container').show();
        }, 100);
    }

    // Handle existing passport photo - use new photo capture UI
    if (data.passport_url) {
        // Show in the new photo preview UI
        $('#photo-preview-img').attr('src', data.passport_url);
        $('#photo-source-badge').html('<i class="mdi mdi-check-circle"></i> Current Photo');
        $('#photo-filename').text(data.filename || 'Patient photo');
        $('#photo-preview-wrapper').addClass('show');
        $('.photo-capture-panel').removeClass('active');
        // Store existing reference
        $('#pf-passport').data('existing', data.filename);
    } else {
        $('#photo-preview-wrapper').removeClass('show');
        $('.photo-capture-tab').removeClass('active').first().addClass('active');
        $('.photo-capture-panel').removeClass('active');
        $('#panel-upload').addClass('active');
    }

    // Handle existing old records
    if (data.old_records_url) {
        $('.old-records-preview-container').show();
        const ext = data.old_records.split('.').pop().toLowerCase();
        if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
            $('#old-records-preview-img').attr('src', data.old_records_url).show();
            $('#old-records-preview-icon').hide();
        } else {
            $('#old-records-preview-img').hide();
            $('#old-records-preview-icon').show();
        }
        $('#old-records-preview-name').text(data.old_records);
        $('#pf-view-old-records').attr('href', data.old_records_url);
        // Store existing filename for reference
        $('#pf-old-records').data('existing', data.old_records);
    } else {
        $('.old-records-preview-container').hide();
        $('#old-records-preview-img').attr('src', '').hide();
        $('#old-records-preview-name').text('');
        $('#pf-view-old-records').attr('href', '#');
    }

    // Trigger change for floating labels
    $('#patient-form .form-control').each(function() {
        if ($(this).val()) {
            $(this).addClass('has-value');
        }
    });
}

function updatePatientFormStepper() {
    $('.stepper-item').each(function() {
        const step = parseInt($(this).data('step'));
        $(this).removeClass('active completed');

        if (step < patientFormCurrentStep) {
            $(this).addClass('completed');
        } else if (step === patientFormCurrentStep) {
            $(this).addClass('active');
        }
    });

    $('.stepper-line').each(function(index) {
        $(this).removeClass('completed');
        if (index + 1 < patientFormCurrentStep) {
            $(this).addClass('completed');
        }
    });
}

function updatePatientFormNavigation() {
    // Show/hide prev button
    if (patientFormCurrentStep === 1) {
        $('#pf-btn-prev').hide();
    } else {
        $('#pf-btn-prev').show();
    }

    // Show/hide next/submit buttons
    if (patientFormCurrentStep === patientFormTotalSteps) {
        $('#pf-btn-next').hide();
        $('#pf-btn-submit').show();
        // Show registration summary, hide emergency summary
        $('#registration-summary').show();
        $('#emergency-intake-summary').hide();
        updatePatientFormSummary();
    } else {
        $('#pf-btn-next').show();
        $('#pf-btn-submit').hide();
    }
}

function goToPatientFormStep(step) {
    if (step < 1 || step > patientFormTotalSteps) return;

    // Validate current step before moving forward
    if (step > patientFormCurrentStep && !validatePatientFormStep(patientFormCurrentStep)) {
        return;
    }

    patientFormCurrentStep = step;

    // Show the step
    $('.form-step').removeClass('active');
    $(`.form-step[data-step="${step}"]`).addClass('active');

    // Update stepper
    updatePatientFormStepper();

    // Update navigation
    updatePatientFormNavigation();

    // Scroll to top of modal body
    $('.form-steps-container').scrollTop(0);
}

function validatePatientFormStep(step) {
    let isValid = true;
    const $step = $(`.form-step[data-step="${step}"]`);

    // Clear previous validations
    $step.find('.form-control').removeClass('is-valid is-invalid');
    $step.find('.invalid-feedback').text('');

    if (step === 1) {
        // Validate basic info
        const surname = $('#pf-surname').val().trim();
        const firstname = $('#pf-firstname').val().trim();
        const gender = $('#pf-gender').val();
        const dob = $('#pf-dob').val();

        if (!surname) {
            $('#pf-surname').addClass('is-invalid');
            $('#pf-surname').siblings('.invalid-feedback').text('Surname is required');
            isValid = false;
        } else {
            $('#pf-surname').addClass('is-valid');
        }

        if (!firstname) {
            $('#pf-firstname').addClass('is-invalid');
            $('#pf-firstname').siblings('.invalid-feedback').text('First name is required');
            isValid = false;
        } else {
            $('#pf-firstname').addClass('is-valid');
        }

        if (!gender) {
            $('#pf-gender').addClass('is-invalid');
            $('#pf-gender').siblings('.invalid-feedback').text('Gender is required');
            isValid = false;
        } else {
            $('#pf-gender').addClass('is-valid');
        }

        if (!dob) {
            // Show error on whichever panel is visible
            var $ageWrapper = $('.pf-age-dob-wrapper');
            if ($ageWrapper.hasClass('mode-age')) {
                $('#pf-age-val').addClass('is-invalid');
            } else {
                $('#pf-dob').addClass('is-invalid');
            }
            $('#pf-age-dob-error').text('Enter age or date of birth').show();
            isValid = false;
        } else {
            if ($('.pf-age-dob-wrapper').hasClass('mode-age')) {
                $('#pf-age-val').addClass('is-valid');
            } else {
                $('#pf-dob').addClass('is-valid');
            }
            $('#pf-age-dob-error').hide();
        }

        // Validate phone if provided
        const phone = $('#pf-phone').val().trim();
        if (phone && !isValidPhone(phone)) {
            $('#pf-phone').addClass('is-invalid');
            $('#pf-phone').siblings('.invalid-feedback').text('Invalid phone number');
            isValid = false;
        }

        // Validate email if provided
        const email = $('#pf-email').val().trim();
        if (email && !isValidEmail(email)) {
            $('#pf-email').addClass('is-invalid');
            $('#pf-email').siblings('.invalid-feedback').text('Invalid email address');
            isValid = false;
        }
    }

    if (!isValid) {
        // Focus first invalid field
        $step.find('.is-invalid:first').focus();
        toastr.warning('Please fill in all required fields correctly');
    }

    return isValid;
}

function isValidPhone(phone) {
    return /^[\d\s+\-()]{7,20}$/.test(phone);
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function updatePatientFormAge() {
    const dob = $('#pf-dob').val();
    if (!dob) {
        $('#pf-age-display').text('');
        return;
    }

    const birthDate = new Date(dob);
    const today = new Date();

    let years = today.getFullYear() - birthDate.getFullYear();
    let months = today.getMonth() - birthDate.getMonth();
    let days = today.getDate() - birthDate.getDate();

    if (days < 0) {
        months--;
        days += new Date(today.getFullYear(), today.getMonth(), 0).getDate();
    }

    if (months < 0) {
        years--;
        months += 12;
    }

    let ageText = '';
    if (years > 0) {
        ageText = `${years} year${years !== 1 ? 's' : ''}`;
        if (months > 0) {
            ageText += `, ${months} month${months !== 1 ? 's' : ''}`;
        }
    } else if (months > 0) {
        ageText = `${months} month${months !== 1 ? 's' : ''}`;
        if (days > 0) {
            ageText += `, ${days} day${days !== 1 ? 's' : ''}`;
        }
    } else {
        ageText = `${days} day${days !== 1 ? 's' : ''}`;
    }

    $('#pf-age-display').html(`<i class="mdi mdi-calendar-account"></i> Age: ${ageText}`);
}

function updateAllergiesTags() {
    const $container = $('#pf-allergies-tags');
    $container.empty();

    patientFormAllergies.forEach((allergy, index) => {
        $container.append(`
            <span class="allergy-tag-item">
                <i class="mdi mdi-alert-circle"></i>
                ${escapeHtml(allergy)}
                <span class="remove-allergy" data-index="${index}">&times;</span>
            </span>
        `);
    });

    $('#pf-allergies').val(JSON.stringify(patientFormAllergies));
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

function updatePatientFormSummary() {
    const fullName = [
        $('#pf-surname').val().trim(),
        $('#pf-firstname').val().trim(),
        $('#pf-othername').val().trim()
    ].filter(Boolean).join(' ');

    // Basic Information
    $('#summary-file-no').text($('#pf-file-no').val() || '-');
    $('#summary-name').text(fullName || '-');
    $('#summary-gender').text($('#pf-gender').val() || '-');
    $('#summary-phone').text($('#pf-phone').val() || 'N/A');
    $('#summary-email').text($('#pf-email').val() || 'N/A');
    $('#summary-address').text($('#pf-address').val().trim() || 'N/A');

    // Date of Birth & Age
    const dob = $('#pf-dob').val();
    if (dob) {
        const birthDate = new Date(dob);
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        $('#summary-dob').text(birthDate.toLocaleDateString('en-US', options));

        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        $('#summary-age').text(`${age} years`);
    } else {
        $('#summary-dob').text('-');
        $('#summary-age').text('-');
    }

    // File uploads - check for new file or existing file
    const passportFile = $('#pf-passport')[0].files[0];
    const existingPassport = $('#pf-passport').data('existing');
    if (passportFile) {
        $('#summary-passport').html('<span class="text-success"><i class="mdi mdi-check-circle"></i> ' + passportFile.name + ' <em>(new)</em></span>');
    } else if (existingPassport) {
        $('#summary-passport').html('<span class="text-info"><i class="mdi mdi-file-image"></i> ' + existingPassport + ' <em>(existing)</em></span>');
    } else {
        $('#summary-passport').text('Not uploaded');
    }

    const oldRecordsFile = $('#pf-old-records')[0].files[0];
    const existingOldRecords = $('#pf-old-records').data('existing');
    if (oldRecordsFile) {
        $('#summary-old-records').html('<span class="text-success"><i class="mdi mdi-check-circle"></i> ' + oldRecordsFile.name + ' <em>(new)</em></span>');
    } else if (existingOldRecords) {
        $('#summary-old-records').html('<span class="text-info"><i class="mdi mdi-file-document"></i> ' + existingOldRecords + ' <em>(existing)</em></span>');
    } else {
        $('#summary-old-records').text('Not uploaded');
    }

    // Medical Information
    $('#summary-blood-group').text($('#pf-blood-group').val() || '-');
    $('#summary-genotype').text($('#pf-genotype').val() || '-');
    $('#summary-disability').text($('#pf-disability option:selected').text() || '-');
    $('#summary-nationality').text($('#pf-nationality').val() || '-');
    $('#summary-ethnicity').text($('#pf-ethnicity').val() || '-');

    // Allergies
    if (patientFormAllergies && patientFormAllergies.length > 0) {
        $('#summary-allergies').html(patientFormAllergies.map(a => '<span class="badge bg-danger me-1">' + a + '</span>').join(' '));
    } else {
        $('#summary-allergies').text('None');
    }

    $('#summary-medical-history').text($('#pf-medical-history').val().trim() || 'None');

    // Next of Kin
    $('#summary-nok-name').text($('#pf-nok-name').val().trim() || '-');
    $('#summary-nok-phone').text($('#pf-nok-phone').val().trim() || '-');
    $('#summary-nok-address').text($('#pf-nok-address').val().trim() || '-');

    // HMO
    const hmoId = $('#pf-hmo').val();
    if (hmoId) {
        $('#summary-hmo').text($('#pf-hmo option:selected').text());
    } else {
        $('#summary-hmo').text('Private');
    }
    $('#summary-hmo-no').text($('#pf-hmo-no').val().trim() || '-');
}

function clearPatientFormSummary() {
    // Clear all summary fields
    $('#summary-file-no, #summary-name, #summary-gender, #summary-dob, #summary-age').text('-');
    $('#summary-phone, #summary-email, #summary-address').text('-');
    $('#summary-passport, #summary-old-records').text('Not uploaded');
    $('#summary-blood-group, #summary-genotype, #summary-disability').text('-');
    $('#summary-nationality, #summary-ethnicity').text('-');
    $('#summary-allergies').text('None');
    $('#summary-medical-history').text('-');
    $('#summary-nok-name, #summary-nok-phone, #summary-nok-address').text('-');
    $('#summary-hmo').text('Private');
    $('#summary-hmo-no').text('-');
}

function submitPatientForm() {
    // Final validation
    if (!validatePatientFormStep(1)) {
        goToPatientFormStep(1);
        return;
    }

    const mode = $('#patient-form-mode').val();
    const patientId = $('#patient-form-id').val();
    const $btn = $('#pf-btn-submit');
    const originalHtml = $btn.html();

    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving...');

    // Use FormData for file uploads
    const formData = new FormData();
    formData.append('_token', '{{ csrf_token() }}');
    formData.append('file_no', $('#pf-file-no').val());
    formData.append('surname', $('#pf-surname').val().trim());
    formData.append('firstname', $('#pf-firstname').val().trim());
    formData.append('othername', $('#pf-othername').val().trim());
    formData.append('gender', $('#pf-gender').val());
    formData.append('dob', $('#pf-dob').val());
    formData.append('phone_no', $('#pf-phone').val().trim());
    formData.append('email', $('#pf-email').val().trim());
    formData.append('address', $('#pf-address').val().trim());
    formData.append('blood_group', $('#pf-blood-group').val());
    formData.append('genotype', $('#pf-genotype').val());
    formData.append('disability', $('#pf-disability').val());
    formData.append('nationality', $('#pf-nationality').val());
    formData.append('ethnicity', $('#pf-ethnicity').val());
    formData.append('allergies', JSON.stringify(patientFormAllergies));
    formData.append('medical_history', $('#pf-medical-history').val().trim());
    formData.append('misc', $('#pf-misc').val().trim());
    formData.append('next_of_kin_name', $('#pf-nok-name').val().trim());
    formData.append('next_of_kin_phone', $('#pf-nok-phone').val().trim());
    formData.append('next_of_kin_address', $('#pf-nok-address').val().trim());
    formData.append('hmo_id', $('#pf-hmo').val() || 1);
    formData.append('hmo_no', $('#pf-hmo-no').val().trim());

    // Add registration service if selected
    const registrationServiceId = $('#pf-registration-service').val();
    if (registrationServiceId) {
        formData.append('registration_service_id', registrationServiceId);
    }

    // Add file uploads if present
    const passportFile = $('#pf-passport')[0].files[0];
    const passportDataUrl = $('#pf-passport-data').val();

    if (passportFile) {
        // User uploaded a file
        formData.append('filename', passportFile);
    } else if (passportDataUrl) {
        // User captured from webcam - send as base64
        formData.append('passport_data', passportDataUrl);
    }

    const oldRecordsFile = $('#pf-old-records')[0].files[0];
    if (oldRecordsFile) {
        formData.append('old_records', oldRecordsFile);
    }

    let url;
    if (mode === 'edit' && patientId) {
        url = patientFormConfig.updateUrl.replace('__ID__', patientId);
        formData.append('_method', 'PUT');
    } else {
        url = patientFormConfig.registerUrl;
    }

    $.ajax({
        url: url,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                toastr.success(mode === 'edit' ? 'Patient updated successfully' : 'Patient registered successfully');
                $('#patientFormModal').modal('hide');

                // Reload patient data if editing current patient or load new patient
                const newPatientId = response.patient?.id || patientId;
                if (newPatientId && typeof patientFormConfig.onSuccess === 'function') {
                    patientFormConfig.onSuccess(newPatientId, mode);
                }
            } else {
                toastr.error(response.message || 'Operation failed');
            }
        },
        error: function(xhr) {
            const errors = xhr.responseJSON?.errors;
            if (errors) {
                Object.values(errors).forEach(err => {
                    toastr.error(err[0]);
                });
            } else {
                toastr.error(xhr.responseJSON?.message || 'Operation failed');
            }
        },
        complete: function() {
            $btn.prop('disabled', false).html(originalHtml);
        }
    });
}

// Patient Form Event Listeners
$(document).ready(function() {
    // Stepper navigation
    $('.stepper-item').on('click', function() {
        const step = parseInt($(this).data('step'));
        goToPatientFormStep(step);
    });

    // Next button
    $('#pf-btn-next').on('click', function() {
        // In emergency mode, navigate by sequence index rather than step number
        if ($('#patientFormModal').hasClass('pf-emergency-mode')) return;
        goToPatientFormStep(patientFormCurrentStep + 1);
    });

    // Previous button
    $('#pf-btn-prev').on('click', function() {
        if ($('#patientFormModal').hasClass('pf-emergency-mode')) return;
        goToPatientFormStep(patientFormCurrentStep - 1);
    });

    // Submit button
    $('#patient-form').on('submit', function(e) {
        e.preventDefault();
        submitPatientForm();
    });

    // File number mode buttons
    $(document).on('click', '.file-no-mode-btn', function() {
        const mode = $(this).data('mode');
        toggleFileNumberEdit(mode);
    });

    // ---- Age / DOB Toggle Widget ----
    var _pfAgeDobSyncing = false; // prevent infinite loops

    // Toggle between Age and DOB modes
    $(document).on('click', '.pf-adt-btn', function() {
        var mode = $(this).data('mode');
        $('.pf-adt-btn').removeClass('active');
        $(this).addClass('active');
        $('.pf-age-dob-wrapper').removeClass('mode-age mode-dob').addClass('mode-' + mode);
    });

    // Age input changed → compute DOB → sync
    $(document).on('input change', '#pf-age-val, #pf-age-unit', function() {
        if (_pfAgeDobSyncing) return;
        _pfAgeDobSyncing = true;

        var val = parseInt($('#pf-age-val').val());
        var unit = $('#pf-age-unit').val();
        if (!isNaN(val) && val >= 0) {
            var d = new Date();
            if (unit === 'years') d.setFullYear(d.getFullYear() - val);
            else if (unit === 'months') d.setMonth(d.getMonth() - val);
            else if (unit === 'days') d.setDate(d.getDate() - val);

            var iso = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
            $('#pf-dob').val(iso);

            // Show hint ≈ year
            var hint = unit === 'years' ? '≈ ' + d.getFullYear() : d.toLocaleDateString('en-GB', {day:'numeric', month:'short', year:'numeric'});
            $('#pf-age-dob-hint').html('<span class="badge bg-light text-secondary">' + hint + '</span>');

            updatePatientFormAge();
            checkDuplicatePatient();
        } else {
            $('#pf-dob').val('');
            $('#pf-age-dob-hint').html('');
            $('#pf-age-display').text('');
        }
        _pfAgeDobSyncing = false;
    });

    // DOB change → update age display + back-sync to age input
    $(document).on('change', '#pf-dob', function() {
        updatePatientFormAge();
        checkDuplicatePatient();

        if (_pfAgeDobSyncing) return;
        _pfAgeDobSyncing = true;
        var dob = $(this).val();
        if (dob) {
            var bd = new Date(dob), now = new Date();
            var diffMs = now - bd;
            var totalDays = Math.floor(diffMs / 86400000);
            if (totalDays < 91) {
                $('#pf-age-val').val(totalDays);
                $('#pf-age-unit').val('days');
            } else if (totalDays < 730) {
                $('#pf-age-val').val(Math.floor(totalDays / 30.44));
                $('#pf-age-unit').val('months');
            } else {
                var yrs = now.getFullYear() - bd.getFullYear();
                if (now.getMonth() < bd.getMonth() || (now.getMonth() === bd.getMonth() && now.getDate() < bd.getDate())) yrs--;
                $('#pf-age-val').val(yrs);
                $('#pf-age-unit').val('years');
            }
            $('#pf-age-dob-hint').html('');
        } else {
            $('#pf-age-val').val('');
            $('#pf-age-dob-hint').html('');
        }
        _pfAgeDobSyncing = false;

        // Also clear emergency approx-age if user typed exact DOB
        if ($(this).val() && pfEmergencyMode) { $('#pf-approx-age').val(''); }
    });

    // Duplicate detection: trigger on key fields
    $('#pf-surname, #pf-firstname').on('input', function() {
        checkDuplicatePatient();
    });
    $('#pf-phone').on('input', function() {
        checkDuplicatePatient();
    });

    // Dismiss duplicate panel
    $(document).on('click', '.pf-dup-dismiss', function() {
        _dupDismissed = true;
        $('#pf-duplicate-panel').slideUp(200);
    });

    // Select existing patient from duplicate suggestions
    $(document).on('click', '.pf-dup-item', function() {
        var patientId = $(this).data('patient-id');
        if (!patientId) return;
        $('#patientFormModal').modal('hide');
        if (window.patientFormConfig && typeof window.patientFormConfig.onSelectExisting === 'function') {
            window.patientFormConfig.onSelectExisting(patientId);
        }
    });

    // HMO change - show/hide HMO number field
    $('#pf-hmo').on('change', function() {
        if ($(this).val() && $(this).val() != 1) {
            $('#pf-hmo-no-container').show();
        } else {
            $('#pf-hmo-no-container').hide();
            $('#pf-hmo-no').val('');
        }
    });

    // ============================================
    // PHOTO CAPTURE FUNCTIONALITY (Upload & Webcam)
    // ============================================
    let webcamStream = null;
    let capturedPhotoBlob = null;

    // Photo capture tab switching
    $('.photo-capture-tab').on('click', function() {
        const panel = $(this).data('panel');

        // Update tabs
        $('.photo-capture-tab').removeClass('active');
        $(this).addClass('active');

        // Update panels
        $('.photo-capture-panel').removeClass('active');
        $(`#panel-${panel}`).addClass('active');

        // Stop webcam when switching away from webcam tab
        if (panel !== 'webcam' && webcamStream) {
            stopWebcam();
        }
    });

    // Upload dropzone functionality
    const dropzone = $('#photo-dropzone');
    const fileInput = $('#pf-passport');

    // Click to browse
    dropzone.on('click', function() {
        fileInput.click();
    });

    // Drag and drop
    dropzone.on('dragover dragenter', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('dragover');
    });

    dropzone.on('dragleave dragend drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
    });

    dropzone.on('drop', function(e) {
        const files = e.originalEvent.dataTransfer.files;
        if (files.length > 0 && files[0].type.startsWith('image/')) {
            fileInput[0].files = files;
            fileInput.trigger('change');
        }
    });

    // File input change handler
    fileInput.on('change', function() {
        const file = this.files[0];
        if (file) {
            if (file.size > 5 * 1024 * 1024) {
                toastr.error('File size must be less than 5MB');
                this.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                showPhotoPreview(e.target.result, 'Uploaded', file.name);
                capturedPhotoBlob = null; // Clear any webcam capture
            };
            reader.readAsDataURL(file);
        }
    });

    // Webcam functions
    function startWebcam() {
        navigator.mediaDevices.getUserMedia({
            video: {
                width: { ideal: 640 },
                height: { ideal: 480 },
                facingMode: 'user'
            }
        })
        .then(function(stream) {
            webcamStream = stream;
            const video = document.getElementById('pf-webcam-video');
            video.srcObject = stream;

            $('#webcam-placeholder').hide();
            $('#btn-start-webcam').addClass('d-none');
            $('#btn-capture-photo, #btn-stop-webcam').removeClass('d-none');
        })
        .catch(function(err) {
            console.error('Webcam error:', err);
            toastr.error('Could not access camera. Please check permissions.');
        });
    }

    function stopWebcam() {
        if (webcamStream) {
            webcamStream.getTracks().forEach(track => track.stop());
            webcamStream = null;
        }

        const video = document.getElementById('pf-webcam-video');
        video.srcObject = null;

        $('#webcam-placeholder').show();
        $('#btn-start-webcam').removeClass('d-none');
        $('#btn-capture-photo, #btn-stop-webcam').addClass('d-none');
    }

    function capturePhoto() {
        const video = document.getElementById('pf-webcam-video');
        const canvas = document.getElementById('pf-photo-canvas');
        const ctx = canvas.getContext('2d');

        // Set canvas size to video size
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        // Draw mirrored image
        ctx.translate(canvas.width, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(video, 0, 0);

        // Convert to blob
        canvas.toBlob(function(blob) {
            capturedPhotoBlob = blob;
            const url = URL.createObjectURL(blob);
            showPhotoPreview(url, 'Captured', 'webcam-photo.jpg');

            // Store base64 data for form submission
            const reader = new FileReader();
            reader.onloadend = function() {
                $('#pf-passport-data').val(reader.result);
            };
            reader.readAsDataURL(blob);

            // Stop webcam after capture
            stopWebcam();
        }, 'image/jpeg', 0.85);
    }

    function showPhotoPreview(src, source, filename) {
        // Hide capture panels and show preview
        $('.photo-capture-panel').removeClass('active');
        $('#photo-preview-wrapper').addClass('show');

        $('#photo-preview-img').attr('src', src);
        $('#photo-source-badge').html(`<i class="mdi mdi-check-circle"></i> ${source}`);
        $('#photo-filename').text(filename);
    }

    function resetPhotoCapture() {
        // Reset everything
        capturedPhotoBlob = null;
        $('#pf-passport').val('');
        $('#pf-passport-data').val('');
        $('#photo-preview-wrapper').removeClass('show');

        // Show first panel (upload)
        $('.photo-capture-tab').removeClass('active').first().addClass('active');
        $('.photo-capture-panel').removeClass('active');
        $('#panel-upload').addClass('active');

        stopWebcam();
    }

    // Webcam button handlers
    $('#btn-start-webcam').on('click', startWebcam);
    $('#btn-stop-webcam').on('click', stopWebcam);
    $('#btn-capture-photo').on('click', capturePhoto);

    // Photo preview actions
    $('#btn-change-photo').on('click', resetPhotoCapture);
    $('#btn-remove-photo').on('click', resetPhotoCapture);

    // Stop webcam when modal closes and restore default state
    $('#patientFormModal').on('hidden.bs.modal', function() {
        stopWebcam();
        // Restore file number controls (may have been hidden by ANC mode)
        $('.file-no-btn-group').show();
        $('#pf-file-no-info').show();
    });

    // Old records file preview - when new file selected
    $('#pf-old-records').on('change', function() {
        const file = this.files[0];
        if (file) {
            $('#old-records-new-name').text(file.name);
            $('#pf-old-records-new-preview').show();
            // Hide existing preview if showing
            $('.old-records-preview-container').hide();
        } else {
            $('#pf-old-records-new-preview').hide();
            // Show existing preview back if there was one
            if ($('#pf-old-records').data('existing')) {
                $('.old-records-preview-container').show();
            }
        }
    });

    // Cancel new old records selection - revert to existing
    $('#pf-cancel-old-records').on('click', function() {
        $('#pf-old-records').val('');
        $('#pf-old-records-new-preview').hide();
        // Show existing preview back if there was one
        if ($('#pf-old-records').data('existing')) {
            $('.old-records-preview-container').show();
        }
    });

    // Clear existing old records (mark for removal)
    $('#pf-clear-old-records').on('click', function() {
        $('#pf-old-records').val('').removeData('existing');
        $('.old-records-preview-container').hide();
        $('#pf-old-records-new-preview').hide();
    });

    // Allergies input
    $('#pf-allergy-input').on('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const allergy = $(this).val().trim();
            if (allergy && !patientFormAllergies.includes(allergy)) {
                patientFormAllergies.push(allergy);
                updateAllergiesTags();
            }
            $(this).val('');
        }
    });

    // Remove allergy
    $(document).on('click', '.remove-allergy', function() {
        const index = $(this).data('index');
        patientFormAllergies.splice(index, 1);
        updateAllergiesTags();
    });

    // Live validation
    $('#patient-form .form-control[required]').on('blur', function() {
        const $field = $(this);
        if ($field.val().trim()) {
            $field.removeClass('is-invalid').addClass('is-valid');
        } else {
            $field.removeClass('is-valid');
        }
    });

    // Floating labels - detect value
    $('#patient-form .form-control').on('input change', function() {
        if ($(this).val()) {
            $(this).addClass('has-value');
        } else {
            $(this).removeClass('has-value');
        }
    });
});

// =============================================
// EMERGENCY MODE LOGIC
// =============================================
(function() {
    'use strict';

    let pfEmergencyMode = false;
    let pfEmergencyTimerInterval = null;
    let pfEmergencyTimerSeconds = 0;
    let pfEmergencySearchTimeout = null;
    let pfDirectServiceSearchTimeout = null;
    let pfDirectServices = []; // [{type, id, name}]
    // Step sequence: normal = [1,2,3,4], emergency = [1,2,3,5,6,4]
    let pfStepSequence = [1, 2, 3, 4];

    const pfApproxAgeMap = {
        'neonate': 14, 'infant': 183, 'child_1_5': 1095, 'child_6_12': 3285,
        'adolescent': 5475, 'adult_18_30': 8760, 'adult_31_50': 14600,
        'adult_51_65': 21170, 'elderly': 27375
    };

    // Expose function to open modal in emergency mode
    window.showEmergencyIntakeModal = function() {
        enableEmergencyMode();
        showPatientFormModal('create');
    };

    function enableEmergencyMode() {
        pfEmergencyMode = true;
        pfStepSequence = [1, 2, 3, 5, 6, 4];

        var $modal = $('#patientFormModal');
        $modal.addClass('pf-emergency-mode');

        // Update title
        $('#patient-form-title').html('<i class="mdi mdi-ambulance mdi-24px"></i> Emergency / Walk-In Intake');

        // Show emergency stepper items (not form-steps — CSS .form-step handles those via .active class)
        $('.pf-emergency-stepper').show();

        // Update total steps for navigation
        window._pfTotalSteps = pfStepSequence.length;

        // Generate EX- prefixed file number for emergency patients
        generateEmergencyFileNumber();

        // Start timer when modal actually shows
        $modal.off('shown.bs.modal.emergency').on('shown.bs.modal.emergency', function() {
            startEmergencyTimer();
        });
    }

    function generateEmergencyFileNumber() {
        $.ajax({
            url: '/reception/patient/next-file-number',
            method: 'GET',
            data: { prefix: 'EX-' },
            success: function(response) {
                var nextFileNo = response.file_no;
                $('#pf-file-no').val(nextFileNo).addClass('status-valid');
                $('#pf-next-file-no').text(nextFileNo);
                $('#pf-duplicate-warning').hide();
                // Show recent EX- numbers as hint
                var recent = response.recent_file_nos || [];
                var lastTwo = recent.slice(0, 2);
                if (lastTwo.length > 0) {
                    $('#pf-file-no-hint').html('<small class="text-muted">Recent: ' + lastTwo.join(', ') + '</small>').show();
                }
            },
            error: function() {
                $('#pf-file-no').val('EX-001');
                $('#pf-next-file-no').text('EX-001');
            }
        });
    }

    function disableEmergencyMode() {
        pfEmergencyMode = false;
        pfStepSequence = [1, 2, 3, 4];

        var $modal = $('#patientFormModal');
        $modal.removeClass('pf-emergency-mode');

        // Hide emergency stepper items (form-steps handled by CSS .form-step via .active class)
        $('.pf-emergency-stepper').hide();

        // Reset summaries to default state
        $('#registration-summary').show();
        $('#emergency-intake-summary').hide();

        // Reset total steps
        window._pfTotalSteps = 4;

        stopEmergencyTimer();
        resetEmergencyFields();
    }

    // ---- Timer ----
    function startEmergencyTimer() {
        pfEmergencyTimerSeconds = 0;
        clearInterval(pfEmergencyTimerInterval);
        $('#pf-emergency-timer').text('00:00');
        pfEmergencyTimerInterval = setInterval(function() {
            pfEmergencyTimerSeconds++;
            var m = String(Math.floor(pfEmergencyTimerSeconds / 60)).padStart(2, '0');
            var s = String(pfEmergencyTimerSeconds % 60).padStart(2, '0');
            $('#pf-emergency-timer').text(m + ':' + s);
        }, 1000);
    }

    function stopEmergencyTimer() {
        clearInterval(pfEmergencyTimerInterval);
        pfEmergencyTimerInterval = null;
    }

    // ---- Patient Search (emergency) ----
    $(document).on('input', '#pf-emergency-patient-search', function() {
        clearTimeout(pfEmergencySearchTimeout);
        var query = $(this).val().trim();
        if (query.length < 2) { $('#pf-emergency-patient-results').hide(); return; }

        pfEmergencySearchTimeout = setTimeout(function() {
            $.get('/emergency/search-patient', { q: query }, function(patients) {
                var $results = $('#pf-emergency-patient-results').empty();
                if (patients.length === 0) {
                    $results.html('<div class="list-group-item text-muted text-center">No patients found</div>');
                } else {
                    patients.forEach(function(p) {
                        $results.append(
                            '<a href="#" class="list-group-item list-group-item-action pf-emergency-patient-item py-1"' +
                            ' data-id="' + p.id + '" data-name="' + escapeHtml(p.name) + '" data-fileno="' + escapeHtml(p.file_no) + '"' +
                            ' data-phone="' + escapeHtml(p.phone || '') + '" data-hmo="' + escapeHtml(p.hmo || '') + '" data-allergies="' + escapeHtml(p.allergies || '') + '">' +
                            '<div class="d-flex justify-content-between align-items-center">' +
                            '<div><strong>' + escapeHtml(p.name) + '</strong>' +
                            '<small class="d-block text-muted">' + escapeHtml(p.file_no) + ' | ' + (p.gender || '') + ' | ' + escapeHtml(p.phone || '') + '</small></div>' +
                            '<span class="badge bg-secondary">' + escapeHtml(p.hmo || 'Private') + '</span>' +
                            '</div></a>'
                        );
                    });
                }
                $results.show();
            });
        }, 300);
    });

    // Select patient from emergency search
    $(document).on('click', '.pf-emergency-patient-item', function(e) {
        e.preventDefault();
        var $el = $(this);
        var name = $el.data('name');
        $('#pf-emergency-patient-id').val($el.data('id'));
        $('#pf-emergency-patient-name').text(name);
        $('#pf-emergency-patient-fileno').text($el.data('fileno'));
        $('#pf-emergency-patient-phone').text($el.data('phone'));
        $('#pf-emergency-patient-hmo').text($el.data('hmo') || 'Private');
        // Avatar initials
        var initials = name ? name.split(' ').map(function(w){ return w[0]; }).join('').substring(0,2).toUpperCase() : '?';
        $('#pf-emergency-patient-avatar').text(initials);
        $('#pf-emergency-selected-patient').addClass('show');
        $('#pf-emergency-patient-results').hide();
        $('#pf-emergency-patient-search').val('');
        $('#pf-existing-empty-state').hide();

        // Pre-fill allergies if existing
        var allergies = $el.data('allergies');
        if (allergies && allergies !== 'null' && String(allergies).length > 2) {
            $('#pf-allergy-has').prop('checked', true).trigger('change');
            var clean = String(allergies);
            try { var arr = JSON.parse(clean); if (Array.isArray(arr)) clean = arr.join(', '); } catch(e) {}
            $('#pf-allergies-text').val(clean);
            $('#pf-allergy-text-input').show();
        }

        // Hide new-patient form fields when existing selected
        $('#pf-new-patient-wrapper').addClass('collapsed');
    });

    // Clear selected patient
    $(document).on('click', '#pf-emergency-clear-patient', function() {
        $('#pf-emergency-patient-id').val('');
        $('#pf-emergency-selected-patient').removeClass('show');
        $('#pf-existing-empty-state').show();
        // Don't uncollapse wrapper — user stays on "existing" tab
    });

    // ---- Patient Chooser Tab Switching ----
    $(document).on('click', '.pf-chooser-tab', function() {
        var panel = $(this).data('panel');
        $('#pf-patient-chooser-mode').val(panel);

        // Switch active tab
        $('.pf-chooser-tab').removeClass('active');
        $(this).addClass('active');

        // Switch panel
        $('.pf-chooser-panel').removeClass('active');
        $('.pf-chooser-panel[data-panel="' + panel + '"]').addClass('active');

        // Update body border color
        $('#pf-chooser-body').removeClass('border-existing border-new border-unidentified')
            .addClass('border-' + panel);

        // Reset unidentified class on wrapper
        $('#pf-new-patient-wrapper').removeClass('pf-unidentified-active');

        if (panel === 'existing') {
            // Collapse new-patient wrapper (existing patient already selected or searching)
            $('#pf-new-patient-wrapper').addClass('collapsed');
            $('#pf-is-unidentified').val('0');
            // Restore name fields if they were set for unidentified
            if ($('#pf-surname').val() === 'Unknown') $('#pf-surname').val('');
            if ($('#pf-firstname').val() === 'Patient') $('#pf-firstname').val('');
        } else if (panel === 'new') {
            // Clear any selected existing patient
            $('#pf-emergency-patient-id').val('');
            $('#pf-emergency-selected-patient').removeClass('show');
            $('#pf-existing-empty-state').show();
            // Show full new patient form fields
            $('#pf-new-patient-wrapper').removeClass('collapsed');
            $('#pf-is-unidentified').val('0');
            // Restore name fields if they were set for unidentified
            if ($('#pf-surname').val() === 'Unknown') $('#pf-surname').val('');
            if ($('#pf-firstname').val() === 'Patient') $('#pf-firstname').val('');
        } else if (panel === 'unidentified') {
            // Clear any selected existing patient
            $('#pf-emergency-patient-id').val('');
            $('#pf-emergency-selected-patient').removeClass('show');
            $('#pf-existing-empty-state').show();
            // Show wrapper but in unidentified mode — only Gender, Approx Age, Phone visible
            $('#pf-new-patient-wrapper').removeClass('collapsed').addClass('pf-unidentified-active');
            $('#pf-is-unidentified').val('1');
            $('#pf-surname').val('Unknown');
            $('#pf-firstname').val('Patient');
            // Sync unidentified gender field from main gender if set
            $('#pf-gender-unid').val($('#pf-gender').val());
        }
    });

    // Sync unidentified-only fields back to main fields
    $(document).on('change', '#pf-gender-unid', function() {
        $('#pf-gender').val($(this).val());
    });
    $(document).on('change', '#pf-approx-age-unid', function() {
        var key = $(this).val();
        $('#pf-approx-age').val(key); // sync to main approx age
        if (key && pfApproxAgeMap[key]) {
            var d = new Date();
            d.setDate(d.getDate() - pfApproxAgeMap[key]);
            var iso = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
            $('#pf-dob').val(iso).trigger('change');
        }
    });
    $(document).on('input', '#pf-phone-unid', function() {
        $('#pf-phone').val($(this).val());
    });

    // ---- Approx Age → DOB ----
    $(document).on('change', '#pf-approx-age', function() {
        var key = $(this).val();
        if (key && pfApproxAgeMap[key]) {
            var d = new Date();
            d.setDate(d.getDate() - pfApproxAgeMap[key]);
            var iso = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
            $('#pf-dob').val(iso).trigger('change');
        }
    });

    // ---- ESI Level Selection ----
    $(document).on('click', '.pf-esi-btn', function() {
        $('.pf-esi-btn').removeClass('selected');
        $(this).addClass('selected');
        $('#pf-esi-level').val($(this).data('esi'));

        var hint = $(this).data('hint');
        if (hint) {
            $('#pf-esi-hint-text').text(hint);
            $('#pf-esi-hint-box').slideDown(150);
        }

        // Auto-expand GCS + vitals for ESI 1-2
        var esi = parseInt($(this).data('esi'));
        if (esi <= 2) {
            $('#pf-vitals-panel').collapse('show');
            $('#pf-gcs-panel').collapse('show');
        }
    });

    // ---- GCS Auto-Calculation ----
    $(document).on('change', '.pf-gcs-input', function() {
        var eye = parseInt($('#pf-gcs-eye').val()) || 0;
        var verbal = parseInt($('#pf-gcs-verbal').val()) || 0;
        var motor = parseInt($('#pf-gcs-motor').val()) || 0;

        if (eye && verbal && motor) {
            var total = eye + verbal + motor;
            $('#pf-gcs-total').val(total);
            $('#pf-gcs-total-val').val(total);
            var $el = $('#pf-gcs-total');
            $el.removeClass('pf-gcs-severe pf-gcs-moderate pf-gcs-mild');
            if (total <= 8) $el.addClass('pf-gcs-severe');
            else if (total <= 12) $el.addClass('pf-gcs-moderate');
            else $el.addClass('pf-gcs-mild');
        } else {
            $('#pf-gcs-total').val('--').removeClass('pf-gcs-severe pf-gcs-moderate pf-gcs-mild');
            $('#pf-gcs-total-val').val('');
        }
    });

    // Pain scale display
    $(document).on('input', '#pf-pain-scale', function() {
        $('#pf-pain-display').text($(this).val());
    });

    // Allergy radio toggle
    $(document).on('change', 'input[name="pf_allergy_status"]', function() {
        if ($(this).val() === 'has_allergies') {
            $('#pf-allergy-text-input').slideDown(150);
        } else {
            $('#pf-allergy-text-input').slideUp(150);
        }
    });

    // ---- Disposition Toggle ----
    $(document).on('change', '.pf-disposition-radio', function() {
        var val = $(this).val();
        $('#pf-admit-options, #pf-consult-options, #pf-direct-options').hide();
        if (val === 'admit_emergency') { $('#pf-admit-options').slideDown(200); loadDispositionData(); }
        else if (val === 'queue_consultation') { $('#pf-consult-options').slideDown(200); loadDispositionData(); }
        else if (val === 'direct_service') { $('#pf-direct-options').slideDown(200); }
    });

    var pfDispositionLoaded = false;
    function loadDispositionData() {
        if (pfDispositionLoaded) return;
        pfDispositionLoaded = true;

        $.get('/emergency/available-beds', function(beds) {
            var $sel = $('#pf-bed-select').empty().append('<option value="">-- No bed (assign later) --</option>');
            beds.forEach(function(b) {
                $sel.append('<option value="' + b.id + '">' + escapeHtml(b.name) + ' — ' + escapeHtml(b.ward) + ' (' + escapeHtml(b.bed_type) + ')</option>');
            });
        });

        $.get('/emergency/clinics', function(clinics) {
            var opts = '<option value="">-- Select Clinic --</option>';
            clinics.forEach(function(c) { opts += '<option value="' + c.id + '">' + escapeHtml(c.name) + '</option>'; });
            $('#pf-clinic-select').html(opts);
            $('#pf-admit-clinic-select').html(opts);
        });

        $.get('/emergency/services', function(data) {
            var $admitSvc = $('#pf-admit-service-select').empty().append('<option value="">-- Select Service --</option>');
            if (data.admission) data.admission.forEach(function(s) {
                $admitSvc.append('<option value="' + s.id + '">' + escapeHtml(s.name) + ' — ₦' + Number(s.price).toLocaleString() + '</option>');
            });
            var $consultSvc = $('#pf-service-select').empty().append('<option value="">-- Select Service --</option>');
            if (data.consultation) data.consultation.forEach(function(s) {
                $consultSvc.append('<option value="' + s.id + '">' + escapeHtml(s.name) + ' — ₦' + Number(s.price).toLocaleString() + '</option>');
            });
        });
    }

    // ---- Direct Service Search ----
    $(document).on('input', '#pf-direct-service-search', function() {
        clearTimeout(pfDirectServiceSearchTimeout);
        var query = $(this).val().trim();
        if (query.length < 2) { $('#pf-direct-service-results').hide(); return; }

        pfDirectServiceSearchTimeout = setTimeout(function() {
            var labUrl = '/reception/services/lab';
            var imgUrl = '/reception/services/imaging';
            Promise.all([$.get(labUrl, {q: query}), $.get(imgUrl, {q: query})]).then(function(results) {
                var $results = $('#pf-direct-service-results').empty();
                results[0].forEach(function(s) {
                    if (!pfDirectServices.find(function(x){ return x.type==='lab' && x.id===s.id; })) {
                        $results.append('<a href="#" class="list-group-item list-group-item-action pf-add-direct-service py-1" data-type="lab" data-id="'+s.id+'" data-name="'+escapeHtml(s.name)+'"><span class="badge bg-primary me-1">LAB</span> '+escapeHtml(s.name)+'</a>');
                    }
                });
                results[1].forEach(function(s) {
                    if (!pfDirectServices.find(function(x){ return x.type==='imaging' && x.id===s.id; })) {
                        $results.append('<a href="#" class="list-group-item list-group-item-action pf-add-direct-service py-1" data-type="imaging" data-id="'+s.id+'" data-name="'+escapeHtml(s.name)+'"><span class="badge bg-info me-1">IMG</span> '+escapeHtml(s.name)+'</a>');
                    }
                });
                if ($results.children().length === 0) {
                    $results.html('<div class="list-group-item text-muted text-center">No services found</div>');
                }
                $results.show();
            });
        }, 300);
    });

    $(document).on('click', '.pf-add-direct-service', function(e) {
        e.preventDefault();
        pfDirectServices.push({ type: $(this).data('type'), id: $(this).data('id'), name: $(this).data('name') });
        renderDirectServices();
        $('#pf-direct-service-results').hide();
        $('#pf-direct-service-search').val('');
    });

    $(document).on('click', '.pf-remove-service', function() {
        pfDirectServices.splice($(this).data('index'), 1);
        renderDirectServices();
    });

    function renderDirectServices() {
        var $c = $('#pf-selected-direct-services').empty();
        if (pfDirectServices.length === 0) { $c.html('<small class="text-muted">No services selected</small>'); return; }
        pfDirectServices.forEach(function(s, i) {
            var badge = s.type === 'lab' ? 'bg-primary' : 'bg-info';
            $c.append('<span class="pf-service-chip"><span class="badge '+badge+' me-1">'+s.type.toUpperCase()+'</span>'+escapeHtml(s.name)+' <span class="pf-remove-service" data-index="'+i+'"><i class="mdi mdi-close-circle"></i></span></span>');
        });
    }

    // ---- Override step navigation for emergency mode ----
    var _origGoToStep = window.goToPatientFormStep || goToPatientFormStep;

    // Patch the global goToPatientFormStep if emergency mode is active
    var origGoTo = goToPatientFormStep;
    goToPatientFormStep = function(step) {
        if (!pfEmergencyMode) { return origGoTo(step); }

        // Map logical index to step number
        var currentIdx = pfStepSequence.indexOf(patientFormCurrentStep);
        var targetIdx = -1;

        // If step is being called as a step NUMBER (from stepper clicks), find its index
        if (pfStepSequence.indexOf(step) !== -1) {
            targetIdx = pfStepSequence.indexOf(step);
        } else {
            return; // Invalid step
        }

        // Validate when moving forward
        if (targetIdx > currentIdx) {
            for (var i = currentIdx; i < targetIdx; i++) {
                if (!validateEmergencyStep(pfStepSequence[i])) return;
            }
        }

        patientFormCurrentStep = step;

        // Show the step
        $('.form-step').removeClass('active');
        $('.form-step[data-step="' + step + '"]').addClass('active');

        // Update stepper
        updateEmergencyStepper();

        // Update navigation
        updateEmergencyNavigation();

        // Scroll to top
        $('.form-steps-container').scrollTop(0);
    };

    function updateEmergencyStepper() {
        var currentIdx = pfStepSequence.indexOf(patientFormCurrentStep);
        // Update all stepper items that are in our sequence
        $('.stepper-item').each(function() {
            var stepNum = parseInt($(this).data('step'));
            var stepIdx = pfStepSequence.indexOf(stepNum);
            $(this).removeClass('active completed');
            if (stepIdx === -1) return; // Not in current sequence
            if (stepIdx < currentIdx) $(this).addClass('completed');
            else if (stepIdx === currentIdx) $(this).addClass('active');
        });

        // Update stepper lines
        var lineIdx = 0;
        $('.stepper-line:visible').each(function() {
            $(this).removeClass('completed');
            if (lineIdx < currentIdx) $(this).addClass('completed');
            lineIdx++;
        });
    }

    function updateEmergencyNavigation() {
        var currentIdx = pfStepSequence.indexOf(patientFormCurrentStep);
        var isFirst = (currentIdx === 0);
        var isLast = (currentIdx === pfStepSequence.length - 1);

        if (isFirst) $('#pf-btn-prev').hide(); else $('#pf-btn-prev').show();

        if (isLast) {
            $('#pf-btn-next').hide();
            $('#pf-btn-submit').show();
            $('#pf-submit-text').text('Submit Emergency Intake');
            // Show emergency summary instead of registration summary
            $('#registration-summary').hide();
            $('#emergency-intake-summary').show();
            updateEmergencyIntakeSummary();
        } else {
            $('#pf-btn-next').show();
            $('#pf-btn-submit').hide();
        }
    }

    function validateEmergencyStep(step) {
        if (step === 1) {
            // In emergency mode, either existing patient or new patient info
            var hasExisting = !!$('#pf-emergency-patient-id').val();
            if (hasExisting) return true;

            var isUnidentified = $('#pf-is-unidentified').val() === '1';
            if (!isUnidentified) {
                if (!$('#pf-surname').val().trim() || !$('#pf-firstname').val().trim()) {
                    toastr.warning('Surname and First Name are required.');
                    return false;
                }
            }
            if (!$('#pf-gender').val()) {
                toastr.warning('Gender is required.');
                return false;
            }
            // DOB optional in emergency (may use approx age)
            return true;
        }
        if (step === 5) {
            // Triage: ESI + chief complaint required
            if (!$('#pf-esi-level').val()) {
                toastr.warning('Please select an ESI triage level.');
                return false;
            }
            if (!$('#pf-chief-complaint').val().trim()) {
                toastr.warning('Chief complaint is required.');
                return false;
            }
            return true;
        }
        if (step === 6) {
            // Disposition validation
            var disp = $('input[name="pf_disposition"]:checked').val();
            if (!disp) { toastr.warning('Please select a disposition.'); return false; }
            if (disp === 'admit_emergency') {
                if (!$('#pf-admit-service-select').val()) { toastr.warning('Admission service is required.'); return false; }
                if (!$('#pf-admit-clinic-select').val()) { toastr.warning('Clinic is required.'); return false; }
            }
            if (disp === 'queue_consultation') {
                if (!$('#pf-clinic-select').val()) { toastr.warning('Clinic is required.'); return false; }
                if (!$('#pf-service-select').val()) { toastr.warning('Service is required.'); return false; }
            }
            if (disp === 'direct_service' && pfDirectServices.length === 0) {
                toastr.warning('Add at least one lab or imaging service.');
                return false;
            }
            return true;
        }
        // For other steps, delegate to original validation
        return validatePatientFormStep(step);
    }

    // Override Next/Prev for emergency mode
    $(document).off('click.pfEmergencyNav').on('click.pfEmergencyNav', '#pf-btn-next', function() {
        if (!pfEmergencyMode) return; // Let original handler work
        var currentIdx = pfStepSequence.indexOf(patientFormCurrentStep);
        if (currentIdx < pfStepSequence.length - 1) {
            goToPatientFormStep(pfStepSequence[currentIdx + 1]);
        }
    });

    $(document).off('click.pfEmergencyPrev').on('click.pfEmergencyPrev', '#pf-btn-prev', function() {
        if (!pfEmergencyMode) return;
        var currentIdx = pfStepSequence.indexOf(patientFormCurrentStep);
        if (currentIdx > 0) {
            goToPatientFormStep(pfStepSequence[currentIdx - 1]);
        }
    });

    // ---- Override form submission for emergency mode ----
    var origSubmit = submitPatientForm;
    submitPatientForm = function() {
        if (!pfEmergencyMode) { return origSubmit(); }

        // Emergency two-phase submit:
        // Phase 1: Register patient (if new) via existing endpoint
        // Phase 2: Submit emergency intake (triage + disposition) to /emergency/intake
        var existingPatientId = $('#pf-emergency-patient-id').val();
        var $btn = $('#pf-btn-submit');
        var originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing...');

        if (existingPatientId) {
            // Skip patient creation — go straight to emergency intake
            submitEmergencyIntake(existingPatientId, $btn, originalHtml);
        } else {
            // Phase 1: Create the patient first
            var isUnidentified = $('#pf-is-unidentified').val() == '1';

            // For unidentified patients without DOB, default to adult (~30 years)
            if (isUnidentified && !$('#pf-dob').val()) {
                var d = new Date();
                d.setFullYear(d.getFullYear() - 30);
                var iso = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
                $('#pf-dob').val(iso);
            }

            // For unidentified, pack distinguishing features + unidentified flag into misc
            var miscVal = $('#pf-misc').val().trim();
            if (isUnidentified) {
                var miscObj = {};
                if (miscVal) {
                    try { miscObj = JSON.parse(miscVal); } catch(e) { miscObj = { notes: miscVal }; }
                }
                miscObj.unidentified = true;
                miscObj.distinguishing_features = $('#pf-distinguishing-features').val() || '';
                miscObj.arrival_mode = $('#pf-arrival-mode').val() || '';
                miscObj.approx_age = $('#pf-approx-age').val() || '';
                miscVal = JSON.stringify(miscObj);
            }

            var formData = new FormData();
            formData.append('_token', $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}');
            formData.append('file_no', $('#pf-file-no').val());
            formData.append('surname', $('#pf-surname').val().trim());
            formData.append('firstname', $('#pf-firstname').val().trim());
            formData.append('othername', $('#pf-othername').val().trim());
            formData.append('gender', $('#pf-gender').val());
            formData.append('dob', $('#pf-dob').val());
            formData.append('phone_no', $('#pf-phone').val().trim());
            formData.append('email', $('#pf-email').val().trim());
            formData.append('address', $('#pf-address').val().trim());
            formData.append('blood_group', $('#pf-blood-group').val());
            formData.append('genotype', $('#pf-genotype').val());
            formData.append('disability', $('#pf-disability').val());
            formData.append('nationality', $('#pf-nationality').val());
            formData.append('ethnicity', $('#pf-ethnicity').val());
            formData.append('allergies', JSON.stringify(patientFormAllergies));
            formData.append('medical_history', $('#pf-medical-history').val().trim());
            formData.append('misc', miscVal);
            formData.append('next_of_kin_name', $('#pf-nok-name').val().trim());
            formData.append('next_of_kin_phone', $('#pf-nok-phone').val().trim());
            formData.append('next_of_kin_address', $('#pf-nok-address').val().trim());
            formData.append('hmo_id', $('#pf-hmo').val() || 1);
            formData.append('hmo_no', $('#pf-hmo-no').val().trim());

            // File uploads
            var passportFile = $('#pf-passport')[0].files[0];
            var passportData = $('#pf-passport-data').val();
            if (passportFile) formData.append('filename', passportFile);
            else if (passportData) formData.append('passport_data', passportData);
            var oldRecords = $('#pf-old-records')[0].files[0];
            if (oldRecords) formData.append('old_records', oldRecords);

            $.ajax({
                url: patientFormConfig.registerUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success && response.patient) {
                        submitEmergencyIntake(response.patient.id, $btn, originalHtml);
                    } else {
                        toastr.error(response.message || 'Patient registration failed.');
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function(xhr) {
                    var errors = xhr.responseJSON?.errors;
                    if (errors) {
                        Object.values(errors).forEach(function(err) { toastr.error(err[0]); });
                    } else {
                        toastr.error(xhr.responseJSON?.message || 'Patient registration failed.');
                    }
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        }
    };

    function submitEmergencyIntake(patientId, $btn, originalHtml) {
        var disposition = $('input[name="pf_disposition"]:checked').val();

        var intakeData = {
            _token: $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}',
            patient_id: patientId,
            is_new_patient: 0,
            is_unidentified: $('#pf-is-unidentified').val() == '1' ? 1 : 0,
            // Triage
            esi_level: $('#pf-esi-level').val(),
            chief_complaint: $('#pf-chief-complaint').val(),
            triage_notes: $('#pf-triage-notes').val(),
            // Vitals
            vital_hr: $('#pf-vital-hr').val() || null,
            vital_bp_sys: $('#pf-vital-bp-sys').val() || null,
            vital_bp_dia: $('#pf-vital-bp-dia').val() || null,
            vital_spo2: $('#pf-vital-spo2').val() || null,
            vital_temp: $('#pf-vital-temp').val() || null,
            vital_rr: $('#pf-vital-rr').val() || null,
            vital_bs: $('#pf-vital-bs').val() || null,
            // GCS + Pain
            gcs_eye: $('#pf-gcs-eye').val() || null,
            gcs_verbal: $('#pf-gcs-verbal').val() || null,
            gcs_motor: $('#pf-gcs-motor').val() || null,
            gcs_total: $('#pf-gcs-total-val').val() || null,
            pain_scale: $('#pf-pain-scale').val(),
            // Allergies
            allergy_status: $('input[name="pf_allergy_status"]:checked').val(),
            allergies_text: $('#pf-allergies-text').val(),
            // Arrival
            arrival_mode: $('#pf-arrival-mode').val(),
            brought_by_name: $('#pf-brought-by-name').val(),
            brought_by_phone: $('#pf-brought-by-phone').val(),
            distinguishing_features: $('#pf-distinguishing-features').val(),
            // Disposition
            disposition: disposition,
            clinic_id: $('#pf-clinic-select').val() || null,
            service_id: $('#pf-service-select').val() || null,
            admit_service_id: $('#pf-admit-service-select').val() || null,
            admit_clinic_id: $('#pf-admit-clinic-select').val() || null,
            bed_id: $('#pf-bed-select').val() || null,
            elapsed_seconds: pfEmergencyTimerSeconds
        };

        if (disposition === 'direct_service') {
            intakeData.direct_services = pfDirectServices.map(function(s) { return {type: s.type, id: s.id}; });
        }

        $.ajax({
            url: '/emergency/intake',
            method: 'POST',
            data: JSON.stringify(intakeData),
            contentType: 'application/json',
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message || 'Emergency intake completed.');
                    $('#patientFormModal').modal('hide');

                    if (typeof patientFormConfig.onSuccess === 'function') {
                        patientFormConfig.onSuccess(patientId, 'emergency');
                    }
                    if (typeof loadPatient === 'function') loadPatient(patientId);
                    else if (typeof selectPatient === 'function') selectPatient(patientId);
                    if (typeof loadQueueCounts === 'function') loadQueueCounts();
                } else {
                    toastr.error(response.message || 'Emergency intake failed.');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON?.message || 'Server error during emergency intake.';
                if (xhr.responseJSON?.errors) {
                    Object.values(xhr.responseJSON.errors).flat().forEach(function(e) { toastr.error(e); });
                } else {
                    toastr.error(msg);
                }
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    }

    // ---- Emergency Intake Summary ----
    function updateEmergencyIntakeSummary() {
        var existingId = $('#pf-emergency-patient-id').val();

        // Patient info
        if (existingId) {
            $('#emg-summary-name').text($('#pf-emergency-patient-name').text() || '-');
            $('#emg-summary-fileno').text($('#pf-emergency-patient-fileno').text() || '-');
            $('#emg-summary-gender').text('-');
            $('#emg-summary-patient-type').html('<span class="badge bg-info">Existing Patient</span>');
        } else {
            var fullName = [$('#pf-surname').val(), $('#pf-firstname').val(), $('#pf-othername').val()].filter(Boolean).join(' ');
            $('#emg-summary-name').text(fullName || '-');
            $('#emg-summary-fileno').text($('#pf-file-no').val() || '-');
            $('#emg-summary-gender').text($('#pf-gender').val() || '-');
            var isUnidentified = $('#pf-is-unidentified').val() === '1';
            $('#emg-summary-patient-type').html(isUnidentified
                ? '<span class="badge bg-warning text-dark">Unidentified</span>'
                : '<span class="badge bg-success">New Patient</span>');
        }

        // Triage
        var esi = $('#pf-esi-level').val();
        var esiLabels = {1:'1 - Resuscitation',2:'2 - Emergent',3:'3 - Urgent',4:'4 - Less Urgent',5:'5 - Non-Urgent'};
        var esiColors = {1:'danger',2:'danger',3:'warning',4:'info',5:'success'};
        if (esi) {
            $('#emg-summary-esi').html('<span class="badge bg-' + (esiColors[esi]||'secondary') + '">' + (esiLabels[esi]||esi) + '</span>');
        } else {
            $('#emg-summary-esi').text('-');
        }
        $('#emg-summary-complaint').text($('#pf-chief-complaint').val() || '-');
        var gcs = $('#pf-gcs-total-val').val();
        $('#emg-summary-gcs').text(gcs ? gcs + '/15' : 'Not assessed');
        $('#emg-summary-pain').text($('#pf-pain-scale').val() > 0 ? $('#pf-pain-scale').val() + '/10' : '0/10');

        var allergyStatus = $('input[name="pf_allergy_status"]:checked').val();
        if (allergyStatus === 'nkda') $('#emg-summary-allergy').html('<span class="badge bg-success">NKDA</span>');
        else if (allergyStatus === 'has_allergies') $('#emg-summary-allergy').html('<span class="badge bg-danger">' + ($('#pf-allergies-text').val() || 'Has Allergies') + '</span>');
        else $('#emg-summary-allergy').html('<span class="badge bg-secondary">Unknown</span>');

        // Vitals
        var vitals = [];
        var hr = $('#pf-vital-hr').val(); if (hr) vitals.push({label:'HR', val: hr + ' bpm'});
        var bps = $('#pf-vital-bp-sys').val(); var bpd = $('#pf-vital-bp-dia').val();
        if (bps && bpd) vitals.push({label:'BP', val: bps + '/' + bpd + ' mmHg'});
        var spo2 = $('#pf-vital-spo2').val(); if (spo2) vitals.push({label:'SpO2', val: spo2 + '%'});
        var temp = $('#pf-vital-temp').val(); if (temp) vitals.push({label:'Temp', val: temp + '°C'});
        var rr = $('#pf-vital-rr').val(); if (rr) vitals.push({label:'RR', val: rr + '/min'});
        var bs = $('#pf-vital-bs').val(); if (bs) vitals.push({label:'BS', val: bs + ' mg/dl'});

        if (vitals.length > 0) {
            var html = '';
            vitals.forEach(function(v) {
                html += '<div class="summary-item"><span class="summary-label">' + v.label + ':</span><span class="summary-value">' + v.val + '</span></div>';
            });
            $('#emg-summary-vitals-grid').html(html);
            $('#emg-summary-vitals-section').show();
        } else {
            $('#emg-summary-vitals-section').hide();
        }

        // Disposition
        var disp = $('input[name="pf_disposition"]:checked').val();
        var dispLabels = {
            'admit_emergency': '<i class="mdi mdi-bed text-danger"></i> Admit to Emergency Ward',
            'queue_consultation': '<i class="mdi mdi-account-clock text-warning"></i> Queue for Consultation',
            'direct_service': '<i class="mdi mdi-flask text-info"></i> Direct to Lab/Imaging'
        };
        $('#emg-summary-disposition').html(dispLabels[disp] || '-');

        var detail = '';
        if (disp === 'admit_emergency') {
            var svc = $('#pf-admit-service-select option:selected').text();
            var clinic = $('#pf-admit-clinic-select option:selected').text();
            var bed = $('#pf-bed-select option:selected').text();
            detail = [svc, clinic, bed].filter(function(x){ return x && !x.startsWith('--'); }).join(' | ');
        } else if (disp === 'queue_consultation') {
            var clinic = $('#pf-clinic-select option:selected').text();
            var svc = $('#pf-service-select option:selected').text();
            detail = [clinic, svc].filter(function(x){ return x && !x.startsWith('--'); }).join(' | ');
        } else if (disp === 'direct_service') {
            detail = pfDirectServices.map(function(s){ return s.type.toUpperCase() + ': ' + s.name; }).join(', ');
        }
        if (detail) {
            $('#emg-summary-disposition-detail').text(detail);
            $('#emg-summary-disposition-detail-row').show();
        } else {
            $('#emg-summary-disposition-detail-row').hide();
        }

        // Elapsed time
        var m = String(Math.floor(pfEmergencyTimerSeconds / 60)).padStart(2, '0');
        var s = String(pfEmergencyTimerSeconds % 60).padStart(2, '0');
        $('#emg-summary-elapsed').text(m + ':' + s);
    }

    // ---- Reset emergency fields ----
    function resetEmergencyFields() {
        // Patient chooser tabs — reset to "existing" tab
        $('.pf-chooser-tab').removeClass('active');
        $('.pf-chooser-tab[data-panel="existing"]').addClass('active');
        $('.pf-chooser-panel').removeClass('active');
        $('.pf-chooser-panel[data-panel="existing"]').addClass('active');
        $('#pf-chooser-body').removeClass('border-new border-unidentified').addClass('border-existing');
        $('#pf-patient-chooser-mode').val('existing');
        $('#pf-existing-empty-state').show();

        // Patient search
        $('#pf-emergency-patient-id').val('');
        $('#pf-emergency-selected-patient').removeClass('show');
        $('#pf-emergency-patient-search').val('');
        $('#pf-emergency-patient-results').hide();
        $('#pf-new-patient-wrapper').removeClass('collapsed pf-unidentified-active');

        // Identity mode
        $('#pf-is-unidentified').val('0');
        $('#pf-distinguishing-features').val('');
        // Clear unidentified-only fields
        $('#pf-gender-unid').val('');
        $('#pf-approx-age-unid').val('');
        $('#pf-phone-unid').val('');

        // Arrival
        $('#pf-approx-age').val('');
        $('#pf-arrival-mode').val('walk_in');
        $('#pf-brought-by-name').val('');
        $('#pf-brought-by-phone').val('');

        // Triage
        $('#pf-esi-level').val('');
        $('.pf-esi-btn').removeClass('selected');
        $('#pf-esi-hint-box').hide();
        $('#pf-chief-complaint').val('');
        $('#pf-vital-hr, #pf-vital-bp-sys, #pf-vital-bp-dia, #pf-vital-spo2, #pf-vital-temp, #pf-vital-rr, #pf-vital-bs').val('');
        $('#pf-gcs-eye, #pf-gcs-verbal, #pf-gcs-motor').val('');
        $('#pf-gcs-total').val('--').removeClass('pf-gcs-severe pf-gcs-moderate pf-gcs-mild');
        $('#pf-gcs-total-val').val('');
        $('#pf-pain-scale').val(0);
        $('#pf-pain-display').text('0');
        $('input[name="pf_allergy_status"][value="nkda"]').prop('checked', true);
        $('#pf-allergy-text-input').hide();
        $('#pf-allergies-text').val('');
        $('#pf-triage-notes').val('');
        $('#pf-vitals-panel, #pf-gcs-panel').collapse('hide');

        // Disposition
        $('input[name="pf_disposition"]').prop('checked', false);
        $('#pf-admit-options, #pf-consult-options, #pf-direct-options').hide();
        pfDirectServices = [];
        renderDirectServices();
        pfDispositionLoaded = false;
    }

    // Reset on modal close
    $('#patientFormModal').on('hidden.bs.modal', function() {
        if (pfEmergencyMode) {
            disableEmergencyMode();
        }
    });

})();


</script>
@endpush

<div class="modal fade" id="patientFormModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header" id="patient-form-header">
                <h5 class="modal-title" id="patient-form-title"><i class="mdi mdi-account-plus"></i> New Patient Registration</h5>
                <span class="pf-emergency-timer badge text-white ms-2" id="pf-emergency-timer">00:00</span>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">&times;</button>
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
                        <div class="stepper-item" data-step="2">
                            <div class="stepper-icon"><i class="mdi mdi-clipboard-pulse"></i></div>
                            <div class="stepper-label">Medical</div>
                        </div>
                        <div class="stepper-line"></div>
                        <div class="stepper-item" data-step="3">
                            <div class="stepper-icon"><i class="mdi mdi-account-supervisor"></i></div>
                            <div class="stepper-label">Next of Kin</div>
                        </div>
                        <div class="stepper-line"></div>
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

                                <div class="row align-items-end pf-row-fileno-names pf-hide-unidentified">
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
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Surname <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="pf-surname" required data-validate="required|min:2" placeholder="Enter surname">
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
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
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Email Address</label>
                                            <input type="email" class="form-control" id="pf-email" data-validate="email" placeholder="Enter email address">
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row pf-row-address pf-hide-unidentified">
                                    <div class="col-12">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Residential Address</label>
                                            <textarea class="form-control" id="pf-address" rows="2" placeholder="Enter residential address"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="row pf-row-uploads pf-hide-unidentified">
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
                                    <div class="row g-2 mt-2">
                                        <div class="col-md-4">
                                            <div class="form-group mb-3">
                                                <label class="form-label mb-1">Approx Age</label>
                                                <select class="form-control form-control-sm" id="pf-approx-age">
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
                                                <small class="text-muted">Used when DOB unknown — auto-fills DOB estimate</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group mb-3">
                                                <label class="form-label mb-1"><i class="mdi mdi-truck-fast"></i> Mode of Arrival</label>
                                                <select class="form-control form-control-sm" id="pf-arrival-mode">
                                                    <option value="walk_in">Walk-In</option>
                                                    <option value="ambulance">Ambulance</option>
                                                    <option value="police">Police / Security</option>
                                                    <option value="referral">Referral</option>
                                                    <option value="brought_in">Brought by Relative</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <div class="form-group mb-3">
                                                <label class="form-label mb-1">Brought By (Name)</label>
                                                <input type="text" class="form-control form-control-sm" id="pf-brought-by-name" placeholder="Name of escort/relative">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group mb-3">
                                                <label class="form-label mb-1">Brought By (Phone)</label>
                                                <input type="text" class="form-control form-control-sm" id="pf-brought-by-phone" placeholder="Phone number">
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
                                        <button type="button" class="btn btn-outline-danger pf-esi-btn" data-esi="1"
                                                data-hint="Immediate life-saving intervention? Intubation, surgical airway, IV push meds, emergency procedure?">
                                            <strong>1</strong><br><small>Resuscitation</small>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger pf-esi-btn" data-esi="2"
                                                data-hint="High risk situation? Confused, lethargic, disoriented? Severe pain/distress (Pain ≥ 8/10)?">
                                            <strong>2</strong><br><small>Emergent</small>
                                        </button>
                                        <button type="button" class="btn btn-outline-warning pf-esi-btn" data-esi="3"
                                                data-hint="Needs 2+ resources (labs, imaging, IV fluids, specialty consult)? Vitals may be outside normal range.">
                                            <strong>3</strong><br><small>Urgent</small>
                                        </button>
                                        <button type="button" class="btn btn-outline-info pf-esi-btn" data-esi="4"
                                                data-hint="Needs only 1 resource (e.g., one X-ray OR one lab test OR simple procedure). Vitals normal.">
                                            <strong>4</strong><br><small>Less Urgent</small>
                                        </button>
                                        <button type="button" class="btn btn-outline-success pf-esi-btn" data-esi="5"
                                                data-hint="No resources needed. Simple exam, prescription refill, minor complaint. Stable vitals.">
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
                                        <label class="list-group-item list-group-item-action d-flex align-items-center">
                                            <input type="radio" name="pf_disposition" value="admit_emergency" class="form-check-input me-2 pf-disposition-radio">
                                            <div>
                                                <strong><i class="mdi mdi-bed text-danger"></i> Admit to Emergency Ward</strong>
                                                <small class="d-block text-muted">Assign bed and admit immediately</small>
                                            </div>
                                        </label>
                                        <label class="list-group-item list-group-item-action d-flex align-items-center">
                                            <input type="radio" name="pf_disposition" value="queue_consultation" class="form-check-input me-2 pf-disposition-radio">
                                            <div>
                                                <strong><i class="mdi mdi-account-clock text-warning"></i> Queue for Consultation</strong>
                                                <small class="d-block text-muted">Send to doctor queue for evaluation</small>
                                            </div>
                                        </label>
                                        <label class="list-group-item list-group-item-action d-flex align-items-center">
                                            <input type="radio" name="pf_disposition" value="direct_service" class="form-check-input me-2 pf-disposition-radio">
                                            <div>
                                                <strong><i class="mdi mdi-flask text-info"></i> Direct to Lab/Imaging</strong>
                                                <small class="d-block text-muted">Order lab or imaging services directly</small>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                {{-- Admit Emergency Options --}}
                                <div id="pf-admit-options" style="display: none;">
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Admission Service <span class="text-danger">*</span></label>
                                            <select class="form-select form-select-sm" id="pf-admit-service-select">
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
                                        <label class="form-label fw-bold">Assign Bed</label>
                                        <select class="form-select form-select-sm" id="pf-bed-select">
                                            <option value="">-- No bed (assign later) --</option>
                                        </select>
                                        <small class="text-muted">Bed can also be assigned later from nursing workbench.</small>
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
                                            <select class="form-select form-select-sm" id="pf-service-select">
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
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">HMO Provider</label>
                                            <select class="form-control" id="pf-hmo">
                                                <!-- Options populated by JS, HMO ID 1 (Private) is default -->
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
                                @if(isset($registrationServices) && $registrationServices->count() > 0)
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

                                    <!-- Triage -->
                                    <div class="summary-section">
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
                                    <div class="summary-section" id="emg-summary-vitals-section" style="display:none;">
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
                        <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
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


