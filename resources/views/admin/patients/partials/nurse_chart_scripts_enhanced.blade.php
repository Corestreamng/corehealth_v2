<script>
    // CSRF token for AJAX requests
    var CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var PATIENT_ID = {{ isset($patient) ? (is_object($patient) ? $patient->id : $patient) : (isset($patient_id) ? $patient_id : 'null') }};
    var medicationChartIndexRoute = "{{ route('nurse.medication.index', [':patient']) }}";
    var medicationChartScheduleRoute = "{{ route('nurse.medication.schedule') }}";
    var medicationChartAdministerRoute = "{{ route('nurse.medication.administer') }}";
    var medicationChartDiscontinueRoute = "{{ route('nurse.medication.discontinue') }}";
    var medicationChartResumeRoute = "{{ route('nurse.medication.resume') }}";
    var medicationChartDeleteRoute = "{{ route('nurse.medication.delete') }}";
    var medicationChartEditRoute = "{{ route('nurse.medication.edit') }}";
    var medicationChartCalendarRoute = "{{ route('nurse.medication.calendar', [':patient', ':medication', ':start_date']) }}";

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
    var intakeOutputChartStartRoute = "{{ route('nurse.intake_output.start') }}";
    var intakeOutputChartEndRoute = "{{ route('nurse.intake_output.end') }}";
    var intakeOutputChartRecordRoute = "{{ route('nurse.intake_output.record') }}";
</script>
<script>
    // Nurse Chart JS (AJAX + Toastr)
    $(function() {
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
            $('#medication-loading').show();
            $('#medication-calendar').hide();

            $.ajax({
                url: medicationChartIndexRoute.replace(':patient', PATIENT_ID),
                type: 'GET',
                success: function(data) {
                    $('#medication-loading').hide();
                    medications = data.prescriptions || [];

                    // Populate medication dropdown
                    const select = $('#drug-select');
                    select.empty();
                    select.append('<option value="">-- Select a medication --</option>');

                    medications.forEach(function(p) {
                        const prod = p.product || {};
                        select.append(`<option value="${p.id}">${prod.product_name || 'Unknown'} - ${prod.product_code || ''}</option>`);

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
                },
                error: function() {
                    $('#medication-loading').hide();
                    toastr.error('Failed to load medications.');
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
                loadMedicationCalendar(medicationId, calendarStartDate);
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
                calendarStartDate.setDate(calendarStartDate.getDate() - 30);
                loadMedicationCalendar(selectedMedication, calendarStartDate);
            }
        });

        $('#next-month-btn').click(function() {
            if (selectedMedication) {
                calendarStartDate.setDate(calendarStartDate.getDate() + 30);
                loadMedicationCalendar(selectedMedication, calendarStartDate);
            }
        });

        $('#today-btn').click(function() {
            if (selectedMedication) {
                calendarStartDate = new Date();
                calendarStartDate.setDate(calendarStartDate.getDate() - 15);
                loadMedicationCalendar(selectedMedication, calendarStartDate);
            }
        });

        function loadMedicationCalendar(medicationId, startDate) {
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
                                    medication_name: medication.product ? medication.product.product_name : 'Medication'
                                });

                                // Edit event if applicable
                                if (admin.edited_at) {
                                    medicationHistory[selectedMedication].push({
                                        date: new Date(admin.edited_at),
                                        action: 'edit',
                                        reason: admin.edit_reason,
                                        details: `Edited administration (${admin.dose} via ${admin.route})`,
                                        user: admin.edited_by_name,
                                        medication_name: medication.product ? medication.product.product_name : 'Medication'
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
                                        medication_name: medication.product ? medication.product.product_name : 'Medication'
                                    });
                                }
                            });
                        }

                        // Show calendar and legend
                        renderCalendarView(medication, currentSchedules, currentAdministrations, data.period);
                        renderLegend(); // Add legend rendering
                        $('#medication-calendar').show();
                        $('#calendar-legend').show();
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
                statusHtml += `<strong>${medication.product.product_name}</strong>: `;

                if (medication.discontinued_at) {
                    const discontinuedDate = new Date(medication.discontinued_at);
                    const discontinuedBy = medication.discontinued_by_name || medication.user_fullname || medication.discontinued_by || 'Unknown';
                    statusHtml += `<span class="text-danger">Discontinued on ${formatDate(discontinuedDate)} by ${discontinuedBy}. Reason: ${medication.discontinued_reason}</span>`;

                    if (medication.resumed_at) {
                        const resumedDate = new Date(medication.resumed_at);
                        const resumedBy = medication.resumed_by_name || medication.user_fullname || medication.resumed_by || 'Unknown';
                        statusHtml += `<br><span class="text-success">Resumed on ${formatDate(resumedDate)} by ${resumedBy}. Reason: ${medication.resumed_reason}</span>`;
                    }
                } else {
                    statusHtml += '<span class="text-success">Active</span>';
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

        // Render the calendar legend
        function renderLegend() {
            const legendHtml = `
            <div class="card mb-3">
                <div class="card-body p-2">
                    <h6 class="card-title mb-2">Legend</h6>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-primary me-1">
                                <i class="mdi mdi-calendar-clock"></i>
                            </span>
                            <small>Scheduled</small>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-success me-1">
                                <i class="mdi mdi-check"></i>
                            </span>
                            <small>Administered</small>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-info me-1">
                                <i class="mdi mdi-pencil"></i>
                            </span>
                            <small>Edited</small>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-dark me-1">
                                <i class="mdi mdi-close"></i>
                            </span>
                            <small>Deleted</small>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-danger me-1">
                                <i class="mdi mdi-calendar-remove"></i>
                            </span>
                            <small>Missed</small>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-secondary me-1">
                                <i class="mdi mdi-calendar"></i>
                            </span>
                            <small>Discontinued</small>
                        </div>
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

            // Update calendar title
            $('#calendar-title').text(
                `${product.product_name || 'Medication'} Schedule: ${formatDate(startDate)} to ${formatDate(endDate)}`
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
                        let adminAction = `data-bs-toggle="modal" data-bs-target="#administerModal" data-schedule-id="${schedule.id}"`;

                        // Check for discontinuation
                        const isDiscontinued = medication.discontinued_at &&
                            new Date(medication.discontinued_at) < scheduleTime &&
                            (!medication.resumed_at || new Date(medication.resumed_at) > scheduleTime);

                        if (isDiscontinued) {
                            badgeClass = 'bg-secondary';
                            adminAction = '';
                        } else if (admin) {
                            // Administered
                            const adminTime = new Date(admin.administered_at);
                            badgeClass = 'bg-success';

                            if (admin.edited_at) {
                                // Edited administration
                                badgeClass = 'bg-info';
                                badgeContent = `<i class="mdi mdi-pencil"></i> ${formatTime(adminTime)}`;
                            } else if (admin.deleted_at) {
                                // Deleted administration
                                badgeClass = 'bg-dark';
                                badgeContent = `<s>${formatTime(adminTime)}</s>`;
                            } else {
                                badgeContent = `<i class="mdi mdi-check"></i> ${formatTime(adminTime)}`;
                            }

                            adminAction = `data-bs-toggle="modal" data-bs-target="#adminDetailsModal" data-admin-id="${admin.id}"`;
                        } else if (scheduleTime < new Date()) {
                            // Missed administration (past schedule with no administration)
                            badgeClass = 'bg-danger';
                            badgeContent = `<i class="mdi mdi-calendar-remove"></i> ${formattedTime}`;
                        }

                        // Create tooltip content
                        let tooltipContent = '';

                        if (isDiscontinued) {
                            tooltipContent = `Discontinued: ${schedule.dose} via ${schedule.route}`;
                        } else if (admin) {
                            const adminTime = new Date(admin.administered_at);
                            const adminUser = admin.administered_by_name || 'Unknown';

                            if (admin.deleted_at) {
                                tooltipContent = `Deleted administration: ${admin.dose} via ${admin.route}\nBy: ${admin.deleted_by_name || 'Unknown'}\nReason: ${admin.delete_reason || 'Not specified'}`;
                            } else if (admin.edited_at) {
                                tooltipContent = `Edited administration: ${admin.dose} via ${admin.route}\nBy: ${admin.edited_by_name || 'Unknown'}\nReason: ${admin.edit_reason || 'Not specified'}`;
                            } else {
                                tooltipContent = `Administered: ${admin.dose} via ${admin.route}\nBy: ${adminUser}\nAt: ${formatDateTime(adminTime)}`;
                                if (admin.comment) {
                                    tooltipContent += `\nNote: ${admin.comment}`;
                                }
                            }
                        } else {
                            tooltipContent = `Scheduled: ${schedule.dose} via ${schedule.route}\nAt: ${formatDateTime(scheduleTime)}`;
                            if (scheduleTime < new Date()) {
                                tooltipContent = `Missed administration: ${schedule.dose} via ${schedule.route}\nScheduled for: ${formatDateTime(scheduleTime)}`;
                            }
                        }

                        // Add tooltip attribute
                        const tooltipAttr = `data-bs-toggle="tooltip" data-bs-placement="top" title="${tooltipContent.replace(/"/g, '&quot;')}"`;

                        calendarHtml += `
                            <span class="badge ${badgeClass} me-1 mb-1 schedule-slot" ${adminAction} ${tooltipAttr}>
                                ${badgeContent}
                            </span>
                        `;
                    });
                }

                calendarHtml += `
                        </div>
                    </td>
                </tr>`;
            });

            // Update calendar body
            $('#calendar-body').html(calendarHtml);
        }

        // Initialize tooltips after calendar render
        $('#calendar-body').on('mouseover', '[data-bs-toggle="tooltip"]', function() {
            if (!$(this).data('bs-tooltip-initialized')) {
                new bootstrap.Tooltip(this);
                $(this).data('bs-tooltip-initialized', true);
            }
        });

        // View logs button click handler - Using event delegation (both by ID and data attribute)
        $(document).on('click', '#view-logs-btn, [data-action="view-medication-logs"]', function() {
            console.log('View logs button clicked');
            if (!selectedMedication) {
                console.log('No medication selected');
                return;
            }

            // Find the selected medication
            const medication = medications.find(m => m.id == selectedMedication);
            if (!medication || !medication.product) {
                console.log('Medication or product not found:', selectedMedication);
                return;
            }

            const logs = medicationHistory[selectedMedication] || [];

            // Sort logs by date, newest first
            logs.sort((a, b) => new Date(b.date) - new Date(a.date));

            // Generate logs HTML
            let logsHtml = '';
            if (logs.length === 0) {
                logsHtml = '<p class="text-muted">No activity logs available for this medication.</p>';
            } else {
                logsHtml = '<div class="table-responsive"><table class="table table-sm table-striped">';
                logsHtml += '<thead><tr><th>Date & Time</th><th>Action</th><th>Details</th><th>User</th></tr></thead><tbody>';

                logs.forEach(log => {
                    const logDate = new Date(log.date);
                    let actionBadgeClass = 'bg-primary';
                    let actionText = log.action;

                    // Style based on action type
                    switch(log.action.toLowerCase()) {
                        case 'administration':
                            actionBadgeClass = 'bg-success';
                            actionText = 'Administered';
                            break;
                        case 'edit':
                            actionBadgeClass = 'bg-info';
                            actionText = 'Edited';
                            break;
                        case 'delete':
                            actionBadgeClass = 'bg-dark';
                            actionText = 'Deleted';
                            break;
                        case 'discontinue':
                            actionBadgeClass = 'bg-warning';
                            actionText = 'Discontinued';
                            break;
                        case 'resume':
                            actionBadgeClass = 'bg-success';
                            actionText = 'Resumed';
                            break;
                    }

                    logsHtml += `<tr>
                        <td>${formatDateTime(logDate)}</td>
                        <td><span class="badge ${actionBadgeClass}">${actionText}</span></td>
                        <td>${log.details || log.reason || '-'}</td>
                        <td>${log.user || 'Unknown'}</td>
                    </tr>`;
                });

                logsHtml += '</tbody></table></div>';
            }

            // Populate and show logs modal
            $('#medication-logs-title').text(`Activity Logs: ${medication.product.product_name}`);
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
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
        });

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
                repeat_daily: repeatDaily ? 1 : 0, // Convert to 1/0 for Laravel to properly interpret as boolean
                selected_days: selectedDays,
                duration_days: durationDays
            };

            $.ajax({
                url: medicationChartScheduleRoute,
                type: 'POST',
                data: scheduleData,
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                success: function(response) {
                    if (response.success) {
                        toastr.success('Schedule created successfully.');
                        $('#setScheduleModal').modal('hide');
                        loadMedicationCalendar(medicationId, calendarStartDate);
                    } else {
                        toastr.error(response.message || 'Failed to create schedule.');
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
                    $('#administer-medication-info').text(`${medication.product.product_name} - ${schedule.dose}`);
                    $('#administer-scheduled-time').text(`Scheduled for: ${formatDateTime(scheduledTime)}`);
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
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                success: function(response) {
                    if (response.success) {
                        toastr.success('Medication administered successfully.');
                        $('#administerModal').modal('hide');
                        loadMedicationCalendar(selectedMedication, calendarStartDate);
                    } else {
                        toastr.error(response.message || 'Failed to administer medication.');
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

                const medication = medications.find(m => m.id == admin.product_or_service_request_id);
                const medicationName = medication && medication.product ? medication.product.product_name : 'Medication';

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
                $('#edit_administered_at').val(formatDateTimeForInput(new Date(admin.administered_at)));
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
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                success: function(response) {
                    if (response.success) {
                        toastr.success('Administration updated successfully.');
                        $('#editAdminModal').modal('hide');
                        loadMedicationCalendar(selectedMedication, calendarStartDate);
                    } else {
                        toastr.error(response.message || 'Failed to update administration.');
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
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                success: function(response) {
                    if (response.success) {
                        toastr.success('Administration deleted successfully.');
                        $('#deleteAdminModal').modal('hide');
                        loadMedicationCalendar(selectedMedication, calendarStartDate);
                    } else {
                        toastr.error(response.message || 'Failed to delete administration.');
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
                        $('#deleteAdminSubmitBtn .spinner-border').addClass('d-none');
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
            $('#discontinue-medication-name').text(`Discontinue ${medication.product.product_name}`);
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
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                success: function(response) {
                    if (response.success) {
                        toastr.success('Medication discontinued successfully.');
                        $('#discontinueModal').modal('hide');

            // Update medication status in our local data
            if (medicationStatus[medicationId]) {
                medicationStatus[medicationId].discontinued = true;
                medicationStatus[medicationId].discontinued_at = new Date();
                medicationStatus[medicationId].discontinued_reason = reason;
            }

            // Add to history for logs
            const historyItem = {
                date: new Date(),
                action: 'discontinue',
                reason: reason,
                user: response.history ? response.history.user_fullname : 'Current User',
                medication_name: medication.product ? medication.product.product_name : 'Medication'
            };

            if (!medicationHistory[medicationId]) {
                medicationHistory[medicationId] = [];
            }
            medicationHistory[medicationId].push(historyItem);

            loadMedicationCalendar(medicationId, calendarStartDate);
                    } else {
                        toastr.error(response.message || 'Failed to discontinue medication.');
                    }
                    // Reset button state
                    if ($('#discontinueSubmitBtn .spinner-border').length) {
                        $('#discontinueSubmitBtn .spinner-border').addClass('d-none');
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
                        $('#discontinueSubmitBtn .spinner-border').addClass('d-none');
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
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
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
                user: response.history ? response.history.user_fullname : 'Current User',
                medication_name: medication.product ? medication.product.product_name : 'Medication'
            };

            if (!medicationHistory[medicationId]) {
                medicationHistory[medicationId] = [];
            }
            medicationHistory[medicationId].push(historyItem);

            loadMedicationCalendar(medicationId, calendarStartDate);
                    } else {
                        toastr.error(response.message || 'Failed to resume medication.');
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

        // Initialize Intake/Output Chart functions
        function loadIntakeOutput(type) {
            // Intake/output functionality would go here
        }

        $('#startFluidPeriodBtn').click(function() {
            // Fluid period functionality would go here
        });

        $('#startSolidPeriodBtn').click(function() {
            // Solid period functionality would go here
        });
    });

    $(document).ready(function() {
        // Reset discontinue form on modal hidden
        $('#discontinueModal').on('hidden.bs.modal', function() {
            $('#discontinue_reason').val('');
            if ($('#discontinueSubmitBtn .spinner-border').length) {
                $('#discontinueSubmitBtn .spinner-border').addClass('d-none');
                $('#discontinueSubmitBtn').prop('disabled', false);
            }
        });

        // Reset resume form on modal hidden
        $('#resumeModal').on('hidden.bs.modal', function() {
            $('#resume_reason').val('');
            if ($('#resumeSubmitBtn .spinner-border').length) {
                $('#resumeSubmitBtn .spinner-border').addClass('d-none');
                $('#resumeSubmitBtn').prop('disabled', false);
            }
        });

        // Reset delete admin form on modal hidden
        $('#deleteAdminModal').on('hidden.bs.modal', function() {
            $('#delete_reason').val('');
            if ($('#deleteAdminSubmitBtn .spinner-border').length) {
                $('#deleteAdminSubmitBtn .spinner-border').addClass('d-none');
                $('#deleteAdminSubmitBtn').prop('disabled', false);
            }
        });

        // Reset edit admin form on modal hidden
        $('#editAdminModal').on('hidden.bs.modal', function() {
            $('#edit_reason').val('');
            if ($('#editAdminSubmitBtn .spinner-border').length) {
                $('#editAdminSubmitBtn .spinner-border').addClass('d-none');
                $('#editAdminSubmitBtn').prop('disabled', false);
            }
        });
    });
</script>

<!-- Medication Logs Modal -->
<div class="modal fade" id="medicationLogsModal" tabindex="-1" aria-labelledby="medicationLogsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="medication-logs-title">Activity Logs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="medication-logs-content">
                <!-- Logs content will be populated dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Add this to your HTML where the buttons should appear -->
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
                    '<button id="view-logs-btn" class="btn btn-info ms-2" disabled ' +
                    'data-action="view-medication-logs">' +
                    '<i class="mdi mdi-history"></i> View Logs</button>'
                );

                // Add it after the resume button
                resumeBtn.after(viewLogsBtn);

                // No need for explicit modal initialization with jQuery's .modal() method
                // The modal will be initialized when .modal('show') is called

                // Improve button layout
                const buttonContainer = resumeBtn.parent();
                if (buttonContainer.length) {
                    // Add flex display to container for better spacing
                    buttonContainer.addClass('d-flex flex-wrap gap-2 mt-2 mb-3');

                    // Style the buttons consistently
                    buttonContainer.find('.btn').each(function() {
                        $(this).addClass('d-flex align-items-center justify-content-center');

                        // If no icon, add one
                        if ($(this).attr('id') === 'set-schedule-btn' && !$(this).find('.mdi').length) {
                            $(this).prepend('<i class="mdi mdi-calendar-plus me-1"></i> ');
                        }
                        if ($(this).attr('id') === 'discontinue-btn' && !$(this).find('.mdi').length) {
                            $(this).prepend('<i class="mdi mdi-stop-circle-outline me-1"></i> ');
                        }
                        if ($(this).attr('id') === 'resume-btn' && !$(this).find('.mdi').length) {
                            $(this).prepend('<i class="mdi mdi-play-circle-outline me-1"></i> ');
                        }
                    });
                }
            }
        }, 500);

        // Extra insurance: add direct click handler for the logs button
        setTimeout(function() {
            $('#view-logs-btn').on('click', function() {
                console.log('Direct click handler triggered');
                if (selectedMedication) {
                    const medication = medications.find(m => m.id == selectedMedication);
                    if (medication) {
                        const logs = medicationHistory[selectedMedication] || [];

                        // Sort logs by date, newest first
                        logs.sort((a, b) => new Date(b.date) - new Date(a.date));

                        // Generate logs HTML (simplified for direct handler)
                        let logsHtml = logs.length === 0 ?
                            '<p class="text-muted">No activity logs available for this medication.</p>' :
                            '<div class="table-responsive"><table class="table table-sm table-striped">...</table></div>';

                        // Populate and show logs modal
                        $('#medication-logs-title').text(`Activity Logs: ${medication.product ? medication.product.product_name : 'Medication'}`);
                        $('#medication-logs-content').html(logsHtml);
                        $('#medicationLogsModal').modal('show');
                    }
                }
            });
        }, 1000);
    });
</script>
