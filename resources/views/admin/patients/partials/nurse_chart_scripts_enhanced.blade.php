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
    let patientPrescriptions = [];
    let patientPrescriptionsLoaded = false;

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
    loadPatientPrescriptions();

    function loadMedicationsList() {
        console.log('Loading medications list (enriched §6.1)...');
        $('#medication-loading').show();
        $('#medication-calendar').hide();

        if (PATIENT_ID === null || PATIENT_ID === 'null') {
            console.error('Patient ID is not set');
            $('#medication-loading').hide();
            toastr.error('Patient ID is not available. Please reload the page.');
            return;
        }

        // §6.1: Use prescribed-drugs API for enriched status data
        const prescribedUrl = medicationChartPrescribedRoute.replace(':patient', PATIENT_ID);

        $.ajax({
            url: prescribedUrl,
            type: 'GET',
            success: function(data) {
                console.log('Prescribed drugs loaded:', data);
                $('#medication-loading').hide();

                const prescriptions = data.prescriptions || [];

                // Store all prescriptions for reference
                window._rxLookup = {};
                prescriptions.forEach(rx => { window._rxLookup[rx.posr_id || rx.id] = rx; });

                // §6.1: Populate dropdown with rich, status-aware options
                const select = $('#drug-select');
                select.empty();
                select.append('<option value="">-- Select a medication --</option>');

                if (prescriptions.length === 0) {
                    toastr.warning('No medications found for this patient.');
                } else {
                    console.log(`Found ${prescriptions.length} prescriptions`);

                    prescriptions.forEach(function(rx) {
                        const posrId = rx.posr_id || '';
                        const canChart = rx.can_chart && posrId;

                        // §6.1: Status icon + color
                        let statusIcon, statusBadge;
                        switch (rx.status) {
                            case 3:
                                statusIcon = '🟢';
                                statusBadge = `<span class="badge bg-success">Dispensed</span>`;
                                break;
                            case 2:
                                if (rx.is_paid) {
                                    statusIcon = '🟡';
                                    statusBadge = `<span class="badge bg-warning text-dark">Awaiting Pharmacy</span>`;
                                } else {
                                    statusIcon = '🟠';
                                    statusBadge = `<span class="badge bg-secondary">${rx.status_label}</span>`;
                                }
                                break;
                            default:
                                statusIcon = '🔴';
                                statusBadge = `<span class="badge bg-danger">Awaiting Billing</span>`;
                        }

                        // Administered progress
                        const adminText = rx.is_dispensed
                            ? `Administered: ${rx.times_administered}/${rx.qty_prescribed}`
                            : '';

                        // Doctor info
                        const doctorText = rx.doctor_name ? `Dr. ${rx.doctor_name}` : '';

                        // Build display text (plain for <option>, rich for Select2)
                        const plainText = `${statusIcon} ${rx.product_name} (${rx.product_code}) — ${rx.status_label}`;

                        const opt = new Option(plainText, posrId || ('rx_' + rx.id), false, false);
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
                    const directEntries = data.direct_entries || [];
                    if (directEntries.length > 0) {
                        // Add separator
                        const separator = new Option('── Direct Administrations ──', '', false, false);
                        separator.disabled = true;
                        $(separator).data('is-separator', true);
                        select.append(separator);

                        directEntries.forEach(function(entry) {
                            const isPatientOwn = entry.drug_source === 'patient_own';
                            const icon = isPatientOwn ? '🟣' : '🔵';
                            const label = isPatientOwn ? "Patient's Own" : 'Ward Stock';
                            const drugName = entry.product_name || entry.external_drug_name || 'Unknown';
                            const codeStr = entry.product_code ? ` (${entry.product_code})` : '';
                            const plainText = `${icon} ${drugName}${codeStr} — ${label}`;

                            const optVal = 'direct_' + entry.drug_source + '_' + (entry.product_id || entry.external_drug_name || entry.id);
                            const opt = new Option(plainText, optVal, false, false);

                            // Store data for Select2 template and calendar loading
                            $(opt).data('direct-entry', entry);
                            $(opt).data('drug-source', entry.drug_source);
                            $(opt).data('product-id', entry.product_id || null);
                            $(opt).data('external-drug-name', entry.external_drug_name || null);
                            $(opt).data('status-icon', icon);
                            $(opt).data('is-direct', true);

                            select.append(opt);
                        });

                        console.log(`Added ${directEntries.length} direct administration entries to dropdown`);
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

                // Update Prescription Dashboard badge
                if (typeof loadPrescriptionDashboard === 'function') {
                    loadPrescriptionDashboard();
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to load medications:', status, error);
                $('#medication-loading').hide();
                toastr.error(`Failed to load medications: ${error}`);
            }
        });
    }

    // §6.1: Select2 template for dropdown results (rich format)
    function formatRxOption(option) {
        if (!option.id) return option.text; // placeholder

        const $opt = $(option.element);

        // Handle separator
        if ($opt.data('is-separator')) {
            return $(`<div class="text-muted fw-bold small py-1 border-top mt-1">${option.text}</div>`);
        }

        // Handle direct administration entries (ward stock / patient's own)
        const directEntry = $opt.data('direct-entry');
        if (directEntry) {
            const icon = $opt.data('status-icon') || '';
            const isPatientOwn = directEntry.drug_source === 'patient_own';
            const label = isPatientOwn ? "Patient's Own" : 'Ward Stock';
            const badgeClass = isPatientOwn ? 'bg-purple' : 'bg-info';
            const badgeHtml = `<span class="badge ${badgeClass}">${label}</span>`;
            const drugName = directEntry.product_name || directEntry.external_drug_name || 'Unknown';
            const codeStr = directEntry.product_code ? `(${directEntry.product_code})` : '';

            return $(`
                <div class="d-flex flex-column py-1">
                    <div class="d-flex align-items-center gap-2">
                        <span style="font-size:1.1em;">${icon}</span>
                        <strong>${drugName}</strong>
                        <small class="text-muted">${codeStr}</small>
                        ${badgeHtml}
                    </div>
                    <div class="d-flex gap-3 ms-4">
                        <small class="text-info">Administered: ${directEntry.times_administered}×</small>
                        <small class="text-muted">by ${directEntry.nurse_name}</small>
                    </div>
                </div>
            `);
        }

        // Handle pharmacy prescriptions
        const rx = $opt.data('rx');
        if (!rx) return option.text;

        const icon = $opt.data('status-icon') || '';
        const badge = $opt.data('status-badge') || '';
        const adminText = $opt.data('admin-text') || '';
        const doctorText = $opt.data('doctor-text') || '';
        const isDisabled = option.disabled;

        const $container = $(`
            <div class="d-flex flex-column py-1 ${isDisabled ? 'opacity-50' : ''}">
                <div class="d-flex align-items-center gap-2">
                    <span style="font-size:1.1em;">${icon}</span>
                    <strong>${rx.product_name}</strong>
                    <small class="text-muted">(${rx.product_code})</small>
                    ${badge}
                </div>
                <div class="d-flex gap-3 ms-4">
                    <small class="text-muted">Qty: ${rx.qty_prescribed}</small>
                    ${adminText ? `<small class="text-info">${adminText}</small>` : ''}
                    ${doctorText ? `<small class="text-muted">${doctorText}</small>` : ''}
                    ${rx.remaining_doses === 0 && rx.is_dispensed ? '<small class="text-success fw-bold">✓ Fully administered</small>' : ''}
                </div>
                ${isDisabled ? `<small class="text-danger ms-4"><i class="mdi mdi-lock"></i> ${rx.status_label} — cannot chart</small>` : ''}
            </div>
        `);

        return $container;
    }

    // §6.1: Select2 template for selected item (compact)
    function formatRxSelection(option) {
        if (!option.id) return option.text;

        const $opt = $(option.element);

        // Handle direct entry selections
        const directEntry = $opt.data('direct-entry');
        if (directEntry) {
            const icon = $opt.data('status-icon') || '';
            const drugName = directEntry.product_name || directEntry.external_drug_name || 'Unknown';
            const codeStr = directEntry.product_code ? `(${directEntry.product_code})` : '';
            return `${icon} ${drugName} ${codeStr}`;
        }

        const rx = $opt.data('rx');
        if (!rx) return option.text;

        const icon = $opt.data('status-icon') || '';
        return `${icon} ${rx.product_name} (${rx.product_code})`;
    }

    // =============================================
    // MEDICATION SELECTION AND CALENDAR LOADING
    // =============================================

    // Drug selection change
    $('#drug-select').change(function() {
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

        // Update date range summary
        updateDateRangeSummary();
    }

    // Update date range summary display
    function updateDateRangeSummary() {
        const startDate = $('#med-start-date').val();
        const endDate = $('#med-end-date').val();
        if (startDate && endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            const days = Math.round((end - start) / (1000 * 60 * 60 * 24)) + 1;
            $('#date-range-summary').html(`<i class="mdi mdi-calendar me-1"></i>${days} days`);
        }
    }

    // Initialize date range on page load
    initializeMedicationDateRange();

    // Reset date range button
    $('#reset-date-range-btn').on('click', function() {
        initializeMedicationDateRange();
        if (selectedMedication) {
            const startDateStr = $('#med-start-date').val();
            const endDateStr = $('#med-end-date').val();
            loadMedicationCalendarWithDateRange(selectedMedication, startDateStr, endDateStr);
        }
        // If on overview tab, reload overview
        if ($('#med-overview-tab').hasClass('active')) {
            loadOverviewCalendar();
        }
    });

    // Handle the apply date range button click
    $('#apply-date-range-btn').on('click', function() {
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

        // Update summary
        updateDateRangeSummary();

        // Check which tab is active
        if ($('#med-overview-tab').hasClass('active')) {
            // Load overview calendar
            loadOverviewCalendar();
        } else if (selectedMedication) {
            // Load entry calendar for selected medication
            calendarStartDate = startDate;
            loadMedicationCalendarWithDateRange(selectedMedication, startDateStr, endDateStr);
        } else {
            toastr.info('Please select a medication on the Entry tab, or switch to Overview to see all medications.');
        }
    });

    // =============================================
    // OVERVIEW TAB FUNCTIONALITY
    // =============================================

    // Medication colors for overview calendar
    const overviewMedColors = [
        { bg: '#e3f2fd', border: '#1976d2', text: '#0d47a1' },  // Blue
        { bg: '#e8f5e9', border: '#388e3c', text: '#1b5e20' },  // Green
        { bg: '#fff3e0', border: '#f57c00', text: '#e65100' },  // Orange
        { bg: '#f3e5f5', border: '#8e24aa', text: '#6a1b9a' },  // Purple
        { bg: '#e0f7fa', border: '#0097a7', text: '#006064' },  // Cyan
        { bg: '#fce4ec', border: '#c2185b', text: '#880e4f' },  // Pink
        { bg: '#fff8e1', border: '#ffa000', text: '#ff6f00' },  // Amber
        { bg: '#e8eaf6', border: '#3f51b5', text: '#1a237e' },  // Indigo
        { bg: '#efebe9', border: '#6d4c41', text: '#3e2723' },  // Brown
        { bg: '#eceff1', border: '#546e7a', text: '#263238' },  // Blue Grey
    ];

    // Overview tab shown event - load calendar when tab is shown
    $('#med-overview-tab').on('shown.bs.tab', function() {
        loadOverviewCalendar();
    });

    // Overview navigation buttons
    $('#overview-prev-btn').on('click', function() {
        const currentStart = new Date($('#med-start-date').val());
        const currentEnd = new Date($('#med-end-date').val());
        const range = Math.round((currentEnd - currentStart) / (1000 * 60 * 60 * 24));

        currentStart.setDate(currentStart.getDate() - range);
        currentEnd.setDate(currentEnd.getDate() - range);

        $('#med-start-date').val(currentStart.toISOString().split('T')[0]);
        $('#med-end-date').val(currentEnd.toISOString().split('T')[0]);
        updateDateRangeSummary();
        loadOverviewCalendar();
    });

    $('#overview-next-btn').on('click', function() {
        const currentStart = new Date($('#med-start-date').val());
        const currentEnd = new Date($('#med-end-date').val());
        const range = Math.round((currentEnd - currentStart) / (1000 * 60 * 60 * 24));

        currentStart.setDate(currentStart.getDate() + range);
        currentEnd.setDate(currentEnd.getDate() + range);

        $('#med-start-date').val(currentStart.toISOString().split('T')[0]);
        $('#med-end-date').val(currentEnd.toISOString().split('T')[0]);
        updateDateRangeSummary();
        loadOverviewCalendar();
    });

    $('#overview-today-btn').on('click', function() {
        initializeMedicationDateRange();
        loadOverviewCalendar();
    });

    // Load overview calendar data
    function loadOverviewCalendar() {
        let startDate = $('#med-start-date').val();
        let endDate = $('#med-end-date').val();

        if (!startDate || !endDate) {
            initializeMedicationDateRange();
            // Re-read values after initialization
            startDate = $('#med-start-date').val();
            endDate = $('#med-end-date').val();
        }

        $('#overview-loading').show();
        $('#unified-overview-container').html('');

        const url = medicationChartIndexRoute.replace(':patient', PATIENT_ID) +
                    '?start_date=' + encodeURIComponent(startDate) +
                    '&end_date=' + encodeURIComponent(endDate);

        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                $('#overview-loading').hide();
                renderOverviewCalendar(data, startDate, endDate);
                updateOverviewStats(data);
            },
            error: function(xhr, status, error) {
                $('#overview-loading').hide();
                $('#unified-overview-container').html(
                    '<div class="alert alert-danger">Failed to load medication data. Please try again.</div>'
                );
                console.error('Overview load error:', error);
            }
        });
    }

    // Update overview statistics
    function updateOverviewStats(data) {
        const prescriptions = data.prescriptions || [];
        const administrations = data.administrations || [];

        // Count total medications
        $('#stat-total-meds').text(prescriptions.length);

        // Count given doses (non-deleted administrations)
        const givenCount = administrations.filter(a => !a.deleted_at).length;
        $('#stat-given').text(givenCount);

        // Count scheduled (schedules without administrations)
        let scheduledCount = 0;
        let missedCount = 0;
        const now = new Date();

        prescriptions.forEach(p => {
            if (p.schedules) {
                p.schedules.forEach(s => {
                    const hasAdmin = administrations.some(a => a.schedule_id === s.id && !a.deleted_at);
                    if (!hasAdmin) {
                        const scheduleTime = new Date(s.scheduled_time);
                        if (scheduleTime < now) {
                            missedCount++;
                        } else {
                            scheduledCount++;
                        }
                    }
                });
            }
        });

        $('#stat-scheduled').text(scheduledCount);
        $('#stat-missed').text(missedCount);
    }

    // Render the unified overview calendar
    function renderOverviewCalendar(data, startDateStr, endDateStr) {
        const container = document.getElementById('unified-overview-container');

        if (!data.prescriptions || data.prescriptions.length === 0) {
            container.innerHTML = '<div class="alert alert-info">No medications found for this period.</div>';
            return;
        }

        // Build medication color map
        const medColorMap = {};
        data.prescriptions.forEach((p, idx) => {
            medColorMap[p.id] = overviewMedColors[idx % overviewMedColors.length];
        });

        // Parse dates
        const startDate = new Date(startDateStr);
        const endDate = new Date(endDateStr);
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        // Calculate first day of week for calendar alignment
        const firstCalendarDay = new Date(startDate);
        firstCalendarDay.setDate(firstCalendarDay.getDate() - firstCalendarDay.getDay());

        // Build administrations lookup by schedule_id
        const adminBySchedule = {};
        (data.administrations || []).forEach(admin => {
            if (!adminBySchedule[admin.schedule_id]) {
                adminBySchedule[admin.schedule_id] = [];
            }
            adminBySchedule[admin.schedule_id].push(admin);
        });

        // Build HTML
        let html = '';

        // Weekday header
        html += '<div class="calendar-weekday-header">';
        const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        weekdays.forEach(day => {
            html += `<div class="weekday-name">${day}</div>`;
        });
        html += '</div>';

        // Calendar grid
        html += '<div class="medication-calendar-grid">';

        let currentDate = new Date(firstCalendarDay);
        const lastCalendarDay = new Date(endDate);
        lastCalendarDay.setDate(lastCalendarDay.getDate() + (6 - lastCalendarDay.getDay()));

        while (currentDate <= lastCalendarDay) {
            const dateStr = currentDate.toISOString().split('T')[0];
            const dayOfWeek = currentDate.getDay();
            const isToday = currentDate.toDateString() === today.toDateString();
            const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;
            const isPast = currentDate < today;
            const isInRange = currentDate >= startDate && currentDate <= endDate;

            let cellClass = 'calendar-day-cell';
            if (!isInRange) cellClass += ' empty-day';
            if (isToday) cellClass += ' today';
            if (isWeekend && isInRange) cellClass += ' weekend';
            if (isPast && isInRange) cellClass += ' past-date';

            html += `<div class="${cellClass}">`;

            if (isInRange) {
                const dayName = weekdays[dayOfWeek];
                const dayNum = currentDate.getDate();

                html += `<div class="day-header">
                    <span class="day-name">${dayName}</span>
                    <span class="day-number">${dayNum}</span>
                </div>`;

                html += '<div class="schedule-items">';

                // Find all schedules for this date across all prescriptions
                data.prescriptions.forEach(prescription => {
                    const medName = extractMedicationName(prescription);
                    const color = medColorMap[prescription.id];

                    if (prescription.schedules) {
                        prescription.schedules.forEach(schedule => {
                            const scheduleDate = new Date(schedule.scheduled_time).toISOString().split('T')[0];

                            if (scheduleDate === dateStr) {
                                const scheduleTime = new Date(schedule.scheduled_time);
                                const timeStr = scheduleTime.toLocaleTimeString('en-US', {
                                    hour: 'numeric',
                                    minute: '2-digit',
                                    hour12: true
                                });

                                // Check administration status
                                const admins = adminBySchedule[schedule.id] || [];
                                const activeAdmin = admins.find(a => !a.deleted_at);

                                let status = 'scheduled';
                                let statusIcon = '⏳';

                                if (activeAdmin) {
                                    status = 'given';
                                    statusIcon = '✓';
                                } else if (scheduleTime < today) {
                                    status = 'missed';
                                    statusIcon = '✗';
                                }

                                // Build item data for modal
                                const itemData = JSON.stringify({
                                    medName: medName,
                                    dose: schedule.dose,
                                    route: schedule.route,
                                    scheduledTime: timeStr,
                                    scheduledDate: dateStr,
                                    status: status,
                                    administeredAt: activeAdmin ? activeAdmin.administered_at : null,
                                    administeredBy: activeAdmin ? (activeAdmin.administered_by_name || 'Unknown') : null,
                                    comment: activeAdmin ? activeAdmin.comment : null,
                                    color: color
                                }).replace(/"/g, '&quot;');

                                html += `<div class="med-item status-${status}"
                                            style="background-color: ${color.bg}; border-left-color: ${color.border}; color: ${color.text};"
                                            onclick="showOverviewMedDetails(this)" data-med-details="${itemData}">
                                    <span class="med-time">${timeStr}</span>
                                    <span class="med-name">${medName}</span>
                                    <span class="med-status">${statusIcon}</span>
                                </div>`;
                            }
                        });
                    }
                });

                html += '</div>';
            }

            html += '</div>';
            currentDate.setDate(currentDate.getDate() + 1);
        }

        html += '</div>';

        container.innerHTML = html;
    }

    // Show medication details modal for overview
    window.showOverviewMedDetails = function(element) {
        const data = JSON.parse(element.getAttribute('data-med-details'));

        // Status badge
        let statusBadge = '';
        switch(data.status) {
            case 'given':
                statusBadge = '<span class="badge bg-success"><i class="mdi mdi-check-circle me-1"></i>Given</span>';
                break;
            case 'scheduled':
                statusBadge = '<span class="badge bg-primary"><i class="mdi mdi-clock-outline me-1"></i>Scheduled</span>';
                break;
            case 'missed':
                statusBadge = '<span class="badge bg-danger"><i class="mdi mdi-alert-circle me-1"></i>Missed</span>';
                break;
        }

        // Build modal content
        let content = `
            <div class="row">
                <div class="col-12 mb-3">
                    <h5 class="mb-2" style="color: ${data.color.text};">
                        <i class="mdi mdi-pill me-2"></i>${data.medName}
                    </h5>
                    ${statusBadge}
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="text-muted small">Dose</label>
                    <div class="fw-bold">${data.dose || 'N/A'}</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="text-muted small">Route</label>
                    <div class="fw-bold">${data.route || 'N/A'}</div>
                </div>
            </div>`;

        if (data.scheduledTime) {
            content += `
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="text-muted small">Scheduled Date</label>
                    <div class="fw-bold"><i class="mdi mdi-calendar me-1"></i>${data.scheduledDate}</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="text-muted small">Scheduled Time</label>
                    <div class="fw-bold"><i class="mdi mdi-clock me-1"></i>${data.scheduledTime}</div>
                </div>
            </div>`;
        }

        if (data.status === 'given' && data.administeredAt) {
            const adminTime = new Date(data.administeredAt);
            content += `
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="text-muted small">Administered At</label>
                    <div class="fw-bold"><i class="mdi mdi-clock-check me-1"></i>${adminTime.toLocaleString()}</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="text-muted small">Administered By</label>
                    <div class="fw-bold"><i class="mdi mdi-account me-1"></i>${data.administeredBy}</div>
                </div>
            </div>`;
        }

        if (data.comment) {
            content += `
            <div class="row">
                <div class="col-12">
                    <label class="text-muted small">Notes</label>
                    <div class="p-2 bg-light rounded">${data.comment}</div>
                </div>
            </div>`;
        }

        document.getElementById('overviewMedDetailsModalBody').innerHTML = content;
        document.getElementById('overviewMedDetailsModalLabel').textContent = 'Medication Details';

        // Move modal to body to avoid z-index issues
        const modalEl = document.getElementById('overviewMedDetailsModal');
        if (modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }

        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    };

    // Function to load medication calendar with a specific date range
    function loadMedicationCalendarWithDateRange(medicationId, startDate, endDate) {
        if (!medicationId) return;

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
                data: {
                    start_date: startDate,
                    end_date: endDate
                },
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

        // Direct entries have product_name at top level; POSR entries have medication.product.product_name
        if (medication.is_direct_entry) {
            const productName = medication.product_name || 'Direct Entry';
            const sourceLabel = medication.drug_source === 'patient_own' ? "Patient's Own" : 'Ward Stock';
            const sourceBadge = medication.drug_source === 'patient_own' ? 'bg-purple' : 'bg-info';
            statusHtml = `
                <div class="alert alert-info py-2 mb-0">
                    <div class="d-flex align-items-center">
                        <i class="mdi mdi-pill me-2 fs-5"></i>
                        <div>
                            <strong>${productName}</strong>: <span class="badge ${sourceBadge}">${sourceLabel}</span>
                        </div>
                    </div>
                </div>`;
        } else if (medication.product && medication.product.product_name) {
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
            <div class="card-modern shadow-sm mb-3">
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
        // Direct entries have product_name at top level; POSR entries have it under .product
        const productName = medication.product_name || product.product_name || 'Medication';
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

                        // §4.6: Add drug source to tooltip
                        if (admin.drug_source && admin.drug_source !== 'pharmacy_dispensed') {
                            var srcLabel = admin.drug_source === 'patient_own' ? 'Patient\'s Own' : 'Ward Stock';
                            if (admin.drug_source === 'ward_stock' && admin.product_request_id) srcLabel += ' (Billed)';
                            tooltipContent += `<br>Source: ${srcLabel}`;
                        }

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

        // Detect if this is a direct entry
        var $selectedOpt = $('#drug-select').find('option:selected');
        var isDirect = $selectedOpt.data('is-direct') || false;

        if (isDirect) {
            // Direct entry: populate drug_source, product_id/external_drug_name
            var drugSource = $selectedOpt.data('drug-source') || '';
            var productId = $selectedOpt.data('product-id') || '';
            var externalDrugName = $selectedOpt.data('external-drug-name') || '';
            var drugName = ($selectedOpt.data('direct-entry') || {}).product_name || externalDrugName || 'Direct Entry';

            $('#schedule_medication_id').val(''); // No POSR for direct entries
            $('#schedule_drug_source').val(drugSource);
            $('#schedule_product_id').val(productId);
            $('#schedule_external_drug_name').val(externalDrugName);
            if ($('#schedule-medication-name').length) {
                $('#schedule-medication-name').text(drugName);
            }
        } else {
            // Standard POSR medication
            const medication = medications.find(m => m.id == selectedMedication);
            if (!medication || !medication.product) return;

            $('#schedule_medication_id').val(selectedMedication);
            $('#schedule_drug_source').val('pharmacy_dispensed');
            $('#schedule_product_id').val('');
            $('#schedule_external_drug_name').val('');
            if ($('#schedule-medication-name').length) {
                $('#schedule-medication-name').text(medication.product.product_name);
            }
        }

        $('#schedule_date').val(new Date().toISOString().split('T')[0]);
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

    // §6.5: Source tab click handler removed — source is now determined by the selected medication
    // The administer modal is for pharmacy_dispensed only (scheduled charting)

    // §6.5: Simplified — always pharmacy_dispensed for scheduled charting
    function setDrugSource(source) {
        $('#administer_drug_source').val(source || 'pharmacy_dispensed');
    }

    function loadPatientPrescriptions(force = false) {
        if (patientPrescriptionsLoaded && !force) {
            return $.Deferred().resolve(patientPrescriptions).promise();
        }

        const url = medicationChartPrescribedRoute.replace(':patient', PATIENT_ID);
        return $.ajax({
            url: url,
            type: 'GET'
        }).then(function(res) {
            if (res && res.success) {
                patientPrescriptions = res.prescriptions || [];
                patientPrescriptionsLoaded = true;
                populateRxSelect();
            } else {
                toastr.warning('Unable to load prescriptions');
            }
            return patientPrescriptions;
        }).catch(function(xhr) {
            console.error('Failed to load prescriptions', xhr);
            toastr.error('Failed to load prescriptions');
            return [];
        });
    }

    // Open administer modal when clicking on a schedule slot
    $(document).on('click', '.schedule-slot[data-schedule-id]', function() {
        const scheduleId = $(this).data('schedule-id');
        const schedule = currentSchedules.find(s => s.id == scheduleId);

        if (schedule) {
            const scheduledTime = new Date(schedule.scheduled_time);

            $('#administer_schedule_id').val(scheduleId);
            $('#administered_dose').val(schedule.dose || '');
            $('#administered_route').val(schedule.route || 'Oral');

            const now = new Date();
            const formattedNow = formatDateTimeForInput(now);
            $('#administered_at').val(formattedNow);

            // Check if this is a direct entry schedule
            var scheduleDrugSource = schedule.drug_source || 'pharmacy_dispensed';
            var isDirect = (scheduleDrugSource === 'ward_stock' || scheduleDrugSource === 'patient_own');

            if (isDirect) {
                // Direct entry schedule: use schedule data directly
                var drugName = schedule.external_drug_name || 'Direct Entry';
                var $selectedOpt = $('#drug-select').find('option:selected');
                var directEntry = $selectedOpt.data('direct-entry');
                if (directEntry) {
                    drugName = directEntry.product_name || directEntry.external_drug_name || drugName;
                }

                $('#administer-medication-info').text(`${drugName} - ${schedule.dose || ''}`);
                $('#administer-scheduled-time').text(`Scheduled for: ${formatDateTime(scheduledTime)}`);
                $('#administer_product_id').val(schedule.product_id || '');
                $('#administer_product_request_id').val('');
                setDrugSource(scheduleDrugSource);
            } else {
                // §6.5: Auto-populate from the enriched dropdown data
                const posrId = selectedMedication;
                const rx = window._rxLookup ? window._rxLookup[posrId] : null;

                if (rx) {
                    $('#administer-medication-info').text(`${rx.product_name} - ${schedule.dose || rx.dose || ''}`);
                    $('#administer-scheduled-time').text(`Scheduled for: ${formatDateTime(scheduledTime)}`);
                    $('#administer_product_id').val(rx.product_id || '');
                    // §6.5: Auto-set product_request_id from dropdown — no secondary selector needed
                    $('#administer_product_request_id').val(rx.product_request_id || '');
                } else {
                    // Fallback to legacy data
                    const medication = medications.find(m => m.id == selectedMedication);
                    if (medication && medication.product) {
                        $('#administer-medication-info').text(`${medication.product.product_name} - ${schedule.dose}`);
                        $('#administer-scheduled-time').text(`Scheduled for: ${formatDateTime(scheduledTime)}`);
                        $('#administer_product_id').val(medication.product.id || '');
                    }
                }

                // §6.5: Source is always pharmacy_dispensed for scheduled charting
                setDrugSource('pharmacy_dispensed');
            }
        }
    });

    // Administer form submission — §6.5: simplified for pharmacy_dispensed only
    $('#administerForm').submit(function(e) {
        e.preventDefault();

        const scheduleId = $('#administer_schedule_id').val();
        const administeredTime = $('#administered_at').val();
        const dose = $('#administered_dose').val();
        const route = $('#administered_route').val();
        const note = $('#administered_note').val();
        const productId = $('#administer_product_id').val();
        const productRequestId = $('#administer_product_request_id').val();

        const stopLoading = function() {
            $('#administerSubmitBtn .spinner-border').addClass('d-none');
            $('#administerSubmitBtn').prop('disabled', false);
        };

        // §6.5: product_request_id is auto-set from dropdown — validate it exists
        if (!productRequestId) {
            toastr.warning('No dispensed prescription linked to this medication. Cannot administer.');
            return;
        }

        // Show loading indicator
        $('#administerSubmitBtn .spinner-border').removeClass('d-none');
        $('#administerSubmitBtn').prop('disabled', true);

        // §6.5: Simplified payload — source is always pharmacy_dispensed
        const adminData = {
            schedule_id: scheduleId,
            administered_at: administeredTime,
            administered_dose: dose,
            route: route,
            comment: note,
            drug_source: 'pharmacy_dispensed',
            product_id: productId,
            product_request_id: productRequestId,
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
                stopLoading();
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
            let medicationName = medication && medication.product ? medication.product
                .product_name : 'Medication';
            // §4.6: For patient's own, use external drug name
            if (admin.drug_source === 'patient_own' && admin.external_drug_name) {
                medicationName = admin.external_drug_name;
            }

            detailsHtml += `<h6>${medicationName}</h6>`;
            // §4.6: Show drug source badge
            detailsHtml += getDrugSourceBadge(admin.drug_source, admin.product_request_id);
            detailsHtml += `<dl class="row mt-2">
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

            // §4.6: Show external drug details for patient's own
            if (admin.drug_source === 'patient_own') {
                detailsHtml += `<div class="alert alert-light border"><small>`;
                if (admin.external_qty) detailsHtml += `<strong>Qty:</strong> ${admin.external_qty} `;
                if (admin.external_batch_number) detailsHtml += `| <strong>Batch:</strong> ${admin.external_batch_number} `;
                if (admin.external_expiry_date) detailsHtml += `| <strong>Expiry:</strong> ${admin.external_expiry_date} `;
                if (admin.external_source_note) detailsHtml += `<br><strong>Source:</strong> ${admin.external_source_note}`;
                detailsHtml += `</small></div>`;
            }
            // §4.6: Show store info for ward stock
            if (admin.drug_source === 'ward_stock' && admin.store_name) {
                detailsHtml += `<div class="alert alert-light border"><small><strong>Store:</strong> ${admin.store_name}</small></div>`;
            }

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

{{-- ======================================================================
     PRESCRIPTION DASHBOARD & DISMISS UI (Nurse Drug Source Revamp §6)
     ====================================================================== --}}
<script>
(function() {
    'use strict';

    var _rxData = [];           // cached prescription data
    var _rxFilter = 'all';      // current filter

    // ── Load prescriptions from API ──────────────────────────────────
    function loadPrescriptionDashboard() {
        if (!PATIENT_ID || PATIENT_ID === 'null') return;

        var url = medicationChartPrescribedRoute.replace(':patient', PATIENT_ID);

        $('#rx-loading').show();
        $('#rx-table-wrap, #rx-empty').hide();

        $.ajax({
            url: url,
            type: 'GET',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
            success: function(resp) {
                _rxData = resp.data || resp || [];
                renderRxDashboard();
            },
            error: function(xhr) {
                console.error('Failed to load prescriptions', xhr);
                $('#rx-loading').hide();
                $('#rx-empty').show().find('p').text('Failed to load prescriptions.');
            }
        });
    }

    // ── Render the dashboard table ──────────────────────────────────
    function renderRxDashboard() {
        $('#rx-loading').hide();

        var filtered = _rxFilter === 'all' ? _rxData : _rxData.filter(function(rx) {
            return String(rx.status) === String(_rxFilter);
        });

        // Update summary counts
        var dispensed = _rxData.filter(function(rx) { return rx.status === 3; }).length;
        var billed   = _rxData.filter(function(rx) { return rx.status === 2; }).length;
        var requested= _rxData.filter(function(rx) { return rx.status === 1; }).length;

        $('#rx-count-dispensed').text(dispensed);
        $('#rx-count-billed').text(billed);
        $('#rx-count-requested').text(requested);
        $('#rx-count-total').text(_rxData.length);

        // Badge on tab
        var badge = $('#rx-tab-badge');
        if (_rxData.length > 0) {
            badge.text(_rxData.length).show();
        } else {
            badge.hide();
        }

        if (filtered.length === 0) {
            $('#rx-table-wrap').hide();
            $('#rx-empty').show().find('p').text(
                _rxFilter === 'all' ? 'No active prescriptions found for this patient.'
                                    : 'No prescriptions match this filter.'
            );
            return;
        }

        $('#rx-empty').hide();
        var tbody = $('#rx-dashboard-body').empty();

        filtered.forEach(function(rx) {
            var statusBadge = _rxStatusBadge(rx.status, rx.status_label);
            var adminInfo = (rx.times_administered || 0) + ' / ' + (rx.qty_prescribed || '?');
            var canDismiss = rx.status !== 3; // can't dismiss dispensed

            var dismissBtn = canDismiss
                ? '<button class="btn btn-outline-danger btn-sm rx-dismiss-btn" data-rx-id="' + rx.id + '" data-rx-name="' + _escHtml(rx.product_name) + '" data-rx-dose="' + _escHtml(rx.dose || '') + '">' +
                  '<i class="mdi mdi-close-circle"></i> Dismiss</button>'
                : '<span class="text-muted small">—</span>';

            var row = '<tr data-rx-status="' + rx.status + '">' +
                '<td><strong>' + _escHtml(rx.product_name) + '</strong>' +
                    (rx.product_code ? ' <small class="text-muted">(' + _escHtml(rx.product_code) + ')</small>' : '') +
                '</td>' +
                '<td>' + _escHtml(rx.dose || '—') + '</td>' +
                '<td>' + _escHtml(rx.doctor_name || '—') + '</td>' +
                '<td><small>' + _formatDate(rx.prescribed_at) + '</small></td>' +
                '<td>' + statusBadge + '</td>' +
                '<td class="text-center"><span class="badge bg-light text-dark">' + adminInfo + '</span></td>' +
                '<td class="text-center">' + dismissBtn + '</td>' +
                '</tr>';

            tbody.append(row);
        });

        $('#rx-table-wrap').show();
    }

    // ── Status badge helper ─────────────────────────────────────────
    function _rxStatusBadge(status, label) {
        var cls = 'secondary';
        if (status === 3) cls = 'success';
        else if (status === 2) cls = 'info';
        else if (status === 1) cls = 'warning';
        return '<span class="badge bg-' + cls + '">' + _escHtml(label || 'Unknown') + '</span>';
    }

    function _escHtml(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function _formatDate(dt) {
        if (!dt) return '—';
        try {
            var d = new Date(dt);
            return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) +
                   ' ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
        } catch(e) { return dt; }
    }

    // ── Filter buttons ──────────────────────────────────────────────
    $(document).on('click', '#rx-filter-group [data-rx-filter]', function() {
        var btn = $(this);
        $('#rx-filter-group .btn').removeClass('active');
        btn.addClass('active');
        _rxFilter = btn.data('rx-filter');
        renderRxDashboard();
    });

    // ── Refresh button ──────────────────────────────────────────────
    $(document).on('click', '#rx-refresh-btn', function() {
        loadPrescriptionDashboard();
    });

    // ── Load on tab show ────────────────────────────────────────────
    $(document).on('shown.bs.tab', '#med-rx-tab', function() {
        loadPrescriptionDashboard();
    });

    // ── Dismiss: open modal ─────────────────────────────────────────
    $(document).on('click', '.rx-dismiss-btn', function() {
        var btn = $(this);
        var rxId   = btn.data('rx-id');
        var rxName = btn.data('rx-name');
        var rxDose = btn.data('rx-dose');

        $('#dismiss-rx-id').val(rxId);
        $('#dismiss-rx-info').html(
            '<div class="p-2 bg-light rounded">' +
            '<strong>' + _escHtml(rxName) + '</strong>' +
            (rxDose ? ' — <em>' + _escHtml(rxDose) + '</em>' : '') +
            '</div>'
        );
        $('#dismiss-rx-reason').val('');
        var modal = new bootstrap.Modal(document.getElementById('dismissRxModal'));
        modal.show();
    });

    // ── Dismiss: confirm ────────────────────────────────────────────
    $(document).on('click', '#confirm-dismiss-rx-btn', function() {
        var rxId   = $('#dismiss-rx-id').val();
        var reason = $('#dismiss-rx-reason').val().trim();

        if (!reason) {
            toastr.warning('Please enter a reason for dismissal.');
            $('#dismiss-rx-reason').focus();
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Dismissing...');

        var url = medicationChartDismissRoute.replace(':patient', PATIENT_ID);

        $.ajax({
            url: url,
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
            data: {
                _token: CSRF_TOKEN,
                product_request_id: rxId,
                reason: reason
            },
            success: function(resp) {
                toastr.success(resp.message || 'Prescription dismissed successfully.');
                bootstrap.Modal.getInstance(document.getElementById('dismissRxModal')).hide();
                loadPrescriptionDashboard(); // refresh
            },
            error: function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.error) || 'Failed to dismiss prescription.';
                toastr.error(msg);
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="mdi mdi-close-circle me-1"></i> Confirm Dismiss');
            }
        });
    });

    // ── Auto-load on page ready if tab is already active ────────────
    $(document).ready(function() {
        // Pre-load badge count even if not on the tab
        if (PATIENT_ID && PATIENT_ID !== 'null') {
            var url = medicationChartPrescribedRoute.replace(':patient', PATIENT_ID);
            $.ajax({
                url: url,
                type: 'GET',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                success: function(resp) {
                    _rxData = resp.data || resp || [];
                    var badge = $('#rx-tab-badge');
                    if (_rxData.length > 0) {
                        badge.text(_rxData.length).show();
                    }
                }
            });
        }
    });

    // ═══════════════════════════════════════════════════════════
    // §6.2–6.4: WARD STOCK & PATIENT'S OWN — DIRECT ADMINISTRATION
    // ═══════════════════════════════════════════════════════════

    // Helper: set datetime-local input to current time
    function setCurrentDateTime(inputId) {
        var now = new Date();
        var offset = now.getTimezoneOffset();
        var local = new Date(now.getTime() - offset * 60000);
        document.getElementById(inputId).value = local.toISOString().slice(0, 16);
    }

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
                if (typeof loadCalendar === 'function') loadCalendar();
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
            url: "{{ url('pharmacy-workbench/stores') }}",
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
                url: "{{ url('live-search-products') }}",
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
                        var qtyClass = qty > 0 ? 'text-success' : 'text-danger';

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
            url: '/pharmacy-workbench/product/' + productId + '/stock',
            method: 'GET',
            success: function(resp) {
                var storeStock = (resp.stores || []).find(function(s) { return s.store_id == storeId; });
                var available = storeStock ? storeStock.quantity : 0;
                var badge = $('#ws_available_stock');
                badge.text(available + ' in store');
                if (available > 0) {
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
                if (typeof loadCalendar === 'function') loadCalendar();
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

})();
</script>
