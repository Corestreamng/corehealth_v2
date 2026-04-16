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
        $('#pf-dob').val(dob);
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
            $('#pf-dob').addClass('is-invalid');
            $('#pf-dob').siblings('.invalid-feedback').text('Date of birth is required');
            isValid = false;
        } else {
            $('#pf-dob').addClass('is-valid');
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
        goToPatientFormStep(patientFormCurrentStep + 1);
    });

    // Previous button
    $('#pf-btn-prev').on('click', function() {
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

    // DOB change - update age
    $('#pf-dob').on('change', function() {
        updatePatientFormAge();
        checkDuplicatePatient();
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


</script>
@endpush

<div class="modal fade" id="patientFormModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header" id="patient-form-header">
                <h5 class="modal-title" id="patient-form-title"><i class="mdi mdi-account-plus"></i> New Patient Registration</h5>
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

                                <div class="row align-items-end">
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
                                <div class="row">
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
                                            <label class="form-label mb-1">Date of Birth <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="pf-dob" required data-validate="required">
                                            <div class="invalid-feedback"></div>
                                            <small class="form-text" id="pf-age-display"></small>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
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
                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Residential Address</label>
                                            <textarea class="form-control" id="pf-address" rows="2" placeholder="Enter residential address"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
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
                            </div>
                        </div>

                        <!-- Step 2: Medical Information -->
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


