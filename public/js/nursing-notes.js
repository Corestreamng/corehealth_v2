if (typeof window.wbUrl !== 'function') {
    window.wbUrl = function(path) {
        var base = (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.baseUrl) ? window.WORKBENCH_CONFIG.baseUrl : '';
        base = base.replace(/\/$/, '');
        var cleanPath = (path || '').replace(/^\//, '');
        return base ? (base + '/' + cleanPath) : ('/' + cleanPath);
    };
}
if (typeof window.wbRoute !== 'function') {
    window.wbRoute = function(name, fallbackPath) {
        if (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.routes) {
            if (window.WORKBENCH_CONFIG.routes[name]) return window.WORKBENCH_CONFIG.routes[name];
            var dotKey = name.replace(/_/g, '.');
            if (window.WORKBENCH_CONFIG.routes[dotKey]) return window.WORKBENCH_CONFIG.routes[dotKey];
            var underscoreKey = name.replace(/\./g, '_');
            if (window.WORKBENCH_CONFIG.routes[underscoreKey]) return window.WORKBENCH_CONFIG.routes[underscoreKey];
        }
        return window.wbUrl(fallbackPath || '');
    };
}

// =============================================
// TAB SWITCH TRIGGERS
// =============================================
$(document).on('shown.bs.tab', '#med-overview-tab', function() {
    loadMedOverview(overviewCurrentStart);
});

$(document).on('shown.bs.tab', '#med-rx-tab', function() {
    loadPrescriptionsTab();
});

// Global variables for I/O chart
let fluidPeriods = [];
let solidPeriods = [];
let currentFluidPeriodId = null;
let currentSolidPeriodId = null;

// Initialize medication chart for a specific patient
function initMedicationChart(patientId) {
    if (!patientId) {
        console.error('No patient ID provided for medication chart');
        return;
    }

    PATIENT_ID = patientId;

    // Update hidden input for patient ID in modals
    $('#schedule_patient_id').val(patientId);
    $('#discontinue_patient_id').val(patientId);
    $('#resume_patient_id').val(patientId);

    // Reset medication state
    selectedMedication = null;
    medications = [];
    medicationStatus = {};
    currentSchedules = [];
    currentAdministrations = [];
    medicationHistory = {};

    // Reset UI
    $('#drug-select').empty().append('<option value="">-- Select a medication --</option>');
    $('#medication-calendar').hide();
    $('#calendar-legend').hide();
    $('#medication-status').empty();
    $('#set-schedule-btn, #discontinue-btn, #resume-btn').prop('disabled', true);

    // Load medications list
    loadMedicationsList();
}

// Initialize I/O chart for a specific patient
function initIntakeOutputChart(patientId) {
    if (!patientId) {
        console.error('No patient ID provided for I/O chart');
        return;
    }

    PATIENT_ID = patientId;

    // Initialize date filters with today - 7 days to today
    const today = new Date();
    const weekAgo = new Date();
    weekAgo.setDate(weekAgo.getDate() - 7);

    $('#fluid_start_date, #solid_start_date').val(weekAgo.toISOString().split('T')[0]);
    $('#fluid_end_date, #solid_end_date').val(today.toISOString().split('T')[0]);

    // Load I/O data
    loadFluidPeriods();
    loadSolidPeriods();
}

// Helper function to get user name
function getUserName(obj) {
    return obj.user_fullname || obj.user_name || obj.administered_by_name ||
        (obj.administeredBy && obj.administeredBy.name) ||
        obj.nurse_name ||
        (obj.nurse && obj.nurse.name) || 'Unknown';
}

// Format date for API calls
function formatDateForApi(date) {
    if (date instanceof Date) {
        return date.toISOString().split('T')[0];
    }
    return date;
}

// Format date for display
function formatDate(date) {
    if (!(date instanceof Date)) date = new Date(date);
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

// Format time for display
function formatTime(date) {
    if (!(date instanceof Date)) date = new Date(date);
    return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
}

// Format datetime for display
function formatDateTime(date) {
    if (!(date instanceof Date)) date = new Date(date);
    return formatDate(date) + ' ' + formatTime(date);
}

// Get day of week
function getDayOfWeek(date) {
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    return days[date.getDay()];
}

// =============================================
// MEDICATION CHART FUNCTIONS
// =============================================

function loadMedicationsList() {
    if (!PATIENT_ID) {
        console.error('Patient ID is not set');
        return;
    }

    console.log('Loading medications list (enriched §6.1)...');
    $('#medication-loading').show();
    $('#medication-calendar').hide();

    // §6.1: Use prescribed-drugs API for enriched status data
    var prescribedUrl = medicationChartPrescribedRoute.replace(':patient', PATIENT_ID);

    $.ajax({
        url: prescribedUrl,
        type: 'GET',
        success: function(data) {
            console.log('Prescribed drugs loaded:', data);
            $('#medication-loading').hide();

            var prescriptions = data.prescriptions || [];

            // Store all prescriptions for reference
            window._rxLookup = {};
            prescriptions.forEach(function(rx) { window._rxLookup[rx.posr_id || rx.id] = rx; });

            // §6.1: Populate dropdown with rich, status-aware options
            var select = $('#drug-select');
            select.empty();
            select.append('<option value="">-- Select a medication --</option>');

            if (prescriptions.length === 0) {
                toastr.warning('No medications found for this patient.');
            } else {
                console.log('Found ' + prescriptions.length + ' prescriptions');

                prescriptions.forEach(function(rx) {
                    var posrId = rx.posr_id || '';
                    var canChart = rx.can_chart && posrId;

                    // §6.1: Status icon + color
                    var statusIcon, statusBadge;
                    switch (rx.status) {
                        case 3:
                            statusIcon = '🟢';
                            statusBadge = '<span class="badge bg-success">Dispensed</span>';
                            break;
                        case 2:
                            if (rx.is_paid) {
                                statusIcon = '🟡';
                                statusBadge = '<span class="badge bg-warning text-dark">Awaiting Pharmacy</span>';
                            } else {
                                statusIcon = '🟠';
                                statusBadge = '<span class="badge bg-secondary">' + rx.status_label + '</span>';
                            }
                            break;
                        default:
                            statusIcon = '🔴';
                            statusBadge = '<span class="badge bg-danger">Awaiting Billing</span>';
                    }

                    // Administered progress
                    var adminText = rx.is_dispensed
                        ? 'Administered: ' + rx.times_administered + '/' + rx.qty_prescribed
                        : '';

                    // Doctor info
                    var doctorText = rx.doctor_name ? 'Dr. ' + rx.doctor_name : '';

                    // Build display text
                    var plainText = statusIcon + ' ' + rx.product_name + ' (' + rx.product_code + ') — ' + rx.status_label;

                    var opt = new Option(plainText, posrId || ('rx_' + rx.id), false, false);
                    opt.disabled = !canChart;

                    // Store rich data on the option for Select2 templateResult
                    $(opt).data('rx', rx);
                    $(opt).data('status-icon', statusIcon);
                    $(opt).data('status-badge', statusBadge);
                    $(opt).data('admin-text', adminText);
                    $(opt).data('doctor-text', doctorText);
                    $(opt).data('drug-source', 'pharmacy_dispensed');
                    $(opt).data('product-request-id', rx.product_request_id);
                    $(opt).data('product-id', rx.product_id);

                    select.append(opt);

                    // Store medication status for discontinue/resume tracking
                    if (posrId) {
                        medicationStatus[posrId] = {
                            discontinued: false,
                            resumed: false,
                        };
                    }
                });

                // §6.1: Merge direct administration entries (ward stock + patient's own)
                var directEntries = data.direct_entries || [];
                if (directEntries.length> 0) {
                    // Add separator
                    var separator = new Option('── Direct Administrations ──', '', false, false);
                    separator.disabled = true;
                    $(separator).data('is-separator', true);
                    select.append(separator);

                    directEntries.forEach(function(entry) {
                        var isPatientOwn = entry.drug_source === 'patient_own';
                        var icon = isPatientOwn ? '🟣' : '🔵';
                        var label = isPatientOwn ? "Patient's Own" : 'Ward Stock';
                        var drugName = entry.product_name || entry.external_drug_name || 'Unknown';
                        var codeStr = entry.product_code ? ' (' + entry.product_code + ')' : '';
                        var plainText = icon + ' ' + drugName + codeStr + ' — ' + label;

                        var optVal = 'direct_' + entry.drug_source + '_' + (entry.product_id || entry.external_drug_name || entry.id);
                        var opt = new Option(plainText, optVal, false, false);

                        // Store data for Select2 template and calendar loading
                        $(opt).data('direct-entry', entry);
                        $(opt).data('drug-source', entry.drug_source);
                        $(opt).data('product-id', entry.product_id || null);
                        $(opt).data('external-drug-name', entry.external_drug_name || null);
                        $(opt).data('status-icon', icon);
                        $(opt).data('is-direct', true);

                        select.append(opt);
                    });

                    console.log('Added ' + directEntries.length + ' direct administration entries to dropdown');
                }

                // §6.1: Initialize Select2 with rich formatting
                if (select.hasClass('select2-hidden-accessible')) {
                    select.select2('destroy');
                }
                select.select2({
                    width: '100%',
                    placeholder: '-- Select a medication --',
                    allowClear: true,
                    templateResult: formatRxOption,
                    templateSelection: formatRxSelection,
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('Failed to load medications:', status, error);
            $('#medication-loading').hide();
            toastr.error('Failed to load medications: ' + error);
        }
    });
}

// Drug selection change
$(document).on('change', '#drug-select', function() {
    const medicationId = $(this).val();

    if (medicationId) {
        selectedMedication = medicationId;

        // Detect if this is a direct entry (ward_stock / patient_own)
        var $selectedOpt = $(this).find('option:selected');
        var isDirect = $selectedOpt.data('is-direct') || false;

        // Enable schedule button; disable discontinue/resume for direct entries
        $('#set-schedule-btn').prop('disabled', false);
        $('#discontinue-btn').prop('disabled', isDirect);
        $('#resume-btn').prop('disabled', isDirect);

        const endDate = new Date(calendarStartDate);
        endDate.setDate(endDate.getDate() + 30);

        const startDateStr = formatDateForApi(calendarStartDate);
        const endDateStr = formatDateForApi(endDate);
        $('#med-start-date').val(startDateStr);
        $('#med-end-date').val(endDateStr);

        loadMedicationCalendarWithDateRange(medicationId, startDateStr, endDateStr);
    } else {
        selectedMedication = null;
        $('#medication-calendar').hide();
        $('#calendar-legend').hide();
        $('#set-schedule-btn, #discontinue-btn, #resume-btn').prop('disabled', true);
        $('#medication-status').empty();
    }
});

// Calendar navigation buttons
$(document).on('click', '#prev-month-btn', function() {
    if (selectedMedication) {
        calendarStartDate.setDate(calendarStartDate.getDate() - 30);
        const endDate = new Date(calendarStartDate);
        endDate.setDate(endDate.getDate() + 30);

        const startDateStr = formatDateForApi(calendarStartDate);
        const endDateStr = formatDateForApi(endDate);
        $('#med-start-date').val(startDateStr);
        $('#med-end-date').val(endDateStr);

        loadMedicationCalendarWithDateRange(selectedMedication, startDateStr, endDateStr);
    }
});

$(document).on('click', '#next-month-btn', function() {
    if (selectedMedication) {
        calendarStartDate.setDate(calendarStartDate.getDate() + 30);
        const endDate = new Date(calendarStartDate);
        endDate.setDate(endDate.getDate() + 30);

        const startDateStr = formatDateForApi(calendarStartDate);
        const endDateStr = formatDateForApi(endDate);
        $('#med-start-date').val(startDateStr);
        $('#med-end-date').val(endDateStr);

        loadMedicationCalendarWithDateRange(selectedMedication, startDateStr, endDateStr);
    }
});

$(document).on('click', '#today-btn', function() {
    if (selectedMedication) {
        calendarStartDate = new Date();
        calendarStartDate.setDate(calendarStartDate.getDate() - 15);

        const endDate = new Date();
        endDate.setDate(endDate.getDate() + 15);

        const startDateStr = formatDateForApi(calendarStartDate);
        const endDateStr = formatDateForApi(endDate);
        $('#med-start-date').val(startDateStr);
        $('#med-end-date').val(endDateStr);

        loadMedicationCalendarWithDateRange(selectedMedication, startDateStr, endDateStr);
    }
});

$(document).on('click', '#apply-date-range-btn', function() {
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

    if (new Date(startDateStr)> new Date(endDateStr)) {
        toastr.warning('Start date cannot be after end date.');
        return;
    }

    calendarStartDate = new Date(startDateStr);
    loadMedicationCalendarWithDateRange(selectedMedication, startDateStr, endDateStr);
});

function loadMedicationCalendarWithDateRange(medicationId, startDate, endDate) {
    if (!medicationId || !PATIENT_ID) return;

    $('#medication-loading').show();
    $('#medication-calendar').hide();

    // Determine if this is a direct entry by checking the selected option
    var $selectedOpt = $('#drug-select').find('option[value="' + medicationId + '"]');
    var isDirect = $selectedOpt.data('is-direct') || false;
    var url;

    if (isDirect) {
        // Direct entry — use directCalendar endpoint with query params
        var drugSource = $selectedOpt.data('drug-source');
        var productId = $selectedOpt.data('product-id');
        var externalDrugName = $selectedOpt.data('external-drug-name');

        url = medicationChartDirectCalendarRoute.replace(':patient', PATIENT_ID);
        var queryParams = {
            drug_source: drugSource,
            start_date: startDate,
            end_date: endDate
        };
        if (productId) queryParams.product_id = productId;
        if (externalDrugName) queryParams.external_drug_name = externalDrugName;

        $.ajax({
            url: url,
            type: 'GET',
            data: queryParams,
            success: function(data) {
                $('#medication-loading').hide();
                handleCalendarResponse(data, medicationId);
            },
            error: function() {
                $('#medication-loading').hide();
                toastr.error('Failed to load medication calendar.');
            }
        });
    } else {
        // Standard POSR — use calendar route
        url = medicationChartCalendarRoute
            .replace(':patient', PATIENT_ID)
            .replace(':medication', medicationId)
            .replace(':start_date', startDate);

        $.ajax({
            url: url,
            type: 'GET',
            data: { start_date: startDate, end_date: endDate },
            success: function(data) {
                $('#medication-loading').hide();
                handleCalendarResponse(data, medicationId);
            },
            error: function() {
                $('#medication-loading').hide();
                toastr.error('Failed to load medication calendar.');
            }
        });
    }
}

// Shared handler for calendar response (works for both POSR and direct entries)
function handleCalendarResponse(data, medicationId) {
    if (data.medication) {
        const medication = data.medication;
        currentSchedules = data.schedules || [];
        currentAdministrations = data.administrations || [];

        // Store total counts from server (all-time, not just date range)
        window._currentMedCounts = {
            totalScheduled: data.total_scheduled || currentSchedules.length,
            totalAdministered: data.total_administered || currentAdministrations.filter(a => a.administered_at).length,
            rangeScheduled: currentSchedules.length,
            rangeAdministered: currentAdministrations.filter(a => a.administered_at).length
        };

        updateMedicationStatus(medication);
        updateMedicationButtons(medication);

        // Store history
        let logEntries = [];
        if (data.history && Array.isArray(data.history)) {
            logEntries = [...data.history];
        }
        if (data.adminHistory && Array.isArray(data.adminHistory)) {
            data.adminHistory.forEach(admin => {
                logEntries.push({
                    date: admin.administered_at,
                    action: 'administration',
                    details: `${admin.dose} ${admin.route} ${admin.comment ? '- ' + admin.comment : ''}`,
                    user: admin.administered_by_name || getUserName(admin) || 'Unknown',
                    id: admin.id
                });
            });
        }
        medicationHistory[selectedMedication] = logEntries;

        renderCalendarView(medication, currentSchedules, currentAdministrations, data.period);
        renderLegend();
        $('#medication-calendar').show();
        $('#calendar-legend').show();
    }
}

function updateMedicationStatus(medication) {
    let statusHtml = '';
    var counts = window._currentMedCounts || { totalScheduled: 0, totalAdministered: 0 };
    var countsHtml = '<span class="badge bg-light text-dark border me-1"><i class="mdi mdi-calendar-clock"></i> ' + counts.totalScheduled + ' scheduled</span>' +
                     '<span class="badge bg-light text-dark border"><i class="mdi mdi-check-circle"></i> ' + counts.totalAdministered + ' administered</span>';

    // Direct entries have product_name at top level; POSR entries have medication.product.product_name
    var productName = '';
    if (medication.is_direct_entry) {
        productName = medication.product_name || 'Direct Entry';
        var sourceLabel = medication.drug_source === 'patient_own' ? "Patient's Own" : 'Ward Stock';
        var sourceBadge = medication.drug_source === 'patient_own' ? 'bg-purple' : 'bg-info';
        statusHtml = `
            <div class="alert alert-info py-2 mb-0">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <i class="mdi mdi-pill me-2"></i>
                        <strong>${productName}</strong>: <span class="badge ${sourceBadge}">${sourceLabel}</span>
                    </div>
                    <div>${countsHtml}</div>
                </div>
            </div>`;
    } else if (medication.product && medication.product.product_name) {
        productName = medication.product.product_name;

        if (medication.discontinued_at && !medication.resumed_at) {
            statusHtml = `
                <div class="alert alert-danger py-2 mb-0">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <i class="mdi mdi-calendar-remove me-2"></i>
                            <strong>${productName}</strong>: Discontinued
                            <div class="small">Reason: ${medication.discontinued_reason || 'N/A'}</div>
                        </div>
                        <div>${countsHtml}</div>
                    </div>
                </div>`;
        } else {
            statusHtml = `
                <div class="alert alert-success py-2 mb-0">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <i class="mdi mdi-check-circle me-2"></i>
                            <strong>${productName}</strong>: <span class="badge bg-success">Active</span>
                        </div>
                        <div>${countsHtml}</div>
                    </div>
                </div>`;
        }
    }

    $('#medication-status').html(statusHtml);
}

function updateMedicationButtons(medication) {
    // Direct entries don't support discontinue/resume
    if (medication.is_direct_entry) {
        $('#discontinue-btn').prop('disabled', true);
        $('#resume-btn').prop('disabled', true);
        $('#set-schedule-btn').prop('disabled', false);
        $('#view-logs-btn').prop('disabled', false);
        return;
    }

    const isDiscontinued = !!medication.discontinued_at;
    const isResumed = !!medication.resumed_at;
    const effectivelyDiscontinued = isDiscontinued && !isResumed;

    $('#discontinue-btn').prop('disabled', effectivelyDiscontinued);
    $('#resume-btn').prop('disabled', !effectivelyDiscontinued);
    $('#set-schedule-btn').prop('disabled', effectivelyDiscontinued);
    $('#view-logs-btn').prop('disabled', false);
}

// View logs button handler
$(document).on('click', '#view-logs-btn', function() {
    if (!selectedMedication) return;

    const logs = medicationHistory[selectedMedication] || [];
    let logsHtml = '';

    if (logs.length === 0) {
        logsHtml = '<div class="alert alert-info">No activity logs available for this medication.</div>';
    } else {
        logsHtml = '<div class="table-responsive"><table class="table table-sm table-striped">';
        logsHtml += '<thead><tr><th>Date</th><th>Action</th><th>Details</th><th>User</th></tr></thead><tbody>';

        logs.forEach(log => {
            const logDate = new Date(log.date);
            let actionBadge = 'bg-primary';
            let actionText = log.action || 'N/A';

            switch((log.action || '').toLowerCase()) {
                case 'administration': actionBadge = 'bg-success'; actionText = 'Administered'; break;
                case 'edit': actionBadge = 'bg-info'; actionText = 'Edited'; break;
                case 'delete': actionBadge = 'bg-dark'; actionText = 'Deleted'; break;
                case 'discontinue': actionBadge = 'bg-warning'; actionText = 'Discontinued'; break;
                case 'resume': actionBadge = 'bg-success'; actionText = 'Resumed'; break;
            }

            logsHtml += `<tr>
                <td><small>${formatDateTime(logDate)}</small></td>
                <td><span class="badge ${actionBadge}">${actionText}</span></td>
                <td>${log.details || log.reason || '-'}</td>
                <td><small>${log.user || 'Unknown'}</small></td>
            </tr>`;
        });

        logsHtml += '</tbody></table></div>';
    }

    // Get medication name from dropdown option data (works for both POSR and direct entries)
    let medicationName = 'Medication';
    const $logsSelectedOpt = $('#drug-select').find('option:selected');
    const logsDirectEntry = $logsSelectedOpt.data('direct-entry');
    if (logsDirectEntry) {
        medicationName = logsDirectEntry.product_name || logsDirectEntry.external_drug_name || 'Direct Entry';
    } else {
        const logsRx = $logsSelectedOpt.data('rx');
        if (logsRx) {
            medicationName = logsRx.product_name || 'Medication';
        }
    }

    $('#medication-logs-title').text('Activity Logs: ' + medicationName);
    $('#medication-logs-content').html(logsHtml);
    $('#medicationLogsModal').modal('show');
});

function renderLegend() {
    const legendHtml = `
        <div class="card-modern shadow-sm mb-3">
            <div class="card-body p-2">
                <h6 class="card-title mb-2"><i class="mdi mdi-information-outline text-primary me-1"></i> Legend</h6>
                <div class="d-flex flex-wrap gap-1">
                    <span class="badge bg-primary rounded-pill"><i class="mdi mdi-calendar-clock"></i> Scheduled</span>
                    <span class="badge bg-success rounded-pill"><i class="mdi mdi-check"></i> Administered</span>
                    <span class="badge bg-info rounded-pill"><i class="mdi mdi-pencil"></i> Edited</span>
                    <span class="badge bg-dark rounded-pill"><i class="mdi mdi-close"></i> Deleted</span>
                    <span class="badge bg-danger rounded-pill"><i class="mdi mdi-calendar-remove"></i> Missed</span>
                    <span class="badge bg-secondary rounded-pill"><i class="mdi mdi-calendar"></i> Discontinued</span>
                </div>
            </div>
        </div>`;

    $('#calendar-legend').html(legendHtml);
}

function renderCalendarView(medication, schedules, administrations, period) {
    const startDate = new Date(period.start);
    const endDate = new Date(period.end);
    const product = medication.product || {};
    // Direct entries have product_name at top level; POSR entries have it under .product
    const productName = medication.product_name || product.product_name || 'Medication';

    let doctorDose = medication.dose || '';
    let doctorInfoHtml = '';
    if (doctorDose) {
        doctorInfoHtml = `<div class="my-2"><span class="badge bg-warning text-dark fw-bold">Doctor's Order: ${doctorDose}</span></div>`;
    }

    const dateRange = `${formatDate(startDate)} to ${formatDate(endDate)}`;

    $('#calendar-title').html(`
        <div class="mb-1"><span class="text-primary fw-bold">${productName}</span></div>
        ${doctorInfoHtml}
        <div class="small text-muted"><i class="mdi mdi-calendar-range"></i> ${dateRange}</div>
    `);

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const days = [];
    for (let d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
        days.push(new Date(d));
    }

    // Build weekday header
    const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    let headerHtml = '<div class="calendar-weekday-header">';
    weekdays.forEach(day => {
        headerHtml += `<div class="weekday-name">${day}</div>`;
    });
    headerHtml += '</div>';

    // Build calendar grid
    let gridHtml = '<div class="medication-calendar-grid">';

    // Add empty cells for padding to align first day with correct weekday
    const firstDayOfWeek = days.length> 0 ? days[0].getDay() : 0;
    for (let i = 0; i < firstDayOfWeek; i++) {
        gridHtml += '<div class="calendar-day-cell empty-day"></div>';
    }

    // Add day cells
    days.forEach((day) => {
        const isToday = day.toDateString() === today.toDateString();
        const isWeekend = day.getDay() === 0 || day.getDay() === 6;
        const isPast = day < today;

        let cellClasses = 'calendar-day-cell';
        if (isToday) cellClasses += ' today';
        if (isWeekend) cellClasses += ' weekend';
        if (isPast && !isToday) cellClasses += ' past-date';

        gridHtml += `<div class="${cellClasses}" data-date="${formatDateForApi(day)}">`;
        gridHtml += `<div class="calendar-day-header">`;
        gridHtml += `<span>${getDayOfWeek(day)}</span>`;
        gridHtml += `<span class="calendar-day-date">${day.getDate()}</span>`;
        gridHtml += `</div>`;
        gridHtml += `<div class="calendar-schedules">`;

        const daySchedules = schedules.filter(s => {
            const scheduleDate = new Date(s.scheduled_time);
            return scheduleDate.toDateString() === day.toDateString();
        });

        // Find unscheduled (direct) administrations for this day
        const dayUnscheduledAdmins = administrations.filter(a => {
            if (!a.administered_at || a.schedule_id) return false;
            const adminDate = new Date(a.administered_at);
            return adminDate.toDateString() === day.toDateString();
        });

        if (daySchedules.length === 0 && dayUnscheduledAdmins.length === 0) {
            gridHtml += `<span class="text-muted small fst-italic">No activity</span>`;
        } else {
            // Render scheduled items
            daySchedules.forEach(schedule => {
                const scheduleTime = new Date(schedule.scheduled_time);
                const formattedTime = formatTime(scheduleTime);
                const admin = administrations.find(a => a.schedule_id === schedule.id);

                // Detect if schedule is for a direct entry (ward_stock / patient_own)
                const isDirectSchedule = schedule.drug_source && schedule.drug_source !== 'pharmacy_dispensed';

                let badgeClass = 'bg-primary';
                let badgeContent = `<i class="mdi mdi-calendar-clock"></i> ${formattedTime}`;
                // All schedule slots open the administer modal (handler routes to correct endpoint)
                let adminAction = `data-bs-target="#administerModal" data-schedule-id="${schedule.id}"`;
                let tooltipContent = `Dose: ${schedule.dose}<br>Route: ${schedule.route}<br>Status: Scheduled`;
                if (isDirectSchedule) {
                    tooltipContent += `<br><em>${schedule.drug_source === 'ward_stock' ? 'Ward Stock' : "Patient's Own"}</em>`;
                }

                const isDiscontinued = medication.discontinued_at &&
                    new Date(medication.discontinued_at) < scheduleTime &&
                    (!medication.resumed_at || new Date(medication.resumed_at)> scheduleTime);

                if (isDiscontinued) {
                    badgeClass = 'bg-secondary';
                    adminAction = '';
                    tooltipContent = `Dose: ${schedule.dose}<br>Route: ${schedule.route}<br>Status: Discontinued`;
                } else if (admin) {
                    badgeClass = 'bg-success';
                    badgeContent = `<i class="mdi mdi-check"></i> ${formattedTime}`;
                    adminAction = `data-bs-target="#adminDetailsModal" data-admin-id="${admin.id}"`;
                    tooltipContent = `Dose: ${admin.dose}<br>Route: ${admin.route}<br>Status: Administered`;

                    if (admin.edited_at) {
                        badgeClass = 'bg-info';
                        badgeContent = `<i class="mdi mdi-pencil"></i> ${formattedTime}`;
                    }
                    if (admin.deleted_at) {
                        badgeClass = 'bg-dark';
                        badgeContent = `<i class="mdi mdi-close"></i> ${formattedTime}`;
                        adminAction = '';
                    }
                } else {
                    const now = new Date();
                    if (scheduleTime < now) {
                        badgeClass = 'bg-danger';
                        badgeContent = `<i class="mdi mdi-alert"></i> ${formattedTime}`;
                        tooltipContent = `Dose: ${schedule.dose}<br>Route: ${schedule.route}<br>Status: Missed`;
                    }
                }

                let removeBtn = '';
                if (!admin && !isDiscontinued) {
                    removeBtn = `<button class='btn btn-sm btn-outline-danger remove-schedule-btn' data-schedule-id='${schedule.id}' title='Remove schedule'><i class='mdi mdi-trash-can-outline'></i></button>`;
                }

                gridHtml += `<div class="calendar-schedule-item">`;
                gridHtml += `<span class="schedule-slot badge ${badgeClass}" ${adminAction} data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="${tooltipContent}">${badgeContent}</span>`;
                gridHtml += removeBtn;
                gridHtml += `</div>`;
            });

            // Render unscheduled (direct) administrations — these come from ward stock / patient's own buttons
            dayUnscheduledAdmins.forEach(admin => {
                const adminTime = new Date(admin.administered_at);
                const formattedTime = formatTime(adminTime);
                let badgeClass = 'bg-success';
                let badgeContent = `<i class="mdi mdi-check"></i> ${formattedTime}`;
                let adminAction = `data-bs-target="#adminDetailsModal" data-admin-id="${admin.id}"`;
                let tooltipContent = `Dose: ${admin.dose || 'N/A'}<br>Route: ${admin.route || 'N/A'}<br>Direct Administration`;

                if (admin.edited_at) {
                    badgeClass = 'bg-info';
                    badgeContent = `<i class="mdi mdi-pencil"></i> ${formattedTime}`;
                }
                if (admin.deleted_at) {
                    badgeClass = 'bg-dark';
                    badgeContent = `<i class="mdi mdi-close"></i> ${formattedTime}`;
                    adminAction = '';
                }

                gridHtml += `<div class="calendar-schedule-item">`;
                gridHtml += `<span class="schedule-slot badge ${badgeClass}" ${adminAction} data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="${tooltipContent}">${badgeContent}</span>`;
                gridHtml += `</div>`;
            });
        }

        gridHtml += `</div></div>`;
    });

    // Add trailing empty cells to complete the last week row
    const lastDayOfWeek = days.length> 0 ? days[days.length - 1].getDay() : 6;
    for (let i = lastDayOfWeek + 1; i < 7; i++) {
        gridHtml += '<div class="calendar-day-cell empty-day"></div>';
    }

    gridHtml += '</div>';

    // Output to container
    $('#calendar-container').html(headerHtml + gridHtml);

    // Initialize tooltips
    $('.schedule-slot[data-bs-toggle="tooltip"]').tooltip('dispose');
    $('.schedule-slot[data-bs-toggle="tooltip"]').tooltip({
        placement: 'top',
        trigger: 'hover',
        container: 'body',
        html: true
    });
}

// Remove schedule handler
$(document).off('click', '.remove-schedule-btn').on('click', '.remove-schedule-btn', function(e) {
    e.preventDefault();
    const scheduleId = $(this).data('schedule-id');
    if (!scheduleId || !confirm('Remove this schedule entry?')) return;

    const btn = $(this);
    btn.prop('disabled', true);

    $.ajax({
        url: medicationChartRemoveScheduleRoute,
        type: 'POST',
        data: { schedule_id: scheduleId, _token: CSRF_TOKEN },
        success: function(response) {
            if (response.success) {
                toastr.success('Schedule removed.');
                if (selectedMedication) {
                    loadMedicationCalendarWithDateRange(selectedMedication, $('#med-start-date').val(), $('#med-end-date').val());
                }
            } else {
                toastr.error(response.message || 'Failed to remove schedule.');
                btn.prop('disabled', false);
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to remove schedule.');
            btn.prop('disabled', false);
        }
    });
});

// Set schedule button
$(document).on('click', '#set-schedule-btn', function() {
    if (!selectedMedication) return;

    // Detect if this is a direct entry
    var $selectedOpt = $('#drug-select').find('option:selected');
    var isDirect = $selectedOpt.data('is-direct') || false;

    if (isDirect) {
        // Direct entry: populate drug_source, product_id/external_drug_name
        var drugSource = $selectedOpt.data('drug-source') || '';
        var productId = $selectedOpt.data('product-id') || '';
        var externalDrugName = $selectedOpt.data('external-drug-name') || '';

        $('#schedule_medication_id').val(''); // No POSR for direct entries
        $('#schedule_drug_source').val(drugSource);
        $('#schedule_product_id').val(productId);
        $('#schedule_external_drug_name').val(externalDrugName);
    } else {
        // Standard POSR medication
        $('#schedule_medication_id').val(selectedMedication);
        $('#schedule_drug_source').val('pharmacy_dispensed');
        $('#schedule_product_id').val('');
        $('#schedule_external_drug_name').val('');
    }

    $('#schedule_date').val(new Date().toISOString().split('T')[0]);
    $('#setScheduleModal').modal('show');
});

// Set schedule form submit
$(document).on('submit', '#setScheduleForm', function(e) {
    e.preventDefault();

    const formData = $(this).serialize();

    $.ajax({
        url: medicationChartScheduleRoute,
        type: 'POST',
        data: formData + '&_token=' + CSRF_TOKEN,
        success: function(response) {
            if (response.success) {
                toastr.success('Schedule created successfully.');
                $('#setScheduleModal').modal('hide');
                if (selectedMedication) {
                    loadMedicationCalendarWithDateRange(selectedMedication, $('#med-start-date').val(), $('#med-end-date').val());
                }
            } else {
                toastr.error(response.message || 'Failed to create schedule.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to create schedule.');
        }
    });
});

// Discontinue button
$(document).on('click', '#discontinue-btn', function() {
    if (!selectedMedication) return;
    $('#discontinue_medication_id').val(selectedMedication);
    const discRx = $('#drug-select').find('option:selected').data('rx');
    const discName = discRx?.product_name || 'Medication';
    $('#discontinue-medication-name').text(discName);
    $('#discontinueModal').modal('show');
});

// Discontinue form submit
$(document).on('submit', '#discontinueForm', function(e) {
    e.preventDefault();

    const btn = $('#discontinueSubmitBtn');
    btn.prop('disabled', true).find('.spinner-border').removeClass('d-none');

    $.ajax({
        url: medicationChartDiscontinueRoute,
        type: 'POST',
        data: $(this).serialize() + '&_token=' + CSRF_TOKEN,
        success: function(response) {
            if (response.success) {
                toastr.success('Medication discontinued.');
                $('#discontinueModal').modal('hide');
                if (selectedMedication) {
                    loadMedicationCalendarWithDateRange(selectedMedication, $('#med-start-date').val(), $('#med-end-date').val());
                }
            } else {
                toastr.error(response.message || 'Failed to discontinue.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to discontinue.');
        },
        complete: function() {
            btn.prop('disabled', false).find('.spinner-border').addClass('d-none');
        }
    });
});

// Resume button
$(document).on('click', '#resume-btn', function() {
    if (!selectedMedication) return;
    $('#resume_medication_id').val(selectedMedication);
    const resRx = $('#drug-select').find('option:selected').data('rx');
    const resName = resRx?.product_name || 'Medication';
    $('#resume-medication-name').text(resName);
    $('#resumeModal').modal('show');
});

// Resume form submit
$(document).on('submit', '#resumeForm', function(e) {
    e.preventDefault();

    const btn = $('#resumeSubmitBtn');
    btn.prop('disabled', true).find('.spinner-border').removeClass('d-none');

    $.ajax({
        url: medicationChartResumeRoute,
        type: 'POST',
        data: $(this).serialize() + '&_token=' + CSRF_TOKEN,
        success: function(response) {
            if (response.success) {
                toastr.success('Medication resumed.');
                $('#resumeModal').modal('hide');
                if (selectedMedication) {
                    loadMedicationCalendarWithDateRange(selectedMedication, $('#med-start-date').val(), $('#med-end-date').val());
                }
            } else {
                toastr.error(response.message || 'Failed to resume.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to resume.');
        },
        complete: function() {
            btn.prop('disabled', false).find('.spinner-border').addClass('d-none');
        }
    });
});

// Store stock data cache for medication administration
var administerStockData = null;

// Administer modal population — §6.5: enriched with _rxLookup data
$(document).on('click', '[data-bs-target="#administerModal"]', function() {
    const scheduleId = $(this).data('schedule-id');
    const schedule = currentSchedules.find(s => s.id == scheduleId);

    if (schedule) {
        $('#administer_schedule_id').val(scheduleId);

        // Determine drug source from schedule
        var scheduleDrugSource = schedule.drug_source || 'pharmacy_dispensed';
        var isDirect = (scheduleDrugSource === 'ward_stock' || scheduleDrugSource === 'patient_own');
        var productId = '';

        // Reset all conditional sections
        $('#administer-ward-stock-section').addClass('d-none');
        $('#administer-patient-own-section').addClass('d-none');
        $('#administer-pharmacy-qty-section').addClass('d-none');
        $('#administer-stock-info').addClass('d-none');
        $('#administer-stock-warning').addClass('d-none');
        administerStockData = null;

        if (isDirect) {
            // Direct entry schedule: use schedule data directly
            productId = schedule.product_id || '';
            var drugName = schedule.external_drug_name || 'Direct Entry';

            // Try to get product name from the selected dropdown option
            var $selectedOpt = $('#drug-select').find('option:selected');
            var directEntry = $selectedOpt.data('direct-entry');
            if (directEntry) {
                drugName = directEntry.product_name || directEntry.external_drug_name || drugName;
            }

            $('#administer_product_id').val(productId);
            $('#administer_product_request_id').val('');
            $('#administer-medication-info').html('<i class="mdi mdi-pill"></i> ' + drugName);
            setDrugSource(scheduleDrugSource);

            // Set the external_drug_name hidden field for patient_own
            $('#administer_external_drug_name').val(schedule.external_drug_name || '');

            // Update source badge
            if (scheduleDrugSource === 'ward_stock') {
                $('#administer-source-badge').html(
                    '<span class="badge bg-warning text-dark"><i class="mdi mdi-hospital-box"></i> Ward Stock</span>' +
                    '<small class="text-muted ms-2">Stock will be deducted from selected store</small>'
                );
                // Show ward stock section + load stores
                $('#administer-ward-stock-section').removeClass('d-none');
                loadAdministerStores(productId);
            } else {
                $('#administer-source-badge').html(
                    '<span class="badge bg-info"><i class="mdi mdi-account-heart"></i> Patient\'s Own</span>' +
                    '<small class="text-muted ms-2">Patient-supplied medication</small>'
                );
                // Show patient's own section
                $('#administer-patient-own-section').removeClass('d-none');
            }
        } else {
            // §6.5: Pharmacy dispensed — use enriched _rxLookup
            var posrId = selectedMedication;
            var rx = window._rxLookup ? window._rxLookup[posrId] : null;

            if (rx) {
                productId = rx.product_id || '';
                var productName = rx.product_name || 'N/A';
                $('#administer_product_id').val(productId);
                $('#administer_product_request_id').val(rx.product_request_id || '');
                $('#administer-medication-info').html('<i class="mdi mdi-pill"></i> ' + productName);
            } else {
                var fallbackRx = window._rxLookup ? window._rxLookup[selectedMedication] : null;
                productId = fallbackRx ? (fallbackRx.product_id || '') : '';
                var productName = fallbackRx ? (fallbackRx.product_name || 'N/A') : 'N/A';
                $('#administer_product_id').val(productId);
                $('#administer-medication-info').html('<i class="mdi mdi-pill"></i> ' + productName);
            }

            setDrugSource('pharmacy_dispensed');
            $('#administer_external_drug_name').val('');

            // Restore pharmacy dispensed badge
            $('#administer-source-badge').html(
                '<span class="badge bg-success"><i class="mdi mdi-pill"></i> Pharmacy Dispensed</span>' +
                '<small class="text-muted ms-2">Source is determined by the selected medication</small>'
            );

            // Show pharmacy qty section and populate remaining info
            $('#administer-pharmacy-qty-section').removeClass('d-none');
            $('#administer_pharmacy_qty').val(1);
            if (rx) {
                var qtyAdmin = rx.qty_administered || 0;
                var remaining = rx.remaining_doses || 0;
                $('#administer-remaining-info').html(
                    '<i class="mdi mdi-pill"></i> Prescribed: <strong>' + (rx.qty_prescribed || 0) + '</strong>' +
                    ' &nbsp;|&nbsp; Administered: <strong>' + qtyAdmin + '</strong>' +
                    ' &nbsp;|&nbsp; Remaining: <strong class="' + (remaining <= 0 ? 'text-danger' : 'text-success') + '">' + remaining + '</strong>'
                );
            } else {
                $('#administer-remaining-info').html('');
            }
        }

        const scheduledTime = new Date(schedule.scheduled_time);
        $('#administer-scheduled-time').html('<i class="mdi mdi-clock-outline"></i> Scheduled: ' + formatDateTime(scheduledTime));
        $('#administered_at').val(new Date().toISOString().slice(0, 16));
        $('#administered_dose').val(schedule.dose);
        $('#administered_route').val(schedule.route);
        $('#administered_note').val('');
        $('#administer_store_id').val('');
        $('#administer_qty').val(1);
        $('#administer_external_qty').val(1);

        // Show scheduled/administered counts
        var counts = window._currentMedCounts || { totalScheduled: 0, totalAdministered: 0 };
        $('#administer-counts-info').html(
            '<i class="mdi mdi-calendar-clock"></i> ' + counts.totalScheduled + ' scheduled &nbsp;|&nbsp; ' +
            '<i class="mdi mdi-check-circle"></i> ' + counts.totalAdministered + ' administered'
        );

        // Explicitly show modal (data-bs-toggle is used for tooltip, not modal)
        $('#administerModal').modal('show');
    }
});

// Load stores into the administer modal's ward stock store select
// Uses the session-resolved store — the same one displayed at the workbench banner.
function loadAdministerStores(productId) {
    if (window.BILLING_KIT_CONFIG?.resolvedStoreId) {
        var $select = $('#administer_store_id');
        $select.empty();
        $select.append('<option value="' + (window.BILLING_KIT_CONFIG?.resolvedStoreId || '') + '" selected>' + (window.BILLING_KIT_CONFIG?.resolvedStoreName || '') + '</option>');
        $select.trigger('change');
    }
}

// Store selection change in administer modal - show stock for selected store
$(document).on('change', '#administer_store_id', function() {
    const storeId = $(this).val();
    const productId = $('#administer_product_id').val();
    const $stockInfo = $('#administer-stock-info');
    const $stockQty = $('#administer-stock-qty');
    const $stockWarning = $('#administer-stock-warning');

    if (!storeId) {
        $stockInfo.addClass('d-none');
        $stockWarning.addClass('d-none');
        return;
    }

    $stockInfo.removeClass('d-none');
    $stockQty.removeClass('bg-success bg-warning bg-danger').addClass('bg-secondary').text('Loading...');

    // Fetch stock for this product/store combination
    if (productId && storeId) {
        $.ajax({
            url: wbUrl('/nursing-workbench/product-batches'),
            method: 'GET',
            data: { product_id: productId, store_id: storeId },
            success: function(response) {
                if (response.success) {
                    const totalAvailable = response.total_available || 0;
                    $stockQty.text(totalAvailable + ' units');
                    if (totalAvailable <= 0) {
                        $stockQty.removeClass('bg-secondary bg-success bg-warning').addClass('bg-danger');
                        $stockWarning.removeClass('d-none');
                        $('#administer-stock-warning-text').text('No stock available in this store!');
                    } else if (totalAvailable < 5) {
                        $stockQty.removeClass('bg-secondary bg-success bg-danger').addClass('bg-warning');
                        $stockWarning.addClass('d-none');
                    } else {
                        $stockQty.removeClass('bg-secondary bg-warning bg-danger').addClass('bg-success');
                        $stockWarning.addClass('d-none');
                    }
                    administerStockData = { stores: [{ store_id: storeId, quantity: totalAvailable }] };
                } else {
                    $stockQty.text('0 units').removeClass('bg-secondary bg-success bg-warning').addClass('bg-danger');
                    $stockWarning.removeClass('d-none');
                    $('#administer-stock-warning-text').text('Failed to check stock');
                }
            },
            error: function() {
                $stockQty.text('Error').removeClass('bg-secondary bg-success bg-warning').addClass('bg-danger');
                $stockWarning.removeClass('d-none');
                $('#administer-stock-warning-text').text('Failed to check stock availability');
            }
        });
    }
});

// Administer form submit — routes to correct endpoint based on drug source
$(document).on('submit', '#administerForm', function(e) {
    e.preventDefault();

    const drugSource = $('#administer_drug_source').val() || 'pharmacy_dispensed';
    const isDirect = (drugSource === 'ward_stock' || drugSource === 'patient_own');

    // ── Direct entry (ward_stock / patient_own): submit to administerDirect ──
    if (isDirect) {
        // Validate required fields per source
        if (drugSource === 'ward_stock') {
            var storeId = $('#administer_store_id').val();
            if (!storeId) {
                toastr.error('Please select a dispensing store');
                $('#administer_store_id').focus();
                return;
            }
            var qty = parseInt($('#administer_qty').val()) || 0;
            if (qty < 1) {
                toastr.error('Quantity must be at least 1');
                $('#administer_qty').focus();
                return;
            }
        } else if (drugSource === 'patient_own') {
            var extQty = parseFloat($('#administer_external_qty').val()) || 0;
            if (extQty <= 0) {
                toastr.error('Quantity must be greater than 0');
                $('#administer_external_qty').focus();
                return;
            }
        }

        var $btn = $('#administerSubmitBtn');
        var originalBtnHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Administering...');

        var directUrl = medicationChartAdministerDirectRoute.replace(':patient', PATIENT_ID);

        // Build form data — collect what administerDirect expects
        var formData = {
            _token: CSRF_TOKEN,
            drug_source: drugSource,
            schedule_id: $('#administer_schedule_id').val(),
            administered_at: $('#administered_at').val(),
            administered_dose: $('#administered_dose').val(),
            route: $('#administered_route').val(),
            note: $('#administered_note').val() || ''
        };

        if (drugSource === 'ward_stock') {
            formData.product_id = $('#administer_product_id').val();
            formData.store_id = $('#administer_store_id').val();
            formData.qty = $('#administer_qty').val();
        } else {
            formData.external_drug_name = $('#administer_external_drug_name').val();
            formData.external_qty = $('#administer_external_qty').val();
        }

        $.ajax({
            url: directUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                $btn.prop('disabled', false).html(originalBtnHtml);
                if (response.success) {
                    toastr.success(response.message || 'Medication administered successfully.');
                    try { bootstrap.Modal.getOrCreateInstance('#administerModal').hide(); } catch(e) {}
                    if (selectedMedication) {
                        loadMedicationCalendarWithDateRange(selectedMedication, $('#med-start-date').val(), $('#med-end-date').val());
                    }
                } else {
                    toastr.error(response.message || 'Failed to administer.');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html(originalBtnHtml);
                var msg = xhr.responseJSON?.message || 'Failed to administer.';
                if (xhr.responseJSON?.errors) {
                    msg = Object.values(xhr.responseJSON.errors).flat().join(', ');
                }
                toastr.error(msg);
            }
        });
        return; // Don't fall through to pharmacy_dispensed flow
    }

    // ── Pharmacy dispensed: standard flow (no store needed — stock from dispensed POSR) ──
    var $btn = $('#administerSubmitBtn');
    var originalBtnHtml = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Administering...');

    // Validate pharmacy qty
    var pharmacyQty = parseFloat($('#administer_pharmacy_qty').val()) || 0;
    if (pharmacyQty <= 0) {
        toastr.error('Quantity must be greater than 0');
        $btn.prop('disabled', false).html(originalBtnHtml);
        $('#administer_pharmacy_qty').focus();
        return;
    }

    $.ajax({
        url: medicationChartAdministerRoute,
        type: 'POST',
        data: $('#administerForm').serialize() + '&qty=' + pharmacyQty + '&_token=' + CSRF_TOKEN,
        success: function(response) {
            $btn.prop('disabled', false).html(originalBtnHtml);
            if (response.success) {
                toastr.success('Medication administered successfully.');
                try { bootstrap.Modal.getOrCreateInstance('#administerModal').hide(); } catch(e) {}
                if (selectedMedication) {
                    loadMedicationCalendarWithDateRange(selectedMedication, $('#med-start-date').val(), $('#med-end-date').val());
                }
            } else {
                toastr.error(response.message || 'Failed to administer.');
            }
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html(originalBtnHtml);
            var msg = xhr.responseJSON?.message || 'Failed to administer.';
            if (xhr.responseJSON?.errors) {
                msg = Object.values(xhr.responseJSON.errors).flat().join(', ');
            }
            toastr.error(msg);
        }
    });
});

// Administration Details Modal - show details when clicking administered badge
$(document).on('click', '[data-bs-target="#adminDetailsModal"]', function() {
    const adminId = $(this).data('admin-id');
    const admin = currentAdministrations.find(a => a.id == adminId);

    if (admin) {
        // Get medication name from dropdown option data (works for both POSR and direct entries)
        let productName = 'Medication';
        const $detailsSelectedOpt = $('#drug-select').find('option:selected');
        const detailsDirectEntry = $detailsSelectedOpt.data('direct-entry');
        if (detailsDirectEntry) {
            productName = detailsDirectEntry.product_name || detailsDirectEntry.external_drug_name || 'Direct Entry';
        } else {
            const detailsRx = $detailsSelectedOpt.data('rx');
            if (detailsRx) {
                productName = detailsRx.product_name || 'Medication';
            }
        }
        const adminTime = new Date(admin.administered_at);

        let detailsHtml = `
            <div class="mb-3">
                <h6 class="text-primary fw-bold"><i class="mdi mdi-pill"></i> ${productName}</h6>
                <small class="text-muted">
                    <i class="mdi mdi-calendar-clock"></i> ${(window._currentMedCounts || {}).totalScheduled || 0} scheduled
                    &nbsp;|&nbsp;
                    <i class="mdi mdi-check-circle"></i> ${(window._currentMedCounts || {}).totalAdministered || 0} administered
                </small>
            </div>
            <table class="table table-sm table-borderless">
                <tr>
                    <td class="text-muted" width="40%"><i class="mdi mdi-clock-outline"></i> Administered At:</td>
                    <td class="fw-bold">${formatDateTime(adminTime)}</td>
                </tr>
                <tr>
                    <td class="text-muted"><i class="mdi mdi-medical-bag"></i> Dose:</td>
                    <td class="fw-bold">${admin.dose || '-'}</td>
                </tr>
                <tr>
                    <td class="text-muted"><i class="mdi mdi-pill"></i> Qty:</td>
                    <td class="fw-bold">${admin.qty || admin.external_qty || 1}</td>
                </tr>
                <tr>
                    <td class="text-muted"><i class="mdi mdi-routes"></i> Route:</td>
                    <td class="fw-bold">${admin.route || '-'}</td>
                </tr>
                <tr>
                    <td class="text-muted"><i class="mdi mdi-account"></i> Administered By:</td>
                    <td class="fw-bold">${admin.administered_by_name || admin.administeredBy?.name || 'Unknown'}</td>
                </tr>`;

        if (admin.store_name) {
            detailsHtml += `
                <tr>
                    <td class="text-muted"><i class="mdi mdi-store"></i> Dispensed From:</td>
                    <td class="fw-bold">${admin.store_name}</td>
                </tr>`;
        }

        if (admin.drug_source && admin.drug_source !== 'pharmacy_dispensed') {
            var sourceBadge = admin.drug_source === 'ward_stock'
                ? '<span class="badge bg-warning text-dark">Ward Stock</span>'
                : '<span class="badge bg-info">Patient\'s Own</span>';
            detailsHtml += `
                <tr>
                    <td class="text-muted"><i class="mdi mdi-tag"></i> Source:</td>
                    <td>${sourceBadge}</td>
                </tr>`;
        }

        if (admin.comment) {
            detailsHtml += `
                <tr>
                    <td class="text-muted"><i class="mdi mdi-note-text"></i> Notes:</td>
                    <td>${admin.comment}</td>
                </tr>`;
        }

        if (admin.edited_at) {
            detailsHtml += `
                <tr class="table-info">
                    <td class="text-muted"><i class="mdi mdi-pencil"></i> Edited At:</td>
                    <td>${formatDateTime(new Date(admin.edited_at))}</td>
                </tr>
                <tr class="table-info">
                    <td class="text-muted"><i class="mdi mdi-account-edit"></i> Edited By:</td>
                    <td>${admin.edited_by_name || admin.editedBy?.name || '-'}</td>
                </tr>`;
        }

        if (admin.deleted_at) {
            detailsHtml += `
                <tr class="table-danger">
                    <td class="text-muted"><i class="mdi mdi-delete"></i> Deleted At:</td>
                    <td>${formatDateTime(new Date(admin.deleted_at))}</td>
                </tr>
                <tr class="table-danger">
                    <td class="text-muted"><i class="mdi mdi-account-remove"></i> Deleted By:</td>
                    <td>${admin.deleted_by_name || admin.deletedBy?.name || '-'}</td>
                </tr>`;
        }

        detailsHtml += '</table>';

        $('#admin-details-content').html(detailsHtml);

        // Store admin id for edit/delete buttons
        $('#edit-admin-btn').data('admin-id', adminId);
        $('#delete-admin-btn').data('admin-id', adminId);

        // Show/hide edit/delete buttons based on status
        if (admin.deleted_at) {
            $('#edit-admin-btn, #delete-admin-btn').hide();
        } else {
            $('#edit-admin-btn, #delete-admin-btn').show();
        }

        // Explicitly show modal (data-bs-toggle is used for tooltip, not modal)
        $('#adminDetailsModal').modal('show');
    } else {
        $('#admin-details-content').html('<div class="alert alert-warning">Administration details not found.</div>');
    }
});

// Edit administration button handler
$(document).on('click', '#edit-admin-btn', function() {
    const adminId = $(this).data('admin-id');
    const admin = currentAdministrations.find(a => a.id == adminId);

    if (admin) {
        // Close details modal and open edit modal
        $('#adminDetailsModal').modal('hide');

        // Populate edit form
        $('#edit_admin_id').val(adminId);
        $('#edit_administered_at').val(admin.administered_at.replace(' ', 'T').slice(0, 16));
        $('#edit_dose').val(admin.dose);
        $('#edit_route').val(admin.route);
        $('#edit_comment').val(admin.comment || '');
        $('#edit_reason').val('');

        $('#editAdminModal').modal('show');
    }
});

// Delete administration button handler
$(document).on('click', '#delete-admin-btn', function() {
    const adminId = $(this).data('admin-id');

    if (adminId) {
        // Close details modal and open delete modal
        $('#adminDetailsModal').modal('hide');

        $('#delete_admin_id').val(adminId);
        $('#delete_reason').val('');

        $('#deleteAdminModal').modal('show');
    }
});

// Edit Administration Form Submit
$(document).on('submit', '#editAdminForm', function(e) {
    e.preventDefault();

    const $btn = $('#editAdminSubmitBtn');
    const originalBtnHtml = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');

    $.ajax({
        url: medicationChartEditRoute,
        type: 'POST',
        data: $(this).serialize() + '&_token=' + CSRF_TOKEN,
        success: function(response) {
            $btn.prop('disabled', false).html(originalBtnHtml);
            if (response.success) {
                toastr.success('Administration updated successfully.');
                $('#editAdminModal').modal('hide');
                if (selectedMedication) {
                    loadMedicationCalendarWithDateRange(selectedMedication, $('#med-start-date').val(), $('#med-end-date').val());
                }
            } else {
                toastr.error(response.message || 'Failed to update administration.');
            }
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html(originalBtnHtml);
            toastr.error(xhr.responseJSON?.message || 'Failed to update administration.');
        }
    });
});

// Delete Administration Form Submit
$(document).on('submit', '#deleteAdminForm', function(e) {
    e.preventDefault();

    const $btn = $('#deleteAdminSubmitBtn');
    const originalBtnHtml = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Deleting...');

    $.ajax({
        url: medicationChartDeleteRoute,
        type: 'POST',
        data: $(this).serialize() + '&_token=' + CSRF_TOKEN,
        success: function(response) {
            $btn.prop('disabled', false).html(originalBtnHtml);
            if (response.success) {
                toastr.success('Administration deleted successfully.');
                $('#deleteAdminModal').modal('hide');
                if (selectedMedication) {
                    loadMedicationCalendarWithDateRange(selectedMedication, $('#med-start-date').val(), $('#med-end-date').val());
                }
            } else {
                toastr.error(response.message || 'Failed to delete administration.');
            }
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html(originalBtnHtml);
            toastr.error(xhr.responseJSON?.message || 'Failed to delete administration.');
        }
    });
});

// Repeat type toggle for days selector
$(document).on('change', 'input[name="repeat_type"]', function() {
    if ($(this).val() === 'selected') {
        $('#days-selector').show();
    } else {
        $('#days-selector').hide();
    }
});

// =============================================
// §6.2–6.4: WARD STOCK & PATIENT'S OWN — DIRECT ADMINISTRATION
// =============================================

// ── Button click handlers ───────────────────────────────────
$('#btn-add-patient-own').on('click', function() {
    // Reset form
    $('#patientOwnForm')[0].reset();
    setCurrentDateTime('po_administered_at');
    var modal = new bootstrap.Modal(document.getElementById('patientOwnModal'));
    modal.show();
});

$('#btn-add-ward-stock').on('click', function() {
    // Reset form
    $('#wardStockForm')[0].reset();
    $('#ws_product_id').val('');
    $('#ws_product_info').hide();
    $('#ws_product_results').hide();
    $('#ws_product_search').val('');
    setCurrentDateTime('ws_administered_at');

    // Load stores
    loadWardStores();

    var modal = new bootstrap.Modal(document.getElementById('wardStockModal'));
    modal.show();
});

// ── Patient's Own Modal Submit ──────────────────────────────
$('#patientOwnForm').on('submit', function(e) {
    e.preventDefault();

    var $btn = $('#patientOwnSubmitBtn');
    var $spinner = $btn.find('.spinner-border');
    $btn.prop('disabled', true);
    $spinner.removeClass('d-none');

    var url = medicationChartAdministerDirectRoute.replace(':patient', PATIENT_ID);
    var formData = {
        drug_source: 'patient_own',
        external_drug_name: $('#po_drug_name').val(),
        external_qty: $('#po_qty').val(),
        external_batch_number: $('#po_batch').val(),
        external_expiry_date: $('#po_expiry').val(),
        external_source_note: $('#po_source_note').val(),
        administered_dose: $('#po_dose').val(),
        route: $('#po_route').val(),
        administered_at: $('#po_administered_at').val(),
        note: $('#po_comment').val()
    };

    $.ajax({
        url: url,
        type: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
        data: formData,
        success: function(resp) {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
            toastr.success(resp.message || 'Patient\'s own drug administered successfully');
            try {
                var modalEl = document.getElementById('patientOwnModal');
                var modalInst = bootstrap.Modal.getInstance(modalEl) || bootstrap.Modal.getOrCreateInstance(modalEl);
                modalInst.hide();
            } catch(e) { $('#patientOwnModal').modal('hide'); }
            // Reload medication list to show the new entry
            if (typeof loadMedicationsList === 'function') loadMedicationsList();
        },
        error: function(xhr) {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
            var msg = 'Failed to administer';
            if (xhr.responseJSON) {
                if (xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    msg = Object.values(errors).flat().join('<br>');
                } else if (xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
            }
            toastr.error(msg);
        }
    });
});

// ── Ward Stock: Load stores ─────────────────────────────────
function loadWardStores() {
    $.ajax({
        url: wbUrl('pharmacy-workbench/stores'),
        type: 'GET',
        success: function(stores) {
            var $select = $('#ws_store');
            $select.find('option:not(:first)').remove();
            stores.forEach(function(store) {
                $select.append('<option value="' + store.id + '">' + store.store_name + (store.location ? ' (' + store.location + ')' : '') + '</option>');
            });
        },
        error: function() {
            toastr.error('Failed to load stores');
        }
    });
}

// ── Ward Stock: Product search ──────────────────────────────
var wsSearchTimeout;
$('#ws_product_search').on('input', function() {
    var query = $(this).val();
    clearTimeout(wsSearchTimeout);

    if (query.length < 2) {
        $('#ws_product_results').hide();
        return;
    }

    wsSearchTimeout = setTimeout(function() {
        $.ajax({
            url: wbUrl('live-search-products'),
            method: 'GET',
            dataType: 'json',
            data: { term: query, patient_id: PATIENT_ID },
            success: function(data) {
                var $results = $('#ws_product_results');
                $results.html('');

                if (!data || data.length === 0) {
                    $results.html('<li class="list-group-item text-muted">No products found</li>').show();
                    return;
                }

                data.forEach(function(item) {
                    var name = item.product_name || 'Unknown';
                    var code = item.product_code || '';
                    var qty = (item.stock && item.stock.current_quantity !== undefined) ? item.stock.current_quantity : 0;
                    var price = (item.price && item.price.current_sale_price !== undefined) ? item.price.current_sale_price : 0;
                    var qtyClass = qty> 0 ? 'text-success' : 'text-danger';

                    var li = '<li class="list-group-item list-group-item-action" style="cursor:pointer;" ' +
                        'data-id="' + item.id + '" ' +
                        'data-name="' + name + '" ' +
                        'data-code="' + code + '" ' +
                        'data-qty="' + qty + '" ' +
                        'data-price="' + price + '">' +
                        '<div class="d-flex justify-content-between">' +
                        '<div><strong>' + name + '</strong> <small class="text-muted">[' + code + ']</small></div>' +
                        '<div class="text-end"><span class="' + qtyClass + '"><strong>' + qty + '</strong> avail.</span><br><small>₦' + Number(price).toLocaleString() + '</small></div>' +
                        '</div></li>';
                    $results.append(li);
                });
                $results.show();
            },
            error: function() {
                $('#ws_product_results').html('<li class="list-group-item text-danger">Search failed</li>').show();
            }
        });
    }, 300);
});

// ── Ward Stock: Select product from search results ──────────
$(document).on('click', '#ws_product_results li[data-id]', function() {
    var id = $(this).data('id');
    var name = $(this).data('name');
    var code = $(this).data('code');
    var qty = $(this).data('qty');
    var price = $(this).data('price');

    $('#ws_product_id').val(id);
    $('#ws_product_search').val(name);
    $('#ws_product_name').text(name);
    $('#ws_product_code').text('[' + code + ']');
    $('#ws_product_price').text('₦' + Number(price).toLocaleString());
    $('#ws_product_results').hide();

    // Show stock for the selected store
    var storeId = $('#ws_store').val();
    if (storeId) {
        updateWsStockDisplay(id, storeId);
    } else {
        $('#ws_available_stock').text(qty + ' global stock').removeClass('bg-success bg-danger').addClass('bg-info');
    }

    $('#ws_product_info').slideDown(200);
});

// ── Ward Stock: Update stock when store changes ─────────────
$('#ws_store').on('change', function() {
    var productId = $('#ws_product_id').val();
    var storeId = $(this).val();
    if (productId && storeId) {
        updateWsStockDisplay(productId, storeId);
    }
});

function updateWsStockDisplay(productId, storeId) {
    $.ajax({
        url: wbUrl('/pharmacy-workbench/product/' + productId + '/stock'),
        method: 'GET',
        success: function(resp) {
            var storeStock = (resp.stores || []).find(function(s) { return s.store_id == storeId; });
            var available = storeStock ? storeStock.quantity : 0;
            var badge = $('#ws_available_stock');
            badge.text(available + ' in store');
            if (available> 0) {
                badge.removeClass('bg-danger bg-info').addClass('bg-success');
            } else {
                badge.removeClass('bg-success bg-info').addClass('bg-danger');
            }
        },
        error: function() {
            $('#ws_available_stock').text('? stock').removeClass('bg-success bg-danger').addClass('bg-warning');
        }
    });
}

// Hide product results when clicking outside
$(document).on('click', function(e) {
    if (!$(e.target).closest('#ws_product_search, #ws_product_results').length) {
        $('#ws_product_results').hide();
    }
});

// ── Ward Stock Modal Submit ─────────────────────────────────
$('#wardStockForm').on('submit', function(e) {
    e.preventDefault();

    var productId = $('#ws_product_id').val();
    if (!productId) {
        toastr.warning('Please search and select a product');
        $('#ws_product_search').focus();
        return;
    }

    var storeId = $('#ws_store').val();
    if (!storeId) {
        toastr.warning('Please select a ward/store');
        $('#ws_store').focus();
        return;
    }

    var $btn = $('#wardStockSubmitBtn');
    var $spinner = $btn.find('.spinner-border');
    $btn.prop('disabled', true);
    $spinner.removeClass('d-none');

    var url = medicationChartAdministerDirectRoute.replace(':patient', PATIENT_ID);
    var formData = {
        drug_source: 'ward_stock',
        product_id: productId,
        store_id: storeId,
        qty: $('#ws_qty').val(),
        administered_dose: $('#ws_dose').val(),
        route: $('#ws_route').val(),
        administered_at: $('#ws_administered_at').val(),
        note: $('#ws_comment').val(),
        bill_patient: $('#ws_bill_patient').is(':checked') ? 1 : 0
    };

    $.ajax({
        url: url,
        type: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
        data: formData,
        success: function(resp) {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
            var msg = resp.message || 'Ward stock drug administered successfully';
            if (formData.bill_patient) {
                msg += ' (billed)';
            }
            toastr.success(msg);
            try {
                var modalEl = document.getElementById('wardStockModal');
                var modalInst = bootstrap.Modal.getInstance(modalEl) || bootstrap.Modal.getOrCreateInstance(modalEl);
                modalInst.hide();
            } catch(e) { $('#wardStockModal').modal('hide'); }
            // Reload to show new entry
            if (typeof loadMedicationsList === 'function') loadMedicationsList();
        },
        error: function(xhr) {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
            var msg = 'Failed to administer';
            if (xhr.responseJSON) {
                if (xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    msg = Object.values(errors).flat().join('<br>');
                } else if (xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
            }
            toastr.error(msg);
        }
    });
});

