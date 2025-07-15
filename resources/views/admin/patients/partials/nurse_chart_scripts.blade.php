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

    // Configurable time window for editing/deleting administrations (from .env)
    var NOTE_EDIT_WINDOW = {{ env('NOTE_EDIT_WINDOW', 30) }}; // Default 30 minutes if not set
    var intakeOutputChartIndexRoute = "{{ route('nurse.intake_output.index', [':patient']) }}";
    var intakeOutputChartStartRoute = "{{ route('nurse.intake_output.start') }}";
    var intakeOutputChartEndRoute = "{{ route('nurse.intake_output.end') }}";
    var intakeOutputChartRecordRoute = "{{ route('nurse.intake_output.record') }}";
</script>
<script>
    // Nurse Chart JS (AJAX + Toaster)
    $(function() {
        // Global variables for medication chart
        let selectedMedication = null;
        let calendarStartDate = new Date();
        calendarStartDate.setDate(calendarStartDate.getDate() - 15); // Start 15 days before today
        let medications = [];
        let medicationStatus = {};

        // Initialize date inputs with today's date
        document.getElementById('start_date').valueAsDate = new Date();

        // Medication Chart initialization
        function loadMedicationsList() {
            $('#medication-loading').show();
            $('#medication-chart-list').hide();
            $('#medication-calendar-view').hide();

            $.get(medicationChartIndexRoute.replace(':patient', PATIENT_ID), function(data) {
                $('#medication-loading').hide();
                medications = data.prescriptions || [];

                // Populate medication dropdown
                const select = $('#drug-select');
                select.empty();
                select.append('<option value="">-- Select a medication --</option>');

                medications.forEach(function(p) {
                    const prod = p.product || {};
                    const isContinued = !p.discontinued_at;
                    const status = isContinued ? '' : ' (Discontinued)';
                    const disabled = !isContinued ? ' disabled' : '';

                    select.append(`<option value="${p.id}"${disabled}>${prod.product_name || 'Unknown'} - ${prod.product_code || ''} ${status}</option>`);

                    // Store medication status
                    medicationStatus[p.id] = {
                        discontinued: !!p.discontinued_at,
                        discontinued_at: p.discontinued_at,
                        discontinued_reason: p.discontinued_reason,
                        resumed_at: p.resumed_at,
                        resumed_reason: p.resumed_reason
                    };
                });

                // Show medications table if no medication is selected yet
                if (!selectedMedication) {
                    $('#medication-chart-list').html(renderMedicationsTable(data)).show();
                }
            }).fail(function() {
                $('#medication-loading').hide();
                $('#medication-chart-list').html('<div class="alert alert-danger">Failed to load medications.</div>').show();
            });
        }

        function renderMedicationsTable(data) {
            let html = `
            <h6 class="my-3">Available Medications</h6>
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th>Medication</th>
                            <th>Code</th>
                            <th>Category</th>
                            <th>Qty</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>`;

            if (data.prescriptions && data.prescriptions.length) {
                data.prescriptions.forEach(function(p) {
                    const prod = p.product || {};
                    const isContinued = !p.discontinued_at;
                    const statusBadge = isContinued ?
                        '<span class="badge bg-success">Active</span>' :
                        '<span class="badge bg-danger">Discontinued</span>';

                    html += `<tr>
                            <td>${prod.product_name || 'Unknown'}</td>
                            <td>${prod.product_code || ''}</td>
                            <td>${prod.category ? prod.category.category_name : ''}</td>
                            <td>${p.qty || ''}</td>
                            <td>${statusBadge}</td>
                            <td>
                                <button class='btn btn-sm btn-primary select-medication-btn' data-id='${p.id}'>
                                    Select
                                </button>
                            </td>
                        </tr>`;
                });
            } else {
                html += `<tr><td colspan="6" class="text-center">No medications found</td></tr>`;
            }

            html += `</tbody></table>
            </div>`;
            return html;
        }

        // Calendar view for medication scheduling
        function loadMedicationCalendar(medicationId, startDate) {
            if (!medicationId) return;

            $('#medication-loading').show();
            $('#medication-calendar-view').hide();

            const formattedStartDate = formatDateForApi(startDate);
            const url = medicationChartCalendarRoute
                .replace(':patient', PATIENT_ID)
                .replace(':medication', medicationId)
                .replace(':start_date', formattedStartDate);

            $.get(url, function(data) {
                $('#medication-loading').hide();

                if (data.medication) {
                    const medication = data.medication;
                    const product = medication.product || {};
                    const schedules = data.schedules || [];
                    const administrations = data.administrations || [];

                    // Update medication status display
                    updateMedicationStatus(medication);

                    // Show calendar
                    renderCalendarView(medication, schedules, administrations, startDate);

                    // Show medication controls
                    $('#medication-controls').show();

                    // Set discontinue/resume button state
                    if (medication.discontinued_at) {
                        $('#discontinue-btn').hide();
                        $('#resume-btn').show();
                    } else {
                        $('#discontinue-btn').show();
                        $('#resume-btn').hide();
                    }
                } else {
                    $('#medication-chart-list').html('<div class="alert alert-warning">Medication not found.</div>').show();
                }
            }).fail(function() {
                $('#medication-loading').hide();
                $('#medication-chart-list').html('<div class="alert alert-danger">Failed to load medication schedule.</div>').show();
            });
        }

        function renderCalendarView(medication, schedules, administrations, startDate) {
            const calendarStart = new Date(startDate);
            const product = medication.product || {};

            // Generate dates for 30 days from start date
            const days = [];
            for (let i = 0; i < 30; i++) {
                const day = new Date(calendarStart);
                day.setDate(calendarStart.getDate() + i);
                days.push(day);
            }

            // Set calendar period header
            const periodStart = formatDate(days[0]);
            const periodEnd = formatDate(days[days.length - 1]);
            $('#calendar-period').text(`${periodStart} to ${periodEnd}`);

            // Build calendar header with dates
            let headerHtml = '<tr><th class="text-center" style="width:120px;">Time</th>';
            days.forEach((day, index) => {
                const dateStr = formatDate(day);
                const isToday = isDateToday(day);
                const dayClass = isToday ? 'table-info' : '';
                headerHtml += `<th class="text-center ${dayClass}" style="min-width:100px;">
                    <div>${getDayName(day)}</div>
                    <div class="small">${dateStr}</div>
                </th>`;
            });
            headerHtml += '</tr>';

            // Group schedules by time
            const timeSlots = {};
            schedules.forEach(schedule => {
                const time = getTimeFromDateTime(schedule.scheduled_time);
                if (!timeSlots[time]) {
                    timeSlots[time] = Array(30).fill(null);
                }

                const scheduleDate = new Date(schedule.scheduled_time);
                const dayIndex = getDayDifference(calendarStart, scheduleDate);

                if (dayIndex >= 0 && dayIndex < 30) {
                    timeSlots[time][dayIndex] = schedule;
                }
            });

            // Sort times
            const sortedTimes = Object.keys(timeSlots).sort();

            // Build calendar body
            let bodyHtml = '';
            sortedTimes.forEach(time => {
                bodyHtml += `<tr><td class="text-center fw-bold">${formatTime(time)}</td>`;

                for (let i = 0; i < 30; i++) {
                    const schedule = timeSlots[time][i];

                    if (!schedule) {
                        bodyHtml += '<td></td>';
                        continue;
                    }

                    // Find matching administration if any
                    const admin = administrations.find(a => a.schedule_id === schedule.id);

                    let cellClass = '';
                    let cellContent = '';
                    let cellAttributes = '';

                    if (admin) {
                        if (admin.deleted_at) {
                            cellClass = 'bg-secondary';
                            cellContent = `<span class="badge text-decoration-line-through text-light">Deleted</span>`;
                        } else {
                            cellClass = 'bg-success';
                            cellContent = `
                                <div class="small text-white">${formatTime(getTimeFromDateTime(admin.administered_time))}</div>
                                <div class="small text-white">${admin.dose || ''} - ${admin.route || ''}</div>
                                <div class="small text-white">By: ${admin.nurse && admin.nurse.name ? admin.nurse.name : 'Unknown'}</div>
                            `;

                            // Check if editable (within edit window)
                            const adminTime = new Date(admin.administered_time);
                            const now = new Date();
                            const diffMinutes = (now - adminTime) / (1000 * 60);

                            if (diffMinutes <= NOTE_EDIT_WINDOW) {
                                cellAttributes = `data-admin-id="${admin.id}" data-bs-toggle="modal" data-bs-target="#editAdminModal" style="cursor:pointer;"`;
                            }
                        }
                    } else {
                        // Future or missed schedule
                        const scheduleDate = new Date(schedule.scheduled_time);
                        const now = new Date();

                        if (scheduleDate < now) {
                            // Missed
                            cellClass = 'bg-danger';
                            cellContent = '<span class="badge text-light">Missed</span>';
                        } else {
                            // Future - can be administered
                            cellClass = 'bg-info schedule-slot';
                            cellContent = `
                                <div class="small text-white">${formatTime(getTimeFromDateTime(schedule.scheduled_time))}</div>
                                <div class="small text-white">${schedule.dose || ''}</div>
                            `;
                            cellAttributes = `data-schedule-id="${schedule.id}" data-bs-toggle="modal" data-bs-target="#administerModal" style="cursor:pointer;"`;
                        }
                    }

                    bodyHtml += `<td class="${cellClass}" ${cellAttributes}>${cellContent}</td>`;
                }

                bodyHtml += '</tr>';
            });

            // Update calendar HTML
            $('.medication-calendar thead').html(headerHtml);
            $('.medication-calendar tbody').html(bodyHtml);

            // Show calendar view
            $('#medication-calendar-view').show();
            $('#medication-chart-list').hide();
        }

        function updateMedicationStatus(medication) {
            const product = medication.product || {};
            let statusHtml = '';

            if (medication.discontinued_at) {
                statusHtml = `
                <strong>${product.product_name}</strong> was discontinued on
                ${formatDateTime(medication.discontinued_at)} by ${medication.discontinued_by || 'Unknown'}.<br>
                <strong>Reason:</strong> ${medication.discontinued_reason || 'No reason provided'}.
                `;

                if (medication.resumed_at) {
                    statusHtml += `<br>Later resumed on ${formatDateTime(medication.resumed_at)} by ${medication.resumed_by || 'Unknown'}.<br>
                    <strong>Resume reason:</strong> ${medication.resumed_reason || 'No reason provided'}.`;
                }
            } else {
                // Active medication status
                statusHtml = `<strong>${product.product_name}</strong> is active. Select dates below to administer or schedule.`;
            }

            $('#drug-status').html(statusHtml).show();
        }

        // Event handlers for medication chart
        $('#drug-select').change(function() {
            const medicationId = $(this).val();
            if (medicationId) {
                selectedMedication = medications.find(m => m.id == medicationId);
                loadMedicationCalendar(medicationId, calendarStartDate);
            } else {
                $('#medication-calendar-view').hide();
                $('#medication-controls').hide();
                $('#drug-status').hide();
                $('#medication-chart-list').show();
            }
        });

        $(document).on('click', '.select-medication-btn', function() {
            const medicationId = $(this).data('id');
            $('#drug-select').val(medicationId).trigger('change');
        });

        // Schedule modal
        $('#set-schedule-btn').click(function() {
            if (!selectedMedication) return;

            const product = selectedMedication.product || {};
            $('#schedule_request_id').val(selectedMedication.id);
            $('#schedule-medication-name').text(product.product_name || 'Selected medication');
            $('#setScheduleModal').modal('show');

            // Set today's date as default
            document.getElementById('administration_time').value = '08:00';
        });

        // Toggle repeat days container
        $('#repeat_daily').change(function() {
            if ($(this).is(':checked')) {
                $('#repeat-days-container').hide();
            } else {
                $('#repeat-days-container').show();
                // Check today's day by default
                const today = new Date().getDay();
                $(`#day-${['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'][today]}`).prop('checked', true);
            }
        });

        // Schedule form submission
        $('#scheduleForm').submit(function(e) {
            e.preventDefault();

            const medicationId = $('#schedule_request_id').val();
            const adminTime = $('#administration_time').val();
            const dose = $('#dose').val();
            const route = $('#schedule_route').val();
            const repeatDaily = $('#repeat_daily').is(':checked');
            const durationDays = $('#duration_days').val();
            const startDate = $('#start_date').val();

            let selectedDays = [];
            if (!repeatDaily) {
                // Get selected days
                $('.day-checkbox:checked').each(function() {
                    selectedDays.push($(this).val());
                });

                if (selectedDays.length === 0) {
                    toastr.error('Please select at least one day of the week');
                    return;
                }
            }

            $('#scheduleLoading').removeClass('d-none');
            $('#scheduleSubmitBtn').attr('disabled', true);

            $.post(medicationChartScheduleRoute, {
                patient_id: PATIENT_ID,
                product_or_service_request_id: medicationId,
                time: adminTime,
                dose: dose,
                route: route,
                repeat_daily: repeatDaily,
                selected_days: selectedDays,
                duration_days: durationDays,
                start_date: startDate,
                _token: CSRF_TOKEN
            }, function(resp) {
                toastr.success('Medication schedule saved!');
                $('#setScheduleModal').modal('hide');
                loadMedicationCalendar(medicationId, calendarStartDate);
            })
            .fail(function(xhr) {
                toastr.error('Failed to save schedule: ' + (xhr.responseJSON?.message || 'Unknown error'));
            })
            .always(function() {
                $('#scheduleLoading').addClass('d-none');
                $('#scheduleSubmitBtn').attr('disabled', false);
            });
        });

        // Calendar navigation
        $('#calendar-nav-prev').click(function() {
            if (!selectedMedication) return;

            const newStartDate = new Date(calendarStartDate);
            newStartDate.setDate(newStartDate.getDate() - 30);
            calendarStartDate = newStartDate;

            loadMedicationCalendar(selectedMedication.id, calendarStartDate);
        });

        $('#calendar-nav-next').click(function() {
            if (!selectedMedication) return;

            const newStartDate = new Date(calendarStartDate);
            newStartDate.setDate(newStartDate.getDate() + 30);
            calendarStartDate = newStartDate;

            loadMedicationCalendar(selectedMedication.id, calendarStartDate);
        });

        // Administer medication
        $(document).on('click', '.schedule-slot', function() {
            const scheduleId = $(this).data('schedule-id');

            // Find schedule details
            $.get(medicationChartIndexRoute.replace(':patient', PATIENT_ID) + '/schedule/' + scheduleId, function(data) {
                const schedule = data.schedule;
                if (!schedule) {
                    toastr.error('Schedule not found');
                    return;
                }

                const medication = schedule.medication || {};
                const product = medication.product || {};

                // Populate modal
                $('#administer_schedule_id').val(schedule.id);
                $('#administer-medication-info').text(`${product.product_name} - ${schedule.dose || ''}`);
                $('#administer-scheduled-time').text(`Scheduled for: ${formatDateTime(schedule.scheduled_time)}`);
                $('#administered_time').val(formatDateTimeForInput(new Date()));
                $('#administered_dose').val(schedule.dose || '');
                $('#administered_route').val(schedule.route || 'Oral');
                $('#note').val('');

                // Show modal
                $('#administerModal').modal('show');
            }).fail(function() {
                toastr.error('Failed to load schedule details');
            });
        });

        // Administer form submission
        $('#administerForm').submit(function(e) {
            e.preventDefault();
            $('#administerLoading').removeClass('d-none');
            $('#administerSubmitBtn').attr('disabled', true);

            $.post(medicationChartAdministerRoute, $(this).serialize() + '&_token=' + CSRF_TOKEN,
                function(resp) {
                    toastr.success('Administration saved!');
                    $('#administerModal').modal('hide');

                    // Reload calendar with the same medication
                    if (selectedMedication) {
                        loadMedicationCalendar(selectedMedication.id, calendarStartDate);
                    }
                })
                .fail(function(xhr) {
                    toastr.error('Failed to save administration: ' + (xhr.responseJSON?.message || 'Unknown error'));
                })
                .always(function() {
                    $('#administerLoading').addClass('d-none');
                    $('#administerSubmitBtn').attr('disabled', false);
                });
        });

        // Discontinue medication
        $('#discontinue-btn').click(function() {
            if (!selectedMedication) return;

            const product = selectedMedication.product || {};
            $('#discontinue_request_id').val(selectedMedication.id);
            $('#discontinue-medication-name').text(`Discontinue ${product.product_name || 'selected medication'}`);
            $('#discontinue_reason').val('');
            $('#discontinueModal').modal('show');
        });

        $('#discontinueForm').submit(function(e) {
            e.preventDefault();
            $('#discontinueLoading').removeClass('d-none');
            $('#discontinueSubmitBtn').attr('disabled', true);

            const medicationId = $('#discontinue_request_id').val();
            const reason = $('#discontinue_reason').val();

            $.post(medicationChartDiscontinueRoute, {
                patient_id: PATIENT_ID,
                product_or_service_request_id: medicationId,
                reason: reason,
                _token: CSRF_TOKEN
            }, function(resp) {
                toastr.success('Medication discontinued!');
                $('#discontinueModal').modal('hide');

                // Update medication status
                medicationStatus[medicationId].discontinued = true;
                medicationStatus[medicationId].discontinued_at = new Date().toISOString();
                medicationStatus[medicationId].discontinued_reason = reason;

                // Reload medication list and calendar
                loadMedicationsList();
                if (selectedMedication) {
                    loadMedicationCalendar(selectedMedication.id, calendarStartDate);
                }
            })
            .fail(function(xhr) {
                toastr.error('Failed to discontinue medication: ' + (xhr.responseJSON?.message || 'Unknown error'));
            })
            .always(function() {
                $('#discontinueLoading').addClass('d-none');
                $('#discontinueSubmitBtn').attr('disabled', false);
            });
        });

        // Resume medication
        $('#resume-btn').click(function() {
            if (!selectedMedication) return;

            const product = selectedMedication.product || {};
            $('#resume_request_id').val(selectedMedication.id);
            $('#resume-medication-name').text(`Resume ${product.product_name || 'selected medication'}`);
            $('#resume_reason').val('');
            $('#resumeModal').modal('show');
        });

        $('#resumeForm').submit(function(e) {
            e.preventDefault();
            $('#resumeLoading').removeClass('d-none');
            $('#resumeSubmitBtn').attr('disabled', true);

            const medicationId = $('#resume_request_id').val();
            const reason = $('#resume_reason').val();

            $.post(medicationChartResumeRoute, {
                patient_id: PATIENT_ID,
                product_or_service_request_id: medicationId,
                reason: reason,
                _token: CSRF_TOKEN
            }, function(resp) {
                toastr.success('Medication resumed!');
                $('#resumeModal').modal('hide');

                // Update medication status
                medicationStatus[medicationId].discontinued = false;
                medicationStatus[medicationId].resumed_at = new Date().toISOString();
                medicationStatus[medicationId].resumed_reason = reason;

                // Reload medication list and calendar
                loadMedicationsList();
                if (selectedMedication) {
                    loadMedicationCalendar(selectedMedication.id, calendarStartDate);
                }
            })
            .fail(function(xhr) {
                toastr.error('Failed to resume medication: ' + (xhr.responseJSON?.message || 'Unknown error'));
            })
            .always(function() {
                $('#resumeLoading').addClass('d-none');
                $('#resumeSubmitBtn').attr('disabled', false);
            });
        });

        // Helper functions
        function formatDate(date) {
            return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        }

        function formatTime(timeStr) {
            return new Date(`2000-01-01T${timeStr}`).toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
        }

        function formatDateTime(dateTimeStr) {
            const date = new Date(dateTimeStr);
            return date.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
        }

        function formatDateTimeForInput(date) {
            return date.toISOString().slice(0, 16);
        }

        function formatDateForApi(date) {
            return date.toISOString().split('T')[0];
        }

        function getDayName(date) {
            return date.toLocaleDateString('en-US', { weekday: 'short' });
        }

        function getTimeFromDateTime(dateTimeStr) {
            return new Date(dateTimeStr).toTimeString().slice(0, 5);
        }

        function isDateToday(date) {
            const today = new Date();
            return date.getDate() === today.getDate() &&
                date.getMonth() === today.getMonth() &&
                date.getFullYear() === today.getFullYear();
        }

        function getDayDifference(date1, date2) {
            const oneDay = 24 * 60 * 60 * 1000;
            const d1 = new Date(date1);
            const d2 = new Date(date2);

            // Reset times to compare dates only
            d1.setHours(0, 0, 0, 0);
            d2.setHours(0, 0, 0, 0);

            return Math.round((d2 - d1) / oneDay);
        }
        // Intake/Output Chart
        function loadIntakeOutput(type) {
            $.get(intakeOutputChartIndexRoute.replace(':patient', PATIENT_ID), function(data) {
                let periods = type === 'fluid' ? data.fluidPeriods : data.solidPeriods;
                let html = '';
                periods.forEach(function(p) {
                    html += `<div class='card mb-2'>
                        <div class='card-header'>Period: ${p.started_at} ${(p.ended_at ? ' - ' + p.ended_at : '')}`;
                    if (!p.ended_at) html += ` <button class='btn btn-sm btn-danger float-end end-period-btn' data-id='${p.id}'
                                data-type='${type}'>End Period</button>`;
                    html += `</div>
                        <div class='card-body'>
                            <table class='table table-sm'>
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Description</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>`;
                    p.records.forEach(function(r) {
                        html += `<tr>
                                <td>${r.type}</td>
                                <td>${r.amount}</td>
                                <td>${r.description || ''}</td>
                                <td>${r.recorded_at}</td>
                            </tr>`;
                    });
                    html += `</tbody>
                    </table>`;
                    if (!p.ended_at) {
                        html +=
                            `<button class='btn btn-primary btn-sm add-record-btn' data-id='${p.id}' data-type='${type}'>Add Record</button>`;
                    }
                    html += `</div>
                    </div>`;
                });
                $('#' + type + '-periods-list').html(html);
            });
        }
        $('#startFluidPeriodBtn').click(function() {
            $.post(intakeOutputChartStartRoute, {
                patient_id: PATIENT_ID,
                type: 'fluid',
                _token: CSRF_TOKEN
            }, function(resp) {
                toastr.success('Fluid period started!');
                loadIntakeOutput('fluid');
            });
        });
        $('#startSolidPeriodBtn').click(function() {
            $.post(intakeOutputChartStartRoute, {
                patient_id: PATIENT_ID,
                type: 'solid',
                _token: CSRF_TOKEN
            }, function(resp) {
                toastr.success('Solid period started!');
                loadIntakeOutput('solid');
            });
        });
        $(document).on('click', '.end-period-btn', function() {
            let pid = $(this).data('id');
            let type = $(this).data('type');
            $.post(intakeOutputChartEndRoute, {
                period_id: pid,
                _token: CSRF_TOKEN
            }, function(resp) {
                toastr.success('Period ended! Balance: ' + resp.balance);
                loadIntakeOutput(type);
            });
        });
        $(document).on('click', '.add-record-btn', function() {
            let pid = $(this).data('id');
            let type = $(this).data('type');
            if (type === 'fluid') {
                $('#fluid_period_id').val(pid);
                $('#fluidRecordModal').modal('show');
            } else {
                $('#solid_period_id').val(pid);
                $('#solidRecordModal').modal('show');
            }
        });
        $('#fluidRecordForm').submit(function(e) {
            e.preventDefault();
            $.post(intakeOutputChartRecordRoute, $(this).serialize() + '&_token=' + CSRF_TOKEN,
                function(resp) {
                    toastr.success('Record added!');
                    $('#fluidRecordModal').modal('hide');
                    loadIntakeOutput('fluid');
                });
        });
        $('#solidRecordForm').submit(function(e) {
            e.preventDefault();
            $.post(intakeOutputChartRecordRoute, $(this).serialize() + '&_token=' + CSRF_TOKEN,
                function(resp) {
                    toastr.success('Record added!');
                    $('#solidRecordModal').modal('hide');
                    loadIntakeOutput('solid');
                });
        });
        // On tab show
        $('#nurseChart-tab').on('shown.bs.tab', function() {
            loadMedicationsList();
            loadIntakeOutput('fluid');
            loadIntakeOutput('solid');
        });

        // Initial load if already active
        if ($('#nurseChartCardBody').hasClass('show')) {
            loadMedicationsList();
            loadIntakeOutput('fluid');
            loadIntakeOutput('solid');
        } else {
            // Initialize when medication tab is shown
            $('#medication-tab').on('shown.bs.tab', function() {
                loadMedicationsList();
            });
        }
    });
</script>

<style>
/* Medication Calendar Styles */
.medication-calendar th,
.medication-calendar td {
    text-align: center;
    vertical-align: middle;
    padding: 0.25rem;
    height: 60px;
}

.medication-calendar thead th {
    position: sticky;
    top: 0;
    background-color: #f8f9fa;
    z-index: 10;
}

.medication-calendar .bg-info,
.medication-calendar .bg-success,
.medication-calendar .bg-danger,
.medication-calendar .bg-secondary {
    color: white;
}

.medication-calendar .schedule-slot:hover {
    filter: brightness(90%);
}

/* Fixed height for the calendar container with scrolling */
#medication-calendar-view {
    max-height: 600px;
    overflow-y: auto;
    margin-bottom: 1rem;
}
</style>

