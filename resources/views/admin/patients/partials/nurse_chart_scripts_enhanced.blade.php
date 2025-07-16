<script>
    // Add custom styles for intake/output sections
    document.addEventListener('DOMContentLoaded', function() {
        const style = document.createElement('style');
        style.textContent = `
            .period-card { transition: all 0.2s ease-in-out; }
            .period-card:hover { box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.15) !important; }
            .badge.rounded-pill { display: inline-flex; align-items: center; }
            .table-sm > :not(caption) > * > * { padding: 0.3rem 0.5rem; vertical-align: middle; }
            @media (max-width: 768px) {
                .period-card .card-header { flex-direction: column; align-items: start !important; }
                .period-card .card-header > div:last-child { margin-top: 0.5rem; width: 100%; }
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

    // Configurable time window for editing/deleting administrations (from .env)
    var NOTE_EDIT_WINDOW = {{ env('NOTE_EDIT_WINDOW', 30) }}; // Default 30 minutes if not set
    var intakeOutputChartIndexRoute = "{{ route('nurse.intake_output.index', [':patient']) }}";
    var intakeOutputChartLogsRoute = "{{ route('nurse.intake_output.logs', [':patient', ':period']) }}";
    var intakeOutputChartStartRoute = "{{ route('nurse.intake_output.start') }}";
    var intakeOutputChartEndRoute = "{{ route('nurse.intake_output.end') }}";
    var intakeOutputChartRecordRoute = "{{ route('nurse.intake_output.record') }}";
</script>
<script>
    // Nurse Chart JS (AJAX + Toastr)

    // Global variables for medication chart
    let selectedMedication = null;
    let calendarStartDate = new Date();
    calendarStartDate.setDate(calendarStartDate.getDate() - 15); // Start 15 days before today
    let medications = [];
    let medicationStatus = {};
    let currentSchedules = [];
    let currentAdministrations = [];
    let medicationHistory = []; // Added to store history/logs

    // Set edit window time display
    $('#edit-window-time').text(NOTE_EDIT_WINDOW);
    $('#delete-window-time').text(NOTE_EDIT_WINDOW);

    // Initialize date inputs with today's date
    if (document.getElementById('schedule_date')) {
        document.getElementById('schedule_date').valueAsDate = new Date();
    }

    // =============================================
    // MEDICATION CHART INITIALIZATION AND DATA LOADING
    // =============================================

    // Load medications list
    loadMedicationsList();

    function loadMedicationsList() {
        console.log('Loading medications list...');
        $('#medication-loading').show();
        $('#medication-calendar').hide();

        // Check if PATIENT_ID is properly set
        if (PATIENT_ID === null || PATIENT_ID === 'null') {
            console.error('Patient ID is not set properly');
            $('#medication-loading').hide();
            toastr.error('Patient ID is not available. Please reload the page.');
            return;
        }

        const ajaxUrl = medicationChartIndexRoute.replace(':patient', PATIENT_ID);
        console.log('Sending AJAX request to:', ajaxUrl);

        $.ajax({
            url: ajaxUrl,
            type: 'GET',
            // No date parameters needed for medication chart anymore
            success: function(data) {
                console.log('Medications loaded successfully:', data);
                $('#medication-loading').hide();
                medications = data.prescriptions || [];

                // Populate medication dropdown
                const select = $('#drug-select');
                select.empty();
                select.append('<option value="">-- Select a medication --</option>');

                if (medications.length === 0) {
                    console.log('No medications found');
                    toastr.warning('No medications found for this patient.');
                } else {
                    console.log(`Found ${medications.length} medications`);
                    medications.forEach(function(p) {
                        const prod = p.product || {};
                        select.append(
                            `<option value="${p.id}">${prod.product_name || 'Unknown'} - ${prod.product_code || ''}</option>`
                        );

                        // Store medication status
                        medicationStatus[p.id] = {
                            discontinued: !!p.discontinued_at,
                            discontinued_at: p.discontinued_at,
                            discontinued_reason: p.discontinued_reason,
                            resumed: !!p.resumed_at,
                            resumed_at: p.resumed_at,
                            resumed_reason: p.resumed_reason
                        };
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to load medications:', status, error);
                console.error(xhr.responseText);
                $('#medication-loading').hide();
                toastr.error(`Failed to load medications: ${error}`);
            },
            complete: function() {
                console.log('Medications AJAX request completed');
            }
        });
    }

    // =============================================
    // MEDICATION SELECTION AND CALENDAR LOADING
    // =============================================

    // Drug selection change
    $('#drug-select').change(function() {
        const medicationId = $(this).val();

        if (medicationId) {
            selectedMedication = medicationId;
            $('#set-schedule-btn').prop('disabled', false);

            // Calculate end date (30 days after start date)
            const endDate = new Date(calendarStartDate);
            endDate.setDate(endDate.getDate() + 30);

            // Update the date range inputs to reflect the initial view
            const startDateStr = formatDateForApi(calendarStartDate);
            const endDateStr = formatDateForApi(endDate);
            $('#med-start-date').val(startDateStr);
            $('#med-end-date').val(endDateStr);

            // Load medication calendar with date range
            loadMedicationCalendarWithDateRange(medicationId, startDateStr, endDateStr);
        } else {
            selectedMedication = null;
            $('#medication-calendar').hide();
            $('#calendar-legend').hide();
            $('#set-schedule-btn').prop('disabled', true);
            $('#discontinue-btn').prop('disabled', true);
            $('#resume-btn').prop('disabled', true);
            $('#medication-status').empty();
        }
    });

    // Calendar navigation
    $('#prev-month-btn').click(function() {
        if (selectedMedication) {
            // Move back 30 days
            calendarStartDate.setDate(calendarStartDate.getDate() - 30);

            // Calculate end date (30 days after start date)
            const endDate = new Date(calendarStartDate);
            endDate.setDate(endDate.getDate() + 30);

            // Update the date range inputs to reflect the new range
            const startDateStr = formatDateForApi(calendarStartDate);
            const endDateStr = formatDateForApi(endDate);
            $('#med-start-date').val(startDateStr);
            $('#med-end-date').val(endDateStr);

            // Load calendar with new date range
            loadMedicationCalendarWithDateRange(selectedMedication, startDateStr, endDateStr);
        }
    });

    $('#next-month-btn').click(function() {
        if (selectedMedication) {
            // Move forward 30 days
            calendarStartDate.setDate(calendarStartDate.getDate() + 30);

            // Calculate end date (30 days after start date)
            const endDate = new Date(calendarStartDate);
            endDate.setDate(endDate.getDate() + 30);

            // Update the date range inputs to reflect the new range
            const startDateStr = formatDateForApi(calendarStartDate);
            const endDateStr = formatDateForApi(endDate);
            $('#med-start-date').val(startDateStr);
            $('#med-end-date').val(endDateStr);

            // Load calendar with new date range
            loadMedicationCalendarWithDateRange(selectedMedication, startDateStr, endDateStr);
        }
    });

    $('#today-btn').click(function() {
        if (selectedMedication) {
            // Reset to 15 days before today
            calendarStartDate = new Date();
            calendarStartDate.setDate(calendarStartDate.getDate() - 15);

            // Calculate end date (30 days after start date)
            const endDate = new Date();
            endDate.setDate(endDate.getDate() + 15);

            // Update the date range inputs to reflect the new range
            const startDateStr = formatDateForApi(calendarStartDate);
            const endDateStr = formatDateForApi(endDate);
            $('#med-start-date').val(startDateStr);
            $('#med-end-date').val(endDateStr);

            // Load calendar with new date range
            loadMedicationCalendarWithDateRange(selectedMedication, startDateStr, endDateStr);
        }
    });

    // Initialize date range inputs with default values or based on current calendar
    function initializeMedicationDateRange(startDate = null, endDate = null) {
        // If no start date provided, default to 15 days ago
        if (!startDate) {
            startDate = new Date();
            startDate.setDate(startDate.getDate() - 15);
        }
        $('#med-start-date').val(startDate instanceof Date ? startDate.toISOString().split('T')[0] : startDate);

        // If no end date provided, default to 30 days after start date
        if (!endDate) {
            endDate = new Date(startDate);
            endDate.setDate(endDate.getDate() + 30);
        }
        $('#med-end-date').val(endDate instanceof Date ? endDate.toISOString().split('T')[0] : endDate);
    }

    // Initialize date range on page load
    initializeMedicationDateRange();

    // Handle the apply date range button click
    $('#apply-date-range-btn').on('click', function() {
        if (!selectedMedication) {
            toastr.warning('Please select a medication first.');
            return;
        }

        const startDateStr = $('#med-start-date').val();
        const endDateStr = $('#med-end-date').val();

        if (!startDateStr || !endDateStr) {
            toastr.warning('Please select both start and end dates.');
            return;
        }

        const startDate = new Date(startDateStr);
        const endDate = new Date(endDateStr);

        if (startDate > endDate) {
            toastr.warning('Start date cannot be after end date.');
            return;
        }

        // Update the calendar start date and load the calendar with the custom range
        calendarStartDate = startDate;
        loadMedicationCalendarWithDateRange(selectedMedication, startDateStr, endDateStr);
    });

    // Function to load medication calendar with a specific date range
    function loadMedicationCalendarWithDateRange(medicationId, startDate, endDate) {
        if (!medicationId) return;

        $('#medication-loading').show();
        $('#medication-calendar').hide();

        const url = medicationChartCalendarRoute
            .replace(':patient', PATIENT_ID)
            .replace(':medication', medicationId)
            .replace(':start_date', startDate);

        $.ajax({
            url: url,
            type: 'GET',
            data: {
                start_date: startDate,
                end_date: endDate
            },
            success: function(data) {
                $('#medication-loading').hide();

                if (data.medication) {
                    const medication = data.medication;
                    currentSchedules = data.schedules || [];
                    currentAdministrations = data.administrations || [];

                    // Update UI based on medication status
                    updateMedicationStatus(medication);
                    updateMedicationButtons(medication);

                    // Store history data for logs
                    let logEntries = [];

                    // Process medication history (discontinue/resume events)
                    if (data.history && Array.isArray(data.history)) {
                        logEntries = [...data.history];
                    }

                    // Process administration history
                    if (data.adminHistory && Array.isArray(data.adminHistory)) {
                        data.adminHistory.forEach(admin => {
                            // Regular administration event
                            logEntries.push({
                                date: admin.administered_at,
                                action: 'administration',
                                details: `${admin.dose} ${admin.route} ${admin.comment ? '- ' + admin.comment : ''}`,
                                user: admin.administered_by_name || getUserName(admin) ||
                                    'Unknown',
                                id: admin.id
                            });

                            // Edit event if applicable
                            if (admin.edited_at) {
                                logEntries.push({
                                    date: admin.edited_at,
                                    action: 'edit',
                                    details: admin.edit_reason || 'No reason provided',
                                    user: admin.edited_by_name || (admin.edited_by ? admin
                                        .edited_by.name : 'Unknown'),
                                    id: admin.id
                                });
                            }

                            // Delete event if applicable
                            if (admin.deleted_at) {
                                logEntries.push({
                                    date: admin.deleted_at,
                                    action: 'delete',
                                    reason: admin.delete_reason || 'No reason provided',
                                    user: admin.deleted_by_name || (admin.deleted_by ? admin
                                        .deleted_by.name : 'Unknown'),
                                    id: admin.id
                                });
                            }
                        });
                    }

                    // Store all logs for this medication
                    medicationHistory[selectedMedication] = logEntries;

                    // Show the calendar with custom date range
                    renderCalendarView(medication, currentSchedules, currentAdministrations, data.period);
                    renderLegend();
                    $('#medication-calendar').show();
                    $('#calendar-legend').show();

                    // Update date range inputs to match the loaded calendar view
                    initializeMedicationDateRange(data.period.start, data.period.end);
                }
            },
            error: function() {
                $('#medication-loading').hide();
                toastr.error('Failed to load medication calendar.');
            }
        });
    }

    function loadMedicationCalendar(medicationId, startDate) {
        if (!medicationId) return;

        // Calculate end date (30 days after start)
        const endDate = new Date(startDate);
        endDate.setDate(endDate.getDate() + 30);

        // Format dates for API
        const startDateStr = formatDateForApi(startDate);
        const endDateStr = formatDateForApi(endDate);

        // Use loadMedicationCalendarWithDateRange for consistent behavior
        return loadMedicationCalendarWithDateRange(medicationId, startDateStr, endDateStr);
    }

    // This is the original loadMedicationCalendar function, preserved for compatibility
    function _loadMedicationCalendarOriginal(medicationId, startDate) {
        if (!medicationId) return;

        $('#medication-loading').show();
        $('#medication-calendar').hide();

        const formattedStartDate = formatDateForApi(startDate);
        const url = medicationChartCalendarRoute
            .replace(':patient', PATIENT_ID)
            .replace(':medication', medicationId)
            .replace(':start_date', formattedStartDate);

        $.ajax({
            url: url,
            type: 'GET',
            success: function(data) {
                $('#medication-loading').hide();

                if (data.medication) {
                    const medication = data.medication;
                    currentSchedules = data.schedules || [];
                    currentAdministrations = data.administrations || [];

                    // Update UI based on medication status
                    updateMedicationStatus(medication);
                    updateMedicationButtons(medication);

                    // Store history data for logs
                    if (data.history) {
                        medicationHistory[selectedMedication] = data.history;
                    }

                    // Store admin history
                    if (data.adminHistory) {
                        if (!medicationHistory[selectedMedication]) {
                            medicationHistory[selectedMedication] = [];
                        }

                        // Add administration events to history
                        data.adminHistory.forEach(admin => {
                            // Administration event
                            medicationHistory[selectedMedication].push({
                                date: new Date(admin.created_at),
                                action: 'administration',
                                details: `Administered ${admin.dose} via ${admin.route}`,
                                user: admin.administered_by_name,
                                medication_name: medication.product ? medication.product
                                    .product_name : 'Medication'
                            });

                            // Edit event if applicable
                            if (admin.edited_at) {
                                medicationHistory[selectedMedication].push({
                                    date: new Date(admin.edited_at),
                                    action: 'edit',
                                    reason: admin.edit_reason,
                                    details: `Edited administration (${admin.dose} via ${admin.route})`,
                                    user: admin.edited_by_name,
                                    medication_name: medication.product ? medication.product
                                        .product_name : 'Medication'
                                });
                            }

                            // Delete event if applicable
                            if (admin.deleted_at) {
                                medicationHistory[selectedMedication].push({
                                    date: new Date(admin.deleted_at),
                                    action: 'delete',
                                    reason: admin.delete_reason,
                                    details: `Deleted administration (${admin.dose} via ${admin.route})`,
                                    user: admin.deleted_by_name,
                                    medication_name: medication.product ? medication.product
                                        .product_name : 'Medication'
                                });
                            }
                        });
                    }

                    // Show calendar and legend
                    renderCalendarView(medication, currentSchedules, currentAdministrations, data.period);
                    renderLegend(); // Add legend rendering
                    $('#medication-calendar').show();
                    $('#calendar-legend').show();

                    // Update date range inputs to match current calendar view
                    if (data.period && data.period.start && data.period.end) {
                        initializeMedicationDateRange(data.period.start, data.period.end);
                    }
                } else {
                    toastr.warning('Medication data not found.');
                }
            },
            error: function() {
                $('#medication-loading').hide();
                toastr.error('Failed to load medication calendar.');
            }
        });
    }

    function updateMedicationStatus(medication) {
        let statusHtml = '';

        if (medication.product && medication.product.product_name) {
            const productName = medication.product.product_name;

            if (medication.discontinued_at) {
                const discontinuedDate = new Date(medication.discontinued_at);
                const discontinuedBy = medication.discontinued_by_name || medication.user_fullname || medication
                    .discontinued_by || 'Unknown';

                statusHtml += `
                    <div class="alert alert-danger py-2 mb-0">
                        <div class="d-flex align-items-center">
                            <i class="mdi mdi-calendar-remove me-2 fs-5"></i>
                            <div>
                                <strong>${productName}</strong>: Discontinued
                                <div class="small">
                                    <span class="fw-bold">Date:</span> ${formatDate(discontinuedDate)}
                                    <span class="fw-bold">By:</span> ${discontinuedBy}
                                </div>
                                <div class="small"><span class="fw-bold">Reason:</span> ${medication.discontinued_reason}</div>
                            </div>
                        </div>
                    `;

                if (medication.resumed_at) {
                    const resumedDate = new Date(medication.resumed_at);
                    const resumedBy = medication.resumed_by_name || medication.user_fullname || medication.resumed_by ||
                        'Unknown';

                    statusHtml += `
                        <div class="mt-2 d-flex align-items-center">
                            <i class="mdi mdi-calendar-check me-2 fs-5 text-success"></i>
                            <div>
                                <strong class="text-success">Resumed</strong>
                                <div class="small">
                                    <span class="fw-bold">Date:</span> ${formatDate(resumedDate)}
                                    <span class="fw-bold">By:</span> ${resumedBy}
                                </div>
                                <div class="small"><span class="fw-bold">Reason:</span> ${medication.resumed_reason}</div>
                            </div>
                        </div>
                        `;
                }

                statusHtml += `</div>`;
            } else {
                statusHtml += `
                    <div class="alert alert-success py-2 mb-0">
                        <div class="d-flex align-items-center">
                            <i class="mdi mdi-check-circle me-2 fs-5"></i>
                            <div>
                                <strong>${productName}</strong>: <span class="badge bg-success">Active</span>
                            </div>
                        </div>
                    </div>`;
            }
        }

        $('#medication-status').html(statusHtml);
    }

    function updateMedicationButtons(medication) {
        const isDiscontinued = !!medication.discontinued_at;
        const isResumed = !!medication.resumed_at;

        // Handle the case where it's discontinued but then resumed
        const effectivelyDiscontinued = isDiscontinued && !isResumed;

        $('#discontinue-btn').prop('disabled', effectivelyDiscontinued);
        $('#resume-btn').prop('disabled', !effectivelyDiscontinued);
        $('#set-schedule-btn').prop('disabled', effectivelyDiscontinued);

        // Enable logs button if medication is selected
        $('#view-logs-btn').prop('disabled', false);
    }

    // Add click handler for view logs button
    $('#view-logs-btn').click(function() {
        showMedicationLogs();
    });

    // Render the calendar legend
    function renderLegend() {
        const legendHtml = `
            <div class="card shadow-sm mb-3">
                <div class="card-body p-2">
                    <h6 class="card-title mb-2 d-flex align-items-center">
                        <i class="mdi mdi-information-outline text-primary me-1"></i> Legend
                    </h6>
                    <div class="d-flex flex-wrap gap-1">
                        <span class="badge bg-primary rounded-pill">
                            <i class="mdi mdi-calendar-clock"></i> Scheduled
                        </span>
                        <span class="badge bg-success rounded-pill">
                            <i class="mdi mdi-check"></i> Administered
                        </span>
                        <span class="badge bg-info rounded-pill">
                            <i class="mdi mdi-pencil"></i> Edited
                        </span>
                        <span class="badge bg-dark rounded-pill">
                            <i class="mdi mdi-close"></i> Deleted
                        </span>
                        <span class="badge bg-danger rounded-pill">
                            <i class="mdi mdi-calendar-remove"></i> Missed
                        </span>
                        <span class="badge bg-secondary rounded-pill">
                            <i class="mdi mdi-calendar"></i> Discontinued
                        </span>
                    </div>
                </div>
            </div>
            `;

        $('#calendar-legend').html(legendHtml);
    }

    function renderCalendarView(medication, schedules, administrations, period) {
        const startDate = new Date(period.start);
        const endDate = new Date(period.end);
        const product = medication.product || {};

        // Update calendar title with responsive design
        const productName = product.product_name || 'Medication';
        // Get doctor recommended dose/freq from medication.dose or medication.product_request.dose
        let doctorDose = medication.dose || (medication.product_request && medication.product_request.dose) || '';
        if (!doctorDose && medication.product_or_service_request_id && medications) {
            // fallback: try to find in medications array
            const medObj = medications.find(m => m.id == medication.product_or_service_request_id);
            if (medObj && medObj.dose) doctorDose = medObj.dose;
        }


        // Get doctor name and prescription date from backend fields
        let doctorName = medication.doctor_name || '';
        let prescriptionDate = medication.prescription_date ? formatDateTime(new Date(medication.prescription_date)) :
            '';

        // Build the doctor info display with clear layout
        let doctorInfoHtml = '';
        if (doctorDose) {
            doctorInfoHtml +=
                `<div class="my-2"><span class="badge bg-warning text-dark fw-bold" style="font-size:1.05rem;vertical-align:middle;">Doctor's Order: ${doctorDose}</span></div>`;
        }
        if (doctorName) {
            doctorInfoHtml +=
                `<div class="mb-1"><span class="text-primary fw-semibold"><i class="mdi mdi-account"></i> Doctor: ${doctorName}</span></div>`;
        }
        if (prescriptionDate) {
            doctorInfoHtml +=
                `<div class="mb-2"><span class="text-muted small"><i class="mdi mdi-calendar-clock"></i> Prescribed: ${prescriptionDate}</span></div>`;
        }

        const dateRange = `${formatDate(startDate)} to ${formatDate(endDate)}`;

        $('#calendar-title').html(
            `<div class="mb-1"><span class="text-primary fw-bold" style="font-size:1.15rem;">${productName}</span></div>
            ${doctorInfoHtml}
            <div class="d-block d-sm-inline small text-muted mb-1">
                <i class="mdi mdi-calendar-range"></i> ${dateRange}
            </div>`
        );

        let daysHtml = '';
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        // Generate days array
        const days = [];
        for (let d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
            days.push(new Date(d));
        }

        // Generate calendar body
        let calendarHtml = '';

        days.forEach((day, index) => {
            const isToday = day.toDateString() === today.toDateString();
            const rowClass = isToday ? 'table-info' : '';

            calendarHtml += `
                <tr class="${rowClass}">
                    <td class="text-center">${getDayOfWeek(day)}</td>
                    <td>${formatDate(day)}</td>
                    <td>
                        <div class="d-flex flex-wrap schedule-slots" data-date="${formatDateForApi(day)}">
                `;

            // Find schedules for this day
            const daySchedules = schedules.filter(s => {
                const scheduleDate = new Date(s.scheduled_time);
                return scheduleDate.toDateString() === day.toDateString();
            });

            if (daySchedules.length === 0) {
                calendarHtml += `<span class="text-muted small">No schedules</span>`;
            } else {
                daySchedules.forEach(schedule => {
                    const scheduleTime = new Date(schedule.scheduled_time);
                    const formattedTime = formatTime(scheduleTime);

                    // Find if there's an administration for this schedule
                    const admin = administrations.find(a => a.schedule_id === schedule.id);

                    let badgeClass = 'bg-primary'; // Default: Scheduled
                    let badgeContent = `<i class="mdi mdi-calendar-clock"></i> ${formattedTime}`;
                    let adminAction =
                        `data-bs-toggle=\"modal\" data-bs-target=\"#administerModal\" data-schedule-id=\"${schedule.id}\"`;
                    let status = 'Scheduled';
                    let tooltipContent =
                        `Dose: ${schedule.dose}<br>Route: ${schedule.route}<br>Status: Scheduled`;

                    // Check for discontinuation
                    const isDiscontinued = medication.discontinued_at &&
                        new Date(medication.discontinued_at) < scheduleTime &&
                        (!medication.resumed_at || new Date(medication.resumed_at) > scheduleTime);

                    if (isDiscontinued) {
                        badgeClass = 'bg-secondary';
                        adminAction = '';
                        status = 'Discontinued';
                        tooltipContent =
                            `Dose: ${schedule.dose}<br>Route: ${schedule.route}<br>Status: Discontinued`;
                        if (medication.discontinued_reason) {
                            tooltipContent += `<br>Reason: ${medication.discontinued_reason}`;
                        }
                        if (medication.discontinued_by_name) {
                            tooltipContent += `<br>Discontinued by: ${medication.discontinued_by_name}`;
                        }
                        if (medication.discontinued_at) {
                            tooltipContent +=
                                `<br>Discontinued on: ${formatDateTime(new Date(medication.discontinued_at))}`;
                        }
                    } else if (admin) {
                        // Base for administered entries
                        badgeClass = 'bg-success';
                        badgeContent = `<i class="mdi mdi-check"></i> ${formattedTime}`;
                        adminAction =
                            `data-bs-toggle=\"modal\" data-bs-target=\"#adminDetailsModal\" data-admin-id=\"${admin.id}\"`;
                        status = 'Administered';

                        const adminTime = formatDateTime(new Date(admin.administered_at));
                        tooltipContent =
                            `Dose: ${admin.dose}<br>Route: ${admin.route}<br>Status: Administered<br>Time: ${adminTime}`;

                        if (admin.administered_by_name) {
                            tooltipContent += `<br>By: ${admin.administered_by_name}`;
                        }

                        if (admin.comment) {
                            tooltipContent += `<br>Note: ${admin.comment}`;
                        }

                        // Append edited info if it exists
                        if (admin.edited_at) {
                            badgeClass = 'bg-info';
                            badgeContent = `<i class="mdi mdi-pencil"></i> ${formattedTime}`;
                            status = 'Edited'; // Status is now Edited
                            tooltipContent +=
                                `<hr class='my-1'><strong>Edited:</strong> ${formatDateTime(new Date(admin.edited_at))}`;
                            if (admin.edited_by_name) {
                                tooltipContent += `<br>Edited by: ${admin.edited_by_name}`;
                            }
                            if (admin.edit_reason) {
                                tooltipContent += `<br>Reason: ${admin.edit_reason}`;
                            }
                            if (admin.previous_data) {
                                try {
                                    const prevData = JSON.parse(admin.previous_data);
                                    tooltipContent += `<br>Previous Dose: ${prevData.dose}`;
                                    tooltipContent += `<br>Previous Route: ${prevData.route}`;
                                } catch (e) {
                                    /* ignore */ }
                            }
                        }

                        // Append deleted info if it exists
                        if (admin.deleted_at) {
                            badgeClass = 'bg-dark';
                            badgeContent = `<i class="mdi mdi-close"></i> ${formattedTime}`;
                            adminAction = ''; // No action for deleted items
                            status = 'Deleted'; // Status is now Deleted
                            tooltipContent +=
                                `<hr class='my-1'><strong>Deleted:</strong> ${formatDateTime(new Date(admin.deleted_at))}`;
                            if (admin.deleted_by_name) {
                                tooltipContent += `<br>Deleted by: ${admin.deleted_by_name}`;
                            }
                            if (admin.delete_reason) {
                                tooltipContent += `<br>Reason: ${admin.delete_reason}`;
                            }
                        }
                    } else {
                        // Not administered (missed or scheduled)
                        const now = new Date();
                        if (scheduleTime < now) {
                            badgeClass = 'bg-danger';
                            badgeContent = `<i class="mdi mdi-alert"></i> ${formattedTime}`;
                            status = 'Missed';
                            tooltipContent =
                                `Dose: ${schedule.dose}<br>Route: ${schedule.route}<br>Status: Missed<br>Scheduled for: ${formatDateTime(scheduleTime)}`;
                        } else {
                            // This is for future scheduled entries
                            status = 'Scheduled';
                            tooltipContent =
                                `Dose: ${schedule.dose}<br>Route: ${schedule.route}<br>Status: Scheduled`;
                        }
                    }

                    // Add remove button if no admin exists and not discontinued
                    let removeBtn = '';
                    if (!admin && !isDiscontinued) {
                        removeBtn =
                            `<button class='btn btn-sm btn-outline-danger ms-1 remove-schedule-btn' data-schedule-id='${schedule.id}' title='Remove schedule'><i class='mdi mdi-trash-can-outline'></i></button>`;
                    }

                    calendarHtml +=
                        `<span class="schedule-slot badge ${badgeClass} rounded-pill me-1"
                            ${adminAction}
                            data-bs-toggle="tooltip"
                            data-bs-html="true"
                            data-bs-title="${tooltipContent}">${badgeContent}</span>${removeBtn}`;
                });
            }

            calendarHtml += `
                        </div>
                    </td>
                </tr>`;
        });

        // Set the generated calendar HTML
        $('#calendar-body').html(calendarHtml);

        // Initialize tooltips on calendar entries - destroy existing tooltips first to prevent duplicates
        $('.schedule-slot[data-bs-toggle="tooltip"]').tooltip('dispose');

        // Initialize all tooltips with improved configuration
        $('.schedule-slot[data-bs-toggle="tooltip"]').tooltip({
            placement: 'top',
            trigger: 'hover',
            container: 'body',
            html: true,
            animation: true,
            delay: {
                show: 100,
                hide: 100
            },
            boundary: 'window'
        });
    }

    function showMedicationLogs() {
        if (!selectedMedication) return;

        // Get logs for this medication
        const logs = medicationHistory[selectedMedication] || [];
        let logsHtml = '';

        if (logs.length === 0) {
            logsHtml = '<div class="alert alert-info">No activity logs available for this medication.</div>';
        } else {
            logsHtml = '<div class="table-responsive"><table class="table table-sm table-striped table-hover mb-0">';
            logsHtml +=
                '<thead class="table-light"><tr><th style="width:160px">Date & Time</th><th style="width:120px">Action</th><th>Details</th><th style="width:140px">User</th></tr></thead><tbody>';

            logs.forEach(log => {
                const logDate = new Date(log.date);
                let actionBadgeClass = 'bg-primary';
                let actionText = log.action;
                let actionIcon = 'mdi-information-outline';

                // Style based on action type
                // Check if action property exists before using toLowerCase()
                const action = log.action ? log.action.toLowerCase() : '';
                switch (action) {
                    case 'administration':
                        actionBadgeClass = 'bg-success';
                        actionText = 'Administered';
                        actionIcon = 'mdi-check';
                        break;
                    case 'edit':
                        actionBadgeClass = 'bg-info';
                        actionText = 'Edited';
                        actionIcon = 'mdi-pencil';
                        break;
                    case 'delete':
                        actionBadgeClass = 'bg-dark';
                        actionText = 'Deleted';
                        actionIcon = 'mdi-close';
                        break;
                    case 'discontinue':
                        actionBadgeClass = 'bg-warning';
                        actionText = 'Discontinued';
                        actionIcon = 'mdi-calendar-remove';
                        break;
                    case 'resume':
                        actionBadgeClass = 'bg-success';
                        actionText = 'Resumed';
                        actionIcon = 'mdi-calendar-check';
                        break;
                }

                logsHtml += `<tr>
                        <td><small>${formatDateTime(logDate)}</small></td>
                        <td><span class="badge ${actionBadgeClass} rounded-pill">
                            <i class="mdi ${actionIcon}"></i> ${actionText}</span>
                        </td>
                        <td>${log.details || log.reason || '-'}</td>
                        <td><small>${log.user || 'Unknown'}</small></td>
                    </tr>`;
            });

            logsHtml += '</tbody></table></div>';
        }

        // Get medication name
        const medication = medications.find(m => m.id == selectedMedication);
        const medicationName = medication && medication.product ? medication.product.product_name : 'Medication';

        // Populate and show logs modal
        $('#medication-logs-title').text(`Activity Logs: ${medicationName}`);
        $('#medication-logs-content').html(logsHtml);

        // Use jQuery modal method to match other modals in the file
        console.log('Attempting to show logs modal');
        try {
            // Check if modal exists in the DOM
            if ($('#medicationLogsModal').length === 0) {
                console.error('Modal element not found in DOM');
                // Try to add the modal if it doesn't exist
                if ($('body').find('#medicationLogsModal').length === 0) {
                    console.log('Creating modal element');
                    const modalHTML = `
                        <div class="modal fade" id="medicationLogsModal" tabindex="-1" aria-labelledby="medicationLogsModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="medication-logs-title">Activity Logs</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">x</button>
                                    </div>
                                    <div class="modal-body" id="medication-logs-content">
                                        <!-- Logs content will be populated dynamically -->
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                    $('body').append(modalHTML);
                }
            }

            $('#medicationLogsModal').modal('show');
            console.log('Modal show method called');
        } catch (err) {
            console.error('Error showing modal:', err);
        }
    }

    // =============================================
    // SCHEDULE MANAGEMENT
    // =============================================

    // Set Schedule button click
    $('#set-schedule-btn').click(function() {
        if (!selectedMedication) return;

        // Find the selected medication
        const medication = medications.find(m => m.id == selectedMedication);
        if (!medication || !medication.product) return;

        $('#schedule_medication_id').val(selectedMedication);
        $('#schedule-medication-name').text(medication.product.product_name);
        $('#setScheduleModal').modal('show');
    });

    // Toggle repeat days selector
    $('input[name="repeat_type"]').change(function() {
        if ($('#repeat_selected_days').is(':checked')) {
            $('#days-selector').show();
        } else {
            $('#days-selector').hide();
        }
    });

    // Set Schedule form submission
    $('#setScheduleForm').submit(function(e) {
        e.preventDefault();

        const medicationId = $('#schedule_medication_id').val();
        const patientId = $('#schedule_patient_id').val();
        const startDate = $('#schedule_date').val();
        const time = $('#schedule_time').val();
        const dose = $('#schedule_dose').val();
        const route = $('#schedule_route').val();
        const durationDays = $('#schedule_duration').val();
        let repeatDaily = $('#repeat_daily').is(':checked');
        let selectedDays = [];

        // Check which repeat type is selected
        if ($('#repeat_daily').is(':checked')) {
            repeatDaily = true;
        } else {
            repeatDaily = false;

            // Get selected days
            $('.btn-check:checked').each(function() {
                selectedDays.push($(this).val());
            });

            if (selectedDays.length === 0) {
                toastr.warning('Please select at least one day for repeating schedule.');
                return;
            }
        }

        // Show loading indicator
        $('#scheduleSubmitBtn .spinner-border').removeClass('d-none');
        $('#scheduleSubmitBtn').prop('disabled', true);

        // Prepare data for API
        const scheduleData = {
            patient_id: patientId,
            product_or_service_request_id: medicationId,
            start_date: startDate,
            time: time,
            dose: dose,
            route: route,
            repeat_daily: repeatDaily ? 1 :
            0, // Convert to 1/0 for Laravel to properly interpret as boolean
            selected_days: selectedDays,
            duration_days: durationDays
        };

        $.ajax({
            url: medicationChartScheduleRoute,
            type: 'POST',
            data: scheduleData,
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            success: function(response) {
                if (response.success) {
                    toastr.success('Schedule created successfully.');
                    $('#setScheduleModal').modal('hide');
                    // Calculate end date and use date range function
                    const endDate = new Date(calendarStartDate);
                    endDate.setDate(endDate.getDate() + 30);
                    loadMedicationCalendarWithDateRange(medicationId,
                        formatDateForApi(calendarStartDate),
                        formatDateForApi(endDate));
                } else {
                    toastr.error(response.message ||
                        'Failed to create schedule.');
                }
            },
            error: function(xhr) {
                const errorMsg = xhr.responseJSON && xhr.responseJSON.errors ?
                    Object.values(xhr.responseJSON.errors).flat().join('<br>') :
                    'Failed to create schedule. Please check your inputs.';
                toastr.error(errorMsg);
            },
            complete: function() {
                $('#scheduleSubmitBtn .spinner-border').addClass('d-none');
                $('#scheduleSubmitBtn').prop('disabled', false);
            }
        });
    });

    // =============================================
    // ADMINISTRATION MANAGEMENT
    // =============================================

    // Open administer modal when clicking on a schedule slot
    $(document).on('click', '.schedule-slot[data-schedule-id]', function() {
        const scheduleId = $(this).data('schedule-id');
        const schedule = currentSchedules.find(s => s.id == scheduleId);

        if (schedule) {
            const scheduledTime = new Date(schedule.scheduled_time);
            const medication = medications.find(m => m.id == selectedMedication);

            $('#administer_schedule_id').val(scheduleId);
            $('#administered_dose').val(schedule.dose || '');
            $('#administered_route').val(schedule.route || 'Oral');

            const now = new Date();
            const formattedNow = formatDateTimeForInput(now);
            $('#administered_at').val(formattedNow);

            if (medication && medication.product) {
                $('#administer-medication-info').text(
                    `${medication.product.product_name} - ${schedule.dose}`);
                $('#administer-scheduled-time').text(
                    `Scheduled for: ${formatDateTime(scheduledTime)}`);
            }
        }
    });

    // Administer form submission
    $('#administerForm').submit(function(e) {
        e.preventDefault();

        const scheduleId = $('#administer_schedule_id').val();
        const administeredTime = $('#administered_at').val();
        const dose = $('#administered_dose').val();
        const route = $('#administered_route').val();
        const note = $('#administered_note').val();

        // Show loading indicator
        $('#administerSubmitBtn .spinner-border').removeClass('d-none');
        $('#administerSubmitBtn').prop('disabled', true);

        // Prepare data for API
        const adminData = {
            schedule_id: scheduleId,
            administered_at: administeredTime,
            administered_dose: dose,
            route: route,
            comment: note
        };

        $.ajax({
            url: medicationChartAdministerRoute,
            type: 'POST',
            data: adminData,
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            success: function(response) {
                if (response.success) {
                    toastr.success('Medication administered successfully.');
                    $('#administerModal').modal('hide');
                    // Calculate end date and use date range function
                    const adminEndDate = new Date(calendarStartDate);
                    adminEndDate.setDate(adminEndDate.getDate() + 30);
                    loadMedicationCalendarWithDateRange(selectedMedication,
                        formatDateForApi(calendarStartDate),
                        formatDateForApi(adminEndDate));
                } else {
                    toastr.error(response.message ||
                        'Failed to administer medication.');
                }
            },
            error: function(xhr) {
                const errorMsg = xhr.responseJSON && xhr.responseJSON.errors ?
                    Object.values(xhr.responseJSON.errors).flat().join('<br>') :
                    'Failed to administer medication. Please check your inputs.';
                toastr.error(errorMsg);
            },
            complete: function() {
                $('#administerSubmitBtn .spinner-border').addClass('d-none');
                $('#administerSubmitBtn').prop('disabled', false);
            }
        });
    });

    // Open admin details modal
    $(document).on('click', '.schedule-slot[data-admin-id]', function() {
        const adminId = $(this).data('admin-id');
        const admin = currentAdministrations.find(a => a.id == adminId);

        if (admin) {
            const canEdit = canEditAdministration(admin);
            let detailsHtml = '';

            const medication = medications.find(m => m.id == admin
                .product_or_service_request_id);
            const medicationName = medication && medication.product ? medication.product
                .product_name : 'Medication';

            detailsHtml += `<h6>${medicationName}</h6>`;
            detailsHtml += `<dl class="row">
                    <dt class="col-sm-4">Administered</dt>
                    <dd class="col-sm-8">${formatDateTime(new Date(admin.administered_at))}</dd>

                    <dt class="col-sm-4">Dose</dt>
                    <dd class="col-sm-8">${admin.dose}</dd>

                    <dt class="col-sm-4">Route</dt>
                    <dd class="col-sm-8">${admin.route}</dd>

                    <dt class="col-sm-4">Note</dt>
                    <dd class="col-sm-8">${admin.note || '(None)'}</dd>

                    <dt class="col-sm-4">Administered By</dt>
                    <dd class="col-sm-8">${getUserName(admin)}</dd>
                </dl>`;

            if (admin.edited_at) {
                detailsHtml += `<div class="alert alert-info">
                        <small><i class="mdi mdi-information-outline"></i> This record was edited on ${formatDateTime(new Date(admin.edited_at))}
                        by ${admin.edited_by_name || admin.edited_by_fullname || (admin.editedBy && admin.editedBy.name) || 'Unknown'}.</small>
                        ${admin.edit_reason ? `<br><small><strong>Reason:</strong> ${admin.edit_reason}</small>` : ''}
                    </div>`;
            }

            if (admin.deleted_at) {
                detailsHtml += `<div class="alert alert-warning">
                        <small><i class="mdi mdi-alert-outline"></i> This record was deleted on ${formatDateTime(new Date(admin.deleted_at))}
                        by ${admin.deleted_by_name || admin.deleted_by_fullname || (admin.deletedBy && admin.deletedBy.name) || 'Unknown'}.</small>
                        ${admin.delete_reason ? `<br><small><strong>Reason:</strong> ${admin.delete_reason}</small>` : ''}
                    </div>`;
            }

            $('#admin-details-content').html(detailsHtml);

            // Set admin ID for edit/delete operations
            $('#edit-admin-btn').data('admin-id', adminId);
            $('#delete-admin-btn').data('admin-id', adminId);

            // Enable/disable edit/delete buttons based on permissions
            $('#edit-admin-btn').prop('disabled', !canEdit || !!admin.deleted_at);
            $('#delete-admin-btn').prop('disabled', !canEdit || !!admin.deleted_at);

            $('#adminDetailsModal').modal('show');
        }
    });

    // Edit button in details modal
    $('#edit-admin-btn').click(function() {
        const adminId = $(this).data('admin-id');
        const admin = currentAdministrations.find(a => a.id == adminId);

        if (admin && canEditAdministration(admin)) {
            // Populate edit form
            $('#edit_admin_id').val(adminId);
            $('#edit_administered_at').val(formatDateTimeForInput(new Date(admin
                .administered_at)));
            $('#edit_dose').val(admin.dose || '');
            $('#edit_route').val(admin.route || 'Oral');
            $('#edit_note').val(admin.note || '');
            $('#edit_reason').val('');

            // Close details modal and show edit modal
            $('#adminDetailsModal').modal('hide');
            $('#editAdminModal').modal('show');
        }
    });

    // Delete button in details modal
    $('#delete-admin-btn').click(function() {
        const adminId = $(this).data('admin-id');
        const admin = currentAdministrations.find(a => a.id == adminId);

        if (admin && canEditAdministration(admin)) {
            // Populate delete form
            $('#delete_admin_id').val(adminId);
            $('#delete_reason').val('');

            // Close details modal and show delete modal
            $('#adminDetailsModal').modal('hide');
            $('#deleteAdminModal').modal('show');
        }
    });

    // Edit administration form submission
    $('#editAdminForm').submit(function(e) {
        e.preventDefault();

        const adminId = $('#edit_admin_id').val();
        const administeredTime = $('#edit_administered_at').val();
        const dose = $('#edit_dose').val();
        const route = $('#edit_route').val();
        const note = $('#edit_note').val();
        const reason = $('#edit_reason').val();

        // Show loading indicator
        if ($('#editAdminSubmitBtn .spinner-border').length) {
            $('#editAdminSubmitBtn .spinner-border').removeClass('d-none');
            $('#editAdminSubmitBtn').prop('disabled', true);
        }

        const editData = {
            administration_id: adminId,
            administered_at: administeredTime,
            dose: dose,
            route: route,
            comment: note,
            edit_reason: reason
        };

        $.ajax({
            url: medicationChartEditRoute,
            type: 'POST',
            data: editData,
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            success: function(response) {
                if (response.success) {
                    toastr.success('Administration updated successfully.');
                    $('#editAdminModal').modal('hide');
                    // Calculate end date and use date range function
                    const updateEndDate = new Date(calendarStartDate);
                    updateEndDate.setDate(updateEndDate.getDate() + 30);
                    loadMedicationCalendarWithDateRange(selectedMedication,
                        formatDateForApi(calendarStartDate),
                        formatDateForApi(updateEndDate));
                } else {
                    toastr.error(response.message ||
                        'Failed to update administration.');
                }
            },
            error: function(xhr) {
                const errorMsg = xhr.responseJSON && xhr.responseJSON.errors ?
                    Object.values(xhr.responseJSON.errors).flat().join('<br>') :
                    'Failed to update administration.';
                toastr.error(errorMsg);
            },
            complete: function() {
                if ($('#editAdminSubmitBtn .spinner-border').length) {
                    $('#editAdminSubmitBtn .spinner-border').addClass('d-none');
                    $('#editAdminSubmitBtn').prop('disabled', false);
                }
            }
        });
    });

    // Delete administration form submission
    $('#deleteAdminForm').submit(function(e) {
        e.preventDefault();

        const adminId = $('#delete_admin_id').val();
        const reason = $('#delete_reason').val();

        // Show loading indicator
        if ($('#deleteAdminSubmitBtn .spinner-border').length) {
            $('#deleteAdminSubmitBtn .spinner-border').removeClass('d-none');
            $('#deleteAdminSubmitBtn').prop('disabled', true);
        }

        const deleteData = {
            administration_id: adminId,
            reason: reason
        };

        $.ajax({
            url: medicationChartDeleteRoute,
            type: 'POST',
            data: deleteData,
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            success: function(response) {
                if (response.success) {
                    toastr.success('Administration deleted successfully.');
                    $('#deleteAdminModal').modal('hide');
                    // Calculate end date and use date range function
                    const deleteEndDate = new Date(calendarStartDate);
                    deleteEndDate.setDate(deleteEndDate.getDate() + 30);
                    loadMedicationCalendarWithDateRange(selectedMedication,
                        formatDateForApi(calendarStartDate),
                        formatDateForApi(deleteEndDate));
                } else {
                    toastr.error(response.message ||
                        'Failed to delete administration.');
                }
            },
            error: function(xhr) {
                const errorMsg = xhr.responseJSON && xhr.responseJSON.errors ?
                    Object.values(xhr.responseJSON.errors).flat().join('<br>') :
                    'Failed to delete administration.';
                toastr.error(errorMsg);
            },
            complete: function() {
                if ($('#deleteAdminSubmitBtn .spinner-border').length) {
                    $('#deleteAdminSubmitBtn .spinner-border').addClass(
                        'd-none');
                    $('#deleteAdminSubmitBtn').prop('disabled', false);
                }
            }
        });
    });

    // =============================================
    // DISCONTINUE/RESUME MANAGEMENT
    // =============================================

    // Discontinue button click
    $('#discontinue-btn').click(function() {
        if (!selectedMedication) return;

        // Find the selected medication
        const medication = medications.find(m => m.id == selectedMedication);
        if (!medication || !medication.product) return;

        $('#discontinue_medication_id').val(selectedMedication);
        $('#discontinue-medication-name').text(
            `Discontinue ${medication.product.product_name}`);
        $('#discontinueModal').modal('show');
    });

    // Discontinue form submission
    $('#discontinueForm').submit(function(e) {
        e.preventDefault();

        const medicationId = $('#discontinue_medication_id').val();
        const reason = $('#discontinue_reason').val();

        // Show loading indicator
        if ($('#discontinueSubmitBtn .spinner-border').length) {
            $('#discontinueSubmitBtn .spinner-border').removeClass('d-none');
            $('#discontinueSubmitBtn').prop('disabled', true);
        }

        const discontinueData = {
            patient_id: PATIENT_ID,
            product_or_service_request_id: medicationId,
            reason: reason
        };

        $.ajax({
            url: medicationChartDiscontinueRoute,
            type: 'POST',
            data: discontinueData,
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            success: function(response) {
                if (response.success) {
                    toastr.success('Medication discontinued successfully.');
                    $('#discontinueModal').modal('hide');

                    // Update medication status in our local data
                    if (medicationStatus[medicationId]) {
                        medicationStatus[medicationId].discontinued = true;
                        medicationStatus[medicationId].discontinued_at =
                            new Date();
                        medicationStatus[medicationId].discontinued_reason =
                            reason;
                    }

                    // Add to history for logs
                    const historyItem = {
                        date: new Date(),
                        action: 'discontinue',
                        reason: reason,
                        user: response.history ? response.history
                            .user_fullname : 'Current User',
                        medication_name: medication.product ? medication
                            .product.product_name : 'Medication'
                    };

                    if (!medicationHistory[medicationId]) {
                        medicationHistory[medicationId] = [];
                    }
                    medicationHistory[medicationId].push(historyItem);

                    // Calculate end date and use date range function
                    const discontinueEndDate = new Date(calendarStartDate);
                    discontinueEndDate.setDate(discontinueEndDate.getDate() +
                        30);
                    loadMedicationCalendarWithDateRange(medicationId,
                        formatDateForApi(calendarStartDate),
                        formatDateForApi(discontinueEndDate));
                } else {
                    toastr.error(response.message ||
                        'Failed to discontinue medication.');
                }
                // Reset button state
                if ($('#discontinueSubmitBtn .spinner-border').length) {
                    $('#discontinueSubmitBtn .spinner-border').addClass(
                        'd-none');
                    $('#discontinueSubmitBtn').prop('disabled', false);
                }
            },
            error: function(xhr) {
                const errorMsg = xhr.responseJSON && xhr.responseJSON.errors ?
                    Object.values(xhr.responseJSON.errors).flat().join('<br>') :
                    'Failed to discontinue medication.';
                toastr.error(errorMsg);

                // Reset button state
                if ($('#discontinueSubmitBtn .spinner-border').length) {
                    $('#discontinueSubmitBtn .spinner-border').addClass(
                        'd-none');
                    $('#discontinueSubmitBtn').prop('disabled', false);
                }
            },
            complete: function() {
                $('#discontinueSubmitBtn .spinner-border').addClass('d-none');
                $('#discontinueSubmitBtn').prop('disabled', false);
            }
        });
    });

    // Resume button click
    $('#resume-btn').click(function() {
        if (!selectedMedication) return;

        // Find the selected medication
        const medication = medications.find(m => m.id == selectedMedication);
        if (!medication || !medication.product) return;

        $('#resume_medication_id').val(selectedMedication);
        $('#resume-medication-name').text(`Resume ${medication.product.product_name}`);
        $('#resumeModal').modal('show');
    });

    // Resume form submission
    $('#resumeForm').submit(function(e) {
        e.preventDefault();

        const medicationId = $('#resume_medication_id').val();
        const reason = $('#resume_reason').val();

        // Show loading indicator
        if ($('#resumeSubmitBtn .spinner-border').length) {
            $('#resumeSubmitBtn .spinner-border').removeClass('d-none');
            $('#resumeSubmitBtn').prop('disabled', true);
        }

        const resumeData = {
            patient_id: PATIENT_ID,
            product_or_service_request_id: medicationId,
            reason: reason
        };

        $.ajax({
            url: medicationChartResumeRoute,
            type: 'POST',
            data: resumeData,
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            success: function(response) {
                if (response.success) {
                    toastr.success('Medication resumed successfully.');
                    $('#resumeModal').modal('hide');

                    // Update medication status in our local data
                    if (medicationStatus[medicationId]) {
                        medicationStatus[medicationId].resumed = true;
                        medicationStatus[medicationId].resumed_at = new Date();
                        medicationStatus[medicationId].resumed_reason = reason;
                    }

                    // Add to history for logs
                    const historyItem = {
                        date: new Date(),
                        action: 'resume',
                        reason: reason,
                        user: response.history ? response.history
                            .user_fullname : 'Current User',
                        medication_name: medication.product ? medication
                            .product.product_name : 'Medication'
                    };

                    if (!medicationHistory[medicationId]) {
                        medicationHistory[medicationId] = [];
                    }
                    medicationHistory[medicationId].push(historyItem);

                    // Calculate end date and use date range function
                    const resumeEndDate = new Date(calendarStartDate);
                    resumeEndDate.setDate(resumeEndDate.getDate() + 30);
                    loadMedicationCalendarWithDateRange(medicationId,
                        formatDateForApi(calendarStartDate),
                        formatDateForApi(resumeEndDate));
                } else {
                    toastr.error(response.message ||
                        'Failed to resume medication.');
                }

                // Reset button state
                if ($('#resumeSubmitBtn .spinner-border').length) {
                    $('#resumeSubmitBtn .spinner-border').addClass('d-none');
                    $('#resumeSubmitBtn').prop('disabled', false);
                }
            },
            error: function(xhr) {
                const errorMsg = xhr.responseJSON && xhr.responseJSON.errors ?
                    Object.values(xhr.responseJSON.errors).flat().join('<br>') :
                    'Failed to resume medication.';
                toastr.error(errorMsg);

                // Reset button state
                if ($('#resumeSubmitBtn .spinner-border').length) {
                    $('#resumeSubmitBtn .spinner-border').addClass('d-none');
                    $('#resumeSubmitBtn').prop('disabled', false);
                }
            },
            complete: function() {
                $('#resumeSubmitBtn .spinner-border').addClass('d-none');
                $('#resumeSubmitBtn').prop('disabled', false);
            }
        });
    });

    // =============================================
    // HELPER FUNCTIONS
    // =============================================

    // Check if user can edit an administration based on time window
    function canEditAdministration(admin) {
        if (!admin || !admin.administered_at) return false;

        const adminTime = new Date(admin.administered_at);
        const now = new Date();
        const diffMinutes = (now - adminTime) / (1000 * 60); // Convert ms to minutes

        return diffMinutes <= NOTE_EDIT_WINDOW;
    }

    // Format date for display
    function formatDate(date) {
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    // Format time for display
    function formatTime(date) {
        return date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // Format date and time for display
    function formatDateTime(date) {
        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // Format date for API
    function formatDateForApi(date) {
        return date.toISOString().split('T')[0]; // YYYY-MM-DD
    }

    // Format datetime for input fields
    function formatDateTimeForInput(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');

        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }

    // Get day of week abbreviation
    function getDayOfWeek(date) {
        const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        return days[date.getDay()];
    }

    // =============================================
    // INTAKE/OUTPUT CHART
    // =============================================

    // Set default dates for intake/output filters (last 30 days)
    function setDefaultIODates() {
        const today = new Date();
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(today.getDate() - 30);

        // Format dates for input fields
        const todayStr = today.toISOString().split('T')[0];
        const thirtyDaysAgoStr = thirtyDaysAgo.toISOString().split('T')[0];

        // Set fluid filter dates
        $('#fluid_start_date').val(thirtyDaysAgoStr);
        $('#fluid_end_date').val(todayStr);

        // Set solid filter dates
        $('#solid_start_date').val(thirtyDaysAgoStr);
        $('#solid_end_date').val(todayStr);

        // Update date range display
        $('#fluid_date_range_display').text(`${formatDate(thirtyDaysAgo)} - ${formatDate(today)}`);
        $('#solid_date_range_display').text(`${formatDate(thirtyDaysAgo)} - ${formatDate(today)}`);
    }

    // Initialize the date filters
    setDefaultIODates();

    // Initialize Intake/Output Chart functions
    function loadIntakeOutput(type, startDate = null, endDate = null) {
        // If no dates provided, use the input fields
        if (!startDate) {
            startDate = type === 'fluid' ? $('#fluid_start_date').val() : $('#solid_start_date')
                .val();
        }
        if (!endDate) {
            endDate = type === 'fluid' ? $('#fluid_end_date').val() : $('#solid_end_date').val();
        }

        // Show loading indicator
        $(`#${type}-periods-list`).html(
            '<div class="text-center my-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Loading data...</p></div>'
        );

        // Make API request with date parameters
        $.get(intakeOutputChartIndexRoute.replace(':patient', PATIENT_ID), {
            start_date: startDate,
            end_date: endDate
        }, function(data) {
            let periods = type === 'fluid' ? data.fluidPeriods : data.solidPeriods;

            // Sort periods to show newest first
            periods.sort(function(a, b) {
                return new Date(b.started_at) - new Date(a.started_at);
            });

            let html = '';

            // Update date range display
            $(`#${type}_date_range_display`).text(
                `${formatDate(new Date(startDate))} - ${formatDate(new Date(endDate))}`);

            if (periods.length === 0) {
                html =
                    '<div class="alert alert-info">No intake & output records found for this period.</div>';
                $(`#${type}-periods-list`).html(html);
                return;
            }

            periods.forEach(function(p) {
                // Get intake and output totals for this period
                let totalIntake = 0;
                let totalOutput = 0;

                if (p.records && p.records.length > 0) {
                    p.records.forEach(function(r) {
                        if (r.type === 'intake') {
                            totalIntake += parseFloat(r.amount);
                        } else if (r.type === 'output') {
                            totalOutput += parseFloat(r.amount);
                        }
                    });
                }

                // Calculate balance
                const balance = totalIntake - totalOutput;
                const balanceClass = balance >= 0 ? 'text-success' : 'text-danger';
                const periodStatus = p.ended_at ? 'secondary' : 'info';
                const periodIcon = type === 'fluid' ? 'mdi-water' :
                    'mdi-food-apple';

                html += `<div class='card mb-2 shadow-sm period-card ${!p.ended_at ? 'border-info' : ''}'>
                        <div class='card-header py-2 d-flex justify-content-between align-items-center ${!p.ended_at ? 'bg-info bg-opacity-10' : ''}'>
                            <div>
                                <span class="badge bg-${periodStatus} rounded-pill me-1" data-bs-toggle="tooltip"
                                    title="${p.ended_at ? 'Closed period' : 'Active period'}">
                                    <i class="mdi ${p.ended_at ? 'mdi-clock-end' : 'mdi-clock-start'}"></i>
                                    ${p.ended_at ? 'Ended' : 'Active'}
                                </span>
                                <strong>${formatDateTime(p.started_at)}</strong>
                                ${(p.ended_at ? ' to ' + formatDateTime(p.ended_at) : '')}
                                <span class="ms-2 text-muted small">(By: ${p.nurse_name || 'Unknown'})</span>
                            </div>
                            <div class="d-flex gap-1">`;

                // Add View Logs button for all periods
                html += `<button class='btn btn-sm btn-outline-secondary view-io-logs-btn' data-period-id='${p.id}'
                                data-type='${type}' data-bs-toggle="tooltip" title="View activity logs">
                                <i class="mdi mdi-history"></i> <span class="d-none d-sm-inline">Logs</span></button>`;

                if (!p.ended_at) {
                    html +=
                        `<button class='btn btn-sm btn-outline-danger end-period-btn' data-period-id='${p.id}'
                                data-type='${type}' data-bs-toggle="tooltip" title="End this period">
                                <i class="mdi mdi-clock-end"></i> <span class="d-none d-sm-inline">End Period</span></button>`;
                }

                html += `</div></div>
                        <div class='card-body p-0'>
                            <div class="table-responsive mb-0">
                                <table class="table table-bordered mb-2">
                                    <tr class="bg-light">
                                        <td width="33%" class="text-center p-2">
                                            <div class="fs-6 fw-bold">Intake</div>
                                            <div class="fs-5">
                                                <i class="mdi ${type === 'fluid' ? 'mdi-water text-primary' : 'mdi-food-apple text-success'}"></i>
                                                ${totalIntake} ${type === 'fluid' ? 'ml' : 'g'}
                                            </div>
                                        </td>
                                        <td width="33%" class="text-center p-2">
                                            <div class="fs-6 fw-bold">Output</div>
                                            <div class="fs-5">
                                                <i class="mdi ${type === 'fluid' ? 'mdi-water-off text-warning' : 'mdi-delete-empty text-danger'}"></i>
                                                ${totalOutput} ${type === 'fluid' ? 'ml' : 'g'}
                                            </div>
                                        </td>
                                        <td width="33%" class="text-center p-2">
                                            <div class="fs-6 fw-bold">Balance</div>
                                            <div class="fs-5 ${balanceClass}">
                                                <i class="mdi ${balance >= 0 ? 'mdi-arrow-up' : 'mdi-arrow-down'}"></i>
                                                ${Math.abs(balance)} ${type === 'fluid' ? 'ml' : 'g'}
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <div class="table-responsive">
                                <table class='table table-sm table-striped mb-0'>
                                    <thead>
                                        <tr>
                                            <th style="width: 80px">Type</th>
                                            <th style="width: 80px">Amount</th>
                                            <th>Description</th>
                                            <th style="width: 160px">Time</th>
                                            <th style="width: 140px">Recorded By</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;

                if (p.records && p.records.length > 0) {
                    // Sort records by recorded_at date (newest first)
                    const sortedRecords = [...p.records].sort((a, b) =>
                        new Date(b.recorded_at) - new Date(a.recorded_at)
                    );

                    sortedRecords.forEach(function(r) {
                        const recordIcon = r.type === 'intake' ?
                            (type === 'fluid' ? 'mdi-water text-primary' :
                                'mdi-food-apple text-success') :
                            (type === 'fluid' ?
                                'mdi-water-off text-warning' :
                                'mdi-delete-empty text-danger');

                        const recordBadge = r.type === 'intake' ?
                            (type === 'fluid' ? 'bg-primary' :
                                'bg-success') :
                            (type === 'fluid' ? 'bg-warning' : 'bg-danger');

                        html += `<tr>
                                    <td>
                                        <span class="badge ${recordBadge} rounded-pill">
                                            <i class="mdi ${recordIcon}"></i> ${r.type.charAt(0).toUpperCase() + r.type.slice(1)}
                                        </span>
                                    </td>
                                    <td><strong>${r.amount}</strong> ${type === 'fluid' ? 'ml' : 'g'}</td>
                                    <td>${r.description || '<span class="text-muted">No description</span>'}</td>
                                    <td>${formatDateTime(r.recorded_at)}</td>
                                    <td>${r.nurse_name || 'Unknown'}</td>
                                </tr>`;
                    });
                } else {
                    html +=
                        `<tr><td colspan="5" class="text-center text-muted py-3">No records yet</td></tr>`;
                }

                html += `</tbody>
                    </table>
                    </div>`;

                if (!p.ended_at) {
                    html += `<div class="card-footer bg-white p-2">
                            <button class='btn btn-primary btn-sm add-record-btn' data-period-id='${p.id}' data-type='${type}'>
                                <i class="mdi mdi-plus-circle"></i> Add ${type.charAt(0).toUpperCase() + type.slice(1)} Record
                            </button>
                        </div>`;
                }

                html += `</div>
                    </div>
                    <hr class="my-4 border-2">`;

            });

            $('#' + type + '-periods-list').html(html ||
                `<div class="alert alert-info d-flex align-items-center">
                        <i class="mdi ${type === 'fluid' ? 'mdi-water' : 'mdi-food-apple'} fs-3 me-3"></i>
                        <div>
                            <h6>No ${type} periods found</h6>
                            <p class="mb-0">Click the "Start New Period" button above to begin tracking ${type} intake and output for this patient.</p>
                        </div>
                    </div>`
            );

            // Initialize tooltips
            $('[data-bs-toggle="tooltip"]').tooltip();
        });
    }

    // Handle View Logs button click for intake/output periods
    $(document).on('click', '.view-io-logs-btn', function() {
        const periodId = $(this).data('period-id');
        const periodType = $(this).data('type');

        console.log('View logs clicked for period:', periodId, periodType);

        // Show loading state in modal
        $('#io-logs-title').text('Loading...');
        $('#io-logs-content').html(
            '<div class="d-flex justify-content-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>'
        );
        $('#intakeOutputLogsModal').modal('show');

        // Fetch period history/logs
        $.ajax({
            url: intakeOutputChartLogsRoute.replace(':patient', PATIENT_ID).replace(
                ':period', periodId),
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    const title =
                        `${periodType.charAt(0).toUpperCase() + periodType.slice(1)} Period Activity Logs`;
                    $('#io-logs-title').text(title);

                    // Process and display logs
                    let logsHtml = '';
                    if (response.history && response.history.length > 0) {
                        logsHtml =
                            '<div class="table-responsive"><table class="table table-sm table-striped">';
                        logsHtml +=
                            '<thead><tr><th style="width:160px">Date & Time</th><th style="width:100px">Action</th><th>Details</th><th style="width:140px">User</th></tr></thead><tbody>';

                        response.history.forEach(log => {
                            // Handle both object format and array format logs
                            const logDate = log.date ? new Date(log
                                .date) : new Date(log[0]);
                            const action = log.action || log[1] ||
                                'Action';
                            const details = log.details || log[2] ||
                                '-';
                            const user = log.user || log[3] ||
                                'Unknown';

                            let actionBadgeClass = 'bg-primary';
                            let actionIcon = 'mdi-information-outline';
                            let actionDisplay = action;

                            // Style based on action type
                            if (action.includes('create_period') ||
                                action.includes('start')) {
                                actionBadgeClass = 'bg-success';
                                actionIcon = 'mdi-clock-start';
                                actionDisplay = 'Started';
                            } else if (action.includes('end_period')) {
                                actionBadgeClass = 'bg-warning';
                                actionIcon = 'mdi-clock-end';
                                actionDisplay = 'Ended';
                            } else if (action.includes(
                                    'add_record_intake')) {
                                actionBadgeClass = 'bg-info';
                                actionIcon = 'mdi-plus-circle';
                                actionDisplay = 'Intake';
                            } else if (action.includes(
                                    'add_record_output')) {
                                actionBadgeClass = 'bg-danger';
                                actionIcon = 'mdi-minus-circle';
                                actionDisplay = 'Output';
                            }

                            logsHtml += `<tr>
                                    <td><small>${formatDateTime(logDate)}</small></td>
                                    <td><span class="badge ${actionBadgeClass} rounded-pill">
                                        <i class="mdi ${actionIcon}"></i> ${actionDisplay}</span></td>
                                    <td>${details}</td>
                                    <td><small>${user}</small></td>
                                </tr>`;
                        });

                        logsHtml += '</tbody></table></div>';
                    } else {
                        logsHtml = `<div class="alert alert-info">
                                <i class="mdi mdi-information-outline me-2"></i>
                                No activity logs available for this period.
                            </div>`;
                    }

                    $('#io-logs-content').html(logsHtml);
                } else {
                    $('#io-logs-content').html(
                        '<div class="alert alert-danger">Failed to load logs</div>'
                    );
                }
            },
            error: function(xhr) {
                console.error('Error loading logs:', xhr);
                $('#io-logs-content').html(
                    '<div class="alert alert-danger">Error loading logs. Please try again.</div>'
                );
            }
        });
    });

    $('#startFluidPeriodBtn').click(function() {
        $.post(intakeOutputChartStartRoute, {
            patient_id: PATIENT_ID,
            type: 'fluid',
            _token: CSRF_TOKEN
        }, function(response) {
            if (response.success) {
                toastr.success('Fluid period started successfully');
                loadIntakeOutput('fluid');
            } else {
                toastr.error(response.message || 'Failed to start fluid period');
            }
        });
    });

    $('#startSolidPeriodBtn').click(function() {
        $.post(intakeOutputChartStartRoute, {
            patient_id: PATIENT_ID,
            type: 'solid',
            _token: CSRF_TOKEN
        }, function(response) {
            if (response.success) {
                toastr.success('Solid period started successfully');
                loadIntakeOutput('solid');
            } else {
                toastr.error(response.message || 'Failed to start solid period');
            }
        });
    });

    // End intake/output period
    $(document).on('click', '.end-period-btn', function() {
        const periodId = $(this).data('period-id');
        const periodType = $(this).data('type');

        if (confirm('Are you sure you want to end this period?')) {
            $.ajax({
                url: intakeOutputChartEndRoute,
                type: 'POST',
                data: {
                    period_id: periodId,
                    _token: CSRF_TOKEN
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success('Period ended successfully');
                        loadIntakeOutput(periodType);
                    } else {
                        toastr.error(response.message ||
                            'Failed to end period');
                    }
                },
                error: function() {
                    toastr.error('Failed to end period');
                }
            });
        }
    });

    // Open record modal
    $(document).on('click', '.add-record-btn', function() {
        const periodId = $(this).data('period-id');
        const periodType = $(this).data('type');

        // Populate the correct form
        if (periodType === 'fluid') {
            $('#fluid_period_id').val(periodId);

            // Set the current time
            const now = new Date();
            $('#fluid_recorded_at').val(formatDateTimeForInput(now));

            $('#fluidRecordModal').modal('show');
        } else {
            $('#solid_period_id').val(periodId);

            // Set the current time
            const now = new Date();
            $('#solid_recorded_at').val(formatDateTimeForInput(now));

            $('#solidRecordModal').modal('show');
        }
    });

    // Submit fluid record form
    $('#fluidRecordForm').submit(function(e) {
        e.preventDefault();

        $.ajax({
            url: intakeOutputChartRecordRoute,
            type: 'POST',
            data: $(this).serialize() + '&_token=' + CSRF_TOKEN,
            success: function(response) {
                if (response.success) {
                    toastr.success('Record added successfully');
                    $('#fluidRecordModal').modal('hide');
                    loadIntakeOutput('fluid');
                } else {
                    toastr.error(response.message || 'Failed to add record');
                }
            },
            error: function(xhr) {
                const errorMsg = xhr.responseJSON && xhr.responseJSON.errors ?
                    Object.values(xhr.responseJSON.errors).flat().join('<br>') :
                    'Failed to add record. Please check your inputs.';
                toastr.error(errorMsg);
            }
        });
    });

    // Submit solid record form
    $('#solidRecordForm').submit(function(e) {
        e.preventDefault();

        $.ajax({
            url: intakeOutputChartRecordRoute,
            type: 'POST',
            data: $(this).serialize() + '&_token=' + CSRF_TOKEN,
            success: function(response) {
                if (response.success) {
                    toastr.success('Record added successfully');
                    $('#solidRecordModal').modal('hide');
                    loadIntakeOutput('solid');
                } else {
                    toastr.error(response.message || 'Failed to add record');
                }
            },
            error: function(xhr) {
                const errorMsg = xhr.responseJSON && xhr.responseJSON.errors ?
                    Object.values(xhr.responseJSON.errors).flat().join('<br>') :
                    'Failed to add record. Please check your inputs.';
                toastr.error(errorMsg);
            }
        });
    });

    // Initialize intake/output on tab activation
    $(document).on('shown.bs.tab',
        'button[data-bs-toggle="tab"][data-bs-target="#intakeOutputChart"]',
        function(e) {
            loadIntakeOutput('fluid');
            loadIntakeOutput('solid');
        });

    // Also load intake/output data when fluid/solid tabs are clicked
    $(document).on('shown.bs.tab', 'button[data-bs-toggle="tab"][data-bs-target="#fluidChart"]',
        function(e) {
            loadIntakeOutput('fluid');
        });

    $(document).on('shown.bs.tab', 'button[data-bs-toggle="tab"][data-bs-target="#solidChart"]',
        function(e) {
            loadIntakeOutput('solid');
        });

    // Date filter change handlers
    $('#fluid_start_date, #fluid_end_date').change(function() {
        const startDate = $('#fluid_start_date').val();
        const endDate = $('#fluid_end_date').val();

        // Validate date range
        if (new Date(startDate) > new Date(endDate)) {
            toastr.warning('Start date cannot be after end date.');
            return;
        }

        loadIntakeOutput('fluid', startDate, endDate);
    });

    $('#solid_start_date, #solid_end_date').change(function() {
        const startDate = $('#solid_start_date').val();
        const endDate = $('#solid_end_date').val();

        // Validate date range
        if (new Date(startDate) > new Date(endDate)) {
            toastr.warning('Start date cannot be after end date.');
            return;
        }

        loadIntakeOutput('solid', startDate, endDate);
    });

    // Date filter button handlers for fluid
    $('#fluid_apply_filter_btn').click(function() {
        const startDate = $('#fluid_start_date').val();
        const endDate = $('#fluid_end_date').val();

        // Validate date range
        if (new Date(startDate) > new Date(endDate)) {
            toastr.warning('Start date cannot be after end date.');
            return;
        }

        loadIntakeOutput('fluid', startDate, endDate);
    });

    $('#fluid_reset_filter_btn').click(function() {
        setDefaultIODates();
        loadIntakeOutput('fluid');
    });

    $('#fluid_prev_period_btn').click(function() {
        const startDate = new Date($('#fluid_start_date').val());
        const endDate = new Date($('#fluid_end_date').val());

        // Move both dates back by 30 days
        startDate.setDate(startDate.getDate() - 30);
        endDate.setDate(endDate.getDate() - 30);

        // Update the input fields
        $('#fluid_start_date').val(startDate.toISOString().split('T')[0]);
        $('#fluid_end_date').val(endDate.toISOString().split('T')[0]);

        // Load data with new dates
        loadIntakeOutput('fluid', startDate.toISOString().split('T')[0], endDate
            .toISOString().split('T')[0]);
    });

    $('#fluid_current_period_btn').click(function() {
        const today = new Date();
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(today.getDate() - 30);

        // Update the input fields
        $('#fluid_start_date').val(thirtyDaysAgo.toISOString().split('T')[0]);
        $('#fluid_end_date').val(today.toISOString().split('T')[0]);

        // Load data with new dates
        loadIntakeOutput('fluid', thirtyDaysAgo.toISOString().split('T')[0], today
            .toISOString().split('T')[0]);
    });

    $('#fluid_next_period_btn').click(function() {
        const startDate = new Date($('#fluid_start_date').val());
        const endDate = new Date($('#fluid_end_date').val());

        // Move both dates forward by 30 days
        startDate.setDate(startDate.getDate() + 30);
        endDate.setDate(endDate.getDate() + 30);

        // Don't allow dates in the future beyond today
        const today = new Date();
        if (endDate > today) {
            endDate.setTime(today.getTime());
            startDate.setTime(today.getTime());
            startDate.setDate(startDate.getDate() - 30);
        }

        // Update the input fields
        $('#fluid_start_date').val(startDate.toISOString().split('T')[0]);
        $('#fluid_end_date').val(endDate.toISOString().split('T')[0]);

        // Load data with new dates
        loadIntakeOutput('fluid', startDate.toISOString().split('T')[0], endDate
            .toISOString().split('T')[0]);
    });

    // Date filter button handlers for solid
    $('#solid_apply_filter_btn').click(function() {
        const startDate = $('#solid_start_date').val();
        const endDate = $('#solid_end_date').val();

        // Validate date range
        if (new Date(startDate) > new Date(endDate)) {
            toastr.warning('Start date cannot be after end date.');
            return;
        }

        loadIntakeOutput('solid', startDate, endDate);
    });

    $('#solid_reset_filter_btn').click(function() {
        setDefaultIODates();
        loadIntakeOutput('solid');
    });

    $('#solid_prev_period_btn').click(function() {
        const startDate = new Date($('#solid_start_date').val());
        const endDate = new Date($('#solid_end_date').val());

        // Move both dates back by 30 days
        startDate.setDate(startDate.getDate() - 30);
        endDate.setDate(endDate.getDate() - 30);

        // Update the input fields
        $('#solid_start_date').val(startDate.toISOString().split('T')[0]);
        $('#solid_end_date').val(endDate.toISOString().split('T')[0]);

        // Load data with new dates
        loadIntakeOutput('solid', startDate.toISOString().split('T')[0], endDate
            .toISOString().split('T')[0]);
    });

    $('#solid_current_period_btn').click(function() {
        const today = new Date();
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(today.getDate() - 30);

        // Update the input fields
        $('#solid_start_date').val(thirtyDaysAgo.toISOString().split('T')[0]);
        $('#solid_end_date').val(today.toISOString().split('T')[0]);

        // Load data with new dates
        loadIntakeOutput('solid', thirtyDaysAgo.toISOString().split('T')[0], today
            .toISOString().split('T')[0]);
    });

    $('#solid_next_period_btn').click(function() {
        const startDate = new Date($('#solid_start_date').val());
        const endDate = new Date($('#solid_end_date').val());

        // Move both dates forward by 30 days
        startDate.setDate(startDate.getDate() + 30);
        endDate.setDate(endDate.getDate() + 30);

        // Don't allow dates in the future beyond today
        const today = new Date();
        if (endDate > today) {
            endDate.setTime(today.getTime());
            startDate.setTime(today.getTime());
            startDate.setDate(startDate.getDate() - 30);
        }

        // Update the input fields
        $('#solid_start_date').val(startDate.toISOString().split('T')[0]);
        $('#solid_end_date').val(endDate.toISOString().split('T')[0]);

        // Load data with new dates
        loadIntakeOutput('solid', startDate.toISOString().split('T')[0], endDate
            .toISOString().split('T')[0]);
    });



    $(document).ready(function() {
        // Ensure the date filters are initialized when page loads
        setDefaultIODates();

        // Initial load of intake/output data when nurse chart tab is first shown
        $(document).on('shown.bs.tab',
            'button[data-bs-toggle="tab"][data-bs-target="#intakeOutputChart"]',
            function(e) {
                // Set default dates before loading
                setDefaultIODates();

                // Load data for both tabs
                loadIntakeOutput('fluid');
                loadIntakeOutput('solid');
            });

        // Also load intake/output data when fluid/solid tabs are clicked
        $(document).on('shown.bs.tab',
            'button[data-bs-toggle="tab"][data-bs-target="#fluidChart"]',
            function(e) {
                loadIntakeOutput('fluid');
            });

        $(document).on('shown.bs.tab',
            'button[data-bs-toggle="tab"][data-bs-target="#solidChart"]',
            function(e) {
                loadIntakeOutput('solid');
            });
    });
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
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">x</button>
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
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">x</button>
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
<script>
    // Create the View Logs button and add it to the UI
    $(document).ready(function() {
        // Wait for the DOM to be fully loaded
        setTimeout(function() {
            // Find the container with the resume button
            const resumeBtn = $('#resume-btn');
            if (resumeBtn.length) {
                // Create the view logs button with data attribute
                const viewLogsBtn = $(
                    `<button type="button" class="btn btn-info btn-sm" id="view-logs-btn">
                                <i class="mdi mdi-history"></i>
                                <span class="d-none d-sm-inline">View Logs</span>
                            </button>`
                );

                // Insert the button before the resume button
                resumeBtn.before(viewLogsBtn);
            }
        }, 1000); // Delay to ensure other scripts have run
    });
</script> --}}
