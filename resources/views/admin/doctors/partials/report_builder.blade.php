{{-- Medical Report Builder Modal --}}
<style>
    /* Force CKEditor editable area to fill the available height */
    #reportBuilderModal .ck-editor__editable {
        min-height: 65vh !important;
        max-height: calc(100vh - 220px) !important;
        overflow-y: auto !important;
    }
</style>
<div class="modal fade" id="reportBuilderModal" tabindex="-1" data-bs-backdrop="static" aria-labelledby="reportBuilderModalLabel">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header py-2" style="background: {{ appsettings('hos_color', '#007bff') }}; color: #fff;">
                <h5 class="modal-title" id="reportBuilderModalLabel">
                    <i class="mdi mdi-file-document"></i> Medical Report Builder
                </h5>
                <div class="d-flex align-items-center gap-2">
                    <input type="hidden" id="reportId" value="">
                    <input type="hidden" id="reportStatus" value="draft">
                    <span class="badge bg-light text-dark" id="reportStatusBadge">Draft</span>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body p-0" style="overflow: hidden;">
                <div class="d-flex h-100">
                    {{-- Left Sidebar: Patient Data Picker --}}
                    <div class="border-end bg-light" style="width: 320px; min-width: 320px; overflow-y: auto; max-height: calc(100vh - 120px);">
                        <div class="p-3">
                            <h6 class="fw-bold mb-3"><i class="mdi mdi-database"></i> Patient Data</h6>
                            <small class="text-muted d-block mb-3">Expand a section and click <strong>Copy</strong> to copy formatted content, then paste into the editor</small>

                            {{-- Report Metadata --}}
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Report Title</label>
                                <input type="text" id="reportTitle" class="form-control form-control-sm" value="Medical Report" maxlength="255">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Report Date</label>
                                <input type="date" id="reportDate" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
                            </div>

                            <hr>

                            {{-- Data Sections --}}
                            <div class="accordion accordion-flush" id="patientDataAccordion">
                                {{-- Demographics --}}
                                <div class="accordion-item border-0 mb-1">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed py-2 px-3 bg-white rounded" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#reportDemoSection">
                                            <i class="mdi mdi-account me-2 text-primary"></i> <small class="fw-bold">Demographics</small>
                                        </button>
                                    </h2>
                                    <div id="reportDemoSection" class="accordion-collapse collapse" data-bs-parent="#patientDataAccordion">
                                        <div class="accordion-body p-2" id="reportDemoBody">
                                            <div class="text-center py-2 text-muted small"><i class="fa fa-spinner fa-spin"></i> Loading...</div>
                                        </div>
                                        <button class="btn btn-sm btn-outline-success w-100 mt-1" onclick="copyDataSection('demographics')">
                                            <i class="mdi mdi-content-copy"></i> Copy to Clipboard
                                        </button>
                                    </div>
                                </div>

                                {{-- Vitals --}}
                                <div class="accordion-item border-0 mb-1">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed py-2 px-3 bg-white rounded" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#reportVitalsSection">
                                            <i class="mdi mdi-heart-pulse me-2 text-danger"></i> <small class="fw-bold">Latest Vitals</small>
                                        </button>
                                    </h2>
                                    <div id="reportVitalsSection" class="accordion-collapse collapse" data-bs-parent="#patientDataAccordion">
                                        <div class="accordion-body p-2" id="reportVitalsBody">
                                            <div class="text-center py-2 text-muted small"><i class="fa fa-spinner fa-spin"></i> Loading...</div>
                                        </div>
                                        <button class="btn btn-sm btn-outline-success w-100 mt-1" onclick="copyDataSection('vitals')">
                                            <i class="mdi mdi-content-copy"></i> Copy to Clipboard
                                        </button>
                                    </div>
                                </div>

                                {{-- Diagnoses --}}
                                <div class="accordion-item border-0 mb-1">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed py-2 px-3 bg-white rounded" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#reportDiagSection">
                                            <i class="mdi mdi-stethoscope me-2 text-warning"></i> <small class="fw-bold">Recent Diagnoses</small>
                                        </button>
                                    </h2>
                                    <div id="reportDiagSection" class="accordion-collapse collapse" data-bs-parent="#patientDataAccordion">
                                        <div class="accordion-body p-2" id="reportDiagBody">
                                            <div class="text-center py-2 text-muted small"><i class="fa fa-spinner fa-spin"></i> Loading...</div>
                                        </div>
                                        <button class="btn btn-sm btn-outline-success w-100 mt-1" onclick="copyDataSection('diagnoses')">
                                            <i class="mdi mdi-content-copy"></i> Copy to Clipboard
                                        </button>
                                    </div>
                                </div>

                                {{-- Medications --}}
                                <div class="accordion-item border-0 mb-1">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed py-2 px-3 bg-white rounded" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#reportMedsSection">
                                            <i class="mdi mdi-pill me-2 text-success"></i> <small class="fw-bold">Recent Medications</small>
                                        </button>
                                    </h2>
                                    <div id="reportMedsSection" class="accordion-collapse collapse" data-bs-parent="#patientDataAccordion">
                                        <div class="accordion-body p-2" id="reportMedsBody">
                                            <div class="text-center py-2 text-muted small"><i class="fa fa-spinner fa-spin"></i> Loading...</div>
                                        </div>
                                        <button class="btn btn-sm btn-outline-success w-100 mt-1" onclick="copyDataSection('medications')">
                                            <i class="mdi mdi-content-copy"></i> Copy to Clipboard
                                        </button>
                                    </div>
                                </div>

                                {{-- Lab Results --}}
                                <div class="accordion-item border-0 mb-1">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed py-2 px-3 bg-white rounded" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#reportLabsSection">
                                            <i class="mdi mdi-flask me-2 text-info"></i> <small class="fw-bold">Recent Lab Results</small>
                                        </button>
                                    </h2>
                                    <div id="reportLabsSection" class="accordion-collapse collapse" data-bs-parent="#patientDataAccordion">
                                        <div class="accordion-body p-2" id="reportLabsBody">
                                            <div class="text-center py-2 text-muted small"><i class="fa fa-spinner fa-spin"></i> Loading...</div>
                                        </div>
                                        <button class="btn btn-sm btn-outline-success w-100 mt-1" onclick="copyDataSection('labs')">
                                            <i class="mdi mdi-content-copy"></i> Copy to Clipboard
                                        </button>
                                    </div>
                                </div>

                                {{-- Imaging Results --}}
                                <div class="accordion-item border-0 mb-1">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed py-2 px-3 bg-white rounded" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#reportImagingSection">
                                            <i class="mdi mdi-radiology-box me-2 text-purple"></i> <small class="fw-bold">Imaging Results</small>
                                        </button>
                                    </h2>
                                    <div id="reportImagingSection" class="accordion-collapse collapse" data-bs-parent="#patientDataAccordion">
                                        <div class="accordion-body p-2" id="reportImagingBody">
                                            <div class="text-center py-2 text-muted small"><i class="fa fa-spinner fa-spin"></i> Loading...</div>
                                        </div>
                                        <button class="btn btn-sm btn-outline-success w-100 mt-1" onclick="copyDataSection('imaging')">
                                            <i class="mdi mdi-content-copy"></i> Copy to Clipboard
                                        </button>
                                    </div>
                                </div>

                                {{-- Procedures --}}
                                <div class="accordion-item border-0 mb-1">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed py-2 px-3 bg-white rounded" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#reportProceduresSection">
                                            <i class="mdi mdi-hospital me-2 text-danger"></i> <small class="fw-bold">Procedures</small>
                                        </button>
                                    </h2>
                                    <div id="reportProceduresSection" class="accordion-collapse collapse" data-bs-parent="#patientDataAccordion">
                                        <div class="accordion-body p-2" id="reportProceduresBody">
                                            <div class="text-center py-2 text-muted small"><i class="fa fa-spinner fa-spin"></i> Loading...</div>
                                        </div>
                                        <button class="btn btn-sm btn-outline-success w-100 mt-1" onclick="copyDataSection('procedures')">
                                            <i class="mdi mdi-content-copy"></i> Copy to Clipboard
                                        </button>
                                    </div>
                                </div>

                                {{-- Clinical Notes --}}
                                <div class="accordion-item border-0 mb-1">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed py-2 px-3 bg-white rounded" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#reportNotesSection">
                                            <i class="mdi mdi-note-text me-2 text-secondary"></i> <small class="fw-bold">Clinical Notes</small>
                                        </button>
                                    </h2>
                                    <div id="reportNotesSection" class="accordion-collapse collapse" data-bs-parent="#patientDataAccordion">
                                        <div class="accordion-body p-2" id="reportNotesBody">
                                            <div class="text-center py-2 text-muted small"><i class="fa fa-spinner fa-spin"></i> Loading...</div>
                                        </div>
                                        <button class="btn btn-sm btn-outline-success w-100 mt-1" onclick="copyDataSection('clinical_notes')">
                                            <i class="mdi mdi-content-copy"></i> Copy to Clipboard
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            {{-- Previous Reports --}}
                            <h6 class="fw-bold small mb-2"><i class="mdi mdi-history"></i> Previous Reports</h6>
                            <div id="previousReportsList" class="mb-2">
                                <div class="text-center py-2 text-muted small">Loading...</div>
                            </div>
                        </div>
                    </div>

                    {{-- Right Side: WYSIWYG Editor --}}
                    <div class="flex-grow-1 d-flex flex-column" style="max-height: calc(100vh - 120px);">
                        <div class="p-2 bg-white border-bottom d-flex justify-content-between align-items-center">
                            <small class="text-muted"><i class="mdi mdi-pencil"></i> Compose your report using the editor below. Use the sidebar to insert patient data.</small>
                        </div>
                        <div class="flex-grow-1 p-3" style="overflow-y: auto;">
                            <div id="reportContentEditor" style="min-height: 700px;"></div>
                            <textarea id="reportContent" class="form-control d-none" rows="20"></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openMedicalReportHistory('{{ $patient->id }}', '{{ addslashes(trim(($patient->user->surname ?? '') . ' ' . ($patient->user->firstname ?? ''))) }}', '{{ $patient->file_no ?? '' }}')">
                    <i class="mdi mdi-history"></i> History
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="saveReport(false)">
                    <i class="fa fa-save"></i> Save Draft
                </button>
                <button type="button" class="btn btn-outline-info btn-sm" onclick="printCurrentReport()">
                    <i class="fa fa-print"></i> Print
                </button>
                <button type="button" class="btn btn-warning btn-sm" onclick="finalizeOnly()">
                    <i class="mdi mdi-check-decagram"></i> Finalize
                </button>
                <button type="button" class="btn btn-success btn-sm" onclick="saveReport(true)">
                    <i class="fa fa-check-circle"></i> Finalize & Print
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Shared Medical Report History Modal --}}
@include('admin.partials.medical_report_history_modal')

<script>
let reportEditorInstance = null;
let reportPatientData = null;

/**
 * Finalize the current report without printing.
 */
function finalizeOnly() {
    let content = '';
    if (reportEditorInstance) {
        content = reportEditorInstance.getData();
    } else {
        content = $('#reportContent').val();
    }

    if (!content || content.trim() === '' || content === '<p>&nbsp;</p>') {
        alert('Report content is required.');
        return;
    }

    let reportId = $('#reportId').val();
    let isNew = !reportId;

    let data = {
        patient_id: '{{ $patient->id }}',
        encounter_id: '{{ $encounter->id ?? "" }}',
        title: $('#reportTitle').val() || 'Medical Report',
        content: content,
        report_date: $('#reportDate').val() || '{{ date("Y-m-d") }}',
    };

    let url = isNew ? '{{ route("medical-reports.store") }}' : `{{ url('medical-reports') }}/${reportId}`;
    let method = isNew ? 'POST' : 'PUT';

    $.ajax({
        url: url,
        type: method,
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        data: data,
        success: function(response) {
            if (response.success) {
                if (response.report) {
                    $('#reportId').val(response.report.id);
                    reportId = response.report.id;
                }

                // Finalize
                $.ajax({
                    url: `{{ url('medical-reports') }}/${reportId}/finalize`,
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function(fResponse) {
                        if (fResponse.success) {
                            $('#reportStatus').val('finalized');
                            $('#reportStatusBadge').text('Finalized').removeClass('bg-light text-dark').addClass('bg-success text-white');
                            if (reportEditorInstance) {
                                reportEditorInstance.enableReadOnlyMode('report-finalized');
                            }
                            toastr.success('Report finalized successfully!');
                            loadPreviousReports();
                        } else {
                            toastr.error(fResponse.message || 'Failed to finalize');
                        }
                    },
                    error: function(xhr) {
                        toastr.error(xhr.responseJSON?.message || 'Failed to finalize report');
                    }
                });
            } else {
                alert(response.message || 'Failed to save report');
            }
        },
        error: function(xhr) {
            alert(xhr.responseJSON?.message || 'Failed to save report');
        }
    });
}

function printCurrentReport() {
    const reportId = $('#reportId').val();
    if (!reportId) {
        alert('Please save the report first before printing.');
        return;
    }
    window.open(`{{ url('medical-reports') }}/${reportId}/print`, '_blank');
}

function openReportBuilder(existingReportId) {
    $('#reportId').val(existingReportId || '');
    $('#reportStatus').val('draft');
    $('#reportStatusBadge').text('Draft').removeClass('bg-success').addClass('bg-light text-dark');
    $('#reportTitle').val('Medical Report');
    $('#reportDate').val('{{ date("Y-m-d") }}');

    $('#reportBuilderModal').modal('show');

    // Initialize editor after modal shows
    setTimeout(function() {
        initReportEditor(existingReportId ? null : getDefaultReportContent());

        // Load patient data for sidebar
        loadReportPatientData();

        // Load previous reports
        loadPreviousReports();

        // If editing existing report, load it
        if (existingReportId) {
            loadExistingReport(existingReportId);
        }
    }, 300);
}

function getDefaultReportContent() {
    const patientName = '{{ addslashes(trim(($patient->user->surname ?? "") . " " . ($patient->user->firstname ?? "") . " " . ($patient->user->othername ?? ""))) }}';
    const fileNo = '{{ $patient->file_no ?? "" }}';
    const today = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

    return `
        <h2 style="text-align: center;">MEDICAL REPORT</h2>
        <p><strong>Date:</strong> ${today}</p>
        <p><strong>Patient:</strong> ${patientName} &nbsp; <strong>File No:</strong> ${fileNo}</p>
        <hr>
        <h3>History of Presenting Illness</h3>
        <p>[Enter clinical history here]</p>
        <h3>Examination Findings</h3>
        <p>[Enter examination findings here]</p>
        <h3>Investigations</h3>
        <p>[Enter investigation results here]</p>
        <h3>Diagnosis</h3>
        <p>[Enter diagnosis here]</p>
        <h3>Treatment</h3>
        <p>[Enter treatment plan here]</p>
        <h3>Conclusion / Recommendation</h3>
        <p>[Enter conclusion here]</p>
    `;
}

function initReportEditor(content) {
    if (reportEditorInstance) {
        reportEditorInstance.destroy()
            .then(() => createReportEditor(content))
            .catch(() => createReportEditor(content));
    } else {
        createReportEditor(content);
    }
}

function createReportEditor(content) {
    const el = document.querySelector('#reportContentEditor');
    if (!el) return;
    el.innerHTML = '';

    if (typeof ClassicEditor !== 'undefined') {
        ClassicEditor
            .create(el, {
                toolbar: {
                    items: [
                        'undo', 'redo',
                        '|', 'heading',
                        '|', 'bold', 'italic', 'underline', 'strikethrough',
                        '|', 'link', 'insertTable',
                        '|', 'bulletedList', 'numberedList', 'outdent', 'indent',
                        '|', 'blockQuote', 'horizontalLine'
                    ]
                }
            })
            .then(editor => {
                reportEditorInstance = editor;
                if (content) editor.setData(content);
            })
            .catch(error => {
                console.error('Report editor init error:', error);
                $('#reportContentEditor').addClass('d-none');
                $('#reportContent').removeClass('d-none').val(content || '');
            });
    } else {
        $('#reportContentEditor').addClass('d-none');
        $('#reportContent').removeClass('d-none').val(content || '');
    }
}

function loadReportPatientData() {
    const patientId = '{{ $patient->id }}';
    $.get(`{{ url('patient') }}/${patientId}/report-data`, function(response) {
        if (response.success) {
            reportPatientData = response.data;
            renderPatientDataSections(response.data);
        }
    }).fail(function() {
        console.error('Failed to load patient data');
    });
}

function renderPatientDataSections(data) {
    // Demographics
    if (data.demographics) {
        let d = data.demographics;
        $('#reportDemoBody').html(`
            <table class="table table-sm mb-0" style="font-size:0.8rem;">
                <tr><td class="fw-bold">Name</td><td>${d.name}</td></tr>
                <tr><td class="fw-bold">File No</td><td>${d.file_no}</td></tr>
                <tr><td class="fw-bold">Sex</td><td>${d.sex}</td></tr>
                <tr><td class="fw-bold">DOB</td><td>${d.dob}</td></tr>
                <tr><td class="fw-bold">Age</td><td>${d.age}</td></tr>
                <tr><td class="fw-bold">Phone</td><td>${d.phone}</td></tr>
                <tr><td class="fw-bold">Blood Group</td><td>${d.blood_group}</td></tr>
            </table>
        `);
    }

    // Vitals
    if (data.vitals) {
        let v = data.vitals;
        $('#reportVitalsBody').html(`
            <table class="table table-sm mb-0" style="font-size:0.8rem;">
                <tr><td class="fw-bold">BP</td><td>${v.bp_systolic}/${v.bp_diastolic} mmHg</td></tr>
                <tr><td class="fw-bold">Pulse</td><td>${v.pulse} bpm</td></tr>
                <tr><td class="fw-bold">Temp</td><td>${v.temperature} &deg;C</td></tr>
                <tr><td class="fw-bold">RR</td><td>${v.respiratory_rate} /min</td></tr>
                <tr><td class="fw-bold">SpO2</td><td>${v.spo2}%</td></tr>
                <tr><td class="fw-bold">Weight</td><td>${v.weight} kg</td></tr>
                <tr><td class="fw-bold">Height</td><td>${v.height} cm</td></tr>
                <tr><td class="fw-bold">BMI</td><td>${v.bmi}</td></tr>
                <tr><td class="fw-bold">Recorded</td><td>${v.recorded_at}</td></tr>
            </table>
        `);
    } else {
        $('#reportVitalsBody').html('<div class="text-muted small text-center py-2">No vitals recorded</div>');
    }

    // Diagnoses
    if (data.diagnoses && data.diagnoses.length > 0) {
        let html = '<ul class="list-unstyled mb-0" style="font-size:0.8rem;">';
        data.diagnoses.forEach(enc => {
            html += `<li class="mb-1"><strong>${enc.date}:</strong> ${enc.diagnoses.join(', ')}</li>`;
        });
        html += '</ul>';
        $('#reportDiagBody').html(html);
    } else {
        $('#reportDiagBody').html('<div class="text-muted small text-center py-2">No diagnoses found</div>');
    }

    // Medications
    if (data.medications && data.medications.length > 0) {
        let html = '<table class="table table-sm mb-0" style="font-size:0.8rem;"><thead><tr><th>Medication</th><th>Dose</th><th>Date</th></tr></thead><tbody>';
        data.medications.forEach(m => {
            html += `<tr><td>${m.name}</td><td>${m.dose}</td><td>${m.date}</td></tr>`;
        });
        html += '</tbody></table>';
        $('#reportMedsBody').html(html);
    } else {
        $('#reportMedsBody').html('<div class="text-muted small text-center py-2">No medications found</div>');
    }

    // Labs
    if (data.labs && data.labs.length > 0) {
        let html = '<table class="table table-sm mb-0" style="font-size:0.8rem;"><thead><tr><th>Test</th><th>Status</th><th>Date</th></tr></thead><tbody>';
        data.labs.forEach(l => {
            html += `<tr><td>${l.name}</td><td>${l.status}</td><td>${l.date}</td></tr>`;
        });
        html += '</tbody></table>';
        $('#reportLabsBody').html(html);
    } else {
        $('#reportLabsBody').html('<div class="text-muted small text-center py-2">No lab results found</div>');
    }

    // Imaging
    if (data.imaging && data.imaging.length > 0) {
        let html = '<table class="table table-sm mb-0" style="font-size:0.8rem;"><thead><tr><th>Study</th><th>Priority</th><th>Date</th></tr></thead><tbody>';
        data.imaging.forEach(img => {
            let priorityBadge = img.priority === 'urgent' ? '<span class="badge bg-warning">Urgent</span>' :
                                img.priority === 'emergency' ? '<span class="badge bg-danger">Emergency</span>' :
                                '<span class="badge bg-secondary">Routine</span>';
            html += `<tr><td>${img.name}</td><td>${priorityBadge}</td><td>${img.date}</td></tr>`;
        });
        html += '</tbody></table>';
        $('#reportImagingBody').html(html);
    } else {
        $('#reportImagingBody').html('<div class="text-muted small text-center py-2">No imaging results found</div>');
    }

    // Procedures
    if (data.procedures && data.procedures.length > 0) {
        let html = '<table class="table table-sm mb-0" style="font-size:0.8rem;"><thead><tr><th>Procedure</th><th>Status</th><th>Outcome</th><th>Date</th></tr></thead><tbody>';
        data.procedures.forEach(p => {
            html += `<tr><td>${p.name} <small class="text-muted">(${p.code})</small></td><td>${p.status}</td><td>${p.outcome}</td><td>${p.scheduled_date}</td></tr>`;
        });
        html += '</tbody></table>';
        $('#reportProceduresBody').html(html);
    } else {
        $('#reportProceduresBody').html('<div class="text-muted small text-center py-2">No procedures found</div>');
    }

    // Clinical Notes
    if (data.clinical_notes && data.clinical_notes.length > 0) {
        let html = '';
        data.clinical_notes.forEach(n => {
            let preview = n.notes.replace(/<[^>]*>/g, '').substring(0, 100);
            html += `<div class="border-bottom pb-1 mb-1" style="font-size:0.8rem;">
                <strong>${n.date}</strong> — <small class="text-muted">${n.doctor}</small>
                <div class="text-truncate">${preview}...</div>
            </div>`;
        });
        $('#reportNotesBody').html(html);
    } else {
        $('#reportNotesBody').html('<div class="text-muted small text-center py-2">No clinical notes found</div>');
    }
}

function insertDataSection(section) {
    if (!reportPatientData) return;

    let html = '';
    switch (section) {
        case 'demographics':
            let d = reportPatientData.demographics;
            html = `<div class="report-section"><table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse; width:100%;">
                <tr><td><strong>Patient Name</strong></td><td>${d.name}</td><td><strong>File No</strong></td><td>${d.file_no}</td></tr>
                <tr><td><strong>Sex</strong></td><td>${d.sex}</td><td><strong>Age</strong></td><td>${d.age}</td></tr>
                <tr><td><strong>DOB</strong></td><td>${d.dob}</td><td><strong>Blood Group</strong></td><td>${d.blood_group}</td></tr>
                <tr><td><strong>Phone</strong></td><td>${d.phone}</td><td><strong>Address</strong></td><td>${d.address}</td></tr>
            </table></div><p>&nbsp;</p>`;
            break;
        case 'vitals':
            let v = reportPatientData.vitals;
            if (!v) { alert('No vitals data available.'); return; }
            html = `<div class="report-section"><h4>Vital Signs</h4>
            <table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse; width:100%;">
                <tr><td><strong>Blood Pressure</strong></td><td>${v.bp_systolic}/${v.bp_diastolic} mmHg</td><td><strong>Pulse</strong></td><td>${v.pulse} bpm</td></tr>
                <tr><td><strong>Temperature</strong></td><td>${v.temperature} &deg;C</td><td><strong>Respiratory Rate</strong></td><td>${v.respiratory_rate} /min</td></tr>
                <tr><td><strong>SpO2</strong></td><td>${v.spo2}%</td><td><strong>Weight</strong></td><td>${v.weight} kg</td></tr>
                <tr><td><strong>Height</strong></td><td>${v.height} cm</td><td><strong>BMI</strong></td><td>${v.bmi}</td></tr>
            </table>
            <p><small>Recorded: ${v.recorded_at}</small></p></div><p>&nbsp;</p>`;
            break;
        case 'diagnoses':
            if (!reportPatientData.diagnoses || reportPatientData.diagnoses.length === 0) { alert('No diagnosis data available.'); return; }
            html = `<div class="report-section"><h4>Diagnoses</h4><ul>`;
            reportPatientData.diagnoses.forEach(enc => {
                enc.diagnoses.forEach(dx => {
                    html += `<li>${dx} <em>(${enc.date})</em></li>`;
                });
            });
            html += `</ul></div><p>&nbsp;</p>`;
            break;
        case 'medications':
            if (!reportPatientData.medications || reportPatientData.medications.length === 0) { alert('No medication data available.'); return; }
            html = `<div class="report-section"><h4>Medications</h4>
            <table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse; width:100%;">
                <tr><th>Medication</th><th>Dose</th><th>Date</th></tr>`;
            reportPatientData.medications.forEach(m => {
                html += `<tr><td>${m.name}</td><td>${m.dose}</td><td>${m.date}</td></tr>`;
            });
            html += `</table></div><p>&nbsp;</p>`;
            break;
        case 'labs':
            if (!reportPatientData.labs || reportPatientData.labs.length === 0) { alert('No lab data available.'); return; }
            html = `<div class="report-section"><h4>Laboratory Investigations</h4>
            <table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse; width:100%;">
                <tr><th>Test</th><th>Status</th><th>Result</th><th>Date</th></tr>`;
            reportPatientData.labs.forEach(l => {
                html += `<tr><td>${l.name}</td><td>${l.status}</td><td>${l.result || '-'}</td><td>${l.date}</td></tr>`;
            });
            html += `</table></div><p>&nbsp;</p>`;
            break;
        case 'imaging':
            if (!reportPatientData.imaging || reportPatientData.imaging.length === 0) { alert('No imaging data available.'); return; }
            html = `<div class="report-section"><h4>Imaging / Radiology</h4>
            <table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse; width:100%;">
                <tr><th>Study</th><th>Result</th><th>Date</th></tr>`;
            reportPatientData.imaging.forEach(img => {
                let result = img.result && img.result !== '-' ? img.result.replace(/<[^>]*>/g, '').substring(0, 200) : '-';
                html += `<tr><td>${img.name}</td><td>${result}</td><td>${img.result_date || img.date}</td></tr>`;
            });
            html += `</table></div><p>&nbsp;</p>`;
            break;
        case 'procedures':
            if (!reportPatientData.procedures || reportPatientData.procedures.length === 0) { alert('No procedures data available.'); return; }
            html = `<div class="report-section"><h4>Procedures</h4>
            <table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse; width:100%;">
                <tr><th>Procedure</th><th>Code</th><th>Status</th><th>Outcome</th><th>Date</th></tr>`;
            reportPatientData.procedures.forEach(p => {
                html += `<tr><td>${p.name}</td><td>${p.code}</td><td>${p.status}</td><td>${p.outcome}</td><td>${p.scheduled_date}</td></tr>`;
            });
            html += `</table></div><p>&nbsp;</p>`;
            break;
        case 'clinical_notes':
            if (!reportPatientData.clinical_notes || reportPatientData.clinical_notes.length === 0) { alert('No clinical notes available.'); return; }
            html = `<div class="report-section"><h4>Clinical Notes</h4>`;
            reportPatientData.clinical_notes.forEach(n => {
                html += `<div style="margin-bottom:10px;"><strong>${n.date}</strong> — Dr. ${n.doctor}<br>${n.notes}</div><hr>`;
            });
            html += `</div><p>&nbsp;</p>`;
            break;
    }

    if (html && reportEditorInstance) {
        const viewFragment = reportEditorInstance.data.processor.toView(html);
        const modelFragment = reportEditorInstance.data.toModel(viewFragment);
        reportEditorInstance.model.insertContent(modelFragment);
    } else if (html) {
        let current = $('#reportContent').val();
        $('#reportContent').val(current + '\n' + html);
    }
}

function copyDataSection(section) {
    if (!reportPatientData) {
        alert('Patient data is still loading. Please wait.');
        return;
    }

    let html = '';
    switch (section) {
        case 'demographics':
            let d = reportPatientData.demographics;
            html = `<div class="report-section"><table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse; width:100%;">
                <tr><td><strong>Patient Name</strong></td><td>${d.name}</td><td><strong>File No</strong></td><td>${d.file_no}</td></tr>
                <tr><td><strong>Sex</strong></td><td>${d.sex}</td><td><strong>Age</strong></td><td>${d.age}</td></tr>
                <tr><td><strong>DOB</strong></td><td>${d.dob}</td><td><strong>Blood Group</strong></td><td>${d.blood_group}</td></tr>
                <tr><td><strong>Phone</strong></td><td>${d.phone}</td><td><strong>Address</strong></td><td>${d.address}</td></tr>
            </table></div><p>&nbsp;</p>`;
            break;
        case 'vitals':
            let v = reportPatientData.vitals;
            if (!v) { alert('No vitals data available.'); return; }
            html = `<div class="report-section"><h4>Vital Signs</h4>
            <table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse; width:100%;">
                <tr><td><strong>Blood Pressure</strong></td><td>${v.bp_systolic}/${v.bp_diastolic} mmHg</td><td><strong>Pulse</strong></td><td>${v.pulse} bpm</td></tr>
                <tr><td><strong>Temperature</strong></td><td>${v.temperature} &deg;C</td><td><strong>Respiratory Rate</strong></td><td>${v.respiratory_rate} /min</td></tr>
                <tr><td><strong>SpO2</strong></td><td>${v.spo2}%</td><td><strong>Weight</strong></td><td>${v.weight} kg</td></tr>
                <tr><td><strong>Height</strong></td><td>${v.height} cm</td><td><strong>BMI</strong></td><td>${v.bmi}</td></tr>
            </table>
            <p><small>Recorded: ${v.recorded_at}</small></p></div><p>&nbsp;</p>`;
            break;
        case 'diagnoses':
            if (!reportPatientData.diagnoses || reportPatientData.diagnoses.length === 0) { alert('No diagnosis data available.'); return; }
            html = `<div class="report-section"><h4>Diagnoses</h4><ul>`;
            reportPatientData.diagnoses.forEach(enc => {
                enc.diagnoses.forEach(dx => {
                    html += `<li>${dx} <em>(${enc.date})</em></li>`;
                });
            });
            html += `</ul></div><p>&nbsp;</p>`;
            break;
        case 'medications':
            if (!reportPatientData.medications || reportPatientData.medications.length === 0) { alert('No medication data available.'); return; }
            html = `<div class="report-section"><h4>Medications</h4>
            <table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse; width:100%;">
                <tr><th>Medication</th><th>Dose</th><th>Date</th></tr>`;
            reportPatientData.medications.forEach(m => {
                html += `<tr><td>${m.name}</td><td>${m.dose}</td><td>${m.date}</td></tr>`;
            });
            html += `</table></div><p>&nbsp;</p>`;
            break;
        case 'labs':
            if (!reportPatientData.labs || reportPatientData.labs.length === 0) { alert('No lab data available.'); return; }
            html = `<div class="report-section"><h4>Laboratory Investigations</h4>
            <table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse; width:100%;">
                <tr><th>Test</th><th>Status</th><th>Result</th><th>Date</th></tr>`;
            reportPatientData.labs.forEach(l => {
                html += `<tr><td>${l.name}</td><td>${l.status}</td><td>${l.result || '-'}</td><td>${l.date}</td></tr>`;
            });
            html += `</table></div><p>&nbsp;</p>`;
            break;
        case 'imaging':
            if (!reportPatientData.imaging || reportPatientData.imaging.length === 0) { alert('No imaging data available.'); return; }
            html = `<div class="report-section"><h4>Imaging / Radiology</h4>
            <table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse; width:100%;">
                <tr><th>Study</th><th>Result</th><th>Date</th></tr>`;
            reportPatientData.imaging.forEach(img => {
                let result = img.result && img.result !== '-' ? img.result.replace(/<[^>]*>/g, '').substring(0, 200) : '-';
                html += `<tr><td>${img.name}</td><td>${result}</td><td>${img.result_date || img.date}</td></tr>`;
            });
            html += `</table></div><p>&nbsp;</p>`;
            break;
        case 'procedures':
            if (!reportPatientData.procedures || reportPatientData.procedures.length === 0) { alert('No procedures data available.'); return; }
            html = `<div class="report-section"><h4>Procedures</h4>
            <table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse; width:100%;">
                <tr><th>Procedure</th><th>Code</th><th>Status</th><th>Outcome</th><th>Date</th></tr>`;
            reportPatientData.procedures.forEach(p => {
                html += `<tr><td>${p.name}</td><td>${p.code}</td><td>${p.status}</td><td>${p.outcome}</td><td>${p.scheduled_date}</td></tr>`;
            });
            html += `</table></div><p>&nbsp;</p>`;
            break;
        case 'clinical_notes':
            if (!reportPatientData.clinical_notes || reportPatientData.clinical_notes.length === 0) { alert('No clinical notes available.'); return; }
            html = `<div class="report-section"><h4>Clinical Notes</h4>`;
            reportPatientData.clinical_notes.forEach(n => {
                html += `<div style="margin-bottom:10px;"><strong>${n.date}</strong> — Dr. ${n.doctor}<br>${n.notes}</div><hr>`;
            });
            html += `</div><p>&nbsp;</p>`;
            break;
    }

    if (!html) return;

    // Copy as rich HTML to clipboard so it pastes with formatting into CKEditor
    const blob = new Blob([html], { type: 'text/html' });
    const clipboardItem = new ClipboardItem({ 'text/html': blob });
    navigator.clipboard.write([clipboardItem]).then(() => {
        // Flash the button to confirm copy
        const btn = event.currentTarget;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="mdi mdi-check"></i> Copied!';
        btn.classList.remove('btn-outline-success');
        btn.classList.add('btn-success');
        setTimeout(() => {
            btn.innerHTML = originalHtml;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-success');
        }, 1500);
    }).catch(() => {
        // Fallback: select from a hidden element
        const temp = document.createElement('div');
        temp.innerHTML = html;
        temp.style.position = 'fixed';
        temp.style.left = '-9999px';
        document.body.appendChild(temp);
        const range = document.createRange();
        range.selectNodeContents(temp);
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
        document.execCommand('copy');
        sel.removeAllRanges();
        document.body.removeChild(temp);
        alert('Copied! Click inside the editor and press Ctrl+V to paste.');
    });
}

function loadPreviousReports() {
    const patientId = '{{ $patient->id }}';
    $.get(`{{ url('patient') }}/${patientId}/medical-reports`, function(response) {
        if (response.success && response.reports.length > 0) {
            let html = '';
            response.reports.forEach(r => {
                let statusBadge = r.status === 'finalized'
                    ? '<span class="badge bg-success">Finalized</span>'
                    : '<span class="badge bg-warning">Draft</span>';
                html += `
                    <div class="card mb-2 border">
                        <div class="card-body p-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong style="font-size: 0.85rem;">${r.title}</strong> ${statusBadge}<br>
                                    <small class="text-muted">${r.report_date} &bull; ${r.doctor}</small>
                                </div>
                                <div class="btn-group btn-group-sm">
                                    ${r.status === 'draft' ? `<button class="btn btn-outline-warning btn-sm" onclick="loadExistingReport(${r.id})" title="Edit"><i class="mdi mdi-pencil"></i></button>` : ''}
                                    <a href="{{ url('medical-reports') }}/${r.id}/print" target="_blank" class="btn btn-outline-primary btn-sm" title="Print"><i class="mdi mdi-printer"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            $('#previousReportsList').html(html);
        } else {
            $('#previousReportsList').html('<div class="text-muted small text-center py-2">No previous reports</div>');
        }
    });
}

function loadExistingReport(reportId) {
    $.get(`{{ url('medical-reports') }}/${reportId}`, function(response) {
        if (response.success) {
            let r = response.report;
            $('#reportId').val(r.id);
            $('#reportTitle').val(r.title);
            $('#reportDate').val(r.report_date ? r.report_date.split('T')[0] : '');
            $('#reportStatus').val(r.status);

            if (r.status === 'finalized') {
                $('#reportStatusBadge').text('Finalized').removeClass('bg-light text-dark bg-warning').addClass('bg-success text-white');
            } else {
                $('#reportStatusBadge').text('Draft').removeClass('bg-success text-white').addClass('bg-light text-dark');
            }

            if (reportEditorInstance) {
                reportEditorInstance.setData(r.content || '');
                if (r.status === 'finalized') {
                    reportEditorInstance.enableReadOnlyMode('report-finalized');
                } else {
                    reportEditorInstance.disableReadOnlyMode('report-finalized');
                }
            } else {
                $('#reportContent').val(r.content || '');
            }
        }
    });
}

function saveReport(finalize) {
    let content = '';
    if (reportEditorInstance) {
        content = reportEditorInstance.getData();
    } else {
        content = $('#reportContent').val();
    }

    if (!content || content.trim() === '' || content === '<p>&nbsp;</p>') {
        alert('Report content is required.');
        return;
    }

    let reportId = $('#reportId').val();
    let isNew = !reportId;

    let data = {
        patient_id: '{{ $patient->id }}',
        encounter_id: '{{ $encounter->id ?? "" }}',
        title: $('#reportTitle').val() || 'Medical Report',
        content: content,
        report_date: $('#reportDate').val() || '{{ date("Y-m-d") }}',
    };

    let url = isNew ? '{{ route("medical-reports.store") }}' : `{{ url('medical-reports') }}/${reportId}`;
    let method = isNew ? 'POST' : 'PUT';

    $.ajax({
        url: url,
        type: method,
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        data: data,
        success: function(response) {
            if (response.success) {
                if (response.report) {
                    $('#reportId').val(response.report.id);
                    reportId = response.report.id;
                }

                if (finalize && reportId) {
                    // Finalize the report
                    $.ajax({
                        url: `{{ url('medical-reports') }}/${reportId}/finalize`,
                        type: 'POST',
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        success: function(fResponse) {
                            if (fResponse.success) {
                                $('#reportStatus').val('finalized');
                                $('#reportStatusBadge').text('Finalized').removeClass('bg-light text-dark').addClass('bg-success text-white');
                                if (reportEditorInstance) {
                                    reportEditorInstance.enableReadOnlyMode('report-finalized');
                                }
                                toastr.success('Report finalized successfully!');
                                // Open print view
                                window.open(`{{ url('medical-reports') }}/${reportId}/print`, '_blank');
                                loadPreviousReports();
                            } else {
                                toastr.error(fResponse.message || 'Failed to finalize');
                            }
                        },
                        error: function(xhr) {
                            toastr.error(xhr.responseJSON?.message || 'Failed to finalize report');
                        }
                    });
                } else {
                    toastr.success('Report saved as draft.');
                    loadPreviousReports();
                }
            } else {
                alert(response.message || 'Failed to save report');
            }
        },
        error: function(xhr) {
            alert(xhr.responseJSON?.message || 'Failed to save report');
        }
    });
}
</script>
