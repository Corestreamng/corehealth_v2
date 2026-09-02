{{--
    Medical Report History Modal (Shared Partial)
    Include in both Doctor's encounter and Reception workbench.
    Requires: jQuery, DataTables, Bootstrap 5, toastr

    Usage:
        @include('admin.partials.medical_report_history_modal')
        Then call: openMedicalReportHistory(patientId)
--}}

<div class="modal fade" id="medicalReportHistoryModal" tabindex="-1" aria-labelledby="medicalReportHistoryModalLabel">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2" style="background: {{ appsettings('hos_color') ?? '#0066cc' }}; color: #fff;">
                <h5 class="modal-title" id="medicalReportHistoryModalLabel">
                    <i class="mdi mdi-file-document-multiple"></i> Medical Report History
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                {{-- Patient context --}}
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <strong id="mrh-patient-name" class="text-dark"></strong>
                        <small class="text-muted ms-2" id="mrh-patient-file"></small>
                    </div>
                    <div class="d-flex gap-2">
                        <select class="form-select form-select-sm" id="mrh-status-filter" style="width: 130px;">
                            <option value="">All Status</option>
                            <option value="finalized">Finalized</option>
                            <option value="draft">Draft</option>
                        </select>
                    </div>
                </div>

                {{-- DataTable --}}
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle" id="medical-reports-datatable" style="width: 100%;">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Title</th>
                                <th>Doctor</th>
                                <th>Status</th>
                                <th class="text-center" style="width: 150px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let medicalReportsTable = null;
let mrhPatientId = null;

/**
 * Open the medical report history modal for a given patient.
 * @param {number} patientId
 * @param {string|null} patientName  - optional, shown in header
 * @param {string|null} patientFileNo - optional, shown in header
 */
function openMedicalReportHistory(patientId, patientName, patientFileNo) {
    mrhPatientId = patientId;
    $('#mrh-patient-name').text(patientName || '');
    $('#mrh-patient-file').text(patientFileNo ? `(${patientFileNo})` : '');
    $('#mrh-status-filter').val('');

    initMedicalReportsTable(patientId);

    if (typeof window.openModalSafely === 'function') {
        window.openModalSafely('#medicalReportHistoryModal');
    } else if (typeof $.fn.modal === 'function') {
        $modal.modal('show');
    }
}

function initMedicalReportsTable(patientId) {
    if (medicalReportsTable) {
        medicalReportsTable.destroy();
        $('#medical-reports-datatable tbody').empty();
    }

    medicalReportsTable = $('#medical-reports-datatable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: `{{ url('patient') }}/${patientId}/medical-reports`,
            type: 'GET',
            dataSrc: function(json) {
                return json.success ? json.reports : [];
            }
        },
        columns: [
            {
                data: 'report_date',
                render: function(data, type, row) {
                    let sub = row.finalized_at ? `<br><small class="text-muted">Finalized: ${row.finalized_at}</small>` : '';
                    return data + sub;
                }
            },
            {
                data: 'title',
                render: function(data) {
                    return `<strong>${data}</strong>`;
                }
            },
            { data: 'doctor' },
            {
                data: 'status',
                render: function(data) {
                    if (data === 'finalized') {
                        return '<span class="badge bg-success"><i class="mdi mdi-check-circle"></i> Finalized</span>';
                    }
                    return '<span class="badge bg-warning text-dark"><i class="mdi mdi-pencil"></i> Draft</span>';
                }
            },
            {
                data: 'id',
                orderable: false,
                className: 'text-center',
                render: function(data, type, row) {
                    let btns = '';

                    // Print button — always available
                    btns += `<a href="{{ url('medical-reports') }}/${data}/print" target="_blank" class="btn btn-outline-primary btn-sm me-1" title="Print">
                                <i class="mdi mdi-printer"></i>
                             </a>`;

                    // Finalize button — only for drafts
                    if (row.status === 'draft') {
                        btns += `<button class="btn btn-outline-success btn-sm me-1" onclick="finalizeMedicalReport(${data})" title="Finalize">
                                    <i class="mdi mdi-check-decagram"></i>
                                 </button>`;
                    }

                    return btns;
                }
            }
        ],
        order: [[0, 'desc']],
        pageLength: 10,
        language: {
            emptyTable: '<div class="text-center text-muted py-3"><i class="mdi mdi-file-document-outline" style="font-size: 2rem;"></i><br>No medical reports found</div>',
            processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...'
        },
        dom: 'rtip',
    });
}

/**
 * Finalize a draft medical report (standalone — no print).
 */
function finalizeMedicalReport(reportId) {
    if (!confirm('Finalize this report? This action cannot be undone.')) return;

    $.ajax({
        url: `{{ url('medical-reports') }}/${reportId}/finalize`,
        type: 'POST',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: function(response) {
            if (response.success) {
                toastr.success('Report finalized successfully');
                if (medicalReportsTable) medicalReportsTable.ajax.reload(null, false);
                // Also refresh sidebar list if present (doctor's workbench)
                if (typeof loadPreviousReports === 'function') loadPreviousReports();
            } else {
                toastr.error(response.message || 'Failed to finalize');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to finalize report');
        }
    });
}

// Status filter
document.addEventListener('change', function(e) {
    if (e.target && e.target.id === 'mrh-status-filter') {
        var val = e.target.value;
        if (typeof medicalReportsTable !== 'undefined' && medicalReportsTable) {
            medicalReportsTable.column(3).search(val).draw();
        }
    }
});
</script>
