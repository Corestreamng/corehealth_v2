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

// Close appointments view
$('#btn-close-appointments-view').on('click', function() {
    $('#appointments-calendar-view').removeClass('active');
});

// Calendar / Table toggle
$('#appt-view-toggle .btn').on('click', function() {
    var view = $(this).data('view');
    currentApptCalendarView = view;
    $('#appt-view-toggle .btn').removeClass('active');
    $(this).addClass('active');
    if (view === 'calendar') {
        $('#appointments-calendar-container').show();
        $('#appointments-table-container').hide();
        if (appointmentsCalendar) {
            $('#appointments-fullcalendar').fullCalendar('rerenderEvents');
        }
    } else {
        $('#appointments-calendar-container').hide();
        $('#appointments-table-container').show();
        initAppointmentsGlobalDataTable();
    }
});

// Calendar filter changes → when filters change we need a full refetch (data set changes completely)
$('#appt-cal-clinic-filter, #appt-cal-doctor-filter, #appt-cal-status-filter').on('change', function() {
    if (appointmentsCalendar) {
        // Filter change = entirely new data set, must use refetch
        $('#appointments-fullcalendar').fullCalendar('refetchEvents');
        window._apptCalLastEvents = {}; // reset fingerprints
    }
    if (appointmentsGlobalDataTable) {
        appointmentsGlobalDataTable.ajax.reload(null, false);
    }
});

// Populate doctor filter when clinic changes
$('#appt-cal-clinic-filter').on('change', function() {
    var clinicId = $(this).val();
    var $docFilter = $('#appt-cal-doctor-filter');
    $docFilter.empty().append('<option value="">All Doctors</option>');
    if (clinicId) {
        $.get(wbUrl("reception/clinics" + '/' + clinicId + '/doctors'), function(data) {
            var doctors = Array.isArray(data) ? data : (data.doctors || []);
            doctors.forEach(function(doc) {
                $docFilter.append('<option value="' + doc.id + '">' + doc.name + '</option>');
            });
        });
    }
});

var apptCalSearchTimer = null;
$('#appt-cal-search').on('keyup', function() {
    clearTimeout(apptCalSearchTimer);
    apptCalSearchTimer = setTimeout(function() {
        if (appointmentsGlobalDataTable) {
            appointmentsGlobalDataTable.search($('#appt-cal-search').val()).draw();
        }
    }, 300);
});

function showAppointmentsCalendarView() {
    hideAllViews();
    $('#appointments-calendar-view').show().addClass('active');

    if (!appointmentsCalendar) {
        initAppointmentsCalendar();
    } else {
        smoothRefreshApptCal();
    }
}

function initAppointmentsCalendar() {
    appointmentsCalendar = true;

    // ── Smooth refresh: diff-based update to avoid blink ────────────
    window._apptCalLastEvents = {};  // id → JSON fingerprint

    function apptCalFetchParams() {
        return {
            start: $('#appointments-fullcalendar').fullCalendar('getView').start.format('YYYY-MM-DD'),
            end: $('#appointments-fullcalendar').fullCalendar('getView').end.format('YYYY-MM-DD'),
            clinic_id: $('#appt-cal-clinic-filter').val(),
            doctor_id: $('#appt-cal-doctor-filter').val(),
            status: $('#appt-cal-status-filter').val(),
            include_queue: 1
        };
    }

    window.smoothRefreshApptCal = function() {
        if (!appointmentsCalendar) return;
        $.get(wbRoute('appointments.calendar-events', '/appointments/calendar-events'), apptCalFetchParams(), function(newEvents) {
            var cal = $('#appointments-fullcalendar');
            var newMap = {};
            (newEvents || []).forEach(function(e) {
                var fp = JSON.stringify([e.id, e.start, e.end, e.color, e.status, e.title, e.can_deliver, e.doctor_id, e.clinic_id]);
                newMap[e.id] = { data: e, fingerprint: fp };
            });

            // Remove events that are gone or changed
            var existingEvents = cal.fullCalendar('clientEvents');
            existingEvents.forEach(function(existing) {
                var newEntry = newMap[existing.id];
                if (!newEntry) {
                    // Event no longer exists — remove it
                    cal.fullCalendar('removeEvents', existing.id);
                } else if (newEntry.fingerprint !== window._apptCalLastEvents[existing.id]) {
                    // Event changed — remove so we can re-add with new data
                    cal.fullCalendar('removeEvents', existing.id);
                } else {
                    // Unchanged — keep it, mark as seen
                    delete newMap[existing.id];
                }
            });

            // Add new or changed events
            Object.keys(newMap).forEach(function(id) {
                cal.fullCalendar('renderEvent', newMap[id].data, true);
            });

            // Store fingerprints for next diff
            window._apptCalLastEvents = {};
            (newEvents || []).forEach(function(e) {
                window._apptCalLastEvents[e.id] = JSON.stringify([e.id, e.start, e.end, e.color, e.status, e.title, e.can_deliver, e.doctor_id, e.clinic_id]);
            });
        });
    };

    $('#appointments-fullcalendar').fullCalendar({
        header: {
            left: 'prev,today,next',
            center: 'title',
            right: 'agendaWeek,agendaDay,month'
        },
        defaultView: 'agendaWeek',
        editable: false,
        allDaySlot: false,
        slotDuration: '00:15:00',
        minTime: '07:00:00',
        maxTime: '20:00:00',
        slotEventOverlap: false,
        height: 'auto',
        contentHeight: 600,
        events: function(start, end, timezone, callback) {
            $.get(wbRoute('appointments.calendar-events', '/appointments/calendar-events'), {
                start: start.format('YYYY-MM-DD'),
                end: end.format('YYYY-MM-DD'),
                clinic_id: $('#appt-cal-clinic-filter').val(),
                doctor_id: $('#appt-cal-doctor-filter').val(),
                status: $('#appt-cal-status-filter').val(),
                include_queue: 1
            }, function(events) {
                // Store fingerprints so first smooth refresh can diff
                window._apptCalLastEvents = {};
                (events || []).forEach(function(e) {
                    window._apptCalLastEvents[e.id] = JSON.stringify([e.id, e.start, e.end, e.color, e.status, e.title, e.can_deliver, e.doctor_id, e.clinic_id]);
                });
                callback(events);
            }).fail(function() {
                callback([]);
                toastr.error('Failed to load calendar events');
            });
        },
        eventRender: function(event, element) {
            // Rich tooltip with delivery status + next step
            var tipContent = '<strong>' + event.patient_name + '</strong>';
            if (event.file_no) tipContent += '<br>File: ' + event.file_no;
            if (event.phone) tipContent += '<br>Phone: ' + event.phone;
            tipContent += '<br>Doctor: ' + (event.doctor || '');
            tipContent += '<br>Status: ' + event.status_label;
            if (event.is_follow_up) tipContent += '<br><span class="badge bg-info">Follow-Up</span>';
            if (event.event_type === 'queue') tipContent += '<br><span class="badge bg-secondary"><i class="mdi mdi-walk"></i> Walk-in Queue</span>';

            // Delivery status
            if (event.can_deliver === false) {
                tipContent += '<br><span style="color:#dc3545;"><i class="mdi mdi-alert-circle"></i> ' + (event.delivery_reason || 'Blocked') + '</span>';
            } else if (event.status === 1 || event.status === 2 || event.status === 3 || event.status === 4) {
                tipContent += '<br><span style="color:#198754;"><i class="mdi mdi-check-circle"></i> Payment OK</span>';
            }

            // Next step guidance
            if (event.next_step) {
                tipContent += '<br><em style="color:#0d6efd;font-size:0.85em;"><i class="mdi mdi-arrow-right-circle"></i> ' + event.next_step + '</em>';
            }

            element.attr('title', '');
            element.tooltip({ title: tipContent, html: true, container: 'body', placement: 'top' });

            // Delivery blocked visual (striped pattern)
            if (event.can_deliver === false) {
                element.css({
                    'background': 'repeating-linear-gradient(45deg, ' + event.color + ', ' + event.color + ' 10px, rgba(255,255,255,0.15) 10px, rgba(255,255,255,0.15) 12px)',
                    'border-right': '3px solid #dc3545'
                });
            }

            // Add file no to event display
            element.find('.fc-title').append(
                '<br><small style="opacity:0.85;">' + (event.file_no || '') +
                (event.phone ? ', ' + event.phone : '') + '</small>'
            );

            // Priority indicator
            if (event.priority === 'emergency') {
                element.css('border-left', '4px solid #dc3545');
            } else if (event.priority === 'urgent') {
                element.css('border-left', '4px solid #ffc107');
            }
        },
        eventClick: function(event, jsEvent, view) {
            jsEvent.preventDefault();
            jsEvent.stopPropagation(); // Prevent doc click handler removing the new menu immediately
            showAppointmentContextMenu(event, jsEvent);
        },
        dayClick: function(date, jsEvent, view) {
            // Could open new appointment form pre-filled with this date
        }
    });
}

// Context menu for calendar events (matching LinkHMS reference)
function showAppointmentContextMenu(event, jsEvent) {
    // Remove existing context menu
    $('.appt-context-menu').remove();

    var status = event.status;
    var isActive = status === 1 || status === 2 || status === 3 || status === 4;
    var isTerminal = status === 0 || status === 5 || status === 7;
    var menuItems = '';

    // Terminal state info line
    if (isTerminal) {
        var termText = status === 0 ? 'Cancelled' : (status === 5 ? 'Completed' : 'No-Show');
        var termIcon = status === 0 ? 'mdi-cancel text-danger' : (status === 5 ? 'mdi-check-circle text-success' : 'mdi-account-remove text-secondary');
        menuItems += '<a class="context-item" style="cursor:default;opacity:0.7;font-weight:600;" data-action="none"><i class="mdi ' + termIcon + '"></i> ' + termText + '</a>';
    }

    // Start Visit - for WAITING or READY
    if (status === 1 || status === 3) {
        if (event.can_deliver === false) {
            menuItems += '<a class="context-item" style="color:#dc3545;cursor:default;opacity:0.85;" data-action="none"><i class="mdi mdi-alert-circle"></i> ' + (event.delivery_reason || 'Payment Pending') + '</a>';
            menuItems += '<a class="context-item" style="color:#0d6efd;cursor:default;opacity:0.85;font-style:italic;" data-action="none"><i class="mdi mdi-arrow-right-circle"></i> Direct patient to billing/cashier</a>';
        } else {
            menuItems += '<a class="context-item text-success" style="cursor:default;" data-action="none"><i class="mdi mdi-check-circle"></i> Payment OK — In doctor\'s queue</a>';
        }
    }
    // Vitals Pending hint
    if (status === 2) {
        if (event.can_deliver === false) {
            menuItems += '<a class="context-item" style="color:#dc3545;cursor:default;opacity:0.85;" data-action="none"><i class="mdi mdi-alert-circle"></i> ' + (event.delivery_reason || 'Payment Pending') + '</a>';
        }
        menuItems += '<a class="context-item" style="color:#17a2b8;cursor:default;opacity:0.85;font-style:italic;" data-action="none"><i class="mdi mdi-needle"></i> Vitals in progress — waiting for nurse</a>';
    }
    // In Consultation hint
    if (status === 4) {
        menuItems += '<a class="context-item" style="color:#198754;cursor:default;opacity:0.85;font-style:italic;" data-action="none"><i class="mdi mdi-stethoscope"></i> Consultation in progress</a>';
    }
    // Check In - for SCHEDULED
    if (status === 6) {
        menuItems += '<a class="context-item text-info" data-action="checkin"><i class="mdi mdi-account-check"></i> Patient Arrived / Check-In</a>';
    }
    // Reschedule - for SCHEDULED and NO-SHOW
    if (status === 6 || status === 7) {
        menuItems += '<a class="context-item text-warning" data-action="reschedule"><i class="mdi mdi-calendar-edit"></i> Reschedule</a>';
    }
    // Change Doctor - for SCHEDULED
    if (status === 6) {
        menuItems += '<a class="context-item" style="color:#6f42c1;" data-action="reassign"><i class="mdi mdi-account-switch"></i> Change Doctor</a>';
    }
    // Cancel - for SCHEDULED, WAITING, VITALS_PENDING, READY
    if ([6, 1, 2, 3].indexOf(status) !== -1) {
        menuItems += '<a class="context-item text-danger" data-action="cancel"><i class="mdi mdi-close-circle"></i> Cancel</a>';
    }
    // No-Show - for SCHEDULED
    if (status === 6) {
        menuItems += '<a class="context-item text-muted" data-action="noshow"><i class="mdi mdi-account-off"></i> Mark No-Show</a>';
    }
    // View History
    menuItems += '<a class="context-item text-secondary" data-action="history"><i class="mdi mdi-link-variant"></i> View History</a>';

    // Next-step hint for non-obvious states
    if (event.next_step && (status === 1 || status === 2 || status === 3 || status === 6)) {
        menuItems += '<a class="context-item text-muted" style="cursor:default;opacity:0.7;font-size:0.78rem;" data-action="none"><i class="mdi mdi-lightbulb-outline text-warning"></i> ' + event.next_step + '</a>';
    }

    var menu = $('<div class="appt-context-menu" data-appt-id="' + (event.appointment_id || event.record_id || '') +
        '" data-event-type="' + (event.event_type || 'appointment') +
        '" data-queue-id="' + (event.queue_id || '') +
        '" data-clinic="' + (event.clinic_id || '') +
        '" data-doctor="' + (event.doctor_id || '') +
        '" data-date="' + (event.start ? moment(event.start).format('YYYY-MM-DD') : '') +
        '" data-patient="' + (event.patient_name || '') +
        '" data-reschedule-count="' + (event.reschedule_count || 0) +
        '">' + menuItems + '</div>');

    menu.css({ top: jsEvent.pageY, left: jsEvent.pageX });
    $('body').append(menu);

    // Auto-close on click outside
    setTimeout(function() {
        $(document).one('click', function() { $('.appt-context-menu').remove(); });
    }, 10);
}

// Context menu action handlers
$(document).on('click', '.appt-context-menu .context-item', function(e) {
    e.stopPropagation();
    var action = $(this).data('action');
    var $menu = $(this).closest('.appt-context-menu');
    var apptId = $menu.data('appt-id');

    $('.appt-context-menu').remove();

    switch (action) {
        case 'checkin':
            if (!confirm('Check in this appointment?')) return;
            $.post(wbRoute('appointments.check-in', '/appointments/check-in').replace('__AID__', apptId), { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')) }, function(res) {
                if (res.success) {
                    toastr.success(res.message || 'Checked in successfully.');
                    // Show actionable next-step guidance
                    toastr.info('<i class="mdi mdi-arrow-right-circle"></i> Patient is now <b>Waiting</b>. If HMO/payment is pending, direct them to <b>billing/cashier</b> before the doctor can start.', 'Next Step', { timeOut: 8000, extendedTimeOut: 4000, enableHtml: true });
                    refreshAppointmentViews();
                }
                else toastr.error(res.message);
            }).fail(function(xhr) { toastr.error(xhr.responseJSON?.message || 'Check-in failed'); });
            break;
        case 'cancel':
            var reason = prompt('Cancellation reason (optional):');
            if (reason === null) return;
            $.post(wbRoute('appointments.cancel', '/appointments/cancel').replace('__AID__', apptId), { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')), reason: reason }, function(res) {
                if (res.success) { toastr.success(res.message); refreshAppointmentViews(); }
                else toastr.error(res.message);
            }).fail(function(xhr) { toastr.error(xhr.responseJSON?.message || 'Cancel failed'); });
            break;
        case 'noshow':
            if (!confirm('Mark this appointment as No-Show?')) return;
            $.post(wbRoute('appointments.no-show', '/appointments/no-show').replace('__AID__', apptId), { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')) }, function(res) {
                if (res.success) { toastr.success(res.message); refreshAppointmentViews(); }
                else toastr.error(res.message);
            }).fail(function(xhr) { toastr.error(xhr.responseJSON?.message || 'Failed'); });
            break;
        case 'reschedule':
            // Trigger the reschedule modal using data from context menu
            $('#reschedule-appt-id').val(apptId);
            $('#reschedule-patient-name').text($menu.data('patient'));
            $('#reschedule-original-date').text($menu.data('date'));
            $('#reschedule-count-info').text('Reschedule #' + (parseInt($menu.data('reschedule-count') || 0) + 1));
            var ctxReschClinic = $menu.data('clinic');
            $('#reschedule-clinic').val(ctxReschClinic);
            $('#reschedule-date').val('');
            $('#reschedule-time').empty().append('<option value="">-- Select date first --</option>').removeClass('d-none');
            $('#reschedule-custom-time-input').addClass('d-none').val('');
            $('#reschedule-custom-time-toggle').prop('checked', false);
            $('#reschedule-doctor').empty().append('<option value="">Same Doctor</option>');
            $('#reschedule-reason').val('');
            loadRescheduleModalDoctors(ctxReschClinic);
            $('#rescheduleAppointmentModal').modal('show');
            break;
        case 'reassign':
            // Directly open reassign modal (delegated handlers won't fire on detached elements)
            var ctxReassignDoctorId = $menu.data('doctor');
            $('#reassign-appt-id').val(apptId);
            $('#reassign-patient-name').text($menu.data('patient'));
            $('#reassign-current-doctor').text('Loading...');
            $('#reassign-doctor').empty().append('<option value="">Loading doctors...</option>');
            $('#reassign-reason').val('');
            $('#reassignDoctorModal').modal('show');
            $.get(wbRoute('appointments.available-doctors', '/appointments/available-doctors').replace('__AID__', apptId), function(res) {
                var $sel = $('#reassign-doctor');
                $sel.empty().append('<option value="">-- Select Doctor --</option>');
                if (res.success && res.doctors) {
                    var currentName = '';
                    res.doctors.forEach(function(doc) {
                        var isCurrent = doc.id == ctxReassignDoctorId;
                        if (isCurrent) currentName = doc.name;
                        $sel.append('<option value="' + doc.id + '"' + (isCurrent ? ' disabled' : '') + '>' + doc.name + (isCurrent ? ' (current)' : '') + '</option>');
                    });
                    $('#reassign-current-doctor').text(currentName || 'Unknown');
                }
            }).fail(function() {
                $('#reassign-doctor').empty().append('<option value="">Error loading doctors</option>');
            });
            break;
        case 'history':
            // Directly open chain modal (delegated events won't fire on detached elements)
            $('#chain-body').html('<div class="text-center py-4"><i class="mdi mdi-loading mdi-spin mdi-36px"></i></div>');
            $('#appointmentChainModal').modal('show');
            $.get(wbRoute('appointments.chain', '/appointments/chain').replace('__AID__', apptId), function(res) {
                if (res.success && res.chain) {
                    var html = '<div class="appointment-chain-timeline">';
                    res.chain.forEach(function(item, idx) {
                        var isActive = item.id == apptId;
                        var statusBadge = item.status_badge || ('<span class="badge bg-secondary">' + (item.status_label || item.status) + '</span>');
                        html += '<div class="chain-item' + (isActive ? ' chain-item-active' : '') + '">';
                        html += '<div class="chain-marker"><span class="chain-dot' + (isActive ? ' active' : '') + '">' + (idx + 1) + '</span></div>';
                        html += '<div class="chain-content">';
                        html += '<div class="d-flex justify-content-between align-items-center mb-1">';
                        html += '<strong>' + (item.appointment_date || '') + '</strong> ' + statusBadge;
                        html += '</div>';
                        html += '<div class="text-muted small">';
                        html += (item.start_time || '') + ' - ' + (item.end_time || '') + ' &bull; Dr. ' + (item.doctor_name || 'Any');
                        html += '</div>';
                        if (item.appointment_type === 'follow_up') html += '<span class="badge bg-info badge-sm">Follow-Up</span> ';
                        if (item.rescheduled_from_id) html += '<span class="badge bg-warning badge-sm">Rescheduled</span> ';
                        if (item.reassignment_reason) html += '<span class="badge bg-purple badge-sm">Reassigned</span> ';
                        if (item.cancellation_reason) html += '<div class="text-muted small mt-1"><em>' + item.cancellation_reason + '</em></div>';
                        html += '</div></div>';
                    });
                    html += '</div>';
                    $('#chain-body').html(html);
                } else {
                    $('#chain-body').html('<div class="alert alert-warning">No chain data found.</div>');
                }
            }).fail(function() {
                $('#chain-body').html('<div class="alert alert-danger">Failed to load history.</div>');
            });
            break;
        case 'start-visit':
            // No longer used — replaced by status-aware hints
            break;
        case 'none':
            // Informational items — do nothing
            break;
    }
});

function refreshAppointmentViews() {
    loadQueueCounts();
    if (appointmentsCalendar) {
        smoothRefreshApptCal();
    }
    if (appointmentsDataTable) {
        appointmentsDataTable.ajax.reload(null, false);
    }
    if (appointmentsGlobalDataTable) {
        appointmentsGlobalDataTable.ajax.reload(null, false);
    }
    if (typeof patientAppointmentsDataTable !== 'undefined' && patientAppointmentsDataTable) {
        patientAppointmentsDataTable.ajax.reload(null, false);
    }
}

function initAppointmentsGlobalDataTable() {
    if (appointmentsGlobalDataTable) {
        appointmentsGlobalDataTable.ajax.reload(null, false);
        return;
    }

    appointmentsGlobalDataTable = $('#appointments-global-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: wbRoute('appointments.list', '/appointments/list'),
            data: function(d) {
                d.clinic_id = $('#appt-cal-clinic-filter').val();
                d.doctor_id = $('#appt-cal-doctor-filter').val();
                d.status = $('#appt-cal-status-filter').val();
                // Don't filter by date — show all upcoming
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'patient_name', name: 'patient_name' },
            { data: 'patient_file_no', name: 'patient_file_no' },
            { data: 'patient_hmo', name: 'patient_hmo' },
            { data: 'clinic_name', name: 'clinic_name' },
            { data: 'doctor_name', name: 'doctor_name' },
            { data: 'appointment_date', name: 'appointment_date' },
            { data: 'time_slot', name: 'start_time' },
            { data: 'type_badge', name: 'appointment_type', orderable: false },
            { data: 'status_badge', name: 'status', orderable: false },
            { data: 'delivery_info', name: 'delivery_info', orderable: false, searchable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[6, 'asc'], [7, 'asc']],
        pageLength: 20,
        language: {
            emptyTable: 'No appointments found',
            processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...'
        }
    });
}

// Keep the old function as alias for backward compatibility
function showAppointmentsQueueView() {
    showAppointmentsCalendarView();
}

function showReferralsQueueView() {
    referralViewMode = 'pending';
    _loadReferralsTable('pending', 'Pending Referrals');
}

// ─── Appointment actions from queue view ─────────────
$(document).on('click', '.btn-check-in-appointment', function() {
    var apptId = $(this).data('id');
    if (!confirm('Check in this appointment?')) return;
    $.post(wbRoute('appointments.check-in', '/appointments/check-in').replace('__AID__', apptId), { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')) }, function(res) {
        if (res.success) {
            toastr.success(res.message);
            refreshAppointmentViews();
        } else {
            toastr.error(res.message);
        }
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON?.message || 'Check-in failed');
    });
});

$(document).on('click', '.btn-cancel-appointment', function() {
    var apptId = $(this).data('id');
    var reason = prompt('Cancellation reason (optional):');
    if (reason === null) return;
    $.post(wbRoute('appointments.cancel', '/appointments/cancel').replace('__AID__', apptId), { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')), reason: reason }, function(res) {
        if (res.success) {
            toastr.success(res.message);
            refreshAppointmentViews();
        } else {
            toastr.error(res.message);
        }
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON?.message || 'Cancel failed');
    });
});

$(document).on('click', '.btn-noshow-appointment', function() {
    var apptId = $(this).data('id');
    if (!confirm('Mark this appointment as No-Show?')) return;
    $.post(wbRoute('appointments.no-show', '/appointments/no-show').replace('__AID__', apptId), { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')) }, function(res) {
        if (res.success) {
            toastr.success(res.message);
            refreshAppointmentViews();
        } else {
            toastr.error(res.message);
        }
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON?.message || 'Failed');
    });
});

// ─── Referral actions from queue view ─────────────

// Book referral — open modal
$(document).on('click', '.btn-book-referral', function() {
    $('#book-ref-id').val($(this).data('id'));
    $('#book-ref-clinic').val($(this).data('clinic'));
    $('#book-ref-doctor').val($(this).data('doctor'));
    $('#book-ref-patient').val($(this).data('patient'));
    $('#book-ref-date').val('');
    $('#book-ref-time').val('09:00');
    $('#book-ref-clinic-override').val('');
    $('#book-ref-doctor-override').val('');

    // Show target info
    var targetClinic = $(this).data('clinic-name') || '';
    var targetDoctor = $(this).data('doctor-name') || '';
    var info = [];
    if (targetClinic) info.push('Clinic: ' + targetClinic);
    if (targetDoctor) info.push('Doctor: ' + targetDoctor);
    if (info.length) {
        $('#book-ref-target-text').text(info.join(' — '));
        $('#book-ref-target-info').show();
    } else {
        $('#book-ref-target-info').hide();
    }

    // Pre-select existing values in override dropdowns
    if ($(this).data('clinic')) {
        $('#book-ref-clinic-override').val($(this).data('clinic'));
    }
    if ($(this).data('doctor')) {
        $('#book-ref-doctor-override').val($(this).data('doctor'));
    }

    $('#bookReferralModal').modal('show');
});

// Confirm book referral
$(document).on('click', '#confirm-book-referral', function() {
    var refId = $('#book-ref-id').val();
    var date = $('#book-ref-date').val();
    var time = $('#book-ref-time').val();
    if (!date) { toastr.warning('Please select an appointment date'); return; }
    if (!time) { toastr.warning('Please select a start time'); return; }

    var $btn = $(this);
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Booking...');

    $.post(wbRoute('referrals.book', '/referrals/book').replace('__RID__', refId), {
        _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
        appointment_date: date,
        start_time: time,
        clinic_id: $('#book-ref-clinic-override').val() || $('#book-ref-clinic').val(),
        doctor_id: $('#book-ref-doctor-override').val() || $('#book-ref-doctor').val()
    }, function(res) {
        if (res.success) {
            toastr.success(res.message);
            $('#bookReferralModal').modal('hide');
            loadQueueCounts();
            if (referralsDataTable) referralsDataTable.ajax.reload(null, false);
        } else {
            toastr.error(res.message);
        }
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON?.message || 'Booking failed');
    }).always(function() {
        $btn.prop('disabled', false).html('<i class="mdi mdi-calendar-check me-1"></i> Confirm Booking');
    });
});

// Refer out — open modal
$(document).on('click', '.btn-refer-out', function() {
    $('#refer-out-ref-id').val($(this).data('id'));
    $('#refer-out-notes').val('');
    $('#referOutModal').modal('show');
});

// Confirm refer out
$(document).on('click', '#confirm-refer-out', function() {
    var refId = $('#refer-out-ref-id').val();
    var $btn = $(this);
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

    $.post(wbRoute('referrals.refer-out', '/referrals/refer-out').replace('__RID__', refId), {
        _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
        action_notes: $('#refer-out-notes').val()
    }, function(res) {
        if (res.success) {
            toastr.success(res.message + ' — You can view & print the referral letter from the history.');
            $('#referOutModal').modal('hide');
            loadQueueCounts();
            // Switch to all view so user can see the referred-out entry
            showReferralsAllView();
        }
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON?.message || 'Refer out failed');
    }).always(function() {
        $btn.prop('disabled', false).html('<i class="mdi mdi-check me-1"></i> Confirm Referred Out');
    });
});

// Decline referral — open modal
$(document).on('click', '.btn-decline-referral', function() {
    $('#decline-ref-id').val($(this).data('id'));
    $('#decline-reason').val('');
    $('#declineReferralModal').modal('show');
});

// Confirm decline
$(document).on('click', '#confirm-decline-referral', function() {
    var refId = $('#decline-ref-id').val();
    var reason = $('#decline-reason').val();
    if (!reason || reason.trim().length < 3) {
        toastr.warning('Please provide a reason for declining');
        return;
    }
    var $btn = $(this);
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Declining...');

    $.post(wbRoute('referrals.decline', '/referrals/decline').replace('__RID__', refId), {
        _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
        reason: reason
    }, function(res) {
        if (res.success) {
            toastr.success(res.message);
            $('#declineReferralModal').modal('hide');
            if (referralsDataTable) referralsDataTable.ajax.reload(null, false);
        }
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON?.message || 'Decline failed');
    }).always(function() {
        $btn.prop('disabled', false).html('<i class="mdi mdi-close-circle me-1"></i> Decline Referral');
    });
});

// Cancel referral
$(document).on('click', '.btn-cancel-referral', function() {
    var refId = $(this).data('id');
    if (!confirm('Cancel this referral? This cannot be undone.')) return;
    var $btn = $(this);
    $btn.prop('disabled', true);
    $.post(wbRoute('referrals.cancel', '/referrals/cancel').replace('__RID__', refId), {
        _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content'))
    }, function(res) {
        if (res.success) {
            toastr.success(res.message);
            loadQueueCounts();
            if (referralsDataTable) referralsDataTable.ajax.reload(null, false);
        }
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON?.message || 'Cancel referral failed');
        $btn.prop('disabled', false);
    });
});

// View referral detail
$(document).on('click', '.btn-view-referral', function() {
    var refId = $(this).data('id');
    $('#referral-detail-loading').show();
    $('#referral-detail-content').hide();
    $('#btn-print-referral-letter').hide();
    $('#referralDetailModal').modal('show');

    $.get(wbRoute('referrals.detail', '/referrals/detail').replace('__RID__', refId), function(data) {
        if (!data.success) { toastr.error('Failed to load referral'); return; }

        var ref = data.referral;
        var urgencyClass = { 'emergency': 'text-danger', 'urgent': 'text-warning', 'routine': 'text-secondary' }[ref.urgency] || '';
        var statusBadge = {
            'pending': '<span class="badge bg-warning text-dark">Pending</span>',
            'booked': '<span class="badge bg-primary">Booked</span>',
            'completed': '<span class="badge bg-success">Completed</span>',
            'cancelled': '<span class="badge bg-danger">Cancelled</span>',
            'declined': '<span class="badge bg-dark">Declined</span>',
            'referred_out': '<span class="badge bg-purple text-white">Referred Out</span>'
        }[ref.status] || '<span class="badge bg-secondary">' + ref.status + '</span>';

        var html = '<div class="p-3">';
        html += '<div class="d-flex justify-content-between mb-3">';
        html += '<div>' + statusBadge + ' <span class="badge ' + (ref.referral_type === 'internal' ? 'bg-info' : 'bg-dark') + '">' + (ref.referral_type === 'internal' ? 'Internal' : 'External') + '</span></div>';
        html += '<span class="' + urgencyClass + ' fw-bold text-uppercase">' + ref.urgency + '</span>';
        html += '</div>';

        html += '<h6 class="border-bottom pb-1 mb-2"><i class="mdi mdi-account me-1"></i> Patient Information</h6>';
        html += '<div class="row mb-3">';
        html += '<div class="col-md-4"><small class="text-muted">Name</small><br><strong>' + ref.patient_name + '</strong></div>';
        html += '<div class="col-md-4"><small class="text-muted">File No</small><br><strong>' + ref.patient_file_no + '</strong></div>';
        html += '<div class="col-md-4"><small class="text-muted">HMO</small><br><strong>' + ref.patient_hmo + '</strong></div>';
        html += '</div>';

        html += '<h6 class="border-bottom pb-1 mb-2"><i class="mdi mdi-stethoscope me-1"></i> Referral Information</h6>';
        html += '<div class="row mb-3">';
        html += '<div class="col-md-6"><small class="text-muted">Referring Doctor</small><br><strong>' + ref.referring_doctor + '</strong></div>';
        html += '<div class="col-md-6"><small class="text-muted">Referring Clinic</small><br><strong>' + ref.referring_clinic + '</strong></div>';
        html += '</div>';

        if (ref.referral_type === 'internal') {
            html += '<div class="row mb-3">';
            html += '<div class="col-md-6"><small class="text-muted">Target Clinic</small><br><strong>' + (ref.target_clinic || 'Any') + '</strong></div>';
            html += '<div class="col-md-6"><small class="text-muted">Target Doctor</small><br><strong>' + (ref.target_doctor || 'Any Available') + '</strong></div>';
            html += '</div>';
        } else {
            html += '<div class="row mb-3">';
            html += '<div class="col-md-6"><small class="text-muted">External Facility</small><br><strong>' + (ref.external_facility_name || 'N/A') + '</strong></div>';
            html += '<div class="col-md-6"><small class="text-muted">External Doctor</small><br><strong>' + (ref.external_doctor_name || 'N/A') + '</strong></div>';
            html += '</div>';
            if (ref.external_facility_address || ref.external_facility_phone) {
                html += '<div class="row mb-3">';
                html += '<div class="col-md-8"><small class="text-muted">Address</small><br>' + (ref.external_facility_address || '—') + '</div>';
                html += '<div class="col-md-4"><small class="text-muted">Phone</small><br>' + (ref.external_facility_phone || '—') + '</div>';
                html += '</div>';
            }
        }

        html += '<h6 class="border-bottom pb-1 mb-2"><i class="mdi mdi-clipboard-pulse me-1"></i> Clinical Details</h6>';
        if (ref.provisional_diagnosis) html += '<div class="mb-2"><small class="text-muted">Provisional Diagnosis</small><br>' + ref.provisional_diagnosis + '</div>';
        if (ref.clinical_summary) html += '<div class="mb-2"><small class="text-muted">Clinical Summary</small><br>' + ref.clinical_summary + '</div>';
        html += '<div class="mb-2"><small class="text-muted">Reason for Referral</small><br>' + (ref.reason || 'N/A') + '</div>';

        if (ref.action_notes) {
            html += '<h6 class="border-bottom pb-1 mb-2 mt-3"><i class="mdi mdi-note-text me-1"></i> Action Notes</h6>';
            html += '<div class="mb-2">' + ref.action_notes + '</div>';
            if (ref.actioned_at) html += '<small class="text-muted">Actioned: ' + ref.actioned_at + '</small>';
        }

        html += '<div class="text-end mt-3"><small class="text-muted">Created: ' + ref.created_at + '</small></div>';
        html += '</div>';

        $('#referral-detail-content').html(html).show();
        $('#referral-detail-loading').hide();

        // Show print button for external referrals
        if (ref.referral_type === 'external') {
            $('#btn-print-referral-letter').data('ref-data', data).show();
        }

    }).fail(function() {
        $('#referral-detail-loading').hide();
        $('#referral-detail-content').html('<div class="alert alert-danger m-3">Failed to load referral details</div>').show();
    });
});

// Print referral letter — open via detail modal
$(document).on('click', '#btn-print-referral-letter', function() {
    var cachedData = $(this).data('ref-data');
    if (cachedData) {
        buildAndPrintReferralLetter(cachedData);
    }
});

// Print referral directly from table
$(document).on('click', '.btn-print-referral', function() {
    var refId = $(this).data('id');
    $.get(wbRoute('referrals.detail', '/referrals/detail').replace('__RID__', refId), function(data) {
        if (data.success) {
            buildAndPrintReferralLetter(data);
        } else {
            toastr.error('Failed to load referral for printing');
        }
    }).fail(function() {
        toastr.error('Failed to load referral');
    });
});

function buildAndPrintReferralLetter(data) {
    var ref = data.referral;
    var hosp = data.hospital;
    var today = new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
    var urgencyClass = 'urgency-' + (ref.urgency || 'routine');

    var html = '<div class="referral-letter">';

    // Hospital header
    html += '<div class="letter-header">';
    if (hosp.logo) html += '<img src="' + hosp.logo + '" alt="Logo">';
    html += '<h2>' + (hosp.name || 'Hospital') + '</h2>';
    if (hosp.address) html += '<p>' + hosp.address + '</p>';
    var contacts = [];
    if (hosp.phones) contacts.push(hosp.phones);
    if (hosp.email) contacts.push(hosp.email);
    if (contacts.length) html += '<p>' + contacts.join(' | ') + '</p>';
    html += '</div>';

    // Title
    html += '<div class="letter-title">Specialist Referral Letter</div>';

    // Meta row
    html += '<div class="letter-meta">';
    html += '<div><strong>Date:</strong> ' + today + '</div>';
    html += '<div><strong>Ref #:</strong> REF-' + String(ref.id).padStart(5, '0') + '</div>';
    html += '</div>';

    // Urgency stamp
    if (ref.urgency !== 'routine') {
        html += '<div style="text-align:right; margin-bottom:10px;"><span class="urgency-stamp ' + urgencyClass + '">' + ref.urgency + '</span></div>';
    }

    // To: section
    html += '<div class="letter-body">';
    html += '<p><strong>To:</strong><br>';
    if (ref.external_doctor_name) html += ref.external_doctor_name + '<br>';
    html += (ref.external_facility_name || 'The Receiving Doctor') + '<br>';
    if (ref.external_facility_address) html += ref.external_facility_address + '<br>';
    if (ref.external_facility_phone) html += 'Tel: ' + ref.external_facility_phone;
    html += '</p>';

    // Patient info table
    html += '<table class="patient-info-table">';
    html += '<tr><td class="label-cell">Patient Name</td><td>' + ref.patient_name + '</td><td class="label-cell">File No</td><td>' + ref.patient_file_no + '</td></tr>';
    var row2 = '<tr>';
    row2 += '<td class="label-cell">Gender</td><td>' + (ref.patient_gender || '—') + '</td>';
    row2 += '<td class="label-cell">Date of Birth</td><td>' + (ref.patient_dob || '—') + '</td>';
    row2 += '</tr>';
    html += row2;
    html += '<tr><td class="label-cell">HMO / Insurance</td><td colspan="3">' + (ref.patient_hmo || '—') + '</td></tr>';
    html += '</table>';

    // Dear Doctor
    html += '<p>Dear Colleague,</p>';

    html += '<p>I am writing to refer the above-named patient to your facility for specialist evaluation and management.</p>';

    // Diagnosis
    if (ref.provisional_diagnosis) {
        html += '<p><strong>Provisional Diagnosis:</strong> ' + ref.provisional_diagnosis + '</p>';
    }

    // Clinical summary
    if (ref.clinical_summary) {
        html += '<p><strong>Clinical Summary:</strong><br>' + ref.clinical_summary + '</p>';
    }

    // Reason
    html += '<p><strong>Reason for Referral:</strong><br>' + (ref.reason || 'N/A') + '</p>';

    html += '<p>Kindly evaluate and manage as appropriate. I would appreciate feedback on the patient\'s progress and management plan.</p>';

    html += '<p>Thank you for your kind attention.</p>';
    html += '</div>';

    // Signature
    html += '<div class="letter-signature">';
    html += '<p>Yours faithfully,</p>';
    html += '<div class="sig-line">';
    html += '<strong>' + ref.referring_doctor + '</strong><br>';
    html += ref.referring_clinic + '<br>';
    html += (hosp.name || '');
    html += '</div>';
    html += '</div>';

    // Footer
    html += '<div class="letter-footer">';
    html += 'This referral letter was generated electronically by ' + (hosp.name || 'the hospital') + ' on ' + today + '. Ref #REF-' + String(ref.id).padStart(5, '0');
    html += '</div>';

    html += '</div>';

    $('#referral-print-area').html(html);

    setTimeout(function() {
        window.print();
    }, 300);
}

// ─── Referral History Toggle ─────────────
var referralViewMode = 'pending'; // 'pending' or 'all'

function showReferralsAllView() {
    referralViewMode = 'all';
    _loadReferralsTable('all', 'All Referrals (History)');
}

function _loadReferralsTable(statusFilter, titleText) {
    hideAllViews();
    $('#queue-view').show().addClass('active');
    $('#queue-view-title').html(titleText + ' <div class="btn-group btn-group-sm ms-3" role="group">' +
        '<button class="btn btn-' + (statusFilter === 'pending' ? '' : 'outline-') + 'primary btn-referral-filter" data-filter="pending">Pending</button>' +
        '<button class="btn btn-' + (statusFilter === 'all' ? '' : 'outline-') + 'primary btn-referral-filter" data-filter="all">All History</button>' +
        '</div>');

    if (queueDataTable) { queueDataTable.destroy(); queueDataTable = null; }
    if (referralsDataTable) { referralsDataTable.destroy(); referralsDataTable = null; }

    var showStatus = (statusFilter === 'all');

    // Rebuild headers — add Status column for "all" view
    var headerHtml = '<thead><tr><th>#</th><th>Patient</th><th>File No</th><th>Referring Doctor</th><th>Clinic</th><th>Target</th><th>Urgency</th><th>Type</th>';
    if (showStatus) headerHtml += '<th>Status</th>';
    headerHtml += '<th>Time</th><th>Actions</th></tr></thead>';
    $('#queue-datatable').empty().html(headerHtml);

    var columns = [
        { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
        { data: 'patient_name', name: 'patient_name' },
        { data: 'patient_file_no', name: 'patient_file_no' },
        { data: 'referring_doctor', name: 'referring_doctor' },
        { data: 'referring_clinic', name: 'referring_clinic' },
        { data: 'target_info', name: 'target_info' },
        { data: 'urgency_badge', name: 'urgency', orderable: false },
        { data: 'type_badge', name: 'referral_type', orderable: false },
    ];
    if (showStatus) columns.push({ data: 'status_badge', name: 'status', orderable: false });
    columns.push({ data: 'time', name: 'created_at' });
    columns.push({ data: 'actions', name: 'actions', orderable: false, searchable: false });

    referralsDataTable = $('#queue-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: wbRoute('referrals.pending', '/referrals/pending'),
            data: function(d) { d.status = statusFilter; }
        },
        columns: columns,
        order: [[showStatus ? 9 : 8, 'desc']],
        pageLength: 15,
        language: {
            emptyTable: statusFilter === 'pending' ? 'No pending referrals' : 'No referrals found',
            processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...'
        }
    });
}

// Filter toggle buttons
$(document).on('click', '.btn-referral-filter', function() {
    var filter = $(this).data('filter');
    referralViewMode = filter;
    if (filter === 'pending') {
        showReferralsQueueView();
    } else {
        showReferralsAllView();
    }
});

// ─── Reschedule Appointment ────────────────────────────────────────

/**
 * Populate the #reschedule-doctor select with doctors for the given clinic.
 * If selectedDoctorId is provided, that option will be pre-selected.
 */
function loadRescheduleModalDoctors(clinicId, selectedDoctorId) {
    var $sel = $('#reschedule-doctor');
    if (!clinicId) { $sel.empty().append('<option value="">Same Doctor</option>'); return; }
    $.get(wbUrl("reception/clinics" + '/' + clinicId + '/doctors'), function(doctors) {
        $sel.empty().append('<option value="">Same Doctor</option>');
        if (doctors && doctors.length) {
            doctors.forEach(function(doc) {
                var selected = (selectedDoctorId && doc.id == selectedDoctorId) ? ' selected' : '';
                $sel.append('<option value="' + doc.id + '"' + selected + '>' + doc.name + '</option>');
            });
        }
    }).fail(function() {
        $sel.empty().append('<option value="">Same Doctor</option>');
    });
}

$(document).on('click', '.btn-reschedule-appointment', function() {
    var $btn = $(this);
    var apptId = $btn.data('id');
    var clinicId = $btn.data('clinic');
    var doctorId = $btn.data('doctor');
    var origDate = $btn.data('date');
    var patientName = $btn.data('patient');
    var rescheduleCount = $btn.data('reschedule-count') || 0;

    $('#reschedule-appt-id').val(apptId);
    $('#reschedule-patient-name').text(patientName);
    $('#reschedule-original-date').text(origDate);
    $('#reschedule-count-info').text('Reschedule #' + (parseInt(rescheduleCount) + 1));
    $('#reschedule-clinic').val(clinicId);
    $('#reschedule-date').val('');
    $('#reschedule-time').empty().append('<option value="">-- Select date first --</option>').removeClass('d-none');
    $('#reschedule-custom-time-input').addClass('d-none').val('');
    $('#reschedule-custom-time-toggle').prop('checked', false);
    $('#reschedule-doctor').empty().append('<option value="">Same Doctor</option>');
    $('#reschedule-reason').val('');
    loadRescheduleModalDoctors(clinicId, doctorId);

    $('#rescheduleAppointmentModal').modal('show');
});

$(document).on('change', '#reschedule-date, #reschedule-clinic, #reschedule-doctor', function() {
    if ($('#reschedule-custom-time-toggle').is(':checked')) return; // skip if custom time active
    var date = $('#reschedule-date').val();
    var clinicId = $('#reschedule-clinic').val();
    var doctorId = $('#reschedule-doctor').val();
    if (!date || !clinicId) {
        $('#reschedule-time').empty().append('<option value="">-- Select date & clinic first --</option>');
        return;
    }
    $.get(wbRoute('appointments.available-slots', '/appointments/available-slots'), {
        date: date, clinic_id: clinicId, doctor_id: doctorId
    }, function(response) {
        var $sel = $('#reschedule-time');
        $sel.empty().append('<option value="">-- Select Time --</option>');
        if (response.success && response.slots && response.slots.length> 0) {
            var hasSlots = false;
            response.slots.forEach(function(slot) {
                if (slot.available) {
                    hasSlots = true;
                    $sel.append('<option value="' + slot.time + '">' + slot.time + '</option>');
                }
            });
            if (!hasSlots) {
                $sel.append('<option value="" disabled>No available slots — use custom time</option>');
            }
        } else {
            $sel.append('<option value="" disabled>No slots configured — use custom time</option>');
        }
    });
});

// Custom time toggle
$(document).on('change', '#reschedule-custom-time-toggle', function() {
    var isCustom = $(this).is(':checked');
    if (isCustom) {
        $('#reschedule-time').addClass('d-none');
        $('#reschedule-custom-time-input').removeClass('d-none');
    } else {
        $('#reschedule-custom-time-input').addClass('d-none').val('');
        $('#reschedule-time').removeClass('d-none');
        // Re-fetch slots if date set
        var date = $('#reschedule-date').val();
        if (date) $('#reschedule-date').trigger('change');
    }
});

$(document).on('submit', '#reschedule-form', function(e) {
    e.preventDefault();
    var apptId = $('#reschedule-appt-id').val();
    var $submitBtn = $(this).find('button[type="submit"]');
    $submitBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Rescheduling...');

    var isCustomTime = $('#reschedule-custom-time-toggle').is(':checked');
    var startTime = isCustomTime ? $('#reschedule-custom-time-input').val() : $('#reschedule-time').val();
    var endTime = isCustomTime ? '' : ($('#reschedule-time option:selected').data('end') || '');

    if (!startTime) {
        $submitBtn.prop('disabled', false).html('<i class="mdi mdi-calendar-edit"></i> Reschedule');
        toastr.warning('Please select or enter a time.');
        return;
    }

    $.ajax({
        url: wbRoute('appointments.reschedule', '/appointments/reschedule').replace('__AID__', apptId),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
            appointment_date: $('#reschedule-date').val(),
            start_time: startTime,
            end_time: endTime,
            custom_time: isCustomTime ? 1 : 0,
            doctor_id: $('#reschedule-doctor').val(),
            reason: $('#reschedule-reason').val()
        },
        success: function(res) {
            if (res.success) {
                toastr.success(res.message);
                $('#rescheduleAppointmentModal').modal('hide');
                refreshAppointmentViews();
                if (typeof patientAppointmentsDataTable !== 'undefined' && patientAppointmentsDataTable) {
                    patientAppointmentsDataTable.ajax.reload(null, false);
                }
            } else {
                toastr.error(res.message);
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Reschedule failed');
        },
        complete: function() {
            $submitBtn.prop('disabled', false).html('<i class="mdi mdi-calendar-edit"></i> Reschedule');
        }
    });
});

// ─── Reassign Doctor ───────────────────────────────────────────────
$(document).on('click', '.btn-reassign-appointment', function() {
    var $btn = $(this);
    var apptId = $btn.data('id');
    var clinicId = $btn.data('clinic');
    var currentDoctorId = $btn.data('doctor');
    var patientName = $btn.data('patient');

    $('#reassign-appt-id').val(apptId);
    $('#reassign-patient-name').text(patientName);
    $('#reassign-current-doctor').text('Loading...');
    $('#reassign-doctor').empty().append('<option value="">Loading doctors...</option>');
    $('#reassign-reason').val('');

    $('#reassignDoctorModal').modal('show');

    // Load available doctors for this appointment
    $.get(wbRoute('appointments.available-doctors', '/appointments/available-doctors').replace('__AID__', apptId), function(res) {
        var $sel = $('#reassign-doctor');
        $sel.empty().append('<option value="">-- Select Doctor --</option>');
        if (res.success && res.doctors) {
            var currentName = '';
            res.doctors.forEach(function(doc) {
                var isCurrent = doc.id == currentDoctorId;
                if (isCurrent) currentName = doc.name;
                $sel.append('<option value="' + doc.id + '"' + (isCurrent ? ' disabled' : '') + '>' + doc.name + (isCurrent ? ' (current)' : '') + '</option>');
            });
            $('#reassign-current-doctor').text(currentName || 'Unknown');
        }
    }).fail(function() {
        $('#reassign-doctor').empty().append('<option value="">Error loading doctors</option>');
    });
});

$(document).on('submit', '#reassign-form', function(e) {
    e.preventDefault();
    var apptId = $('#reassign-appt-id').val();
    var $submitBtn = $(this).find('button[type="submit"]');
    $submitBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Reassigning...');

    $.ajax({
        url: wbRoute('appointments.reassign', '/appointments/reassign').replace('__AID__', apptId),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
            doctor_id: $('#reassign-doctor').val(),
            reason: $('#reassign-reason').val()
        },
        success: function(res) {
            if (res.success) {
                toastr.success(res.message);
                $('#reassignDoctorModal').modal('hide');
                refreshAppointmentViews();
                if (typeof patientAppointmentsDataTable !== 'undefined' && patientAppointmentsDataTable) {
                    patientAppointmentsDataTable.ajax.reload(null, false);
                }
            } else {
                toastr.error(res.message);
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Reassignment failed');
        },
        complete: function() {
            $submitBtn.prop('disabled', false).html('<i class="mdi mdi-account-switch"></i> Reassign');
        }
    });
});

// ─── Appointment Chain / History ───────────────────────────────────
$(document).on('click', '.btn-view-chain', function() {
    var apptId = $(this).data('id');
    $('#chain-body').html('<div class="text-center py-4"><i class="mdi mdi-loading mdi-spin mdi-36px"></i></div>');
    $('#appointmentChainModal').modal('show');

    $.get(wbRoute('appointments.chain', '/appointments/chain').replace('__AID__', apptId), function(res) {
        if (res.success && res.chain) {
            var html = '<div class="appointment-chain-timeline">';
            res.chain.forEach(function(item, idx) {
                var isActive = item.id == apptId;
                var statusBadge = item.status_badge || ('<span class="badge bg-secondary">' + (item.status_label || item.status) + '</span>');
                html += '<div class="chain-item' + (isActive ? ' chain-item-active' : '') + '">';
                html += '<div class="chain-marker"><span class="chain-dot' + (isActive ? ' active' : '') + '">' + (idx + 1) + '</span></div>';
                html += '<div class="chain-content">';
                html += '<div class="d-flex justify-content-between align-items-center mb-1">';
                html += '<strong>' + (item.appointment_date || '') + '</strong> ' + statusBadge;
                html += '</div>';
                html += '<div class="text-muted small">';
                html += (item.start_time || '') + ' - ' + (item.end_time || '') + ' &bull; Dr. ' + (item.doctor_name || 'Any');
                html += '</div>';
                if (item.appointment_type === 'follow_up') html += '<span class="badge bg-info badge-sm">Follow-Up</span> ';
                if (item.rescheduled_from_id) html += '<span class="badge bg-warning badge-sm">Rescheduled</span> ';
                if (item.reassignment_reason) html += '<span class="badge bg-purple badge-sm">Reassigned</span> ';
                if (item.cancellation_reason) html += '<div class="text-muted small mt-1"><em>' + item.cancellation_reason + '</em></div>';
                html += '</div></div>';
            });
            html += '</div>';
            $('#chain-body').html(html);
        } else {
            $('#chain-body').html('<p class="text-muted">No chain data available.</p>');
        }
    }).fail(function() {
        $('#chain-body').html('<p class="text-danger">Failed to load appointment history.</p>');
    });
});

// ─── Patient Appointments Tab DataTable ────────────────────────────
var patientAppointmentsDataTable = null;

function loadPatientAppointments(patientId) {
    if (!patientId) return;

    if (patientAppointmentsDataTable) {
        patientAppointmentsDataTable.destroy();
    }

    patientAppointmentsDataTable = $('#patient-appointments-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: wbRoute('appointments.list', '/appointments/list'),
            data: function(d) {
                d.patient_id = patientId;
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'appointment_date', name: 'appointment_date' },
            { data: 'time_slot', name: 'start_time' },
            { data: 'clinic_name', name: 'clinic_name' },
            { data: 'doctor_name', name: 'doctor_name' },
            { data: 'type_badge', name: 'appointment_type', orderable: false },
            { data: 'status_badge', name: 'status', orderable: false },
            { data: 'delivery_info', name: 'delivery_info', orderable: false, searchable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[1, 'desc']],
        pageLength: 10,
        language: {
            emptyTable: 'No appointments found for this patient',
            processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...'
        }
    });
}

