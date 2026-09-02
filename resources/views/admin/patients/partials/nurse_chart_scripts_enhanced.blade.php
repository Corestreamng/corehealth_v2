<script>

    // Add custom styles for intake/output sections
    document.addEventListener('DOMContentLoaded', function() {
        const style = document.createElement('style');
        style.textContent = `
            .period-card { transition: all 0.2s ease-in-out; }
            .period-card:hover { box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.15) !important; }
            .badge.rounded-pill { display: inline-flex; align-items: center; }
            .table-sm> :not(caption)> *> * { padding: 0.3rem 0.5rem; vertical-align: middle; }
            @media (max-width: 768px) {
                .period-card .card-header { flex-direction: column; align-items: start !important; }
                .period-card .card-header> div:last-child { margin-top: 0.5rem; width: 100%; }
            }

            // Add custom styles for medication chart responsiveness
            .medication-controls { justify-content: flex-end; }
            .schedule-slot { margin-bottom: 4px; transition: all 0.2s ease; }
            .schedule-slot:hover { transform: translateY(-2px); }
            #calendar-legend .badge { display: inline-flex; align-items: center; margin-bottom: 5px; }
            #calendar-legend .badge i { margin-right: 4px; }
            @media (max-width: 767.98px) {
                .medication-controls { justify-content: flex-start; margin-top: 10px; }
                #calendar-title { font-size: 0.9rem; }
                .table-sm td, .table-sm th { padding: 0.25rem 0.5rem; font-size: 0.85rem; }
            }
        `;
        document.head.appendChild(style);
    });

    // CSRF token for AJAX requests
    var CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var PATIENT_ID =
        {{ isset($patient) ? (is_object($patient) ? $patient->id : $patient) : (isset($patient_id) ? $patient_id : 'null') }};
    console.log('Patient ID:', PATIENT_ID);
    var medicationChartIndexRoute = "{{ route('nurse.medication.index', [':patient']) }}";
    var medicationChartScheduleRoute = "{{ route('nurse.medication.schedule') }}";
    var medicationChartAdministerRoute = "{{ route('nurse.medication.administer') }}";
    var medicationChartDiscontinueRoute = "{{ route('nurse.medication.discontinue') }}";
    var medicationChartResumeRoute = "{{ route('nurse.medication.resume') }}";
    var medicationChartDeleteRoute = "{{ route('nurse.medication.delete') }}";
    var medicationChartEditRoute = "{{ route('nurse.medication.edit') }}";
    var medicationChartRemoveScheduleRoute = "{{ route('nurse.medication.remove_schedule') }}";
    var medicationChartCalendarRoute =
        "{{ route('nurse.medication.calendar', [':patient', ':medication', ':start_date']) }}";
    var medicationChartPrescribedRoute = "{{ route('nurse.medication.prescribed_drugs', [':patient']) }}";
    var medicationChartDismissRoute = "{{ route('nurse.medication.dismiss_prescription', [':patient']) }}";
    var medicationChartAdministerDirectRoute = "{{ route('nurse.medication.administer_direct', [':patient']) }}";
    var medicationChartDirectCalendarRoute = "{{ route('nurse.medication.direct_calendar', [':patient']) }}";

    console.log('Medication Chart Routes:', {
        index: medicationChartIndexRoute,
        calendar: medicationChartCalendarRoute
    });

    // Remove schedule entry handler - defined outside rendering functions to prevent duplicates
    $(document).off('click', '.remove-schedule-btn').on('click', '.remove-schedule-btn', function(e) {
        e.preventDefault();
        const scheduleId = $(this).data('schedule-id');
        if (!scheduleId) return;
        if (!confirm('Are you sure you want to remove this schedule entry?')) return;

        const btn = $(this);
        btn.prop('disabled', true);
        btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');

        $.ajax({
            url: medicationChartRemoveScheduleRoute,
            type: 'POST',
            data: {
                schedule_id: scheduleId,
                _token: CSRF_TOKEN
            },
            success: function(response) {
                if (response.success) {
                    toastr.success('Schedule removed successfully.');
                    // Reload calendar for current medication and date range
                    if (selectedMedication) {
                        const startDateStr = $('#med-start-date').val();
                        const endDateStr = $('#med-end-date').val();
                        loadMedicationCalendarWithDateRange(selectedMedication,
                            startDateStr, endDateStr);
                    }
                } else {
                    toastr.error(response.message || 'Failed to remove schedule.');
                    btn.prop('disabled', false);
                    btn.html('<i class="mdi mdi-trash-can-outline"></i>');
                }
            },
            error: function(xhr) {
                console.error('Schedule removal error:', xhr);
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ?
                    xhr.responseJSON.message : 'Failed to remove schedule.');
                btn.prop('disabled', false);
                btn.html('<i class="mdi mdi-trash-can-outline"></i>');
            }
        });
    });

    // Helper function to get the best available user name from different properties
    function getUserName(obj) {
        return obj.user_fullname || obj.user_name || obj.administered_by_name ||
            (obj.administeredBy && obj.administeredBy.name) ||
            obj.nurse_name ||
            (obj.nurse && obj.nurse.name) || 'Unknown';
    }

    // §4.6: Drug source badge helper
    function getDrugSourceBadge(drugSource, productRequestId) {
        switch (drugSource) {
            case 'patient_own':
                return '<span class="badge" style="background:#7b1fa2;"><i class="mdi mdi-account-heart"></i> Patient\'s Own</span>';
            case 'ward_stock':
                if (productRequestId) {
                    return '<span class="badge bg-primary"><i class="mdi mdi-hospital-building"></i> Ward Stock (Billed)</span>';
                }
                return '<span class="badge bg-info"><i class="mdi mdi-hospital-building"></i> Ward Stock</span>';
            case 'pharmacy_dispensed':
            default:
                return '<span class="badge bg-success"><i class="mdi mdi-pill"></i> Pharmacy Dispensed</span>';
        }
    }

    // Configurable time window for editing/deleting administrations (from .env)
    var NOTE_EDIT_WINDOW = {{ appsettings('note_edit_window', 30) }}; // Default 30 minutes if not set
    var intakeOutputChartIndexRoute = "{{ route('nurse.intake_output.index', [':patient']) }}";
    var intakeOutputChartLogsRoute = "{{ route('nurse.intake_output.logs', [':patient', ':period']) }}";
    var intakeOutputChartStartRoute = "{{ route('nurse.intake_output.start') }}";
    var intakeOutputChartEndRoute = "{{ route('nurse.intake_output.end') }}";
    var intakeOutputChartRecordRoute = "{{ route('nurse.intake_output.record') }}";
</script>

<!-- Medication Logs Modal -->
<div class="modal fade" id="medicationLogsModal" tabindex="-1" aria-labelledby="medicationLogsModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="medication-logs-title">
                    <i class="mdi mdi-history text-primary me-1"></i> Activity Logs
                </h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" id="medication-logs-content">
                <!-- Logs content will be populated dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="mdi mdi-close-circle me-1"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add the Intake/Output Logs Modal -->
<div class="modal fade" id="intakeOutputLogsModal" tabindex="-1" aria-labelledby="intakeOutputLogsModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="io-logs-title">
                    <i class="mdi mdi-history me-1"></i> Intake/Output Period Logs
                </h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="io-logs-content">
                <!-- Logs content will be populated dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="mdi mdi-close-circle me-1"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

{{-- <!-- Add this to your HTML where the buttons should appear -->
 --}}

{{-- ======================================================================
     PRESCRIPTION DASHBOARD & DISMISS UI (Nurse Drug Source Revamp §6)
     ====================================================================== --}}

<script src="{{ asset('js/workbench-helper.js') }}"></script>
<script src="{{ asset('js/nurse-chart-scripts-enhanced.js') }}"></script>
