
import os

file_path = r'c:\Users\HARDMOTIONS\Documents\work\corehealth_v2\resources\views\admin\partials\unified_vitals.blade.php'

content = r"""<div class="card border-0 shadow-sm unified-vitals-container">
    <div class="card-header bg-white border-bottom">
        <ul class="nav nav-pills" id="vitals-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="new-vital-tab" data-bs-toggle="pill" data-toggle="pill" href="#new-vital" role="tab" aria-controls="new-vital" aria-selected="true">
                    <i class="mdi mdi-plus-circle me-1"></i> New Vital Reading
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="vitals-history-tab" data-bs-toggle="pill" data-toggle="pill" href="#vitals-history" role="tab" aria-controls="vitals-history" aria-selected="false">
                    <i class="mdi mdi-history me-1"></i> History
                </a>
            </li>
        </ul>
    </div>
    <div class="card-body p-3">
        <div class="tab-content">
            <!-- New Vital Form -->
            <div class="tab-pane fade show active" id="new-vital" role="tabpanel">
                <form id="new-vital-form" method="post" action="{{ route('vitals.store') }}">
                    @csrf
                    <input type="hidden" name="patient_id" id="unified_vitals_patient_id" value="{{ $patient->id ?? '' }}">
                    <div class="row g-3">
                         <div class="col-md-4">
                            <label class="form-label small text-muted">Blood Pressure</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light"><i class="mdi mdi-speedometer"></i></span>
                                <input type="text" class="form-control" name="bloodPressure" placeholder="120/80" pattern="\d+/\d+" required>
                            </div>
                         </div>
                         <div class="col-md-4">
                            <label class="form-label small text-muted">Temperature (°C)</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light"><i class="mdi mdi-thermometer"></i></span>
                                <input type="number" class="form-control" name="bodyTemperature" step="0.1" min="34" max="42" required>
                            </div>
                         </div>
                         <div class="col-md-4">
                            <label class="form-label small text-muted">Weight (kg)</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light"><i class="mdi mdi-weight"></i></span>
                                <input type="number" class="form-control" name="bodyWeight" step="0.1" min="1">
                            </div>
                         </div>
                         <div class="col-md-4">
                            <label class="form-label small text-muted">Heart Rate (bpm)</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light"><i class="mdi mdi-heart-pulse"></i></span>
                                <input type="number" class="form-control" name="heartRate" min="0">
                            </div>
                         </div>
                         <div class="col-md-4">
                            <label class="form-label small text-muted">Resp. Rate (bpm)</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light"><i class="mdi mdi-weather-windy"></i></span>
                                <input type="number" class="form-control" name="respiratoryRate" min="0">
                            </div>
                         </div>
                         <div class="col-md-4">
                            <label class="form-label small text-muted">Time Taken</label>
                            <input type="datetime-local" class="form-control form-control-sm" name="datetimeField" id="datetimeField" required>
                         </div>
                         <div class="col-12">
                            <label class="form-label small text-muted">Notes</label>
                            <textarea class="form-control form-control-sm" name="otherNotes" rows="2" placeholder="Optional notes..."></textarea>
                         </div>
                         <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="mdi mdi-check"></i> Save Vitals</button>
                         </div>
                    </div>
                </form>
            </div>

            <!-- History Table -->
            <div class="tab-pane fade" id="vitals-history" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-sm table-hover w-100" id="unified-vitals-history-table">
                        <thead class="bg-light">
                            <tr>
                                <th>History Log</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.initUnifiedVitals = function(patientId) {
    if (!patientId) {
        console.error("initUnifiedVitals called without patientId");
        return;
    }
    // Set form patient ID
    $('#unified_vitals_patient_id').val(patientId);

    // Destroy existing DataTable if it exists
    if ($.fn.DataTable.isDataTable('#unified-vitals-history-table')) {
        $('#unified-vitals-history-table').DataTable().destroy();
    }

    // Initialize DataTable
    $('#unified-vitals-history-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '/nursing-workbench/patient/' + patientId + '/vitals-history-dt',
        columns: [
            { data: 'info', name: 'info', orderable: false, searchable: false }
        ],
        ordering: false,
        lengthChange: false,
        pageLength: 5,
        searching: false,
        dom: "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-5'i><'col-sm-7'p>>",
        language: {
             emptyTable: "<div class='text-center py-4'>" +
                            "<i class='mdi mdi-heart-pulse text-muted' style='font-size: 3rem;'></i>" +
                            "<p class='text-muted mt-2'>No vitals history found</p>" +
                        "</div>"
        }
    });

    // Reset form field timestamp
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    $('#datetimeField').val(now.toISOString().slice(0, 16));
};

// Form Submission Handler (Attach once)
$(document).off('submit', '#new-vital-form').on('submit', '#new-vital-form', function(e) {
    e.preventDefault();
    const btn = $(this).find('button[type="submit"]');
    const form = $(this);
    const originalText = btn.html();

    btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving...');

    $.ajax({
        url: form.attr('action'),
        method: 'POST',
        data: form.serialize(),
        success: function(response) {
            toastr.success('Vitals saved successfully');
            form[0].reset();
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            $('#datetimeField').val(now.toISOString().slice(0, 16));

            // Keep the patient ID valid
            var currentPatientId = $('#unified_vitals_patient_id').val();
            $('#unified_vitals_patient_id').val(currentPatientId);

            // Switch to history tab and reload
            var historyTab = document.querySelector('#vitals-history-tab');
            if(historyTab) {
                // Try bootstrap 5
                try {
                     var tab = new bootstrap.Tab(historyTab);
                     tab.show();
                } catch(e) {
                     // Try bootstrap 4 / jQuery
                     $(historyTab).tab('show');
                }
            }
            $('#unified-vitals-history-table').DataTable().ajax.reload();
        },
        error: function(xhr) {
             let msg = 'Failed to save vitals';
             if(xhr.responseJSON && xhr.responseJSON.message) {
                 msg = xhr.responseJSON.message;
             }
             toastr.error(msg);
        },
        complete: function() {
            btn.prop('disabled', false).html(originalText);
        }
    });
});
</script>
"""

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
print('Successfully rewrote ' + file_path)
